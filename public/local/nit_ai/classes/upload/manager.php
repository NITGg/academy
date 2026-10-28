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

namespace local_nit_ai\upload;

use local_nit_ai\source;

/**
 * A lesson video uploaded once, to this server, and handed on from here.
 *
 * When the assistant is to write the transcript itself, the video has two
 * places to go: the video provider, and our transcription service. Uploading
 * it to this server first means the teacher sends it once. It arrives in
 * chunks because Cloudflare refuses any single request over 100 MB.
 *
 *   uploading  chunks are arriving; received counts the bytes so far
 *   uploaded   complete; the provider has issued a video id, and the push to
 *              the provider is queued
 *   pushed     the provider has the file
 *   failed     the push gave up; error says why
 *
 * The file is deleted once the provider has it and the transcription service
 * has taken its copy (see release()); an upload whose form was never saved is
 * swept away by cleanup() after a day.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** @var string */
    public const TABLE = 'local_nit_ai_uploads';

    /** @var int Bytes per chunk: well under Cloudflare's 100 MB request cap and PHP's 100M upload limit. */
    public const CHUNK = 16 * 1024 * 1024;

    /** @var int Largest video accepted. */
    public const MAXSIZE = 4 * 1024 * 1024 * 1024;

    /** @var int How long an upload whose form was never saved is kept. */
    public const ORPHAN_AGE = DAYSECS;

    /**
     * Begin an upload.
     *
     * @param int $courseid
     * @param string $filename
     * @param int $filesize bytes
     * @return \stdClass the new row
     * @throws \moodle_exception
     */
    public static function start(int $courseid, string $filename, int $filesize): \stdClass {
        global $DB, $USER;

        if ($filesize <= 0 || $filesize > self::MAXSIZE) {
            throw new \moodle_exception('upload_err_size', 'local_nit_ai', '', display_size(self::MAXSIZE));
        }

        $now = time();
        $row = (object) [
            'token'        => random_string(32),
            'userid'       => (int) $USER->id,
            'courseid'     => $courseid,
            'cmid'         => 0,
            'filename'     => \core_text::substr(clean_filename($filename), 0, 255),
            'filesize'     => $filesize,
            'received'     => 0,
            'status'       => 'uploading',
            'videoid'      => '',
            'payload'      => null,
            'error'        => '',
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        $row->id = $DB->insert_record(self::TABLE, $row);

        touch(self::path($row));
        return $row;
    }

    /**
     * Add one chunk. Chunks must arrive in order: offset is where this one
     * starts, and it has to be exactly what has been received so far.
     *
     * @param string $token
     * @param int $offset
     * @param string $chunkpath the uploaded chunk's temporary file
     * @return \stdClass the row
     * @throws \moodle_exception
     */
    public static function append(string $token, int $offset, string $chunkpath): \stdClass {
        global $DB;

        $row = self::own($token);
        if ($row->status !== 'uploading') {
            throw new \moodle_exception('upload_err_state', 'local_nit_ai');
        }
        if ($offset !== (int) $row->received) {
            // The browser retried a chunk that had in fact arrived, or skipped
            // one. Either way it learns where to carry on from.
            throw new \moodle_exception('upload_err_offset', 'local_nit_ai', '', (int) $row->received);
        }

        $size = (int) filesize($chunkpath);
        if ($size <= 0 || $row->received + $size > $row->filesize) {
            throw new \moodle_exception('upload_err_size', 'local_nit_ai', '', display_size(self::MAXSIZE));
        }

        // Cut the file back to what was recorded first: a chunk whose write died
        // half way must not leave its first half in front of the retry.
        $in = fopen($chunkpath, 'rb');
        $out = fopen(self::path($row), 'c+b');
        $copied = false;
        if ($in && $out && ftruncate($out, (int) $row->received) && fseek($out, 0, SEEK_END) === 0) {
            $copied = stream_copy_to_stream($in, $out);
        }
        $in && fclose($in);
        $out && fclose($out);
        if ($copied !== $size) {
            throw new \moodle_exception('upload_err_write', 'local_nit_ai');
        }

        $row->received += $size;
        $row->timemodified = time();
        $DB->update_record(self::TABLE, $row);
        return $row;
    }

    /**
     * Close a complete upload: open the video on the provider, so the form can
     * be filled and saved while the bytes travel on from cron.
     *
     * A separate step from the last chunk so that it can be retried on its own
     * — a provider that is briefly unreachable must not cost a re-upload — and
     * safe to call twice: a closed upload just answers with its video id.
     *
     * @param string $token
     * @param string $title name for the video on the provider
     * @return \stdClass the row, with videoid set
     * @throws \moodle_exception
     */
    public static function finish(string $token, string $title): \stdClass {
        global $DB;

        $row = self::own($token);
        if ($row->status === 'uploaded' || $row->status === 'pushed') {
            return $row;
        }
        if ($row->status !== 'uploading' || (int) $row->received !== (int) $row->filesize) {
            throw new \moodle_exception('upload_err_state', 'local_nit_ai');
        }

        $started = source::start_upload($title !== '' ? $title : $row->filename);
        $row->videoid = $started['videoid'];
        $row->payload = json_encode($started['payload']);
        $row->status = 'uploaded';
        $row->timemodified = time();
        $DB->update_record(self::TABLE, $row);

        $task = new \local_nit_ai\task\upload_push();
        $task->set_custom_data(['uploadid' => (int) $row->id]);
        \core\task\manager::queue_adhoc_task($task);
        return $row;
    }

    /**
     * Tie a finished upload to the activity whose form was just saved.
     *
     * Only the uploader can claim it, only for the video id the form actually
     * saved, and only once.
     *
     * @param string $token
     * @param int $cmid
     * @param string $videoid the video id the activity now points at
     * @return \stdClass|null the row, or null when it does not belong here
     */
    public static function claim(string $token, int $cmid, string $videoid): ?\stdClass {
        global $DB, $USER;

        $row = $DB->get_record(self::TABLE, ['token' => $token]);
        if (!$row || (int) $row->userid !== (int) $USER->id || $row->videoid === '' || $row->videoid !== $videoid
                || !in_array($row->status, ['uploaded', 'pushed'], true)
                || ((int) $row->cmid !== 0 && (int) $row->cmid !== $cmid)) {
            return null;
        }
        $row->cmid = $cmid;
        $row->timemodified = time();
        $DB->update_record(self::TABLE, $row);
        return $row;
    }

    /**
     * Send a finished upload to the provider. Run by the upload_push adhoc task.
     *
     * @param int $uploadid
     * @return void
     * @throws \moodle_exception so the task retries
     */
    public static function push(int $uploadid): void {
        global $DB;

        $row = $DB->get_record(self::TABLE, ['id' => $uploadid]);
        if (!$row || $row->status !== 'uploaded') {
            return;
        }

        try {
            source::push_upload((array) json_decode((string) $row->payload, true), self::path($row));
        } catch (\Throwable $e) {
            $row->error = \core_text::substr($e->getMessage(), 0, 1000);
            $row->timemodified = time();
            $DB->update_record(self::TABLE, $row);
            throw $e;
        }

        $row->status = 'pushed';
        $row->payload = null;
        $row->error = '';
        $row->timemodified = time();
        $DB->update_record(self::TABLE, $row);

        self::release((int) $row->id);
    }

    /**
     * Delete the file once nobody needs it: the provider has it, and no
     * transcription is still waiting to send it.
     *
     * @param int $uploadid
     * @return void
     */
    public static function release(int $uploadid): void {
        global $DB;

        $row = $DB->get_record(self::TABLE, ['id' => $uploadid]);
        if (!$row || $row->status !== 'pushed') {
            return;
        }
        if ($DB->record_exists(\local_nit_ai\stt\manager::TABLE, ['uploadid' => $uploadid, 'status' => 'pending'])) {
            return;
        }
        @unlink(self::path($row));
    }

    /**
     * Sweep away uploads nobody will use: a form that was never saved, a push
     * that gave up, and old rows whose file is long gone.
     *
     * @return void
     */
    public static function cleanup(): void {
        global $DB;

        $orphan = time() - self::ORPHAN_AGE;
        $old = time() - 7 * DAYSECS;
        $rows = $DB->get_records_select(self::TABLE,
            '(cmid = 0 AND timemodified < :orphan) OR (timemodified < :old)',
            ['orphan' => $orphan, 'old' => $old]);

        foreach ($rows as $row) {
            if ($row->status === 'uploaded' && (int) $row->cmid !== 0) {
                continue; // Still being pushed to the provider for a saved activity.
            }
            @unlink(self::path($row));
            $DB->delete_records(self::TABLE, ['id' => $row->id]);
        }
    }

    /**
     * Where an upload's bytes are kept. Inside moodledata, never under the web root.
     *
     * @param \stdClass $row
     * @return string
     */
    public static function path(\stdClass $row): string {
        global $CFG;
        $dir = make_writable_directory($CFG->dataroot . '/local_nit_ai/uploads');
        return $dir . '/' . clean_param($row->token, PARAM_ALPHANUM);
    }

    /**
     * The current user's upload with this token.
     *
     * @param string $token
     * @return \stdClass
     * @throws \moodle_exception
     */
    protected static function own(string $token): \stdClass {
        global $DB, $USER;

        $row = $DB->get_record(self::TABLE, ['token' => $token]);
        if (!$row || (int) $row->userid !== (int) $USER->id) {
            throw new \moodle_exception('upload_err_notfound', 'local_nit_ai');
        }
        return $row;
    }
}
