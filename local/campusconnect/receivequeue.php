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
 * Retrieve and queue up all incoming messages from the ECS server
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/local/campusconnect/connect.php');
require_once($CFG->dirroot.'/local/campusconnect/event.php');
require_once($CFG->dirroot.'/local/campusconnect/courselink.php');
require_once($CFG->dirroot.'/local/campusconnect/directorytree.php');
require_once($CFG->dirroot.'/local/campusconnect/details.php');
require_once($CFG->dirroot.'/local/campusconnect/course.php');
require_once($CFG->dirroot.'/local/campusconnect/membership.php');

class campusconnect_receivequeue_exception extends moodle_exception {
    function __construct($msg) {
        parent::__construct('error', 'local_campusconnect', '', $msg);
    }
}

class campusconnect_receivequeue {

    /**
     * @var integer[] IDs of events that were unsuccessful and should be tried again next update
     */
    protected static $skipevents = array();
    protected $unittest = false;

    /**
     * Code for pulling events from the ECS server and adding them to
     * the local queue
     */

    /**
     * Retrieve events from all the ECS registered with this system
     */
    public function update_from_all_ecs() {
        // Loop through each of the ECS.
        $ecslist = campusconnect_ecssettings::list_ecs();
        foreach ($ecslist as $ecsid => $ecsname) {
            $settings = new campusconnect_ecssettings($ecsid);
            $connect = new campusconnect_connect($settings);
            $this->update_from_ecs($connect);
        }
    }

    /**
     * Retrieve all the events from the specified ECS and add to the event queue
     * @param campusconnect_connect $connect the connection to the ECS
     */
    public function update_from_ecs(campusconnect_connect $connect) {
        // Loop through all the events.
        while ($events = $connect->read_event_fifo()) {
            foreach ($events as $eventdata) {
                $event = new campusconnect_event($eventdata, $connect->get_ecs_id());
                $this->add_to_queue($event);
            }
            $connect->read_event_fifo(true); // Delete the event from ECS.
        }
    }

    /**
     * Add the given incoming event to the queue to be processed
     * @param campusconnect_event $event the event to be added
     */
    protected function add_to_queue(campusconnect_event $event) {
        global $DB;

        // Check for existing event.
        $oldevent = $DB->get_record('local_campusconnect_eventin', array('type' => $event->get_resource_type(),
                                                                         'resourceid' => $event->get_resource_id(),
                                                                         'serverid' => $event->get_ecs_id()));

        if ($oldevent) {
            // Event already in the queue - update it, if necessary.
            if ($event->should_update_duplicate()) {
                // 'Update' messages should not override the already-queued event
                $upd = new stdClass();
                $upd->id = $oldevent->id;
                $upd->status = $event->get_status();
                $DB->update_record('local_campusconnect_eventin', $upd);
            }
        } else {
            // New event to add to the queue.
            $ins = new stdClass();
            $ins->type = $event->get_resource_type();
            $ins->resourceid = $event->get_resource_id();
            $ins->serverid = $event->get_ecs_id();
            $ins->status = $event->get_status();
            $ins->id = $DB->insert_record('local_campusconnect_eventin', $ins);
        }
    }

    /**
     * Code for processing all the events in the local queue
     */

    /**
     * Retrieve the next event from the incoming event queue (without removing it)
     * @param campusconnect_ecssettings $ecssettings optional is specified, only retrieve events from this ECS server
     * @return mixed campusconnect_event | false
     */
    protected function get_event_from_queue(campusconnect_ecssettings $ecssettings = null) {
        global $DB;

        $params = array();
        $select = array();
        if ($ecssettings != null) {
            $params['serverid'] = $ecssettings->get_id();
            $select[] = 'serverid = :serverid';
        }
        if (!empty(self::$skipevents)) {
            list($ssql, $sparams) = $DB->get_in_or_equal(self::$skipevents, SQL_PARAMS_NAMED, 'param', false);
            $select[] = "id $ssql";
            $params = array_merge($params, $sparams);
        }
        if (empty($select)) {
            $select = '';
        } else {
            $select = implode(' AND ', $select);
        }
        $ret = $DB->get_records_select('local_campusconnect_eventin', $select, $params, 'id', '*', 0, 1);
        if (empty($ret)) {
            return false;
        }
        $ret = reset($ret);
        return new campusconnect_event($ret);
    }

    /**
     * Remove the given event from the incoming queue
     * @param campusconnect_event $event the event to remove
     */
    protected function remove_event_from_queue(campusconnect_event $event) {
        global $DB;

        $DB->delete_records('local_campusconnect_eventin', array('id' => $event->get_id()));
    }

    /**
     * Skip over this event for now, but process it again on the next update
     * @param campusconnect_event $event
     */
    protected function skip_event(campusconnect_event $event) {
        global $DB;
        self::$skipevents[] = $event->get_id();

        $DB->set_field('local_campusconnect_eventin', 'failcount', $event->get_failcount() + 1, array('id' => $event->get_id()));
    }

    /**
     * Called if we are running a unit test - avoid calling 'fix_course_sortorder', as this is out of scope for
     * the unit tests.
     */
    public function set_unittest() {
        $this->unittest = true;
    }

    /**
     * Process all the events in the queue and take the appropriate actions
     * @param campusconnect_ecssettings $ecssettings optional - if provided, only process events from the specified ECS server
     */
    public function process_queue(campusconnect_ecssettings $ecssettings = null) {
        $fixcourses = false;
        $enrolusers = false;

        while ($event = $this->get_event_from_queue($ecssettings)) {
            $success = true;
            switch ($event->get_resource_type()) {
            case campusconnect_event::RES_COURSELINK:
                $success = $this->process_courselink_event($event);
                if ($success) {
                    $fixcourses = true;
                }
                break;
            case campusconnect_event::RES_DIRECTORYTREE:
                $this->process_directorytree_event($event);
                break;
            case campusconnect_event::RES_COURSE:
                $success = $this->process_course_event($event);
                if ($success) {
                    $fixcourses = true;
                }
                break;
            case campusconnect_event::RES_COURSE_MEMBERS:
                $this->process_members_event($event);
                $enrolusers = true;
                break;
            case campusconnect_event::RES_COURSE_URL:
            default:
                debugging("Unexpected incoming event of type: ".$event->get_resource_type());
                break;
            }

            if ($success) {
                $this->remove_event_from_queue($event);
            } else {
                $this->skip_event($event);
            }
        }

        // Check if any new categories need to be created.
        campusconnect_directory::process_new_directories();

        if ($fixcourses && !$this->unittest) { // Avoid fix_course_sortorder in unit tests.
            fix_course_sortorder();
        }
        if ($enrolusers) {
            // There was a change in course members - time to process the enrolments.
            campusconnect_membership::assign_all_roles($ecssettings, true);
        }
    }

    /**
     * Process events related to courselinks
     * @param campusconnect_event $event the event to process
     * @return bool true if successful
     */
    protected function process_courselink_event(campusconnect_event $event) {

        $settings = new campusconnect_ecssettings($event->get_ecs_id());
        $status = $event->get_status();
        // Delete events do not need to retrieve the resource.
        if ($status == campusconnect_event::STATUS_DESTROYED) {
            mtrace("CampusConnect: delete courselink: ".$event->get_resource_id()."\n");
            campusconnect_courselink::delete($event->get_resource_id(), $settings);
            return true;
        }

        if ($status != campusconnect_event::STATUS_CREATED &&
            $status != campusconnect_event::STATUS_UPDATED) {
            throw new campusconnect_receivequeue_exception("Unknown event status: ".$event->get_status());
        }

        // Retrieve the resource.
        $connect = new campusconnect_connect($settings);
        $resource = $connect->get_resource($event->get_resource_id(), campusconnect_event::RES_COURSELINK);
        $details = $connect->get_resource($event->get_resource_id(), campusconnect_event::RES_COURSELINK, true);

        // Process the create/update event.
        if ($status == campusconnect_event::STATUS_CREATED) {
            mtrace("CampusConnect: create courselink: ".$event->get_resource_id()."\n");
            try {
                return campusconnect_courselink::create($event->get_resource_id(), $settings, $resource, $details);
            } catch (moodle_exception $e) {
                $msg = "Unable to create course for resourceid: ".$event->get_resource_id()." title: {$resource->title}";
                mtrace($msg);
                if (!$event->get_failcount()) {
                    // If this is the first time this event has failed - notify the admin by email.
                    campusconnect_notification::queue_message($event->get_ecs_id(),
                                                              campusconnect_notification::MESSAGE_IMPORT_COURSELINK,
                                                              campusconnect_notification::TYPE_ERROR,
                                                              0, $msg);
                }
                return false;
            }
        }

        mtrace("CampusConnect: update courselink: ".$event->get_resource_id()."\n");
        try {
            return campusconnect_courselink::update($event->get_resource_id(), $settings, $resource, $details);
        } catch (moodle_exception $e) {
            $msg = "Unable to update course for resourceid: ".$event->get_resource_id()." title: {$resource->title}";
            mtrace($msg);
            if (!$event->get_failcount()) {
                // If this is the first time this event has failed - notify the admin by email.
                campusconnect_notification::queue_message($event->get_ecs_id(),
                                                          campusconnect_notification::MESSAGE_IMPORT_COURSELINK,
                                                          campusconnect_notification::TYPE_ERROR,
                                                          0, $msg);
            }
            return false;
        }
    }

    /**
     * Process events related to directory trees
     * @param campusconnect_event $event the event to process
     * @return bool true if successful
     */
    protected function process_directorytree_event(campusconnect_event $event) {
        if (!campusconnect_directorytree::enabled()) {
            return true; // Mapping disabled.
        }

        $settings = new campusconnect_ecssettings($event->get_ecs_id());
        $status = $event->get_status();

        // Delete events do not need to retrieve the resource.
        if ($status == campusconnect_event::STATUS_DESTROYED) {
            mtrace("CampusConnect: delete directory: ".$event->get_resource_id()."\n");
            campusconnect_directorytree::delete_directory($event->get_resource_id(), $settings);
            return true;
        }

        if ($status != campusconnect_event::STATUS_CREATED &&
            $status != campusconnect_event::STATUS_UPDATED) {
            throw new campusconnect_receivequeue_exception("Unknown event status: ".$event->get_status());
        }

        // Retrieve the resource.
        $connect = new campusconnect_connect($settings);
        $resource = $connect->get_resource($event->get_resource_id(), campusconnect_event::RES_DIRECTORYTREE);
        if ($resource) {
            $details = $connect->get_resource($event->get_resource_id(), campusconnect_event::RES_DIRECTORYTREE, true);
        } else {
            return true; // The resource no longer exists - assume we will process the 'delete' event in a moment.
        }

        // Process the create/update event.
        if ($status == campusconnect_event::STATUS_CREATED) {
            mtrace("CampusConnect: create directorytree: ".$event->get_resource_id()."\n");
            return campusconnect_directorytree::create_directory($event->get_resource_id(), $settings, $resource, $details);
        }

        mtrace("CampusConnect: update directorytree: ".$event->get_resource_id()."\n");
        if ($status = campusconnect_directorytree::update_directory($event->get_resource_id(), $settings, $resource, $details)) {
            campusconnect_directorytree::delete_missing_directories($event->get_resource_id(), $settings, $resource, $details);
        }

        return $status;
    }

    /**
     * Process events related to courses
     * @param campusconnect_event $event the event to process
     * @return bool true if successful
     */
    protected function process_course_event(campusconnect_event $event) {
        if (!campusconnect_course::enabled()) {
            return true; // Course creation disabled.
        }

        $settings = new campusconnect_ecssettings($event->get_ecs_id());
        $status = $event->get_status();

        // Delete events do not need to retrieve the resource.
        if ($status == campusconnect_event::STATUS_DESTROYED) {
            mtrace("CampusConnect: delete course: ".$event->get_resource_id()."\n");
            campusconnect_course::delete($event->get_resource_id(), $settings);
            return true;
        }

        if ($status != campusconnect_event::STATUS_CREATED &&
            $status != campusconnect_event::STATUS_UPDATED) {
            throw new campusconnect_receivequeue_exception("Unknown event status: ".$event->get_status());
        }

        // Retrieve the resource.
        $connect = new campusconnect_connect($settings);
        $resource = $connect->get_resource($event->get_resource_id(), campusconnect_event::RES_COURSE);
        if ($resource) {
            $details = $connect->get_resource($event->get_resource_id(), campusconnect_event::RES_COURSE, true);
        } else {
            return true; // The resource no longer exists - assume we will process the 'delete' event in a moment.
        }

        // Process the create/update event.
        if ($status == campusconnect_event::STATUS_CREATED) {
            mtrace("CampusConnect: create course: ".$event->get_resource_id()."\n");
            if (!$status = campusconnect_course::create($event->get_resource_id(), $settings, $resource, $details)) {
                mtrace("CamupsConnect: unable to create course - directory not yet mapped");
            }
            return $status;
        }

        mtrace("CampusConnect: update course: ".$event->get_resource_id()."\n");
        if (!$status = campusconnect_course::update($event->get_resource_id(), $settings, $resource, $details)) {
            mtrace("CampusConnect: unable to update course - directory not yet mapped");
        }
        return $status;
    }

    /**
     * Process events related to course members
     * @param campusconnect_event $event the event to process
     * @return bool true if successful
     */
    protected function process_members_event(campusconnect_event $event) {
        $settings = new campusconnect_ecssettings($event->get_ecs_id());
        $status = $event->get_status();

        // Delete events do not need to retrieve the resource.
        if ($status == campusconnect_event::STATUS_DESTROYED) {
            mtrace("CampusConnect: delete members: ".$event->get_resource_id()."\n");
            campusconnect_membership::delete($event->get_resource_id(), $settings);
            return true;
        }

        if ($status != campusconnect_event::STATUS_CREATED &&
            $status != campusconnect_event::STATUS_UPDATED) {
            throw new campusconnect_receivequeue_exception("Unknown event status: ".$event->get_status());
        }

        // Retrieve the resource.
        $connect = new campusconnect_connect($settings);
        $resource = $connect->get_resource($event->get_resource_id(), campusconnect_event::RES_COURSE_MEMBERS);
        if ($resource) {
            $details = $connect->get_resource($event->get_resource_id(), campusconnect_event::RES_COURSE_MEMBERS, true);
        } else {
            return true; // The resource no longer exists - assume we will process the 'delete' event in a moment.
        }

        // Process the create/update event.
        if ($status == campusconnect_event::STATUS_CREATED) {
            mtrace("CampusConnect: create course members: ".$event->get_resource_id()."\n");
            return campusconnect_membership::create($event->get_resource_id(), $settings, $resource, $details);
        }

        mtrace("CampusConnect: update course: ".$event->get_resource_id()."\n");
        return campusconnect_membership::update($event->get_resource_id(), $settings, $resource, $details);
    }
}