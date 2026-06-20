<?php
defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/testnew/lib.php');

class mod_testnew_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $DB, $OUTPUT;

        $mform =& $this->_form;

        $mform->addElement('text', 'name', get_string('testnewname', 'testnew'), array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $ynoptions = array(0 => get_string('no'),
                           1 => get_string('yes'));
        $mform->addElement('select', 'usecode', get_string('usecode', 'testnew'), $ynoptions);
        $mform->setDefault('usecode', 0);
        $mform->addHelpButton('usecode', 'usecode', 'testnew');
        $filemanager_options = array();
        $filemanager_options['accepted_types'] = '*.pdf';
        $filemanager_options['maxbytes'] = 0;
        $filemanager_options['maxfiles'] = 1;
        $filemanager_options['mainfile'] = true;

        $mform->addElement('filemanager', 'type', get_string('selectfiles'), null, $filemanager_options);
        $this->standard_coursemodule_elements();
        // $element = $mform->getElement('introeditor');
        // $attributes = $element->getAttributes();
        // $attributes['rows'] = 5;
        // $element->setAttributes($attributes);
 
        $this->add_action_buttons();
    }
    function validation($data, $files) {
        global $USER;

        $errors = parent::validation($data, $files);

        $usercontext = context_user::instance($USER->id);
        $fs = get_file_storage();
        if (!$files = $fs->get_area_files($usercontext->id, 'user', 'draft', $data['type'], 'sortorder, id', false)) {
            $errors['type'] = get_string('required');
            return $errors;
        }
        if (count($files) == 1) {
            // no need to select main file if only one picked
            return $errors;
        } else if(count($files) > 1) {
            $mainfile = false;
            foreach($files as $file) {
                if ($file->get_sortorder() == 1) {
                    $mainfile = true;
                    break;
                }
            }
            // set a default main file
            if (!$mainfile) {
                $file = reset($files);
                file_set_sortorder($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(),
                                   $file->get_filepath(), $file->get_filename(), 1);
            }
        }
        return $errors;
    }
}
