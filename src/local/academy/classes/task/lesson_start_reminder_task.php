<?php
namespace local_academy\task;

defined('MOODLE_INTERNAL') || die();

class lesson_start_reminder_task extends \core\task\adhoc_task {
    public function execute() {
        global $DB;
        $data = $this->get_custom_data();
        if (empty($data->lessonid) || empty($data->confirmed_time)) {
            return;
        }

        $lesson = $DB->get_record('academy_lessons', array('id' => $data->lessonid));
        if (!$lesson) {
            return;
        }

        // Only send if the lesson is still confirmed and the time matches what we scheduled for.
        if ($lesson->status !== \local_academy\lesson_manager::STATUS_CONFIRMED) {
            return;
        }

        if ((int)$lesson->confirmed_time !== (int)$data->confirmed_time) {
            return;
        }

        \local_academy\notification_manager::lesson_event(
            $lesson,
            'lesson_reminder',
            $lesson->studentid
        );
    }
}
