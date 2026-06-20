<?php
namespace local_academysessions\task;

defined('MOODLE_INTERNAL') || die();

class cleanup_recordings extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('cleanup_task', 'local_academysessions');
    }

    public function execute() {
        global $DB;
        $now = time();

        $expired = $DB->get_records_select('academy_session_recordings',
            "status = 'ready' AND expires_at > 0 AND expires_at <= :now",
            array('now' => $now)
        );

        foreach ($expired as $recording) {
            if (!empty($recording->bunny_video_id)) {
                try {
                    $client = new \local_academysessions\bunny_client();
                    $client->delete_video($recording->bunny_video_id);
                } catch (\Exception $e) {
                    mtrace("Failed to delete Bunny video {$recording->bunny_video_id}: " . $e->getMessage());
                }
            }
            $recording->status = 'archived';
            $recording->timemodified = $now;
            $DB->update_record('academy_session_recordings', $recording);
            mtrace("Recording {$recording->id} archived (expired).");
        }
    }
}
