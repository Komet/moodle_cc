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

class campusconnect_directorytree_exception extends moodle_exception {
    function __construct($msg) {
        parent::__construct('error', 'local_campusconnect', '', $msg);
    }
}

class campusconnect_directorytree {

    const MODE_PENDING = 0;
    const MODE_WHOLE = 1;
    const MODE_MANUAL = 2;
    const MODE_DELETED = 3;

    protected $recordid = null;
    protected $resourceid = null;
    protected $rootid = null;
    protected $title = null;
    protected $ecsid = null;
    protected $mid = null;
    protected $categoryid = null;
    protected $mappingmode = null;

    protected $takeovertitle = null;
    protected $takeoverposition = null;
    protected $takeoverallocation = null;

    protected $stillexists = false;

    protected static $dbfields = array('resourceid', 'rootid', 'title', 'ecsid', 'mid', 'categoryid', 'mappingmode',
                                       'takeovertitle', 'takeoverposition', 'takeoverallocation');
    protected static $createemptycategories = null;
    protected static $enabled = null;

    public function __construct($data = null) {
        if ($data) {
            // local_campusconnect_dirroot record loaded from DB.
            $this->set_data($data);
        }
    }

    public function get_root_id() {
        return $this->rootid;
    }

    public function get_mode() {
        return $this->mappingmode;
    }

    public function get_title() {
        return $this->title;
    }

    public function get_category_id() {
        return $this->categoryid;
    }

    public function should_take_over_title() {
        return $this->takeovertitle;
    }

    public function should_take_over_position() {
        return $this->takeoverposition;
    }

    public function should_take_over_allocation() {
        return $this->takeoverallocation;
    }

    public function update_settings($newsettings) {
        global $DB;

        $newsettings = (array)$newsettings;
        $newsettings = (object)$newsettings;

        if (isset($newsettings->takeovertitle) && $newsettings->takeovertitle != $this->takeovertitle) {
            $this->update_field('takeovertitle', $newsettings->takeovertitle);
            if ($this->takeovertitle && $this->categoryid) {
                $DB->set_field('course_categories', 'name', $this->title, array('id' => $this->categoryid));
            }
        }

        if (isset($newsettings->takeoverposition) && $newsettings->takeoverposition != $this->takeoverposition) {
            $this->update_field('takeoverposition', $newsettings->takeoverposition);
        }

        if (isset($newsettings->takeoverallocation) && $newsettings->takeoverallocation != $this->takeoverallocation) {
            $this->update_field('takeoverallocation', $newsettings->takeoverallocation);
        }
    }

    /**
     * Used during ECS updates to track any trees that no longer
     * exist on the ECS server
     * @return bool - true if still exists
     */
    public function still_exists() {
        return $this->stillexists;
    }

    /**
     * Internal function to set the data loaded from the DB
     * @param obj $data
     */
    protected function set_data($data) {
        $this->recordid = $data->id;
        foreach (self::$dbfields as $field) {
            if (isset($data->$field)) {
                $this->$field = $data->$field;
            }
        }
    }

    /**
     * Internal function to set the value of a specified field
     * @param string $field
     * @param mixed $value
     */
    protected function update_field($field, $value) {
        global $DB;
        $DB->set_field('local_campusconnect_dirroot', $field, $value, array('id' => $this->recordid));
        $this->$field = $value;
    }

    /**
     * Create a database entry for a new directory tree
     * @param int $resourceid - id of the resource in the ECS
     * @param int $rootid - id of root node
     * @param string $title
     * @param int $ecsid
     * @param int $mid
     */
    public function create($resourceid, $rootid, $title, $ecsid, $mid) {
        global $DB;

        $ins = new stdClass();
        $ins->resourceid = $resourceid;
        $ins->rootid = $rootid;
        $ins->title = $title;
        $ins->ecsid = $ecsid;
        $ins->mid = $mid;
        $ins->categoryid = null;
        $ins->mappingmode = self::MODE_PENDING;
        $ins->takeovertitle = true;
        $ins->takeoverposition = true;
        $ins->takeoverallocation = true;

        $ins->id = $DB->insert_record('local_campusconnect_dirroot', $ins);
        $this->set_data($ins);
    }

    /**
     * Set the title of the directory tree (and update the mapped category,
     * if needed)
     * @param string $title
     */
    public function set_title($title) {
        global $DB;

        if ($this->mappingmode == self::MODE_DELETED) {
            throw new coding_exception("Cannot change the title of deleted directory trees");
        }

        if (empty($title)) {
            throw new coding_exception("Directory tree title cannot be empty");
        }

        if ($title == $this->title) {
            return;
        }

        $this->update_field('title', $title);

        if ($this->categoryid && $this->takeovertitle) {
            $DB->set_field('course_categories', 'name', $title, array('id' => $this->categoryid));
        }
    }

    /**
     * Map this directory tree onto a course category
     * @param int $categoryid
     * @return mixed str | null - error string if there is a problem
     */
    public function map_category($categoryid) {
        global $DB;

        if ($this->mappingmode == self::MODE_DELETED) {
            throw new coding_exception("Cannot map deleted directory trees");
        }

        if ($this->categoryid == $categoryid) {
            return; // No change.
        }

        if (! $newcategory = $DB->get_record('course_categories', array('id' => $categoryid))) {
            throw new coding_exception("Directory tree - attempting to map onto non-existent category $categoryid");
        }

        $oldcategoryid = $this->categoryid;
        $this->update_field('categoryid', $categoryid);

        if ($this->title && $this->takeovertitle) {
            $DB->set_field('course_categories', 'name', $this->title, array('id' => $this->categoryid));
        }

        if ($this->mappingmode == self::MODE_PENDING) {
            $this->set_mode(self::MODE_WHOLE);
        }

        if ($oldcategoryid) {
            // Move directories within this directory tree.
            campusconnect_directory::move_category($this->rootid, $oldcategoryid, $this->categoryid);
        } else {
            // Create all categories, if needed.
            if (self::should_create_empty_categories()) {
                $this->create_all_categories();
            }
        }

        return null;
    }

    /**
     * Remove the category mapping.
     */
    public function unmap_category() {
        global $DB;

        if ($this->mappingmode == self::MODE_DELETED) {
            throw new coding_exception("Cannot map deleted directory trees");
        }

        if (empty($this->categoryid)) {
            return; // Nothing to do.
        }

        $this->update_field('categoryid', null);
    }

    /**
     * Set the mapping mode for this directory tree
     * @param int $mode - self::MODE_PENDING, self::MODE_WHOLE, self::MODE_MANUAL
     */
    public function set_mode($mode) {
        if ($mode == $this->mappingmode) {
            return; // No change.
        }

        if (!in_array($mode, array(self::MODE_PENDING, self::MODE_WHOLE, self::MODE_MANUAL))) {
            throw new coding_exception("Invalid directory tree mode $mode");
        }
        if ($mode == self::MODE_PENDING) {
            throw new coding_exception("Directory tree - unable to switch to MODE_PENDING");
        }
        if ($this->mappingmode == self::MODE_MANUAL) {
            throw new coding_exception("Directory tree - unable to switch from MODE_MANUAL");
        }
        if ($this->mappingmode == self::MODE_DELETED) {
            throw new coding_exception("Directory tree - unable to switch from MODE_DELETED");
        }
        $oldmode = $this->mappingmode;

        $this->update_field('mappingmode', $mode);

        if ($oldmode == self::MODE_PENDING) {
            if ($this->categoryid && self::should_create_empty_categories()) {
                $this->create_all_categories();
            }
        }
    }

    /**
     * Mark this directory tree as still existing on the ECS server
     */
    public function set_still_exists() {
        $this->stillexists = true;
        if ($this->mappingmode == self::MODE_DELETED) {
            throw new coding_exception("ECS updating directory tree that is marked as deleted");
        }
    }

    /**
     * Mark the directory tree as deleted
     */
    public function delete() {
        // TODO - send an admin email - do not delete the category.
        //global $DB;
        //$DB->delete_records('local_campusconnect_dirroot', array('id' => $this->recordid));

        campusconnect_directory::delete_root_directory($this->rootid);
        $this->update_field('mappingmode', self::MODE_DELETED);
    }

    /**
     * Retrieve a directory from within this tree
     * @param int $directoryid
     * @return mixed campusconnect_directory | bool - false if not found
     */
    public function get_directory($directoryid) {
        $dirs = campusconnect_directory::get_directories($this->rootid);
        foreach ($dirs as $dir) {
            if ($dir->get_directory_id() == $directoryid) {
                return $dir;
            }
        }
        return false;
    }

    /**
     * Lists all current directory => category mappings
     * (used by the javascript front end)
     * @return array of directoryid => categoryid
     */
    public function list_all_mappings() {
        $ret = array($this->rootid => $this->categoryid);
        $dirs = campusconnect_directory::get_directories($this->rootid);
        foreach ($dirs as $dir) {
            $ret[$dir->get_directory_id()] = $dir->get_category_id();
        }
        return $ret;
    }

    public function locked_mappings() {
        $ret = array($this->rootid => false);
        $dirs = campusconnect_directory::get_directories($this->rootid);
        foreach ($dirs as $dir) {
            $ret[$dir->get_directory_id()] = $dir->is_mapping_locked();
        }
        return $ret;
    }

    /**
     * Called if 'create empty categories' is set, to create all categories for this tree.
     */
    public function create_all_categories() {
        campusconnect_directory::create_all_categories($this->rootid, $this->categoryid);
    }

    public static function should_create_empty_categories() {
        if (is_null(self::$createemptycategories)) {
            self::$createemptycategories = get_config('local_campusconnect', 'createemptycategories');
        }
        return self::$createemptycategories;
    }

    public static function enabled() {
        if (is_null(self::$enabled)) {
            self::$enabled = get_config('local_campusconnect', 'directorymappingenabled');
        }
        return self::$enabled;
    }

    public static function set_enabled($enabled) {
        set_config('directorymappingenabled', $enabled, 'local_campusconnect');
        self::$enabled = $enabled;
    }

    public static function set_create_empty_categories($enabled) {
        set_config('createemptycategories', $enabled, 'local_campusconnect');
        self::$createemptycategories = $enabled;
        if ($enabled) {
            $trees = self::list_directory_trees();
            foreach ($trees as $tree) {
                $catid = $tree->get_category_id();
                campusconnect_directory::create_all_categories($tree->get_root_id(), $catid);
            }
        }
    }

    /**
     * Get a list of all directory trees loaded from ECS servers (only one ECS server
     * and one mid should be providing these, so no parameters needed)
     * @return array of campusconnect_directorytree
     */
    public static function list_directory_trees() {
        global $DB;

        $trees = $DB->get_records('local_campusconnect_dirroot');
        return array_map(function($data) { return new campusconnect_directorytree($data); }, $trees);
    }

    /**
     * Get a single directory tree, identified by its rootid
     * @param int $rootid
     * @return campusconnect_directorytree
     */
    public static function get_by_root_id($rootid) {
        global $DB;

        $tree = $DB->get_record('local_campusconnect_dirroot', array('rootid' => $rootid), '*', MUST_EXIST);
        return new campusconnect_directorytree($tree);
    }

    /**
     * Full update of all directory trees from ECS
     * @return void
     */
    public static function refresh_from_ecs() {
        global $DB;

        if (!self::enabled()) {
            return; // Mapping disabled.
        }

        if (! $cms = campusconnect_participantsettings::get_cms_participant()) {
            return;
        }

        $trees = $DB->get_records('local_campusconnect_dirroot');
        $currenttrees = array();
        foreach ($trees as $tree) {
            $currenttrees[$tree->rootid] = new campusconnect_directorytree($tree);
        }
        unset($trees);

        // Gather directory changes from the ECS server.
        $ecssettings = new campusconnect_ecssettings($cms->get_ecs_id());
        if (!$ecssettings->is_enabled()) {
            return; // Ignore disabled ECS.
        }

        $connect = new campusconnect_connect($ecssettings);
        $resources = $connect->get_resource_list(campusconnect_event::RES_DIRECTORYTREE);
        foreach ($resources->get_ids() as $resourceid) {
            $directory = $connect->get_resource($resourceid, campusconnect_event::RES_DIRECTORYTREE);
            if ($directory->parent->id) {
                // Not a root directory.
                campusconnect_directory::check_update_directory($resourceid, $directory);
                continue;
            }

            if ($directory->id != $directory->rootID) {
                throw new campusconnect_directorytree_exception("Root directory id ($directory->id) does not match the rootID ($directory->rootID)");
            }
            if ($directory->title != $directory->directoryTreeTitle) {
                throw new campusconnect_directorytree_exception("Root directory title ($directory->title) does not match the directoryTreeTitle ($directory->directoryTreeTitle)");
            }

            if (array_key_exists($directory->id, $currenttrees)) {
                // Update existing tree.
                $currenttrees[$directory->id]->set_title($directory->title);
                $currenttrees[$directory->id]->set_still_exists(); // So we can track any trees that no longer exist on ECS.
            } else {
                // Create new tree.
                $newtree = new campusconnect_directorytree();
                $newtree->create($resourceid, $directory->id, $directory->title, $cms->get_ecs_id(), $cms->get_mid());
                $currenttrees[$newtree->get_root_id()] = $newtree;
            }
        }

        // Check if any new categories need to be created.
        campusconnect_directory::process_new_directories();

        // Update any trees that no longer exist on the ECS.
        foreach ($currenttrees as $tree) {
            if (!$tree->still_exists()) {
                $tree->delete(); // Will also delete any contained directories.
            } else {
                campusconnect_directory::remove_missing_directories($tree->get_root_id());
            }
        }
    }

    /**
     * Used by the ECS event processing to create new directories / directory trees
     * @param int $resourceid - the ID on the ECS server
     * @param campusconnect_ecssettings $ecssettings - the ECS being connected to
     * @param obj $directory - the resource data from ECS
     * @param campusconnect_details $details - the metadata for the resource on the ECS
     */
    public static function create_directory($resourceid, campusconnect_ecssettings $ecssettings, $directory, campusconnect_details $details) {
        global $DB;

        $mid = $details->get_sender_mid();
        $ecsid = $ecssettings->get_id();
        $cms = campusconnect_participantsettings::get_cms_participant();
        if (!$cms || $cms->get_mid() != $mid || $cms->get_ecs_id() != $ecsid) {
            throw new campusconnect_directorytree_exception("Received create directory event from non-CMS participant");
        }

        $isdirectorytree = $directory->parent->id ? false : true;
        if ($isdirectorytree) {
            if ($DB->record_exists('local_campusconnect_dirroot', array('rootid' => $directory->rootID))) {
                return self::update_directory($resourceid, $ecssettings, $directory, $details);
                //throw new campusconnect_directorytree_exception("Cannot create a directory tree root node {$directory->rootID} - it already exists.");
            }

            $tree = new campusconnect_directorytree();
            $tree->create($resourceid, $directory->rootID, $directory->title, $ecsid, $mid);

        } else {
            if ($DB->record_exists('local_campusconnect_dir', array('directoryid' => $directory->id))) {
                return self::update_directory($resourceid, $ecssettings, $directory, $details);
                //throw new campusconnect_directorytree_exception("Cannot create a directory tree {$directory->id} - it already exists.");
            }

            $dir = new campusconnect_directory();
            $dir->create($resourceid, $directory->rootID, $directory->id, $directory->parent->id, $directory->title, $directory->order);
        }

        return true;
    }

    /**
     * Used by the ECS event processing to update directories / directory trees
     * @param int $resourceid - the ID on the ECS server
     * @param campusconnect_ecssettings $ecssettings - the ECS being connected to
     * @param obj $directory - the resource data from ECS
     * @param campusconnect_details $details - the metadata for the resource on the ECS
     */
    public static function update_directory($resourceid, campusconnect_ecssettings $ecssettings, $directory, campusconnect_details $details) {
        global $DB;

        $mid = $details->get_sender_mid();
        $ecsid = $ecssettings->get_id();
        $cms = campusconnect_participantsettings::get_cms_participant();
        if (!$cms || $cms->get_mid() != $mid || $cms->get_ecs_id() != $ecsid) {
            throw new campusconnect_directorytree_exception("Received update directory event from non-CMS participant");
        }

        $isdirectorytree = $directory->parent->id ? false : true;
        if ($isdirectorytree) {
            if (!$currdirtree = $DB->get_record('local_campusconnect_dirroot', array('rootid' => $directory->rootID))) {
                return self::create_directory($resourceid, $ecssettings, $directory, $details);
            }

            $tree = new campusconnect_directorytree($currdirtree);
            $tree->set_title($directory->title);

        } else {
            if (!$currdir = $DB->get_record('local_campusconnect_dir', array('directoryid' => $directory->id))) {
                return self::create_directory($resourceid, $ecssettings, $directory, $details);
            }

            $dir = new campusconnect_directory($currdir);
            $dir->check_parent_id($directory->parent->id);
            $dir->set_title($directory->title);
            $dir->set_order($directory->order);
        }

        return true;
    }

    /**
     * Used by the ECS event processing to delete directories / directory trees
     * @param int $resourceid - the ID on the ECS server
     * @param campusconnect_ecssettings $ecssettings - the ECS being connected to
     */
    public static function delete_directory($resourceid, campusconnect_ecssettings $ecssettings) {
        global $DB;

        $dirtree = $DB->get_record('local_campusconnect_dirroot', array('resourceid' => $resourceid));
        if ($dirtree) {
            $dirtree = new campusconnect_directorytree($dirtree);
            $dirtree->delete();
            return true;
        }

        $dir = $DB->get_record('local_campusconnect_dir', array('resourceid' => $resourceid));
        if ($dir) {
            $dir = new campusconnect_directory($dir);
            $dir->delete();
            return true;
        }

        // Not found - but don't worry about it.
        return true;
    }
}

class campusconnect_directory {

    const MAPPING_AUTOMATIC = 0;
    const MAPPING_MANUAL_PENDING = 1; // No courses within it yet.
    const MAPPING_MANUAL = 3; // Courses now exist within it.
    const MAPPING_DELETED = 2;

    const STATUS_PENDING_UNMAPPED = 1000;
    const STATUS_PENDING_MANUAL = 1001;
    const STATUS_PENDING_AUTOMATIC = 1002;
    const STATUS_MAPPED_MANUAL = 1003;
    const STATUS_MAPPED_AUTOMATIC = 1004;
    const STATUS_DELETED = 1005;

    protected $recordid = null;
    protected $resourceid = null;
    protected $rootid = null;
    protected $directoryid = null;
    protected $title = null;
    protected $parentid = null;
    protected $sortorder = null;
    protected $categoryid = null;
    protected $mapping = self::MAPPING_AUTOMATIC;

    protected $stillexists = false; // Flag used during updates from ECS
    protected $parent = null;

    protected static $dbfields = array('resourceid', 'rootid', 'directoryid', 'title', 'parentid', 'sortorder', 'categoryid', 'mapping');

    protected static $dirs = array();
    protected static $newdirs = array();

    /**
     * Create a directory instance
     * @param object $data optional - the record loaded from the database
     */
    public function __construct($data = null) {
        if ($data) {
            $this->set_data($data);
        }
    }

    protected function set_data($data) {
        $this->recordid = $data->id;
        foreach (self::$dbfields as $field) {
            if (isset($data->$field)) {
                $this->$field = $data->$field;
            }
        }
    }

    public function get_root_id() {
        return $this->rootid;
    }

    public function get_directory_id() {
        return $this->directoryid;
    }

    public function get_title() {
        return $this->title;
    }

    public function get_category_id() {
        return $this->categoryid;
    }

    public function get_directory_tree() {
        return campusconnect_directorytree::get_by_root_id($this->rootid);
    }

    public function is_mapping_locked() {
        return ($this->mapping == self::MAPPING_MANUAL);
    }

    /**
     * Get the parent directory
     * @return mixed campusconnect_directory | null (if the parent is the root directory)
     */
    public function get_parent() {
        if (!$this->parentid) {
            throw new coding_exception("get_parent - all directories must have a parentid (directoryid: {$this->directoryid})");
        }
        if ($this->parentid == $this->rootid) {
            return null;
        }
        if ($this->parent != null) {
            return $this->parent;
        }
        $dirs = self::get_directories($this->rootid);
        foreach ($dirs as $dir) {
            if ($dir->get_directory_id() == $this->parentid) {
                $this->parent = $dir;
                return $this->parent;
            }
        }
        throw new coding_exception("get_parent - parent {$this->parentid} not found for directory {$this->directoryid}");
    }

    /**
     * Get the child directories below this one
     * @return array of campusconnect_directory
     */
    public function get_children() {
        $children = array();
        $dirs = self::get_directories($this->rootid);
        foreach ($dirs as $dir) {
            if ($dir->parentid == $this->directoryid) {
                $children[] = $dir;
            }
        }
        return $children;
    }

    public function check_categoryid_mapped_by_child($categoryid) {
        if ($this->categoryid == $categoryid) {
            return true;
        }
        foreach ($this->get_children() as $child) {
            if ($child->check_categoryid_mapped_by_child($categoryid)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build an unordered list element for this directory and it's children
     * @return str HTML fragment
     */
    public function output_directory_tree_node($radioname, $selecteddir = null) {
        static $classes = array(
            self::STATUS_PENDING_UNMAPPED => 'status_pending_unmapped',
            self::STATUS_PENDING_AUTOMATIC => 'status_pending_automatic',
            self::STATUS_PENDING_MANUAL => 'status_pending_manual',
            self::STATUS_MAPPED_MANUAL => 'status_mapped_manual',
            self::STATUS_MAPPED_AUTOMATIC => 'status_mapped_automatic',
            self::STATUS_DELETED => 'status_deleted'
        );

        $expand = false;
        $childnodes = '';
        if ($children = $this->get_children()) {
            foreach ($children as $child) {
                list($childnode, $childexpand) = $child->output_directory_tree_node($radioname, $selecteddir);
                $childnodes .= $childnode;
                $expand = $expand || $childexpand;
            }
            $childnodes = html_writer::tag('ul', $childnodes);
        }
        $status = $this->get_status();
        $class = $classes[$status];
        $ret = html_writer::tag('span', s($this->title), array('class' => $class));
        if ($radioname) {
            $elid = $radioname.'-'.$this->directoryid;
            $label = html_writer::tag('label', $ret, array('for' => $elid));
            $radioparams = array('type' => 'radio',
                                 'name' => $radioname,
                                 'id' => $elid,
                                 'class' => 'directoryradio',
                                 'value' => $this->directoryid);
            if ($selecteddir == $this->directoryid) {
                $radioparams['checked'] = 'checked';
                $expand = true;
            }
            if ($status == self::STATUS_MAPPED_AUTOMATIC ||
                $status == self::STATUS_DELETED ||
                $status == self::STATUS_PENDING_AUTOMATIC) {
                $radioparams['disabled'] = 'disabled';
            } else if ($status == self::STATUS_MAPPED_MANUAL ||
                       $status == self::STATUS_PENDING_MANUAL) {
                $expand = true;
            }
            $ret = html_writer::empty_tag('input', $radioparams);
            $ret .= ' '.$label;
            $ret = html_writer::tag('span', $ret); // To stop YUI treeview getting upset.
        }
        $ret .= $childnodes;
        if ($expand) {
            $params = array('class' => 'expanded');
        } else {
            $params = array();
        }
        return array(html_writer::tag('li', $ret, $params), $expand);
    }

    /**
     * Calculate the current status of the directory
     * @return int status - see campusconnect_directory::STATUS_* for possible values
     */
    public function get_status() {
        if ($this->mapping == self::MAPPING_DELETED) {
            return self::STATUS_DELETED;
        }

        if ($this->mapping == self::MAPPING_MANUAL) {
            return self::STATUS_MAPPED_MANUAL;
        }

        if ($this->mapping == self::MAPPING_MANUAL_PENDING) {
            return self::STATUS_PENDING_MANUAL;
        }

        if (! $parent = $this->get_parent()) {
            return self::STATUS_PENDING_UNMAPPED;
        }

        $parentstatus = $parent->get_status();
        if ($parentstatus == self::STATUS_PENDING_UNMAPPED) {
            return self::STATUS_PENDING_UNMAPPED;
        }

        if ($this->categoryid) {
            return self::STATUS_MAPPED_AUTOMATIC;
        } else {
            return self::STATUS_PENDING_AUTOMATIC;
        }
    }

    /**
     * Used during ECS updates to spot directories that are no longer on the ECS
     * @return bool - true if updated during last ECS update
     */
    public function still_exists() {
        return $this->stillexists;
    }

    /**
     * Check the parentid from the ECS matches the parentid the directory already has - throws
     * exception if they do not match.
     * @param int $parentid (from ECS)
     */
    public function check_parent_id($parentid) {
        if ($this->parentid != $parentid) {
            throw new campusconnect_directorytree_exception("parent {$this->parentid} for directory {$this->directoryid} does not match parent id {$parentid} from ECS");
        }
    }

    /**
     * Create a new directory record
     * @param int $resourceid - id of the resource on the ECS
     * @param int $rootid
     * @param int $directoryid
     * @param int $parentid
     * @param string $title
     * @param int $sortorder
     */
    public function create($resourceid, $rootid, $directoryid, $parentid, $title, $sortorder) {
        global $DB;

        $ins = new stdClass();
        $ins->resourceid = $resourceid;
        $ins->rootid = $rootid;
        $ins->directoryid = $directoryid;
        $ins->title = $title;
        $ins->parentid = $parentid;
        $ins->sortorder = $sortorder;
        $ins->categoryid = null;
        $ins->mapping = self::MAPPING_AUTOMATIC;
        $ins->id = $DB->insert_record('local_campusconnect_dir', $ins);

        $this->set_data($ins);
        self::add_to_dirs($this->rootid, $this->recordid, $this);
    }

    public function delete() {
        $this->set_field('mapping', self::MAPPING_DELETED);
    }

    protected function set_field($field, $value) {
        global $DB;

        $DB->set_field('local_campusconnect_dir', $field, $value, array('id' => $this->recordid));
        $this->$field = $value;
    }

    public function set_title($title) {
        global $DB;

        if ($title == $this->title) {
            return; // No update needed.
        }

        $this->set_field('title', $title);
        if ($this->categoryid) {
            $DB->set_field('course_categories', 'name', $this->title, array('id' => $this->categoryid));
        }
    }

    public function set_order($sortorder) {
        global $DB;

        if ($sortorder == $this->sortorder) {
            return; // No update needed.
        }

        $this->set_field('sortorder', $sortorder);
        if ($this->categoryid) {
            // TODO - do something with this sortorder field.
        }
    }

    /**
     * Mark as still existing on the ECS server, after the current update
     */
    public function set_still_exists() {
        $this->stillexists = true;
    }

    /**
     * Map this directory onto a course category
     * @param int $categoryid
     */
    public function map_category($categoryid) {
        global $DB;

        if ($this->categoryid && $this->mapping == self::MAPPING_AUTOMATIC) {
            throw new campusconnect_directorytree_exception("Cannot map directory {$this->directoryid} as it is already mapped automatically");
        }

        if ($this->categoryid == $categoryid) {
            return; // No change.
        }

        if (! $newcategory = $DB->get_record('course_categories', array('id' => $categoryid))) {
            throw new coding_exception("Directory tree - attempting to map onto non-existent category $categoryid");
        }

        if ($this->categoryid) {
            if ($this->check_categoryid_mapped_by_child($categoryid)) {
                return get_string('cannotmapsubcategory', 'local_campusconnect');
            }
        }

        $oldcategoryid = $this->categoryid;
        $this->set_field('categoryid', $categoryid);

        if ($this->mapping == self::MAPPING_AUTOMATIC) {
            $this->set_field('mapping', self::MAPPING_MANUAL_PENDING);
        }

        if ($oldcategoryid) {
            // Need to move all contained courses & directories.
            self::move_category($this->directoryid, $oldcategoryid, $categoryid);
        } else {
            if (campusconnect_directorytree::should_create_empty_categories()) {
                $tree = $this->get_directory_tree();
                $tree->create_all_categories();
            }
        }
    }

    /**
     * Unmap this directory from the category.
     */
    public function unmap_category() {
        if ($this->mapping != self::MAPPING_MANUAL_PENDING) {
            throw new campusconnect_directorytree_exception("Unmapping of directories can only be done when mapping is pending - current mapping status: {$this->mapping}");
        }

        $this->set_field('categoryid', null);
        $this->set_field('mapping', self::MAPPING_AUTOMATIC);
    }

    /**
     * Create a category for the selected directory, along with any parent categories
     * that do not already exist.
     * @param int $rootcategoryid - ID of the category that the root directory is mapped on to
     * @param bool $fixsortorder optional - used to make sure fix_course_sortorder is only called once
     * @retrun int $id of the category created (or already allocated)
     */
    public function create_category($rootcategoryid, $fixsortorder = true) {
        global $DB;

        echo $this->get_title();

        if ($this->categoryid) {
            // Directory already has an associated category - return it.
            if ($this->mapping == self::MAPPING_MANUAL_PENDING) {
                // Time to fix this mapping in place.
                $this->set_field('mapping', self::MAPPING_MANUAL);
            }
            return $this->categoryid;
        }

        if ($this->parentid == $this->rootid) {
            // Reached the directory tree root - return the mapped category.
            $parentcat = $rootcategoryid;
        } else {
            // Make sure the parent category has been created.
            $parent = $this->get_parent();
            $parentcat = $parent->create_category($rootcategoryid, false);
        }

        if (!$parentcat) {
            return null; // Will happen if the root node is unmapped.
        }

        // Create a new category for this directory.
        $ins = new stdClass();
        $ins->parent = $parentcat;
        $ins->name = $this->title;
        $ins->sortorder = 999; // TODO - do something with the order field.
        $categoryid = $DB->insert_record('course_categories', $ins);
        $this->set_field('categoryid', $categoryid);

        if ($fixsortorder) {
            // Only do once - on the outer level of the loop.
            fix_course_sortorder();
        }

        return $this->categoryid;
    }

    /**
     * Update the directory details from the ECS
     * @param object $directory the details, direct from the ECS
     * @return mixed campusconnect_directory | false : returns the directory,
     *                 if a new directory was created, false if it already existed
     */
    public static function check_update_directory($resourceid, $directory) {
        $dirs = self::get_directories($directory->rootID);
        foreach ($dirs as $dir) {
            if ($dir->get_directory_id() == $directory->id) {
                // Found directory - update it (if needed).
                $dir->check_parent_id($directory->parent->id);
                $dir->set_title($directory->title);
                $dir->set_order($directory->order);
                $dir->set_still_exists();
                return false;
            }
        }

        // Not found - create it.
        $dir = new campusconnect_directory();
        $dir->create($resourceid, $directory->rootID, $directory->id, $directory->parent->id, $directory->title, $directory->order);
        $dir->set_still_exists();
        return $dir;
    }

    /**
     * Called after all calls to 'check_update_directory', to remove
     * any directories not listed on the ECS
     * @param int $rootid
     */
    public static function remove_missing_directories($rootid) {
        $dirs = self::get_directories($rootid);
        foreach ($dirs as $dir) {
            if (!$dir->still_exists()) {
                $dir->delete();
            }
        }
    }

    /**
     * Delete all directory mappings (but not the categories / courses they
     * are mapped on to)
     * @param int $rootid the directory tree being deleted
     */
    public static function delete_root_directory($rootid) {
        //global $DB;
        //$DB->delete_records('local_campusconnect_dir', array('rootid' => $rootid));

        $dirs = self::get_directories($rootid);
        foreach ($dirs as $dir) {
            $dir->delete();
        }
    }

    /**
     * Get all the directories within the given directory tree
     * @param int $rootid
     * @return array of campusconnect_directory objects (indexed by recordid)
     */
    public static function get_directories($rootid) {
        global $DB;
        if (!isset(self::$dirs[$rootid])) {
            $dirs = $DB->get_records('local_campusconnect_dir', array('rootid' => $rootid));
            self::$dirs[$rootid] = array_map(function ($data) { return new campusconnect_directory($data); }, $dirs);
        }
        return self::$dirs[$rootid];
    }

    /**
     * Get the first level of directories for the given directory tree
     * @param int $rootid
     * @return array of campusconnect_directory objects
     */
    public static function get_toplevel_directories($rootid) {
        $dirs = self::get_directories($rootid);
        $tldirs = array();
        foreach ($dirs as $dir) {
            if ($dir->parentid == $rootid) {
                $tldirs[] = $dir;
            }
        }
        return $tldirs;
    }

    /**
     * Output the directory tree as nested unordered lists (ready for use with YUI treeview).
     * @param int $rootid - the tree to output
     * @param str $radioname - optional - if set, creates radio input elements for each item
     * @return str HTML of the lists
     */
    public static function output_directory_tree($dirtree, $radioname, $selecteddir = null) {
        $expand = false;
        $childdirs = '';
        if ($dirs = self::get_toplevel_directories($dirtree->get_root_id())) {
            foreach ($dirs as $dir) {
                list($childdir, $childexpand) = $dir->output_directory_tree_node($radioname, $selecteddir);
                $childdirs .= $childdir;
                $expand = $expand || $childexpand;
            }
            $childdirs = html_writer::tag('ul', $childdirs);
        }
        $elid = $radioname.'-'.$dirtree->get_root_id();
        $label = html_writer::tag('label', s($dirtree->get_title()), array('for' => $elid));
        $radioparams = array('type' => 'radio',
                             'name' => $radioname,
                             'id' => $elid,
                             'class' => 'directoryradio',
                             'value' => $dirtree->get_root_id());
        if (is_null($selecteddir) || $dirtree->get_root_id() == $selecteddir) {
            $radioparams['checked'] = 'checked';
        }
        $ret = html_writer::empty_tag('input', $radioparams);
        $ret .= ' '.$label;
        $ret = html_writer::tag('span', $ret).$childdirs;
        if ($expand) {
            $params = array('class' => 'expanded');
        } else {
            $params = array();
        }
        return html_writer::tag('ul', html_writer::tag('li', $ret, $params));
    }

    public static function output_category_tree($radioname, $selectedcategory = null) {
        $ret = '';

        $cats = get_child_categories(0);
        foreach ($cats as $cat) {
            $ret .= self::output_category_and_children($cat, $radioname, $selectedcategory);
        }

        return html_writer::tag('ul', $ret);
    }

    public static function output_category_and_children($category, $radioname, $selectedcategory = null) {
        $childcats = '';
        if ($cats = get_child_categories($category->id)) {
            foreach ($cats as $cat) {
                $childcats .= self::output_category_and_children($cat, $radioname, $selectedcategory);
            }
            $childcats = html_writer::tag('ul', $childcats);
        }
        $ret = s($category->name);
        $elid = $radioname.'-'.$category->id;
        $labelparams = array('for' => $elid,
                             'id' => 'label'.$elid,
                             'class' => 'categorylabel');
        $radioparams = array('type' => 'radio',
                             'name' => $radioname,
                             'id' => $elid,
                             'class' => 'categoryradio',
                             'value' => $category->id);
        if ($selectedcategory == $category->id) {
            $radioparams['checked'] = 'checked';
            $labelparams['class'] .= ' mapped_category';
        }
        $label = html_writer::tag('label', $ret, $labelparams);
        $ret = html_writer::empty_tag('input', $radioparams);
        $ret .= ' '.$label;
        $ret .= $childcats;
        return html_writer::tag('li', $ret);
    }

    /**
     * Add a newly-created directory to the cached list of directories
     * @param int $rootid
     * @param int $recordid - db id for the directory
     * @param campusconnect_directory $directory - the directory
     */
    protected static function add_to_dirs($rootid, $recordid, campusconnect_directory $directory) {
        if (isset(self::$dirs[$rootid])) {
            self::$dirs[$rootid][$recordid] = $directory;
        }
        if (!isset(self::$newdirs[$rootid])) {
            $newdirs[$rootid] = array();
        }
        $newdirs[$rootid][$recordid] = $directory;
    }

    /**
     * Create any sub-categories of the given directory tree that do not already
     * exist
     * @param int $rootid the root node of the directory tree
     * @param int $rootcategoryid the id of the Moodle category for the root node
     */
    public static function create_all_categories($rootid, $rootcategoryid) {
        global $DB;

        $dirs = self::get_directories($rootid);
        foreach ($dirs as $dir) {
            if (!$dir->get_category_id()) {
                $dir->create_category($rootcategoryid, false);
            }
        }

        fix_course_sortorder();
    }

    /**
     * Move all the courses and sub-directories when the root node of a directory tree
     * has been re-mapped
     * @param int $rootid the root node of the directory tree
     * @param int $oldcategoryid the previous root category (for checking)
     * @param int $newcategoryid the new root category mapping
     */
    public static function move_category($directoryid, $oldcategoryid, $newcategoryid) {
        global $DB;

        // Find all directories at the top level of this tree that have not been manually mapped.
        $dirstomove = $DB->get_records('local_campusconnect_dir', array('parentid' => $directoryid,
                                                                        'mapping' => self::MAPPING_AUTOMATIC));

        // Move the category parents, as needed (checking the old parents were 'oldcategoryid').
        foreach ($dirstomove as $dirtomove) {
            if (!$dirtomove->categoryid) {
                continue; // Not yet mapped - nothing to do.
            }

            $category = $DB->get_record('course_categories', array('id' => $dirtomove->categoryid), 'id, parent', MUST_EXIST);
            if ($category->parent != $oldcategoryid) {
                throw new campusconnect_directorytree_exception("move_category: found automatic directory {$dirtomove->id} where category parent != old category");
            }
            $category->parent = $newcategoryid;
            $DB->update_record('course_categories', $category);
        }

        // TODO - uncomment this code once 'course' import is implemented
        /*
        // Move any courses within the root directory.
        $coursestomove = $DB->get_records('local_campusconnect_course', array('parentid' => $rootid));
        foreach ($coursestomove as $coursetomove) {
            $course = $DB->get_record('course', array('id' => $coursetomove->id), 'id, category', MUST_EXIST);
            if ($course->category != $oldcategoryid) {
                throw new campusconnect_directorytree_exception("move_root_category: found course $courseid in root directory where category != root directory category");
            }
            $course->category = $newcategoryid;
            $DB->update_record('course', $course);
        }
        */

        // Tidy up the sort order, course count and category path fields.
        fix_course_sortorder();
    }

    /**
     * Check through the newly-created directories, to see if any matching categories
     * also need creating
     */
    public static function process_new_directories() {
        if (!self::$newdirs) {
            return;
        }

        if (!campusconnect_directorytree::should_create_empty_categories()) {
            return;
        }

        $dirtrees = campusconnect_directorytree::list_directory_trees();
        foreach (self::$newdirs as $rootid => $dirs) {
            foreach ($dirs as $dir) {
                $founddirtree = null;
                foreach ($dirtrees as $dirtree) {
                    if ($dirtree->get_root_id() == $dir->get_root_id()) {
                        $founddirtree = $dirtree;
                        break;
                    }
                }
                if (!$founddirtree) {
                    throw new campusconnect_directorytree_exception("Unable to find directory tree ".$dir->get_root_id()." for directory ".$dir->get_directory_id());
                }
                $mode = $founddirtree->get_category_id();
                $rootcategoryid = $founddirtree->get_category_id();
                if (($mode == campusconect_directorytree::MODE_MANUAL || $mode == campusconnect_directorytree::MODE_WHOLE) && $categoryid) {
                    $dir->create_category($rootcategoryid, false);
                }
            }
        }
        self::$newdirs = array();
        fix_course_sortorder();
    }
}