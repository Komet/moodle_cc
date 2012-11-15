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
 * Database upgrade steps for auth_campusconnect
 *
 * @package   auth_campusconnect
 * @copyright 2012 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_auth_campusconnect_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2012111500) {

        // Define table auth_campusconnect to be created
        $table = new xmldb_table('auth_campusconnect');

        // Adding fields to table auth_campusconnect
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('ecsid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('ecs_uid', XMLDB_TYPE_CHAR, '60', null, null, null, null);
        $table->add_field('username', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table auth_campusconnect
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('ecsid', XMLDB_KEY_FOREIGN, array('ecsid'), 'local_campusconnect_ecs', array('id'));

        // Adding indexes to table auth_campusconnect
        $table->add_index('ecs_uid', XMLDB_INDEX_UNIQUE, array('ecs_uid'));

        // Conditionally launch create table for auth_campusconnect
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // campusconnect savepoint reached
        upgrade_plugin_savepoint(true, 2012111500, 'auth', 'campusconnect');
    }
}
