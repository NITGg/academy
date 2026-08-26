<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

class country_detector {

    /**
     * Resolve the country a buyer's price should be keyed on, or '' when it genuinely
     * cannot be determined.
     *
     * Pricing is ALWAYS by country. The chain, in order, is:
     *
     *   1. LOGGED-IN user's profile country — the built-in field the academy manages.
     *      Stable, and it cannot whiplash between visits.
     *   2. IP geolocation — used for guests (no profile at all) AND for a logged-in user
     *      whose profile country is empty, so an incomplete profile still gets a localised
     *      price instead of silently landing on someone else's country.
     *   3. Country hint sent by the Flutter app.
     *   4. '' — country unknown. The caller must then fall back to the *default price*
     *      row/base price, NOT to some other country's price.
     *
     * Returning '' at step 4 is the whole point: an unknown country must never be silently
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
        global $USER, $CFG;

        $userid = $userid ?? $USER->id;

        // Anonymous (id 0) or the site guest account = guest for pricing purposes.
        $isguest = ($userid <= 0)
            || (!empty($CFG->siteguest) && (int) $userid === (int) $CFG->siteguest);

        // 1. Profile country (logged-in only).
        if (!$isguest) {
            $profile_country = self::from_profile($userid);
            if ($profile_country !== '') {
                return $profile_country;
            }
        }

        // 2. IP geolocation. Covers guests, and logged-in users with no profile country.
        // Yields '' when geolocation is unconfigured, the IP is private, or the lookup fails.
        $ip_country = self::from_ip($ip ?? getremoteaddr());
        if ($ip_country !== '') {
            return $ip_country;
        }

        // 3. Country provided by the Flutter app.
        if (!empty($app_country) && self::is_valid_country($app_country)) {
            return strtoupper($app_country);
        }

        // 4. Unknown — the caller applies the default price.
        return '';
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

    private static function from_profile(int $userid): string {
        global $DB;
        $country = $DB->get_field('user', 'country', ['id' => $userid]);
        if (!empty($country) && self::is_valid_country($country)) {
            return strtoupper($country);
        }
        return '';
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
