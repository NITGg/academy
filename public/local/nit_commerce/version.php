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
 * Plugin version and metadata for NIT Commerce (discount coupons + automatic offers).
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_nit_commerce';
$plugin->version   = 2026091202;        // Checkout modal + coupon feed: Max discount amount shown to the buyer.
$plugin->requires  = 2024100700;
$plugin->supported = [405, 502];
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';
$plugin->dependencies = [
    'local_nit_core' => 2026080404,
    // The Reports tab on manage_coupons / manage_offers renders the shared report panel from
    // local_nit_finance, so the finance plugin must upgrade first.
    'local_nit_finance' => 2026091200,
];
