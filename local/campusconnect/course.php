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
        global $DB;

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
            throw new campusconnect_course_exception("Cannot create a course from resource $resourceid - it already exists.");
        }

        /** @var $categories campusconnect_course_category[] */
        $categories = self::get_categories($course, $ecssettings);
        if (empty($categories)) {
            return false; // The directory has not yet been mapped onto a category => cannot yet create the course.
        }

        self::set_course_defaults($coursedata);

        $internallink = 0;
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

            $ins->id = $DB->insert_record('local_campusconnect_crs', $ins);

            if (!$internallink) {
                $internallink = $newcourse->id; // Point all subsequent courses at the first one (the 'real' course).

                // Let the ECS server know about the created link.
                $courseurl = new campusconnect_course_url($ins->id);
                $courseurl->add();

                // Process any existing enrolment requests for this course
                campusconnect_membership::assign_course_users($newcourse, $ins->cmsid);
            }
        }

        return true;
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

        $currcourses = self::get_by_resourceid($resourceid, $ecssettings->get_id());
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

        // Compare the existing allocations to the new allocations.
        list($csql, $params) = $DB->get_in_or_equal(array_keys($currcourses), SQL_PARAMS_NAMED);
        $existingcategoryids = $DB->get_records_sql_menu("SELECT ccc.id, c.category
                                                            FROM {local_campusconnect_crs} ccc
                                                            JOIN {course} c ON ccc.courseid = c.id
                                                           WHERE ccc.id $csql", $params);
        $unchangedcategories = array();
        /** @var $newcategories campusconnect_course_category[] */
        $newcategories = array();
        foreach ($categories as $category) {
            $crsid = array_search($category->get_categoryid(), $existingcategoryids);
            if ($crsid !== false) {
                $unchangedcategories[$crsid] = $category;
                unset($existingcategoryids[$crsid]); // Any left in this array will be deleted.
            } else {
                $newcategories[] = $category;
            }
        }
        self::remove_allocations($currcourses, $existingcategoryids, $unchangedcategories, $newcategories, $ecssettings->get_id() < 0);

        $coursedata = self::map_course_settings($course, $ecssettings);

        // Update all the existing crs records.
        foreach ($currcourses as $currcourse) {
            if (!$DB->record_exists('course', array('id' => $currcourse->courseid))) {
                // The course has been deleted - recreate it.
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

                // Update the course record to point at this new course.
                $upd = new stdClass();
                $upd->id = $currcourse->id;
                $upd->courseid = $newcourse->id;
                if (isset($course->basicData->id)) {
                    $upd->cmsid = $course->basicData->id;
                }
                $DB->update_record('local_campusconnect_crs', $upd);

                if ($currcourse->internallink == 0) {
                    // Let the ECS server know about the updated link.
                    $courseurl = new campusconnect_course_url($currcourse->id);
                    $courseurl->update();
                }

            } else {
                // Course still exists - update it.
                $coursedata->id = $currcourse->courseid;
                if ($ecssettings->get_id() > 0) {
                    // Nasty hack for unit testing - 'update_course' is too complex to
                    // be practical to mock up the database responses
                    update_course($coursedata);
                } else {
                    global $DB;
                    /** @noinspection PhpUndefinedMethodInspection */
                    $DB->mock_update_course($coursedata);
                }

                // The cms course id has changed (not sure if this should ever happen, but handle it anyway)
                if (isset($course->basicData->id) && $course->basicData->id != $currcourse->cmsid) {
                    $upd = new stdClass();
                    $upd->id = $currcourse->id;
                    $upd->cmsid = $course->basicData->id;
                    $DB->update_record('local_campusconnect_crs', $upd);

                    if ($currcourse->internallink == 0) {
                        // Let the ECS server know about the updated link.
                        $courseurl = new campusconnect_course_url($currcourse->id);
                        $courseurl->update();
                    }
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
                $DB->insert_record('local_campusconnect_crs', $ins);
            }
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
            if ($ecssettings->get_id() > 0) {
                // Nasty hack for unit testing - 'delete_course' is too complex to
                // be practical to mock up the database responses
                delete_course($currcourse->courseid, false);
            } else {
                /** @noinspection PhpUndefinedMethodInspection */
                $DB->mock_delete_course($currcourse->courseid);
            }
            if ($currcourse->internallink == 0) {
                // Leave the course_url code to delete the record once it has informed the ECS
                $courseurl = new campusconnect_course_url($currcourse->id);
                $courseurl->delete();
            } else {
                $DB->delete_records('local_campusconnect_crs', array('id' => $currcourse->id));
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
                                    '', 'resourceid');

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
     * @return int[] mapping CMS courseid => Moodle courseid
     */
    public static function get_courseids_from_cmscourseids(array $cmscourseids) {
        global $DB;

        if (empty($cmscourseids)) {
            return array();
        }

        list($csql, $params) = $DB->get_in_or_equal($cmscourseids);
        return $DB->get_records_select_menu('local_campusconnect_crs', "cmsid $csql AND internallink = 0", $params,
                                            '', 'cmsid, courseid');
    }

    /**
     * Given a list of Moodle courseids, return the CMS course ids that these map onto
     * @param int[] $cmscourseids
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
     * Use the 'allocation' section of the course resource to determine the category ID to create the course in.
     * The category will be created, if required.
     * @param stdClass $course
     * @param campusconnect_ecssettings $ecssettings
     * @return campusconnect_course_category[] empty if the directory is not yet mapped, so the course cannot be created
     */
    protected static function get_categories(stdClass $course, campusconnect_ecssettings $ecssettings) {
        if (!isset($course->allocations)) {
            debugging("Warning - course request without 'allocations' details - using default import category");
            return $ecssettings->get_import_category();
        }

        $ret = array();
        foreach ($course->allocations as $allocation) {
            if ($catid = campusconnect_directorytree::get_category_for_course($allocation->parentID)) {
                $ret[] = new campusconnect_course_category($catid, $allocation->order);
            }
        }

        return $ret;
    }

    /**
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

        // Make sure the 'real' course continues to exist - move it to a different category, if no longer mapped to its current location.
        foreach ($removecategoryids as $rcrsid => $rcatid) {
            $currcourse = $currcourses[$rcrsid];
            if ($currcourse->internallink == 0) { // We are trying to remove the 'real' course - instead move it.
                if (!empty($newcategories)) {
                    // Move it into the newly-mapped category.
                    /** @var $newcategory campusconnect_course_category */
                    $newcategory = array_shift($newcategories);
                    $DB->set_field('course', 'category', $newcategory->get_categoryid(), array('id' => $currcourse->courseid));
                } else {
                    // No newly-mapped categories, so will need to move it into an existing category.
                    $removecrsid = array_shift(array_keys($unchangedcategories));
                    $updatecategory = array_shift($unchangedcategories);
                    $DB->set_field('course', 'category', $updatecategory->get_categoryid(), array('id' => $currcourse->courseid));

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
        foreach ($removecategoryids as $rcrsid => $rcatid) {
            $currcourse = $currcourses[$rcrsid];
            if (!empty($newcategories)) {
                // There is a newly-mapped category to move this internal link into.
                $newcategory = array_shift($newcategories);
                $DB->set_field('course', 'category', $newcategory->get_categoryid(), array('id' => $currcourse->courseid));
            } else {
                // No newly-mapped category => just remove the course completely.
                if (!$unittest) {
                    delete_course($currcourse->courseid, false);
                } else {
                    /** @noinspection PhpUndefinedMethodInspection */
                    $DB->mock_delete_course($currcourse->courseid);
                }
                $DB->delete_records('local_campusconnnect_crs', array('id' => $rcrsid));
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

    /**
     * @param int $categoryid
     * @param int $order
     */
    public function __construct($categoryid, $order) {
        $this->categoryid = $categoryid;
        $this->order = $order;
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
}

/**
 * Looks after passing the course URL back to the ECS when a course is created
 */
class campusconnect_course_url {

    const STATUS_UPTODATE = 0;
    const STATUS_CREATED = 1;
    const STATUS_UPDATED = 2;
    const STATUS_DELETED = 3;

    protected $crs;

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
     * @param campusconnect_connect $connect
     */
    public static function update_ecs(campusconnect_connect $connect) {
        global $DB;

        /** @var $cms campusconnect_participantsettings */
        $cms = campusconnect_participantsettings::get_cms_participant();

        if ($connect->get_ecs_id() != $cms->get_ecs_id()) {
            return; // Not updating the ECS that the CMS is on.
        }

        $courseurls = $DB->get_records_select('local_campusconnect_crs', 'ecsid = ? AND urlstatus <> ?',
                                              array($connect->get_ecs_id(), self::STATUS_UPTODATE));
        foreach ($courseurls as $courseurl) {
            if ($courseurl->urlstatus == self::STATUS_DELETED) {
                // Delete from ECS then delete the local record
                $connect->delete_resource($courseurl->resourceid, campusconnect_event::RES_COURSE_URL);
                $DB->delete_records('local_campusconnect_crs', array('id' => $courseurl->id));
                continue;
            }

            // Prepare the course_url data object
            $moodleurl = new moodle_url('/course/view.php', array('id' => $courseurl->courseid));
            $data = new stdClass();
            $data->cms_course_id = $courseurl->cmsid.''; // Convert to string if 'NULL'
            $data->ecs_course_url = $connect->get_resource_url($courseurl->resourceid, campusconnect_event::RES_COURSE);
            $data->lms_course_url = $moodleurl->out();

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
                $connect->update_resource($courseurl->resourceid, campusconnect_event::RES_COURSE_URL, $data, null, $cms->get_mid());
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

    protected function set_status($status) {
        global $DB;

        $upd = new stdClass();
        $upd->id = $this->crs->id;
        $upd->urlstatus = $status;
        if ($status == self::STATUS_DELETED) {
            $upd->courseid = 0; // Remove the rest of the details, as they are no longer needed.
            $upd->resourceid = 0;
            $upd->internallink = 0;
        }
        $DB->update_record('local_campusconnect_crs', $upd);
    }
}