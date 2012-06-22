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
    require_once($CFG->dirroot.'/local/campusconnect/lib.php');

    admin_externalpage_setup('campusconnectsettings');

    $sitecontext = get_context_instance(CONTEXT_SYSTEM);
    $site = get_site();
    
    $ecslist = campusconnect_ecssettings::list_ecs();
    
    $settings = new campusconnect_ecssettings('47');
    
    $ecssettings = $settings->get_settings();
    
    $connect = new campusconnect_connect($settings);
    
    $test = campusconnect_participantsettings::load_communities($settings);
	
	echo $OUTPUT->header();
	echo $OUTPUT->heading(get_string('pluginname', 'local_campusconnect'));
	
	print 'Campus Connect Settings';
	
	print_object($ecslist);
	print_object($ecssettings);
	
	print_object($test);
	
	echo $OUTPUT->footer();

?>