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

namespace local_nit_media;

use context_course;
use moodle_url;
use stdClass;

/**
 * The course promo video — the clip a visitor can play in the hero of the course page.
 *
 * Core has no such field: a course has a picture (course/overviewfiles) but no
 * trailer. This class owns the one that was added to the course settings form
 * (see {@see \local_nit_media\hook\course_form}) right under "Course image":
 *
 *   - an UPLOADED file, kept in the course context under
 *     local_nit_media / promovideo (itemid 0), streamed by
 *     {@see local_nit_media_pluginfile()} with byteserving so the player can seek;
 *   - or a LINK — a YouTube or Vimeo page URL, or a direct .mp4/.webm address —
 *     kept in local_nit_media_promo. The link is turned into the address the
 *     player needs here ({@see embed()}), so the theme never parses a URL.
 *
 * An uploaded file wins over a link when both are set: the file is the thing
 * the teacher put the most effort into, and it plays without a third party.
 *
 * The theme asks one question — {@see for_course()} — and gets back either null
 * (no promo, draw the hero as before) or a small object it can put straight into
 * the markup:
 *
 *   ->kind   'file' | 'youtube' | 'vimeo' | 'direct'
 *   ->src    the address the player loads: a pluginfile URL, an embed URL, or the
 *            direct link (string)
 *   ->player 'video' for a <video> element (file / direct), 'iframe' otherwise
 *   ->id     the YouTube / Vimeo video id ('' for a file or a direct link)
 *   ->source the link as typed ('' for an uploaded file)
 *
 * Autoplay is asked for on the embed address — the player is only ever created
 * after a click on the play button, which is a user gesture, so browsers honour
 * it — and the YouTube embed goes through the -nocookie host so watching a
 * trailer does not set a tracking cookie on the visitor.
 *
 * @package    local_nit_media
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class promo_video {

    /** @var string file area of the uploaded clip (course context, itemid 0). */
    public const FILEAREA = 'promovideo';

    /** @var string table holding the link form of the promo. */
    public const TABLE = 'local_nit_media_promo';

    /** @var string[] container types the file manager accepts — the ones browsers play natively. */
    public const ACCEPTED_TYPES = ['.mp4', '.m4v', '.webm', '.ogv'];

    /**
     * File manager options for the upload, shared by the form and the save.
     *
     * @return array
     */
    public static function file_options(): array {
        global $CFG;
        return [
            'maxfiles' => 1,
            'subdirs' => 0,
            'maxbytes' => $CFG->maxbytes,
            'accepted_types' => self::ACCEPTED_TYPES,
            'return_types' => FILE_INTERNAL,
        ];
    }

    /**
     * The promo of a course, ready for the player, or null when it has none.
     *
     * @param int $courseid
     * @return stdClass|null
     */
    public static function for_course(int $courseid): ?stdClass {
        if ($courseid <= 0 || $courseid == SITEID) {
            return null;
        }
        $context = context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return null;
        }

        $file = self::stored_file($context);
        if ($file) {
            return (object) [
                'kind' => 'file',
                'player' => 'video',
                'src' => moodle_url::make_pluginfile_url(
                    $context->id, 'local_nit_media', self::FILEAREA, 0,
                    $file->get_filepath(), $file->get_filename())->out(false),
                'id' => '',
                'source' => '',
            ];
        }

        $url = self::url($courseid);
        if ($url === '') {
            return null;
        }
        return self::embed($url);
    }

    /**
     * The promo of a course in the shape the mobile app reads.
     *
     * Appended to every course row of local_payments_get_courses_with_pricing (and so
     * to local_nit_category_search, which hands its rows to that function), next to
     * the "Course File Summary" custom fields and the course picture the app already
     * parses. Every key is always present so the app can bind without null checks;
     * a course with no promo has `promo_video_type` = '' and the rest empty.
     *
     *   promo_video_type    '' | 'file' | 'youtube' | 'vimeo' | 'direct'
     *   promo_video_player  '' | 'video' | 'iframe' — what to play promo_video_url in:
     *                       a native video player, or a WebView
     *   promo_video_url     what to play. For 'file' it is a webservice/pluginfile.php
     *                       address — append `?token=` exactly as for the course
     *                       picture (overviewfiles[].fileurl). For 'youtube' / 'vimeo'
     *                       it is the embed address, autoplay on, ready for a WebView.
     *                       For 'direct' it is the link as typed.
     *   promo_video_id      the YouTube / Vimeo video id, for a native SDK player
     *                       (e.g. youtube_player_flutter); '' otherwise
     *   promo_video_source  the link as the teacher typed it (the watch page); '' for
     *                       an uploaded file
     *
     * @param int $courseid
     * @return array
     */
    public static function for_app(int $courseid): array {
        $out = [
            'promo_video_type' => '',
            'promo_video_player' => '',
            'promo_video_url' => '',
            'promo_video_id' => '',
            'promo_video_source' => '',
        ];
        $promo = self::for_course($courseid);
        if (!$promo) {
            return $out;
        }
        $out['promo_video_type'] = $promo->kind;
        $out['promo_video_player'] = $promo->player;
        $out['promo_video_url'] = $promo->src;
        $out['promo_video_id'] = $promo->id;
        $out['promo_video_source'] = $promo->source;
        if ($promo->kind === 'file') {
            // The app fetches files through the token door, like the course picture.
            $context = context_course::instance($courseid);
            $file = self::stored_file($context);
            $out['promo_video_url'] = moodle_url::make_webservice_pluginfile_url(
                $context->id, 'local_nit_media', self::FILEAREA, 0,
                $file->get_filepath(), $file->get_filename())->out(false);
        }
        return $out;
    }

    /**
     * The uploaded clip, if any.
     *
     * @param context_course $context
     * @return \stored_file|null
     */
    public static function stored_file(context_course $context): ?\stored_file {
        $files = get_file_storage()->get_area_files(
            $context->id, 'local_nit_media', self::FILEAREA, 0, 'sortorder DESC, id DESC', false);
        return $files ? reset($files) : null;
    }

    /**
     * The saved link of a course, '' when none.
     *
     * @param int $courseid
     * @return string
     */
    public static function url(int $courseid): string {
        global $DB;
        return (string) $DB->get_field(self::TABLE, 'url', ['courseid' => $courseid]);
    }

    /**
     * Save (or clear) the link of a course.
     *
     * @param int $courseid
     * @param string $url '' removes the row
     * @return void
     */
    public static function set_url(int $courseid, string $url): void {
        global $DB;
        $url = trim($url);
        $existing = $DB->get_record(self::TABLE, ['courseid' => $courseid]);
        if ($url === '') {
            if ($existing) {
                $DB->delete_records(self::TABLE, ['id' => $existing->id]);
            }
            return;
        }
        if ($existing) {
            if ($existing->url !== $url) {
                $existing->url = $url;
                $existing->timemodified = time();
                $DB->update_record(self::TABLE, $existing);
            }
            return;
        }
        $DB->insert_record(self::TABLE, (object) [
            'courseid' => $courseid,
            'url' => $url,
            'timemodified' => time(),
        ]);
    }

    /**
     * Drop everything the course had: the row and the uploaded clip.
     *
     * @param int $courseid
     * @return void
     */
    public static function delete_for_course(int $courseid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['courseid' => $courseid]);
        // The file area goes with the course context itself when the course is
        // deleted; this is for a caller that only wants the promo gone.
        $context = context_course::instance($courseid, IGNORE_MISSING);
        if ($context) {
            get_file_storage()->delete_area_files($context->id, 'local_nit_media', self::FILEAREA);
        }
    }

    /**
     * Turn a link the teacher typed into what the player loads.
     *
     * Recognised:
     *   - youtube.com/watch?v=ID, youtu.be/ID, youtube.com/shorts/ID,
     *     youtube.com/embed/ID, youtube.com/live/ID
     *   - vimeo.com/ID, vimeo.com/channels/x/ID, player.vimeo.com/video/ID
     *   - a direct https address ending in .mp4 / .m4v / .webm / .ogv
     *
     * @param string $url
     * @return stdClass|null null when the link is none of the above
     */
    public static function embed(string $url): ?stdClass {
        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return null;
        }
        $parts = parse_url($url);
        if (empty($parts['host'])) {
            return null;
        }
        $host = strtolower(preg_replace('/^www\./', '', $parts['host']));
        $path = $parts['path'] ?? '/';

        // YouTube.
        if (in_array($host, ['youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'youtu.be'], true)) {
            $id = '';
            if ($host === 'youtu.be') {
                $id = trim($path, '/');
            } else if (preg_match('#^/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{6,})#', $path, $m)) {
                $id = $m[1];
            } else {
                parse_str($parts['query'] ?? '', $q);
                $id = (string) ($q['v'] ?? '');
            }
            $id = preg_replace('/[^A-Za-z0-9_-]/', '', explode('/', $id)[0] ?? '');
            if ($id === '') {
                return null;
            }
            return (object) [
                'kind' => 'youtube',
                'player' => 'iframe',
                'src' => 'https://www.youtube-nocookie.com/embed/' . $id
                    . '?autoplay=1&rel=0&modestbranding=1&playsinline=1',
                'id' => $id,
                'source' => $url,
            ];
        }

        // Vimeo.
        if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true)) {
            if (!preg_match('#/(\d{5,})(?:/|$)#', $path, $m)) {
                return null;
            }
            $src = 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1&dnt=1';
            // An unlisted Vimeo link carries its hash as a second path segment.
            if (preg_match('#/' . $m[1] . '/([a-f0-9]{6,})#', $path, $h)) {
                $src .= '&h=' . $h[1];
            }
            return (object) ['kind' => 'vimeo', 'player' => 'iframe', 'src' => $src, 'id' => $m[1], 'source' => $url];
        }

        // A file address on any host.
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array('.' . $ext, self::ACCEPTED_TYPES, true)) {
            return (object) ['kind' => 'direct', 'player' => 'video', 'src' => $url, 'id' => '', 'source' => $url];
        }

        return null;
    }
}
