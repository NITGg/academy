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
 * English strings for local_msgrules.
 *
 * @package    local_msgrules
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Student messaging restrictions';
$string['settings'] = 'Settings';
$string['managecourses'] = 'Restrictions per course';

// The ticks. "No restriction" is the master switch; the other three combine freely, and none
// of them ticked means the students on that course may message nobody at all.
$string['modeopen'] = 'No restriction';
$string['modenobody'] = 'Nobody - students cannot message anyone';
$string['modeallowlist'] = 'Only: {$a}';
$string['allowteachers'] = 'Teachers';
$string['allowadmins'] = 'Site administrators';
$string['allowpeers'] = 'Fellow students';
$string['usedefault'] = 'Use the setting for all courses';
$string['followsdefault'] = 'Currently: {$a}';
$string['allcourses'] = 'All courses';
$string['allcourses_help'] = 'Used by every course that has not been given its own setting below.';

// Settings.
$string['enabled'] = 'Enforce these restrictions';
$string['enabled_desc'] = 'When on, students are held to the mode set for each of their courses. '
    . 'Turning it off restores every conversation the plugin had closed, leaving any block a user made themselves in '
    . 'place. Choose the modes first, then switch this on.';
$string['maxusers'] = 'Maximum accounts';
$string['maxusers_desc'] = 'A restricted student needs one row per person on the site they may not write to, so the '
    . 'work grows with the size of the site. Above this figure a rebuild refuses to run rather than spending hours in '
    . 'cron. Raise it deliberately.';

// Management screen.
$string['coursesintro'] = 'Each course decides what its own students may do. Teachers are never restricted - they can '
    . 'always write to their students - and a student on several courses gets whatever any one of those courses allows.';
$string['ticksintro'] = 'Tick "No restriction" to leave a course alone. Otherwise tick every group its students are '
    . 'still allowed to message - you can combine them, so ticking Teachers and Site administrators lets a student '
    . 'reach both and nobody else. Ticking none of the three means they may message nobody at all.';
$string['course'] = 'Course';
$string['restriction'] = 'Students on this course may message';
$string['searchcourses'] = 'Search courses';
$string['nocoursesfound'] = 'No courses matched.';
$string['rebuildnow'] = 'Reapply now';
$string['rebuildqueued'] = 'Queued - it will apply on the next cron run. This site is too large to rebuild while you '
    . 'wait.';
$string['rebuildapplied'] = 'Live now: {$a->students} restricted students, {$a->added} conversations closed, '
    . '{$a->removed} reopened. Log in as a test student to check it.';
$string['currentstate'] = 'Current state';
$string['managedblocks'] = 'Conversations currently closed by these restrictions: {$a}';
$string['disabledwarning'] = 'These restrictions are not being enforced. Switch on "Enforce these restrictions" in the '
    . 'settings once the courses below say what you want.';
$string['messagingoffwarning'] = 'The site messaging system is switched off, so nothing can be sent anyway. These '
    . 'restrictions will start to matter once messaging is enabled under Site administration > Advanced features.';

// Bypass diagnostics.
$string['bypassheading'] = 'Roles that ignore these restrictions';
$string['bypassintro'] = 'The restrictions work through the recipient\'s blocked-users list, and core lets two '
    . 'capabilities ignore that list entirely. Anybody holding either one can message whoever they like whatever a '
    . 'course says. That is usually what you want for teachers; remove the capability from a role if you do not.';
$string['bypassnone'] = 'No role other than a site administrator can ignore the restrictions.';
$string['bypassrole'] = '{$a->role} - via {$a->capability}';
$string['adminexempt'] = 'Site administrators can always write to anyone, whatever these settings say. Whether a '
    . 'student may write back to them is the "Site administrators" tick - untick it everywhere and students have no '
    . 'way to reach support from inside Moodle.';

// Tasks.
$string['tasksyncblocks'] = 'Reapply student messaging restrictions';
$string['tasksyncuser'] = 'Apply messaging restrictions to one user';

// Errors.
$string['errortoomanyusers'] = 'This site has {$a->count} accounts, above the configured maximum of {$a->max}. Raise '
    . '"Maximum accounts" in the plugin settings if you really want to rebuild over this many.';

// Capabilities.
$string['msgrules:manage'] = 'Manage student messaging restrictions';

// Privacy.
$string['privacy:metadata:local_msgrules_managed'] = 'Which of the conversation blocks in a user\'s account were placed '
    . 'by the course messaging restrictions rather than by the user.';
$string['privacy:metadata:local_msgrules_managed:userid'] = 'The user whose blocked-users list holds the entry.';
$string['privacy:metadata:local_msgrules_managed:blockeduserid'] = 'The user being kept out of that conversation.';
$string['privacy:metadata:local_msgrules_managed:timecreated'] = 'When the restrictions placed the entry.';
