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
 * File serving for the file profile field.
 *
 * @package    profilefield_file
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serve a file stored in a file profile field.
 *
 * Reached through the generic plugin branch of file_pluginfile(), which calls this
 * with the arguments below. The URL shape is:
 *   /pluginfile.php/{usercontextid}/profilefield_file/files/{fieldid}/{filename}
 *
 * Access is decided by the profile field's own visibility setting, so a file is
 * exactly as public - or as private - as the field it belongs to.
 *
 * @param stdClass|null $course unused, no course for a user context
 * @param stdClass|null $cm unused
 * @param context $context the user context the file lives in
 * @param string $filearea must be 'files'
 * @param array $args itemid (the profile field id), then the file path parts
 * @param bool $forcedownload
 * @param array $options
 * @return void the function either sends the file or terminates
 */
function profilefield_file_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/user/profile/lib.php');

    // The literal 'files' rather than profile_field_file::FILEAREA: the field class
    // is only require_once'd by profile_get_user_field() further down, so the
    // constant does not exist yet at this point.
    if ($context->contextlevel != CONTEXT_USER || $filearea !== 'files') {
        send_file_not_found();
    }

    if (!empty($CFG->forcelogin)) {
        require_login();
    }

    if (count($args) < 2) {
        send_file_not_found();
    }

    $fieldid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    // The itemid must be a real file field, otherwise the URL is guessing.
    if (!$DB->record_exists('user_info_field', ['id' => $fieldid, 'datatype' => 'file'])) {
        send_file_not_found();
    }

    $userid = (int) $context->instanceid;

    // Apply the field's own visibility rules to the viewer.
    $field = profile_get_user_field('file', $fieldid, $userid);
    if (!$field->is_visible($context)) {
        send_file_not_found();
    }

    $file = get_file_storage()->get_file($context->id, 'profilefield_file',
        'files', $fieldid, $filepath, $filename);

    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 60 * 60, 0, $forcedownload, $options);
}
