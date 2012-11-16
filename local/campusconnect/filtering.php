<?php
// This file is part of Moodle - http://moodle.org/
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
 * Controls the filtering of incomming courses into the correct category(s)
 *
 * @package   local_campusconnect
 * @copyright 2012 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class campusconnet_filtering {

    static $config = null;
    static $globalsettings = array('enabled' => 'bool', 'defaultcategory' => 'int', 'usesinglecategory' => 'bool',
                                   'singlecategory' => 'int', 'attributes' => 'array');

    //////////////////////////////////////////////
    // Global settings for course filtering
    //////////////////////////////////////////////

    /**
     * Check if courses import filtering is enabled
     * @return bool
     */
    public static function enabled() {
        $config = self::get_config();
        if (isset($config->filteringenabled)) {
            return $config->filteringenabled;
        }
        return false;
    }

    /**
     * Returns the default category in which to create courses (if no other valid category is found)
     * @return int|bool - false if no category set
     */
    public static function get_default_category() {
        $config = self::get_config();
        if (isset($config->filteringdefaultcategory)) {
            return $config->filteringdefaultcategory;
        }
        return false;
    }

    /**
     * Returns the category in which all 'real' courses should be created (with internal links going in any other
     * categories)
     * @return int|bool - false if courses should not be created in a single category
     */
    public static function create_in_category() {
        $config = self::get_config();
        if (isset($config->filteringusesinglecategory) && $config->filterusesinglecategory) {
            if (isset($config->filtersinglecategory)) {
                return $config->filteringsinglecategory;
            }
        }
        return false;
    }

    /**
     * Returns an ordered list of the course attributes that are being used for filtering courses.
     * @return string[]
     */
    public static function course_attributes() {
        $config = self::get_config();
        if (isset($config->filteringattributes)) {
            return explode(',', $config->filteringattributes);
        }
        return array();
    }

    /**
     * Returns all the global settings as an array - suitable for use in the config form. See
     * campusconnect_filtering::$globalsetting for a full list of the settings.
     * @return array
     */
    public static function load_global_settings() {
        $settings = array();
        $config = self::get_config();
        foreach (self::$globalsettings as $name => $type) {
            $configname = "filtering{$name}";
            if (isset($config->$configname)) {
                $val = $config->$configname;
                if ($type == 'array') {
                    $val = explode(',', $val);
                }
            } else {
                if ($type == 'array') {
                    $val = array();
                } else {
                    $val = false;
                }
            }
            $settings[$name] = $val;
        }
        return $settings;
    }

    /**
     * Saves all the global settings provided in the array. See campusconnect_filtering::$globalsetting for
     * a full list of the available settings.
     * @param array $settings
     * @throws coding_exception
     */
    public static function save_global_settings(array $settings) {
        foreach (self::$globalsettings as $name => $type) {
            if (!isset($settings[$name])) {
                continue;
            }
            $val = $settings[$name];
            switch ($type) {
            case 'bool':
                $val = $val ? 1 : 0;
                break;
            case 'int':
                $val = intval($val);
                break;
            case 'array':
                if (!is_array($val)) {
                    throw new coding_exception("Expected value '$name' to be an array");
                }
                array_map('trim', $val);
                $val = implode(',', $val);
                break;
            }
            set_config("filtering{$name}", $val, 'local_campusconnect');
        }
        self::$config = null; // Clear out the config cache.
    }

    //////////////////////////////////////////////
    // Internal functions
    //////////////////////////////////////////////

    /**
     * Internal function to load all config settings
     * @return mixed|null
     */
    protected static function get_config() {
        if (is_null(self::$config)) {
            self::$config = get_config('local_campusconnect');
        }
        return self::$config;
    }

}