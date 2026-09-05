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

$string['pluginname'] = 'Messaging rules';
$string['settings'] = 'Settings';
$string['managematrix'] = 'Who may message whom';

// Settings.
$string['enabled'] = 'Enforce messaging rules';
$string['enabled_desc'] = 'When on, a user may only start a conversation with someone the matrix below permits. '
    . 'Turning it off restores every conversation the plugin had closed, leaving any block a user made themselves in place. '
    . 'Draw the matrix first, then switch this on.';
$string['maxusers'] = 'Maximum accounts';
$string['maxusers_desc'] = 'A rule is stored as one block row per denied direction, so the work grows with the square of '
    . 'the number of accounts. Above this figure a rebuild refuses to run rather than spending hours in cron. Raise it '
    . 'deliberately, and expect a rebuild on a large site to be slow.';

// Management screen.
$string['matrixintro'] = 'Tick a box to let members of the cohort in that row start a conversation with members of the '
    . 'cohort in that column. Direction matters: allowing students to write to instructors says nothing about the reply, '
    . 'which needs its own tick in the opposite cell.';
$string['sendercohort'] = 'Sender';
$string['recipientcohort'] = 'May write to';
$string['nocohort'] = 'Not in any cohort';
$string['nocohort_help'] = 'Covers every account that belongs to no cohort at all, including brand-new sign-ups.';
$string['rulessaved'] = 'Rules saved.';
$string['rebuildnow'] = 'Rebuild now';
$string['rebuildqueued'] = 'A rebuild has been queued and will apply on the next cron run. This site is too large to '
    . 'rebuild while you wait.';
$string['rebuildapplied'] = 'Applied to {$a->users} accounts: {$a->added} conversations closed, {$a->removed} reopened. '
    . 'This is live now - log in as a test account to check it.';
$string['currentstate'] = 'Current state';
$string['managedblocks'] = 'Conversations currently closed by these rules: {$a}';
$string['nocohortsyet'] = 'There are no cohorts on this site yet, so there is nothing to draw rules over. Create the '
    . 'groups you want to separate under Site administration > Users > Cohorts, then come back.';
$string['disabledwarning'] = 'These rules are not being enforced. Switch on "Enforce messaging rules" in the settings '
    . 'once the matrix says what you want.';
$string['messagingoffwarning'] = 'The site messaging system is switched off, so nothing can be sent anyway. These rules '
    . 'will start to matter once messaging is enabled under Site administration > Advanced features.';

// Bypass diagnostics.
$string['bypassheading'] = 'Roles that ignore these rules';
$string['bypassintro'] = 'The rules work through the recipient\'s blocked-users list, and core lets two capabilities '
    . 'ignore that list entirely. Anybody holding either one can message whoever they like whatever the matrix says. '
    . 'Remove the capability from the roles below if you want the rules to apply to them.';
$string['bypassnone'] = 'No role other than a site administrator can ignore the rules.';
$string['bypassrole'] = '{$a->role} - via {$a->capability}';
$string['adminexempt'] = 'Site administrators are always exempt: they are never blocked, and nobody is ever blocked from '
    . 'writing to them, so there is always a way to reach support.';

// Tasks.
$string['tasksyncblocks'] = 'Rebuild messaging rules';
$string['tasksyncuser'] = 'Apply messaging rules to one user';

// Errors.
$string['errortoomanyusers'] = 'This site has {$a->count} accounts, above the configured maximum of {$a->max}. Raise '
    . '"Maximum accounts" in the plugin settings if you really want to rebuild over this many.';

// Capabilities.
$string['msgrules:manage'] = 'Manage the messaging rules matrix';

// Privacy.
$string['privacy:metadata:local_msgrules_managed'] = 'Which of the conversation blocks in a user\'s account were placed '
    . 'by the site messaging rules rather than by the user.';
$string['privacy:metadata:local_msgrules_managed:userid'] = 'The user whose blocked-users list holds the entry.';
$string['privacy:metadata:local_msgrules_managed:blockeduserid'] = 'The user being kept out of that conversation.';
$string['privacy:metadata:local_msgrules_managed:timecreated'] = 'When the rules placed the entry.';
