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

$error = array();
$ecslist = campusconnect_ecssettings::list_ecs();
$allcommunities = array();
foreach ($ecslist as $ecsid => $ecsname) {
    $settings = new campusconnect_ecssettings($ecsid);
    try {
        $allcommunities[$ecsname] = campusconnect_participantsettings::load_communities($settings);
    } catch (campusconnect_connect_exception $e) {
        $ecslist = array();
        $error[$ecsname] = $e->getMessage();
    }
}

if (optional_param('saveparticipants', false, PARAM_TEXT)) {
    // Array of participant identifiers that were included in this update.
    $updateparticipants = required_param_array('updateparticipants', PARAM_TEXT);
    // Array of participant identifiers to export to.
    $export = optional_param_array('export', array(), PARAM_TEXT);
    // Array of participant identifiers to import from.
    $import = optional_param_array('import', array(), PARAM_TEXT);
    // Array of import types (indexed by participant identifiers).
    $importtypes = required_param_array('importtype', PARAM_TEXT);

    foreach ($allcommunities as $communities) {
        foreach ($communities as $community) {
            foreach ($community->participants as $identifier => $participant) {
                if (!in_array($identifier, $updateparticipants)) {
                    continue; // This participant was not in the list being updated.
                }

                $tosave = new stdClass;
                $tosave->import = in_array($identifier, $import);
                $tosave->export = in_array($identifier, $export);
                $tosave->importtype = $importtypes[$identifier];

                $participant->save_settings($tosave);
            }
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_campusconnect'));

$importopts = array(campusconnect_participantsettings::IMPORT_LINK => get_string('ecscourselink', 'local_campusconnect'),
                    campusconnect_participantsettings::IMPORT_COURSE => get_string('course', 'local_campusconnect'),
                    campusconnect_participantsettings::IMPORT_CMS => get_string('campusmanagement', 'local_campusconnect'));

$strparticipants = get_string('participants', 'local_campusconnect');
$strfurtherinformation = get_string('furtherinformation', 'local_campusconnect');
$strexport = get_string('export', 'local_campusconnect');
$strimport = get_string('import', 'local_campusconnect');
$strimporttype = get_string('importtype', 'local_campusconnect');

$strprovider = get_string('provider', 'local_campusconnect');
$strdomain = get_string('domainname', 'local_campusconnect');
$stremail = get_string('email', 'local_campusconnect');
$strabbr = get_string('abbr', 'local_campusconnect');
$strpartid = get_string('partid', 'local_campusconnect');

$strsavechanges = get_string('savechanges');
$strcancel = get_string('cancel');

if ($error) {
    foreach ($error as $ecsname => $errormessage) {
        echo $OUTPUT->notification($ecsname.': '.get_string('errorparticipants', 'local_campusconnect', $errormessage));
    }

} else {
    foreach ($allcommunities as $ecs => $communities) {
        print "<h3>$ecs</h3>";
        print '<hr>';
        print '<form action="" method="POST">';
        foreach ($communities as $community) {
            print "<h4>{$community->name}</h4>";
            print '<table class="generaltable" width="100%">
        <thead>
            <tr>
                <th class="header c0">'.$strparticipants.'</th>
                <th class="header c1">'.$strfurtherinformation.'</th>
                <th class="header c2">'.$strexport.'</th>
                <th class="header c3">'.$strimport.'</th>
                <th class="header c4 lastcol">'.$strimporttype.'</th>
            </tr>
        </thead>
        <tbody>';
            foreach ($community->participants as $participant) {
                print '<tr><td><h4';
                if ($participant->is_me()) {
                    print ' class="itsme"';
                }
                print '>';
                print s($participant->get_name());
                print '</h4></td><td>';
                print "<strong>{$strprovider}:</strong> ".$participant->get_organisation()."<br />";
                print "<strong>{$strdomain}:</strong> ".$participant->get_domain()."<br />";
                print "<strong>{$stremail}:</strong> ".$participant->get_email()."<br />";
                print "<strong>{$strabbr}:</strong> ".$participant->get_organisation_abbr()."<br />";
                print "<strong>{$strpartid}:</strong> ".$participant->get_identifier();
                print '</td>';
                print "<td style='text-align: center'>";
                echo html_writer::checkbox('export[]', $participant->get_identifier(),
                                           $participant->is_export_enabled());
                echo '</td>';
                print "<td style='text-align: center'>";
                echo html_writer::checkbox('import[]', $participant->get_identifier(),
                                           $participant->is_import_enabled());
                echo '</td>';
                print "<td style='text-align: center'>";
                echo html_writer::select($importopts, 'importtype['.$participant->get_identifier().']',
                                         $participant->get_import_type(), '');

                echo html_writer::empty_tag('input', array('type' => 'hidden',
                                                           'name' => 'updateparticipants[]',
                                                           'value' => $participant->get_identifier()));
                print '</td>';
                print '</tr>';
            }
            print '</tbody></table>';
        }
        print '<div style="float: right;">
        <input type="submit" name="saveparticipants" value="'.$strsavechanges.'" />
        <input onclick="window.location.reload( true );" type="button" value="'.$strcancel.'" />
    </div>';
        print '</form>';
    }
}

echo $OUTPUT->footer();