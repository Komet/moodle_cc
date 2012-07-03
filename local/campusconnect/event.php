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
 * Represents an incoming event
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class campusconnect_event_exception extends moodle_exception {
    function __construct($msg) {
        parent::__construct('error', 'local_campusconnect', '', $msg);
    }
}

class campusconnect_event {
    const STATUS_CREATED = 'created';
    const STATUS_UPDATED = 'updated';
    const STATUS_DESTROYED = 'destroyed';
    const STATUS_NEW_EXPORT = 'new_export'; // Not quite sure when this is sent.

    const RES_COURSELINK = 'campusconnect/courselinks';
    const RES_DIRECTORYTREE = 'campusconnect/cms_directorytree';
    const RES_COURSE = 'campusconnect/cms_courses';
    const RES_COURSE_MEMBERS = 'campusconnect/cmd_course_members';

    protected static $validstatus = array(self::STATUS_CREATED, self::STATUS_UPDATED, self::STATUS_DESTROYED);
    protected static $validresources = array(self::RES_COURSELINK, self::RES_DIRECTORYTREE, self::RES_COURSE, self::RES_COURSE_MEMBERS);

    protected $resource;
    protected $resourceid;
    protected $resourcetype;
    protected $ecsid;
    protected $status;
    protected $id = null;

    public function __construct($eventdata, $ecsid = null) {
        if (isset($eventdata->id)) {
            // Constructing from a database record.
            $this->id = $eventdata->id;
            $this->ecsid = $eventdata->serverid;
            $this->status = $eventdata->status;
            $this->resourcetype = $eventdata->type;
            $this->resourceid = $eventdata->resourceid;
            $this->resource = $this->resourcetype.'/'.$this->resourceid;

        } else {
            // Constructing from an ECS response.
            $this->ecsid = $ecsid;
            $this->resource = $eventdata->ressource; // Handle the spelling mistake.
            $this->status = $eventdata->status;

            $resource = explode('/', $this->resource);
            $this->resourceid = array_pop($resource);
            $this->resourcetype = implode('/', $resource);
        }

        if (!self::is_valid_resource($this->resourcetype)) {
            throw new campusconnect_event_exception("Unexpected event type: $this->resourcetype");
        }
        if (!self::is_valid_status($this->status)) {
            throw new campusconnect_event_exception("Unexpected event status: $status");
        }
    }

    public function get_id() {
        if (is_null($this->id)) {
            throw new coding_exception("Can only call 'get_id' on events loaded from the database");
        }
        return $this->id;
    }

    public function get_ecs_id() {
        return $this->ecsid;
    }

    public function get_resource_id() {
        return $this->resourceid;
    }

    public function get_resource_type() {
        return $this->resourcetype;
    }

    public function get_status() {
        return $this->status;
    }

    /**
     * Create / destroy status should override duplicate events,
     * update status should not override
     * @return bool true if any duplicate event should be updated
     */
    public function should_update_duplicate() {
        return $this->get_status() == self::STATUS_CREATED || $this->get_status() == self::STATUS_DESTROYED;
    }

    public static function is_valid_resource($type) {
        return in_array($type, self::$validresources);
    }

    public static function is_valid_status($status) {
        return in_array($type, self::$validstatus);
    }
}