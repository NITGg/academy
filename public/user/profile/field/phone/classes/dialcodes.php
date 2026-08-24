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

namespace profilefield_phone;

defined('MOODLE_INTERNAL') || die();

/**
 * International dialling codes, keyed by ISO 3166-1 alpha-2 country code.
 *
 * Moodle ships localised country *names* (`get_string_manager()->get_list_of_countries()`)
 * but no dialling codes, so this is the missing half. The country names still come
 * from Moodle so they follow the current language; this class only adds the "+NN"
 * and the flag.
 *
 * @package    profilefield_phone
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dialcodes {

    /**
     * ISO alpha-2 => dialling code (without the leading "+").
     *
     * @var array<string,string>
     */
    const CODES = [
        'AF' => '93', 'AL' => '355', 'DZ' => '213', 'AD' => '376', 'AO' => '244',
        'AR' => '54', 'AM' => '374', 'AU' => '61', 'AT' => '43', 'AZ' => '994',
        'BH' => '973', 'BD' => '880', 'BY' => '375', 'BE' => '32', 'BZ' => '501',
        'BJ' => '229', 'BT' => '975', 'BO' => '591', 'BA' => '387', 'BW' => '267',
        'BR' => '55', 'BN' => '673', 'BG' => '359', 'BF' => '226', 'BI' => '257',
        'KH' => '855', 'CM' => '237', 'CA' => '1', 'CV' => '238', 'CF' => '236',
        'TD' => '235', 'CL' => '56', 'CN' => '86', 'CO' => '57', 'KM' => '269',
        'CG' => '242', 'CD' => '243', 'CR' => '506', 'CI' => '225', 'HR' => '385',
        'CU' => '53', 'CY' => '357', 'CZ' => '420', 'DK' => '45', 'DJ' => '253',
        'DO' => '1', 'EC' => '593', 'EG' => '20', 'SV' => '503', 'GQ' => '240',
        'ER' => '291', 'EE' => '372', 'ET' => '251', 'FJ' => '679', 'FI' => '358',
        'FR' => '33', 'GA' => '241', 'GM' => '220', 'GE' => '995', 'DE' => '49',
        'GH' => '233', 'GR' => '30', 'GT' => '502', 'GN' => '224', 'GW' => '245',
        'GY' => '592', 'HT' => '509', 'HN' => '504', 'HK' => '852', 'HU' => '36',
        'IS' => '354', 'IN' => '91', 'ID' => '62', 'IR' => '98', 'IQ' => '964',
        'IE' => '353', 'IL' => '972', 'IT' => '39', 'JM' => '1', 'JP' => '81',
        'JO' => '962', 'KZ' => '7', 'KE' => '254', 'KI' => '686', 'KW' => '965',
        'KG' => '996', 'LA' => '856', 'LV' => '371', 'LB' => '961', 'LS' => '266',
        'LR' => '231', 'LY' => '218', 'LI' => '423', 'LT' => '370', 'LU' => '352',
        'MO' => '853', 'MG' => '261', 'MW' => '265', 'MY' => '60', 'MV' => '960',
        'ML' => '223', 'MT' => '356', 'MH' => '692', 'MR' => '222', 'MU' => '230',
        'MX' => '52', 'FM' => '691', 'MD' => '373', 'MC' => '377', 'MN' => '976',
        'ME' => '382', 'MA' => '212', 'MZ' => '258', 'MM' => '95', 'NA' => '264',
        'NR' => '674', 'NP' => '977', 'NL' => '31', 'NZ' => '64', 'NI' => '505',
        'NE' => '227', 'NG' => '234', 'KP' => '850', 'MK' => '389', 'NO' => '47',
        'OM' => '968', 'PK' => '92', 'PW' => '680', 'PS' => '970', 'PA' => '507',
        'PG' => '675', 'PY' => '595', 'PE' => '51', 'PH' => '63', 'PL' => '48',
        'PT' => '351', 'QA' => '974', 'RO' => '40', 'RU' => '7', 'RW' => '250',
        'KN' => '1', 'LC' => '1', 'VC' => '1', 'WS' => '685', 'SM' => '378',
        'ST' => '239', 'SA' => '966', 'SN' => '221', 'RS' => '381', 'SC' => '248',
        'SL' => '232', 'SG' => '65', 'SK' => '421', 'SI' => '386', 'SB' => '677',
        'SO' => '252', 'ZA' => '27', 'KR' => '82', 'SS' => '211', 'ES' => '34',
        'LK' => '94', 'SD' => '249', 'SR' => '597', 'SE' => '46', 'CH' => '41',
        'SY' => '963', 'TW' => '886', 'TJ' => '992', 'TZ' => '255', 'TH' => '66',
        'TL' => '670', 'TG' => '228', 'TO' => '676', 'TT' => '1', 'TN' => '216',
        'TR' => '90', 'TM' => '993', 'TV' => '688', 'UG' => '256', 'UA' => '380',
        'AE' => '971', 'GB' => '44', 'US' => '1', 'UY' => '598', 'UZ' => '998',
        'VU' => '678', 'VA' => '379', 'VE' => '58', 'VN' => '84', 'YE' => '967',
        'ZM' => '260', 'ZW' => '263',
    ];

    /**
     * The dialling code for a country, without the leading "+".
     *
     * @param string $iso ISO alpha-2 country code
     * @return string dialling code, or '' when unknown
     */
    public static function code(string $iso): string {
        return self::CODES[strtoupper($iso)] ?? '';
    }

    /**
     * The flag emoji for a country.
     *
     * Built from the two regional-indicator code points, so no image assets are
     * needed. Renders as a flag on most mobile and mac/linux browsers; Windows
     * desktop shows the two-letter code instead, which is still meaningful.
     *
     * @param string $iso ISO alpha-2 country code
     * @return string the flag emoji, or '' when the code is malformed
     */
    public static function flag(string $iso): string {
        $iso = strtoupper($iso);
        if (!preg_match('/^[A-Z]{2}$/', $iso)) {
            return '';
        }
        $a = 0x1F1E6 + (ord($iso[0]) - ord('A'));
        $b = 0x1F1E6 + (ord($iso[1]) - ord('A'));
        return self::utf8($a) . self::utf8($b);
    }

    /**
     * A single UTF-8 character from a Unicode code point.
     *
     * `mb_chr()` is not guaranteed to be present and `core_text` has no
     * code-point-to-character helper, so the flag emoji is encoded here.
     *
     * @param int $cp Unicode code point
     * @return string the UTF-8 encoded character
     */
    protected static function utf8(int $cp): string {
        if ($cp <= 0x7F) {
            return chr($cp);
        } else if ($cp <= 0x7FF) {
            return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
        } else if ($cp <= 0xFFFF) {
            return chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
        }
        return chr(0xF0 | ($cp >> 18)) . chr(0x80 | (($cp >> 12) & 0x3F))
            . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
    }

    /**
     * The list shown in the country select: ISO => "🇪🇬 Egypt (+20)".
     *
     * Countries with a known dialling code are listed, sorted by the localised
     * name so the order follows the interface language.
     *
     * @return array<string,string>
     */
    public static function menu(): array {
        $names = get_string_manager()->get_list_of_countries(true);

        $menu = [];
        foreach (self::CODES as $iso => $dial) {
            if (!isset($names[$iso])) {
                continue;
            }
            $flag = self::flag($iso);
            $menu[$iso] = trim($flag . ' ' . $names[$iso] . ' (+' . $dial . ')');
        }

        \core_collator::asort($menu);

        return $menu;
    }

    /**
     * The country to preselect for a brand-new entry.
     *
     * Tries the visitor's IP first (needs a GeoIP database or geoPlugin key to be
     * configured in Site administration > Location > IP address lookup); falls back
     * to the site-configured default, then to Egypt.
     *
     * @param string $fallback ISO alpha-2 to use when nothing else is known
     * @return string ISO alpha-2 country code
     */
    public static function default_country(string $fallback = 'EG'): string {
        global $CFG;

        $iso = self::country_from_ip();
        if ($iso !== '') {
            return $iso;
        }

        if (!empty($CFG->country) && isset(self::CODES[strtoupper($CFG->country)])) {
            return strtoupper($CFG->country);
        }

        return isset(self::CODES[strtoupper($fallback)]) ? strtoupper($fallback) : 'EG';
    }

    /**
     * The ISO country code for the current visitor's IP address, if resolvable.
     *
     * Prefers Moodle's own GeoIP lookup when a database or key is configured; when
     * none is, it falls back to a free, no-setup online lookup so the feature works
     * out of the box. Private/reserved addresses (localhost, LAN) and any failure
     * resolve to '' so callers can skip rather than block.
     *
     * @param bool $allowonline whether to fall back to an external lookup when no
     *        local GeoIP source resolves the address. Off by default: the caller
     *        that runs on every page (the country preselect) must stay local and
     *        fast; only the opt-in IP-match check turns it on.
     * @return string ISO alpha-2 country code, or ''
     */
    public static function country_from_ip(bool $allowonline = false): string {
        global $CFG;

        $ip = getremoteaddr();
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            // No usable public address (e.g. localhost or a LAN client behind no proxy).
            return '';
        }

        // 1) Moodle's configured GeoIP source, if any.
        if (!empty($CFG->geoip2file) || !empty($CFG->geopluginapikey)) {
            $iso = self::country_from_moodle($ip);
            if ($iso !== '') {
                return $iso;
            }
        }

        // 2) Free online lookup - needs no admin setup, but is an external call, so
        // only for the opt-in check that asks for it.
        return $allowonline ? self::country_from_online($ip) : '';
    }

    /**
     * Resolve a country ISO code through Moodle's own iplookup.
     *
     * `iplookup_find_location()` returns a localised country *name*, so this maps it
     * back to an ISO code via Moodle's country list.
     *
     * @param string $ip a public IP address
     * @return string ISO alpha-2 country code, or ''
     */
    protected static function country_from_moodle(string $ip): string {
        global $CFG;

        require_once($CFG->dirroot . '/iplookup/lib.php');
        $info = iplookup_find_location($ip);
        if (!empty($info['error']) || empty($info['country'])) {
            return '';
        }

        $iso = array_search($info['country'], get_string_manager()->get_list_of_countries(true), true);
        return ($iso !== false && isset(self::CODES[$iso])) ? $iso : '';
    }

    /**
     * Resolve a country ISO code through a free, no-key online service.
     *
     * Sends only the IP address to the lookup host over HTTPS, with a short timeout;
     * any failure returns '' so the caller degrades gracefully. This is an external
     * call, so it only runs for the opt-in features that need it.
     *
     * @param string $ip a public IP address
     * @return string ISO alpha-2 country code, or ''
     */
    protected static function country_from_online(string $ip): string {
        global $CFG;

        // One lookup per address per request, in case both callers ask.
        static $cache = [];
        if (array_key_exists($ip, $cache)) {
            return $cache[$ip];
        }

        require_once($CFG->libdir . '/filelib.php');

        // Free, no-key, HTTPS services that return the ISO country code as JSON.
        // A second is tried if the first is unreachable or rate-limited, so a busy
        // moment on one host does not disable the check.
        $endpoints = [
            ['url' => 'https://api.country.is/' . rawurlencode($ip), 'key' => 'country'],
            ['url' => 'https://ipwho.is/' . rawurlencode($ip) . '?fields=country_code', 'key' => 'country_code'],
        ];

        $iso = '';
        foreach ($endpoints as $endpoint) {
            $body = download_file_content($endpoint['url'], null, null, false, 4, 4, false, null, false);
            if (!is_string($body)) {
                continue;
            }
            $data = json_decode($body, true);
            if (!is_array($data) || empty($data[$endpoint['key']])) {
                continue;
            }
            $candidate = strtoupper(trim((string) $data[$endpoint['key']]));
            if (preg_match('/^[A-Z]{2}$/', $candidate) && isset(self::CODES[$candidate])) {
                $iso = $candidate;
                break;
            }
        }

        return $cache[$ip] = $iso;
    }
}

/**
 * A single UTF-8 character from a Unicode code point.
 *
 * `mb_chr()` is not guaranteed to be available, and `core_text` has no direct
 * code-point-to-character helper, so this small local encoder is used to build the
 * flag emoji.
 *
 * @param int $cp Unicode code point
 * @return string the UTF-8 encoded character
 */
function core_utf8(int $cp): string {
    if ($cp <= 0x7F) {
        return chr($cp);
    } else if ($cp <= 0x7FF) {
        return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
    } else if ($cp <= 0xFFFF) {
        return chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
    } else {
        return chr(0xF0 | ($cp >> 18)) . chr(0x80 | (($cp >> 12) & 0x3F))
            . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
    }
}
