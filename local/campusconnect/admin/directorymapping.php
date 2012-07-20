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
 * Front end for mapping directories onto categories
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../config.php');

require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/local/campusconnect/directorytree.php');
require_once($CFG->dirroot.'/local/campusconnect/admin/directorymapping_form.php');

//TODO - correct settings at top of form

//TODO - add form / save button / processing for mapping tree nodes

//TODO - add YUI treeview

//TODO - add javascript code to display mappings as they are selected

//TODO - add classes to show directory tree status

$rootid = required_param('rootid', PARAM_INT);
$dirtree = campusconnect_directorytree::get_by_root_id($rootid);

admin_externalpage_setup('campusconnectdirectorymapping');
$PAGE->set_url(new moodle_url('/local/campusconnect/admin/directorymapping.php', array('rootid' => $rootid)));

$form = new campusconnect_directorymapping_form(null, array('dirtree' => $dirtree));
if ($form->is_cancelled()) {
    redirect($PAGE->url); // Will clear the settings back to their previous values.
}
if ($data = $form->get_data()) {
    $dirtree->update_settings($data);
    redirect($PAGE->url); // To remove the POST params from the page load.
}

$table = new html_table();
$table->head = array(
    get_string('localcategories', 'local_campusconnect'),
    get_string('cmsdirectories', 'local_campusconnect')
);
$table->size = array(
    '50%',
    ''
);
$table->attributes = array('style' => 'width: 90%;');

$categorytree = campusconnect_directory::output_category_tree('category');
$categorytree = html_writer::tag('div', $categorytree, array('id' => 'campusconnect_categorytree'));
if ($dirtree = campusconnect_directory::output_directory_tree($dirtree, 'directory')) {
    $dirtree = html_writer::tag('div', $dirtree, array('id' => 'campusconnect_dirtree'));
} else {
    $dirtree = get_string('nodirectories', 'local_campusconnect');
}
$row = array(
    $categorytree,
    $dirtree
);
$table->data = array($row);

echo $OUTPUT->header();

$form->display();
echo $OUTPUT->heading(get_string('directorymapping', 'local_campusconnect'));
echo html_writer::table($table);

echo $OUTPUT->footer();
