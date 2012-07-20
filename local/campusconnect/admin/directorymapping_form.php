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
 * Form for general directory tree settings
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/local/campusconnect/directorytree.php');

class campusconnect_directorymapping_form extends moodleform {
    public function definition() {
        $mform = $this->_form;

        $enabled = campusconnect_directorytree::enabled();
        $createemptycategories = campusconnect_directorytree::should_create_empty_categories();

        $mform->addElement('header', 'general', get_string('directorytreesettings', 'local_campusconnect'));

        $mform->addElement('static', 'warning', '', 'TODO - replace this with the correct form');

        $mform->addElement('selectyesno', 'enabled', get_string('activatenodemapping', 'local_campusconnect'));
        $mform->setDefault('enabled', $enabled);
        $mform->addElement('selectyesno', 'createemptycategories', get_string('createemptycategories', 'local_campusconnect'));
        $mform->setDefault('createemptycategories', $createemptycategories);
        $mform->addHelpButton('createemptycategories', 'createemptycategories', 'local_campusconnect');

        $this->add_action_buttons();
    }
}