/**
 * Behaviour for home_6_categories4.html - the "photo cards" categories section
 * (Figma "home 13"): one card per top-level category, the category IMAGE as the
 * photo, a brand-coloured scrim over it, the category LOGO and the name on top.
 *
 * Served by js.php?name=home_categories_cards so a fix ships with a git pull;
 * the pasted block only carries markup and a four-line loader.
 *
 * Data: window.NIT_CATEGORIES (theme_nit_get_categories()), per category:
 *   image      the category image (local_nit_category), the photo
 *   logolight  the category logo for light mode (Change style tab)
 *   logodark   the category logo for dark mode
 *   name, url
 * A category with no logo for a mode falls back to the SITE logo for that
 * mode, so the card never shows a white mark on a white scrim.
 *
 * Colours: --nit-brand-* only. The scrim is the page background at 66%, the
 * text is the primary text colour, so the light/dark switch (a class on the
 * html element) repaints the cards with no script. Both logo variants are in
 * the card and CSS shows the one for the current mode.
 *
 * Options on the [data-nit-catcards] element:
 *   data-ids    comma list of category ids, drawn in that order (empty = all)
 *   data-limit  how many cards at most (default 6)
 */
(function() {
    'use strict';

    var STYLE_ID = 'nit-catcards-style';

    function injectStyle() {
        if (document.getElementById(STYLE_ID)) {
            return;
        }
        var scrimStrong = 'color-mix(in srgb, var(--nit-brand-background) 66%, transparent)';
        var scrimSoft = 'color-mix(in srgb, var(--nit-brand-background) 48%, transparent)';
        var css = [
            '.nit-cc-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:25px 32px;',
            'width:100%;max-width:1264px;margin:0 auto}',
            '@media (max-width:991.98px){.nit-cc-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}}',
            '@media (max-width:575.98px){.nit-cc-grid{grid-template-columns:minmax(0,1fr);gap:16px}}',

            '.nit-cc-card{position:relative;display:block;aspect-ratio:400/279;border-radius:12px;',
            'overflow:hidden;isolation:isolate;container-type:inline-size;',
            'background:var(--nit-brand-surface);color:var(--nit-brand-textprimary)!important;',
            'text-decoration:none!important;box-shadow:17px 21px 22px rgba(0,0,0,.5);',
            'transition:transform .3s ease,box-shadow .3s ease}',
            'html.nit-mode-light .nit-cc-card{box-shadow:0 14px 30px rgba(0,0,0,.18)}',
            '.nit-cc-card:hover{transform:translateY(-6px)}',
            '.nit-cc-card:focus-visible{outline:3px solid var(--nit-brand-primary);outline-offset:3px}',

            '.nit-cc-photo{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;',
            'z-index:-2;transition:transform .6s ease}',
            '.nit-cc-card:hover .nit-cc-photo{transform:scale(1.06)}',

            // The scrim is darkest behind the text, which sits at the inline
            // start: the right in Arabic, the left in English.
            '.nit-cc-card::before{content:"";position:absolute;inset:0;z-index:-1;',
            'background:linear-gradient(to right,' + scrimStrong + ' 0%,' + scrimStrong + ' 68%,' + scrimSoft + ' 100%)}',
            'html[dir=rtl] .nit-cc-card::before{',
            'background:linear-gradient(to left,' + scrimStrong + ' 0%,' + scrimStrong + ' 68%,' + scrimSoft + ' 100%)}',

            '.nit-cc-body{position:absolute;inset-block-start:23.3%;inset-inline:6%;',
            'display:flex;flex-direction:column;align-items:flex-start;gap:6px;max-width:72%}',

            '.nit-cc-logo{display:block;height:clamp(20px,7cqi,28px);width:auto;max-width:100%;object-fit:contain}',
            'html.nit-mode-dark .nit-cc-logo--light,html:not(.nit-mode-dark) .nit-cc-logo--dark{display:none}',

            '.nit-cc-name{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:3;overflow:hidden;',
            'margin:0;font-size:clamp(18px,7cqi,28px);line-height:1.43;font-weight:700;',
            'color:var(--nit-brand-textprimary);text-align:start;overflow-wrap:anywhere}',

            '@media (prefers-reduced-motion:reduce){.nit-cc-card,.nit-cc-photo{transition:none}',
            '.nit-cc-card:hover{transform:none}.nit-cc-card:hover .nit-cc-photo{transform:none}}'
        ].join('');
        var st = document.createElement('style');
        st.id = STYLE_ID;
        st.textContent = css;
        (document.head || document.documentElement).appendChild(st);
    }

    // The site's own mark for a mode: the switch's per-mode logos when the site
    // has two sets, otherwise the one compact mark.
    function siteLogo(mode) {
        var toggle = window.NIT_MODE_TOGGLE;
        var logos = toggle && toggle.logos && toggle.logos[mode];
        if (logos && (logos.compact || logos.navbar)) {
            return logos.compact || logos.navbar;
        }
        return window.NIT_LOGO || window.NIT_LOGO_FULL || '';
    }

    function pick(all, ids, limit) {
        if (ids.length) {
            var byId = {};
            all.forEach(function(c) {
                byId[String(c.id)] = c;
            });
            return ids.map(function(id) {
                return byId[id];
            }).filter(Boolean).slice(0, limit);
        }
        return all.slice(0, limit);
    }

    function logoImg(src, modifier) {
        var img = document.createElement('img');
        img.className = 'nit-cc-logo' + (modifier ? ' nit-cc-logo--' + modifier : '');
        img.src = src;
        img.alt = '';
        img.decoding = 'async';
        return img;
    }

    function buildCard(cat) {
        var a = document.createElement('a');
        a.className = 'nit-cc-card';
        a.href = cat.url || '#';

        if (cat.image) {
            var photo = document.createElement('img');
            photo.className = 'nit-cc-photo';
            photo.src = cat.image;
            photo.alt = '';
            photo.loading = 'lazy';
            photo.decoding = 'async';
            a.appendChild(photo);
        }

        var body = document.createElement('span');
        body.className = 'nit-cc-body';

        var light = cat.logolight || siteLogo('light');
        var dark = cat.logodark || siteLogo('dark');
        if (light && light === dark) {
            body.appendChild(logoImg(light, ''));
        } else {
            if (light) {
                body.appendChild(logoImg(light, 'light'));
            }
            if (dark) {
                body.appendChild(logoImg(dark, 'dark'));
            }
        }

        var name = document.createElement('span');
        name.className = 'nit-cc-name';
        name.setAttribute('dir', 'auto');
        name.textContent = cat.name || '';
        body.appendChild(name);

        a.appendChild(body);
        return a;
    }

    function render(section) {
        if (section.getAttribute('data-nit-catcards-done')) {
            return;
        }
        section.setAttribute('data-nit-catcards-done', '1');

        var grid = section.querySelector('[data-nit-catcards-grid]');
        if (!grid) {
            return;
        }
        var all = Array.isArray(window.NIT_CATEGORIES) ? window.NIT_CATEGORIES : [];
        var ids = (grid.getAttribute('data-ids') || '').split(',').map(function(s) {
            return s.trim();
        }).filter(Boolean);
        var limit = parseInt(grid.getAttribute('data-limit'), 10);
        if (!(limit > 0)) {
            limit = 6;
        }

        var cats = pick(all, ids, limit);
        if (!cats.length) {
            section.style.display = 'none';
            return;
        }

        grid.textContent = '';
        grid.classList.add('nit-cc-grid');
        cats.forEach(function(cat) {
            grid.appendChild(buildCard(cat));
        });
    }

    function run() {
        injectStyle();
        document.querySelectorAll('[data-nit-catcards]').forEach(render);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
