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

require_once($CFG->dirroot.'/local/campusconnect/ecssettings.php');
require_once($CFG->dirroot.'/local/campusconnect/connect.php');

class campusconnect_participantsettings {

    const IMPORT_LINK = 1;
    const IMPORT_COURSE = 2;
    const IMPORT_CMS = 3;

    // Settings saved locally in the database.
    protected $recordid = null;
    protected $ecsid = null;
    protected $mid = null;
    protected $export = false;
    protected $import = false;
    protected $importtype = self::IMPORT_LINK;

    // Settings loaded from the ECS server.
    protected $name = null;
    protected $description = null;
    protected $dns = null;
    protected $email = null;
    protected $org = null;
    protected $orgabbr = null;

    protected static $validsettings = array('export', 'import', 'importtype');
    protected static $ecssettings = array('name', 'description', 'dns', 'email', 'org', 'orgabbr');

    public function __construct($ecsid, $mid, $extradetails = null) {
        $this->load_settings($ecsid, $mid);

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
        }
    }

    public function get_mid() {
        return $this->mid;
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
        }
    }

    protected function set_settings($settings) {
        foreach (self::$validsettings as $setting) {
            if (isset($settings->$setting)) {
                $this->$setting = $settings->$setting;
            }
        }
        if (isset($settings->id)) {
            $this->recordid = $settings->id;
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
    }

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
            foreach ($community->participants as $participant) {
                $mid = $participant->mid;
                $part = new campusconnect_participantsettings($ecsid, $mid, $participant);
                $comm->participants[$mid] = $part;
            }

            $resp[$community->community->cid] = $comm;
        }

        return $resp;
    }

    public static function delete_ecs_participant_settings($ecsid) {
        // Delete settings for all participants in the given ECS
        global $DB;

        $DB->delete_records('local_campusconnect_part', array('ecsid' => $ecsid));
    }
}