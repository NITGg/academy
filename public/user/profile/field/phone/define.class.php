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
 * Phone profile field definition.
 *
 * @package    profilefield_phone
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use profilefield_phone\dialcodes;

defined('MOODLE_INTERNAL') || die();

/**
 * Admin-side definition form for a phone field.
 *
 * The only field-specific setting is the country to preselect when neither the
 * user's IP nor the site default resolves. Everything else (required, unique,
 * locked, visibility, signup) is the standard profile-field machinery.
 *
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_define_phone extends profile_define_base {

    /**
     * Add the default-country selector.
     *
     * @param moodleform $form
     */
    public function define_form_specific($form) {
        $countries = ['' => get_string('choosedots')] + dialcodes::menu();
        $form->addElement('select', 'defaultdata',
            get_string('defaultcountry', 'profilefield_phone'), $countries);
        $form->setType('defaultdata', PARAM_ALPHA);
        $form->addHelpButton('defaultdata', 'defaultcountry', 'profilefield_phone');
    }
}
