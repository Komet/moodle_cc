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
 * Settings page for campus connect
 *
 * @package    admin_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../config.php');

global $CFG, $PAGE, $OUTPUT;

require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/local/campusconnect/connect.php');

$PAGE->set_url(new moodle_url('/local/campusconnect/admin/importedcourses.php'));
$PAGE->set_context(context_system::instance());

admin_externalpage_setup('campusconnectimportedcourses');

require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_campusconnect'));

$ecslist = campusconnect_ecssettings::list_ecs();
print '<h4 style="text-align: center">'.get_string('importedcourses', 'local_campusconnect').'</h4>';
print '<table class="generaltable" width="100%">
            <thead>
                <tr>
                    <th class="header c0">Title</th>
                    <th class="header c1">Links</th>
                    <th class="header c2">Imported From</th>
                    <th class="header c3 lastcol">Meta Data</th>
                </tr>
            </thead>
            <tbody>';

foreach ($ecslist as $ecsid => $ecsname) {
    $ecssettings = new campusconnect_ecssettings($ecsid);
    $connect = new campusconnect_connect($ecssettings);
    try {
        $resources = $connect->get_resource_list(campusconnect_event::RES_COURSELINK);
    } catch (Exception $e) {
        continue;
    }
    $resources = $resources->get_ids();

    foreach ($resources as $id) {
        $resource = $connect->get_resource($id, campusconnect_event::RES_COURSELINK);
        print '<tr>';
        print "<td>{$resource->title}</td>";
        print "<td>{$resource->url}</td>";
        print "<td>{$ecsname}</td>";
        print '<td>';
        foreach ((array)$resource as $name => $detail) {
            print "<div><strong>$name:</strong> ";
            if (is_array($detail) || is_object($detail)) {
                $detail = (array)$detail;
                foreach ($detail as $d) {
                    if (!empty($d)) {
                        print $d.'<br />';
                    }
                }
            } else {
                print "$detail";
            }
            print '</div>';
        }
        print '</td>';
        print '</tr>';
    }

}

print '</tbody></table>';

echo $OUTPUT->footer();