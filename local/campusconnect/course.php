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
     * @return bool true if successful
     */
    public static function create($resourceid, campusconnect_ecssettings $ecssettings, $course, campusconnect_details $transferdetails) {
        global $DB;

        $mid = $transferdetails->get_sender_mid();
        $ecsid = $ecssettings->get_id();
        $cms = campusconnect_participantsettings::get_cms_participant();
        if (!$cms || $cms->get_mid() != $mid || $cms->get_ecs_id() != $ecsid) {
            throw new campusconnect_course_exception("Received create course event from non-CMS participant");
        }

        $coursedata = self::map_course_settings($course, $ecssettings);
        if (self::get_by_resourceid($resourceid, $ecssettings->get_id())) {
            throw new campusconnect_course_exception("Cannot create a courselink to resource $resourceid - it already exists.");
        }

        $coursedata->category = self::get_category($course, $ecssettings);
        if (is_null($coursedata->category)) {
            return false; // The directory has not yet been mapped onto a category => cannot yet create the course.
        }

        self::set_course_defaults($coursedata);

        if ($ecssettings->get_id() > 0) {
            $baseshortname = $coursedata->shortname;
            $num = 1;
            while ($DB->record_exists('course', array('shortname' => $coursedata->shortname))) {
                $num++;
                $coursedata->shortname = "{$baseshortname}_{$num}";
            }
            $course = create_course($coursedata);
        } else {
            // Nasty hack for unit testing - 'create_course' is too complex to
            // be practical to mock up the database responses
            global $DB;
            $course = $coursedata;
            /** @noinspection PhpUndefinedMethodInspection */
            $course->id = $DB->mock_create_course($coursedata);
        }

        $ins = new stdClass();
        $ins->courseid = $course->id;
        $ins->resourceid = $resourceid;
        $ins->ecsid = $ecssettings->get_id();
        $ins->mid = $mid;

        $DB->insert_record('local_campusconnect_crs', $ins);

        return true;
    }

    /**
     * Used by the ECS event processing to update courses
     * @param int $resourceid - the ID on the ECS server
     * @param campusconnect_ecssettings $ecssettings - the ECS being connected to
     * @param object $course - the resource data from ECS
     * @param campusconnect_details $transferdetails - the metadata for the resource on the ECS
     * @return bool true if successful
     */
    public static function update($resourceid, campusconnect_ecssettings $ecssettings, $course, campusconnect_details $transferdetails) {
        global $DB;

        $mid = $transferdetails->get_sender_mid();
        $ecsid = $ecssettings->get_id();
        $cms = campusconnect_participantsettings::get_cms_participant();
        if (!$cms || $cms->get_mid() != $mid || $cms->get_ecs_id() != $ecsid) {
            throw new campusconnect_course_exception("Received update course event from non-CMS participant");
        }

        $coursedata = self::map_course_settings($course, $ecssettings);
        if (!$currcourse = self::get_by_resourceid($resourceid, $ecssettings->get_id())) {
            return self::create($resourceid, $ecssettings, $course, $transferdetails);
            //throw new campusconnect_course_exception("Cannot update course resource $resourceid - it doesn't exist");
        }

        if ($currcourse->mid != $mid) {
            throw new campusconnect_course_exception("Participant $mid attempted to update resource created by participant {$currcourse->mid}");
        }

        $coursedata->category = self::get_category($course, $ecssettings);
        if (is_null($coursedata->category)) {
            return false; // The directory has not yet been mapped onto a category => cannot yet create the course.
        }

        if (!$DB->record_exists('course', array('id' => $currcourse->courseid))) {
            // The course has been deleted - recreate it.
            if ($ecssettings->get_id() > 0) {
                $baseshortname = $coursedata->shortname;
                $num = 1;
                while ($DB->record_exists('course', array('shortname' => $coursedata->shortname))) {
                    $num++;
                    $coursedata->shortname = "{$baseshortname}_{$num}";
                }
                $course = create_course($coursedata);
            } else {
                // Nasty hack for unit testing - 'create_course' is too complex to
                // be practical to mock up the database responses
                global $DB;
                $course = $coursedata;
                /** @noinspection PhpUndefinedMethodInspection */
                $course->id = $DB->mock_create_course($coursedata);
            }

            // Update the course record to point at this new course.
            $upd = new stdClass();
            $upd->id = $currcourse->id;
            $upd->courseid = $course->id;
            $DB->update_record('local_campusconnect_crs', $upd);

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

        if ($currcourse = self::get_by_resourceid($resourceid, $ecssettings->get_id())) {
            if ($ecssettings->get_id() > 0) {
                // Nasty hack for unit testing - 'delete_course' is too complex to
                // be practical to mock up the database responses
                delete_course($currcourse->courseid);
            } else {
                /** @noinspection PhpUndefinedMethodInspection */
                $DB->mock_delete_course($currcourse->courseid);
            }
            $DB->delete_records('local_campusconnect_crs', array('id' => $currcourse->id));
        }

        return true;
    }

    /**
     * Get the course db record from its resourceid and ecsid
     * @param int $resourceid
     * @param int $ecsid
     * @return mixed false | object
     */
    public static function get_by_resourceid($resourceid, $ecsid) {
        global $DB;
        $params = array('resourceid' => $resourceid, 'ecsid' => $ecsid);
        return $DB->get_record('local_campusconnect_crs', $params);
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
     * @return mixed integer | null - 'null' means the directory is not yet mapped, so the course cannot be created
     */
    protected static function get_category(stdClass $course, campusconnect_ecssettings $ecssettings) {
        if (!isset($course->allocations)) {
            debugging("Warning - course request without 'allocations' details - using default import category");
            return $ecssettings->get_import_category();
        }

        if (count($course->allocations) > 1) {
            debugging("Warning - course request with multiple 'allocations' details - not yet supported");
        }

        $allocation = reset($course->allocations);
        return campusconnect_directorytree::get_category_for_course($allocation->parentID);
    }

    /**
     * Return the sort position to create this course at
     * @param stdClass $course
     * @return mixed integer | null - the sort position for the course within the category
     */
    protected static function get_sort_order(stdClass $course) {
        if (!isset($course->allocations)) {
            return null;
        }
        $allocation = reset($course->allocations);
        return $allocation->order;
    }
}
