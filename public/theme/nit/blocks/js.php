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
 * paste can only be changed by pasting it again, so a fix committed to the
 * repository never reaches the site. The paste therefore keeps the markup and
 * loads its behaviour from here, where a git pull is enough.
 *
 * Deliberately no Moodle bootstrap. This serves one of a fixed list of files
 * from its own directory to anyone who can see the home page, so there is
 * nothing for a session to decide — and loading Moodle would mean taking the
 * session lock on every page that shows the block.
 *
 * Revalidated rather than cached outright: the browser keeps the file and asks
 * whether it changed, which costs one 304 and makes a deploy live at once. A
 * hard cache would put us back in "the fix did not ship".
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// The whitelist is the security boundary: only these names resolve, so the
// name can never be read as a path.
$allowed = [
    'home_subscriptions',
];

$name = isset($_GET['name']) ? (string) $_GET['name'] : '';

if (!in_array($name, $allowed, true)) {
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: text/plain; charset=utf-8');
    die('Unknown block script.');
}

$file = __DIR__ . '/' . $name . '.js';

if (!is_readable($file)) {
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: text/plain; charset=utf-8');
    die('Block script missing.');
}

$etag = '"' . md5($name . filemtime($file) . filesize($file)) . '"';

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
