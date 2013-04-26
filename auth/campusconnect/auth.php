<?php

/**
 * @package    campusconnect
 * @copyright  2012 Synergy Learning
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); ///  It must be included from a Moodle page
}
global $CFG;
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

        self::log("\n\n====Login required - checking for CampusConnect authentication====");

        if (!isset($SESSION) || !isset($SESSION->wantsurl)) {
            self::log("No destination URL");
            return;
        }
        $urlparse = parse_url($SESSION->wantsurl);
        if (!is_array($urlparse) || !isset($urlparse['query'])) {
            self::log("Destination URL lacks query string");
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
            $paramassoc[$split[0]] = urldecode($split[1]);
        }

        // Check the incoming URL matches a valid Moodle course URL
        if (!isset($paramassoc['id'])) {
            self::log("No courseid in the destination URL");
            return; // URL didn't include a course ID
        }
        $courseurl = new moodle_url('/course/view.php', array('id' => $paramassoc['id']));
        $courseurl = $courseurl->out();
        $courseviewurl = new moodle_url('/local/campusconnect/viewcourse.php', array('id' => $paramassoc['id']));
        $courseviewurl = $courseviewurl->out();
        if (substr_compare($SESSION->wantsurl, $courseurl, 0, strlen($courseurl)) !== 0) {
            $courseurl = $courseviewurl;
            if (substr_compare($SESSION->wantsurl, $courseurl, 0, strlen($courseurl)) !== 0) {
                self::log("Destination URL is not a Moodle course");
                return; // URL didn't match a Moodle course URL
            }
        }
        self::log("Destination URL: {$SESSION->wantsurl}");

        require_once($CFG->dirroot.'/local/campusconnect/connect.php');
        $connecterrors = false;
        $authenticated = false;
        $ecslist = campusconnect_ecssettings::list_ecs();
        $authenticatingecs = null;
        if (!empty($paramassoc['ecs_hash_url'])) {
            self::log("ecs_hash_url found: {$paramassoc['ecs_hash_url']}");

            // Newer 'ecs_hash_url' param included => use this to determine the ECS to authenticate against.
            $hashurl = $paramassoc['ecs_hash_url'];
            $matches = array();
            if (!preg_match('|(.*)/sys/auths/(.*)|', $hashurl, $matches)) {
                self::log("Unable to parse ecs_hash_url");
                return; // Not able to parse the 'ecs_hash_url' successfully.
            }
            $baseurl = $matches[1];
            $hash = $matches[2];

            $ecslist = campusconnect_ecssettings::list_ecs();
            foreach ($ecslist as $ecsid => $ecsname) {
                $settings = new campusconnect_ecssettings($ecsid);
                self::log("Comparing hash URL: {$baseurl} with ECS server '$ecsname' ($ecsid): ".$settings->get_url());
                if (self::strip_port($settings->get_url()) == self::strip_port($baseurl)) {
                    // Found an ECS with matching URL - attempt to authenticate the hash.
                    try {
                        $connect = new campusconnect_connect($settings);
                        $auth = $connect->get_auth($hash);
                        self::log("Checking hash against ECS server:");
                        self::log_print_r($auth);
                        if (is_object($auth) && isset($auth->hash) && $auth->hash == $hash) {
                            if (isset($auth->realm)) {
                                $realm = campusconnect_connect::generate_realm($courseurl, $paramassoc);
                                if ($realm != $auth->realm) {
                                    self::log("Locally generated realm: {$realm} does not match auth realm: {$auth->realm}");
                                    continue; // Params do not match those when the original hash was generated.
                                }
                            } else {
                                self::log("Realm not included in auth response");
                            }
                            $authenticated = true;
                            $authenticatingecs = $ecsid;
                            break;
                        }
                    } catch (campusconnect_connect_exception $e) {
                        $connecterrors = true;
                    }
                }
            }

        } else {

            // Fall back to the legacy 'ecs_hash' param
            if (empty($paramassoc['ecs_hash'])) {
                self::log("Neither ecs_hash nor ecs_hash_url included in destination URL");
                return;
            }
            $hash = $paramassoc['ecs_hash'];
            self::log("ecs_hash found: {$hash}");

            foreach ($ecslist as $ecsid => $ecsname) {
                self::log("Attempting to authenticate hash against '{$ecsname}' ($ecsid)");
                // Try each of the active ECS and see if the hash authenticates.
                $settings = new campusconnect_ecssettings($ecsid);
                try {
                    $connect = new campusconnect_connect($settings);
                    $auth = $connect->get_auth($hash);
                    self::log_print_r($auth);
                    if (is_object($auth) && isset($auth->hash) && ($auth->hash == $hash)) {
                        if (isset($auth->realm)) {
                            $realm = campusconnect_connect::generate_realm($courseurl, $paramassoc);
                            if ($realm != $auth->realm) {
                                self::log("Locally generated realm: {$realm} does not match auth realm: {$auth->realm}");
                                continue;
                            }
                        } else {
                            self::log("Realm not included in auth response");
                        }
                        $authenticated = true;
                        $authenticatingecs = $ecsid;
                        break;
                    }
                } catch (campusconnect_connect_exception $e) {
                    $connecterrors = true;
                }
            }
        }

        //Throw an error only if a connection exception was thrown and the user wasn't authenticated by any other ECS
        if (!$authenticated) {
            if ($connecterrors) {
                print_error('ecserror_subject', 'local_campusconnect');
            }
            self::log("No ECS servers have authenticated the ECS hash");
            return;
        }

        //We've now confirmed authentication! Let's create/find the user:
        if (isset($paramassoc['ecs_uid'])) {
            $uidhash = $paramassoc['ecs_uid']; // New name for the parameter.
        } else {
            if (!isset($paramassoc['ecs_uid_hash'])) {
                self::log("Neither ecs_uid nor ecs_uid_hash found in destination URL");
                return;
            }
            $uidhash = $paramassoc['ecs_uid_hash']; // Legacy name for the parameter.
        }
        if (!isset($paramassoc['ecs_login'])) {
            self::log("ecs_login not found in destination URL");
            return;
        }
        $username = $this->username_from_params($paramassoc['ecs_login'], $uidhash, $authenticatingecs);
        $basicuserfields = array('firstname', 'lastname', 'email');

        self::log("Authentication successful");

        //If user does not exist, create:
        if (!$ccuser = get_complete_user_data('username', $username)){
            self::log("Creating a new user account with username {$username}");
            $ccuser = new stdClass();
            $ccuser->username = $username;
            foreach ($basicuserfields as $field) {
                $ccuser->{$field} = isset($paramassoc['ecs_'.$field]) ? $paramassoc['ecs_'.$field] : '';
            }
            $ccuser->modified = time();
            $ccuser->confirmed = 1;
            $ccuser->auth = $this->authtype;
            $ccuser->mnethostid = $CFG->mnet_localhost_id;
            $ccuser->lang = $CFG->lang;
            $ccuser->timecreated = time();
            if (!$id = $DB->insert_record('user', $ccuser)) {
                print_error('errorcreatinguser', 'auth_campusconnect');
            }
            $ccuser = get_complete_user_data('id', $id);
        }

        self::log("User account details:");
        self::log_print_r($ccuser);

        //Do we need to update details?
        $needupdate = false;
        foreach ($basicuserfields as $field) {
            if (isset($paramassoc['ecs_'.$field]) && $paramassoc['ecs_'.$field] != $ccuser->{$field}) {
                $ccuser->{$field} = $paramassoc['ecs_'.$field];
                $needupdate = true;
            }
        }
        if ($needupdate) {
            self::log("Updating user account details:");
            self::log_print_r($ccuser);
            $DB->update_record('user', $ccuser);
        }

        //Let index.php know that user is authenticated:
        global $frm, $user;
        $frm = (object)array('username' => $ccuser->username, 'password' => '');
        $user = clone($ccuser);
        auth_plugin_campusconnect::$authenticateduser = clone($ccuser);
    }

    /**
     * Return the given URL with the port number removed.
     * @param $url
     * @return string
     */
    public static function strip_port($url) {
        $parts = parse_url($url);
        $wantedparts = array('scheme', 'host', 'path', 'query');
        $ret = '';
        foreach ($wantedparts as $part) {
            if (array_key_exists($part, $parts)) {
                $ret .= $parts[$part];
                if ($part == 'scheme') {
                    $ret .= '://';
                }
            }
        }
        return $ret;
    }

    /**
     * Logout - check if user is enrolled in any course, if not, delete
     *
     */
    function prelogout_hook() {
        global $USER, $DB;
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
     * Cron - delete users who timed out and never enrolled,
     * Inactivate users who haven't been active for some time
     * And notify relevant users about users created
     */
    function cron() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/campusconnect/connect.php');

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
          SELECT * FROM {user_enrolments} uen
          WHERE uen.userid = usr.id
        )
        AND NOT EXISTS (
          SELECT * FROM {log} lg
          WHERE lg.userid = usr.id
          AND lg.action = 'enrol'
        )
        AND NOT EXISTS (
          SELECT * FROM {sessions} ssn
          WHERE ssn.userid = usr.id
        )
        ";
        $deleteusers = $DB->get_records_sql($sql, $params);
        foreach ($deleteusers as $deleteuser) {
            mtrace(get_string('deletinguser', 'auth_campusconnect'). ': '. $deleteuser->id);
            $this->user_dataprotect_delete($deleteuser);
        }

        //Make users who haven't enrolled in a long time inactive
        $ecslist = campusconnect_ecssettings::list_ecs();
        $ecsemails = array(); //We'll need it for later
        foreach ($ecslist as $ecsid => $ecsname) {
            //Get the activation period
            $settings = new campusconnect_ecssettings($ecsid);
            $monthsago = $settings->get_import_period();
            $month = date('n') - $monthsago;
            $year = date('Y');
            $day = date('j');
            if ($month < 1) {
                $year += floor(($month -1) / 12);
                $month = $month % 12 + 12;
            }
            $cutoff = mktime(date('H'), date('i'), date('s'), $month, $day, $year);
            $sql = "SELECT u.id
                      FROM {user} u
                      JOIN {auth_campusconnect} ac ON u.username = ac.username AND ac.ecsid = :ecsid
                     WHERE u.suspended = 0 AND u.deleted = 0
                       AND (
                         EXISTS (
                           SELECT *
                             FROM {user_enrolments} uen
                            WHERE uen.userid = u.id
                         ) OR EXISTS (
                           SELECT *
                             FROM {log} lg
                            WHERE lg.userid = u.id
                              AND lg.action = 'enrol'
                         )
                       )
                       AND NOT EXISTS (
                         SELECT *
                           FROM {log} lg2
                          WHERE lg2.userid = u.id
                            AND lg2.action = 'enrol'
                            AND lg2.time > :cutoff
                       )
                   ";
            $params = array('ecsid' => $ecsid, 'cutoff' => $cutoff);
            $userids = $DB->get_fieldset_sql($sql, $params);
            if (!empty($userids)) {
                list($usql, $params) = $DB->get_in_or_equal($userids);
                $DB->execute("UPDATE {user} u
                                 SET suspended = 1
                               WHERE u $usql", $params);
            }

            //For later:
            $ecsemails[$ecsid] = $settings->get_notify_users();
        }

        //Notify relevant users about new accounts
        if (!$lastsent = get_config('auth_campusconnect', 'lastnewusersemailsent')) {
            $lastsent = 0;
        }
        $sendupto = time() - 1;
        $params = array(
            'auth' => $this->authtype,
            'lastsent' => $lastsent,
            'sendupto' => $sendupto
        );
        $sql = "
        SELECT usr.*
        FROM {user} usr
        WHERE usr.auth = :auth
        AND deleted = 0
        AND usr.timecreated > :lastsent
        AND usr.timecreated <= :sendupto
        ";
        $newusers = $DB->get_records_sql($sql, $params);
        $adminuser = get_admin();
        $notified = array();
        foreach ($newusers as $newuser) {
            $subject = get_string('newusernotifysubject', 'auth_campusconnect');
            $messagetext = get_string('newusernotifybody', 'auth_campusconnect', $newuser);
            $usernamesplit = explode('_', $newuser->username);
            if (!isset($usernamesplit[1]) || substr($usernamesplit[1], 0, 3) != 'ecs') {
                mtrace(get_string('usernamecantfindecs', 'auth_campusconnect'). ': ' . $newuser->username);
            }
            $ecsid = (int)str_replace('ecs', '', $usernamesplit[1]);
            if (!isset($ecslist[$ecsid])) {
                mtrace(get_string('usernamecantfindecs', 'auth_campusconnect'). ': ' . $newuser->username);
            }
            if (!isset($notified[$ecsid])) {
                list($in, $params) = $DB->get_in_or_equal($ecsemails[$ecsid]);
                $notified[$ecsid] = $DB->get_records_select('user', "username $in", $params);
            }
            foreach ($notified[$ecsid] as $recepient) {
                email_to_user($recepient, $adminuser, $subject, $messagetext);
            }
        }

        set_config('lastnewusersemailsent', $sendupto, 'auth_campusconnect');

        return true;
    }

    //Local functions

    /*
     * Generate Moodle username from an array of query parameters and ECS id
     * @param string $uidhash - an 'ecs_uid_hash' from the url params
     * @param int $ecsid - the ECS that authenticated the user
     */
    private function username_from_params($username, $uidhash, $ecsid) {
        global $DB;

        // See if we already know about this user.
        if ($ecsuser = $DB->get_record('auth_campusconnect', array('ecsid' => $ecsid, 'ecs_uid' => $uidhash))) {
            return $ecsuser->username; // User has previously authenticated here - just return their previous username.
        }

        // Generate a new username for this user.
        $prefix = 'campusconnect_ecs'.$ecsid.'_';
        $username = $prefix.$username;

        // Make sure the username is unique.
        $i = 1;
        $finalusername = $username;
        while ($DB->record_exists('user', array('username' => $finalusername))) {
            $finalusername = $username.($i++);
        }

        // Record the username for future reference.
        $ins = new stdClass();
        $ins->ecsid = $ecsid;
        $ins->ecs_uid = $uidhash;
        $ins->username = $finalusername;
        $ins->id = $DB->insert_record('auth_campusconnect', $ins);

        // Return the generated username.
        return $finalusername;
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

    protected static function log($msg) {
        global $CFG;

        if ($CFG->debug == DEBUG_DEVELOPER) {
            require_once($CFG->dirroot.'/local/campusconnect/log.php');
            campusconnect_log::add($msg, false, false);
        }
    }

    protected static function log_print_r($obj) {
        global $CFG;

        if ($CFG->debug == DEBUG_DEVELOPER) {
            require_once($CFG->dirroot.'/local/campusconnect/log.php');
            campusconnect_log::add_object($obj, false, false);
        }
    }
}