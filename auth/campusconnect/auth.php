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
        require_once($CFG->dirroot.'/local/campusconnect/enrolment.php');

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

            list($authenticatingecs, $participantid, $connecterrors) =
                self::check_authentication($baseurl, $hash, $courseurl, $paramassoc);

        } else {

            // Fall back to the legacy 'ecs_hash' param
            if (empty($paramassoc['ecs_hash'])) {
                self::log("Neither ecs_hash nor ecs_hash_url included in destination URL");
                return;
            }
            $hash = $paramassoc['ecs_hash'];
            self::log("ecs_hash found: {$hash}");

            list($authenticatingecs, $participantid, $connecterrors) =
                self::check_authentication(null, $hash, $courseurl, $paramassoc);
        }

        //Throw an error only if a connection exception was thrown and the user wasn't authenticated by any other ECS
        if (!$authenticatingecs) {
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
        $username = $this->username_from_params($paramassoc['ecs_institution'], $paramassoc['ecs_login'], $uidhash,
                                                campusconnect_enrolment::PERSON_UID, $authenticatingecs, $participantid);
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
     * Check against each ECS to see if any of them can authenticate the user.
     *
     * @param string|null $baseurl
     * @param string $hash
     * @param string $courseurl
     * @param array $paramassoc
     * @return array [$authenticatingecs, $pid, $connecterrors] - $authenticatingecs is null if not authenticated.
     */
    protected static function check_authentication($baseurl, $hash, $courseurl, $paramassoc) {
        $authenticatingecs = null;
        $participantid = null;
        $connecterrors = false;

        $ecslist = campusconnect_ecssettings::list_ecs();
        foreach ($ecslist as $ecsid => $ecsname) {
            $settings = new campusconnect_ecssettings($ecsid);
            if ($baseurl) {
                self::log("Comparing hash URL: {$baseurl} with ECS server '$ecsname' ($ecsid): ".$settings->get_url());
            } else {
                self::log("Attempting to authenticate hash against '{$ecsname}' ($ecsid)");
            }
            if (!$baseurl || self::strip_port($settings->get_url()) == self::strip_port($baseurl)) {
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
                        $now = time();
                        if (isset($auth->sov)) {
                            $sov = strtotime($auth->sov);
                            if ($sov && $sov > $now) {
                                self::log("Start of validation timestamp ({$auth->sov} = {$sov}) is after the current time ({$now})");
                                continue;
                            }
                        }
                        if (isset($auth->eov)) {
                            $eov = strtotime($auth->eov);
                            if ($eov && $eov < $now) {
                                self::log("End of validation timestamp ({$auth->eov} = {$eov}) is before the current time ({$now})");
                                continue;
                            }
                        }
                        if (!self::use_authentication_token($ecsid, $auth->pid, $paramassoc['id'])) {
                            $connecterrors = false;
                            self::log("Authentication token is valid, but is from a participant we are not accepting tokens from");
                            break; // Do not check against any other ECS.
                        }
                        $participantid = $auth->pid;
                        $authenticatingecs = $ecsid;
                        break;
                    }
                } catch (campusconnect_connect_exception $e) {
                    $connecterrors = true;
                }
            }
        }

        return array($authenticatingecs, $participantid, $connecterrors);
    }

    protected static function use_authentication_token($ecsid, $pid, $courseid) {
        global $CFG;
        require_once($CFG->dirroot.'/local/campusconnect/participantsettings.php');
        require_once($CFG->dirroot.'/local/campusconnect/export.php');
        // Check the participant settings to see if we should be handling tokens from this participant.
        $mids = campusconnect_participantsettings::get_mids_from_pid($ecsid, $pid);
        $export = new campusconnect_export($courseid);
        foreach ($mids as $mid) {
            if ($export->should_handle_auth_token($ecsid, $mid)) {
                return true; // We are accepting authentication tokens from this participant.
            }
        }

        // Ignore the token, as we're not handling authentication from that participant.
        return false;
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
        global $USER, $DB, $CFG;
        if ($USER->auth != $this->authtype) {
            return;
        }

        // Am I currently enrolled?
        if(!empty($USER->enrol['enrolled'])) {
            return;
        }

        if (!$authrecord = $DB->get_record('auth_campusconnect', array('username' => $USER->username))) {
            require_once($CFG->dirroot.'/local/campusconnect/log.php');
            campusconnect_log::add("auth_campusconnect - user '{$USER->username}' missing record in auth_campusconnect database table");
            return; // Should really exist - log this and move on.
        }

        // Currently not enrolled - have I ever enrolled in anything?
        if ($authrecord->lastenroled) {
            return;
        }

        // OK, delete:
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

    /**
     * Cron - delete users who timed out and never enrolled,
     * Inactivate users who haven't been active for some time
     * And notify relevant users about users created
     */
    function cron() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/campusconnect/connect.php');

        // Find users whose session should have expired by now and haven't ever enroled in a course.
        $params = array(
            'minaccess' => time() - $CFG->sessiontimeout,
        );
        $sql = "
        SELECT u.id, u.username
          FROM {user} u
          JOIN {auth_campusconnect} ac ON ac.username = u.username
         WHERE u.deleted = 0 AND u.lastaccess < :minaccess
           AND ac.lastenroled IS NULL
        ";
        $deleteusers = $DB->get_records_sql($sql, $params);
        foreach ($deleteusers as $deleteuser) {
            mtrace(get_string('deletinguser', 'auth_campusconnect'). ': '. $deleteuser->id);
            $this->user_dataprotect_delete($deleteuser);
        }

        // Make users who haven't enrolled in a long time inactive.
        $ecslist = campusconnect_ecssettings::list_ecs();
        $ecsemails = array(); // We'll need it for later.
        foreach ($ecslist as $ecsid => $ecsname) {
            // Get the activation period.
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
                     WHERE u.suspended = 0 AND u.deleted = 0 AND ac.lastenroled IS NOT NULL AND ac.lastenroled < :cutoff
                   ";
            $params = array('ecsid' => $ecsid, 'cutoff' => $cutoff);
            $userids = $DB->get_fieldset_sql($sql, $params);
            if (!empty($userids)) {
                list($usql, $params) = $DB->get_in_or_equal($userids);
                $DB->execute("UPDATE {user}
                                 SET suspended = 1
                               WHERE id $usql", $params);
                // Trigger an event for all users.
                foreach ($DB->get_recordset_list('user', 'id', $userids) as $user) {
                    session_kill_user($user->id); // Just in case the user is currently logged in.
                    events_trigger('user_updated', $user);
                }
            }

            //For later:
            $ecsemails[$ecsid] = $settings->get_notify_users();
        }

        // Notify relevant users about new accounts.
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
        SELECT u.*, ac.ecsid
          FROM {user} u
          JOIN {auth_campusconnect} ac ON ac.username = u.username
         WHERE deleted = 0
           AND u.timecreated > :lastsent
           AND u.timecreated <= :sendupto
        ";
        $newusers = $DB->get_records_sql($sql, $params);
        $adminuser = get_admin();
        $notified = array();
        foreach ($newusers as $newuser) {
            $subject = get_string('newusernotifysubject', 'auth_campusconnect');
            $messagetext = get_string('newusernotifybody', 'auth_campusconnect', $newuser);
            $ecsid = $newuser->ecsid;
            if (!isset($ecslist[$ecsid])) {
                mtrace(get_string('usernamecantfindecs', 'auth_campusconnect'). ': ' . $newuser->username);
                continue;
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

    /**
     * Generate Moodle username from an array of query parameters and ECS id
     * @param string $institution
     * @param string $username
     * @param string $personid - the unique identifier from the URL params
     * @param string $personidtype - the type of unique identifier used
     * @param int $ecsid - the ECS that authenticated the user
     * @param int $pid - the ID of the participant the user came from
     * @return string
     */
    private function username_from_params($institution, $username, $personid, $personidtype, $ecsid, $pid) {
        global $DB;

        // See if we already know about this user.
        if ($ecsuser = $DB->get_record('auth_campusconnect', array('personid' => $personid, 'personidtype' => $personidtype))) {
            if ($pid && $ecsuser->pid != $pid) {
                // Update an old record that doesn't contain the user's PID.
                $DB->set_field('auth_campusconnect', 'pid', $pid, array('id' => $ecsuser->id));
            }
            return $ecsuser->username; // User has previously authenticated here - just return their previous username.
        }

        // Generate a new username for this user.
        $prefix = $institution.'_';
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
        $ins->pid = $pid;
        $ins->personid = $personid;
        $ins->personidtype = $personidtype;
        $ins->username = $finalusername;
        $ins->id = $DB->insert_record('auth_campusconnect', $ins);

        // Return the generated username.
        return $finalusername;
    }

    /**
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

        // Set to deleted.
        delete_user($user);

        // Delete logs.
        $DB->delete_records('log', array('userid' => $user->id));
    }

    protected static function log($msg) {
        global $CFG;

        require_once($CFG->dirroot.'/local/campusconnect/log.php');
        campusconnect_log::add($msg, true, false, false);
    }

    protected static function log_print_r($obj) {
        global $CFG;

        require_once($CFG->dirroot.'/local/campusconnect/log.php');
        campusconnect_log::add_object($obj, true, false);
    }
}