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

namespace local_profilefields\form;

use core_user;
use local_profilefields\manager;
use local_profilefields\policies;
use local_profilefields\signup;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

/**
 * The "finish your registration" form.
 *
 * Shows only what `completion::missing()` says is outstanding - never a field the
 * user has already answered. Somebody arriving from Google has their name and
 * email already, so in practice this is two or three boxes.
 *
 * Every element is added the same way the sign-up form adds it: the core boxes
 * copy `login/signup_form.php`, and each custom field renders itself through
 * `profile_field_base::edit_field()`, which is the same call `user/edit.php` and
 * the sign-up form both make. That is what keeps the two forms from drifting -
 * there is no second copy of a field definition anywhere in here.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class complete_form extends moodleform {

    /**
     * Build the form from the outstanding requirements.
     *
     * @return void
     */
    protected function definition() {
        global $CFG;

        $mform = $this->_form;
        $missing = $this->_customdata['missing'];

        $mform->addElement('hidden', 'returnurl');
        $mform->setType('returnurl', PARAM_LOCALURL);

        // One interleaved pass, so the boxes land in the sign-up order rather than
        // core-then-custom.
        $config = manager::get_config();

        foreach ($missing['fields'] as $entry) {
            if ($entry['kind'] === 'core') {
                $this->add_core_field($entry['name']);
                // Honour the "Rename" column on the management page, the same way
                // signup::apply() does - otherwise the box the user was going to see
                // as "Nationality" turns up here labelled "Country". Custom fields
                // need no equivalent: their name lives on the field record itself,
                // which is what edit_field() reads.
                $label = trim((string) ($config[$entry['name']]['label'] ?? ''));
                if ($label !== '' && $mform->elementExists($entry['name'])) {
                    $mform->getElement($entry['name'])->setLabel(format_string($label));
                }
                // An account that skipped sign-up is asked to confirm what the
                // system filled in for it, not to retype it from scratch. (Custom
                // fields need no help: edit_field_set_default() already preselects
                // the user's own value.)
                if ($entry['current'] !== '') {
                    $mform->setDefault($entry['name'], $entry['current']);
                }
            } else {
                // The field object owns its element, its default and its rules.
                $entry['field']->edit_field($mform);
            }
        }

        if (!empty($missing['consent'])) {
            $mform->addElement('advcheckbox', signup::CONSENT, '', policies::consent_label());
            $mform->setType(signup::CONSENT, PARAM_INT);
        }

        // Same courtesy the sign-up form does: keep the country box in step with the
        // phone's country code, so most people never touch it.
        if (manager::country_from_phone()
                && $mform->elementExists('country')
                && $mform->elementExists(signup::CUSTOM_PREFIX . 'phone')) {
            signup::inject_country_sync();
        }

        $this->add_action_buttons(false, get_string('completesave', 'local_profilefields'));
    }

    /**
     * Add one built-in Moodle field, defined exactly as sign-up defines it.
     *
     * Every name in `completion::RENDERABLE_CORE` must have a case here - that
     * constant is what stops the gate ever asking for a field this method cannot
     * draw, which would be an inescapable redirect loop.
     *
     * @param string $name core user field name
     * @return void
     */
    protected function add_core_field(string $name): void {
        global $CFG;

        $mform = $this->_form;

        switch ($name) {
            case 'city':
                $mform->addElement('text', 'city', get_string('city'), 'maxlength="120" size="20"');
                $mform->setType('city', core_user::get_property_type('city'));
                if (!empty($CFG->defaultcity)) {
                    $mform->setDefault('city', $CFG->defaultcity);
                }
                $mform->addRule('city', get_string('required'), 'required', null, 'client');
                break;

            case 'country':
                $countries = array_merge(
                    ['' => get_string('selectacountry')],
                    get_string_manager()->get_list_of_countries()
                );
                $mform->addElement('select', 'country', get_string('country'), $countries);
                $mform->setDefault('country', '');
                $mform->addRule('country', get_string('required'), 'required', null, 'client');
                break;

            case 'firstname':
            case 'lastname':
                $mform->addElement('text', $name, get_string($name));
                $mform->setType($name, core_user::get_property_type($name));
                $mform->addRule($name, get_string('required'), 'required', null, 'client');
                break;
        }
    }

    /**
     * Server-side checks the client rules cannot make.
     *
     * @param array $data submitted values
     * @param array $files
     * @return array element name => message
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $missing = $this->_customdata['missing'];

        // An unticked advcheckbox submits 0 rather than nothing, so a 'required'
        // rule never fires on it - the sign-up form checks it the same way.
        if (!empty($missing['consent']) && empty($data[signup::CONSENT])) {
            $errors[signup::CONSENT] = get_string('consentrequired', 'local_profilefields');
        }

        // Custom fields carry their own validation (uniqueness, phone format, ...).
        foreach ($missing['fields'] as $entry) {
            if ($entry['kind'] === 'custom') {
                $errors = array_merge($errors, (array) $entry['field']->edit_validate_field((object) $data));
            }
        }

        // The "phone country must match the visitor's location" rule.
        //
        // profilefield_phone applies it only to a visitor creating an account
        // (field.class.php: `$this->userid == 0 && !isloggedin()`), because an
        // ordinary profile edit is legitimately done from another country. This page
        // is neither: it IS the sign-up questions, just asked late, so the rule has
        // to be applied here by hand or a Google account becomes the way around it -
        // register with a Saudi number from an Egyptian address and nothing stops
        // you, which the sign-up form would have refused.
        if (manager::ip_match_phone()) {
            $errors = array_merge($errors, signup::validate_ip_match($data));
        }

        return $errors;
    }
}
