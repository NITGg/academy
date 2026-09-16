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

namespace local_nit_media\hook;

use local_nit_media\promo_video;

/**
 * The "Course promo video" field of the course settings form (course/edit.php).
 *
 * Core dispatches three hooks around course_edit_form — definition, validation
 * and submission — and this class answers all three, so the trailer is set
 * where the course picture is set: the two elements are inserted right under
 * "Course image", inside the Description section, rather than in a section of
 * their own. Every element is prefixed `nitmedia_` so nothing collides with a
 * course column, a custom field or another plugin. The rules live in
 * {@see promo_video}; this class only draws the elements and passes data through.
 *
 * @package    local_nit_media
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_form {

    /** @var string the file manager element. */
    const EL_FILE = 'nitmedia_promovideo_filemanager';

    /** @var string the link element. */
    const EL_URL = 'nitmedia_promovideo_url';

    /** @var string marker: the save came from a form that carried the field. */
    const EL_PRESENT = 'nitmedia_promo_present';

    /**
     * Add the two elements under "Course image".
     *
     * @param \core_course\hook\after_form_definition $hook
     * @return void
     */
    public static function after_form_definition(\core_course\hook\after_form_definition $hook): void {
        $form = $hook->formwrapper;
        $mform = $hook->mform;
        $course = $form->get_course();
        $courseid = (int) ($course->id ?? 0);

        // The same gate core puts on the picture and the summary: on an existing
        // course they are frozen for whoever lacks it, and a frozen file manager
        // is just a disabled box, so the field is simply left out.
        if ($courseid && !has_capability('moodle/course:changesummary', $form->get_context())) {
            return;
        }

        // Where the elements go: straight after "Course image" — i.e. before the
        // header that follows it. The picture element itself is optional (site
        // setting), so the anchor is the header, which is always there.
        $anchor = $mform->elementExists('courseformathdr') ? 'courseformathdr' : null;

        $draftitemid = file_get_submitted_draft_itemid(self::EL_FILE);
        $contextid = $courseid ? \context_course::instance($courseid)->id : null;
        file_prepare_draft_area($draftitemid, $contextid, 'local_nit_media', promo_video::FILEAREA, 0,
            promo_video::file_options());

        $file = $mform->createElement('filemanager', self::EL_FILE, get_string('promovideo', 'local_nit_media'),
            null, promo_video::file_options());
        $url = $mform->createElement('text', self::EL_URL, get_string('promovideo_url', 'local_nit_media'),
            ['size' => 60, 'placeholder' => 'https://www.youtube.com/watch?v=…']);
        $present = $mform->createElement('hidden', self::EL_PRESENT, 1);

        if ($anchor) {
            $mform->insertElementBefore($file, $anchor);
            $mform->insertElementBefore($url, $anchor);
            $mform->insertElementBefore($present, $anchor);
        } else {
            $mform->addElement($file);
            $mform->addElement($url);
            $mform->addElement($present);
        }
        $mform->addHelpButton(self::EL_FILE, 'promovideo', 'local_nit_media');
        $mform->addHelpButton(self::EL_URL, 'promovideo_url', 'local_nit_media');
        $mform->setType(self::EL_URL, PARAM_URL);
        $mform->setType(self::EL_PRESENT, PARAM_INT);
        $mform->setDefault(self::EL_FILE, $draftitemid);
        $mform->setDefault(self::EL_URL, $courseid ? promo_video::url($courseid) : '');
    }

    /**
     * Refuse a link the player could not load.
     *
     * @param \core_course\hook\after_form_validation $hook
     * @return void
     */
    public static function after_form_validation(\core_course\hook\after_form_validation $hook): void {
        $data = $hook->get_data();
        if (empty($data[self::EL_PRESENT])) {
            return;
        }
        $url = trim((string) ($data[self::EL_URL] ?? ''));
        if ($url !== '' && promo_video::embed($url) === null) {
            $hook->add_errors([self::EL_URL => get_string('promovideo_url_invalid', 'local_nit_media')]);
        }
    }

    /**
     * Store the file and the link once the course itself has been saved.
     *
     * The same hook fires for every save of a course — web services, the upload
     * tool, a course restore — and those carry no promo at all. The marker keeps
     * them from wiping the course's video.
     *
     * @param \core_course\hook\after_form_submission $hook
     * @return void
     */
    public static function after_form_submission(\core_course\hook\after_form_submission $hook): void {
        $data = $hook->get_data();
        if (empty($data->{self::EL_PRESENT}) || empty($data->id)) {
            return;
        }
        $context = \context_course::instance((int) $data->id);
        if (isset($data->{self::EL_FILE})) {
            file_save_draft_area_files((int) $data->{self::EL_FILE}, $context->id, 'local_nit_media',
                promo_video::FILEAREA, 0, promo_video::file_options());
        }
        promo_video::set_url((int) $data->id, (string) ($data->{self::EL_URL} ?? ''));
    }
}
