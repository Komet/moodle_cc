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
 * @copyright  2013 Synergy Learning
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
require_once($CFG->dirroot.'/local/campusconnect/directorytree.php');
require_once($CFG->dirroot.'/local/campusconnect/membership.php');
require_once($CFG->dirroot.'/local/campusconnect/simpletest/enabledtests.php');

/**
 * Class local_campusconnect_coursemembers_test
 * @group local_campusconnect
 */
class local_campusconnect_coursemembers_test extends advanced_testcase {
    /** @var campusconnect_ecssettings[] $settings */
    protected $settings = array();
    protected $mid = array();
    /** @var campusconnect_directory[] $directory */
    protected $directory = array();
    /** @var campusconnect_details $transferdetails */
    protected $transferdetails = null;
    protected $users = array();

    protected $usernames = array('user1', 'user2', 'user3', 'user4');

    protected $directorydata = array(1001 => 'dir1', 1002 => 'dir2', 1003 => 'dir3');
    protected $coursedata = '
    {
        "lectureID": "abc_1234",
        "title": "Test course creation",
        "organisation": "Synergy Learning",
        "organisationalUnits":
        [
            {
                "id": "org01",
                "title": "Org1 title"
            },
            {
                "id": "org02",
                "title": "Org2 title"
            }
        ],
        "term": "Summer 2013",
        "termID": "20131",
        "lectureType": "online",
        "hoursPerWeek": 2,
        "groupScenario": 0,
        "degreeProgrammes":
        [
            {
                "id": "programmeID",
                "title": "Test programme",
                "code": "pr21",
                "courseUnitYearOfStudy":
                {
                    "from": 5,
                    "to": 8
                }
            }
        ],
        "allocations":
        [
            {
                "parentID": "id1001",
                "order": 6
            },
            {
                "parentID": "id1002",
                "order": 9
            }
        ],
        "comment1": "This just a test",
        "recommendedReading": "Lord of the Rings",
        "prerequisites": "ability to breathe",
        "lectureAssessmentType": "guessing",
        "lectureTopics": "things + other stuff",
        "linkToCurriculumt": "none",
        "targetAudiences":
        [
            "everyone"
        ],
        "links":
        [
            {
                "href": "http://en.wikipedia.org",
                "title": "Wikipedia"
            }
        ],
        "groups":
        [
            {
                "title": "Test Group1",
                "comment": "This is a group",
                "lecturers":
                [
                    {
                        "firstName": "Humphrey",
                        "lastName": "Bogart"
                    },
                    {
                        "firstName": "Sam",
                        "lastName": "Spade"
                    }
                ],
                "maxParticipants": 20
            },
            {
                "title": "Test Group2",
                "lecturers":
                [
                    {
                        "firstName": "Humphrey",
                        "lastName": "Bogart"
                    }
                ]
            },
            {
            }
        ],
        "modules":
        [
            {
                "id": "mod01",
                "title": "First module",
                "number": 5,
                "credits": 20,
                "hoursPerWeek": 10,
                "duration": 5,
                "cycle": "weekly"
            }
        ]
    }
    ';

    protected $coursemembers = '
    {
        "lectureID": "abc_1234",
        "members":
        [
            {
                "personID": "user1",
                "role": 0,
                "groups":
                [
                    {
                        "num": 0,
                        "role": 0
                    }
                ]
            },
            {
                "personID": "user2",
                "role": 1
            },
            {
                "personID": "user3",
                "role": 1,
                "groups":
                [
                    {
                        "num": 1,
                        "role": 1
                    },
                    {
                        "num": 2,
                        "role": 2
                    }
                ]
            },
            {
                "personID": "user4",
                "groups":
                [
                    {
                        "num": 1
                    }
                ]
            }
        ]
    }
    ';


    public function setUp() {
        global $DB;

        if (defined('SKIP_CAMPUSCONNECT_COURSEMEMBERS_TESTS')) {
            $this->markTestSkipped('Skipping connect tests, to save time');
        }

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
            $this->settings[$key] = $ecs;
            $this->mid[$key] = $key * 10; // Real MID not needed, as no actual connection is created.
        }

        // Set participant 1 as the CMS for participant 2
        $part = (object)array(
            'ecsid' => $this->settings[2]->get_id(),
            'mid' => $this->mid[1],
            'export' => 0,
            'import' => 1,
            'importtype' => campusconnect_participantsettings::IMPORT_CMS,
        );
        $DB->insert_record('local_campusconnect_part', $part);

        // Create the directories for the courses + map on to categories.
        $dirtree = new campusconnect_directorytree();
        $dirtree->create(1000, 'idroot', 'Dir root', $this->settings[2]->get_id(), $this->mid[1]);
        foreach ($this->directorydata as $id => $name) {
            $dirid = 'id'.$id;
            $dir = new campusconnect_directory();
            $dir->create($id, 'idroot', $dirid, 'idroot', $name, 1);
            $this->directory[] = $dir;
        }
        $category = $this->getDataGenerator()->create_category(array('name' => 'category_tree'));
        $dirtree->map_category($category->id);
        $dirtree->create_all_categories();
        // Reload the directory objects after creating the categories for them.
        foreach ($this->directory as $key => $dir) {
            $this->directory[$key] = $dirtree->get_directory($dir->get_directory_id());
        }

        // Create some fake transfer details for the requests.
        $this->transferdetails = new campusconnect_details((object)array(
            'url' => 'fakeurl',
            'receivers' => array(0 => (object)array('itsyou' => 1, 'mid' => $this->mid[2])),
            'senders' => array(0 => (object)array('mid' => $this->mid[1])),
            'owner' => (object)array('itsyou' => 0),
            'content_type' => campusconnect_event::RES_COURSE
        ));

        // Create some users to be enrolled in the course.
        foreach ($this->usernames as $username) {
            $this->users[] = $this->getDataGenerator()->create_user(array('username' => $username));
        }
    }

    public function test_parse_memberdata() {
        global $DB;

        // Gain access to the protected function 'extract_parallel_groups'.
        $class = new ReflectionClass('campusconnect_membership');
        $extract = $class->getMethod('extract_parallel_groups');
        $extract->setAccessible(true);

        $resourceid = -10;
        $memberdata = json_decode($this->coursemembers);
        $this->assertNotEmpty($memberdata);

        $this->assertEmpty($DB->get_records('local_campusconnect_mbr'));
        campusconnect_membership::create($resourceid, $this->settings[2], $memberdata, $this->transferdetails);

        $members = $DB->get_records('local_campusconnect_mbr', array(), 'id');

        $this->assertCount(4, $members);
        $member1 = array_shift($members);
        $member2 = array_shift($members);
        $member3 = array_shift($members);
        $member4 = array_shift($members);

        $this->assertEquals('abc_1234', $member1->cmscourseid);
        $this->assertEquals('user1', $member1->personid);
        $this->assertEquals(campusconnect_membership::ROLE_LECTURER, $member1->role);
        $this->assertEquals(campusconnect_membership::STATUS_CREATED, $member1->status);
        $this->assertEquals(array(0 => campusconnect_membership::ROLE_LECTURER), $extract->invoke(null, $member1));

        $this->assertEquals('abc_1234', $member2->cmscourseid);
        $this->assertEquals('user2', $member2->personid);
        $this->assertEquals(campusconnect_membership::ROLE_STUDENT, $member2->role);
        $this->assertEquals(campusconnect_membership::STATUS_CREATED, $member2->status);
        $this->assertEquals(array(), $extract->invoke(null, $member2));

        $this->assertEquals('abc_1234', $member3->cmscourseid);
        $this->assertEquals('user3', $member3->personid);
        $this->assertEquals(campusconnect_membership::ROLE_STUDENT, $member3->role);
        $this->assertEquals(campusconnect_membership::STATUS_CREATED, $member3->status);
        $this->assertEquals(array(1 => campusconnect_membership::ROLE_STUDENT,
                                 2 => campusconnect_membership::ROLE_ASSISTANT), $extract->invoke(null, $member3));

        $this->assertEquals('abc_1234', $member4->cmscourseid);
        $this->assertEquals('user4', $member4->personid);
        $this->assertEquals(campusconnect_membership::ROLE_UNSPECIFIED, $member4->role);
        $this->assertEquals(campusconnect_membership::STATUS_CREATED, $member4->status);
        $this->assertEquals(array(1 => campusconnect_membership::ROLE_UNSPECIFIED), $extract->invoke(null, $member4));
    }

    public function test_create_members_nogroups() {

        return;

        global $DB;

        // Course create request from participant 1 to participant 2
        $resourceid = -10;
        $course = json_decode($this->coursedata);
        unset($course->groupScenario);
        unset($course->groups);
        campusconnect_course::create($resourceid, $this->settings[2], $course, $this->transferdetails);

        // Get the details of the two courses created.
        $courses = $DB->get_records_select('course', 'id > 1', array(), 'id', 'id, fullname, shortname, category, summary');
        $this->assertCount(2, $courses);
        $course1 = array_shift($courses);
        $course2 = array_shift($courses);

        // TODO - create the members.
        // Check the users are enroled on the course.
    }
}