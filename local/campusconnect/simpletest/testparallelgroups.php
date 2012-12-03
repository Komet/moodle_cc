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

global $CFG;
require_once($CFG->dirroot.'/local/campusconnect/course.php');
require_once($CFG->dirroot.'/local/campusconnect/simpletest/enabledtests.php');

global $DB;
/** @noinspection PhpDynamicAsStaticMethodCallInspection */
Mock::generate(get_class($DB), 'mockDB_pgroups', array('mock_create_group', 'mock_update_group'));

class campusconnect_parallelgroups_testing extends campusconnect_parallelgroups {
    public function create_or_update_group($course, $pgroup, $id = null) {
        /** @var $DB SimpleMock */
        global $DB;
        $comment = isset($pgroup->comment) ? $pgroup->comment : null;
        if (is_null($id)) {
            $DB->mock_create_group($course->id, $pgroup->title, $comment);
            return 'created';
        } else {
            $DB->mock_update_group($id, $course->id, $pgroup->title, $comment);
            return $id;
        }
    }
}

class local_campusconnect_parallelgroups_test extends UnitTestCase {
    protected $realDB = null;

    public function skip() {
        $this->skipIf(defined('SKIP_CAMPUSCONNECT_PARALLELGROUPS_TESTS'), 'Skipping parallelgroups tests, to save time');
    }

    public function setUp() {
        // Override the $DB global.
        global $DB;
        $this->realDB = $DB;
        /** @noinspection PhpUndefinedClassInspection */
        $DB = new mockDB_pgroups();
    }

    public function tearDown() {
        // Restore the $DB global.
        global $DB;
        $DB = $this->realDB;

    }

    public function test_get_groups() {
        $pgroupsdata = array();
        $pgroupsdata['empty'] = array();
        $pgroupsdata['onegroup'] = array(
            (object)array(
                'title' => 'GroupA',
                'id' => 'grp1',
                'comment' => 'The first group',
                'lecturers' => array(
                    (object)array('firstName' => 'Fred', 'lastName' => 'Bloggs')
                )
            )
        );
        $pgroupsdata['multiplegroups'] = array(
            (object)array(
                'title' => 'GroupA',
                'id' => 'grp1',
                'comment' => 'The first group',
                'lecturers' => array(
                    (object)array('firstName' => 'Fred', 'lastName' => 'Bloggs')
                )
            ),
            (object)array(
                'title' => 'GroupB',
                'id' => 'grp2',
                'comment' => 'The second group',
                'lecturers' => array(
                    (object)array('firstName' => 'Fred', 'lastName' => 'Bloggs'),
                    (object)array('firstName' => 'Bob', 'lastName' => 'Smith')
                )
            ),
            (object)array(
                'title' => 'GroupC',
                'id' => 'grp3',
                'lecturers' => array(
                    (object)array('firstName' => 'Bob', 'lastName' => 'Smith')
                )
            ),
            (object)array(
                'title' => 'GroupD',
                'id' => 'grp4',
                'comment' => 'The last group',
                'lecturers' => array(
                    (object)array('firstName' => 'Joe', 'lastName' => 'Bloggs')
                )
            )
        );

        $outdata = array();
        foreach ($pgroupsdata as $name => $pgroups) {
            $outdata[$name] = array();
            foreach ($pgroups as $pgroup) {
                $flattened = (object)array(
                    'id' => $pgroup->id,
                    'title' => $pgroup->title,
                    'comment' => isset($pgroup->comment) ? $pgroup->comment : null,
                    'lecturer' => isset($pgroup->lecturers) ?
                        ($pgroup->lecturers[0]->firstName.' '.$pgroup->lecturers[0]->lastName) : ''
                );
                $outdata[$name][$pgroup->id] = $flattened;
            }
        }

        // Test no 'parallel groups mode'
        $coursedata = (object)array(
            'basicData' => (object)array(
                'title' => 'Test course'
            ),
            'parallelGroups' => $pgroupsdata['multiplegroups']
        );
        list($groups, $mode) = campusconnect_parallelgroups::get_parallel_groups($coursedata);
        $this->assertEqual($groups, array()); // No groups in output.
        $this->assertEqual($mode, campusconnect_parallelgroups::PGROUP_NONE);

        // Test invalid groups mode
        $coursedata = (object)array(
            'basicData' => (object)array(
                'title' => 'Test course',
                'parallelGroupScenario' => 0
            ),
            'parallelGroups' => $pgroupsdata['multiplegroups']
        );
        try {
            campusconnect_parallelgroups::get_parallel_groups($coursedata);
            $this->fail("campusconnect_course_exception not thrown for invalid parallelgroups scenario");
        } catch (campusconnect_course_exception $e) {
        }


        // Test single course mode
        $coursedata = (object)array(
            'basicData' => (object)array(
                'title' => 'Test course',
                'parallelGroupScenario' => 1
            ),
            'parallelGroups' => $pgroupsdata['empty']
        );
        list($groups, $mode) = campusconnect_parallelgroups::get_parallel_groups($coursedata);
        $this->assertEqual($groups, array($outdata['empty']));
        $this->assertEqual($mode, campusconnect_parallelgroups::PGROUP_ONE_COURSE);

        $coursedata = (object)array(
            'basicData' => (object)array(
                'parallelGroupScenario' => 1
            ),
            'parallelGroups' => $pgroupsdata['onegroup']
        );
        list($groups, $mode) = campusconnect_parallelgroups::get_parallel_groups($coursedata);
        $this->assertEqual($groups, array($outdata['onegroup']));
        $this->assertEqual($mode, campusconnect_parallelgroups::PGROUP_ONE_COURSE);

        $coursedata = (object)array(
            'basicData' => (object)array(
                'parallelGroupScenario' => 1
            ),
            'parallelGroups' => $pgroupsdata['multiplegroups']
        );
        list($groups, $mode) = campusconnect_parallelgroups::get_parallel_groups($coursedata);
        $this->assertEqual($groups, array($outdata['multiplegroups'])); // All groups in one course.
        $this->assertEqual($mode, campusconnect_parallelgroups::PGROUP_ONE_COURSE);

        // Test multiple groups mode
        $coursedata = (object)array(
            'basicData' => (object)array(
                'parallelGroupScenario' => 2
            ),
            'parallelGroups' => $pgroupsdata['empty']
        );
        list($groups, $mode) = campusconnect_parallelgroups::get_parallel_groups($coursedata);
        $this->assertEqual($groups, array($outdata['empty']));
        $this->assertEqual($mode, campusconnect_parallelgroups::PGROUP_SEPARATE_GROUPS);

        $coursedata = (object)array(
            'basicData' => (object)array(
                'parallelGroupScenario' => 2
            ),
            'parallelGroups' => $pgroupsdata['onegroup']
        );
        list($groups, $mode) = campusconnect_parallelgroups::get_parallel_groups($coursedata);
        $this->assertEqual($groups, array($outdata['onegroup']));
        $this->assertEqual($mode, campusconnect_parallelgroups::PGROUP_SEPARATE_GROUPS);

        $coursedata = (object)array(
            'basicData' => (object)array(
                'parallelGroupScenario' => 2
            ),
            'parallelGroups' => $pgroupsdata['multiplegroups']
        );
        list($groups, $mode) = campusconnect_parallelgroups::get_parallel_groups($coursedata);
        $this->assertEqual($groups, array($outdata['multiplegroups'])); // All groups in one course.
        $this->assertEqual($mode, campusconnect_parallelgroups::PGROUP_SEPARATE_GROUPS);

        // Test multiple courses mode
        $coursedata = (object)array(
            'basicData' => (object)array(
                'title' => 'Test course',
                'parallelGroupScenario' => 3
            ),
            'parallelGroups' => $pgroupsdata['empty']
        );
        list($groups, $mode) = campusconnect_parallelgroups::get_parallel_groups($coursedata);
        $this->assertEqual($groups, array());
        $this->assertEqual($mode, campusconnect_parallelgroups::PGROUP_SEPARATE_COURSES);

        $coursedata = (object)array(
            'basicData' => (object)array(
                'parallelGroupScenario' => 3
            ),
            'parallelGroups' => $pgroupsdata['onegroup']
        );
        list($groups, $mode) = campusconnect_parallelgroups::get_parallel_groups($coursedata);
        $this->assertEqual($groups, array($outdata['onegroup']));
        $this->assertEqual($mode, campusconnect_parallelgroups::PGROUP_SEPARATE_COURSES);

        $coursedata = (object)array(
            'basicData' => (object)array(
                'parallelGroupScenario' => 3
            ),
            'parallelGroups' => $pgroupsdata['multiplegroups']
        );
        list($groups, $mode) = campusconnect_parallelgroups::get_parallel_groups($coursedata);
        $this->assertEqual($groups, array(
                                         array('grp1' => $outdata['multiplegroups']['grp1']),
                                         array('grp2' => $outdata['multiplegroups']['grp2']),
                                         array('grp3' => $outdata['multiplegroups']['grp3']),
                                         array('grp4' => $outdata['multiplegroups']['grp4'])
                                         )); // All groups in separate courses (one per course).
        $this->assertEqual($mode, campusconnect_parallelgroups::PGROUP_SEPARATE_COURSES);

        // Test multiple lecturers mode
        $coursedata = (object)array(
            'basicData' => (object)array(
                'title' => 'Test course',
                'parallelGroupScenario' => 4
            ),
            'parallelGroups' => $pgroupsdata['empty']
        );
        list($groups, $mode) = campusconnect_parallelgroups::get_parallel_groups($coursedata);
        $this->assertEqual($groups, array());
        $this->assertEqual($mode, campusconnect_parallelgroups::PGROUP_SEPARATE_LECTURERS);

        $coursedata = (object)array(
            'basicData' => (object)array(
                'parallelGroupScenario' => 4
            ),
            'parallelGroups' => $pgroupsdata['onegroup']
        );
        list($groups, $mode) = campusconnect_parallelgroups::get_parallel_groups($coursedata);
        $this->assertEqual($groups, array('Fred Bloggs' => $outdata['onegroup']));
        $this->assertEqual($mode, campusconnect_parallelgroups::PGROUP_SEPARATE_LECTURERS);

        $coursedata = (object)array(
            'basicData' => (object)array(
                'parallelGroupScenario' => 4
            ),
            'parallelGroups' => $pgroupsdata['multiplegroups']
        );
        list($groups, $mode) = campusconnect_parallelgroups::get_parallel_groups($coursedata);
        $this->assertEqual($groups, array(
                                         'Fred Bloggs' => array('grp1' => $outdata['multiplegroups']['grp1'],
                                                                'grp2' => $outdata['multiplegroups']['grp2']),
                                         'Bob Smith' => array('grp3' => $outdata['multiplegroups']['grp3']),
                                         'Joe Bloggs' => array('grp4' => $outdata['multiplegroups']['grp4'])
                                    )); // 'Fred Bloggs' groups in one course, other lecturers in separate courses.
        $this->assertEqual($mode, campusconnect_parallelgroups::PGROUP_SEPARATE_LECTURERS);
    }

    public function test_create_groups() {
        /** @var $DB SimpleMock */
        global $DB;

        $ecssettings = new campusconnect_ecssettings(null, 'ecs1');
        $resourceid = -20;
        $pgclass = new campusconnect_parallelgroups_testing($ecssettings, $resourceid);
        $course = (object)array('id' => -30);

        $pgroupsdata = arraY();
        $pgroupsdata['empty'] = array();
        $pgroupsdata['onegroup'] = array(
            (object)array(
                'title' => 'GroupA',
                'id' => 'grp1',
                'comment' => 'The first group',
                'lecturer' => 'Fred Bloggs'
            )
        );
        $pgroupsdata['multiplegroups'] = array(
            (object)array(
                'title' => 'GroupA',
                'id' => 'grp1',
                'comment' => 'The first group',
                'lecturers' => 'Fred Bloggs'
            ),
            (object)array(
                'title' => 'GroupB',
                'id' => 'grp2',
                'comment' => 'The second group',
                'lecturers' => 'Fred Bloggs'
            ),
            (object)array(
                'title' => 'GroupC',
                'id' => 'grp3',
                'lecturers' => 'Bob Smith'
            ),
            (object)array(
                'title' => 'GroupD',
                'id' => 'grp4',
                'comment' => 'The last group',
                'lecturers' => 'Joe Bloggs'
            )
        );
        $insdata = array();
        foreach ($pgroupsdata as $key => $pgroups) {
            $ins = array();
            foreach ($pgroups as $pgroup) {
                $ins[] = (object)array(
                    'ecsid' => $ecssettings->get_id(),
                    'resourceid' => $resourceid,
                    'courseid' => $course->id,
                    'cmsgroupid' => $pgroup->id,
                    'grouptitle' => $pgroup->title,
                    'groupid' => 0
                );
            }
            $insdata[$key] = $ins;
        }

        $DB->setReturnValue('get_records_sql', array()); // No existing pgrecords.
        $DB->setReturnValue('get_records', array()); // No existing pgrecords.

        $cgidx = 0; // Number of times groups created
        $ugidx = 0; // Number of times groups updated
        $iridx = 0; // Number of records inserted
        $uridx = 0; // Number of records updated

        // Expect nothing to be updated for PGROUP_NONE
        $pgmode = campusconnect_parallelgroups::PGROUP_NONE;
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['empty']);
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['multiplegroups']);

        // PGROUP_ONE_COURSE - pgroups should be created, but no Moodle groups
        $pgmode = campusconnect_parallelgroups::PGROUP_ONE_COURSE;
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['empty']); // Empty groups => nothing created

        $DB->expectAt($iridx++, 'insert_record', array('local_campusconnect_pgroup', $insdata['onegroup'][0]));
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['onegroup']);

        for ($i=0; $i<count($insdata['multiplegroups']); $i++) {
            $DB->expectAt($iridx++, 'insert_record', array('local_campusconnect_pgroup', $insdata['multiplegroups'][$i]));
        }
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['multiplegroups']);

        // PGROUP_SEPARATE_GROUPS - pgroups should be created and Moodle groups
        $pgmode = campusconnect_parallelgroups::PGROUP_SEPARATE_GROUPS;
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['empty']); // Empty groups => nothing created

        $DB->expectAt($iridx++, 'insert_record', array('local_campusconnect_pgroup', $insdata['onegroup'][0]));
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['onegroup']); // Single group => no Moodle group created

        for ($i=0; $i<count($insdata['multiplegroups']); $i++) {
            $ins = clone $insdata['multiplegroups'][$i];
            $ins->groupid = 'created'; // Dummy group id from testing class
            $comment = isset($pgroupsdata['multiplegroups'][$i]->comment) ? $pgroupsdata['multiplegroups'][$i]->comment : null;
            $DB->expectAt($iridx++, 'insert_record', array('local_campusconnect_pgroup', $ins));
            $DB->expectAt($cgidx++, 'mock_create_group', array($course->id, $pgroupsdata['multiplegroups'][$i]->title, $comment));;
        }
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['multiplegroups']);

        // PGROUP_SEPARATE_COURSEs - pgroups should be created, but no Moodle groups (as only 1 group per course)
        $pgmode = campusconnect_parallelgroups::PGROUP_SEPARATE_COURSES;
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['empty']); // Empty groups => nothing created

        $DB->expectAt($iridx++, 'insert_record', array('local_campusconnect_pgroup', $insdata['onegroup'][0]));
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['onegroup']);

        try {
            $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['multiplegroups']);
            $this->fail("Expected a coding exception when attempting to update with multiple groups when in 'separate courses' mode");
        } catch (coding_exception $e) {

        }

        // PGROUP_SEPARATE_LECTURERS - pgroups created and Moodle groups for the course with more than one group
        $pgmode = campusconnect_parallelgroups::PGROUP_SEPARATE_LECTURERS;
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['empty']); // Empty groups => nothing created

        $DB->expectAt($iridx++, 'insert_record', array('local_campusconnect_pgroup', $insdata['onegroup'][0]));
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['onegroup']);

        for ($i=0; $i<count($insdata['multiplegroups']); $i++) {
            $ins = clone $insdata['multiplegroups'][$i];
            $ins->groupid = 'created'; // Dummy group id from testing class
            $comment = isset($pgroupsdata['multiplegroups'][$i]->comment) ? $pgroupsdata['multiplegroups'][$i]->comment : null;
            $DB->expectAt($iridx++, 'insert_record', array('local_campusconnect_pgroup', $ins));
            $DB->expectAt($cgidx++, 'mock_create_group', array($course->id, $pgroupsdata['multiplegroups'][$i]->title, $comment));;
        }
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['multiplegroups']);

        $DB->expectCallCount('mock_create_group', $cgidx);
        $DB->expectCallCount('mock_update_group', $ugidx);
        $DB->expectCallCount('insert_record', $iridx);
        $DB->expectCallCount('update_record', $uridx);
    }

    public function test_match_groups() {
        /** @var $DB SimpleMock */
        global $DB;

        $pgclass = new campusconnect_parallelgroups_testing(new campusconnect_ecssettings(null, 'ecs1'), -10);

        $pgroups = array(
            array(
                (object)array('id' => 'grp1', 'title' => 'GroupA'),
            ),
            array(
                (object)array('id' => 'grp2', 'title' => 'GroupB'),
                (object)array('id' => 'grp3', 'title' => 'GroupC'),
            ),
            array(
                (object)array('id' => 'grp4', 'title' => 'GroupD'),
                (object)array('id' => 'grp5', 'title' => 'GroupE'),
                (object)array('id' => 'grp6', 'title' => 'GroupF'),
            ),
        );

        $gridx = 0; // Get records select

        // No existing groups => all end up in the 'notmatched' array
        $DB->setReturnValueAt($gridx++, 'get_records', array());
        list($matched, $notmatched) = $pgclass->match_parallel_groups_to_courses($pgroups);
        $this->assertEqual($matched, array());
        $this->assertEqual($notmatched, $pgroups);

        // One set of existing groups matched
        $ret = array(
            'grp1' => (object)array('id' => -1, 'cmsgroupid' => 'grp1', 'courseid' => -20)
        );
        $DB->setReturnValueAt($gridx++, 'get_records', $ret);
        list($matched, $notmatched) = $pgclass->match_parallel_groups_to_courses($pgroups);
        $this->assertEqual($matched, array(-20 => $pgroups[0]));
        $this->assertEqual($notmatched, array($pgroups[1], $pgroups[2]));

        // All sets of existing groups matched
        $ret = array(
            'grp1' => (object)array('id' => -1, 'cmsgroupid' => 'grp1', 'courseid' => -20),
            'grp2' => (object)array('id' => -2, 'cmsgroupid' => 'grp2', 'courseid' => -30),
            'grp3' => (object)array('id' => -3, 'cmsgroupid' => 'grp3', 'courseid' => -30),
            'grp5' => (object)array('id' => -4, 'cmsgroupid' => 'grp5', 'courseid' => -40),
        );
        $DB->setReturnValueAt($gridx++, 'get_records', $ret);
        list($matched, $notmatched) = $pgclass->match_parallel_groups_to_courses($pgroups);
        $this->assertEqual($matched, array(-20 => $pgroups[0], -30 => $pgroups[1], -40 => $pgroups[2]));
        $this->assertEqual($notmatched, array());

        // Groups with overlapping courseids
        $ret = array(
            'grp1' => (object)array('id' => -1, 'cmsgroupid' => 'grp1', 'courseid' => -20),
            'grp2' => (object)array('id' => -2, 'cmsgroupid' => 'grp2', 'courseid' => -30),
            'grp3' => (object)array('id' => -3, 'cmsgroupid' => 'grp3', 'courseid' => -30),
            'grp5' => (object)array('id' => -4, 'cmsgroupid' => 'grp5', 'courseid' => -30),
        );
        $DB->setReturnValueAt($gridx++, 'get_records', $ret);
        list($matched, $notmatched) = $pgclass->match_parallel_groups_to_courses($pgroups);
        $this->assertEqual($matched, array(-20 => $pgroups[0], -30 => $pgroups[1]));
        $this->assertEqual($notmatched, array($pgroups[2]));

        $DB->expectCallCount('get_records', $gridx);
    }

    public function test_update_groups() {
        /** @var $DB SimpleMock */
        global $DB;

        $ecssettings = new campusconnect_ecssettings(null, 'ecs1');
        $resourceid = -20;
        $pgclass = new campusconnect_parallelgroups_testing($ecssettings, $resourceid);
        $course = (object)array('id' => -30);

        $pgroupsdata = arraY();
        $pgroupsdata['empty'] = array();
        $pgroupsdata['onegroup'] = array(
            (object)array(
                'title' => 'GroupA',
                'id' => 'grp1',
                'comment' => 'The first group',
                'lecturer' => 'Fred Bloggs'
            )
        );
        $pgroupsdata['multiplegroups'] = array(
            (object)array(
                'title' => 'GroupA',
                'id' => 'grp1',
                'comment' => 'The first group',
                'lecturers' => 'Fred Bloggs'
            ),
            (object)array(
                'title' => 'GroupB',
                'id' => 'grp2',
                'comment' => 'The second group',
                'lecturers' => 'Fred Bloggs'
            ),
            (object)array(
                'title' => 'GroupC',
                'id' => 'grp3',
                'lecturers' => 'Bob Smith'
            ),
            (object)array(
                'title' => 'GroupD',
                'id' => 'grp4',
                'comment' => 'The last group',
                'lecturers' => 'Joe Bloggs'
            )
        );
        $insdata = array();
        $groupid = -10;
        $id = -101;
        foreach ($pgroupsdata as $key => $pgroups) {
            $ins = array();
            foreach ($pgroups as $pgroup) {
                $ins[] = (object)array(
                    'ecsid' => $ecssettings->get_id(),
                    'resourceid' => $resourceid,
                    'courseid' => $course->id,
                    'cmsgroupid' => $pgroup->id,
                    'grouptitle' => $pgroup->title,
                    'groupid' => $groupid,
                    'groupexists' => $groupid,
                    'id' => $id
                );
            }
            $insdata[$key] = $ins;
            $groupid -= 10;
            $id -= 10;
        }

        $DB->setReturnValue('get_records', array());

        $cgidx = 0; // Number of times groups created
        $ugidx = 0; // Number of times groups updated
        $iridx = 0; // Number of records inserted
        $uridx = 0; // Number of records updated
        $grsidx = 0; // Number of times get_records_sql called

        // Expect nothing to be updated for PGROUP_NONE
        $pgmode = campusconnect_parallelgroups::PGROUP_NONE;
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['empty']);
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['multiplegroups']);

        // PGROUP_ONE_COURSE - pgroups should be created, but no Moodle groups
        $pgmode = campusconnect_parallelgroups::PGROUP_ONE_COURSE;
        $DB->setReturnValueAt($grsidx++, 'get_records_sql', array());
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['empty']); // Empty groups => nothing created

        // pgroup mapped and group still exists => no changes expected
        $ret = clone $insdata['onegroup'][0];
        $ret->groupexists = -10;
        $ret = array($ret->cmsgroupid => $ret);
        $DB->setReturnValueAt($grsidx++, 'get_records_sql', $ret);
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['onegroup']);

        // pgroup mapped but group does not exist => no changes expected (as only one group per course)
        $ret = clone $insdata['onegroup'][0];
        $ret->groupexists = null;
        $ret = array($ret->cmsgroupid => $ret);
        $DB->setReturnValueAt($grsidx++, 'get_records_sql', $ret);
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['onegroup']);

        // pgroup mapped, but name changed => expect update record
        $ret = clone $insdata['onegroup'][0];
        $ret->groupexists = null;
        $ret->grouptitle = 'oldtitle';
        $ret->id = -15;
        $ret = array($ret->cmsgroupid => $ret);
        $DB->setReturnValueAt($grsidx++, 'get_records_sql', $ret);
        $ins = (object)array('grouptitle' => $insdata['onegroup'][0]->grouptitle, 'id' => -15);
        $DB->expectAt($uridx++, 'update_record', array('local_campusconnect_pgroup', $ins));
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['onegroup']);

        // PGROUP_SEPARATE_LECTURERS - pgroups created and Moodle groups
        // No changes to the group data
        $pgmode = campusconnect_parallelgroups::PGROUP_SEPARATE_LECTURERS;
        $ret = array();
        foreach ($insdata['multiplegroups'] as $ins) {
            $gp = clone $ins;
            $ret[$gp->cmsgroupid] = $gp;
        }
        $DB->setReturnValueAt($grsidx++, 'get_records_sql', $ret);
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['multiplegroups']); // No changes expected.

        // One group name changed - expect the name to be updated + group to be updated
        $ret = array();
        foreach ($insdata['multiplegroups'] as $ins) {
            $gp = clone $ins;
            $ret[$gp->cmsgroupid] = $gp;
        }
        $ret['grp2']->grouptitle = 'oldtitle';
        $DB->setReturnValueAt($grsidx++, 'get_records_sql', $ret);
        $ins = (object)array('grouptitle' => $insdata['multiplegroups'][1]->grouptitle, 'id' => $ret['grp2']->id);
        $DB->expectAt($uridx++, 'update_record', array('local_campusconnect_pgroup', $ins));
        $DB->expectAt($ugidx++, 'mock_update_group', array($ret['grp2']->groupid, $course->id,
                                                          $pgroupsdata['multiplegroups'][1]->title,
                                                          $pgroupsdata['multiplegroups'][1]->comment));
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['multiplegroups']);

        // One pgroup missing - expect the pgroup and group to be created
        $ret = array();
        foreach ($insdata['multiplegroups'] as $ins) {
            $gp = clone $ins;
            $ret[$gp->cmsgroupid] = $gp;
        }
        unset($ret['grp2']);
        $DB->setReturnValueAt($grsidx++, 'get_records_sql', $ret);
        $ins = clone $insdata['multiplegroups'][1];
        unset($ins->groupexists, $ins->id);
        $ins->groupid = 'created'; // Dummy group id from testing class
        $DB->expectAt($iridx++, 'insert_record', array('local_campusconnect_pgroup', $ins));
        $DB->expectAt($cgidx++, 'mock_create_group', array($course->id,
                                                          $pgroupsdata['multiplegroups'][1]->title,
                                                          $pgroupsdata['multiplegroups'][1]->comment));
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['multiplegroups']);

        // One Moodle group missing - expect the group to be created and Moodle group updated
        $ret = array();
        foreach ($insdata['multiplegroups'] as $ins) {
            $gp = clone $ins;
            $ret[$gp->cmsgroupid] = $gp;
        }
        $ret['grp2']->groupexists = null;
        $DB->setReturnValueAt($grsidx++, 'get_records_sql', $ret);
        $ins = (object)array('groupid' => 'created', 'grouptitle' => $insdata['multiplegroups'][1]->grouptitle,
                             'id' => $ret['grp2']->id);
        $DB->expectAt($uridx++, 'update_record', array('local_campusconnect_pgroup', $ins));
        $DB->expectAt($cgidx++, 'mock_create_group', array($course->id,
                                                          $pgroupsdata['multiplegroups'][1]->title,
                                                          $pgroupsdata['multiplegroups'][1]->comment));
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['multiplegroups']);

        // All pgroups missing - expect each one to be created
        $ret = array();
        foreach ($insdata['multiplegroups'] as $ins) {
            $gp = clone $ins;
            $gp->cmsgroupid .= 'changed';
            $ret[$gp->cmsgroupid] = $gp;
        }
        $DB->setReturnValueAt($grsidx++, 'get_records_sql', $ret);
        for ($i=0; $i<count($insdata['multiplegroups']); $i++) {
            $ins = clone $insdata['multiplegroups'][$i];
            unset($ins->groupexists, $ins->id);
            $ins->groupid = 'created'; // Dummy group id from testing class
            $comment = isset($pgroupsdata['multiplegroups'][$i]->comment) ? $pgroupsdata['multiplegroups'][$i]->comment : null;
            $DB->expectAt($iridx++, 'insert_record', array('local_campusconnect_pgroup', $ins));
            $DB->expectAt($cgidx++, 'mock_create_group', array($course->id, $pgroupsdata['multiplegroups'][$i]->title, $comment));;
        }
        $pgclass->update_parallel_groups($course, $pgmode, $pgroupsdata['multiplegroups']);

        // Check we had the expected number of calls to each function.
        $DB->expectCallCount('mock_create_group', $cgidx);
        $DB->expectCallCount('mock_update_group', $ugidx);
        $DB->expectCallCount('insert_record', $iridx);
        $DB->expectCallCount('update_record', $uridx);
        $DB->expectCallCount('get_records_sql', $grsidx);
    }
}