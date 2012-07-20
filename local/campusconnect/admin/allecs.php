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

/*
 * Settings page for campus connect
 *
 * @package    admin_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../config.php');

require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/local/campusconnect/connect.php');

global $CFG, $DB;

$PAGE->set_url(new moodle_url('/local/campusconnect/admin/allecs.php'));
$PAGE->set_context(context_system::instance());

if (isset($_GET['fn'])) {
    $function = $_GET['fn'];
}

if (isset($_POST['addnewecs'])) {
    $toadd = array();
    $toadd['name']=$_POST['name'];
    $toadd['url']=$_POST['url'];
    $toadd['auth']= 2;
    $toadd['ecsauth']= 2;
}

admin_externalpage_setup('allecs');

require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_campusconnect'));

print '<a href="ecs.php?fn=add"><h3>Add New ECS</h3></a><br />';
print '<h4>Available ECS</h4>';
$ecslist = campusconnect_ecssettings::list_ecs();
print '<table class="generaltable" width="100%">
        <thead><tr><th class="header c0">Active</th><th class="header c1">Name</th>
        <th class="header c2 lastcol">Actions</th></tr></thead><tbody>';
foreach ($ecslist as $ecsid => $ecs) {
    $ecsdetails = new campusconnect_ecssettings($ecsid);
    $url = $ecsdetails->get_url();
    print '<tr>';
    $connection = new campusconnect_connect($ecsdetails);
    try {
        $idtest = $connection->get_resource_list(campusconnect_event::RES_COURSELINK);
        print "<td style='text-align: center'>YES</td>";
    } catch (Exception $e) {
        print '<td style="text-align: center">NO</td>';
    }
    print "<td><div class='info'>
        <strong><a href='ecs.php?id=$ecsid'>$ecs</a></strong><br />
        <strong>Server Address:</strong> $url
    </div></td>";
    print "<td><a href='ecs.php?id=$ecsid'>Edit</a> | <a href='ecs.php?delete=$ecsid'>Delete</a></td>";
    print '</tr>';
}
print '</tbody></table>';

echo $OUTPUT->footer();