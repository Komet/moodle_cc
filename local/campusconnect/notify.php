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
 * Send out notifications to admin users about updates
 *
 * @package   local_campusconnect
 * @copyright 2012 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class campusconnect_notification {
    const MESSAGE_IMPORT_COURSELINK = 1;
    const MESSAGE_EXPORT_COURSELINK = 2;
    const MESSAGE_USER = 3;
    const MESSAGE_COURSE = 4;
    const MESSAGE_DIRTREE = 5;

    static $messagetypes = array(self::MESSAGE_IMPORT_COURSELINK, self::MESSAGE_EXPORT_COURSELINK,
                                 self::MESSAGE_USER, self::MESSAGE_COURSE, self::MESSAGE_DIRTREE);

    /**
     * Queue a new notification to be sent out via email
     * @param int $ecsid the ECS this message relates to
     * @param int $type what the notification relates to (MESSAGE_IMPORT_COURSELINK, MESSAGE_EXPORT_COURSELINK,
     *                  MESSAGE_COURSE, MESSAGE_DIRTREE, MESSAGE_USER)
     * @param int $dataid for courselinks: the ID of the Moodle course, for users: the ID of the new user
     */
    public static function queue_message($ecsid, $type, $dataid) {
        global $DB;

        // TODO davo - add 'subtype' to handle create/update/delete differentiation
        if (!in_array($type, self::$messagetypes)) {
            throw new coding_exception("Unknown message type '$type'");
        }
        $ins = (object)array(
            'ecsid' => $ecsid,
            'type' => $type,
            'data' => $dataid
        );
        $DB->insert_record('local_campusconnect_notify', $ins);
    }

    /**
     * Send out all notification emails for the given ECS
     * @param campusconnect_ecssettings $ecssettings
     */
    public static function send_notifications(campusconnect_ecssettings $ecssettings) {
        global $DB;

        $types = array(
            self::MESSAGE_IMPORT_COURSELINK => (object)array(
                'string' => 'import',
                'table' => 'course',
                'name' => 'fullname',
                'url' => '/course/view.php',
                'users' => $ecssettings->get_notify_content(),
            ),
            self::MESSAGE_EXPORT_COURSELINK => (object)array(
                'string' => 'export',
                'table' => 'course',
                'name' => 'fullname',
                'url' => '/course/view.php',
                'users' => $ecssettings->get_notify_courses(),
            ),
            self::MESSAGE_USER => (object)array(
                'string' => 'newuser',
                'table' => 'user',
                'name' => 'firstname,lastname',
                'url' => '/user/view.php',
                'users' => $ecssettings->get_notify_users(),
            ),
            self::MESSAGE_COURSE => (object)array(
                'string' => 'course',
                'table' => 'course',
                'name' => 'fullname',
                'url' => '/course/view.php',
                'users' => $ecssettings->get_notify_content(),
            ),
        );

        $sitename = format_string($DB->get_field('course', 'fullname', array('id' => SITEID), MUST_EXIST));

        foreach ($types as $typeid => $type) {
            $params = array('ecsid' => $ecssettings->get_id(), 'type' => $typeid);
            $notifications = $DB->get_records('local_campusconnect_notify', $params);
            if ($notifications) {
                $subject = get_string("notify{$type->string}_subject", 'local_campusconnect', $sitename);
                $bodytext = get_string("notify{$type->string}_body", 'local_campusconnect', $sitename)."\n\n";
                $body = str_replace("\n", '<br />', $bodytext);
                $body .= html_writer::start_tag('ul');
                foreach ($notifications as $notification) {
                    $object = $DB->get_record($type->table, array('id' => $notification->data), "id, {$type->name}");
                    if (!$object) {
                        continue;
                    }
                    $link = new moodle_url($type->url, array('id' => $object->id));
                    if ($type->name == 'firstname,lastname') {
                        $name = fullname($object);
                    } else {
                        $name = format_string($object->{$type->name});
                    }
                    $bodytext .= $name.' - '.$link->out(false)."\n";
                    $body .= html_writer::tag('li', html_writer::link($link, $name))."\n";
                }
                $body .= html_writer::end_tag('ul');

                self::send_notification($type->users, $subject, $body, $bodytext);

                $DB->delete_records('local_campusconnect_notify', $params);
            }
        }
    }

    /**
     * Send out notification to the list of users.
     * @param string[] $users usernames of the users to email
     * @param string $subject email subject
     * @param string $body HTML message content
     * @param string $bodytext plain text message content
     */
    protected static function send_notification($users, $subject, $body, $bodytext) {
        global $DB;

        if (empty($users)) {
            return;
        }
        $admin = get_admin();
        $userobjs = $DB->get_records_list('user', 'username', $users, '', 'id, firstname, lastname, email, mailformat');
        foreach ($userobjs as $user) {
            email_to_user($user, $admin, $subject, $bodytext, $body);
        }
    }
}