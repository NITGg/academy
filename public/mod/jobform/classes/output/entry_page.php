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

namespace mod_jobform\output;

use local_jobform\field_types;
use mod_jobform\submission_manager;
use renderable;
use renderer_base;
use templatable;

/**
 * A Job Form laid out as one sheet: the applicant's form, their sent answers,
 * or — for a reviewer — someone else's submission with its earlier versions.
 *
 * The moodleform itself is untouched; this only wraps whatever HTML it is
 * given in the frame a paper application form has: a docket on top (which
 * form, for which course, who is filling it in, when, its status and version)
 * and the numbered sections below. theme_nit's _jobform.scss does the layout.
 *
 * @package    mod_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry_page implements renderable, templatable {

    /** @var string the form (or read-only answers) HTML */
    protected string $body;
    /** @var object the jobform record */
    protected object $jobform;
    /** @var object the course record */
    protected object $course;
    /** @var object[] the activity's fields */
    protected array $fields;
    /** @var object|false the applicant's submission row, if any */
    protected $submission;
    /** @var object the applicant */
    protected object $user;
    /** @var bool true when the body is the sent answers rather than the form */
    protected bool $readonly;
    /** @var array[] earlier sent versions: each {version, timesent, body} */
    protected array $versions;
    /** @var \moodle_url|null where the Update button on a read-only sheet leads */
    protected ?\moodle_url $editurl;

    /**
     * @param string $body the rendered moodleform, or the read-only answers
     * @param object $jobform
     * @param object $course
     * @param object[] $fields
     * @param object|false $submission the applicant's submission row, if any
     * @param object $user the applicant
     * @param bool $readonly the body is the sent answers, not the form
     * @param array[] $versions earlier sent versions to list under the sheet,
     *                          each {version: int, timesent: int, body: string}
     * @param \moodle_url|null $editurl for a read-only sheet the applicant may
     *                          resend: the URL that reopens the fields
     */
    public function __construct(string $body, object $jobform, object $course, array $fields,
            $submission, object $user, bool $readonly = false, array $versions = [],
            ?\moodle_url $editurl = null) {
        $this->body = $body;
        $this->jobform = $jobform;
        $this->course = $course;
        $this->fields = array_values($fields);
        $this->submission = $submission;
        $this->user = $user;
        $this->readonly = $readonly;
        $this->versions = $versions;
        $this->editurl = $editurl;
    }

    /**
     * Whether a field is one the applicant has to fill in (a fixed value is
     * supplied by the admin, so it never is).
     *
     * @param object $field
     * @return bool
     */
    protected static function counts_as_required(object $field): bool {
        return !empty($field->required) && $field->type !== field_types::TYPE_FIXED;
    }

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        // The status is the submission's, whatever the sheet is showing: a sent
        // form the applicant is allowed to edit again still reads "Sent".
        $status = 'new';
        $when = time();
        if ($this->submission && $this->submission->status === submission_manager::STATUS_SUBMITTED) {
            $status = 'sent';
            $when = (int) $this->submission->timemodified;
        } else if ($this->submission && $this->submission->status === submission_manager::STATUS_DRAFT) {
            $status = 'draft';
            $when = (int) $this->submission->timemodified;
        }
        $dateformat = get_string('strftimedate', 'langconfig');

        // Version N = the earlier sent versions kept, plus the live one.
        $version = $this->submission ? submission_manager::count_versions((int) $this->submission->id) + 1 : 1;

        $versions = [];
        foreach ($this->versions as $v) {
            $versions[] = [
                'number'   => (int) $v['version'],
                'timesent' => userdate((int) $v['timesent'], $dateformat),
                'body'     => $v['body'],
            ];
        }

        $required = count(array_filter($this->fields, [self::class, 'counts_as_required']));

        return [
            'title'       => format_string($this->jobform->name),
            'coursename'  => format_string($this->course->fullname),
            'applicant'   => [
                'fullname' => fullname($this->user),
                'email'    => $this->user->email,
                'picture'  => $output->user_picture($this->user, ['size' => 48, 'link' => false]),
            ],
            'date'        => userdate($when, $dateformat),
            'datelabel'   => get_string('date_' . $status, 'mod_jobform'),
            'status'      => $status,
            'statustext'  => get_string('status_' . $status, 'mod_jobform'),
            'version'     => $version,
            'showversion' => $version > 1,
            'readonly'    => $this->readonly,
            // A sent form the applicant may update: on the read-only sheet the
            // note says it can be updated (next to the Update button); with the
            // fields reopened it says an update is in progress.
            'resendnote'  => ($status === 'sent' && !$this->readonly)
                ? get_string('updatingnote', 'mod_jobform', userdate($when, $dateformat))
                : (($status === 'sent' && $this->editurl)
                    ? get_string('resendnote', 'mod_jobform', userdate($when, $dateformat))
                    : ''),
            'updating'    => $status === 'sent' && !$this->readonly,
            'editurl'     => $this->editurl ? $this->editurl->out(false) : '',
            'hasrequired' => $required > 0,
            'body'        => $this->body,
            // The read-only sheet ends on the signature line, dated as sent.
            'signature'   => $this->readonly && $status !== 'new'
                ? \mod_jobform\form\entry_form::signature_html(fullname($this->user), $when)
                : '',
            'versions'    => $versions,
            'hasversions' => count($versions) > 0,
        ];
    }
}
