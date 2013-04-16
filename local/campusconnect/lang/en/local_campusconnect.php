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
$string['addattributes'] = 'Add more attributes';
$string['addecs'] = 'Add ECS';
$string['allecs'] = 'All ECS';
$string['allwords'] = 'All words';
$string['allwords_help'] = 'If selected then all courses will match this attribute, otherwise only courses with the attribute value in the list below will be matched.';
$string['alreadycms'] = 'Cannot set \'{$a->newcms}\' to have import type Campus Management, as \'{$a->currcms}\' already has import type Campus Management';
$string['assignmenttocategories'] = 'Assignment To Categories';
$string['attribute'] = 'Attribute';
$string['attributename'] = 'Attribute Name';
$string['attributesonce'] = 'You can only list each attribute once - \'{$a}\' is already in use';
$string['authenticationtype'] = 'Authentication Type';
$string['cacertificate'] = 'CA Certificate';
$string['campusmanagement'] = 'Campus Management';
$string['cannotbeempty'] = 'The field {$a} cannot be empty';
$string['cannotmapsubcategory'] = 'You cannot map a directory onto a sub-category of the current category';
$string['categoryassignment'] = 'Category Assignment';
$string['categoryid'] = 'Category ID';
$string['categoryiddesc'] = 'Please enter the ID of the category where new Course Links will be created';
$string['ccrolename'] = 'CampusConnect Role Name';
$string['certificatebase'] = 'Certificate/Base';
$string['certificateexpiry'] = 'Certificate expiry date';
$string['certificatekey'] = 'Certificate Key';
$string['clientcertificate'] = 'Client Certificate';
$string['cmsdirectories'] = 'Campus Management System directories';
$string['cmsrootid'] = 'Root ID on CMS';
$string['connectionsettings'] = 'Connection Settings';
$string['contentnotificationdesc'] = 'Enter one or more usernames of users (comma seperated) that will be informed about new ECS content by e-mail.';
$string['course'] = 'Course';
$string['courseattributes'] = 'Course attributes';
$string['courseavailablefields'] = 'Available Fields for Course';
$string['courseenabled'] = 'Import courses';
$string['courseextavailablefields'] = 'Available Fields for Course links';
$string['coursefiltering'] = 'Course filtering';
$string['coursefilteringsettings'] = 'Course filtering settings';
$string['coursename'] = 'Course name';
$string['coursenotificationdesc'] = 'Enter one or more usernames of users (comma seperated) that will be informed about new approved courses by e-mail.';
$string['created'] = 'Created';
$string['createemptycategories'] = 'Create empty categories';
$string['createemptycategories_help'] = 'Setting this to \'yes\' will cause Moodle categories to be created as soon as new directories are added by the Campus Management System, even if they do not have any courses in them.';
$string['createsubdirectories'] = 'Create subdirectories';
$string['createsubdirectories_help'] = 'This setting will automatically create subdirectories based on the value of this attribute in the course data';
$string['currentassignments'] = 'Current Assignments';
$string['daterange'] = 'Date Range';
$string['defaultcategory'] = 'Default category';
$string['defaultcategoryrequired'] = 'You must specify a default category';
$string['deleted'] = 'Deleted';
$string['deleteecsareyousure'] = 'Are you sure you want to delete this ECS?';
$string['deleteecsareyousuremessage'] = 'You are about to delete all records associated with this ECS. Are you sure?';
$string['directconnection'] = 'None (dev only)';
$string['directorymapping'] = 'Directory mapping';
$string['directorytrees'] = 'Directory trees';
$string['directorytreesettings'] = 'Directory tree settings';
$string['domainname'] = 'Domain name';
$string['ecs'] = 'ECS';
$string['ecsauth'] = 'ECS auth id (dev only)';
$string['ecscourselink'] = 'Course link';
$string['ecsdatamapping'] = 'ECS data mapping';
$string['ecsenabled'] = 'ECS enabled';
$string['ecserror_body'] = 'An error occurred whilst attempting to connect to the ECS server \'{$a->ecsname}\' ({$a->ecsid}). The error was:
{$a->msg}';
$string['ecserror_subject'] = 'Error connecting to the ECS server';
$string['email'] = 'Email';
$string['enablefiltering'] = 'Override CMS directory allocations';
$string['error'] = 'Error: {$a}';
$string['errorallwordsused'] = 'All words is set for attribute \'{$a}\', you cannot use a words list here';
$string['errorallwordsusedcategory'] = 'All words is already set for attribute \'{$a->attribute}\', in category \'{$a->categoryname}\'. The attribute cannot be used again here.';
$string['errorparticipants'] = 'There was an error whilst attempting to load the list of participants from the ECS server: {$a}';
$string['errorwordsused'] = 'The word(s) \'{$a->words}\' have already been used for attribute \'{$a->attribute}\', in category \'{$a->categoryname}\'';
$string['export'] = 'Export';
$string['externalcourse'] = 'Course link';
$string['exportparticipants'] = 'Exported to';
$string['exportstatus'] = 'Status';
$string['exportuptodate'] = 'Up to date';
$string['exportcreated'] = 'Export pending';
$string['exportupdated'] = 'Update pending';
$string['exportdeleted'] = 'Removal pending';
$string['faileddownload'] = 'Failed to download resource \'{$a}\'';
$string['field_courseid'] = 'Course ID';
$string['field_coursetype'] = 'Course type';
$string['field_credits'] = 'Credits';
$string['field_language'] = 'Language';
$string['field_organisation'] = 'Organisation';
$string['field_semesterhours'] = 'Semester hours';
$string['field_status'] = 'Status';
$string['field_term'] = 'Term';
$string['filteringattribute'] = 'Attribute';
$string['filternocategories'] = 'The course import filter returned no categories - is the \'default category\' set?';
$string['filtersettings'] = 'Filter settings';
$string['filterwords'] = 'Filter words';
$string['fixedvalue'] = 'Fixed Value';
$string['furtherinformation'] = 'Further information';
$string['import'] = 'Import';
$string['importcat'] = 'Import category';
$string['importtype'] = 'Import type';
$string['importedcourses'] = 'Imported courses';
$string['importedfrom'] = 'Imported from';
$string['invaliddirectory'] = 'Attempting to map non-existent directory {$a}';
$string['keypassword'] = 'Key Password';
$string['links'] = 'Link';
$string['localcategories'] = 'Local categories';
$string['localfieldnotfound'] = 'The local field {$a} does not exist';
$string['localsettings'] = 'Local Settings';
$string['manualmappingwarning'] = 'Warning: you are about to manually map a directory within a Campus Management System directory tree onto a local category. Once you do this the mapping mode for the directory tree will be set to \'manual\' and you will be unable to change it back again.';
$string['mapdirectory'] = 'Map directory';
$string['mappingtype'] = 'Mapping Type';
$string['messageprovider:ecserror'] = 'ECS Connection problems';
$string['metadata'] = 'Meta data';
$string['minutes'] = 'Minutes';
$string['modedeleted'] = 'Deleted by CMS';
$string['modemanual'] = 'Manually mapped';
$string['modepending'] = 'Not yet mapped';
$string['modewhole'] = 'Whole tree mapped';
$string['months'] = 'months';
$string['moodlerole'] = 'Moodle Role';
$string['mustbevalidcategory'] = 'Must be a valid category id';
$string['name'] = 'Name';
$string['newassignment'] = 'New Assignment';
$string['noattributes'] = 'No attributes available for use - please select some \'Course attributes\' above';
$string['nocategoryselected'] = 'No category selected';
$string['nocourseexport'] = 'No courses are currently exported';
$string['nodirectoryselected'] = 'No directory selected';
$string['noimportedcourses'] = 'No imported courses';
$string['nomappings'] = 'No mappings available';
$string['notifcationaboutecsusers'] = 'Notifications about ECS users';
$string['notificationaboutapprovedcourses'] = 'Notifications about approved courses';
$string['notificationaboutnewecontent'] = 'Notifications about new E-Content';
$string['notifications'] = 'Notifications';

$string['notifycourse_body'] = 'The following courses have been created on {$a}:';
$string['notifycourse_subject'] = '{$a} - newly created courses';
$string['notifycourse_update_body'] = 'The following courses have been updated on {$a}:';
$string['notifycourse_update_subject'] = '{$a} - updated courses';
$string['notifycourse_delete_body'] = 'The following courses have been deleted in the Campus Management System for {$a}. They have NOT been automatically deleted from the VLE:';
$string['notifycourse_delete_subject'] = '{$a} - deleted courses';
$string['notifycourse_error_body'] = 'There was a problem creating/updating the following courses in for {$a}:';
$string['notifycourse_error_subject'] = '{$a} - error creating/updating courses';
$string['notifydirectorytree_delete_body'] = '{$a} - directory trees deleted';
$string['notifydirectorytree_delete_subject'] = 'The following directory trees have been deleted from {$a}:';
$string['notifyexport_body'] = 'The following course links have been exported from {$a}:';
$string['notifyexport_subject'] = '{$a} - newly exported course links';
$string['notifyexport_update_body'] = 'The following exported course links have been updated from {$a}:';
$string['notifyexport_update_subject'] = '{$a} - updated exported course links';
$string['notifyexport_delete_body'] = 'The following course links are no longer being exported from {$a}:';
$string['notifyexport_delete_subject'] = '{$a} - removed exported course links';
$string['notifyexport_error_body'] = 'There was an error whilst exporting course links from {$a}:';
$string['notifyexport_error_subject'] = '{$a} - error exporting course links';
$string['notifyimport_body'] = 'The following course links have been imported into {$a}:';
$string['notifyimport_subject'] = '{$a} - newly imported course links';
$string['notifyimport_update_body'] = 'The following imported course links have been updated in {$a}:';
$string['notifyimport_update_subject'] = '{$a} - updated imported course links';
$string['notifyimport_delete_body'] = 'The following course links are no longer imported into {$a}:';
$string['notifyimport_delete_subject'] = '{$a} - removed imported course links';
$string['notifyimport_error_body'] = 'There was an error creating course links for {$a} (please check the configured import category exists):';
$string['notifyimport_error_subject'] = '{$a} - error importing course links';
$string['notifynewuser_body'] = 'The following users have been created on {$a}:';
$string['notifynewuser_subject'] = '{$a} - newly created users';

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
$string['refresh_processcourse'] = 'Updating all imported courses';
$string['refresh_processcourselinks'] = 'Updating all imported course links';
$string['refresh_processcourseurl'] = 'Updating all exported course urls';
$string['refresh_processdirtree'] = 'Updating all directory trees';
$string['refresh_processexport'] = 'Updating all exported courses';
$string['refresh_processmembership'] = 'Updating all course membership';
$string['refresh_processmessages'] = 'Processing event queue';
$string['refreshdone'] = 'Refresh complete - all imported and exported content checked against ECS server \'{$a}\'';
$string['refreshecs'] = 'Refresh all content';
$string['refreshexport'] = 'Refresh exports';
$string['refreshing'] = 'Refreshing all content for ECS \'{$a}\'';
$string['releasedcourses'] = 'Released Courses';
$string['remapdirectory'] = 'Remap directory';
$string['remotefieldnotfound'] = 'The remote field {$a} does not exist';
$string['roleassignments'] = 'Role Assignments';
$string['roleassignmentsdesc'] = 'The chosen role will be assigned to newly created ECS user accounts';
$string['rolemapping'] = 'Role Mapping';
$string['savegeneral'] = 'Save general settings';
$string['seconds'] = 'seconds';
$string['serveraddress'] = 'Server address';
$string['settings'] = 'Settings';
$string['showmapping'] = 'Show mapping';
$string['singlecategory'] = 'Single category';
$string['takeoverallocation'] = 'Take over allocation';
$string['takeoverposition'] = 'Take over position';
$string['takeovertitle'] = 'Take over title';
$string['thisvle'] = 'This vle';
$string['title'] = 'Title';
$string['treename'] = 'Directory tree name';
$string['treestatus'] = 'Status';
$string['unknown'] = 'Unknown (deleted)';
$string['unmapdirectory'] = 'Detatch directory';
$string['unmapped'] = 'Not mapped';
$string['unused'] = 'None selected';
$string['updated'] = 'Updated';
$string['url'] = 'URL';
$string['urldesc'] = 'You must not include the http:// or https:// part';
$string['useattribute'] = 'Use attribute';
$string['useraccountsettings'] = 'User Account Settings';
$string['username'] = 'Username';
$string['usernamepassword'] = 'Username/Password';
$string['usernotificationdesc'] = 'Enter one or more usernames of users (comma seperated) that will be informed about new ECS users by e-mail.';
$string['usesinglecategory'] = 'Create courses in single category';
$string['usesinglecategory_help'] = 'All courses will be created in the specified category, with course links created in the categories selected by the filtering rules.';
$string['warningexports'] = 'Warning - the following course(s) are currently exported to \'{$a}\' - they will no longer be exported if you continue.';
$string['warningimports'] = 'Warning - the following course(s) are currently imported from \'{$a}\' - they will be removed if you continue.';