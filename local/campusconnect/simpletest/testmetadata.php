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

//define('SKIP_CAMPUSCONNECT_METADATA_TESTS', 1);

class local_campusconnect_metadata_test extends UnitTestCase {
    protected $settings = null;

    public function skip() {
        $this->skipIf(defined('SKIP_CAMPUSCONNECT_METADATA_TESTS'), 'Skipping receivequeue tests, to save time');
    }

    public function setUp() {
        $this->settings = new campusconnect_ecssettings(null, 'unittest1');
    }

    public function tearDown() {
        campusconnect_metadata::delete_ecs_metadata_mappings($this->settings->get_id());
    }

    public function test_set_import_mapping() {
        $defaultmappings = array('fullname' => '{title}', 'shortname' => '{title}', 'idnumber' => '', 'startdate' => 'firstDate',
                                 'lang' => 'lang', 'timecreated' => '', 'timemodified' => '');

        // Test the default settings.
        $meta = new campusconnect_metadata($this->settings, true);
        $mappings = $meta->get_import_mappings();
        unset($mappings['summary']); // Default summary is fiddly to test
        $this->assertEqual($mappings, $defaultmappings, 'Expected get_import_mappings to return the default settings');

        // Test setting and immediately retrieving.
        $testmappings = $defaultmappings;
        $testmappings['fullname'] = '{title} - {destinationForDisplay} - {firstDate}';
        $testmappings['summary'] = 'Title: {title}';
        $this->assertTrue($meta->set_import_mappings($testmappings), 'Error whilst calling set_import_mappings');
        $mappings = $meta->get_import_mappings();
        $this->assertEqual($mappings, $testmappings, 'Expected get_import_mappings to return the mappings just set');

        // Test retrieving from new object.
        $meta = new campusconnect_metadata($this->settings, true);
        $mappings = $meta->get_import_mappings();
        $this->assertEqual($mappings, $testmappings, 'Expected get_import_mappings to return the mappings previously set');

        // Test setting individual fields.
        $testmappings['shortname'] = '{id} - {title}';
        $this->assertTrue($meta->set_import_mapping('shortname', $testmappings['shortname']), 'Error whilst calling set_import_mapping');
        $mappings = $meta->get_import_mappings();
        $this->assertEqual($mappings, $testmappings, 'Expected set_import_mapping to update the shortname correctly');

        // Test retrieving from new object.
        $meta = new campusconnect_metadata($this->settings, true);
        $mappings = $meta->get_import_mappings();
        $this->assertEqual($mappings, $testmappings, 'Expected get_import_mappings to return the mappings previously set');

        // Test setting invalid mapping.
        $this->expectException('coding_exception', "Should not be able to map the remote 'title' field onto the local 'startdate' field");
        $meta->set_import_mapping('startdate', 'title');
        $mappings = $meta->get_import_mappings();
        $this->assertEqual($mappings, $testmappings, "Able to set an invalid mapping 'startdate' = 'title'");

        // Test setting string with invalid placeholder.
        $this->assertFalse($meta->set_import_mapping('summary', '{title} - {fishfinger} - {firstDate}'), "Should not be able to include invalid fields in 'summary' mapping");
        list($errfield, $errstr) = $meta->get_last_error();
        $this->assertEqual($errfield, 'summary', "Expected an error in the 'summary' field");
        $mappings = $meta->get_import_mappings();
        $this->assertEqual($mappings, $testmappings, "Able to set an invalid mapping for 'summary' field");
    }

    public function test_set_export_mapping() {
        $defaultmappings = array('destinationForDisplay' => '', 'lang' => 'lang', 'hoursPerWeek' => '',
                                 'id' => '', 'number' => '', 'term' => '', 'credits' => '',
                                 'status' => '', 'courseType' => '', 'title' => '{fullname}',
                                 'firstDate' => 'startdate', 'datesAndVenues.day' => '',
                                 'datesAndVenues.start' => '', 'datesAndVenues.end' => '',
                                 'datesAndVenues.cycle' => '', 'datesAndVenues.venue' => '',
                                 'datesAndVenues.firstDate.startDatetime' => '',
                                 'datesAndVenues.firstDate.endDatetime' => '',
                                 'datesAndVenues.lastDate.startDatetime' => '',
                                 'datesAndVenues.lastDate.endDatetime' => '',
                                 'degreeProgrammes' => '', 'lecturers' => '');

        // Test the default settings.
        $meta = new campusconnect_metadata($this->settings, true);
        $mappings = $meta->get_export_mappings();
        $this->assertEqual($mappings, $defaultmappings, 'Expected get_export_mappings to return the default settings');

        // Test setting and immediately retrieving.
        $testmappings = $defaultmappings;
        $testmappings['title'] = '{fullname} - {shortname}';
        $testmappings['id'] = '{idnumber}';
        $testmappings['firstDate'] = '';
        $testmappings['datesAndVenues.lastDate.endDatetime'] = 'timecreated';
        $this->assertTrue($meta->set_export_mappings($testmappings), 'Error whilst calling set_export_mappings');
        $mappings = $meta->get_export_mappings();
        $this->assertEqual($mappings, $testmappings, 'Expected get_export_mappings to return the mappings just set');

        // Test retrieving from new object.
        $meta = new campusconnect_metadata($this->settings, true);
        $mappings = $meta->get_export_mappings();
        $this->assertEqual($mappings, $testmappings, 'Expected get_export_mappings to return the mappings previously set');

        // Test setting individual fields.
        $testmappings['title'] = '{idnumber} - {shortname}';
        $this->assertTrue($meta->set_export_mapping('title', $testmappings['title']), 'Error whilst calling set_export_mapping');
        $mappings = $meta->get_export_mappings();
        $this->assertEqual($mappings, $testmappings, 'Expected set_export_mapping to update the shortname correctly');

        // Test retrieving from new object.
        $meta = new campusconnect_metadata($this->settings, true);
        $mappings = $meta->get_export_mappings();
        $this->assertEqual($mappings, $testmappings, 'Expected get_export_mappings to return the mappings previously set');

        // Test setting invalid mapping.
        $this->expectException('coding_exception', "Should not be able to map the local 'fullname' field onto the remote 'firstname' field");
        $meta->set_export_mapping('firstDate', 'fullname');
        $mappings = $meta->get_export_mappings();
        $this->assertEqual($mappings, $testmappings, "Able to set an invalid mapping 'begin' = 'fullname'");

        // Test setting string with invalid placeholder.
        $this->assertFalse($meta->set_export_mapping('title', '{title} - {fishfinger} - {firstDate}'), "Should not be able to include invalid fields in 'title' mapping");
        list($errfield, $errstr) = $meta->get_last_error();
        $this->assertEqual($errfield, 'title', "Expected an error in the 'title' field");
        $mappings = $meta->get_export_mappings();
        $this->assertEqual($mappings, $testmappings, "Able to set an invalid mapping for 'title' field");
    }

    public function test_map_remote_to_course() {
        $mappings = array('fullname' => 'Title: {title}', 'shortname' => '{title}', 'idnumber' => '{id}', 'startdate' => 'firstDate',
                          'lang' => 'lang', 'timecreated' => '', 'timemodified' => '',
                          'summary' => 'Destination: {destinationForDisplay}, firstDate: {firstDate}, lecturers: {lecturers}');

        $datesandvenues = array((object)array('day' => 'Monday', 'start' => '2012-06-20T14:48:00+01:00', 'end' => '2012-06-30T15:00:00+01:00',
                                              'cycle' => 'week', 'venue' => 'Room 101',
                                              'firstDate' => (object)array('startDatetime' => '2012-06-20T14:48:00+01:00',
                                                                           'endDatetime' => '2012-06-20T15:00:00+01:00'),
                                              'lastDate' => (object)array('startDatetime' => '2012-06-30T14:48:00+01:00',
                                                                          'endDatetime' => '2012-06-30T15:00:00+01:00')));
        $lecturers = array((object)array('firstName' => 'Prof.', 'lastName' => 'Plum'),
                           (object)array('firstName' => 'C.', 'lastName' => 'Mustard'));
        $remotedata = (object)array('url' => 'http://www.synergy-learning.com', 'destinationForDisplay' => 'Test org',
                                    'lang' => 'en', 'hoursPerWeek' => 5, 'id' => 'ABC-123', 'number' => '5', 'term' => '1st',
                                    'credits' => 50, 'status' => 'open', 'courseType' => 'online', 'title' => 'Test course',
                                    'firstDate' => '2012-06-20T14:48:00+01:00', 'datesAndVenues' => $datesandvenues, 'lecturers' => $lecturers);

        $expectedcourse = (object)array('fullname' => 'Title: '.$remotedata->title,
                                        'shortname' => $remotedata->title,
                                        'idnumber' => 'ABC-123',
                                        'summary' => 'Destination: '.$remotedata->destinationForDisplay.', firstDate: '.userdate(strtotime($remotedata->firstDate), get_string('strftimedatetime')).', lecturers: Prof. Plum, C. Mustard',
                                        'startdate' => strtotime($remotedata->firstDate),
                                        'lang' => $remotedata->lang);


        $meta = new campusconnect_metadata($this->settings, true);
        $meta->set_import_mappings($mappings);
        $course = $meta->map_remote_to_course($remotedata);

        $this->assertEqual($course, $expectedcourse);
    }

    public function test_map_course_to_remote() {
        $mappings = array('destinationForDisplay' => '', 'lang' => 'lang', 'hoursPerWeek' => '',
                          'id' => '{idnumber}', 'number' => '', 'term' => '', 'credits' => '', 'status' => '',
                          'courseType' => '', 'title' => '{fullname} - {shortname} - {startdate}', 'firstDate' => 'startdate',
                          'datesAndVenues.day' => '', 'datesAndVenues.start' => '', 'datesAndVenues.end' => '',
                          'datesAndVenues.cycle' => '', 'datesAndVenues.venue' => '',
                          'datesAndVenues.firstDate.startDatetime' => 'startdate', 'datesAndVenues.firstDate.endDatetime' => '',
                          'datesAndVenues.lastDate.startDatetime' => '', 'datesAndVenues.lastDate.endDatetime' => '',
                          'degreeProgrammes' => '', 'lecturers' => '');

        $course = (object)array('fullname' => 'Test course fullname',
                                'shortname' => 'Shortname',
                                'summary' => "I don't expect to see this summary in the output",
                                'lang' => 'en',
                                'startdate' => 1340200080);

        $startdatestr = userdate($course->startdate, '%Y-%m-%dT%H:%M:%S%z');
        $expectedremote = (object)array('lang' => $course->lang,
                                        'id' => '',
                                        'title' => $course->fullname.' - '.$course->shortname.' - '.$startdatestr,
                                        'firstDate' => $startdatestr,
                                        'datesAndVenues' => array((object)array('firstDate' => (object)array('startDatetime' => $startdatestr))));

        $meta = new campusconnect_metadata($this->settings, true);
        $meta->set_export_mappings($mappings);
        $remotedata = $meta->map_course_to_remote($course);

        $this->assertEqual($remotedata, $expectedremote, "Mapped data did not match expectations");
    }
}