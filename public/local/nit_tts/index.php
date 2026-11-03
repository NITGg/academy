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
 * Admin page: type Arabic text, hear it spoken.
 *
 * The audio is never stored. It goes back to the browser inline in the page, so
 * nothing lingers on disk once the admin moves on.
 *
 * @package    local_nit_tts
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_nit_tts_page');

$PAGE->set_heading(get_string('pluginname', 'local_nit_tts'));

$form = new \local_nit_tts\form\tts_form();
$audio = null;
$error = null;

if ($data = $form->get_data()) {
    core_php_time_limit::raise(240);
    try {
        $audio = \local_nit_tts\gemini::synthesize(trim($data->text), $data->voice, trim($data->style ?? ''));
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
}

echo $OUTPUT->header();

if (\local_nit_tts\gemini::apikey() === '') {
    $settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_nit_tts']);
    echo $OUTPUT->notification(get_string('err_nokey_link', 'local_nit_tts', $settingsurl->out()), 'warning');
}

$form->display();

if ($error) {
    echo $OUTPUT->notification($error, 'error');
}

if ($audio) {
    $src = 'data:audio/wav;base64,' . base64_encode($audio);
    echo html_writer::start_div('local-nit-tts-result mt-3');
    echo html_writer::tag('audio', '', ['controls' => 'controls', 'autoplay' => 'autoplay', 'src' => $src, 'class' => 'w-100']);
    echo html_writer::link($src, get_string('download', 'local_nit_tts'),
        ['download' => 'tts-' . date('Ymd-His') . '.wav', 'class' => 'btn btn-secondary mt-2']);
    echo html_writer::end_div();
}

// Gemini can take a while on long text; stop a second click from sending it twice.
$PAGE->requires->js_amd_inline("
    require(['jquery'], function($) {
        $('form.mform').on('submit', function() {
            $(this).find('input[type=submit]').prop('disabled', true)
                .val(" . json_encode(get_string('converting', 'local_nit_tts')) . ");
        });
    });
");

echo $OUTPUT->footer();
