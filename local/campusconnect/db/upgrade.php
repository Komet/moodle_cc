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

    // Add more fields to ECS settings
    if ($oldversion < 2012061800) {
        $table = new xmldb_table('local_campusconnect_ecs');

        $field = new xmldb_field('crontime', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'keypass');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('lastcron', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'crontime');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('importcategory', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'lastcron');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('importrole', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'importcategory');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('importperiod', XMLDB_TYPE_INTEGER, '6', null, null, null, '6', 'importrole');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('notifyusers', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, 'importperiod');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('notifycontent', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, 'notifyusers');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('notifycourses', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, 'notifycontent');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // campusconnect savepoint reached
        upgrade_plugin_savepoint(true, 2012061800, 'local', 'campusconnect');
    }

    if ($oldversion < 2012061801) {

        // Define table local_campusconnect_part to be created
        $table = new xmldb_table('local_campusconnect_part');

        // Adding fields to table local_campusconnect_part
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('ecsid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('mid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('export', XMLDB_TYPE_INTEGER, '4', null, null, null, '0');
        $table->add_field('import', XMLDB_TYPE_INTEGER, '4', null, null, null, '0');
        $table->add_field('importtype', XMLDB_TYPE_INTEGER, '4', null, null, null, '1');

        // Adding keys to table local_campusconnect_part
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('ecsid', XMLDB_KEY_FOREIGN, array('ecsid'), 'local_campusconnect_ecs', array('id'));

        // Adding indexes to table local_campusconnect_part
        $table->add_index('ecsid_mid', XMLDB_INDEX_UNIQUE, array('ecsid', 'mid'));

        // Conditionally launch create table for local_campusconnect_part
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // campusconnect savepoint reached
        upgrade_plugin_savepoint(true, 2012061801, 'local', 'campusconnect');
    }

    if ($oldversion < 2012062600) {

        // Define table local_campusconnect_mappings to be created
        $table = new xmldb_table('local_campusconnect_mappings');

        // Adding fields to table local_campusconnect_mappings
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('field', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('setto', XMLDB_TYPE_TEXT, 'small', null, null, null, null);
        $table->add_field('ecsid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('type', XMLDB_TYPE_INTEGER, '4', null, null, null, null);

        // Adding keys to table local_campusconnect_mappings
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('ecsid', XMLDB_KEY_FOREIGN, array('ecsid'), 'local_campusconnect_ecs', array('id'));

        // Conditionally launch create table for local_campusconnect_mappings
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // campusconnect savepoint reached
        upgrade_plugin_savepoint(true, 2012062600, 'local', 'campusconnect');
    }

    if ($oldversion < 2012062601) {

        // Define field displayname to be added to local_campusconnect_part
        $table = new xmldb_table('local_campusconnect_part');
        $field = new xmldb_field('displayname', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'importtype');

        // Conditionally launch add field displayname
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // campusconnect savepoint reached
        upgrade_plugin_savepoint(true, 2012062601, 'local', 'campusconnect');
    }

    if ($oldversion < 2012062700) {

        // Define table local_campusconnect_export to be created
        $table = new xmldb_table('local_campusconnect_export');

        // Adding fields to table local_campusconnect_export
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('ecsid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('mids', XMLDB_TYPE_TEXT, 'small', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '4', null, null, null, null);
        $table->add_field('resourceid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table local_campusconnect_export
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));
        $table->add_key('ecsid', XMLDB_KEY_FOREIGN, array('ecsid'), 'local_campusconnect_ecs', array('id'));

        // Conditionally launch create table for local_campusconnect_export
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // campusconnect savepoint reached
        upgrade_plugin_savepoint(true, 2012062700, 'local', 'campusconnect');
    }

    if ($oldversion < 2012071800) {

        // Define table local_campusconnect_dirroot to be created
        $table = new xmldb_table('local_campusconnect_dirroot');

        // Adding fields to table local_campusconnect_dirroot
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('resourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('rootid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('title', XMLDB_TYPE_TEXT, 'small', null, XMLDB_NOTNULL, null, null);
        $table->add_field('ecsid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('mid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('mappingmode', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('takeovertitle', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('takeoverposition', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('takeoverallocation', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1');

        // Adding keys to table local_campusconnect_dirroot
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('ecsid', XMLDB_KEY_FOREIGN, array('ecsid'), 'local_campusconnect_ecs', array('id'));

        // Conditionally launch create table for local_campusconnect_dirroot
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // campusconnect savepoint reached
        upgrade_plugin_savepoint(true, 2012071800, 'local', 'campusconnect');
    }

    if ($oldversion < 2012071801) {

        // Define table local_campusconnect_dir to be created
        $table = new xmldb_table('local_campusconnect_dir');

        // Adding fields to table local_campusconnect_dir
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('resourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('rootid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('directoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('title', XMLDB_TYPE_TEXT, 'small', null, XMLDB_NOTNULL, null, null);
        $table->add_field('parentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('mapping', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table local_campusconnect_dir
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for local_campusconnect_dir
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // campusconnect savepoint reached
        upgrade_plugin_savepoint(true, 2012071801, 'local', 'campusconnect');
    }

    if ($oldversion < 2012071900) {

        // Changing precision of field type on table local_campusconnect_eventin to (50)
        $table = new xmldb_table('local_campusconnect_eventin');
        $field = new xmldb_field('type', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'id');

        // Launch change of precision for field type
        $dbman->change_field_precision($table, $field);

        // campusconnect savepoint reached
        upgrade_plugin_savepoint(true, 2012071900, 'local', 'campusconnect');
    }

    if ($oldversion < 2012072000) {
        // Define index rootid (unique) to be added to local_campusconnect_dirroot
        $table = new xmldb_table('local_campusconnect_dirroot');
        $index = new xmldb_index('rootid', XMLDB_INDEX_UNIQUE, array('rootid'));

        // Conditionally launch add index rootid
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index rootid (not unique) to be added to local_campusconnect_dir
        $table = new xmldb_table('local_campusconnect_dir');
        $index = new xmldb_index('rootid', XMLDB_INDEX_NOTUNIQUE, array('rootid'));

        // Conditionally launch add index rootid
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // campusconnect savepoint reached
        upgrade_plugin_savepoint(true, 2012072000, 'local', 'campusconnect');
    }

    if ($oldversion < 2012072600) {

        // Define field enabled to be added to local_campusconnect_ecs
        $table = new xmldb_table('local_campusconnect_ecs');
        $field = new xmldb_field('enabled', XMLDB_TYPE_INTEGER, '4', null, null, null, '1', 'id');

        // Conditionally launch add field enabled
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // campusconnect savepoint reached
        upgrade_plugin_savepoint(true, 2012072600, 'local', 'campusconnect');
    }

    if ($oldversion < 2012100201) {

        // Define table local_campusconnect_crs to be created
        $table = new xmldb_table('local_campusconnect_crs');

        // Adding fields to table local_campusconnect_crs
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('resourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('ecsid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('mid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table local_campusconnect_crs
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('courseid', XMLDB_KEY_FOREIGN_UNIQUE, array('courseid'), 'course', array('id'));

        // Conditionally launch create table for local_campusconnect_crs
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // campusconnect savepoint reached
        upgrade_plugin_savepoint(true, 2012100201, 'local', 'campusconnect');
    }

    if ($oldversion < 2012100800) {

        // Define field internallink to be added to local_campusconnect_crs
        $table = new xmldb_table('local_campusconnect_crs');
        $field = new xmldb_field('internallink', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'mid');

        // Conditionally launch add field internallink
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // campusconnect savepoint reached
        upgrade_plugin_savepoint(true, 2012100800, 'local', 'campusconnect');
    }

    if ($oldversion < 2012101000) {

        // Define field urlresourceid to be added to local_campusconnect_crs
        $table = new xmldb_table('local_campusconnect_crs');

        $field = new xmldb_field('urlresourceid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'internallink');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field urlstatus to be added to local_campusconnect_crs
        $field = new xmldb_field('urlstatus', XMLDB_TYPE_INTEGER, '4', null, null, null, '0', 'urlresourceid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // campusconnect savepoint reached
        upgrade_plugin_savepoint(true, 2012101000, 'local', 'campusconnect');
    }

    if ($oldversion < 2012101001) {

        // Define field cmsid to be added to local_campusconnect_crs
        $table = new xmldb_table('local_campusconnect_crs');
        $field = new xmldb_field('cmsid', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, 'resourceid');

        // Conditionally launch add field cmsid
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // campusconnect savepoint reached
        upgrade_plugin_savepoint(true, 2012101001, 'local', 'campusconnect');
    }

    if ($oldversion < 2012101500) {

        // Changing type of field cmsid on table local_campusconnect_crs to char
        $table = new xmldb_table('local_campusconnect_crs');
        $field = new xmldb_field('cmsid', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'resourceid');
        $dbman->change_field_type($table, $field);

        // Add an index to the CMSid field
        $index = new xmldb_index('cmsid', XMLDB_INDEX_NOTUNIQUE, array('cmsid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // campusconnect savepoint reached
        upgrade_plugin_savepoint(true, 2012101500, 'local', 'campusconnect');
    }

    if ($oldversion < 2012101501) {
        // Define table local_campusconnect_mbr to be created
        $table = new xmldb_table('local_campusconnect_mbr');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('resourceid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('cmscourseid', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('personid', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('role', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '4', null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('personid', XMLDB_INDEX_NOTUNIQUE, array('personid'));

        // Conditionally launch create table for local_campusconnect_mbr
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // campusconnect savepoint reached
        upgrade_plugin_savepoint(true, 2012101501, 'local', 'campusconnect');
    }

    if ($oldversion < 2012101502) {

        // Define table local_campusconnect_rolemap to be created
        $table = new xmldb_table('local_campusconnect_rolemap');

        // Adding fields to table local_campusconnect_rolemap
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('ccrolename', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('moodleroleid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);

        // Adding keys to table local_campusconnect_rolemap
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for local_campusconnect_rolemap
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // campusconnect savepoint reached
        upgrade_plugin_savepoint(true, 2012101502, 'local', 'campusconnect');
    }

    if ($oldversion < 2012111400) {
        // Define table local_campusconnect_notify to be created
        $table = new xmldb_table('local_campusconnect_notify');

        // Adding fields to table local_campusconnect_notify
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('ecsid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('type', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null);
        $table->add_field('data', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table local_campusconnect_notify
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table local_campusconnect_notify
        $table->add_index('ecsid_type', XMLDB_INDEX_NOTUNIQUE, array('ecsid', 'type'));

        // Conditionally launch create table for local_campusconnect_notify
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // campusconnect savepoint reached
        upgrade_plugin_savepoint(true, 2012111400, 'local', 'campusconnect');
    }

    return true;
}