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

namespace local_academy\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_academy\certificates_api;

defined('MOODLE_INTERNAL') || die();

/**
 * `/mod/customcert/my_certificates.php` for a client that cannot render the page.
 *
 * `mod_customcert` ships one listing web service, `mod_customcert_list_issues`,
 * and it is the teacher's list: it takes a certificate id and requires
 * `mod/customcert:viewallcertificates`, so a learner asking for their own
 * certificates is refused by it. There has never been a way to ask "what have
 * *I* earned".
 *
 * The rows carry what a screen needs and the report page does not print: the
 * course the certificate belongs to, so a row can be opened; and the public
 * verification URL, which is the thing an employer is actually given.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_my_certificates extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'page' => new external_value(PARAM_INT, 'Zero-based page number.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT,
                'Rows per page. Capped at ' . certificates_api::MAXPERPAGE . '.',
                VALUE_DEFAULT, certificates_api::PERPAGE),
            'lang' => new external_value(PARAM_LANG,
                'Display language for the names, e.g. en or ar (optional).', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * The caller's own certificates, newest first.
     *
     * There is no user id parameter: viewing somebody else's certificates needs
     * `mod/customcert:viewallcertificates` and belongs on the report screens that
     * already ask for it.
     *
     * @param int $page zero-based page number
     * @param int $perpage rows per page
     * @param string $lang display language
     * @return array
     */
    public static function execute($page = 0, $perpage = certificates_api::PERPAGE, $lang = ''): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'page' => $page,
            'perpage' => $perpage,
            'lang' => $lang,
        ]);

        self::validate_context(\context_user::instance($USER->id));

        if ($params['lang'] !== '') {
            force_current_language($params['lang']);
        }

        // A site with no certificate module has no certificates, which is an empty
        // list rather than an error: the account screen simply does not offer the
        // entry, and a client that asks anyway should get the same answer.
        if (!certificates_api::available()) {
            return [
                'certificates' => [],
                'total' => 0,
                'page' => 0,
                'perpage' => (int) $params['perpage'],
                'available' => false,
                'warnings' => [],
            ];
        }

        $userid = (int) $USER->id;
        $perpage = min(max(1, (int) $params['perpage']), certificates_api::MAXPERPAGE);
        $pagenum = max(0, (int) $params['page']);

        return [
            'certificates' => certificates_api::rows(certificates_api::fetch($userid, $pagenum, $perpage)),
            'total' => certificates_api::count($userid),
            'page' => $pagenum,
            'perpage' => $perpage,
            'available' => true,
            'warnings' => [],
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'certificates' => new external_multiple_structure(
                new external_single_structure([
                    'issueid' => new external_value(PARAM_INT, 'This particular issue of the certificate.'),
                    'certificateid' => new external_value(PARAM_INT,
                        'The certificate activity. Pass this to local_academy_get_certificate_pdf.'),
                    'cmid' => new external_value(PARAM_INT,
                        'Its course-module id, for opening the activity. 0 if it could not be resolved.'),
                    'courseid' => new external_value(PARAM_INT, 'The course the certificate belongs to.'),
                    'name' => new external_value(PARAM_RAW, 'The certificate\'s name.'),
                    'coursename' => new external_value(PARAM_RAW, 'The course\'s name.'),
                    'code' => new external_value(PARAM_RAW,
                        'The verification code printed on the certificate.'),
                    'timecreated' => new external_value(PARAM_INT, 'When it was issued.'),
                    'verifyurl' => new external_value(PARAM_URL,
                        'Public verification page for this code - needs no login, and keeps working after '
                        . 'the account is deleted. This is the link to share or put behind a QR code.'),
                    'downloadurl' => new external_value(PARAM_URL,
                        'The web download. It needs a browser session, so a token client should use '
                        . 'local_academy_get_certificate_pdf instead.'),
                ]), 'This page of certificates, newest first.'
            ),
            'total' => new external_value(PARAM_INT, 'How many certificates this account holds in all.'),
            'page' => new external_value(PARAM_INT, 'The page returned.'),
            'perpage' => new external_value(PARAM_INT, 'Rows per page actually used, after the cap.'),
            'available' => new external_value(PARAM_BOOL,
                'False when this site has no certificate module. Hide the screen rather than showing '
                . 'an empty list.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
