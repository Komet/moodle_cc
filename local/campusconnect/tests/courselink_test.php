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
 * Test course link creation + authentication
 *
 * @package    local_campusconnect
 * @copyright  2014 Davo Smith, Synergy Learning
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
global $CFG;
require_once($CFG->dirroot.'/local/campusconnect/connect.php');
require_once($CFG->dirroot.'/local/campusconnect/courselink.php');

/**
 * Class local_campusconnect_courselink_test
 * @group local_campusconnect
 */
class local_campusconnect_courselink_test extends advanced_testcase {
    /**
     * @var campusconnect_connect[]
     */
    protected $connect = array();
    /**
     * @var integer[]
     */
    protected $mid = array();

    protected function setUp() {
        global $CFG;

        require_once($CFG->dirroot.'/auth/campusconnect/auth.php');

        $this->resetAfterTest();

        // Create the connections for testing.
        $names = array(1 => 'unittest1', 2 => 'unittest2', 3 => 'unittest3');
        foreach ($names as $key => $name) {
            $category = $this->getDataGenerator()->create_category(array('name' => 'import'.$key));
            $ecs = new campusconnect_ecssettings();
            $ecs->save_settings(array(
                                     'url' => 'http://localhost:3000',
                                     'auth' => campusconnect_ecssettings::AUTH_NONE,
                                     'ecsauth' => $name,
                                     'importcategory' => $category->id,
                                     'importrole' => 'student',
                                ));
            $this->connect[$key] = new campusconnect_connect($ecs);
        }

        // Retrieve the mid values for each participant.
        foreach ($this->connect as $key => $connect) {
            // Make sure all data structures are initialised.
            campusconnect_participantsettings::load_communities($this->connect[1]->get_settings());

            $memberships = $connect->get_memberships();
            foreach ($memberships[0]->participants as $participant) {
                if ($participant->itsyou) {
                    $this->mid[$key] = $participant->mid;
                    break;
                }
            }
        }
    }

    protected function tearDown() {
        // Delete all resources (just in case).
        foreach ($this->connect as $connect) {
            $courselinks = $connect->get_resource_list(campusconnect_event::RES_COURSELINK);
            foreach ($courselinks->get_ids() as $eid) {
                // All courselinks were created by 'unittest1'.
                $this->connect[1]->delete_resource($eid, campusconnect_event::RES_COURSELINK);
            }
        }

        // Delete all events.
        foreach ($this->connect as $connect) {
            while ($connect->read_event_fifo(true));
        }

        $this->connect = array();
        $this->mid = array();
    }

    protected function setup_courselink() {
        global $DB;

        // Course link from 'unittest1' => 'unittest2'.
        $part1 = new campusconnect_participantsettings($this->connect[1]->get_ecs_id(), $this->mid[2]);
        $part1->save_settings(array('export' => true));
        $part2 = new campusconnect_participantsettings($this->connect[2]->get_ecs_id(), $this->mid[1]);
        $part2->save_settings(array('import' => true, 'importtype' => campusconnect_participantsettings::IMPORT_LINK));

        // Check there are currently no course links on 'unittest2'.
        $courselinks = $DB->get_records('local_campusconnect_clink', array('ecsid' => $this->connect[2]->get_ecs_id(),
                                                                           'mid' => $this->mid[1]));
        $this->assertEmpty($courselinks);

        // Generate a course + export it to 'unittest2'.
        $srccourse = $this->getDataGenerator()->create_course(
            array('fullname' => 'test full name', 'shortname' => 'test short name')
        );
        $export = new campusconnect_export($srccourse->id);
        $export->set_export($part1->get_identifier(), true);

        // Run the updates.
        campusconnect_export::update_ecs($this->connect[1]); // Export.
        campusconnect_courselink::refresh_from_participant($this->connect[2]->get_ecs_id(), $this->mid[1]); // Import.

        // Retrieve the courselinks on 'unittest2'.
        $courselinks = $DB->get_records('local_campusconnect_clink', array('ecsid' => $this->connect[2]->get_ecs_id(),
                                                                           'mid' => $this->mid[1]));
        $this->assertCount(1, $courselinks); // Should only have imported 1 course link.
        $courselink = reset($courselinks);
        $dstcourseid = $courselink->courseid; // The course that represents the course link on 'unittest1'.
        $course = $DB->get_record('course', array('id' => $dstcourseid));
        $this->assertEquals('test full name', $course->fullname); // Make sure the correct course link has been created.
        // Cannot test the shortname, as that will have been renamed for conflicting with the original course shortname.

        return array($dstcourseid, $part1, $part2);
    }

    public function test_courselink_authentication() {
        global $USER;

        list($dstcourseid, , ) = $this->setup_courselink();

        // Generate a URL on 'unittest2'.
        $authuser = $this->getDataGenerator()->create_user(
            array('firstname' => 'firstname1',
                  'lastname' => 'lastname1',
                  'email' => 'testuser1@example.com',
                  'username' => 'firstname1.lastname1')
        );
        $olduser = $USER;
        $USER = clone $authuser;
        $url = campusconnect_courselink::check_redirect($dstcourseid);
        $USER = $olduser;
        $this->assertNotEquals(false, $url); // Make sure this is correctly identified as a course link.

        // Authenticate the URL on 'unittest1'.
        $userdetails = auth_plugin_campusconnect::authenticate_from_url($url);
        $this->assertNotNull($userdetails); // Check the user has authenticated correctly.
        foreach (array('firstname', 'lastname', 'email') as $fieldname) { // Make sure all user details transferred as expected.
            $this->assertEquals($authuser->$fieldname, $userdetails->$fieldname);
        }

        // Generate a second URL on 'unittest2'.
        $authuser->firstname = 'firstname2';
        $authuser->lastname = 'lastname2';
        $authuser->email = 'testuser2@example.com';
        $olduser = $USER;
        $USER = clone $authuser;
        $url = campusconnect_courselink::check_redirect($dstcourseid);
        $USER = $olduser;
        $this->assertNotEquals(false, $url); // Make sure this is correctly identified as a course link.

        // Authenticate this URL and check that the same username is retrieved and the details have been updated correctly.
        $userdetails2 = auth_plugin_campusconnect::authenticate_from_url($url);
        $this->assertNotNull($userdetails2);
        $this->assertEquals($userdetails->username, $userdetails2->username); // Should be matched up to the same username.
        foreach (array('firstname', 'lastname', 'email') as $fieldname) { // Make sure all user details updated.
            $this->assertEquals($authuser->$fieldname, $userdetails2->$fieldname);
        }
    }

    public function test_token_settings() {
        global $USER;

        $authuser = $this->getDataGenerator()->create_user(
            array('firstname' => 'firstname1',
                  'lastname' => 'lastname1',
                  'email' => 'testuser1@example.com',
                  'username' => 'firstname1.lastname1')
        );
        list($dstcourseid, $part1, $part2) = $this->setup_courselink();

        // Make sure the token data is ignored when disabled on the receiving site.
        /** @var campusconnect_participantsettings $part1 */
        $part1->save_settings(array('exporttoken' => false)); // Disable handling of token for exported courselinks.
        $olduser = $USER;
        $USER = clone $authuser;
        $url = campusconnect_courselink::check_redirect($dstcourseid);
        $USER = $olduser;

        $this->assertNotEquals(false, $url); // Make sure this is correctly identified as a course link.
        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        $this->assertArrayHasKey('ecs_hash', $params); // Make sure the 'ecs_hash' has been added.

        $userdetails = auth_plugin_campusconnect::authenticate_from_url($url);
        $this->assertNull($userdetails); // Check that the authentication is ignored.

        // Make sure the token data is not generated when disabled on the sending site.
        /** @var campusconnect_participantsettings $part2 */
        $part2->save_settings(array('importtoken' => false)); // Disable sending of token for imported courselinks.
        $olduser = $USER;
        $USER = clone $authuser;
        $url = campusconnect_courselink::check_redirect($dstcourseid);
        $USER = $olduser;

        $this->assertNotEquals(false, $url); // Make sure this is correctly identified as a course link.
        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        $this->assertCount(1, $params);
        $this->assertArrayHasKey('id', $params); // Check the URL only has the courseid and no other details have been added.
    }
}
