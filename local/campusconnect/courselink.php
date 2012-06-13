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

class campusconnect_courselink_exception extends moodle_exception {
    function __construct($msg) {
        parent::__construct('error', 'local_campusconnect', '', $msg);
    }
}

class campusconnect_courselink {

    /**
     * Create a new courselink with the details provided.
     * @param int $resourceid the id of this link on the ECS server
     * @param int $ecsid the id of the ECS server this came from
     * @param object $courselink the details of the course from the ECS server
     * @param object $transferdetails the details of where the link came from / went to
     * @return bool true if successfully created
     */
    public static function create($resourceid, $ecsid, $courselink, $transferdetails) {
        print_object($courselink);
        print_object($transferdetails);
        return false; // Just while testing.
    }

    /**
     * Update a new courselink with the details provided.
     * @param int $resourceid the id of this link on the ECS server
     * @param int $ecsid the id of the ECS server this came from
     * @param object $courselink the details of the course from the ECS server
     * @param object $transferdetails the details of where the link came from / went to
     * @return bool true if successfully updated
     */
    public static function update($resourceid, $ecsid, $courselink, $transferdetails) {
        print_object($courselink);
        print_object($transferdetails);
        return false; // Just while testing.
    }

    /**
     * Delete the courselink based on the details provided
     * @param int $resourceid the id of this link on the ECS server
     * @param int $ecsid the id of the ECS server this came from
     * @return bool true if successfully deleted
     */
    public static function delete($resourceid, $ecsid) {
        return false; // Just while testing.
    }

    /**
     * Check if the courseid provided refers to a remote course and return the URL if it does
     * @param int $courseid the ID of the course being viewed
     * @return mixed moodle_url | false - the URL to redirect to
     */
    public static function check_redirect($courseid) {
        // TODO write this function
        return false;
    }
}