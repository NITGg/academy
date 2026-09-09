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
 * Public JSON API for the NIT design system.
 *
 * Mirrors the four tabs of the design-system gallery (theme/nit/gallery.php) as
 * data, so external clients — the Flutter / mobile app in particular — can sync
 * their theme with the web brand. Everything returned is public branding
 * (already visible in the site's CSS / DOM); no login is required, the endpoint
 * is read-only and exposes nothing sensitive. Category listing is
 * visibility-aware, so an anonymous request sees only guest-visible categories.
 *
 * Response shape:
 * {
 *   "generated": 1712345678,
 *   "site": { "name": "NIT Academy", "url": "https://…" },
 *   "brandcolors": {                        // Tab 1 — Brand Colors
 *     "roles": ["primary", "secondary", …], // role keys shared by every group
 *     // Which group the site wears for each colour scheme — the pair its own
 *     // light/dark switch moves between. Follow this instead of hard-coding
 *     // group keys: renumbering the groups then costs no client release.
 *     "schemes": { "light": "g4", "dark": "g5" },
 *     "groups": [
 *       // "scheme" is how the group's OWN roles are authored ("light" = dark
 *       // ink on a bright ground), measured from its Background against its
 *       // Text primary — not where the site happens to use it, and not the
 *       // admin-editable "name". Present on every group.
 *       { "key": "g1", "name": "Group 1", "scheme": "dark",
 *         "isdefault": true, "class": "",
 *         "roles": [
 *           { "key": "g1_primary", "role": "primary", "label": "Primary",
 *             "cssvar": "--nit-brand-primary", "value": "#e5322d",
 *             "default": "#e5322d", "iscustom": false,
 *             "usage": ["background main button", …] }, … ] }, … ]
 *   },
 *   "categorystyles": {                     // Tab 2 — Category styles
 *     "groups": [ { "key": "g1", "name": "Group 1", "scheme": "dark" }, … ],
 *     "categories": [
 *       // The flat group/class keys are the LIGHT values, kept for readers that
 *       // predate the second style; "modes" carries both. "isdefault" means the
 *       // category has no style of its own for that mode, so "group" is the
 *       // site's group for it — the one those pages really render in.
 *       { "id": 3, "name": "Programming", "group": "g4", "groupname": "Group 4 (Daylight — light)",
 *         "class": "nit-brand-4", "isdefault": false,
 *         "modes": {
 *           "light": { "group": "g4", "groupname": "Group 4 (Daylight — light)",
 *                      "class": "nit-brand-4", "scheme": "light",
 *                      "isdefault": false, "logo": "https://…/category-logo-light.png" },
 *           "dark":  { "group": "g5", "groupname": "Group 5 (Graphite — dark)",
 *                      "class": "nit-brand-5", "scheme": "dark",
 *                      "isdefault": false, "logo": "" }
 *         } }, … ]
 *   },
 *   "fonts": [                              // Tab 3 — Fonts
 *     { "lang": "en", "label": "English font", "family": "NIT Site Font EN",
 *       "rtl": false, "fallback": "…", "hasfont": true, "filename": "font-en.ttf",
 *       "url": "https://…/pluginfile.php/…/font-en.ttf" }, … ]
 *   "components": [                         // Tab 4 — Components
 *     { "name": "Buttons", "variants": [ { "label": "Primary",
 *       "class": "btn btn-primary" }, … ] }, … ]
 * }
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Lightweight, session-less request — this is public, cacheable branding data.
define('NO_MOODLE_COOKIES', true);

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$payload = theme_nit_design_system_export();

// Public branding data: allow cross-origin reads (e.g. the mobile app) and a
// short cache so repeated fetches are cheap.
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: public, max-age=300');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    // CORS pre-flight — headers above are enough.
    exit;
}

echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
