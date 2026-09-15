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
use theme_nit\local\gear_menu;

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
    var buttons = document.querySelectorAll('[data-nit-mode-toggle]');
    buttons.forEach(function(btn) {
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
            var label = (config.labels || {})[next];
            // EVERY switch on the page, not only the one clicked. The Site home
            // draws a second copy inside the hero block (window.NIT_MODE_TOGGLE)
            // for when the bar is hidden there, and while editing both are on
            // the page at once - left alone, the other one would still show the
            // old mode's icon and flip the page back on its next click.
            buttons.forEach(function(other) {
                other.setAttribute('data-nit-mode', next);
                if (label) {
                    other.setAttribute('aria-label', label);
                    other.setAttribute('title', label);
                    var sr = other.querySelector('.nit-mode-toggle-label');
                    if (sr) {
                        sr.textContent = label;
                    }
                }
            });
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

        // A visitor who is ALREADY the guest and came back to this screen. Core
        // hides "Access as a guest" then (`canloginasguest` is false for the
        // guest user - logging the guest in again is a no-op), and the utility
        // line lost its way back into the site. In its place, a plain link home:
        // same spot, same weight, and it does what the visitor wanted from the
        // button that vanished.
        [$context->nitisguest, $context->nithomeurl] = $this->guest_continue_context();

        // The light/dark switch, beside the language menu in the utility line.
        // The log-in layout has no navigation bar to carry the site's, and the
        // mode is a cookie, so what is chosen here is what the site opens in.
        $context->nitmodetoggle = $this->auth_mode_toggle_context();

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

        // The utility line the log-in card ends with - the language switcher and
        // guest access - built exactly the way core_auth\output\login builds it
        // for the log-in page. Core's sign-up renderable knows nothing of either,
        // so a visitor who landed on sign-up in the wrong language had to go back
        // to log-in to change it. The guest form POSTs to login/index.php with
        // the session's login token, the same as it does from the log-in card.
        $languagemenu = new \core\output\language_menu($this->page);
        $context['languagemenu'] = $languagemenu->export_for_action_menu($this);
        $context['canloginasguest'] = !empty($CFG->guestloginbutton) && !isguestuser();
        $context['loginurl'] = (new \moodle_url('/login/index.php'))->out(false);
        $context['logintoken'] = \core\session\manager::get_login_token();
        // Already the guest: a link home stands in for the button (see render_login()).
        [$context['nitisguest'], $context['nithomeurl']] = $this->guest_continue_context();
        // The light/dark switch, the same as the log-in card's (see render_login()).
        $context['nitmodetoggle'] = $this->auth_mode_toggle_context();

        return $this->render_from_template('core/signup_form_layout', $context);
    }

    /**
     * The "Continue as guest" link the account screens show to a visitor who is
     * already browsing as the guest.
     *
     * Core's guest button is hidden for the guest user itself (the POST would
     * only log the guest in again), so the screen offered that visitor no way
     * back into the site short of the browser's Back button. Shown only when the
     * site offers guest access at all: a site with `guestloginbutton` off never
     * had the button, and should not grow a link in its place.
     *
     * @return array [bool show, string home URL]
     */
    protected function guest_continue_context(): array {
        global $CFG;

        $show = !empty($CFG->guestloginbutton) && isloggedin() && isguestuser();
        return [$show, (new \moodle_url('/'))->out(false)];
    }

    /**
     * The one-click language swap of a two-language site, as template context.
     *
     * The half of navbar_language_menu() that is also wanted OFF the bar: the
     * Site home may hide the navbar altogether ("Home page chrome"), and the
     * hero block then draws its own language button in the corner. That button
     * has to switch exactly the way the bar's does — same URL, same label, same
     * accessible name — so the front-page layout prints this very array as
     * window.NIT_LANG_TOGGLE rather than letting the block guess a ?lang= URL
     * (which would be wrong for a language pack code with a region, and
     * would not know the target language's own name for the tooltip).
     *
     * Null when the control is not a swap: no language menu at all, or three or
     * more installed languages, where the bar falls back to core's dropdown and
     * the hero simply shows no language control.
     *
     * @param array|null $langmenu core's exported language menu, when the caller
     *                             already has it; built here otherwise
     * @return array|null url, label ("AR"), title ("العربية (ar)"), langcode ("ar")
     */
    public function lang_toggle_context(?array $langmenu = null): ?array {
        if ($langmenu === null) {
            $languagemenu = new \core\output\language_menu($this->page);
            $langmenu = $languagemenu->export_for_template($this);
        }
        if (empty($langmenu)) {
            return null;
        }

        // The languages the visitor is not currently reading in. Core marks the
        // active one with `isactive` and points it at '#'.
        $others = [];
        foreach ($langmenu['items'] ?? [] as $item) {
            if (empty($item['isactive'])) {
                $others[] = $item;
            }
        }
        if (count($others) !== 1) {
            return null;
        }

        $target = $others[0];
        // The code of the language the button switches to ("AR" / "EN"). The
        // URL core built for that language carries it as ?lang=xx.
        $langcode = $target['url'] instanceof \moodle_url
            ? (string) $target['url']->get_param('lang')
            : '';
        $label = strtoupper(explode('_', $langcode)[0]);

        return [
            'url' => $target['url'] instanceof \moodle_url
                ? $target['url']->out(false)
                : (string) $target['url'],
            'label' => $label,
            // The full name of the target language ("العربية"), for the
            // tooltip and for screen readers — two letters alone say very
            // little when read aloud.
            'title' => $target['title'] ?? $label,
            'langcode' => $langcode,
        ];
    }

    /**
     * Pages a visitor is never sent back to after logging in.
     *
     * Path prefixes, compared against the page's path under wwwroot. The
     * account screens themselves (log in, sign up, forgot password, the
     * confirmation page), our own sign-up notice and the profile completion
     * gate are all steps *of* logging in, not places to return to; a visitor
     * who logs in from one of them gets core's default landing instead.
     */
    const LOGIN_NO_RETURN_PATHS = [
        '/login/',
        '/local/profilefields/verify.php',
        '/local/profilefields/complete.php',
    ];

    /**
     * The "Log in" link on the navbar: the login page, told where to come back to.
     *
     * A visitor who clicks Log in on a course, a category, a static page should
     * land back on that page once signed in - and once a sign-up begun from
     * there is confirmed. Core has the mechanism ($SESSION->wantsurl, which
     * login/index.php honours, signup.php keeps, auth_email carries across the
     * confirmation email and confirm.php restores) but not the intent: the
     * login page only guesses the page from the HTTP referer, and only when
     * nothing is already in wantsurl. Both fail the visitor here. A referer is
     * an optional header; and wantsurl is pinned by every "log in first" door
     * on the site (a locked course, enrol/index.php) and survives the visitor
     * walking away from it, so a later Log in from the About page still lands
     * on the course they looked at an hour earlier.
     *
     * So the link says it outright: `nitreturn=` carries the current page, and
     * theme_nit's after_config hook writes it into $SESSION->wantsurl on the
     * login page, ahead of anything stale. The parameter is ours - core's
     * `wantsurl=` on that page is honoured only under Behat.
     *
     * Only a visitor gets the parameter (a logged-in user has no Log in link),
     * and only from a page worth returning to: not the front page, where core's
     * own choice between the front page and the dashboard is the better answer,
     * and none of {@see self::LOGIN_NO_RETURN_PATHS}.
     *
     * @return string the login URL, HTML-escaped for an href
     */
    public function navbar_login_url(): string {
        $login = new \moodle_url(get_login_url());

        $return = $this->login_return_url();
        if ($return !== null) {
            $login->param('nitreturn', $return);
        }

        return $login->out();
    }

    /**
     * The page the current visitor should come back to after logging in, as a
     * site-relative URL ("/course/view.php?id=5") - or null when there is none.
     *
     * @return string|null
     */
    protected function login_return_url(): ?string {
        if (isloggedin() && !isguestuser()) {
            return null;
        }
        // A page that never declared its URL (core prints a debugging notice
        // when one is read) and the error page count as nowhere in particular.
        if (!$this->page->has_set_url()) {
            return null;
        }

        try {
            $local = $this->page->url->out_as_local_url(false);
        } catch (\Throwable $e) {
            // Not under wwwroot; nothing to return to.
            return null;
        }

        $path = (string) parse_url($local, PHP_URL_PATH);
        if ($local === '' || $path === '' || $path === '/' || $path === '/index.php') {
            return null;
        }
        foreach (self::LOGIN_NO_RETURN_PATHS as $prefix) {
            if (strpos($path, $prefix) === 0) {
                return null;
            }
        }

        return $local;
    }

    /**
     * The footer's "You are not logged in. (Log in)" line, with its link
     * pointed where the navbar's is, so the two ways off a page agree on where
     * the visitor comes back to.
     *
     * Core builds the line as a string around get_login_url(); the link is the
     * only part that changes, so the string is edited rather than rebuilt.
     *
     * @param bool|null $withlinks see core
     * @return string HTML
     */
    public function login_info($withlinks = null) {
        $html = parent::login_info($withlinks);

        $target = $this->navbar_login_url();
        $plain = get_login_url();
        if ($target !== $plain) {
            $html = str_replace('href="' . $plain . '"', 'href="' . $target . '"', $html);
        }

        return $html;
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

        // Exactly one alternative → a direct swap button, no menu.
        $toggle = $this->lang_toggle_context($langmenu);
        if ($toggle !== null) {
            return $this->render_from_template('theme_nit/navbar_lang_toggle', $toggle);
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
        $toggle = $this->mode_toggle_context();
        if ($toggle === null) {
            return '';
        }

        return $this->render_from_template('theme_nit/navbar_mode_toggle', [
            'mode' => $toggle['mode'],
            'label' => $toggle['label'],
            'config' => json_encode($toggle['config']),
        ]);
    }

    /**
     * The light/dark switch for the account screens, as template context.
     *
     * The log-in layout has no navigation bar, so the switch the bar carries
     * everywhere else is missing from exactly the screens a visitor meets
     * first — and the mode is a cookie, not a preference, so a choice made
     * here is the mode the whole site opens in once they are through. The
     * log-in and sign-up cards draw it in their utility line, beside the
     * language menu, through the theme_nit/auth_mode_toggle partial.
     *
     * The same context as the bar's button (mode_toggle_context(): same
     * classes, same cookie, same click handler), with `config` already encoded
     * the way the template's data attribute wants it. Null when the switch
     * would change nothing, and the templates then draw no control.
     *
     * @return array|null mode, label, config (JSON string) — or null
     */
    public function auth_mode_toggle_context(): ?array {
        $toggle = $this->mode_toggle_context();
        if ($toggle === null) {
            return null;
        }
        $toggle['config'] = json_encode($toggle['config']);
        return $toggle;
    }

    /**
     * Whether the light/dark click handler has been queued for this page.
     *
     * The handler binds every `[data-nit-mode-toggle]` on the page in one go, so
     * it must be queued exactly once however many switches ask for it — the bar's
     * and the hero block's both do on an edited Site home. Queued twice it would
     * bind twice, and one click would flip the mode there and straight back.
     *
     * @var bool
     */
    private bool $modetogglejsqueued = false;

    /**
     * Everything the light/dark switch needs, as data, and its click handler queued.
     *
     * The working half of navbar_mode_toggle(). Split out because the switch is
     * wanted in TWO places: the bar, which renders it through the template, and
     * the Site home's hero block, which draws its own button when "Home page
     * chrome" hides the bar and takes this same array from
     * window.NIT_MODE_TOGGLE (layout/frontpage.php). One source means the two
     * buttons can never disagree about which classes a mode owns, which logos
     * it wants, or what the cookie is called.
     *
     * Calling this queues the click handler, so a page that prints the array
     * gets a working button with no script of its own — the block only has to
     * copy `config` onto `data-nit-mode-config`.
     *
     * @return array|null mode ("light"/"dark"), label (the mode it switches TO),
     *                    config (array; see the handler) — or null when the
     *                    switch would change nothing on this page
     */
    public function mode_toggle_context(): ?array {
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
            return null;
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

        if (!$this->modetogglejsqueued) {
            $this->page->requires->js_amd_inline(self::MODE_TOGGLE_JS);
            $this->modetogglejsqueued = true;
        }

        return [
            'mode' => $mode,
            'label' => $config['labels'][$mode],
            'config' => $config,
        ];
    }

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
     * A chart, on the brand.
     *
     * Core's charts are canvases: no stylesheet reaches them, and both of the
     * places they take colour from are blind to the palette — `core/chart_base`
     * hands each series a colour from a fixed built-in list, and Chart.js paints
     * its legend, ticks and grid in its own greys. So before the chart is built,
     * theme_nit/chart_brand rewires both from the live `--nit-brand-*` custom
     * properties (whichever group the switch or a category style put on
     * `<html>`). It is queued here, ahead of the `{{#js}}` block core's chart
     * template adds, so it always runs first; the chart itself is core's, unchanged.
     *
     * @param \core\chart_base $chart The chart.
     * @param bool $withtable Whether to include a data table with the chart.
     * @return string
     */
    public function render_chart(\core\chart_base $chart, $withtable = true) {
        $this->page->requires->js_call_amd('theme_nit/chart_brand', 'init');

        return parent::render_chart($chart, $withtable);
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
     *     الدورات|/local/nit_category/catalogue.php?q=
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
            if ($this->navbar_link_hidden_from_visitor($item)) {
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
     * Pages a custom menu line may point at that a visitor has no business seeing.
     *
     * The custom menu syntax (`text|url|title|lang`) has no notion of who a line
     * is for, and the calendar is only meaningful once there is a user to have
     * events — a visitor who follows it lands on the login page. Paths are
     * matched as prefixes against the link's path.
     */
    const VISITOR_HIDDEN_PATHS = ['/calendar/'];

    /**
     * Whether one custom menu row is kept off the navbar for the current viewer.
     *
     * Applies to a visitor only: not logged in, or logged in as the guest
     * account. Every real account sees every line the administrator wrote.
     *
     * @param array $item an exported custom menu node ({text, url, ...})
     * @return bool true to drop the row
     */
    protected function navbar_link_hidden_from_visitor(array $item): bool {
        if (isloggedin() && !isguestuser()) {
            return false;
        }
        if (empty($item['url'])) {
            return false;
        }
        $path = parse_url((string) $item['url'], PHP_URL_PATH);
        if ($path === null || $path === false) {
            return false;
        }
        // A link written relative to the site ("/calendar/view.php") and one
        // written in full both end up compared as the path under wwwroot.
        global $CFG;
        $root = (string) parse_url($CFG->wwwroot, PHP_URL_PATH);
        if ($root !== '' && $root !== '/' && strpos($path, $root) === 0) {
            $path = substr($path, strlen($root));
        }
        foreach (self::VISITOR_HIDDEN_PATHS as $prefix) {
            if (strpos($path, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * The mobile drawer's rows: core's merged primary + custom list, minus what a
     * visitor should not see.
     *
     * Core merges the custom menu lines into `mobileprimarynav`
     * (\core\navigation\output\primary::merge_primary_and_custom) inside the
     * layout, where the theme cannot get between the setting and the template.
     * The drawer template (theme_nit's copy of theme_boost/primary-drawer-mobile)
     * therefore iterates this method instead of the layout's variable, so the
     * same gate that keeps a row off the bar keeps it out of the drawer.
     *
     * @return array the rows, in the shape the drawer template expects
     */
    public function navbar_mobile_primary_nav(): array {
        $primary = new primary($this->page);
        $rows = [];
        foreach ($primary->mobile_rows($this) as $row) {
            $row = (array) $row;
            if ($this->navbar_link_hidden_from_visitor($row)) {
                continue;
            }
            if (!empty($row['children'])) {
                $row['children'] = array_values(array_filter($row['children'], function($child) {
                    return !$this->navbar_link_hidden_from_visitor((array) $child);
                }));
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * The whole gear dropdown on the navbar — button and panel — or nothing.
     *
     * The panel is the groups the administrator wrote into the Gear menu items
     * box under Appearance → Advanced theme settings (the line syntax is in
     * theme_nit\local\gear_menu), with core's edit-mode switch relocated in
     * after the first of them — that is where it always sat, between
     * Navigation and Management, and a row that moves around as groups are
     * renamed is harder to find than one that stays put.
     *
     * Each piece is empty for some visitor — a guest has no group left once
     * every logged-in row is gone, a student never edits — and a gear that
     * opens onto an empty panel is worse than no gear, so the button is drawn
     * only when at least one piece has something in it.
     *
     * @return string HTML, or '' when the dropdown would be empty
     */
    public function navbar_gear_menu(): string {
        $groups = $this->navbar_gear_groups();
        $editswitch = (string) $this->edit_switch();

        if (empty($groups) && trim($editswitch) === '') {
            return '';
        }

        if (!empty($groups)) {
            $groups[0]['isfirst'] = true;
        }

        return $this->render_from_template('theme_nit/navbar_gear_menu', [
            'groups' => $groups,
            'hasgroups' => !empty($groups),
            'editswitch' => $editswitch,
        ]);
    }

    /**
     * The gear dropdown's groups, as this viewer gets to see them.
     *
     * Reads the administrator's lines (theme_nit\local\gear_menu), keeps the
     * rows this viewer is in the audience of, and drops any group that has no row
     * left — a heading over nothing is not a group.
     *
     * A row is marked active when it is the page being viewed: same path, and
     * every parameter the row's URL names has that value on the page (so the
     * one settings section a row points at lights up, not every settings page).
     * A row that is also one of core's primary-navigation nodes — My courses,
     * Site administration — borrows core's answer as well, so it stays lit
     * inside a course or deep in the admin tree exactly as core lights it.
     *
     * @return array list of ['heading' => string, 'hasheading' => bool, 'items' => [{text, url, isactive}]]
     */
    public function navbar_gear_groups(): array {
        // Reading $PAGE->url on a page that never set one raises a developer
        // notice, and highlighting the current row is not worth that: without a
        // URL, nothing is simply marked active.
        $currentpath = $this->page->has_set_url() ? $this->page->url->get_path() : null;

        // Core's own verdict on its rows, keyed by path (see the doc block).
        // Home is left out: core lights it as the fallback for any page that
        // matches nothing else, so a row pointing at `/` would be "current" on
        // a static page, a search, anywhere — path matching alone is right there.
        $coreactive = [];
        foreach ($this->page->primarynav->children as $node) {
            $action = $node->action();
            if ($node->key !== 'home' && $action instanceof \moodle_url) {
                $coreactive[self::navbar_path_key($action->get_path())] = (bool) $node->isactive;
            }
        }

        $groups = [];
        foreach (gear_menu::parse(gear_menu::definition()) as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                if (!gear_menu::visible($item['url'], $item['audience'], $this->page)) {
                    continue;
                }
                $url = gear_menu::url($item['url'])->out(false);
                $key = self::navbar_path_key((string) parse_url($url, PHP_URL_PATH));
                $items[] = [
                    'text' => gear_menu::label($item['names']),
                    'url' => $url,
                    'isactive' => $this->navbar_custom_menu_is_active(['url' => $url], $currentpath)
                        || !empty($coreactive[$key]),
                ];
            }
            if (empty($items)) {
                continue;
            }
            $heading = gear_menu::label($group['names']);
            $groups[] = [
                'heading' => $heading,
                'hasheading' => $heading !== '',
                'items' => $items,
            ];
        }

        return $groups;
    }

    /**
     * Is this navbar link the page the visitor is on?
     *
     * The path has to match, and so does every parameter the link carries a
     * value for. The parameters matter because the static pages are one script:
     * "من نحن" and "اتصل بنا" are both /local/profilefields/page.php and differ
     * only by `?page=`, so on a path-only test the whole pair lights up at once.
     *
     * A parameter the link leaves empty (`catalogue.php?q=`) is not compared — that
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
