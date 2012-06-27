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
 * Class to support the mapping of course meta data
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class campusconnect_metadata {

    const TYPE_IMPORT_COURSE = 1;
    const TYPE_IMPORT_EXTERNAL_COURSE = 2;
    const TYPE_EXPORT_COURSE = 3;
    const TYPE_EXPORT_EXTERNAL_COURSE = 4;

    protected static $coursefields = array('fullname' => 'string', 'shortname' => 'string',
                                           'idnumber' => 'string', 'summary' => 'string',
                                           'startdate' => 'date', 'lang' => 'lang',
                                           'timecreated' => 'date', 'timemodified' => 'date');

    protected static $remotefields = array('organization' => 'string', 'url' => 'url',
                                          'lang' => 'lang', 'semesterHours' => 'string',
                                          'courseID' => 'string', 'term' => 'string',
                                          'credits' => 'string', 'status' => 'string',
                                          'title' => 'string', 'room' => 'string',
                                          'cycle' => 'string', 'begin' => 'date',
                                          'end' => 'date', 'study_courses' => 'list',
                                          'lecturer' => 'list');

    // Default import mappings
    protected $importmappings = array(
        'fullname' => '{title}',
        'shortname' => '{title}',
        'idnumber' => '',
        'summary' => null, // This is built on first load using get_string
        'startdate' => 'begin',
        'lang' => 'lang',
        'timecreated' => '',
        'timemodified' => ''
    );

    // Default export mappings
    protected $exportmappings = array(
        'organization' => '',
        'lang' => 'lang',
        'semesterHours' => '',
        'courseID' => '',
        'term' => '',
        'credits' => '',
        'status' => '',
        'title' => '{fullname}',
        'room' => '',
        'cycle' => '',
        'begin' => 'startdate',
        'end' => '',
        'study_courses' => '',
        'lecturer' => ''
    );

    protected $lasterrormsg = null;
    protected $lasterrorfield = null;
    protected $external = true;
    protected $ecsid = null;

    /**
     * Returns a list of all the remote fields (any of which can be
     * inserted into text fields as '{fieldname}')
     * @return array of string - the available fields
     */
    public static function list_remote_fields() {
        return array_keys(self::$remotefields);
    }

    /**
     * Returns a list of all local (course) fields
     * @return array of string - the available fields
     */
    public static function list_local_fields() {
        return array_keys(self::$coursefields);
    }

    /**
     * Text fields should allow the user to construct the value via a combination
     * of free text and remote fields (surrounded by '{' and '}' characters)
     * @param string $fieldname - the course field to check
     * @return bool true if it is a text field
     */
    public static function is_text_field($fieldname) {
        if (!array_key_exists($fieldname, self::$coursefields)) {
            throw new coding_exception("$fieldname is not an available Moodle course field");
        }
        return (self::$coursefields[$fieldname] == 'string');
    }

    /**
     * Text fields should allow the user to construct the value via a combination
     * of free text and course fields (surrounded by '{' and '}' characters)
     * @param string $fieldname - the course field to check
     * @return bool true if it is a text field
     */
    public static function is_remote_text_field($fieldname) {
        if (!array_key_exists($fieldname, self::$remotefields)) {
            throw new coding_exception("$fieldname is not an available remote field");
        }
        return (self::$remotefields[$fieldname] == 'string');
    }

    /**
     * List suitable remote fields for mapping onto the given course field
     * @param string $localfieldname the local field to look for mappings for
     * @return array of fields that could match this
     */
    public static function list_remote_to_local_fields($localfieldname) {
        if (!array_key_exists($localfieldname, self::$coursefields)) {
            throw new coding_exception("$localfieldname is not an available Moodle course field");
        }
        $type = self::$coursefields[$localfieldname];
        $ret = array();
        foreach (self::$remotefields as $rname => $rtype) {
            if ($rtype == $type) {
                $ret[] = $rname;
            }
        }
        return $ret;
    }

    /**
     * List suitable course fields for mapping onto the given remote field
     * @param string $remotefieldname the remote field to look for mappings for
     * @return array of fields that could match this
     */
    public static function list_local_to_remote_fields($remotefieldname) {
        if (!array_key_exists($remotefieldname, self::$remotefields)) {
            throw new coding_exception("$remotefieldname is not an available Moodle course field");
        }
        $type = self::$remotefields[$remotefieldname];
        $ret = array();
        foreach (self::$coursefields as $cname => $ctype) {
            if ($ctype == $type) {
                $ret[] = $cname;
            }
        }
        return $ret;
    }

    /**
     * Generate a default summary layout (could be used to reset back to the default)
     * @return string the default summary
     */
    public static function generate_default_summary() {
        $mapping = array('organization' => get_string('field_organisation', 'local_campusconnect'),
                         'lang' => get_string('field_language', 'local_campusconnect'),
                         'semesterHours' => get_string('field_semesterhours', 'local_campusconnect'),
                         'courseID' => get_string('field_courseid', 'local_campusconnect'),
                         'term' => get_string('field_term', 'local_campusconnect'),
                         'credits' => get_string('field_credits', 'local_campusconnect'),
                         'status' => get_string('field_status', 'local_campusconnect'),
                         'courseType' => get_string('field_coursetype', 'local_campusconnect'));
        $summary = '';
        foreach ($mapping as $field => $text) {
            $summary .= "<b>$text:</b> \{$field\}<br/>";
        }

        return $summary;
    }

    /**
     * Delete all metadata mappings associated with the given ECS
     * @param campusconnect_ecssettings $ecssettings the ECS to clear
     */
    public static function delete_ecs_metadata_mappings($ecsid) {
        global $DB;

        $DB->delete_records('local_campusconnect_mappings', array('ecsid' => $ecsid));
    }

    /**
     * @param campusconnect_ecssettings $ecssettings the ECS this is the mapping for
     * @param bool $external - true if this is the mappings for 'external courses'
     */
    public function __construct(campusconnect_ecssettings $ecssettings, $external = true) {
        global $DB;

        $this->external = $external;
        $this->ecsid = $ecssettings->get_id();

        $mappings = $DB->get_records('local_campusconnect_mappings', array('ecsid' => $this->ecsid));
        foreach ($mappings as $mapping) {
            if ($external) {
                if ($mapping->type == self::TYPE_IMPORT_COURSE ||
                    $mapping->type == self::TYPE_EXPORT_COURSE) {
                    continue;
                }
            } else {
                if ($mapping->type == self::TYPE_IMPORT_EXTERNAL_COURSE ||
                    $mapping->type == self::TYPE_EXPORT_EXTERNAL_COURSE) {
                    continue;
                }
            }
            switch ($mapping->type) {
            case self::TYPE_IMPORT_COURSE:
            case self::TYPE_IMPORT_EXTERNAL_COURSE:
                if (array_key_exists($mapping->field, self::$coursefields)) {
                    $this->importmappings[$mapping->field] = $mapping->setto;
                }
                break;
            case self::TYPE_EXPORT_COURSE:
            case self::TYPE_EXPORT_EXTERNAL_COURSE:
                if (array_key_exists($mapping->field, self::$remotefields)) {
                    $this->exportmappings[$mapping->field] = $mapping->setto;
                }
                break;
            }
        }

        if (is_null($this->importmappings['summary'])) {
            $this->importmappings['summary'] = self::generate_default_summary();
        }
    }

    /**
     * Is this mapping for external courses?
     * @return bool true if the mapping is for external courses
     */
    public function is_external() {
        return $this->external;
    }

    /**
     * Get the list of mappings used on import
     * @return array localfield => remotefield
     */
    public function get_import_mappings() {
        return $this->importmappings;
    }

    /**
     * Get the list of mappings used on export
     * @return array remotefield => localfield
     */
    public function get_export_mappings() {
        return $this->exportmappings;
    }

    /**
     * Get the last error caused during set_import/export_mapping(s)
     * @return array (error message, error field name)
     */
    public function get_last_error() {
        return array($this->lasterrormsg, $this->lasterrorfield);
    }

    /**
     * Set a single import mapping
     * @param string $localfield - the field that will receive the incoming value
     * @param string $remotefield - the name of the field (for non-text fields) or the
     *                          string to set (for text fields) e.g. 'Course name: {title}'
     * @return bool false if an error occurred (see get_last_error for details)
     */
    public function set_import_mapping($localfield, $remotefield) {
        if (self::is_text_field($localfield)) {
            if (preg_match_all('/\{([^}]+)\}/', $remotefield, $includedfields)) {
                foreach ($includedfields[1] as $field) {
                    if (!array_key_exists($field, self::$remotefields)) {
                        $this->lasterrorfield = $localfield;
                        $this->lasterrormsg = get_string('remotefieldnotfound', 'local_campusconnect', $field);
                        return false;
                    }
                }
            }

        } else {
            if (!empty($remotefield) && !in_array($remotefield, self::list_remote_to_local_fields($localfield))) {
                throw new coding_exception("$remotefield is not a suitable field to map onto $localfield");
            }
        }

        $required = array('fullname', 'shortname');
        if (in_array($localfield, $required) && empty($remotefield)) {
            $this->lasterrorfield = $localfield;
            $this->lasterrormsg = get_string('cannotbeempty', 'local_campusconnect', $remotefield);
            return false;
        }

        if ($this->external) {
            $type = self::TYPE_IMPORT_EXTERNAL_COURSE;
        } else {
            $type = self::TYPE_IMPORT_COURSE;
        }

        $this->importmappings[$localfield] = $remotefield;
        $this->save_mapping($localfield, $remotefield, $type);
        return true;
    }

    /**
     * Set a single export mapping
     * @param string $remotefield - the field that will receive the exported value
     * @param string $localfield - the name of the field to export
     * @return bool false if an error occurred (see get_last_error for details)
     */
    public function set_export_mapping($remotefield, $localfield) {
        if (self::is_remote_text_field($remotefield)) {
            if (preg_match_all('/\{([^}]+)\}/', $localfield, $includedfields)) {
                foreach ($includedfields[1] as $field) {
                    if (!array_key_exists($field, self::$coursefields)) {
                        $this->lasterrorfield = $remotefield;
                        $this->lasterrormsg = get_string('localfieldnotfound', 'local_campusconnect', $field);
                        return false;
                    }
                }
            }

        } else {
            if (!empty($localfield) && !in_array($localfield, self::list_local_to_remote_fields($remotefield))) {
                throw new coding_exception("$localfield is not a suitable field to map onto $remotefield");
            }
        }

        if ($this->external) {
            $type = self::TYPE_EXPORT_EXTERNAL_COURSE;
        } else {
            $type = self::TYPE_EXPORT_COURSE;
        }

        $this->exportmappings[$remotefield] = $localfield;
        $this->save_mapping($remotefield, $localfield, $type);
        return true;
    }

    /**
     * Set all import mappings - does not delete missing mappings, set to '' to clear
     * @param array $mappings - localfield => remotefield/text (see set_import_mapping for details)
     * @return bool false if an error occurred (see get_last_error for details)
     */
    public function set_import_mappings($mappings) {
        foreach ($mappings as $local => $remote) {
            if (!$this->set_import_mapping($local, $remote)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Set all export mappings - does not delete missing mappings, set to '' to clear
     * @param array $mappings - remotefield => localfield
     * @return bool false if an error occurred (see get_last_error for details)
     */
    public function set_export_mappings($mappings) {
        foreach ($mappings as $remote => $local) {
            if (!$this->set_export_mapping($remote, $local)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Save the setting in the database
     * @param string $field - the name of the field the mapping is for
     * @param string $setto - what this field should map to
     * @param int $type - the type of the mapping
     */
    protected function save_mapping($field, $setto, $type) {
        global $DB;

        $existing = $DB->get_record('local_campusconnect_mappings', array('ecsid' => $this->ecsid,
                                                                          'field' => $field,
                                                                          'type' => $type));
        if ($existing) {
            $upd = new stdClass();
            $upd->id = $existing->id;
            $upd->setto = $setto;
            $DB->update_record('local_campusconnect_mappings', $upd);
        } else {
            $ins = new stdClass();
            $ins->field = $field;
            $ins->setto = $setto;
            $ins->ecsid = $this->ecsid;
            $ins->type = $type;
            $DB->insert_record('local_campusconnect_mappings', $ins);
        }
    }

    /**
     * Maps the remote details onto a course object
     * @param object $remotedetails the details from the ECS server
     * @return object the course details
     */
    public function map_remote_to_course($remotedetails) {
        // Copy all details out of structured object into flat array.
        $timeplace = array('room', 'cycle', 'begin', 'end');
        $details = array();
        foreach ($remotedetails as $name => $value) {
            if ($name == 'timePlace') {
                foreach ($timeplace as $field) {
                    if (isset($value->$field)) {
                        $details[$field] = $value->$field;
                    }
                }
                continue;
            }

            $details[$name] = $value;
        }
        // Convert dates, lists, etc. into suitable format for Moodle.
        foreach (self::$remotefields as $fieldname => $fieldtype) {
            if (isset($details[$fieldname])) {
                switch ($fieldtype) {
                case 'date':
                    $details[$fieldname] = strtotime($details[$fieldname]);
                    break;
                case 'list':
                    $details[$fieldname] = implode(',', $details[$fieldname]);
                    break;
                case 'lang': // TODO - test if this needs any conversion.
                case 'url':
                case 'string':
                default:
                    // Nothing to do for these.
                }
            }
        }
        // Copy details into course object, as specified by $this->importmappings.
        $course = new stdClass();
        foreach ($this->importmappings as $localfield => $remotefield) {
            if (empty($remotefield)) {
                continue;
            }
            if (self::is_text_field($localfield)) {
                $course->$localfield = $remotefield;
                if (preg_match_all('/\{([^}]+)\}/', $course->$localfield, $includedfields)) {
                    foreach ($includedfields[1] as $field) {
                        if (isset($details[$field])) {
                            $type = self::$remotefields[$field];
                            if ($type == 'date') {
                                $val = userdate($details[$field], get_string('strftimedatetime'));
                            } else {
                                $val = $details[$field];
                            }
                        } else {
                            $val = '';
                        }
                        $course->$localfield = str_replace('{'.$field.'}', $val, $course->$localfield);
                    }
                }

            } else {
                if (isset($details[$remotefield])) {
                    $course->$localfield = $details[$remotefield];
                }
            }
        }
        return $course;
    }

    /**
     * Maps the course object onto the remote details
     * @param object $course the course details
     * @return object details to send to the ECS server
     */
    public function map_course_to_remote($course) {
        // Make sure we don't update the original $course object
        $course = (array)$course;
        $course = (object)$course;

        // Convert data types, as required.
        foreach (self::$coursefields as $fieldname => $fieldtype) {
            if (isset($course->$fieldname)) {
                switch ($fieldtype) {
                case 'date':
                    $course->$fieldname = userdate($course->$fieldname, '%Y-%m-%dT%H:%M:%S%z');
                    break;
                case 'list':
                    $course->$fieldname = explode(',', $course->$fieldname);
                    break;
                case 'lang': // TODO - test if this needs any conversion.
                case 'url':
                case 'string':
                default:
                    // Nothing to do for these.
                }
            }
        }

        // Copy all details from the course into a flat array (as specified by $this->exportmappings).
        $details = array();
        foreach ($this->exportmappings as $remotefield => $localfield) {
            if (empty($localfield)) {
                continue;
            }
            if (self::is_remote_text_field($remotefield)) {
                $details[$remotefield] = $localfield;
                if (preg_match_all('/\{([^}]+)\}/', $details[$remotefield], $includedfields)) {
                    foreach ($includedfields[1] as $field) {
                        if (isset($course->$field)) {
                            $val = $course->$field;
                        } else {
                            $val = '';
                        }
                        $details[$remotefield] = str_replace('{'.$field.'}', $val, $details[$remotefield]);
                    }
                }

            } else {
                if (isset($course->$localfield)) {
                    $details[$remotefield] = $course->$localfield;
                }
            }
       }

        // Copy the details into the final structure.
        $timeplace = array('room', 'cycle', 'begin', 'end');
        $remotedetails = new stdClass();
        foreach ($details as $field => $value) {
            if (in_array($field, $timeplace)) {
                if (!isset($remotedetails->timePlace)) {
                    $remotedetails->timePlace = new stdClass();
                }
                $remotedetails->timePlace->$field = $value;
                continue;
            }
            $remotedetails->$field = $value;
        }

        return $remotedetails;
    }
}