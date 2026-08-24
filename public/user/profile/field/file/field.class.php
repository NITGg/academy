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
 * File profile field.
 *
 * @package    profilefield_file
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * A profile field holding one uploaded file.
 *
 * Where the file lives: the user's own context, component `profilefield_file`,
 * filearea `files`, itemid = the profile field's id. Context is per-user and
 * itemid is per-field, so the pair is unique without needing the user_info_data
 * row to exist first - which matters because that row is written after the upload.
 *
 * `user_info_data.data` holds the filename. Nothing depends on it to find the file,
 * but keeping it there means is_empty(), the profile field API and reports all
 * behave normally instead of seeing a permanently blank field.
 *
 * Storing files in the user context also means Moodle's own account deletion
 * removes them: deleting the context deletes the files, no cleanup hook needed.
 *
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_field_file extends profile_field_base {

    /** @var string The file area every file profile field stores into. */
    const FILEAREA = 'files';

    /**
     * Add the file manager to the edit profile form.
     *
     * @param MoodleQuickForm $mform
     */
    public function edit_field_add($mform) {
        $mform->addElement('filemanager', $this->inputname, format_string($this->field->name),
            null, $this->filemanager_options());
        // The submitted value is a draft area id, not the file itself.
        $mform->setType($this->inputname, PARAM_INT);
    }

    /**
     * No default. `defaultdata` is meaningless for a file, and the base class
     * would push a string into a filemanager element, which breaks it.
     *
     * @param MoodleQuickForm $mform
     */
    public function edit_field_set_default($mform) {
        return;
    }

    /**
     * No required rule here.
     *
     * The base class adds a *client-side* rule, which cannot see into a draft file
     * area and so would fire on a field the user has just filled in. The check is
     * done server-side in edit_validate_field() instead.
     *
     * @param MoodleQuickForm $mform
     */
    public function edit_field_set_required($mform) {
        return;
    }

    /**
     * Freeze the uploader when the field is locked.
     *
     * Unlike the base class this does not call setConstant(): the constant would
     * be the stored filename, which is not a draft area id.
     *
     * @param MoodleQuickForm $mform
     */
    public function edit_field_set_locked($mform) {
        if (!$mform->elementExists($this->inputname)) {
            return;
        }
        if ($this->is_locked() && !has_capability('moodle/user:update', context_system::instance())) {
            $mform->hardFreeze($this->inputname);
        }
    }

    /**
     * Give the form a draft area seeded with the file already on file.
     *
     * @param stdClass $user
     */
    public function edit_load_user_data($user) {
        $draftitemid = 0;
        $contextid = null;
        $itemid = null;

        if ($this->userid > 0 && ($context = context_user::instance($this->userid, IGNORE_MISSING))) {
            $contextid = $context->id;
            $itemid = $this->get_itemid();
        }

        file_prepare_draft_area($draftitemid, $contextid, 'profilefield_file', self::FILEAREA,
            $itemid, $this->filemanager_options());

        $user->{$this->inputname} = $draftitemid;
    }

    /**
     * Move the uploaded file out of the draft area and record its name.
     *
     * The whole method is overridden rather than using the preprocess hook,
     * because the base class would store the draft area id as the field value.
     *
     * @param stdClass $usernew data coming from the form
     */
    public function edit_save_data($usernew) {
        global $DB;

        if (!isset($usernew->{$this->inputname})) {
            // Field not present in the form - locked and invisible, so leave it be.
            return;
        }

        $userid = (int) ($usernew->id ?? 0);
        if ($userid <= 0) {
            // No account yet, so no user context to store into. A file field is
            // barred from the sign-up form for exactly this reason.
            return;
        }

        $draftitemid = (int) $usernew->{$this->inputname};
        if (!$draftitemid) {
            return;
        }

        $context = context_user::instance($userid);
        file_save_draft_area_files($draftitemid, $context->id, 'profilefield_file', self::FILEAREA,
            $this->get_itemid(), $this->filemanager_options());

        $file = $this->get_file($userid);

        $data = (object) [
            'userid'     => $userid,
            'fieldid'    => $this->field->id,
            'data'       => $file ? $file->get_filename() : '',
            'dataformat' => 0,
        ];

        if ($dataid = $DB->get_field('user_info_data', 'id',
                ['userid' => $userid, 'fieldid' => $this->field->id])) {
            $data->id = $dataid;
            $DB->update_record('user_info_data', $data);
        } else {
            $DB->insert_record('user_info_data', $data);
        }
    }

    /**
     * Validate the submitted draft area.
     *
     * The base class is not reused: its uniqueness check would compare draft area
     * ids, and a file field is never unique anyway (see profile_define_file).
     *
     * @param stdClass $usernew
     * @return array error messages keyed by element name
     */
    public function edit_validate_field($usernew) {
        $errors = [];

        if (!$this->is_required()) {
            return $errors;
        }

        $draftitemid = (int) ($usernew->{$this->inputname} ?? 0);
        $info = $draftitemid ? file_get_draft_area_info($draftitemid) : ['filecount' => 0];
        if (empty($info['filecount'])) {
            $errors[$this->inputname] = get_string('required');
        }

        return $errors;
    }

    /**
     * Render the stored file: a download link, or the image itself.
     *
     * @return string HTML
     */
    public function display_data() {
        $file = $this->get_file();
        if (!$file) {
            return '';
        }

        $url = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            'profilefield_file',
            self::FILEAREA,
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );

        if ($this->field->param3 === 'image' && $file->is_valid_image()) {
            return html_writer::empty_tag('img', [
                'src'   => $url->out(),
                'alt'   => format_string($this->field->name),
                'class' => 'img-fluid profilefield_file-image',
            ]);
        }

        return html_writer::link($url, s($file->get_filename()), ['target' => '_blank']);
    }

    /**
     * Keep the raw filename out of the user object - on its own it is not
     * meaningful data, and anything printing it would show a bare filename.
     *
     * @return bool
     */
    public function is_user_object_data() {
        return false;
    }

    /**
     * The submitted form value is a draft area id.
     *
     * @return array the param type and null property
     */
    public function get_field_properties() {
        return [PARAM_INT, NULL_NOT_ALLOWED];
    }

    /**
     * The file area itemid for this field: the profile field's own id.
     *
     * @return int
     */
    protected function get_itemid(): int {
        return (int) $this->field->id;
    }

    /**
     * The stored file for this field and user, if there is one.
     *
     * @param int|null $userid defaults to the field's user
     * @return stored_file|null
     */
    protected function get_file(?int $userid = null) {
        $userid = $userid ?? (int) $this->userid;
        if ($userid <= 0) {
            return null;
        }

        $context = context_user::instance($userid, IGNORE_MISSING);
        if (!$context) {
            return null;
        }

        $files = get_file_storage()->get_area_files($context->id, 'profilefield_file', self::FILEAREA,
            $this->get_itemid(), 'itemid, filepath, filename', false);

        return $files ? reset($files) : null;
    }

    /**
     * Options shared by the draft area and the file manager element.
     *
     * They must match on both sides, otherwise a file accepted by the uploader can
     * be silently dropped when it is saved.
     *
     * @return array
     */
    protected function filemanager_options(): array {
        global $CFG;

        $maxbytes = (int) $this->field->param2;

        return [
            'subdirs'        => 0,
            'maxfiles'       => 1,
            'maxbytes'       => $maxbytes > 0 ? $maxbytes : (int) $CFG->maxbytes,
            'accepted_types' => $this->accepted_types(),
        ];
    }

    /**
     * The accepted file types, in the form the file manager expects.
     *
     * @return string|array '*' for any type, otherwise a list of types/extensions
     */
    protected function accepted_types() {
        $types = trim((string) $this->field->param1);
        if ($types === '' || $types === '*') {
            return '*';
        }
        return array_values(array_filter(array_map('trim', explode(',', $types))));
    }
}
