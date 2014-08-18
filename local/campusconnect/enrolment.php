<?php
// This file is part of Moodle - http://moodle.org/
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
 * Handle enrolment status resources - both exporting and importing
 *
 * @package   local_campusconnect
 * @copyright 2014 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot.'/local/campusconnect/log.php');

/**
 * Class campusconnect_enrolment
 */
class campusconnect_enrolment {

    const PERSON_UNIQUECODE = 'ecs_PersonalUniqueCode';
    const PERSON_LOGIN = 'ecs_login';
    const PERSON_UID = 'ecs_uid';
    const PERSON_LOGINUID = 'ecs_loginUID';
    const PERSON_EMAIL = 'ecs_email';
    const PERSON_EPPN = 'ecs_eppn';

    public static $validpersontypes = array(self::PERSON_UNIQUECODE, self::PERSON_LOGIN, self::PERSON_UID,
                                            self::PERSON_LOGINUID, self::PERSON_EMAIL, self::PERSON_EPPN);

    const STATUS_ACTIVE = 'active';
    const STATUS_PENDING = 'pendig';
    const STATUS_REJECTED = 'rejected';
    const STATUS_UNSUBSCRIBED = 'unsubscribed';
    const STATUS_DENIED = 'denied';
    const STATUS_INACTIVE = 'inactive_account';

    public static $validstatuses = array(self::STATUS_ACTIVE, self::STATUS_PENDING, self::STATUS_REJECTED,
                                         self::STATUS_UNSUBSCRIBED, self::STATUS_DENIED, self::STATUS_INACTIVE);

    /**
     * Queue a status change to send back to the participant the user came from.
     *
     * @param int $courseid
     * @param object $user
     * @param string $status
     */
    public static function set_status($courseid, $user, $status) {
        global $DB;

        if ($user->auth != 'campusconnect') {
            throw new coding_exception('campusconnect_enrolment::set_status is only relevant for users authenticated via auth_campusconnect');
        }
        if (!in_array($status, self::$validstatuses)) {
            throw new coding_exception("Invalid status: {$status}");
        }

        $params = array('courseid' => $courseid, 'userid' => $user->id);
        if ($existing = $DB->get_record('local_campusconnect_enrex', $params)) {
            // Already an unsent message about this user's enrolment status - update the status.
            $upd = (object)array(
                'id' => $existing->id,
                'status' => $status,
            );
            $DB->update_record('local_campusconnect_enrex', $upd);

        } else {
            // New entry.
            $ins = (object)$params;
            $ins->status = $status;
            $DB->insert_record('local_campusconnect_enrex', $ins);
        }

        campusconnect_log::add("Setting status for user {$user->id} in course {$courseid} to '{$status}'", true, false, false);
    }

    /**
     * Send all queued messages back to the relevant participants
     *
     * @param campusconnect_connect $connect
     */
    public static function update_ecs(campusconnect_connect $connect) {
        global $DB;

        // Get all records.
        $enrolments = $DB->get_recordset('local_campusconnect_enrex', null, 'id');

        foreach ($enrolments as $enrol) {
            // Generate the data to send to the server.
            $sql = "SELECT u.*, ac.ecsid, ac.pid, ac.personid, ac.personidtype
                      FROM {user} u
                      JOIN {auth_campusconnect} ac ON ac.username = u.username
                     WHERE u.id = :id";
            if ($user = $DB->get_record_sql($sql, array('id' => $enrol->userid))) {
                if ($user->ecsid != $connect->get_ecs_id()) {
                    continue; // User came from a different ECS - wait until we are processing that ECS.
                    // Note, we don't filter by ECS id in the SQL query, as we need to distinguish between records that
                    // don't relate to any auth_campusconnect users (which should be deleted) and those that relate to
                    // users from a different ECS (as most sites only have a few ECS, this should be a minimal overhead).
                }
                $mids = campusconnect_participantsettings::get_mids_from_pid($user->ecsid, $user->pid);
                $export = new campusconnect_export($enrol->courseid);
                if ($export->is_exported_to($user->ecsid, $mids)) {
                    $course = $DB->get_record('course', array('id' => $enrol->courseid));
                    $data = (object)array(
                        'url' => campusconnect_export::get_course_url($course),
                        'id' => campusconnect_export::get_course_id($course, $connect),
                        'personID' => $user->personid,
                        'personIDtype' => $user->personidtype,
                        'status' => $enrol->status,
                    );
                    foreach ($mids as $mid) {
                        if ($export->should_send_enrolment_status($connect->get_ecs_id(), $mid)) {
                            $connect->add_resource(campusconnect_event::RES_ENROLMENT, $data, null, $mid);
                            campusconnect_log::add("Sending status update for user {$user->id} in course {$enrol->courseid} to ECS {$user->ecsid} MID {$mid} PID {$user->pid} - new status = {$enrol->status}", true, false, false);
                        }
                    }
                } else {
                    campusconnect_log::add("NOT sending status update for user {$user->id} in course {$enrol->courseid} - this course is not exported to the participant the user came from (ECS {$user->ecsid} PID {$user->pid})", true, false, false);
                }
            }

            $DB->delete_records('local_campusconnect_enrex', array('id' => $enrol->id));
        }
    }

    /**
     * Update the user's course enrolment status with that from the ECS.
     *
     * @param campusconnect_ecssettings $settings
     * @param object $resource
     * @param campusconnect_details $details
     * @return bool
     */
    public static function update_status_from_ecs(campusconnect_ecssettings $settings, $resource, campusconnect_details $details) {
        global $DB;

        // Match back to the original user.
        if (!$user = campusconnect_courselink::get_user_from_personid($resource->personID, $resource->personIDtype)) {
            campusconnect_log::add("Cannot find user matching personID: {$resource->personID} ({$resource->personIDtype})");
            return true;
        }

        // Find the course link to the external course.
        $ecsid = $settings->get_id();
        $mid = $details->get_sender_mid();
        $url = $resource->url;

        $participantsettings = new campusconnect_participantsettings($ecsid, $mid);
        if (!$participantsettings->is_import_enrolment_enabled()) {
            return true; // Ignoring enrolment status updates from this participant.
        }

        $courselink = $DB->get_record('local_campusconnect_clink', array('ecsid' => $ecsid, 'mid' => $mid, 'url' => $url));
        if (!$courselink) {
            campusconnect_log::add("Cannot find an imported course link matching ecsid: $ecsid, mid: $mid, url: $url");
            return true;
        }

        if (!$course = $DB->get_record('course', array('id' => $courselink->courseid))) {
            campusconnect_log::add("Cannot find course $courselink->courseid, referred to be courselink $courselink->id");
            return true;
        }

        switch ($resource->status) {
            case self::STATUS_ACTIVE:
                // Enrol user in the course link course.
                enrol_try_internal_enrol($course->id, $user->id);
                campusconnect_log::add("member_status change received - enrolling user {$user->id} in course {$course->id}", true, false, false);
                break;

            case self::STATUS_UNSUBSCRIBED:
            case self::STATUS_DENIED:
            case self::STATUS_INACTIVE:
            case self::STATUS_REJECTED:
                // Unenrol user from the course link course.
                $enrol = enrol_get_plugin('manual');
                $instance = $DB->get_records('enrol', array('enrol' => 'manual', 'courseid' => $course->id));
                if ($instance) {
                    $instance = reset($instance);
                    $enrol->unenrol_user($instance, $user->id);
                }
                campusconnect_log::add("member_status change received - unenrolling user {$user->id} from course {$course->id}", true, false, false);
                break;

            case self::STATUS_PENDING:
                // Nothing to do for PENDING enrolments.
                break;

            default:
                campusconnect_log::add("Unexpected status from member_status resource: {$resource->status}", true, false, false);
                break;
        }

        return true;
    }
}