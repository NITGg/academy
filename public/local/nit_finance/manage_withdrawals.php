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
 * The platform's Financial Report — every figure the four subject pages show, in one place.
 *
 * This page used to be a platform wallet: five cards fed by \local_nit_flex\api\flex, plus a
 * teacher withdrawal queue. The Flex plugin is not installed on this site, so the cards were
 * reading zeros from a model that does not exist here and the queue had nothing behind it. It
 * is rebuilt on the one ledger the platform actually keeps — {local_payments_transactions} in,
 * {local_payments_refunds} out.
 *
 * Nothing on this page is special-cased: it is the same panel embedded in the Reports tab of
 * manage_courses, manage_subscriptions, manage_coupons and manage_offers, run with every scope
 * instead of one. That is what makes it the master: those four pages are this page, narrowed.
 *
 * The URL keeps its old name so existing bookmarks, the admin menu entry and any link already
 * in the wild still land somewhere useful.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_finance\output\report_panel;
use local_nit_finance\report\revenue;

admin_externalpage_setup('local_nit_finance_reports');
require_capability('local/nit_finance:manage', context_system::instance());

$PAGE->set_title(get_string('financialreports', 'local_nit_finance'));
$PAGE->set_heading(get_string('financialreports', 'local_nit_finance'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('financialreports', 'local_nit_finance'));

// Said once, above the strip, because it is about the page rather than about any one scope:
// the panel's own line underneath changes as the scope does.
echo html_writer::div(get_string('rep_masterintro', 'local_nit_finance'), 'alert alert-info');

// Every scope, as a strip across the top. The scope rides in the URL hash, so an admin who
// lives in the refunds view can bookmark it.
echo report_panel::render(revenue::SCOPE_ALL, ['scopes' => revenue::scopes()]);

echo $OUTPUT->footer();
