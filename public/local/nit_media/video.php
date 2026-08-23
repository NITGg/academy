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
 * @package    local_nit_media
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

// Front page marketing content, so it is public unless the whole site is not.
if (!empty($CFG->forcelogin)) {
    require_login();
}

$context = context_system::instance();

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

if (!$file) {
    // Nothing uploaded yet. The hero block turns this into a visible message
    // with this address in it, rather than a player that spins forever.
    send_file_not_found();
}

// Nothing below writes to the session, and holding it open blocks the user's
// other requests while a large file streams.
core\session\manager::write_close();

send_stored_file($file, DAY_SECS, 0, false, ['cacheability' => 'public']);
