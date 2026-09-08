<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

class price_resolver {

    /**
     * Whether a course has any active pricing rule configured at all.
     * Used to decide whether to apply the payment gate — a course with
     * no active pricing is treated as free/open.
     */
    public static function has_pricing(int $courseid): bool {
        global $DB;
        return $DB->record_exists('local_payments_course_prices', [
            'courseid' => $courseid,
            'is_active' => 1,
        ]);
    }

    /**
     * The two price rows a course that sells must have, and whether it has them.
     *
     * A priced course needs a MINIMUM of two rows, and they are not two of the
     * same kind:
     *
     *   1. The home country's row — Egypt, unless the admin set another
     *      "default country" — in local money. Egyptian buyers are the site's
     *      largest audience and its payment providers charge them in EGP; without
     *      this row they fall through to the row below and are quoted dollars.
     *   2. The Default price row (country "*"), in international money. This is
     *      the row EVERY buyer the site cannot place lands on — a visitor from a
     *      country nobody priced for, and any guest whose IP cannot be geolocated.
     *      Priced in EGP it quotes an Egyptian amount to the whole world.
     *
     * One row alone always fails one of those audiences, and which one it fails
     * is invisible from the admin screen — the price simply looks right to whoever
     * is looking at it. So the gaps are named on the pricing page instead of being
     * left to be discovered by a buyer.
     *
     * Reported, not enforced: rows can only be added one at a time, so a course
     * is *necessarily* incomplete between the first save and the second, and a
     * hard block would make the second row impossible to reach. A course with no
     * rows at all is free and complete — the requirement begins with the decision
     * to sell.
     *
     * @param int $courseid
     * @param string $homecurrency ISO 4217 the home country should be priced in
     * @return array{selling:bool, homecountry:string, homecurrency:string,
     *         hashome:bool, hasdefault:bool, defaultcurrency:string,
     *         defaultislocal:bool, complete:bool}
     */
    public static function pricing_gaps(int $courseid, string $homecurrency = 'EGP'): array {
        global $DB;

        return self::gaps_for_rows(
            $DB->get_records('local_payments_course_prices', ['courseid' => $courseid]),
            $homecurrency
        );
    }

    /**
     * The same verdict, asked about a set of rows rather than about a course.
     *
     * Split out so a form can ask "what would this course look like AFTER the save
     * I am about to make?" and refuse a save that breaks it. Checking the database
     * before writing to it would answer the wrong question — it describes the
     * course as it is, not as the admin is about to leave it.
     *
     * @param array $rows rows of local_payments_course_prices (active and not)
     * @param string $homecurrency ISO 4217 the home country should be priced in
     * @return array same shape as {@see self::pricing_gaps()}
     */
    public static function gaps_for_rows(array $rows, string $homecurrency = 'EGP'): array {
        $home = country_detector::fallback_country();
        $homecurrency = strtoupper($homecurrency);

        $active = array_filter($rows, function ($row) {
            return (int) $row->is_active === 1;
        });

        $hashome = false;
        $default = null;
        foreach ($active as $row) {
            if ((string) $row->country === $home) {
                $hashome = true;
            }
            if ((int) $row->is_default === 1) {
                $default = $row;
            }
        }

        $defaultcurrency = $default ? strtoupper((string) $default->currency) : '';

        return [
            // No active rows at all = a free course. Nothing is missing from a
            // course that is not for sale.
            'selling' => !empty($active),
            'homecountry' => $home,
            'homecurrency' => $homecurrency,
            'hashome' => $hashome,
            'hasdefault' => (bool) $default,
            'defaultcurrency' => $defaultcurrency,
            // The Default row is the one the rest of the world sees. Priced in the
            // home currency it is quoting a local amount to everybody abroad.
            'defaultislocal' => $default && $defaultcurrency === $homecurrency,
            'complete' => empty($active)
                || ($hashome && $default && $defaultcurrency !== $homecurrency),
        ];
    }

    /**
     * Would this set of rows break a course that is currently priced correctly?
     *
     * The rule the pricing screen enforces, in one place so the form and the delete
     * handler cannot apply it differently:
     *
     *   a complete course may never be made incomplete,
     *   an incomplete one may always be changed.
     *
     * The second half matters as much as the first. Courses priced before this rule
     * existed have a single row, and a flat "must be complete to save" would trap
     * them: every edit that fixes them starts from an incomplete course. Making the
     * test about the DIRECTION of the change is what lets the rule be enforced
     * without freezing the courses it was written for.
     *
     * @param int $courseid
     * @param array $after the rows the course would have after the save
     * @return bool true = refuse this save
     */
    public static function would_break_pricing(int $courseid, array $after): bool {
        $before = self::pricing_gaps($courseid);
        if (!$before['complete']) {
            return false;
        }
        $result = self::gaps_for_rows($after);
        return $result['selling'] && !$result['complete'];
    }

    /**
     * Resolve the price for a course based on user's detected country.
     *
     * @param int $courseid
     * @param int|null $userid
     * @param string|null $app_country Country from Flutter app.
     * @return object {price_id, price, sale_price, original_price, currency, country, discount_pct, is_sale_active}
     * @throws country_required_exception if the buyer is signed in with no profile country.
     * @throws \moodle_exception if no pricing found.
     */
    public static function resolve(int $courseid, ?int $userid = null, ?string $app_country = null): object {
        global $DB, $USER;

        $userid = $userid ?? $USER->id;

        // A signed-in account with no profile country has no price — not the default one, not
        // an IP-guessed one. Refusing here (rather than at each of the dozen display surfaces)
        // is what makes the rule leak-proof: nothing can print an amount it never received.
        if (country_detector::pricing_blocked($userid)) {
            throw new country_required_exception("Course {$courseid}: user {$userid} has no profile country");
        }

        // May be '' when the country genuinely cannot be determined (a guest whose IP lookup
        // failed and who sent no app hint). That case must land on the course's Default price
        // row, never on the admin default country's row.
        $country = country_detector::detect_for_pricing($userid, $app_country);

        // Country-specific active price wins; otherwise the course's default active price.
        $price_record = null;
        if ($country !== '') {
            $price_record = $DB->get_record_select(
                'local_payments_course_prices',
                'courseid = :courseid AND country = :country AND is_active = 1',
                ['courseid' => $courseid, 'country' => $country],
                '*',
                IGNORE_MULTIPLE
            );
        }

        if (!$price_record) {
            $price_record = $DB->get_record_select(
                'local_payments_course_prices',
                'courseid = :courseid AND is_default = 1 AND is_active = 1',
                ['courseid' => $courseid],
                '*',
                IGNORE_MULTIPLE
            );
        }

        if (!$price_record) {
            throw new \moodle_exception('nopricefound', 'local_payments', '', null,
                "No pricing rule found for course {$courseid}, country {$country}");
        }

        // Sale/date/priority were removed — the stored price is the final price.
        $price = (float) $price_record->price;

        return (object) [
            'price_id' => (int) $price_record->id,
            'price' => $price,
            'sale_price' => null,
            'original_price' => $price,
            'currency' => $price_record->currency,
            // Consumers such as manager::get_provider() and the transaction row need a real
            // code, so an unknown country is reported as the admin default here — that only
            // affects provider routing/reporting, never which price was chosen above.
            'country' => $country !== '' ? $country : country_detector::fallback_country(),
            'discount_pct' => 0,
            'is_sale_active' => false,
            'sale_ends_at' => 0,
        ];
    }

    /**
     * The currency this visitor is quoted in, under the same country rules prices use.
     *
     * Prices carry the currency, not the country: the visitor's currency is simply the one
     * on the active price rows that would be picked for them. Course prices answer first,
     * subscription prices second (a site may sell only plans), and the admin default
     * currency last. The answer is a property of the visitor rather than of any one item,
     * which is why it takes no course id — callers with no item at all (the coupon
     * catalogue, for one) still need to know which currency the visitor thinks in.
     *
     * @param int|null $userid defaults to the current user
     * @param string|null $app_country country hint from the mobile app
     * @return string ISO 4217 (uppercase), or '' when the site names no currency anywhere
     */
    public static function visitor_currency(?int $userid = null, ?string $app_country = null): string {
        global $DB, $USER;

        $userid = $userid ?? $USER->id;

        static $cache = [];
        $key = $userid . '|' . (string) $app_country;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $country = country_detector::detect_for_pricing($userid, $app_country);
        $currency = '';

        if ($country !== '') {
            $currency = (string) $DB->get_field_select(
                'local_payments_course_prices',
                'currency',
                'country = :country AND is_active = 1',
                ['country' => $country],
                IGNORE_MULTIPLE
            );

            if ($currency === '' && $DB->get_manager()->table_exists('nit_sub_price')) {
                $currency = (string) $DB->get_field_select(
                    'nit_sub_price',
                    'currency',
                    'country = :country',
                    ['country' => $country],
                    IGNORE_MULTIPLE
                );
            }
        }

        // No country, or a country nobody priced for: the visitor lands on the default
        // rows, so their currency is the one those rows are written in.
        if ($currency === '') {
            $currency = self::default_currency();
        }

        $cache[$key] = strtoupper(trim($currency));

        return $cache[$key];
    }

    /**
     * The currency the site prices in when no country of its own applies.
     *
     * The default price rows answer before the admin setting on purpose: the setting ships
     * with a value nobody chose (USD), so a site that prices every default row in EGP and
     * never opened the payments settings page would otherwise be described as a dollar
     * site — and anything comparing currencies (which coupons a visitor can spend, say)
     * would conclude that nothing matches anything.
     *
     * @return string ISO 4217 (uppercase), or '' when the site names none at all
     */
    public static function default_currency(): string {
        global $DB;

        static $currency = null;
        if ($currency !== null) {
            return $currency;
        }

        $fromrows = (string) $DB->get_field_select(
            'local_payments_course_prices',
            'currency',
            'is_default = 1 AND is_active = 1',
            [],
            IGNORE_MULTIPLE
        );

        if ($fromrows === '' && $DB->get_manager()->table_exists('nit_subscription')) {
            $fromrows = (string) $DB->get_field_select('nit_subscription', 'currency', 'status = :status',
                ['status' => 'active'], IGNORE_MULTIPLE);
        }

        if ($fromrows === '') {
            $fromrows = (string) get_config('local_payments', 'default_currency');
        }

        $currency = strtoupper(trim($fromrows));

        return $currency;
    }

    /**
     * Is this viewer barred from seeing prices because their profile has no country?
     *
     * Thin passthrough so display code (templates, category cards, the theme) can ask the
     * question without reaching for the detector, and there is still exactly one answer.
     *
     * @param int|null $userid defaults to the current user
     * @return bool
     */
    public static function country_required(?int $userid = null): bool {
        return country_detector::pricing_blocked($userid);
    }

    /**
     * Template context for the "set your country" notice: message, button label, target URL.
     *
     * @return array{country_message: string, country_short: string, country_action: string, country_url: string}
     */
    public static function country_notice(): array {
        $notice = country_detector::country_required_notice();

        return [
            'country_message' => $notice['message'],
            'country_short' => $notice['short'],
            'country_action' => $notice['action'],
            'country_url' => $notice['url'],
        ];
    }

    /**
     * Check if a user has already purchased a course.
     */
    public static function is_purchased(int $courseid, int $userid): bool {
        global $DB;
        return $DB->record_exists('local_payments_transactions', [
            'courseid' => $courseid,
            'userid' => $userid,
            'status' => status_machine::COMPLETED,
        ]);
    }

    /**
     * Whether an active subscription of the user's covers this course (grants access but
     * hasn't created real Moodle enrolment yet — see buy.php's action=enroll handler).
     */
    public static function is_covered_by_active_subscription(int $courseid, int $userid): bool {
        if ($userid <= 0
            || !class_exists('\local_nit_subscriptions\subscription_purchase_manager')
            || !class_exists('\local_nit_subscriptions\subscription_manager')) {
            return false;
        }
        $activesub = \local_nit_subscriptions\subscription_purchase_manager::get_active_subscription($userid);
        if (!$activesub) {
            return false;
        }
        $covered_courses = \local_nit_subscriptions\subscription_manager::courses_for_subscription($activesub->subscriptionid);
        return in_array($courseid, $covered_courses);
    }

    /**
     * Build the context array for rendering the local_payments/course_card_price
     * template for a given course, from any course-listing page (catalog grids,
     * category pages, etc).
     */
    public static function card_context(int $courseid, ?int $userid = null): array {
        global $USER;

        $userid = $userid ?? (int) ($USER->id ?? 0);
        $is_enrolled = $userid > 0 ? enrollment_handler::is_enrolled($userid, $courseid) : false;
        $course_url = (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false);

        // Subscription coverage grants access but doesn't create real Moodle enrolment until
        // the student explicitly clicks "Enroll" (see buy.php's action=enroll handler) — so it
        // must not be treated as already enrolled here.
        $can_enroll_via_sub = !$is_enrolled && self::is_covered_by_active_subscription($courseid, $userid);

        if ($can_enroll_via_sub) {
            return [
                'is_enrolled' => false,
                'is_free' => false,
                'is_purchased' => false,
                'can_enroll_via_sub' => true,
                'course_url' => $course_url,
                'enroll_url' => (new \moodle_url('/local/payments/buy.php',
                    ['courseid' => $courseid, 'action' => 'enroll', 'sesskey' => sesskey()]))->out(false),
            ];
        }

        // The course keeps showing in "My courses" after a subscription/package lapses (the
        // enrolment record survives but is expired). Flag that so the card can invite the
        // student to renew instead of silently looking like a fresh, never-enrolled course.
        $can_renew = !$is_enrolled && $userid > 0
            && enrollment_handler::has_expired_enrolment($userid, $courseid);

        if ($is_enrolled || !self::has_pricing($courseid)) {
            return [
                'is_enrolled' => $is_enrolled,
                'is_free' => !self::has_pricing($courseid),
                'is_purchased' => false,
                'can_renew' => $can_renew,
                'course_url' => $course_url,
            ];
        }

        $buy_url = (new \moodle_url('/local/payments/buy.php', ['courseid' => $courseid]))->out(false);
        $is_purchased = $userid > 0 ? self::is_purchased($courseid, $userid) : false;

        if ($is_purchased) {
            return [
                'is_enrolled' => false,
                'is_free' => false,
                'is_purchased' => true,
                'can_renew' => $can_renew,
                'course_url' => $course_url,
                'buy_url' => $buy_url,
            ];
        }

        try {
            $pricing = self::resolve($courseid, $userid > 0 ? $userid : null);
        } catch (country_required_exception $e) {
            // Signed in with no profile country: the card shows the "set your country" notice
            // where the price and the Buy button would be. Caught BEFORE the generic handler
            // below — falling into that one would label a paid course "Free".
            return [
                'is_enrolled' => false,
                'is_free' => false,
                'is_purchased' => false,
                'can_renew' => $can_renew,
                'course_url' => $course_url,
                'country_required' => true,
            ] + self::country_notice();
        } catch (\moodle_exception $e) {
            return [
                'is_enrolled' => false,
                'is_free' => true,
                'is_purchased' => false,
                'can_renew' => $can_renew,
                'course_url' => $course_url,
            ];
        }

        return [
            'is_enrolled' => false,
            'is_free' => false,
            'is_purchased' => false,
            'can_renew' => $can_renew,
            'price' => number_format((float) $pricing->price, 2),
            'sale_price' => $pricing->sale_price !== null ? number_format((float) $pricing->sale_price, 2) : '',
            'original_price' => number_format((float) $pricing->original_price, 2),
            'currency' => $pricing->currency,
            'is_sale_active' => (bool) $pricing->is_sale_active,
            'discount_pct' => (int) $pricing->discount_pct,
            'buy_url' => $buy_url,
            'course_url' => $course_url,
        ];
    }

    /**
     * Everything a screen needs to offer one course to one viewer, in one array.
     *
     * The same question — "what does this person see where the price and the
     * button go?" — is asked by the category cards, the catalogue and the course
     * page's own hero. It used to be answered once per screen, and the answers
     * drifted: a course could be labelled "Free" on one page and carry a "Buy
     * now" button on another, because one screen fell back to any active rule
     * when the viewer's country matched nothing and the other did not.
     *
     * So it is answered here, once. Callers decide how it LOOKS; nothing else
     * decides what it SAYS.
     *
     * The keys are the ones local_nit_category's cards already used, because
     * those cards were the first screen to get every state right and the rest
     * are being brought into line with them rather than the other way round:
     *
     *   enrolled        already in the course — no offer of any kind
     *   purchased       paid but not enrolled yet — must not be asked to pay twice
     *   covered         an active subscription includes it — "Enroll", not "Buy"
     *   free            the course has no active pricing rule at all
     *   haspricing      it has one (the same test buy.php and enrol.php gate on)
     *   price/currency  the amount THIS viewer is quoted, 0.0 when none resolves
     *   offerlabel      e.g. "-40%" when local_nit_commerce has a live offer
     *   offerfinal      the discounted amount that label refers to
     *   countryrequired signed in with no profile country: no price, no purchase
     *
     * Guest vs signed in: resolve() is country-aware and keyed on the viewer, so
     * the user id goes through in BOTH states. A signed-in account is priced on
     * its profile country and nothing else — with that field empty there is no
     * price and no Buy button, only 'countryrequired'. A guest (id 0) is priced
     * by IP geolocation, and when that yields nothing the course's Default price
     * row is used; see local_payments\country_detector::detect_for_pricing().
     *
     * @param int $courseid
     * @param int|null $userid defaults to the current user
     * @return array{enrolled:bool, purchased:bool, covered:bool, free:bool, haspricing:bool,
     *         price:float, currency:string, offerlabel:string, offerfinal:float,
     *         countryrequired:bool}
     */
    public static function course_state(int $courseid, ?int $userid = null): array {
        global $USER, $DB;

        $uid = $userid ?? (int) ($USER->id ?? 0);

        $out = [
            'enrolled' => false, 'purchased' => false, 'covered' => false, 'free' => true,
            'haspricing' => false, 'price' => 0.0, 'currency' => '',
            'offerlabel' => '', 'offerfinal' => 0.0, 'countryrequired' => false,
        ];

        $ctx = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$ctx) {
            return $out;
        }
        $out['enrolled'] = $uid > 0 && is_enrolled($ctx, $uid, '', true);

        // "Paid" means local_payments has an active rule — the same test enrol.php
        // and buy.php gate on, so no screen can offer a flow the server refuses.
        $out['haspricing'] = self::has_pricing($courseid);
        $out['free'] = !$out['haspricing'];

        if (!$out['haspricing']) {
            return $out;
        }

        // An enrolled viewer skips the purchase/coverage probes — the "Enrolled"
        // badge already wins over both — but NOT the price resolution below: a
        // paid course shows what it costs in every state.
        if (!$out['enrolled']) {
            $out['purchased'] = $uid > 0 && self::is_purchased($courseid, $uid);
            if (!$out['purchased'] && class_exists('\local_nit_subscriptions\subscription_purchase_manager')) {
                $out['covered'] = self::is_covered_by_active_subscription($courseid, $uid);
            }
        }

        try {
            $pricing = self::resolve($courseid, $uid);
            $out['price'] = (float) $pricing->price;
            $out['currency'] = (string) $pricing->currency;
        } catch (country_required_exception $e) {
            // Signed in with no profile country — no price exists for this viewer.
            // Caught before the generic handler on purpose: that one reaches past
            // the resolver for "any active rule", which is exactly the borrowed
            // price this rule forbids.
            $out['countryrequired'] = true;
            return $out;
        } catch (\Throwable $e) {
            // resolve() throws when nothing matches the viewer's country AND the
            // course has no default rule — a per-viewer miss, not a free course.
            // Fall back to any active rule so the screen still shows a real price
            // instead of claiming the course is free.
            $fallback = $DB->get_record_select(
                'local_payments_course_prices',
                'courseid = :courseid AND is_active = 1',
                ['courseid' => $courseid],
                'price, currency',
                IGNORE_MULTIPLE
            );
            if ($fallback) {
                $out['price'] = (float) $fallback->price;
                $out['currency'] = (string) $fallback->currency;
            }
        }

        if ($out['price'] > 0 && class_exists('\local_nit_commerce\discount_manager')) {
            try {
                $summary = \local_nit_commerce\discount_manager::offer_summary('course', $courseid, $out['price']);
                if ($summary) {
                    $out['offerlabel'] = $summary['label'];   // e.g. "-40%".
                    $out['offerfinal'] = (float) $summary['final'];
                }
            } catch (\Throwable $e) {
                // No offer engine / bad offer data — show the undiscounted price.
                $out['offerlabel'] = '';
                $out['offerfinal'] = 0.0;
            }
        }

        return $out;
    }
}
