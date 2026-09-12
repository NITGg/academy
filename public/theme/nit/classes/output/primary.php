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

namespace theme_nit\output;

use renderer_base;

/**
 * Core's primary navigation, with the mobile drawer's row list reachable on its own.
 *
 * Core builds `mobileprimarynav` inside export_for_template() alongside the user
 * and language menus, and only the layout file receives the result. The theme's
 * drawer wants that same list — the primary nodes merged with the custom menu
 * lines — but filtered per viewer (see core_renderer::navbar_mobile_primary_nav),
 * so it needs the list from a renderer method, and it needs it without paying
 * for the user menu a second time. The two builders are protected in core, which
 * is what this subclass is for.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class primary extends \core\navigation\output\primary {

    /**
     * The rows core would hand the mobile drawer, before any viewer filtering.
     *
     * @param renderer_base $output
     * @return array exported nodes ({text, url, isactive, children, ...})
     */
    public function mobile_rows(renderer_base $output): array {
        return $this->merge_primary_and_custom($this->get_primary_nav(), $this->get_custom_menu($output), true);
    }
}
