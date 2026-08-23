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
 * Settings for local_nit_media.
 *
 * One page under Local plugins where the home page hero video is uploaded,
 * replaced or removed. The file manager below gives all three: drop a file in
 * to upload, delete the existing one to remove it, do both to replace it.
 *
 * @package    local_nit_media
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_nit_media_settings', get_string('pluginname', 'local_nit_media'));
    $ADMIN->add('localplugins', $settings);

    // The hero block is static HTML and cannot interpolate a file name, so it
    // points at one fixed address that always serves whatever was uploaded
    // last. Showing that address here is the whole contract between the two.
    $endpoint = (new moodle_url('/local/nit_media/video.php'))->out();

    $settings->add(new admin_setting_heading(
        'local_nit_media/herovideoheading',
        get_string('herovideoheading', 'local_nit_media'),
        get_string('herovideoheading_desc', 'local_nit_media', html_writer::link($endpoint, $endpoint))
    ));

    $settings->add(new admin_setting_configstoredfile(
        'local_nit_media/herovideo',
        get_string('herovideo', 'local_nit_media'),
        get_string('herovideo_desc', 'local_nit_media'),
        'herovideo',
        0,
        [
            'maxfiles' => 1,
            'accepted_types' => ['.mp4', '.m4v', '.webm', '.ogv'],
        ]
    ));
}
