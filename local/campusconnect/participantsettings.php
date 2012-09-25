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
 * Represents a participant (VLE/CMS) in an ECS community.
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/local/campusconnect/ecssettings.php');
require_once($CFG->dirroot.'/local/campusconnect/connect.php');

class campusconnect_participantsettings {

    const IMPORT_LINK = 1;
    const IMPORT_COURSE = 2;
    const IMPORT_CMS = 3;

    // Settings saved locally in the database.
    protected $recordid = null;
    protected $ecsid = null;
    /** @var int $mid */
    protected $mid = null;
    protected $export = false;
    protected $import = false;
    protected $importtype = self::IMPORT_LINK;

    protected $displayname = null; // Constructed from the community name + part name

    // Settings loaded from the ECS server.
    protected $name = null;
    protected $communityname = null;
    protected $description = null;
    protected $dns = null;
    protected $email = null;
    protected $org = null;
    protected $orgabbr = null;
    protected $itsyou = null;

    // Flagged as being exported in the current course.
    protected $exported = null;

    protected static $validsettings = array('export', 'import', 'importtype');
    protected static $ecssettings = array('name', 'description', 'dns', 'email', 'org', 'orgabbr', 'communityname', 'itsyou');

    /**
     * @param mixed $ecsidordata either the ID of the ECS or an object containing
     *                           the settings record loaded from the database
     * @param int $mid optional the participant ID (required if the ECS ID is provided)
     * @param object $extradetails details about the participant loaded from the ECS
     */
    public function __construct($ecsidordata, $mid = null, $extradetails = null) {
        if (is_object($ecsidordata)) {
            // Data already loaded from database - store it.
            $this->set_settings($ecsidordata);
        } else {
            if (is_null($mid)) {
                throw new coding_exception("Must set the participant id (mid) if not passing in the database record");
            }
            $this->load_settings($ecsidordata, $mid);
        }

        if (isset($extradetails)) {
            foreach ($extradetails as $name => $value) {
                if ($name == 'org') {
                    $this->org = $value->name;
                    $this->orgabbr = $value->abbr;
                    continue;
                }
                if (in_array($name, self::$ecssettings)) {
                    $this->$name = $value;
                }
            }

            $this->set_display_name();
        }
    }

    public function get_ecs_id() {
        return $this->ecsid;
    }

    public function get_mid() {
        return $this->mid;
    }

    public function get_identifier() {
        return "{$this->ecsid}_{$this->mid}";
    }

    public function is_export_enabled() {
        return $this->export;
    }

    public function is_import_enabled() {
        return $this->import;
    }

    public function get_import_type() {
        return $this->importtype;
    }

    public function get_displayname() {
        return $this->displayname;
    }

    public function get_name() {
        return $this->name;
    }

    public function get_description() {
        return $this->description;
    }

    public function get_domain() {
        return $this->dns;
    }

    public function get_email() {
        return $this->email;
    }

    public function get_organisation() {
        return $this->org;
    }

    public function get_organisation_abbr() {
        return $this->orgabbr;
    }

    public function is_me() {
        return ($this->itsyou == true);
    }

    public function is_exported() {
        if (is_null($this->exported)) {
            throw new coding_exception('is_exported can only be called after set_exported has been called (usually via campusconnect_export)');
        }
        return $this->exported;
    }

    public function show_exported($exported) {
        $this->exported = $exported;
    }

    protected function load_settings($ecsid, $mid) {
        global $DB;

        $settings = $DB->get_record('local_campusconnect_part', array('mid' => $mid,
                                                                      'ecsid' => $ecsid));
        if ($settings) {
            $this->set_settings($settings);
        } else {
            $ins = new stdClass();
            $this->mid = $ins->mid = $mid;
            $this->ecsid = $ins->ecsid = $ecsid;
            foreach (self::$validsettings as $setting) {
                $ins->$setting = $this->$setting; // Set all the defaults from this class.
            }
            $this->recordid = $DB->insert_record('local_campusconnect_part', $ins);
        }
    }

    /**
     * Set the display name for this participant (and save it in
     * the database, so it can be shown later, without needing to
     * connect to the ECS)
     */
    protected function set_display_name() {
        global $DB;

        if (empty($this->name)) {
            return;
        }
        $displayname = $this->name;
        if (!empty($this->communityname)) {
            $displayname = $this->communityname.': '.$displayname;
        }

        if ($displayname != $this->displayname) {
            $this->displayname = $displayname;
            $upd = new stdClass();
            $upd->id = $this->recordid;
            $upd->displayname = $displayname;
            $DB->update_record('local_campusconnect_part', $upd);
        }
    }

    public function save_settings($settings) {
        global $DB;

        $settings = (array)$settings; // Avoid updating passed-in objects
        $settings = (object)$settings;

        // Check to see if anything has changed and all settings are valid.
        if ($this->export) {
            if (isset($settings->export) && !$settings->export) {
                $settings->export = false;
            } else {
                unset($settings->export);
            }
        } else {
            if (!empty($settings->export)) {
                $settings->export = true;
            } else {
                unset($settings->export);
            }
        }

        if ($this->import) {
            if (isset($settings->import) && !$settings->import) {
                $settings->import = false;
            } else {
                unset($settings->import);
            }
        } else {
            if (!empty($settings->import)) {
                $settings->import = true;
            } else {
                unset($settings->import);
            }
        }

        if (isset($settings->importtype)) {
            $validimporttypes = array(self::IMPORT_LINK, self::IMPORT_COURSE, self::IMPORT_CMS);
            if (!in_array($settings->importtype, $validimporttypes)) {
                throw new coding_exception("Invalid importtype: $settings->importtype");
            }
            if ($settings->importtype == $this->importtype) {
                unset($settings->importtype);
            }
        }

        // Clean the settings - make sure only expected values exist.
        $updateneeded = false;
        foreach ($settings as $name => $value) {
            if (in_array($name, self::$validsettings)) {
                $updateneeded = true;
            } else {
                unset($settings->$name);
            }
        }

        if ($updateneeded) {
            $settings->id = $this->recordid;
            $DB->update_record('local_campusconnect_part', $settings);
            $this->set_settings($settings);

            // Import state changed - need to update all course links
            if (isset($settings->import)) {
                if ($settings->import) {
                    campusconnect_courselink::refresh_from_participant($this->ecsid, $this->mid);
                } else {
                    // No longer importing course links
                    campusconnect_courselink::delete_mid_courselinks($this->mid);
                }
            }

            if (isset($settings->export)) {
                if ($settings->export) {
                    // Nothing to do here - will be updated at next cron.
                } else {
                    campusconnect_export::delete_mid_exports($this);
                }
            }
        }
    }

    protected function set_settings($settings) {
        foreach (self::$validsettings as $setting) {
            if (isset($settings->$setting)) {
                $this->$setting = $settings->$setting;
            }
        }
        if (isset($settings->id)) {
            // The settings came from the database.
            $this->recordid = $settings->id;
            $dbsettings = array('mid', 'ecsid', 'displayname');
            foreach ($dbsettings as $fieldname) {
                if (isset($settings->$fieldname)) {
                    $this->$fieldname = $settings->$fieldname;
                }
            }
        }
    }

    public function get_settings() {
        $ret = new stdClass();
        foreach (self::$validsettings as $setting) {
            $ret->$setting = $this->$setting;
        }
        foreach (self::$ecssettings as $setting) {
            $ret->$setting = $this->$setting;
        }
        return $ret;
    }

    public function delete_settings() {
        global $DB;

        $DB->delete_records('local_campusconnect_part', array('id' => $this->recordid));
        campusconnect_courselink::delete_mid_courselinks($this->mid);
    }

    /**
     * Get a list of all the participants in all the ECS that we are able to
     * export courses to
     * @return campusconnect_participantsettings[] indexed by ecsid_mid
     */
    public static function list_potential_export_participants() {
        global $DB;
        $parts = $DB->get_records('local_campusconnect_part', array('export' => 1), 'displayname');
        $ret = array();
        foreach ($parts as $part) {
            $participant = new campusconnect_participantsettings($part);
            $ret[$participant->get_identifier()] = $participant;
        }
        return $ret;
    }

    /**
     * Load all the communities we are a member of (including participant lists) from
     * the given ECS
     * @param campusconnect_ecssettings $ecssettings - the ECS to connect to
     * @return array details of the communities
     */
    public static function load_communities(campusconnect_ecssettings $ecssettings) {
        $connect = new campusconnect_connect($ecssettings);
        $communities = $connect->get_memberships();
        $ecsid = $ecssettings->get_id();

        $resp = array();
        foreach ($communities as $community) {
            $comm = new stdClass();
            $comm->name = $community->community->name;
            $comm->description = $community->community->description;
            $comm->participants = array();
            $comm->ecsid = $ecsid;
            foreach ($community->participants as $participant) {
                $mid = $participant->mid;
                $participant->communityname = $comm->name;
                $part = new campusconnect_participantsettings($ecsid, $mid, $participant);
                $comm->participants[$part->get_identifier()] = $part;
            }

            $resp[$community->community->cid] = $comm;
        }

        return $resp;
    }

    /**
     * Delete the settings for the participants in this ECS (also deletes
     * and course links created by these participants)
     * @param int $ecsid
     */
    public static function delete_ecs_participant_settings($ecsid) {
        global $DB;

        $parts = $DB->get_records('local_campusconnect_part', array('ecsid' => $ecsid));
        foreach ($parts as $participant) {
            campusconnect_courselink::delete_mid_courselinks($participant->mid);
        }
        $DB->delete_records('local_campusconnect_part', array('ecsid' => $ecsid));
    }

    /**
     * Returns the participant that has import type CMS
     * @return mixed campusconnect_participantsettings | false
     */
    public static function get_cms_participant() {
        global $DB;

        $participant = $DB->get_records('local_campusconnect_part', array('import' => 1, 'importtype' => self::IMPORT_CMS));
        if (count($participant) > 1) {
            throw new coding_exception('There should only ever be one participant set to IMPORT_CMS');
        }

        $participant = reset($participant);
        if ($participant) {
            return new campusconnect_participantsettings($participant);
        }

        return false;
    }
}