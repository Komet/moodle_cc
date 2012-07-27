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
 * Represents a link to an external course
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/campusconnect/participantsettings.php');
require_once($CFG->dirroot.'/local/campusconnect/metadata.php');
require_once($CFG->dirroot.'/course/lib.php');

class campusconnect_courselink_exception extends moodle_exception {
    function __construct($msg) {
        parent::__construct('error', 'local_campusconnect', '', $msg);
    }
}

class campusconnect_courselink {

    /**
     * Create a new courselink with the details provided.
     * @param int $resourceid the id of this link on the ECS server
     * @param campusconnect_ecssettings $settings the settings for this ECS server
     * @param object $courselink the details of the course from the ECS server
     * @param campusconnect_details $transferdetails the details of where the link came from / went to
     * @return bool false if a problem occurred
     */
    public static function create($resourceid, campusconnect_ecssettings $settings, $courselink, campusconnect_details $transferdetails) {
        global $DB;

        $mid = $transferdetails->get_sender_mid();
        $ecsid = $settings->get_id();
        $partsettings = new campusconnect_participantsettings($ecsid, $mid);

        if (!$partsettings->is_import_enabled()) {
            return true;
        }

        $coursedata = self::map_course_settings($courselink, $settings);

        if ($partsettings->get_import_type() == campusconnect_participantsettings::IMPORT_LINK) {
            if ($currlink = self::get_by_resourceid($resourceid, $settings->get_id())) {
                throw new campusconnect_courselink_exception("Cannot create a courselink to resource $resourceid - it already exists.");
            }

            $coursedata->category = $settings->get_import_category();

            if ($settings->get_id() > 0) {
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
                $course->id = $DB->mock_create_course($coursedata);
            }

            $ins = new stdClass();
            $ins->courseid = $course->id;
            $ins->url = $courselink->url;
            $ins->resourceid = $resourceid;
            $ins->ecsid = $settings->get_id();
            $ins->mid = $mid;

            $DB->insert_record('local_campusconnect_clink', $ins);
        }

        return true;
    }

    /**
     * Update a new courselink with the details provided.
     * @param int $resourceid the id of this link on the ECS server
     * @param campusconnect_ecssettings $settings the settings for this ECS server
     * @param object $courselink the details of the course from the ECS server
     * @param campusconnect_details $transferdetails the details of where the link came from / went to
     * @return bool true if successfully updated
     */
    public static function update($resourceid, campusconnect_ecssettings $settings, $courselink, campusconnect_details $transferdetails) {
        global $DB;

        $mid = $transferdetails->get_sender_mid();
        $ecsid = $settings->get_id();
        $partsettings = new campusconnect_participantsettings($ecsid, $mid);

        if (!$partsettings->is_import_enabled()) {
            return true;
        }

        $coursedata = self::map_course_settings($courselink, $settings);

        if ($partsettings->get_import_type() == campusconnect_participantsettings::IMPORT_LINK) {
            if (!$currlink = self::get_by_resourceid($resourceid, $settings->get_id())) {
                return self::create($resourceid, $settings, $courselink, $transferdetails);
                //throw new campusconnect_courselink_exception("Cannot update courselink to resource $resourceid - it doesn't exist");
            }

            if ($currlink->mid != $mid) {
                throw new campusconnect_courselink_exception("Participant $mid attempted to update resource created by participant {$currlink->mid}");
            }

            if (!$DB->record_exists('course', array('id' => $currlink->courseid))) {
                // The course has been deleted - recreate it.
                $coursedata->category = $settings->get_import_category();
                if ($settings->get_id() > 0) {
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
                    $course->id = $DB->mock_create_course($coursedata);
                }

                // Update the courselink record to point at this new course.
                $upd = new stdClass();
                $upd->id = $currlink->id;
                $upd->courseid = $course->id;
                $DB->update_record('local_campusconnect_clink', $upd);

            } else {
                // Course still exists - update it.
                $coursedata->id = $currlink->courseid;
                if ($settings->get_id() > 0) {
                    // Nasty hack for unit testing - 'update_course' is too complex to
                    // be practical to mock up the database responses
                    update_course($coursedata);
                } else {
                    global $DB;
                    $DB->mock_update_course($coursedata);
                }
            }


            if ($currlink->url != $courselink->url) {
                $upd = new stdClass();
                $upd->id = $currlink->id;
                $upd->url = $courselink->url;

                $DB->update_record('local_campusconnect_clink', $upd);
            }
        }

        return true;
    }

    /**
     * Delete the courselink based on the details provided
     * @param int $resourceid the id of this link on the ECS server
     * @param campusconnect_ecssettings $settings the settings for this ECS server
     * @return bool true if successfully deleted
     */
    public static function delete($resourceid, campusconnect_ecssettings $settings) {
        global $DB;

        if ($currlink = self::get_by_resourceid($resourceid, $settings->get_id())) {
            if ($settings->get_id() > 0) {
                // Nasty hack for unit testing - 'delete_course' is too complex to
                // be practical to mock up the database responses
                delete_course($currlink->courseid);
            } else {
                $DB->mock_delete_course($currlink->courseid);
            }
            $DB->delete_records('local_campusconnect_clink', array('id' => $currlink->id));
        }

        return true;
    }

    public static function refresh_from_ecs(campusconnect_connect $connect) {
        throw new coding_exception('This function will be written in Phase 2');
    }

    /**
     * Delete all the courselinks to the given participant (used when
     * deleting an ECS or switching off import from a particular participant)
     * @param int $mid the participant ID the course links are associated with
     */
    public static function delete_mid_courselinks($mid) {
        global $DB;

        $courselinks = $DB->get_records('local_campusconnect_clink', array('mid' => $mid));
        foreach ($courselinks as $courselink) {
            delete_course($courselink->courseid);
        }
        $DB->delete_records('local_campusconnect_clink', array('mid' => $mid));
    }


    /**
     * Check if the courseid provided refers to a remote course and return the URL if it does
     * @param int $courseid the ID of the course being viewed
     * @return mixed moodle_url | false - the URL to redirect to
     */
    public static function check_redirect($courseid) {
        global $USER;

        if (!$courselink = self::get_by_courseid($courseid)) {
            return false;
        }

        $url = $courselink->url;

        if (!isguestuser()) {
            // Add the auth token.
            if (strpos($url, '?') !== false) {
                $url .= '&';
            } else {
                $url .= '?';
            }
            $url .= 'ecs_hash='.self::get_ecs_hash($courselink);
            $url .= '&'.self::get_user_data($USER);
        }

        return $url;
    }

    protected static function get_ecs_hash($courselink) {
        $ecssettings = new campusconnect_ecssettings($courselink->ecsid);
        $connect = new campusconnect_connect($ecssettings);

        $post = (object)array('url' => $courselink->url);
        $post = json_encode($post);

        return $connect->add_auth($post, $courselink->mid);
    }

    protected static function get_user_data($user) {
        global $CFG;

        $uid_hash = 'moodle_'.$CFG->wwwroot.'_usr_'.$user->id;
        $userdata = array('ecs_login' => $user->username,
                          'ecs_firstname' => $user->firstname,
                          'ecs_lastname' => $user->lastname,
                          'ecs_email' => $user->email,
                          'ecs_institution' => '',
                          'ecs_uid_hash' => $uid_hash);
        $userdata = array_map('urlencode', $userdata);

        return http_build_query($userdata);
    }

    public static function get_by_courseid($courseid) {
        global $DB;
        return $DB->get_record('local_campusconnect_clink', array('courseid' => $courseid));
    }

    public static function get_by_resourceid($resourceid, $ecsid) {
        global $DB;
        $params = array('resourceid' => $resourceid, 'ecsid' => $ecsid);
        return $DB->get_record('local_campusconnect_clink', $params);
    }

    protected static function map_course_settings($courselink, campusconnect_ecssettings $ecssettings) {

        $metadata = new campusconnect_metadata($ecssettings, true);
        $coursedata = $metadata->map_remote_to_course($courselink);
        $coursedata->summaryformat = FORMAT_HTML;

        return $coursedata;
    }
}