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
 * Plugin callbacks for local_nit_instructors.
 *
 * @package    local_nit_instructors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Put the Academic and Professional Background on a profile page.
 *
 * AC-4.5.9 is precise about who sees this: "shown only on accounts holding the
 * instructor role. It is absent from a learner's profile screen entirely, not
 * merely disabled." So the node is added or it is not - there is no greyed-out
 * version, and a learner's profile page has no trace of the group at all.
 *
 * What is shown depends on who is looking:
 *
 * - the instructor themselves get their editable draft, the status of their last
 *   change, and a link to the form;
 * - everybody else gets the approved version only, which is AC-4.5.14's promise
 *   that a change in review is invisible until it is approved.
 *
 * @param \core_user\output\myprofile\tree $tree the profile page's node tree
 * @param stdClass $user the profile being viewed
 * @param bool $iscurrentuser whether that profile belongs to the viewer
 * @param stdClass|null $course the course context, when viewed from within one
 * @return bool
 */
function local_nit_instructors_myprofile_navigation(
    \core_user\output\myprofile\tree $tree,
    $user,
    $iscurrentuser,
    $course
) {
    if (during_initial_install() || empty($user->id) || isguestuser($user)) {
        return true;
    }

    if (!\local_nit_instructors\profile::is_instructor((int) $user->id)) {
        return true;
    }

    // A node whose parent category is not on the tree throws and takes the whole
    // profile page down, so the parent is chosen from what is actually there.
    $categories = (array) $tree->categories;
    $parent = '';
    foreach (['coursedetails', 'miscellaneous', 'contact'] as $candidate) {
        if (isset($categories[$candidate])) {
            $parent = $candidate;
            break;
        }
    }

    if ($parent === '') {
        return true;
    }

    $version = $iscurrentuser
        ? \local_nit_instructors\profile::editable((int) $user->id)
        : \local_nit_instructors\profile::approved((int) $user->id);

    $content = \local_nit_instructors\output::group($version, (int) $user->id);

    if ($iscurrentuser) {
        // The banner and the background as it stands, and no "edit" link: the
        // self-service edit page has been withdrawn, so there is nowhere for one to
        // lead. An instructor still sees where their background stands; it is now
        // corrected for them rather than by them.
        $content = \local_nit_instructors\output::status_banner((int) $user->id)
            . $content;
    } else {
        $content .= html_writer::div(
            html_writer::link(
                new moodle_url('/local/nit_instructors/view.php', ['id' => $user->id]),
                get_string('viewpublic', 'local_nit_instructors')),
            'mt-2');
    }

    $tree->add_node(new \core_user\output\myprofile\node(
        $parent,
        'local_nit_instructors_background',
        get_string('background', 'local_nit_instructors'),
        null,
        null,
        $content
    ));

    return true;
}

/**
 * Remove an instructor's background when their account is deleted.
 *
 * A profile is a description of a person. Once the account has gone there is
 * nobody for it to describe, and leaving it behind would mean a table quietly
 * accumulating the qualifications of people who left.
 *
 * @param stdClass $user the account being deleted
 * @return void
 */
function local_nit_instructors_pre_user_delete($user) {
    if (during_initial_install() || empty($user->id)) {
        return;
    }

    try {
        \local_nit_instructors\profile::purge((int) $user->id);
    } catch (\Throwable $e) {
        // A background that will not delete must not stop the account deleting;
        // the account going is what the learner asked for.
        debugging('local_nit_instructors: could not purge background on user delete: '
            . $e->getMessage(), DEBUG_DEVELOPER);
    }
}
