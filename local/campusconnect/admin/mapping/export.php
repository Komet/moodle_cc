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

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

$mform = new campusconnect_export_form("{$CFG->wwwroot}/admin/campusconnect/datamapping.php?type=export");

if ($mform->is_cancelled()) {

    redirect("{$CFG->wwwroot}/admin/campusconnect/datamapping.php?type=export", '', 0);

} else if ($post=$mform->get_data()) {

    foreach ($post as $key => $value) {
        $bits = explode('_', $key, 3);
        if ($bits[0] > 0) {
            if ($bits[2] == 'external') {
                $newarray[$bits[0].'_external'][$bits[1]] = $value;
            } else {
                $newarray[$bits[0]][$bits[1]] = $value;
            }
        }
    }

    foreach ($newarray as $id => $details) {
        $id = explode('_', $id, 3);
        $ecssettings = new campusconnect_ecssettings($id[0]);
        $savemetadata = new campusconnect_metadata($ecssettings, (isset($id[1]) && $id[1] == 'external'));
        $savemetadata->set_export_mappings($details);
    }

    redirect("{$CFG->wwwroot}/admin/campusconnect/datamapping.php?type=export", '', 0);

} else {

    print '<div class="controls"><a href="?type=import">Import</a> |
            <strong><a href="?type=export">Export</a></strong></div><br /><br />';

    $mform->display();
}



class campusconnect_export_form extends moodleform {

    public function definition() {
        global $CFG;

        $ecslist = campusconnect_ecssettings::list_ecs();

        foreach ($ecslist as $ecsid => $ecsname) {

            $mform =& $this->_form;

            $mform->addElement('header');
            $mform->addElement('html', "<h2>$ecsname</h2>");
            $mform->addElement('html', "<h3>".get_string('course', 'local_campusconnect')."</h3>");

            $ecssettings = new campusconnect_ecssettings($ecsid);
            $metadata = new campusconnect_metadata($ecssettings, false);
            $remotefields = $metadata->list_remote_fields(false);
            $currentmappings = $metadata->get_export_mappings();

            foreach ($remotefields as $remotemap) {
                if ($remotemap == 'summary') {
                    $mform->addElement('editor', $ecsid.'_'.$remotemap, $remotemap);
                    $mform->setType('fieldname', PARAM_RAW);
                    $mform->setDefault($ecsid.'_'.$remotemap, array('text'=>$currentmappings[$remotemap], 'format'=>FORMAT_HTML));
                } else if ($metadata->is_remote_text_field($remotemap, false)) {
                    $mform->addElement('text', $ecsid.'_'.$remotemap, $remotemap, $currentmappings[$remotemap]);
                    $mform->setDefault($ecsid.'_'.$remotemap, $currentmappings[$remotemap]);
                } else {
                    $maparray = $metadata->list_local_to_remote_fields($remotemap, false);
                    $maps = array();
                    foreach ($maparray as $i) {
                        $maps[$i] = $i;
                    }
                    $mform->addElement('select', $ecsid.'_'.$remotemap, $remotemap, $maps, $currentmappings[$remotemap]);
                    $mform->setDefault($ecsid.'_'.$remotemap, $currentmappings[$remotemap]);
                }
            }

            $mform->addElement('html', "<h3>".get_string('externalcourse', 'local_campusconnect')."</h3>");

            $ecssettings = new campusconnect_ecssettings($ecsid);
            $metadata = new campusconnect_metadata($ecssettings, true);
            $remotefields = $metadata->list_remote_fields(true);
            $currentmappings = $metadata->get_export_mappings();

            foreach ($remotefields as $remotemap) {
                if ($remotemap == 'summary') {
                    $mform->addElement('editor', $ecsid.'_'.$remotemap.'_external', $remotemap);
                    $mform->setType('fieldname', PARAM_RAW);
                    $mform->setDefault($ecsid.'_'.$remotemap.'_external',
                        array('text'=>$currentmappings[$remotemap], 'format'=>FORMAT_HTML));
                } else if ($metadata->is_remote_text_field($remotemap, true)) {
                    $mform->addElement('text', $ecsid.'_'.$remotemap.'_external',
                        $remotemap, $currentmappings[$remotemap]);
                    $mform->setDefault($ecsid.'_'.$remotemap.'_external', $currentmappings[$remotemap]);
                } else {
                    $maparray = $metadata->list_local_to_remote_fields($remotemap, true);
                    $maps = array();
                    foreach ($maparray as $i) {
                        $maps[$i] = $i;
                    }
                    $mform->addElement('select', $ecsid.'_'.$remotemap.'_external',
                        $remotemap, $maps, $currentmappings[$remotemap]);
                    $mform->setDefault($ecsid.'_'.$remotemap.'_external', $currentmappings[$remotemap]);
                }
            }
        }

        $this->add_action_buttons();

    }
}