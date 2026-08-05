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
 * Academy settings: lesson deadlines + earning percentages (US-AD-2-1).
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_core\api\config as core_config;
use local_nit_lessons\exception\lesson_exception;
use local_nit_lessons\service\settings_service;

require_login();
$context = context_system::instance();
require_capability('local/nit_lessons:managesettings', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/nit_lessons/manage_settings.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('settings', 'local_nit_lessons'));
$PAGE->set_heading(get_string('settings', 'local_nit_lessons'));

$service = new settings_service();
$financeconfig = core_config::for_plugin('local_nit_finance');

if (data_submitted() && confirm_sesskey()) {
    $teacherpercent = required_param('teacher_percent', PARAM_INT);
    $platformpercent = required_param('platform_percent', PARAM_INT);
    if ($teacherpercent + $platformpercent !== 100) {
        throw new lesson_exception('err_percenttotal');
    }
    $deadlines = [];
    foreach (array_keys(settings_service::DEFAULTS) as $key) {
        $deadlines[$key] = optional_param($key, settings_service::DEFAULTS[$key], PARAM_INT);
    }
    $service->update($deadlines);
    $financeconfig->set('teacher_percent', $teacherpercent);
    $financeconfig->set('platform_percent', $platformpercent);
    redirect($PAGE->url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$current = $service->get_all();
$teacherpercent = (int) $financeconfig->get_int('teacher_percent', 40);
$platformpercent = (int) $financeconfig->get_int('platform_percent', 60);

echo $OUTPUT->header();

$form = html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false),
    'style' => 'max-width:560px']);
$form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

$form .= $OUTPUT->heading(get_string('deadlines', 'local_nit_lessons'), 4);
foreach ($current as $key => $value) {
    $field = html_writer::label(get_string($key, 'local_nit_lessons'), 'set_' . $key) .
        html_writer::empty_tag('input', ['type' => 'number', 'min' => 0, 'id' => 'set_' . $key,
            'name' => $key, 'value' => $value, 'class' => 'form-control']);
    $form .= html_writer::div($field, 'form-group mb-2');
}

$form .= $OUTPUT->heading(get_string('financial', 'local_nit_lessons'), 4);
$form .= html_writer::div(
    html_writer::label(get_string('teacher_percent', 'local_nit_lessons'), 'set_teacher_percent') .
    html_writer::empty_tag('input', ['type' => 'number', 'min' => 0, 'max' => 100,
        'id' => 'set_teacher_percent', 'name' => 'teacher_percent', 'value' => $teacherpercent,
        'class' => 'form-control']), 'form-group mb-2');
$form .= html_writer::div(
    html_writer::label(get_string('platform_percent', 'local_nit_lessons'), 'set_platform_percent') .
    html_writer::empty_tag('input', ['type' => 'number', 'min' => 0, 'max' => 100,
        'id' => 'set_platform_percent', 'name' => 'platform_percent', 'value' => $platformpercent,
        'class' => 'form-control']), 'form-group mb-3');

$form .= html_writer::tag('button', get_string('savesettings', 'local_nit_lessons'),
    ['type' => 'submit', 'class' => 'btn btn-primary']);
$form .= html_writer::end_tag('form');

echo $form;
echo $OUTPUT->footer();
