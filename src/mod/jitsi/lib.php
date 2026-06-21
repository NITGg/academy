<?php
defined('MOODLE_INTERNAL') || die();

function jitsi_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_OTHER;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return false;
        default:
            return null;
    }
}

function jitsi_add_instance($data, $mform = null) {
    global $DB;
    $data->timemodified = time();
    return $DB->insert_record('jitsi', $data);
}

function jitsi_update_instance($data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    return $DB->update_record('jitsi', $data);
}

function jitsi_delete_instance($id) {
    global $DB;
    if (!$DB->get_record('jitsi', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('jitsi', ['id' => $id]);
    return true;
}

function jitsi_get_coursemodule_info($coursemodule) {
    global $DB;
    $jitsi = $DB->get_record('jitsi', ['id' => $coursemodule->instance], 'id, name, intro, introformat');
    if (!$jitsi) {
        return null;
    }
    $info = new cached_cm_info();
    $info->name = $jitsi->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('jitsi', $jitsi, $coursemodule->id, false);
    }
    return $info;
}

/**
 * Dynamically hide Jitsi activity from students not enrolled in the linked session.
 */
function jitsi_cm_info_dynamic(cm_info $cm) {
    global $DB, $USER;

    if (has_capability('mod/jitsi:moderate', $cm->context)) {
        return;
    }

    $session = $DB->get_record('academy_live_sessions', ['jitsiid' => $cm->instance]);
    if (!$session) {
        return;
    }

    $allowed = $DB->record_exists('academy_session_students', [
        'sessionid' => $session->id,
        'userid'    => $USER->id,
    ]);

    if (!$allowed) {
        $cm->set_user_visible(false);
        $cm->set_available(false);
        return;
    }

    $now          = time();
    $visible_from = $session->start_time - 1800;

    if ($now < $visible_from) {
        $cm->set_available(false, false, '');
    }
}
