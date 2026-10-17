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

    /** The core user-menu entries the administrator may take off (theme_nit/usermenuhide). */
    const USERMENU_HIDEABLE = ['preferences', 'language', 'switchroleto'];

    /**
     * Which of USERMENU_HIDEABLE are switched off. A site that has not saved the
     * setting yet gets the default, which is all of them: Profile and Log out only.
     *
     * @return string[]
     */
    public static function usermenu_hidden(): array {
        $value = get_config('theme_nit', 'usermenuhide');
        if ($value === false) {
            return self::USERMENU_HIDEABLE;
        }
        return array_values(array_intersect(self::USERMENU_HIDEABLE, explode(',', (string) $value)));
    }

    /**
     * Core's user menu without the entries the administrator switched off.
     *
     * Core appends Preferences, Language, Switch role to… and Log out in code
     * (user_get_user_navigation_info and the parent of this method), after the
     * `customusermenuitems` lines, so that setting can never remove them. Log out
     * — and the "Return to my normal role" / log-in-as revert link that replaces
     * it — always stays, so nobody can be locked into a session.
     *
     * The dividers are rebuilt afterwards: core puts one before the second-to-last
     * row, and with rows gone that would land in the wrong place or twice.
     *
     * @param renderer_base $output
     * @return array same shape as core's
     */
    public function get_user_menu(renderer_base $output): array {
        $menu = parent::get_user_menu($output);
        $hidden = self::usermenu_hidden();
        if (empty($menu['items']) || empty($hidden)) {
            return $menu;
        }

        $removedsubmenus = [];
        $items = [];
        foreach ($menu['items'] as $item) {
            $item = clone $item;
            if (!empty($item->submenulink)) {
                if (in_array('language', $hidden)) {
                    $removedsubmenus[] = $item->submenuid;
                    continue;
                }
            } else if (isset($item->titleidentifier)) {
                $key = explode(',', $item->titleidentifier)[0];
                if (in_array($key, $hidden)) {
                    continue;
                }
            }
            if ($item->itemtype === 'divider') {
                // Fold a stand-alone divider into the row above it.
                if ($items) {
                    $items[count($items) - 1]->divider = true;
                }
                continue;
            }
            $item->divider = false;
            $items[] = $item;
        }
        if (count($items) >= 2) {
            $items[count($items) - 2]->divider = true;
        }
        if ($items) {
            $items[count($items) - 1]->divider = false;
        }

        $menu['items'] = $items;
        $menu['submenus'] = array_values(array_filter($menu['submenus'] ?? [], function($submenu) use ($removedsubmenus) {
            return !in_array($submenu->id, $removedsubmenus);
        }));
        return $menu;
    }
}
