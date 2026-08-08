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
 * NIT design-system component gallery (admin/dev only).
 *
 * Living documentation and the accessibility test surface for the design
 * system. Reached from Site administration → Appearance → NIT Design System.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
// Palette helpers (theme_nit_colour_palette / theme_nit_colour) live here.
require_once(__DIR__ . '/lib.php');

// Handles login, the moodle/site:config capability, admin page layout and
// breadcrumb, using the external page registered in settings.php.
admin_externalpage_setup('theme_nit_gallery');

$pageurl = new moodle_url('/theme/nit/gallery.php');

// -----------------------------------------------------------------------------
// Colour palette editor — save / reset. The palette (theme_nit_colour_palette())
// is the site's single source of colour truth; values are stored as theme_nit
// config (`colour_<key>`) and compiled into SCSS + --nit-* custom properties by
// the theme's SCSS callbacks. After a change we purge theme caches so the CSS
// rebuilds on the next request.
// -----------------------------------------------------------------------------
if (($data = data_submitted()) && confirm_sesskey()) {
    $palette = theme_nit_colour_palette();

    if (!empty($data->resetcolours)) {
        foreach (array_keys($palette) as $key) {
            unset_config('colour_' . $key, 'theme_nit');
        }
        theme_reset_all_caches();
        redirect($pageurl, get_string('coloursreset', 'theme_nit'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    if (!empty($data->savecolours)) {
        foreach ($palette as $key => $meta) {
            $field = 'colour_' . $key;
            $value = optional_param($field, '', PARAM_RAW_TRIMMED);
            // Accept a valid #rgb / #rrggbb only; otherwise fall back to the
            // token's default so a bad value can never poison the stylesheet.
            if (!preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
                $value = $meta['default'];
            }
            set_config($field, $value, 'theme_nit');
        }
        theme_reset_all_caches();
        redirect($pageurl, get_string('colourssaved', 'theme_nit'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

$gallery = new \theme_nit\output\gallery();

// Two-way sync between each colour picker and its hex text field.
$PAGE->requires->js_amd_inline(<<<'JS'
require([], function() {
    document.querySelectorAll('[data-nit-colour]').forEach(function(row) {
        var picker = row.querySelector('input[type="color"]');
        var text = row.querySelector('input[type="text"]');
        if (!picker || !text) {
            return;
        }
        picker.addEventListener('input', function() {
            text.value = picker.value.toUpperCase();
        });
        text.addEventListener('input', function() {
            if (/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(text.value)) {
                picker.value = text.value;
            }
        });
    });
});
JS);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_nit/gallery/showcase', $gallery->export_for_template($OUTPUT));
echo $OUTPUT->footer();
