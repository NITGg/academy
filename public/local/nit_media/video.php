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
 * Serves the home page hero video at one stable address.
 *
 * The hero is a static HTML block: it cannot ask Moodle for a file name, so it
 * cannot use a pluginfile URL, which carries the name and changes whenever the
 * admin uploads a different file. This endpoint is the indirection — the block
 * always points here and this always serves whatever is currently uploaded on
 * the plugin's settings page. Replacing the video needs no edit to the block.
 *
 * send_stored_file byteserves, so seeking in the player works (see
 * byteserving_send_file in lib/filelib.php).
 *
 * Append ?diag=1 as a site administrator to see what is actually stored
 * instead of streaming it — that tells a missing file apart from a file the
 * browser cannot decode, which are indistinguishable from the player's error.
 *
 * @package    local_nit_media
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

$diag = optional_param('diag', 0, PARAM_BOOL);

// Front page marketing content, so it is public unless the whole site is not.
if ($diag || !empty($CFG->forcelogin)) {
    require_login();
}

$context = context_system::instance();

if ($diag) {
    require_capability('moodle/site:config', $context);
}

$fs = get_file_storage();
$files = $fs->get_area_files(
    $context->id,
    'local_nit_media',
    'herovideo',
    0,
    'itemid, filepath, filename',
    false
);

$file = reset($files);

if ($diag) {
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/local/nit_media/video.php', ['diag' => 1]));
    $PAGE->set_title(get_string('diagnostics', 'local_nit_media'));
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('diagnostics', 'local_nit_media'));

    if (!$file) {
        echo $OUTPUT->notification(
            'No file is stored in local_nit_media/herovideo. Upload one on the plugin settings page.',
            'error'
        );
    } else {
        $rows = [
            'File name'  => $file->get_filename(),
            'Size'       => display_size($file->get_filesize()),
            'MIME type'  => $file->get_mimetype(),
            'Uploaded'   => userdate($file->get_timemodified()),
            'Served at'  => (new moodle_url('/local/nit_media/video.php'))->out(),
        ];
        $table = new html_table();
        $table->data = array_map(null, array_keys($rows), array_values($rows));
        echo html_writer::table($table);
        echo $OUTPUT->notification(
            'The file is present and will be streamed. If the player still refuses it, the container or '
            . 'codec is the problem rather than the upload - re-encode as an H.264 MP4.',
            'info'
        );
    }

    echo $OUTPUT->footer();
    exit;
}

if (!$file) {
    // Nothing uploaded yet. The hero block turns this into a visible message
    // with this address in it, rather than a player that spins forever.
    send_file_not_found();
}

// send_stored_file unlocks the session itself before streaming
// (\core\session\manager::write_close in lib/filelib.php), so there is nothing
// to do here first.
send_stored_file($file, DAYSECS, 0, false, ['cacheability' => 'public']);
