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
 * Handles the importing of membership lists from the ECS
 *
 * @package   local_campusconnect
 * @copyright 2012 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Exception thrown by the campusconnect_courselink object
 */
class campusconnect_membership_exception extends moodle_exception {
    /**
     * Throw a new exception
     * @param string $msg
     */
    function __construct($msg) {
        parent::__construct('error', 'local_campusconnect', '', $msg);
    }
}

/**
 * Looks after membership update requests.
 */
class campusconnect_membership {

    const STATUS_ASSIGNED = 0;
    const STATUS_CREATED = 1;
    const STATUS_UPDATED = 2;
    const STATUS_DELETED = 3;

    /**
     * Functions to create & update membership lists based on events from the ECS
     */

    /**
     * Create a new membership list
     * @param int $resourceid the resourceid on the ECS server
     * @param campusconnect_ecssettings $ecssettings
     * @param object $membership the details of the membership list
     * @param campusconnect_details $transferdetails
     * @return bool true if successful
     */
    public static function create($resourceid, campusconnect_ecssettings $ecssettings, $membership, campusconnect_details $transferdetails) {
        global $DB;

        $cms = campusconnect_participantsettings::get_cms_participant();
        $mid = $transferdetails->get_sender_mid();
        $ecsid = $ecssettings->get_id();
        if (!$cms || $cms->get_mid() != $mid || $cms->get_ecs_id() != $ecsid) {
            throw new campusconnect_course_exception("Received create membership event from non-CMS participant");
        }

        if (self::get_by_resourceid($resourceid)) {
            throw new campusconnect_course_exception("Cannot create a membership list from resource $resourceid - it already exists.");
        }

        $ins = new stdClass();
        $ins->resourceid = $resourceid;
        $ins->cmscourseid = $membership->courseID;
        $ins->status = self::STATUS_CREATED;

        foreach ($membership->members as $member) {
            // TODO - process the $member->parallelGroup values
            $ins->personid = $member->personID;
            $ins->role = $member->courseRole;

            $DB->insert_record('local_campusconnect_mbr', $ins);
        }

        return true;
    }

    /**
     * Update an existing membership list
     * @param int $resourceid the resourceid on the ECS server
     * @param campusconnect_ecssettings $ecssettings
     * @param object $membership the details of the membership list
     * @param campusconnect_details $transferdetails
     * @return bool true if successful
     */
    public static function update($resourceid, campusconnect_ecssettings $ecssettings, $membership, campusconnect_details $transferdetails) {
        global $DB;

        $cms = campusconnect_participantsettings::get_cms_participant();
        $mid = $transferdetails->get_sender_mid();
        $ecsid = $ecssettings->get_id();
        if (!$cms || $cms->get_mid() != $mid || $cms->get_ecs_id() != $ecsid) {
            throw new campusconnect_course_exception("Received create membership event from non-CMS participant");
        }

        $currmembers = self::get_by_resourceid($resourceid);
        if (!$currmembers) {
            return self::create($resourceid, $ecssettings, $membership, $transferdetails);
        }

        // Check the courseID first
        $sortedcurrmembers = array();
        foreach ($currmembers as $currmember) {
            if ($currmember->cmscourseid != $membership->courseID) {
                // The courseid has changed (not sure if this is allowed, but deal with it anyway)
                // => mark the enrolment to be deleted (a new enrolment will be created below)
                if ($currmember->status != self::STATUS_DELETED) {
                    if ($currmember->status == self::STATUS_CREATED) {
                        // Record created, but never used => just delete the record
                        $DB->delete_records('local_campusconnect_mbr', array('id' => $currmember->id));
                    } else {
                        // Record created & used => mark for deletion
                        $upd = new stdClass();
                        $upd->id = $currmember->id;
                        $upd->status = self::STATUS_DELETED;
                        $DB->update_record('local_campusconnect_mbr', $upd);
                    }
                }
            } else {
                $sortedcurrmembers[$currmember->personid] = $currmember;
            }
        }

        // Now compare the membership lists - add new members, update roles for existing members, remove expired members
        foreach ($membership->members as $member) {
            if (array_key_exists($member->personID, $sortedcurrmembers)) {
                // Existing member - check if the role has changed.
                $curr = $sortedcurrmembers[$member->personID];
                if ($curr->role == $member->courseRole && $curr->status != self::STATUS_DELETED) {
                    // Unchanged role assignment - nothing to update.
                } else {
                    $upd = new stdClass();
                    $upd->id = $curr->id;
                    $upd->role = $member->courseRole;
                    if ($curr->status != self::STATUS_CREATED) {
                        $upd->status = self::STATUS_UPDATED;
                    }
                    $DB->update_record('local_campusconnect_mbr', $upd);
                }
                unset($sortedcurrmembers[$member->personID]); // Remove from list, so not deleted at the end.
            } else {
                // New member
                $ins = new stdClass();
                $ins->resourceid = $resourceid;
                $ins->cmscourseid = $membership->courseID;
                $ins->status = self::STATUS_CREATED;
                $ins->personid = $member->personID;
                $ins->role = $member->courseRole;

                $DB->insert_record('local_campusconnect_mbr', $ins);
            }
        }

        // Remove any members who are no longer in the list.
        foreach ($sortedcurrmembers as $removedmember) {
            if ($removedmember->status == self::STATUS_CREATED) {
                // Record was created but never processed - just delete it.
                $DB->delete_records('local_campusconnect_mbr', array('id' => $removedmember->id));
            } else if ($removedmember->status != self::STATUS_DELETED) {
                // Mark record as ready for deletion.
                $upd = new stdClass();
                $upd->id = $removedmember->id;
                $upd->status = self::STATUS_DELETED;
                $DB->update_record('local_campusconnect_mbr', $upd);
            }
        }

        return true;
    }

    /**
     * Mark a membership list entry as deleted (the record will be deleted once the enrolment changes have
     * been processed)
     * @param int $resourceid - the ID on the ECS server
     * @param campusconnect_ecssettings $ecssettings - the ECS being connected to
     * @return bool true if successful
     */
    public static function delete($resourceid, campusconnect_ecssettings $ecssettings) {
        global $DB;

        $currmembers = self::get_by_resourceid($resourceid);
        foreach ($currmembers as $currmember) {
            if ($currmember->status == self::STATUS_CREATED) {
                $DB->delete_records('local_campusconnect_mbr', array('id' => $currmember->id));
            } else {
                if ($currmember->status != self::STATUS_DELETED) {
                    $upd = new stdClass();
                    $upd->id = $currmember->id;
                    $upd->status = self::STATUS_DELETED;
                    $DB->update_record('local_campusconnect_mbr', $upd);
                }
            }
        }

        return true;
    }

    /**
     * Update all courses from the ECS
     * @param campusconnect_ecssettings $ecssettings
     * @return object containing: ->created - array of created resource ids
     *                            ->updated - array of updated resource ids
     *                            ->deleted - array of deleted resource ids
     */
    public static function refresh_from_ecs(campusconnect_ecssettings $ecssettings) {
        global $DB;

        $ret = (object)array('created' => array(), 'updated' => array(), 'deleted' => array());

        // Get the CMS participant.
        /** @var $cms campusconnect_participantsettings */
        if (!$cms = campusconnect_participantsettings::get_cms_participant()) {
            return $ret;
        }
        if ($cms->get_ecs_id() != $ecssettings->get_id()) {
            // Not refreshing the ECS that the CMS is attached to
            return $ret;
        }

        // Get full list of courselinks from this ECS.
        $memberships = $DB->get_records('local_campusconnect_mbr', array(), '', 'DISTINCT resourceid');

        // Get full list of courselink resources shared with us.
        $connect = new campusconnect_connect($ecssettings);
        $servermemberships = $connect->get_resource_list(campusconnect_event::RES_COURSE_MEMBERS);

        // Go through all the links from the server and compare to what we have locally.
        foreach ($servermemberships->get_ids() as $resourceid) {
            $details = $connect->get_resource($resourceid, campusconnect_event::RES_COURSE_MEMBERS, false);
            $transferdetails = $connect->get_resource($resourceid, campusconnect_event::RES_COURSE_MEMBERS, true);

            // Check if we already have this locally.
            if (isset($memberships[$resourceid])) {
                self::update($resourceid, $ecssettings, $details, $transferdetails);
                $ret->updated[] = $resourceid;
                unset($memberships[$resourceid]); // So we can delete anything left in the list at the end.
            } else {
                // We don't already have this membership list
                if (empty($details)) {
                    continue; // This probably shouldn't occur, but we're just going to ignore it.
                }

                self::create($resourceid, $ecssettings, $details, $transferdetails);
                $ret->created[] = $resourceid;
            }
        }

        // Delete any membership lists still in our local list (they have either been deleted remotely, or they are from
        // a CMS we no longer import memberships from).
        foreach ($memberships as $membership) {
            self::delete($membership->resourceid, $ecssettings);
            $ret->deleted[] = $membership->resourceid;
        }

        self::assign_all_roles($ecssettings, false);

        return $ret;
    }

    /**
     * Functions to process membership list items and assign roles to the users
     */
    public static function assign_all_roles(campusconnect_ecssettings $ecssettings, $output = false) {
        global $DB;

        // Check the enrolment plugin is enabled and we are on the correct ECS for processing course members.

        if (!enrol_is_enabled('campusconnect')) {
            if ($output) {
                mtrace("CampusConnect enrolment plugin not enabled - no enrolments will take place\n");
            }
            return;
        }
        /** @var $enrol enrol_plugin */
        if (!$enrol = enrol_get_plugin('campusconnect')) {
            if ($output) {
                mtrace("CampusConnect enrolment cannot be loaded - no enrolments will take place\n");
            }
            return;
        }

        /** @var $cms campusconnect_participantsettings */
        $cms = campusconnect_participantsettings::get_cms_participant();
        if (!$cms || $cms->get_ecs_id() != $ecssettings->get_id()) {
            return; // Not processing the CMS's ECS at the moment.
        }

        // Load membership list items from the database (which have status != ASSIGNED)
        $memberships = $DB->get_records_select('local_campusconnect_mbr', 'status != ?', array(self::STATUS_ASSIGNED));
        if (empty($memberships)) {
            return;
        }

        // Get a list of all affected users
        $personids = array();
        $cmscourseids = array();
        foreach ($memberships as $membership) {
            $personids[$membership->personid] = $membership->personid;
            $cmscourseids[$membership->cmscourseid] = $membership->cmscourseid;
        }
        $userids = self::get_userids_from_personids($personids);

        // Get a list of all the courses to enrol users into
        $courseids = campusconnect_course::get_courseids_from_cmscourseids($cmscourseids);

        if (empty($userids) || empty($courseids)) {
            return; // No existing users in the list of personids or no existing courses to enrol them onto.
        }

        // Get a list of the enrol instances for 'campusconnect' in these courses.
        list($csql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $enrolinstances = $DB->get_records_select('enrol', "enrol = 'campusconnect' AND courseid $csql",
                                                  $params, 'sortorder, id ASC');
        $courseenrol = array();
        foreach ($enrolinstances as $enrolinstance) {
            if (!isset($courseenrol[$enrolinstance->courseid])) {
                $courseenrol[$enrolinstance->courseid] = $enrolinstance;
            }
        }

        // Call 'assign_role' on each of them.
        foreach ($memberships as $membership) {
            if (!isset($userids[$membership->personid])) {
                if ($output) {
                    mtrace("User '{$membership->personid}' not found - skipping");
                }
                continue; // User doesn't (yet) exist - skip them.
            }
            $userid = $userids[$membership->personid];
            if (!isset($courseids[$membership->cmscourseid])) {
                if ($output) {
                    mtrace("Course '{$membership->cmscourseid}' not found - skipping");
                }
                continue; // Course doesn't (yet) exist - skip it.
            }
            $courseid = $courseids[$membership->cmscourseid];

            if (!isset($courseenrol[$courseid])) {
                // No CampusConnect enrolment instance - add one.
                if (!$enrolinstance = self::add_enrol_instance($courseid, $enrol)) {
                    if ($output) {
                        mtrace("Unable to add CampusConnect enrolment to course $courseid\n");
                    }
                    continue;
                }
                $courseenrol[$courseid] = $enrolinstance;
            }
            $enrolinstance = $courseenrol[$courseid];

            if ($membership->status == self::STATUS_DELETED) {
                // Deleted => unenrol user, then remove mbr record.
                if ($output) {
                    mtrace("Unenroling user '{$membership->personid}' ({$userid}) from course '{$membership->cmscourseid}' ({$courseid})");
                }
                $enrol->unenrol_user($enrolinstance, $userid);

                $DB->delete_records('local_campusconnect_mbr', array('id' => $membership->id));
            } else {
                $roleid = self::get_roleid($membership->role);
                if ($membership->status == self::STATUS_UPDATED) {
                    // Updated => change the user's role (this will remove any other 'enrol_campusconnect' roles from this course.
                    if ($output) {
                        mtrace("Changing role for user '{$membership->personid}' ({$userid}) in course '{$membership->cmscourseid}' ({$courseid}) to role '{$membership->role}' ({$roleid})");
                    }
                    $context = context_course::instance($courseid);
                    role_unassign_all(array('contextid' => $context->id, 'userid' => $userid,
                                           'component' => 'enrol_campusconnect', 'itemid' => $enrolinstance->id));
                    role_assign($roleid, $userid, $context->id, 'enrol_campusconnect', $enrolinstance->id);

                } else {
                    // Created => enrol the user with the given role.
                    if ($output) {
                        mtrace("Enroling user '{$membership->personid}' ({$userid}) in course '{$membership->cmscourseid}' ({$courseid}) with role '{$membership->role}' ({$roleid})");
                    }
                    $enrol->enrol_user($enrolinstance, $userid, $roleid);
                }

                $upd = new stdClass();
                $upd->id = $membership->id;
                $upd->status = self::STATUS_ASSIGNED;
                $DB->update_record('local_campusconnect_mbr', $upd);
            }
        }
    }

    /**
     * Process the 'create course' event and see if any user memberships have already been sent for this course
     * @param object $course
     * @param $cmscourseid
     * @return bool true if successful
     */
    public static function assign_course_users($course, $cmscourseid) {
        global $DB;

        $memberships = self::get_by_cmscourseids(array($cmscourseid));
        if (empty($memberships)) {
            return true;
        }

        if (!enrol_is_enabled('campusconnect')) {
            return true;
        }
        /** @var $enrol enrol_plugin */
        if (!$enrol = enrol_get_plugin('campusconnect')) {
            return true;
        }

        // Get a list of all affected users
        $personids = array();
        foreach ($memberships as $membership) {
            $personids[$membership->personid] = $membership->personid;
        }
        $userids = self::get_userids_from_personids($personids);

        // Get a list of the enrol instances for 'campusconnect' in these courses.
        $enrolinstance = $DB->get_records('enrol', array('enrol' => 'campusconnect', 'courseid' => $course->id),
                                          'sortorder, id ASC', '*', 0, 1);
        if (empty($enrolinstance)) {
            $enrolinstance = self::add_enrol_instance($course->id, $enrol);
        } else {
            $enrolinstance = reset($enrolinstance);
        }

        // Call 'assign_role' on each of them.
        foreach ($memberships as $membership) {
            if (!isset($userids[$membership->personid])) {
                continue; // User doesn't (yet) exist - skip them.
            }
            $userid = $userids[$membership->personid];
            $roleid = self::get_roleid($membership->role);

            $enrol->enrol_user($enrolinstance, $userid, $roleid);

            $upd = new stdClass();
            $upd->id = $membership->id;
            $upd->status = self::STATUS_ASSIGNED;
            $DB->update_record('local_campusconnect_mbr', $upd);
        }

        return true;
    }

    /**
     * Process the 'create user' event and see if the new user already has an assigned role in the membership list
     * @param object $user
     * @return bool true if successful
     */
    public static function assign_user_roles($user) {
        global $DB;

        $memberships = self::get_by_user($user);
        if (empty($memberships)) {
            return true;
        }

        if (!enrol_is_enabled('campusconnect')) {
            return true;
        }
        /** @var $enrol enrol_plugin */
        if (!$enrol = enrol_get_plugin('campusconnect')) {
            return true;
        }

        // Get a list of all the courses to enrol the user into
        $cmscourseids = array();
        foreach ($memberships as $membership) {
            $cmscourseids[$membership->cmscourseid] = $membership->cmscourseid;
        }
        $courseids = campusconnect_course::get_courseids_from_cmscourseids($cmscourseids);
        if (empty($courseids)) {
            return true; // No existing courses to enrol them onto.
        }

        // Get a list of the enrol instances for 'campusconnect' in these courses.
        list($csql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $enrolinstances = $DB->get_records_select('enrol', "enrol = 'campusconnect' AND courseid $csql",
                                                  $params, 'sortorder, id ASC');
        $courseenrol = array();
        foreach ($enrolinstances as $enrolinstance) {
            if (!isset($courseenrol[$enrolinstance->courseid])) {
                $courseenrol[$enrolinstance->courseid] = $enrolinstance;
            }
        }

        // Call 'assign_role' on each of them.
        foreach ($memberships as $membership) {
            if (!isset($courseids[$membership->cmscourseid])) {
                continue; // Course doesn't (yet) exist - skip it.
            }
            $courseid = $courseids[$membership->cmscourseid];
            if (!isset($courseenrol[$courseid])) {
                // No CampusConnect enrolment instance - add one.
                if (!$enrolinstance = self::add_enrol_instance($courseid, $enrol)) {
                    continue;
                }
                $courseenrol[$courseid] = $enrolinstance;
            }
            $enrolinstance = $courseenrol[$courseid];
            $roleid = self::get_roleid($membership->role);

            $enrol->enrol_user($enrolinstance, $user->id, $roleid);

            $upd = new stdClass();
            $upd->id = $membership->id;
            $upd->status = self::STATUS_ASSIGNED;
            $DB->update_record('local_campusconnect_mbr', $upd);
        }

        return true;
    }

    /**
     * Take a list of personids given by the ECS and return a list of Moodle userids that these relate to
     * @param string[] $personids
     * @return int[] the Moodle userids: personid => userid
     */
    protected static function get_userids_from_personids($personids) {
        // Assumes, for now, that the 'personids' are the 'usernames' of the users.
        global $DB;

        if (empty($personids)) {
            return array();
        }

        list($psql, $params) = $DB->get_in_or_equal($personids);
        return $DB->get_records_select_menu('user', "username $psql", $params, '', 'username, id');
    }

    /**
     * Returns a list of requested role assignments for a given user
     * @param $user
     * @return object[] the local_campusconnect_mbr that relate to the given user
     */
    protected static function get_by_user($user) {
        // Assumes, for now, that the 'personids' are the 'usernames' of the users.
        global $DB;
        return $DB->get_records_select('local_campusconnect_mbr', "personid = :username AND (status = :created OR status = :updated)",
                                       array('username' => $user->username, 'created' => self::STATUS_CREATED,
                                            'updated' => self::STATUS_UPDATED));
    }

    /**
     * Returns a list of role assignments for a given course
     * @param $course
     * @return object[] the local_campusconnect_mbr that relate to the given course
     */
    protected static function get_by_course($course) {
        $cmscourseids = campusconnect_course::get_cmscourseids_from_courseids(array($course->id));

        return self::get_by_cmscourseids($cmscourseids);
    }

    /**
     * Return a list of the local_campusconnect_mbr records for the given cmscourseids
     * @param int[] $cmscourseids
     * @return object[]
     */
    protected static function get_by_cmscourseids($cmscourseids) {
        global $DB;
        if (empty($cmscourseids)) {
            return array();
        }
        list($csql, $params) = $DB->get_in_or_equal($cmscourseids, SQL_PARAMS_NAMED);
        $params['created'] = self::STATUS_CREATED;
        $params['updated'] = self::STATUS_UPDATED;
        return $DB->get_records_select('local_campusconnect_mbr', "cmscourseid $csql AND
                                                                   (status = :created OR status = :updated)",
                                       $params);
    }

    /**
     * Get the membership object from the resourceid
     * @param $resourceid
     * @return array
     */
    protected static function get_by_resourceid($resourceid) {
        global $DB;
        return $DB->get_records('local_campusconnect_mbr', array('resourceid' => $resourceid));
    }

    /**
     * Add a new instance of the CampusConnect enrol plugin to the given course and return the instance data
     * @param int $courseid the course to add the plugin to
     * @param enrol_plugin $enrol optional - the enrol plugin object to use
     * @return mixed object|false
     */
    protected static function add_enrol_instance($courseid, enrol_plugin $enrol = null) {
        global $DB;

        if (is_null($enrol)) {
            if (!$enrol = enrol_get_plugin('campusconnect')) {
                return false;
            }
        }
        if (!$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST)) {
            return false;
        }
        if (!$enrolid = $enrol->add_default_instance($course)) {
            return false;
        }

        return $DB->get_record('enrol', array('id' => $enrolid));
    }

    /**
     * Map the role onto the Moodle role
     * @param string $role role taken from the course_membership message
     * @return int Moodle roleid to map this user onto
     */
    protected static function get_roleid($role) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/campusconnect/rolemap.php');

        if ($roleid = campusconnect_get_roleid($role)) {
            return $roleid;
        }

        static $defaultroleid = null;
        if (is_null($defaultroleid)) {
            /** @var $cmsparticipant campusconnect_participantsettings */
            $cmsparticipant = campusconnect_participantsettings::get_cms_participant();
            $ecsid = $cmsparticipant->get_ecs_id();
            $ecssettings = new campusconnect_ecssettings($ecsid);
            $defaultrole = $ecssettings->get_import_role();
            $defaultroleid = $DB->get_field('role', 'id', array('shortname' => $defaultrole));
        }

        return $defaultroleid;
    }
}
