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

namespace local_nit_core\hook;

use core\hook\output\before_standard_top_of_body_html_generation;
use local_nit_core\api\config;
use local_nit_core\output\welcome_panel;

/**
 * Output hook callbacks — the "when/where" of the rendering seam.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class output_callbacks {
    /**
     * Inject the NIT welcome panel on the dashboard when enabled.
     *
     * Gated by the local_nit_core/showwelcomepanel setting (off by default) and
     * scoped to the dashboard. The SDK builds the view-model; the theme renders
     * it (via its template override), so no presentation lives here.
     *
     * @param before_standard_top_of_body_html_generation $hook the output hook
     * @return void
     */
    public static function add_welcome_panel(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;

        if (!config::get_bool('showwelcomepanel')) {
            return;
        }
        if (!isset($PAGE) || $PAGE->pagelayout !== 'mydashboard') {
            return;
        }

        $renderer = $hook->renderer;
        $panel = new welcome_panel();

        // Prefer the theme's NIT renderer when present; degrade to a generic
        // template render otherwise (graceful if the active theme is not NIT).
        if (is_callable([$renderer, 'render_nit'])) {
            $html = $renderer->render_nit($panel);
        } else {
            $html = $renderer->render_from_template($panel->template_name(), $panel->export_for_template($renderer));
        }

        $hook->add_html($html);
    }

    /**
     * Upgrade every short-text course custom field on the course-edit form into a
     * "chips" editor: the teacher types a word/phrase and presses Enter to turn it
     * into a removable pill, repeatedly. No schema change — the hidden real input
     * still submits, carrying every entry joined by the chip separator so the value
     * stays one string in {customfield_data} and can be read back as a list.
     *
     * The separator is the same token the course-detail renderer splits on
     * ({@see \theme_nit\output\format_topics_renderer::CHIP_SEP}), so any field
     * edited this way is consumable as a chip list downstream.
     *
     * @param before_standard_top_of_body_html_generation $hook the output hook
     * @return void
     */
    public static function add_course_edit_chips(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;

        if (!isset($PAGE) || $PAGE->pagetype !== 'course-edit') {
            return;
        }

        // Single source of truth for the delimiter: the theme renderer's CHIP_SEP.
        // Fall back to the literal if the NIT theme is not the active class autoload.
        $sep = class_exists('\theme_nit\output\format_topics_renderer')
            ? \theme_nit\output\format_topics_renderer::CHIP_SEP
            : '@@|@@';

        // Widget styling. Uses the brand palette so it matches the site (with plain
        // fallbacks for a non-NIT theme). The course-edit form paints `.felement
        // input` with !important, so the typing field's colour/border rules are
        // !important with a descendant selector to reliably beat it.
        $css = <<<'CSS'
.nit-chips{display:flex;flex-wrap:wrap;gap:6px;align-items:center;width:100%;min-height:42px;padding:7px 9px;border:1px solid color-mix(in srgb, var(--nit-brand-primary,#C0392B) 35%, transparent);border-radius:8px;background:var(--nit-brand-surface,#0D2149) !important;box-sizing:border-box}
.nit-chips .nit-chips__list{display:flex;flex-wrap:wrap;gap:6px}
.nit-chips .nit-chips__chip{display:inline-flex;align-items:center;gap:7px;background:var(--nit-brand-primary,#C0392B) !important;color:var(--nit-brand-textprimary,#fff) !important;font-weight:600;font-size:13px;padding:4px 6px 4px 12px;border-radius:40px;line-height:1.5;max-width:100%}
.nit-chips .nit-chips__label{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:520px;color:inherit !important}
.nit-chips .nit-chips__x{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border:none !important;border-radius:50%;background:rgba(0,0,0,0.22) !important;color:inherit !important;cursor:pointer;font-size:14px;line-height:1;padding:0;font-family:inherit;flex:0 0 auto}
.nit-chips .nit-chips__x:hover{background:rgba(0,0,0,0.4) !important}
.nit-chips .nit-chips__input{flex:1 1 140px;min-width:140px;border:none !important;outline:none !important;background:transparent !important;color:var(--nit-brand-textprimary,#fff) !important;font:inherit;padding:5px 4px !important;margin:0 !important;height:auto !important;box-shadow:none !important}
.nit-chips .nit-chips__input::placeholder{color:var(--nit-brand-textsecondary,#8A9AB5) !important;opacity:1}
CSS;

        // Every short-text custom field renders as <input type="text" id="id_customfield_*">.
        // Long text (textarea/editor), select, checkbox and date fields are other
        // elements, so this type+prefix selector picks out exactly the short-text ones.
        $js = <<<'JS'
require([], function() {
    var SEP = {$this_sep_placeholder};

    if (!document.getElementById('nit-chips-css')) {
        var st = document.createElement('style');
        st.id = 'nit-chips-css';
        st.textContent = {$this_css_placeholder};
        document.head.appendChild(st);
    }

    function build(input) {
        if (!input || input.getAttribute('data-nit-chips')) { return; }
        input.setAttribute('data-nit-chips', '1');

        var chips = [];

        var wrap  = document.createElement('div');
        wrap.className = 'nit-chips';
        var list  = document.createElement('div');
        list.className = 'nit-chips__list';
        var typer = document.createElement('input');
        typer.type = 'text';
        typer.className = 'nit-chips__input';
        typer.setAttribute('autocomplete', 'off');
        typer.setAttribute('dir', 'auto');
        typer.placeholder = input.getAttribute('placeholder') || '';
        wrap.appendChild(list);
        wrap.appendChild(typer);

        // Keep the original input in the form (it is what submits) but hide it.
        input.style.display = 'none';
        input.parentNode.insertBefore(wrap, input.nextSibling);

        function serialize() { input.value = chips.join(SEP); }

        function render() {
            list.textContent = '';
            chips.forEach(function(text, i) {
                var chip = document.createElement('span');
                chip.className = 'nit-chips__chip';
                var label = document.createElement('span');
                label.className = 'nit-chips__label';
                label.setAttribute('dir', 'auto');
                label.textContent = text;
                var x = document.createElement('button');
                x.type = 'button';
                x.className = 'nit-chips__x';
                x.setAttribute('aria-label', 'Remove');
                x.innerHTML = '&times;';
                x.addEventListener('click', function() {
                    chips.splice(i, 1);
                    serialize();
                    render();
                    typer.focus();
                });
                chip.appendChild(label);
                chip.appendChild(x);
                list.appendChild(chip);
            });
        }

        // A pasted/typed value may itself contain the separator -> split it too.
        function commit(raw) {
            String(raw).split(SEP).forEach(function(part) {
                var t = part.trim();
                if (t !== '') { chips.push(t); }
            });
            serialize();
            render();
        }

        // Seed from the existing stored value.
        if (input.value && input.value.trim() !== '') {
            input.value.split(SEP).forEach(function(part) {
                var t = part.trim();
                if (t !== '') { chips.push(t); }
            });
            render();
        }

        typer.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault(); // never let Enter submit the whole course form here
                if (typer.value.trim() !== '') { commit(typer.value); typer.value = ''; }
            } else if (e.key === 'Backspace' && typer.value === '' && chips.length) {
                chips.pop();
                serialize();
                render();
            }
        });
        // Don't lose a half-typed entry if the user clicks Save without pressing Enter.
        typer.addEventListener('blur', function() {
            if (typer.value.trim() !== '') { commit(typer.value); typer.value = ''; }
        });
        // Clicking anywhere in the box focuses the typing field.
        wrap.addEventListener('click', function(e) {
            if (e.target === wrap || e.target === list) { typer.focus(); }
        });
    }

    function init() {
        var form = document.querySelector('form.mform') || document;
        var inputs = form.querySelectorAll('input[type="text"][id^="id_customfield_"]');
        Array.prototype.forEach.call(inputs, build);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
});
JS;

        // Splice the CSS + separator in as JSON string literals so quotes/newlines are safe.
        $js = str_replace('{$this_css_placeholder}', json_encode($css), $js);
        $js = str_replace('{$this_sep_placeholder}', json_encode($sep), $js);

        $PAGE->requires->js_amd_inline($js);
    }
}
