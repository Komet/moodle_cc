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

    protected static $coursefields = array(
        'fullname' => 'string', 'shortname' => 'string',
        'idnumber' => 'string', 'summary' => 'string',
        'startdate' => 'date', 'lang' => 'lang',
        'timecreated' => 'date', 'timemodified' => 'date');

    protected static $remotefieldsext = array(
        'destinationForDisplay' => 'string',
        'lang' => 'lang', 'hoursPerWeek' => 'string',
        'id' => 'string', 'number' => 'string',
        'term' => 'string', 'credits' => 'string',
        'status' => 'string', 'courseType' => 'string',
        'title' => 'string', 'firstDate' => 'date',
        'datesAndVenues.day' => 'string', 'datesAndVenues.start' => 'date',
        'datesAndVenues.end' => 'date', 'datesAndVenues.cycle' => 'string',
        'datesAndVenues.venue' => 'string', 'datesAndVenues.firstDate.startDatetime' => 'date',
        'datesAndVenues.firstDate.endDatetime' => 'date', 'datesAndVenues.lastDate.startDatetime' => 'date',
        'datesAndVenues.lastDate.endDatetime' => 'date', 'degreeProgrammes_code' => 'degreelist',
        'degreeProgrammes_title' => 'degreelist', 'degreeProgrammes' => 'degreelist',
        'lecturers_lastName' => 'personlist', 'lecturers_firstName' => 'personlist',
        'lecturers' => 'personlist');

    protected static $remotefields = array(
        'organisation' => 'string', 'id' => 'string',
        'term' => 'string', 'number' => 'string',
        'title' => 'string', 'courseType' => 'string',
        'hoursPerWeek' => 'string', 'maxParticipants' => 'string',
        'parallelGroupScenario' => 'string', 'lecturers_firstName' => 'personlist',
        'lecturers_lastName' => 'personlist', 'lecturers' => 'personlist',
        'degreeProgrammes_code' => 'degreelist', 'degreeProgrammes_title' => 'degreelist',
        'degreeProgrammes' => 'degreelist', 'comment1' => 'string',
        'comment2' => 'string', 'comment3' => 'string',
        'recommendedReading' => 'string', 'prerequisites' => 'string',
        'courseAssessmentMethod' => 'string', 'courseTopics' => 'string',
        'linkToCurriculum' => 'string', 'targetAudience' => 'string',
        'links' => 'linklist', 'linkToCourse' => 'link',
        'modules' => 'moduleslist');
    // Note - leaving out fields 'allocations', 'organisationalUnit', 'parallelGroups', as there is no obvious place to map these to in Moodle.

    // Default import mappings
    protected $importmappings = array(
        'fullname' => '{title}',
        'shortname' => '{title}',
        'idnumber' => '',
        'summary' => null, // This is built on first load using get_string
        'startdate' => 'firstDate',
        'lang' => 'lang',
        'timecreated' => '',
        'timemodified' => ''
    );

    // Default export mappings
    protected $exportmappings = array(
        'organisation' => '',
        'id' => '',
        'term' => '',
        'number' => '',
        'title' => '{fullname}',
        'courseType' => '',
        'hoursPerWeek' => '',
        'maxParticipants' => '',
        'parallelGroupScenario' => '',
        'lecturers' => '',
        'degreeProgrammes' => '',
        'comment1' => '',
        'comment2' => '',
        'comment3' => '',
        'recommendedReading' => '',
        'prerequisites' => '',
        'courseAssessmentMethod' => '',
        'courseTopics' => '',
        'linkToCurriculum' => '',
        'targetAudience' => '',
        'links' => '',
        'linkToCourse' => '',
        'modules' => ''
    );

    // Default external export mappings
    protected $exportmappingsext = array(
        'destinationForDisplay' => '',
        'lang' => 'lang',
        'hoursPerWeek' => '',
        'id' => '',
        'number' => '',
        'term' => '',
        'credits' => '',
        'status' => '',
        'courseType' => '',
        'title' => '{fullname}',
        'firstDate' => 'startdate',
        'datesAndVenues.day' => '',
        'datesAndVenues.start' => '',
        'datesAndVenues.end' => '',
        'datesAndVenues.cycle' => '',
        'datesAndVenues.venue' => '',
        'datesAndVenues.firstDate.startDatetime' => '',
        'datesAndVenues.firstDate.endDatetime' => '',
        'datesAndVenues.lastDate.startDatetime' => '',
        'datesAndVenues.lastDate.endDatetime' => '',
        'degreeProgrammes' => '',
        'lecturers' => ''
    );

    protected $lasterrormsg = null;
    protected $lasterrorfield = null;
    protected $external = true;
    protected $ecsid = null;

    /**
     * Returns a list of all the remote fields (any of which can be
     * inserted into text fields as '{fieldname}')
     * @param bool $external - true for external course mappings, false for internal
     * @return array of string - the available fields
     */
    public static function list_remote_fields($external = true) {
        if ($external) {
            return array_keys(self::$remotefieldsext);
        } else {
            return array_keys(self::$remotefields);
        }
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
     * @param bool $external - true for external course mappings, false for internal
     * @return bool true if it is a text field
     */
    public static function is_remote_text_field($fieldname, $external = true) {
        $remotefields = $external ? self::$remotefieldsext : self::$remotefields;
        if (!array_key_exists($fieldname, $remotefields)) {
            throw new coding_exception("$fieldname is not an available remote field");
        }
        return ($remotefields[$fieldname] == 'string');
    }

    /**
     * List suitable remote fields for mapping onto the given course field
     * @param string $localfieldname the local field to look for mappings for
     * @param bool $external - true for external course mappings, false for internal
     * @return array of fields that could match this
     */
    public static function list_remote_to_local_fields($localfieldname, $external = true) {
        if (!array_key_exists($localfieldname, self::$coursefields)) {
            throw new coding_exception("$localfieldname is not an available Moodle course field");
        }
        $type = self::$coursefields[$localfieldname];
        $remotefields = $external ? self::$remotefieldsext : self::$remotefields;
        $ret = array();
        foreach ($remotefields as $rname => $rtype) {
            if ($rtype == $type) {
                $ret[] = $rname;
            }
        }
        return $ret;
    }

    /**
     * List suitable course fields for mapping onto the given remote field
     * @param string $remotefieldname the remote field to look for mappings for
     * @param bool $external - true for external course mappings, false for internal
     * @return array of fields that could match this
     */
    public static function list_local_to_remote_fields($remotefieldname, $external = true) {
        $remotefields = $external ? self::$remotefieldsext : self::$remotefields;
        if (!array_key_exists($remotefieldname, $remotefields)) {
            if ($external) {
                throw new coding_exception("$remotefieldname is not an available remote external course field");
            } else {
                throw new coding_exception("$remotefieldname is not an available remote course field");
            }
        }
        $type = $remotefields[$remotefieldname];
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
     * @param bool $external optional (default true)
     * @return string the default summary
     */
    public static function generate_default_summary($external = true) {
        if ($external) {
            $mapping = array('destinationForDisplay' => get_string('field_organisation', 'local_campusconnect'),
                             'lang' => get_string('field_language', 'local_campusconnect'),
                             'term' => get_string('field_term', 'local_campusconnect'),
                             'credits' => get_string('field_credits', 'local_campusconnect'),
                             'status' => get_string('field_status', 'local_campusconnect'),
                             'courseType' => get_string('field_coursetype', 'local_campusconnect'));
        } else {
            $mapping = array('organisation' => get_string('field_organisation', 'local_campusconnect'),
                             'term' => get_string('field_term', 'local_campusconnect'),
                             'courseType' => get_string('field_coursetype', 'local_campusconnect'));
        }
        $summary = '';
        foreach ($mapping as $field => $text) {
            $summary .= '<b>'.$text.':</b> {'.$field.'}<br/>';
        }

        return $summary;
    }

    /**
     * Delete all metadata mappings associated with the given ECS
     * @param int $ecsid the ECS to clear
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

        if (!$this->external) {
            // Clear the defaults for these, as no suitable fields for 'internal course' mapping.
            $this->importmappings['startdate'] = '';
            $this->importmappings['lang'] = '';
        } else {
            $this->exportmappings = $this->exportmappingsext;
        }

        $remotefields = $this->external ? self::$remotefieldsext : self::$remotefields;
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
                if (array_key_exists($mapping->field, $remotefields)) {
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
        $remotefields = $this->external ? self::$remotefieldsext : self::$remotefields;
        if (self::is_text_field($localfield)) {
            if (preg_match_all('/\{([^}]+)\}/', $remotefield, $includedfields)) {
                foreach ($includedfields[1] as $field) {
                    if (!array_key_exists($field, $remotefields)) {
                        $this->lasterrorfield = $localfield;
                        $this->lasterrormsg = get_string('remotefieldnotfound', 'local_campusconnect', $field);
                        return false;
                    }
                }
            }

        } else {
            if (!empty($remotefield) && !in_array($remotefield, self::list_remote_to_local_fields($localfield, $this->external))) {
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
        if (self::is_remote_text_field($remotefield, $this->external)) {
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
            if (!empty($localfield) && !in_array($localfield, self::list_local_to_remote_fields($remotefield, $this->external))) {
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
     * Convert the structured details into a flat array for easier processing
     * @param stdClass $remotedetails
     * @return array
     */
    public function flatten_remote_data($remotedetails) {
        $details = array();
        foreach ($remotedetails as $name => $value) {
            if ($name == 'basicData') {
                foreach ($value as $fieldname => $fieldvalue) {
                    $details[$fieldname] = $fieldvalue;
                }
                continue;
            } else if ($name == 'datesAndVenues') {
                foreach ($value[0] as $fieldname => $fieldvalue) {
                    if ($fieldname == 'firstDate') {
                        $details['datesAndVenues.firstDate.startDatetime'] = $fieldvalue->startDatetime;
                        $details['datesAndVenues.firstDate.endDatetime'] = $fieldvalue->endDatetime;
                    } elseif ($fieldname == 'lastDate') {
                        $details['datesAndVenues.lastDate.startDatetime'] = $fieldvalue->startDatetime;
                        $details['datesAndVenues.lastDate.endDatetime'] = $fieldvalue->endDatetime;
                    } else {
                        $details['datesAndVenues.'.$fieldname] = $fieldvalue;
                    }
                }
                continue;
            }
            $details[$name] = $value;
        }

        // Convert dates, lists, etc. into suitable format for Moodle.
        $remotefields = $this->external ? self::$remotefieldsext : self::$remotefields;
        foreach ($remotefields as $fieldname => $fieldtype) {
            $fieldnameparts = explode('_', $fieldname);
            $basename = $fieldnameparts[0];
            $subname = false;
            if (isset($fieldnameparts[1])) {
                $subname = $fieldnameparts[1];
            }
            if (isset($details[$basename])) {
                switch ($fieldtype) {
                case 'date':
                    $details[$fieldname] = strtotime($details[$basename]);
                    break;
                case 'personlist':
                    foreach ($details[$basename] as $key => $person) {
                        if ($subname) {
                            $details[$fieldname][$key] = $person->{$subname};
                        } else {
                            $fakeuser = new stdClass();
                            $fakeuser->firstname = $person->firstName;
                            $fakeuser->lastname = $person->lastName;
                            $details[$fieldname][$key] = fullname($fakeuser);
                        }
                    }
                    $details[$fieldname] = implode(', ', $details[$fieldname]);
                    break;
                case 'degreelist':
                    foreach ($details[$basename] as $key => $degree) {
                        if ($subname) {
                            $details[$fieldname][$key] = $degree->{$subname};
                        } else {
                            $details[$fieldname][$key] = $degree->code.' - '.$degree->title;
                        }
                    }
                    $details[$fieldname] = implode(', ', $details[$fieldname]);
                    break;
                case 'linklist':
                    foreach ($details[$basename] as $key => $link) {
                        $details[$fieldname][$key] = html_writer::link($details[$basename][$key]->href, $details[$basename][$key]->title);
                    }
                    $details[$fieldname] = implode(', ', $details[$fieldname]);
                    break;
                case 'moduleslist':
                    foreach ($details[$basename] as $key => $module) {
                        $details[$fieldname][$key] = $module->title;
                    }
                    $details[$fieldname] = implode(', ', $details[$fieldname]);
                    break;
                case 'list':
                    $details[$fieldname] = implode(', ', $details[$basename]);
                    break;
                case 'link':
                    $details[$fieldname] = html_writer::link($details[$basename]->href, $details[$basename]->title);
                    break;
                case 'lang': // TODO davo - test if this needs any conversion.
                case 'url':
                case 'string':
                default:
                    // Nothing to do for these.
                }
            }
        }
        return $details;
    }

    /**
     * Maps the remote details onto a course object
     * @param object $remotedetails the details from the ECS server
     * @return object the course details
     */
    public function map_remote_to_course($remotedetails) {
        // Copy all details out of structured object into flat array.
        $details = $this->flatten_remote_data($remotedetails);
        $remotefields = $this->external ? self::$remotefieldsext : self::$remotefields;
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
                            $type = $remotefields[$field];
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

        // Fix up the status field
        if ($this->external) {
            if ($remotedetails->status == 'offline') {
                $course->visible = 0;
            } else {
                $course->visible = 1;
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
            if (self::is_remote_text_field($remotefield, $this->external)) {
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
        $basicdata = array('organisation', 'id', 'term', 'number', 'title', 'courseType', 'hoursPerWeek', 'maxParticipants', 'parallelGroupScenario');
        $remotedetails = new stdClass();
        foreach ($details as $field => $value) {
            if ($this->external) {
                $fieldparts = explode('.', $field);
                if ($fieldparts[0] == 'datesAndVenues') {
                    if (!isset($remotedetails->datesAndVenues)) {
                        $remotedetails->datesAndVenues = array(new stdClass());
                    }
                    if (count($fieldparts) > 2) {
                        $subfieldname1 = $fieldparts[1];
                        $subfieldname2 = $fieldparts[2];
                        if (!isset($remotedetails->datesAndVenues[0]->$subfieldname1)) {
                            $remotedetails->datesAndVenues[0]->$subfieldname1 = new stdClass();
                        }
                        $remotedetails->datesAndVenues[0]->$subfieldname1->$subfieldname2 = $value;
                    } else {
                        $subfieldname = $fieldparts[1];
                        $remotedetails->datesAndVenues[0]->$subfieldname = $value;
                    }
                    continue;
                }
            } else {
                if (in_array($field, $basicdata)) {
                    if (!isset($remotedetails->basicData)) {
                        $remotedetails->basicData = new stdClass();
                    }
                    $remotedetails->basicData->$field = $value;
                    continue;
                }
            }
            $remotedetails->$field = $value;
        }

        // Fix up the status field
        if ($this->external) {
            if ($course->visible) {
                $remotedetails->status = 'online';
            } else {
                $remotedetails->status = 'offline';
            }
        }

        return $remotedetails;
    }
}