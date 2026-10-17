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

namespace local_profilefields;

use context_system;
use core_user;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * The Contact page's banner picture and its "send us a message" form.
 *
 * The form is plain HTML posted back to page.php rather than a moodleform: it is
 * four boxes on a public marketing page, drawn to the Figma, and a moodleform's
 * label-beside-box rows and fieldset chrome are exactly what the design does not
 * have. What a moodleform would have given - the sesskey, cleaning every value,
 * telling the visitor which box is wrong without losing what they typed - is done
 * here by hand.
 *
 * The message goes out by email and is not stored: the mailbox it lands in is the
 * record. The recipient is the address set on the Contact tab, else the email row
 * of the footer (the one the page itself shows), else the site's support contact.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contactform {

    /** @var string File area of the banner picture - one file, item id 0. */
    const HERO_FILEAREA = 'contacthero';

    /** @var int Seconds a session must wait between two messages. */
    const THROTTLE = 60;

    /** @var int Longest message accepted, in characters. */
    const MAXLENGTH = 5000;

    /**
     * Whether the page shows the form at all. On unless switched off.
     *
     * @return bool
     */
    public static function enabled(): bool {
        $value = get_config(staticpages::COMPONENT, 'pagecontactformenabled');
        return $value === false || $value === '' || (bool) $value;
    }

    /**
     * The address typed on the Contact tab, '' when none.
     *
     * @return string
     */
    public static function recipient_setting(): string {
        return (string) get_config(staticpages::COMPONENT, 'pagecontactrecipient');
    }

    /**
     * Where a message is actually sent.
     *
     * @return \stdClass a user record email_to_user() accepts
     */
    public static function recipient(): \stdClass {
        $to = clone core_user::get_support_user();

        $email = self::recipient_setting();
        if (!validate_email($email)) {
            $email = '';
            foreach (footer::config()['contact']['rows'] as $row) {
                if ($row['key'] === 'email' && validate_email($row['text'])) {
                    $email = $row['text'];
                    break;
                }
            }
        }
        if ($email !== '') {
            $to->email = $email;
        }

        return $to;
    }

    /**
     * The file options of the banner picture.
     *
     * @return array
     */
    public static function hero_file_options(): array {
        return about::hero_file_options();
    }

    /**
     * Where the banner picture is served from, or '' when there is none.
     *
     * @return string URL
     */
    public static function hero_image_url(): string {
        $files = get_file_storage()->get_area_files(context_system::instance()->id,
            staticpages::COMPONENT, self::HERO_FILEAREA, 0, 'itemid, filepath, filename', false);
        $file = reset($files);
        if (!$file) {
            return '';
        }

        return moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(),
            $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename())->out(false);
    }

    /**
     * Handle a posted message, then hand back what the form should show.
     *
     * A good message is sent and the visitor is redirected back to an empty form
     * with a green notice - so a reload cannot send it twice. A bad one comes back
     * with what was typed still in the boxes and a line under each wrong one.
     *
     * @param moodle_url $url the Contact page
     * @return array template context for the form
     */
    public static function handle(moodle_url $url): array {
        global $USER, $SESSION;

        $values = ['name' => '', 'email' => '', 'phone' => '', 'message' => ''];
        if (isloggedin() && !isguestuser()) {
            $values['name'] = fullname($USER);
            $values['email'] = (string) $USER->email;
            $values['phone'] = (string) ($USER->phone1 ?: $USER->phone2);
        }
        $errors = [];

        if (self::enabled() && data_submitted() && optional_param('nitcontact', 0, PARAM_BOOL)) {
            require_sesskey();

            $values = [
                'name'    => trim(optional_param('name', '', PARAM_TEXT)),
                'email'   => trim(optional_param('email', '', PARAM_EMAIL)),
                'phone'   => trim(optional_param('phone', '', PARAM_TEXT)),
                'message' => trim(optional_param('message', '', PARAM_TEXT)),
            ];

            // A bot fills every box it finds; a person never sees this one.
            if (optional_param('website', '', PARAM_RAW) !== '') {
                redirect($url, get_string('contactsent', 'local_profilefields'),
                    null, \core\output\notification::NOTIFY_SUCCESS);
            }

            if ($values['name'] === '') {
                $errors['name'] = get_string('contacterrname', 'local_profilefields');
            }
            if (!validate_email($values['email'])) {
                $errors['email'] = get_string('contacterremail', 'local_profilefields');
            }
            if ($values['message'] === '') {
                $errors['message'] = get_string('contacterrmessage', 'local_profilefields');
            } else if (\core_text::strlen($values['message']) > self::MAXLENGTH) {
                $errors['message'] = get_string('contacterrlong', 'local_profilefields', self::MAXLENGTH);
            }

            $last = (int) ($SESSION->local_profilefields_contactsent ?? 0);
            if (!$errors && $last && time() - $last < self::THROTTLE) {
                $errors['form'] = get_string('contacterrwait', 'local_profilefields');
            }

            if (!$errors) {
                if (self::send($values)) {
                    $SESSION->local_profilefields_contactsent = time();
                    redirect($url, get_string('contactsent', 'local_profilefields'),
                        null, \core\output\notification::NOTIFY_SUCCESS);
                }
                $errors['form'] = get_string('contacterrsend', 'local_profilefields');
            }
        }

        $context = [
            'enabled'  => self::enabled(),
            'action'   => $url->out(false),
            'sesskey'  => sesskey(),
            'formerror' => $errors['form'] ?? '',
            'fields'   => [],
        ];
        $fields = [
            'name'    => ['type' => 'text', 'autocomplete' => 'name', 'required' => true],
            'email'   => ['type' => 'email', 'autocomplete' => 'email', 'required' => true],
            'phone'   => ['type' => 'tel', 'autocomplete' => 'tel', 'required' => false],
            'message' => ['type' => 'textarea', 'autocomplete' => 'off', 'required' => true],
        ];
        foreach ($fields as $name => $field) {
            $context['fields'][] = $field + [
                'name'        => $name,
                'istextarea'  => $field['type'] === 'textarea',
                'value'       => $values[$name],
                'placeholder' => get_string('contactph' . $name, 'local_profilefields'),
                'error'       => $errors[$name] ?? '',
                'haserror'    => isset($errors[$name]),
                'maxlength'   => $name === 'message' ? self::MAXLENGTH : 255,
            ];
        }

        return $context;
    }

    /**
     * Mail one message to the recipient, with the visitor as the reply-to.
     *
     * @param array $values name, email, phone, message - already cleaned
     * @return bool
     */
    protected static function send(array $values): bool {
        global $SITE;

        $a = (object) [
            'site'    => format_string($SITE->fullname),
            'name'    => $values['name'],
            'email'   => $values['email'],
            'phone'   => $values['phone'] !== '' ? $values['phone'] : '-',
            'message' => $values['message'],
        ];
        $subject = get_string('contactmailsubject', 'local_profilefields', $a);
        $text = get_string('contactmailbody', 'local_profilefields', $a);

        return (bool) email_to_user(self::recipient(), core_user::get_noreply_user(), $subject, $text,
            text_to_html(s($text), false, false, true), '', '', true, $values['email'], $values['name']);
    }
}
