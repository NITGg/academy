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
 * Library callbacks for local_nit_media.
 *
 * @package    local_nit_media
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serves the course promo video.
 *
 * Same door as the course picture (course/overviewfiles in lib/filelib.php):
 * the trailer is what a visitor watches BEFORE buying, so it is as public as the
 * course page itself — a login is asked for only when the site forces one, and
 * a hidden course keeps its clip for those who may see the course. The locked
 * course preview (local_payments\course_preview) already lets a previewer
 * through pluginfile.php for any file in a course context, so nothing more is
 * needed for a signed-out shopper.
 *
 * send_stored_file byteserves, so seeking in the player works.
 *
 * @param stdClass $course
 * @param stdClass|null $cm
 * @param context $context
 * @param string $filearea
 * @param array $args remaining URL path segments (itemid, filepath, filename)
 * @param bool $forcedownload
 * @param array $options
 * @return void never returns — either sends the file or a "not found"
 */
function local_nit_media_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG;

    if ($context->contextlevel != CONTEXT_COURSE || $filearea !== \local_nit_media\promo_video::FILEAREA) {
        send_file_not_found();
    }
    if (!empty($CFG->forcelogin)) {
        require_login();
    }
    if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context)) {
        send_file_not_found();
    }

    array_shift($args); // The itemid, always 0.
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = get_file_storage()->get_file($context->id, 'local_nit_media', $filearea, 0, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    \core\session\manager::write_close(); // Unlock the session while the video streams.
    send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
}
