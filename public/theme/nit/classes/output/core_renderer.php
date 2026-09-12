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
     * The light/dark switch's click handler.
     *
     * Inline rather than a built AMD module because it is fifteen lines that
     * take all of their configuration from the button's own data attribute —
     * shipping it as amd/src + amd/build would mean a grunt run on every edit
     * for no gain. Everything mode-specific (which classes each mode owns, the
     * cookie name and path, the labels) is decided server-side in
     * navbar_mode_toggle() and read out of `data-nit-mode-config`.
     */
    /**
     * Swap the bar onto its scrolled brand group once the page moves.
     *
     * All this does is add one class to the bar and take it away again; WHICH
     * class is decided server-side (theme_nit_navbar_scroll_group()) and handed
     * over in `data-nit-scroll-group`, and the class itself is an ordinary brand
     * switch, so every navbar colour, size, shape and the glass follow it with
     * no further scripting. Nothing is emitted at all when a group does not name
     * a second one.
     *
     * The threshold is 8px, not a fraction of the viewport: the question is "has
     * the page moved", and Moodle's own `body.scrolled` answers a different one
     * (it waits a full screen — see theme/boost/amd/src/drawers.js).
     *
     * `passive` and the rAF gate keep this off the scroll critical path: the
     * listener does nothing but set a flag, and the class is touched once per
     * frame and only when the answer actually changed.
     */
    private const SCROLL_GROUP_JS = <<<'JS'
require([], function() {
    var bar = document.querySelector('[data-nit-scroll-group]');
    if (!bar) {
        return;
    }
    var group = bar.getAttribute('data-nit-scroll-group');
    var scrolled = null;
    var queued = false;
    var apply = function() {
        queued = false;
        var now = window.scrollY > 8;
        if (now === scrolled) {
            return;
        }
        scrolled = now;
        // Adding the class is the whole switch; removing it drops the bar back
        // to the group it inherits from <html>, which is where it started.
        bar.classList.toggle(group, now);
    };
    window.addEventListener('scroll', function() {
        if (!queued) {
            queued = true;
            window.requestAnimationFrame(apply);
        }
    }, {passive: true});
    apply();
});
JS;

    private const MODE_TOGGLE_JS = <<<'JS'
require([], function() {
    document.querySelectorAll('[data-nit-mode-toggle]').forEach(function(btn) {
        var config;
        try {
            config = JSON.parse(btn.getAttribute('data-nit-mode-config') || '{}');
        } catch (e) {
            return;
        }
        var swap = function(names, add) {
            (names || '').split(' ').filter(Boolean).forEach(function(name) {
                document.documentElement.classList[add ? 'add' : 'remove'](name);
            });
        };
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var current = btn.getAttribute('data-nit-mode') === 'dark' ? 'dark' : 'light';
            var next = current === 'dark' ? 'light' : 'dark';
            swap((config.classes || {})[current], false);
            swap((config.classes || {})[next], true);
            btn.setAttribute('data-nit-mode', next);
            var label = (config.labels || {})[next];
            if (label) {
                btn.setAttribute('aria-label', label);
                btn.setAttribute('title', label);
                var sr = btn.querySelector('.nit-mode-toggle-label');
                if (sr) {
                    sr.textContent = label;
                }
            }
            // The logos. Everything else the switch does is CSS and lands at
            // once; an <img src> was picked by the server, so without this the
            // colours flip and the mark stays on the previous mode's version
            // until the page is reloaded. Absent when the site has only one set.
            var logos = (config.logos || {})[next];
            if (logos) {
                if (logos.navbar) {
                    document.querySelectorAll(
                        '.nit-navbar-logo, .navbar.fixed-top .navbar-brand .logo,' +
                        ' .drawer-primary .drawerheader .logo, [data-nit-orbit-logo]'
                    ).forEach(function(img) {
                        img.src = logos.navbar;
                    });
                }
                if (logos.footer) {
                    document.querySelectorAll('.nit-sitefooter__logo').forEach(function(img) {
                        img.src = logos.footer;
                    });
                }
                // The opt-in hook for front-page HTML blocks. A block that draws
                // the brand mark adds data-nit-logo="full" (the wide lock-up,
                // window.NIT_LOGO_FULL) or "compact" (window.NIT_LOGO) to its
                // image and is swapped with everything else. Better than listing
                // each block's own selector here: a block is pasted content that
                // this file cannot see, and the two would fall out of step the
                // first time one was renamed.
                ['full', 'compact'].forEach(function(kind) {
                    if (!logos[kind]) {
                        return;
                    }
                    document.querySelectorAll('[data-nit-logo="' + kind + '"]').forEach(function(img) {
                        img.src = logos[kind];
                    });
                });
                if (logos.favicon) {
                    // "shortcut icon" is a space-separated list, which is what
                    // ~= matches on — a plain [rel="icon"] would miss it.
                    document.querySelectorAll('link[rel~="icon"]').forEach(function(link) {
                        link.href = logos.favicon;
                    });
                }
            }
            // A year, so the choice survives the browser being closed. Nothing
            // personal goes in it - it holds the string "light" or "dark".
            document.cookie = config.cookie + '=' + next
                + ';path=' + (config.path || '/')
                + ';max-age=31536000;samesite=Lax'
                + (config.secure ? ';secure' : '');
        });
    });
});
JS;

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
        //
        // The `login` layout (log in, sign up, forgot password, confirm) is left
        // off too, but for a design reason rather than a technical one: those
        // screens are a full-height two-panel composition with the picture panel
        // running to the bottom of the viewport, and a band of link columns
        // underneath it turns that into a scrolling page for no gain. The links
        // it carries are all reachable from the site the visitor lands on after
        // signing in.
        $footerlesslayouts = ['maintenance', 'login'];
        if (in_array($this->page->pagelayout, $footerlesslayouts, true) || during_initial_install()) {
            return '';
        }

        // The Site home may have the band switched off by "Home page chrome" on
        // the gallery's Change style tab (theme_nit_home_chrome()). This page
        // only — and never while editing, in step with the navbar rule in
        // layout/frontpage.php, which also puts the `nit-home-nofooter` body
        // class on for the popover's box.
        if ($this->page->pagelayout === 'frontpage' && !$this->page->user_is_editing()
                && !theme_nit_home_chrome()['footer']) {
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
     * On a two-language site — which the academy is, English and Arabic — the
     * control is not a menu at all but a single button that swaps to the other
     * language in one click: a dropdown that only ever offers one choice makes
     * the visitor open a list to pick the only thing in it. The button is
     * labelled with the language it switches TO (the site in English shows
     * "AR"), because it is now an action rather than a status. Three or more
     * installed languages fall back to core's dropdown, which is the right
     * control once there is a real choice to make.
     *
     * @return string HTML, or '' when there is nothing to show
     */
    public function navbar_language_menu(): string {
        $languagemenu = new \core\output\language_menu($this->page);
        $langmenu = $languagemenu->export_for_template($this);
        if (empty($langmenu)) {
            return '';
        }

        // The languages the visitor is not currently reading in. Core marks the
        // active one with `isactive` and points it at '#'.
        $others = [];
        foreach ($langmenu['items'] ?? [] as $item) {
            if (empty($item['isactive'])) {
                $others[] = $item;
            }
        }

        // Exactly one alternative → a direct swap button, no menu.
        if (count($others) === 1) {
            $target = $others[0];
            // The code of the language the button switches to ("AR" / "EN"). The
            // URL core built for that language carries it as ?lang=xx.
            $langcode = $target['url'] instanceof \moodle_url
                ? (string) $target['url']->get_param('lang')
                : '';
            $label = strtoupper(explode('_', $langcode)[0]);

            return $this->render_from_template('theme_nit/navbar_lang_toggle', [
                'url' => $target['url'] instanceof \moodle_url
                    ? $target['url']->out(false)
                    : (string) $target['url'],
                'label' => $label,
                // The full name of the target language ("العربية"), for the
                // tooltip and for screen readers — two letters alone say very
                // little when read aloud.
                'title' => $target['title'] ?? $label,
                'langcode' => $langcode,
            ]);
        }

        // Three or more languages: core's dropdown. The bar shows the two-letter
        // code ("EN" / "AR"), not the full language name core exports ("English
        // (en)"). That is what the design sets there, and the long form was the
        // widest thing in the right cluster — on a 1280px laptop it pushed the
        // avatar off the bar. Only the button label changes: the dropdown still
        // lists every language under its own full name, and the anchor carries
        // aria-label="Language", so a screen reader reads the control the same
        // way either way.
        $langmenu['title'] = strtoupper(explode('_', current_language())[0]);

        return $this->render_from_template('theme_boost/language_menu', $langmenu);
    }

    /**
     * The attribute that tells the bar which group to wear once the page scrolls.
     *
     * A bar sitting on top of a hero and a bar floating over scrolled content are
     * two different design problems, so a group can name a SECOND group for the
     * bar to switch to the moment the page moves (gallery → Brand Colors → Navbar
     * → On scroll). What comes back is one attribute holding that group's switch
     * class; the class does the rest, because every navbar colour, size, shape
     * and the glass are declared on `.nit-navbar` as well as on `:root` (see
     * scss/foundation/_root.scss) and so re-resolve against whatever group the
     * BAR is wearing — not the page's.
     *
     * Emitted as a raw attribute string rather than a template variable because
     * the navbar template is a `theme_boost/navbar` override: keeping the change
     * to one `{{{ }}}` in the `<nav>` tag keeps the override diff a single line.
     *
     * Returns '' — and requires no script at all — when the group does not name a
     * second one, which is the default and the overwhelmingly common case.
     *
     * @return string an HTML attribute, with a leading space, or ''
     */
    public function navbar_scroll_attributes(): string {
        $resting = \theme_nit_active_chrome_group();
        $scrolled = \theme_nit_navbar_scroll_group($resting);
        if ($scrolled === $resting) {
            // The overwhelmingly common answer. No attribute, no listener, no
            // class — a bar that does not change on scroll costs nothing.
            return '';
        }

        // Group 1's switch class exists for exactly this: without it, "scroll
        // into Group 1" would be the one combination that could not be said.
        $class = \theme_nit_brand_group_class($scrolled) ?: 'nit-brand-1';
        $this->page->requires->js_amd_inline(self::SCROLL_GROUP_JS);

        return ' data-nit-scroll-group="' . s($class) . '"';
    }

    /**
     * Render the navbar light/dark switch.
     *
     * The button does not carry a palette of its own: light and dark each map to
     * one of the Brand-Colors groups (assigned on the gallery "Change style"
     * tab), and switching mode swaps that group's switch class on the <html>
     * element. Because a group switch is nothing but a set of CSS custom
     * properties, the swap re-skins the page instantly in the browser — no
     * reload, and no flash of the other palette.
     *
     * The choice is remembered in a cookie rather than a user preference, so it
     * works for the logged-out catalogue too, and so the next request can render
     * the right mode server-side (theme_nit\local\hook_callbacks
     * ::before_html_attributes) instead of letting JavaScript repaint after load.
     *
     * Inside a styled category the two groups are the CATEGORY's own pair, not
     * the site's — a category is branded per mode, so the switch stays useful
     * there and moves between that category's light and dark looks. (It used to
     * be hidden on those pages, back when a category had one style and a click
     * could only have taken the visitor out of it.)
     *
     * Returns '' when both modes resolve to the same group AND to the same logo:
     * then the button would change nothing, and a control that does nothing is
     * worse than no control.
     *
     * @return string HTML, or '' when the switch would be a no-op
     */
    public function navbar_mode_toggle(): string {
        global $CFG;

        require_once($CFG->dirroot . '/theme/nit/lib.php');

        $mode = theme_nit_current_mode();

        // The <html> classes each mode owns, handed to the browser so the click
        // handler can swap one set for the other without asking the server.
        $classes = [];
        foreach (array_keys(theme_nit_modes()) as $key) {
            // Built by the same function the server used for this page's <html>,
            // so the classes the button applies can never drift from the ones
            // that were rendered — including `nit-chrome-light`, which is what
            // tells CSS the bar is light.
            $classes[$key] = theme_nit_mode_classes_for($key);
        }

        // The logos each mode wants. Everything else about the switch is CSS, so
        // it changes under the visitor's finger — but a picture is an `src`, and
        // the server had already chosen one by the time the page arrived. Without
        // this the palette flipped instantly and the logo stayed on the old one
        // until a reload, which reads as the switch being half-broken.
        //
        // Three sizes because three places ask for the mark differently: the bar
        // and the drawer at core's 300x300 default, the footer at 0x120
        // (theme_nit_get_site_footer_context), and the browser tab.
        $logos = [];
        $out = static fn($url) => $url ? $url->out(false) : '';
        foreach (['light', 'dark'] as $key) {
            // The sizes each place asks for, so a swapped URL is byte-identical
            // to what a reload would have produced and the browser reuses the
            // file it already has instead of fetching a second rendition.
            //   navbar  300x300 — core's default (navbar, drawer, orbit centre)
            //   footer    0x120 — theme_nit_get_site_footer_context()
            //   compact   0x200 — window.NIT_LOGO, for front-page blocks
            //   full      0x300 — window.NIT_LOGO_FULL, the wide lock-up
            $logos[$key] = [
                'navbar' => $out(theme_nit_logo_url('logocompact', 300, 300, $key)),
                'footer' => $out(theme_nit_logo_url('logocompact', 0, 120, $key)),
                'compact' => $out(theme_nit_logo_url('logocompact', 0, 200, $key)
                    ?: theme_nit_logo_url('logo', 0, 200, $key)),
                'full' => $out(theme_nit_logo_url('logo', 0, 300, $key)
                    ?: theme_nit_logo_url('logocompact', 0, 300, $key)),
                'favicon' => $out(theme_nit_logo_url('favicon', 0, 0, $key)),
            ];
        }
        // A site with one logo gets no logo payload at all: identical maps mean
        // there is nothing to swap, and the handler should not touch an `src` it
        // would only rewrite to the same value.
        $samelogos = ($logos['light'] === $logos['dark']);
        if ($samelogos) {
            $logos = null;
        }

        // Nothing left to change: this page renders in the same group and draws
        // the same mark either way. Asked of theme_nit_active_chrome_group()
        // rather than of theme_nit_mode_groups(), because inside a styled
        // category the pair being compared is that category's, not the site's.
        // The two groups, not the two class lists: those always differ by
        // `nit-mode-light` / `nit-mode-dark`, which is a hook, not a look.
        if (theme_nit_active_chrome_group('light') === theme_nit_active_chrome_group('dark')
                && $samelogos) {
            return '';
        }

        $config = [
            'classes' => $classes,
            // Each mode's label names the mode the button switches TO, matching
            // what the icon shows.
            'labels' => [
                'light' => get_string('modeswitchtodark', 'theme_nit'),
                'dark' => get_string('modeswitchtolight', 'theme_nit'),
            ],
            'logos' => $logos,
            'cookie' => THEME_NIT_MODE_COOKIE,
            // Scope the cookie to the Moodle install, so a site under /moodle
            // does not write a cookie the whole domain has to carry.
            'path' => empty($CFG->sessioncookiepath) ? '/' : $CFG->sessioncookiepath,
            'secure' => (strpos($CFG->wwwroot, 'https://') === 0),
        ];

        $this->page->requires->js_amd_inline(self::MODE_TOGGLE_JS);

        return $this->render_from_template('theme_nit/navbar_mode_toggle', [
            'mode' => $mode,
            'label' => $config['labels'][$mode],
            'config' => json_encode($config),
        ]);
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
    /**
     * The full site logo, for the mode this page renders in.
     *
     * Overriding core's accessor rather than adding a second one means every
     * existing caller — the log-in card, the sign-up card, the site footer —
     * becomes mode-aware without being touched. Falls through to core whenever
     * nothing is configured to swap, so a site that never fills the new fields
     * behaves exactly as it did.
     *
     * @param int|null $maxwidth
     * @param int $maxheight
     * @return moodle_url|false
     */
    public function get_logo_url($maxwidth = null, $maxheight = 200) {
        global $CFG;

        require_once($CFG->dirroot . '/theme/nit/lib.php');
        $url = theme_nit_logo_url('logo', (int) $maxwidth, (int) $maxheight);

        return $url ?: parent::get_logo_url($maxwidth, $maxheight);
    }

    /**
     * The browser-tab icon, for the mode this page renders in.
     *
     * Same reasoning as get_logo_url() above. Core falls back to the theme's own
     * favicon image when the site has uploaded none; that path is left to core.
     *
     * @return moodle_url
     */
    public function favicon() {
        global $CFG;

        require_once($CFG->dirroot . '/theme/nit/lib.php');
        $url = theme_nit_logo_url('favicon');

        return $url ?: parent::favicon();
    }

    /**
     * The compact (navbar) logo, for the mode this page renders in.
     *
     * The third and last of the overridden logo accessors. Taking core's method
     * over — rather than adding a NIT-only one and teaching each template to
     * call it — is the whole point: anything that asks Moodle for the site's
     * compact logo gets the right one, including the parts of core and of other
     * plugins that this theme will never touch.
     *
     * Chosen on the server rather than by shipping both images and hiding one in
     * CSS, because the browser would download both and the wrong one would flash
     * before the stylesheet applied.
     *
     * @param int $maxwidth
     * @param int $maxheight
     * @return moodle_url|false
     */
    public function get_compact_logo_url($maxwidth = 300, $maxheight = 300) {
        global $CFG;

        require_once($CFG->dirroot . '/theme/nit/lib.php');
        $url = theme_nit_logo_url('logocompact', (int) $maxwidth, (int) $maxheight);

        return $url ?: parent::get_compact_logo_url($maxwidth, $maxheight);
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
     * Paths are compared through navbar_path_key(), not as raw strings, because
     * the front page has two spellings and core and the administrator each pick
     * a different one — see that method.
     *
     * @param array $item exported custom menu node
     * @param string|null $currentpath path of the page being viewed, if it set a URL
     * @return bool
     */
    protected function navbar_custom_menu_is_active(array $item, ?string $currentpath): bool {
        if ($currentpath === null) {
            return false;
        }
        $currentkey = self::navbar_path_key($currentpath);

        if (!empty($item['url'])) {
            $itempath = parse_url($item['url'], PHP_URL_PATH);
            if ($itempath !== null && $itempath !== false
                    && self::navbar_path_key($itempath) === $currentkey) {
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

    /**
     * One path, in the one spelling two spellings of the same page share.
     *
     * The site front page is `/` AND `/index.php`, and which one you get depends
     * on who wrote it down. Core's own front page sets its URL to the first
     * (`$PAGE->set_url('/')`, public/index.php); an administrator typing a menu
     * line writes the second, because that is what the address bar shows them
     * after they click it. Compared as raw strings the two never matched, so the
     * home link was the one row on the bar that could never be the current page —
     * no active colour, no active shape, on the page most visitors land on.
     *
     * A trailing slash is folded for the same reason: `/local/x/` and
     * `/local/x` are one directory, and only one of them will be written down.
     *
     * Both sides of every comparison go through this, so the fold cannot make two
     * genuinely different pages match — it only removes spellings.
     *
     * @param string $path a URL path
     * @return string a comparable key
     */
    protected static function navbar_path_key(string $path): string {
        $path = preg_replace('~/index\.php$~', '/', $path);
        return rtrim($path, '/') . '/';
    }
}
