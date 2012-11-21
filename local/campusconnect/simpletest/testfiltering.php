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
 * Tests for course import filtering for CampusConnect
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/local/campusconnect/filtering.php');
require_once($CFG->dirroot.'/local/campusconnect/simpletest/enabledtests.php');

global $DB;
/** @noinspection PhpDynamicAsStaticMethodCallInspection */
Mock::generate(get_class($DB), 'mockDB');

class local_campusconnect_filtering_test extends UnitTestCase {
    protected $realDB = null;

    public function skip() {
        $this->skipIf(defined('SKIP_CAMPUSCONNECT_COURSEFILTER_TESTS'), 'Skipping course filtering tests, to save time');
    }

    public function setUp() {
        // Override the $DB global.
        global $DB;
        $this->realDB = $DB;
        /** @noinspection PhpUndefinedClassInspection */
        $DB = new mockDB();
    }

    public function tearDown() {
        // Restore the $DB global.
        global $DB;
        $DB = $this->realDB;
    }

    public function test_check_filter_match() {
        // Test a single 'allwords' filter match
        $filter = array(
            'attribute1' => (object)array(
                'allwords' => true,
                'words' => array(),
                'createsubdirectories' => false
            )
        );
        $metadata = array(
            'attribute1' => 'testvalue',
            'attribute2' => 'fish'
        );
        $this->assertTrue(campusconnect_filtering::check_filter_match($metadata, $filter));

        // Test matching multiple 'allwords' filters
        $filter['attribute2'] = (object)array(
            'allwords' => true,
            'words' => array(),
            'createsubdirectories' => false
        );
        $filter['attribute3'] = (object)array(
            'allwords' => true,
            'words' => array(),
            'createsubdirectories' => false
        );
        $this->assertTrue(campusconnect_filtering::check_filter_match($metadata, $filter));

        // Test matching a single 'specific words' filter
        $filter = array(
            'attribute1' => (object)array(
                'allwords' => false,
                'words' => array('cat', 'testvalue', 'dog'),
                'createsubdirectories' => false
            )
        );
        $this->assertTrue(campusconnect_filtering::check_filter_match($metadata, $filter));

        // Test matching multiple 'specific words' filters
        $filter['attribute2'] = (object)array(
            'allwords' => false,
            'words' => array('cow', 'horse', 'fish'),
            'createsubdirectories' => false
        );
        $this->assertTrue(campusconnect_filtering::check_filter_match($metadata, $filter));

        // Test failing due to missing attribute in metadata
        $filter['attribute3'] = (object)array(
            'allwords' => false,
            'words' => array('lion', 'tiger'),
            'createsubdirectories' => false
        );
        $this->assertFalse(campusconnect_filtering::check_filter_match($metadata, $filter));

        // Test failing due to non-matching of attribute
        $filter = array(
            'attribute1' => (object)array(
                'allwords' => false,
                'words' => array('cat', 'testvalue', 'dog'),
                'createsubdirectories' => false
            ),
            'attribute2' => (object)array(
                'allwords' => false,
                'words' => array('cow', 'horse', 'fishes'),
                'createsubdirectories' => false
            )
        );
        $this->assertFalse(campusconnect_filtering::check_filter_match($metadata, $filter));

        // Test matching array attribute
        $filter = array(
            'attribute1' => (object)array(
                'allwords' => false,
                'words' => array('cat', 'testvalue', 'dog'),
                'createsubdirectories' => false
            )
        );
        $metadata = array(
            'attribute1' => array('big', 'small', 'testvalue'),
            'attribute2' => array('fish', 'whale', 'mermaid')
        );
        $this->assertTrue(campusconnect_filtering::check_filter_match($metadata, $filter));

        // Test failing to match array attribute
        $filter['attribute2'] = (object)array(
            'allwords' => false,
            'words' => array('lion', 'tiger', 'bear'),
            'createsubdirectories' => false
        );
        $this->assertFalse(campusconnect_filtering::check_filter_match($metadata, $filter));
    }

    public function test_find_or_create_category() {
        /** @var $DB SimpleMock */
        global $DB;

        $DB->setReturnValue('get_field', false, array('course_categories', 'id', '*')); // Category does not exist already.
        $ins = 0;
        $getf = 0;

        // Test creating the course directly in the parent category
        $filter = array(
            'attribute1' => (object)array(
                'allwords' => true,
                'words' => array(),
                'createsubdirectories' => false
            )
        );
        $metadata = array(
            'attribute1' => 'testvalue',
            'attribute2' => 'fish'
        );
        $categoryid = campusconnect_filtering::find_or_create_category($metadata, $filter, -5);
        $this->assertEqual($categoryid, array(-5));

        // Test creating the course directly in the parent category (with multiple attributes)
        $filter = array(
            'attribute1' => (object)array(
                'allwords' => true,
                'words' => array(),
                'createsubdirectories' => false
            ),
            'attribute2' => (object)array(
                'allwords' => false,
                'words' => array('fish', 'cat', 'dog'),
                'createsubdirectories' => false
            )
        );
        $categoryid = campusconnect_filtering::find_or_create_category($metadata, $filter, -5);
        $this->assertEqual($categoryid, array(-5));

        // Test creating course in subcategory of parent category
        $filter = array(
            'attribute1' => (object)array(
                'allwords' => true,
                'words' => array(),
                'createsubdirectories' => true
            )
        );
        $DB->setReturnValueAt($ins, 'insert_record', -6);
        $DB->expectAt($ins++, 'insert_record', array('course_categories', '*'));
        $DB->expectAt($getf++, 'get_field', array('course_categories', 'id', '*'));
        $categoryid = campusconnect_filtering::find_or_create_category($metadata, $filter, -5);
        $this->assertEqual($categoryid, array(-6));

        // Test creating course in two levels of subcategories
        $filter = array(
            'attribute1' => (object)array(
                'allwords' => true,
                'words' => array(),
                'createsubdirectories' => true
            ),
            'attribute2' => (object)array(
                'allwords' => false,
                'words' => array('fish', 'cat', 'dog'),
                'createsubdirectories' => true
            )
        );
        $DB->setReturnValueAt($ins, 'insert_record', -6);
        $DB->expectAt($ins++, 'insert_record', array('course_categories', '*'));
        $DB->expectAt($getf++, 'get_field', array('course_categories', 'id', '*'));
        $DB->setReturnValueAt($ins, 'insert_record', -7);
        $DB->expectAt($ins++, 'insert_record', array('course_categories', '*'));
        $DB->expectAt($getf++, 'get_field', array('course_categories', 'id', '*'));
        $categoryid = campusconnect_filtering::find_or_create_category($metadata, $filter, -5);
        $this->assertEqual($categoryid, array(-7));

        // Test creating course in subcategory from 2nd filter only
        $filter = array(
            'attribute1' => (object)array(
                'allwords' => true,
                'words' => array(),
                'createsubdirectories' => false
            ),
            'attribute2' => (object)array(
                'allwords' => false,
                'words' => array('fish', 'cat', 'dog'),
                'createsubdirectories' => true
            )
        );
        $DB->setReturnValueAt($ins, 'insert_record', -6);
        $DB->expectAt($ins++, 'insert_record', array('course_categories', '*'));
        $DB->expectAt($getf++, 'get_field', array('course_categories', 'id', '*'));
        $categoryid = campusconnect_filtering::find_or_create_category($metadata, $filter, -5);
        $this->assertEqual($categoryid, array(-6));

        // Test creating course in single level subcategories with multiple attribute values
        $filter = array(
            'attribute1' => (object)array(
                'allwords' => true,
                'words' => array(),
                'createsubdirectories' => true
            )
        );
        $metadata = array(
            'attribute1' => array('testvalue', 'testvalue2'),
            'attribute2' => array('fish', 'whale', 'mermaid')
        );
        $DB->setReturnValueAt($ins, 'insert_record', -6); // base > testvalue
        $DB->expectAt($ins++, 'insert_record', array('course_categories', '*'));
        $DB->expectAt($getf++, 'get_field', array('course_categories', 'id', '*'));
        $DB->setReturnValueAt($ins, 'insert_record', -7); // base > testvalue2
        $DB->expectAt($ins++, 'insert_record', array('course_categories', '*'));
        $DB->expectAt($getf++, 'get_field', array('course_categories', 'id', '*'));
        $categoryid = campusconnect_filtering::find_or_create_category($metadata, $filter, -5);
        $this->assertEqual($categoryid, array(-6, -7));

        // Test creating course in single level subcategories with multiple attribute values (but limited words)
        $filter = array(
            'attribute1' => (object)array(
                'allwords' => false,
                'words' => array('testvalue'),
                'createsubdirectories' => true
            )
        );
        $metadata = array(
            'attribute1' => array('testvalue', 'testvalue2'),
            'attribute2' => array('fish', 'whale', 'mermaid')
        );
        $DB->setReturnValueAt($ins, 'insert_record', -6); // base > testvalue
        $DB->expectAt($ins++, 'insert_record', array('course_categories', '*'));
        $DB->expectAt($getf++, 'get_field', array('course_categories', 'id', '*'));
        $categoryid = campusconnect_filtering::find_or_create_category($metadata, $filter, -5);
        $this->assertEqual($categoryid, array(-6));

        // Test creating course in two  levels of subcategories with multiple attribute values
        $filter = array(
            'attribute1' => (object)array(
                'allwords' => true,
                'words' => array(),
                'createsubdirectories' => true
            ),
            'attribute2' => (object)array(
                'allwords' => true,
                'words' => array(),
                'createsubdirectories'=> true
            )
        );
        $metadata = array(
            'attribute1' => array('testvalue', 'testvalue2'),
            'attribute2' => array('fish', 'whale', 'mermaid')
        );
        $DB->setReturnValueAt($ins, 'insert_record', -6); // base > testvalue
        $DB->expectAt($ins++, 'insert_record', array('course_categories', '*'));
        $DB->expectAt($getf++, 'get_field', array('course_categories', 'id', '*'));
        $DB->setReturnValueAt($ins, 'insert_record', -7); // base > testvalue > fish
        $DB->expectAt($ins++, 'insert_record', array('course_categories', '*'));
        $DB->expectAt($getf++, 'get_field', array('course_categories', 'id', '*'));
        $DB->setReturnValueAt($ins, 'insert_record', -8); // base > testvalue > whale
        $DB->expectAt($ins++, 'insert_record', array('course_categories', '*'));
        $DB->expectAt($getf++, 'get_field', array('course_categories', 'id', '*'));
        $DB->setReturnValueAt($ins, 'insert_record', -9); // base > testvalue > mermaid
        $DB->expectAt($ins++, 'insert_record', array('course_categories', '*'));
        $DB->expectAt($getf++, 'get_field', array('course_categories', 'id', '*'));
        $DB->setReturnValueAt($ins, 'insert_record', -10);  // base > testvalue2
        $DB->expectAt($ins++, 'insert_record', array('course_categories', '*'));
        $DB->expectAt($getf++, 'get_field', array('course_categories', 'id', '*'));
        $DB->setReturnValueAt($ins, 'insert_record', -11);  // base > testvalue2 > fish
        $DB->expectAt($ins++, 'insert_record', array('course_categories', '*'));
        $DB->expectAt($getf++, 'get_field', array('course_categories', 'id', '*'));
        $DB->setReturnValueAt($ins, 'insert_record', -12); // base > testvalue2 > whale
        $DB->expectAt($ins++, 'insert_record', array('course_categories', '*'));
        $DB->expectAt($getf++, 'get_field', array('course_categories', 'id', '*'));
        $DB->setReturnValueAt($ins, 'insert_record', -13); // base > testvalue2 > mermaid
        $DB->expectAt($ins++, 'insert_record', array('course_categories', '*'));
        $DB->expectAt($getf++, 'get_field', array('course_categories', 'id', '*'));
        $categoryid = campusconnect_filtering::find_or_create_category($metadata, $filter, -5);
        $this->assertEqual($categoryid, array(-7, -8, -9, -11, -12, -13));

        $DB->expectCallCount('insert_record', $ins);
        $DB->expectCallCount('get_field', $getf);
    }
}