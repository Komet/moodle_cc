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

require_once($CFG->libdir."/formslib.php");

class campusconnect_ecs_form extends moodleform {

    public function definition() {

        global $DB;

        $roles = $DB->get_records('role');
        $strrequired = get_string('required');

        $mform = $this->_form;

        $mform->addElement('header', 'connectionsettings', get_string('connectionsettings', 'local_campusconnect'));

        $mform->addElement('text', 'name', get_string('name', 'local_campusconnect'));
        $mform->addRule('name', $strrequired, 'required', null, 'client');
        $mform->addElement('text', 'url', get_string('url', 'local_campusconnect'));
        $mform->addRule('url', $strrequired, 'required', null, 'client');
        $mform->addElement('static', 'urldesc', '', get_string('urldesc', 'local_campusconnect'));
        $mform->addElement('select', 'protocol', get_string('protocol', 'local_campusconnect'), array('http'=>'HTTP', 'https'=>'HTTPS'));
        $mform->addRule('protocol', $strrequired, 'required', null, 'client');
        $mform->addElement('text', 'port', get_string('port', 'local_campusconnect'));

        $mform->addElement('select', 'cc_auth', get_string('authenticationtype', 'local_campusconnect'), array('2'=>get_string('certificatebase', 'local_campusconnect'), '3'=>get_string('usernamepassword', 'local_campusconnect')));

        $mform->addElement('text', 'certpath', get_string('clientcertificate', 'local_campusconnect'));
        $mform->disabledIf('certpath', 'cc_auth', 'eq', '3');
        $mform->addElement('text', 'keypath', get_string('certificatekey', 'local_campusconnect'));
        $mform->disabledIf('keypath', 'cc_auth', 'eq', '3');
        $mform->addElement('text', 'keypass', get_string('keypassword', 'local_campusconnect'));
        $mform->disabledIf('keypass', 'cc_auth', 'eq', '3');
        $mform->addElement('text', 'cacertpath', get_string('cacertificate', 'local_campusconnect'));
        $mform->disabledIf('cacertpath', 'cc_auth', 'eq', '3');

        $mform->addElement('text', 'httpuser', get_string('username', 'local_campusconnect'));
        $mform->disabledIf('httpuser', 'cc_auth', 'eq', '2');
        $mform->addElement('text', 'httppass', get_string('password', 'local_campusconnect'));
        $mform->disabledIf('httppass', 'cc_auth', 'eq', '2');

        $mform->addElement('header', 'localsettings', get_string('localsettings', 'local_campusconnect'));

        $selectarray=array();
        $selectarray[] = &MoodleQuickForm::createElement('select', 'pollingtime[mm]', '', range(0, 59));
        $selectarray[] = &MoodleQuickForm::createElement('static', 'pollingmins', '', get_string('minutes', 'local_campusconnect'));
        $selectarray[] = &MoodleQuickForm::createElement('select', 'pollingtime[ss]', '', range(0, 59));
        $selectarray[] = &MoodleQuickForm::createElement('static', 'pollingsecs', '', get_string('seconds', 'local_campusconnect'));
        $mform->addGroup($selectarray, 'pollingtime', get_string('pollingtime', 'local_campusconnect'), array(' '), false);

        $mform->addElement('text', 'importcategory', get_string('categoryid', 'local_campusconnect'));
        $mform->addElement('static', 'categoryiddesc', '', get_string('categoryiddesc', 'local_campusconnect'));
        $mform->addRule('importcategory', $strrequired, 'required', null, 'client');

        $mform->addElement('header', 'useraccountsettings', get_string('useraccountsettings', 'local_campusconnect'));

        $optroles = array();
        foreach ($roles as $role) {
            $optroles[$role->shortname] = $role->name;
        }
        $mform->addElement('select', 'importrole', get_string('roleassignments', 'local_campusconnect'), $optroles);
        $mform->setDefault('importrole', 'student');
        $mform->addElement('select', 'importperiod', get_string('activationperiod', 'local_campusconnect'), range(0, 36));
        $mform->addElement('static', 'activationmonths', '', get_string('months', 'local_campusconnect'));
        $mform->setDefault('importperiod', '10');

        $mform->addElement('header', 'notifications', get_string('notifications', 'local_campusconnect'));

        $mform->addElement('text', 'notifyusers', get_string('notifcationaboutecsusers', 'local_campusconnect'));
        $mform->addElement('static', 'usernotdesc', '', get_string('usernotificationdesc', 'local_campusconnect'));
        $mform->addElement('text', 'notifycontent', get_string('notificationaboutnewecontent', 'local_campusconnect'));
        $mform->addElement('static', 'contentnotdesc', '', get_string('contentnotificationdesc', 'local_campusconnect'));
        $mform->addElement('text', 'notifycourses', get_string('notificationaboutapprovedcourses', 'local_campusconnect'));
        $mform->addElement('static', 'coursenotdesc', '', get_string('coursenotificationdesc', 'local_campusconnect'));

        $this->add_action_buttons();

    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['cc_auth'] == '2') {
            if (empty($data['certpath'])) {
                $errors['certpath'] = get_string('required');
            }
            if (empty($data['keypath'])) {
                $errors['keypath'] = get_string('required');
            }
            if (empty($data['keypass'])) {
                $errors['keypass'] = get_string('required');
            }
            if (empty($data['cacertpath'])) {
                $errors['cacertpath'] = get_string('required');
            }
        }
        if ($data['cc_auth'] == '3') {
            if (empty($data['httpuser'])) {
                $errors['httpuser'] = get_string('required');
            }
            if (empty($data['httppass'])) {
                $errors['httppass'] = get_string('required');
            }
        }
        return $errors;
    }

}