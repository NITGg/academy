<?php
namespace local_academysessions\task;

defined('MOODLE_INTERNAL') || die();

class sync_recordings extends \core\task\scheduled_task {

    public function get_name() {
        return 'Sync session recordings (MinIO → Bunny Stream via Demo API)';
    }

    public function execute() {
        global $DB, $CFG;

        $bunny_api_url  = get_config('local_academysessions', 'bunny_demo_url')  ?: 'http://bunny-demo-api:4000';
        $bunny_api_key  = get_config('local_academysessions', 'bunny_demo_key')  ?: 'academy-internal-secret-2024';
        $minio_endpoint = get_config('local_academysessions', 'minio_endpoint')  ?: 'http://minio:9000';
        $minio_bucket   = get_config('local_academysessions', 'minio_bucket')    ?: 'academy-recordings';

        $minio = new \local_academysessions\minio_client();
        if (!$minio->is_configured()) {
            mtrace('MinIO not configured — skipping sync.');
            return;
        }

        $files = $minio->list_recordings();
        if (empty($files)) {
            mtrace('No recordings found in MinIO.');
            $this->check_pending_uploads($bunny_api_url, $bunny_api_key);
            return;
        }

        mtrace('Found ' . count($files) . ' recording(s) in MinIO.');

        foreach ($files as $file) {
            $key = $file['key'];

            $existing = $DB->get_record_sql(
                "SELECT id FROM {academy_session_recordings} WHERE title = :title",
                ['title' => $key]
            );
            if ($existing) {
                continue;
            }

            [$session, $cmid] = $this->find_session_for_key($key);
            if (!$session && !$cmid) {
                mtrace("Recording $key: no matching session or activity — skipping.");
                continue;
            }

            if ($session) {
                mtrace("Recording $key → Session {$session->id} '{$session->title}'");
            } else {
                mtrace("Recording $key → standalone cmid={$cmid} (no linked session)");
            }

            try {
                $minio_url   = $this->presign_minio_url($minio_endpoint, $minio_bucket, $key);
                $video_title = $session
                    ? $session->title . ' — ' . userdate($session->start_time, '%Y-%m-%d %H:%M')
                    : 'Recording — ' . basename($key, '.mp4');

                $ingest_resp  = $this->call_demo_api_post(
                    $bunny_api_url . '/api/internal/ingest',
                    $bunny_api_key,
                    ['title' => $video_title, 'minioUrl' => $minio_url]
                );

                if (empty($ingest_resp['bunnyVideoId'])) {
                    throw new \Exception('Demo API ingest returned no bunnyVideoId: ' . json_encode($ingest_resp));
                }

                $bunny_video_id = $ingest_resp['bunnyVideoId'];
                mtrace("  Ingested → Bunny video {$bunny_video_id} (status: {$ingest_resp['status']})");

                $expiry_days = get_config('local_academysessions', 'recording_expiry_days') ?: 30;
                $rec = new \stdClass();
                $rec->sessionid      = $session ? $session->id : null;
                $rec->cmid           = $cmid;
                $rec->bunny_video_id = $bunny_video_id;
                $rec->title          = $key;
                $rec->status         = 'syncing';
                $rec->expires_at     = time() + ($expiry_days * 86400);
                $rec->timecreated    = time();
                $rec->timemodified   = time();
                $DB->insert_record('academy_session_recordings', $rec);

                $minio->delete_file($key);
                mtrace("  Deleted from MinIO.");

            } catch (\Exception $e) {
                mtrace("  ERROR: " . $e->getMessage());
            }
        }

        $this->check_pending_uploads($bunny_api_url, $bunny_api_key);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    // Returns [$session_or_null, $cmid_or_null]
    private function find_session_for_key($key) {
        global $DB;
        // Key format: recordings/{jibri_uuid}/{room}_{date}.mp4
        // The room name (containing cmid) is in the filename, not the UUID directory.
        $filename   = basename($key, '.mp4');   // e.g. academy_jitsi_1966_858a7d21_2026-06-22...
        $found_cmid = null;

        if (preg_match('/^academy_jitsi_(\d+)_/', $filename, $m)
            || preg_match('/^academy_(\d+)_/', $filename, $m)) {
            $found_cmid = (int)$m[1];
            $cm         = $DB->get_record('course_modules', ['id' => $found_cmid]);
            if ($cm) {
                $session = $DB->get_record_sql(
                    "SELECT * FROM {academy_live_sessions}
                     WHERE jitsiid = :jid AND status = 'ended'
                     ORDER BY start_time DESC LIMIT 1",
                    ['jid' => $cm->instance]
                );
                if ($session) {
                    return [$session, $found_cmid];
                }
                // Course-level fallback
                $session = $DB->get_record_sql(
                    "SELECT * FROM {academy_live_sessions}
                     WHERE courseid = :cid AND status = 'ended'
                     ORDER BY start_time DESC LIMIT 1",
                    ['cid' => $cm->course]
                );
                if ($session) {
                    return [$session, $found_cmid];
                }
                // No session but we have the cmid — standalone room.
                return [null, $found_cmid];
            }
        }

        // Last resort: most recent ended session without a recording.
        $session = $DB->get_record_sql(
            "SELECT s.* FROM {academy_live_sessions} s
             LEFT JOIN {academy_session_recordings} r ON s.id = r.sessionid
             WHERE s.status = 'ended' AND r.id IS NULL
             ORDER BY s.start_time DESC LIMIT 1"
        );
        return [$session ?: null, $found_cmid];
    }

    private function presign_minio_url($endpoint, $bucket, $key) {
        $expires  = time() + 3600;
        $secret   = get_config('local_academysessions', 'minio_secret_key') ?: 'minioadmin123';
        $access   = get_config('local_academysessions', 'minio_access_key') ?: 'minioadmin';
        $resource = "/$bucket/$key";
        $tosign   = "GET\n\n\n$expires\n$resource";
        $sig      = urlencode(base64_encode(hash_hmac('sha1', $tosign, $secret, true)));

        // bunnyStreamDemo runs on the HOST (not in Docker), so the URL must use
        // localhost:9000 (MinIO's published port), not the Docker-internal "minio" hostname.
        $host_endpoint = get_config('local_academysessions', 'minio_host_endpoint') ?: 'http://localhost:9000';
        return "$host_endpoint/$bucket/$key?AWSAccessKeyId=$access&Expires=$expires&Signature=$sig";
    }

    private function call_demo_api_post($url, $key, $payload) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Internal-Key: ' . $key,
            ],
            CURLOPT_TIMEOUT        => 600,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code < 200 || $code >= 300) {
            throw new \Exception("Demo API POST $url failed (HTTP $code): $resp");
        }
        return json_decode($resp, true);
    }

    private function call_demo_api_get($url, $key) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['X-Internal-Key: ' . $key],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code < 200 || $code >= 300) {
            throw new \Exception("Demo API GET $url failed (HTTP $code): $resp");
        }
        return json_decode($resp, true);
    }

    private function check_pending_uploads($bunny_api_url, $bunny_api_key) {
        global $DB;

        $pending = $DB->get_records('academy_session_recordings', ['status' => 'syncing']);
        if (empty($pending)) {
            return;
        }

        mtrace('Checking ' . count($pending) . ' pending upload(s)...');

        foreach ($pending as $rec) {
            if (empty($rec->bunny_video_id)) {
                continue;
            }
            try {
                $embed_resp = $this->call_demo_api_get(
                    $bunny_api_url . '/api/internal/videos/' . $rec->bunny_video_id . '/embed',
                    $bunny_api_key
                );

                if (empty($embed_resp['status'])) {
                    continue;
                }

                $new_status = strtolower($embed_resp['status']) === 'ready' ? 'ready' : 'syncing';
                if ($new_status === $rec->status) {
                    continue;
                }

                $rec->status       = $new_status;
                $rec->timemodified = time();

                if ($new_status === 'ready' && !empty($embed_resp['embedUrl'])) {
                    $rec->bunny_video_url = $embed_resp['embedUrl'];
                    $expiry_days = get_config('local_academysessions', 'recording_expiry_days') ?: 30;
                    $rec->expires_at = time() + ($expiry_days * 86400);
                }

                $DB->update_record('academy_session_recordings', $rec);
                mtrace("Recording {$rec->id}: status → {$new_status}");

                if ($new_status === 'ready' && !empty($rec->sessionid)) {
                    $session = $DB->get_record('academy_live_sessions', ['id' => $rec->sessionid]);
                    if ($session) {
                        \local_academysessions\session_manager::create_recording_activity($session);
                        mtrace("  Created recording activity for session {$session->id}");
                    }
                }
            } catch (\Exception $e) {
                mtrace("  ERROR checking recording {$rec->id}: " . $e->getMessage());
            }
        }
    }
}
