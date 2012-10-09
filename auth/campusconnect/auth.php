<?php

/**
 * @package    auth
 * @subpackage campusconnect
 * @copyright  2012 Synergy Learning
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); ///  It must be included from a Moodle page
}

/**
 * CampusConnect authentication plugin.
 */
class auth_plugin_campusconnect extends auth_plugin_base {

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
        global $SESSION, $CFG;

        if (!isset($SESSION) || !isset($SESSION->wantsurl)) {
            return;
        }
        $urlquery = parse_url($SESSION->wantsurl, PHP_URL_QUERY);
        if (empty($urlquery)) {
            return;
        }
        $urlquery = str_replace('&amp;', '&', $urlquery);
        $queryparams = explode('&', $urlquery);
        $paramassoc = array();
        foreach ($queryparams as $paramval) {
            $split = explode('=', $paramval);
            if (count($split)<2) {
                continue;
            }
            $paramassoc[$split[0]] = $split[1];
        }
        if (!isset($paramassoc['ecs_hash'])) {
            return;
        }
        $hash = $paramassoc['ecs_hash'];

        require_once($CFG->dirroot.'\local\campusconnect\connect.php');
        $connecterrors = false;
        $authenticated = false;
        $ecslist = campusconnect_ecssettings::list_ecs();
        foreach ($ecslist as $ecsid => $ecsname) {
            $settings = new campusconnect_ecssettings($ecsid);
            try {
                $connect = new campusconnect_connect($settings);
                $auth = $connect->get_auth($hash);
                if (is_object($auth) && isset($auth->abbr)) {
                    $authenticated = true;
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

        //We're now authenticated! Let's create/find the user:
    }

    /**
     * Logout - check if user is enrolled in any course, if not, delete
     *
     */
    function prelogout_hook() {
        global $CFG;
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

}