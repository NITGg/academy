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
    protected static function inject_country_sync(): void {
        global $PAGE;

        $js = <<<'JS'
(function() {
    var phone = document.querySelector('select[name="profile_field_phone[country]"]');
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
     * Validate that the visitor's IP country matches the phone country.
     *
     * Returns an error only when a geo-IP source resolves the IP to a *different*
     * country. When no geo-IP is configured, or the address cannot be resolved, the
     * check is skipped rather than blocking a legitimate sign-up.
     *
     * @param array $data submitted sign-up values
     * @return array element name => error message (empty when OK or skipped)
     */
    public static function validate_ip_match(array $data): array {
        global $DB, $CFG;

        if (!class_exists('\profilefield_phone\dialcodes')) {
            return [];
        }

        // The phone field the check applies to (first one shown on sign-up).
        $field = $DB->get_record_select('user_info_field',
            "datatype = ? AND signup = 1", ['phone'], '*', IGNORE_MULTIPLE);
        if (!$field) {
            return [];
        }
        $element = self::CUSTOM_PREFIX . $field->shortname;
        $value = $data[$element] ?? null;
        if (!is_array($value) || empty($value['country'])) {
            return [];
        }

        $ipiso = \profilefield_phone\dialcodes::country_from_ip();
        if ($ipiso === '') {
            // No geo-IP source, or the address is unresolvable - do not block.
            return [];
        }

        if (strtoupper($value['country']) !== $ipiso) {
            return [$element => get_string('ipmismatch', 'local_profilefields')];
        }
        return [];
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

        // The element names to place, in order. The password box carries its policy
        // blurb (a separate static element that only reads right directly above it).
        $wanted = [];
        foreach (manager::order_tokens(array_keys($elementfor)) as $token) {
            $name = $elementfor[$token];
            if ($name === 'password' && $mform->elementExists('passwordpolicyinfo')) {
                $wanted[] = 'passwordpolicyinfo';
            }
            $wanted[] = $name;
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
