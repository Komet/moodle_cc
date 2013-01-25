<?php
// This file is part of the CampusConnect plugin for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Handle 'course' creation requests from the ECS server
 *
 * @package   local_campusconnect
 * @copyright 2012 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/local/campusconnect/notify.php');

/**
 * Exception thrown by the campusconnect_courselink object
 */
class campusconnect_course_exception extends moodle_exception {
    /**
     * Throw a new exception
     * @param string $msg
     */
    function __construct($msg) {
        parent::__construct('error', 'local_campusconnect', '', $msg);
    }
}

/**
 * Looks after the creation / update of courses based on requests from the CMS (via the ECS)
 */
class campusconnect_course {

    /**
     * Used by the ECS event processing to create new courses
     * @param int $resourceid - the ID on the ECS server
     * @param campusconnect_ecssettings $ecssettings - the ECS being connected to
     * @param object $course - the resource data from ECS
     * @param campusconnect_details $transferdetails - the metadata for the resource on the ECS
     * @param campusconnect_participantsettings $cms - passed in when doing full refresh (to save a DB query)
     * @return bool true if successful
     */
    public static function create($resourceid, campusconnect_ecssettings $ecssettings, $course,
                                  campusconnect_details $transferdetails, campusconnect_participantsettings $cms = null) {
        if (is_null($cms)) {
            $cms = campusconnect_participantsettings::get_cms_participant();
        }
        $mid = $transferdetails->get_sender_mid();
        $ecsid = $ecssettings->get_id();
        if (!$cms || $cms->get_mid() != $mid || $cms->get_ecs_id() != $ecsid) {
            throw new campusconnect_course_exception("Received create course event from non-CMS participant");
        }

        $coursedata = self::map_course_settings($course, $ecssettings);
        if (self::get_by_resourceid($resourceid, $ecssettings->get_id())) {
            debugging("Cannot create a course from resource $resourceid - it already exists.");
            return true; // The event should be removed from the queue, so we don't get this error again.
        }

        /** @var $categories campusconnect_course_category[] */
        $categories = self::get_categories($course, $ecssettings);
        if (empty($categories)) {
            return false; // The directory has not yet been mapped onto a category => cannot yet create the course.
        }

        list($pgroups, $pgroupmode) = campusconnect_parallelgroups::get_parallel_groups($course);
        if (count($pgroups) < 1) {
            $pgroups[] = array(); // Make sure there is at least one course to be created.
        }

        $pgclass = new campusconnect_parallelgroups($ecssettings, $resourceid);
        foreach ($pgroups as $pgcourse) {
            self::create_new_course($ecssettings, $resourceid, $course, $mid, $coursedata, $pgclass, $pgroupmode,
                                    $pgcourse, $categories);
        }

        return true;
    }

    /**
     * Create a new course to match a given parellel group and set of categories
     * @param campusconnect_ecssettings $ecssettings
     * @param int $resourceid the ID of the resource on the ECS
     * @param stdClass $course the details of the course from the ECS
     * @param int $mid the member ID that the course came from
     * @param stdClass $coursedata the course data after mapping onto Moodle course data
     * @param campusconnect_parallelgroups $pgclass
     * @param int $pgroupmode the parallel groups scenario
     * @param stdClass[] $pgcourse the parallel groups to create in this course
     * @param campusconnect_course_category[] $categories the categories in which to create this course
     */
    protected static function create_new_course(campusconnect_ecssettings $ecssettings, $resourceid, $course, $mid,
                                                $coursedata, campusconnect_parallelgroups $pgclass,
                                                $pgroupmode, $pgcourse, $categories) {
        global $DB;

        $internallink = 0;

        $coursedata = clone $coursedata;
        self::set_course_defaults($coursedata);
        $coursedata->fullname = $pgclass->update_course_name($coursedata->fullname, $pgroupmode, $pgcourse);

        foreach ($categories as $category) {
            $coursedata->category = $category->get_categoryid();
            if ($ecssettings->get_id() > 0) {
                $baseshortname = $coursedata->shortname;
                $num = 1;
                while ($DB->record_exists('course', array('shortname' => $coursedata->shortname))) {
                    $num++;
                    $coursedata->shortname = "{$baseshortname}_{$num}";
                }
                $newcourse = create_course($coursedata);
            } else {
                // Nasty hack for unit testing - 'create_course' is too complex to
                // be practical to mock up the database responses
                global $DB;
                $newcourse = $coursedata;
                /** @noinspection PhpUndefinedMethodInspection */
                $newcourse->id = $DB->mock_create_course($coursedata);
            }

            $ins = new stdClass();
            $ins->courseid = $newcourse->id;
            $ins->resourceid = $resourceid;
            $ins->cmsid = isset($course->basicData->id) ? $course->basicData->id : '';
            $ins->ecsid = $ecssettings->get_id();
            $ins->mid = $mid;
            $ins->internallink = $internallink;
            $ins->sortorder = $category->get_order();
            $ins->directoryid = $category->get_directoryid();

            $ins->id = $DB->insert_record('local_campusconnect_crs', $ins);

            if (!$internallink) {
                $internallink = $newcourse->id; // Point all subsequent courses at the first one (the 'real' course).

                // Let the ECS server know about the created link.
                $courseurl = new campusconnect_course_url($ins->id);
                $courseurl->add();

                // Create any required groups for this course
                $pgclass->update_parallel_groups($newcourse, $pgroupmode, $pgcourse);

                // Process any existing enrolment requests for this course
                campusconnect_membership::assign_course_users($newcourse, $ins->cmsid);

                campusconnect_notification::queue_message($ecssettings->get_id(),
                                                          campusconnect_notification::MESSAGE_COURSE,
                                                          campusconnect_notification::TYPE_CREATE,
                                                          $newcourse->id);
            }
        }
    }

    /**
     * Used by the ECS event processing to update courses
     * @param int $resourceid - the ID on the ECS server
     * @param campusconnect_ecssettings $ecssettings - the ECS being connected to
     * @param object $course - the resource data from ECS
     * @param campusconnect_details $transferdetails - the metadata for the resource on the ECS
     * @param campusconnect_participantsettings $cms - the cms (already loaded if doing full refresh)
     * @return bool true if successful
     */
    public static function update($resourceid, campusconnect_ecssettings $ecssettings, $course,
                                  campusconnect_details $transferdetails, campusconnect_participantsettings $cms = null) {
        global $DB;

        if (is_null($cms)) {
            $cms = campusconnect_participantsettings::get_cms_participant();
        }
        $mid = $transferdetails->get_sender_mid();
        $ecsid = $ecssettings->get_id();
        if (!$cms || $cms->get_mid() != $mid || $cms->get_ecs_id() != $ecsid) {
            throw new campusconnect_course_exception("Received update course event from non-CMS participant");
        }

        $currcourses = self::get_by_resourceid($resourceid, $ecsid);
        if (empty($currcourses)) {
            return self::create($resourceid, $ecssettings, $course, $transferdetails);
            //throw new campusconnect_course_exception("Cannot update course resource $resourceid - it doesn't exist");
        }

        $currcourse = reset($currcourses);
        if ($currcourse->mid != $mid) {
            throw new campusconnect_course_exception("Participant $mid attempted to update resource created by participant {$currcourse->mid}");
        }

        $categories = self::get_categories($course, $ecssettings);
        if (empty($categories)) {
            return false; // The directory has not yet been mapped onto a category => cannot yet create the course.
        }

        list($pgroups, $pgroupmode) = campusconnect_parallelgroups::get_parallel_groups($course);
        if (count($pgroups) < 1) {
            $pgroups[] = array(); // Make sure there is at least one course to be created.
        }
        $pgclass = new campusconnect_parallelgroups($ecssettings, $resourceid);
        list ($pgmatched, $pgnotmatched) = $pgclass->match_parallel_groups_to_courses($pgroups, $pgroupmode, $currcourse->courseid);

        // Compare the existing allocations to the new allocations.
        list($csql, $params) = $DB->get_in_or_equal(array_keys($currcourses), SQL_PARAMS_NAMED);
        $existingcategoryids = $DB->get_records_sql_menu("SELECT ccc.id, c.category
                                                            FROM {local_campusconnect_crs} ccc
                                                            JOIN {course} c ON ccc.courseid = c.id
                                                           WHERE ccc.id $csql
                                                           ORDER BY c.category", $params);
        $unchangedcategories = array();
        /** @var $newcategories campusconnect_course_category[] */
        $newcategories = array();
        foreach ($categories as $category) {
            $crsid = array_search($category->get_categoryid(), $existingcategoryids);
            if ($crsid !== false) {
                do {
                    // Match up all parallel courses in the same category.
                    $unchangedcategories[$crsid] = $category;
                    unset($existingcategoryids[$crsid]); // Any left in this array will be deleted.
                } while (($crsid = array_search($category->get_categoryid(), $existingcategoryids)) !== false);
            } else {
                $newcategories[] = $category;
            }
        }
        self::remove_allocations($currcourses, $existingcategoryids, $unchangedcategories, $newcategories, $ecssettings->get_id() < 0);

        $coursedata = self::map_course_settings($course, $ecssettings);

        // Check for orphaned crs records.
        foreach ($currcourses as $key => $currcourse) {
            if (!isset($unchangedcategories[$currcourse->id])) {
                if ($currcourse->internallink != 0) {
                    // Internal link course has been deleted - can safely delete the crs record.
                    $DB->delete_records('local_campusconnect_crs', array('id' => $currcourse->id));
                    unset($currcourses[$key]);
                } else {
                    // The 'real' course has been deleted, need to recreate it.
                    // Create in the first category, which is where the real course should always be located.
                    $oldcourseid = $currcourse->courseid;
                    $category = reset($categories);
                    $coursedetails = clone $coursedata;
                    $coursedetails->category = $category->get_categoryid();
                    $baseshortname = $coursedetails->shortname;
                    $num = 1;
                    while ($DB->record_exists('course', array('shortname' => $coursedetails->shortname))) {
                        $num++;
                        $coursedetails->shortname = "{$baseshortname}_{$num}";
                    }
                    if (isset($pgmatched[$currcourse->courseid])) {
                        $coursedetails->fullname = $pgclass->update_course_name($coursedetails->fullname,
                                                                                $pgroupmode, $pgmatched[$currcourse->courseid]);
                    }
                    $newcourse = create_course($coursedetails);
                    unset($coursedetails);

                    // Update the main crs record for this entry
                    $currcourse->courseid = $newcourse->id;
                    $DB->set_field('local_campusconnect_crs', 'courseid', $newcourse->id, array('id' => $currcourse->id));

                    // Update any courselinks to point at this course.
                    foreach ($currcourses as $crs) {
                        if ($crs->internallink == $oldcourseid) {
                            $crs->internallink = $newcourse->id;
                            $DB->set_field('local_campusconnect_crs', 'internallink', $newcourse->id, array('id' => $crs->id));
                        }
                    }

                    // Update any groups for this course
                    if (isset($pgmatched[$oldcourseid])) {
                        $pgclass->update_parallel_groups($newcourse, $pgroupmode, $pgmatched[$oldcourseid]);
                    }
                }
            }
        }

        // Update all the existing crs records.
        foreach ($currcourses as $currcourse) {
            if (!$DB->record_exists('course', array('id' => $currcourse->courseid))) {
                throw new coding_exception("crs record {$currcourse->id} references non-existent course {$currcourse->courseid}");
            } else {
                // Course still exists - update it.
                $coursedetails = clone $coursedata;
                $coursedetails->id = $currcourse->courseid;
                if (isset($pgmatched[$coursedetails->id])) {
                    $coursedetails->fullname = $pgclass->update_course_name($coursedetails->fullname,
                                                                            $pgroupmode, $pgmatched[$coursedetails->id]);
                }
                if ($ecssettings->get_id() > 0) {
                    // Nasty hack for unit testing - 'update_course' is too complex to
                    // be practical to mock up the database responses
                    update_course($coursedetails);
                } else {
                    global $DB;
                    /** @noinspection PhpUndefinedMethodInspection */
                    $DB->mock_update_course($coursedetails);
                }

                // The cms course id has changed (not sure if this should ever happen, but handle it anyway)
                if (isset($course->basicData->id) && $course->basicData->id != $currcourse->cmsid) {
                    $upd = new stdClass();
                    $upd->id = $currcourse->id;
                    $upd->cmsid = $course->basicData->id;
                    $DB->update_record('local_campusconnect_crs', $upd);
                }

                if ($currcourse->internallink == 0) {
                    // Let the ECS server know about the updated link.
                    $courseurl = new campusconnect_course_url($currcourse->id);
                    $courseurl->update();
                    campusconnect_notification::queue_message($ecssettings->get_id(),
                                                              campusconnect_notification::MESSAGE_COURSE,
                                                              campusconnect_notification::TYPE_UPDATE,
                                                              $currcourse->courseid);
                }

                // Check the groups for this course
                if (isset($pgmatched[$coursedetails->id])) {
                    $pgclass->update_parallel_groups($coursedetails, $pgroupmode, $pgmatched[$coursedetails->id]);
                }
            }
        }

        // Add new crs records for any new categories that also need links in them.
        if (!empty($newcategories)) {
            $currcourse = reset($currcourses);
            $internallink = ($currcourse->internallink == 0) ? $currcourse->courseid : $currcourse->internallink;
            foreach ($newcategories as $newcategory) {
                $coursedata->category = $newcategory->get_categoryid();
                if ($ecssettings->get_id() > 0) {
                    $baseshortname = $coursedata->shortname;
                    $num = 1;
                    while ($DB->record_exists('course', array('shortname' => $coursedata->shortname))) {
                        $num++;
                        $coursedata->shortname = "{$baseshortname}_{$num}";
                    }
                    $newcourse = create_course($coursedata);
                } else {
                    // Nasty hack for unit testing - 'create_course' is too complex to
                    // be practical to mock up the database responses
                    global $DB;
                    $newcourse = $coursedata;
                    /** @noinspection PhpUndefinedMethodInspection */
                    $newcourse->id = $DB->mock_create_course($coursedata);
                }

                // Create a new crs record to redirect to the internallink course
                $ins = new stdClass();
                $ins->courseid = $newcourse->id;
                $ins->resourceid = $resourceid;
                $ins->cmsid = isset($course->basicData->id) ? $course->basicData->id : '';
                $ins->ecsid = $ecsid;
                $ins->mid = $mid;
                $ins->internallink = $internallink;
                $ins->sortorder = $newcategory->get_order();
                $ins->directoryid = $newcategory->get_directoryid();
                $ins->id = $DB->insert_record('local_campusconnect_crs', $ins);
                $currcourses[] = $ins;
            }
        }

        // Check the 'real' course is in the first category in the list, if not, swap the course with one of the links.
        $firstcategory = reset($categories);
        $firstcategoryid = $firstcategory->get_categoryid();
        $realcourseids = array();
        foreach ($currcourses as $currcourse) {
            $realid = ($currcourse->internallink == 0) ? $currcourse->courseid : $currcourse->internallink;
            $realcourseids[$realid] = $realid;
        }
        $realcategories = $DB->get_records_list('course', 'id', $realcourseids, '', 'id, category');
        foreach ($realcategories as $realcategory) {
            if ($realcategory->category != $firstcategoryid) {
                // The 'real' course is not in the first category - find the course that is in that category and swap them.
                $params = array('resourceid' => $resourceid, 'ecsid' => $ecsid, 'firstcategoryid' => $firstcategoryid);
                $swapcourseid = $DB->get_field_sql('SELECT c.id
                                                  FROM {course} c
                                                  JOIN {local_campusconnect_crs} ccc ON c.id = ccc.courseid
                                                 WHERE ccc.resourceid = :resourceid AND ccc.ecsid = :ecsid
                                                   AND c.category = :firstcategoryid', $params, MUST_EXIST);

                $realcourse = (object)array('id' => $realcategory->id, 'category' => $firstcategoryid);
                $swapcourse = (object)array('id' => $swapcourseid, 'category' => $realcategory->category);
                $DB->update_record('course', $realcourse);
                $DB->update_record('course', $swapcourse);

                // Swap the directoryids & sortorder for these courses
                $crs1 = $DB->get_record('local_campusconnect_crs', array('courseid' => $realcourse->id),
                                        'id, sortorder, directoryid', MUST_EXIST);
                $crs2 = $DB->get_record('local_campusconnect_crs', array('courseid' => $swapcourseid),
                                        'id, sortorder, directoryid', MUST_EXIST);
                $tempid = $crs1->id;
                $crs1->id = $crs2->id;
                $crs2->id = $tempid;
                $DB->update_record('local_campusconnect_crs', $crs1);
                $DB->update_record('local_campusconnect_crs', $crs2);
            }
        }

        // Create new courses for parallel groups that didn't exist before.
        foreach ($pgnotmatched as $pgcourse) {
            self::create_new_course($ecssettings, $resourceid, $course, $mid, $coursedata, $pgclass, $pgroupmode, $pgcourse, $categories);
        }

        return true;
    }

    /**
     * Used by the ECS event processing to delete courses
     * @param int $resourceid - the ID on the ECS server
     * @param campusconnect_ecssettings $ecssettings - the ECS being connected to
     * @return bool true if successful
     */
    public static function delete($resourceid, campusconnect_ecssettings $ecssettings) {
        global $DB;

        $currcourses = self::get_by_resourceid($resourceid, $ecssettings->get_id());
        foreach ($currcourses as $currcourse) {
            if ($currcourse->internallink == 0) {
                // Do not actually delete the 'real' course
                campusconnect_notification::queue_message($ecssettings->get_id(),
                                                          campusconnect_notification::MESSAGE_COURSE,
                                                          campusconnect_notification::TYPE_DELETE,
                                                          $currcourse->courseid);

                // Leave the course_url code to delete the record once it has informed the ECS
                $courseurl = new campusconnect_course_url($currcourse->id);
                $courseurl->delete();
            } else {
                // Delete the internal links
                $DB->delete_records('local_campusconnect_crs', array('id' => $currcourse->id));
                delete_course($currcourse->courseid, false);
            }
        }

        return true;
    }

    /**
     * Update all courses from the ECS
     * @param campusconnect_ecssettings $ecssettings
     * @return object containing: ->created - array of created resource ids
     *                            ->updated - array of updated resource ids
     *                            ->deleted - array of deleted resource ids
     */
    public static function refresh_from_ecs(campusconnect_ecssettings $ecssettings) {
        global $DB;

        $ret = (object)array('created' => array(), 'updated' => array(), 'deleted' => array());

        // Get the CMS participant.
        /** @var $cms campusconnect_participantsettings */
        if (!$cms = campusconnect_participantsettings::get_cms_participant()) {
            return $ret;
        }
        if ($cms->get_ecs_id() != $ecssettings->get_id()) {
            // Not refreshing the ECS that the CMS is attached to
            return $ret;
        }

        // Get full list of courselinks from this ECS.
        $courses = $DB->get_records('local_campusconnect_crs', array('ecsid' => $cms->get_ecs_id(), 'mid' => $cms->get_mid()),
                                    '', 'DISTINCT resourceid');

        // Get full list of courselink resources shared with us.
        $connect = new campusconnect_connect($ecssettings);
        $servercourses = $connect->get_resource_list(campusconnect_event::RES_COURSE);

        // Go through all the links from the server and compare to what we have locally.
        foreach ($servercourses->get_ids() as $resourceid) {
            $details = $connect->get_resource($resourceid, campusconnect_event::RES_COURSE, false);
            $transferdetails = $connect->get_resource($resourceid, campusconnect_event::RES_COURSE, true);

            // Check if we already have this locally.
            if (isset($courses[$resourceid])) {
                self::update($resourceid, $ecssettings, $details, $transferdetails, $cms);
                $ret->updated[] = $resourceid;
                unset($courses[$resourceid]); // So we can delete anything left in the list at the end.
            } else {
                // We don't already have this course
                if (empty($details)) {
                    continue; // This probably shouldn't occur, but we're just going to ignore it.
                }

                self::create($resourceid, $ecssettings, $details, $transferdetails, $cms);
                $ret->created[] = $resourceid;
            }
        }

        // Delete any courses still in our local list (they have either been deleted remotely, or they are from
        // participants we no longer import course links from).
        foreach ($courses as $course) {
            self::delete($course->resourceid, $ecssettings);
            $ret->deleted[] = $course->resourceid;
        }

        return $ret;
    }

    /**
     * Sort all directories based on their allocation sortorder
     * @param int $rootdir the CampusConnect directory to find courses within
     * @return bool true if there were changes to the course table (so fix_course_sortorder is needed)
     */
    public static function sort_courses($rootdir) {
        global $DB;

        // Find all the allocated courses within this root directory with a sortorder, ordered by subdirectory + sortorder
        $sql = "SELECT crs.*, c.sortorder AS coursesortorder
                  FROM {local_campusconnect_crs} crs
                  JOIN {local_campusconnect_dir} dir ON crs.directoryid = dir.directoryid
                  JOIN {course} c ON crs.courseid = c.id
                 WHERE crs.sortorder <> 0 AND dir.rootid = ?
              ORDER BY crs.directoryid, crs.sortorder, c.sortorder"; // Use course sortorder, if CMS sort order is the same
        $crs = $DB->get_records_sql($sql, array($rootdir));

        // Check that the course sortorder increases as we go through the sorted list within each subdirectory
        $changes = false;
        $lastorder = -1;
        $lastdir = -1;
        foreach ($crs as $cr) {
            if ($cr->directoryid != $lastdir) {
                // Onto the next subdirectory.
                $lastdir = $cr->directoryid;
                $lastorder = -1;
            } else {
                if ($cr->coursesortorder <= $lastorder) {
                    // Found a course with an out-of-sequence sortorder => fix it.
                    $DB->set_field('course', 'sortorder', $lastorder + 1, array('id' => $cr->courseid));
                    $changes = true;
                    $lastorder = $lastorder + 1;
                } else {
                    $lastorder = $cr->coursesortorder;
                }
            }
        }

        return $changes;
    }

    /**
     * Get the course db record from its resourceid and ecsid
     * @param int $resourceid
     * @param int $ecsid
     * @return mixed false | object[] - may be multiple if the same course is mapped into multiple locations
     */
    public static function get_by_resourceid($resourceid, $ecsid) {
        global $DB;
        $params = array('resourceid' => $resourceid, 'ecsid' => $ecsid);
        return $DB->get_records('local_campusconnect_crs', $params);
    }

    /**
     * Returns the redirect URL if this is an internal link to the real course.
     * @param int $courseid
     * @return mixed moodle_url | false - the url to redirect to
     */
    public static function check_redirect($courseid) {
        global $DB;
        if (! $course = $DB->get_record('local_campusconnect_crs', array('courseid' => $courseid))) {
            return false;
        }
        if ($course->internallink == 0) {
            return false; // This is the 'real' course - no redirect needed
        }
        return new moodle_url('/course/view.php', array('id' => $course->internallink));
    }

    /**
     * Given a list of courseids from the CMS, return the Moodle course ids that these map onto
     * @param int[] $cmscourseids
     * @return array[] [ CMS courseid => Moodle courseid[], All moodle courseid[] ]
     */
    public static function get_courseids_from_cmscourseids(array $cmscourseids) {
        global $DB;

        if (empty($cmscourseids)) {
            return array();
        }

        list($csql, $params) = $DB->get_in_or_equal($cmscourseids);

        $recs = $DB->get_records_select('local_campusconnect_crs', "cmsid  $csql AND internallink = 0", $params,
                                             'id', 'id, cmsid, courseid');
        $mapping = array();
        $courseids = array();
        foreach ($recs as $rec) {
            if (!isset($mapping[$rec->cmsid])) {
                $mapping[$rec->cmsid] = array();
            }
            $mapping[$rec->cmsid][] = $rec->courseid;
            $courseids[] = $rec->courseid;
        }

        return array($mapping, $courseids);
    }

    /**
     * Given a list of Moodle courseids, return the CMS course ids that these map onto
     * @param int[] $courseids
     * @return int[] mapping CMS courseid => Moodle courseid
     */
    public static function get_cmscourseids_from_courseids(array $courseids) {
        global $DB;

        if (empty($courseids)) {
            return array();
        }

        list($csql, $params) = $DB->get_in_or_equal($courseids);
        return $DB->get_records_select_menu('local_campusconnect_crs', "courseid $csql AND internallink = 0", $params,
                                            '', 'courseid, cmsid');
    }

    /**
     * Generate the Moodle course metadata, based on the metadata details from the ECS server
     * @param object $course
     * @param campusconnect_ecssettings $ecssettings
     * @return object
     */
    protected static function map_course_settings($course, campusconnect_ecssettings $ecssettings) {
        $metadata = new campusconnect_metadata($ecssettings, false);
        $coursedata = $metadata->map_remote_to_course($course);
        $coursedata->summaryformat = FORMAT_HTML;

        return $coursedata;
    }

    /**
     * Updates the course object to include suitable defaults, where no alternatives are specified
     * @param stdClass $course data object to be updated
     */
    protected static function set_course_defaults(stdClass &$course) {
        $config = get_config('moodlecourse');

        $params = array('format', 'numsections', 'hiddensections', 'newsitems', 'showgrades', 'showreports', 'maxbytes',
                        'groupmode', 'groupmodeforce', 'visible', 'lang', 'enablecompletion', 'completionstartonenrol');

        foreach ($params as $param) {
            if (!isset($course->$param) && isset($config->$param)) {
                $course->$param = $config->$param;
            }
        }
        if (!completion_info::is_enabled_for_site()) {
            $course->enablecompletion = 0;
            $course->completionstartonenrol = 0;
        }
    }

    /**
     * Use the course filtering rules or the 'allocation' section of the course resource to determine the category ID
     * to create the course in.
     * The category will be created, if required.
     * @param stdClass $course
     * @param campusconnect_ecssettings $ecssettings
     * @return campusconnect_course_category[] empty if the directory is not yet mapped, so the course cannot be created
     */
    protected static function get_categories(stdClass $course, campusconnect_ecssettings $ecssettings) {
        global $CFG;
        require_once($CFG->dirroot.'/local/campusconnect/filtering.php');

        // Use course filtering rules, if enabled
        if (campusconnect_filtering::enabled()) {
            $catids = campusconnect_filtering::get_categories($course, $ecssettings);
            if (empty($catids)) {
                throw new campusconnect_course_exception(get_string('filternocategories', 'local_campusconnect'));
            }
            $ret = array();
            foreach ($catids as $catid) {
                $ret[] = new campusconnect_course_category($catid);
            }
            return $ret;
        }

        // No course filtering rules - use the 'allocations' specified by the CMS.
        if (empty($course->allocations)) {
            debugging("Warning - course request without 'allocations' details - using default import category");
            return $ecssettings->get_import_category();
        }

        $ret = array();
        foreach ($course->allocations as $allocation) {
            if ($catid = campusconnect_directorytree::get_category_for_course($allocation->parentID)) {
                $order = isset($allocation->order) ? $allocation->order : 0;
                $ret[] = new campusconnect_course_category($catid, $order, $allocation->parentID);
            }
        }

        return $ret;
    }

    /**
     * Internal function that deletes internal course links from categories that no longer contain a link to that course
     * Where possible, courses are moved into new categories, instead of deleting them. 'Real' courses are always retained
     * (and moved to new categories, if required).
     * @param object[] $currcourses
     * @param int[] $removecategoryids mapping local_campusconnect_crs.id => categoryid
     * @param campusconnect_course_category[] $unchangedcategories
     * @param campusconnect_course_category[] $newcategories
     * @param bool $unittest set if doing unit testing
     * @throws coding_exception
     */
    protected static function remove_allocations(&$currcourses, &$removecategoryids, &$unchangedcategories, &$newcategories, $unittest = false) {
        global $DB;

        if (empty($removecategoryids)) {
            return; // Nothing to change
        }

        if (empty($newcategories) && empty($unchangedcategories)) {
            throw new coding_exception("campusconnect_course::remove_allocations - unchangedcategories and newcategories should never both be empty");
        }

        $firstnewcategory = false;
        // Make sure the 'real' course continues to exist - move it to a different category, if no longer mapped to its current location.
        foreach ($removecategoryids as $rcrsid => $rcatid) {
            $currcourse = $currcourses[$rcrsid];
            if ($currcourse->internallink == 0) { // We are trying to remove the 'real' course - instead move it.
                if (!empty($newcategories) || $firstnewcategory) {
                    // Move it into the newly-mapped category.
                    /** @var $firstnewcategory campusconnect_course_category */
                    if ($firstnewcategory === false) {
                        // Only one 'real course' per parallel course, so map all onto the first 'new category'.
                        $firstnewcategory = array_shift($newcategories);
                    }
                    $DB->set_field('course', 'category', $firstnewcategory->get_categoryid(), array('id' => $currcourse->courseid));
                    // Update the directoryid / sortorder for this course
                    $upd = new stdClass();
                    $upd->id = $currcourse->id;
                    $upd->directoryid = $firstnewcategory->get_directoryid();
                    $upd->sortorder = $firstnewcategory->get_order();
                    $DB->update_record('local_campusconnect_crs', $upd);

                    // Make sure this does not get 'cleaned up' later on.
                    $currcourse->directoryid = $upd->directoryid;
                    $currcourse->sortorder = $upd->sortorder;
                    $unchangedcategories[$currcourse->id] = $firstnewcategory;
                } else {
                    // No newly-mapped categories, so will need to move it into an existing category.
                    $removecrsid = array_shift(array_keys($unchangedcategories));
                    $updatecategory = array_shift($unchangedcategories);

                    if ($currcourses[$removecrsid]->internallink == 0) {
                        throw new coding_exception("Attempting to replace one 'real course' with another - this should not happen");
                    }

                    $DB->set_field('course', 'category', $updatecategory->get_categoryid(), array('id' => $currcourse->courseid));
                    // Update the directoryid / sortorder for this course
                    $upd = new stdClass();
                    $upd->id = $currcourse->id;
                    $upd->directoryid = $updatecategory->get_directoryid();
                    $upd->sortorder = $updatecategory->get_order();
                    $DB->update_record('local_campusconnect_crs', $upd);

                    // The existing course (which was an internal link) is no longer needed - delete it and the crs record.
                    $removecourseid = $currcourses[$removecrsid]->courseid;
                    if (!$unittest) {
                        delete_course($removecourseid, false);
                    } else {
                        /** @noinspection PhpUndefinedMethodInspection */
                        $DB->mock_delete_course($removecourseid);
                    }
                    $DB->delete_records('local_campusconnect_crs', array('id' => $removecrsid));
                    unset($currcourses[$removecrsid]);
                }
                unset($removecategoryids[$rcrsid]);
            }
        }

        // We are trying to remove some internal links and create new internal links - instead, move as many as possible
        // to new categories
        /** @var $currentnewcat campusconnect_course_category */
        $currentnewcat = null;
        $currentcatid = null;
        foreach ($removecategoryids as $rcrsid => $rcatid) {
            $currcourse = $currcourses[$rcrsid];
            if ($currentcatid == $rcatid) {
                // A parallel course in the same category - move to the new category as well.
                $DB->set_field('course', 'category', $currentnewcat->get_categoryid(), array('id' => $currcourse->courseid));
            } else if (!empty($newcategories)) {
                // There is a newly-mapped category to move this internal link into.
                $currentnewcat = array_shift($newcategories);
                $currentcatid = $rcatid;
                $DB->set_field('course', 'category', $currentnewcat->get_categoryid(), array('id' => $currcourse->courseid));
            } else {
                // No newly-mapped category => just remove the course completely.
                if (!$unittest) {
                    delete_course($currcourse->courseid, false);
                } else {
                    /** @noinspection PhpUndefinedMethodInspection */
                    $DB->mock_delete_course($currcourse->courseid);
                }
                $DB->delete_records('local_campusconnect_crs', array('id' => $rcrsid));
                unset($currcourses[$rcrsid]);
            }
        }
    }
}

/**
 * Stores details about the Moodle category in which to create a course
 */
class campusconnect_course_category {
    /** @var int $categorid */
    protected $categoryid;
    /** @var int $order */
    protected $order;
    /** @var int $directory */
    protected $directoryid;

    /**
     * @param int $categoryid
     * @param int $order
     * @param int $directoryid
     */
    public function __construct($categoryid, $order = 0, $directoryid = 0) {
        $this->categoryid = $categoryid;
        $this->order = $order;
        $this->directoryid = $directoryid;
    }

    /**
     * The Moodle category in which to create the course
     * @return int
     */
    public function get_categoryid() {
        return $this->categoryid;
    }

    /**
     * The sort order within the course
     * @return int
     */
    public function get_order() {
        return $this->order;
    }

    /**
     * The Moodle directory in which to create the course
     * @return int
     */
    public function get_directoryid() {
        return $this->directoryid;
    }

}

/**
 * Looks after passing the course URL back to the ECS when a course is created
 */
class campusconnect_course_url {

    /** The course url has been updated on the ECS */
    const STATUS_UPTODATE = 0;
    /** New course url, not yet sent to the ECS */
    const STATUS_CREATED = 1;
    /** Course url has changed, not yet updated the ECS */
    const STATUS_UPDATED = 2;
    /** Course url has been deleted, not yet updated the ECS */
    const STATUS_DELETED = 3;

    /** @var stdClass the crs record from the database */
    protected $crs;

    /**
     * @param int $crsid
     */
    public function __construct($crsid) {
        $this->crs = $this->get_record($crsid);
    }

    /**
     * Notify the ECS that the requested course has been created
     */
    public function add() {
        if ($this->crs->urlstatus != self::STATUS_UPTODATE) {
            throw new campusconnect_course_exception("campusconnect_course_url::add - unexpected status for newly created crs record ($this->crs->id)");
        }
        if ($this->crs->urlresourceid != 0) {
            throw new campusconnect_course_exception("campusconnect_course_url::add - newly created crs record should not have a urlresourceid ($this->crs->id)");
        }
        $this->set_status(self::STATUS_CREATED);
    }

    /**
     * Notify the ECS that the URL of the course has changed
     */
    public function update() {
        if ($this->crs->urlstatus == self::STATUS_CREATED || $this->crs->urlstatus == self::STATUS_UPDATED) {
            return; // Nothing to do - updates already pending.
        }
        if ($this->crs->urlstatus == self::STATUS_DELETED) {
            throw new campusconnect_course_exception("campusconnect_course_url::update - attempting to update crs record ($this->crs->id) that is scheduled for deletion");
        }
        if ($this->crs->urlresourceid) {
            $this->set_status(self::STATUS_UPDATED);
        } else {
            // Catching odd situations in which no URL has been created yet, so switching to CREATE instead of UPDATE
            $this->set_status(self::STATUS_CREATED);
        }
    }

    /**
     * Notify the ECS that this course has been deleted
     */
    public function delete() {
        global $DB;

        if ($this->crs->urlstatus == self::STATUS_CREATED) {
            // Never reached the ECS server - just delete it.
            $DB->delete_records('local_campusconnect_crs', array('id' => $this->crs->id));
            return;
        }
        if ($this->crs->urlresourceid == 0) {
            throw new campusconnect_course_exception("campusconnect_course_url::delete - cannot delete record on ECS with no urlresourceid ($this->crs->id)");
        }

        $this->set_status(self::STATUS_DELETED);
    }

    /**
     * Update the ECS with all the changes to course urls
     * @param campusconnect_connect $connect
     */
    public static function update_ecs(campusconnect_connect $connect) {
        global $DB;

        /** @var $cms campusconnect_participantsettings */
        $cms = campusconnect_participantsettings::get_cms_participant();
        if (!$cms) {
            return;
        }

        if ($connect->get_ecs_id() != $cms->get_ecs_id()) {
            return; // Not updating the ECS that the CMS is on.
        }

        $courseurls = $DB->get_records_select('local_campusconnect_crs', 'ecsid = ? AND urlstatus <> ?',
                                              array($connect->get_ecs_id(), self::STATUS_UPTODATE), 'resourceid');
        // Loop throught he courseurls and combine together those that match a single resourceid
        /** @var stdClass $lasturl */
        $lasturl = null;
        foreach ($courseurls as $key => $courseurl) {
            if ($lasturl && $lasturl->resourceid == $courseurl->resourceid) {
                $lasturl->courseids[] = $courseurl->courseid;
                unset($courseurls[$key]);
            } else {
                $courseurl->courseids = array($courseurl->courseid);
                $lasturl = $courseurl;
            }
        }

        // Update/create all the courseurl resources on the ECS server.
        foreach ($courseurls as $courseurl) {
            if ($courseurl->urlstatus == self::STATUS_DELETED) {
                // Delete from ECS then delete the local record
                $connect->delete_resource($courseurl->urlresourceid, campusconnect_event::RES_COURSE_URL);
                $DB->delete_records('local_campusconnect_crs', array('id' => $courseurl->id));
                continue;
            }

            // Prepare the course_url data object
            $moodleurls = array();
            foreach ($courseurl->courseids as $courseid) {
                $moodleurl = new moodle_url('/course/view.php', array('id' => $courseid));
                $moodleurls[] = $moodleurl->out();
            }
            $data = new stdClass();
            $data->cms_course_id = $courseurl->cmsid.''; // Convert to string if 'NULL'
            $data->ecs_course_url = $connect->get_resource_url($courseurl->resourceid, campusconnect_event::RES_COURSE);
            $data->lms_course_urls = $moodleurls;

            if ($courseurl->urlstatus == self::STATUS_UPDATED) {
                if (!$courseurl->resourceid) {
                    $courseurl->urlstatus = self::STATUS_CREATED;
                    debugging("campusconnect_course_url::update_ecs - cannot update course url ({$courseurl->id}) without resourceid (creating new resource instead)");
                }
            }

            // Update ECS server.
            if ($courseurl->urlstatus == self::STATUS_CREATED) {
                $urlresourceid = $connect->add_resource(campusconnect_event::RES_COURSE_URL, $data, null, $cms->get_mid());
            }
            if ($courseurl->urlstatus == self::STATUS_UPDATED) {
                $connect->update_resource($courseurl->urlresourceid, campusconnect_event::RES_COURSE_URL, $data, null, $cms->get_mid());
            }

            // Update local crs record.
            $upd = new stdClass();
            $upd->id = $courseurl->id;
            $upd->urlstatus = self::STATUS_UPTODATE;
            if (!empty($urlresourceid)) {
                $upd->urlresourceid = $urlresourceid;
            }
            $DB->update_record('local_campusconnect_crs', $upd);
        }
    }

    /**
     * Get list of course URLS from ECS - delete any that should not be there any more, create
     * any that should be there and update all others
     * NOTE: This does not work as the list of courseurls pulled from the ECS does not include our links.
     * @param campusconnect_connect $connect
     * @return object an object containing: ->created = array of resourceids created
     *                            ->updated = array of resourceids updated
     *                            ->deleted = array of resourceids deleted
     */
    public static function refresh_ecs(campusconnect_connect $connect) {
        //global $DB;

        $ret = (object)array('created' => array(), 'updated' => array(), 'deleted' => array());

        return $ret; // This function does not work, as pulling a list of courseurls from the ECS server does not include our links.

/*        $cms = campusconnect_participantsettings::get_cms_participant();
        if ($connect->get_ecs_id() != $cms->get_ecs_id()) {
            return $ret; // Not updating the ECS that the CMS is on.
        }

        // Start by updating ECS with any recent changes.
        self::update_ecs($connect);

        // Get a list of MIDs that this site is known by.
        $mymids = array();
        $knownmids = array();
        $memberships = $connect->get_memberships();
        foreach ($memberships as $membership) {
            foreach ($membership->participants as $participant) {
                if ($participant->itsyou) {
                    $mymids[] = $participant->mid;
                } else {
                    $knownmids[] = $participant->mid;
                }
            }
        }

        // Get a list of the courses we have exported.
        $exportedcourseurls = $DB->get_records_select('local_campusconnect_crs', "ecsid = :ecsid AND urlresourceid <> 0",
                                                      array('ecsid' => $connect->get_ecs_id()), '',
                                                      'urlresourceid, id, courseid, cmsid, resourceid');
        $exportedresourceids = array_keys($exportedcourseurls);

        // Check all the resources on the server against our local list.
        $resources = $connect->get_resource_list(campusconnect_event::RES_COURSE_URL);
        foreach ($resources->get_ids() as $resourceid) {
            $transferdetails = $connect->get_resource($resourceid, campusconnect_event::RES_COURSE_URL, true);
            if (!$transferdetails->sent_by_me($mymids)) {
                continue; // Not one of this VLE's resources.
            }

            if (!array_key_exists($resourceid, $exportedresourceids)) {
                // This VLE does not have that course url - need remove from ECS.
                // (Not that this should ever happen).
                $connect->delete_resource($resourceid, campusconnect_event::RES_COURSE_URL);
                $ret->deleted[] = $resourceid;
            } else {
                // Course url is present in VLE and on ECS - update with latest details.
                $courseid = $exportedcourseurls[$resourceid]->courseid;
                $cmsid = $exportedcourseurls[$resourceid]->cmsid;
                $courseresourceid = $exportedcourseurls[$resourceid]->resourceid;

                $moodleurl = new moodle_url('/course/view.php', array('id' => $courseid));
                $data = new stdClass();
                $data->cms_course_id = $cmsid.''; // Convert to string if 'NULL'
                $data->ecs_course_url = $connect->get_resource_url($courseresourceid, campusconnect_event::RES_COURSE);
                $data->lms_course_url = $moodleurl->out();

                $connect->update_resource($resourceid, campusconnect_event::RES_COURSE_URL, $data, null, $cms->get_mid());

                $exportedcourseurls[$resourceid]->updated = true;
                $ret->updated[] = $resourceid;
            }
        }

        // Check for any course urls that were not found on the ECS.
        foreach ($exportedcourseurls as $exportedcourseurl) {
            if (!empty($exportedcourseurl->updated)) {
                continue; // Already updated.
            }

            // Course not found on ECS - add it (should not happen).
            $courseid = $exportedcourseurl->courseid;
            $cmsid = $exportedcourseurl->cmsid;
            $courseresourceid = $exportedcourseurl->resourceid;

            $moodleurl = new moodle_url('/course/view.php', array('id' => $courseid));
            $data = new stdClass();
            $data->cms_course_id = $cmsid.''; // Convert to string if 'NULL'
            $data->ecs_course_url = $connect->get_resource_url($courseresourceid, campusconnect_event::RES_COURSE);
            $data->lms_course_url = $moodleurl->out();

            $resourceid = $connect->add_resource(campusconnect_event::RES_COURSE_URL, $data, null, $cms->get_mid());

            $upd = new stdClass();
            $upd->id = $exportedcourseurl->id;
            $upd->urlresourceid = $resourceid;
            $DB->update_record('local_campusconnect_crs', $upd);
            $ret->created[] = $resourceid;
        }

        return $ret;*/
    }

    /**
     * Load the crs record and check it is valid
     * @param $crsid
     * @return mixed
     * @throws campusconnect_course_exception
     */
    protected function get_record($crsid) {
        global $DB;

        $crs = $DB->get_record('local_campusconnect_crs', array('id' => $crsid), '*', MUST_EXIST);
        if ($crs->internallink != 0) {
            throw new campusconnect_course_exception("Should not be sending course_url resources for internal course links (crsid = $crsid)");
        }

        return $crs;
    }

    /**
     * Update the status of the course link
     * @param $status
     */
    protected function set_status($status) {
        global $DB;

        $upd = new stdClass();
        $upd->id = $this->crs->id;
        $upd->urlstatus = $status;
        if ($status == self::STATUS_DELETED) {
            $upd->resourceid = 0; // Remove the rest of the details, as they are no longer needed.
            $upd->internallink = 0;
        }
        $DB->update_record('local_campusconnect_crs', $upd);
    }
}

/**
 * Looks after parallel groups - parsing them out of the data from the ECS, matching them up to existing parallel groups
 * and creating the right Moodle groups for them.
 */
class campusconnect_parallelgroups {
    // Parallel group scenarios
    /** No groups created */
    const PGROUP_NONE = 0;
    /** Groups created, but mapped onto one course/group */
    const PGROUP_ONE_COURSE = 1;
    /** Groups mapped onto groups in a single course */
    const PGROUP_SEPARATE_GROUPS = 2;
    /** Groups mapped onto single groups in multiple courses */
    const PGROUP_SEPARATE_COURSES = 3;
    /** One course per lecturer, one course group per course */
    const PGROUP_SEPARATE_LECTURERS = 4;

    /**
     * @var campusconnect_ecssettings
     */
    protected $ecssettings;
    /**
     * @var int
     */
    protected $resourceid;

    /**
     * @param campusconnect_ecssettings $ecssettings
     * @param int $resourceid
     */
    function __construct(campusconnect_ecssettings $ecssettings, $resourceid) {
        $this->ecssettings = $ecssettings;
        $this->resourceid = $resourceid;
    }

    /**
     * Extract the details of the courses/groups to create to satisfy the parallel groups scenario.
     * Note: Internal function - public to allow for unit testing.
     * @param stdClass $course the course details from the ECS
     * @return array [ $parallelgroups, $scenario] - where $parallelgroups is an array as follows:
     *          [ [$group1, $group2], [$group3], [$group4] ] - outer array represents courses,
     *                                                         inner array represents groups within courses
     *          Courses with only a single group should be created with NO moodle groups
     *          If the $scenario is PGROUP_NONE, no groups should be created
     *          If the $scenario is PGROUP_ONE_COURSE, parallel group records should be created, but no Moodle groups
     *          Each group object contains: $id, $title, $comment, $lecturer (the first lecturer listed)
     */
    public static function get_parallel_groups($course) {
        if (!empty($course->basicData->parallelGroupScenario)) {
            $scenario = $course->basicData->parallelGroupScenario;
        } else {
            return array(array(), self::PGROUP_NONE);
        }

        $parallelgroups = self::get_parallel_group_internal($course);

        switch ($scenario) {
        case self::PGROUP_ONE_COURSE:
        case self::PGROUP_SEPARATE_GROUPS:
            $courses = array($parallelgroups);
            break;

        case self::PGROUP_SEPARATE_COURSES:
            $courses = array();
            foreach ($parallelgroups as $key => $group) {
                $courses[] = array($key => $group);
            }
            break;

        case self::PGROUP_SEPARATE_LECTURERS:
            $courses = array();
            foreach ($parallelgroups as $pgroup) {
                $lecturer = $pgroup->lecturer;
                if (empty($lecturer)) {
                    $lecturer = 0;
                }
                if (!isset($courses[$lecturer])) {
                    $courses[$lecturer] = array();
                }
                $courses[$lecturer][$pgroup->id] = $pgroup;
            }
            break;

        default:
            debugging("Unknown parallel groups scenario: {$scenario}");
            $courses = array();
            $scenario = self::PGROUP_NONE;
        }

        return array($courses, $scenario);
    }

    /**
     * Extract the parallel groups from the course data
     * @param stdClass $course the course data from the ECS
     * @return stdClass[] - groupid => group details (with 'lecturers' flattened to the name of the first lecturer)
     */
    protected static function get_parallel_group_internal($course) {
        if (!isset($course->parallelGroups)) {
            return array();
        }

        $groups = array();
        foreach ($course->parallelGroups as $group) {
            $details = new stdClass();
            $details->id = $group->id;
            $details->title = !empty($group->title) ? $group->title : '';
            $details->comment = isset($group->comment) ? $group->comment : null;
            if (isset($group->lecturers)) {
                // Only use the first lecturer name => map all groups starting with same lecturer onto same course
                // (PGROUP_SEPARATE_LECTURERS)
                $lecturer = reset($group->lecturers);
                $details->lecturer = $lecturer->firstName.' '.$lecturer->lastName;
            } else {
                $details->lecturer = '';
            }
            $groups[$details->id] = $details;
        }

        return $groups;
    }

    /**
     * Given the details of the parallel groups and pg roles for a user, return a list of courses and groups to
     * enrol this user into.
     * @param string[] $pgroups mapping cmsgroupid => role to assign
     * @param int[] $defaultcourseids all courseids associated with the cms course the user is enroling into
     * @param string $defaultrole the role to be assigned to the user
     * @return array
     */
    public static function get_groups_for_user($pgroups, $defaultcourseids, $defaultrole) {
        global $DB;

        // User not enroling in a parallel group - add them to the first course in the list.
        if (empty($pgroups)) {
            $pgroup = (object)array(
                'courseid' => reset($defaultcourseids),
                'role' => $defaultrole,
                'groupid' => 0,
                'cmsgroupid' => ''
            );
            return array($pgroup);
        }

        static $groupcache = array();

        // User enroling in parallel groups - generate a list of all the courses they need to enrol in.
        $ret = array();
        foreach ($pgroups as $groupid => $grouprole) {
            if (!isset($groupcache[$groupid])) {
                $groupcache[$groupid] = $DB->get_record('local_campusconnect_pgroup', array('cmsgroupid' => $groupid),
                                                        'cmsgroupid, courseid, groupid');
            }
            if ($groupcache[$groupid]) {
                $ret[] = (object)array(
                    'courseid' => $groupcache[$groupid]->courseid,
                    'role' => empty($grouprole) ? $defaultrole : $grouprole,
                    'groupid' => $groupcache[$groupid]->groupid,
                    'cmsgroupid' => $groupid
                );
                if (!in_array($groupcache[$groupid]->courseid, $defaultcourseids)) {
                    debugging("Expected {$groupcache[$groupid]->courseid}, the course for parallel group {$groupid}, to be in the list of courses: (".implode(', ', $defaultcourseids).")");
                }
            }
        }
        return $ret;
    }

    /**
     * Attempts to organise the parallel groups based on the Moodle courseids that the groups have already been mapped onto.
     * Any groups that cannot be mapped onto an existing course are mapped on to an existing course are returned separately.
     * @param stdClass[] $pgroups
     * @param int $pgroupsmode the current parallel groups mode
     * @param int $firstcourseid the ID of the first existing course (needed if there are no exiting pgroups found)
     * @return array [ $matched, $notmatched ] - $matched = associative array $courseid => array of group details
     *                                           $notmatched = array of array of group details
     */
    public function match_parallel_groups_to_courses($pgroups, $pgroupsmode, $firstcourseid) {
        global $DB;

        if ($pgroupsmode == self::PGROUP_NONE) {
            return array(array(), array());
        }
        $matched = array();
        $notmatched = array();
        $existing = $DB->get_records('local_campusconnect_pgroup', array('ecsid' => $this->ecssettings->get_id(),
                                                                        'resourceid' => $this->resourceid),
                                     '', 'cmsgroupid, id, courseid');
        if (empty($existing)) {
            // This probably means we've just switched from PGROUP_NONE to one of the scenarios. Assume that the existing
            // course matches the first pgcourse
            $pgcourse = array_shift($pgroups);
            return array(array($firstcourseid => $pgcourse), $pgroups);
        }

        foreach ($pgroups as $pcourse) {
            // Go through the groups in each parallel course and see if we can match them with already-instantiated versions
            // (based on the group 'ID' from the CMS).
            foreach ($pcourse as $pg) {
                if (array_key_exists($pg->id, $existing)) {
                    $courseid = $existing[$pg->id]->courseid;
                    if (!isset($matched[$courseid])) {
                        // If courseid might already be 'taken' if switching from one group per pgroup to one course
                        // per pgroup, so only match up the first time the courseid is found (others will be used to
                        // create new courses).
                        $matched[$courseid] = $pcourse;
                        continue 2; // Move on to the next course in the parallel groups.
                    }
                }
            }
            // None of the existing parallel groups in Moodle matched any of the parallel groups in this course
            $notmatched[] = $pcourse;
        }

        return array($matched, $notmatched);
    }

    /**
     * Compare the parallel groups already existing in the course and update to match the current scenario / groups
     * @param stdClass $course
     * @param int $pgroupmode
     * @param array $pcourse
     */
    public function update_parallel_groups(stdClass $course, $pgroupmode, $pcourse) {
        global $DB;

        if ($pgroupmode == self::PGROUP_NONE) {
            return; // Nothing to do.
        }

        if ($pgroupmode == self::PGROUP_SEPARATE_COURSES) {
            if (count($pcourse) > 1) {
                throw new coding_exception("With 'separate groups' mode, only one group should be passed in to each course");
            }
        }

        $sql = "SELECT pg.cmsgroupid, pg.id, pg.grouptitle, pg.groupid, g.id AS groupexists
                  FROM {local_campusconnect_pgroup} pg
                  LEFT JOIN {groups} g ON g.id = pg.groupid
                 WHERE pg.ecsid = :ecsid AND pg.resourceid = :resourceid AND pg.courseid = :courseid";
        $params = array('ecsid' => $this->ecssettings->get_id(), 'resourceid' => $this->resourceid, 'courseid' => $course->id);
        $existing = $DB->get_records_sql($sql, $params);

        unset($params['courseid']);
        $existingallcourses = $DB->get_records('local_campusconnect_pgroup', $params, '',
                                               'cmsgroupid, id, courseid, groupid, grouptitle');

        $ins = new stdClass();
        $ins->ecsid = $this->ecssettings->get_id();
        $ins->resourceid = $this->resourceid;
        $ins->courseid = $course->id;

        // Create each of the parallel groups requested.
        $creategroup = ($pgroupmode != self::PGROUP_ONE_COURSE) && (count($pcourse) > 1);
        foreach ($pcourse as $pg) {
            if (array_key_exists($pg->id, $existing)) {
                // The pgroup is already mapped onto this course - update it if needed.
                $upd = new stdClass();
                if ($creategroup && is_null($existing[$pg->id]->groupexists)) {
                    // The Moodle group does not exist/has been deleted - (re)create it..
                    $upd->groupid = $this->create_or_update_group($course, $pg);
                    $upd->grouptitle = $pg->title;
                } else if (!$creategroup && $existing[$pg->id]->groupid) {
                    // Not creating groups but there is an existing group - remove the reference to the group
                    $upd->groupid = 0;
                    $upd->grouptitle = $pg->title;
                } else if ($existing[$pg->id]->grouptitle != $pg->title) {
                    // Group title has changed - update it (and the Moodle group title as well, if required).
                    $upd->grouptitle = $pg->title;
                    if ($creategroup) {
                        $this->create_or_update_group($course, $pg, $existing[$pg->id]->groupid);
                    }
                } else {
                    continue; // No changes, so no need to update the record.
                }

                // Update pgroup record with the changes.
                $upd->id = $existing[$pg->id]->id;
                $DB->update_record('local_campusconnect_pgroup', $upd);

            } else if (array_key_exists($pg->id, $existingallcourses)) {
                // The group exists, but is in a different course (probably because the parallel groups scenario has changed)
                $upd = new stdClass();
                $upd->id = $existingallcourses[$pg->id]->id;
                $upd->courseid = $course->id;
                $upd->grouptitle = $pg->title;
                if ($creategroup) {
                    $upd->groupid = $this->create_or_update_group($course, $pg);
                } else {
                    $upd->groupid = 0;
                }
                $DB->update_record('local_campusconnect_pgroup', $upd);

            } else {
                // The pgroup does not yet exist.
                if ($DB->record_exists('local_campusconnect_pgroup', array('cmsgroupid' => $pg->id))) {
                    debugging("Group already exists with ID: {$pg->id} - skipping creation of new group");
                } else {
                    $ins->cmsgroupid = $pg->id;
                    $ins->grouptitle = $pg->title;
                    if ($creategroup) {
                        $ins->groupid = $this->create_or_update_group($course, $pg);
                    } else {
                        $ins->groupid = 0;
                    }
                    $DB->insert_record('local_campusconnect_pgroup', $ins);
                }
            }
        }

        // No deletion of unwanted groups.
    }

    /**
     * Create a new Moodle group (if it doesn't exist) or update it (if it does)
     * @param stdClass $course details of the Moodle course to create the group in
     * @param stdClass $pgroup details of the parallel group to create in this course
     * @param int $id optional - if set, the group is updated, otherwise the group is created
     * @return int the ID of the newly created group
     */
    public function create_or_update_group($course, $pgroup, $id = null) {
        global $CFG;
        require_once($CFG->dirroot.'/group/lib.php');
        $data = new stdClass();
        $data->courseid = $course->id;
        $data->name = !empty($pgroup->title) ? $pgroup->title : $pgroup->id;
        if (isset($pgroup->comment) && !is_null($pgroup->comment)) {
            $data->description = $pgroup->comment;
            $data->descriptionformat = FORMAT_PLAIN;
        }
        if ($id) {
            $data->id = $id;
            groups_update_group($data);
        } else {
            $data->id = groups_create_group($data);
        }
        return $data->id;
    }

    /**
     * Add the name of the group or lecturer to the course fullname
     * @param string $coursename the base course name
     * @param int $pgroupmode the parallel groups scenario in use
     * @param stdClass[] $pcourse the details of the parallel groups to create in this course
     * @return string the new fullname for the course
     */
    public function update_course_name($coursename, $pgroupmode, $pcourse) {
        $extra = '';
        if ($pgroupmode == self::PGROUP_SEPARATE_COURSES) {
            $pgroup = reset($pcourse);
            if (!empty($pgroup->title)) {
                $extra = " ({$pgroup->title})";
            }
        } else if ($pgroupmode == self::PGROUP_SEPARATE_LECTURERS) {
            $pgroup = reset($pcourse);
            if (!empty($pgroup)) {
                $extra = " ({$pgroup->lecturer})";
            }
        }
        return $coursename.$extra;
    }
}
