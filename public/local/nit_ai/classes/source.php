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
 * The only place that knows anything about video providers.
 *
 * The assistant needs four things from a player: which video this is, how long
 * it runs, where the viewer is, and the ability to jump. The last two are the
 * JS adapter's job (amd/src/player_*.js); the first two are answered here.
 *
 * Adding Vimeo or a plain uploaded file means one case here and one adapter
 * there — nothing else in the plugin refers to a provider by name.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source {

    /**
     * @var string[] Core activities the assistant attaches to from outside: their
     *               forms through lib.php callbacks, their pages through an output
     *               hook (see hook_callbacks). mod_vdocipher calls in by itself.
     */
    public const GENERIC_MODULES = ['resource', 'url', 'h5pactivity'];

    /** @var string[] What a link may end in for us to fetch the video ourselves. */
    protected const MEDIA_EXTENSIONS = ['mp4', 'm4v', 'webm', 'mov', 'mkv', 'ogv', 'mp3', 'm4a', 'wav', 'ogg', 'oga',
        'aac', 'flac'];

    /**
     * Identify the video behind a course module.
     *
     * ref is whatever changes when the video is replaced — the provider's id, the
     * file's content hash, the link — so a transcript of an older video shows up
     * as stale.
     *
     * @param object $cm cm_info or a course_modules record
     * @return array ['ref' => string, 'length' => int] — ref is '' when there is
     *               no recognised video, length is 0 when the provider has not
     *               reported a duration yet
     */
    public static function describe(object $cm): array {
        global $DB;

        switch ($cm->modname ?? '') {
            case 'vdocipher':
                $row = $DB->get_record('local_vdocipher_videos', ['cmid' => (int) $cm->id], 'videoid, length');
                if ($row) {
                    return ['ref' => (string) $row->videoid, 'length' => (int) $row->length];
                }
                // The mapping row is written on save; fall back to the instance.
                $instance = $DB->get_record('vdocipher', ['id' => self::instance($cm)], 'videoid');
                return ['ref' => $instance ? (string) $instance->videoid : '', 'length' => 0];

            case 'resource':
                $file = self::resource_file($cm);
                return ['ref' => $file ? $file->get_contenthash() : '', 'length' => 0];

            case 'url':
                $address = self::url_address($cm);
                return ['ref' => $address !== '' ? 'url:' . sha1($address) : '', 'length' => 0];

            case 'h5pactivity':
                $file = self::h5p_package($cm);
                return ['ref' => $file ? $file->get_contenthash() : '', 'length' => 0];
        }

        return ['ref' => '', 'length' => 0];
    }

    /**
     * Which JS adapter drives this module's player.
     *
     * The name is both the registry key in window.NITAI.players and the second
     * half of the file name, js/player_<name>.js.
     *
     * @param object $cm
     * @return string adapter name, or '' when we cannot talk to the player
     */
    public static function adapter(object $cm): string {
        switch ($cm->modname ?? '') {
            case 'vdocipher':
                return 'vdocipher';
            // Moodle plays both a video file and a YouTube link through its own
            // media player (Video.js), so one adapter drives both.
            case 'resource':
                $file = self::resource_file($cm);
                return ($file && self::is_media_mimetype($file->get_mimetype())) ? 'media' : '';
            case 'url':
                $address = self::url_address($cm);
                return (self::is_youtube($address) || self::is_direct_media($address)) ? 'media' : '';
            case 'h5pactivity':
                return 'h5p';
        }
        return '';
    }

    /**
     * Reasons this activity cannot show the assistant that only its own settings
     * explain — shown on the teacher's review panel beside the general checks.
     *
     * @param object $cm
     * @return string[]
     */
    public static function problems(object $cm): array {
        global $CFG, $DB;

        $problems = [];
        switch ($cm->modname ?? '') {
            case 'resource':
                require_once($CFG->dirroot . '/mod/resource/locallib.php');
                $file = self::resource_file($cm);
                if ($file && !self::is_media_mimetype($file->get_mimetype())) {
                    $problems[] = get_string('check_notmedia', 'local_nit_ai');
                }
                $resource = $DB->get_record('resource', ['id' => self::instance($cm)]);
                if ($resource && $file) {
                    $resource->mainfile = $file->get_filename();
                    if (resource_get_final_display_type($resource) != RESOURCELIB_DISPLAY_EMBED) {
                        $problems[] = get_string('check_display', 'local_nit_ai');
                    }
                }
                break;

            case 'url':
                require_once($CFG->dirroot . '/mod/url/locallib.php');
                $url = $DB->get_record('url', ['id' => self::instance($cm)]);
                if ($url && url_get_final_display_type($url) != RESOURCELIB_DISPLAY_EMBED) {
                    $problems[] = get_string('check_display', 'local_nit_ai');
                }
                break;
        }
        return $problems;
    }

    /**
     * What the transcription service can be handed straight from the activity,
     * without the teacher uploading anything.
     *
     * @param object $cm
     * @return array one of ['file' => \stored_file], ['url' => string],
     *               ['h5p' => \stored_file] (the package; see h5p_extract_media()),
     *               or ['error' => string] saying why not and what to do instead
     */
    public static function media(object $cm): array {
        switch ($cm->modname ?? '') {
            case 'resource':
                $file = self::resource_file($cm);
                if ($file && self::is_media_mimetype($file->get_mimetype())) {
                    return ['file' => $file];
                }
                return ['error' => get_string('stt_err_notmedia', 'local_nit_ai')];

            case 'url':
                $address = self::url_address($cm);
                if (self::is_direct_media($address)) {
                    return ['url' => $address];
                }
                return ['error' => get_string('stt_err_urlneedsupload', 'local_nit_ai')];

            case 'h5pactivity':
                $file = self::h5p_package($cm);
                return $file ? ['h5p' => $file] : ['error' => get_string('stt_err_h5pnovideo', 'local_nit_ai')];
        }
        return ['error' => get_string('stt_err_nomedia', 'local_nit_ai')];
    }

    /**
     * Take the video out of an H5P package (an interactive video, typically) into
     * a temporary file.
     *
     * The video is found through content/content.json rather than by looking for
     * big files, so it is the one the H5P content actually plays.
     *
     * @param \stored_file $package
     * @return array ['path' => temporary file, 'filename' => string]; the caller deletes the file
     * @throws \moodle_exception when the package plays its video from elsewhere, or has none
     */
    public static function h5p_extract_media(\stored_file $package): array {
        $zippath = $package->copy_content_to_temp('local_nit_ai');
        $zip = new \ZipArchive();
        try {
            if ($zip->open($zippath) !== true) {
                throw new \moodle_exception('stt_err_h5pnovideo', 'local_nit_ai');
            }
            $content = json_decode((string) $zip->getFromName('content/content.json'), true);
            $local = [];
            $remote = false;
            self::collect_media(is_array($content) ? $content : [], $local, $remote);

            if (!$local) {
                throw new \moodle_exception($remote ? 'stt_err_h5premote' : 'stt_err_h5pnovideo', 'local_nit_ai');
            }

            $entry = 'content/' . ltrim($local[0], '/');
            $in = $zip->getStream($entry);
            if (!$in) {
                throw new \moodle_exception('stt_err_h5pnovideo', 'local_nit_ai');
            }
            $filename = clean_filename(basename($local[0]));
            $path = make_request_directory() . '/' . $filename;
            $out = fopen($path, 'wb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
            return ['path' => $path, 'filename' => $filename];
        } finally {
            $zip->close();
            @unlink($zippath);
        }
    }

    /**
     * Find the video and audio files an H5P content.json refers to.
     *
     * H5P describes every file as {"path": …, "mime": …}; a path that is a full
     * address (YouTube, mostly) is played from elsewhere and is not in the package.
     *
     * @param array $node
     * @param string[] $local paths inside the package's content/ folder, by reference
     * @param bool $remote set when a media file lives elsewhere, by reference
     * @return void
     */
    protected static function collect_media(array $node, array &$local, bool &$remote): void {
        if (isset($node['path'], $node['mime']) && is_string($node['path']) && is_string($node['mime'])
                && (self::is_media_mimetype($node['mime']) || stripos($node['mime'], 'youtube') !== false)) {
            if (preg_match('#^https?://#i', $node['path'])) {
                $remote = true;
            } else {
                $local[] = $node['path'];
            }
        }
        foreach ($node as $child) {
            if (is_array($child)) {
                self::collect_media($child, $local, $remote);
            }
        }
    }

    /**
     * Is this link one we can fetch as a video file?
     *
     * @param string $address
     * @return bool
     */
    public static function is_direct_media(string $address): bool {
        $path = (string) parse_url($address, PHP_URL_PATH);
        $extension = \core_text::strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return preg_match('#^https?://#i', $address) && in_array($extension, self::MEDIA_EXTENSIONS, true);
    }

    /**
     * @param string $address
     * @return bool
     */
    public static function is_youtube(string $address): bool {
        return (bool) preg_match('#^https?://(www\.|m\.)?(youtube\.com|youtu\.be|youtube-nocookie\.com)/#i', $address);
    }

    /**
     * @param string $mimetype
     * @return bool
     */
    protected static function is_media_mimetype(string $mimetype): bool {
        return str_starts_with($mimetype, 'video/') || str_starts_with($mimetype, 'audio/');
    }

    /**
     * The file a File (mod_resource) activity shows: the first by sort order,
     * the same one its view page picks.
     *
     * @param object $cm
     * @return \stored_file|null
     */
    protected static function resource_file(object $cm): ?\stored_file {
        $files = get_file_storage()->get_area_files(\context_module::instance((int) $cm->id)->id,
            'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
        return $files ? reset($files) : null;
    }

    /**
     * @param object $cm
     * @return string the link a URL activity points at, '' if none
     */
    protected static function url_address(object $cm): string {
        global $DB;
        return trim((string) $DB->get_field('url', 'externalurl', ['id' => self::instance($cm)]));
    }

    /**
     * @param object $cm
     * @return \stored_file|null the .h5p package of an H5P activity
     */
    protected static function h5p_package(object $cm): ?\stored_file {
        $files = get_file_storage()->get_area_files(\context_module::instance((int) $cm->id)->id,
            'mod_h5pactivity', 'package', 0, 'id', false);
        return $files ? reset($files) : null;
    }

    /**
     * The activity instance id, whether or not the caller's cm object carries it.
     *
     * @param object $cm
     * @return int
     */
    protected static function instance(object $cm): int {
        global $DB;
        if (!empty($cm->instance)) {
            return (int) $cm->instance;
        }
        return (int) $DB->get_field('course_modules', 'instance', ['id' => (int) $cm->id]);
    }

    /**
     * Open a new video on the provider, ready to receive a file from this server.
     *
     * Two halves on purpose: the id comes back at once, so the teacher's form can
     * be filled and saved, while the bytes go later from cron (push_upload()).
     *
     * @param string $title shown in the provider's dashboard
     * @return array ['videoid' => string, 'payload' => array]
     * @throws \moodle_exception when the provider is not set up or refuses
     */
    public static function start_upload(string $title): array {
        if (!\local_vdocipher\api_client::is_configured()) {
            throw new \moodle_exception('upload_err_noprovider', 'local_nit_ai');
        }
        $creds = (new \local_vdocipher\api_client())->get_upload_credentials($title);
        $videoid = (string) ($creds['videoId'] ?? '');
        $payload = $creds['clientPayload'] ?? [];
        if ($videoid === '' || empty($payload['uploadLink'])) {
            throw new \moodle_exception('upload_err_provider', 'local_nit_ai', '', 'no upload credentials');
        }
        return ['videoid' => $videoid, 'payload' => $payload];
    }

    /**
     * Send the file to the video start_upload() opened.
     *
     * @param array $payload from start_upload()
     * @param string $path the assembled video on this server
     * @return void
     * @throws \moodle_exception
     */
    public static function push_upload(array $payload, string $path): void {
        $client = new \local_vdocipher\api_client();
        // An hour of lesson can be a gigabyte; give the transfer the time it needs.
        $client->push_file($payload, $path, 3 * HOURSECS);
    }
}
