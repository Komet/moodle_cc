<?php
// This file is part of the CampusConnect plugin for Moodle - http://moodle.org/
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
 * Configuration settings for connecting to an ECS
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class campusconnect_ecssettings {

    const AUTH_NONE = 1; // Development only - direct connection to ECS server.
    const AUTH_HTTP = 2; // Basic HTTP authentication.
    const AUTH_CERTIFICATE = 3; // Certificate based authentication.

    protected $url = '';
    protected $auth = self::AUTH_CERTIFICATE;
    protected $ecsauth = '';
    protected $httpuser = '';
    protected $httppass = '';
    protected $cacertpath = '';
    protected $certpath = '';
    protected $keypath = '';
    protected $keypass = '';

    protected $recordid = null;
    protected $name = '';

    protected $validsettings = array('recordid' => 'id',
                                     'name' => 'name',
                                     'url' => 'url',
                                     'auth' => 'auth',
                                     'ecsauth' => 'ecsauth',
                                     'httpuser' => 'httpuser',
                                     'httppass' => 'httppass',
                                     'cacertpath' => 'cacertpath',
                                     'certpath' => 'certpath',
                                     'keypath' => 'keypath',
                                     'keypass' => 'keypass');

    /**
     * Initialise a settings object
     * @param int $ecsname optional - the ID of the ECS to load settings for
     * @param string $unittest optional - the ecsauth name to use for unit testing
     * @return void
     */
    function __construct($ecsid = null, $unittest = null) {

        // Create fake settings for unit testing
        static $unittestecs = array();
        if ($ecsid < 0) {
            $ecsid = -$ecsid;
            $unittest = $unittestecs[$ecsid];
        }
        if ($unittest) {
            if (is_null($ecsid)) {
                $ecsid = count($unittestecs) + 1;
                $unittestecs[$ecsid] = $unittest;
            }
            $this->url = 'http://localhost:3000';
            $this->auth = self::AUTH_NONE;
            $this->ecsauth = $unittest;
            $this->recordid = -$ecsid;
            return;
        }

        // Load the settings, if an ECS ID has been specified.
        if ($ecsid) {
            $this->load_settings($ecsid);
        }
    }

    public static function list_ecs() {
        global $DB;
        return $DB->get_records_menu('local_campusconnect_ecs', array(), 'name, id', 'id, name');
    }

    public function get_id() {
        return $this->recordid;
    }

    public function get_name() {
        return $this->name;
    }

    public function get_url() {
        return $this->url;
    }

    public function get_auth_type() {
        return $this->auth;
    }

    public function get_ecs_auth() {
        if ($this->get_auth_type() != self::AUTH_NONE) {
            throw new coding_exception('get_ecs_auth only valid when using no authentication');
        }
        return $this->ecsauth;
    }

    public function get_http_user() {
        if ($this->get_auth_type() != self::AUTH_HTTP) {
            throw new coding_exception('get_http_user only valid when using http authentication');
        }
        return $this->httpuser;
    }

    public function get_http_password() {
        if ($this->get_auth_type() != self::AUTH_HTTP) {
            throw new coding_exception('get_http_password only valid when using http authentication');
        }
        return $this->httppass;
    }

    public function get_ca_cert_path() {
        if ($this->get_auth_type() != self::AUTH_CERTIFICATE) {
            throw new coding_exception('get_ca_cert_path only valid when using certificate authentication');
        }
        return $this->cacertpath;
    }

    public function get_client_cert_path() {
        if ($this->get_auth_type() != self::AUTH_CERTIFICATE) {
            throw new coding_exception('get_client_cert_path only valid when using certificate authentication');
        }
        return $this->certpath;
    }

    public function get_key_path() {
        if ($this->get_auth_type() != self::AUTH_CERTIFICATE) {
            throw new coding_exception('get_key_path only valid when using certificate authentication');
        }
        return $this->keypath;
    }

    public function get_key_pass() {
        if ($this->get_auth_type() != self::AUTH_CERTIFICATE) {
            throw new coding_exception('get_key_pass only valid when using certificate authentication');
        }
        return $this->keypass;
    }

    protected function load_settings($ecsid) {
        global $DB;

        $settings = $DB->get_record('local_campusconnect_ecs', array('id' => $ecsid), '*', MUST_EXIST);
        $this->set_settings($settings);
    }

    protected function set_settings($settings) {
        foreach ($this->validsettings as $localname => $dbname) {
            if (isset($settings->$dbname)) {
                $this->$localname = $settings->$dbname;
            }
        }
    }

    public function save_settings($settings) {
        global $DB;

        $settings = (array)$settings; // Avoid updating passed-in objects
        $settings = (object)$settings;

        // Clean the settings - make sure only expected values exist.
        foreach ($settings as $setting => $value) {
            if (!array_key_exists($setting, $this->validsettings)) {
                unset($settings->$setting);
            }
        }

        // Check the settings are valid.
        if (empty($settings->url) && empty($this->url)) {
            throw new coding_exception("campusconnect_ecssettings - missing 'url' field");
        }

        if (isset($settings->auth)) {
            $auth = $settings->auth;
        } else {
            if (empty($this->auth)) {
                throw new coding_exception('campusconnect_ecssettings - missing \'auth\' field');
            }
            $auth = $this->auth;
        }

        switch ($auth) {
        case self::AUTH_NONE:
            if (empty($settings->ecsauth) && empty($this->ecsauth)) {
                throw new coding_exception('campusconnect_ecssettings - auth method \'AUTH_NONE\' requires an \'ecsauth\' value');
            }
            break;
        case self::AUTH_HTTP:
            $requiredfields = array('httpuser', 'httppass');
            foreach ($requiredfields as $required) {
                if (empty($settings->$required) && empty($this->$required)) {
                    throw new coding_exception("campusconnect_ecssettings - auth method 'AUTH_HTTP' requires a '$required' value");
                }
            }
            break;
        case self::AUTH_CERTIFICATE:
            $requiredfields = array('cacertpath', 'certpath', 'keypath', 'keypass');
            foreach ($requiredfields as $required) {
                if (empty($settings->$required) && empty($this->$required)) {
                    throw new coding_exception("campusconnect_ecssettings - auth method 'AUTH_CERTIFICATE' requires a '$required' value");
                }
            }
            break;
        default:
            throw new coding_exception('campusconnect_ecssettings - invalid \'auth\' value');
        }

        // Save the settings
        if (is_null($this->recordid)) {
            // Newly created ECS connection.
            $settings->id = $DB->insert_record('local_campusconnect_ecs', $settings);
        } else {
            $settings->id = $this->recordid;
            $DB->update_record('local_campusconnect_ecs', $settings);
        }

        // Update the local settings
        $this->set_settings($settings);
    }

    public function get_settings() {
        $ret = new stdClass();
        foreach ($this->validsettings as $localname => $dbname) {
            $ret->$localname = $this->$localname;
        }
        return $ret;
    }

    public function delete() {
        global $DB;

        if (!is_null($this->recordid)) {
            $DB->delete_records('local_campusconnect_ecs', array('id' => $this->recordid));
            $this->recordid = null;
            $this->auth = -1;
        }
    }
}