<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * The name a certificate was earned under.
 *
 * AC-4.5.1 says changing a profile name must not alter certificates already
 * issued. Out of the box it does, because nothing about a certificate is stored:
 * mod_customcert keeps only userid, template and code in customcert_issues, and
 * builds the PDF afresh on every download - pdf_generation_service::generate_pdf()
 * loads the user with \core_user::get_user() and the studentname element prints
 * fullname() from whatever that returns *now*. Rename yourself and every
 * certificate you have ever been awarded is reprinted under the new name, the
 * verification page included.
 *
 * So the name is copied once, when the certificate is issued, and rendering
 * reads the copy. That is the whole idea; everything here is bookkeeping.
 *
 * Snapshots are keyed on the issue, not on the user, and that distinction is the
 * point. Someone who legitimately changes their name should see the old name on
 * the certificates they already held and the new name on the ones they earn
 * afterwards. Keying on the user could only ever give them one name for all of
 * them, and would be wrong for half.
 *
 * Only the name is frozen. Course title, grade and date are still rendered live:
 * AC-4.5.1 is about the holder's name, and freezing a whole certificate is a
 * different and much larger feature.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificate_names {

    /** Table holding one captured name per issued certificate. */
    const TABLE = 'academy_certificate_names';

    /**
     * Capture the holder's name for a certificate that has just been issued.
     *
     * Idempotent: re-issuing, a replayed event or a re-run backfill must never
     * overwrite a name already captured. The first capture is the truthful one,
     * and a later one would be exactly the corruption this class exists to stop.
     *
     * @param int $issueid customcert_issues.id
     * @param int $userid the certificate holder
     * @return void
     */
    public static function capture(int $issueid, int $userid): void {
        global $DB;

        if ($issueid <= 0 || $userid <= 0) {
            return;
        }

        if ($DB->record_exists(self::TABLE, ['issueid' => $issueid])) {
            return;
        }

        $user = \core_user::get_user($userid);
        if (!$user) {
            return;
        }

        $DB->insert_record(self::TABLE, (object) [
            'issueid'     => $issueid,
            'userid'      => $userid,
            // Stored already formatted rather than as first/last name parts:
            // fullname() also depends on $CFG->fullnamedisplay, so keeping the
            // parts would let a certificate re-order its holder's name years
            // later because a site setting changed.
            'fullname'    => \core_text::substr(fullname($user), 0, 255),
            'timecreated' => time(),
        ]);
    }

    /**
     * The name to print, for a known certificate issue.
     *
     * @param int $issueid customcert_issues.id
     * @return string|null the captured name, or null when this issue has none
     */
    public static function for_issue(int $issueid): ?string {
        global $DB;

        if ($issueid <= 0) {
            return null;
        }

        $name = $DB->get_field(self::TABLE, 'fullname', ['issueid' => $issueid]);

        return ($name === false || $name === '') ? null : (string) $name;
    }

    /**
     * Find the issue a certificate page is being rendered for.
     *
     * Elements are handed the user and their own page, not the issue, so the
     * link has to be walked: page -> template -> customcert -> this user's issue.
     * Same route mod_customcert's own 'code' element takes to print the
     * verification code, and for the same reason.
     *
     * @param int $pageid customcert_pages.id the element sits on
     * @param int $userid the certificate holder
     * @return int the issue id, or 0 when there is none (a template preview, say)
     */
    public static function issue_for_page(int $pageid, int $userid): int {
        global $DB;

        if ($pageid <= 0 || $userid <= 0) {
            return 0;
        }

        $page = $DB->get_record('customcert_pages', ['id' => $pageid], 'id, templateid');
        if (!$page) {
            return 0;
        }

        $customcert = $DB->get_record('customcert', ['templateid' => $page->templateid], 'id', IGNORE_MULTIPLE);
        if (!$customcert) {
            return 0;
        }

        // IGNORE_MULTIPLE mirrors the core element: a user can hold more than one
        // issue of the same certificate, and the oldest is the one whose name was
        // captured first.
        $issue = $DB->get_records(
            'customcert_issues',
            ['userid' => $userid, 'customcertid' => $customcert->id],
            'timecreated ASC, id ASC',
            'id',
            0,
            1
        );

        return $issue ? (int) reset($issue)->id : 0;
    }

    /**
     * Capture names for certificates issued before this existed.
     *
     * Their true issue-time name is gone - nothing recorded it - so today's name
     * is the best available answer, and capturing it at least freezes them from
     * now on. Run once from the upgrade step.
     *
     * @return int how many snapshots were written
     */
    public static function backfill(): int {
        global $DB;

        if (!$DB->get_manager()->table_exists('customcert_issues')) {
            // Site does not run mod_customcert. Nothing to freeze.
            return 0;
        }

        $sql = "SELECT ci.id, ci.userid
                  FROM {customcert_issues} ci
             LEFT JOIN {" . self::TABLE . "} cn ON cn.issueid = ci.id
                 WHERE cn.id IS NULL";

        $written = 0;
        $issues = $DB->get_recordset_sql($sql);
        foreach ($issues as $issue) {
            self::capture((int) $issue->id, (int) $issue->userid);
            $written++;
        }
        $issues->close();

        return $written;
    }
}
