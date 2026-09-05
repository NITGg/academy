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

namespace local_nit_emails;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/message/lib.php');

/**
 * Every message this site sends a learner, whichever machinery sends it.
 *
 * The first version of this page listed Moodle *message providers* and nothing else, and
 * that made it wrong in the way that matters: the academy's own emails do not go through
 * message_send() at all. A course purchase, a subscription confirmation, a registration
 * welcome and a job-application acknowledgement are written by our own code and handed
 * straight to email_to_user(). None of them appeared, and unticking a provider row could
 * not stop any of them — worst of all for "Payment confirmation notifications", which
 * looks exactly like the purchase email and is a different message entirely.
 *
 * So this class is a registry of *events as an administrator understands them*, not of one
 * plugin API. Three kinds of row sit in it, and each knows how to read and write its own
 * switch:
 *
 *   KIND_PROVIDER  a Moodle message provider. Two channels, stored the way message_send()
 *                  reads them — see {@see channels}.
 *   KIND_CONFIG    an email our own code sends directly. One channel (there is no bell for
 *                  it), stored as a plugin config flag that the sender itself consults.
 *   KIND_ALWAYS    an email that is deliberately not switchable: password resets, the
 *                  address-change confirmation, the alert that a password was changed.
 *                  Listed so an administrator can see it exists, read-only so nobody can
 *                  quietly break account recovery or hide a takeover.
 *
 * Rows are grouped into the academy's own messages first and Moodle's stock events second,
 * because the first group is what anybody opens this page to change and the second is
 * forty-odd rows of activity-module noise around it.
 *
 * @package    local_nit_emails
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_registry {

    /** @var string Row backed by a Moodle message provider. */
    const KIND_PROVIDER = 'provider';

    /** @var string Row backed by a plugin config flag, written by our own sender. */
    const KIND_CONFIG = 'config';

    /** @var string Row that is sent unconditionally and shown read-only. */
    const KIND_ALWAYS = 'always';

    /** @var string This event really does deliver on this channel, and it can be switched. */
    const MODE_TOGGLE = 'toggle';

    /** @var string This event delivers on this channel and is not switchable here. */
    const MODE_ALWAYS = 'always';

    /** @var string This event has no such channel at all — the cell is empty, not unticked. */
    const MODE_NA = 'na';

    /** @var array<string, array>|null the parsed db/messages.php of each component, once */
    private static $declarations = null;

    // =========================================================================
    // The page's shape.
    // =========================================================================

    /**
     * The two sections, in the order they are read.
     *
     * @return array[] key, label, intro, and either rows[] (flat) or blocks[] (sub-grouped)
     */
    public static function sections(): array {
        return [
            [
                'key'   => 'academy',
                'label' => get_string('events_group_academy', 'local_nit_emails'),
                'intro' => get_string('events_group_academy_desc', 'local_nit_emails'),
                'rows'  => self::academy_rows(),
                'blocks' => [],
            ],
            [
                'key'   => 'moodle',
                'label' => get_string('events_group_moodle', 'local_nit_emails'),
                'intro' => get_string('events_group_moodle_desc', 'local_nit_emails'),
                'rows'  => [],
                'blocks' => self::moodle_blocks(),
            ],
        ];
    }

    /**
     * Every row on the page, in one flat list — what the save loop walks.
     *
     * @return array[]
     */
    public static function all_rows(): array {
        $out = [];
        foreach (self::sections() as $section) {
            foreach ($section['rows'] as $row) {
                $out[] = $row;
            }
            foreach ($section['blocks'] as $block) {
                foreach ($block['rows'] as $row) {
                    $out[] = $row;
                }
            }
        }
        return $out;
    }

    // =========================================================================
    // Group one: the academy's own messages.
    // =========================================================================

    /**
     * The messages this academy sends, in the order somebody looking for one would scan.
     *
     * Money first, then the account, then recruitment, then the security mail that cannot
     * be switched off.
     *
     * @return array[]
     */
    private static function academy_rows(): array {
        $rows = [];

        // ---- The branded transactional emails (local_nit_emails' own three) -------------
        // These share their switch with the wording page, which is the point: an
        // administrator who turns "Course purchased" off here sees it off there too,
        // because there is one stored flag and two views of it.
        foreach (templates::events() as $event) {
            $rows[] = self::config_row(
                'nitmail_' . $event,
                templates::event_name($event),
                get_string('events_sender_nitmail', 'local_nit_emails'),
                'local_nit_emails',
                'enabled_' . $event,
                templates::is_enabled($event),
                new \moodle_url('/local/nit_emails/manage.php', ['event' => $event]),
                get_string('events_editwording', 'local_nit_emails')
            );
        }

        // ---- Our message providers ------------------------------------------------------
        foreach (self::providers_for(static fn(string $component): bool =>
                strpos($component, 'local_') === 0 || $component === 'mod_jobform') as $row) {
            $rows[] = $row;
        }

        // ---- The job-application acknowledgement ----------------------------------------
        // Its sibling — the notice to the reviewers — is the mod_jobform_submission provider
        // picked up just above. This is the other half, and it had no switch at all until
        // this page needed one.
        $rows[] = self::config_row(
            'jobform_applicant',
            get_string('events_jobformack', 'local_nit_emails'),
            get_string('events_sender_jobform', 'local_nit_emails'),
            'mod_jobform',
            'notifyapplicant',
            \mod_jobform\notifier::is_applicant_email_enabled()
        );

        // ---- Account and security mail, read-only ---------------------------------------
        foreach (self::security_events() as $key => $label) {
            $rows[] = [
                'id'       => 'always_' . $key,
                'kind'     => self::KIND_ALWAYS,
                'label'    => $label,
                'sub'      => get_string('events_alwaysnote', 'local_nit_emails'),
                'link'     => null,
                'linktext' => '',
                'channels' => [
                    channels::CHANNEL_EMAIL => ['mode' => self::MODE_ALWAYS, 'on' => true],
                    channels::CHANNEL_POPUP => ['mode' => self::MODE_NA, 'on' => false],
                ],
            ];
        }

        return $rows;
    }

    /**
     * The mail an account depends on, which is why none of it is switchable.
     *
     * Turning off the address-change confirmation would leave a half-finished change with no
     * way to complete it; turning off the password-changed alert removes the one signal that
     * somebody else is in the account. They are listed so an administrator can see the site
     * sends them, and read-only so that seeing them is all they can do.
     *
     * @return array<string, string> key => label
     */
    private static function security_events(): array {
        return [
            'passwordreset'   => get_string('events_sec_passwordreset', 'local_nit_emails'),
            'passwordchanged' => get_string('events_sec_passwordchanged', 'local_nit_emails'),
            'emailchange'     => get_string('events_sec_emailchange', 'local_nit_emails'),
            'accountdeleted'  => get_string('events_sec_accountdeleted', 'local_nit_emails'),
            'newdevice'       => get_string('events_sec_newdevice', 'local_nit_emails'),
        ];
    }

    // =========================================================================
    // Group two: Moodle's stock events.
    // =========================================================================

    /**
     * Everything core and the activity modules raise, gathered under its own plugin.
     *
     * @return array[] one entry per component: label, rows[]
     */
    private static function moodle_blocks(): array {
        $ours = static fn(string $component): bool =>
            strpos($component, 'local_') === 0 || $component === 'mod_jobform';

        $bycomponent = [];
        foreach (self::providers_for(static fn(string $component): bool => !$ours($component)) as $row) {
            $bycomponent[$row['component']][] = $row;
        }

        // Core first — it is the one heading a reader recognises — then the rest by name.
        $components = array_keys($bycomponent);
        \core_collator::asort($components);
        $components = array_values($components);
        if (($core = array_search('moodle', $components, true)) !== false) {
            unset($components[$core]);
            array_unshift($components, 'moodle');
        }

        $out = [];
        foreach ($components as $component) {
            $rows = $bycomponent[$component];
            \core_collator::asort_array_of_arrays_by_key($rows, 'label');
            $out[] = [
                'key'   => $component,
                'label' => channels::component_name($component),
                'rows'  => array_values($rows),
            ];
        }
        return $out;
    }

    // =========================================================================
    // Rows.
    // =========================================================================

    /**
     * Provider rows whose component passes $wanted.
     *
     * @param callable $wanted string $component => bool
     * @return array[]
     */
    private static function providers_for(callable $wanted): array {
        $preferences = get_message_output_default_preferences();

        $out = [];
        foreach (get_message_providers() as $provider) {
            $component = (string) $provider->component;
            if (!$wanted($component)) {
                continue;
            }
            $out[] = self::provider_row($component, (string) $provider->name, $preferences);
        }

        \core_collator::asort_array_of_arrays_by_key($out, 'label');
        return array_values($out);
    }

    /**
     * One message-provider row.
     *
     * @param string $component
     * @param string $name
     * @param \stdClass $preferences the whole `message` config
     * @return array
     */
    private static function provider_row(string $component, string $name, \stdClass $preferences): array {
        $declared = self::declared_channels($component, $name);

        $channels = [];
        foreach (channels::channels() as $channel) {
            if (!$declared[$channel]) {
                // The plugin raising this event never offered this channel, so there is
                // nothing to switch. An unticked box would read as "off", which is a
                // different and untrue statement — hence an empty cell.
                $channels[$channel] = ['mode' => self::MODE_NA, 'on' => false];
                continue;
            }
            $state = channels::state($component, $name, $channel, $preferences);
            $channels[$channel] = ['mode' => self::MODE_TOGGLE, 'on' => $state['on'],
                'locked' => $state['locked']];
        }

        return [
            'id'        => 'provider_' . $component . '__' . $name,
            'kind'      => self::KIND_PROVIDER,
            'component' => $component,
            'name'      => $name,
            'label'     => channels::provider_name($component, $name),
            'sub'       => $component . '_' . $name,
            'link'      => null,
            'linktext'  => '',
            'channels'  => $channels,
        ];
    }

    /**
     * One row backed by a plugin config flag — an email our own code sends.
     *
     * @param string $id form-safe row id
     * @param string $label what the event is called
     * @param string $sub the small grey line under it
     * @param string $plugin config namespace holding the flag
     * @param string $setting the flag's name
     * @param bool $on its current value, read through the sender's own accessor
     * @param \moodle_url|null $link an optional link beside the label
     * @param string $linktext its wording
     * @return array
     */
    private static function config_row(string $id, string $label, string $sub, string $plugin,
            string $setting, bool $on, ?\moodle_url $link = null, string $linktext = ''): array {
        return [
            'id'       => $id,
            'kind'     => self::KIND_CONFIG,
            'plugin'   => $plugin,
            'setting'  => $setting,
            'label'    => $label,
            'sub'      => $sub,
            'link'     => $link,
            'linktext' => $linktext,
            'channels' => [
                channels::CHANNEL_EMAIL => ['mode' => self::MODE_TOGGLE, 'on' => $on],
                // Written by email_to_user(), which has no bell to ring. Saying so is the
                // whole reason this column can show something other than a checkbox.
                channels::CHANNEL_POPUP => ['mode' => self::MODE_NA, 'on' => false],
            ],
        ];
    }

    // =========================================================================
    // Saving.
    // =========================================================================

    /**
     * Apply the form's answer for one row.
     *
     * A channel that is not a live switch on that row — not applicable, or deliberately
     * always sent — is dropped before anything is written, so an empty cell can never be
     * mistaken for "the administrator unticked it".
     *
     * @param array $row a row from this registry
     * @param array<string, bool> $posted channel => ticked
     * @return bool whether anything changed
     */
    public static function apply(array $row, array $posted): bool {
        $wanted = [];
        foreach ($row['channels'] as $channel => $cell) {
            if ($cell['mode'] === self::MODE_TOGGLE && array_key_exists($channel, $posted)) {
                $wanted[$channel] = (bool) $posted[$channel];
            }
        }
        if (empty($wanted)) {
            return false;
        }

        if ($row['kind'] === self::KIND_PROVIDER) {
            return channels::apply($row['component'], $row['name'], $wanted);
        }

        if ($row['kind'] === self::KIND_CONFIG) {
            $new = !empty($wanted[channels::CHANNEL_EMAIL]) ? 1 : 0;
            $old = $row['channels'][channels::CHANNEL_EMAIL]['on'] ? 1 : 0;
            if ($new === $old) {
                return false;
            }
            add_to_config_log($row['setting'], (string) $old, (string) $new, $row['plugin']);
            set_config($row['setting'], $new, $row['plugin']);
            return true;
        }

        return false;
    }

    // =========================================================================
    // What a plugin says its event supports.
    // =========================================================================

    /**
     * Which channels this provider was actually declared with.
     *
     * A plugin lists its event in db/messages.php with a default per processor. A processor
     * that is absent from that list, or marked MESSAGE_DISALLOWED, is not a channel this
     * event has — core will never deliver on it however the site is configured — and the
     * page must not pretend otherwise. This is the "there are events that do not have this
     * feature" case: on this site, `moodle_messagecontactrequests` declares no bell channel.
     *
     * @param string $component
     * @param string $name
     * @return array<string, bool> channel => does this event have it
     */
    private static function declared_channels(string $component, string $name): array {
        $defaults = self::declaration($component)[$name]['defaults'] ?? null;

        $out = [];
        foreach (channels::channels() as $channel) {
            if ($defaults === null) {
                // No readable declaration (a plugin removed from disk, say). Assume the
                // channel exists rather than hiding a control that may well work — the
                // stored setting is still the truth, and an empty cell here would be the
                // more confident wrong answer.
                $out[$channel] = true;
                continue;
            }
            if (!array_key_exists($channel, $defaults)) {
                $out[$channel] = false;
                continue;
            }
            $permission = ((int) $defaults[$channel])
                & (MESSAGE_DISALLOWED | MESSAGE_PERMITTED | MESSAGE_FORCED);
            $out[$channel] = ($permission !== MESSAGE_DISALLOWED);
        }
        return $out;
    }

    /**
     * One component's db/messages.php, read once per request.
     *
     * @param string $component
     * @return array the $messageproviders array, or [] when there is no readable file
     */
    private static function declaration(string $component): array {
        global $CFG;

        if (self::$declarations === null) {
            self::$declarations = [];
        }
        if (array_key_exists($component, self::$declarations)) {
            return self::$declarations[$component];
        }

        $file = ($component === 'moodle')
            ? $CFG->dirroot . '/lib/db/messages.php'
            : \core_component::get_component_directory($component) . '/db/messages.php';

        $messageproviders = [];
        if ($file && is_readable($file)) {
            include($file);
        }

        self::$declarations[$component] = is_array($messageproviders) ? $messageproviders : [];
        return self::$declarations[$component];
    }
}
