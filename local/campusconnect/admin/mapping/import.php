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

require_once("$CFG->libdir/formslib.php");

$mform = new campusconnect_import_form();

if ($mform->is_cancelled()) {

    redirect("{$CFG->wwwroot}/admin/campusconnect/datamapping.php?type=import", '', 0);

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

    redirect("{$CFG->wwwroot}/admin/campusconnect/datamapping.php?type=import", '', 0);

} else {

    print '<div class="controls"><strong><a href="?type=import">Import</a></strong> |
            <a href="?type=export">Export</a></div><br /><br />';
    print '<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>';
    print '<script type="text/javascript">

        $(document).ready(function() {

          var allPanels = $(".meta_accordion > div.meta_content").hide();

          $(".meta_accordion > .meta_title > h3 > a").click(function() {
                allPanels.slideUp();
            if ( $(this).parent().siblings() > 0 || $(this).parent().parent().next().css("display") == "none") {
                $(this).parent().parent().next().slideDown();
            }
            return false;
          });

        });

        </script>';


    $mform->display();
}



class campusconnect_import_form extends moodleform {

    public function definition() {
        global $CFG;

        $ecslist = campusconnect_ecssettings::list_ecs();

        foreach ($ecslist as $ecsid => $ecsname) {

            $mform =& $this->_form;

            $mform->addElement('html', "<h2><a href='javascript://'>$ecsname</a></h2>");
            $mform->addElement('header');

            $mform->addElement('html', '<div class="meta_accordion">');

            $mform->addElement('html', '<div class="meta_title">');
            $mform->addElement('html', "<h3><a href='javascript://'>Kurse</a></h35>");
            $mform->addElement('html', '</div>');
            $mform->addElement('html', '<div class="meta_content">');

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

            $mform->addElement('html', '</div>');

            $mform->addElement('html', '<div class="meta_title">');
            $mform->addElement('html', "<h3><a href='javascript://'>External Kurse</a></h3>");
            $mform->addElement('html', '</div>');
            $mform->addElement('html', '<div class="meta_content">');

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

            $mform->addElement('html', '</div>');

            $mform->addElement('html', '</div>');
        }

        $this->add_action_buttons();

    }
}