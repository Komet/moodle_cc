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
require_once($CFG->dirroot.'/local/campusconnect/notify.php');

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

    const INCLUDE_LEGACY_PARAMS = true; // Include the legacy 'ecs_hash' and 'ecs_uid_hash' params in the courselink url.

    protected $recordid;
    protected $courseid;
    protected $url;
    protected $resourceid;
    protected $ecsid;
    protected $mid;
    protected $title;
    protected $participantname;
    protected $summary;
    protected $timemodified;

    public function __construct(stdClass $data) {
        $this->recordid = $data->id;
        $this->courseid = $data->courseid;
        $this->url = $data->url;
        $this->resourceid = $data->resourceid;
        $this->ecsid = $data->ecsid;
        $this->mid = $data->mid;
        $this->title = $data->title;
        $this->summary = $data->summary;
        $this->participantname = $data->participantname;
        $this->timemodified = $data->timemodified;
    }

    public function get_title() {
        return $this->title;
    }

    public function get_url() {
        return $this->url;
    }

    public function get_link() {
        return html_writer::link($this->url, $this->url);
    }

    public function get_participantname() {
        return $this->participantname." ({$this->ecsid}_{$this->mid})";
    }

    public function get_summary() {
        return $this->summary;
    }

    public function get_timemodified() {
        return $this->timemodified;
    }

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

        if (is_array($courselink)) {
            $courselink = reset($courselink);
        }

        $coursedata = self::map_course_settings($courselink, $settings);

        if ($partsettings->get_import_type() == campusconnect_participantsettings::IMPORT_LINK) {
            if (self::get_by_resourceid($resourceid, $settings->get_id())) {
                mtrace("Cannot create a courselink to resource $resourceid - it already exists.");
                return true; // To remove this update from the list.
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

            campusconnect_notification::queue_message($settings->get_id(),
                                                      campusconnect_notification::MESSAGE_IMPORT_COURSELINK,
                                                      campusconnect_notification::TYPE_CREATE,
                                                      $course->id);
        }

        return true;
    }

    /**
     * Update a new courselink with the details provided.
     * @param int $resourceid the id of this link on the ECS server
     * @param campusconnect_ecssettings $settings the settings for this ECS server
     * @param object $courselink the details of the course from the ECS server
     * @param mixed $transferdetails campusconnect_details | null the details of where the link came from / went to
     * @param int $mid set when doing a full update (and $transferdetails = null)
     * @return bool true if successfully updated
     */
    public static function update($resourceid, campusconnect_ecssettings $settings, $courselink, $transferdetails, $mid = null) {
        global $DB;

        if ((is_null($transferdetails) && is_null($mid)) ||
            (!is_null($transferdetails) && !is_null($mid))) {
            throw new coding_exception('campusconnect_courselink::update must set EITHER $transferdetails OR $mid');
        }

        if (is_null($mid)) {
            /** @var $transferdetails campusconnect_details */
            $mid = $transferdetails->get_sender_mid();
            $ecsid = $settings->get_id();
            $partsettings = new campusconnect_participantsettings($ecsid, $mid);

            if (!$partsettings->is_import_enabled()) {
                return true;
            }
        } else {
            $partsettings = null;
        }

        if (is_array($courselink)) {
            $courselink = reset($courselink);
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

                campusconnect_notification::queue_message($settings->get_id(),
                                                          campusconnect_notification::MESSAGE_IMPORT_COURSELINK,
                                                          campusconnect_notification::TYPE_CREATE,
                                                          $coursedata->id);
            } else {
                // Course still exists - update it.
                $coursedata->id = $currlink->courseid;
                if ($settings->get_id() > 0) {
                    // Nasty hack for unit testing - 'update_course' is too complex to
                    // be practical to mock up the database responses
                    update_course($coursedata);

                    campusconnect_notification::queue_message($settings->get_id(),
                                                              campusconnect_notification::MESSAGE_IMPORT_COURSELINK,
                                                              campusconnect_notification::TYPE_UPDATE,
                                                              $coursedata->id);
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
            $msg = "{$currlink->courseid} ($resourceid)";
            if ($coursename = $DB->get_field('course', 'fullname', array('id' => $currlink->courseid))) {
                $msg .= ' - '.format_string($coursename);
            }
            campusconnect_notification::queue_message($settings->get_id(),
                                                      campusconnect_notification::MESSAGE_IMPORT_COURSELINK,
                                                      campusconnect_notification::TYPE_DELETE,
                                                      0, $msg);
            if ($settings->get_id() > 0) {
                // Nasty hack for unit testing - 'delete_course' is too complex to
                // be practical to mock up the database responses
                delete_course($currlink->courseid, false);
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
     * @param campusconnect_ecssettings $ecssettings
     * @param int $singlemid optional - only update courselinks from this participant
     * @return object containing: ->created - array of created resource ids
     *                            ->updated - array of updated resource ids
     *                            ->deleted - array of deleted resource ids
     */
    public static function refresh_from_ecs(campusconnect_ecssettings $ecssettings, $singlemid = null) {
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
                if (is_null($singlemid) || $part->get_mid() == $singlemid) {
                    $importparticipants[$part->get_mid()] = $part;
                }
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
                if (!is_null($singlemid) && $mid != $singlemid) {
                    unset($courselinks[$resourceid]);
                    continue; // Skip links that don't match the MID we are interested in.
                }
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

                if (!is_null($singlemid) && $transferdetails->get_sender_mid() != $singlemid) {
                    continue; // Skip links that don't match the MID we are interested in.
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
     * Update all courselinks exported by the given participant
     * @param integer $ecsid the ECS we are connecting to
     * @param integer $mid the MID of the participant we are updating from
     */
    public static function refresh_from_participant($ecsid, $mid) {
        $ecssettings = new campusconnect_ecssettings($ecsid);
        self::refresh_from_ecs($ecssettings, $mid);
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

        self::log("\n\n****Checking for external courselink redirect for course {$courseid}****");

        if (!$courselink = self::get_by_courseid($courseid)) {
            self::log("Not an external courselink");
            return false;
        }

        $url = $courselink->url;
        self::log("Link to external url: {$url}");

        if (!isguestuser()) {
            // Add the auth token.
            if (strpos($url, '?') !== false) {
                $url .= '&';
            } else {
                $url .= '?';
            }
            $hash = self::get_ecs_hash($courselink, $USER);
            if (self::INCLUDE_LEGACY_PARAMS) {
                self::log("Adding legacy ecs_hash: {$hash}");
                $url .= 'ecs_hash='.$hash.'&';
            }
            self::log("Adding ecs_hash_url: ".self::get_encoded_hash_url($courselink, $hash));
            $url .= 'ecs_hash_url='.self::get_encoded_hash_url($courselink, $hash);
            self::log("Adding user params: ".self::get_user_data_params($USER));
            $url .= '&'.self::get_user_data_params($USER);
        }

        self::log("Redirecting to: {$url}");

        return $url;
    }

    /**
     * Internal - generate an authentication hash for the given
     * course link
     * @param object $courselink
     * @param object $user
     * @return string
     */
    protected static function get_ecs_hash($courselink, $user) {
        $ecssettings = new campusconnect_ecssettings($courselink->ecsid);
        $connect = new campusconnect_connect($ecssettings);

        $userdata = self::get_user_data($user);
        $realm = campusconnect_connect::generate_realm($courselink->url, $userdata);
        $post = (object)array('realm' => $realm);
        if (self::INCLUDE_LEGACY_PARAMS) {
            $post->url = $courselink->url;
        }

        return $connect->add_auth($post, $courselink->mid);
    }

    /**
     * Generate the correct encoded URL for the 'ecs_hash_url' param
     * @param campusconnect_courselink $courselink
     * @param string $hash
     * @return string
     */
    protected static function get_encoded_hash_url($courselink, $hash) {
        $ecssettings = new campusconnect_ecssettings($courselink->ecsid);
        $ret = $ecssettings->get_url().'/sys/auths/'.$hash;

        return urlencode($ret);
    }

    /**
     * Generate an array containing all the userdata fields
     * @param object $user
     * @return array
     */
    protected static function get_user_data($user) {
        global $CFG;

        $siteid = substr(sha1($CFG->wwwroot), 0, 8); // Generate a unique ID from the site URL
        $uid_hash = 'moodle_'.$siteid.'_usr_'.$user->id;
        $userdata = array('ecs_login' => $user->username,
                          'ecs_firstname' => $user->firstname,
                          'ecs_lastname' => $user->lastname,
                          'ecs_email' => $user->email,
                          'ecs_institution' => '',
                          'ecs_uid' => $uid_hash);
        if (self::INCLUDE_LEGACY_PARAMS) {
            $userdata['ecs_uid_hash'] = $uid_hash;
        }

        return $userdata;
    }

    /**
     * Internal - generate the user data to append to the courselink URL to allow SSO
     * @param object $user
     * @return string
     */
    protected static function get_user_data_params($user) {
        $userdata = self::get_user_data($user);
        return http_build_query($userdata, null, '&');
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
     * Get the courselink db record from its resourceid and ecsid
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

    /**
     * Retrieve a list of all the imported course links
     * @param integer $ecsid optional - only retrieve links for this ECS
     * @param integer $mid optional - only retrieve links for this MID
     * @return campusconnect_courselink[] the course link details
     */
    public static function list_links($ecsid = null, $mid = null) {
        global $DB;

        if (!is_null($mid) && is_null($ecsid)) {
            throw new coding_exception('campusconnect_courselink::list_links - must specify ecsid if mid is specified');
        }

        $sql = "SELECT cl.*, c.fullname AS title, p.displayname AS participantname, c.summary, c.timemodified
                  FROM {local_campusconnect_clink} cl
                  JOIN {course} c ON cl.courseid = c.id
                  JOIN {local_campusconnect_part} p ON cl.ecsid = p.ecsid AND cl.mid = p.mid";
        $params = array();
        if (!is_null($ecsid)) {
            $params['ecsid'] = $ecsid;
            $sql .= " WHERE cl.ecsid = :ecsid ";
            if (!is_null($mid)) {
                $params['mid'] = $mid;
                $sql .= " AND cl.mid = :mid ";
            }
        }
        $links = $DB->get_records_sql($sql, $params);
        $ret = array();
        foreach ($links as $link) {
            $ret[] = new campusconnect_courselink($link);
        }

        return $ret;
    }

    protected static function log($msg) {
        global $CFG;

        if ($CFG->debug == DEBUG_DEVELOPER) {
            require_once($CFG->dirroot.'/local/campusconnect/log.php');
            campusconnect_log::add($msg, false, false);
        }
    }
}