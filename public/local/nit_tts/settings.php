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
 * Site settings and admin page for local_nit_tts.
 *
 * @package    local_nit_tts
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_settingpage('local_nit_tts', get_string('settings', 'local_nit_tts'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configpasswordunmask(
        'local_nit_tts/apikey',
        get_string('setting_apikey', 'local_nit_tts'),
        get_string('setting_apikey_desc', 'local_nit_tts'),
        ''
    ));

    // Gemini ships its TTS models as previews and renames them on release, so the
    // model is a setting rather than a constant.
    $settings->add(new admin_setting_configtext(
        'local_nit_tts/model',
        get_string('setting_model', 'local_nit_tts'),
        get_string('setting_model_desc', 'local_nit_tts'),
        'gemini-2.5-flash-preview-tts',
        PARAM_ALPHANUMEXT
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_nit_tts_page',
        get_string('pluginname', 'local_nit_tts'),
        new moodle_url('/local/nit_tts/index.php')
    ));
}
