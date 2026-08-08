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

use renderable;
use renderer_base;
use templatable;

/**
 * View-model for the design-system component gallery.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gallery implements renderable, templatable {
    /**
     * Export sample data for the gallery template.
     *
     * @param renderer_base $output the renderer
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        // Read the live, admin-configured brand colours (settings.php). Falls
        // back to the design-system primitive when a picker is left empty, so
        // the swatches always mirror what the site actually renders.
        $swatch = function (string $key, string $name, string $default): array {
            $hex = get_config('theme_nit', $key);
            return ['name' => $name, 'hex' => (!empty($hex) ? $hex : $default)];
        };

        return [
            'swatches' => [
                $swatch('brandprimary', 'Primary', '#2a50c8'),
                $swatch('brandsecondary', 'Secondary', '#626c7a'),
                $swatch('brandsuccess', 'Success', '#1e7a54'),
                $swatch('brandwarning', 'Warning', '#9a6410'),
                $swatch('branddanger', 'Danger', '#b23a2e'),
                $swatch('brandinfo', 'Info', '#0e7c86'),
                $swatch('inkcolour', 'Ink', '#171b22'),
            ],
            'stats' => [
                ['label' => 'Active learners', 'value' => '1,284', 'trend' => '+12%', 'up' => true],
                ['label' => 'Course completions', 'value' => '842', 'trend' => '+5%', 'up' => true],
                ['label' => 'Overdue tasks', 'value' => '37', 'trend' => '-8%', 'up' => false],
            ],
        ];
    }
}
