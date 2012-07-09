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
 * extra language strings needed in CampusConnect
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['cannotbeempty'] = 'The field {$a} cannot be empty';
$string['ecserror_body'] = 'An error occurred whilst attempting to connect to the ECS server \'{$a->ecsname}\' ({$a->ecsid}). The error was:
{$a->msg}';
$string['ecserror_subject'] = 'Error connecting to the ECS server';
$string['error'] = 'Error: {$a}';

$string['field_courseid'] = 'Course ID';
$string['field_coursetype'] = 'Course type';
$string['field_credits'] = 'Credits';
$string['field_language'] = 'Language';
$string['field_organisation'] = 'Organisation';
$string['field_semesterhours'] = 'Semester hours';
$string['field_status'] = 'Status';
$string['field_term'] = 'Term';

$string['localfieldnotfound'] = 'The local field {$a} does not exist';
$string['messageprovider:ecserror'] = 'ECS Connection problems';
$string['pluginname'] = 'CampusConnect';
$string['remotefieldnotfound'] = 'The remote field {$a} does not exist';



//Strings for admin settings

$string['settings'] = 'Settings';
$string['ecs'] = 'ECS';
$string['addecs'] = 'Add ECS';
$string['allecs'] = 'All ECS';
$string['participants'] = 'Participants';
$string['ecsdatamapping'] = 'ECS Data Mapping';
$string['assignmenttocategories'] = 'Assignment To Categories';
$string['importedcourses'] = 'Imported Courses';
$string['releasedcourses'] = 'Released Courses';

$string['connectionsettings'] = 'Connection Settings';
$string['name'] = 'Name';
$string['url'] = 'URL';
$string['protocol'] = 'Protocol';
$string['port'] = 'Port';
$string['authenticationtype'] = 'Authentication Type';
$string['certificatebase'] = 'Certificate/Base';
$string['clientcertificate'] = 'Client Certificate';
$string['certificatekey'] = 'Certificate Key';
$string['keypassword'] = 'Key Password';
$string['cacertificate'] = 'CA Certificate';
$string['usernamepassword'] = 'Username/Password';
$string['username'] = 'Username';
$string['password'] = 'Password';

$string['ecscourselink'] = 'ECS Course Link';
$string['course'] = 'Course';
$string['campusmanagement'] = 'Campus Management';

$string['currentassignments'] = 'Current Assignments';
$string['newassignment'] = 'New Assignment';
$string['importcat'] = 'Import Category';
$string['categoryassignment'] = 'Category Assignment';
$string['attributename'] = 'Attribute Name';
$string['mappingtype'] = 'Mapping Type';
$string['fixedvalue'] = 'Fixed Value';
$string['attribute'] = 'Attribute';
$string['daterange'] = 'Date Range';

$string['localsettings'] = 'Local Settings';
$string['pollingtime'] = 'Polling Time';
$string['minutes'] = 'Minutes';
$string['seconds'] = 'seconds';
$string['pollingtimedesc'] = 'Please define the polling time for creation and update of Course Links';
$string['categoryid'] = 'Category ID';
$string['categoryiddesc'] = 'Please enter the ID of the category where new Course Links will be created';

$string['useraccountsettings'] = 'User Account Settings';
$string['roleassignments'] = 'Role Assignments';
$string['roleassignmentsdesc'] = 'The chosen role will be assigned to newly created ECS user accounts';
$string['activationperiod'] = 'Activation Period';
$string['months'] = 'months';
$string['activationperioddesc'] = 'New ECS user accounts are limited to the session duration. After assigning a user to a course the activation period will be extended by the given number of months';

$string['notifications'] = 'Notifications';
$string['notifcationaboutecsusers'] = 'Notifications about ECS users';
$string['usernotificationdesc'] = 'Enter one or more usernames of users (comma seperated) that will be informed about new ECS users by e-mail.';
$string['notificationaboutnewecontent'] = 'Notifications about new E-Content';
$string['contentnotificationdesc'] = 'Enter one or more usernames of users (comma seperated) that will be informed about new ECS content by e-mail.';
$string['notificationaboutapprovedcourses'] = 'Notifications about approved courses';
$string['coursenotificationdesc'] = 'Enter one or more usernames of users (comma seperated) that will be informed about new approved courses by e-mail.';

$string['pleasefilloutallrequiredfields'] = 'Please fill out all required fields';