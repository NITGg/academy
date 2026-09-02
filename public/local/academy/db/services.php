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
 * Web services for local_academy.
 *
 * This plugin has spoken its own JSON protocol (`/local/academy/api.php`) since
 * before it had any of these; that endpoint is unchanged and every function on
 * it still works. What is registered here is the subset an app building the
 * account screen needs alongside the `local_profilefields_*` functions, so that
 * one screen is not assembled out of two different protocols.
 *
 * The certificate pair is not a duplicate of anything: `mod_customcert` offers
 * no way for a learner to list their own certificates, and it lives here rather
 * than being added to that module because the module is upstream code we do not
 * modify.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_academy_get_my_certificates' => [
        'classname'   => 'local_academy\external\get_my_certificates',
        'methodname'  => 'execute',
        'description' => 'The caller\'s own certificates, newest first, with the course each belongs to '
            . 'and the public verification link for each.',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_academy_get_certificate_pdf' => [
        'classname'   => 'local_academy\external\get_certificate_pdf',
        'methodname'  => 'execute',
        'description' => 'One of the caller\'s certificates as a PDF, base64 encoded. A certificate is '
            . 'rendered on demand rather than stored, so there is no file URL to fetch instead.',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_academy_change_password' => [
        'classname'   => 'local_academy\external\change_password',
        'methodname'  => 'execute',
        'description' => 'Change the caller\'s own password, given the current one. Ends every session '
            . 'and every token the account holds, including the caller\'s.',
        'type'        => 'write',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
