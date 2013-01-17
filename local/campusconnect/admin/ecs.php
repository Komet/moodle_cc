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

global $CFG, $PAGE, $OUTPUT;

require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/local/campusconnect/connect.php');
require_once($CFG->dirroot.'/local/campusconnect/admin/ecs_form.php');

$deleteid = optional_param('delete', null, PARAM_INT);
$ecsid = optional_param('id', $deleteid, PARAM_INT);
$confirm = optional_param('confirm', null, PARAM_INT);

require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));

if ($ecsid) {
    admin_externalpage_setup('ecs'.$ecsid);
} else {
    admin_externalpage_setup('allecs');
}

$PAGE->set_url(new moodle_url('/local/campusconnect/admin/ecs.php'));
$PAGE->set_context(context_system::instance());

$ecssettings = new campusconnect_ecssettings($ecsid);

if (isset($deleteid)){
    require_sesskey();

    if ($confirm & confirm_sesskey()) {
        $ecssettings->delete();
        redirect(new moodle_url('/local/campusconnect/admin/allecs.php'));
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('deleteecsareyousure', 'local_campusconnect'));
    echo $OUTPUT->confirm(
            get_string('deleteecsareyousuremessage', 'local_campusconnect'),
            new moodle_url($PAGE->url, array('delete' => $deleteid, 'confirm' => 1)),
            new moodle_url('/local/campusconnect/admin/allecs.php'));
    echo $OUTPUT->footer();
    exit;

}

$currentsettings = $ecssettings->get_settings();

$form = new campusconnect_ecs_form('', $currentsettings);

$url_parts = parse_url($currentsettings->url);
if (!empty($url_parts['scheme'])) {
    $currentsettings->protocol = $url_parts['scheme'];
}
$currentsettings->url = '';
if (!empty($url_parts['host'])) {
    $currentsettings->url .= $url_parts['host'];
}
if (!empty($url_parts['path'])) {
    $currentsettings->url .= $url_parts['path'];
}
if (!empty($url_parts['port'])) {
    $currentsettings->port = $url_parts['port'];
}

$minutes = floor($currentsettings->crontime / 60);
$seconds = $currentsettings->crontime % 60;
$currentsettings->pollingtimemin = $minutes;
$currentsettings->pollingtimesec = $seconds;
$currentsettings->id = $ecsid;

$form->set_data($currentsettings);

if ($form->is_cancelled()) {
    redirect($PAGE->url); // Will clear the settings back to their previous values.
}
if ($data = $form->get_data()) {

    $data->crontime = ($data->pollingtimemin * 60) + $data->pollingtimesec;
    $url = $data->url;
    if (!empty($data->port)) {
        $spliturl = explode('/', $url, 2);
        $url = $spliturl[0].':'.$data->port;
        if (isset($spliturl[1])) {
            $url .= '/'.$spliturl[1];
        }
    }
    $data->url = $data->protocol.'://'.$url;

    $ecssettings->save_settings($data);

    redirect(new moodle_url('/local/campusconnect/admin/allecs.php'));
}

echo $OUTPUT->header();

$form->display();

echo $OUTPUT->footer();