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
     * Both hits and misses are cached: a miss otherwise re-runs the geo lookup (which can be
     * a remote HTTP call) for every course card on every catalogue page.
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
        $cache->set($key, $country);
        return $country;
    }

    /**
     * The actual geolocation call, normalised to an ISO 3166-1 alpha-2 code.
     *
     * Core's iplookup_find_location() hands back a *localised country name* ("Egypt", "Mexico"),
     * never a code — feeding that straight into a country-keyed price lookup can never match.
     * So read the ISO code from the GeoIP2 database directly, and only fall back to core (and
     * to mapping its name back to a code) for the other providers core supports.
     */
    private static function lookup_ip_country(string $ip): string {
        global $CFG;

        if (!empty($CFG->geoip2file) && file_exists($CFG->geoip2file)
                && class_exists('\GeoIp2\Database\Reader')) {
            try {
                $reader = new \GeoIp2\Database\Reader($CFG->geoip2file);
                $record = $reader->city($ip);
                $code = (string) ($record->country->isoCode ?? '');
                if (self::is_valid_country($code)) {
                    return strtoupper($code);
                }
            } catch (\Throwable $e) {
                // Unreadable database, or an IP the database holds no record for.
                debugging('local_payments: GeoIP2 lookup failed for ' . $ip . ': ' . $e->getMessage(),
                    DEBUG_DEVELOPER);
            }
            return '';
        }

        require_once($CFG->dirroot . '/iplookup/lib.php');
        try {
            $location = iplookup_find_location($ip);
        } catch (\Throwable $e) {
            debugging('local_payments: iplookup failed for ' . $ip . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }

        $name = trim((string) ($location['country'] ?? ''));
        if ($name === '') {
            return '';
        }
        if (self::is_valid_country($name)) {
            return strtoupper($name);
        }
        return self::code_from_country_name($name);
    }

    /**
     * Map a country name back to its ISO code, trying the current language first (that is the
     * language core localised the name into) and then English.
     */
    private static function code_from_country_name(string $name): string {
        $sm = get_string_manager();
        foreach ([null, 'en'] as $lang) {
            $map = array_flip($sm->get_list_of_countries(true, $lang));
            if (isset($map[$name])) {
                return strtoupper($map[$name]);
            }
        }
        return '';
    }

    /**
     * Only a routable public address can be geolocated. Loopback and LAN addresses (which is
     * what a misconfigured reverse proxy hands us) are "no usable IP", not a country.
     */
    private static function is_public_ip(string $ip): bool {
        return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private static function is_valid_country(string $code): bool {
        return preg_match('/^[A-Za-z]{2}$/', $code) === 1;
    }
}
