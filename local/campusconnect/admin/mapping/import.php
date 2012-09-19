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

global $CFG;
require_once("$CFG->libdir/formslib.php");

$mform = new campusconnect_import_form();

$redir = new moodle_url('/local/campusconnect/admin/datamapping.php', array('type' => 'import'));

$errors = array();
$ecslist = campusconnect_ecssettings::list_ecs();
if ($mform->is_cancelled()) {

    redirect($redir);

} else if ($post = $mform->get_data()) {

    $internaldata = array();
    $externaldata = array();
    foreach ($post as $key => $value) {
        $bits = explode('_', $key, 3);
        if (count($bits) == 3 && $bits[0] > 0) {
            list($ecsid, $field, $type) = $bits;
            if ($field == 'summary') {
                $value = $value['text'];
            }
            if ($type == 'external') {
                if (!isset($externaldata[$ecsid])) {
                    $externaldata[$ecsid] = array();
                }
                $externaldata[$ecsid][$field] = $value;
            } else {
                if (!isset($internaldata[$ecsid])) {
                    $internaldata[$ecsid] = array();
                }
                $internaldata[$ecsid][$field] = $value;
            }
        }
    }

    foreach ($ecslist as $ecsid => $ecsname) {
        if (isset($internaldata[$ecsid]) || isset($externaldata[$ecsid])) {
            $ecssettings = new campusconnect_ecssettings($ecsid);
            if (isset($internaldata[$ecsid])) {
                $metadata = new campusconnect_metadata($ecssettings, false);
                if (!$metadata->set_import_mappings($internaldata[$ecsid])) {
                    list ($errmsg, $errfield) = $metadata->get_last_error();
                    $errors[$ecsid.'_'.$errfield.'_internal'] = $errmsg;
                }
            }
            if (isset($externaldata[$ecsid])) {
                $metadata = new campusconnect_metadata($ecssettings, true);
                if (!$metadata->set_import_mappings($externaldata[$ecsid])) {
                    list ($errmsg, $errfield) = $metadata->get_last_error();
                    $errors[$ecsid.'_'.$errfield.'_external'] = $errmsg;
                }
            }

        }
    }

    if (empty($errors)) {
        redirect($redir);
    }
}

print '<div class="controls"><strong><a href="?type=import">'.get_string('import', 'local_campusconnect').'</a></strong> |
            <a href="?type=export">'.get_string('export', 'local_campusconnect').'</a></div>';

$remotefields = campusconnect_metadata::list_remote_fields(false);
$helpcontent = '';
foreach ($remotefields as $remotefield) {
    $helpcontent .= '{'.$remotefield.'}<br />';
}
print "<div style='float: left; width: 45%; border: 1px solid #000; background: #ddd; margin: 10px 5px; padding: 5px;'><strong>"
.get_string('courseavailablefields', 'local_campusconnect').':</strong><br />'.$helpcontent."</div>";

$remotefields = campusconnect_metadata::list_remote_fields(true);
$helpcontent = '';
foreach ($remotefields as $remotefield) {
    $helpcontent .= '{'.$remotefield.'}<br />';
}
print "<div style='float: right; width: 45%; border: 1px solid #000; background: #ddd; margin: 10px 5px; padding: 5px'><strong>"
.get_string('courseextavailablefields', 'local_campusconnect').':</strong><br />'.$helpcontent."</div>";

if (!empty($errors)) {
    $mform->set_errors($errors);
}

$mform->display();


class campusconnect_import_form extends moodleform {

    public function definition() {
        global $CFG;

        $ecslist = campusconnect_ecssettings::list_ecs();

        foreach ($ecslist as $ecsid => $ecsname) {

            $mform = $this->_form;

            $mform->addElement('header');
            $mform->addElement('html', "<h2>$ecsname</h2>");

            $mform->addElement('html', "<h3>".get_string('course')."</h3>");

            $ecssettings = new campusconnect_ecssettings($ecsid);
            $metadata = new campusconnect_metadata($ecssettings, false);
            $localfields = $metadata->list_local_fields();
            $remotefields = $metadata->list_remote_fields(false);
            $currentmappings = $metadata->get_import_mappings();

            $strunmapped = get_string('unmapped', 'local_campusconnect');
            $strnomappings = get_string('nomappings', 'local_campusconnect');

            foreach ($localfields as $localmap) {
                $elname = $ecsid.'_'.$localmap.'_internal';
                if ($localmap == 'summary') {
                    $mform->addElement('editor', $elname, $localmap);
                    $mform->setType('fieldname', PARAM_RAW);
                    $mform->setDefault($elname, array('text'=>$currentmappings[$localmap], 'format'=>FORMAT_HTML));
                } else if ($metadata->is_text_field($localmap)) {
                    $mform->addElement('text', $elname, $localmap, $currentmappings[$localmap]);
                    $mform->setDefault($elname, $currentmappings[$localmap]);
                } else {
                    $maparray = $metadata->list_remote_to_local_fields($localmap, false);
                    if ($maparray) {
                        $maps = array('' => $strunmapped);
                        foreach ($maparray as $i) {
                            $maps[$i] = $i;
                        }
                    } else {
                        $maps = array('' => $strnomappings);
                    }
                    $mform->addElement('select', $elname, $localmap, $maps, $currentmappings[$localmap]);
                    $mform->setDefault($elname, $currentmappings[$localmap]);
                }
            }

            $mform->addElement('html', "<h3>".get_string('externalcourse', 'local_campusconnect')."</h3>");

            $metadata = new campusconnect_metadata($ecssettings, true);
            $localfields = $metadata->list_local_fields();
            $currentmappings = $metadata->get_import_mappings();

            foreach ($localfields as $localmap) {
                $elname = $ecsid.'_'.$localmap.'_external';
                if ($localmap == 'summary') {
                    $mform->addElement('editor', $elname, $localmap);
                    $mform->setType('fieldname', PARAM_RAW);
                    $mform->setDefault($elname, array('text'=>$currentmappings[$localmap], 'format'=>FORMAT_HTML));
                } else if ($metadata->is_text_field($localmap)) {
                    $mform->addElement('text', $elname, $localmap, $currentmappings[$localmap]);
                    $mform->setDefault($elname, $currentmappings[$localmap]);
                } else {
                    $maparray = $metadata->list_remote_to_local_fields($localmap, true);
                    if ($maparray) {
                        $maps = array('' => $strunmapped);
                        foreach ($maparray as $i) {
                            $maps[$i] = $i;
                        }
                    } else {
                        $maps = array('' => $strnomappings);
                    }
                    $mform->addElement('select', $elname, $localmap, $maps, $currentmappings[$localmap]);
                    $mform->setDefault($elname, $currentmappings[$localmap]);
                }
            }
        }

        $this->add_action_buttons();

    }

    public function set_errors($errors) {
        $form = $this->_form;
        foreach ($errors as $element => $message) {
            $form->setElementError($element, $message);
        }
    }
}