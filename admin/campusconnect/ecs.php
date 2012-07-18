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
 * ECS settings page for campus connect
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

require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));

$PAGE->set_url('/admin/campusconnect/ecs.php');
$PAGE->set_context(context_system::instance());

global $CFG, $DB;

if (isset($_GET['fn'])) {
    $function = $_GET['fn'];
    admin_externalpage_setup('allecs');
}

if (isset($_GET['delete'])) {
    $deleteid = $_GET['delete'];
    $ecssettings = new campusconnect_ecssettings($_GET['id']);
    admin_externalpage_setup('ecs'.$_GET['delete']);

}

if (isset($_GET['id'])) {
    $ecssettings = new campusconnect_ecssettings($_GET['id']);
    admin_externalpage_setup('ecs'.$_GET['id']);
}

if (isset($_POST['addnewecs'])) {

    $toadd = array();
    $error = array();

    if (empty($_POST['name']) || empty($_POST['url']) || empty($_POST['port']) || !is_numeric($_POST['port'])) {
        $error['connection'] = 1;
    }

    if (!empty($_POST['name'])) {
        $toadd['name'] = $_POST['name'];
    }
    if (!empty($_POST['url'])) {
        $toadd['url'] = $_POST['url'];
    }
    if (!empty($_POST['port']) && is_numeric($_POST['port'])) {
        $toadd['url'] .= ':'.$_POST['port'];
    }

    if (isset($_POST['cc_auth'])) {
        if ($_POST['cc_auth'] == 'cc_auth_cert') {

            if (empty($_POST['cc_auth_cert']['clientcert'])|| empty($_POST['cc_auth_cert']['certkey']) ||
            empty($_POST['cc_auth_cert']['keypass']) || empty($_POST['cc_auth_cert']['cacert'])) {
                $error['certauth'] = 1;
            }

            $toadd['auth'] = 3;

            if (!empty($_POST['cc_auth_cert']['clientcert'])) {
                $toadd['certpath'] = $_POST['cc_auth_cert']['clientcert'];
            }
            if (!empty($_POST['cc_auth_cert']['certkey'])) {
                $toadd['keypath'] = $_POST['cc_auth_cert']['certkey'];
            }
            if (!empty($_POST['cc_auth_cert']['keypass'])) {
                $toadd['keypass'] = $_POST['cc_auth_cert']['keypass'];
            }
            if (!empty($_POST['cc_auth_cert']['cacert'])) {
                $toadd['cacertpath'] = $_POST['cc_auth_cert']['cacert'];
            }


        } else if ($_POST['cc_auth'] == 'cc_auth_user') {

            if (empty($_POST['cc_auth_user']['username']) || empty($_POST['cc_auth_user']['password'])) {
                $error['userauth'] = 1;
            }

            $toadd['auth'] = 2;

            if (!empty($_POST['cc_auth_user']['username'])) {
                $toadd['httpuser'] = $_POST['cc_auth_user']['username'];
            }
            if (!empty($_POST['cc_auth_user']['password'])) {
                $toadd['httppass'] = $_POST['cc_auth_user']['password'];
            }

        }
    }

    if (isset($_POST['pollingtime'])) {

        $time = $_POST['pollingtime']['mm']*60 + $_POST['pollingtime']['ss'];
        $toadd['crontime'] = $time;

    }

    if (empty($_POST['importid']) || !is_numeric($_POST['importid'])) {
        $error['category'] = 1;
    }

    if (!empty($_POST['importid']) && is_numeric($_POST['importid'])) {

        $toadd['importcategory'] = $_POST['importid'];

    }

    if (isset($_POST['roleassignment'])) {
        $toadd['importrole'] = $_POST['roleassignment'];
    }
    if (isset($_POST['duration'])) {
        $toadd['importperiod'] = $_POST['duration']['MM'];
    }

    if (!empty($_POST['usernotification'])) {
        $toadd['notifyusers'] = $_POST['usernotification'];
    }
    if (!empty($_POST['contentnotification'])) {
        $toadd['notifycontent'] = $_POST['contentnotification'];
    }
    if (!empty($_POST['coursenotification'])) {
        $toadd['notifycourses'] = $_POST['coursenotification'];
    }


    if (empty($error)) {

        $success = $DB->insert_record('local_campusconnect_ecs', $toadd);

    }

}

if (isset($_POST['saveexistingecs'])) {

    $tosave = array();
    $error = array();

    if (empty($_POST['name']) || empty($_POST['url']) || empty($_POST['port']) || !is_numeric($_POST['port'])) {
        $error['connection'] = 1;
    }

    if (!empty($_POST['name'])) {
        $tosave['name'] = $_POST['name'];
    }
    if (!empty($_POST['url'])) {
        $tosave['url'] = $_POST['url'];
    }
    if (!empty($_POST['port']) && is_numeric($_POST['port'])) {
        $tosave['url'] .= ':'.$_POST['port'];
    }

    if (isset($_POST['cc_auth'])) {
        if ($_POST['cc_auth'] == 'cc_auth_cert') {

            if (empty($_POST['cc_auth_cert']['clientcert'])|| empty($_POST['cc_auth_cert']['certkey']) ||
            empty($_POST['cc_auth_cert']['keypass']) || empty($_POST['cc_auth_cert']['cacert'])) {
                $error['certauth'] = 1;
            }

            $tosave['auth'] = 3;

            if (!empty($_POST['cc_auth_cert']['clientcert'])) {
                $tosave['certpath'] = $_POST['cc_auth_cert']['clientcert'];
            }
            if (!empty($_POST['cc_auth_cert']['certkey'])) {
                $tosave['keypath'] = $_POST['cc_auth_cert']['certkey'];
            }
            if (!empty($_POST['cc_auth_cert']['keypass'])) {
                $tosave['keypass'] = $_POST['cc_auth_cert']['keypass'];
            }
            if (!empty($_POST['cc_auth_cert']['cacert'])) {
                $tosave['cacertpath'] = $_POST['cc_auth_cert']['cacert'];
            }


        } else if ($_POST['cc_auth'] == 'cc_auth_user') {

            if (empty($_POST['cc_auth_user']['username']) || empty($_POST['cc_auth_user']['password'])) {
                $error['userauth'] = 1;
            }

            $tosave['auth'] = 2;

            if (!empty($_POST['cc_auth_user']['username'])) {
                $tosave['httpuser'] = $_POST['cc_auth_user']['username'];
            }
            if (!empty($_POST['cc_auth_user']['password'])) {
                $tosave['httppass'] = $_POST['cc_auth_user']['password'];
            }

        }
    }

    if (isset($_POST['pollingtime'])) {

        $time = $_POST['pollingtime']['mm']*60 + $_POST['pollingtime']['ss'];
        $tosave['crontime'] = $time;

    }

    if (empty($_POST['importid']) || !is_numeric($_POST['importid'])) {
        $error['category'] = 1;
    }

    if (!empty($_POST['importid']) && is_numeric($_POST['importid'])) {

        $tosave['importcategory'] = $_POST['importid'];

    }

    if (isset($_POST['roleassignment'])) {
        $tosave['importrole'] = $_POST['roleassignment'];
    }
    if (isset($_POST['duration'])) {
        $tosave['importperiod'] = $_POST['duration']['MM'];
    }

    if (!empty($_POST['usernotification'])) {
        $tosave['notifyusers'] = $_POST['usernotification'];
    }
    if (!empty($_POST['contentnotification'])) {
        $tosave['notifycontent'] = $_POST['contentnotification'];
    }
    if (!empty($_POST['coursenotification'])) {
        $tosave['notifycourses'] = $_POST['coursenotification'];
    }


    if (empty($error)) {
        $success = $ecssettings->save_settings($tosave);
    }

}


echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_campusconnect'));

if (isset($deleteid)) {
    print 'TODO';
    echo $OUTPUT->footer();
    exit;
}

if ((isset($function) && $function == 'add') || isset($_GET['id'])) {


    $roles = $DB->get_records('role');

    if (isset($_GET['id'])) {
        $settings = $ecssettings->get_settings();
        $post = array();
        $post['name'] = $settings->name;
        $post['url'] = substr($settings->url, 0, strpos($settings->url, ':'));
        $post['port'] = substr($settings->url, strpos($settings->url, ':')+1);
        if ($settings->auth == 2) {
            $post['cc_auth'] = 'cc_auth_user';
        }
        if ($settings->auth == 3) {
            $post['cc_auth'] = 'cc_auth_cert';
        }
        $post['cc_auth_cert']['clientcert'] = $settings->certpath;
        $post['cc_auth_cert']['certkey'] = $settings->keypath;
        $post['cc_auth_cert']['keypass'] = $settings->keypass;
        $post['cc_auth_cert']['cacert'] = $settings->cacertpath;
        $post['cc_auth_user']['username'] = $settings->httpuser;
        $post['cc_auth_user']['password'] = $settings->httppass;
        $minutes = floor($settings->crontime/60);
        $seconds = $settings->crontime - ($minutes*60);
        $post['pollingtime']['mm'] = $minutes;
        $post['pollingtime']['ss'] = $seconds;
        $post['importid'] = $settings->importcategory;
        $post['roleassignment'] = $settings->importrole;
        $post['duration']['MM'] = $settings->importperiod;
        $post['usernotification'] = $settings->notifyusers;
        $post['contentnotification'] = $settings->notifycontent;
        $post['coursenotification'] = $settings->notifycourses;
    }

    if ($function=='add' || !empty($error)) {
        $post = $_POST;
    }

    ?>
        <form action="" method="POST" id="addnewecs" class="mform">
            <div class="settingsform">
                <fieldset class="clearfix" name="connectionsettings">
                    <legend><?php print get_string('connectionsettings', 'local_campusconnect') ?></legend>
                    <div class="fcontainer clearfix">
                    <?php
                        if ($error['connection']) {
                            print '<span class="error">'
                                .get_string('pleasefilloutallrequiredfields', 'local_campusconnect').
                            '</span>';
                        }
                    ?>

                        <div class="fitem clearfix">
                            <div class="fitemtitle">
                                <label for="name">
                                    <?php print get_string('name', 'local_campusconnect') ?>
                                    <?php print "<img src='".$OUTPUT->pix_url('req')."' alt='req' />" ?>
                                </label>
                            </div>
                            <div class="felement ftext">
                                <input type="text" name="name" <?php print "value='{$post['name']}'"; ?> />
                            </div>
                        </div>
                        <div class="fitem clearfix">
                            <div class="fitemtitle">
                                <label for="url">
                                    <?php print get_string('url', 'local_campusconnect') ?>
                                    <?php print "<img src='".$OUTPUT->pix_url('req')."' alt='req' />" ?>
                                </label>
                            </div>
                            <div class="felement ftext">
                                <input type="text" name="url" <?php print "value='{$post['url']}'"; ?>/>
                            </div>
                        </div>
                        <div class="fitem clearfix">
                            <div class="fitemtitle">
                                <label for="protocol">
                                    <?php print get_string('protocol', 'local_campusconnect') ?>
                                    <?php print "<img src='".$OUTPUT->pix_url('req')."' alt='req' />" ?>
                                </label>
                            </div>
                            <div class="felement fselect">
                                <select id="protocol" name="protocol">
                                    <?php print_option(array('0'=>'HTTP', '1'=>'HTTPS'), $post['protocol']) ?>
                                </select>
                            </div>
                        </div>
                        <div class="fitem clearfix">
                            <div class="fitemtitle">
                                <label for="port">
                                    <?php print get_string('port', 'local_campusconnect') ?>
                                    <?php print "<img src='".$OUTPUT->pix_url('req')."' alt='req' />" ?>
                                </label>
                            </div>
                            <div class="felement ftext">
                                <input type="text" name="port" <?php print "value='{$post['port']}'"; ?>/>
                            </div>
                        </div>
                        <div class="fitem clearfix">
                            <div class="fitemtitle">
                                <label for="port">
                                    <?php print get_string('authenticationtype', 'local_campusconnect') ?>
                                </label>
                            </div>
                            <div class="felement ftext">
                                <div class="fitem clearfix">
                                    &nbsp;
                                    <input type="radio" onclick="cc_switchAuthCert();"
                                                            <?php
                                                            if ($post['cc_auth'] == 'cc_auth_cert' || !isset($post['cc_auth'])) {
                                                                print 'checked';
                                                            }
                                                            ?>
                                    name="cc_auth" value="cc_auth_cert" />
                                    <?php print get_string('certificatebase', 'local_campusconnect') ?>
                                    <div id="cc_auth_cert"
                                        <?php
                                            if ($post['cc_auth'] == 'cc_auth_user') {
                                                print 'style="display: none"';
                                            }
                                        ?> class="clearfix">
                                        <?php
                                            if ($error['certauth']) {
                                                print '<span class="error">'.
                                                    get_string('pleasefilloutallrequiredfields', 'local_campusconnect').
                                                '</span>';
                                            }
                                        ?>
                                        <div class="fitem clearfix">
                                            <div class="fitemtitle">
                                                <label for="clientcert">
                                                    <?php print get_string('clientcertificate', 'local_campusconnect') ?>
                                                    <?php print "<img src='".$OUTPUT->pix_url('req')."' alt='req' />" ?>
                                                </label>
                                            </div>
                                            <div class="felement ftext">
                                                <input type="text" name="cc_auth_cert[clientcert]"
                                                    <?php print "value='{$post['cc_auth_cert']['clientcert']}'"; ?>/>
                                            </div>
                                        </div>
                                        <div class="fitem clearfix">
                                            <div class="fitemtitle">
                                                <label for="certkey">
                                                    <?php print get_string('certificatekey', 'local_campusconnect') ?>
                                                    <?php print "<img src='".$OUTPUT->pix_url('req')."' alt='req' />" ?>
                                                </label>
                                            </div>
                                            <div class="felement ftext">
                                                <input type="text" name="cc_auth_cert[certkey]"
                                                    <?php print "value='{$post['cc_auth_cert']['certkey']}'"; ?>/>
                                            </div>
                                        </div>
                                        <div class="fitem clearfix">
                                            <div class="fitemtitle">
                                                <label for="keypass">
                                                    <?php print get_string('keypassword', 'local_campusconnect') ?>
                                                    <?php print "<img src='".$OUTPUT->pix_url('req')."' alt='req' />" ?>
                                                </label>
                                            </div>
                                            <div class="felement ftext">
                                                <input type="password" name="cc_auth_cert[keypass]"
                                                    <?php print "value='{$post['cc_auth_cert']['keypass']}'"; ?>/>
                                            </div>
                                        </div>
                                        <div class="fitem clearfix">
                                            <div class="fitemtitle">
                                                <label for="cacert">
                                                    <?php print get_string('cacertificate', 'local_campusconnect') ?>
                                                    <?php print "<img src='".$OUTPUT->pix_url('req')."' alt='req' />" ?>
                                                </label>
                                            </div>
                                            <div class="felement ftext">
                                                <input type="text" name="cc_auth_cert[cacert]"
                                                    <?php print "value='{$post['cc_auth_cert']['cacert']}'"; ?>/>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="fitem clearfix">
                                    &nbsp;
                                    <input type="radio" onclick="cc_switchAuthUser();"
                                    <?php
                                        if ($post['cc_auth'] == 'cc_auth_user') {
                                            print 'checked';
                                        }
                                    ?> name="cc_auth" value="cc_auth_user" />
                                    <?php print get_string('usernamepassword', 'local_campusconnect') ?>
                                    <div id="cc_auth_user"
                                        <?php
                                            if ($post['cc_auth'] == 'cc_auth_cert' || !isset($post['cc_auth'])) {
                                                print 'style="display:none"';
                                            }
                                        ?> class="clearfix">
                                        <?php
                                            if ($error['userauth']) {
                                                print '<span class="error">'
                                                    .get_string('pleasefilloutallrequiredfields', 'local_campusconnect').
                                                '</span>';
                                            }
                                        ?>
                                        <div class="fitem clearfix">
                                            <div class="fitemtitle">
                                                <label for="username">
                                                    <?php print get_string('username', 'local_campusconnect') ?>
                                                    <?php print "<img src='".$OUTPUT->pix_url('req')."' alt='req' />" ?>
                                                </label>
                                            </div>
                                            <div class="felement ftext">
                                                <input type="text" name="cc_auth_user[username]"
                                                    <?php print "value='{$post['cc_auth_user']['username']}'"; ?>/>
                                            </div>
                                        </div>
                                        <div class="fitem clearfix">
                                            <div class="fitemtitle">
                                                <label for="password">
                                                    <?php print get_string('password', 'local_campusconnect') ?>
                                                    <?php print "<img src='".$OUTPUT->pix_url('req')."' alt='req' />" ?>
                                                </label>
                                            </div>
                                            <div class="felement ftext">
                                                <input type="text" name="cc_auth_user[password]"
                                                    <?php print "value='{$post['cc_auth_user']['password']}'"; ?> />
                                            </div>
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
                        </div>
                    </div>
                </fieldset>
                <fieldset class="clearfix" name="localsettings">
                    <legend><?php print get_string('localsettings', 'local_campusconnect') ?></legend>
                    <div class="fcontainer clearfix">
                    <?php
                        if ($error['category']) {
                            print '<span class="error">'
                                .get_string('pleasefilloutallrequiredfields', 'local_campusconnect').
                            '</span>';
                        }
                    ?>

                        <div class="fitem clearfix">
                            <div class="fitemtitle">
                                <label for="pollingtime">
                                    <?php print get_string('pollingtime', 'local_campusconnect') ?>
                                    <?php print "<img src='".$OUTPUT->pix_url('req')."' alt='req' />" ?>
                                </label>
                            </div>
                            <div class="felement fselect">
                                <?php print get_string('minutes', 'local_campusconnect') ?>:
                                <select name="pollingtime[mm]" size="0">
                                     <?php
                                         print_option(range(0, 59), $post['pollingtime']["mm"]);
                                     ?>
                                </select>
                                &nbsp;
                                <?php print get_string('seconds', 'local_campusconnect') ?>
                                    : <select name="pollingtime[ss]" size="0">
                                     <?php
                                         print_option(range(0, 59), $post['pollingtime']["ss"]);
                                     ?>
                                </select>
                            </div>
                            <div class="felement fstatic">
                                <p><?php print get_string('pollingtimedesc', 'local_campusconnect') ?></p>
                            </div>
                        </div>
                        <div class="fitem clearfix">
                            <div class="fitemtitle">
                                <label for="importid">
                                    <?php print get_string('categoryid', 'local_campusconnect') ?>
                                    <?php print "<img src='".$OUTPUT->pix_url('req')."' alt='req' />" ?>
                                    </label>
                            </div>
                            <div class="felement ftext">
                                <input type="text" name="importid" <?php print "value='{$post['importid']}'"; ?> />
                            </div>
                            <div class="felement fstatic">
                                <p><?php print get_string('categoryiddesc', 'local_campusconnect') ?></p>
                            </div>
                        </div>
                    </div>
                </fieldset>
                <fieldset class="clearfix" name="useraccountsettings">
                    <legend><?php print get_string('useraccountsettings', 'local_campusconnect') ?></legend>
                    <div class="fcontainer clearfix">
                        <div class="fitem clearfix">
                            <div class="fitemtitle">
                                <label for="roleassignment">
                                    <?php print get_string('roleassignments', 'local_campusconnect') ?>
                                    <?php print "<img src='".$OUTPUT->pix_url('req')."' alt='req' />" ?>
                                </label>
                            </div>
                            <div class="felement fselect">
                                <select id="roleassignment" name="roleassignment">
                                    <?php
                                        $optroles = array();
                                        foreach ($roles as $role) {
                                            $optroles[$role->shortname] = $role->name;
                                        }
                                        print_option($optroles, $_POST['roleassignment']);
                                    ?>
                                </select>
                            </div>
                            <div class="felement fstatic">
                                <p><?php print get_string('roleassignmentsdesc', 'local_campusconnect') ?></p>
                            </div>
                        </div>
                        <div class="fitem clearfix">
                            <div class="fitemtitle">
                                <label for="duration">
                                    <?php print get_string('activationperiod', 'local_campusconnect') ?>
                                    <?php print "<img src='".$OUTPUT->pix_url('req')."' alt='req' />" ?>
                                </label>
                            </div>
                            <div class="felement fselect">
                                <?php print get_string('months', 'local_campusconnect') ?>
                                    : <select name="duration[MM]" size="0">
                                     <?php
                                         print_option(range(0, 36), $post['duration']["MM"], 6);
                                     ?>
                                </select>
                            </div>
                            <div class="felement fstatic">
                                <p><?php print get_string('activationperioddesc', 'local_campusconnect') ?></p>
                            </div>
                        </div>
                    </div>
                </fieldset>
                <fieldset class="clearfix" name="notifications">
                    <legend><?php print get_string('notifications', 'local_campusconnect') ?></legend>
                    <div class="fcontainer clearfix">
                        <div class="fitem clearfix">
                            <div class="fitemtitle">
                                <label for="usernotification">
                                    <?php print get_string('notifcationaboutecsusers', 'local_campusconnect') ?>
                                </label>
                            </div>
                            <div class="felement fselect">
                                <input type="text" name="usernotification"
                                    <?php print "value='{$post['usernotification']}'"; ?>/>
                            </div>
                            <div class="felement fstatic">
                                <p><?php print get_string('usernotificationdesc', 'local_campusconnect') ?></p>
                            </div>
                        </div>
                        <div class="fitem clearfix">
                            <div class="fitemtitle">
                                <label for="contentnotification">
                                    <?php print get_string('notificationaboutnewecontent', 'local_campusconnect') ?>
                                </label>
                            </div>
                            <div class="felement fselect">
                                <input type="text" name="contentnotification"
                                    <?php print "value='{$post['contentnotification']}'"; ?>/>
                            </div>
                            <div class="felement fstatic">
                                <p><?php print get_string('contentnotificationdesc', 'local_campusconnect') ?></p>
                            </div>
                        </div>
                        <div class="fitem clearfix">
                            <div class="fitemtitle">
                                <label for="coursenotification">
                                    <?php print get_string('notificationaboutapprovedcourses', 'local_campusconnect') ?>
                                </label>
                            </div>
                            <div class="felement fselect">
                                <input type="text" name="coursenotification"
                                    <?php print "value='{$post['coursenotification']}'"; ?>/>
                            </div>
                            <div class="felement fstatic">
                                <p><?php print get_string('coursenotificationdesc', 'local_campusconnect') ?></p>
                            </div>
                        </div>
                    </div>
                </fieldset>
                <div id="fitem_id_submitbutton" class="fitem fitem_fsubmit">
                    <div class="fitemtitle">
                        <label for="id_submitbutton"> </label>
                    </div>
                    <?php if ($function == 'add') { ?>
                        <div class="felement fsubmit">
                            <input type="hidden" name="addnewecs" value="1" />
                            <input type="submit" value="Add New ECS" />
                        </div>
                    <?php } ?>
                    <?php if (isset($_GET['id'])) { ?>
                        <div class="felement fsubmit">
                            <input type="hidden" name="saveexistingecs" value="1" />
                            <input type="submit" value="Save ECS" />
                        </div>
                    <?php } ?>
                </div>
            </div>
        </form>
    <?php
} else if (isset($id)) {

    print "ECS: $id";

} else {

    print 'Error: Missing Parameter';

}

echo $OUTPUT->footer();