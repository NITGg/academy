<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

class country_detector {

    /**
     * Detect a buyer's country for pricing.
     *
     * The policy is hybrid, keyed on whether the buyer is logged in:
     *
     *  - LOGGED-IN user: their Moodle profile country drives pricing (the built-in field
     *    the academy manages). It is stable and cannot whiplash between visits.
     *  - GUEST (not logged in / guest account): there is no profile country, so the shop
     *    window is localised by IP geolocation — an anonymous visitor sees their own
     *    country's price. A guest cannot pay: buying redirects to login/registration, after
     *    which their profile country takes over, so the guest price is only a preview.
     *
     * Priority (logged-in):  1. Profile → 2. App header → 3. Admin default → 4. 'EG'
     * Priority (guest):      1. IP      → 2. App header → 3. Admin default → 4. 'EG'
     */
    public static function detect(?int $userid = null, ?string $app_country = null, ?string $ip = null): string {
        global $USER, $CFG;

        $userid = $userid ?? $USER->id;

        // Anonymous (id 0) or the site guest account = guest for pricing purposes.
        $isguest = ($userid <= 0)
            || (!empty($CFG->siteguest) && (int) $userid === (int) $CFG->siteguest);

        if ($isguest) {
            // 1. IP geolocation — localise the price for anonymous visitors.
            $ip_country = self::from_ip($ip ?? getremoteaddr());
            if (!empty($ip_country)) {
                return $ip_country;
            }
        } else {
            // 1. Logged-in user's profile country — the built-in field pricing is keyed on.
            $profile_country = self::from_profile($userid);
            if (!empty($profile_country)) {
                return $profile_country;
            }
        }

        // 2. Country provided by the Flutter app (fallback when the above yields nothing,
        // e.g. an app guest, or a logged-in user with no profile country yet).
        if (!empty($app_country) && self::is_valid_country($app_country)) {
            return strtoupper($app_country);
        }

        // 3. Admin default.
        $default = get_config('local_payments', 'default_country');
        if (!empty($default)) {
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

    private static function from_ip(string $ip): string {
        if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1') {
            return '';
        }

        // Hash the IP into an alphanumeric cache key: raw IPs contain dots (and
        // colons for IPv6), which are rejected as invalid "simple keys".
        $key = md5($ip);

        $cache = \cache::make('local_payments', 'country_detection');
        $cached = $cache->get($key);
        if ($cached !== false) {
            return $cached;
        }

        global $CFG;
        require_once($CFG->dirroot . '/iplookup/lib.php');

        $location = iplookup_find_location($ip);
        if (!empty($location['country'])) {
            $country = strtoupper($location['country']);
            $cache->set($key, $country);
            return $country;
        }

        return '';
    }

    private static function is_valid_country(string $code): bool {
        return preg_match('/^[A-Za-z]{2}$/', $code) === 1;
    }
}
