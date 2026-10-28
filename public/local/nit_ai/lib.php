<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Callbacks that attach the assistant to core activities.
 *
 * mod_vdocipher is ours and calls local_nit_ai itself. The File, URL and H5P
 * activities are core, and core is never edited (see CLAUDE.md), so the
 * assistant joins their settings forms through Moodle's standard course-module
 * callbacks below, and their pages through an output hook (hook_callbacks).
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add the assistant's section to a File, URL or H5P activity's settings form.
 *
 * @param moodleform_mod $formwrapper
 * @param MoodleQuickForm $mform
 * @return void
 */
function local_nit_ai_coursemodule_standard_elements($formwrapper, $mform) {
    $modname = (string) ($formwrapper->get_current()->modulename ?? '');
    if (!in_array($modname, \local_nit_ai\source::GENERIC_MODULES, true)) {
        return;
    }

    $cm = $formwrapper->get_coursemodule() ?: null;
    \local_nit_ai\ui::add_form_elements($mform, $cm, $modname);

    // These forms are not ours to give a data_preprocessing(), so the stored
    // values go in as element defaults instead.
    $defaults = [];
    \local_nit_ai\ui::prepare_form_defaults($defaults, $cm ? context_module::instance($cm->id) : null,
        $cm ? (int) $cm->id : 0, $modname);
    foreach ($defaults as $name => $value) {
        $mform->setDefault($name, $value);
    }
}

/**
 * Store what the assistant's section collected, once the activity itself is saved.
 *
 * @param stdClass $data the submitted module info
 * @param stdClass $course
 * @return stdClass the module info, unchanged
 */
function local_nit_ai_coursemodule_edit_post_actions($data, $course) {
    $modname = (string) ($data->modulename ?? '');
    if (in_array($modname, \local_nit_ai\source::GENERIC_MODULES, true) && !empty($data->coursemodule)) {
        $cm = (object) ['id' => (int) $data->coursemodule, 'modname' => $modname, 'instance' => (int) ($data->instance ?? 0)];
        try {
            \local_nit_ai\api::save_from_module($cm, $data);
        } catch (\Throwable $e) {
            // The assistant is an add-on: never let it block saving the activity.
            debugging('local_nit_ai: could not save assistant settings: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
    return $data;
}
