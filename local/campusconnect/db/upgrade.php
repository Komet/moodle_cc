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
 * Code for upgrading to new versions of the plugin
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_campusconnect_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2012061300) {
        // Define table local_campusconnect_eventin to be created
        $table = new xmldb_table('local_campusconnect_eventin');

        // Adding fields to table local_campusconnect_eventin
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '30', null, null, null, null);
        $table->add_field('resourceid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('serverid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, null, null, null);

        // Adding keys to table local_campusconnect_eventin
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('serverid', XMLDB_KEY_FOREIGN, array('serverid'), 'local_campusconnect_ecs', array('id'));

        // Conditionally launch create table for local_campusconnect_eventin
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2012061300, 'local', 'campusconnect');
    }

    if ($oldversion < 2012061301) {
        // Define table local_campusconnect_clink to be created
        $table = new xmldb_table('local_campusconnect_clink');

        // Adding fields to table local_campusconnect_clink
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('url', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('resourceid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('ecsid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('mid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table local_campusconnect_clink
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));
        $table->add_key('ecsid', XMLDB_KEY_FOREIGN, array('ecsid'), 'local_campusconnect_ecs', array('id'));

        // Conditionally launch create table for local_campusconnect_clink
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2012061301, 'local', 'campusconnect');
    }

    return true;
}