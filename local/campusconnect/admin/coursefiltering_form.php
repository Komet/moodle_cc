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
 * Forms for the 'course filtering' page
 *
 * @package   local_campusconnect
 * @copyright 2012 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->libdir.'/formslib.php');

class campusconnect_coursefiltering_form extends moodleform {
    protected function definition() {
        $mform = $this->_form;

        $attributes = $this->_customdata['attributes'];
        $attributescount = $this->_customdata['attributescount'];

        $categorylist = array();
        $categoryparents = array();
        make_categories_list($categorylist, $categoryparents);

        // Form elements
        $mform->addElement('header', '', get_string('coursefilteringsettings', 'local_campusconnect'));
        $mform->addElement('selectyesno', 'enabled', get_string('enablefiltering', 'local_campusconnect'));
        $mform->addElement('select', 'defaultcategory', get_string('defaultcategory', 'local_campusconnect'), $categorylist);
        $mform->addElement('selectyesno', 'usesinglecategory', get_string('usesinglecategory', 'local_campusconnect'));
        $mform->addElement('select', 'singlecategory', get_string('singlecategory', 'local_campusconnect'), $categorylist);

        $mform->addElement('header', '', get_string('courseattributes', 'local_campusconnect'));
        $attributes = array(-1 => get_string('unused', 'local_campusconnect')) + $attributes;
        $repeatarray = array(
            $mform->createElement('select', 'attributes', get_string('filteringattribute', 'local_campusconnect'), $attributes)
        );
        $repeatopts = array('attributes' => array('default' => -1));
        $stradd = get_string('addattributes', 'local_campusconnect');
        $this->repeat_elements($repeatarray, $attributescount, $repeatopts, 'attributescount', 'add_attributes', 2, $stradd, true);

        $this->add_action_buttons(true, get_string('savegeneral', 'local_campusconnect'));

        // Help buttons
        $mform->addHelpButton('usesinglecategory', 'usesinglecategory', 'local_campusconnect');

        // Disable all form elements if filtering is disabled.
        $mform->disabledIf('defaultcategory', 'enabled', 'eq', 0);
        $mform->disabledIf('usesinglecategory', 'enabled', 'eq', 0);
        $mform->disabledIf('singlecategory', 'enabled', 'eq', 0);
        $mform->disabledIf('attributes', 'enabled', 'eq', 0);
    }

    public function validation($data, $files) {
        // Check that each course attribute is only listed once.
        $errors = parent::validation($data, $files);
        $usedattrib = array();
        if (isset($data['attributes'])) {
            foreach ($data['attributes'] as $idx => $value) {
                if ($value == -1) {
                    continue;
                }
                if (in_array($value, $usedattrib)) {
                    $errors["attributes[$idx]"] = get_string('attributesonce', 'local_campusconnect', $value);
                } else {
                    $usedattrib[] = $value;
                }
            }
        }
        return $errors;
    }
}