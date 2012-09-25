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

global $CFG;
require_once($CFG->dirroot.'/local/campusconnect/participantsettings.php');
require_once($CFG->dirroot.'/local/campusconnect/metadata.php');
require_once($CFG->dirroot.'/course/lib.php');

/**
 * Exception thrown by the campusconnect_courselink object
 */
class campusconnect_courselink_exception extends moodle_exception {
    /**
     * Throw a new exception
     * @param string $msg
     */
    function __construct($msg) {
        parent::__construct('error', 'local_campusconnect', '', $msg);
    }
}

/**
 * Holds and updates courselinks created that link fake local courses to real courses on an external server.
 */
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

        if (is_null($transferdetails)) {
            throw new coding_exception('campusconnect_courselink::create - $transferdetails must not be null. Did you get here via "refresh_from_ecs"?');
        }

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
                /** @noinspection PhpUndefinedMethodInspection */
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
     * @param int $mid set when doing a full update (and $transferdetails = null)
     * @return bool true if successfully updated
     */
    public static function update($resourceid, campusconnect_ecssettings $settings, $courselink, campusconnect_details $transferdetails, $mid = null) {
        global $DB;

        if ((is_null($transferdetails) && is_null($mid)) ||
            (!is_null($transferdetails) && !is_null($mid))) {
            throw new coding_exception('campusconnect_courselink::update must set EITHER $transferdetails OR $mid');
        }

        if (is_null($mid)) {
            $mid = $transferdetails->get_sender_mid();
            $ecsid = $settings->get_id();
            $partsettings = new campusconnect_participantsettings($ecsid, $mid);

            if (!$partsettings->is_import_enabled()) {
                return true;
            }
        } else {
            $partsettings = null;
        }

        $coursedata = self::map_course_settings($courselink, $settings);

        if ($partsettings && $partsettings->get_import_type() == campusconnect_participantsettings::IMPORT_LINK) {
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
                    /** @noinspection PhpUndefinedMethodInspection */
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
                    /** @noinspection PhpUndefinedMethodInspection */
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
                /** @noinspection PhpUndefinedMethodInspection */
                $DB->mock_delete_course($currlink->courseid);
            }
            $DB->delete_records('local_campusconnect_clink', array('id' => $currlink->id));
        }

        return true;
    }

    /**
     * Update all courselinks from the ECS
     * @param campusconnect_connect $connect
     * @return object containing: ->created - array of created resource ids
     *                            ->updated - array of updated resource ids
     *                            ->deleted - array of deleted resource ids
     */
    public static function refresh_from_ecs(campusconnect_ecssettings $ecssettings) {
        global $DB;

        $ret = (object)array('created' => array(), 'updated' => array(), 'deleted' => array());

        // Get full list of courselinks from this ECS.
        $courselinks = $DB->get_records('local_campusconnect_clink', array('ecsid' => $ecssettings->get_id()), '', 'resourceid, ecsid, mid');

        // Get list of participants we are importing from.
        $communities = campusconnect_participantsettings::load_communities($ecssettings);
        $importparticipants = array();
        foreach ($communities as $community) {
            /** @var campusconnect_participantsettings $part */
            foreach ($community->participants as $part) {
                if (!$part->is_import_enabled()) {
                    continue;
                }
                if ($part->get_import_type() != campusconnect_participantsettings::IMPORT_LINK) {
                    continue;
                }
                $importparticipants[$part->get_mid()] = $part;
            }
        }
        unset($communities);

        // Get full list of courselink resources shared with us.
        $connect = new campusconnect_connect($ecssettings);
        $serverlinks = $connect->get_resource_list(campusconnect_event::RES_COURSELINK);

        // Go through all the links from the server and compare to what we have locally.
        foreach ($serverlinks->get_ids() as $resourceid) {
            // Check if we already have this locally.
            if (isset($courselinks[$resourceid])) {
                $mid = $courselinks[$resourceid]->mid;
                if (isset($importparticipants[$mid])) {
                    $details = $connect->get_resource($resourceid, campusconnect_event::RES_COURSELINK, false);
                    self::update($resourceid, $ecssettings, $details, null, $mid);
                    $ret->updated[] = $resourceid;
                    unset($courselinks[$resourceid]); // So we can delete anything left in the list at the end.
                }
            } else {
                // We don't already have this link
                $details = $connect->get_resource($resourceid, campusconnect_event::RES_COURSELINK, false);
                $transferdetails = $connect->get_resource($resourceid, campusconnect_event::RES_COURSELINK, true);

                if (empty($details)) {
                    continue; // This probably shouldn't occur, but we're just going to ignore it.
                }

                self::create($resourceid, $ecssettings, $details, $transferdetails);
                $ret->created[] = $resourceid;
            }
        }

        // Delete any course links still in our local list (they have either been deleted remotely, or they are from
        // participants we no longer import course links from).
        foreach ($courselinks as $courselink) {
            self::delete($courselink->resourceid, $ecssettings);
            $ret->deleted[] = $courselink->resourceid;
        }

        return $ret;
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
     * @return mixed string | false - the URL to redirect to
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

    /**
     * Internal - generate an authentication hash for the given
     * course link
     * @param $courselink
     * @return string
     */
    protected static function get_ecs_hash($courselink) {
        $ecssettings = new campusconnect_ecssettings($courselink->ecsid);
        $connect = new campusconnect_connect($ecssettings);

        $post = (object)array('url' => $courselink->url);

        return $connect->add_auth($post, $courselink->mid);
    }

    /**
     * Internal - generate the user data to append to the courselink URL to allow SSO
     * @param object $user
     * @return string
     */
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

    /**
     * Get the courselink db record from the courseid
     * @param $courseid
     * @return mixed false | object
     */
    public static function get_by_courseid($courseid) {
        global $DB;
        return $DB->get_record('local_campusconnect_clink', array('courseid' => $courseid));
    }

    /**
     * Get the courselink db record from it's resourceid and ecsid
     * @param int $resourceid
     * @param int $ecsid
     * @return mixed false | object
     */
    public static function get_by_resourceid($resourceid, $ecsid) {
        global $DB;
        $params = array('resourceid' => $resourceid, 'ecsid' => $ecsid);
        return $DB->get_record('local_campusconnect_clink', $params);
    }

    /**
     * Generate the Moodle course metadata, based on the metadata details from the ECS server
     * @param object $courselink
     * @param campusconnect_ecssettings $ecssettings
     * @return object
     */
    protected static function map_course_settings($courselink, campusconnect_ecssettings $ecssettings) {

        $metadata = new campusconnect_metadata($ecssettings, true);
        $coursedata = $metadata->map_remote_to_course($courselink);
        $coursedata->summaryformat = FORMAT_HTML;

        return $coursedata;
    }
}