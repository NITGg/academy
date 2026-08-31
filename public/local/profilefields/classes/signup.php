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

use core_text;
use MoodleQuickForm;

defined('MOODLE_INTERNAL') || die();

/**
 * Applies the configured layout to the core sign-up form.
 *
 * Everything here runs from `core_login_extend_signup_form()`, which core calls
 * at the very end of `login_signup_form::definition()` - by then the eight core
 * boxes and the custom profile fields are already in the form, so this class
 * only has to take away, relabel and reorder.
 *
 * Two details drive most of the code:
 *
 * 1. `signup_validate_data()` reads `$data['username']` and `$data['email2']`
 *    unconditionally. An element that is simply deleted disappears from
 *    `exportValues()` too, so those reads would warn and then pin an error onto
 *    an element that no longer renders - a form that refuses to submit with no
 *    visible reason. Every switched-off field is therefore replaced by a hidden
 *    element carrying a sensible value rather than deleted outright.
 *
 * 2. `definition()` runs inside the `moodleform` constructor *before*
 *    `_process_submission()` copies `$_POST` into the form. Writing the derived
 *    value into `$_POST` here is what makes core's own validation see it.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signup {

    /**
     * Reshape the sign-up form according to the saved configuration.
     *
     * @param MoodleQuickForm $mform the sign-up form, mid-definition
     * @return void
     */
    public static function apply(MoodleQuickForm $mform): void {
        $config = manager::get_config();

        // Username first: it decides whether that box exists at all.
        if (manager::username_from_email()) {
            self::autofill_username($mform);
        }

        // The sign-up form is meant to read as one flat list (name, email, phone,
        // ...), so drop the per-category headers Moodle adds for custom fields;
        // otherwise reordering a custom field upwards would strand its header.
        self::strip_custom_headers($mform);

        foreach (manager::core_fields() as $name => $meta) {
            if (empty($meta['onsignup'])) {
                continue;
            }
            // Already dealt with above, and what is left under that name is a hidden
            // element with nothing to label or reorder.
            if ($name === 'username' && manager::username_from_email()) {
                continue;
            }
            if (!$mform->elementExists($name)) {
                continue;
            }

            if (!manager::on_signup($name)) {
                self::switch_off($mform, $name);
                continue;
            }

            $label = trim((string) ($config[$name]['label'] ?? ''));
            if ($label !== '') {
                $mform->getElement($name)->setLabel(format_string($label));
            }

            // "Required" is only ours to decide where core does not already insist.
            if (!empty($meta['canrequire'])) {
                if (!empty($config[$name]['required'])) {
                    $mform->addRule($name, get_string('required'), 'required', null, 'client');
                } else {
                    self::clear_rules($mform, $name);
                }
            }
        }

        self::reorder($mform, $config);
        self::attach_password_policy($mform);

        // Appended last, so it sits just above the buttons core adds after us.
        if (manager::consent_enabled()) {
            self::add_consent($mform);
        }

        // Client-side: keep the Country box in step with the phone field's country.
        if (manager::country_from_phone()) {
            self::inject_country_sync();
        }
    }

    /** @var string Element name of the inline policy-consent checkbox. */
    const CONSENT = 'localprofilefieldsconsent';

    /**
     * Add the inline "I agree to the policies" checkbox.
     *
     * The alternative to tool_policy's separate acceptance page: one required
     * checkbox on the form, its label linking to the policy documents. Formal
     * per-policy acceptance records are only kept by tool_policy's own flow; this
     * records agreement as a condition of submitting the form (enforced in
     * local_profilefields_validate_extend_signup_form()).
     *
     * @param MoodleQuickForm $mform the sign-up form, mid-definition
     * @return void
     */
    protected static function add_consent(MoodleQuickForm $mform): void {
        if ($mform->elementExists(self::CONSENT)) {
            return;
        }
        $mform->addElement('advcheckbox', self::CONSENT, '', policies::consent_label());
        $mform->setType(self::CONSENT, PARAM_INT);
    }

    /**
     * Emit the JS that copies the phone field's country into the Country box.
     *
     * Both selects use ISO alpha-2 country codes as their option values, so the sync
     * is a straight value copy. A MutationObserver is not needed - the sign-up form
     * is server-rendered in one shot.
     *
     * @return void
     */
    public static function inject_country_sync(): void {
        global $PAGE;

        $element = self::phone_element();
        if ($element === '') {
            return;
        }

        $js = <<<JS
(function() {
    var phone = document.querySelector('select[name="{$element}[country]"]');
    var country = document.querySelector('select[name="country"]');
    if (!phone || !country) {
        return;
    }
    var sync = function() {
        if (phone.value && country.querySelector('option[value="' + phone.value + '"]')) {
            country.value = phone.value;
        }
    };
    phone.addEventListener('change', sync);
    sync();
})();
JS;
        $PAGE->requires->js_init_code($js, true);
    }

    /**
     * The form element name of the phone field the sign-up flow uses.
     *
     * One lookup for every caller that needs the phone: the sync script, the
     * IP-match rule and the "country follows the phone" derivation all have to
     * agree on *which* phone field they mean, and none of them may assume the
     * shortname is literally `phone` - the admin names the field.
     *
     * The sign-up one wins when there is more than one, because that is the box
     * the visitor actually filled in; any phone field will do otherwise, which is
     * what the completion page needs when the admin took the phone off sign-up but
     * left it required.
     *
     * @return string e.g. `profile_field_phone`, or '' when the site has no phone field
     */
    public static function phone_element(): string {
        global $DB;

        // Several callers ask in one request (the form, the rules, the derivation),
        // and the answer cannot change mid-request.
        static $element = null;
        if ($element !== null) {
            return $element;
        }

        $field = $DB->get_record_select('user_info_field', 'datatype = ? AND signup = 1',
            ['phone'], 'shortname', IGNORE_MULTIPLE)
            ?: $DB->get_record_select('user_info_field', 'datatype = ?',
                ['phone'], 'shortname', IGNORE_MULTIPLE);

        return $element = $field ? self::CUSTOM_PREFIX . $field->shortname : '';
    }

    /**
     * The country code chosen in the phone field, out of a set of submitted values.
     *
     * The single implementation of "which country did the phone say?", shared by
     * the web sign-up form, the completion page and the web-service sign-up, so
     * every registration path stores the same answer. An unrecognised code comes
     * back as '' rather than being written to the profile - `country` is what
     * `local_payments\country_detector` prices on, so it only ever takes a real
     * ISO alpha-2 code.
     *
     * @param array $data submitted values, keyed by element name
     * @return string ISO alpha-2, or '' when there is no usable phone value
     */
    public static function phone_country(array $data): string {
        $element = self::phone_element();
        if ($element === '') {
            return '';
        }

        $value = $data[$element] ?? null;
        if (!is_array($value) || empty($value['country'])) {
            return '';
        }

        $iso = core_text::strtoupper(trim((string) $value['country']));
        $countries = get_string_manager()->get_list_of_countries(true);

        return isset($countries[$iso]) ? $iso : '';
    }

    /**
     * The country the phone field carries in the request being processed.
     *
     * The web form's server-side twin of `inject_country_sync()`: the script keeps
     * a *visible* Country box in step, but when the admin switched that box off
     * there is nothing on the page to sync and the value has to be derived from
     * what was posted. Reading `$_POST` directly is the same trick `add_hidden()`
     * relies on - `definition()` runs before QuickForm copies the submission in.
     *
     * @return string ISO alpha-2, or '' when nothing usable was posted
     */
    public static function posted_phone_country(): string {
        $element = self::phone_element();
        if ($element === '' || empty($_POST[$element]) || !is_array($_POST[$element])) {
            return '';
        }

        // Read by hand rather than with optional_param_array(): that helper raises a
        // coding exception when a value is itself an array, which a hand-made POST
        // can arrange, and this runs while the sign-up form is being built.
        $iso = $_POST[$element]['country'] ?? '';
        if (!is_scalar($iso)) {
            return '';
        }

        return self::phone_country([$element => ['country' => clean_param((string) $iso, PARAM_ALPHA)]]);
    }

    /**
     * The country the account's saved phone number carries.
     *
     * The completion page can be finishing a registration whose phone was answered
     * earlier - core's own /user/edit.php redirect may have collected it - in which
     * case nothing is posted for the phone and the country still has to come from
     * somewhere. `profilefield_phone` stores the pair as `ISO:number`.
     *
     * @param \stdClass $user the account to read
     * @return string ISO alpha-2, or '' when there is no usable stored value
     */
    public static function stored_phone_country(\stdClass $user): string {
        global $DB;

        $element = self::phone_element();
        if ($element === '' || empty($user->id)) {
            return '';
        }
        $shortname = substr($element, strlen(self::CUSTOM_PREFIX));

        // profile_load_custom_fields() may already have put it on the session user.
        $raw = (string) ($user->profile[$shortname] ?? '');
        if ($raw === '') {
            $raw = (string) $DB->get_field_sql(
                "SELECT d.data
                   FROM {user_info_data} d
                   JOIN {user_info_field} f ON f.id = d.fieldid
                  WHERE d.userid = ? AND f.shortname = ?",
                [$user->id, $shortname]);
        }
        if ($raw === '' || strpos($raw, ':') === false) {
            return '';
        }
        [$iso] = explode(':', $raw, 2);

        return self::phone_country([$element => ['country' => $iso]]);
    }

    /**
     * Validate that the visitor's IP country matches the phone country.
     *
     * @param array $data submitted sign-up values
     * @return array element name => error message (empty when the sign-up may proceed)
     */
    public static function validate_ip_match(array $data): array {
        // The phone field the check applies to (first one shown on sign-up).
        $element = self::phone_element();
        if ($element === '') {
            return [];
        }
        $value = $data[$element] ?? null;
        if (!is_array($value) || empty($value['country'])) {
            return [];
        }

        $error = self::ip_country_error((string) $value['country']);
        return $error === null ? [] : [$element => $error];
    }

    /**
     * Refuse a registration coming from an address on the deny list.
     *
     * The error is pinned to a caller-chosen element rather than to the phone field:
     * the deny list has nothing to do with the number that was typed, and an error
     * keyed to an element that does not render produces a form that refuses to
     * submit with nothing shown. `email` is the right anchor on the sign-up form;
     * complete.php has no email box and names one of its own.
     *
     * @param string $element the form element to pin the message to
     * @return array element name => error message (empty when the address is fine)
     */
    public static function validate_ip_allowed(string $element = 'email'): array {
        if (!blocklist::blocks()) {
            return [];
        }

        blocklog::record(blocklog::REASON_BLOCKED);

        return [$element => get_string('ipblocked', 'local_profilefields')];
    }

    /**
     * The location error for a chosen country, or null when sign-up may proceed.
     *
     * This is the single implementation of the rule. The web sign-up form reaches it
     * through `validate_ip_match()`, and `profilefield_phone` calls it straight from
     * `edit_validate_field()` so the web-service sign-up used by the mobile app is
     * covered too - core's `signup_validate_data()` runs profile-field validation on
     * both paths, but only the web form runs the plugin sign-up callbacks.
     *
     * Three outcomes, and every one of them that refuses also writes a row to
     * {@see blocklog} so the reports page can account for it:
     *
     * - the countries agree, or the check is switched off: allowed;
     * - the countries disagree: refused as a mismatch;
     * - nothing could resolve the address: refused, unless the admin has turned
     *   {@see manager::block_unresolved_ip()} off, in which case it is allowed.
     *
     * @param string $iso the alpha-2 country code the visitor chose
     * @return string|null localised error message, or null when the sign-up is fine
     */
    public static function ip_country_error(string $iso): ?string {
        // Every caller can fire in one request; the verdict cannot change mid-submit.
        static $verdicts = [];

        $iso = strtoupper(trim($iso));
        if ($iso === '' || !manager::ip_match_phone()
                || !class_exists('\profilefield_phone\dialcodes')) {
            return null;
        }
        if (array_key_exists($iso, $verdicts)) {
            return $verdicts[$iso];
        }

        // AC-4.6.6: an exempt address is never compared to a country. Checked
        // before the lookup, not after - the point of an exemption is to skip the
        // check, and running it anyway would still cost the visitor the network
        // call this is meant to spare them.
        if (allowlist::exempts()) {
            return $verdicts[$iso] = null;
        }

        // Allow the free online fallback here: this runs once, on submit, only when
        // the admin turned the check on.
        $ipiso = \profilefield_phone\dialcodes::country_from_ip(true);

        if ($ipiso === '') {
            // AC-4.6.10: every source was unreachable. That is an outage, not a
            // fact about this visitor, so it gets its own message ("temporarily
            // unavailable" rather than "your country does not match") and raises an
            // alert - nobody can register while it lasts, and without the alert the
            // first anyone would know is a support ticket. Registration is refused
            // either way; browsing and pricing keep working on the default price,
            // because nothing on those paths consults this method.
            if (\profilefield_phone\dialcodes::service_was_down()) {
                blocklog::record(blocklog::REASON_SERVICEDOWN, $iso);
                self::alert_service_down();

                return $verdicts[$iso] = get_string('ipservicedown', 'local_profilefields');
            }

            // Nothing resolved the address - no geo-IP source, a lookup that
            // declined to place it, or a private/reserved address such as a LAN
            // client behind no proxy. The rule cannot be applied, and whether that
            // means "let them through" or "turn them away" is a policy question, so
            // it is an admin setting rather than a silent default. Note that a site
            // behind a reverse proxy needs $CFG->getremoteaddrconf set so
            // getremoteaddr() returns the real client address; otherwise every
            // visitor looks like the proxy and resolves to nothing.
            if (!manager::block_unresolved_ip()) {
                return $verdicts[$iso] = null;
            }

            blocklog::record(blocklog::REASON_UNRESOLVED, $iso);

            return $verdicts[$iso] = get_string('ipunresolved', 'local_profilefields');
        }

        if ($ipiso === $iso) {
            return $verdicts[$iso] = null;
        }

        blocklog::record(blocklog::REASON_MISMATCH, $iso, $ipiso);

        return $verdicts[$iso] = get_string('ipmismatch', 'local_profilefields');
    }

    /**
     * Raise the operational alert AC-4.6.10 asks for when geolocation is down.
     *
     * Delivered as an admin notification rather than an email: it is the standard
     * channel for "the site needs attention", it reaches whoever is actually
     * administering rather than an address configured once and forgotten, and it
     * shows on the notifications page where the rest of the site's health lives.
     *
     * Throttled to one alert an hour through a config flag. An outage produces one
     * refusal per visitor attempting to register, and a busy hour would otherwise
     * bury the administrator in identical notices about a single fault - which is
     * a reliable way to make the next alert be ignored.
     *
     * @return void
     */
    protected static function alert_service_down(): void {
        $last = (int) get_config(manager::COMPONENT, 'servicedownalerted');

        if ($last > (time() - HOURSECS)) {
            return;
        }

        set_config('servicedownalerted', time(), manager::COMPONENT);

        try {
            $message = get_string('servicedownalert', 'local_profilefields');

            foreach (get_admins() as $admin) {
                $notification = new \core\message\message();
                $notification->component = 'moodle';
                $notification->name = 'errors';
                $notification->userfrom = \core_user::get_noreply_user();
                $notification->userto = $admin;
                $notification->subject = get_string('reasonservicedown', 'local_profilefields');
                $notification->fullmessage = $message;
                $notification->fullmessageformat = FORMAT_PLAIN;
                $notification->fullmessagehtml = \html_writer::tag('p', s($message));
                $notification->smallmessage = get_string('reasonservicedown', 'local_profilefields');
                $notification->notification = 1;

                message_send($notification);
            }
        } catch (\Throwable $e) {
            // A failure to raise the alarm must not become a second failure on top
            // of the one being reported: the visitor is already being refused, and
            // throwing here would turn that refusal into a white screen.
            debugging('local_profilefields: could not raise the geolocation alert: '
                . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Replace the username box with a value derived from the email address.
     *
     * @param MoodleQuickForm $mform the sign-up form, mid-definition
     * @return void
     */
    protected static function autofill_username(MoodleQuickForm $mform): void {
        $username = manager::derive_username(optional_param('email', '', PARAM_RAW));

        if ($mform->elementExists('username')) {
            $mform->removeElement('username', true);
        }
        self::clear_rules($mform, 'username');
        self::add_hidden($mform, 'username', $username);
    }

    /**
     * Take a core field off the sign-up form without leaving core's checks starved.
     *
     * @param MoodleQuickForm $mform the sign-up form, mid-definition
     * @param string $name core field name
     * @return void
     */
    protected static function switch_off(MoodleQuickForm $mform, string $name): void {
        $fallback = self::fallback_value($name);

        $mform->removeElement($name, true);
        self::clear_rules($mform, $name);
        self::add_hidden($mform, $name, $fallback);
    }

    /**
     * The value a switched-off field should still submit.
     *
     * @param string $name core field name
     * @return string
     */
    protected static function fallback_value(string $name): string {
        global $CFG;

        switch ($name) {
            case 'email2':
                // The confirmation box only exists to catch typos; with the box gone
                // there is nothing to compare, so it mirrors the address itself.
                return (string) optional_param('email', '', PARAM_RAW);
            case 'city':
                return (string) ($CFG->defaultcity ?? '');
            case 'country':
                // "Country follows the phone" has to hold whether or not the Country
                // box is on the form. When it is, the sync script does it in the
                // browser; when it is switched off - which is how this site is set
                // up - there is no select to sync, so the hidden input carries the
                // posted phone country instead of the site default. Without this the
                // web form stores $CFG->country for everyone, and pricing falls back
                // to a geo-IP guess for an answer the user already gave us.
                if (manager::country_from_phone()) {
                    $iso = self::posted_phone_country();
                    if ($iso !== '') {
                        return $iso;
                    }
                }
                return (string) ($CFG->country ?? '');
            default:
                return '';
        }
    }

    /**
     * Add a hidden element carrying a server-decided value.
     *
     * The constant covers the first render, the `$_POST` write covers submission -
     * `HTML_QuickForm_input::exportValue()` prefers submitted data over constants,
     * so both are needed for the value to survive a round trip.
     *
     * @param MoodleQuickForm $mform the sign-up form, mid-definition
     * @param string $name element name
     * @param string $value value to carry
     * @return void
     */
    protected static function add_hidden(MoodleQuickForm $mform, string $name, string $value): void {
        $mform->addElement('hidden', $name);
        $mform->setType($name, PARAM_RAW);
        $mform->setConstant($name, $value);

        if (!empty($_POST)) {
            $_POST[$name] = $value;
        }
    }

    /**
     * Drop every rule attached to an element, including its "required" flag.
     *
     * This mirrors what `MoodleQuickForm::hardFreeze()` does. A rule left behind
     * for an element that is gone still runs on the server *and* is written into
     * the generated client-side validation script, where it looks for a field the
     * page no longer has.
     *
     * @param MoodleQuickForm $mform the sign-up form, mid-definition
     * @param string $name element name
     * @return void
     */
    protected static function clear_rules(MoodleQuickForm $mform, string $name): void {
        $mform->_rules[$name] = [];

        $key = array_search($name, $mform->_required, true);
        if ($key !== false) {
            unset($mform->_required[$key]);
        }
    }

    /**
     * Put the visible sign-up fields into the admin's chosen order.
     *
     * Works on a unified list of both core fields and custom profile fields, so an
     * admin can place a custom field (phone, nationality) above a core one
     * (password). Tokens from the stored order map to element names: a core name is
     * the element name as-is; `cf:<shortname>` maps to `profile_field_<shortname>`.
     *
     * @param MoodleQuickForm $mform the sign-up form, mid-definition
     * @param array $config config map keyed by field name
     * @return void
     */
    protected static function reorder(MoodleQuickForm $mform, array $config): void {
        // Element name for every token that is actually on the form right now.
        $elementfor = [];
        foreach (self::tokens_present($mform) as $token) {
            $element = self::token_element($token);
            if ($element !== '' && $mform->elementExists($element)) {
                $elementfor[$token] = $element;
            }
        }
        if (count($elementfor) < 2) {
            return;
        }

        // The element names to place, in order. The password-policy blurb is left
        // out on purpose - it is not a field of its own, and attach_password_policy()
        // pins it under the password box once the fields are in their final order.
        $wanted = [];
        foreach (manager::order_tokens(array_keys($elementfor)) as $token) {
            $wanted[] = $elementfor[$token];
        }
        $wantedset = array_flip($wanted);

        // Rebuild the element list in one pass rather than moving elements one by one:
        // MoodleQuickForm's removeElement()/insertElementBefore() do not keep the
        // element-name index consistent across a run of moves, so the second move
        // onwards resolves the wrong element and the form ends up duplicated. Pulling
        // the wanted elements out, ordering them, and splicing the block back in at
        // the first one's old position is deterministic.
        $others = [];
        $found = [];
        $insertat = null;
        foreach ($mform->_elements as $element) {
            $name = (string) $element->getName();
            if (isset($wantedset[$name]) && !isset($found[$name])) {
                $found[$name] = $element;
                if ($insertat === null) {
                    $insertat = count($others);
                }
            } else {
                $others[] = $element;
            }
        }
        if ($insertat === null) {
            return;
        }

        $block = [];
        foreach ($wanted as $name) {
            if (isset($found[$name])) {
                $block[] = $found[$name];
            }
        }

        array_splice($others, $insertat, 0, $block);
        $mform->_elements = array_values($others);
        self::rebuild_index($mform);
    }

    /** @var string Wrapper class the theme styles the policy blurb as a field hint with. */
    const POLICY_CLASS = 'localprofilefields-passwordpolicy';

    /**
     * Turn the password-policy blurb into a hint sitting under the password box.
     *
     * Core adds `passwordpolicyinfo` as a static element *before* the password
     * element, so the rules render as a full form row above the box they describe -
     * read top to bottom, they look like a stray sentence belonging to the field
     * above. Moving the element after the password box, and tagging its wrapper so
     * the theme can render it small and muted, makes it read as that field's hint.
     *
     * Runs after reorder(), which deliberately ignores the blurb: it is not a field
     * an admin can place, it just follows the password box wherever that lands.
     *
     * @param MoodleQuickForm $mform the sign-up form, mid-definition
     * @return void
     */
    protected static function attach_password_policy(MoodleQuickForm $mform): void {
        if (!$mform->elementExists('passwordpolicyinfo') || !$mform->elementExists('password')) {
            return;
        }

        // An element's `class` attribute lands on the wrapper row as "extraclasses".
        $mform->getElement('passwordpolicyinfo')->updateAttributes(['class' => self::POLICY_CLASS]);

        // Rebuilt in one pass for the same reason reorder() does it that way:
        // removeElement()/insertElementBefore() leave the name-to-index map stale.
        $policy = null;
        $rest = [];
        foreach ($mform->_elements as $element) {
            if ($policy === null && (string) $element->getName() === 'passwordpolicyinfo') {
                $policy = $element;
                continue;
            }
            $rest[] = $element;
        }
        if ($policy === null) {
            return;
        }

        // A site can have the password policy switched on and still have nothing
        // to say about it - every rule left at zero makes print_password_policy()
        // return an empty string. Core adds the element on the setting alone, so
        // what lands on the form is an empty row: no label, no text, and the full
        // height of a field. Drop it rather than leave a blank gap in the middle
        // of the form, but only when it really is empty - the moment the site
        // states a rule, the hint comes back under the password box.
        if (trim(html_to_text((string) $policy->toHtml(), 0, false)) === '') {
            $mform->_elements = $rest;
            self::rebuild_index($mform);
            return;
        }

        foreach ($rest as $i => $element) {
            if ((string) $element->getName() === 'password') {
                array_splice($rest, $i + 1, 0, [$policy]);
                $mform->_elements = $rest;
                self::rebuild_index($mform);
                return;
            }
        }
    }

    /**
     * Rebuild the form's name-to-index maps from the current element list.
     *
     * Mirrors how MoodleQuickForm indexes elements as they are added, so the form
     * behaves identically after the element list has been reordered in place.
     *
     * @param MoodleQuickForm $mform the sign-up form, mid-definition
     * @return void
     */
    protected static function rebuild_index(MoodleQuickForm $mform): void {
        $mform->_elementIndex = [];
        $mform->_duplicateIndex = [];
        foreach ($mform->_elements as $i => $element) {
            $name = $element->getName();
            if (!isset($mform->_elementIndex[$name])) {
                $mform->_elementIndex[$name] = $i;
            } else {
                $mform->_duplicateIndex[$name][] = $i;
            }
        }
    }

    /** @var string Prefix Moodle gives every custom profile field element. */
    const CUSTOM_PREFIX = 'profile_field_';

    /**
     * Remove the custom-field category headers from the sign-up form.
     *
     * @param MoodleQuickForm $mform the sign-up form, mid-definition
     * @return void
     */
    protected static function strip_custom_headers(MoodleQuickForm $mform): void {
        $remove = [];
        foreach ($mform->_elements as $element) {
            $name = (string) $element->getName();
            if ($element->getType() === 'header' && preg_match('/^category_\d+$/', $name)) {
                $remove[] = $name;
            }
        }
        foreach ($remove as $name) {
            $mform->removeElement($name, true);
        }
    }

    /**
     * The order tokens for the sign-up fields currently on the form.
     *
     * A core field contributes its own name; a custom field contributes
     * `cf:<shortname>`. Only fields this plugin is willing to reorder are returned -
     * the reCAPTCHA, policy checkbox, buttons and hidden helpers are left alone.
     *
     * @param MoodleQuickForm $mform the sign-up form, mid-definition
     * @return string[] tokens in their present order
     */
    protected static function tokens_present(MoodleQuickForm $mform): array {
        $core = manager::core_fields();
        $tokens = [];
        foreach ($mform->_elements as $element) {
            $name = (string) $element->getName();
            if ($name === '' || $element->getType() === 'hidden') {
                // Switched-off fields and the derived username are hidden inputs, not
                // things to place among the visible fields.
                continue;
            }
            if (isset($core[$name]) && !empty($core[$name]['onsignup'])) {
                $tokens[] = $name;
            } else if (strpos($name, self::CUSTOM_PREFIX) === 0) {
                $tokens[] = 'cf:' . substr($name, strlen(self::CUSTOM_PREFIX));
            }
        }
        return $tokens;
    }

    /**
     * The form element name a reorder token points at.
     *
     * @param string $token a core field name or `cf:<shortname>`
     * @return string element name, or '' when the token is malformed
     */
    protected static function token_element(string $token): string {
        if (strpos($token, 'cf:') === 0) {
            $shortname = substr($token, 3);
            return $shortname === '' ? '' : self::CUSTOM_PREFIX . $shortname;
        }
        return $token;
    }

}
