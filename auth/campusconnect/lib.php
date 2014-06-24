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
 * Library functions
 *
 * @package   auth_campusconnect
 * @copyright 2014 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
defined('MOODLE_INTERNAL') || die();

/**
 * Handle user enrolment events - update 'last enroled' value + notify ECS (if needed).
 *
 * @param $eventdata
 * @return bool
 */
function auth_campusconnect_user_enrolled($eventdata) {
    global $DB, $USER, $CFG;

    if ($eventdata->userid == $USER->id) {
        $user = $USER;
    } else {
        $user = $DB->get_record('user', array('id' => $eventdata->userid));
    }

    if ($user->auth !== 'campusconnect') {
        return true; // Only interested in users who authenticated via Campus Connect.
    }

    if (!$authrec = $DB->get_record('auth_campusconnect', array('username' => $user->username))) {
        require_once($CFG->dirroot.'/local/campusconnect/log.php');
        campusconnect_log::add("auth_campusconnect - user '{$user->username}' missing record in auth_campusconnect database table");
        return true; // I don't think this should ever happen, but avoid throwing a fatal error.
    }

    $upd = (object)array(
        'id' => $authrec->id,
        'lastenroled' => $eventdata->timecreated,
    );
    $DB->update_record('auth_campusconnect', $upd);

    require_once($CFG->dirroot.'/local/campusconnect/enrolment.php');
    campusconnect_enrolment::set_status($eventdata->courseid, $user, campusconnect_enrolment::STATUS_ACTIVE);

    return true;
}

/**
 * Handle unenrolment events
 *
 * @param $eventdata
 * @return bool
 */
function auth_campusconnect_user_unenrolled($eventdata) {
    global $CFG, $USER, $DB;

    if ($eventdata->userid == $USER->id) {
        $user = $USER;
    } else {
        $user = $DB->get_record('user', array('id' => $eventdata->userid));
    }

    if ($user->auth != 'campusconnect') {
        return true; // Only interested in users who authenticated via Campus Connect.
    }

    require_once($CFG->dirroot.'/local/campusconnect/enrolment.php');
    campusconnect_enrolment::set_status($eventdata->courseid, $user, campusconnect_enrolment::STATUS_UNSUBSCRIBED);

    return true;
}

/**
 * Handle user updated events and check for users becoming suspended (or unsuspended).
 *
 * @param object $user
 * @return bool
 */
function auth_campusconnect_user_updated($user) {
    global $CFG, $DB;

    if ($user->auth != 'campusconnect') {
        return true;  // Only interested in users who authenticated via Campus Connect.
    }

    require_once($CFG->dirroot.'/local/campusconnect/enrolment.php');
    $oldsuspended = $DB->get_field('auth_campusconnect', 'suspended', array('username' => $user->username));
    if ($oldsuspended && !$user->suspended) {
        $status = campusconnect_enrolment::STATUS_ACTIVE; // User no longer suspended - mark all enrolments as active.
    } else if (!$oldsuspended && $user->suspended) {
        $status = campusconnect_enrolment::STATUS_INACTIVE; // User is now suspended - mark all enrolments as inactive.
    } else {
        return true; // No change in suspended status.
    }
    $DB->set_field('auth_campusconnect', 'suspended', $user->suspended, array('username' => $user->username));

    // Update status for all courses the user is enroled in.
    foreach (enrol_get_all_users_courses($user->id) as $course) {
        campusconnect_enrolment::set_status($course->id, $user, $status);
    }

    return true;
}