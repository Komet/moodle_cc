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

require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/local/campusconnect/connect.php');

require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));

$PAGE->set_url(new moodle_url('/local/campusconnect/admin/participants.php'));
$PAGE->set_context(context_system::instance());

admin_externalpage_setup('campusconnectparticipants');

$ecslist = campusconnect_ecssettings::list_ecs();

if (isset($_POST['saveparticipants'])) {

    foreach ($ecslist as $ecsid => $ecs) {

        // FIXME - this will try to process every participant against every ECS, rather
        // than just processing the participants that are related to that ECS

        foreach ($_POST['participants'] as $mid => $settings) {

            $psettings = new campusconnect_participantsettings($ecsid, $mid);
            if (isset($settings['import'])) {
                $tosave['import'] = 1;
            } else {
                $tosave['import'] = 0;
            }
            if (isset($settings['export'])) {
                $tosave['export'] = 1;
            } else {
                $tosave['export'] = 0;
            }
            $tosave['importtype'] = $settings['importtype'];
            $psettings->save_settings($tosave);
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_campusconnect'));

$importopts = array(campusconnect_participantsettings::IMPORT_LINK => get_string('ecscourselink', 'local_campusconnect'),
                    campusconnect_participantsettings::IMPORT_COURSE => get_string('course', 'local_campusconnect'),
                    campusconnect_participantsettings::IMPORT_CMS => get_string('campusmanagement', 'local_campusconnect'));

foreach ($ecslist as $ecsid => $ecs) {
    $settings = new campusconnect_ecssettings($ecsid);
    $connect = new campusconnect_connect($settings);
    $communities = $connect->get_memberships();
    print "<h3>$ecs</h3>";
    print '<hr>';
    print '<form action="" method="POST">';
    foreach ($communities as $community) {
        print "<h4>{$community->community->name}</h4>";
        print '<table class="generaltable" width="100%">
        <thead>
            <tr>
                <th class="header c0">Participants</th>
                <th class="header c1">Further Information</th>
                <th class="header c2">Export</th>
                <th class="header c3">Import</th>
                <th class="header c4 lastcol">Import Type</th>
            </tr>
        </thead>
        <tbody>';
        foreach ($community->participants as $participant) {
            $psettings = new campusconnect_participantsettings($ecsid, $participant->mid);
            $participant->import = $psettings->is_import_enabled();
            $participant->export = $psettings->is_export_enabled();
            $participant->importtype = $psettings->get_import_type();
            print '<tr><td><h4';
            if ($participant->itsyou == 1) {
                print ' style="color: blue"';
            }
            print '>';
            print $participant->name;
            print '</h4></td><td>';
                print "<strong>Provider:</strong> {$participant->org->name}<br />";
                print "<strong>Domainname:</strong> {$participant->dns}<br />";
                print "<strong>E-Mail:</strong> {$participant->email}<br />";
                print "<strong>Abbreviation:</strong> {$participant->org->abbr}<br />";
                print "<strong>Participant ID:</strong> {$ecsid}_{$participant->mid}";
            print '</td>';
            print "<td style='text-align: center'><input ";
            if ($participant->import) {
                print 'checked';
            }
            print " type='checkbox' value='1' name='participants[$participant->mid][import]' /></td>";
            print "<td style='text-align: center'><input ";
            if ($participant->export) {
                print 'checked';
            }
            print " type='checkbox' value='1' name='participants[$participant->mid][export]' /></td>";
            print "<td style='text-align: center'>";
            echo html_writer::select($importopts, "participants[$participant->mid][importtype]", $participant->importtype, '');
            print '</td>';
            print '</tr>';
        }
        print '</tbody></table>';
    }
    print '<div style="float: right;">
        <input type="submit" name="saveparticipants" value="'.get_string('savechanges').'" />
        <input onclick="window.location.reload( true );" type="button" value="'.get_string('cancel').'" />
    </div>';
    print '</form>';

}


echo $OUTPUT->footer();