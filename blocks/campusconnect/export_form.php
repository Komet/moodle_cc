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
 * Form for editing export settings for CampusConnect block
 *
 * @package    block_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');

class block_campusconnect_export_form extends moodleform {
    function definition() {
        $mform = $this->_form;
        $export = $this->_customdata;

        $mform->addElement('selectyesno', 'enableexport', get_string('exportcourse', 'block_campusconnect'));
        $mform->setDefault('enableexport', $export->is_exported());

        $parts = $export->list_participants();
        foreach ($parts as $identifier => $part) {
            $elname = 'part_'.$identifier;
            $mform->addElement('advcheckbox', $elname, '', s($part->get_displayname()));
            $mform->disabledIf($elname, 'enableexport', 'eq', 0);
            $mform->setDefault($elname, $part->is_exported());
        }
        $mform->addElement('hidden', 'courseid', $export->get_courseid());

        $this->add_action_buttons();
    }
}