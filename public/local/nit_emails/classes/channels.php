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
 * "Does this event send an email? Does it send a notification?" — for every event on the site.
 *
 * Moodle already knows the answer; it just does not ask the question anywhere in one place.
 * Every notification the site can send is a *message provider* (an event declared by a plugin
 * in its `db/messages.php`) and every way of delivering one is a *message processor* — of
 * which two matter to a learner: `email` and `popup`, the bell in the navbar. What
 * {@see \message_send()} actually consults, per provider and per processor, is three plugin
 * config values in the `message` namespace:
 *
 *   {component}_{name}_disable                       the whole event, off. Nothing is sent.
 *   message_provider_{component}_{name}_enabled      a comma list of the processors that are on.
 *   {processor}_provider_{component}_{name}_locked   whether the user may change that.
 *
 * This class reads and writes exactly those. There is deliberately no table and no second
 * store: a parallel copy of "should this email go out" would drift from the one core reads,
 * and the page would then be a lie — it would show settings that changed nothing.
 *
 * The three combine into four states, which the page shows as one tick per channel:
 *
 *   enabled, not locked   on, and the user may turn it off in their own preferences  → TICKED
 *   enabled, locked       on for everybody, the user may NOT turn it off ("Always")  → TICKED
 *   not enabled, locked   off, and the user may not turn it on                       → CLEARED
 *   not enabled, unlocked off by default, but the user may turn it on                → CLEARED
 *
 * Two ticks therefore cannot express all four states, so the writing rules are chosen to be
 * the least surprising ones:
 *
 *   - A tick that was already ticked is left completely alone. That is what keeps an
 *     "Always sent" set up on core's own Notification settings page from being quietly
 *     downgraded every time somebody saves this one.
 *   - Turning a channel ON means "permitted, on by default": the user keeps the final say.
 *   - Turning a channel OFF means OFF: locked, so it cannot come back through a user
 *     preference. An administrator who unticks "Email" means no email, not "no email unless
 *     the recipient asks for one".
 *   - Unticking both channels also sets the provider's master `_disable`, and ticking either
 *     one clears it — so a row that reads "off, off" really is off at the top of
 *     message_send(), whatever else is configured underneath.
 *
 * @package    local_nit_emails
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class channels {

    /** @var string Delivery by email. */
    const CHANNEL_EMAIL = 'email';

    /** @var string Delivery to the notification bell (and, through it, the mobile app). */
    const CHANNEL_POPUP = 'popup';

    /** @var string The config namespace core keeps every one of these values in. */
    const STORE = 'message';

    /**
     * The two channels this page governs, in column order.
     *
     * Deliberately two and not "every installed processor": the question being answered is a
     * learner's ("will I get an email? will I see a bell?"), and a page that also offered
     * SMS and Airnotifier would be core's Notification settings page rebuilt badly. Anything
     * else installed keeps whatever it is set to — {@see self::save()} never touches it.
     *
     * @return string[]
     */
    public static function channels(): array {
        return [self::CHANNEL_EMAIL, self::CHANNEL_POPUP];
    }

    /**
     * The channels that can actually deliver anything right now.
     *
     * A processor that is disabled site-wide, or that is installed but not configured, sends
     * nothing whatever this page says — so the page greys its column out and says why rather
     * than showing ticks that do not do anything.
     *
     * @return array<string, \stdClass> channel => processor record, missing ones absent
     */
    public static function available(): array {
        $processors = get_message_processors();
        $out = [];
        foreach (self::channels() as $channel) {
            if (isset($processors[$channel]) && $processors[$channel]->enabled
                    && $processors[$channel]->configured) {
                $out[$channel] = $processors[$channel];
            }
        }
        return $out;
    }

    // =========================================================================
    // Reading.
    // =========================================================================

    /**
     * Every event on the site, gathered under the plugin that raises it.
     *
     * The site's own plugins come first — they are the ones somebody opens this page to
     * change — then core, then the activity modules, then anything else. Within a group the
     * events are in the order of their names, so a row stays where it was between visits.
     *
     * @return array[] one entry per component: key, label, rows[]
     */
    public static function groups(): array {
        $preferences = get_message_output_default_preferences();

        $bycomponent = [];
        foreach (get_message_providers() as $provider) {
            $bycomponent[$provider->component][] = self::row(
                (string) $provider->component, (string) $provider->name, $preferences);
        }

        $out = [];
        foreach (self::component_order(array_keys($bycomponent)) as $component) {
            $rows = $bycomponent[$component];
            \core_collator::asort_array_of_arrays_by_key($rows, 'label');
            $out[] = [
                'key'   => $component,
                'label' => self::component_name($component),
                'rows'  => array_values($rows),
            ];
        }
        return $out;
    }

    /**
     * Everything one event's row needs to draw itself.
     *
     * @param string $component the plugin that raises the event, or 'moodle'
     * @param string $name the provider name inside that plugin
     * @param \stdClass $preferences the whole `message` config, read once by the caller
     * @return array
     */
    private static function row(string $component, string $name, \stdClass $preferences): array {
        $states = [];
        foreach (self::channels() as $channel) {
            $states[$channel] = self::state($component, $name, $channel, $preferences);
        }

        return [
            'component' => $component,
            'name'      => $name,
            'key'       => $component . '_' . $name,
            'label'     => self::provider_name($component, $name),
            'field'     => self::field_prefix($component, $name),
            'disabled'  => !empty($preferences->{$component . '_' . $name . '_disable'}),
            'states'    => $states,
        ];
    }

    /**
     * What one channel of one event is set to.
     *
     * @param string $component
     * @param string $name
     * @param string $channel one of {@see self::channels()}
     * @param \stdClass|null $preferences the `message` config, or null to read it here
     * @return array on (the tick), locked (the user may not change it)
     */
    public static function state(string $component, string $name, string $channel,
            ?\stdClass $preferences = null): array {
        $preferences = $preferences ?? get_message_output_default_preferences();
        $base = $component . '_' . $name;

        $enabled = (string) ($preferences->{'message_provider_' . $base . '_enabled'} ?? '');
        $on = in_array($channel, array_filter(explode(',', $enabled)), true);

        return [
            'on'     => $on,
            'locked' => !empty($preferences->{$channel . '_provider_' . $base . '_locked'}),
        ];
    }

    // =========================================================================
    // Writing.
    // =========================================================================

    /**
     * Apply what the form said, for one event.
     *
     * Reads the current state again rather than trusting anything the page carried back: a
     * form posted from a stale tab must not undo a change somebody made in between, and a
     * channel whose tick did not move is left byte-for-byte alone (see the class comment on
     * why that matters for "Always sent").
     *
     * A channel missing from $wanted is not governed by this save and is left untouched —
     * that is how a column the page had to grey out (a processor switched off site-wide)
     * avoids being read as "the administrator unticked it".
     *
     * @param string $component
     * @param string $name
     * @param array<string, bool> $wanted channel => should it be on; absent = leave alone
     * @return bool whether anything actually changed
     */
    public static function apply(string $component, string $name, array $wanted): bool {
        $preferences = get_message_output_default_preferences();
        $base = $component . '_' . $name;
        $changed = false;

        // The enabled list holds every processor, not only our two, so it is edited in place:
        // an administrator's SMS or mobile-app setting is none of this page's business.
        $key = 'message_provider_' . $base . '_enabled';
        $enabled = array_values(array_filter(explode(',', (string) ($preferences->{$key} ?? ''))));
        $before = $enabled;

        foreach (self::channels() as $channel) {
            if (!array_key_exists($channel, $wanted)) {
                continue;
            }
            $on = !empty($wanted[$channel]);
            $was = in_array($channel, $enabled, true);
            if ($on === $was) {
                continue;
            }

            if ($on) {
                $enabled[] = $channel;
                // "Permitted, on by default": switching a channel on hands the final say
                // back to the recipient, which is what an unlocked provider means.
                $changed = self::set($channel . '_provider_' . $base . '_locked', 0, $preferences) || $changed;
            } else {
                $enabled = array_values(array_diff($enabled, [$channel]));
                // Locked, or the recipient could switch back on something an administrator
                // has just switched off.
                $changed = self::set($channel . '_provider_' . $base . '_locked', 1, $preferences) || $changed;
            }
        }

        if ($enabled !== $before) {
            $changed = self::set($key, empty($enabled) ? null : implode(',', $enabled), $preferences) || $changed;
        }

        // The master switch message_send() checks first. It follows the enabled list as a
        // whole — every processor, not only the two columns — so an event still delivered by
        // the mobile app is not killed off because its email and bell were unticked, and an
        // event nothing delivers any more is stopped at the top of the function rather than
        // walking the processor loop to reach the same answer.
        $changed = self::set($base . '_disable', empty($enabled) ? 1 : null, $preferences) || $changed;

        return $changed;
    }

    /**
     * Store one value, logging the change the way core's own settings pages do.
     *
     * @param string $name the config name inside the `message` namespace
     * @param string|int|null $value null removes the setting
     * @param \stdClass $preferences the values as they were before this save
     * @return bool whether the value actually moved
     */
    private static function set(string $name, $value, \stdClass $preferences): bool {
        $old = isset($preferences->{$name}) ? (string) $preferences->{$name} : '';
        $new = $value === null ? '' : (string) $value;

        if ($old === $new) {
            return false;
        }

        add_to_config_log($name, $old, $new, self::STORE);
        if ($value === null) {
            unset_config($name, self::STORE);
        } else {
            set_config($name, $value, self::STORE);
        }
        return true;
    }

    // =========================================================================
    // Naming.
    // =========================================================================

    /**
     * The form field prefix for one event: `<channel>_<prefix>`.
     *
     * Component and provider names are lower-case letters, digits and underscores, so the
     * result is a plain PARAM_ALPHANUMEXT field name and needs no encoding.
     *
     * @param string $component
     * @param string $name
     * @return string
     */
    public static function field_prefix(string $component, string $name): string {
        return $component . '__' . $name;
    }

    /**
     * The event's name as its own plugin words it.
     *
     * @param string $component
     * @param string $name
     * @return string
     */
    public static function provider_name(string $component, string $name): string {
        $identifier = 'messageprovider:' . $name;
        if (get_string_manager()->string_exists($identifier, $component)) {
            return get_string($identifier, $component);
        }
        // A provider whose plugin forgot the string still has to be governable, so fall back
        // to the raw name rather than printing Moodle's [[missing string]] marker.
        return $name;
    }

    /**
     * The plugin's name, as the group heading.
     *
     * @param string $component
     * @return string
     */
    public static function component_name(string $component): string {
        if ($component === 'moodle') {
            return get_string('coresystem');
        }
        if (get_string_manager()->string_exists('pluginname', $component)) {
            return get_string('pluginname', $component);
        }
        return $component;
    }

    /**
     * The order the groups are read in: ours, core, activities, everything else.
     *
     * The site's own plugins lead because they raise the events this academy actually cares
     * about — a purchase confirmation, a refund decision, a subscription about to lapse —
     * and burying them under thirty core activity modules would make the page useless for
     * the one job it has.
     *
     * @param string[] $components the components that have at least one event
     * @return string[]
     */
    private static function component_order(array $components): array {
        $ours = $core = $activities = $rest = [];

        foreach ($components as $component) {
            if (strpos($component, 'local_') === 0) {
                $ours[] = $component;
            } else if ($component === 'moodle') {
                $core[] = $component;
            } else if (strpos($component, 'mod_') === 0) {
                $activities[] = $component;
            } else {
                $rest[] = $component;
            }
        }

        foreach ([&$ours, &$activities, &$rest] as &$bucket) {
            \core_collator::asort($bucket);
            $bucket = array_values($bucket);
        }
        unset($bucket);

        return array_merge($ours, $core, $activities, $rest);
    }
}
