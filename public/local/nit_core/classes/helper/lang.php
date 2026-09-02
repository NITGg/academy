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

namespace local_nit_core\helper;

/**
 * Language helpers shared across the NIT plugins.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
final class lang {
    /** @var bool true once the shutdown restore for this request has been queued. */
    private static bool $restorequeued = false;

    /**
     * Render this ONE request in the given language, without touching the session's own language.
     *
     * Why this exists rather than a bare force_current_language() call: that function writes
     * $SESSION->forcelang, and $SESSION outlives the request. current_language() reads forcelang
     * ahead of everything else — ahead of $SESSION->lang, which is what the navbar's ?lang=xx
     * switcher sets — so an AJAX endpoint that announced "answer me in English" once left the
     * whole session pinned to English and made the switcher look broken: the URL changed, the
     * page did not. (The only escape was core's ?forcelang=none, until the next AJAX call
     * pinned it again.)
     *
     * So: set it for the work we are about to do, and put it back on shutdown. Custom shutdown
     * callbacks run before \core\session\manager::write_close(), so the restored value is what
     * gets persisted. Safe on the exit() that the JSON endpoints finish with, and on an
     * uncaught exception, which a try/finally around a script that echoes and exits is not.
     *
     * Only for request-scoped display language (a JSON feed, a CSV export, a web service
     * answering in the caller's language). Deliberate, lasting switches — the navbar switcher,
     * a user's profile language — belong in $SESSION->lang and are none of this function's
     * business.
     *
     * @param string $language language code, e.g. 'ar'. An empty string is a no-op.
     * @return void
     */
    public static function for_request(string $language): void {
        global $SESSION;

        if ($language === '') {
            return;
        }

        // Queue the undo once, against the value the request arrived with. Calling this twice
        // in one request must still restore that original, not the intermediate one.
        if (!self::$restorequeued) {
            self::$restorequeued = true;
            $original = $SESSION->forcelang ?? '';
            \core\shutdown_manager::register_function(static function () use ($original): void {
                // force_current_language('') clears the override; core restores it the same way.
                force_current_language($original);
            });
        }

        force_current_language($language);
    }
}
