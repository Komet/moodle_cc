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
 * Tests for the course export processing for CampusConnect
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

require_once($CFG->dirroot.'/local/campusconnect/export.php');
require_once($CFG->dirroot.'/local/campusconnect/simpletest/enabledtests.php');

class local_campusconnect_export_test extends UnitTestCase {
    protected $settings = array();
    protected $connect = array();
    protected $mid = array();
    protected $resources = array();
    protected $community = null;

    public function skip() {
        $this->skipIf(defined('SKIP_CAMPUSCONNECT_EXPORT_TESTS'), 'Skipping export tests, to save time');
    }

    public function setUp() {
        // Create the connections for testing.
        $names = array(1 => 'unittest1', 2 => 'unittest2', 3 => 'unittest3');
        foreach ($names as $key => $name) {
            $this->settings[$key] = new campusconnect_ecssettings(null, $name);
            $this->connect[$key] = new campusconnect_connect($this->settings[$key]);
        }

        // Retrieve the mid values for each participant.
        foreach ($this->connect as $key => $connect) {
            $memberships = $connect->get_memberships();
            foreach ($memberships[0]->participants as $participant) {
                if ($participant->itsyou) {
                    $this->mid[$key] = $participant->mid;
                }
            }
        }

        // Enable export from ecs1 to each of the participants.
        foreach ($names as $key => $name) {
            $part = new campusconnect_participantsettings($this->settings[1]->get_id(), $this->mid[$key]);
            $part->save_settings(array('export' => true));
        }

        // Data for test resources to create
        $this->resources[1] = (object)array('url' => 'http://www.example.com/test123',
                                            'title' => 'Course from ECS',
                                            'organization' => 'Synergy Learning',
                                            'lang' => 'en',
                                            'semesterHours' => '5',
                                            'courseID' => 'course5:220',
                                            'term' => 'WS 06/07',
                                            'credits' => '10',
                                            'status' => 'online',
                                            'courseType' => 'Vorlesung');

        $this->resources[2] = (object)array('url' => 'http://www.example.com/test456');

        // General settings used by the tests
        $this->community = 'unittest';
     }

    public function tearDown() {
        // Delete all resources (just in case).
        foreach ($this->connect as $connect) {
            $courselinks = $connect->get_resource_list();
            foreach ($courselinks->get_ids() as $eid) {
                // All courselinks were created by 'unittest1'.
                $this->connect[1]->delete_resource($eid);
            }
        }

        // Delete all events & participants.
        foreach ($this->connect as $connect) {
            while ($connect->read_event_fifo(true));
            $connect->get_settings()->delete();
        }

        $this->connect = array();
        $this->mid = array();
    }

    public function test_list_participants() {
        $export = new campusconnect_export(-10);
        $participants = $export->list_participants();

        $this->assertTrue(count($participants) >= 3, 'Should be at least 3 participants to export to');
        $found = array(1 => false, 2 => false, 3 => false);
        foreach ($participants as $part) {
            if ($part->get_ecs_id() == $this->settings[1]->get_id()) {
                $idx = array_search($part->get_mid(), $this->mid);
                $this->assertTrue($idx !== false, 'Found potential export participant in this ECS that is not in expected list');
                $found[$idx] = true;
            }
        }
        foreach ($found as $idx => $ok) {
            $this->assertTrue($ok, "Participant $idx not found in the list of potential participants");
        }
    }

    public function test_set_export() {
        $export = new campusconnect_export(-10);
        // Check this course is exported to no participants at the start.
        $this->assertFalse($export->is_exported(), 'Course should not currently be exported');
        $this->assertEqual(count($export->list_current_exports()), 0, 'Course exported participants list should be empty');

        // Check exporting to one participant works as expected.
        $potentialexports = $export->list_participants();
        $potential = array();
        foreach ($potentialexports as $part) {
            // Ignore any potential exports that are not part of the unit-testing environment.
            if ($part->get_ecs_id() == $this->settings[1]->get_id()) {
                $potential[] = $part;
            }
        }
        $export->set_export($potential[0]->get_identifier(), true);
        $this->assertTrue($export->is_exported(), 'Course should now be marked as exported');
        $exports = $export->list_current_exports();
        $this->assertEqual(count($exports), 1, 'Course should be exported to one participant only');
        $this->assertTrue(isset($exports[$potential[0]->get_identifier()]), 'Expected the export to match the participant we exported to');
        $potentialexports = $export->list_participants();
        foreach ($potentialexports as $key => $part) {
            // Ignore any potential exports that are not part of the unit-testing environment.
            if ($part->get_ecs_id() == $this->settings[1]->get_id()) {
                if ($part->get_mid() == $potential[0]->get_mid()) {
                    $this->assertTrue($part->is_exported(), 'Expected this participant to be exported to');
                } else {
                    $this->assertFalse($part->is_exported(), 'Expected this participant to NOT be exported to');
                }
            }
        }

        // Check that re-loading the export settings works
        $export = new campusconnect_export(-10);
        $this->assertTrue($export->is_exported(), 'Course should now be marked as exported');
        $exports = $export->list_current_exports();
        $this->assertEqual(count($exports), 1, 'Course should be exported to one participant only');
        $this->assertTrue(isset($exports[$potential[0]->get_identifier()]), 'Expected the export to match the participant we exported to');
        $potentialexports = $export->list_participants();
        foreach ($potentialexports as $key => $part) {
            // Ignore any potential exports that are not part of the unit-testing environment.
            if ($part->get_ecs_id() == $this->settings[1]->get_id()) {
                if ($part->get_mid() == $potential[0]->get_mid()) {
                    $this->assertTrue($part->is_exported(), 'Expected this participant to be exported to');
                } else {
                    $this->assertFalse($part->is_exported(), 'Expected this participant to NOT be exported to');
                }
            }
        }

        // Check that setting a second setting works.
        $export->set_export($potential[1]->get_identifier(), true);
        $this->assertTrue($export->is_exported(), 'Course should now be marked as exported');
        $exports = $export->list_current_exports();
        $this->assertEqual(count($exports), 2, 'Course should be exported to two participants');
        $this->assertTrue(isset($exports[$potential[0]->get_identifier()]), 'Expected the export to match the participant we exported to');
        $this->assertTrue(isset($exports[$potential[1]->get_identifier()]), 'Expected the export to match the participant we exported to');

        // Check that clearing a setting works.
        $export->set_export($potential[0]->get_identifier(), false);
        $this->assertTrue($export->is_exported(), 'Course should now be marked as exported');
        $exports = $export->list_current_exports();
        $this->assertEqual(count($exports), 1, 'Course should be exported to two participants');
        $this->assertTrue(isset($exports[$potential[1]->get_identifier()]), 'Expected the export to match the participant we exported to');

        // Check that clearing both settings works.
        $export->set_export($potential[1]->get_identifier(), false);
        $this->assertFalse($export->is_exported(), 'Course should now be marked as NOT exported');
        $exports = $export->list_current_exports();
        $this->assertEqual(count($exports), 0, 'Course should be exported to two participants');
    }

    public function test_clear_exports() {
        $export = new campusconnect_export(-10);

        $potentialexports = $export->list_participants();
        $potential = array();
        foreach ($potentialexports as $part) {
            // Ignore any potential exports that are not part of the unit-testing environment.
            if ($part->get_ecs_id() == $this->settings[1]->get_id()) {
                $potential[] = $part;
            }
        }
        // Set the exports.
        $export->set_export($potential[0]->get_identifier(), true);
        $export->set_export($potential[1]->get_identifier(), true);

        // Clear the exports.
        $export->clear_exports();

        // Check the export list is immediately empty.
        $this->assertFalse($export->is_exported(), 'Course should now be marked as NOT exported');
        $exports = $export->list_current_exports();
        $this->assertEqual(count($exports), 2, 'Course should be exported to two participants');

        // Check the export list is empty after reloading it.
        $export = new campusconnect_export(-10);
        $this->assertFalse($export->is_exported(), 'Course should now be marked as NOT exported');
        $exports = $export->list_current_exports();
        $this->assertEqual(count($exports), 0, 'Course should be exported to two participants');
    }

    public function test_update_ecs_empty() {
        // Check that there are no courses currently exported.
        $result = $this->connect[2]->get_resource_list();
        $this->assertFalse($result->get_ids(), 'Expected there to be no exported courses');

        // Update the ECS with exported courses - should be nothing to export.
        campusconnect_export::update_ecs($this->connect[1]);
        $result = $this->connect[2]->get_resource_list();
        $this->assertFalse($result->get_ids(), 'Expected there to still be no exported courses');
    }

    public function test_update_ecs_exported() {
        global $CFG;

        $export = new campusconnect_export(-10);

        $exportcourse = (object)array('id' => -10,
                                      'fullname' => 'testexport',
                                      'shortname' => 'testexport',
                                      'startdate' => mktime(12, 0, 0, 4, 1, 2012));
        $coursedata = array(-10 => $exportcourse);

        $potentialexports = $export->list_participants();
        $potential = array();
        foreach ($potentialexports as $part) {
            // Ignore any potential exports that are not part of the unit-testing environment.
            if ($part->get_ecs_id() == $this->settings[1]->get_id()) {
                $idx = array_search($part->get_mid(), $this->mid);
                $this->assertTrue($idx !== false, 'Unexpected participant in the unit test community');
                $potential[$idx] = $part;
            }
        }
        // Export the course from ECS 1 to ECS 2.
        $export->set_export($potential[2]->get_identifier(), true);

        // Check there are still no exported courses.
        $result = $this->connect[2]->get_resource_list();
        $this->assertFalse($result->get_ids(), 'Expected there to be no exported courses for ECS 2');
        $result = $this->connect[3]->get_resource_list();
        $this->assertFalse($result->get_ids(), 'Expected there to be no exported courses for ECS 3');

        // Update the ECS.
        campusconnect_export::update_ecs($this->connect[1], $coursedata);

        // Check the expected course is now available.
        $result = $this->connect[3]->get_resource_list();
        $this->assertFalse($result->get_ids(), 'Expected there to still be no exported courses for ECS 3');
        $result = $this->connect[2]->get_resource_list();
        $ids = $result->get_ids();
        $this->assertEqual(count($ids), 1, 'Expected there to now be exported courses for ECS 2');
        $result = $this->connect[2]->get_resource($ids[0], false);

        $this->assertIsA($result, 'stdClass');
        $this->assertEqual($result->url, $CFG->wwwroot.'/course/view.php?id=-10', "Unexpected URL: {$result->url}");
        $this->assertEqual($result->title, $exportcourse->fullname, 'Exported title does not match the course fullname');
        $this->assertEqual($result->firstDate, '2012-04-1T12:00:00+0100', "Exported firstDate timestamp ({$result->firstDate}) does not match");

        // Check that removing the course from export works.
        $export = new campusconnect_export(-10); // Need to create a new object, otherwise changes from 'update_ecs' not recorded.
        $export->set_export($potential[2]->get_identifier(), false);
        campusconnect_export::update_ecs($this->connect[1], $coursedata);

        // Check the course is no longer available.
        $result = $this->connect[2]->get_resource_list();
        $this->assertFalse($result->get_ids(), 'Expected there to be no exported courses for ECS 2');
        $result = $this->connect[3]->get_resource_list();
        $this->assertFalse($result->get_ids(), 'Expected there to be no exported courses for ECS 3');
    }
}