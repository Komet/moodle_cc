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

$string['messageprovider:ecserror'] = 'ECS Connection problems';
$string['pluginname'] = 'CampusConnect';
