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
 * Cache definitions for local_profilefields.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [

    // Rate-limit tallies for addresses that own no unconfirmed account, so that
    // local_profilefields_resend_confirmation refuses them at exactly the moments
    // it refuses a real one - see local_profilefields\resend_api.
    //
    // A cache and not a table on purpose: this is throwaway state about addresses
    // we have decided to know nothing about, and it must not accumulate into a
    // record of who has been asked for. Losing it costs one free probe.
    //
    // The keys are salted hashes, not addresses. The stored value is a short list
    // of timestamps, which is why simpledata is safe.
    'resendattempts' => [
        'mode' => \core_cache\store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        // The rate-limit window itself. Entries are also filtered by age on read,
        // so a store that ignores ttl still gives the right answer - this only
        // decides when the space is reclaimed.
        'ttl' => HOURSECS,
    ],
];
