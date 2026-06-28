<?php
/**
 * External function: mod_jitsi_get_session_info
 *
 * Returns everything a native mobile app needs to render a Jitsi session:
 *   - Jitsi server URL, room name, JWT token  (for native Jitsi SDK)
 *   - Whiteboard URL                          (for in-app WebView)
 *   - Recordings with HLS playback URLs       (for native video player)
 *
 * @package   mod_jitsi
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jitsi\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use context_module;

class get_session_info extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    public static function execute(int $cmid): array {
        global $DB, $USER, $CFG;

        // Validate and get context.
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        $cmid   = $params['cmid'];

        $modinfo = get_fast_modinfo(
            $DB->get_field('course_modules', 'course', ['id' => $cmid], MUST_EXIST)
        );
        $cm     = $modinfo->get_cm($cmid);
        $course = get_course($cm->course);
        $jitsi  = $DB->get_record('jitsi', ['id' => $cm->instance], '*', MUST_EXIST);

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/jitsi:view', $context);

        $is_teacher = has_capability('mod/jitsi:moderate', $context);
        $available  = $is_teacher || (bool)$cm->available;

        // ── Jitsi config ─────────────────────────────────────────────────────
        $jitsi_host = get_config('local_academysessions', 'jitsi_host') ?: 'localhost:8443';
        $server_url = (strpos($jitsi_host, 'http') === 0)
            ? rtrim($jitsi_host, '/')
            : 'https://' . $jitsi_host;

        $room = 'academy_jitsi_' . $cm->id . '_' . substr(md5($jitsi->id . $cm->id), 0, 8);
        $jwt  = \local_academysessions\jitsi_jwt::generate(
            $room, fullname($USER), $USER->email, $is_teacher
        );

        // ── Whiteboard URL ────────────────────────────────────────────────────
        $excalidraw_app = get_config('local_academysessions', 'excalidraw_app')
            ?: 'https://localhost/whiteboard';
        $wb_room        = 'academy_wb_jitsi_' . $cm->id;
        $whiteboard_url = rtrim($excalidraw_app, '/') . '/#room=' . rawurlencode($wb_room);

        // ── Recordings ────────────────────────────────────────────────────────
        $recordings     = [];
        $rec_rows       = $DB->get_records('academy_session_recordings',
            ['cmid' => $cm->id], 'id DESC');

        $demo_url = get_config('local_academysessions', 'bunny_demo_url')
            ?: 'http://host.docker.internal:3000';
        $demo_key = get_config('local_academysessions', 'bunny_demo_key')
            ?: 'academy-internal-secret-2024';

        foreach ($rec_rows as $rec) {
            $playback_url  = null;
            $thumbnail_url = null;
            $embed_url     = null;

            if ($rec->status === 'ready' && !empty($rec->bunny_video_id)) {
                // Call bunnyStream /playback endpoint for signed HLS + thumbnail.
                $ch = curl_init($demo_url . '/api/internal/videos/'
                    . urlencode($rec->bunny_video_id) . '/playback');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => ['X-Internal-Key: ' . $demo_key],
                    CURLOPT_TIMEOUT        => 8,
                ]);
                $body = curl_exec($ch);
                curl_close($ch);
                $data = json_decode($body, true);
                if ($data && ($data['status'] ?? '') === 'READY') {
                    $playback_url  = $data['playback_url']  ?? null;
                    $thumbnail_url = $data['thumbnail_url'] ?? null;
                    $embed_url     = $data['embed_url']     ?? null;
                }
            }

            $recordings[] = [
                'id'            => (int)$rec->id,
                'title'         => $rec->title ?? '',
                'status'        => $rec->status,
                'playback_url'  => $playback_url  ?? '',
                'thumbnail_url' => $thumbnail_url ?? '',
                'embed_url'     => $embed_url     ?? '',
                'timecreated'   => (int)($rec->timecreated ?? 0),
            ];
        }

        return [
            'cmid'          => $cm->id,
            'name'          => format_string($jitsi->name),
            'available'     => $available,
            'available_info'=> strip_tags($cm->availableinfo ?? ''),
            'is_teacher'    => $is_teacher,
            'server_url'    => $server_url,
            'room'          => $room,
            'jwt'           => $jwt,
            'subject'       => format_string($jitsi->name),
            'whiteboard_url'=> $whiteboard_url,
            'recordings'    => $recordings,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'           => new external_value(PARAM_INT,  'Course module ID'),
            'name'           => new external_value(PARAM_TEXT, 'Activity name'),
            'available'      => new external_value(PARAM_BOOL, 'Whether session is accessible'),
            'available_info' => new external_value(PARAM_TEXT, 'Restriction message if not available'),
            'is_teacher'     => new external_value(PARAM_BOOL, 'Whether current user is moderator'),
            'server_url'     => new external_value(PARAM_URL,  'Jitsi server URL for native SDK'),
            'room'           => new external_value(PARAM_TEXT, 'Jitsi room name'),
            'jwt'            => new external_value(PARAM_RAW,  'JWT token for native Jitsi SDK'),
            'subject'        => new external_value(PARAM_TEXT, 'Conference subject/title'),
            'whiteboard_url' => new external_value(PARAM_URL,  'Excalidraw whiteboard URL (open in WebView)'),
            'recordings'     => new external_multiple_structure(
                new external_single_structure([
                    'id'            => new external_value(PARAM_INT,  'Recording DB id'),
                    'title'         => new external_value(PARAM_TEXT, 'Recording title'),
                    'status'        => new external_value(PARAM_ALPHA,'Status: syncing|ready'),
                    'playback_url'  => new external_value(PARAM_URL,  'HLS .m3u8 for native player', VALUE_OPTIONAL),
                    'thumbnail_url' => new external_value(PARAM_URL,  'Thumbnail image URL', VALUE_OPTIONAL),
                    'embed_url'     => new external_value(PARAM_URL,  'Bunny iframe embed URL', VALUE_OPTIONAL),
                    'timecreated'   => new external_value(PARAM_INT,  'Unix timestamp'),
                ])
            ),
        ]);
    }
}
