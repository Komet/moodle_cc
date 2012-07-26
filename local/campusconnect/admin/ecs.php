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

require_once(dirname(__FILE__).'/../../../config.php');

require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/local/campusconnect/connect.php');
require_once($CFG->dirroot.'/local/campusconnect/admin/ecs_form.php');

require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));

$PAGE->set_url(new moodle_url('/local/campusconnect/admin/ecs.php'));
$PAGE->set_context(context_system::instance());

global $CFG, $DB;

$function = optional_param('fn', null, PARAM_TEXT);
$deleteid = optional_param('delete', null, PARAM_INT);
$ecsid = optional_param('id', $deleteid, PARAM_INT);

$ecssettings = new campusconnect_ecssettings($ecsid);

$form = new campusconnect_ecs_form('', $ecssettings->get_settings());
if ($form->is_cancelled()) {
    redirect($PAGE->url); // Will clear the settings back to their previous values.
}
if ($data = $form->get_data()) {

    $data->crontime = ($data->pollingtime['mm'] * 60) + $data->pollingtime['ss'];
    $data->url = $data->protocol.'://'.$data->url.':'.$data->port;
    $data = (object)(array_merge((array)$ecssettings->get_settings(),(array)$data));

    $ecssettings->save_settings($data);

}

echo $OUTPUT->header();

$form->display();

echo $OUTPUT->footer();