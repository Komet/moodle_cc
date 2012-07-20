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
 * Tests for main connection class for CampusConnect
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

require_once($CFG->dirroot.'/local/campusconnect/connect.php');
require_once($CFG->dirroot.'/local/campusconnect/simpletest/enabledtests.php');

class local_campusconnect_connect_test extends UnitTestCase {
    protected $connect = array();
    protected $mid = array();

    public function skip() {
        $this->skipIf(defined('SKIP_CAMPUSCONNECT_CONNECT_TESTS'), 'Skipping connect tests, to save time');
    }

    public function setUp() {
        // Create the connections for testing
        $names = array(1 => 'unittest1', 2 => 'unittest2', 3 => 'unittest3');
        foreach ($names as $key => $name) {
            $settings = new campusconnect_ecssettings(null, $name);
            $this->connect[$key] = new campusconnect_connect($settings);
        }

        // Retrieve the mid values for each participant
        foreach ($this->connect as $key => $connect) {
        	var_dump($connect);
        	print '<br />';
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
        // Delete all resources (just in case)
        foreach ($this->connect as $connect) {
            $courselinks = $connect->get_resource_list(campusconnect_export::RES_COURSELINK);
            foreach ($courselinks->get_ids() as $eid) {
                // All courselinks were created by 'unittest1'
                $this->connect[1]->delete_resource($eid, campusconnect_event::RES_COURSELINK);
            }
        }

        // Delete all events
        foreach ($this->connect as $connect) {
            while ($connect->read_event_fifo(true));
        }

        $this->connect = array();
        $this->mid = array();
    }

    public function test_get_memberships() {
        $result = $this->connect[1]->get_memberships();

        // Test that 'unittest1' is a member of only the community 'unittest'
        // with 3 other participants
        $this->assertIsA($result, 'Array');
        $this->assertEqual(count($result), 1);
        $this->assertEqual($result[0]->community->name, 'unittest');
        $this->assertEqual(count($result[0]->participants), 3);

        // Test that the 3 unittest participants are found in the community
        // and that 'Unit test 1' is identified as 'unittest1'
        $found = array(1 => false, 2 => false, 3 => false);
        foreach ($result[0]->participants as $participant) {
            if ($participant->name == 'Unit test 1') {
                $this->assertEqual($participant->itsyou, 1);
                $found[1] = true;
            } else if ($participant->name == 'Unit test 2') {
                $found[2] = true;
            } else if ($participant->name == 'Unit test 3') {
                $found[3] = true;
            }
        }
        foreach ($found as $participantfound) {
            $this->assertTrue($participantfound);
        }
    }

    public function test_auth() {
        $url = 'http://www.example.com/test123/';
        $post = (object)array('url' => $url);

        // Retrieve an auth hash for connecting from 'unittest1' to 'unittest2'
        $hash = $this->connect[1]->add_auth($post, $this->mid[2]);

        // Test that 'unittest1' can confirm this hash
        $result = $this->connect[1]->get_auth($hash);
        $this->assertEqual($result->hash, $hash);
        $this->assertEqual($result->url, $url);

        // Test that 'unittest2' can also confirm this hash
        $result = $this->connect[2]->get_auth($hash);
        $this->assertEqual($result->hash, $hash);
        $this->assertEqual($result->url, $url);

        // Test that 'unittest3' cannot retrieve this hash
        $this->expectException('campusconnect_connect_exception');
        $result = $this->connect[3]->get_auth($hash);
    }

    public function test_add_delete_resource() {
        $url = 'http://www.example.com/test123/';
        $post = (object)array('url' => $url);
        $community = 'unittest';

        // Add the resource - the response should be an integer > 0
        $eid = $this->connect[1]->add_resource(campusconnect_event::RES_COURSELINK, $post, $community);
        $this->assertIsA($eid, 'integer');
        $this->assertTrue($eid > 0);

        // Get the resource - should match the details specified at the top of this function
        $result = $this->connect[1]->get_resource($eid, campusconnect_event::RES_COURSELINK);
        $this->assertIsA($result, 'stdClass');
        $this->assertEqual($result->url, $url);

        // Get the resource details - should be sent / owned by mid[1] and received by mid[2] & mid[3]
        $result = $this->connect[1]->get_resource($eid, campusconnect_event::RES_COURSELINK, true);
        $this->assertIsA($result, 'stdClass');
        $this->assertEqual($result->senders[0]->mid, $this->mid[1]);
        $found = array(2 => false, 3 => false);
        foreach ($result->receivers as $receiver) {
            foreach ($this->mid as $idx => $mid) {
                if ($mid == $receiver->mid) {
                    $found[$idx] = true;
                    break;
                }
            }
        }
        foreach ($found as $foundparticipant) {
            $this->assertTrue($foundparticipant);
        }

        // Delete the resource
        $result = $this->connect[1]->delete_resource($eid, campusconnect_event::RES_COURSELINK);

        // Check the resource does not exist any more
        $this->expectException('campusconnect_connect_exception');
        $result = $this->connect[1]->get_resource($eid, campusconnect_event::RES_COURSELINK, false);
    }

    public function test_read_event_fifo() {
        $url = 'http://www.example.com/test123/';
        $post = (object)array('url' => $url);
        $community = 'unittest';

        // Check the event queue is empty
        $result = $this->connect[2]->read_event_fifo();
        $this->assertFalse($result);

        // Add a resource
        $eid = $this->connect[1]->add_resource(campusconnect_event::RES_COURSELINK, $post, $community);

        // Check there is a create event in the queue
        $result = $this->connect[2]->read_event_fifo();
        $this->assertIsA($result, 'array');
        $this->assertEqual(count($result), 1);
        $this->assertIsA($result[0], 'stdClass');
        $this->assertEqual($result[0]->status, 'created');
        $this->assertEqual($result[0]->ressource, "campusconnect/courselinks/$eid");

        // Check the event is still there if not deleted
        $result = $this->connect[2]->read_event_fifo();
        $this->assertIsA($result, 'array');
        $this->assertEqual(count($result), 1);
        $this->assertIsA($result[0], 'stdClass');
        $this->assertEqual($result[0]->status, 'created');
        $this->assertEqual($result[0]->ressource, "campusconnect/courselinks/$eid");

        // Check the event queue is empty after deletion
        $result = $this->connect[2]->read_event_fifo(true);
        $result = $this->connect[2]->read_event_fifo();
        $this->assertFalse($result);

        // Delete the resource
        $this->connect[1]->delete_resource($eid, campusconnect_event::RES_COURSELINK);

        // Check there is now a deleted event
        $result = $this->connect[2]->read_event_fifo();
        $this->assertIsA($result, 'array');
        $this->assertEqual(count($result), 1);
        $this->assertIsA($result[0], 'stdClass');
        $this->assertEqual($result[0]->status, 'destroyed');
        $this->assertEqual($result[0]->ressource, "campusconnect/courselinks/$eid");
    }

    public function test_get_resource_list() {
        $url = 'http://www.example.com/test123/';
        $post = (object)array('url' => $url);

        // Check the resource list is empty to begin with
        $result = $this->connect[2]->get_resource_list(campusconnect_export::RES_COURSELINK);
        $this->assertIsA($result, 'campusconnect_uri_list');
        $this->assertFalse($result->get_ids());
        $result = $this->connect[3]->get_resource_list(campusconnect_export::RES_COURSELINK);
        $this->assertFalse($result->get_ids());

        // Add a resource (only shared with 'unittest2')
        $eid = $this->connect[1]->add_resource(campusconnect_event::RES_COURSELINK, $post, null, $this->mid[2]);

        // Check 'unittest2' can see the new resource, but not 'unittest3'
        $result = $this->connect[2]->get_resource_list(campusconnect_export::RES_COURSELINK);
        $ids = $result->get_ids();
        $this->assertEqual(count($ids), 1);
        $this->assertEqual($ids[0], $eid);
        $result = $this->connect[3]->get_resource_list(campusconnect_export::RES_COURSELINK);
        $this->assertFalse($result->get_ids());

        // Delete the resource
        $this->connect[1]->delete_resource($eid, campusconnect_event::RES_COURSELINK);

        // Check 'unittest2' can no longer see the resource
        $result = $this->connect[2]->get_resource_list(campusconnect_export::RES_COURSELINK);
        $this->assertFalse($result->get_ids());
    }

    public function test_update_resource() {
        $url = 'http://www.example.com/test123/';
        $post = (object)array('url' => $url);
        $community = 'unittest';

        $url2 = 'http://www.example.com/updatetesting/';
        $post2 = (object)array('url' => $url2);

        // Add a resource
        $eid = $this->connect[1]->add_resource(campusconnect_event::RES_COURSELINK, $post, $community);

        // Get the resource - should match the details specified at the top of this function
        $result = $this->connect[2]->get_resource($eid, campusconnect_event::RES_COURSELINK, false);
        $this->assertIsA($result, 'stdClass');
        $this->assertEqual($result->url, $url);

        // Update the resource
        $result = $this->connect[1]->update_resource($eid, campusconnect_event::RES_COURSELINK, $post2, $community);

        // Get the resource - should match the second set of details
        $result = $this->connect[2]->get_resource($eid, campusconnect_event::RES_COURSELINK, false);
        $this->assertIsA($result, 'stdClass');
        $this->assertEqual($result->url, $url2);

        // Double-check 'unittest2' cannot update the resource
        $this->expectException('campusconnect_connect_exception');
        $result = $this->connect[2]->update_resource($eid, campusconnect_event::RES_COURSELINK, $post2, $community);

        // Delete the resource
        $this->connect[1]->delete_resource($eid, campusconnect_event::RES_COURSELINK);
    }
}
