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

namespace local_nit_mlang\hook;

use core\hook\output\before_standard_head_html_generation;
use local_nit_mlang\langs;
use local_nit_mlang\registry;

/**
 * Output hook callbacks — the "where" of the multilang field editor.
 *
 * There is no core hook that lets a plugin walk a `moodleform` definition, so the
 * enhancement is applied in the browser: this callback only decides whether the
 * current page/user should get it, and hands the JS module the language list and
 * the field registry. The module itself matches fields (including ones a modal
 * loads later, via a MutationObserver) and swaps in the per-language editor.
 *
 * @package    local_nit_mlang
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class output_callbacks {

    /**
     * Load the per-language field editor on this page, when it applies.
     *
     * @param before_standard_head_html_generation $hook the output hook
     * @return void
     */
    public static function load_field_editor(before_standard_head_html_generation $hook): void {
        global $PAGE, $CFG;

        // Never during install/upgrade (no capabilities yet) or for logged-out
        // visitors (nobody edits content from the login or signup page).
        if (!isset($PAGE) || during_initial_install() || !empty($CFG->upgraderunning) || !isloggedin() || isguestuser()) {
            return;
        }
        if (!self::setting('enabled')) {
            return;
        }

        // Nothing to translate between when a single language pack is installed.
        $languages = langs::get();
        if (count($languages) < 2) {
            return;
        }

        // Content authors only: a student filling in a forum subject must keep the
        // ordinary single field.
        try {
            $context = $PAGE->context ?: \context_system::instance();
        } catch (\Throwable $e) {
            $context = \context_system::instance();
        }
        if (!has_capability('local/nit_mlang:edit', $context)) {
            return;
        }

        $PAGE->requires->js_call_amd('local_nit_mlang/fields', 'init', [[
            'pagetype'       => (string) $PAGE->pagetype,
            'langs'          => $languages,
            'textfields'     => registry::text_fields(),
            'textexcludes'   => registry::text_excludes(),
            'editorexcludes' => registry::editor_excludes(),
            'editors'        => self::setting('editors'),
            'strings'        => [
                'translations' => get_string('translations', 'local_nit_mlang'),
            ],
        ]]);
    }

    /**
     * Read an on/off setting, treating "never saved" as on.
     *
     * Both switches ship enabled; this keeps that true in the window between the
     * plugin being installed and an administrator first opening its settings page.
     *
     * @param string $name setting name
     * @return bool
     */
    protected static function setting(string $name): bool {
        $value = get_config('local_nit_mlang', $name);
        return $value === false ? true : (bool) $value;
    }
}
