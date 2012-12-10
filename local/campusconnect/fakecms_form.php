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
        $crsresources = $this->_customdata['crsresources'];
        $crsresources = array_combine($crsresources, $crsresources);

        $actions = array(
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
        $mform->addElement('select', 'diraction', 'Action', $actions);
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
        
        // Courses
        $mform->addElement('header', '', 'Course');
        $mform->addElement('select', 'crsaction', 'Action', $actions);
        $mform->addElement('select', 'crsresourceid', 'Existing resource', $crsresources);
        $mform->disabledIf('crsresourceid', 'crsaction', 'eq', 'create');

        $mform->addElement('text', 'crsorganisation', 'Course organisation');
        $mform->addElement('text', 'crsid', 'Course id', array('size' => 20));
        $mform->addElement('text', 'crsterm', 'Term', array('size' => 20));
        $mform->addElement('text', 'crstitle', 'Title');
        $mform->addElement('text', 'crstype', 'Course type');
        $mform->addElement('text', 'crsmaxpart', 'Max participants', array('size' => 20));
        $mform->addElement('select', 'crsparallel', 'Parallel group scenario', array(-1 => 'none', 1 => 'One course', 2 => 'Separate groups', 3 => 'Separate courses', 4 => 'Separate lecturers'));

        for ($i=1; $i<=2; $i++) {
            $grp = array(
                $mform->createElement('text', "crslecturerfirst[$i]", ''),
                $mform->createElement('text', "crslecturerlast[$i]", ''),
            );
            $mform->addGroup($grp, "crslecturer[$i]", "Lecturer $i (first, last)", ' ', false);
        }

        for ($i=1; $i<=3; $i++) {
            $grp = array(
                $mform->createElement('text', "crsallparent[$i]", '', array('size' => 20)),
                $mform->createElement('text', "crsallorder[$i]", '', array('size' => 20)),
            );
            $mform->addGroup($grp, "crsallocation[$i]", "Allocation $i (parentdir, order)", ' ', false);
        }

        $mform->addElement('static', '', '', 'Parallel groups');
        for ($i=1; $i<=3; $i++) {
            $mform->addElement('text', "crsptitle[$i]", "PGroup$i title");
            $mform->addElement('text', "crspid[$i]", "PGroup$i id");
            $mform->addElement('text', "crspcomment[$i]", "PGroup$i comment");
            for ($j=1; $j<=3; $j++) {
                $grp = array(
                    $mform->createElement('text', "crsplecturerfirst[$i][$j]", ''),
                    $mform->createElement('text', "crsplecturerlast[$i][$j]", ''),
                );
                $mform->addGroup($grp, "crsplecturer[$i][$j]", "Lecturer $j (first, last)", ' ', false);
            }
        }

        $mform->addElement('submit', 'crssubmit', 'Send course request');
        
    }
}
