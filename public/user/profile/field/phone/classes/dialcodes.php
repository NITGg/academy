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
        // The rest of Moodle's country list. The table above was the set the
        // academy expected to see; AC-4.20.4 asks for a control that is generic
        // and covers *all* countries rather than an illustrative shortlist, so
        // the remaining ISO 3166-1 entries are here: the sovereign states that
        // were missing (Antigua and Barbuda, the Bahamas, Barbados, Dominica,
        // Grenada, Eswatini) and the dependent territories, which run their own
        // numbering and are where people actually live - Puerto Rico, Guam,
        // Reunion, Greenland, the Channel Islands.
        //
        // Every ISO code in lang/en/countries.php now has an entry here. When
        // Moodle adds a country, add its dialling code too, or the control quietly
        // stops covering all countries again.
        //
        // Many of these share a dialling code: the whole North American plan is
        // '1', and the French overseas departments split 262/590/594/596. That is
        // fine - what gets stored is the ISO code, not the '+NN', so Guadeloupe
        // and Saint Martin stay distinguishable even though both dial +590.
        'AG' => '1',   'AI' => '1',   'AQ' => '672', 'AS' => '1',   'AW' => '297',
        'AX' => '358', 'BB' => '1',   'BL' => '590', 'BM' => '1',   'BQ' => '599',
        'BS' => '1',   'BV' => '47',  'CC' => '61',  'CK' => '682', 'CW' => '599',
        'CX' => '61',  'DM' => '1',   'EH' => '212', 'FK' => '500', 'FO' => '298',
        'GD' => '1',   'GF' => '594', 'GG' => '44',  'GI' => '350', 'GL' => '299',
        'GP' => '590', 'GS' => '500', 'GU' => '1',   'HM' => '672', 'IM' => '44',
        'IO' => '246', 'JE' => '44',  'KY' => '1',   'MF' => '590', 'MP' => '1',
        'MQ' => '596', 'MS' => '1',   'NC' => '687', 'NF' => '672', 'NU' => '683',
        'PF' => '689', 'PM' => '508', 'PN' => '64',  'PR' => '1',   'RE' => '262',
        'SH' => '290', 'SJ' => '47',  'SX' => '1',   'SZ' => '268', 'TC' => '1',
        'TF' => '262', 'TK' => '690', 'UM' => '1',   'VG' => '1',   'VI' => '1',
        'WF' => '681', 'YT' => '262',
    ];

    /**
     * How many digits a national (subscriber) number has, per country.
     *
     * `[min, max]` digit counts for the number AFTER the dialling code, i.e. what
     * the user types in the number box. A "+20 1012345678" entry is 10 digits.
     *
     * Why a table and not a library
     * -----------------------------
     * Getting this exactly right for every country is what libphonenumber exists
     * for, and Moodle ships nothing of the kind - there is no site setting for
     * phone length anywhere in core. Pulling in libphonenumber means a Composer
     * dependency and a few megabytes of metadata to keep updated, for a sign-up
     * box. So this table carries the countries the academy actually sees, taken
     * from the ITU national numbering plans and covering mobile numbers (the
     * shorter landline formats in the same country are inside the same range).
     *
     * Anything not listed falls back to GENERIC_LENGTH, which is the widest range
     * any E.164 number can occupy. That is deliberate: a country we have not
     * checked must never reject a real number. Add entries as needed - a wrong
     * length here blocks a legitimate sign-up, so only add what you can verify.
     *
     * @var array<string,int[]>
     */
    const LENGTHS = [
        // Arab world.
        'EG' => [10, 10], 'SA' => [9, 9],  'AE' => [9, 9],  'KW' => [8, 8],
        'QA' => [8, 8],   'BH' => [8, 8],  'OM' => [8, 8],  'JO' => [9, 9],
        'LB' => [7, 8],   'IQ' => [10, 10], 'SY' => [9, 9], 'YE' => [9, 9],
        'PS' => [9, 9],   'LY' => [9, 9],  'SD' => [9, 9],  'MA' => [9, 9],
        'DZ' => [9, 9],   'TN' => [8, 8],  'MR' => [8, 8],  'SO' => [8, 9],
        'DJ' => [8, 8],   'KM' => [7, 7],
        // Elsewhere, most common among our learners.
        'TR' => [10, 10], 'IR' => [10, 10], 'PK' => [10, 10], 'IN' => [10, 10],
        'BD' => [10, 10], 'ID' => [9, 12],  'MY' => [9, 10],  'PH' => [10, 10],
        'CN' => [11, 11], 'JP' => [10, 10], 'KR' => [9, 10],
        'US' => [10, 10], 'CA' => [10, 10], 'GB' => [10, 10], 'IE' => [9, 9],
        'DE' => [10, 11], 'FR' => [9, 9],   'IT' => [9, 10],  'ES' => [9, 9],
        'PT' => [9, 9],   'NL' => [9, 9],   'BE' => [8, 9],   'CH' => [9, 9],
        'AT' => [10, 11], 'SE' => [7, 9],   'NO' => [8, 8],   'DK' => [8, 8],
        'FI' => [9, 10],  'PL' => [9, 9],   'RO' => [9, 9],   'GR' => [10, 10],
        'RU' => [10, 10], 'UA' => [9, 9],
        'NG' => [10, 10], 'KE' => [9, 9],   'ZA' => [9, 9],   'GH' => [9, 9],
        'ET' => [9, 9],   'TZ' => [9, 9],   'UG' => [9, 9],
        'BR' => [10, 11], 'MX' => [10, 10], 'AR' => [10, 10], 'AU' => [9, 9],
        'NZ' => [8, 10],
    ];

    /** @var int[] The [min, max] used for any country not in LENGTHS. */
    const GENERIC_LENGTH = [4, 15];

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
     * The digit count a national number may have in a country.
     *
     * @param string $iso ISO alpha-2 country code
     * @return int[] [min, max]
     */
    public static function length(string $iso): array {
        return self::LENGTHS[strtoupper($iso)] ?? self::GENERIC_LENGTH;
    }

    /**
     * Is this a plausible national number for the country?
     *
     * Length only. It cannot tell a real number from an invented one of the right
     * shape - that needs a carrier lookup - but it does catch the everyday mistakes:
     * a digit dropped, a digit doubled, the dialling code typed into the number box,
     * or a number pasted from another country.
     *
     * @param string $iso ISO alpha-2 country code
     * @param string $number the national number, digits only
     * @return bool
     */
    public static function length_ok(string $iso, string $number): bool {
        [$min, $max] = self::length($iso);
        $digits = strlen(preg_replace('/\D/', '', $number));

        return $digits >= $min && $digits <= $max;
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
     * The list shown in the country select: ISO => "Egypt +20 🇪🇬".
     *
     * Every country Moodle knows is offered - CODES covers the whole of
     * lang/en/countries.php, so the control is generic rather than a shortlist of
     * the countries we happened to expect (AC-4.20.4). The list is sorted by the
     * localised name, so the order follows the interface language.
     *
     * The label leads with the country NAME, and that ordering is load-bearing, not
     * cosmetic. A browser's select type-ahead ("press S to jump to Saudi Arabia")
     * matches from the first character of the option text. This label used to open
     * with the flag emoji - which is built from two regional-indicator code points
     * standing for the ISO code - so the jump landed on the country CODE instead:
     * pressing "g" never found Germany (DE), and whether it worked at all depended
     * on how the browser folds those code points. That reads as "sometimes the
     * keyboard filters, sometimes it doesn't".
     *
     * So: name, then dialling code, then the flag last. The flag is the first thing
     * a narrow select clips, which is the right thing to lose - and on Windows,
     * where there is no flag glyph, it was rendering as a duplicate of the ISO code
     * anyway.
     *
     * @return array<string,string>
     */
    public static function menu(): array {
        $names = get_string_manager()->get_list_of_countries(true);

        // Sorted on the names, not on the finished labels: a label starts with a flag
        // emoji (a pair of regional-indicator letters that track the ISO code), so
        // sorting those would order the list by country code instead of by the name
        // people are actually reading.
        $order = [];
        foreach (array_keys(self::CODES) as $iso) {
            if (isset($names[$iso])) {
                $order[$iso] = $names[$iso];
            }
        }
        \core_collator::asort($order);

        $menu = [];
        foreach ($order as $iso => $name) {
            $menu[$iso] = trim($name . ' +' . self::CODES[$iso] . ' ' . self::flag($iso));
        }

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
        global $CFG, $SESSION;

        $ip = getremoteaddr();
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            // No usable public address (e.g. localhost or a LAN client behind no proxy).
            return '';
        }

        // AC-4.6.9: "cached for the duration of the session and does not add more
        // than 300 ms to page rendering". A static cache only ever held the answer
        // for one request, so a visitor paid the lookup again on every page. The
        // session is the unit the specification names, and the address is part of
        // the key so that a visitor who changes network is re-resolved rather than
        // priced from where they used to be (AC-4.6.7).
        $cachekey = 'phone_country_' . $ip . ($allowonline ? '_online' : '');
        if (isset($SESSION->{$cachekey})) {
            self::$servicedown = !empty($SESSION->{$cachekey . '_down'});

            return (string) $SESSION->{$cachekey};
        }

        self::$servicedown = false;

        // 1) Moodle's configured GeoIP source, if any. A local .mmdb file is a disk
        // read rather than a network call, which is the only way the 300 ms in
        // AC-4.6.9 is comfortably met - see the class note on geoip2file.
        if (!empty($CFG->geoip2file) || !empty($CFG->geopluginapikey)) {
            $iso = self::country_from_moodle($ip);
            if ($iso !== '') {
                $SESSION->{$cachekey} = $iso;

                return $iso;
            }
        }

        // 2) Free online lookup - needs no admin setup, but is an external call, so
        // only for the opt-in check that asks for it.
        $iso = $allowonline ? self::country_from_online($ip) : '';

        // Only a settled answer is worth caching. A failure caused by the lookup
        // hosts being unreachable is a transient state of ours, and pinning it to
        // the session would keep refusing this visitor long after the outage ended.
        if (!self::$servicedown) {
            $SESSION->{$cachekey} = $iso;
        }

        return $iso;
    }

    /**
     * @var bool Whether the last lookup failed because no source could be reached.
     *
     * The difference between "we asked and nobody could place this address" and
     * "we could not ask anybody" is invisible in the return value - both are '' -
     * but AC-4.6.10 gives them different messages and only one of them is an
     * incident. This flag carries that distinction out to the caller, which reads
     * it through {@see self::service_was_down()} immediately after the lookup.
     */
    protected static $servicedown = false;

    /**
     * Did the most recent lookup fail because every source was unreachable?
     *
     * Only meaningful directly after a call to {@see self::country_from_ip()}.
     *
     * @return bool
     */
    public static function service_was_down(): bool {
        return self::$servicedown;
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
        $reached = false;

        foreach ($endpoints as $endpoint) {
            // One second, not four. This runs on the sign-up submit, and with two
            // endpoints tried in turn a four-second timeout meant a bad network day
            // cost the learner eight seconds and then refused them anyway - against
            // a specification that allows 300 ms. One second is long enough for a
            // service that is answering and short enough that one that is not stops
            // being the learner's problem.
            $body = download_file_content($endpoint['url'], null, null, false, 1, 1, false, null, false);
            if (!is_string($body)) {
                continue;
            }

            // We got bytes back, so the host is alive. Whether it could place this
            // particular address is a separate question, answered below.
            $reached = true;
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

        // Nothing answered at all. That is an outage on our side of the question,
        // not a fact about this visitor, and AC-4.6.10 treats the two differently.
        self::$servicedown = ($iso === '' && !$reached);

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
