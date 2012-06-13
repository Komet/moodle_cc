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
 * Tests for the incoming events processing for CampusConnect
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * These tests assume the following set up is already in place with
 * your ECS server:
 * - ECS server running on localhost:3000
 * - participant ids 'unittest1', 'unittest2' and 'unittest3' created
 * - participants are named 'Unit test 1', 'Unit test 2' and 'Unit test 3'
 * - all 3 participants have been added to a community called 'unittest'
 * - none of the participants are members of any other community
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/campusconnect/receivequeue.php');

global $DB;
Mock::generate(get_class($DB), 'mockDB');

class local_campusconnect_receivequeue_test extends UnitTestCase {
    protected $connect = array();
    protected $mid = array();
    protected $realDB = null;

    public function setUp() {
        // Override the $DB global.
        global $DB;
        $this->realDB = $DB;
        $DB = new mockDB();

        // Create the connections for testing.
        $names = array(1 => 'unittest1', 2 => 'unittest2', 3 => 'unittest3');
        foreach ($names as $key => $name) {
            $settings = new campusconnect_ecssettings(null, $name);
            $this->connect[$key] = new campusconnect_connect($settings);
        }

        // Retrieve the mid values for each participant.
        foreach ($this->connect as $key => $connect) {
            $memberships = $connect->get_memberships();
            foreach ($memberships[0]->participants as $participant) {
                if ($participant->itsyou) {
                    $this->mid[$key] = $participant->mid;
                    break;
                }
            }
        }
    }

    public function tearDown() {
        // Restore the $DB global.
        global $DB;
        $DB = $this->realDB;

        // Delete all resources (just in case).
        foreach ($this->connect as $connect) {
            $courselinks = $connect->get_resource_list();
            foreach ($courselinks->get_ids() as $eid) {
                // All courselinks were created by 'unittest1'.
                $this->connect[1]->delete_resource($eid);
            }
        }

        // Delete all events.
        foreach ($this->connect as $connect) {
            while ($connect->read_event_fifo(true));
        }

        $this->connect = array();
        $this->mid = array();
    }

    public function test_update_from_ecs_empty() {
        global $DB;

        // Check the update queue - no updates expected
        $queue = new campusconnect_receivequeue();
        $DB->expectNever('insert_record');
        $DB->expectNever('update_record');
        $DB->expectNever('delete_records');
        $queue->update_from_ecs($this->connect[2]);
    }

    public function test_update_from_ecs_create_update_delete() {
        global $DB;

        $url = 'http://www.example.com/test123/';
        $post = (object)array('url' => $url);
        $post = json_encode($post);
        $url2 = 'http://www.example.com/test456/';
        $post2 = (object)array('url' => $url2);
        $post2 = json_encode($post2);
        $community = 'unittest';
        $queue = new campusconnect_receivequeue();

        // Add a resource to the community
        $eid = $this->connect[1]->add_resource($post, $community);

        // Set up the expectations - 3 records inserted, none deleted/updated
        $DB->expectNever('update_record');
        $DB->expectNever('delete_records');
        $DB->setReturnValue('insert_record', 1);

        $expecteddata = array();
        $expecteddata[0] = (object)array('type' => 'campusconnect/courselinks',
                                      'resourceid' => "$eid",
                                      'serverid' => -1,
                                      'status' => campusconnect_event::STATUS_CREATED);
        $expecteddata[1] = clone $expecteddata[0];
        $expecteddata[1]->status = campusconnect_event::STATUS_UPDATED;
        $expecteddata[2] = clone $expecteddata[0];
        $expecteddata[2]->status = campusconnect_event::STATUS_DESTROYED;

        foreach ($expecteddata as $timing => $data) {
            $DB->expectAt($timing, 'insert_record', array('local_campusconnect_eventin', $data));
        }
        $DB->expectCallCount('insert_record', count($expecteddata));

        // Check there is an event in the queue on the server.
        $result = $this->connect[2]->read_event_fifo();
        $this->assertIsA($result, 'array');

        // Check the event is transferred correctly into the queue.
        // Expect first 'insert_record' call
        $queue->update_from_ecs($this->connect[2]);

        // Check there are no events in the queue any more.
        $result = $this->connect[2]->read_event_fifo();
        $this->assertEqual($result, false);

        // Update the resource.
        $this->connect[1]->update_resource($eid, $post2, $community);

        // Check there is an event in the queue on the server.
        $result = $this->connect[2]->read_event_fifo();
        $this->assertIsA($result, 'array');

        // Check the event is transferred correctly into the queue.
        // Expect second 'insert_record' call
        $queue->update_from_ecs($this->connect[2]);

        // Check there are no events in the queue any more.
        $result = $this->connect[2]->read_event_fifo();
        $this->assertEqual($result, false);

        // Delete the resource.
        $this->connect[1]->delete_resource($eid);

        // Check there is an event in the queue on the server.
        $result = $this->connect[2]->read_event_fifo();
        $this->assertIsA($result, 'array');

        // Check the event is transferred correctly into the queue.
        // Expect third 'insert_record' call
        $queue->update_from_ecs($this->connect[2]);

        // Check there are no events in the queue any more.
        $result = $this->connect[2]->read_event_fifo();
        $this->assertEqual($result, false);
    }

    public function test_update_from_ecs_create_two() {
        global $DB;

        $url = 'http://www.example.com/test123/';
        $post = (object)array('url' => $url);
        $post = json_encode($post);
        $url2 = 'http://www.example.com/test456/';
        $post2 = (object)array('url' => $url2);
        $post2 = json_encode($post2);
        $community = 'unittest';
        $queue = new campusconnect_receivequeue();

        $DB->expectNever('update_record');
        $DB->expectNever('delete_records');

        // Create two resources.
        $eid = $this->connect[1]->add_resource($post, $community);
        $eid2 = $this->connect[1]->add_resource($post2, $community);

        // Check there is at least one event in the queue on the server.
        $result = $this->connect[2]->read_event_fifo();
        $this->assertIsA($result, 'array');

        // Check the event is transferred correctly into the queue.
        $expecteddata = (object)array('type' => 'campusconnect/courselinks',
                                      'resourceid' => "$eid",
                                      'serverid' => -1,
                                      'status' => campusconnect_event::STATUS_CREATED);
        $DB->expectAt(0, 'insert_record', array('local_campusconnect_eventin', $expecteddata));
        $expecteddata2 = (object)array('type' => 'campusconnect/courselinks',
                                      'resourceid' => "$eid2",
                                      'serverid' => -1,
                                      'status' => campusconnect_event::STATUS_CREATED);
        $DB->expectAt(1, 'insert_record', array('local_campusconnect_eventin', $expecteddata2));
        $DB->expectCallCount('insert_record', 2);
        $DB->setReturnValue('insert_record', 1);
        $queue->update_from_ecs($this->connect[2]);

        // Check there are no events in the queue any more.
        $result = $this->connect[2]->read_event_fifo();
        $this->assertEqual($result, false);
    }
}