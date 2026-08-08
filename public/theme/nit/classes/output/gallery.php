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
        // Build the editable colour palette, grouped for display. The palette
        // (defined in lib.php) is ordered so each group's tokens are contiguous.
        $groups = [];
        $current = null;
        foreach (\theme_nit_colour_palette() as $key => $meta) {
            if ($current === null || $current['name'] !== $meta['group']) {
                if ($current !== null) {
                    $groups[] = $current;
                }
                $current = ['name' => $meta['group'], 'colours' => []];
            }
            $value = \theme_nit_colour($key);
            $current['colours'][] = [
                'key' => $key,
                'label' => $meta['label'],
                'configname' => 'theme_nit | colour_' . $key,
                'value' => $value,
                'default' => $meta['default'],
                'isdefault' => (strtolower($value) === strtolower($meta['default'])),
            ];
        }
        if ($current !== null) {
            $groups[] = $current;
        }

        return [
            'sesskey' => sesskey(),
            'actionurl' => (new \moodle_url('/theme/nit/gallery.php'))->out(false),
            'colourgroups' => $groups,
            'stats' => [
                ['label' => 'Active learners', 'value' => '1,284', 'trend' => '+12%', 'up' => true],
                ['label' => 'Course completions', 'value' => '842', 'trend' => '+5%', 'up' => true],
                ['label' => 'Overdue tasks', 'value' => '37', 'trend' => '-8%', 'up' => false],
            ],
        ];
    }
}
