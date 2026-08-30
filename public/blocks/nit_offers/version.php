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
 * Version details for block_nit_offers.
 *
 * A slim announcement bar listing the site's currently running offers, read live
 * from local_nit_commerce so the bar never has to be edited by hand.
 *
 * @package    block_nit_offers
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'block_nit_offers';
$plugin->version   = 2026082900;        // YYYYMMDDXX.
$plugin->requires  = 2024100700;        // Moodle 4.5 LTS baseline.
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';
