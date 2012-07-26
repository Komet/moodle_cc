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
 * Main connection class for CampusConnect
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/campusconnect/ecssettings.php');
require_once($CFG->dirroot.'/local/campusconnect/urilist.php');

class campusconnect_connect_exception extends moodle_exception {
    function __construct($msg) {
        parent::__construct('error', 'local_campusconnect', '', $msg);
        $this->email_admin($msg);
    }

    function email_admin($msg) {
        // TODO - implement this function
        // May need to consider gathering the errors into a log and only sending emails at most once an hour?
    }
}

class campusconnect_connect {

    /** The curl connection currently being prepared **/
    protected $curlresource = null;
    /** The headers to send in the next request **/
    protected $headers = array();
    /** The settings for connecting to the server **/
    protected $settings = null;
    /** The response headers from the last request **/
    protected $responseheaders = array();

    /** HTTP response codes **/
    const HTTP_CODE_OK = 200;
    const HTTP_CODE_CREATED = 201;
    const HTTP_CODE_NOT_FOUND = 404;

    /**
     * Construct a new connection
     * @param campusconnect_ecssettings $settings - the settings for connecting to the ECS server
     */
    public function __construct(campusconnect_ecssettings $settings) {
        $this->settings = $settings;
    }

    /**
     * Get the ID of the ECS this is connected to
     * @return int the ECSID (the ID of the record in 'local_campusconnect_ecs')
     */
    public function get_ecs_id() {
        return $this->settings->get_id();
    }

    /**
     * Get the ECS settings object
     * @return campusconnect_ecssettings
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Get the category to put new courses into
     * @return int the categoryid
     */
    public function get_import_category() {
        return $this->settings->get_import_category();
    }

    /**
     * Generate an auth token for a user
     * @param mixed $post the details of the URL the user is connecting to
     * @param int $targetmid the id of the participant the user is connecting to
     * @return string the hash value to append to the url parameters
     */
    public function add_auth($post, $targetmid) {
        if (!is_object($post)) {
            throw new campusconnect_connect_exception('add_auth - expected \'post\' to be an object');
        }

        $poststr = json_encode($post);
        $this->init_connection('/sys/auths');
        $this->set_memberships($targetmid);
        $this->set_postfields($poststr);

        $result = $this->call();
        if (!$this->check_status(self::HTTP_CODE_CREATED)) {
            throw new campusconnect_connect_exception('add_auth - bad response: '.$this->get_status());
        }

        $result = $this->parse_json($result);
        return $result->hash;
    }

    /**
     * Check an auth token
     * @param hash the hash value retrieved from the url parameters
     * @return object the authentication details for this connection
     */
    public function get_auth($hash) {
        if (empty($hash)) {
            throw new campusconnect_connect_exception('get_auth - no auth hash given');
        }
        $this->init_connection('/sys/auths/'.$hash);

        $result = $this->call();
        if (!$this->check_status(self::HTTP_CODE_OK)) {
            throw new campusconnect_connect_exception('get_auth - bad response: '.$this->get_status());
        }

        return $this->parse_json($result);
    }

    /**
     * Get a list of all the event queues (not supported by ECS server?)
     * @return object list of queues
     */
    public function get_event_queues() {
        $this->init_connection('/eventqueues');

        $result = $this->call();
        if (!$this->check_status(self::HTTP_CODE_OK)) {
            throw new campusconnect_connect_exception('get_event_queues - bad response: '.$this->get_status());
        }

        return $this->parse_json($result);
    }

    /**
     * Get the next event for this participant
     * @param bool $delete optional - true to delete the message once read
     * @return object the details of the event
     */
    public function read_event_fifo($delete = false) {
        $this->init_connection('/sys/events/fifo');
        if ($delete) {
            $this->set_postfields('');
        }

        $result = $this->call();
        if (!$this->check_status(self::HTTP_CODE_OK)) {
            throw new campusconnect_connect_exception('read_event_fifo - bad response: '.$this->get_status());
        }

        return $this->parse_json($result);
    }

    /**
     * Get a list of available resources on the remote VLEs
     * @param str $type the type of resource to load (see campusconnect_event for list)
     * @return array of links to get further details about each resource
     */
    public function get_resource_list($type) {
        if (!campusconnect_event::is_valid_resource($type)) {
            throw new coding_error("get_resource_list: unknown resource type $type");
        }
        $this->init_connection('/'.$type);

        $result = $this->call();
        if (!$this->check_status(self::HTTP_CODE_OK)) {
            throw new campusconnect_connect_exception('get_resource_list - bad response: '.$this->get_status());
        }

        return $this->parse_uri_list($result);
    }

    /**
     * Get an individual resource
     * @param int $id of the resource to retrieve
     * @param str $type the type of resource to load (see campusconnect_event for list)
     * @param bool $detailsonly optional - if true then retrieves the delivery
     *                           details for the resource, false for the contents
     * @return mix object | false the details retrieved
     */
    public function get_resource($id, $type, $detailsonly = false) {
        if (!campusconnect_event::is_valid_resource($type)) {
            throw new coding_error("get_resource: unknown resource type $type");
        }
        $resourcepath = '/'.$type;
        if ($id) {
            $resourcepath .= "/$id";
        }
        if ($detailsonly) {
            $resourcepath .= '/details';
        }

        $this->init_connection($resourcepath);

        $result = $this->call();
        if (!$this->check_status(self::HTTP_CODE_OK)) {
            //throw new campusconnect_connect_exception('get_resource - bad response: '.$this->get_status()." ($resourcepath)");
            return false; // Resource does not exist on the server.
        }

        return $this->parse_json($result);
    }

    /**
     * Add a resource that other VLEs can retrieve
     * @param str $type the type of resource to load (see campusconnect_event for list)
     * @param object $post the details of the resource to create
     * @param string $targetcommunityids a comma-separated list of community IDs that have access to this resource
     * @param string $targetmids a comma-separated list of participant IDs that have access to this resource
     * @result int the id that this resource has been allocated on the ECS
     */
    public function add_resource($type, $post, $targetcommunityids = null, $targetmids = null) {
        if (!campusconnect_event::is_valid_resource($type)) {
            throw new coding_error("add_resource: unknown resource type $type");
        }
        if (!is_object($post)) {
            throw new coding_exception('add_resource - expected \'post\' to be an object');
        }
        if (is_null($targetmids) && is_null($targetcommunityids)) {
            throw new coding_exception('add_resource - must specify either \'targetmids\' or \'targetcommunityids\'');
        } else if (!is_null($targetmids) && !is_null($targetcommunityids)) {
            throw new coding_exception('add_resource - cannot specify both \'targetmids\' and \'targetcommunityids\'');
        }

        $poststr = json_encode($post);
        $this->init_connection('/'.$type);
        $this->set_postfields($poststr);
        $this->include_response_header();
        if (!is_null($targetmids)) {
            $this->set_memberships($targetmids);
        } else {
            $this->set_communities($targetcommunityids);
        }

        $result = $this->call();
        if (!$this->check_status(self::HTTP_CODE_CREATED)) {
            throw new campusconnect_connect_exception('add_resource - bad response: '.$this->get_status());
        }

        return $this->get_econtentid_from_header();
    }

    /**
     * Update a previously shared resource
     * @param int $id the id allocated when the resource was first posted
     * @param str $type the type of resource to load (see campusconnect_event for list)
     * @param object $post the new details
     * @param string $targetcommunityids a comma-separated list of community IDs that have access to this resource
     * @param string $targetmids a comma-separated list of participant IDs that have access to this resource
     * @return object the response from the ECS server
     */
    public function update_resource($id, $type, $post, $targetcommunityids = null, $targetmids = null) {
        if (!campusconnect_event::is_valid_resource($type)) {
            throw new coding_error("update_resource: unknown resource type $type");
        }
        if (!is_object($post)) {
            throw new coding_exception('add_resource - expected \'post\' to be an object');
        }
        if (!$id) {
            throw new campusconnect_connect_exception('update_resource - no resource id given');
        }
        if (is_null($targetmids) && is_null($targetcommunityids)) {
            throw new coding_exception('update_resource - must specify either \'targetmids\' or \'targetcommunityids\'');
        } else if (!is_null($targetmids) && !is_null($targetcommunityids)) {
            throw new coding_exception('update_resource - cannot specify both \'targetmids\' and \'targetcommunityids\'');
        }

        $this->init_connection("/$type/$id");
        if (!is_null($targetmids)) {
            $this->set_memberships($targetmids);
        } else {
            $this->set_communities($targetcommunityids);
        }

        // Create a temporary file in memory
        if (!$fp = fopen('php://temp', 'w+')) {
            throw new campusconnect_connect_exception('update_resource - unable to create temporary file');
        }
        $poststr = json_encode($post);
        fwrite($fp, $poststr);
        fseek($fp, 0);
        $this->set_putfile($fp, strlen($poststr));
        $result = $this->call();
        fclose($fp);

        if (!$this->check_status(self::HTTP_CODE_OK)) {
            throw new campusconnect_connect_exception('update_resource - bad response: '.$this->get_status());
        }

        return $this->parse_json($result);
    }

    /**
     * Delete a previously shared resource
     * @param int $id the id allocated when the resource was first posted
     * @param str $type the type of resource to load (see campusconnect_event for list)
     * @return object the response from the server
     */
    public function delete_resource($id, $type) {
        if (!campusconnect_event::is_valid_resource($type)) {
            throw new coding_error("delete_resource: unknown resource type $type");
        }
        if (!$id) {
            throw new campusconnect_connect_exception('delete_resource - no resource id given');
        }
        $this->init_connection("/$type/$id");
        $this->set_delete();

        $result = $this->call();
        return $this->parse_json($result);
    }

    /**
     * Get the details of the communities this VLE is a member of
     * @param int $mid optional the id of a specific community to retrieve?
     * @return object the details returned by the ECS server
     */
    public function get_memberships($mid = 0) {
        $resourcepath = '/sys/memberships';
        if ($mid) {
            $resourcepath .= "/$id";
        }
        $this->init_connection($resourcepath);

        $result = $this->call();
        if (!$this->check_status(self::HTTP_CODE_OK)) {
            throw new campusconnect_connect_exception('get_memberships - bad response: '.$this->get_status());
        }

        return $this->parse_json($result);
    }

    /**
     * Internal functions to check / parse the results
     */

    /**
     * Interpret the ECS server response as JSON
     * @param string $result the response from the ECS server
     * @return object the interpreted response
     */
    protected function parse_json($result) {
        return json_decode($result);
    }

    /**
     * Interpret the ECS server response as a list of URIs
     * @param string $result the response from the ECS server
     * @return campusconnect_uri_list list of URIs
     */
    protected function parse_uri_list($result) {
        $uris = new campusconnect_uri_list();
        $lines = explode("\n", $result);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            $id = array_pop(explode("/", $line));
            $uris->add($line, $id);
        }
        return $uris;
    }

    /**
     * Check to see if the HTTP status of the request matched the given value
     * @param int $checkstatus the status to check against
     * @return bool - true if the status matched
     */
    protected function check_status($checkstatus) {
        return ($checkstatus == $this->get_status());
    }

    /**
     * Get the HTTP status of the request
     * @return int HTTP status code
     */
    protected function get_status() {
        return curl_getinfo($this->curlresource, CURLINFO_HTTP_CODE);
    }

    /**
     * Retrieve the assigned 'econtentid' from the response header
     * @return int the id
     */
    protected function get_econtentid_from_header() {
        $header = $this->responseheaders;
        if (!isset($header['Location'])) {
            return false;
        }
        $id = array_pop(explode('/', $header['Location']));
        return intval($id);
    }

    /**
     * Callback function to parse the response header into an array of
     * headername => headervalue
     * @param resource $handle the curl resource
     * @param string $header the raw text of the HTTP header
     */
    protected function parse_response_header($handle, $header) {
        $lines = explode("\r\n", $header);
        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) < 2) {
                continue;
            }
            list($name, $value) = $parts;
            $this->responseheaders[$name] = $value;
        }
        return strlen($header);
    }


    /**
     * Internal methods to simplify the use of curl
     */

    /**
     * Initialise the curl connection to a particular resource on the ECS server
     * @param string $resourcepath - the path to the desired resource on the server
     */
    protected function init_connection($resourcepath) {

        if (substr($resourcepath, 0, 1) != '/' || substr($resourcepath, -1) == '/') {
            throw new coding_exception('Resource path must start with \'/\' and not end with \'/\'');
        }
        $this->headers = array(); // Clear out any headers from previous calls.
        $this->curlresource = curl_init($this->settings->get_url().$resourcepath);

        // Set up standard options
        $this->set_option(CURLOPT_RETURNTRANSFER, 1);
        $this->set_option(CURLOPT_VERBOSE, 1);
        $this->set_option(CURLINFO_HEADER_OUT, 1);
        $this->set_header('Accept', 'application/json');
        $this->set_header('Content-Type', 'application/json');

        switch ($this->settings->get_auth_type()) {
        case campusconnect_ecssettings::AUTH_NONE:
            $this->set_header('X-EcsAuthId', $this->settings->get_ecs_auth());
            break;

        case campusconnect_ecssettings::AUTH_HTTP:
            $this->set_option(CURLOPT_SSL_VERIFYHOST, 0);
            $this->set_option(CURLOPT_SSL_VERIFYPEER, 0);
            $this->set_option(CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            $this->set_option(CURLOPT_USERPWD, $this->settings->get_http_user().':'.
                              $this->settings->get_http_password());
            break;

        case campusconnect_ecssettings::AUTH_CERTIFICATE:
            $this->set_option(CURLOPT_SSL_VERIFYHOST, 1);
            $this->set_option(CURLOPT_SSL_VERIFYPEER, 1);
            $this->set_option(CURLOPT_CAINFO, $this->settings->get_ca_cert_path());
            $this->set_option(CURLOPT_SSLCERT, $this->settings->get_client_cert_path());
            $this->set_option(CURLOPT_SSLKEY, $this->settings->get_key_path());
            $this->set_option(CURLOPT_SSLKEYPASSWD, $this->settings->get_key_pass());
            break;

        default:
            throw new coding_exception('Unknown auth type: '.$this->settings->get_auth_type());
            break;
        }
    }

    /**
     * Set the community participant(s) that this message is intended for
     * @param string $memberships comma-separated list of participant mids
     */
    protected function set_memberships($memberships) {
        $this->set_header('X-EcsReceiverMemberships', $memberships);
    }

    /**
     * Set the community(ies) that this message is intended for
     * @param string $communities comma-separated list of community ids
     */
    protected function set_communities($communities) {
        $this->set_header('X-EcsReceiverCommunities', $communities);
    }

    /**
     * Adds the post data to the request and sets the method to POST
     * @param mixed $post the parameters, either as urlencoded string or as
     *                    an array $key => $value
     */
    protected function set_postfields($post) {
        $this->set_option(CURLOPT_POST, true);
        $this->set_option(CURLOPT_POSTFIELDS, $post);
    }

    /**
     * Adds a file to the request and sets the method to PUT
     * @param filepointer $fp the file resource to read from
     * @param int $size the size of the file
     */
    protected function set_putfile($fp, $size) {
        $this->set_option(CURLOPT_PUT, true);
        $this->set_option(CURLOPT_UPLOAD, true);
        $this->set_option(CURLOPT_INFILE, $fp);
        $this->set_option(CURLOPT_INFILESIZE, $size);
    }

    /**
     * Set the request method to DELETE
     */
    protected function set_delete() {
        $this->set_option(CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    /**
     * Include the response header in the returned data
     */
    protected function include_response_header() {
        $this->set_option(CURLOPT_HEADER, true);
    }

    /**
     * Send the request to the server
     * @return string the result of the request
     */
    protected function call() {
        if (!$this->settings->is_enabled()) {
            throw new coding_exception('campusconnect_connect: call() - should not be attempting to connect to disabled ECS ('.$this->get_ecs_id().')');
        }

        $this->set_option(CURLOPT_HTTPHEADER, $this->get_headers());
        $this->set_option(CURLOPT_HEADERFUNCTION, array($this, 'parse_response_header'));
        $this->responseheaders = array();

        if (($res = curl_exec($this->curlresource)) === false) {
            throw new campusconnect_connect_exception('curl error: '.curl_error($this->curlresource).
                                                      ' ('.curl_errno($this->curlresource).')');
        }

        return $res;
    }

    /**
     * Add a header to the list of HTTP headers to be added when the curl call is made
     * @param string $name the name of the header
     * @param string $value the value of the header
     */
    protected function set_header($name, $value) {
        $this->headers[$name] = $value;
    }

    /**
     * Add a curl option to be used when the curl call is made
     * @param int $option the option to set
     * @param mixed $value the value of the option
     */
    protected function set_option($option, $value) {
        curl_setopt($this->curlresource, $option, $value);
    }

    /**
     * Generate an array of all requested HTTP headers (ready to add to the call)
     * @return array of headers
     */
    protected function get_headers() {
        if (empty($this->headers)) {
            return false;
        }

        $ret = array();
        foreach ($this->headers as $key => $val) {
            $ret[] = "$key: $val";
        }
        return $ret;
    }
}