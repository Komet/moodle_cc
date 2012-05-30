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

    const AUTH_NONE = 0; // Development only - direct connection to ECS server
    const AUTH_HTTP = 1; // Basic HTTP authentication
    const AUTH_CERTIFICATE = 2; // Certificate based authentication

    protected $url = 'http://localhost:3000';
    protected $auth = self::AUTH_NONE;
    protected $ecsauth = 'test';
    protected $httpuser = 'user';
    protected $httppass = 'pass';
    protected $cacertpath = '';
    protected $certpath = '';
    protected $keypath = '';
    protected $keypass = '';

    /**
     * Initialise a settings object
     * @param string $test optional - ecsauth name if being used for unit testing
     */
    function __construct($test = null) {
        if ($test) {
            $this->url = 'http://localhost:3000';
            $this->auth = self::AUTH_NONE;
            $this->ecsauth = $test;
        }
    }

    function get_url() {
        return $this->url;
    }

    function get_auth_type() {
        return $this->auth;
    }

    function get_ecs_auth() {
        if ($this->get_auth_type() != self::AUTH_NONE) {
            throw new coding_exception('get_ecs_auth only valid when using no authentication');
        }
        return $this->ecsauth;
    }

    function get_http_user() {
        if ($this->get_auth_type() != self::AUTH_HTTP) {
            throw new coding_exception('get_user only valid when using http authentication');
        }
        return $this->httpuser;
    }

    function get_http_password() {
        if ($this->get_auth_type() != self::AUTH_HTTP) {
            throw new coding_exception('get_password only valid when using http authentication');
        }
        return $this->httppass;
    }

    function get_ca_cert_path() {
        if ($this->get_auth_type() != self::AUTH_CERTIFICATE) {
            throw new coding_exception('get_ca_cert_path only valid when using certificate authentication');
        }
        return $this->cacertpath;
    }

    function get_client_cert_path() {
        if ($this->get_auth_type() != self::AUTH_CERTIFICATE) {
            throw new coding_exception('get_client_cert_path only valid when using certificate authentication');
        }
        return $this->certpath;
    }

    function get_key_path() {
        if ($this->get_auth_type() != self::AUTH_CERTIFICATE) {
            throw new coding_exception('get_key_path only valid when using certificate authentication');
        }
        return $this->keypath;
    }

    function get_key_pass() {
        if ($this->get_auth_type() != self::AUTH_CERTIFICATE) {
            throw new coding_exception('get_key_pass only valid when using certificate authentication');
        }
        return $this->keypass;
    }
}