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
 * File profile field definition.
 *
 * @package    profilefield_file
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Settings shown on the "Create a new profile field: File" admin screen.
 *
 * The three parameters map onto the user_info_field param columns:
 *   param1 - accepted file types (a `filetypes` element value, '*' for any)
 *   param2 - maximum upload size in bytes (0 = follow the site limit)
 *   param3 - how the stored file is rendered on the profile: 'link' or 'image'
 *
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_define_file extends profile_define_base {

    /**
     * Add the file-specific settings to the field definition form.
     *
     * @param MoodleQuickForm $form
     */
    public function define_form_specific($form) {
        global $CFG;

        // A file field has no meaningful default value, but the column is written
        // by the shared define_save(), so keep a hidden empty one like
        // profilefield_social does.
        $form->addElement('hidden', 'defaultdata', '');
        $form->setType('defaultdata', PARAM_TEXT);

        // Param 1: which file types may be uploaded.
        $form->addElement('filetypes', 'param1', get_string('acceptedtypes', 'profilefield_file'));
        $form->setType('param1', PARAM_RAW);
        $form->setDefault('param1', '*');
        $form->addHelpButton('param1', 'acceptedtypes', 'profilefield_file');

        // Param 2: maximum upload size, capped by the site limit.
        $choices = [0 => get_string('sitedefaultsize', 'profilefield_file')] + get_max_upload_sizes($CFG->maxbytes);
        $form->addElement('select', 'param2', get_string('maxbytes', 'profilefield_file'), $choices);
        $form->setType('param2', PARAM_INT);
        $form->setDefault('param2', 0);

        // Param 3: link (a download link) or image (rendered inline).
        $form->addElement('select', 'param3', get_string('displaymode', 'profilefield_file'), [
            'link'  => get_string('displaymodelink', 'profilefield_file'),
            'image' => get_string('displaymodeimage', 'profilefield_file'),
        ]);
        $form->setType('param3', PARAM_ALPHA);
        $form->setDefault('param3', 'link');
        $form->addHelpButton('param3', 'displaymode', 'profilefield_file');
    }

    /**
     * Normalise the settings before they are written.
     *
     * @param array|stdClass $data from the add/edit profile field form
     * @return array|stdClass
     */
    public function define_save_preprocess($data) {
        $data = (object) $data;

        if (empty($data->param1)) {
            $data->param1 = '*';
        }
        $data->param2 = (int) ($data->param2 ?? 0);
        if (($data->param3 ?? '') !== 'image') {
            $data->param3 = 'link';
        }

        // A file field can never be "unique": the stored value is a filename, and
        // two people may legitimately upload files with the same name.
        $data->forceunique = 0;

        // Uploading needs a user context, which does not exist until the account
        // does, so this field type cannot appear on the sign-up form.
        $data->signup = 0;

        return $data;
    }
}
