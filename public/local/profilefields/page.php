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
 * One of the site's static pages (AC-4.21) - About, Contact, Terms, Privacy,
 * Refund or FAQ.
 *
 * One address per page, and it never changes: `?page=terms` is the Terms page
 * whichever tool_policy version is current, in whichever language the visitor is
 * reading, and whether the text is a policy document or typed on the tab. That is
 * the whole reason this file exists rather than the footer linking straight at
 * `/admin/tool/policy/view.php?policyid=4&versionid=7` - that URL names one
 * revision of one document in one language, so it rots on the next revision and
 * cannot be the Arabic page as well.
 *
 * Public by default, because these pages are what a visitor reads before deciding
 * to sign up. A site that forces login to browse gates them like everything else.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_profilefields\staticpages;

$slug = required_param('page', PARAM_ALPHA);

if (!staticpages::exists($slug)) {
    throw new moodle_exception('staticpageunknown', 'local_profilefields', '', s($slug));
}

if (!empty($CFG->forcelogin)) {
    require_login();
}

$context = context_system::instance();
$url = staticpages::url($slug);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('nit_fullwidth');

// An unpublished page still answers for whoever can publish it, so the text can be
// checked before it goes live. Everyone else gets a plain "no such page".
$canedit = has_capability('moodle/site:config', $context);
if (!staticpages::enabled($slug) && !$canedit) {
    throw new moodle_exception('staticpageunavailable', 'local_profilefields');
}

$view = staticpages::view($slug);

$PAGE->set_title(format_string($SITE->shortname) . ': ' . $view['title']);
$PAGE->set_heading($view['title']);
$PAGE->navbar->add($view['title'], $url);

$view['canedit'] = $canedit;
$view['editurl'] = (new moodle_url('/local/profilefields/manage.php',
    ['tab' => 'page' . $slug]))->out(false);
$view['hascontent'] = trim($view['content']) !== '';
$view['hascontact'] = !empty($view['contact']) || !empty($view['social']) || $view['mapembed'] !== '';
$view['hasfaq'] = !empty($view['faq']);
$view['published'] = staticpages::enabled($slug);
$view['iscontact'] = $view['kind'] === staticpages::KIND_CONTACT;
$view['isfaq'] = $view['kind'] === staticpages::KIND_FAQ;

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_profilefields/staticpage', $view);
echo $OUTPUT->footer();
