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
 * ECS settings page for campus connect
 *
 * @package    admin_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir."/formslib.php");
require_once($CFG->dirroot.'/local/campusconnect/ecssettings.php'); // For AUTH_xx definitions

class campusconnect_rolemapping_form extends moodleform {

    public function definition() {
        global $DB;

        $roles = $DB->get_records('role');
        $mappings = $DB->get_records('local_campusconnect_rolemap');

        $mform = $this->_form;

        $mform->addElement('header', 'rolemappingsettings', get_string('rolemapping', 'local_campusconnect'));

        $this->add_action_buttons();
    }
}