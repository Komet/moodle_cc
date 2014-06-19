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

    // Reminder: role values are - 0 = lecturer (editingteacher); 1 = learner/student (student); 2 = assistant (teacher).
    protected $coursemembers = '
    {
        "lectureID": "abc_1234",
        "members":
        [
            {
                "personID": "user1",
                "role": 2,
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
                "role": 0,
                "groups":
                [
                    {
                        "num": 0,
                        "role": 1
                    },
                    {
                        "num": 1,
                        "role": 2
                    },
                    {
                        "num": 2,
                        "role": 1
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
        campusconnect_participantsettings::get_cms_participant(true); // Reset the cached 'cms participant' value.

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
            // statslib_test has an annoying habit of creating 'user1' + 'user2', even when not running those tests.
            if (!$user = $DB->get_record('user', array('username' => $username))) {
                $user = $this->getDataGenerator()->create_user(array('username' => $username));
            }
            $this->users[] = $user;
        }

        // Enable the campusconnect enrol plugin.
        $enabled = enrol_get_plugins(true);
        $enabled['campusconnect'] = true;
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));

        // Set up the default role mappings.
        $mappings = array(campusconnect_membership::ROLE_LECTURER => 'editingteacher',
                          campusconnect_membership::ROLE_STUDENT => 'student',
                          campusconnect_membership::ROLE_ASSISTANT => 'teacher');
        $roles = get_all_roles();
        foreach ($mappings as $ccrole => $moodlerole) {
            foreach ($roles as $role) {
                if ($role->shortname == $moodlerole) {
                    $DB->insert_record('local_campusconnect_rolemap',
                                       (object)array('ccrolename' => $ccrole,
                                                     'moodleroleid' => $role->id));
                }
            }
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
        $this->assertEquals(campusconnect_membership::ROLE_ASSISTANT, $member1->role);
        $this->assertEquals(campusconnect_membership::STATUS_CREATED, $member1->status);
        $this->assertEquals(array(0 => campusconnect_membership::ROLE_LECTURER), $extract->invoke(null, $member1));

        $this->assertEquals('abc_1234', $member2->cmscourseid);
        $this->assertEquals('user2', $member2->personid);
        $this->assertEquals(campusconnect_membership::ROLE_STUDENT, $member2->role);
        $this->assertEquals(campusconnect_membership::STATUS_CREATED, $member2->status);
        $this->assertEquals(array(), $extract->invoke(null, $member2));

        $this->assertEquals('abc_1234', $member3->cmscourseid);
        $this->assertEquals('user3', $member3->personid);
        $this->assertEquals(campusconnect_membership::ROLE_LECTURER, $member3->role);
        $this->assertEquals(campusconnect_membership::STATUS_CREATED, $member3->status);
        $this->assertEquals(array(0 => campusconnect_membership::ROLE_STUDENT,
                                 1 => campusconnect_membership::ROLE_ASSISTANT,
                                 2 => campusconnect_membership::ROLE_STUDENT), $extract->invoke(null, $member3));

        $this->assertEquals('abc_1234', $member4->cmscourseid);
        $this->assertEquals('user4', $member4->personid);
        $this->assertEquals(campusconnect_membership::ROLE_UNSPECIFIED, $member4->role);
        $this->assertEquals(campusconnect_membership::STATUS_CREATED, $member4->status);
        $this->assertEquals(array(1 => campusconnect_membership::ROLE_UNSPECIFIED), $extract->invoke(null, $member4));
    }

    protected static function get_course_enrolments($courseid, $userids) {
        global $DB;

        list($usql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $sql = "
        SELECT ue.id, ue.userid, e.enrol
          FROM {user_enrolments} ue
          JOIN {enrol} e ON e.id = ue.enrolid
         WHERE e.courseid = :courseid AND ue.userid $usql";
        $params['courseid'] = $courseid;

        return $DB->get_records_sql($sql, $params);
    }

    protected static function get_groups($courseid, $userid) {
        global $DB;

        $sql = "
        SELECT g.id, g.name
          FROM {groups} g
          JOIN {groups_members} gm ON gm.groupid = g.id
         WHERE g.courseid = :courseid AND gm.userid = :userid
         ORDER BY g.name ASC
        ";
        $params = array('courseid' => $courseid, 'userid' => $userid);

        return $DB->get_records_sql_menu($sql, $params);
    }

    public function test_create_members_nogroups() {
        global $DB;

        // Course create request from participant 1 to participant 2
        $courseresourceid = -10;
        $memberresourceid = -20;
        $course = json_decode($this->coursedata);
        unset($course->groupScenario);
        unset($course->groups);
        campusconnect_course::create($courseresourceid, $this->settings[2], $course, $this->transferdetails);

        // Get the details of the two courses created.
        $courses = $DB->get_records_select('course', 'id > 1', array(), 'id', 'id, fullname, shortname, category, summary');
        $this->assertCount(2, $courses);
        $course1 = array_shift($courses);
        $course2 = array_shift($courses);

        // Create the course members.
        $members = json_decode($this->coursemembers);
        campusconnect_membership::create($memberresourceid, $this->settings[2], $members, $this->transferdetails);
        campusconnect_membership::assign_all_roles($this->settings[2]);

        // Check the users are enroled on the 'real' course.
        $userids = array();
        foreach ($this->users as $user) {
            $userids[] = $user->id;
        }

        $userenrolments = self::get_course_enrolments($course1->id, $userids);
        $this->assertCount(4, $userenrolments);
        foreach ($userenrolments as $userenrolment) {
            $this->assertEquals('campusconnect', $userenrolment->enrol);
        }

        // Check no users have been enroled on the course link.
        $userenrolments = self::get_course_enrolments($course2->id, $userids);
        $this->assertEmpty($userenrolments);

        // Check the roles that each user has been given.
        $context = context_course::instance($course1->id);
        $roles1 = get_user_roles($context, $this->users[0]->id, false);
        $roles2 = get_user_roles($context, $this->users[1]->id, false);
        $roles3 = get_user_roles($context, $this->users[2]->id, false);
        $roles4 = get_user_roles($context, $this->users[3]->id, false);

        $this->assertCount(1, $roles1); // Each user should only have 1 role in the course.
        $this->assertCount(1, $roles2);
        $this->assertCount(1, $roles3);
        $this->assertCount(1, $roles4);

        $role1 = reset($roles1);
        $role2 = reset($roles2);
        $role3 = reset($roles3);
        $role4 = reset($roles4);

        $this->assertEquals('teacher', $role1->shortname); // Membership role..
        $this->assertEquals('student', $role2->shortname); // Membership role.
        $this->assertEquals('editingteacher', $role3->shortname); // Membership role.
        $this->assertEquals('student', $role4->shortname); // Default role.

        // Check no group memberships.
        $groups1 = self::get_groups($course1->id, $this->users[0]->id);
        $groups2 = self::get_groups($course1->id, $this->users[1]->id);
        $groups3 = self::get_groups($course1->id, $this->users[2]->id);
        $groups4 = self::get_groups($course1->id, $this->users[3]->id);

        $this->assertEmpty($groups1);
        $this->assertEmpty($groups2);
        $this->assertEmpty($groups3);
        $this->assertEmpty($groups4);
    }

    public function test_create_members_separategroups() {
        global $DB;

        // Course create request from participant 1 to participant 2
        $courseresourceid = -10;
        $memberresourceid = -20;
        $course = json_decode($this->coursedata);
        $course->groupScenario = campusconnect_parallelgroups::PGROUP_SEPARATE_GROUPS;
        campusconnect_course::create($courseresourceid, $this->settings[2], $course, $this->transferdetails);

        // Get the details of the two courses created.
        $courses = $DB->get_records_select('course', 'id > 1', array(), 'id', 'id, fullname, shortname, category, summary');
        $this->assertCount(2, $courses);
        $course1 = array_shift($courses);
        $course2 = array_shift($courses);

        // Create the course members.
        $members = json_decode($this->coursemembers);
        campusconnect_membership::create($memberresourceid, $this->settings[2], $members, $this->transferdetails);
        campusconnect_membership::assign_all_roles($this->settings[2]);

        // Check the users are enroled on the 'real' course.
        $userids = array();
        foreach ($this->users as $user) {
            $userids[] = $user->id;
        }

        $userenrolments = self::get_course_enrolments($course1->id, $userids);
        $this->assertCount(4, $userenrolments);
        foreach ($userenrolments as $userenrolment) {
            $this->assertEquals('campusconnect', $userenrolment->enrol);
        }

        // Check no users have been enroled on the course link.
        $userenrolments = self::get_course_enrolments($course2->id, $userids);
        $this->assertEmpty($userenrolments);

        // Check the roles that each user has been given.
        $context = context_course::instance($course1->id);
        $roles1 = get_user_roles($context, $this->users[0]->id, false);
        $roles2 = get_user_roles($context, $this->users[1]->id, false);
        $roles3 = get_user_roles($context, $this->users[2]->id, false);
        $roles4 = get_user_roles($context, $this->users[3]->id, false);

        $this->assertCount(1, $roles1);
        $this->assertCount(1, $roles2);
        $this->assertCount(2, $roles3);
        $this->assertCount(1, $roles4);

        $role1 = reset($roles1);
        $role2 = reset($roles2);
        $role3 = reset($roles3);
        $role3b = next($roles3);
        $role4 = reset($roles4);

        $this->assertEquals('editingteacher', $role1->shortname); // From group 0.
        $this->assertEquals('student', $role2->shortname); // Membership role.
        $this->assertEquals('teacher', $role3->shortname); // From group 1.
        $this->assertEquals('student', $role3b->shortname); // From group 0.
        $this->assertEquals('student', $role4->shortname); // Default role.

        // Check the group memberships.
        $groups1 = self::get_groups($course1->id, $this->users[0]->id);
        $groups2 = self::get_groups($course1->id, $this->users[1]->id);
        $groups3 = self::get_groups($course1->id, $this->users[2]->id);
        $groups4 = self::get_groups($course1->id, $this->users[3]->id);

        $this->assertCount(1, $groups1);
        $this->assertCount(0, $groups2);
        $this->assertCount(2, $groups3);
        $this->assertCount(1, $groups4);

        $this->assertEmpty(array_diff(array('Test Group1'), $groups1));
        $this->assertEmpty(array_diff(array('Test Group1', 'Test Group2'), $groups3));
        $this->assertEmpty(array_diff(array('Test Group2'), $groups4));
    }

    public function test_create_members_separatecourses() {
        global $DB;

        // Course create request from participant 1 to participant 2
        $courseresourceid = -10;
        $memberresourceid = -20;
        $course = json_decode($this->coursedata);
        $course->groupScenario = campusconnect_parallelgroups::PGROUP_SEPARATE_COURSES;
        campusconnect_course::create($courseresourceid, $this->settings[2], $course, $this->transferdetails);

        // Get the details of the two courses created.
        $courses = $DB->get_records_select('course', 'id > 1', array(), 'id', 'id, fullname, shortname, category, summary');
        $this->assertCount(4, $courses);
        $course1 = array_shift($courses);
        $course2 = array_shift($courses);
        $course3 = array_shift($courses);
        $course4 = array_shift($courses);

        // Create the course members.
        $members = json_decode($this->coursemembers);
        campusconnect_membership::create($memberresourceid, $this->settings[2], $members, $this->transferdetails);
        campusconnect_membership::assign_all_roles($this->settings[2]);

        // Check the users are enroled on the 'real' courses.
        $userids = array();
        foreach ($this->users as $user) {
            $userids[] = $user->id;
        }

        $userenrolments = self::get_course_enrolments($course1->id, $userids);
        $this->assertCount(3, $userenrolments); // User 1, 2, 3.
        $enroleduserids = array();
        foreach ($userenrolments as $userenrolment) {
            $this->assertEquals('campusconnect', $userenrolment->enrol);
            $enroleduserids[] = $userenrolment->userid;
        }
        $this->assertEmpty(array_diff(array($this->users[0]->id, $this->users[1]->id, $this->users[2]->id), $enroleduserids));

        $userenrolments = self::get_course_enrolments($course3->id, $userids);
        $this->assertCount(2, $userenrolments); // User 3, 4.
        $enroleduserids = array();
        foreach ($userenrolments as $userenrolment) {
            $this->assertEquals('campusconnect', $userenrolment->enrol);
            $enroleduserids[] = $userenrolment->userid;
        }
        $this->assertEmpty(array_diff(array($this->users[2]->id, $this->users[3]->id), $enroleduserids));

        // Check no users have been enroled on the course links.
        $userenrolments = self::get_course_enrolments($course2->id, $userids);
        $this->assertEmpty($userenrolments);
        $userenrolments = self::get_course_enrolments($course4->id, $userids);
        $this->assertEmpty($userenrolments);

        // Check the roles that each user has been given in course1.
        $context = context_course::instance($course1->id);
        $roles1 = get_user_roles($context, $this->users[0]->id, false);
        $roles2 = get_user_roles($context, $this->users[1]->id, false);
        $roles3 = get_user_roles($context, $this->users[2]->id, false);
        $roles4 = get_user_roles($context, $this->users[3]->id, false);

        $this->assertCount(1, $roles1); // Each user should only have 1 role in the course.
        $this->assertCount(1, $roles2);
        $this->assertCount(1, $roles3);
        $this->assertCount(0, $roles4);

        $role1 = reset($roles1);
        $role2 = reset($roles2);
        $role3 = reset($roles3);

        $this->assertEquals('editingteacher', $role1->shortname); // From group 0.
        $this->assertEquals('student', $role2->shortname); // Membership role.
        $this->assertEquals('student', $role3->shortname); // From group 1.

        // Check the roles that each user has been given in course3.
        $context = context_course::instance($course3->id);
        $roles1 = get_user_roles($context, $this->users[0]->id, false);
        $roles2 = get_user_roles($context, $this->users[1]->id, false);
        $roles3 = get_user_roles($context, $this->users[2]->id, false);
        $roles4 = get_user_roles($context, $this->users[3]->id, false);

        $this->assertCount(0, $roles1); // Each user should only have 1 role in the course.
        $this->assertCount(0, $roles2);
        $this->assertCount(1, $roles3);
        $this->assertCount(1, $roles4);

        $role3 = reset($roles3);
        $role4 = reset($roles4);

        $this->assertEquals('teacher', $role3->shortname); // From group 1.
        $this->assertEquals('student', $role4->shortname); // Default role.

        // Check the group memberships for course 1.
        $groups1 = self::get_groups($course1->id, $this->users[0]->id);
        $groups2 = self::get_groups($course1->id, $this->users[1]->id);
        $groups3 = self::get_groups($course1->id, $this->users[2]->id);
        $groups4 = self::get_groups($course1->id, $this->users[3]->id);

        $this->assertCount(0, $groups1);
        $this->assertCount(0, $groups2);
        $this->assertCount(0, $groups3);
        $this->assertCount(0, $groups4);

        // Check the group memberships for course 3.
        $groups1 = self::get_groups($course3->id, $this->users[0]->id);
        $groups2 = self::get_groups($course3->id, $this->users[1]->id);
        $groups3 = self::get_groups($course3->id, $this->users[2]->id);
        $groups4 = self::get_groups($course3->id, $this->users[3]->id);

        $this->assertCount(0, $groups1);
        $this->assertCount(0, $groups2);
        $this->assertCount(0, $groups3);
        $this->assertCount(0, $groups4);
    }

    public function test_create_members_separatelecturers() {
        global $DB;

        // Course create request from participant 1 to participant 2
        $courseresourceid = -10;
        $memberresourceid = -20;
        $course = json_decode($this->coursedata);
        $course->groupScenario = campusconnect_parallelgroups::PGROUP_SEPARATE_LECTURERS;
        campusconnect_course::create($courseresourceid, $this->settings[2], $course, $this->transferdetails);

        // Get the details of the two courses created.
        $courses = $DB->get_records_select('course', 'id > 1', array(), 'id', 'id, fullname, shortname, category, summary');
        $this->assertCount(2, $courses);
        $course1 = array_shift($courses); // group0 + group1 (Humphrey Bogart).
        $course2 = array_shift($courses); //   course link.

        // Create the course members.
        $members = json_decode($this->coursemembers);
        campusconnect_membership::create($memberresourceid, $this->settings[2], $members, $this->transferdetails);
        campusconnect_membership::assign_all_roles($this->settings[2]);

        // Check the users are enroled on the 'real' course.
        $userids = array();
        foreach ($this->users as $user) {
            $userids[] = $user->id;
        }

        $userenrolments = self::get_course_enrolments($course1->id, $userids);
        $this->assertCount(4, $userenrolments); // User 1, 2, 3, 4 - group0 + group1.
        $enroleduserids = array();
        foreach ($userenrolments as $userenrolment) {
            $this->assertEquals('campusconnect', $userenrolment->enrol);
            $enroleduserids[] = $userenrolment->userid;
        }
        $this->assertEmpty(array_diff(array($this->users[0]->id, $this->users[1]->id, $this->users[2]->id, $this->users[3]->id),
                                      $enroleduserids));

        // Check no users have been enroled on the course links.
        $userenrolments = self::get_course_enrolments($course2->id, $userids);
        $this->assertEmpty($userenrolments);

        // Check the roles that each user has been given in the course.
        $context = context_course::instance($course1->id);
        $roles1 = get_user_roles($context, $this->users[0]->id, false);
        $roles2 = get_user_roles($context, $this->users[1]->id, false);
        $roles3 = get_user_roles($context, $this->users[2]->id, false);
        $roles4 = get_user_roles($context, $this->users[3]->id, false);

        $this->assertCount(1, $roles1);
        $this->assertCount(1, $roles2);
        $this->assertCount(2, $roles3);
        $this->assertCount(1, $roles4);

        $role1 = reset($roles1);
        $role2 = reset($roles2);
        $role3 = reset($roles3);
        $role3b = next($roles3);
        $role4 = reset($roles4);

        $this->assertEquals('editingteacher', $role1->shortname); // From group 0.
        $this->assertEquals('student', $role2->shortname); // Membership role.
        $this->assertEquals('teacher', $role3->shortname); // From group 1.
        $this->assertEquals('student', $role3b->shortname); // From group 0.
        $this->assertEquals('student', $role4->shortname); // Default role.

        // Check the group memberships for course 1.
        $groups1 = self::get_groups($course1->id, $this->users[0]->id);
        $groups2 = self::get_groups($course1->id, $this->users[1]->id);
        $groups3 = self::get_groups($course1->id, $this->users[2]->id);
        $groups4 = self::get_groups($course1->id, $this->users[3]->id);

        $this->assertCount(1, $groups1);
        $this->assertCount(0, $groups2);
        $this->assertCount(2, $groups3);
        $this->assertCount(1, $groups4);

        $this->assertEmpty(array_diff(array('Test Group1'), $groups1));
        $this->assertEmpty(array_diff(array('Test Group1', 'Test Group2'), $groups3));
        $this->assertEmpty(array_diff(array('Test Group2'), $groups4));
    }
}