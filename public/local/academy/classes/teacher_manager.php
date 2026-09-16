<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Teacher (instructor) directory for the app.
 *
 * Faithful to the old academy's local_academy teacher API — same function names
 * (get_all_teachers / browse_teachers / get_teacher) and the same response keys —
 * so the existing web/mobile clients work unchanged.
 *
 * Difference from the old academy: this build is Moodle-native (courses model,
 * not the Flex tutoring engine), so it has no tutoring hours/years/busy-times
 * tables. Those fields are still present in every response for contract parity,
 * but with empty/default values (hours=[], years=[], busy_times=[], rating=0,
 * approved=1, available=1). The Moodle-native fields (userid, fullname, email,
 * phone, bio, photourl) carry real data; headline / bio / experience / subjects
 * are fed from the "Instructor Fields" custom profile group (the same fields the
 * course page's instructor card shows — see instructor_fields()), and the
 * single-teacher view adds the rest of that group (qualifications, certificates,
 * awards, social links, cover image, résumé) plus a `courses` list — a superset
 * that never breaks the old shape.
 *
 * A "teacher" = any non-deleted user holding a role with the 'teacher' or
 * 'editingteacher' archetype in at least one course. No custom tables, no core
 * modifications.
 */
class teacher_manager {

    /** Caller's web-service token, so returned image URLs are directly loadable. */
    private static $token = '';

    /** Set the caller's token so photo/image URLs are returned token-embedded. */
    public static function set_token(string $token): void {
        self::$token = $token;
    }

    /** User columns needed to build a name + user_picture + contact safely. */
    private static function user_fields(): string {
        return 'u.id, u.firstname, u.lastname, u.middlename, u.alternatename,
                u.firstnamephonetic, u.lastnamephonetic, u.picture, u.imagealt, u.email, u.phone1,
                u.description, u.descriptionformat, u.city, u.country';
    }

    /** Same columns without the "u." alias, for get_record_select. */
    private static function user_fields_plain(): string {
        return str_replace('u.', '', self::user_fields());
    }

    /** Is this user a teacher anywhere? (archetype-based, so admins are excluded.) */
    public static function is_teacher(int $userid): bool {
        global $DB;
        if (empty($userid) || isguestuser($userid)) {
            return false;
        }
        $sql = "SELECT 1
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.userid = :uid AND r.archetype IN ('teacher', 'editingteacher')";
        return $DB->record_exists_sql($sql, ['uid' => $userid]);
    }

    /**
     * Admin: list all teachers with optional filters and pagination.
     * Matches the old academy shape: { total, page, perpage, teachers[] }.
     *
     * @param array $filters search, courseid, categoryid, page, perpage
     *                       (subject/year/approved/available accepted but no-ops here)
     */
    public static function get_all_teachers(array $filters = []): array {
        global $DB;

        $where  = ['u.deleted = 0', "r.archetype IN ('teacher', 'editingteacher')"];
        $params = [];

        if (!empty($filters['courseid'])) {
            $where[] = 'EXISTS (SELECT 1 FROM {role_assignments} raf
                                 JOIN {context} ctx ON ctx.id = raf.contextid AND ctx.contextlevel = 50
                                 JOIN {role} rf ON rf.id = raf.roleid
                                                AND rf.archetype IN (\'teacher\', \'editingteacher\')
                                WHERE raf.userid = u.id AND ctx.instanceid = :courseid)';
            $params['courseid'] = (int) $filters['courseid'];
        }
        if (!empty($filters['categoryid'])) {
            $where[] = 'EXISTS (SELECT 1 FROM {role_assignments} raf
                                 JOIN {context} ctx ON ctx.id = raf.contextid AND ctx.contextlevel = 50
                                 JOIN {course} c ON c.id = ctx.instanceid AND c.category = :categoryid
                                 JOIN {role} rf ON rf.id = raf.roleid
                                                AND rf.archetype IN (\'teacher\', \'editingteacher\')
                                WHERE raf.userid = u.id)';
            $params['categoryid'] = (int) $filters['categoryid'];
        }
        if (!empty($filters['search'])) {
            $q = '%' . $DB->sql_like_escape($filters['search']) . '%';
            $where[] = '(' . $DB->sql_like('u.firstname', ':sq1', false)
                     . ' OR ' . $DB->sql_like('u.lastname', ':sq2', false)
                     . ' OR ' . $DB->sql_like('u.email', ':sq3', false) . ')';
            $params['sq1'] = $q;
            $params['sq2'] = $q;
            $params['sq3'] = $q;
        }

        $whereclause = implode(' AND ', $where);
        $basesql = "FROM {user} u
                    JOIN {role_assignments} ra ON ra.userid = u.id
                    JOIN {role} r ON r.id = ra.roleid
                   WHERE $whereclause";

        $total   = (int) $DB->count_records_sql("SELECT COUNT(DISTINCT u.id) $basesql", $params);
        $page    = max(0, (int) ($filters['page'] ?? 0));
        $perpage = min(200, max(1, (int) ($filters['perpage'] ?? 20)));

        $rows = $DB->get_records_sql(
            "SELECT DISTINCT " . self::user_fields() . " $basesql
              ORDER BY u.lastname ASC, u.firstname ASC",
            $params,
            $page * $perpage,
            $perpage
        );

        $teachers = [];
        foreach ($rows as $u) {
            // Admin listing keeps email (matches old get_all_teachers).
            $teachers[] = self::format_profile($u, true, false);
        }

        return ['total' => $total, 'page' => $page, 'perpage' => $perpage, 'teachers' => $teachers];
    }

    /**
     * Public: browse instructors. Matches the old academy: returns a bare array
     * with email dropped. The $subject filter is accepted for signature parity
     * (the courses model has no subjects, so it does not filter).
     */
    public static function browse_teachers(string $subject = ''): array {
        global $DB;
        $sql = "SELECT DISTINCT " . self::user_fields() . "
                  FROM {user} u
                  JOIN {role_assignments} ra ON ra.userid = u.id
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE u.deleted = 0 AND u.suspended = 0
                   AND r.archetype IN ('teacher', 'editingteacher')
              ORDER BY u.lastname ASC, u.firstname ASC";
        $rows = $DB->get_records_sql($sql);

        $out = [];
        foreach ($rows as $u) {
            $out[] = self::format_profile($u, false, false);
        }
        return $out;
    }

    /** Public: a single instructor's profile + the courses they teach (email dropped). */
    public static function get_teacher(int $teacherid): array {
        global $DB;
        $u = $DB->get_record_select(
            'user',
            'id = :id AND deleted = 0',
            ['id' => $teacherid],
            self::user_fields_plain()
        );
        if (!$u || !self::is_teacher($teacherid)) {
            throw new \moodle_exception('err_teachernotfound', 'local_academy');
        }
        return self::format_profile($u, false, true);
    }

    /** Courses a teacher teaches (visible courses only). */
    public static function get_teacher_courses(int $teacherid): array {
        global $DB;

        $sql = "SELECT DISTINCT c.id, c.fullname, c.shortname, c.summary, c.summaryformat
                  FROM {course} c
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                  JOIN {role} r ON r.id = ra.roleid
                                AND r.archetype IN ('teacher', 'editingteacher')
                 WHERE ra.userid = :uid AND c.id <> :site AND c.visible = 1
              ORDER BY c.fullname ASC";
        $rows = $DB->get_records_sql($sql, ['uid' => $teacherid, 'site' => SITEID]);

        $courses = [];
        foreach ($rows as $c) {
            $context = \context_course::instance($c->id);
            $summary = '';
            if (!empty($c->summary)) {
                $summary = trim(html_to_text(
                    format_text($c->summary, $c->summaryformat, ['context' => $context, 'noclean' => true]),
                    0,
                    false
                ));
            }
            $courses[] = [
                'id'        => (int) $c->id,
                'fullname'  => format_string($c->fullname, true, ['context' => $context]),
                'shortname' => $c->shortname,
                'summary'   => $summary,
                'imageurl'  => self::course_image_url($c->id, $context),
                'url'       => (new \moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
            ];
        }
        return $courses;
    }

    // ── helpers ──

    /**
     * Build the teacher view-model in the old academy's exact shape.
     *
     * @param \stdClass $u          user row (see user_fields)
     * @param bool $withemail       include the email field (admin listing) — public views drop it
     * @param bool $withcourses     append the `courses` list (single-teacher view)
     */
    private static function format_profile(\stdClass $u, bool $withemail, bool $withcourses): array {
        $bio = '';
        if (!empty($u->description)) {
            $bio = trim(html_to_text(
                format_text($u->description, $u->descriptionformat ?? FORMAT_HTML,
                    ['context' => \context_system::instance(), 'noclean' => true]),
                0,
                false
            ));
        }

        // The "Instructor Fields" profile group — the same data the course page's
        // instructor card and dialog draw (theme_nit format_topics_renderer).
        $ins = self::instructor_fields((int) $u->id, $withcourses);

        // Old academy shape — Flex-only fields kept as defaults for contract parity.
        // headline / bio / experience / subjects are fed from the instructor
        // fields where they are filled in; the tutoring-only ones stay empty.
        $out = [
            'userid'     => (int) $u->id,
            'fullname'   => fullname($u),
            'email'      => $u->email ?? '',
            'phone'      => $u->phone1 ?? '',
            'headline'   => $ins['specialization'],
            // The Biography instructor field first; the account's own description
            // is what a non-instructor profile has, so it stays as the fallback.
            'bio'        => $ins['biography'] !== '' ? $ins['biography'] : $bio,
            'experience' => $ins['experience'],
            'photourl'   => self::picture_url($u),
            'rating'     => 0,
            'approved'   => 1,
            'available'  => 1,
            'subjects'   => $ins['specialization'] !== '' ? [$ins['specialization']] : [],
            'years'      => [],
            'hours'      => [],
            'busy_times' => [],
            // Superset (never in the old shape, additive): quick course info.
            'coursecount' => self::course_count((int) $u->id),
        ] + $ins;
        if (!$withemail) {
            unset($out['email']);
        }
        if ($withcourses) {
            $out['courses'] = self::get_teacher_courses((int) $u->id);
        }
        return $out;
    }

    /**
     * The "Instructor Fields" custom profile fields, as one flat array.
     *
     * The group was built by hand on the site (local_profilefields only knows
     * its labels — see \local_profilefields\provision::INSTRUCTOR_FIELDS), so
     * fields are found by shortname, the one part of a profile field that is a
     * code. Same mapping as the course page's instructor card:
     *
     *   specialization, yearsofexperience, languages ... short text
     *   biography, experience, qualifications,
     *   certificates, awards ........................... rich text → plain text
     *   linkedin, website, facebook, instagram,
     *   twitter, youtube ............................... social → absolute URL
     *   coverimage, resume ............................. file → token-embedded URL
     *
     * Every key is always present, so a client can read them without guarding,
     * and a field the site does not have simply stays at its empty value.
     * Field visibility is honoured through the profile API (is_visible() for the
     * token's user), the values are authored as {mlang} pairs and resolve to the
     * request language via the `lang` parameter, and a rich-text field arrives
     * as plain text (lists keep their bullets) — the app renders it, not HTML.
     *
     * @param int $userid
     * @param bool $full include the long fields, social links and files (single
     *                   view); a listing gets just the short ones
     * @return array
     */
    private static function instructor_fields(int $userid, bool $full): array {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $out = [
            'specialization'   => '',
            'years_experience' => 0,
            'languages'        => [],
            'biography'        => '',
            'experience'       => '',
        ];
        if ($full) {
            $out += [
                'qualifications' => '',
                'certificates'   => '',
                'awards'         => '',
                'social'         => [
                    'linkedin' => '', 'website' => '', 'facebook' => '',
                    'instagram' => '', 'twitter' => '', 'youtube' => '',
                ],
                'cover_url'      => '',
                'resume_url'     => '',
            ];
        }

        $short = ['specialization', 'yearsofexperience', 'languages'];
        $rich  = ['biography', 'experience'];
        $files = [];
        if ($full) {
            $rich  = array_merge($rich, ['qualifications', 'certificates', 'awards']);
            $files = ['coverimage' => 'cover_url', 'resume' => 'resume_url'];
        }

        $usercontext = \context_user::instance($userid, IGNORE_MISSING);
        if (!$usercontext) {
            return $out;
        }

        foreach (profile_get_user_fields_with_data($userid) as $f) {
            $name = (string) ($f->field->shortname ?? '');
            if (!$f->is_visible()) {
                continue;
            }

            // File fields: the file is the value, whatever the data row says.
            if (isset($files[$name])) {
                $file = self::profile_file($usercontext, (int) $f->field->id);
                if ($file && ($name !== 'coverimage' || $file->is_valid_image())) {
                    $out[$files[$name]] = ws_files::tokenize(\moodle_url::make_pluginfile_url(
                        $file->get_contextid(), 'profilefield_file', 'files',
                        $file->get_itemid(), $file->get_filepath(), $file->get_filename()
                    )->out(false), self::$token);
                }
                continue;
            }

            if ($f->is_empty()) {
                continue;
            }

            if (in_array($name, $short, true)) {
                $text = self::ml((string) $f->data);
                if ($name === 'specialization') {
                    $out['specialization'] = $text;
                } else if ($name === 'yearsofexperience') {
                    // "12", "12+" — anything else the admin typed is not a number.
                    $out['years_experience'] = preg_match('/^\s*(\d{1,2})\s*\+?\s*$/', $text, $m) ? (int) $m[1] : 0;
                } else {
                    // "Arabic, English" / "العربية، الإنجليزية" → one entry each.
                    $out['languages'] = preg_split('/\s*[,،|\/\n]+\s*/u', $text, -1, PREG_SPLIT_NO_EMPTY);
                }
            } else if (in_array($name, $rich, true)) {
                // The field's own renderer formats it (embedded files, filters);
                // {mlang} is resolved again in case the filter is off for content.
                $out[$name] = self::plain(self::ml($f->display_data()));
            } else if ($full && isset($out['social'][$name])) {
                $out['social'][$name] = self::social_url($name, (string) $f->data);
            }
        }

        return $out;
    }

    /**
     * Resolve a possibly-bilingual "{mlang}" value to plain text in the current
     * language — which the `lang` request parameter sets.
     *
     * format_string() lets the site's multilang filter do it when that filter is
     * enabled for strings; the fallback resolver covers a site where it is not,
     * so the app never receives raw {mlang} markup. Same rule as the course page:
     * the current language wins, then an "other" block, then the first block, so
     * a value written in one language only is shown rather than lost.
     *
     * @param string $raw
     * @return string
     */
    private static function ml(string $raw): string {
        if (trim($raw) === '') {
            return '';
        }
        if (stripos($raw, '{mlang') === false) {
            return trim($raw);
        }
        if (!preg_match_all('/\{mlang\s+([^}]+)\}(.*?)\{mlang\}/is', $raw, $matches, PREG_SET_ORDER)) {
            return trim($raw);
        }
        $lang = current_language();
        $matched = $other = '';
        $first = null;
        foreach ($matches as $block) {
            $langs = array_map('trim', explode(',', strtolower($block[1])));
            $first = $first ?? $block[2];
            if (in_array($lang, $langs, true)) {
                $matched .= $block[2];
            }
            if (in_array('other', $langs, true)) {
                $other .= $block[2];
            }
        }
        return trim($matched !== '' ? $matched : ($other !== '' ? $other : ($first ?? '')));
    }

    /**
     * Rich text as the plain text an app label can show.
     *
     * Not html_to_text(): that one shouts <strong> in capitals and indents nested
     * lists with tabs, which is fine in an e-mail and wrong on a phone. Here a
     * list item becomes a "• " line, a paragraph/line break a newline, every other
     * tag is dropped, and entities are decoded.
     *
     * @param string $html
     * @return string
     */
    private static function plain(string $html): string {
        $text = preg_replace('~<li\b[^>]*>~i', '• ', $html);
        $text = preg_replace('~<(br\s*/?|ul\b[^>]*|ol\b[^>]*)>~i', "\n", $text);
        $text = preg_replace('~</(li|ul|ol|tr)\s*>~i', "\n", $text);
        $text = preg_replace('~</(p|div|h[1-6]|blockquote)\s*>~i', "\n\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Non-breaking spaces from the editor, then per-line trim, then at most
        // one blank line between paragraphs.
        $text = str_replace("\u{a0}", ' ', $text);
        $text = implode("\n", array_map('trim', explode("\n", $text)));
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }

    /**
     * A social field's value as an absolute https URL, or '' when it is not one.
     *
     * The fields are free text: a full address, a bare domain ("example.com") or
     * a handle ("@name") all happen. A domain gets its scheme, a handle goes to
     * the network the field is for, and anything that still is not a URL is
     * dropped rather than handed to the app as a broken link.
     *
     * @param string $network field shortname (which network)
     * @param string $raw stored value, possibly {mlang} markup
     * @return string
     */
    private static function social_url(string $network, string $raw): string {
        $raw = self::ml($raw);
        if ($raw === '') {
            return '';
        }
        $hosts = ['twitter' => 'x.com/', 'instagram' => 'instagram.com/', 'youtube' => 'youtube.com/@',
            'facebook' => 'facebook.com/', 'linkedin' => 'linkedin.com/in/'];
        if ($raw[0] === '@' && isset($hosts[$network])) {
            $raw = 'https://' . $hosts[$network] . ltrim($raw, '@');
        } else if (!preg_match('~^https?://~i', $raw)) {
            $raw = 'https://' . ltrim($raw, '/');
        }
        $clean = clean_param($raw, PARAM_URL);
        return preg_match('~^https?://[^/\s]+~i', $clean) ? $clean : '';
    }

    /**
     * The one file stored in a profilefield_file field for a user — where that
     * field type keeps it: the user's context, component `profilefield_file`,
     * area `files`, itemid = the field id.
     *
     * @param \context_user $usercontext
     * @param int $fieldid
     * @return \stored_file|null
     */
    private static function profile_file(\context_user $usercontext, int $fieldid): ?\stored_file {
        $files = get_file_storage()->get_area_files($usercontext->id, 'profilefield_file', 'files',
            $fieldid, 'itemid, filepath, filename', false);
        return $files ? reset($files) : null;
    }

    /** How many visible courses this teacher teaches. */
    private static function course_count(int $userid): int {
        global $DB;
        $sql = "SELECT COUNT(DISTINCT ctx.instanceid)
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                  JOIN {role} r ON r.id = ra.roleid AND r.archetype IN ('teacher', 'editingteacher')
                  JOIN {course} c ON c.id = ctx.instanceid AND c.visible = 1
                 WHERE ra.userid = :uid";
        return (int) $DB->count_records_sql($sql, ['uid' => $userid]);
    }

    /** Absolute profile-picture URL (real photo if uploaded, else the default). */
    private static function picture_url(\stdClass $u): string {
        $page = new \moodle_page();
        $page->set_context(\context_system::instance());
        $up = new \user_picture($u);
        $up->size = 100;
        return ws_files::tokenize($up->get_url($page)->out(false), self::$token);
    }

    /** Course overview image URL, or '' if none. */
    private static function course_image_url(int $courseid, \context $context): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'filename', false);
        foreach ($files as $file) {
            if ($file->is_valid_image()) {
                return ws_files::tokenize(\moodle_url::make_pluginfile_url(
                    $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                    null, $file->get_filepath(), $file->get_filename()
                )->out(false), self::$token);
            }
        }
        return '';
    }
}
