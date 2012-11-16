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
 * Front end for controlling the automatic filtering of created courses into subdirectories
 *
 * @package   local_campusconnect
 * @copyright 2012 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../config.php');

global $CFG, $PAGE, $OUTPUT;

require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/local/campusconnect/filtering.php');
require_once($CFG->dirroot.'/local/campusconnect/admin/coursefiltering_form.php');
require_once($CFG->dirroot.'/local/campusconnect/metadata.php');

$url = new moodle_url('/local/campusconnect/admin/coursefiltering.php', array());

admin_externalpage_setup('campusconnectcoursefiltering');

$PAGE->set_url($url);

// Process the general settings form.
$globalsettings = campusconnect_filtering::load_global_settings();
$attributescount = max(count($globalsettings['attributes']), 3);
$custom = array(
    'attributes' => campusconnect_metadata::list_remote_fields(false),
    'attributescount' => $attributescount
);
foreach ($globalsettings['attributes'] as $key => $value) {
    $attribname = "attributes[{$key}]";
    $globalsettings[$attribname] = $value;
}
$form = new campusconnect_coursefiltering_form(null, $custom);
$form->set_data($globalsettings);

if ($form->is_cancelled()) {
    redirect($PAGE->url); // Will clear the settings back to their previous values.
}
if ($data = $form->get_data()) {
    foreach ($data->attributes as $key => $val) {
        if ($val == -1) {
            unset($data->attributes[$key]);
        }
    }
    campusconnect_filtering::save_global_settings($data);
    redirect($PAGE->url); // To remove the POST params from the page load.
}

// Output everything.
echo $OUTPUT->header();

$form->display();

echo $OUTPUT->footer();
