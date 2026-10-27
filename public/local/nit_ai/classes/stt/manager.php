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

namespace local_nit_ai\stt;

use local_nit_ai\api;
use local_nit_ai\source;

/**
 * Automatic transcripts: a teacher uploads the lesson video, our own Whisper
 * service writes the transcript, and it lands in the same place an uploaded
 * transcript file would — waiting for the same review and approval.
 *
 * The life of one request, one row per course module:
 *
 *   pending  the video is stored here and an adhoc task is sending it
 *   queued   the service has it and is working through the videos before it
 *   running  the service is transcribing it (progress 0–100)
 *   done     the transcript was saved; the video is gone from both sides
 *   failed   the service could not read it; error says why
 *
 * The video is a means, never kept: it is deleted here once the service has
 * it, and the service deletes its copy once the transcript is written.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** @var string One transcription request per course module. */
    public const TABLE = 'local_nit_ai_stt';

    /** @var string File area holding the video until the service has taken it. */
    public const FILEAREA = 'media';

    /**
     * The request for a course module, if there is one.
     *
     * @param int $cmid
     * @return \stdClass|null
     */
    public static function get(int $cmid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['cmid' => $cmid]) ?: null;
    }

    /**
     * Take a video from the activity form and start transcribing it.
     *
     * The form never shows a stored video back (it is deleted as soon as the
     * service has it), so its draft area is empty on every ordinary re-save.
     * An empty draft means "nothing new", not "remove the video".
     *
     * @param \context $context module context
     * @param int $cmid
     * @param int $courseid
     * @param int $draftitemid draft area from the form's filemanager
     * @param bool $enable switch the assistant on when the transcript arrives
     * @return bool true when a new video was queued
     */
    public static function queue_from_form(\context $context, int $cmid, int $courseid, int $draftitemid,
            bool $enable): bool {
        global $DB, $USER;

        if (!$draftitemid || !self::draft_has_file($draftitemid)) {
            return false;
        }

        // Whatever was in flight describes a video the teacher just replaced.
        self::forget($cmid);

        file_save_draft_area_files($draftitemid, $context->id, 'local_nit_ai', self::FILEAREA, 0,
            ['subdirs' => 0, 'maxfiles' => 1]);
        $file = self::stored_media($context);
        if (!$file) {
            return false;
        }

        $now = time();
        $DB->insert_record(self::TABLE, (object) [
            'cmid'         => $cmid,
            'courseid'     => $courseid,
            'userid'       => (int) $USER->id,
            'filename'     => \core_text::substr($file->get_filename(), 0, 255),
            'enable'       => (int) $enable,
            'jobid'        => '',
            'status'       => 'pending',
            'progress'     => 0,
            'position'     => 0,
            'error'        => '',
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        $task = new \local_nit_ai\task\stt_submit();
        $task->set_custom_data(['cmid' => $cmid]);
        \core\task\manager::queue_adhoc_task($task, true);

        return true;
    }

    /**
     * Send a stored video to the service. Run by the stt_submit adhoc task.
     *
     * @param int $cmid
     * @return void
     * @throws \moodle_exception when the service cannot take it yet, so the task retries
     */
    public static function submit(int $cmid): void {
        $row = self::get($cmid);
        if (!$row || $row->status !== 'pending') {
            return; // Replaced or removed since it was queued.
        }

        $context = \context_module::instance($cmid);
        $file = self::stored_media($context);
        if (!$file) {
            self::update($row, ['status' => 'failed', 'error' => get_string('stt_err_nomedia', 'local_nit_ai')]);
            return;
        }

        try {
            $job = (new client())->submit($file);
        } catch (\Throwable $e) {
            // Shown on the panel while the task keeps retrying, so a service
            // that is down reads as "down", not as a queue that never moves.
            self::update($row, ['error' => $e->getMessage()]);
            throw $e;
        }

        self::update($row, [
            'jobid'    => (string) $job['id'],
            'status'   => 'queued',
            'position' => (int) ($job['position'] ?? 0),
            'error'    => '',
        ]);
        get_file_storage()->delete_area_files($context->id, 'local_nit_ai', self::FILEAREA);
    }

    /**
     * Ask the service about every video it is holding for us. Run every minute
     * by the stt_poll scheduled task.
     *
     * @return void
     */
    public static function poll(): void {
        global $DB;

        $rows = $DB->get_records_select(self::TABLE, "status IN ('queued', 'running') AND jobid <> ''",
            null, 'timecreated ASC');
        if (!$rows) {
            return;
        }

        $client = new client();
        foreach ($rows as $row) {
            try {
                $job = $client->status($row->jobid);
            } catch (\Throwable $e) {
                mtrace('local_nit_ai: transcription service unreachable: ' . $e->getMessage());
                return; // Every other row would fail the same way; try again next minute.
            }

            try {
                self::apply_job($row, $job, $client);
            } catch (\Throwable $e) {
                mtrace('local_nit_ai: could not finish transcript for cmid ' . $row->cmid . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Bring one row up to date with what the service says.
     *
     * @param \stdClass $row
     * @param array|null $job null when the service no longer knows the job
     * @param client $client
     * @return void
     */
    protected static function apply_job(\stdClass $row, ?array $job, client $client): void {
        if ($job === null) {
            self::fail($row, get_string('stt_err_lost', 'local_nit_ai'));
            return;
        }

        switch ($job['status'] ?? '') {
            case 'queued':
                self::update($row, ['status' => 'queued', 'position' => (int) ($job['position'] ?? 0)]);
                break;

            case 'running':
                self::update($row, ['status' => 'running', 'progress' => (int) ($job['progress'] ?? 0)]);
                break;

            case 'failed':
                $error = (string) ($job['error'] ?? '');
                // ffmpeg could not open it: to a teacher that is "not a video
                // we can read", and ffmpeg's own words only belong in the log.
                if (str_starts_with($error, 'ffmpeg:')) {
                    mtrace('local_nit_ai: cmid ' . $row->cmid . ': ' . $error);
                    $error = get_string('stt_err_unreadable', 'local_nit_ai');
                }
                self::fail($row, $error);
                $client->delete($row->jobid);
                break;

            case 'done':
                self::store_transcript($row, $client->vtt($row->jobid));
                self::update($row, ['status' => 'done', 'progress' => 100, 'error' => '']);
                $client->delete($row->jobid);
                self::notify($row, true);
                break;
        }
    }

    /**
     * Save the transcript exactly as an uploaded file would be saved, so it
     * meets the same parser, the same checks and the same approval.
     *
     * @param \stdClass $row
     * @param string $vtt
     * @return void
     */
    protected static function store_transcript(\stdClass $row, string $vtt): void {
        global $DB;

        $cm = get_coursemodule_from_id('', (int) $row->cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance((int) $cm->id);
        $filename = pathinfo($row->filename, PATHINFO_FILENAME) . '.vtt';

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'local_nit_ai', api::FILEAREA);
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'local_nit_ai',
            'filearea'  => api::FILEAREA,
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => $filename,
        ], $vtt);

        $existing = api::get((int) $cm->id);
        $describe = source::describe($cm);

        api::save(
            cmid: (int) $cm->id,
            courseid: (int) $cm->course,
            component: 'mod_' . $cm->modname,
            sourceref: $describe['ref'],
            sourcelength: $describe['length'],
            enabled: $existing ? (bool) $existing->enabled : (bool) $row->enable,
            // Whisper hears the lesson; it does not read the screen.
            hasonscreen: false,
            content: $vtt,
            filename: $filename
        );

        // Saved from cron, so api::save() stamped the cron user. The teacher who
        // uploaded the video owns this transcript.
        $DB->set_field(api::TABLE, 'usermodified', (int) $row->userid, ['cmid' => (int) $cm->id]);
    }

    /**
     * Drop everything held for a course module: the row, a video not yet sent,
     * and the job on the service.
     *
     * @param int $cmid
     * @return void
     */
    public static function forget(int $cmid): void {
        global $DB;

        $row = self::get($cmid);
        if ($row && $row->jobid !== '' && in_array($row->status, ['queued', 'running'], true)) {
            (new client())->delete($row->jobid);
        }
        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);

        try {
            $context = \context_module::instance($cmid);
        } catch (\Throwable $e) {
            return; // Module context already gone.
        }
        get_file_storage()->delete_area_files($context->id, 'local_nit_ai', self::FILEAREA);
    }

    /**
     * The line the teacher's review panel shows, or null when there is nothing
     * in flight worth mentioning.
     *
     * @param int $cmid
     * @return array|null ['text' => string, 'failed' => bool]
     */
    public static function panel_status(int $cmid): ?array {
        $row = self::get($cmid);
        if (!$row || $row->status === 'done') {
            return null;
        }

        switch ($row->status) {
            case 'pending':
                $text = $row->error !== ''
                    ? get_string('stt_status_retrying', 'local_nit_ai', $row->error)
                    : get_string('stt_status_pending', 'local_nit_ai');
                break;
            case 'queued':
                $text = get_string('stt_status_queued', 'local_nit_ai', max(1, (int) $row->position));
                break;
            case 'running':
                $text = get_string('stt_status_running', 'local_nit_ai', (int) $row->progress);
                break;
            default:
                $text = get_string('stt_status_failed', 'local_nit_ai', $row->error);
        }

        return ['text' => $text, 'failed' => $row->status === 'failed'];
    }

    /**
     * Mark a request failed and tell whoever uploaded the video.
     *
     * @param \stdClass $row
     * @param string $error
     * @return void
     */
    protected static function fail(\stdClass $row, string $error): void {
        self::update($row, ['status' => 'failed', 'error' => \core_text::substr($error, 0, 1000)]);
        self::notify($row, false);
    }

    /**
     * Let the uploader know the transcript is ready to review, or why it is not.
     *
     * @param \stdClass $row
     * @param bool $success
     * @return void
     */
    protected static function notify(\stdClass $row, bool $success): void {
        $user = \core_user::get_user((int) $row->userid);
        $cm = get_coursemodule_from_id('', (int) $row->cmid, 0, false, IGNORE_MISSING);
        if (!$user || !$cm) {
            return;
        }

        $url = new \moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => (int) $cm->id]);
        // Written in the teacher's language, not the cron user's: a notification
        // is read later, somewhere no page filter will resolve {mlang} for it.
        $lang = $user->lang ?: current_language();
        $a = (object) ['name' => self::in_language($cm->name, $lang), 'error' => $row->error];
        $key = $success ? 'stt_msg_done' : 'stt_msg_failed';
        $strings = get_string_manager();

        $message = new \core\message\message();
        $message->component = 'local_nit_ai';
        $message->name = 'transcriptready';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->courseid = (int) $cm->course;
        $message->subject = $strings->get_string($key . '_subject', 'local_nit_ai', $a, $lang);
        $message->fullmessage = $strings->get_string($key . '_body', 'local_nit_ai', $a, $lang);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = $message->subject;
        $message->notification = 1;
        $message->contexturl = $url->out(false);
        $message->contexturlname = $a->name;

        try {
            message_send($message);
        } catch (\Throwable $e) {
            debugging('local_nit_ai: could not send transcript notification: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * A {mlang ar}…{mlang}{mlang en}…{mlang} name, read in one language: that
     * language's half, else the first half that says anything.
     *
     * @param string $raw
     * @param string $lang
     * @return string
     */
    protected static function in_language(string $raw, string $lang): string {
        if (!preg_match_all('/\{mlang\s+([^}]+)\}(.*?)\{mlang\}/is', $raw, $blocks, PREG_SET_ORDER)) {
            return trim(strip_tags($raw));
        }
        $first = '';
        foreach ($blocks as [, $langs, $text]) {
            if (trim($text) === '') {
                continue;
            }
            if (in_array($lang, array_map('trim', explode(',', strtolower($langs))), true)) {
                return trim(strip_tags($text));
            }
            $first = $first !== '' ? $first : $text;
        }
        return trim(strip_tags($first));
    }

    /**
     * Write some fields of a row.
     *
     * @param \stdClass $row updated in place
     * @param array $fields
     * @return void
     */
    protected static function update(\stdClass $row, array $fields): void {
        global $DB;

        foreach ($fields as $name => $value) {
            $row->$name = $value;
        }
        $row->timemodified = time();
        $DB->update_record(self::TABLE, $row);
    }

    /**
     * Does a draft area hold a file? Counted, never read: it is a video.
     *
     * @param int $draftitemid
     * @return bool
     */
    protected static function draft_has_file(int $draftitemid): bool {
        global $USER;

        $usercontext = \context_user::instance($USER->id);
        return !get_file_storage()->is_area_empty($usercontext->id, 'user', 'draft', $draftitemid);
    }

    /**
     * The video waiting to be sent, if there is one.
     *
     * @param \context $context
     * @return \stored_file|null
     */
    protected static function stored_media(\context $context): ?\stored_file {
        $files = get_file_storage()->get_area_files($context->id, 'local_nit_ai', self::FILEAREA, 0,
            'timemodified DESC', false);
        return $files ? reset($files) : null;
    }
}
