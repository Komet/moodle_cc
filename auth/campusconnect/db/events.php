<?php
// This file is part of Moodle - http://moodle.org/
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
 * List of events handled by this plugin
 *
 * @package   auth_campusconnect
 * @copyright 2014 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
defined('MOODLE_INTERNAL') || die();

$handlers = array(
    'user_enrolled' => array(
        'handlerfile' => '/auth/campusconnect/lib.php',
        'handlerfunction' => 'auth_campusconnect_user_enrolled',
        'schedule' => 'instant',
        'internal' => 1
    ),

    'user_unenrolled' => array(
        'handlerfile' => '/auth/campusconnect/lib.php',
        'handlerfunction' => 'auth_campusconnect_user_unenrolled',
        'schedule' => 'instant',
        'internal' => 1
    ),

    'user_updated' => array(
        'handlerfile' => '/auth/campusconnect/lib.php',
        'handlerfunction' => 'auth_campusconnect_user_updated',
        'schedule' => 'instant',
        'internal' => 1
    ),
);
