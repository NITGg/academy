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
 * Renders and sends the three transactional emails.
 *
 * This is the only entry point the rest of the site uses. Each send_* method is
 * safe to call unconditionally: it returns false (and sends nothing) when the
 * event is switched off in the admin page or the recipient cannot be emailed,
 * which lets the caller keep its previous behaviour as a fallback.
 *
 * The language is the recipient's, not the sender's: the user's own `lang`
 * (falling back to the site default) decides whether the Arabic or the English
 * template is used, and it is forced for the whole render so course names,
 * dates and {mlang} custom fields come out in the same language as the wording
 * around them. Arabic additionally flips the shell to RTL.
 *
 * @package    local_nit_emails
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_emails;

defined('MOODLE_INTERNAL') || die();

/**
 * Transactional email sender.
 */
class mailer {

    /**
     * Does this plugin own the message for $event?
     *
     * Callers that still carry an older notification of their own ask this
     * first: true means "leave it to me" (whether that ends in an email or —
     * because an admin switched the event off — in deliberate silence), false
     * means the plugin is standing aside and the caller should do what it did
     * before this plugin existed.
     *
     * @param string $event one of the templates::EVENT_* keys
     * @return bool
     */
    public static function handles(string $event): bool {
        return templates::is_enabled($event);
    }

    /**
     * "You bought this course" — the course file summary.
     *
     * @param \stdClass $user recipient
     * @param \stdClass $course the purchased course
     * @param \stdClass|null $transaction local_payments transaction (amount, currency, order_id)
     * @return bool true when the email was handed to the mail system
     */
    public static function send_course_purchase(\stdClass $user, \stdClass $course,
            ?\stdClass $transaction = null): bool {
        return self::dispatch(templates::EVENT_COURSE, $user,
            function () use ($user, $course, $transaction) {
                return context_builder::course($user, $course, $transaction);
            });
    }

    /**
     * "Your subscription is active" — the plan summary.
     *
     * @param \stdClass $purchase nit_sub_purchase record (must carry userid + subscriptionid)
     * @return bool
     */
    public static function send_subscription_purchase(\stdClass $purchase): bool {
        global $DB;

        $user = $DB->get_record('user', ['id' => $purchase->userid ?? 0]);
        $subscription = $DB->get_record('nit_subscription', ['id' => $purchase->subscriptionid ?? 0]);
        if (!$user || !$subscription) {
            return false;
        }

        return self::dispatch(templates::EVENT_SUBSCRIPTION, $user,
            function () use ($user, $subscription, $purchase) {
                return context_builder::subscription($user, $subscription, $purchase);
            });
    }

    /**
     * "Your account is ready" — sent once registration is complete.
     *
     * @param \stdClass $user the new user
     * @return bool
     */
    public static function send_registration(\stdClass $user): bool {
        return self::dispatch(templates::EVENT_REGISTRATION, $user,
            function () use ($user) {
                return context_builder::registration($user);
            });
    }

    /**
     * Send a copy of a template to one person, filled with sample data, so an
     * admin can see what it looks like in a real inbox.
     *
     * @param string $event
     * @param \stdClass $user recipient (the admin)
     * @param string $lang 'en' | 'ar' — which authored template to send
     * @return bool
     */
    public static function send_test(string $event, \stdClass $user, string $lang): bool {
        $lang = templates::normalise_lang($lang);
        $old = force_current_language($lang);
        try {
            $rendered = self::render($event, $lang, context_builder::sample($event, $user));
            $rendered['subject'] = '[' . get_string('test', 'local_nit_emails') . '] ' . $rendered['subject'];
            return self::deliver($user, $rendered);
        } finally {
            force_current_language($old);
        }
    }

    /**
     * The full HTML of a template filled with sample data — what the preview
     * page shows in its frame.
     *
     * @param string $event
     * @param string $lang 'en' | 'ar'
     * @param \stdClass $user viewer, used for the name placeholders
     * @return string complete HTML document
     */
    public static function preview(string $event, string $lang, \stdClass $user): string {
        $lang = templates::normalise_lang($lang);
        $old = force_current_language($lang);
        try {
            return self::render($event, $lang, context_builder::sample($event, $user))['html'];
        } finally {
            force_current_language($old);
        }
    }

    /**
     * Fill a template and wrap it in the branded shell.
     *
     * @param string $event
     * @param string $lang 'en' | 'ar'
     * @param array<string, string> $values placeholder => ready-to-insert HTML
     * @return array{subject: string, html: string, text: string}
     */
    public static function render(string $event, string $lang, array $values): array {
        $search = [];
        $replace = [];
        foreach ($values as $key => $value) {
            $search[] = '{' . $key . '}';
            $replace[] = $value;
        }

        $subject = str_replace($search, $replace, templates::subject($event, $lang));
        // The subject is plain text: any markup a value carried is stripped and
        // entities are decoded so "&amp;" does not reach the inbox literally.
        $subject = html_entity_decode(strip_tags($subject), ENT_QUOTES, 'UTF-8');

        $body = str_replace($search, $replace, templates::body($event, $lang));
        $html = self::wrap($body, $lang, $subject);

        return [
            'subject' => $subject,
            'html'    => $html,
            'text'    => html_to_text($html, 0),
        ];
    }

    // =========================================================================
    // Internals
    // =========================================================================

    /**
     * Shared send path: check the switch, pick the language, render, deliver.
     *
     * @param string $event
     * @param \stdClass $user recipient
     * @param callable $valuesfn builds the placeholder values (called with the
     *                           recipient's language already forced)
     * @return bool
     */
    private static function dispatch(string $event, \stdClass $user, callable $valuesfn): bool {
        if (!templates::is_enabled($event) || !self::can_email($user)) {
            return false;
        }

        // AC-4.5.5. Consulted for every event, not only the ones that happen to be
        // optional today: preferences::accepts() answers "yes" for anything that
        // is not marketing, so the check costs nothing now and is already in place
        // for the first campaign email somebody adds later. Putting it here rather
        // than at each call site is what makes that guarantee hold.
        if (!preferences::accepts((int) $user->id, $event)) {
            return false;
        }

        $lang = self::user_lang($user);
        $old = force_current_language($lang);
        try {
            $rendered = self::render($event, templates::normalise_lang($lang), $valuesfn());
            return self::deliver($user, $rendered);
        } catch (\Throwable $e) {
            debugging('local_nit_emails: ' . $event . ' email failed: ' . $e->getMessage(), DEBUG_NORMAL);
            return false;
        } finally {
            force_current_language($old);
        }
    }

    /**
     * Hand a rendered email to Moodle's mail system.
     *
     * @param \stdClass $user recipient
     * @param array{subject: string, html: string, text: string} $rendered
     * @return bool
     */
    private static function deliver(\stdClass $user, array $rendered): bool {
        return (bool) email_to_user(
            $user,
            \core_user::get_noreply_user(),
            $rendered['subject'],
            $rendered['text'],
            $rendered['html']
        );
    }

    /**
     * Is there a real, reachable mailbox behind this user?
     *
     * @param \stdClass $user
     * @return bool
     */
    private static function can_email(\stdClass $user): bool {
        return !empty($user->id)
            && !empty($user->email)
            && empty($user->deleted)
            && empty($user->suspended)
            && !isguestuser($user);
    }

    /**
     * The language this user's email should be written in: their own, or the
     * site default when they have never chosen one.
     *
     * @param \stdClass $user
     * @return string a Moodle language code
     */
    private static function user_lang(\stdClass $user): string {
        global $CFG;
        $lang = trim((string) ($user->lang ?? ''));
        return $lang !== '' ? $lang : (string) $CFG->lang;
    }

    /**
     * Wrap an authored body fragment in the branded email shell: a coloured
     * header band carrying the site name, the body on a white card, and a
     * footer with the site address. Arabic is laid out right-to-left.
     *
     * Everything is inline-styled and table-based because mail clients strip
     * <style> and do not lay out with flex/grid.
     *
     * @param string $body the filled body fragment
     * @param string $lang 'en' | 'ar'
     * @param string $title used as the document title / preheader
     * @return string complete HTML document
     */
    private static function wrap(string $body, string $lang, string $title): string {
        global $CFG;

        $rtl = ($lang === 'ar');
        $dir = $rtl ? 'rtl' : 'ltr';
        $align = $rtl ? 'right' : 'left';
        $brand = self::brand_colour();
        $sitename = format_string(get_site()->fullname);
        $font = $rtl
            ? "'Segoe UI', Tahoma, 'Noto Naskh Arabic', Arial, sans-serif"
            : "'Segoe UI', Helvetica, Arial, sans-serif";

        // The templates mark their call to action with class="nit-btn"; mail
        // clients drop stylesheets, so the class is swapped for real inline
        // styles at send time. Admins keep writing plain links.
        $button = 'display:inline-block;background:' . $brand . ';color:#ffffff;text-decoration:none;'
            . 'padding:12px 26px;border-radius:6px;font-weight:bold;';
        $body = str_replace(
            ['class="nit-btn"', "class='nit-btn'"],
            ['style="' . $button . '"', 'style="' . $button . '"'],
            $body
        );

        $logo = self::logo_url();
        $header = $logo
            ? '<img src="' . s($logo) . '" alt="' . s($sitename) . '" height="40"'
                . ' style="height:40px;max-width:220px;display:block;border:0;">'
            : '<span style="color:#ffffff;font-size:20px;font-weight:bold;">' . $sitename . '</span>';

        return '<!DOCTYPE html>'
            . '<html dir="' . $dir . '" lang="' . $lang . '"><head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . s($title) . '</title>'
            . '</head>'
            . '<body style="margin:0;padding:0;background:#f4f6fa;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"'
            . ' style="background:#f4f6fa;padding:24px 12px;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="640" cellpadding="0" cellspacing="0"'
            . ' style="width:100%;max-width:640px;background:#ffffff;border-radius:10px;overflow:hidden;'
            . 'border:1px solid #e3e8ef;">'

            // Header band.
            . '<tr><td style="background:' . $brand . ';padding:20px 28px;" align="' . $align . '">'
            . $header . '</td></tr>'

            // Body.
            . '<tr><td dir="' . $dir . '" align="' . $align . '"'
            . ' style="padding:28px;font-family:' . $font . ';font-size:15px;line-height:1.7;'
            . 'color:#1f2b3a;text-align:' . $align . ';direction:' . $dir . ';">'
            . $body
            . '</td></tr>'

            // Footer.
            . '<tr><td dir="' . $dir . '" align="' . $align . '"'
            . ' style="padding:18px 28px;background:#f7f9fc;border-top:1px solid #e3e8ef;'
            . 'font-family:' . $font . ';font-size:12px;line-height:1.6;color:#5b6b7f;'
            . 'text-align:' . $align . ';direction:' . $dir . ';">'
            . '<div>' . $sitename . ' — <a href="' . s($CFG->wwwroot) . '"'
            . ' style="color:' . $brand . ';text-decoration:none;">' . s($CFG->wwwroot) . '</a></div>'
            . '<div>' . get_string('footer_automated', 'local_nit_emails') . '</div>'
            . '</td></tr>'

            . '</table></td></tr></table></body></html>';
    }

    /**
     * The theme's brand primary, so the email matches the site.
     *
     * @return string a #rrggbb colour
     */
    private static function brand_colour(): string {
        $colour = get_config('theme_nit', 'colour_primary');
        return (is_string($colour) && preg_match('/^#[0-9a-f]{3,8}$/i', $colour)) ? $colour : '#5488c4';
    }

    /**
     * The site logo, when one is configured and reachable from a mail client.
     *
     * @return string|null absolute URL or null
     */
    private static function logo_url(): ?string {
        global $OUTPUT;
        try {
            $url = $OUTPUT->get_logo_url(null, 80);
            return $url ? $url->out(false) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
