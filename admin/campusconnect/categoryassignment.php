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
 * Settings page for campus connect
 *
 * @package    admin_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/local/campusconnect/connect.php');
require_once($CFG->dirroot.'/admin/campusconnect/lib.php');

defined('MOODLE_INTERNAL') || die();

$PAGE->set_url('/admin/campusconnect/categoryassignment.php');
$PAGE->set_context(context_system::instance());

admin_externalpage_setup('campusconnectcategoryassignment');

require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_campusconnect'));

$categories = get_categories();
foreach ($categories as $category) {
    $cats[$category->id] = $category->name;
}

print get_string('currentassignments', 'local_campusconnect');

print '<table class="generaltable"><thead><tr><th class="header">Current Assignments</th></tr></thead></table>';

print '<h2>'.get_string('newassignment', 'local_campusconnect').'</h2>';

?>
<form action="" method="POST" id="currentassignments" class="mform">
    <div class="settingsform">
        <fieldset class="clearfix" name="connectionsettings">
            <legend><?php print get_string('categoryassignment', 'local_campusconnect') ?></legend>
            <div class="fcontainer clearfix">
                <div class="fitem clearfix">
                    <div class="fitemtitle">
                        <label for="importcat">
                            <?php print get_string('importcat', 'local_campusconnect') ?>
                            <?php print "<img src='".$OUTPUT->pix_url('req')."' alt='req' />" ?>
                        </label>
                    </div>
                    <div class="felement ftext">
                        <select name="importcat">
                            <?php print_option($cats, $_POST); ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="fcontainer clearfix">
                <div class="fitem clearfix">
                    <div class="fitemtitle">
                        <label for="attrname">
                            <?php print get_string('attributename', 'local_campusconnect') ?>
                            <?php print "<img src='".$OUTPUT->pix_url('req')."' alt='req' />" ?>
                        </label>
                    </div>
                    <div class="felement ftext">
                        <select name="attrname">
                            <?php print_option(array('1' => 'Community', '2' => 'Participant ID'), $_POST); ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="fitem clearfix">
                            <div class="fitemtitle">
                                <label for="maptype">
                                    <?php print get_string('mappingtype', 'local_campusconnect') ?>
                                    <?php print "<img src='".$OUTPUT->pix_url('req')."' alt='req' />" ?>
                                </label>
                            </div>
                            <div class="felement ftext">
                                <div class="fitem clearfix">
                                    &nbsp;
                                    <input type="radio" onclick="cc_switchAuthCert();"
                                        <?php if ($post['cc_auth'] == 'cc_auth_cert' || !isset($post['cc_auth'])) {
                                            print 'checked';
                                        } ?>
                                        name="cc_auth" value="cc_auth_cert" />
                                    <?php print get_string('fixedvalue', 'local_campusconnect') ?>
                                    <div id="cc_auth_cert"
                                    <?php if ($post['cc_auth'] == 'cc_auth_user') {
                                        print 'style="display: none"';
                                    } ?> class="clearfix">
                                        <div class="fitem clearfix">
                                            <div class="fitemtitle">
                                                <label for="fixedvalue">
                                                    <?php print get_string('attribute', 'local_campusconnect') ?>
                                                </label>
                                            </div>
                                            <div class="felement ftext">
                                                <input type="text" name="fixedvalue"/>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="fitem clearfix">
                                    &nbsp;
                                    <input type="radio" onclick="cc_switchAuthUser();"
                                        <?php if ($post['cc_auth'] == 'cc_auth_user') {
                                            print 'checked';
                                        } ?>
                                        name="cc_auth" value="cc_auth_user" />
                                    <?php print get_string('daterange', 'local_campusconnect') ?>
                                    <div id="cc_auth_user"
                                        <?php if ($post['cc_auth'] == 'cc_auth_cert' || !isset($post['cc_auth'])) {
                                            print 'style="display:none"';
                                        } ?> class="clearfix">
                                        <div class="fitem felement fselect clearfix">
                                            <label>Day</label>
                                            <select name="startdate[day]" id="id_startdate_day">
                                                <?php print_option(range(1, 31), $_POST); ?>
                                            </select>&nbsp;
                                            <label>Month</label>
                                            <select name="startdate[month]" id="id_startdate_month">
                                                <?php for ($i = 1; $i <= 12; $i++) {
                                                        $months[$i] = date('F', strtotime("{$i}/01/2000"));
                                                    }
                                                    print_option($months , $_POST);
                                                ?>
                                            </select>&nbsp;
                                            <label>Year</label>
                                            <select name="startdate[year]" id="id_startdate_year">
                                                <?php print_option(range(2000, 2025), $_POST, 2012); ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <script type="text/javascript">
                                    function cc_switchAuthCert() {
                                        document.getElementById('cc_auth_cert').style.display='block';
                                        document.getElementById('cc_auth_user').style.display='none';
                                    }

                                    function cc_switchAuthUser() {
                                        document.getElementById('cc_auth_cert').style.display='none';
                                        document.getElementById('cc_auth_user').style.display='block';
                                    }
                                </script>
                            </div>
        </fieldset>
    </div>
</form>
<?php

echo $OUTPUT->footer();