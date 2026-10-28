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
 * Read and correct a video's transcript.
 *
 * The review the assistant waits for is a review of the text, so the text is
 * here in full, line by line against its time in the video, and each line can
 * be corrected in place. Times are kept as they are: they come from the video,
 * and a teacher fixing a misheard word should not have to think about them.
 *
 * Saving writes a new transcript, which — like any new transcript — needs
 * approving again; "save and approve" does both at once.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_ai\api;
use local_nit_ai\helper;
use local_nit_ai\source;

$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('local/nit_ai:manage', $context);

$url = new moodle_url('/local/nit_ai/transcript.php', ['cmid' => $cmid]);
$viewurl = new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('editortitle', 'local_nit_ai') . ' | ' . format_string($cm->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('editortitle', 'local_nit_ai'), $url);

$record = api::get($cmid);
if (!$record) {
    redirect($viewurl, get_string('notranscript', 'local_nit_ai'), null, \core\output\notification::NOTIFY_WARNING);
}

$describe = source::describe($cm);
$stale = api::is_stale($record, $describe['ref']);
$cues = api::cues($record);

if (data_submitted() && confirm_sesskey()) {
    $approve = optional_param('saveapprove', '', PARAM_RAW) !== '';

    if ($record->hastimestamps) {
        // Only the text comes from the browser; each time is read back from
        // what is stored, by position, so a line cannot be moved by editing.
        $texts = optional_param_array('t', [], PARAM_TEXT);
        $edited = [];
        foreach ($cues as $i => $cue) {
            $text = trim((string) ($texts[$i] ?? $cue['t']));
            if ($text !== '') {
                $edited[] = ['s' => (int) $cue['s'], 'e' => (int) $cue['e'], 't' => $text];
            }
        }
        $content = helper::to_vtt($edited);
        $extension = 'vtt';
    } else {
        $content = trim(optional_param('plain', '', PARAM_TEXT));
        $extension = 'txt';
    }

    if ($content === '' || ($record->hastimestamps && $content === "WEBVTT\n\n")) {
        redirect($url, get_string('editor_empty', 'local_nit_ai'), null, \core\output\notification::NOTIFY_ERROR);
    }

    $stored = helper::stored_file($context);
    $basename = $stored ? pathinfo($stored->get_filename(), PATHINFO_FILENAME) : 'transcript';
    $filename = $basename . '.' . $extension;

    helper::store_file($context, $filename, $content);
    api::save(
        cmid: $cmid,
        courseid: (int) $course->id,
        component: 'mod_' . $cm->modname,
        sourceref: $describe['ref'],
        sourcelength: $describe['length'],
        enabled: (bool) $record->enabled,
        hasonscreen: (bool) $record->hasonscreen,
        content: $content,
        filename: $filename
    );

    // Never approve a transcript of a video that has since been replaced.
    if ($approve && !$stale) {
        api::approve($cmid);
        redirect($viewurl, get_string('editor_savedapproved', 'local_nit_ai'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($url, get_string('editor_saved', 'local_nit_ai'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$lines = [];
foreach ($cues as $i => $cue) {
    $lines[] = [
        'index' => $i,
        'time'  => helper::timecode((int) $cue['s']),
        'text'  => $cue['t'],
        'rows'  => max(1, (int) ceil(core_text::strlen($cue['t']) / 70)),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_nit_ai/transcript_editor', [
    'title'       => get_string('editortitle', 'local_nit_ai'),
    'name'        => format_string($cm->name),
    'intro'       => get_string('editorintro', 'local_nit_ai'),
    'status'      => get_string($record->approved ? 'approved' : 'notapproved', 'local_nit_ai'),
    'approved'    => (bool) $record->approved,
    'stale'       => $stale,
    'stalemsg'    => get_string('check_stale', 'local_nit_ai'),
    'hastimes'    => (bool) $record->hastimestamps,
    'lines'       => $lines,
    'plain'       => (string) $record->plaintext,
    'action'      => $url->out(false),
    'sesskey'     => sesskey(),
    'timelabel'   => get_string('editor_time', 'local_nit_ai'),
    'textlabel'   => get_string('editor_text', 'local_nit_ai'),
    'filterlabel' => get_string('editor_filter', 'local_nit_ai'),
    'savelabel'   => get_string('editor_save', 'local_nit_ai'),
    'approvelabel' => get_string('editor_saveapprove', 'local_nit_ai'),
    'backurl'     => $viewurl->out(false),
    'backlabel'   => get_string('editor_back', 'local_nit_ai'),
]);
echo $OUTPUT->footer();
