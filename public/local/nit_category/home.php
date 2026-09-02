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
 * JSON feed for the home-page blocks that had markup but no data.
 *
 * Follows the same protocol as the other NIT api.php files, because the blocks
 * that consume it are written against that shape already:
 *   ?function=<name> → {"status":"success","data":...} | {"status":"error","error":...}
 *
 * Three functions, matching the three home-page sections of Section 4.7 that
 * were shipped as static markup with `{{placeholder}}` card templates and no
 * script to fill them:
 *
 * - `get_categories`  — the category grid, with the live course count AC-4.7.6
 *                       asks for;
 * - `get_my_courses`  — the My courses block of AC-4.7.7 to AC-4.7.9, with the
 *                       progress and resume point AC-4.7.8 requires;
 * - `get_continue`    — the one course the hero's "Continue learning" call to
 *                       action points at (AC-4.7.3).
 *
 * Read-only throughout, so no sesskey and no POST. `get_categories` is public
 * because a guest has to see the grid; the other two describe the caller's own
 * enrolments and answer an empty list when nobody is logged in rather than
 * refusing, so that the block can render its guest state without a failed
 * request in the console.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/nit_category/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/completionlib.php');

$function = optional_param('function', '', PARAM_ALPHANUMEXT);

// A site with forcelogin on is not browsable by guests at all, so the public
// read has to respect that rather than becoming the one hole in it.
if (!empty($CFG->forcelogin)) {
    require_login(null, false);
}

$PAGE->set_context(context_system::instance());

// The home page is bilingual and is rendered from static HTML with {mlang} spans,
// so the block tells us which language it is being read in rather than relying on
// the session, which may not match when the page was cached.
$alang = optional_param('alang', '', PARAM_LANG);
if ($alang !== '') {
    \local_nit_core\helper\lang::for_request($alang);
}

header('Content-Type: application/json; charset=utf-8');

/**
 * Emit the JSON envelope and stop.
 *
 * @param array $payload
 * @return void
 */
function nit_home_respond(array $payload): void {
    echo json_encode($payload);
    exit;
}

try {
    switch ($function) {
        case 'get_categories':
            nit_home_respond([
                'status' => 'success',
                'data' => \local_nit_category\home::categories(
                    optional_param('limit', 12, PARAM_INT)
                ),
            ]);
            break;

        case 'get_my_courses':
            // `learner` carries what an empty list cannot: whether the caller is
            // somebody who could hold an enrolment at all. AC-4.7.7 offers the
            // "browse the catalogue" invitation to an authenticated learner who
            // has none, and to nobody else - and the page cannot tell the two
            // apart on its own, because Moodle's `notloggedin` body class is
            // absent for a guest, who is signed in but owns nothing.
            nit_home_respond([
                'status' => 'success',
                'learner' => isloggedin() && !isguestuser(),
                'data' => \local_nit_category\home::my_courses(
                    optional_param('limit', 12, PARAM_INT)
                ),
            ]);
            break;

        case 'get_continue':
            nit_home_respond([
                'status' => 'success',
                'data' => \local_nit_category\home::continue_learning(),
            ]);
            break;

        default:
            nit_home_respond(['status' => 'error', 'error' => 'unknown_function']);
    }
} catch (\Throwable $e) {
    // The home page must render even when a section cannot. The blocks treat a
    // non-success envelope as "stay away": a missing section is a far better
    // front page than a stack trace, a section stuck on "Loading...", or - for
    // the My courses block - an invitation to buy a first course shown to
    // somebody who already owns ten.
    nit_home_respond(['status' => 'error', 'error' => $e->getMessage()]);
}
