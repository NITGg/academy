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
 * Renders one email template exactly as it will be sent, filled with sample
 * data. Served as a bare HTML document so what the admin sees is the same
 * markup a mail client receives — no theme, no page chrome.
 *
 * @package    local_nit_emails
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_emails\mailer;
use local_nit_emails\templates;

require_login();
require_capability('local/nit_emails:manage', context_system::instance());

$event = required_param('event', PARAM_ALPHANUMEXT);
$lang = optional_param('lang', 'en', PARAM_ALPHA);

if (!templates::is_event($event)) {
    throw new moodle_exception('invalidparameter', 'debug');
}

$html = mailer::preview($event, $lang, $USER);

// Same content type as the mail body; nothing here is user-supplied except the
// admin's own template, which this capability is already trusted to author.
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
echo $html;
