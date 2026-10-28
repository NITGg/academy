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

namespace local_nit_ai;

/**
 * Hook callbacks.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Put the review panel and the chat under a File, URL or H5P activity's
     * content.
     *
     * The footer hook is where a plugin can add to a core page: its output lands
     * right after the page's main content, inside the main region — under the
     * video, which is where a teacher reviewing it and a student asking about it
     * both are. Only on the activity's own view page, and only once the
     * assistant has been asked for there: a PDF's page gets nothing.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     * @return void
     */
    public static function before_footer(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE;

        if (during_initial_install() || !isloggedin()) {
            return;
        }
        $cm = $PAGE->cm;
        if (!$cm || !in_array($cm->modname, source::GENERIC_MODULES, true)
                || $PAGE->pagetype !== 'mod-' . $cm->modname . '-view') {
            return;
        }
        if (!api::get((int) $cm->id) && !stt\manager::get((int) $cm->id)) {
            return;
        }

        try {
            $context = \context_module::instance((int) $cm->id);
            $html = ui::review_panel($cm, $context) . ui::chat_drawer($cm, $context);
            if ($html !== '') {
                $hook->add_html(\html_writer::div($html, 'nitai-activity mt-4'));
            }
        } catch (\Throwable $e) {
            debugging('local_nit_ai: could not add the assistant to the page: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
