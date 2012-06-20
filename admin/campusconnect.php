<?php

/**
 * Settings page for campus connect
 *
 * @package    admin_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
	require_once('../config.php');
    require_once($CFG->libdir.'/adminlib.php');
    require_once($CFG->dirroot.'/local/campusconnect/connect.php');

    admin_externalpage_setup('campusconnectsettings');

    $sitecontext = get_context_instance(CONTEXT_SYSTEM);
    $site = get_site();    
    
    $connect = new campusconnect_connect();
    
    
    $ecs = $connect->get_ecs_id();
    
	
	echo $OUTPUT->header();
	echo $OUTPUT->heading(get_string('campusconnect', 'admin'));
	
	print 'hello';
	print_object($ecs);
	
	echo $OUTPUT->footer();

?>