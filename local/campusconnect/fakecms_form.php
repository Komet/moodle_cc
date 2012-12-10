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
 * Supports the creation of resources via fakecms.php
 *
 * @package   local_campusconnect
 * @copyright 2012 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/formslib.php');


/** Form for entering CMS data */
class fakecms_form extends moodleform {

    public function definition() {
        $mform = $this->_form;

        $participants = $this->_customdata['participants'];
        $cmsparticipant = $this->_customdata['cmsparticipant'];
        $thisparticipant = $this->_customdata['thisparticipant'];
        $dirresources = $this->_customdata['dirresources'];
        $dirresources = array_combine($dirresources, $dirresources);

        $actiones = array(
            'create' => 'create',
            'retrieve' => 'retrieve',
            'update' => 'update',
            'delete' => 'delete',
        );

        // General settings
        $mform->addElement('header', '', 'General');
        $mform->addElement('select', 'srcpart', 'Send from', $participants);
        $mform->setDefault('srcpart', $cmsparticipant);
        $mform->addElement('select', 'dstpart', 'Send to', $participants);
        $mform->setDefault('dstpart', $thisparticipant);

        // Directory trees
        $mform->addElement('header', '', 'Directory tree');
        $mform->addElement('select', 'diraction', 'Action', $actiones);
        $mform->addElement('select', 'dirresourceid', 'Existing resource', $dirresources);
        $mform->disabledIf('dirresourceid', 'diraction', 'eq', 'create');

        $mform->addElement('text', 'dirtreetitle', 'Directory tree name');
        $mform->setDefault('dirtreetitle', 'Directory tree');
        $mform->addElement('text', 'dirrootid', 'Root directory id', array('size' => 20));
        $mform->addElement('text', 'dirid', 'Directory id', array('size' => 20));
        $mform->addElement('text', 'dirtitle', 'Directory title');
        $mform->addElement('text', 'dirparentid', 'Parent directory id', array('size' => 20));
        $mform->addElement('text', 'dirorder', 'Directory order (within parent)', array('size' => 10));

        $mform->addElement('submit', 'dirsubmit', 'Send directory request');
    }
}
