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
 * Tests for the incoming directory tree notifications for CampusConnect
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

/**
 * NOTE: move_category is not tested due to complexity
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/campusconnect/directorytree.php');
require_once($CFG->dirroot.'/local/campusconnect/simpletest/enabledtests.php');

global $DB;
Mock::generate(get_class($DB), 'mockDB');

class local_campusconnect_directorytree_test extends UnitTestCase {
    protected $settings = array();
    protected $connect = array();
    protected $mid = array();
    protected $realDB = null;
    protected $community = null;

    public function skip() {
        $this->skipIf(defined('SKIP_CAMPUSCONNECT_DIRECTORYTREE_TESTS'), 'Skipping directorytree tests, to save time');
    }

    public function setUp() {
        // Override the $DB global.
        global $DB;
        $this->realDB = $DB;
        $DB = new mockDB();

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

        // General settings used by the tests
        $this->community = 'unittest';
    }

    public function tearDown() {
        // Restore the $DB global.
        global $DB;
        $DB = $this->realDB;

        // Delete all resources (just in case).
        foreach ($this->connect as $connect) {
            $courselinks = $connect->get_resource_list(campusconnect_event::RES_DIRECTORYTREE);
            foreach ($courselinks->get_ids() as $eid) {
                // All directory trees were created by 'unittest1'.
                $this->connect[1]->delete_resource($eid, campusconnect_event::RES_DIRECTORYTREE);
            }
        }

        // Delete all events & participants.
        foreach ($this->connect as $connect) {
            while ($connect->read_event_fifo(true));
            campusconnect_participantsettings::delete_ecs_participant_settings($connect->get_ecs_id());
        }

        $this->connect = array();
        $this->mid = array();
    }

    public function test_directorytree_class() {
        $data = (object)array(
            'id' => -1,
            'resourceid' => 5,
            'rootid' => 8,
            'title' => 'Test directory',
            'ecsid' => -1,
            'mid' => 14,
            'categoryid' => null,
            'mappingmode' => campusconnect_directorytree::MODE_PENDING,
            'takeovertitle' => true,
            'takeoverposition' => true,
            'takeoverallocation' => true
        );

        $dirtree = new campusconnect_directorytree($data);

        $this->assertEqual($dirtree->get_root_id(), $data->rootid);
        $this->assertEqual($dirtree->get_mode(), $data->mappingmode);
        $this->assertEqual($dirtree->get_title(), $data->title);
        $this->assertEqual($dirtree->get_category_id(), $data->categoryid);
        $this->assertEqual($dirtree->should_take_over_title(), $data->takeovertitle);
        $this->assertEqual($dirtree->should_take_over_position(), $data->takeoverposition);
        $this->assertEqual($dirtree->should_take_over_allocation(), $data->takeoverallocation);
    }

    public function test_directorytree_create() {
        global $DB;

        $data = (object)array(
            'resourceid' => 5,
            'rootid' => 8,
            'title' => 'Test directory',
            'ecsid' => -1,
            'mid' => 14,
            'categoryid' => null,
            'mappingmode' => campusconnect_directorytree::MODE_PENDING,
            'takeovertitle' => true,
            'takeoverposition' => true,
            'takeoverallocation' => true
        );

        $DB->expectAt(0, 'insert_record', array('local_campusconnect_dirroot', $data));
        $DB->setReturnValue('insert_record', 1);

        $dirtree = new campusconnect_directorytree();
        $dirtree->create($data->resourceid, $data->rootid, $data->title, $data->ecsid, $data->mid);

        $this->assertEqual($dirtree->get_root_id(), $data->rootid);
        $this->assertEqual($dirtree->get_mode(), $data->mappingmode);
        $this->assertEqual($dirtree->get_title(), $data->title);
        $this->assertEqual($dirtree->get_category_id(), $data->categoryid);
        $this->assertEqual($dirtree->should_take_over_title(), $data->takeovertitle);
        $this->assertEqual($dirtree->should_take_over_position(), $data->takeoverposition);
        $this->assertEqual($dirtree->should_take_over_allocation(), $data->takeoverallocation);
    }

    public function test_directorytree_set_title_and_category() {
        global $DB;

        $data = (object)array(
            'id' => -1,
            'resourceid' => 5,
            'rootid' => 8,
            'title' => 'Test directory',
            'ecsid' => -1,
            'mid' => 14,
            'categoryid' => null,
            'mappingmode' => campusconnect_directorytree::MODE_PENDING,
            'takeovertitle' => true,
            'takeoverposition' => true,
            'takeoverallocation' => true
        );

        // Try without category.
        $newtitle = 'Change title';
        $DB->expectAt(0, 'set_field', array('local_campusconnect_dirroot', 'title', $newtitle, array('id' => -1)));

        $dirtree = new campusconnect_directorytree($data);
        $dirtree->set_title($newtitle);

        $this->assertEqual($dirtree->get_title(), $newtitle);

        // Set the category and make sure the name is updated.
        $categoryid = -5;
        $DB->expectAt(1, 'set_field', array('local_campusconnect_dirroot', 'categoryid', $categoryid, array('id' => -1)));
        $DB->expectAt(2, 'set_field', array('course_categories', 'name', $newtitle, array('id' => $categoryid)));
        $DB->setReturnValue('get_record', (object)array('id' => $categoryid));

        $dirtree->map_category($categoryid);

        $this->assertEqual($dirtree->get_category_id(), $categoryid);

        // Set the title again and make sure the category name is updated.
        $newtitle = 'A different title';
        $DB->expectAt(3, 'set_field', array('local_campusconnect_dirroot', 'title', $newtitle, array('id' => -1)));
        $DB->expectAt(4, 'set_field', array('course_categories', 'name', $newtitle, array('id' => $categoryid)));

        $dirtree->set_title($newtitle);

        $this->assertEqual($dirtree->get_title(), $newtitle);
    }

    function test_directorytree_set_mode() {
        $data = (object)array(
            'id' => -1,
            'resourceid' => 5,
            'rootid' => 8,
            'title' => 'Test directory',
            'ecsid' => -1,
            'mid' => 14,
            'categoryid' => null,
            'mappingmode' => campusconnect_directorytree::MODE_PENDING,
            'takeovertitle' => true,
            'takeoverposition' => true,
            'takeoverallocation' => true
        );

        $dirtree = new campusconnect_directorytree($data);
        $dirtree->set_mode(campusconnect_directorytree::MODE_WHOLE);
        $this->assertEqual($dirtree->get_mode(), campusconnect_directorytree::MODE_WHOLE);

        $dirtree->set_mode(campusconnect_directorytree::MODE_MANUAL);
        $this->assertEqual($dirtree->get_mode(), campusconnect_directorytree::MODE_MANUAL);

        $this->expectException('coding_exception', 'Should not be able to change from MODE_MANUAL => MODE_WHOLE');
        $dirtree->set_mode(campusconnect_directorytree::MODE_WHOLE);
        $this->expectException('coding_exception', 'Should not be able to change from MODE_MANUAL => MODE_PENDING');
        $dirtree->set_mode(campusconnect_directorytree::MODE_PENDING);

        $dirtree = new campusconnect_directorytree($data);
        $dirtree->set_mode(campusconnect_directorytree::MODE_WHOLE);
        $this->expectException('coding_exception', 'Should not be able to change from MODE_WHOLE => MODE_PENDING');
        $dirtree->set_mode(campusconnect_directorytree::MODE_PENDING);
    }

    function test_directorytree_delete() {
        global $DB;

        $data = (object)array(
            'id' => -1,
            'resourceid' => 5,
            'rootid' => 8,
            'title' => 'Test directory',
            'ecsid' => -1,
            'mid' => 14,
            'categoryid' => null,
            'mappingmode' => campusconnect_directorytree::MODE_PENDING,
            'takeovertitle' => true,
            'takeoverposition' => true,
            'takeoverallocation' => true
        );

        $DB->setReturnValue('get_records', array()); // No directories.
        $DB->expectAt(0, 'set_field', array('local_campusconnect_dirroot', 'mappingmode', campusconnect_directorytree::MODE_DELETED, array('id' => -1)));
        $dirtree = new campusconnect_directorytree($data);
        $dirtree->delete();
        $this->assertEqual($dirtree->get_mode(), campusconnect_directorytree::MODE_DELETED);

        $this->expectException('coding_exception', 'Should not be able to change from MODE_DELETED => MODE_WHOLE');
        $dirtree->set_mode(campusconnect_directorytree::MODE_WHOLE);
        $this->expectException('coding_exception', 'Should not be able to change from MODE_DELETED => MODE_MANUAL');
        $dirtree->set_mode(campusconnect_directorytree::MODE_MANUAL);
    }

    function test_list_directory_trees() {
        global $DB;

        $treedata1 = (object)array(
            'id' => -1,
            'resourceid' => 5,
            'rootid' => 8,
            'title' => 'Test directory',
            'ecsid' => -1,
            'mid' => 14,
            'categoryid' => null,
            'mappingmode' => campusconnect_directorytree::MODE_PENDING,
            'takeovertitle' => true,
            'takeoverposition' => true,
            'takeoverallocation' => true
        );

        $treedata2 = (object)array(
            'id' => -3,
            'resourceid' => 5,
            'rootid' => 10,
            'title' => 'Test directory2',
            'ecsid' => -1,
            'mid' => 14,
            'categoryid' => null,
            'mappingmode' => campusconnect_directorytree::MODE_PENDING,
            'takeovertitle' => true,
            'takeoverposition' => true,
            'takeoverallocation' => true
        );

        $treedata3 = (object)array(
            'id' => -5,
            'resourceid' => 5,
            'rootid' => 15,
            'title' => 'Test directory3',
            'ecsid' => -1,
            'mid' => 14,
            'categoryid' => null,
            'mappingmode' => campusconnect_directorytree::MODE_PENDING,
            'takeovertitle' => true,
            'takeoverposition' => true,
            'takeoverallocation' => true
        );

        $tree1 = new campusconnect_directorytree($treedata1);
        $tree2 = new campusconnect_directorytree($treedata2);
        $tree3 = new campusconnect_directorytree($treedata3);

        $DB->setReturnValue('get_records', array($treedata1->id => $treedata1,
                                                 $treedata2->id => $treedata2,
                                                 $treedata3->id => $treedata3));

        $trees = campusconnect_directorytree::list_directory_trees();
        $this->assertEqual($trees, array($treedata1->id => $tree1,
                                         $treedata2->id => $tree2,
                                         $treedata3->id => $tree3));
    }

    function test_directorytree_refresh_create() {
        global $DB;

        $dirtree = (object)array(
            'directoryTreeTitle' => 'Testing directory',
            'id' => '5',
            'title' => 'Testing directory',
            'order' => '1',
            'term' => '2',
            'parent' => (object)array(
                'id' => '',
                'order' => '1',
                'title' => ''
            ),
            'rootID' => '5'
        );

        // Add a directorytree resource
        $eid = $this->connect[1]->add_resource($dirtree, $this->community, null, campusconnect_event::RES_DIRECTORYTREE);

        $expecteddata = (object)array(
            'resourceid' => $eid,
            'rootid' => '5',
            'title' => 'Testing directory',
            'ecsid' => $this->connect[2]->get_ecs_id(), // Received by ECS 2
            'mid' => $this->mid[1],
            'categoryid' => null,
            'mappingmode' => campusconnect_directorytree::MODE_PENDING,
            'takeovertitle' => true,
            'takeoverposition' => true,
            'takeoverallocation' => true
        );

        $cmsdata = (object)array(
            'id' => 7,
            'export' => 0,
            'import' => 1,
            'importtype' => campusconnect_participantsettings::IMPORT_CMS,
            'mid' => $this->mid[1],
            'ecsid' => $this->connect[2]->get_ecs_id(), // Received by ECS 2
            'displayname' => 'The CMS'
        );

        $DB->setReturnValueAt(0, 'get_records', array($cmsdata->id => $cmsdata)); // Get CMS participant
        $DB->setReturnValue('get_records', array()); // Get directory trees
        $DB->expectAt(0, 'insert_record', array('local_campusconnect_dirroot', $expecteddata));

        campusconnect_directorytree::refresh_from_ecs();
    }

    function test_directorytree_refresh_update() {
        global $DB;

        $dirtree = (object)array(
            'directoryTreeTitle' => 'Testing directory (new name)',
            'id' => '5',
            'title' => 'Testing directory (new name)',
            'order' => '1',
            'term' => '2',
            'parent' => (object)array(
                'id' => '',
                'order' => '1',
                'title' => ''
            ),
            'rootID' => '5'
        );

        // Add a directorytree resource
        $eid = $this->connect[1]->add_resource($dirtree, $this->community, null, campusconnect_event::RES_DIRECTORYTREE);

        $treedata = (object)array(
            'id' => -2,
            'resourceid' => $eid,
            'rootid' => '5',
            'title' => 'Testing directory (old name)',
            'ecsid' => $this->connect[2]->get_ecs_id(), // Received by ECS 2
            'mid' => $this->mid[1], // Sent by participant 1
            'categoryid' => null,
            'mappingmode' => campusconnect_directorytree::MODE_PENDING,
            'takeovertitle' => true,
            'takeoverposition' => true,
            'takeoverallocation' => true
        );

        $cmsdata = (object)array(
            'id' => 7,
            'export' => 0,
            'import' => 1,
            'importtype' => campusconnect_participantsettings::IMPORT_CMS,
            'mid' => $this->mid[1], // Sent by participant 1
            'ecsid' => $this->connect[2]->get_ecs_id(), // Received by ECS 2
            'displayname' => 'The CMS'
        );

        $DB->setReturnValueAt(0, 'get_records', array($cmsdata->id => $cmsdata)); // Get CMS participant
        $DB->setReturnValueAt(1, 'get_records', array($treedata->id => $treedata)); // Get directory trees
        $DB->setReturnValue('get_records', array());
        $DB->expectAt(0, 'set_field', array('local_campusconnect_dirroot', 'title', $dirtree->title, array('id' => -2)));

        campusconnect_directorytree::refresh_from_ecs();
    }

    function test_directorytree_refresh_delete() {
        global $DB;

        $treedata = (object)array(
            'id' => -2,
            'resourceid' => 19,
            'rootid' => '5',
            'title' => 'Testing directory',
            'ecsid' => $this->connect[2]->get_ecs_id(), // Received by ECS 2
            'mid' => $this->mid[1], // Sent by participant 1
            'categoryid' => null,
            'mappingmode' => campusconnect_directorytree::MODE_PENDING,
            'takeovertitle' => true,
            'takeoverposition' => true,
            'takeoverallocation' => true
        );

        $cmsdata = (object)array(
            'id' => 7,
            'export' => 0,
            'import' => 1,
            'importtype' => campusconnect_participantsettings::IMPORT_CMS,
            'mid' => $this->mid[1], // Sent by participant 1
            'ecsid' => $this->connect[2]->get_ecs_id(), // Received by ECS 2
            'displayname' => 'The CMS'
        );

        $DB->setReturnValueAt(0, 'get_records', array($cmsdata->id => $cmsdata)); // Get CMS participant
        $DB->setReturnValueAt(1, 'get_records', array($treedata->id => $treedata)); // Get directory trees
        $DB->setReturnValue('get_records', array());
        $DB->expectAt(0, 'set_field', array('local_campusconnect_dirroot', 'mappingmode', campusconnect_directorytree::MODE_DELETED, array('id' => -2)));

        campusconnect_directorytree::refresh_from_ecs();
    }

    function test_directorytree_directory_create() {
        global $DB;

        $dirtree = (object)array(
            'directoryTreeTitle' => 'Testing directory',
            'id' => '5',
            'title' => 'Testing directory',
            'order' => '1',
            'term' => '2',
            'parent' => (object)array(
                'id' => '',
                'order' => '1',
                'title' => ''
            ),
            'rootID' => '5'
        );

        $resourceid = 10;

        $expecteddata = (object)array(
            'resourceid' => $resourceid,
            'rootid' => '5',
            'title' => 'Testing directory',
            'ecsid' => $this->connect[2]->get_ecs_id(), // Received by ECS 2
            'mid' => $this->mid[1],
            'categoryid' => null,
            'mappingmode' => campusconnect_directorytree::MODE_PENDING,
            'takeovertitle' => true,
            'takeoverposition' => true,
            'takeoverallocation' => true
        );

        $cmsdata = (object)array(
            'id' => 7,
            'export' => 0,
            'import' => 1,
            'importtype' => campusconnect_participantsettings::IMPORT_CMS,
            'mid' => $this->mid[1], // Sent by participant 1
            'ecsid' => $this->connect[2]->get_ecs_id(), // Received by ECS 2
            'displayname' => 'The CMS'
        );

        $details = new campusconnect_details((object)array(
                                                 'url' => 'http://www.example.com',
                                                 'receivers' => array((object)array('itsyou' => true)),
                                                 'senders' => array((object)array('mid' => $this->mid[1])),
                                                 'owner' => '1',
                                                 'content_type' => 'type'
                                             ));

        $DB->setReturnValueAt(0, 'get_records', array($cmsdata->id => $cmsdata)); // Get CMS participant
        $DB->setReturnValue('record_exists', false); // Doesn't already exist.

        $DB->expectAt(0, 'insert_record', array('local_campusconnect_dirroot', $expecteddata));
        campusconnect_directorytree::create_directory($resourceid, $this->settings[2], $dirtree, $details);
    }

    function test_directorytree_directory_update() {
        global $DB;

        $dirtree = (object)array(
            'directoryTreeTitle' => 'Testing directory',
            'id' => '5',
            'title' => 'Testing directory',
            'order' => '1',
            'term' => '2',
            'parent' => (object)array(
                'id' => '',
                'order' => '1',
                'title' => ''
            ),
            'rootID' => '5'
        );

        $resourceid = 10;

        $treedata = (object)array(
            'id' => '-10',
            'resourceid' => $resourceid,
            'rootid' => '5',
            'title' => 'Testing directory (old name)',
            'ecsid' => $this->connect[2]->get_ecs_id(), // Received by ECS 2
            'mid' => $this->mid[1],
            'categoryid' => null,
            'mappingmode' => campusconnect_directorytree::MODE_PENDING,
            'takeovertitle' => true,
            'takeoverposition' => true,
            'takeoverallocation' => true
        );

        $cmsdata = (object)array(
            'id' => 7,
            'export' => 0,
            'import' => 1,
            'importtype' => campusconnect_participantsettings::IMPORT_CMS,
            'mid' => $this->mid[1], // Sent by participant 1
            'ecsid' => $this->connect[2]->get_ecs_id(), // Received by ECS 2
            'displayname' => 'The CMS'
        );

        $details = new campusconnect_details((object)array(
                                                 'url' => 'http://www.example.com',
                                                 'receivers' => array((object)array('itsyou' => true)),
                                                 'senders' => array((object)array('mid' => $this->mid[1])),
                                                 'owner' => '1',
                                                 'content_type' => 'type'
                                             ));

        $DB->setReturnValueAt(0, 'get_records', array($cmsdata->id => $cmsdata)); // Get CMS participant
        $DB->setReturnValue('get_record', $treedata); // Already exists.

        $DB->expectAt(0, 'set_field', array('local_campusconnect_dirroot', 'title', $dirtree->title, array('id' => $treedata->id)));
        campusconnect_directorytree::update_directory($resourceid, $this->settings[2], $dirtree, $details);
    }

    function test_directorytree_directory_delete() {
        global $DB;

        $resourceid = 10;

        $treedata = (object)array(
            'id' => '-10',
            'resourceid' => $resourceid,
            'rootid' => '5',
            'title' => 'Testing directory (old name)',
            'ecsid' => $this->connect[2]->get_ecs_id(), // Received by ECS 2
            'mid' => $this->mid[1],
            'categoryid' => null,
            'mappingmode' => campusconnect_directorytree::MODE_PENDING,
            'takeovertitle' => true,
            'takeoverposition' => true,
            'takeoverallocation' => true
        );

        $DB->setReturnValue('get_record', $treedata); // Already exists.

        $DB->expectAt(0, 'set_field', array('local_campusconnect_dirroot', 'mappingmode', campusconnect_directorytree::MODE_DELETED, array('id' => $treedata->id)));
        campusconnect_directorytree::delete_directory($resourceid, $this->settings[2]);
    }

    /*

To test:

==directory==

set_data - get_root_id, get_directory_id, get_title
get_parent
get_status
create
delete
set_title
set_order
map_category
create_category
check_update_directory
remove_missing_directories
delete_root_directory
get_directories

     */

}