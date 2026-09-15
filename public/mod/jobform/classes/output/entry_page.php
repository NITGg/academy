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
use local_jobform\mlang;
use mod_jobform\submission_manager;
use renderable;
use renderer_base;
use templatable;

/**
 * The applicant's page: the form (or the sent answers) laid out as one sheet.
 *
 * The moodleform itself is untouched — it still validates and saves exactly as
 * before. This only wraps its HTML in the frame a paper application form has:
 * a docket on top (which form, for which course, who is filling it in, when,
 * and its status), the numbered sections, and a side rail that tracks the
 * required fields as they are filled. The rail is progressive: without
 * JavaScript it is a plain list of the sections.
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
    /** @var array group records keyed by id, in order */
    protected array $groups;
    /** @var object|false the applicant's submission row, if any */
    protected $submission;
    /** @var object the applicant */
    protected object $user;
    /** @var bool true when the sheet shows sent answers rather than the form */
    protected bool $sent;

    /**
     * @param string $body the rendered moodleform, or the read-only answers
     * @param object $jobform
     * @param object $course
     * @param object[] $fields
     * @param array $groups
     * @param object|false $submission
     * @param object $user
     * @param bool $sent
     */
    public function __construct(string $body, object $jobform, object $course, array $fields,
            array $groups, $submission, object $user, bool $sent = false) {
        $this->body = $body;
        $this->jobform = $jobform;
        $this->course = $course;
        $this->fields = array_values($fields);
        $this->groups = $groups;
        $this->submission = $submission;
        $this->user = $user;
        $this->sent = $sent;
    }

    /**
     * The sections in the order the form shows them, mirroring
     * {@see \mod_jobform\form\entry_form::definition()} so the rail's anchors
     * match the fieldset ids the form emits (`id_jfgroup_N`).
     *
     * @return array[] each {index, number, name, anchor, required}
     */
    protected function sections(): array {
        $bygroup = [];
        foreach ($this->fields as $field) {
            $gid = (int) ($field->groupid ?? 0);
            if (!$gid || !isset($this->groups[$gid])) {
                $gid = 0;
            }
            $bygroup[$gid][] = $field;
        }
        $grouped = array_diff(array_keys($bygroup), [0]);
        if (!count($this->groups) || !count($grouped)) {
            return [];
        }

        $sections = [];
        $i = 0;
        foreach ($this->groups as $group) {
            if (empty($bygroup[$group->id])) {
                continue;
            }
            $sections[] = $this->section($i++, mlang::resolve($group->name), $bygroup[$group->id]);
        }
        if (!empty($bygroup[0])) {
            $sections[] = $this->section($i++, get_string('generalsection', 'mod_jobform'), $bygroup[0]);
        }
        return $sections;
    }

    /**
     * One rail entry.
     *
     * @param int $index zero-based
     * @param string $name resolved section title
     * @param object[] $fields the section's fields
     * @return array
     */
    protected function section(int $index, string $name, array $fields): array {
        return [
            'index'    => $index,
            'number'   => sprintf('%02d', $index + 1),
            'name'     => $name,
            'anchor'   => 'id_jfgroup_' . $index,
            'required' => count(array_filter($fields, [self::class, 'counts_as_required'])),
        ];
    }

    /**
     * Whether a field takes part in the "required fields filled" count.
     *
     * A fixed value is supplied by the admin, so it is never something the
     * applicant has to fill in.
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
        $status = 'new';
        $statustext = get_string('status_new', 'mod_jobform');
        $when = time();
        if ($this->sent) {
            $status = 'sent';
            $statustext = get_string('status_sent', 'mod_jobform');
            $when = (int) ($this->submission->timemodified ?? $when);
        } else if ($this->submission && $this->submission->status === submission_manager::STATUS_DRAFT) {
            $status = 'draft';
            $statustext = get_string('status_draft', 'mod_jobform');
            $when = (int) $this->submission->timemodified;
        }

        $sections = $this->sections();
        $required = count(array_filter($this->fields, [self::class, 'counts_as_required']));

        return [
            'title'        => format_string($this->jobform->name),
            'coursename'   => format_string($this->course->fullname),
            'applicant'    => [
                'fullname' => fullname($this->user),
                'email'    => $this->user->email,
                'picture'  => $output->user_picture($this->user, ['size' => 48, 'link' => false]),
            ],
            'date'         => userdate($when, get_string('strftimedate', 'langconfig')),
            'datelabel'    => $status === 'new'
                ? get_string('date_today', 'mod_jobform')
                : get_string('date_saved', 'mod_jobform'),
            'status'       => $status,
            'statustext'   => $statustext,
            'sent'         => $this->sent,
            'sections'     => $sections,
            'hassections'  => count($sections) > 0,
            'required'     => $required,
            'hasrequired'  => $required > 0,
            'body'         => $this->body,
        ];
    }
}
