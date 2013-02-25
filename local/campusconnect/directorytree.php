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
global $CFG;
require_once($CFG->dirroot.'/local/campusconnect/log.php');

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

    /** @var int $recordid */
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

    public function __construct(stdClass $data = null) {
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

    public function is_deleted() {
        return ($this->mappingmode == self::MODE_DELETED);
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
     * @param object $data - record from the database
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
     * @return mixed string | null - error string if there is a problem
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
        if ($this->mappingmode == self::MODE_DELETED) {
            throw new coding_exception("Cannot unmap deleted directory trees");
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
            //throw new coding_exception("ECS updating directory tree that is marked as deleted");
            // Not sure how it ended up being marked as deleted, but try to resurrect it now.
            $this->update_field('mappingmode', self::MODE_PENDING);
        }
    }

    /**
     * Mark the directory tree as deleted
     */
    public function delete() {
        global $CFG;
        campusconnect_directory::delete_root_directory($this->rootid);
        $this->update_field('mappingmode', self::MODE_DELETED);

        require_once($CFG->dirroot.'/local/campusconnect/notify.php');
        campusconnect_notification::queue_message($this->ecsid,
                                                  campusconnect_notification::MESSAGE_DIRTREE,
                                                  campusconnect_notification::TYPE_DELETE,
                                                  $this->rootid);
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
     * @return int[] of directoryid => categoryid
     */
    public function list_all_mappings() {
        $rootmapping = new stdClass();
        $rootmapping->category = intval($this->categoryid);
        $rootmapping->canunmap = true;
        $rootmapping->canmap = true;
        $ret = array($this->rootid => $rootmapping);
        $dirs = campusconnect_directory::get_directories($this->rootid);
        foreach ($dirs as $dir) {
            $mapping = new stdClass();
            $mapping->category = intval($dir->get_category_id());
            $mapping->canunmap = $dir->can_unmap();
            $mapping->canmap = $dir->can_map();
            $mapping->dirid = $dir->get_directory_id();
            $ret[$mapping->dirid] = $mapping;
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
     * @param bool $includedeleted set to true to include deleted trees
     * @return campusconnect_directorytree[]
     */
    public static function list_directory_trees($includedeleted = false) {
        global $DB;

        if ($includedeleted) {
            $trees = $DB->get_records('local_campusconnect_dirroot');
        } else {
            $trees = $DB->get_records_select('local_campusconnect_dirroot', 'mappingmode <> ?', array(self::MODE_DELETED));
        }
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
     * If the directory tree format matches the old schema, then update it to the new schema
     * @param $directories
     */
    public static function convert_from_old_schema($directories) {
        if (isset($directories->nodes)) {
            // New schema - push a root node into the list of nodes for easier processing
            $node = (object)array(
                'id' => $directories->rootID,
                'title' => $directories->directoryTreeTitle,
                'term' => isset($directories->term) ? $directories->term : null,
                'parent' => (object)array(
                    'id' => 0,
                )
            );
            array_unshift($directories->nodes, $node);
        } else {
            // Old schema - create 'nodes' array from the single directory structure
            $node = (object)array(
                'id' => $directories->id,
                'title' => $directories->title,
                'parent' => (object)array(
                    'id' => $directories->parent->id
                )
            );
            if (isset($directories->order)) {
                $node->order = $directories->order;
            }
            if (isset($directories->term)) {
                $node->term = $directories->term;
            }
            if (isset($directories->parent->title)) {
                $node->parent->title = $directories->parent->title;
            }
            $directories->nodes = array($node);
        }
    }

    /**
     * Full update of all directory trees from ECS
     * @param campusconnect_ecssettings $ecssettings
     * @return object an object containing: ->created = array of resourceids created
     *                            ->updated = array of resourceids updated
     *                            ->deleted = array of resourceids deleted
     */
    public static function refresh_from_ecs(campusconnect_ecssettings $ecssettings) {
        global $DB;

        $ret = (object)array('created' => array(), 'updated' => array(), 'deleted' => array(), 'errors' => array());

        if (!self::enabled()) {
            return $ret; // Mapping disabled.
        }

        /** @var $cms campusconnect_participantsettings */
        if (! $cms = campusconnect_participantsettings::get_cms_participant()) {
            return $ret;
        }

        if ($cms->get_ecs_id() != $ecssettings->get_id()) {
            return $ret; // Not refreshing the ECS the CMS is connected to.
        }

        // Gather directory changes from the ECS server.
        if (!$ecssettings->is_enabled()) {
            return $ret; // Ignore disabled ECS.
        }

        $trees = self::list_directory_trees(true);
        /** @var $currenttrees campusconnect_directorytree[] */
        $currenttrees = array();
        foreach ($trees as $tree) {
            $currenttrees[$tree->get_root_id()] = $tree;
        }
        unset($trees);

        $connect = new campusconnect_connect($ecssettings);
        $resources = $connect->get_resource_list(campusconnect_event::RES_DIRECTORYTREE);
        foreach ($resources->get_ids() as $resourceid) {
            $directories = $connect->get_resource($resourceid, campusconnect_event::RES_DIRECTORYTREE);

            if (!$directories) {
                // Resource failed to download - not sure why that would ever happen, but just skip it.
                $ret->errors[] = get_string('faileddownload', 'local_campusconnect',
                                            campusconnect_event::RES_DIRECTORYTREE.'/'.$resourceid);
                continue;
            }

            if (is_array($directories)) {
                $directories = reset($directories);
            }
            self::convert_from_old_schema($directories); // Handle any directory trees matching the old version of the schema

            foreach ($directories->nodes as $directory) {
                if ($directory->parent->id) {
                    // Not a root directory.
                    campusconnect_directory::check_update_directory($resourceid, $directory, $directories->rootID);
                    continue;
                }

                if ($directory->id != $directories->rootID) {
                    throw new campusconnect_directorytree_exception("Root directory id ($directory->id) does not match the rootID ($directories->rootID)");
                }
                if ($directory->title != $directories->directoryTreeTitle) {
                    throw new campusconnect_directorytree_exception("Root directory title ($directory->title) does not match the directoryTreeTitle ($directories->directoryTreeTitle)");
                }

                if (array_key_exists($directory->id, $currenttrees)) {
                    // Update existing tree.
                    $currenttrees[$directory->id]->set_still_exists(); // So we can track any trees that no longer exist on ECS.
                    $currenttrees[$directory->id]->set_title($directory->title);
                    $ret->updated[] = $currenttrees[$directory->id]->resourceid;
                } else {
                    // Create new tree.
                    $newtree = new campusconnect_directorytree();
                    $newtree->create($resourceid, $directory->id, $directory->title, $cms->get_ecs_id(), $cms->get_mid());
                    $currenttrees[$newtree->get_root_id()] = $newtree;
                    $ret->created[] = $newtree->resourceid;
                }
            }
        }

        // Check if any new categories need to be created.
        campusconnect_directory::process_new_directories();

        // Update any trees that no longer exist on the ECS.
        foreach ($currenttrees as $tree) {
            if (!$tree->still_exists() && !$tree->is_deleted()) {
                $tree->delete(); // Will also delete any contained directories.
                $ret->deleted[] = $tree->resourceid;
            } else {
                campusconnect_directory::remove_missing_directories($tree->get_root_id());
            }
        }

        // Look for any directories mapped on to categories that no longer exist.
        self::check_all_mappings();
        return $ret;
    }

    /**
     * Used by the ECS event processing to create new directories / directory trees
     * @param int $resourceid - the ID on the ECS server
     * @param campusconnect_ecssettings $ecssettings - the ECS being connected to
     * @param object|object[] $directories - the resource data from ECS
     * @param campusconnect_details $details - the metadata for the resource on the ECS
     * @return bool true if successful
     */
    public static function create_directory($resourceid, campusconnect_ecssettings $ecssettings, $directories, campusconnect_details $details) {
        global $DB;

        $mid = $details->get_sender_mid();
        $ecsid = $ecssettings->get_id();
        $cms = campusconnect_participantsettings::get_cms_participant();
        if (!$cms || $cms->get_mid() != $mid || $cms->get_ecs_id() != $ecsid) {
            campusconnect_log::add("Warning: received create directory ({$resourceid}) event from non-CMS participant");
            return true;
        }

        if (is_array($directories)) {
            $directories = reset($directories);
        }
        self::convert_from_old_schema($directories);

        foreach ($directories->nodes as $directory) {
            $isdirectorytree = $directory->parent->id ? false : true;
            if ($isdirectorytree) {
                if ($DB->record_exists('local_campusconnect_dirroot', array('rootid' => $directories->rootID))) {
                    $toupdate = (object)array(
                        'rootID' => $directories->rootID,
                        'directoryTreeTitle' => $directories->directoryTreeTitle,
                        'nodes' => array($directory)
                    );
                    self::update_directory($resourceid, $ecssettings, $toupdate, $details);
                    continue;
                    //throw new campusconnect_directorytree_exception("Cannot create a directory tree root node {$directory->rootID} - it already exists.");
                }

                $tree = new campusconnect_directorytree();
                $tree->create($resourceid, $directories->rootID, $directory->title, $ecsid, $mid);

            } else {
                if ($DB->record_exists('local_campusconnect_dir', array('directoryid' => $directory->id))) {
                    $toupdate = (object)array(
                        'rootID' => $directories->rootID,
                        'directoryTreeTitle' => $directories->directoryTreeTitle,
                        'nodes' => array($directory)
                    );
                    self::update_directory($resourceid, $ecssettings, $toupdate, $details);
                    continue;
                    //throw new campusconnect_directorytree_exception("Cannot create a directory tree {$directory->id} - it already exists.");
                }

                $dir = new campusconnect_directory();
                if (empty($directory->order)) {
                    $directory->order = null;
                }
                $dir->create($resourceid, $directories->rootID, $directory->id, $directory->parent->id, $directory->title, $directory->order);
            }
        }

        return true;
    }

    /**
     * Used by the ECS event processing to update directories / directory trees
     * @param int $resourceid - the ID on the ECS server
     * @param campusconnect_ecssettings $ecssettings - the ECS being connected to
     * @param object|object[] $directories - the resource data from ECS
     * @param campusconnect_details $details - the metadata for the resource on the ECS
     * @return bool true if successful
     */
    public static function update_directory($resourceid, campusconnect_ecssettings $ecssettings, $directories, campusconnect_details $details) {
        global $DB;

        $mid = $details->get_sender_mid();
        $ecsid = $ecssettings->get_id();
        $cms = campusconnect_participantsettings::get_cms_participant();
        if (!$cms || $cms->get_mid() != $mid || $cms->get_ecs_id() != $ecsid) {
            campusconnect_log::add("Warning: received update directory ({$resourceid}) event from non-CMS participant");
            return true;
        }

        if (is_array($directories)) {
            $directories = reset($directories);
        }
        self::convert_from_old_schema($directories);

        foreach ($directories->nodes as $directory) {
            $isdirectorytree = $directory->parent->id ? false : true;
            if ($isdirectorytree) {
                if (!$currdirtree = $DB->get_record('local_campusconnect_dirroot', array('rootid' => $directories->rootID))) {
                    $tocreate = (object)array(
                        'rootID' => $directories->rootID,
                        'directoryTreeTitle' => $directories->directoryTreeTitle,
                        'nodes' => array($directory)
                    );
                    self::create_directory($resourceid, $ecssettings, $tocreate, $details);
                    continue;
                }

                $tree = new campusconnect_directorytree($currdirtree);
                $tree->set_title($directory->title);

            } else {
                if (!$currdir = $DB->get_record('local_campusconnect_dir', array('directoryid' => $directory->id))) {
                    $tocreate = (object)array(
                        'rootID' => $directories->rootID,
                        'directoryTreeTitle' => $directories->directoryTreeTitle,
                        'nodes' => array($directory)
                    );
                    self::create_directory($resourceid, $ecssettings, $tocreate, $details);
                    continue;
                }


                if (empty($directory->order)) {
                    $directory->order = null;
                }
                $dir = new campusconnect_directory($currdir);
                $dir->check_parent_id($directory->parent->id);
                $dir->set_title($directory->title);
                $dir->set_order($directory->order);
            }
        }

        return true;
    }

    /**
     * Used by the ECS event processing to delete directories / directory trees
     * @param int $resourceid - the ID on the ECS server
     * @param campusconnect_ecssettings $ecssettings - the ECS being connected to
     * @return bool true if successful
     */
    public static function delete_directory($resourceid, campusconnect_ecssettings $ecssettings) {
        global $DB;

        $cms = campusconnect_participantsettings::get_cms_participant();
        if (!$cms || $ecssettings->get_id() != $cms->get_ecs_id()) {
            campusconnect_log::add("Warning: received delete directory ({$resourceid}) event from non-CMS participant");
            return true;
        }

        $dirtrees = $DB->get_records('local_campusconnect_dirroot', array('resourceid' => $resourceid));
        foreach ($dirtrees as $dirtree) {
            $dirtree = new campusconnect_directorytree($dirtree);
            $dirtree->delete();
        }

        $dirs = $DB->get_records('local_campusconnect_dir', array('resourceid' => $resourceid));
        foreach ($dirs as $dir) {
            $dir = new campusconnect_directory($dir);
            $dir->delete();
        }

        return true;
    }

    /**
     * Go through the list of directories from the ECS and remove any local directories that have the same resource id,
     * but are not in the list from the ECS
     * @param int $resourceid the resourceid that these directories are associated with
     * @param campusconnect_ecssettings $ecssettings
     * @param object|object[] $directories the list of directories from the ECS
     * @param campusconnect_details $details
     */
    public static function delete_missing_directories($resourceid, campusconnect_ecssettings $ecssettings, $directories, campusconnect_details $details) {
        global $DB;

        $mid = $details->get_sender_mid();
        $ecsid = $ecssettings->get_id();
        $cms = campusconnect_participantsettings::get_cms_participant();
        if (!$cms || $cms->get_mid() != $mid || $cms->get_ecs_id() != $ecsid) {
            campusconnect_log::add("Warning: received update directory ({$resourceid}) event from non-CMS participant");
            return;
        }

        // Get the details of the existing directories / trees in Moodle
        $existingtreesdb = $DB->get_records('local_campusconnect_dirroot', array('resourceid' => $resourceid,
                                                                                'ecsid' => $ecsid, 'mid' => $mid));
        $existingdirsdb = $DB->get_records('local_campusconnect_dir', array('resourceid' => $resourceid));
        /** @var campusconnect_directorytree[] $existingtrees */
        $existingtrees = array();
        /** @var campusconnect_directory[] $existingdirs */
        $existingdirs = array();
        foreach ($existingtreesdb as $existingtreedb) {
            $existingtrees[$existingtreedb->rootid] = new campusconnect_directorytree($existingtreedb);
        }
        foreach ($existingdirsdb as $existingdirdb) {
            $existingdirs[$existingdirdb->directoryid] = new campusconnect_directory($existingdirdb);
        }
        if (is_array($directories)) {
            $directories = reset($directories);
        }
        self::convert_from_old_schema($directories);
        unset($existingtreesdb, $existingdirsdb);

        // Loop through all the directories / trees in this resource and match them up with the existing directories in Moodle
        foreach ($directories->nodes as $directory) {
            $isdirectorytree = $directory->parent->id ? false : true;
            if ($isdirectorytree) {
                if (!isset($existingtrees[$directories->rootID])) {
                    throw new coding_exception("delete_missing_directories - found a directory tree {$directories->rootID} in the resource that does not exist in Moodle (after doing the update)");
                }
                $existingtrees[$directories->rootID]->set_still_exists();
            } else {
                if (!isset($existingdirs[$directory->id])) {
                    throw new coding_exception("delete_missing_directories - found a directory {$directories->id} in the resource that does not exist in Moodle (after doing the update)");
                }
                $existingdirs[$directory->id]->set_still_exists();
            }
        }

        // Delete any trees / directories no longer found in this resource
        foreach ($existingtrees as $existingtree) {
            if (!$existingtree->still_exists()) {
                $existingtree->delete();
            }
        }
        foreach ($existingdirs as $existingdir) {
            if (!$existingdir->still_exists()) {
                $existingdir->delete();
            }
        }
    }

    public static function check_all_mappings() {
        global $DB;

        // Check all (non-deleted) directory tree mappings.
        $categoryids = array();
        $trees = $DB->get_records_select('local_campusconnect_dirroot', 'mappingmode <> ?', array(self::MODE_DELETED));
        /** @var $dirtrees campusconnect_directorytree[] */
        $dirtrees = array();
        foreach ($trees as $tree) {
            $dirtree = new campusconnect_directorytree($tree);
            if ($catid = $dirtree->get_category_id()) {
                $dirtrees[] = $dirtree;
                $categoryids[] = $catid;
            }
        }
        $categories = $DB->get_records_list('course_categories', 'id', $categoryids, 'id', 'id');
        foreach ($dirtrees as $tree) {
            if ($tree->get_category_id()) {
                if (!array_key_exists($tree->get_category_id(), $categories)) {
                    // Looks like the category has been deleted - clear the mapping.
                    $tree->unmap_category();
                }
            }
        }

        // Check all directory mappings.
        $dbdirs = $DB->get_records('local_campusconnect_dir');
        /** @var $dirs campusconnect_directory[] */
        $dirs = array();
        $categoryids = array();
        foreach ($dbdirs as $dbdir) {
            $dir = new campusconnect_directory($dbdir);
            if ($catid = $dir->get_category_id()) {
                $dirs[] = $dir;
                $categoryids[] = $catid;
            }
        }
        $categories = $DB->get_records_list('course_categories', 'id', $categoryids, 'id', 'id, sortorder');
        /** @var $recreate campusconnect_directory[] */
        $recreate = array();
        foreach ($dirs as $dir) {
            if ($dir->get_category_id()) {
                if (!array_key_exists($dir->get_category_id(), $categories)) {
                    // Directory was mapped onto a category that no longer exists.
                    if ($dir->clear_deleted_category()) {
                        $recreate[] = $dir;
                    }
                }
            }
        }
        // Try to recreate the categories for any automatically mapped directories that
        // previously had categories.
        $fixorder = false;
        if ($recreate) {
            foreach ($recreate as $dir) {
                $dirtree = $dir->get_directory_tree();
                $dir->create_category($dirtree->get_category_id(), false);
            }
            $fixorder = true;
        }

        // Update the sort order for all categories, if selected
        foreach ($dirtrees as $dirtree) {
            if ($dirtree->should_take_over_position()) {
                $changes = campusconnect_directory::sort_categories($dirtree->rootid, $dirs, $categories);
                $fixorder = $fixorder || $changes;
            }
            if ($dirtree->should_take_over_allocation()) {
                $changes = campusconnect_course::sort_courses($dirtree->rootid);
                $fixorder = $fixorder || $changes;
            }
        }

        if ($fixorder) {
            fix_course_sortorder();
        }
    }

    /**
     * Returns the category that a given directory is mapped on to, creating the category if required and
     * fixing the mapping in place if it is a provisional mapping.
     * @param integer $directoryid the ID of the directory on the ECS
     * @return mixed integer | null the ID of the Moodle category to create the course in - null if mapping not available
     */
    public static function get_category_for_course($directoryid) {
        global $DB;

        if ($directoryid < 0) {
            // Unit testing bypass
            return $directoryid - 1;
        }

        $sql = "SELECT dr.*
                  FROM {local_campusconnect_dirroot} dr
                  JOIN {local_campusconnect_dir} d ON d.rootid = dr.rootid
                 WHERE d.directoryid = :directoryid";
        $params = array('directoryid' => $directoryid);
        if (!$dirtreedata = $DB->get_record_sql($sql, $params)) {
            throw new campusconnect_directorytree_exception("Attempting to find category for non-existent directory $directoryid");
        }

        $dirtree = new campusconnect_directorytree($dirtreedata);
        /** @var $dir campusconnect_directory */
        $dir = $dirtree->get_directory($directoryid);
        return $dir->create_category($dirtree->get_category_id());
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
    /** @var $rootid int */
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
    /** @var campusconnect_directory[] $newdirs */
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

    public function can_unmap() {
        // Only pending-manual mappings can be remapped if the category id exists
        return ($this->mapping == self::MAPPING_MANUAL_PENDING);
    }

    public function can_map() {
        // Can only map if not already automatically mapped.
        if ($this->categoryid) {
            return ($this->mapping != self::MAPPING_AUTOMATIC);
        }
        return true;
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
        /** @var $dirs campusconnect_directory[] */
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
     * @return campusconnect_directory[]
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
     * @param string $radioname
     * @param int $selecteddir
     * @return string HTML fragment
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
        if ($sortorder == $this->sortorder) {
            return; // No update needed.
        }

        $this->set_field('sortorder', $sortorder);

        // Sortorder is automatically checked as part of the cron process, so any category moving will happen then.
    }

    /**
     * Mark as still existing on the ECS server, after the current update
     */
    public function set_still_exists() {
        $this->stillexists = true;
        if ($this->mapping == self::MAPPING_DELETED) {
            // Should not be the case, but resurrect the directory by setting the mapping to automatic
            $this->set_field('mapping', self::MAPPING_AUTOMATIC);
        }
    }

    /**
     * Map this directory onto a course category
     * @param int $categoryid
     * @return null|string - error message to display
     */
    public function map_category($categoryid) {
        global $DB;

        if (!$this->can_map()) {
            throw new campusconnect_directorytree_exception("Cannot map directory {$this->directoryid} as it is already mapped automatically");
        }

        if ($this->categoryid == $categoryid) {
            return null; // No change.
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

        return null;
    }

    /**
     * Unmap this directory from the category.
     */
    public function unmap_category() {
        if (!$this->can_unmap()) {
            throw new campusconnect_directorytree_exception("Unmapping of directories can only be done when mapping is pending - current mapping status: {$this->mapping}");
        }

        $this->set_field('categoryid', null);
        $this->set_field('mapping', self::MAPPING_AUTOMATIC);
    }

    /**
     * The category this directory is mapped on to no longer exists - find the
     * most appropriate fix, based on the mapping status.
     * @return bool - true if should attempt to recreate
     */
    public function clear_deleted_category() {
        if (!$this->categoryid) {
            return false;
        }
        if ($this->mapping == self::MAPPING_DELETED) {
            return false;
        }

        $this->set_field('categoryid', null);

        if ($this->mapping == self::MAPPING_AUTOMATIC) {
            return true;
        }

        $this->set_field('mapping', self::MAPPING_AUTOMATIC);

        return false;
    }

    /**
     * Create a category for the selected directory, along with any parent categories
     * that do not already exist.
     * @param int $rootcategoryid - ID of the category that the root directory is mapped on to
     * @param bool $fixsortorder optional - used to make sure fix_course_sortorder is only called once
     * @return int $id of the category created (or already allocated)
     */
    public function create_category($rootcategoryid, $fixsortorder = true) {
        global $DB;

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
     * @param int $resourceid
     * @param object $directory the details, direct from the ECS
     * @param int $rootid the id of the root of the directory tree
     * @return mixed campusconnect_directory | false : returns the directory,
     *                 if a new directory was created, false if it already existed
     */
    public static function check_update_directory($resourceid, $directory, $rootid) {
        $dirs = self::get_directories($rootid);
        foreach ($dirs as $dir) {
            if ($dir->get_directory_id() == $directory->id) {
                // Found directory - update it (if needed).
                $dir->check_parent_id($directory->parent->id);
                $dir->set_title($directory->title);
                if (isset($directory->order)) {
                    $dir->set_order($directory->order);
                }
                $dir->set_still_exists();
                return false;
            }
        }

        // Not found - create it.
        $order = isset($directory->order) ? $directory->order : null;
        $dir = new campusconnect_directory();
        $dir->create($resourceid, $rootid, $directory->id, $directory->parent->id, $directory->title, $order);
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

        /** @var $dirs campusconnect_directory[] */
        $dirs = self::get_directories($rootid);
        foreach ($dirs as $dir) {
            $dir->delete();
        }
    }

    /**
     * Get all the directories within the given directory tree
     * @param int $rootid
     * @return campusconnect_directory[]
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
     * @return campusconnect_directory[]
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
     * @param campusconnect_directorytree $dirtree
     * @param string $radioname - optional - if set, creates radio input elements for each item
     * @param null $selecteddir
     * @internal param int $rootid - the tree to output
     * @return string HTML of the lists
     */
    public static function output_directory_tree(campusconnect_directorytree $dirtree, $radioname, $selecteddir = null) {
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
            self::$newdirs[$rootid] = array();
        }
        self::$newdirs[$rootid][$recordid] = $directory;
    }

    /**
     * Create any sub-categories of the given directory tree that do not already
     * exist
     * @param int $rootid the root node of the directory tree
     * @param int $rootcategoryid the id of the Moodle category for the root node
     */
    public static function create_all_categories($rootid, $rootcategoryid) {
        $dirs = self::get_directories($rootid);
        foreach ($dirs as $dir) {
            if ($dir->mapping == self::MAPPING_DELETED) {
                continue; // Ignore any deleted directories when creating categories.
            }
            if (!$dir->get_category_id()) {
                $dir->create_category($rootcategoryid, false);
            }
        }

        fix_course_sortorder();
    }

    /**
     * Move all the courses and sub-directories when the root node of a directory tree
     * has been re-mapped
     * @param int $directoryid the root node of the directory tree
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

        // Move any courses within the root directory.
        $coursestomove = $DB->get_records('local_campusconnect_course', array('parentid' => $directoryid));
        foreach ($coursestomove as $coursetomove) {
            $course = $DB->get_record('course', array('id' => $coursetomove->id), 'id, category', MUST_EXIST);
            if ($course->category != $oldcategoryid) {
                throw new campusconnect_directorytree_exception("move_root_category: found course {$course->id} in root directory where category != root directory category");
            }
            $course->category = $newcategoryid;
            $DB->update_record('course', $course);
        }

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
        foreach (self::$newdirs as $dirs) {
            /** @var $dir campusconnect_directory */
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
                $mode = $founddirtree->get_mode();
                $rootcategoryid = $founddirtree->get_category_id();
                $createcategory = ($mode == campusconnect_directorytree::MODE_MANUAL);
                $createcategory = $createcategory || ($mode == campusconnect_directorytree::MODE_WHOLE && $rootcategoryid);
                if ($createcategory) {
                    $dir->create_category($rootcategoryid, false);
                }
            }
        }
        self::$newdirs = array();
        fix_course_sortorder();
    }

    /**
     * Make sure that the sortorder of the categories matches the sort order of the directories.
     * @param int $rootid
     * @param campusconnect_directory[] $dirs
     * @param stdClass[] $categories
     * @return bool true if changes made
     */
    public static function sort_categories($rootid, $dirs, $categories) {
        global $DB;
        $updated = false;
        $sorteddirs = array();
        foreach ($dirs as $dir) {
            if ($dir->rootid != $rootid) {
                continue;
            }
            if ($dir->get_status() != self::STATUS_MAPPED_AUTOMATIC) {
                continue; // Only automatically mapped categories should be sorted
            }
            if (!isset($sorteddirs[$dir->parentid])) {
                $sorteddirs[$dir->parentid] = array();
            }
            $sortorder = $dir->sortorder;
            while (isset($sorteddirs[$dir->parentid][$sortorder])) {
                // Already a dir with the same parent and sortorder - adjust the sortorder until we avoid the conflict
                $sortorder++;
            }
            if ($sortorder != $dir->sortorder) {
                $dir->set_order($sortorder); // Save the updated sortorder
            }
            $sorteddirs[$dir->parentid][$sortorder] = $dir;
        }
        foreach ($sorteddirs as $sdirs) {
            /** @var $sdirs campusconnect_directory[]  */
            ksort($sdirs);
            $lastsort = -1;
            foreach ($sdirs as $dir) {
                $catid = $dir->get_category_id();
                if (!$catid) {
                    continue;
                }
                if (!isset($categories[$catid])) {
                    continue; // Not sure this should happen, but will skip for now and hope it is fixed on the next cron.
                }
                $catsort = $categories[$catid]->sortorder;
                if ($catsort <= $lastsort) {
                    $catsort = $lastsort + 1;
                    $DB->set_field('course_categories', 'sortorder', $catsort, array('id' => $catid));
                    $updated = true;
                }
                $lastsort = $catsort;
            }
        }

        return $updated;
    }
}

