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
     * The site footer band (AC-4.7.13).
     *
     * Called from theme_nit's override of theme_boost/footer as
     * `{{{ output.nit_site_footer }}}` - a Mustache template cannot read a plugin
     * setting on its own, so the one line the override adds to Boost's markup
     * comes through here. Returns an empty string when the footer is switched off
     * or its content plugin is absent, so the page is simply drawn without it.
     *
     * @return string HTML
     */
    public function nit_site_footer(): string {
        // The maintenance layout also renders Boost's footer partial, and it is
        // used during install and upgrade - when the database is mid-migration or
        // not there at all. Boost's own config.php spells out that this layout
        // must make no database or cache calls, and reading the footer's content
        // is exactly that, so it is the one page the band is left off.
        if ($this->page->pagelayout === 'maintenance' || during_initial_install()) {
            return '';
        }

        $context = theme_nit_get_site_footer_context($this);
        if ($context === null) {
            return '';
        }

        return $this->render_from_template('theme_nit/site_footer', $context);
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

        // §4.3 draws the reveal toggle inside the password box at every width,
        // the way the sign-up screen already does - a learner mistypes a password
        // on a laptop too. The site setting still decides WHETHER there is a
        // toggle (an administrator who turned it off still gets no toggle); its
        // "small screens only" option is a placement, and the placement on this
        // screen is the theme's.
        if (!empty($context->togglepassword)) {
            $context->smallscreensonly = false;
        }

        // AC-4.3.4: core settles on the generic "invalid login" wording before
        // the account state is re-read, so the attempt that actually trips the
        // lockout still reports nothing but a bad password. local_academy watched
        // that failure happen and left the finished sentence behind for us - the
        // judgement and the wording are its, this only puts the text on the page.
        // Guarded on the plugin, so a site without it still renders a login form.
        if (class_exists('\local_academy\lockout')) {
            $notice = \local_academy\lockout::take_pending_notice();
            if ($notice !== null) {
                $context->error = $notice;
                $context->errortitle = '';
            }
        }

        return $this->render_from_template('core/loginform', $context);
    }

    /**
     * Render the sign-up form, with the parts §4.1 asks for that core omits.
     *
     * The screen-elements table of §4.1 lists "Sign in with Google" and "Sign in
     * with Apple" as elements of this screen. Core's sign-up page has no concept of
     * identity providers at all - they exist only on the login page - so the
     * buttons cannot appear without being put here.
     *
     * Everything else in the context is core's; this adds the providers and the two
     * lines of copy the specification words, and leaves the form itself alone.
     *
     * @param \core_auth\output\login_signup_form $form the sign-up form renderable
     * @return string HTML
     */
    public function render_login_signup_form($form): string {
        global $SITE, $CFG;

        $context = $form->export_for_template($this);

        $url = $this->get_logo_url();
        $context['logourl'] = $url ? $url->out(false) : null;
        $context['sitename'] = format_string($SITE->fullname, true,
            ['context' => \context_course::instance(SITEID), 'escape' => false]);

        // The same providers the login screen offers, built the way login/index.php
        // builds them. A learner who signed up with Google must find that button in
        // both places, or they will create a second, password account by accident -
        // which AC-4.3.7 then has to reconcile.
        $providers = \auth_plugin_base::get_identity_providers(get_enabled_auth_plugins());
        $providers = \auth_plugin_base::prepare_identity_providers_for_output($providers, $this);

        $context['nitidentityproviders'] = $providers;
        $context['nithasproviders'] = !empty($providers);

        return $this->render_from_template('core/signup_form_layout', $context);
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

    /**
     * Render the "Management" group of the navbar gear menu.
     *
     * The academy's day-to-day management screens (coupons, offers,
     * subscriptions, the job form, the design gallery, site media) each live
     * under a different branch of Site administration, so reaching any one of
     * them is three or four clicks down a tree. They are the screens an
     * administrator opens every day, so the gear menu carries them directly, as
     * a second group beneath core's Navigation list.
     *
     * Every row is gated on the same capability the page itself requires, so a
     * user who could not open the page never sees the link; a row whose plugin
     * is not installed on the site is skipped entirely (its capability would
     * not exist to ask about). Returns '' when nothing survives that filter -
     * the group, heading and all, then simply is not there.
     *
     * @return string HTML, or '' when the user may see none of the links
     */
    public function navbar_management_menu(): string {
        $syscontext = \context_system::instance();

        // [component, capability, url, label string identifier, string component].
        $candidates = [
            ['local_nit_commerce', 'local/nit_commerce:managecoupons',
                '/local/nit_commerce/manage_coupons.php', 'managecoupons', 'local_nit_commerce'],
            ['local_nit_commerce', 'local/nit_commerce:manageoffers',
                '/local/nit_commerce/manage_offers.php', 'manageoffers', 'local_nit_commerce'],
            ['local_nit_subscriptions', 'local/nit_subscriptions:managesubscriptions',
                '/local/nit_subscriptions/manage_subscriptions.php', 'managesubscriptions', 'local_nit_subscriptions'],
            ['local_jobform', 'local/jobform:manage',
                '/local/jobform/manage.php', 'managejobform', 'local_jobform'],
            ['theme_nit', 'moodle/site:config',
                '/theme/nit/gallery.php', 'navgallery', 'theme_nit'],
            ['local_nit_media', 'moodle/site:config',
                '/admin/settings.php', 'pluginname', 'local_nit_media'],
            // Course purchases + the enrolment-source report (AC-4.10.5). Last in the group:
            // it is the screen that gets *read* rather than edited, so it sits after the
            // things an administrator goes to the menu to change.
            ['local_nit_subscriptions', 'local/nit_subscriptions:managesubscriptions',
                '/local/nit_subscriptions/manage_courses.php', 'managecourses', 'local_nit_subscriptions'],
        ];

        // Reading $PAGE->url on a page that never set one raises a developer
        // notice, and highlighting the current row is not worth that: without a
        // URL, nothing is simply marked active.
        $currentpath = $this->page->has_set_url() ? $this->page->url->get_path() : null;
        $currentsection = $this->page->has_set_url() ? $this->page->url->get_param('section') : null;
        $items = [];

        foreach ($candidates as [$component, $capability, $path, $identifier, $stringcomponent]) {
            // A site that does not run one of these plugins still gets a menu:
            // the row is skipped rather than asking about a capability that the
            // access definitions never installed.
            if (\core_component::get_component_directory($component) === null) {
                continue;
            }
            if (!has_capability($capability, $syscontext)) {
                continue;
            }

            // Site media has no page of its own - it is an admin settings
            // section, so it needs the query string that selects it.
            $params = $component === 'local_nit_media' ? ['section' => 'local_nit_media_settings'] : [];
            $url = new \moodle_url($path, $params);

            // /admin/settings.php is every settings page, so the section is what
            // tells them apart; for a page of its own the path is enough.
            $isactive = $currentpath === $url->get_path()
                && (empty($params['section']) || $currentsection === $params['section']);

            $items[] = [
                'text' => get_string($identifier, $stringcomponent),
                'url' => $url->out(false),
                'isactive' => $isactive,
            ];
        }

        if (empty($items)) {
            return '';
        }

        return $this->render_from_template('theme_nit/navbar_management_menu', [
            'heading' => get_string('navmanagement', 'theme_nit'),
            'items' => $items,
        ]);
    }

    /**
     * Render the one search control in the header (AC-4.22.1).
     *
     * The navbar carries a single box, and it searches the shop window: courses and
     * subject areas together, in both languages, whichever the interface is in. The
     * matching, the grouping and the counts all belong to local_nit_category — this is
     * only where the control is drawn, and where its script is asked for.
     *
     * The box is a plain GET form pointing at that plugin's results page, so it works
     * with scripting off and every search has its own address; the script adds the
     * preview panel on top of that.
     *
     * Returns '' when the catalogue plugin is not installed on the site: the theme is a
     * theme, and a search box that can only 404 is worse than no search box.
     *
     * @return string HTML, or '' when there is nothing to search with
     */
    public function navbar_site_search(): string {
        if (\core_component::get_component_directory('local_nit_category') === null) {
            return '';
        }

        // The results page prefills the box with what was searched for, so refining a
        // search means editing the words rather than retyping them. Read from the page
        // URL rather than the request: only a page that declared `q` its own means it.
        $query = '';
        if ($this->page->has_set_url()) {
            $query = (string) ($this->page->url->get_param('q') ?? '');
        }

        $this->page->requires->js(new \moodle_url('/local/nit_category/search.js'));

        return $this->render_from_template('theme_nit/navbar_site_search', [
            'action' => (new \moodle_url('/local/nit_category/search.php'))->out(false),
            'query' => $query,
        ]);
    }

    /**
     * Render the site's own links as titles across the navbar.
     *
     * The rows come from core's own setting — Site administration → Appearance →
     * Advanced theme settings → Custom menu items ($CFG->custommenuitems) — so
     * adding, renaming, reordering or removing a link is an administrator's job
     * and needs no code. One line per link:
     *
     *     الرئيسية|/
     *     الدورات|/local/nit_category/search.php?q=
     *
     * Only *this* method is needed on top of the setting, because the NIT navbar
     * collapses core's horizontal primary navigation into the gear dropdown and
     * never draws core's `moremenu`. Reading the setting here (rather than
     * picking the rows back out of `mobileprimarynav`) is what keeps the bar
     * showing the site's own links only, with Dashboard / My courses / Site
     * administration staying in the gear where the design put them.
     *
     * Parsing, per-language lines (`text|url|title|ar`) and the multilang
     * filtering are core's, mirrored from \core\navigation\output\primary so the
     * setting behaves here exactly as its help text describes. A nested line
     * (one leading `-`) becomes a dropdown under its parent.
     *
     * @return string HTML, or '' when the setting is empty
     */
    public function navbar_custom_menu(): string {
        global $CFG;

        if (empty($CFG->custommenuitems)) {
            return '';
        }

        $custommenuitems = $CFG->custommenuitems;

        // Same gate core uses: the labels only run through the string filters
        // when the admin asked for it, which is what makes {mlang} spans in a
        // menu line resolve (see local_nit_mlang / $CFG->stringfilters).
        if (!empty($CFG->navfilter) && !empty($CFG->stringfilters)) {
            $custommenuitems = \filter_manager::instance()
                ->filter_string($custommenuitems, \context_system::instance());
        }

        $nodes = \core\output\custom_menu::convert_text_to_menu_nodes($custommenuitems, current_language());
        if (empty($nodes)) {
            return '';
        }

        // Reading $PAGE->url on a page that never set one raises a developer
        // notice; without a URL nothing is simply marked as the current page.
        $currentpath = $this->page->has_set_url() ? $this->page->url->get_path() : null;

        $items = [];
        foreach ($nodes as $node) {
            $item = (array) $node->export_for_template($this);
            // A divider ("###") is a separator inside a dropdown; on a
            // horizontal bar it has nothing to separate, so it is dropped.
            if (!empty($item['divider'])) {
                continue;
            }
            $item['isactive'] = $this->navbar_custom_menu_is_active($item, $currentpath);
            $items[] = $item;
        }

        if (empty($items)) {
            return '';
        }

        return $this->render_from_template('theme_nit/navbar_custom_menu', ['items' => $items]);
    }

    /**
     * The core navigation rows of the gear dropdown.
     *
     * The gear is core's navigation and nothing else: Home, Dashboard, My
     * courses, Site administration. It deliberately does NOT use the template's
     * `mobileprimarynav`, because core merges the custom menu items into that
     * list (\core\navigation\output\primary::merge_primary_and_custom) — and
     * with the site's own links now drawn as titles on the bar, letting them
     * through here would show every one of them twice on a desktop screen.
     *
     * The mobile drawer keeps the merged list: below the `md` breakpoint the bar
     * links are hidden, so the drawer is the only place those links can be.
     *
     * Walks $PAGE->primarynav the same way core does, one level of children.
     *
     * @return string HTML, or '' when there is no navigation to show
     */
    public function navbar_gear_nav(): string {
        $items = $this->navbar_primary_nav_items($this->page->primarynav);
        if (empty($items)) {
            return '';
        }

        return $this->render_from_template('theme_nit/navbar_gear_nav', ['items' => $items]);
    }

    /**
     * Flatten a navigation node's children into template rows.
     *
     * @param \navigation_node $parent node whose children to export
     * @return array [{text, url, isactive, children, haschildren}]
     */
    protected function navbar_primary_nav_items($parent): array {
        $nodes = [];
        foreach ($parent->children as $node) {
            if (!$node->has_action() && empty($node->children)) {
                continue;
            }
            $children = $this->navbar_primary_nav_items($node);
            $activechildren = array_filter($children, fn($child) => !empty($child['isactive']));
            $action = $node->action();

            $nodes[] = [
                'text' => $node->text,
                'url' => $action ? $action->out(false) : '',
                'isactive' => $node->isactive || !empty($activechildren),
                'children' => $children,
                'haschildren' => !empty($children),
            ];
        }

        return $nodes;
    }

    /**
     * Is this navbar link the page the visitor is on?
     *
     * The path has to match, and so does every parameter the link carries a
     * value for. The parameters matter because the static pages are one script:
     * "من نحن" and "اتصل بنا" are both /local/profilefields/page.php and differ
     * only by `?page=`, so on a path-only test the whole pair lights up at once.
     *
     * A parameter the link leaves empty (`search.php?q=`) is not compared — that
     * line means "the courses page", not "a search for nothing", so it stays
     * marked while the visitor refines a search. Everything else about the
     * address is ignored, so a sesskey or an anchor changes nothing.
     *
     * A parent is active when one of its children is: the visitor is inside that
     * section either way.
     *
     * @param array $item exported custom menu node
     * @param string|null $currentpath path of the page being viewed, if it set a URL
     * @return bool
     */
    protected function navbar_custom_menu_is_active(array $item, ?string $currentpath): bool {
        if ($currentpath === null) {
            return false;
        }

        if (!empty($item['url'])) {
            $itempath = parse_url($item['url'], PHP_URL_PATH);
            if ($itempath !== null && $itempath !== false && $itempath === $currentpath) {
                parse_str((string) parse_url($item['url'], PHP_URL_QUERY), $itemparams);
                $matches = true;
                foreach ($itemparams as $name => $value) {
                    if ($value === '' || $value === null) {
                        continue;
                    }
                    if ((string) $this->page->url->get_param($name) !== (string) $value) {
                        $matches = false;
                        break;
                    }
                }
                if ($matches) {
                    return true;
                }
            }
        }

        foreach ($item['children'] ?? [] as $child) {
            if ($this->navbar_custom_menu_is_active((array) $child, $currentpath)) {
                return true;
            }
        }

        return false;
    }
}
