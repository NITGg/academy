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

namespace local_nit_ai;

/**
 * Small shared helpers.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Seconds as MM:SS, or H:MM:SS once past an hour.
     *
     * @param int $seconds
     * @return string
     */
    public static function timecode(int $seconds): string {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $secs)
            : sprintf('%d:%02d', $minutes, $secs);
    }

    /**
     * Write cues out as a WebVTT file.
     *
     * The inverse of transcript_parser for what we keep: whole-second start and
     * end times and plain text. Used when a teacher corrects a transcript, so the
     * stored file and the stored cues stay one and the same.
     *
     * @param array $cues list of ['s' => int, 'e' => int, 't' => string]
     * @return string
     */
    public static function to_vtt(array $cues): string {
        $out = "WEBVTT\n\n";
        foreach ($cues as $cue) {
            $start = max(0, (int) $cue['s']);
            $end = max($start, (int) $cue['e']);
            $out .= self::vtt_time($start) . ' --> ' . self::vtt_time($end) . "\n" . trim($cue['t']) . "\n\n";
        }
        return $out;
    }

    /**
     * Seconds as a WebVTT timestamp, HH:MM:SS.000.
     *
     * @param int $seconds
     * @return string
     */
    protected static function vtt_time(int $seconds): string {
        return sprintf('%02d:%02d:%02d.000', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    /**
     * Replace the stored transcript file with new content.
     *
     * @param \context $context module context
     * @param string $filename
     * @param string $content
     * @return void
     */
    public static function store_file(\context $context, string $filename, string $content): void {
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'local_nit_ai', api::FILEAREA);
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'local_nit_ai',
            'filearea'  => api::FILEAREA,
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => $filename,
        ], $content);
    }

    /**
     * Read the uploaded transcript out of a form's draft file area.
     *
     * @param int $draftitemid
     * @return array|null ['content' => string, 'filename' => string], or null when nothing was uploaded
     */
    public static function draft_file_content(int $draftitemid): ?array {
        global $USER;

        if (!$draftitemid) {
            return null;
        }

        $usercontext = \context_user::instance($USER->id);
        $files = get_file_storage()->get_area_files(
            $usercontext->id,
            'user',
            'draft',
            $draftitemid,
            'timemodified DESC',
            false
        );

        foreach ($files as $file) {
            return ['content' => $file->get_content(), 'filename' => $file->get_filename()];
        }
        return null;
    }

    /**
     * Move a submitted transcript into the module's file area and report whether
     * it is actually a different file from the one already there.
     *
     * The distinction matters: re-saving an activity form must not look like a
     * new transcript, because a new transcript withdraws the teacher's approval.
     *
     * @param \context $context module context
     * @param int $draftitemid draft area from the form's filemanager
     * @return array|null ['content' => string, 'filename' => string] when the
     *                    transcript changed, null when it did not
     */
    public static function save_draft_and_detect_change(\context $context, int $draftitemid): ?array {
        $before = self::stored_file($context);
        $beforehash = $before ? $before->get_contenthash() : '';

        file_save_draft_area_files(
            $draftitemid,
            $context->id,
            'local_nit_ai',
            api::FILEAREA,
            0,
            ['subdirs' => 0, 'maxfiles' => 1]
        );

        $after = self::stored_file($context);
        if (!$after || $after->get_contenthash() === $beforehash) {
            return null;
        }

        return ['content' => $after->get_content(), 'filename' => $after->get_filename()];
    }

    /**
     * The stored transcript file for a module, if one is kept.
     *
     * @param \context $context
     * @return \stored_file|null
     */
    public static function stored_file(\context $context): ?\stored_file {
        $files = get_file_storage()->get_area_files(
            $context->id,
            'local_nit_ai',
            api::FILEAREA,
            0,
            'timemodified DESC',
            false
        );
        foreach ($files as $file) {
            return $file;
        }
        return null;
    }
}
