<?php

// * Miscellaneous settings

if ($hassiteconfig) {

     // Web service test clients DO NOT COMMIT : THE EXTERNAL WEB PAGE IS NOT AN ADMIN PAGE !!!!!
    $ADMIN->add('campusconnect', new admin_externalpage('campusconnectsettings',  get_string('settings','local_campusconnect'), "$CFG->wwwroot/admin/campusconnect.php"));
    
    $ADMIN->add('campusconnect', new admin_category('ECS', get_string('ecs','local_campusconnect')));

    $ADMIN->add('ECS', new admin_externalpage('allecs',  get_string('allecs','local_campusconnect'), "$CFG->wwwroot/admin/campusconnect/allecs.php"));

}
