<?php
defined('MOODLE_INTERNAL') || die();

function sessionrecording_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return false;
        default:
            return null;
    }
}

function sessionrecording_add_instance($data, $mform = null) {
    global $DB;
    $data->timemodified = time();
    return $DB->insert_record('sessionrecording', $data);
}

function sessionrecording_update_instance($data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    return $DB->update_record('sessionrecording', $data);
}

function sessionrecording_delete_instance($id) {
    global $DB;
    if (!$DB->get_record('sessionrecording', array('id' => $id))) {
        return false;
    }
    $DB->delete_records('sessionrecording', array('id' => $id));
    return true;
}
