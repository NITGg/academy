<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

class country_detector {

    /**
     * Detect user's country for pricing.
     *
     * Pricing follows the buyer's Moodle profile country: it is the built-in field the
     * academy uses to decide which per-country price applies, and it is what admins and
     * users see and manage. IP geolocation is deliberately NOT consulted — a user's stated
     * country drives their price, and if no price is configured for that country the
     * resolvers fall back to the item's default price.
     *
     * Priority: 1. User profile → 2. Flutter app header → 3. Admin default → 4. 'EG'
     *
     * (The $ip argument is retained for backwards compatibility with existing callers but
     * is no longer used.)
     */
    public static function detect(?int $userid = null, ?string $app_country = null, ?string $ip = null): string {
        global $USER;

        $userid = $userid ?? $USER->id;

        // 1. User profile country — the built-in field pricing is keyed on.
        $profile_country = self::from_profile($userid);
        if (!empty($profile_country)) {
            return $profile_country;
        }

        // 2. Country provided by the Flutter app (used only when the profile has none,
        // e.g. an app guest who has not set a profile country yet).
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
