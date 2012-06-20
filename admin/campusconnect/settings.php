<?php

/**
 * Settings page for campus connect
 *
 * @package    admin_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

defined('MOODLE_INTERNAL') || die();

$PAGE->set_url('/admin/campusconnect/settings.php');
$PAGE->set_context(context_system::instance());

require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('campusconnect', 'admin'));

print 'hello';

echo $OUTPUT->footer();

?>