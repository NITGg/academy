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
 * Playing time of a stored video, read out of the container itself.
 *
 * Moodle stores no duration for an uploaded video: the browser only learns it
 * after fetching the file's metadata, which is why the length shows up in the
 * player on mod/resource/view.php but nowhere on the course page. There is no
 * ffmpeg on the server either, so we read it the one way that needs nothing
 * installed - by parsing the container header.
 *
 * Both containers keep the total playing time in a small header near the start
 * (or the end) of the file, so a handful of seeks answers the question without
 * reading any video data:
 *
 *   - ISO base media (.mp4 / .m4v / .mov / .m4a): the `mvhd` box inside `moov`
 *     holds a timescale and a duration expressed in that scale.
 *   - Matroska (.webm / .mkv): the Info element inside Segment holds
 *     TimecodeScale (nanoseconds, default 1000000) and Duration (a float in
 *     TimecodeScale units).
 *
 * Anything else - Ogg, Flash video, a container we do not recognise - returns
 * null and the caller simply shows no duration. Nothing here throws at the
 * caller: a missing, truncated or malformed file is just "unknown".
 *
 * Results are cached twice over, because neither answer can change on its own:
 * the parse is keyed by content hash (same bytes, same length, forever) and the
 * per-module lookup is keyed by the course cache revision, so a warm page render
 * costs no file queries at all.
 *
 * @package    local_nit_media
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_media;

defined('MOODLE_INTERNAL') || die();

/**
 * Reads and caches the playing time of stored video files.
 */
class duration {

    /** @var int Cache marker for "we looked and could not tell" - distinct from "not looked at yet". */
    const UNKNOWN = -1;

    /** @var int Never trust a header claiming more than a day; that is a corrupt field, not a video. */
    const MAX_SECONDS = 86400;

    /**
     * Playing time of the video behind a course module, in whole seconds.
     *
     * @param \cm_info $cm the course module
     * @return int|null seconds, or null when the module is not a video or its length is unreadable
     */
    public static function for_cm(\cm_info $cm): ?int {
        $cache = \core_cache\cache::make('local_nit_media', 'duration');

        // The course cache revision is bumped whenever a module is edited, which
        // is the only way the file behind it can change - so it is a safe stamp.
        $key = 'cm_' . $cm->id . '_' . (int) $cm->get_course()->cacherev;

        $cached = $cache->get($key);
        if ($cached !== false) {
            return $cached == self::UNKNOWN ? null : (int) $cached;
        }

        $file = self::main_video_file($cm);
        $seconds = $file ? self::for_stored_file($file) : null;

        $cache->set($key, $seconds ?? self::UNKNOWN);
        return $seconds;
    }

    /**
     * Playing time of a stored file, in whole seconds.
     *
     * @param \stored_file $file
     * @return int|null seconds, or null when the length cannot be read
     */
    public static function for_stored_file(\stored_file $file): ?int {
        $cache = \core_cache\cache::make('local_nit_media', 'duration');
        $key = 'hash_' . $file->get_contenthash();

        $cached = $cache->get($key);
        if ($cached !== false) {
            return $cached == self::UNKNOWN ? null : (int) $cached;
        }

        $seconds = self::parse($file);

        $cache->set($key, $seconds ?? self::UNKNOWN);
        return $seconds;
    }

    /**
     * Render seconds the way a player does: m:ss, or h:mm:ss past the hour.
     *
     * @param int $seconds
     * @return string
     */
    public static function format(int $seconds): string {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $secs)
            : sprintf('%d:%02d', $minutes, $secs);
    }

    /**
     * The video file a course module plays, if it plays one.
     *
     * Only mod_resource is handled: that is the "File" activity, the one that
     * shows a player when its main file is a video. Other modules either hold no
     * single file (page, folder) or point elsewhere (url), and so have no one
     * duration to report.
     *
     * @param \cm_info $cm
     * @return \stored_file|null
     */
    protected static function main_video_file(\cm_info $cm): ?\stored_file {
        if ($cm->modname !== 'resource') {
            return null;
        }

        // The same ordering mod_resource itself uses to pick the file it serves
        // (see resource_view in mod/resource/lib.php): highest sortorder wins.
        $fs = get_file_storage();
        $files = $fs->get_area_files($cm->context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
        if (!$files) {
            return null;
        }

        $file = reset($files);
        if (strpos((string) $file->get_mimetype(), 'video/') !== 0) {
            return null;
        }

        return $file;
    }

    /**
     * Parse the container header of a stored file for its duration.
     *
     * @param \stored_file $file
     * @return int|null seconds, or null when unreadable or unsupported
     */
    protected static function parse(\stored_file $file): ?int {
        $fh = null;
        try {
            $fh = $file->get_content_file_handle();
            if (!is_resource($fh)) {
                return null;
            }
            $seconds = self::parse_handle($fh, (int) $file->get_filesize());
        } catch (\Throwable $e) {
            // A file the storage cannot open is simply one with no known length.
            $seconds = null;
        } finally {
            if (is_resource($fh)) {
                fclose($fh);
            }
        }

        if ($seconds === null || $seconds < 1 || $seconds > self::MAX_SECONDS) {
            return null;
        }
        return $seconds;
    }

    /**
     * Sniff the container from its first bytes and hand off to the right parser.
     *
     * Sniffing rather than trusting the extension: .mov and .mp4 are the same
     * container, and a mislabelled file would otherwise read as unsupported.
     *
     * @param resource $fh seekable handle
     * @param int $size file size in bytes
     * @return int|null
     */
    protected static function parse_handle($fh, int $size): ?int {
        if ($size < 16 || fseek($fh, 0) !== 0) {
            return null;
        }
        $magic = self::read($fh, 8);
        if (strlen($magic) < 8) {
            return null;
        }

        if (substr($magic, 0, 4) === "\x1A\x45\xDF\xA3") {
            return self::parse_matroska($fh, $size);
        }
        // ISO base media files declare their brand in a `ftyp` box at offset 4;
        // some QuickTime files lead with `moov`, `mdat`, `free`, `skip` or `wide`.
        if (in_array(substr($magic, 4, 4), ['ftyp', 'moov', 'mdat', 'free', 'skip', 'wide'], true)) {
            return self::parse_isobmff($fh, $size);
        }

        return null;
    }

    // =========================================================================
    // ISO base media file format (MP4 / M4V / MOV / M4A).
    // =========================================================================

    /**
     * Read moov > mvhd and divide its duration by its timescale.
     *
     * @param resource $fh
     * @param int $size
     * @return int|null
     */
    protected static function parse_isobmff($fh, int $size): ?int {
        $moov = self::find_box($fh, 0, $size, 'moov');
        if ($moov === null) {
            return null;
        }
        $mvhd = self::find_box($fh, $moov[0], $moov[1], 'mvhd');
        if ($mvhd === null) {
            return null;
        }

        if (fseek($fh, $mvhd[0]) !== 0) {
            return null;
        }
        $head = self::read($fh, 4);
        if (strlen($head) < 4) {
            return null;
        }
        $version = ord($head[0]);

        if ($version === 1) {
            // 8+8 creation/modification, 4 timescale, 8 duration.
            $body = self::read($fh, 28);
            if (strlen($body) < 28) {
                return null;
            }
            $timescale = unpack('N', substr($body, 16, 4))[1];
            $duration = unpack('J', substr($body, 20, 8))[1];
            // A negative value here is a 64-bit field with its top bit set: not a length.
            $unknown = ($duration < 0);
        } else {
            // 4+4 creation/modification, 4 timescale, 4 duration.
            $body = self::read($fh, 16);
            if (strlen($body) < 16) {
                return null;
            }
            $timescale = unpack('N', substr($body, 8, 4))[1];
            $duration = unpack('N', substr($body, 12, 4))[1];
            // 0xFFFFFFFF is the spec's "duration not known" marker, used by
            // fragmented MP4 where the real length lives in the fragments.
            $unknown = ($duration === 0xFFFFFFFF);
        }

        if ($timescale <= 0 || $duration <= 0 || $unknown) {
            return null;
        }

        return (int) floor($duration / $timescale);
    }

    /**
     * Walk the boxes between two offsets and return the payload range of one type.
     *
     * @param resource $fh
     * @param int $from first byte of the first box header
     * @param int $to one past the last byte available at this level
     * @param string $want four-character box type
     * @return array|null [payload start, payload end], or null when absent
     */
    protected static function find_box($fh, int $from, int $to, string $want): ?array {
        $pos = $from;

        while ($pos + 8 <= $to) {
            if (fseek($fh, $pos) !== 0) {
                return null;
            }
            $header = self::read($fh, 8);
            if (strlen($header) < 8) {
                return null;
            }
            $parsed = unpack('Nsize/a4type', $header);
            $size = $parsed['size'];
            $type = $parsed['type'];
            $headerlen = 8;

            if ($size === 1) {
                // 64-bit size: the real length follows the type.
                $ext = self::read($fh, 8);
                if (strlen($ext) < 8) {
                    return null;
                }
                $size = unpack('J', $ext)[1];
                $headerlen = 16;
            } else if ($size === 0) {
                // "To the end of the enclosing box" - only legal on the last one.
                $size = $to - $pos;
            }

            // A size that does not fit means the walk has lost sync; reading past
            // it would return garbage, so stop rather than answer wrongly.
            if ($size < $headerlen || $size > $to - $pos) {
                return null;
            }

            if ($type === $want) {
                return [$pos + $headerlen, $pos + $size];
            }
            $pos += $size;
        }

        return null;
    }

    // =========================================================================
    // Matroska / WebM.
    // =========================================================================

    /**
     * Read Segment > Info > {TimecodeScale, Duration}.
     *
     * @param resource $fh
     * @param int $size
     * @return int|null
     */
    protected static function parse_matroska($fh, int $size): ?int {
        $segment = self::find_ebml($fh, 0, $size, "\x18\x53\x80\x67");
        if ($segment === null) {
            return null;
        }
        $info = self::find_ebml($fh, $segment[0], $segment[1], "\x15\x49\xA9\x66");
        if ($info === null) {
            return null;
        }

        $timecodescale = 1000000;   // Spec default: one millisecond, expressed in nanoseconds.
        $duration = null;

        $pos = $info[0];
        while ($pos < $info[1]) {
            $element = self::read_ebml_element($fh, $pos, $info[1]);
            if ($element === null) {
                break;
            }
            [$id, $start, $end, $next] = $element;

            if ($id === "\x2A\xD7\xB1") {
                $timecodescale = self::read_ebml_uint($fh, $start, $end) ?? $timecodescale;
            } else if ($id === "\x44\x89") {
                $duration = self::read_ebml_float($fh, $start, $end);
            }
            $pos = $next;
        }

        if ($duration === null || $duration <= 0 || $timecodescale <= 0) {
            return null;
        }

        return (int) floor($duration * $timecodescale / 1000000000);
    }

    /**
     * Walk EBML siblings between two offsets and return the payload range of one ID.
     *
     * @param resource $fh
     * @param int $from
     * @param int $to
     * @param string $wantid raw ID bytes, width marker included
     * @return array|null [payload start, payload end], or null when absent
     */
    protected static function find_ebml($fh, int $from, int $to, string $wantid): ?array {
        $pos = $from;

        while ($pos < $to) {
            $element = self::read_ebml_element($fh, $pos, $to);
            if ($element === null) {
                return null;
            }
            [$id, $start, $end, $next] = $element;

            if ($id === $wantid) {
                return [$start, $end];
            }
            $pos = $next;
        }

        return null;
    }

    /**
     * Read one EBML element header at an offset.
     *
     * @param resource $fh
     * @param int $pos offset of the element ID
     * @param int $to one past the last byte available at this level
     * @return array|null [raw id, payload start, payload end, next sibling offset]
     */
    protected static function read_ebml_element($fh, int $pos, int $to): ?array {
        if ($pos + 2 > $to || fseek($fh, $pos) !== 0) {
            return null;
        }
        // The longest possible header is an 8-byte ID plus an 8-byte size.
        $header = self::read($fh, (int) min(16, $to - $pos));

        $idlen = self::vint_length($header, 0);
        if ($idlen === null || strlen($header) < $idlen + 1) {
            return null;
        }
        $id = substr($header, 0, $idlen);

        $size = self::read_vint_value($header, $idlen);
        if ($size === null) {
            return null;
        }
        [$sizelen, $length, $unknownlength] = $size;

        $start = $pos + $idlen + $sizelen;
        // An unknown-length element (legal on Segment and Cluster) runs to the end
        // of its parent as far as we are concerned.
        $end = $unknownlength ? $to : $start + $length;

        if ($start > $to || $end > $to || $end <= $pos) {
            return null;
        }

        return [$id, $start, $end, $end];
    }

    /**
     * Length in bytes of the variable-width integer starting at an offset.
     *
     * The first set bit marks the width: 1xxxxxxx is one byte, 01xxxxxx two, and
     * so on up to eight.
     *
     * @param string $buffer
     * @param int $offset
     * @return int|null
     */
    protected static function vint_length(string $buffer, int $offset): ?int {
        if (!isset($buffer[$offset])) {
            return null;
        }
        $first = ord($buffer[$offset]);
        for ($i = 0; $i < 8; $i++) {
            if ($first & (0x80 >> $i)) {
                return $i + 1;
            }
        }
        return null;
    }

    /**
     * Decode the variable-width integer at an offset.
     *
     * @param string $buffer
     * @param int $offset
     * @return array|null [byte length, value, whether it is the "unknown length" pattern]
     */
    protected static function read_vint_value(string $buffer, int $offset): ?array {
        $len = self::vint_length($buffer, $offset);
        if ($len === null || strlen($buffer) < $offset + $len) {
            return null;
        }

        // Drop the width marker from the first byte, then append the rest.
        $value = ord($buffer[$offset]) & (0xFF >> $len);
        $allones = ($value === (0xFF >> $len));
        for ($i = 1; $i < $len; $i++) {
            $byte = ord($buffer[$offset + $i]);
            $value = ($value << 8) | $byte;
            $allones = $allones && ($byte === 0xFF);
        }

        return [$len, $value, $allones];
    }

    /**
     * Read an EBML unsigned integer payload.
     *
     * @param resource $fh
     * @param int $start
     * @param int $end
     * @return int|null
     */
    protected static function read_ebml_uint($fh, int $start, int $end): ?int {
        $len = $end - $start;
        if ($len < 1 || $len > 8 || fseek($fh, $start) !== 0) {
            return null;
        }
        $bytes = self::read($fh, $len);
        if (strlen($bytes) < $len) {
            return null;
        }

        $value = 0;
        for ($i = 0; $i < $len; $i++) {
            $value = ($value << 8) | ord($bytes[$i]);
        }
        return $value;
    }

    /**
     * Read an EBML float payload - Duration is stored as one.
     *
     * @param resource $fh
     * @param int $start
     * @param int $end
     * @return float|null
     */
    protected static function read_ebml_float($fh, int $start, int $end): ?float {
        $len = $end - $start;
        if (($len !== 4 && $len !== 8) || fseek($fh, $start) !== 0) {
            return null;
        }
        $bytes = self::read($fh, $len);
        if (strlen($bytes) < $len) {
            return null;
        }

        // 'G' and 'E' are big-endian float and double - EBML is big-endian throughout.
        $value = unpack($len === 4 ? 'G' : 'E', $bytes)[1];
        return is_finite($value) ? (float) $value : null;
    }

    // =========================================================================
    // Helpers.
    // =========================================================================

    /**
     * Read exactly $len bytes, short reads permitting.
     *
     * fread() on a stream may return less than asked for without being at EOF,
     * so every call site would otherwise need this loop.
     *
     * @param resource $fh
     * @param int $len
     * @return string what was actually read - callers check the length
     */
    protected static function read($fh, int $len): string {
        $data = '';
        while ($len > 0) {
            $chunk = fread($fh, $len);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $data .= $chunk;
            $len -= strlen($chunk);
        }
        return $data;
    }
}
