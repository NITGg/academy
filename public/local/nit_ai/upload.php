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
 * Chunked upload of a lesson video to this server (js/upload.js drives it).
 *
 *   POST action=start  sesskey, courseid, filename, filesize
 *        → {"token": "…", "chunk": 16777216}
 *   POST action=chunk  sesskey, courseid, token, offset, chunk=<file part>
 *        → {"received": n}
 *   POST action=finish sesskey, courseid, token, title   (once every byte is in; safe to repeat)
 *        → {"videoid": "…"}
 *   any error → {"error": "…", "received": n?}
 *
 * See \local_nit_ai\upload\manager for what happens to the file afterwards.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../config.php');

use local_nit_ai\upload\manager;

$courseid = required_param('courseid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);

require_login($courseid, false);
require_sesskey();
$context = context_course::instance($courseid);

header('Content-Type: application/json; charset=utf-8');

try {
    // Creating a video and its transcript: the same people who may add the activity.
    require_capability('moodle/course:manageactivities', $context);
    require_capability('local/nit_ai:manage', $context);

    if ($action === 'start') {
        $row = manager::start(
            $courseid,
            required_param('filename', PARAM_FILE),
            required_param('filesize', PARAM_INT)
        );
        echo json_encode(['token' => $row->token, 'chunk' => manager::CHUNK]);

    } else if ($action === 'chunk') {
        if (empty($_FILES['chunk']['tmp_name']) || !is_uploaded_file($_FILES['chunk']['tmp_name'])) {
            throw new moodle_exception('upload_err_write', 'local_nit_ai');
        }
        \core_php_time_limit::raise(120);

        $row = manager::append(
            required_param('token', PARAM_ALPHANUM),
            required_param('offset', PARAM_INT),
            $_FILES['chunk']['tmp_name']
        );
        echo json_encode(['received' => (int) $row->received]);

    } else if ($action === 'finish') {
        $row = manager::finish(required_param('token', PARAM_ALPHANUM), optional_param('title', '', PARAM_TEXT));
        echo json_encode(['videoid' => $row->videoid]);

    } else {
        throw new moodle_exception('invalidparameter', 'debug');
    }
} catch (\Throwable $e) {
    $out = ['error' => $e->getMessage()];
    // A chunk sent twice, or one missed: tell the browser where to carry on.
    if ($e instanceof moodle_exception && $e->errorcode === 'upload_err_offset') {
        $out['received'] = (int) $e->a;
    }
    echo json_encode($out);
}
