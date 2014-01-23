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

    // TODO davo - update ECS.

    return true;
}

/**
 * Handle unenrolment events
 *
 * @param $eventdata
 * @return bool
 */
function auth_campusconnect_user_unernolled($eventdata) {
    // Don't want to do anything with this yet - but may be wanted in the future.
    return true;
}