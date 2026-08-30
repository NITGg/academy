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

use local_nit_core\output\view_model;

/**
 * NIT core renderer — the "how to show" half of the rendering seam.
 *
 * Picked up automatically via theme_overridden_renderer_factory (set in
 * config.php). Extends Boost's renderer (NIT is a Boost child) so all of
 * Boost's renderer methods are inherited. Deliberately thin: it renders SDK
 * view-models and holds no business logic (Reference Architecture Rule 1).
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \theme_boost\output\core_renderer {
    /**
     * Render a NIT view-model through its (theme-overridable) template.
     *
     * @param view_model $viewmodel the view-model to render
     * @return string HTML
     */
    public function render_nit(view_model $viewmodel): string {
        return $this->render_from_template(
            $viewmodel->template_name(),
            $viewmodel->export_for_template($this)
        );
    }

    /**
     * Render the login form, telling the template whether to offer "Remember me".
     *
     * AC-4.3.5 puts a checkbox on this screen that core has no concept of. The
     * markup lives in theme_nit's override of core/loginform; this supplies the
     * one flag that decides whether it is drawn, because a Mustache template
     * cannot read a plugin setting on its own.
     *
     * Everything else about the context is core's - the parent builds it and this
     * adds one property - so a login screen change in a future Moodle release
     * arrives here intact.
     *
     * @param \core_auth\output\login $form the login form renderable
     * @return string HTML
     */
    public function render_login(\core_auth\output\login $form): string {
        $context = $form->export_for_template($this);

        // Guarded on the plugin being installed at all: theme_nit has to keep
        // rendering a login page on a site that does not run local_profilefields.
        $context->nitrememberme = class_exists('\local_profilefields\rememberme')
            && \local_profilefields\rememberme::enabled();

        return $this->render_from_template('core/loginform', $context);
    }

    /**
     * Render the standalone navbar language menu for every user.
     *
     * Core exposes the standalone language menu (primary::export_for_template)
     * only to logged-out/guest users; once logged in, the switcher is folded
     * into the user menu. The NIT navbar keeps a persistent language button
     * beside the brand (matching the legacy site), so it builds the menu here
     * regardless of login state. Returns '' when the menu should not show
     * (language menu disabled, or a single installed language).
     *
     * @return string HTML, or '' when there is nothing to show
     */
    public function navbar_language_menu(): string {
        $languagemenu = new \core\output\language_menu($this->page);
        $langmenu = $languagemenu->export_for_template($this);
        if (empty($langmenu)) {
            return '';
        }
        return $this->render_from_template('theme_boost/language_menu', $langmenu);
    }
}
