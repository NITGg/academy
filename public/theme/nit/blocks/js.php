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
 * Serve the JavaScript for a home-page HTML block.
 *
 * The blocks in this directory are pasted into Moodle HTML blocks, which means
 * the copy that actually runs lives in the database. Anything inline in that
 * paste — markup, styles, and until now several hundred lines of JavaScript —
 * can only be changed by pasting it again. A fix committed to the repository
 * simply never reached the site, which is a bad way to find out.
 *
 * So the paste keeps the markup and loads its behaviour from here. The script
 * ships with the rest of the code and deploys with a git pull, and the block in
 * the database only has to be re-pasted when its markup changes.
 *
 * Revalidated rather than cached outright: the browser keeps the file and asks
 * whether it has changed, which costs one 304 and means a deploy is live at
 * once. A hard cache would put us straight back into "the fix did not ship".
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// A static asset with no user-specific content: no session, so this never
// blocks on the session lock while a page is loading.
define('NO_MOODLE_COOKIES', true);
define('ABORT_AFTER_CONFIG', true);

require(__DIR__ . '/../../../config.php');

// ABORT_AFTER_CONFIG loads lib/configonlylib.php and nothing else, so the
// ordinary optional_param() does not exist here — calling it is a fatal error,
// and the script this file exists to serve never arrives. min_optional_param()
// is the equivalent from that minimal library; SAFEDIR is its PARAM_ALPHANUMEXT
// (it strips everything outside [a-zA-Z0-9_-]).
$name = min_optional_param('name', '', 'SAFEDIR');

// SAFEDIR already rules out slashes and dots, so the name cannot climb out of
// this directory; the whitelist below is the real gate anyway.
$allowed = [
    'home_subscriptions',
    'home_my_course',
];

if (!in_array($name, $allowed, true)) {
    header('HTTP/1.1 404 Not Found');
    die('Unknown block script.');
}

$file = __DIR__ . '/' . $name . '.js';

if (!is_readable($file)) {
    header('HTTP/1.1 404 Not Found');
    die('Block script missing.');
}

$mtime = filemtime($file);
$etag = '"' . md5($name . $mtime . filesize($file)) . '"';

header('Content-Type: application/javascript; charset=utf-8');
header('ETag: ' . $etag);
header('Cache-Control: no-cache, must-revalidate');

// The browser already has this exact file.
if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    header('HTTP/1.1 304 Not Modified');
    exit;
}

header('Content-Length: ' . filesize($file));
readfile($file);
