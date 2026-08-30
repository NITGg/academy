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
 * English strings for block_nit_offers.
 *
 * @package    block_nit_offers
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT Offers bar';
$string['nit_offers:addinstance'] = 'Add a new NIT Offers bar block';
$string['nit_offers:myaddinstance'] = 'Add a new NIT Offers bar block to the Dashboard';

// Bar content.
$string['offerflag'] = 'Offer';
$string['ends'] = 'Ends {$a}';
$string['starts'] = 'Starts {$a}';
$string['endstoday'] = 'Ends today';
$string['endstomorrow'] = 'Ends tomorrow';
$string['endsindays'] = 'Ends in {$a} days';
$string['seecourses'] = 'See courses';
$string['appliesto'] = 'Applies to: {$a}';
$string['amountoff'] = '{$a} off';
$string['dismiss'] = 'Dismiss this announcement';
$string['previousoffer'] = 'Previous offer';
$string['nextoffer'] = 'Next offer';
$string['showoffer'] = 'Show offer';
$string['nooffers'] = 'There are no offers running right now.';
$string['editingnooffers'] = 'No offers are running right now, so this bar is hidden to everyone else. Create one in Site administration &gt; Plugins &gt; Local plugins &gt; NIT Commerce &gt; Manage offers.';

// Settings.
$string['showtitle'] = 'Show the block title';
$string['showtitle_help'] = 'Off by default: the bar is meant to sit flush on the page with no block header or card around it.';
$string['blocktitle'] = 'Block title';
$string['source'] = 'What the bar shows';
$string['source_help'] = 'Live offers: the bar builds itself from the offers currently running in NIT Commerce, so it never needs editing. Custom message: you write the bar text yourself, and it stays until you change it.';
$string['source_auto'] = 'Live offers (automatic)';
$string['source_custom'] = 'Custom message';
$string['customhtml'] = 'Custom message';
$string['customhtml_help'] = 'The headline shown in the bar. Plain text or simple inline HTML.';
$string['maxoffers'] = 'Maximum offers shown';
$string['maxoffers_help'] = 'How many of the running offers the bar cycles through. The newest offers are shown first.';
$string['ctalabel'] = 'Link text';
$string['ctalabel_help'] = 'Leave empty to use "See courses".';
$string['ctaurl'] = 'Link address';
$string['ctaurl_help'] = 'Where the link at the end of the bar goes. Leave empty to send visitors to the course catalogue. An offer scoped to a single course always links to that course instead.';
$string['rotate'] = 'Cycle through offers automatically';
$string['rotate_help'] = 'When more than one offer is running, the bar advances every few seconds. It pauses while the pointer is over it, and never moves for visitors who ask for reduced motion.';
$string['dismissible'] = 'Let visitors close the bar';
$string['dismissible_help'] = 'Adds a close button. The bar stays hidden in that browser until the set of running offers changes.';
$string['hidewhenempty'] = 'Hide the block when nothing is running';
$string['hidewhenempty_help'] = 'Recommended. When off, the bar prints a short "no offers" note instead of disappearing.';
$string['appearance'] = 'Appearance';
$string['tone'] = 'Bar colour';
$string['tone_help'] = 'Which brand colour tints the bar.';
$string['tone_accent'] = 'Accent';
$string['tone_primary'] = 'Primary';
$string['tone_success'] = 'Success';
$string['tone_warning'] = 'Warning';
