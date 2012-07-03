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
 * Export courses to ECS server
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/campusconnect/connect.php');
require_once($CFG->dirroot.'/local/campusconnect/participantsettings.php');

class campusconnect_export {

    // Holds the status of the exported course until the ECS has been updated.
    const STATUS_UPTODATE = 0;
    const STATUS_CREATED = 1;
    const STATUS_UPDATED = 2;
    const STATUS_DELETED = 3;

    protected $exportparticipants = null;
    protected $exportsettings = null;
    protected $courseid = null;

    function __construct($courseid) {
        global $DB, $SITE;

        if ($courseid == $SITE->id) {
            throw new coding_exception("The SITE course is not eligable for export via CampusConnect");
        }

        $this->courseid = $courseid;
        $this->exportsettings = $DB->get_records('local_campusconnect_export', array('courseid' => $this->courseid));
        $mids = array();
        foreach ($this->exportsettings as $setting) {
            if ($setting->status != self::STATUS_DELETED) {
                $mids[$setting->ecsid] = explode(',', $setting->mids);
            }
        }
        $this->exportparticipants = campusconnect_participantsettings::list_potential_export_participants();

        foreach ($this->exportparticipants as $part) {
            $exported = array_key_exists($part->get_ecs_id(), $mids);
            $exported = $exported && in_array($part->get_mid(), $mids[$part->get_ecs_id()]);
            $part->show_exported($exported);
        }
    }

    /**
     * Returns the courseid that this export object is for
     * @return int $courseid
     */
    function get_courseid() {
        return $this->courseid;
    }

    /**
     * Is this course exported to any participant in any ECS?
     * @return bool true if exported to at least one participant
     */
    function is_exported() {
        // See if we have a non-deleted export record for any ECS
        foreach ($this->exportsettings as $setting) {
            if ($setting->status != self::STATUS_DELETED) {
                return true;
            }
        }
        return false;
    }

    /**
     * List all the participants this course is currently exported to.
     * @return array of ecsid_mid => campusconnect_participantsettings
     */
    function list_current_exports() {
        $ret = array();
        foreach ($this->exportparticipants as $identifier => $part) {
            if ($part->is_exported()) {
                $ret[$identifier] = $part;
            }
        }
        return $ret;
    }

    /**
     * Returns a list of potential participants to export to. Use $part->display_name()
     * for the name to display and $part->is_exported() to see if it is currently exported
     * @return array of ecsid_mid => campusconnect_participantsettings
     */
    function list_participants() {
        return $this->exportparticipants;
    }

    /**
     * Set the export status for an individual participant
     * @param string $identifier - ecsid_mid for the participant
     * @param bool $export true to export to them, false to not export
     * @return void
     */
    function set_export($identifier, $export) {
        global $DB;

        if (!array_key_exists($identifier, $this->exportparticipants)) {
            throw new coding_exception("Attempting to set the exported value of a participant ($identifier) not in the available to export to list");
        }
        $ecsid = $this->exportparticipants[$identifier]->get_ecs_id();
        $mid = $this->exportparticipants[$identifier]->get_mid();
        $this->exportparticipants[$identifier]->show_exported($export);

        foreach ($this->exportsettings as $setting) {
            if ($setting->ecsid == $ecsid) {
                // We already have a local export record for this course & ECS.
                $mids = array_filter(explode(',', $setting->mids));
                if ($export) {
                    if (in_array($mid, $mids)) {
                        return; // Already on list to export to.
                    }
                    $mids[] = $mid;
                } else {
                    if (($key = array_search($mid, $mids)) === false) {
                        return; // Wasn't in the export list anyway.
                    }
                    unset($mids[$key]);
                }
                $upd = new stdClass();
                $upd->id = $setting->id;
                $upd->mids = implode(',', $mids);
                if ($setting->status != self::STATUS_CREATED) {
                    // ECS server already knows about this resource => send update or delete as appropriate.
                    if (empty($mids)) {
                        $upd->status = self::STATUS_DELETED;
                    } else {
                        $upd->status = self::STATUS_UPDATED;
                    }
                } else {
                    if (empty($mids)) {
                        // ECS server never received the 'create' message => just delete the local export record.
                        $DB->delete_records('local_campusconnect_export', array('id' => $upd->id));
                        unset($this->exportsettings[$upd->id]);
                        return;
                    }
                }
                // Update the database and the exportsettings array.
                $DB->update_record('local_campusconnect_export', $upd);
                $this->exportsettings[$upd->id]->mids = $upd->mids;
                if (isset($upd->status)) {
                    $this->exportsettings[$upd->id]->status = $upd->status;
                }
                return;
            }
        }

        // No current export record for this course & ECS => create one.
        $ins = new stdClass();
        $ins->courseid = $this->courseid;
        $ins->ecsid = $ecsid;
        $ins->mids = $mid;
        $ins->status = self::STATUS_CREATED;
        $ins->id = $DB->insert_record('local_campusconnect_export', $ins);
        $this->exportsettings[$ins->id] = $ins;
    }

    /**
     * Set this course to not be exported to any participants
     */
    public function clear_exports() {
        global $DB;

        foreach ($this->exportsettings as $setting) {
            if ($setting->status == self::STATUS_CREATED) {
                $DB->delete_records('local_campusconnect_export', array('id' => $setting->id));
                unset($this->exportsettings[$setting->id]);

            } else if ($setting->status != self::STATUS_DELETED) {
                $upd = new stdClass();
                $upd->id = $setting->id;
                $upd->mids = '';
                $upd->status = self::STATUS_DELETED;
                $DB->update_record('local_campusconnect_export', $upd);

                $this->exportsettings[$upd->id]->mids = '';
                $this->exportsettings[$upd->id]->status = self::STATUS_DELETED;
            }
        }
    }

    /**
     * Send out course update messages to all ECS we are registered with.
     */
    public static function update_all_ecs() {
        $ecslist = campusconnect_ecssettings::list_ecs();
        foreach ($ecslist as $ecsid => $ecsname) {
            $settings = new campusconnect_ecssettigns($ecsid);
            $connect = new campusconnect_connect($settings);
            self::update_ecs($connect);
        }
    }

    /**
     * Send out course update messages to a single ECS.
     * @param campusconnect_connect $connect - a connection to the specific ECS
     * @param array $unittestdata - course data to use when unit testing
     */
    public static function update_ecs(campusconnect_connect $connect, $unittestdata = null) {
        global $DB;

        // Get a list of all the courses that need updating on the ECS server.
        $updated = $DB->get_records_select('local_campusconnect_export', 'ecsid = :ecsid AND status <> :uptodate',
                                           array('ecsid' => $connect->get_ecs_id(), 'uptodate' => self::STATUS_UPTODATE));
        foreach ($updated as $export) {
            if ($export->status == self::STATUS_DELETED) {
                // Delete from ECS server, then delete local record.
                $connect->delete_resource($export->resourceid);
                $DB->delete_records('local_campusconnect_export', array('id' => $export->id));
                continue;
            }

            // Get the course data & adjust using meta-data mapping rules.
            if (is_null($unittestdata)) {
                $course = $DB->get_record('course', array('id' => $export->courseid), '*', MUST_EXIST);
            } else {
                $course = $unittestdata[$export->courseid];
            }
            $metadata = new campusconnect_metadata($connect->get_settings());
            $data = $metadata->map_course_to_remote($course);
            $url = new moodle_url('/course/view.php', array('id' => $course->id));
            $data->url = $url->out();

            // Update ECS server.
            if ($export->status == self::STATUS_CREATED) {
                $resourceid = $connect->add_resource($data, null, $export->mids);
            }
            if ($export->status == self::STATUS_UPDATED) {
                $connect->update_resource($export->resourceid, $data, null, $export->mids);
            }

            // Update local export record.
            $upd = new stdClass();
            $upd->id = $export->id;
            $upd->status = self::STATUS_UPTODATE;
            if (isset($resourceid)) {
                $upd->resourceid = $resourceid;
            }
            $DB->update_record('local_campusconnect_export', $upd);
        }
    }

    /**
     * Delete all course exports for this ECS - may fail if the ECS cannot be contacted
     * @param int $ecsid - the ECS to delete
     * @param bool $force optional - set to true to delete even if the ECS connection fails
     */
    public static function delete_ecs_exports($ecsid, $force=false) {
        global $DB;

        $exports = $DB->get_records('local_campusconnect_export', array('ecsid' => $ecsid));
        if ($exports) {
            $secssettings = new campusconnect_ecssettings($ecsid);
            $connect = new campusconnect_connect($ecssettings);
            foreach ($exports as $export) {
                if ($export->status != self::STATUS_CREATED) {
                    try {
                        $connect->delete_resource($export->resourceid);
                        $DB->delete_record('local_campusconnect_export', array('id' => $export->id));
                    } catch (Exception $e) {
                        if (!$force) {
                            throw $e;
                        }
                    }
                }
            }
            // Final clean-up.
            $DB->delete_records('local_campusconnect_export', array('ecsid' => $ecsid));
        }
    }
}