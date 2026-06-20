<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_sessionrecording_mod_form extends moodleform_mod {

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('pluginname', 'sessionrecording'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        $mform->addElement('text', 'sessionid', 'Session ID', array('size' => '10'));
        $mform->setType('sessionid', PARAM_INT);

        $mform->addElement('text', 'recordingid', 'Recording ID', array('size' => '10'));
        $mform->setType('recordingid', PARAM_INT);

        $mform->addElement('text', 'bunny_video_url', 'Video URL', array('size' => '64'));
        $mform->setType('bunny_video_url', PARAM_URL);

        $mform->addElement('text', 'attendee_groupid', 'Attendee Group ID', array('size' => '10'));
        $mform->setType('attendee_groupid', PARAM_INT);

        $mform->addElement('date_time_selector', 'visible_until', 'Visible until');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
