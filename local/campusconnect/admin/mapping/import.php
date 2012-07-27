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

$mform = new campusconnect_import_form();

if ($mform->is_cancelled()) {

    redirect(new moodle_url('/local/campusconnect/admin/datamapping.php', array('type'=>'import')));

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
        $details['summary'] = $details['summary']['text'];
        $ecssettings = new campusconnect_ecssettings($id[0]);
        $savemetadata = new campusconnect_metadata($ecssettings, (isset($id[1]) && $id[1] == 'external'));
        $savemetadata->set_import_mappings($details);
    }

    redirect(new moodle_url('/local/campusconnect/admin/datamapping.php', array('type'=>'import')));

} else {

    print '<div class="controls"><strong><a href="?type=import">Import</a></strong> |
            <a href="?type=export">Export</a></div><br /><br />';


    $mform->display();
}



class campusconnect_import_form extends moodleform {

    public function definition() {
        global $CFG;

        $ecslist = campusconnect_ecssettings::list_ecs();

        foreach ($ecslist as $ecsid => $ecsname) {

            $mform =& $this->_form;

            $mform->addElement('header');
            $mform->addElement('html', "<h2>$ecsname</h2>");

            $mform->addElement('html', "<h3>".get_string('course')."</h3>");

            $ecssettings = new campusconnect_ecssettings($ecsid);
            $metadata = new campusconnect_metadata($ecssettings, false);
            $localfields = $metadata->list_local_fields();
            $currentmappings = $metadata->get_import_mappings();

            foreach ($localfields as $localmap) {
                if ($localmap == 'summary') {
                    $mform->addElement('editor', $ecsid.'_'.$localmap, $localmap);
                    $mform->setType('fieldname', PARAM_RAW);
                    $mform->setDefault($ecsid.'_'.$localmap, array('text'=>$currentmappings[$localmap], 'format'=>FORMAT_HTML));
                } else if ($metadata->is_text_field($localmap)) {
                    $mform->addElement('text', $ecsid.'_'.$localmap, $localmap, $currentmappings[$localmap]);
                    $mform->setDefault($ecsid.'_'.$localmap, $currentmappings[$localmap]);
                } else {
                    $maparray = $metadata->list_remote_to_local_fields($localmap, false);
                    $maps = array();
                    foreach ($maparray as $i) {
                        $maps[$i] = $i;
                    }
                    $mform->addElement('select', $ecsid.'_'.$localmap, $localmap, $maps, $currentmappings[$localmap]);
                    $mform->setDefault($ecsid.'_'.$localmap, $currentmappings[$localmap]);
                }
            }

            $mform->addElement('html', "<h3>".get_string('externalcourse', 'local_campusconnect')."</h3>");

            $ecssettings = new campusconnect_ecssettings($ecsid);
            $metadata = new campusconnect_metadata($ecssettings, true);
            $localfields = $metadata->list_local_fields();
            $currentmappings = $metadata->get_import_mappings();

            foreach ($localfields as $localmap) {
                if ($localmap == 'summary') {
                    $mform->addElement('editor', $ecsid.'_'.$localmap.'_external', $localmap);
                    $mform->setType('fieldname', PARAM_RAW);
                    $mform->setDefault($ecsid.'_'.$localmap.'_external',
                        array('text'=>$currentmappings[$localmap], 'format'=>FORMAT_HTML));
                } else if ($metadata->is_text_field($localmap)) {
                    $mform->addElement('text', $ecsid.'_'.$localmap.'_external', $localmap, $currentmappings[$localmap]);
                    $mform->setDefault($ecsid.'_'.$localmap.'_external', $currentmappings[$localmap]);
                } else {
                    $maparray = $metadata->list_remote_to_local_fields($localmap, true);
                    $maps = array();
                    foreach ($maparray as $i) {
                        $maps[$i] = $i;
                    }
                    $mform->addElement('select', $ecsid.'_'.$localmap.'_external', $localmap, $maps, $currentmappings[$localmap]);
                    $mform->setDefault($ecsid.'_'.$localmap.'_external', $currentmappings[$localmap]);
                }
            }
        }

        $this->add_action_buttons();

    }
}