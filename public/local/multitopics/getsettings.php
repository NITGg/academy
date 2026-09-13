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
 * GET /local/multitopics/getsettings.php
 *
 * The mobile app's launch feed: the single unauthenticated call it makes before
 * anything else. Everything the app cannot change without a store release lives
 * here instead, edited under Site administration → Plugins → Local plugins →
 * Mobile app settings.
 *
 * Contract (the app parses it exactly this way — do not "improve" it):
 *   - 200 with Content-Type application/json, one top-level "data" object.
 *   - EVERY value is a JSON string, numbers and booleans included - except
 *     `pages`, an object of the About and Contact pages in the shape of
 *     local_profilefields_get_static_page (optional ?lang=ar|en picks the text).
 *   - A key that has no usable value is OMITTED, never sent empty or malformed:
 *     the app falls back per key, but a malformed value throws on its side
 *     (a bad version string locks every user behind a blocking update dialog).
 *   - Never cached: ip_country is resolved from the caller's address and decides
 *     which market a guest is priced in, so a cached body would hand every
 *     visitor the first caller's country.
 *
 * Auth: none. This is public, so the token it publishes must be the dedicated
 * read-only guest-browsing token (see cli/app_guest_token.php).
 *
 * @package    local_multitopics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
// A stray PHP notice printed into the body would turn valid JSON into a parse
// error on the app side, which fails exactly like the 404 did.
define('NO_DEBUG_DISPLAY', true);

require(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$cfg = (object) (array) get_config('local_multitopics');
$data = [];

/**
 * Put a non-empty trimmed string on the feed, else leave the key out.
 */
$put = static function (string $key, $value) use (&$data): void {
    $value = trim((string) $value);
    if ($value !== '') {
        $data[$key] = $value;
    }
};

// ── Access ─────────────────────────────────────────────────────────────────
$put('admin_token', preg_match('/^[a-f0-9]{32}$/i', $cfg->admin_token ?? '') ? $cfg->admin_token : '');
$put('google_client_id', $cfg->google_client_id ?? '');

$timeout = (int) ($cfg->server_timeout_duration ?? 0);
$put('server_timeout_duration', $timeout > 0 ? (string) $timeout : '');

// ── Playback protection ────────────────────────────────────────────────────
// Both flags are always sent: the app treats anything but a literal "0" as ON
// for screen recording, and "1" as ON for the watermark.
// Unset (page never saved) must read as ON, not OFF - the checkbox default is not
// stored until an admin saves the page.
$data['prevent_screen_recording'] = (isset($cfg->prevent_screen_recording) && (string) $cfg->prevent_screen_recording === '0') ? '0' : '1';
$data['watermark'] = empty($cfg->watermark) ? '0' : '1';

foreach (['watermark_speed', 'watermark_fontsize'] as $key) {
    $value = trim((string) ($cfg->$key ?? ''));
    $put($key, is_numeric($value) && (float) $value > 0 ? $value : '');
}

// Six hex digits, no "#": the app prepends 0xff and parses the rest as hex.
$colour = ltrim(trim((string) ($cfg->watermark_color ?? '')), '#');
$put('watermark_color', preg_match('/^[0-9A-Fa-f]{6}$/', $colour) ? strtoupper($colour) : '');

// ── Store versions ─────────────────────────────────────────────────────────
// Strict semver only. A value that does not parse on the app side throws, and
// one that parses higher than the installed build shows a non-dismissible
// update dialog — so a typo here would lock every user out. Send nothing rather
// than something the app cannot read.
foreach (['android', 'ios'] as $platform) {
    $version = trim((string) ($cfg->{$platform . '_version'} ?? ''));
    $put($platform . '_version', preg_match('/^\d+\.\d+\.\d+$/', $version) ? $version : '');
    $put($platform . '_url', $cfg->{$platform . '_url'} ?? '');
}

// ── Support ────────────────────────────────────────────────────────────────
// wa.me wants digits only (no "+", spaces or dashes) and a pre-encoded text.
$put('whatsapp_phone', preg_replace('/\D+/', '', (string) ($cfg->whatsapp_phone ?? '')));
$message = trim((string) ($cfg->whatsapp_message ?? ''));
$put('whatsapp_message', $message === '' ? '' : rawurlencode($message));

// ── Country from the caller's IP ───────────────────────────────────────────
// Same answer the shop gives a guest (profile → IP → default ladder, guest
// branch), so the app and the priced course cards agree on the market. Omitted
// when the address cannot be placed; the app then uses the device signal.
if (class_exists('\local_payments\country_detector')) {
    try {
        $put('ip_country', \local_payments\country_detector::detect_for_pricing(0, null, getremoteaddr()));
    } catch (\Throwable $e) {
        debugging('local_multitopics: ip_country lookup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

// ── Support e-mail and the About / Contact pages ───────────────────────────
// Both come from local_profilefields, which already owns them for the web site
// and the app's `local_profilefields_get_static_page` function: the footer's
// e-mail row is the support address, and the two pages are served through the
// very same external class so the feed and the WS can never disagree. Nested
// objects, unlike the flat string keys above; the app reads them as such.
if (class_exists('\local_profilefields\footer')) {
    $email = trim(\local_profilefields\footer::get('email'));
    $put('support_email', validate_email($email) ? $email : '');
}

if (class_exists('\local_profilefields\external\get_static_page')) {
    // ?lang=ar|en picks the language of the page text; absent = site default.
    $lang = optional_param('lang', '', PARAM_LANG);
    $pages = [];
    foreach (['about', 'contact'] as $slug) {
        try {
            $page = \local_profilefields\external\get_static_page::execute($slug, $lang);
            unset($page['warnings']);
            $pages[$slug] = $page;
            if (!empty($page['published'])) {
                $put($slug . '_url', $page['url']);
            }
        } catch (\Throwable $e) {
            debugging("local_multitopics: static page '$slug' failed: " . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
    if ($pages) {
        $data['pages'] = $pages;
    }
}

echo json_encode(['data' => (object) $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
