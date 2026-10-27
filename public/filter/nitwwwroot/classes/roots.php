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

namespace filter_nitwwwroot;

/**
 * The addresses this site has been served from, and the rewrites they imply.
 *
 * Content keeps whatever address it was written under: the editor stores
 * absolute links, admins paste them, an HTML block names its images. When the
 * site moves to a new domain or out of a sub-directory, every one of those
 * links still points at the old place. Rather than hunt them down after each
 * move, this class remembers every wwwroot the site has run under (setting
 * filter_nitwwwroot/roots) and turns them into rewrite rules for the filter:
 *
 *  1. an absolute link under an earlier address points at the current one;
 *  2. a root-relative link under an earlier sub-directory (href="/moodle-new/...")
 *     loses the sub-directory;
 *  3. when the site itself lives in a sub-directory, a root-relative link
 *     (href="/theme/...") is read the way moodle_url reads one - relative to
 *     wwwroot - so block markup never has to name the directory.
 *
 * The current wwwroot is added to the list the first time it is seen, which is
 * what makes the next move need no edit at all.
 *
 * @package    filter_nitwwwroot
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class roots {

    /** Characters that may follow a site address inside a link. */
    private const BOUNDARY = '(?=[\/?#"\'\s<>)]|$)';

    /** Attribute (or CSS url()) openings whose value is a link; captured as group 1. */
    private const LINKATTR = '((?:\b(?:href|src|poster|action|data-base)\s*=\s*|url\(\s*)["\']?)';

    /** @var array|null [pattern, address, keep group 1] triples for this request; patterns use ~ (BOUNDARY holds #). */
    private static $rules = null;

    /**
     * Reduce an address to the form it is stored and compared in.
     *
     * @param string $url
     * @return string scheme://host[:port][/path] without a trailing slash, or '' when not an http(s) URL
     */
    public static function normalise(string $url): string {
        $parts = parse_url(trim($url));
        if (empty($parts['scheme']) || empty($parts['host'])
                || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return '';
        }
        $root = strtolower($parts['scheme']) . '://' . strtolower($parts['host']);
        if (!empty($parts['port'])) {
            $root .= ':' . $parts['port'];
        }
        return $root . rtrim($parts['path'] ?? '', '/');
    }

    /**
     * Every address on record.
     *
     * @return string[]
     */
    public static function all(): array {
        $list = preg_split('/\s+/', (string) get_config('filter_nitwwwroot', 'roots'), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique(array_filter(array_map([self::class, 'normalise'], $list))));
    }

    /**
     * Add addresses to the record. Invalid and already-known ones are skipped.
     *
     * @param string[] $urls
     */
    public static function remember(array $urls): void {
        $known = self::all();
        $merged = array_values(array_unique(array_merge($known,
            array_filter(array_map([self::class, 'normalise'], $urls)))));
        if ($merged !== $known) {
            set_config('roots', implode("\n", $merged), 'filter_nitwwwroot');
        }
        self::$rules = null;
    }

    /**
     * The rewrite rules for the current wwwroot, built once per request.
     *
     * @return array[] list of [pattern, address, keep group 1]
     */
    public static function rules(): array {
        global $CFG;

        if (self::$rules !== null) {
            return self::$rules;
        }

        $current = self::normalise($CFG->wwwroot);
        if ($current === '') {
            return self::$rules = [];
        }
        if (!in_array($current, self::all(), true)) {
            self::remember([$current]);
        }
        $currentpath = (string) parse_url($current, PHP_URL_PATH);
        $currentnoscheme = preg_replace('#^https?:#', '', $current);

        // Longest first, so https://host/sub is rewritten before https://host could claim it.
        $legacy = array_diff(self::all(), [$current]);
        usort($legacy, fn($a, $b) => strlen($b) - strlen($a));

        $rules = [];
        $paths = [];
        foreach ($legacy as $root) {
            $noscheme = preg_replace('#^https?:#', '', $root);
            $guard = '';
            if ($noscheme !== $currentnoscheme && strpos($currentnoscheme . '/', $noscheme . '/') === 0) {
                // An earlier address that is a parent of the current one: a link already under the
                // current address must not get the sub-directory a second time.
                $guard = '(?!' . preg_quote(substr($currentnoscheme, strlen($noscheme)), '~') . self::BOUNDARY . ')';
            }
            // Same host and path under the other scheme is rewritten too, so http links become https.
            $rules[] = ['~(?:https?:)?' . preg_quote($noscheme, '~') . self::BOUNDARY . $guard . '~i', $current, false];

            $path = (string) parse_url($root, PHP_URL_PATH);
            if ($path !== '' && $path !== $currentpath && strpos($currentpath . '/', $path . '/') !== 0) {
                $paths[$path] = true;
            }
        }

        foreach (array_keys($paths) as $path) {
            $rules[] = ['~' . self::LINKATTR . preg_quote($path, '~') . self::BOUNDARY . '~i', $current, true];
        }

        if ($currentpath !== '') {
            // Root-relative links mean "relative to wwwroot", exactly as moodle_url reads them.
            $rules[] = ['~' . self::LINKATTR . '(?!' . preg_quote($currentpath, '~') . self::BOUNDARY . ')(?=/(?!/))~i',
                $current, true];
        }

        return self::$rules = $rules;
    }

    /**
     * Apply the rules to a piece of text.
     *
     * @param string $text
     * @return string
     */
    public static function rewrite(string $text): string {
        foreach (self::rules() as [$pattern, $address, $keepattr]) {
            $result = preg_replace_callback($pattern,
                fn($m) => ($keepattr ? $m[1] : '') . $address, $text);
            if ($result === null) {
                debugging('filter_nitwwwroot: ' . preg_last_error_msg() . ' in ' . $pattern, DEBUG_DEVELOPER);
                continue;
            }
            $text = $result;
        }
        return $text;
    }
}
