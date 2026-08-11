<?php
/**
 * The edit form for a VdoCipher Video activity.
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_vdocipher_mod_form extends moodleform_mod {

    public function definition() {
        $mform = $this->_form;

        // ── General ──────────────────────────────────────────────────────────
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // ── Video source ─────────────────────────────────────────────────────
        $mform->addElement('header', 'videosource', get_string('videosource', 'vdocipher'));
        $mform->setExpanded('videosource', true);

        // Upload a file (server-side pushed to VdoCipher). Best for modest files;
        // very large videos are better uploaded on the VdoCipher dashboard/app.
        $mform->addElement('filepicker', 'videofile', get_string('videofile', 'vdocipher'),
            null, ['accepted_types' => ['video']]);
        $mform->addHelpButton('videofile', 'videofile', 'vdocipher');

        // …or paste an existing VdoCipher video id.
        $mform->addElement('text', 'videoid', get_string('videoid', 'vdocipher'), ['size' => 48]);
        $mform->setType('videoid', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('videoid', 'videoid', 'vdocipher');

        // ── Standard elements ────────────────────────────────────────────────
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Require at least one video source: an uploaded file or a pasted id.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $haspasted = !empty(trim($data['videoid'] ?? ''));
        $hasfile   = !empty($this->get_new_filename('videofile'));

        // On edit, an already-saved instance may keep its existing video without
        // re-entering anything.
        $editing = !empty($this->_instance);

        if (!$haspasted && !$hasfile && !$editing) {
            $errors['videoid'] = get_string('err_novideosource', 'vdocipher');
        }
        return $errors;
    }
}
