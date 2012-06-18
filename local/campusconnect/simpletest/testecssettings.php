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

require_once($CFG->dirroot.'/local/campusconnect/ecssettings.php');

class local_campusconnect_ecssettings_test extends UnitTestCase {

    protected $testdata = array('name' => 'test1name',
                                'url' => 'http://www.example.com',
                                'auth' => campusconnect_ecssettings::AUTH_NONE,
                                'ecsauth' => 'test1',
                                'importcategory' => null,  // Set via 'setUp' function, below
                                'importrole' => 'student',
                                'importperiod' => 6);

    public function setUp() {
        global $DB;

        // Use the first course category found in the database as the import category, for testing
        $importcategory = $DB->get_records('course_categories', array(), 'id', 'id', 0, 1);
        $importcategory = reset($importcategory);
        $this->testdata['importcategory'] = $importcategory->id;
    }

    public function tearDown() {
        // Make sure all test ECS are gone (in case a test crashed earlier)
        $ecslist = campusconnect_ecssettings::list_ecs();
        foreach ($ecslist as $ecsid => $ecsname) {
            if ($ecsname == 'test1name' || $ecsname == 'test2name') {
                $settings = new campusconnect_ecssettings($ecsid);
                $settings->delete();
            }
        }
    }

    public function test_create_delete_settings() {
        $data = $this->testdata;
        $settings = new campusconnect_ecssettings();
        $settings->save_settings($data);

        $id = $settings->get_id();

        // Check the settings have been created successfully
        $this->assertIsA($id, 'integer');
        $this->assertTrue($id > 0);

        // Check the settings are as expected
        $this->assertEqual($settings->get_name(), 'test1name');
        $this->assertEqual($settings->get_url(), 'http://www.example.com');
        $this->assertEqual($settings->get_auth_type(), campusconnect_ecssettings::AUTH_NONE);
        $this->assertEqual($settings->get_ecs_auth(), 'test1');

        // Load the settings back from the database
        $settings = new campusconnect_ecssettings($id);

        // Check the loaded settings are as expected
        $this->assertEqual($settings->get_name(), 'test1name');
        $this->assertEqual($settings->get_url(), 'http://www.example.com');
        $this->assertEqual($settings->get_auth_type(), campusconnect_ecssettings::AUTH_NONE);
        $this->assertEqual($settings->get_ecs_auth(), 'test1');

        // Delete the settings
        $settings->delete();

        // Check the settings do not exist any more
        $this->expectException('dml_missing_record_exception');
        $settings = new campusconnect_ecssettings($id);
    }

    public function test_connect_setting_validation() {
        $settings = new campusconnect_ecssettings();
        $testdata = $this->testdata;

        // Test all general settings are required.
        $data = $testdata;
        unset($data['name']);
        $this->expectException('coding_exception');
        $settings->save_settings($data);

        $data = $testdata;
        unset($data['url']);
        $this->expectException('coding_exception');
        $settings->save_settings($data);

        $data = $testdata;
        unset($data['auth']);
        $this->expectException('coding_exception');
        $settings->save_settings($data);

        // Test the AUTH_NONE settings.
        $data = $testdata;
        unset($data['ecsauth']);
        $this->expectException('coding_exception');
        $settings->save_settings($data);

        $data = $testdata;
        $settings->save_settings($data);
        $this->assertTrue($settings->get_id() > 0);
        $settings->delete();

        // Test the AUTH_HTTP settings.
        $testdata['auth'] = campusconnect_ecssettings::AUTH_HTTP;
        $testdata['httppass'] = 'pass';
        $this->expectException('coding_exception');
        $settings->save_settings($testdata);

        unset($testdata['httppass']);
        $testdata['httpuser'] = 'user';
        $this->expectException('coding_exception');
        $settings->save_settings($testdata);

        $testdata['httppass'] = 'pass';
        $settings->save_settings($testdata);
        $this->assertTrue($settings->get_id() > 0);
        $settings->delete();

        // Test the AUTH_CERTIFICATE settings.
        $testdata['auth'] = campusconnect_ecssettings::AUTH_CERTIFICATE;
        $testdata['cacertpath'] = 'cacertpath';
        $testdata['certpath'] = 'certpath';
        $testdata['keypath'] = 'keypath';
        $testdata['keypass'] = 'keypass';

        $data = $testdata;
        unset($data['cacertpath']);
        $this->expectException('coding_exception');
        $settings->save_settings($data);

        $data = $testdata;
        unset($data['certpath']);
        $this->expectException('coding_exception');
        $settings->save_settings($data);

        $data = $testdata;
        unset($data['keypath']);
        $this->expectException('coding_exception');
        $settings->save_settings($data);

        $data = $testdata;
        unset($data['keypass']);
        $this->expectException('coding_exception');
        $settings->save_settings($data);

        $data = $testdata;
        $settings->save_settings($data);
        $this->assertTrue($settings->get_id() > 0);
        $settings->delete();
    }

    public function test_incoming_setting_validation() {
        global $DB;

        $settings = new campusconnect_ecssettings();

        // Disable cron
        $data = $this->testdata;
        $data['crontime'] = 0;
        $settings->save_settings($data);
        $this->assertFalse($settings->time_for_cron(), 'Cron should be disabled');

        // Enable cron
        $data['crontime'] = 60;
        $settings->save_settings($data);
        $this->assertTrue($settings->time_for_cron(), 'Cron not ready to start');

        // Try setting 'lastcron' - should be ignored
        $data['lastcron'] = time() + 100;
        $settings->save_settings($data);
        $this->assertTrue($settings->time_for_cron(), 'Lastcron was incorrectly updated');

        // Update the lastcron properly
        $settings->update_last_cron();
        $this->assertFalse($settings->time_for_cron(), 'Last cron not correctly updated');

        // Check importcategory validation
        $data = $this->testdata;
        $lastcategory = $DB->get_records('course_categories', array(), 'id DESC', 'id', 0, 1);
        $lastcategory = reset($lastcategory);
        $data['importcategory'] = $lastcategory->id + 1;
        $this->expectException('coding_exception');
        $settings->save_settings($data);

        // Check importrole validation
        $data = $this->testdata;
        $data['importrole'] = 'thisroledoesnotexist';
        $this->expectException('coding_exception');
        $settings->save_settings($data);

    }

    public function test_notify_setting() {
        $settings = new campusconnect_ecssettings();

        // Check notifyXX saving
        $data = $this->testdata;
        $data['notifyusers'] = '    testa@example.com,   testb@example.com

, testc@example.com';
        $data['notifycontent'] = 'testa@example.com,testb@example.com,testc@example.com';
        $data['notifycourses'] = 'testa@example.com';
        $settings->save_settings($data);
        $this->assertEqual($settings->get_notify_users(), array('testa@example.com', 'testb@example.com', 'testc@example.com'));
        $this->assertEqual($settings->get_notify_content(), array('testa@example.com', 'testb@example.com', 'testc@example.com'));
        $this->assertEqual($settings->get_notify_courses(), array('testa@example.com'));
    }

    public function test_list_ecs() {
        $startingecs = campusconnect_ecssettings::list_ecs();

        $data = $this->testdata;

        // Add an ECS and test it is in the list
        $settings1 = new campusconnect_ecssettings();
        $settings1->save_settings($data);
        $id1 = $settings1->get_id();

        $ecslist = array_diff(campusconnect_ecssettings::list_ecs(), $startingecs);
        $this->assertIsA($ecslist, 'Array');
        $this->assertEqual($ecslist, array($id1 => 'test1name'));

        // Add a second ECS and test it is also in the list
        $data['name'] = 'test2name';
        $settings2 = new campusconnect_ecssettings();
        $settings2->save_settings($data);
        $id2 = $settings2->get_id();

        $ecslist = array_diff(campusconnect_ecssettings::list_ecs(), $startingecs);
        $this->assertIsA($ecslist, 'Array');
        $this->assertEqual($ecslist, array($id1 => 'test1name',
                                           $id2 => 'test2name'));

        // Delete the first ECS and test the list only contains the second one
        $settings1->delete();
        $ecslist = array_diff(campusconnect_ecssettings::list_ecs(), $startingecs);
        $this->assertIsA($ecslist, 'Array');
        $this->assertEqual($ecslist, array($id2 => 'test2name'));

        // Delete the second ECS and test the list is empty
        $settings2->delete();
        $ecslist = array_diff(campusconnect_ecssettings::list_ecs(), $startingecs);
        $this->assertIsA($ecslist, 'Array');
        $this->assertEqual($ecslist, array());
    }

    public function test_settings_retrieval() {
        $notifyusers = array('testa@example.com','testb@example.com');
        $notifycontent = array('testc@example.com','testd@example.com');
        $notifycourses = array('teste@example.com','testf@example.com');
        $data = array_merge($this->testdata, array(
                                'crontime' => 60,
                                'httpuser' => 'username',
                                'httppass' => 'pass',
                                'cacertpath' => 'path/to/cacert',
                                'certpath' => 'path/to/cert',
                                'keypath' => 'path/to/key',
                                'keypass' => 'supersecretpass',
                                'notifyusers' => implode(',', $notifyusers),
                                'notifycontent' => implode(',', $notifycontent),
                                'notifycourses' => implode(',', $notifycourses)
                            ));

        $settings = new campusconnect_ecssettings();
        $settings->save_settings($data);
        $id = $settings->get_id();

        // Check that retrieving all settings works.
        $settings = new campusconnect_ecssettings($id);
        $allsettings = $settings->get_settings();
        foreach ($data as $field => $value) {
            $this->assertTrue(isset($allsettings->$field), "$field is not set");
            $this->assertEqual($value, $allsettings->$field);
        }

        // Check each individual setting.
        $this->assertEqual($settings->get_name(), $data['name']);
        $this->assertEqual($settings->get_url(), $data['url']);
        $this->assertEqual($settings->get_auth_type(), $data['auth']);
        $this->assertEqual($settings->get_ecs_auth(), $data['ecsauth']);

        $settings->save_settings(array('auth' => campusconnect_ecssettings::AUTH_HTTP));
        $this->assertEqual($settings->get_http_user(), $data['httpuser']);
        $this->assertEqual($settings->get_http_password(), $data['httppass']);

        $settings->save_settings(array('auth' => campusconnect_ecssettings::AUTH_CERTIFICATE));
        $this->assertEqual($settings->get_ca_cert_path(), $data['cacertpath']);
        $this->assertEqual($settings->get_client_cert_path(), $data['certpath']);
        $this->assertEqual($settings->get_key_path(), $data['keypath']);
        $this->assertEqual($settings->get_key_pass(), $data['keypass']);

        $this->assertEqual($settings->get_import_category(), $data['importcategory']);
        $this->assertEqual($settings->get_import_role(), $data['importrole']);
        $this->assertEqual($settings->get_import_period(), $data['importperiod']);

        $this->assertEqual($settings->get_notify_users(), $notifyusers);
        $this->assertEqual($settings->get_notify_content(), $notifycontent);
        $this->assertEqual($settings->get_notify_courses(), $notifycourses);

        $settings->delete();
    }
}
