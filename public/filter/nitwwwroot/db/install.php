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
 * Switch the filter on site-wide and record every address the site is known by.
 *
 * @package    filter_nitwwwroot
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Post-install: turn the filter on and seed the list of earlier site addresses.
 *
 * The seeds are the addresses this site has been served from before the filter
 * existed; everything after that is recorded on its own (see roots::legacy()).
 */
function xmldb_filter_nitwwwroot_install() {
    global $CFG;
    require_once($CFG->libdir . '/filterlib.php');

    filter_set_global_state('nitwwwroot', TEXTFILTER_ON);

    \filter_nitwwwroot\roots::remember([
        'https://academy2026.nitg-eg.com/moodle-new',
        'https://eaac-learning.com/moodle-new',
        $CFG->wwwroot,
    ]);
}
