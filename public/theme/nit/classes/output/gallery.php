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
        // Brand Colors palette: the new semantic layer, one section per group
        // (Group 1/2/3), each listing the same roles. The palette is ordered so
        // each group's roles are contiguous. `usage` is a list of UI targets,
        // wrapped as {label} objects so the template renders one chip each.
        $brandgroups = [];
        $bidx = [];  // group name => index in $brandgroups.
        foreach (\theme_nit_brand_palette() as $key => $meta) {
            $gname = $meta['group'];
            if (!array_key_exists($gname, $bidx)) {
                $bidx[$gname] = count($brandgroups);
                $brandgroups[] = ['name' => $gname, 'groupkey' => $meta['groupkey'], 'roles' => []];
            }
            $value = \theme_nit_brandcolour($key);
            $brandgroups[$bidx[$gname]]['roles'][] = [
                'key' => $key,
                'label' => $meta['label'],
                'usage' => array_map(static fn($u) => ['label' => $u], $meta['usage']),
                'cssvar' => '--nit-brand-' . $meta['role'],
                'value' => $value,
                'default' => $meta['default'],
                'isdefault' => (strtolower($value) === strtolower($meta['default'])),
            ];
        }

        // Category branding for the "Category styles" tab. Only the MAIN
        // (top-level) categories are assignable; every page under a main
        // category (its subcategories, its courses, filtered views) inherits it
        // via theme_nit_category_brand_group() / theme_nit_category_logo_url().
        //
        // Each row carries the same decision twice — once for light mode, once
        // for dark — because that is what a category's style now is: a pair the
        // navbar light/dark button moves between. `modes` is that pair, in
        // switch order, so the template prints the columns from the data rather
        // than naming "light" and "dark" itself.
        global $CFG;
        // Category pictures come from local_nit_category; load it so the per-row
        // thumbnail below can resolve, but keep the tab working without it.
        if (file_exists($CFG->dirroot . '/local/nit_category/lib.php')) {
            require_once($CFG->dirroot . '/local/nit_category/lib.php');
        }

        $grouplabels = \theme_nit_brand_groups();
        $modelabels = [
            'light' => \get_string('modelight', 'theme_nit'),
            'dark' => \get_string('modedark', 'theme_nit'),
        ];
        $logoslots = \theme_nit_category_logo_slots();

        // One read of each map, rather than one per category.
        $catmaps = [];
        foreach (array_keys(\theme_nit_modes()) as $mode) {
            $catmaps[$mode] = \theme_nit_category_group_map($mode);
        }

        // The column headings, in the same order every row's cells come out in.
        // Built here rather than in the template because each one names a mode:
        // "Style — light mode", "Logo — dark mode".
        $catmodecolumns = [];
        foreach (array_keys($catmaps) as $mode) {
            $label = $modelabels[$mode] ?? $mode;
            $catmodecolumns[] = [
                'mode' => $mode,
                'label' => $label,
                'stylelabel' => \get_string('categorystyles_col_stylefor', 'theme_nit', $label),
                'logolabel' => \get_string('categorystyles_col_logofor', 'theme_nit', $label),
            ];
        }

        // Every file field this form renders, so the page can tell the server
        // which uploads were actually on their way — see the max_file_uploads
        // note on the pruning script in gallery.php.
        $catlogofields = [];

        $categorygroups = [];
        foreach (\core_course_category::top()->get_children() as $cat) {
            $modes = [];
            foreach ($catmaps as $mode => $map) {
                $current = $map[$cat->id] ?? '';
                if (!array_key_exists($current, $grouplabels)) {
                    $current = '';
                }
                // "Site default" first, and selected when nothing is assigned:
                // an unassigned category must leave its pages on the site's own
                // group for that mode, which is not the same answer as Group 1.
                $options = [[
                    'value' => '',
                    'label' => \get_string('categorystyles_sitedefault', 'theme_nit'),
                    'selected' => ($current === ''),
                ]];
                foreach ($grouplabels as $gkey => $glabel) {
                    $options[] = ['value' => $gkey, 'label' => $glabel, 'selected' => ($gkey === $current)];
                }

                $slot = $logoslots[$mode];
                $logo = \theme_nit_category_logo_url((int) $cat->id, $mode);
                $catlogofields[] = $slot['input'] . '_' . (int) $cat->id;
                $modes[] = [
                    'mode' => $mode,
                    'label' => $modelabels[$mode] ?? $mode,
                    'options' => $options,
                    'isdefault' => ($current === ''),
                    'select' => 'catgroup' . $mode . '[' . (int) $cat->id . ']',
                    'logoinput' => $slot['input'] . '_' . (int) $cat->id,
                    'logoremove' => $slot['remove'] . '[' . (int) $cat->id . ']',
                    'haslogo' => (bool) $logo,
                    'logourl' => $logo ? $logo->out(false) : '',
                ];
            }

            // The category's picture (local_nit_category), so this one screen shows
            // both halves of a category's branding — its palette and its image — and
            // an admin can see at a glance which categories are still missing one.
            $image = '';
            if (function_exists('local_nit_category_get_image_url')) {
                $image = \local_nit_category_get_image_url((int) $cat->id, false);
            }

            $categorygroups[] = [
                'id' => $cat->id,
                'name' => $cat->get_formatted_name(),
                'modes' => $modes,
                'image' => $image,
                'hasimage' => ($image !== ''),
                'imageurl' => (new \moodle_url('/local/nit_category/image.php', ['id' => $cat->id]))->out(false),
            ];
        }

        // Display mode → brand-group mapping for the "Site styles" section of the
        // same tab. This is what the navbar light/dark button switches between
        // OUTSIDE a styled category: the button holds no palette, it just puts
        // the chosen group's switch class on <html>. Same shape as the category
        // rows above (one selector per row, pre-set to the stored assignment)
        // because it is the same decision made about a different subject.
        $modegroups = [];
        foreach (\theme_nit_mode_groups() as $mode => $current) {
            $options = [];
            foreach ($grouplabels as $gkey => $glabel) {
                $options[] = ['value' => $gkey, 'label' => $glabel, 'selected' => ($gkey === $current)];
            }
            $modegroups[] = [
                'mode' => $mode,
                'label' => $modelabels[$mode] ?? $mode,
                'options' => $options,
            ];
        }

        // Per-language font slots: current filename (if any) + a live preview
        // that renders in the uploaded family the compiled CSS already exposes.
        $fonts = [];
        foreach (\theme_nit_font_slots() as $slot) {
            $filename = \get_config('theme_nit', $slot['setting']);
            $hasfont = is_string($filename) && $filename !== '';
            $fonts[] = [
                'input' => $slot['input'],
                'label' => \get_string($slot['strkey'], 'theme_nit'),
                'family' => $slot['family'],
                'sample' => \get_string($slot['samplekey'], 'theme_nit'),
                'rtl' => $slot['rtl'],
                'hasfont' => $hasfont,
                'filename' => $hasfont ? ltrim($filename, '/') : '',
            ];
        }

        // Account screens: the picture beside each of the two cards, plus the
        // quote drawn over it. The picture URL is the live one the compiled CSS
        // uses (theme_nit_auth_background_scss reads the same slot), so the
        // thumbnail on this tab is the thing itself and not a re-derivation of it.
        $theme = \theme_config::load('nit');
        $authimages = [];
        foreach (\theme_nit_auth_image_slots() as $key => $slot) {
            $filename = \get_config('theme_nit', $slot['setting']);
            $hasimage = is_string($filename) && $filename !== '';
            $authimages[] = [
                'key' => $key,
                'input' => $slot['input'],
                'label' => \get_string($slot['strkey'], 'theme_nit'),
                'description' => \get_string($slot['deskey'], 'theme_nit'),
                'hasimage' => $hasimage,
                'filename' => $hasimage ? ltrim($filename, '/') : '',
                'url' => $hasimage
                    ? (string) $theme->setting_file_url($slot['setting'], $slot['filearea'])
                    : '',
            ];
        }

        // One quote + attribution pair per language. `rtl` lets the template turn
        // the Arabic boxes round, so what an admin types looks like what a learner
        // will read.
        $authtexts = [];
        foreach (\theme_nit_auth_text_langs() as $lang => $meta) {
            $stored = \theme_nit_auth_text($lang);
            $authtexts[] = [
                'lang' => $lang,
                'label' => \get_string($meta['strkey'], 'theme_nit'),
                'rtl' => $meta['rtl'],
                'quote' => $stored['quote'],
                'author' => $stored['author'],
            ];
        }

        return [
            'sesskey' => sesskey(),
            'actionurl' => (new \moodle_url('/theme/nit/gallery.php'))->out(false),
            'brandgroups' => $brandgroups,
            'categorygroups' => $categorygroups,
            'catmodecolumns' => $catmodecolumns,
            'catlogofields' => implode(',', $catlogofields),
            'hascategorygroups' => !empty($categorygroups),
            'modegroups' => $modegroups,
            'fonts' => $fonts,
            'authimages' => $authimages,
            'authtexts' => $authtexts,
            'stats' => [
                ['label' => 'Active learners', 'value' => '1,284', 'trend' => '+12%', 'up' => true],
                ['label' => 'Course completions', 'value' => '842', 'trend' => '+5%', 'up' => true],
                ['label' => 'Overdue tasks', 'value' => '37', 'trend' => '-8%', 'up' => false],
            ],
        ];
    }
}
