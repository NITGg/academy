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
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_academy\certificates_api;

defined('MOODLE_INTERNAL') || die();

/**
 * The download icon on the certificate list, for a client that has a token
 * rather than a session.
 *
 * A certificate is not a stored file. `mod_customcert` renders it on demand from
 * the template and the user record, so there is no `pluginfile.php` URL to hand
 * a client and nothing `webservice/pluginfile.php` could serve - the web page's
 * download link is a page that generates and streams, and it needs a browser
 * session to reach.
 *
 * So the bytes come back with the answer, the way
 * `local_payments_get_invoice` returns an invoice. A certificate is a single
 * page of graphics; one round trip beats a second authenticated request.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_certificate_pdf extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'certificateid' => new external_value(PARAM_INT,
                'The certificate activity, as local_academy_get_my_certificates reported it.'),
            'lang' => new external_value(PARAM_LANG,
                'Display language for the filename and for any error, e.g. en or ar (optional). '
                . 'It does not decide the language the certificate is written in - see execute().',
                VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * The caller's own copy of one certificate.
     *
     * `lang` is accepted for the same reason its sibling accepts it: a client
     * builds this screen with one language in hand and sends it to every call.
     * What it reaches here is narrower than it looks, and the difference matters
     * enough to state - it localises the suggested filename (which is the
     * template's name through `format_string()`, so a multilang name has to
     * resolve) and any error string, and nothing else.
     *
     * It deliberately does not reach the document. `mod_customcert` decides that
     * itself, in {@see mod_customcert_get_language_to_use()}: the activity's own
     * forced language if the certificate sets one, otherwise the recipient's
     * profile language, otherwise the site default. The request never enters
     * that list. Honouring `lang` there would mean rendering a certificate in a
     * language its author pinned away from, so a client that wants an Arabic
     * certificate changes the account's language, not this parameter.
     *
     * @param int $certificateid the customcert instance
     * @param string $lang display language for the filename and errors
     * @return array
     */
    public static function execute($certificateid, $lang = ''): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(),
            ['certificateid' => $certificateid, 'lang' => $lang]);

        self::validate_context(\context_user::instance($USER->id));

        if ($params['lang'] !== '') {
            force_current_language($params['lang']);
        }

        // Only ever the caller's own copy, and only one they hold - the check is
        // inside pdf(), which refuses an id no issue exists for.
        $pdf = certificates_api::pdf((int) $params['certificateid'], (int) $USER->id);

        return [
            'filename' => $pdf['filename'],
            'mimetype' => 'application/pdf',
            'filesize' => strlen($pdf['content']),
            'content' => base64_encode($pdf['content']),
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
            'filename' => new external_value(PARAM_FILE, 'Suggested filename to save it as.'),
            'mimetype' => new external_value(PARAM_RAW, 'Always application/pdf.'),
            'filesize' => new external_value(PARAM_INT, 'Size of the decoded PDF, in bytes.'),
            'content' => new external_value(PARAM_RAW, 'The PDF itself, base64 encoded.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
