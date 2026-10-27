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
 * Strings for filter_nitwwwroot.
 *
 * @package    filter_nitwwwroot
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['filtername'] = 'Site address links';
$string['privacy:metadata'] = 'The Site address links filter does not store any personal data.';
$string['roots'] = 'Earlier site addresses';
$string['roots_desc'] = 'Every address this site has been served from, one per line. Links in content that point under any of them are shown pointing at the current address ({$a}) instead. A new address is added here on its own the first time the site runs under it, so a domain change needs no edit. Remove a line only if that address now belongs to a different site.';
