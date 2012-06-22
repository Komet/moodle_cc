<?php

// * Miscellaneous settings

if ($hassiteconfig) {
	
    require_once($CFG->dirroot.'/local/campusconnect/lib.php');
    
    $ecslist = campusconnect_ecssettings::list_ecs();
    
     // Web service test clients DO NOT COMMIT : THE EXTERNAL WEB PAGE IS NOT AN ADMIN PAGE !!!!!
    $ADMIN->add('campusconnect', new admin_externalpage('campusconnectsettings',  get_string('settings','local_campusconnect'), "$CFG->wwwroot/admin/campusconnect.php"));
    
    $ADMIN->add('campusconnect', new admin_category('ECS', get_string('ecs','local_campusconnect')));

    $ADMIN->add('ECS', new admin_externalpage('allecs',  get_string('allecs','local_campusconnect'), "$CFG->wwwroot/admin/campusconnect/allecs.php"));
    
    foreach ($ecslist as $ecsid => $ecsname) {
	    $ADMIN->add('ECS', new admin_externalpage('esc',  $ecsname, "$CFG->wwwroot/admin/campusconnect/ecs.php?id=$ecsid"));
    }
    
    $ADMIN->add('campusconnect', new admin_externalpage('campusconnectparticipants',  get_string('participants','local_campusconnect'), "$CFG->wwwroot/admin/campusconnect/participants.php"));
    
    $ADMIN->add('campusconnect', new admin_externalpage('campusconnectdatamapping',  get_string('ecsdatamapping','local_campusconnect'), "$CFG->wwwroot/admin/campusconnect/datamapping.php"));
    
    $ADMIN->add('campusconnect', new admin_externalpage('campusconnectcategoryassignment',  get_string('assignmenttocategories','local_campusconnect'), "$CFG->wwwroot/admin/campusconnect/categoryassignment.php"));

}
