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
 * Tests for ECS settings
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/campusconnect/participantsettings.php');

//define('SKIP_CAMPUSCONNECT_PARTICIPANT_TESTS', 1);

class local_campusconnect_participantsettings_test extends UnitTestCase {

    protected $ecssettings = null;

    public function skip() {
        $this->skipIf(defined('SKIP_CAMPUSCONNECT_PARTICIPANT_TESTS'), 'Skipping participant tests, to save time');
    }

    public function setUp() {
        // Create a test ECS
        $this->ecssettings = new campusconnect_ecssettings(null, 'unittest1');
    }

    public function tearDown() {
        // Delete any participant settings created
        campusconnect_participantsettings::delete_ecs_participant_settings($this->ecssettings->get_id());
    }

    public function test_load_participants() {
        $communities = campusconnect_participantsettings::load_communities($this->ecssettings);
        $this->assertIsA($communities, 'array', 'Expected an array of communities');
        $this->assertEqual(count($communities), 1, 'Expected unittest1 to be part of just one community');
        $community = reset($communities);
        $this->assertEqual($community->name, 'unittest', "Expected the community to be called 'unittest'");

        $parts = $community->participants;
        $this->assertEqual(count($parts), 3, "Expected 3 participants in the 'unittest' community");

        $expectednames = array('Unit test 1', 'Unit test 2', 'Unit test 3');
        $expecteddisplaynames = array('unittest: Unit test 1', 'unittest: Unit test 2', 'unittest: Unit test 3');
        foreach ($parts as $part) {
            $this->assertIsA($part, 'campusconnect_participantsettings');
            $name = $part->get_name();
            $pos = array_search($name, $expectednames);
            $this->assertIsA($pos, 'integer', "Unexpected participant '$name'");
            unset($expectednames[$pos]);

            $displayname = $part->get_displayname();
            $pos = array_search($displayname, $expecteddisplaynames);
            $this->assertIsA($pos, 'integer', "Unexpected participant display name '$displayname'");
        }
    }

    public function test_save_settings() {
        // Get the first participant in the community
        $communities = campusconnect_participantsettings::load_communities($this->ecssettings);
        $community = reset($communities);
        $participant = reset($community->participants);
        $mid = $participant->get_mid();

        // Check the default settings
        $this->assertFalse($participant->is_export_enabled(), 'Participants should default to not receiving exported courses');
        $this->assertFalse($participant->is_import_enabled(), 'Participants should default to not having courses imported');
        $this->assertEqual($participant->get_import_type(), campusconnect_participantsettings::IMPORT_LINK, 'Participants should default to importtype IMPORT_LINK');

        // Change all the settings
        $settings = array('import' => true, 'export' => true, 'importtype' => campusconnect_participantsettings::IMPORT_COURSE);
        $participant->save_settings($settings);

        // Check all settings have updated immediately
        $this->assertTrue($participant->is_export_enabled(), 'Export setting not updated');
        $this->assertTrue($participant->is_import_enabled(), 'Import setting not updated');
        $this->assertEqual($participant->get_import_type(), campusconnect_participantsettings::IMPORT_COURSE, 'Importtype setting not updated');

        $settings = $participant->get_settings();
        $this->assertEqual($settings->export, $participant->is_export_enabled(), 'Export setting internal consistency failure');
        $this->assertEqual($settings->import, $participant->is_import_enabled(), 'Import setting internal consistency failure');
        $this->assertEqual($settings->importtype, $participant->get_import_type(), 'Importtype setting internal consistency failure');
        $this->assertEqual($settings->name, $participant->get_name(), 'name setting internal consistency failure');

        // Check settings all save correctly
        $participant = new campusconnect_participantsettings($this->ecssettings->get_id(), $mid);
        $this->assertTrue($participant->is_export_enabled(), 'Export setting not saved');
        $this->assertTrue($participant->is_import_enabled(), 'Import setting not saved');
        $this->assertEqual($participant->get_import_type(), campusconnect_participantsettings::IMPORT_COURSE, 'Importtype setting not saved');
    }

    public function test_settings_validation() {
        // Get the first participant in the community
        $communities = campusconnect_participantsettings::load_communities($this->ecssettings);
        $community = reset($communities);
        $participant = reset($community->participants);
        $mid = $participant->get_mid();

        $settings = array('import' => 'fish', 'export' => 500);
        $participant->save_settings($settings);
        $this->assertTrue($participant->is_import_enabled());
        $this->assertTrue($participant->is_export_enabled());

        $settings['import'] = 0;
        $settings['export'] = '';
        $participant->save_settings($settings);
        $this->assertFalse($participant->is_import_enabled());
        $this->assertFalse($participant->is_export_enabled());

        // Check these settings can be set without an exception
        $settings['importtype'] = campusconnect_participantsettings::IMPORT_LINK;
        $participant->save_settings($settings);
        $settings['importtype'] = campusconnect_participantsettings::IMPORT_COURSE;
        $participant->save_settings($settings);
        $settings['importtype'] = campusconnect_participantsettings::IMPORT_CMS;
        $participant->save_settings($settings);
        // Check validation of invalid settings
        $this->expectException('coding_exception');
        $settings['importtype'] = 500;
        $participant->save_settings($settings);
    }
}
