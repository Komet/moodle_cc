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

$string['abbr'] = 'Abbreviation';
$string['activatenodemapping'] = 'Enable node mapping';
$string['activationperiod'] = 'Activation Period';
$string['activationperioddesc'] = 'New ECS user accounts are limited to the session duration. After assigning a user to a course the activation period will be extended by the given number of months';
$string['addecs'] = 'Add ECS';
$string['allecs'] = 'All ECS';
$string['assignmenttocategories'] = 'Assignment To Categories';
$string['attribute'] = 'Attribute';
$string['attributename'] = 'Attribute Name';
$string['authenticationtype'] = 'Authentication Type';
$string['cacertificate'] = 'CA Certificate';
$string['campusmanagement'] = 'Campus Management';
$string['cannotbeempty'] = 'The field {$a} cannot be empty';
$string['cannotmapsubcategory'] = 'You cannot map a directory onto a sub-category of the current category';
$string['categoryassignment'] = 'Category Assignment';
$string['categoryid'] = 'Category ID';
$string['categoryiddesc'] = 'Please enter the ID of the category where new Course Links will be created';
$string['certificatebase'] = 'Certificate/Base';
$string['certificatekey'] = 'Certificate Key';
$string['clientcertificate'] = 'Client Certificate';
$string['cmsdirectories'] = 'Campus Management System directories';
$string['cmsrootid'] = 'Root ID on CMS';
$string['connectionsettings'] = 'Connection Settings';
$string['contentnotificationdesc'] = 'Enter one or more usernames of users (comma seperated) that will be informed about new ECS content by e-mail.';
$string['course'] = 'Course';
$string['coursename'] = 'Course name';
$string['coursenotificationdesc'] = 'Enter one or more usernames of users (comma seperated) that will be informed about new approved courses by e-mail.';
$string['createemptycategories'] = 'Create empty categories';
$string['createemptycategories_help'] = 'Setting this to \'yes\' will cause Moodle categories to be created as soon as new directories are added by the Campus Management System, even if they do not have any courses in them.';
$string['currentassignments'] = 'Current Assignments';
$string['daterange'] = 'Date Range';
$string['deleteecsareyousure'] = 'Are you sure you want to delete this ECS?';
$string['deleteecsareyousuremessage'] = 'You are about to delete all records associated with this ECS. Are you sure?';
$string['directorymapping'] = 'Directory mapping';
$string['directorytrees'] = 'Directory trees';
$string['directorytreesettings'] = 'Directory tree settings';
$string['domainname'] = 'Domain name';
$string['ecs'] = 'ECS';
$string['ecscourselink'] = 'Course Link';
$string['ecsdatamapping'] = 'ECS Data Mapping';
$string['ecsenabled'] = 'ECS Enabled';
$string['ecserror_body'] = 'An error occurred whilst attempting to connect to the ECS server \'{$a->ecsname}\' ({$a->ecsid}). The error was:
{$a->msg}';
$string['ecserror_subject'] = 'Error connecting to the ECS server';
$string['email'] = 'Email';
$string['error'] = 'Error: {$a}';
$string['errorparticipants'] = 'There was an error whilst attempting to load the list of participants from the ECS server: {$a}';
$string['export'] = 'Export';
$string['externalcourse'] = 'External Course';
$string['exportparticipants'] = 'Exported to';
$string['exportstatus'] = 'Status';
$string['exportuptodate'] = 'Up to date';
$string['exportcreated'] = 'Export pending';
$string['exportupdated'] = 'Update pending';
$string['exportdeleted'] = 'Removal pending';
$string['field_courseid'] = 'Course ID';
$string['field_coursetype'] = 'Course type';
$string['field_credits'] = 'Credits';
$string['field_language'] = 'Language';
$string['field_organisation'] = 'Organisation';
$string['field_semesterhours'] = 'Semester hours';
$string['field_status'] = 'Status';
$string['field_term'] = 'Term';
$string['fixedvalue'] = 'Fixed Value';
$string['furtherinformation'] = 'Further information';
$string['import'] = 'Import';
$string['importcat'] = 'Import Category';
$string['importtype'] = 'Import type';
$string['importedcourses'] = 'Imported Courses';
$string['invaliddirectory'] = 'Attempting to map non-existent directory {$a}';
$string['keypassword'] = 'Key Password';
$string['localcategories'] = 'Local categories';
$string['localfieldnotfound'] = 'The local field {$a} does not exist';
$string['localsettings'] = 'Local Settings';
$string['manualmappingwarning'] = 'Warning: you are about to manually map a directory within a Campus Management System directory tree onto a local category. Once you do this the mapping mode for the directory tree will be set to \'manual\' and you will be unable to change it back again.';
$string['mapdirectory'] = 'Map directory';
$string['mappingtype'] = 'Mapping Type';
$string['messageprovider:ecserror'] = 'ECS Connection problems';
$string['minutes'] = 'Minutes';
$string['modedeleted'] = 'Deleted by CMS';
$string['modemanual'] = 'Manually mapped';
$string['modepending'] = 'Not yet mapped';
$string['modewhole'] = 'Whole tree mapped';
$string['months'] = 'months';
$string['mustbevalidcategory'] = 'Must be a valid category id';
$string['name'] = 'Name';
$string['newassignment'] = 'New Assignment';
$string['nocategoryselected'] = 'No category selected';
$string['nocourseexport'] = 'No courses are currently exported';
$string['nodirectoryselected'] = 'No directory selected';
$string['nomappings'] = 'No mappings available';
$string['notifcationaboutecsusers'] = 'Notifications about ECS users';
$string['notificationaboutapprovedcourses'] = 'Notifications about approved courses';
$string['notificationaboutnewecontent'] = 'Notifications about new E-Content';
$string['notifications'] = 'Notifications';
$string['nodirectories'] = 'No directores have been created by the Campus Management System';
$string['noparticipants'] = 'There are no participants in this community';
$string['notrees'] = 'No directory trees have been created by the Campus Management System';
$string['offline'] = 'Offline';
$string['participants'] = 'Participants';
$string['partid'] = 'Participant ID';
$string['password'] = 'Password';
$string['pleasefilloutallrequiredfields'] = 'Please fill out all required fields';
$string['pluginname'] = 'CampusConnect';
$string['pollingtime'] = 'Polling Time';
$string['pollingtimedesc'] = 'Please define the polling time for creation and update of Course Links';
$string['port'] = 'Port';
$string['protocol'] = 'Protocol';
$string['provider'] = 'Provider';
$string['refreshdone'] = 'Refresh complete - all imported and exported content checked against ECS server \'{$a}\'';
$string['refreshecs'] = 'Refresh all content';
$string['refreshexport'] = 'Refresh exports';
$string['releasedcourses'] = 'Released Courses';
$string['remapdirectory'] = 'Remap directory';
$string['remotefieldnotfound'] = 'The remote field {$a} does not exist';
$string['roleassignments'] = 'Role Assignments';
$string['roleassignmentsdesc'] = 'The chosen role will be assigned to newly created ECS user accounts';
$string['seconds'] = 'seconds';
$string['settings'] = 'Settings';
$string['showmapping'] = 'Show mapping';
$string['takeoverallocation'] = 'Take over allocation';
$string['takeoverposition'] = 'Take over position';
$string['takeovertitle'] = 'Take over title';
$string['thisvle'] = 'This vle';
$string['treename'] = 'Directory tree name';
$string['treestatus'] = 'Status';
$string['unmapdirectory'] = 'Detatch directory';
$string['unmapped'] = 'Not mapped';
$string['url'] = 'URL';
$string['urldesc'] = 'You must not include the http:// or https:// part';
$string['useraccountsettings'] = 'User Account Settings';
$string['username'] = 'Username';
$string['usernamepassword'] = 'Username/Password';
$string['usernotificationdesc'] = 'Enter one or more usernames of users (comma seperated) that will be informed about new ECS users by e-mail.';
