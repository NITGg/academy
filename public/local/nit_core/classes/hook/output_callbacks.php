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

use core\hook\output\before_standard_top_of_body_html_generation;
use local_nit_core\api\config;
use local_nit_core\output\welcome_panel;

/**
 * Output hook callbacks — the "when/where" of the rendering seam.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class output_callbacks {
    /**
     * Inject the NIT welcome panel on the dashboard when enabled.
     *
     * Gated by the local_nit_core/showwelcomepanel setting (off by default) and
     * scoped to the dashboard. The SDK builds the view-model; the theme renders
     * it (via its template override), so no presentation lives here.
     *
     * @param before_standard_top_of_body_html_generation $hook the output hook
     * @return void
     */
    public static function add_welcome_panel(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;

        if (!config::get_bool('showwelcomepanel')) {
            return;
        }
        if (!isset($PAGE) || $PAGE->pagelayout !== 'mydashboard') {
            return;
        }

        $renderer = $hook->renderer;
        $panel = new welcome_panel();

        // Prefer the theme's NIT renderer when present; degrade to a generic
        // template render otherwise (graceful if the active theme is not NIT).
        if (is_callable([$renderer, 'render_nit'])) {
            $html = $renderer->render_nit($panel);
        } else {
            $html = $renderer->render_from_template($panel->template_name(), $panel->export_for_template($renderer));
        }

        $hook->add_html($html);
    }
}
