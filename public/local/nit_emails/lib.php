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
 * Plugin callbacks for local_nit_emails.
 *
 * @package    local_nit_emails
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Put "Email preferences" on the learner's own profile page (AC-4.5.5).
 *
 * Own profile only. These are personal choices, and an administrator who needs to
 * change them for somebody does it from the back office rather than by walking
 * into their profile.
 *
 * The parent category is resolved against what is actually on the tree, because a
 * node pointing at a category that does not exist makes
 * `attach_nodes_to_categories()` throw and takes the whole profile page with it.
 *
 * @param \core_user\output\myprofile\tree $tree the profile page's node tree
 * @param stdClass $user the profile being viewed
 * @param bool $iscurrentuser whether that profile belongs to the viewer
 * @param stdClass|null $course the course context, when viewed from within one
 * @return bool
 */
function local_nit_emails_myprofile_navigation(
    \core_user\output\myprofile\tree $tree,
    $user,
    $iscurrentuser,
    $course
) {
    if (during_initial_install() || !$iscurrentuser || isguestuser($user)) {
        return true;
    }

    $categories = (array) $tree->categories;
    $parent = '';
    foreach (['privacyandpolicies', 'administration', 'miscellaneous', 'contact'] as $candidate) {
        if (isset($categories[$candidate])) {
            $parent = $candidate;
            break;
        }
    }

    if ($parent === '') {
        return true;
    }

    $tree->add_node(new \core_user\output\myprofile\node(
        $parent,
        'local_nit_emails_prefs',
        get_string('prefstitle', 'local_nit_emails'),
        null,
        new moodle_url('/local/nit_emails/preferences.php')
    ));

    return true;
}
