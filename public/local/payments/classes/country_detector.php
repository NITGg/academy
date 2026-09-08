<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

class country_detector {

    /**
     * Resolve the country a buyer's price should be keyed on, or '' when it genuinely
     * cannot be determined.
     *
     * Pricing is ALWAYS by country. The chain splits on whether anybody is signed in:
     *
     *   A SIGNED-IN account is priced on its **profile country and nothing else**. If that
     *   field is empty the account has no price at all — see {@see self::pricing_blocked()},
     *   which every price surface checks *before* calling this. It deliberately does not fall
     *   through to IP: an account we can ask is an account whose own answer is the only
     *   honest one, and a guessed country would let the same buyer be quoted two different
     *   prices from two different networks. This method returns '' for that account; the gate,
     *   not the '' , is what stops the price being shown.
     *
     *   A GUEST (nobody signed in) has no profile to read, so:
     *     1. IP geolocation — approximate, but it localises the shop window.
     *     2. Country hint sent by the Flutter app.
     *     3. '' — country unknown. The caller must then fall back to the *default price*
     *        row/base price, NOT to some other country's price.
     *
     * Returning '' at the end is the whole point: an unknown country must never be silently
     * rewritten to the admin "default country", because that country may well have a price
     * row of its own and the buyer would be shown a price meant for somebody else. Use
     * {@see self::detect()} where a non-empty code is structurally required (payment-provider
     * routing, stamping a country on a transaction).
     *
     * @param int|null $userid
     * @param string|null $app_country country hint from the mobile app
     * @param string|null $ip override the remote address (testing)
     * @return string ISO 3166-1 alpha-2 (uppercase), or '' when unknown
     */
    public static function detect_for_pricing(?int $userid = null, ?string $app_country = null, ?string $ip = null): string {
        global $USER;

        $userid = $userid ?? $USER->id;

        // Signed in: the profile country is the only source, and an empty one is a dead end
        // (pricing_blocked() is what the display/purchase surfaces act on). No IP guess.
        if (!self::is_guest($userid)) {
            return self::from_profile($userid);
        }

        // Guest — no profile to read. 1. IP geolocation. Yields '' when geolocation is
        // unconfigured, the IP is private, or the lookup fails.
        $ip_country = self::from_ip($ip ?? getremoteaddr());
        if ($ip_country !== '') {
            return $ip_country;
        }

        // 2. Country provided by the Flutter app.
        if (!empty($app_country) && self::is_valid_country($app_country)) {
            return strtoupper($app_country);
        }

        // 3. Unknown — the caller applies the default price.
        return '';
    }

    /**
     * Must this user be shown no price at all?
     *
     * True for a signed-in account whose profile country is empty. Such an account is not
     * priced by IP or by the admin default — it is not priced at all: every price surface
     * prints {@see self::country_required_notice()} instead of an amount, and every purchase
     * entry point refuses. The buyer sets their country once, on their profile, and the whole
     * shop starts working.
     *
     * Guests are NOT blocked: they have no profile to fill in, so they keep the IP →
     * default-price ladder in {@see self::detect_for_pricing()} and the shop window still
     * shows real prices to visitors who have not signed up yet.
     *
     * @param int|null $userid defaults to the current user
     * @return bool
     */
    public static function pricing_blocked(?int $userid = null): bool {
        global $USER;

        $userid = $userid ?? (int) $USER->id;

        return !self::is_guest($userid) && self::from_profile($userid) === '';
    }

    /**
     * What a blocked buyer is told, and where to send them to fix it.
     *
     * One place builds it so the course cards, the course page, the buy page, the
     * subscription block and the mobile web services all say the same thing.
     *
     * `url` prefers local_profilefields' sign-up completion page — a short form that asks only
     * what registration would still ask — but ONLY when that page would actually ask for the
     * country. If it would not (the gate is off, or country is not one of its required boxes)
     * it bounces a "complete" user straight back to the site home, which would leave the buyer
     * with nowhere to go; the profile editor always carries the country selector, so that is
     * the fallback.
     *
     * Memoised: a catalogue page resolves a card context per course, and each one asks for this
     * notice.
     *
     * @return array{message: string, short: string, action: string, url: string}
     */
    public static function country_required_notice(): array {
        global $USER, $PAGE;
        static $notice = null;

        if ($notice !== null) {
            return $notice;
        }

        $url = new \moodle_url('/user/edit.php', ['id' => (int) $USER->id]);
        if (class_exists('\local_profilefields\completion') && \local_profilefields\completion::enabled()) {
            $missing = \local_profilefields\completion::missing($USER);
            foreach ($missing['fields'] as $field) {
                if (($field['name'] ?? '') === 'country') {
                    $here = $PAGE && $PAGE->has_set_url() ? $PAGE->url->out(false) : '';
                    $url = \local_profilefields\completion::url($here);
                    break;
                }
            }
        }

        $notice = [
            'message' => get_string('countryrequired_desc', 'local_payments'),
            'short' => get_string('countryrequired', 'local_payments'),
            'action' => get_string('countryrequired_action', 'local_payments'),
            'url' => $url->out(false),
        ];

        return $notice;
    }

    /**
     * Anonymous (id 0) or the site guest account — nobody with a profile to price on.
     *
     * @param int $userid
     * @return bool
     */
    private static function is_guest(int $userid): bool {
        global $CFG;

        return ($userid <= 0)
            || (!empty($CFG->siteguest) && (int) $userid === (int) $CFG->siteguest);
    }

    /**
     * Detect a buyer's country, always returning a usable code.
     *
     * Same chain as {@see self::detect_for_pricing()}, but an unknown country falls back to
     * the admin "default country" setting (then 'EG'). Use this where a country code is
     * structurally required — routing to a payment provider, stamping a transaction row —
     * and NOT for choosing which price to show.
     *
     * @param int|null $userid
     * @param string|null $app_country
     * @param string|null $ip
     * @return string ISO 3166-1 alpha-2 (uppercase), never empty
     */
    public static function detect(?int $userid = null, ?string $app_country = null, ?string $ip = null): string {
        $country = self::detect_for_pricing($userid, $app_country, $ip);
        return $country !== '' ? $country : self::fallback_country();
    }

    /**
     * The money the site's own country pays in.
     *
     * Follows the "default country" setting rather than being hard-coded, so a site
     * selling from Riyadh gets SAR without an edit here. Anything unmapped falls back
     * to EGP — the currency this plugin was written for — because a course priced in
     * a currency nobody local uses is worse than one priced in the wrong local one.
     *
     * Lives here rather than on the pricing form because it is a fact about the SITE,
     * and both the form and the completeness rule need the same answer.
     *
     * @return string ISO 4217 (uppercase)
     */
    public static function home_currency(): string {
        $map = [
            'EG' => 'EGP', 'SA' => 'SAR', 'AE' => 'AED', 'KW' => 'KWD',
            'BH' => 'BHD', 'QA' => 'QAR', 'OM' => 'OMR', 'GB' => 'GBP', 'US' => 'USD',
        ];

        return $map[self::fallback_country()] ?? 'EGP';
    }

    /**
     * The admin-configured "default country", used only where a code is mandatory.
     *
     * @return string ISO 3166-1 alpha-2 (uppercase), never empty
     */
    public static function fallback_country(): string {
        $default = get_config('local_payments', 'default_country');
        if (!empty($default) && self::is_valid_country($default)) {
            return strtoupper($default);
        }
        return 'EG';
    }

    /**
     * The profile country, or '' when the account has none.
     *
     * Cached per request: a catalogue page asks this once per card (once to decide whether the
     * viewer is blocked, once more to price), and it is one column on one row that cannot
     * change mid-request.
     */
    private static function from_profile(int $userid): string {
        global $DB;
        static $cache = [];

        if (array_key_exists($userid, $cache)) {
            return $cache[$userid];
        }

        $country = $DB->get_field('user', 'country', ['id' => $userid]);
        $cache[$userid] = (!empty($country) && self::is_valid_country($country))
            ? strtoupper($country) : '';

        return $cache[$userid];
    }

    /**
     * Country code for an IP, or '' if it cannot be established.
     *
     * Hits are cached for the definition's 24 hours: a miss otherwise re-runs the geo
     * lookup (which can be a remote HTTP call) for every course card on every catalogue
     * page.
     *
     * A miss is cached too — with one exception. When the lookup failed because no
     * source could be REACHED, that is an outage of ours and not a fact about this
     * visitor; caching it would keep quoting them the default price for a day after
     * the service came back. "Nobody could place this address" is a settled answer and
     * is cached; "we could not ask anybody" is not.
     */
    private static function from_ip(string $ip): string {
        if (!self::is_public_ip($ip)) {
            return '';
        }

        // Hash the IP into an alphanumeric cache key: raw IPs contain dots (and
        // colons for IPv6), which are rejected as invalid "simple keys".
        $key = md5($ip);

        $cache = \cache::make('local_payments', 'country_detection');
        $cached = $cache->get($key);
        if ($cached !== false) {
            return (string) $cached;
        }

        $country = self::lookup_ip_country($ip);

        if ($country !== '' || !self::lookup_service_was_down()) {
            $cache->set($key, $country);
        }

        return $country;
    }

    /**
     * The actual geolocation call, normalised to an ISO 3166-1 alpha-2 code.
     *
     * Handed to profilefield_phone\dialcodes, which owns the site's ONE country ladder:
     * Moodle's configured GeoIP source first, then a free online HTTPS lookup that needs
     * no admin setup. That second rung is the whole reason this delegates rather than
     * asking Moodle directly.
     *
     * This method used to stop at the first rung. On a site whose $CFG->geoip2file points
     * at a database file that is not actually there — the state this site was in — that
     * meant every lookup returned '' and every guest was quoted the course's default
     * price, while the sign-up screen, which already used the full ladder, placed the
     * same visitor in Egypt correctly. One visitor, one address, two different answers,
     * because the shop and the sign-up form each had a lookup of their own.
     *
     * The online rung is opted into here (`true`). This runs behind the 24-hour cache in
     * from_ip(), so a listing page costs at most one lookup per address per day, and
     * getting the price right is worth an occasional one-second call — it is the same
     * trade the registration check already makes on every submit.
     *
     * @param string $ip a public IP address
     * @return string ISO 3166-1 alpha-2 (uppercase), or ''
     */
    private static function lookup_ip_country(string $ip): string {
        if (!class_exists('\profilefield_phone\dialcodes')) {
            // The phone profile field is not installed. Nothing else on this site
            // resolves an address, so there is no country and the default price is
            // the honest answer.
            return '';
        }

        try {
            $code = \profilefield_phone\dialcodes::country_for_ip($ip, true);
        } catch (\Throwable $e) {
            debugging('local_payments: country lookup failed for ' . $ip . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER);
            return '';
        }

        return self::is_valid_country($code) ? strtoupper($code) : '';
    }

    /**
     * Did the most recent lookup fail because no source could be reached?
     *
     * Only meaningful immediately after {@see self::lookup_ip_country()}. The caller
     * uses it to decide whether a miss is worth caching — see {@see self::from_ip()}.
     *
     * @return bool
     */
    private static function lookup_service_was_down(): bool {
        return class_exists('\profilefield_phone\dialcodes')
            && \profilefield_phone\dialcodes::service_was_down();
    }

    /**
     * Can this site place an IP address in a country at all?
     *
     * Every per-country price is unreachable for a signed-out visitor when the answer
     * is no: {@see self::detect_for_pricing()} returns '' for every guest, and every one
     * of them is quoted the course's Default price row whatever country they are in. From
     * the admin screen that is indistinguishable from a price rule that does not work,
     * which is why the pricing page asks this and says so.
     *
     * Answered about the WHOLE ladder, not just its first rung. A local GeoIP2 database
     * is the fast rung and the one worth configuring, but profilefield_phone's free
     * online lookup needs no setup at all — and a site running on that rung alone is
     * placing visitors perfectly well. Reporting it as "no geolocation" because no
     * .mmdb file is configured is how this plugin ended up warning about a working
     * site while quoting the wrong price on it, which is the opposite of useful.
     *
     * Only asks whether a lookup COULD run. Whether it will actually be handed the
     * visitor's own address is a separate question — a reverse proxy with
     * `$CFG->getremoteaddrconf` unset hides every visitor behind its own private
     * address — and that one is answered per request by country_diagnose.php.
     *
     * @return bool
     */
    public static function geolocation_available(): bool {
        return self::local_geolocation_available()
            || class_exists('\profilefield_phone\dialcodes');
    }

    /**
     * Is a LOCAL geolocation source configured — a database read rather than a network call?
     *
     * The difference is speed, not correctness: without one, every address that is not
     * already cached costs an external HTTPS request. Worth telling an administrator
     * about, which is why the diagnostics separate the two.
     *
     * @return bool
     */
    public static function local_geolocation_available(): bool {
        global $CFG;

        return (!empty($CFG->geoip2file) && file_exists($CFG->geoip2file))
            || !empty($CFG->geopluginapikey);
    }

    /**
     * Only a routable public address can be geolocated. Loopback and LAN addresses (which is
     * what a misconfigured reverse proxy hands us) are "no usable IP", not a country.
     *
     * Public so the diagnostic page can say WHICH of the two ways an address fails.
     */
    public static function is_public_ip(string $ip): bool {
        return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * Look one IP address up, ignoring the per-request cache.
     *
     * For the diagnostic page only: {@see self::from_ip()} caches misses for 24 hours,
     * which is right for a catalogue page and wrong for the screen an administrator opens
     * to find out whether they have just fixed geolocation.
     *
     * @param string $ip
     * @return string ISO 3166-1 alpha-2 (uppercase), or '' when it cannot be established
     */
    public static function lookup_uncached(string $ip): string {
        if (!self::is_public_ip($ip)) {
            return '';
        }
        return self::lookup_ip_country($ip);
    }

    private static function is_valid_country(string $code): bool {
        return preg_match('/^[A-Za-z]{2}$/', $code) === 1;
    }
}
