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
 * Tests for metadata mapping for CampusConnect
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/campusconnect/metadata.php');

class local_campusconnect_metadata_test extends UnitTestCase {
    public function setUp() {
        // Rename all 'map?_*' config values to 'unittest_*'
        $config = get_config('local_campusconnect');
        foreach ($config as $name => $value) {
            if (substr_compare($name, 'unittest_', 0, 9) == 0) {
                continue; // Skip renaming ones that are already renamed
            }
            if ((substr_compare($name, 'mapi_', 0, 5) == 0) ||
                (substr_compare($name, 'mape_', 0, 5) == 0)) {
                set_config('unittest_'.$name, $value, 'local_campusconnect');
                unset_config($name, 'local_campusconnect');
            }
        }
    }

    public function tearDown() {
        // Put all config values back to their original values
        $config = get_config('local_campusconnect');
        foreach ($config as $name => $value) {
            if ((substr_compare($name, 'mapi_', 0, 5) == 0) ||
                (substr_compare($name, 'mape_', 0, 5) == 0)) {
                unset_config($name, 'local_campusconnect');
            }
        }
        foreach ($config as $name => $value) {
            if (substr_compare($name, 'unittest_', 0, 9) == 0) {
                $origname = substr($name, 9);
                set_config($origname, $value, 'local_campusconnect');
                unset_config($name, 'local_campusconnect');
            }
        }
    }

    public function test_set_import_mapping() {
        $defaultmappings = array('fullname' => '{title}', 'shortname' => '{title}', 'idnumber' => '', 'startdate' => 'begin',
                                 'lang' => 'lang', 'timecreated' => '', 'timemodified' => '');

        // Test the default settings.
        $meta = new campusconnect_metadata();
        $mappings = $meta->get_import_mappings();
        unset($mappings['summary']); // Default summary is fiddly to test
        $this->assertEqual($mappings, $defaultmappings, 'Expected get_import_mappings to return the default settings');

        // Test setting and immediately retrieving.
        $testmappings = $defaultmappings;
        $testmappings['fullname'] = '{title} - {organization} - {begin}';
        $testmappings['summary'] = 'Title: {title}';
        $this->assertTrue($meta->set_import_mappings($testmappings), 'Error whilst calling set_import_mappings');
        $mappings = $meta->get_import_mappings();
        $this->assertEqual($mappings, $testmappings, 'Expected get_import_mappings to return the mappings just set');

        // Test retrieving from new object.
        $meta = new campusconnect_metadata();
        $mappings = $meta->get_import_mappings();
        $this->assertEqual($mappings, $testmappings, 'Expected get_import_mappings to return the mappings previously set');

        // Test setting individual fields.
        $testmappings['shortname'] = '{courseID} - {title}';
        $this->assertTrue($meta->set_import_mapping('shortname', $testmappings['shortname']), 'Error whilst calling set_import_mapping');
        $mappings = $meta->get_import_mappings();
        $this->assertEqual($mappings, $testmappings, 'Expected set_import_mapping to update the shortname correctly');

        // Test retrieving from new object.
        $meta = new campusconnect_metadata();
        $mappings = $meta->get_import_mappings();
        $this->assertEqual($mappings, $testmappings, 'Expected get_import_mappings to return the mappings previously set');

        // Test setting invalid mapping.
        $this->expectException('coding_exception', "Should not be able to map the remote 'title' field onto the local 'startdate' field");
        $meta->set_import_mapping('startdate', 'title');
        $mappings = $meta->get_import_mappings();
        $this->assertEqual($mappings, $testmappings, "Able to set an invalid mapping 'startdate' = 'title'");

        // Test setting string with invalid placeholder.
        $this->assertFalse($meta->set_import_mapping('summary', '{title} - {fishfinger} - {begin}'), "Should not be able to include invalid fields in 'summary' mapping");
        list($errfield, $errstr) = $meta->get_last_error();
        $this->assertEqual($errfield, 'summary', "Expected an error in the 'summary' field");
        $mappings = $meta->get_import_mappings();
        $this->assertEqual($mappings, $testmappings, "Able to set an invalid mapping for 'summary' field");
    }

    public function test_set_export_mapping() {
        $defaultmappings = array('organization' => '', 'lang' => 'lang', 'semesterHours' => '',
                                 'courseID' => '', 'term' => '', 'credits' => '', 'status' => '',
                                 'title' => '{fullname}', 'room' => '', 'cycle' => '', 'begin' => 'startdate',
                                 'end' => '', 'study_courses' => '', 'lecturer' => '');

        // Test the default settings.
        $meta = new campusconnect_metadata();
        $mappings = $meta->get_export_mappings();
        $this->assertEqual($mappings, $defaultmappings, 'Expected get_export_mappings to return the default settings');

        // Test setting and immediately retrieving.
        $testmappings = $defaultmappings;
        $testmappings['title'] = '{fullname} - {shortname}';
        $testmappings['courseID'] = '{idnumber}';
        $testmappings['begin'] = '';
        $testmappings['end'] = 'timecreated';
        $this->assertTrue($meta->set_export_mappings($testmappings), 'Error whilst calling set_export_mappings');
        $mappings = $meta->get_export_mappings();
        $this->assertEqual($mappings, $testmappings, 'Expected get_export_mappings to return the mappings just set');

        // Test retrieving from new object.
        $meta = new campusconnect_metadata();
        $mappings = $meta->get_export_mappings();
        $this->assertEqual($mappings, $testmappings, 'Expected get_export_mappings to return the mappings previously set');

        // Test setting individual fields.
        $testmappings['title'] = '{idnumber} - {shortname}';
        $this->assertTrue($meta->set_export_mapping('title', $testmappings['title']), 'Error whilst calling set_export_mapping');
        $mappings = $meta->get_export_mappings();
        $this->assertEqual($mappings, $testmappings, 'Expected set_export_mapping to update the shortname correctly');

        // Test retrieving from new object.
        $meta = new campusconnect_metadata();
        $mappings = $meta->get_export_mappings();
        $this->assertEqual($mappings, $testmappings, 'Expected get_export_mappings to return the mappings previously set');

        // Test setting invalid mapping.
        $this->expectException('coding_exception', "Should not be able to map the local 'fullname' field onto the remote 'begin' field");
        $meta->set_export_mapping('begin', 'fullname');
        $mappings = $meta->get_export_mappings();
        $this->assertEqual($mappings, $testmappings, "Able to set an invalid mapping 'begin' = 'fullname'");

        // Test setting string with invalid placeholder.
        $this->assertFalse($meta->set_export_mapping('title', '{title} - {fishfinger} - {begin}'), "Should not be able to include invalid fields in 'title' mapping");
        list($errfield, $errstr) = $meta->get_last_error();
        $this->assertEqual($errfield, 'title', "Expected an error in the 'title' field");
        $mappings = $meta->get_export_mappings();
        $this->assertEqual($mappings, $testmappings, "Able to set an invalid mapping for 'title' field");
    }

    public function test_map_remote_to_course() {
        $mappings = array('fullname' => 'Title: {title}', 'shortname' => '{title}', 'idnumber' => '{courseID}', 'startdate' => 'begin',
                          'lang' => 'lang', 'timecreated' => '', 'timemodified' => '',
                          'summary' => 'Org: {organization}, Begin: {begin}');

        $timeplace = (object)array('room' => 'Room 101', 'cycle' => 'week', 'begin' => '2012-06-20T14:48:00+01:00', 'end' => '2012-06-30T15:00:00+01:00');
        $lecturer = array('Prof. Plum', 'C. Mustard');
        $remotedata = (object)array('organization' => 'Test org', 'lang' => 'en', 'term' => '1st',
                                    'credits' => '50', 'title' => 'Test course', 'timePlace' => $timeplace, 'lecturer' => $lecturer);

        $expectedcourse = (object)array('fullname' => 'Title: '.$remotedata->title,
                                        'shortname' => $remotedata->title,
                                        'idnumber' => '',
                                        'startdate' => strtotime($timeplace->begin),
                                        'lang' => $remotedata->lang,
                                        'summary' => 'Org: '.$remotedata->organization.', Begin: '.userdate(strtotime($timeplace->begin), get_string('strftimedatetime')));

        $meta = new campusconnect_metadata();
        $meta->set_import_mappings($mappings);
        $course = $meta->map_remote_to_course($remotedata);

        $this->assertEqual($course, $expectedcourse, "Mapped data did not match expectations");
    }

    public function test_map_course_to_remote() {
        $mappings = array('organization' => '', 'lang' => 'lang', 'semesterHours' => '',
                          'courseID' => '{idnumber}', 'term' => '', 'credits' => '', 'status' => '',
                          'title' => '{fullname} - {shortname} - {startdate}', 'room' => '', 'cycle' => '', 'begin' => 'startdate',
                          'end' => '', 'study_courses' => '', 'lecturer' => '');
        $course = (object)array('fullname' => 'Test course fullname',
                                'shortname' => 'Shortname',
                                'summary' => "I don't expect to see this summary in the output",
                                'lang' => 'en',
                                'startdate' => 1340200080);

        $startdatestr = userdate($course->startdate, '%Y-%m-%dT%H:%M:%S%z');
        $expectedremote = (object)array('lang' => $course->lang,
                                        'courseID' => '',
                                        'title' => $course->fullname.' - '.$course->shortname.' - '.$startdatestr,
                                        'timePlace' => (object)array('begin' => $startdatestr));

        $meta = new campusconnect_metadata();
        $meta->set_export_mappings($mappings);
        $remotedata = $meta->map_course_to_remote($course);

        $this->assertEqual($remotedata, $expectedremote, "Mapped data did not match expectations");
    }
}