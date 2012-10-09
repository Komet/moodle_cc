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
 * Tests for the course request processing for CampusConnect
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

global $CFG;
require_once($CFG->dirroot.'/local/campusconnect/course.php');
require_once($CFG->dirroot.'/local/campusconnect/simpletest/enabledtests.php');

global $DB;
/** @noinspection PhpDynamicAsStaticMethodCallInspection */
Mock::generate(get_class($DB), 'mockDB_coursecreate', array('mock_create_course',
                                                            'mock_update_course',
                                                            'mock_delete_course'));


class MatchSpecified extends EqualExpectation {

    function __construct($expected, $msg = '') {
        if (!is_object($expected)) {
            throw new coding_exception('MatchSpecified expects to be given an object');
        }
        parent::__construct($expected, $msg);
    }

    function test($compare) {
        foreach ($this->_value as $expname => $expvalue) {
            if (!isset($compare->$expname)) {
                return false;
            }
            if ($compare->$expname != $expvalue) {
                return false;
            }
        }
        return true;
    }
}

class local_campusconnect_course_test extends UnitTestCase {
    /** @var campusconnect_ecssettings[] $settings */
    protected $settings = array();
    protected $mid = array();
    protected $realDB = null;

    public function skip() {
        $this->skipIf(defined('SKIP_CAMPUSCONNECT_COURSE_TESTS'), 'Skipping course tests, to save time');
    }

    public function setUp() {
        // Override the $DB global.
        global $DB;
        $this->realDB = $DB;
        /** @noinspection PhpUndefinedClassInspection */
        $DB = new mockDB_coursecreate();

        // Create the connections for testing.
        $names = array(1 => 'unittest1', 2 => 'unittest2', 3 => 'unittest3');
        foreach ($names as $key => $name) {
            $this->settings[$key] = new campusconnect_ecssettings(null, $name);
            $this->mid[$key] = $key * 10; // Real MID not needed, as no actual connection is created.
        }
    }

    public function tearDown() {
        // Restore the $DB global.
        global $DB;
        $DB = $this->realDB;

        $this->mid = array();
    }

    public function test_create_course() {
        /** @var $DB SimpleMock */
        global $DB;

        // Course create request from participant 1 to participant 2
        $resourceid = -10;
        $ecssettings = $this->settings[1];
        $dstmid = $this->mid[1];
        $srcmid = $this->mid[2];
        $directoryid1 = -1000;
        $directoryid2 = -1010;
        $course = (object)array(
            'basicData' => (object)array(
                'organisation' => 'Synergy Learning',
                'id' => 'abc_1234',
                'title' => 'Test course creation (updating)',
            ),
            'allocations' => array(
                (object)array(
                    'parentID' => $directoryid1,
                    'order' => 6
                ),
                (object)array(
                    'parentID' => $directoryid2,
                    'order' => 9
                )
            )
        );
        $transferdetails = new campusconnect_details((object)array(
            'url' => 'fakeurl',
            'receivers' => array(0 => (object)array('itsyou' => 1, 'mid' => $dstmid)),
            'senders' => array(0 => (object)array('mid' => $srcmid)),
            'owner' => (object)array('itsyou' => 0),
            'content_type' => campusconnect_event::RES_COURSE
        ));
        $cmsparticipant = (object) array(
            'id' => -30,
            'ecsid' => $ecssettings->get_id(),
            'mid' => $srcmid,
            'export' => 0,
            'import' => 1,
            'importtype' => campusconnect_participantsettings::IMPORT_CMS,
            'displayname' => 'Test CMS'
        );
        $newcourseid1 = -40;
        $newcourseid2 = -50;
        $crsrec1 = (object) array(
            'courseid' => $newcourseid1,
            'resourceid' => $resourceid,
            'ecsid' => $ecssettings->get_id(),
            'mid' => $srcmid,
            'internallink' => 0
        );
        $crsrec2 = (object)(array)$crsrec1;
        $crsrec2->courseid = $newcourseid2;
        $crsrec2->internallink = $newcourseid1;
        $coursedata1 = (object)array(
            'fullname' => $course->basicData->title,
            'shortname' => $course->basicData->title,
            'category' => $directoryid1 - 1 // Unit testing maps category => directory -1
        );
        $coursedata2 = (object)(array)$coursedata1;
        $coursedata2->category = $directoryid2 - 1;  // Unit testing maps category => directory -1

        // Set up the expectations
        $idx = 0;
        $DB->setReturnValueAt($idx++, 'get_records', array($cmsparticipant)); // Get CMS participant
        $DB->setReturnValueAt($idx++, 'get_records', array()); // Use default metadata mappings
        $DB->setReturnValueAt($idx++, 'get_records', array()); // No existing crs record

        $idx = 0;
        $DB->setReturnValueAt($idx++, 'mock_create_course', $newcourseid1);
        $DB->setReturnValueAt($idx++, 'mock_create_course', $newcourseid2);

        $DB->expectAt(0, 'mock_create_course', array(new MatchSpecified($coursedata1))); // Create the course in the first category
        $DB->expectAt(1, 'mock_create_course', array(new MatchSpecified($coursedata2))); // Create the course in the second category
        $DB->expectAt(0, 'insert_record', array('local_campusconnect_crs', $crsrec1)); // Create the crs record
        $DB->expectAt(1, 'insert_record', array('local_campusconnect_crs', $crsrec2)); // Create a crs record that redirects to the first course

        campusconnect_course::create($resourceid, $ecssettings, $course, $transferdetails);
    }

    public function test_update_course() {
        /** @var $DB SimpleMock */
        global $DB;

        // Course create request from participant 1 to participant 2
        $resourceid = -10;
        $ecssettings = $this->settings[1];
        $dstmid = $this->mid[1];
        $srcmid = $this->mid[2];
        $directoryid1 = -1000;
        $directoryid2 = -1010;
        $course = (object)array(
            'basicData' => (object)array(
                'organisation' => 'Synergy Learning',
                'id' => 'abc_1234',
                'title' => 'Test course creation (updating)',
            ),
            'allocations' => array(
                (object)array(
                    'parentID' => $directoryid1,
                    'order' => 6
                ),
                (object)array(
                    'parentID' => $directoryid2,
                    'order' => 9
                )
            )
        );
        $transferdetails = new campusconnect_details((object)array(
            'url' => 'fakeurl',
            'receivers' => array(0 => (object)array('itsyou' => 1, 'mid' => $dstmid)),
            'senders' => array(0 => (object)array('mid' => $srcmid)),
            'owner' => (object)array('itsyou' => 0),
            'content_type' => campusconnect_event::RES_COURSE
        ));
        $cmsparticipant = (object) array(
            'id' => -30,
            'ecsid' => $ecssettings->get_id(),
            'mid' => $srcmid,
            'export' => 0,
            'import' => 1,
            'importtype' => campusconnect_participantsettings::IMPORT_CMS,
            'displayname' => 'Test CMS'
        );
        $newcourseid1 = -40;
        $newcourseid2 = -50;
        $crsrec1 = (object) array(
            'id' => -60,
            'courseid' => $newcourseid1,
            'resourceid' => $resourceid,
            'ecsid' => $ecssettings->get_id(),
            'mid' => $srcmid,
            'internallink' => 0
        );
        $crsrec2 = (object)(array)$crsrec1;
        $crsrec2->id = -70;
        $crsrec2->courseid = $newcourseid2;
        $crsrec2->internallink = $newcourseid1;

        $coursedata1 = (object)array(
            'id' => $newcourseid1,
            'fullname' => $course->basicData->title,
            'shortname' => $course->basicData->title,
            'category' => $directoryid1 - 1 // Unit testing maps category => directory -1
        );
        $coursedata2 = (object)(array)$coursedata1;
        $coursedata2->category = $directoryid2 - 1; // Unit testing maps category => directory -1
        $coursedata2->id = $newcourseid2;

        $updcoursedata1 = (object)(array)$coursedata1;
        unset($updcoursedata1->category);
        $updcoursedata2 = (object)(array)$coursedata2;
        unset($updcoursedata2->category);

        /**
         * Test update that only changes course data (but does not add / remove course from categories)
         */
        // Set up the expectations
        $gr_idx = 0;
        $DB->setReturnValueAt($gr_idx++, 'get_records', array($cmsparticipant)); // Get CMS participant
        $DB->setReturnValueAt($gr_idx++, 'get_records', array()); // Use default metadata mappings
        $DB->setReturnValueAt($gr_idx++, 'get_records', array($crsrec1, $crsrec2)); // Existing crs records

        $sm_idx = 0;
        $DB->setReturnValueAt($sm_idx++, 'get_records_sql_menu', array($crsrec1->id => $coursedata1->category, $crsrec2->id => $coursedata2->category));

        $DB->setReturnValue('record_exists', true); // Course still exists

        $uc_idx = 0;
        $DB->expectAt($uc_idx++, 'mock_update_course', array(new MatchSpecified($updcoursedata1))); // Update the course in the first category
        $DB->expectAt($uc_idx++, 'mock_update_course', array(new MatchSpecified($updcoursedata2))); // Update the course in the second category

        campusconnect_course::update($resourceid, $ecssettings, $course, $transferdetails);

        /**
         * Test update that moves course into a new category (removing from original category)
         */
        $directoryid3 = -1020;
        $course->allocations = array(
            (object)array(
                'parentID' => $directoryid2,
                'order' => 9
            ),
            (object)array(
                'parentID' => $directoryid3,
                'order' => 15
            )
        );

        $DB->setReturnValueAt($gr_idx++, 'get_records', array($cmsparticipant)); // Get CMS participant
        $DB->setReturnValueAt($gr_idx++, 'get_records', array()); // Use default metadata mappings
        $DB->setReturnValueAt($gr_idx++, 'get_records', array($crsrec1->id => $crsrec1, $crsrec2->id => $crsrec2)); // Existing crs records

        $DB->setReturnValueAt($sm_idx++, 'get_records_sql_menu', array($crsrec1->id => $coursedata1->category, $crsrec2->id => $coursedata2->category));

        $DB->setReturnValue('record_exists', true); // Course still exists

        $coursedata1->category = $directoryid3 - 1;
        $DB->expectAt($uc_idx++, 'mock_update_course', array(new MatchSpecified($updcoursedata1))); // Update the course in the first category
        $DB->expectAt($uc_idx++, 'mock_update_course', array(new MatchSpecified($updcoursedata2))); // Update the course in the second category

        $sf_idx = 0; // Move the course into the new category
        $DB->expectAt($sf_idx++, 'set_field', array('course', 'category', $directoryid3 - 1, array('id' => $coursedata1->id)));

        campusconnect_course::update($resourceid, $ecssettings, $course, $transferdetails);

        /**
         * Test update that creates a single new allocation
         */
        $course->allocations = array(
            (object)array(
                'parentID' => $directoryid1,
                'order' => 6
            ),
            (object)array(
                'parentID' => $directoryid2,
                'order' => 9
            ),
            (object)array(
                'parentID' => $directoryid3,
                'order' => 15
            )
        );
        $coursedata1->category = $directoryid1 - 1;
        $coursedata2->category = $directoryid2 - 1;
        $coursedata3 = (object)(array)$coursedata2;
        unset($coursedata3->id);
        $coursedata3->category = $directoryid3 - 1; // Unit testing maps category => directory -1
        $newcourseid3 = -60;
        $crsrec3 = (object)(array)$crsrec2;
        unset($crsrec3->id);
        $crsrec3->courseid = $newcourseid3;

        $DB->setReturnValueAt($gr_idx++, 'get_records', array($cmsparticipant)); // Get CMS participant
        $DB->setReturnValueAt($gr_idx++, 'get_records', array()); // Use default metadata mappings
        $DB->setReturnValueAt($gr_idx++, 'get_records', array($crsrec1->id => $crsrec1, $crsrec2->id => $crsrec2)); // Existing crs records

        $DB->setReturnValueAt($sm_idx++, 'get_records_sql_menu', array($crsrec1->id => $coursedata1->category, $crsrec2->id => $coursedata2->category));

        $DB->setReturnValue('record_exists', true); // Course still exists
        $DB->setReturnValueAt(0, 'mock_create_course', $newcourseid3); // Create new course.

        $DB->expectAt($uc_idx++, 'mock_update_course', array(new MatchSpecified($updcoursedata1))); // Update the course in the first category
        $DB->expectAt($uc_idx++, 'mock_update_course', array(new MatchSpecified($updcoursedata2))); // Update the course in the second category
        $DB->expectAt(0, 'mock_create_course', array(new MatchSpecified($coursedata3))); // Create a new course to hold the new internal link

        $DB->expectAt(0, 'insert_record', array('local_campusconnect_crs', $crsrec3));

        campusconnect_course::update($resourceid, $ecssettings, $course, $transferdetails);

        /**
         * Test update that removes a single allocation
         */
        $course->allocations = array(
            (object)array(
                'parentID' => $directoryid2,
                'order' => 9
            )
        );
        $coursedata1->category = $directoryid1 - 1;
        $coursedata2->category = $directoryid2 - 1;

        $DB->setReturnValueAt($gr_idx++, 'get_records', array($cmsparticipant)); // Get CMS participant
        $DB->setReturnValueAt($gr_idx++, 'get_records', array()); // Use default metadata mappings

        $DB->setReturnValueAt($gr_idx++, 'get_records', array($crsrec1->id => $crsrec1, $crsrec2->id => $crsrec2)); // Existing crs records
        $DB->setReturnValueAt($sm_idx++, 'get_records_sql_menu', array($crsrec1->id => $coursedata1->category, $crsrec2->id => $coursedata2->category));

        $DB->setReturnValue('record_exists', true); // Course still exists

        $coursedata1->category = $directoryid3 - 1;
        $DB->expectAt($uc_idx++, 'mock_update_course', array(new MatchSpecified($updcoursedata1))); // Update the course1 (now in the second category)
        // Move the 'real' course from the first category into the second category
        $DB->expectAt($sf_idx++, 'set_field', array('course', 'category', $coursedata2->category, array('id' => $coursedata1->id)));

        $DB->expectAt(0, 'mock_delete_course', array($coursedata2->id)); // Delete the 2nd course
        $DB->expectAt(0, 'delete_records', array('local_campusconnect_crs', array('id' => $crsrec2->id))); // Delete the internal link associated with this course.

        campusconnect_course::update($resourceid, $ecssettings, $course, $transferdetails);

        // Final check of call counts:
        $DB->expectCallCount('get_records', $gr_idx);
        $DB->expectCallCount('get_records_sql_menu', $sm_idx);
        $DB->expectCallCount('mock_update_course', $uc_idx);
        $DB->expectCallCount('set_field', $sf_idx);

        $DB->expectOnce('delete_records');
        $DB->expectOnce('mock_delete_course');
        $DB->expectOnce('insert_record');
        $DB->expectOnce('mock_create_course');
    }

    public function test_delete_course() {
        /** @var $DB SimpleMock */
        global $DB;

        $resourceid = -10;
        $ecssettings = $this->settings[1];
        $srcmid = $this->mid[2];
        $newcourseid1 = -40;
        $newcourseid2 = -50;
        $crsrec1 = (object) array(
            'id' => -60,
            'courseid' => $newcourseid1,
            'resourceid' => $resourceid,
            'ecsid' => $ecssettings->get_id(),
            'mid' => $srcmid,
            'internallink' => 0
        );
        $crsrec2 = (object)(array)$crsrec1;
        $crsrec2->id = -70;
        $crsrec2->courseid = $newcourseid2;
        $crsrec2->internallink = $newcourseid1;

        $DB->setReturnValueAt(0, 'get_records', array($crsrec1, $crsrec2)); // Existing crs records

        $dc_idx = 0; // Delete the courses associated with this resource.
        $DB->expectAt($dc_idx++, 'mock_delete_course', array($newcourseid1));
        $DB->expectAt($dc_idx++, 'mock_delete_course', array($newcourseid2));

        $dr_idx = 0; // Delete the matching crs records.
        $DB->expectAt($dr_idx++, 'delete_records', array('local_campusconnect_crs', array('id' => $crsrec1->id)));
        $DB->expectAt($dr_idx++, 'delete_records', array('local_campusconnect_crs', array('id' => $crsrec2->id)));

        $DB->expectCallCount('mock_delete_course', $dc_idx);
        $DB->expectCallCount('delete_records', $dr_idx);

        campusconnect_course::delete($resourceid, $ecssettings);
    }

    public function test_redirect() {
        /** @var $DB SimpleMock */
        global $DB;

        $resourceid = -10;
        $ecssettings = $this->settings[1];
        $srcmid = $this->mid[2];
        $courseid1 = -40;
        $courseid2 = -50;
        $crsrec1 = (object) array(
            'id' => -60,
            'courseid' => $courseid1,
            'resourceid' => $resourceid,
            'ecsid' => $ecssettings->get_id(),
            'mid' => $srcmid,
            'internallink' => 0
        );
        $crsrec2 = (object)(array)$crsrec1;
        $crsrec2->id = -70;
        $crsrec2->courseid = $courseid2;
        $crsrec2->internallink = $courseid1;

        $gr_idx = 0;

        // No associated crs record => expect no redirect
        $DB->setReturnValueAt($gr_idx++, 'get_record', false);
        $this->assertFalse(campusconnect_course::check_redirect(-1));

        // 'Real' course => expect no redirect
        $DB->setReturnValueAt($gr_idx++, 'get_record', $crsrec1);
        $this->assertFalse(campusconnect_course::check_redirect(-1));

        // 'Internal link' course => expect redirect to course1
        $DB->setReturnValueAt($gr_idx++, 'get_record', $crsrec2);
        $expdurl = new moodle_url('/course/view.php', array('id' => $courseid1));
        $this->assertEqual(campusconnect_course::check_redirect(-1)->out(), $expdurl->out());
    }
}