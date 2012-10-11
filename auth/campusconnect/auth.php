<?php

/**
 * @package    campusconnect
 * @copyright  2012 Synergy Learning
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/authlib.php');

/**
 * CampusConnect authentication plugin.
 */
class auth_plugin_campusconnect extends auth_plugin_base {

    static $authenticateduser;

    /**
     * Constructor.
     */
    function auth_plugin_campusconnect() {
        $this->authtype = 'campusconnect';
    }

    /**
     * Authenticates user against ECS
     * Returns true if ECS confirms user is authenticated.
     *
     * @param string $username The username (with system magic quotes)
     * @param string $password The password (with system magic quotes)
     * @return bool Authentication success or failure.
     */
    function user_login($username, $password) {
        if (isset(auth_plugin_campusconnect::$authenticateduser)
            && is_object(auth_plugin_campusconnect::$authenticateduser)
            && isset(auth_plugin_campusconnect::$authenticateduser->id)
        ) {
            return true;
        }
        return false;
    }

    function prevent_local_passwords() {
        return false;
    }

    /**
     * Returns true if this authentication plugin is 'internal'.
     *
     * @return bool
     */
    function is_internal() {
        return false;
    }

    /**
     * Hook for overriding behaviour of login page.
     * This method is called from login/index.php page for all enabled auth plugins.
     *
     */
    function loginpage_hook() {
        global $SESSION, $CFG, $DB;

        if (!isset($SESSION) || !isset($SESSION->wantsurl)) {
            return;
        }
        $urlparse = parse_url($SESSION->wantsurl);
        if (!is_array($urlparse) || !isset($urlparse['query'])) {
            return;
        }
        $urlquery = str_replace('&amp;', '&', $urlparse['query']);
        $queryparams = explode('&', $urlquery);
        $paramassoc = array();
        foreach ($queryparams as $paramval) {
            $split = explode('=', $paramval);
            if (count($split)<2) {
                continue;
            }
            $paramassoc[$split[0]] = urldecode(urldecode($split[1]));
        }

        if (!isset($paramassoc['ecs_hash'])) {
            return;
        }
        $hash = $paramassoc['ecs_hash'];

        require_once($CFG->dirroot.'\local\campusconnect\connect.php');
        $connecterrors = false;
        $authenticated = false;
        $ecslist = campusconnect_ecssettings::list_ecs();
        $authenticatingecs = null;
        foreach ($ecslist as $ecsid => $ecsname) {
            $settings = new campusconnect_ecssettings($ecsid);
            try {
                $connect = new campusconnect_connect($settings);
                $auth = $connect->get_auth($hash);
                if (is_object($auth) && isset($auth->hash)) {
                    $authenticated = true;
                    $authenticatingecs = $settings->get_id();
                    break;
                }
            } catch (campusconnect_connect_exception $e) {
                $connecterrors = true;
            }
        }

        //Throw an error only if a connection exception was thrown and the user wasn't authenticated by any other ECS
        if (!$authenticated) {
            if ($connecterrors) {
                print_error('ecserror_subject', 'local_campusconnect');
            }
            return;
        }

        //We've now confirmed authentication! Let's create/find the user:
        if (!isset($paramassoc['ecs_uid_hash'])) {
            return;
        }
        $uidhash = $paramassoc['ecs_uid_hash'];
        $username = $this->username_from_params($uidhash, $authenticatingecs);

        //If user does not exist, create:
        if (!$ccuser = get_complete_user_data('username', $username)){
            $ccuser = new stdClass();
            $ccuser->username = $username;
            $requiredfields = array('firstname', 'lastname', 'email');
            foreach ($requiredfields as $field) {
                $ccuser->{$field} = isset($paramassoc['ecs_'.$field]) ? $paramassoc['ecs_'.$field] : '';
            }
            $ccuser->modified = time();
            $ccuser->confirmed = 1;
            $ccuser->auth = $this->authtype;
            $ccuser->mnethostid = $CFG->mnet_localhost_id;
            $ccuser->lang = $CFG->lang;
            if (!$id = $DB->insert_record('user', $ccuser)) {
                print_error('errorcreatinguser', 'auth_campusconnect');
            }
            $ccuser = get_complete_user_data('id', $id);
        }

        //Let index.php know that user is authenticated:
        global $frm, $user;
        $frm = (object)array('username' => $ccuser->username, 'password' => '');
        $user = clone($ccuser);
        auth_plugin_campusconnect::$authenticateduser = clone($ccuser);
    }

    /**
     * Logout - check if user is enrolled in any course, if not, delete
     *
     */
    function prelogout_hook() {
        global $CFG, $USER, $DB;
        if ($USER->auth != $this->authtype) {
            return;
        }

        //Am I currently enrolled?
        if(isset($USER->enrol) && isset($USER->enrol['enrolled']) && count($USER->enrol['enrolled'])) {
            return;
        }

        //Currently not enrolled - have I ever enrolled in anything?
        if ($DB->record_exists('log', array('userid' => $USER->id, 'action' => 'enrol'))) {
            return;
        }

        //OK, delete:
        $user = $DB->get_record('user', array('id' => $USER->id));
        $this->user_dataprotect_delete($user);
    }

    /**
     * Reads user information from ECS and returns it as array()
     *
     * @param string $username username
     * @return mixed array with no magic quotes or false on error
     */
    function get_userinfo($username) {
        return array();
    }

    /*
     * Cron - delete users who timed out and never enrolled
     * And inactivate users who haven't been active for some time
     */
    function cron() {
        global $CFG, $DB;

        //Find users whose session should have expired by now
        $params = array(
            'minaccess' => time() - $CFG->sessiontimeout,
            'auth' => $this->authtype,
        );
        $sql = "
        SELECT usr.*
        FROM {user} usr
        WHERE deleted = 0
        AND usr.lastaccess < :minaccess
        AND usr.auth = :auth
        AND NOT EXISTS (
          SELECT *
          FROM {user_enrolments} uen
          WHERE uen.userid = usr.id
        )
        AND NOT EXISTS (
          SELECT *
          FROM {log} lg
          WHERE lg.userid = usr.id
          AND lg.action = 'enrol'
        )
        ";
        $deleteusers = $DB->get_records_sql($sql, $params);
        foreach ($deleteusers as $deleteuser) {
            mtrace(get_string('deletinguser', 'auth_campusconnect'). ': '. $deleteuser->id);
            $this->user_dataprotect_delete($deleteuser);
        }


        //TODO change cron time to 5 minutes

        return true;
    }

    //Local functions

    /*
     * Generate Moodle username from an array of query parameters and ECS id
     * @param string $uidhash - an 'ecs_uid_hash' from the url params
     * @param int $ecsid - the ECS that authenticated the user
     */

    private function username_from_params($uidhash, $ecsid) {
        $split = explode('_usr_', $uidhash);
        if (count($split) != 2) {
            return 'campusconnect_'.sha1($uidhash);
        }
        $remoteuserid = $split[1];
        if (strlen($remoteuserid)>40) {
            $remoteuserid = sha1($remoteuserid);
        }
        return 'campusconnect_ecs'.$ecsid.'_usr'.$remoteuserid;
    }

    /*
     * Removes all personal information from a user table, deletes the user and all logs
     * @param object $user
     */
    private function user_dataprotect_delete($user) {
        global $DB;

        //Clean personal information:
        $user->email = 'usr' . $user->id . '@' . 'usr' . $user->id . '.com';
        $fieldstoclear = array('idnumber', 'firstname', 'lastname',
                               'yahoo', 'aim', 'msn', 'phone1', 'phone2','institution', 'department',
                               'address', 'city', 'country', 'lastip', 'url', 'description', 'imagealt');
        foreach ($fieldstoclear as $fieldname) {
            $user->{$fieldname} = '';
        }
        $DB->update_record('user', $user);

        //Set to deleted:
        delete_user($user);

        //Delete logs:
        $DB->delete_records('log', array('userid' => $user->id));
    }
}