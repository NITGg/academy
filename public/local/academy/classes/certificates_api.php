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

namespace local_academy;

use context_module;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * "My certificates" - the list behind `/mod/customcert/my_certificates.php`, and
 * the PDF behind its download icon.
 *
 * It lives here rather than in `mod_customcert` for the reason the whole
 * repository is arranged around: `mod_customcert` is somebody else's plugin, and
 * a web service added to its `db/services.php` is a merge conflict on its next
 * release. Nothing upstream is touched - this reads the module's tables and
 * calls its own services, exactly as its page does.
 *
 * The module also cannot be assumed present: an academy may not have it
 * installed, which is why the account screen only draws its Certificates entry
 * when the file is there. Every entry point here asks {@see self::available()}
 * first.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificates_api {

    /** @var int Certificates per page, when the caller does not say. */
    const PERPAGE = 10;

    /** @var int The most rows one call may ask for. */
    const MAXPERPAGE = 100;

    /**
     * Whether this site has the certificate module at all.
     *
     * @return bool
     */
    public static function available(): bool {
        global $CFG;

        return file_exists($CFG->dirroot . '/mod/customcert/lib.php');
    }

    /**
     * Fail unless the module is installed.
     *
     * @return void
     */
    public static function require_available(): void {
        if (!self::available()) {
            throw new \moodle_exception('err_nocertificates', 'local_academy');
        }
    }

    /**
     * How many certificates this account has been issued.
     *
     * @param int $userid
     * @return int
     */
    public static function count(int $userid): int {
        global $DB;

        return $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {customcert} c
               JOIN {customcert_issues} ci ON ci.customcertid = c.id
              WHERE ci.userid = :userid", ['userid' => $userid]);
    }

    /**
     * One page of the account's certificates, newest first.
     *
     * The same join `mod_customcert`'s own report makes, with three columns it
     * does not need and a client does: the issue id, the course id and the
     * course-module id. A list you cannot open anything from is not a screen.
     *
     * @param int $userid whose certificates
     * @param int $page zero-based page number
     * @param int $perpage rows per page
     * @return stdClass[]
     */
    public static function fetch(int $userid, int $page = 0, int $perpage = self::PERPAGE): array {
        global $DB;

        return $DB->get_records_sql(
            "SELECT ci.id AS issueid, c.id AS certificateid, c.name, c.course AS courseid,
                    co.fullname AS coursename, ci.code, ci.timecreated
               FROM {customcert} c
               JOIN {customcert_issues} ci ON ci.customcertid = c.id
               JOIN {course} co ON co.id = c.course
              WHERE ci.userid = :userid
           ORDER BY ci.timecreated DESC, ci.id DESC",
            ['userid' => $userid], max(0, $page) * $perpage, $perpage);
    }

    /**
     * A page of issues as a client should receive them.
     *
     * The names are put through `format_string()` in the certificate's own module
     * context, which is what the report column does - a course or activity named
     * with a multilang span has to resolve, not arrive as markup.
     *
     * @param stdClass[] $issues as returned by {@see self::fetch()}
     * @return array[]
     */
    public static function rows(array $issues): array {
        $rows = [];

        foreach ($issues as $issue) {
            $cm = get_coursemodule_from_instance('customcert', $issue->certificateid, 0, false, IGNORE_MISSING);
            $context = $cm ? context_module::instance($cm->id) : \context_system::instance();

            $rows[] = [
                'issueid' => (int) $issue->issueid,
                'certificateid' => (int) $issue->certificateid,
                'cmid' => $cm ? (int) $cm->id : 0,
                'courseid' => (int) $issue->courseid,
                'name' => format_string($issue->name, true, ['context' => $context]),
                'coursename' => format_string($issue->coursename, true, ['context' => $context]),
                'code' => (string) $issue->code,
                'timecreated' => (int) $issue->timecreated,
                // Public, and deliberately so: AC-4.5.7 keeps issued certificates
                // verifiable even after the account that earned them is deleted.
                // Safe to print on the certificate and to put behind a QR code.
                'verifyurl' => (new \moodle_url('/mod/customcert/verify_certificate.php',
                    ['contextid' => $context->id, 'code' => $issue->code]))->out(false),
                // Needs a browser session, so it is of no use to a token client -
                // fetch the bytes with local_academy_get_certificate_pdf instead.
                // Reported anyway for a client that can hand a URL to a WebView.
                'downloadurl' => (new \moodle_url('/mod/customcert/my_certificates.php', [
                    'certificateid' => $issue->certificateid,
                    'downloadcert' => 1,
                ]))->out(false),
            ];
        }

        return $rows;
    }

    /**
     * The certificate PDF itself, for one issue this user actually holds.
     *
     * Rendered through `mod_customcert`'s own generation service in return mode,
     * so the document is byte-for-byte the one the web download produces - the
     * same template, the same elements, and the same per-user language switch
     * that decides which language the certificate is written in.
     *
     * @param int $certificateid the customcert instance
     * @param int $userid whose copy
     * @return array{filename: string, content: string} the suggested name and the raw PDF
     */
    public static function pdf(int $certificateid, int $userid): array {
        global $CFG, $DB;

        self::require_available();

        require_once($CFG->dirroot . '/mod/customcert/lib.php');

        // The check my_certificates.php makes before it will render anything: an
        // issue must exist for this user. Without it, any signed-in account could
        // print anybody's certificate simply by naming its id.
        //
        // A certificate that does not exist and one this user was never issued get
        // the same answer, deliberately: distinguishing them would let a caller
        // walk the ids and learn which certificates the site has.
        $customcert = $DB->get_record('customcert', ['id' => $certificateid]);

        $issuerepo = new \mod_customcert\service\issue_repository();
        if (!$customcert || !$issuerepo->find_by_user_certificate((int) $customcert->id, $userid)) {
            throw new \moodle_exception('err_nocertificateissue', 'local_academy');
        }

        $template = \mod_customcert\template::from_record(
            (new \mod_customcert\service\template_repository())->get_by_id_or_fail((int) $customcert->templateid));

        $pdfservice = \mod_customcert\service\pdf_generation_service::create();

        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        $filename = $pdfservice->compute_filename_for_user($template, $user, $customcert);

        // The fourth argument is "return the bytes instead of sending them"; the
        // web page leaves it off and lets the PDF go straight to the browser.
        $content = (string) $pdfservice->generate_pdf($template, false, $userid, true);

        return ['filename' => $filename, 'content' => $content];
    }
}
