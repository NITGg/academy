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

namespace local_nit_core\hook;

use core\hook\after_config;

/**
 * Language hook callbacks — let an explicit ?lang=xx win over a stale forced language.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lang_callbacks {
    /**
     * Drop a leftover $SESSION->forcelang when the visitor explicitly asks for a language.
     *
     * current_language() reads $SESSION->forcelang before $SESSION->lang, so a session that
     * still carries a forced language ignores the navbar switcher entirely — the ?lang=xx link
     * sets $SESSION->lang in setup.php and nothing on screen changes. Sessions got into that
     * state through NIT AJAX endpoints that used to set forcelang and never put it back
     * (\local_nit_core\helper\lang::for_request now does), and those sessions stay stuck until
     * they are logged out, so clear the override here rather than waiting them out.
     *
     * Clicking a language link is an unambiguous request for that language, which makes
     * dropping the override the right reading and not merely the convenient one — it is what
     * core's own ?forcelang=none escape hatch does, minus having to know about it.
     *
     * Runs on after_config, dispatched from setup.php just after the block that turns ?lang=xx
     * into $SESSION->lang, and well before anything asks current_language() what to render in.
     *
     * @param after_config $hook the configuration hook (unused; the request is the input)
     * @return void
     */
    public static function after_config(after_config $hook): void {
        global $SESSION;

        // Nothing forced, nothing to clear.
        if (empty($SESSION->forcelang)) {
            return;
        }

        // GET only, matching how core reads ?lang= — a POSTed lang field is form data, not a
        // language switch, and must not silently retune the session.
        if (!isset($_GET['lang'])) {
            return;
        }

        // An explicit ?forcelang= in the same request is the visitor asking for the override.
        // Core has just honoured it a few lines above us; do not immediately undo it.
        if (isset($_GET['forcelang'])) {
            return;
        }

        // PARAM_SAFEDIR, as core uses here: PARAM_LANG blanks out an unknown code, so a typo
        // would read as "no language asked for" instead of "asked for something we don't have".
        $lang = optional_param('lang', '', PARAM_SAFEDIR);
        if ($lang === '' || !get_string_manager()->translation_exists($lang, false)) {
            return;
        }

        unset($SESSION->forcelang);

        // setup.php already called moodle_setlocale() for the language it thought was current.
        // That was the forced one, so redo it now that $SESSION->lang has the floor.
        moodle_setlocale();
    }
}
