<?php
namespace local_academysessions\task;

defined('MOODLE_INTERNAL') || die();

class sync_recordings extends \core\task\scheduled_task {

    public function get_name() {
        return 'Sync session recordings (MinIO → Bunny Stream)';
    }

    public function execute() {
        global $DB, $CFG;

        $bunny = new \local_academysessions\bunny_client();
        if (!$bunny->is_configured()) {
            mtrace('Bunny Stream not configured — skipping sync.');
            return;
        }

        // ── Pipeline 1: Pick up Jitsi recordings from MinIO ──
        $this->sync_from_minio($bunny);

        // ── Pipeline 2: Check pending Bunny uploads for completion ──
        $this->check_pending_uploads($bunny);
    }

    private function sync_from_minio($bunny) {
        global $DB, $CFG;

        $minio = new \local_academysessions\minio_client();
        if (!$minio->is_configured()) {
            mtrace('MinIO not configured — skipping MinIO sync.');
            return;
        }

        $files = $minio->list_recordings();
        if (empty($files)) {
            mtrace('No recordings found in MinIO.');
            return;
        }

        mtrace('Found ' . count($files) . ' recording(s) in MinIO.');

        foreach ($files as $file) {
            $key = $file['key'];

            // Extract room name from the key: recordings/{room_dir}/{filename}.mp4
            // Room dir format: academy_{cmid}_{hash}
            $parts = explode('/', $key);
            if (count($parts) < 3) {
                continue;
            }
            $roomdir = $parts[1];

            // Check if already processed.
            $existing = $DB->get_record_sql(
                "SELECT id FROM {academy_session_recordings} WHERE title = :title",
                array('title' => $key)
            );
            if ($existing) {
                continue;
            }

            // Try to match room to a session via the googlemeet activity.
            // Room format: academy_{cmid}_{hash}
            $session = null;
            if (preg_match('/^academy_(\d+)_/', $roomdir, $m)) {
                $cmid = (int)$m[1];
                $cm = $DB->get_record('course_modules', array('id' => $cmid));
                if ($cm) {
                    $session = $DB->get_record_sql(
                        "SELECT * FROM {academy_live_sessions}
                         WHERE googlemeetid = :gmid AND status = 'ended'
                         ORDER BY start_time DESC LIMIT 1",
                        array('gmid' => $cm->instance)
                    );

                    if (!$session) {
                        $session = $DB->get_record_sql(
                            "SELECT * FROM {academy_live_sessions}
                             WHERE courseid = :cid AND status = 'ended'
                             ORDER BY start_time DESC LIMIT 1",
                            array('cid' => $cm->course)
                        );
                    }
                }
            }

            if (!$session) {
                // Try to find the most recent ended session without a recording.
                $session = $DB->get_record_sql(
                    "SELECT s.* FROM {academy_live_sessions} s
                     LEFT JOIN {academy_session_recordings} r ON s.id = r.sessionid
                     WHERE s.status = 'ended' AND r.id IS NULL
                     ORDER BY s.start_time DESC LIMIT 1"
                );
            }

            if (!$session) {
                mtrace("Recording $key: No matching session found — skipping.");
                continue;
            }

            mtrace("Recording $key → Session {$session->id} '{$session->title}'");

            try {
                // Download from MinIO to temp.
                $tmpdir = $CFG->tempdir . '/academysessions';
                if (!is_dir($tmpdir)) {
                    mkdir($tmpdir, 0777, true);
                }
                $tmpfile = $tmpdir . '/minio_' . $session->id . '_' . time() . '.mp4';

                mtrace("  Downloading from MinIO...");
                $size = $minio->download_file($key, $tmpfile);
                mtrace("  Downloaded: " . round($size / 1024 / 1024, 1) . " MB");

                // Create + upload to Bunny.
                $videotitle = $session->title . ' - ' . userdate($session->start_time, '%Y-%m-%d %H:%M');
                mtrace("  Creating video on Bunny Stream...");
                $bunnyvideo = $bunny->create_video($videotitle);

                mtrace("  Uploading to Bunny Stream...");
                $bunny->upload_video_from_file($bunnyvideo->guid, $tmpfile);

                // Clean up temp.
                @unlink($tmpfile);

                // Save recording record.
                $expiry_days = get_config('local_academysessions', 'recording_expiry_days') ?: 30;
                $rec = new \stdClass();
                $rec->sessionid = $session->id;
                $rec->bunny_video_id = $bunnyvideo->guid;
                $rec->bunny_video_url = $bunny->get_embed_url($bunnyvideo->guid, time() + 7200);
                $rec->title = $key;
                $rec->status = 'syncing';
                $rec->expires_at = time() + ($expiry_days * 86400);
                $rec->timecreated = time();
                $rec->timemodified = time();
                $DB->insert_record('academy_session_recordings', $rec);

                mtrace("  Uploaded to Bunny (video ID: {$bunnyvideo->guid}), awaiting transcoding.");

                // Optionally delete from MinIO after successful upload.
                $minio->delete_file($key);
                mtrace("  Deleted from MinIO.");

            } catch (\Exception $e) {
                @unlink($tmpfile);
                mtrace("  ERROR: " . $e->getMessage());
            }
        }
    }

    private function check_pending_uploads($bunny) {
        global $DB;

        $pending = $DB->get_records('academy_session_recordings', array('status' => 'syncing'));
        if (empty($pending)) {
            return;
        }

        mtrace('Checking ' . count($pending) . ' pending Bunny upload(s)...');

        foreach ($pending as $rec) {
            if (empty($rec->bunny_video_id)) {
                continue;
            }
            try {
                $video = $bunny->get_video($rec->bunny_video_id);
                $newstatus = $bunny->map_status($video->status);

                if ($newstatus === $rec->status) {
                    continue;
                }

                $rec->status = $newstatus;
                $rec->timemodified = time();

                if ($newstatus === 'ready') {
                    $rec->duration = isset($video->length) ? $video->length : 0;
                    $expiry_days = get_config('local_academysessions', 'recording_expiry_days') ?: 30;
                    $rec->expires_at = time() + ($expiry_days * 86400);
                    $rec->bunny_video_url = $bunny->get_embed_url($rec->bunny_video_id, time() + 7200);
                }

                $DB->update_record('academy_session_recordings', $rec);
                mtrace("Recording {$rec->id} status → {$newstatus}");

                if ($newstatus === 'ready') {
                    $session = $DB->get_record('academy_live_sessions', array('id' => $rec->sessionid));
                    if ($session) {
                        \local_academysessions\session_manager::create_recording_activity($session);
                        mtrace("Created recording activity for session {$session->id}");
                    }
                }
            } catch (\Exception $e) {
                mtrace("ERROR checking recording {$rec->id}: " . $e->getMessage());
            }
        }
    }
}
