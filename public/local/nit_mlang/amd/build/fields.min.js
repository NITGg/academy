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
 * Per-language editing for every translatable field in the site.
 *
 * A translatable value is stored in ONE database column using the multilang
 * filter's markup:
 *
 *     {mlang en}Certificate of Success{mlang}{mlang ar}شهادة نجاح{mlang}
 *
 * Authors should never have to type that. This module replaces such a field with
 * one input per installed language pack, parses whatever is already stored back
 * into those inputs, and re-composes the markup as the author types — so the form
 * still submits exactly one value, in exactly the format the filter expects, and
 * no server-side code has to change.
 *
 * Two shapes are handled:
 *   - plain `<input type="text">`  -> one labelled input per language, stacked.
 *   - rich text editors           -> a language tab strip above the editor, since
 *                                    stacking several TinyMCE instances is neither
 *                                    fast nor usable.
 *
 * Everything is re-run through a MutationObserver, so forms that arrive later —
 * modal/dynamic forms, repeated elements, the activity chooser — are enhanced too.
 *
 * @module     local_nit_mlang/fields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('local_nit_mlang/fields', [], function() {

    /** @var {Object|null} The configuration handed over by the PHP side. */
    var cfg = null;

    /** @var {number} Counter used to build unique names for parked textareas. */
    var seq = 0;

    /**
     * Editor widgets that must write their current state back before a form is
     * serialised, as `{textarea, sync}` pairs.
     *
     * @var {Array}
     */
    var flushers = [];

    /**
     * Compiled glob patterns, keyed by the pattern itself.
     *
     * The registry is re-tested against every text input on the page each time the
     * DOM changes, so compiling the same handful of patterns over and over is worth
     * avoiding.
     *
     * @var {Object}
     */
    var patterns = {};

    /**
     * Turn a `*`-glob into an anchored regular expression.
     *
     * @param {String} glob pattern such as `name[*]` or `mod-data-*`
     * @return {RegExp}
     */
    function globToRe(glob) {
        if (!patterns[glob]) {
            var escaped = glob.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*');
            patterns[glob] = new RegExp('^' + escaped + '$');
        }
        return patterns[glob];
    }

    /**
     * Does `value` match any of the glob patterns?
     *
     * @param {String} value
     * @param {String[]} patterns
     * @return {Boolean}
     */
    function matchesAny(value, patterns) {
        for (var i = 0; i < patterns.length; i++) {
            if (globToRe(patterns[i]).test(value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Is this field switched off for this page by an exclusion rule?
     *
     * Rules read `pagetypeglob|fieldglob`, so the same field name can be a display
     * string on one page and an identifier on another.
     *
     * @param {String} fieldname the HTML name attribute
     * @param {String[]} rules exclusion rules
     * @return {Boolean}
     */
    function isExcluded(fieldname, rules) {
        for (var i = 0; i < rules.length; i++) {
            var parts = rules[i].split('|');
            var pageglob = parts[0].trim();
            var fieldglob = (parts.length > 1 ? parts[1] : '*').trim();
            if (globToRe(pageglob).test(cfg.pagetype) && globToRe(fieldglob).test(fieldname)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Split a stored value into its per-language parts.
     *
     * Understands `{mlang xx}…{mlang}`, a comma-separated `{mlang xx,yy}` head, and
     * the legacy `<span lang="xx" class="multilang">…</span>` markup. Blocks for a
     * language that is not installed (including `{mlang other}`) are preserved in
     * `extras` so re-saving the field never silently deletes them.
     *
     * A value with no markup at all belongs to the site default language, which is
     * the first entry of the configured language list.
     *
     * @param {String} raw the stored value
     * @return {Object} `{values: {code: text}, extras: [{code, text}]}`
     */
    function parse(raw) {
        var values = {};
        var extras = [];
        var text = raw === null || raw === undefined ? '' : String(raw);
        var installed = cfg.langs.map(function(lang) {
            return lang.code;
        });

        var assign = function(codes, body) {
            var claimed = false;
            codes.forEach(function(code) {
                if (installed.indexOf(code) >= 0) {
                    values[code] = body;
                    claimed = true;
                }
            });
            if (!claimed) {
                extras.push({code: codes.join(','), text: body});
            }
        };

        var found = false;
        var re = /\{\s*mlang\s+([^}]+)\}([\s\S]*?)\{\s*mlang\s*\}/gi;
        var m;
        while ((m = re.exec(text)) !== null) {
            found = true;
            assign(m[1].toLowerCase().split(',').map(function(code) {
                return code.trim();
            }), m[2]);
        }

        if (!found) {
            var span = /<span[^>]*\blang\s*=\s*["']([a-zA-Z0-9_-]+)["'][^>]*\bclass\s*=\s*["'][^"']*multilang[^"']*["'][^>]*>([\s\S]*?)<\/span>/gi;
            while ((m = span.exec(text)) !== null) {
                found = true;
                assign([m[1].toLowerCase()], m[2]);
            }
        }

        if (!found && text !== '') {
            values[cfg.langs[0].code] = text;
        }

        return {values: values, extras: extras};
    }

    /**
     * Is this value "nothing"?
     *
     * A rich text editor that has been cleared still reports markup such as
     * `<p><br></p>`, and an empty language must not become an `{mlang}` block. The
     * test deliberately only forgives structural emptiness, so content that is just
     * an image or a table still counts as filled in.
     *
     * @param {String} value
     * @return {Boolean}
     */
    function isBlank(value) {
        return /^(?:\s|&nbsp;|<p>|<\/p>|<br\s*\/?>|<div>|<\/div>)*$/i.test(String(value || ''));
    }

    /**
     * Build the stored value from the per-language parts.
     *
     * One language filled in is stored as plain text — that keeps monolingual
     * content (and numbers) clean, and the filter has nothing to resolve anyway.
     * Two or more produce the `{mlang}` markup.
     *
     * @param {Object} values map of language code to text
     * @param {Array} extras blocks for languages that are not installed
     * @return {String}
     */
    function compose(values, extras) {
        var parts = [];
        cfg.langs.forEach(function(lang) {
            var value = (values[lang.code] || '');
            if (!isBlank(value)) {
                parts.push({code: lang.code, text: value});
            }
        });
        (extras || []).forEach(function(extra) {
            if (!isBlank(extra.text)) {
                parts.push(extra);
            }
        });

        if (parts.length === 0) {
            return '';
        }
        if (parts.length === 1 && (extras || []).length === 0) {
            return parts[0].text;
        }
        return parts.map(function(part) {
            return '{mlang ' + part.code + '}' + part.text + '{mlang}';
        }).join('');
    }

    /**
     * Push a value into the element the form actually submits, and tell the rest of
     * the page about it so mform validation and the unsaved-changes checker agree.
     *
     * `silent` is used while setting the widget up: re-composing an already stored
     * value can legitimately change its spelling (a lone `{mlang en}…{mlang}` block
     * collapses to plain text), and firing events for that would make Moodle's
     * unsaved-changes checker warn about a form nobody has touched yet.
     *
     * @param {HTMLElement} element the hidden original input
     * @param {String} value composed value
     * @param {Boolean} silent suppress the change notification
     * @return {void}
     */
    function publish(element, value, silent) {
        if (element.value === value) {
            return;
        }
        element.value = value;
        if (silent) {
            return;
        }
        element.dispatchEvent(new Event('input', {bubbles: true}));
        element.dispatchEvent(new Event('change', {bubbles: true}));
    }

    /**
     * Should this element be left alone?
     *
     * @param {HTMLElement} element
     * @return {Boolean}
     */
    function skip(element) {
        return !element.name ||
            element.disabled ||
            element.readOnly ||
            element.hasAttribute('data-nitml') ||
            element.closest('[data-nitml-widget]') !== null ||
            element.closest('form') === null;
    }

    /**
     * Replace one plain text input with a stack of per-language inputs.
     *
     * The original input stays in the DOM (hidden) and keeps its name, id and any
     * error state, so server-side validation messages still land in the right
     * place — it simply receives the composed value instead of being typed into.
     *
     * @param {HTMLInputElement} input
     * @param {Boolean} inline true to sit the language boxes side by side instead
     * @return {void}
     */
    function enhanceText(input, inline) {
        input.setAttribute('data-nitml', '1');

        // The field's own length limit belongs on each language box, not on the
        // hidden original: the composed value is two or three languages plus the
        // {mlang} markup, and is legitimately longer than any one of them.
        var maxlength = input.getAttribute('maxlength');
        input.removeAttribute('maxlength');

        var parsed = parse(input.value);
        var values = parsed.values;
        var extras = parsed.extras;

        var widget = document.createElement('div');
        widget.className = 'nitml' + (inline ? ' nitml--inline' : '');
        widget.setAttribute('data-nitml-widget', 'text');

        var sync = function(silent) {
            publish(input, compose(values, extras), silent === true);
        };

        cfg.langs.forEach(function(lang, index) {
            var row = document.createElement('div');
            row.className = 'nitml__row';

            var label = document.createElement('span');
            label.className = 'nitml__lang';
            label.textContent = lang.name + ' (' + lang.code + ')';

            var field = document.createElement('input');
            field.type = 'text';
            field.className = 'form-control nitml__input';
            field.value = values[lang.code] || '';
            field.setAttribute('dir', lang.dir);
            field.setAttribute('lang', lang.code);
            field.setAttribute('autocomplete', 'off');
            field.setAttribute('aria-label', label.textContent);
            if (input.placeholder) {
                field.placeholder = input.placeholder;
            }
            if (maxlength) {
                field.setAttribute('maxlength', maxlength);
            }
            if (index === 0 && input.hasAttribute('autofocus')) {
                field.setAttribute('autofocus', 'autofocus');
                input.removeAttribute('autofocus');
            }

            field.addEventListener('input', function() {
                values[lang.code] = field.value;
                sync();
            });

            row.appendChild(label);
            row.appendChild(field);
            widget.appendChild(row);
        });

        input.classList.add('nitml-source');
        input.parentNode.insertBefore(widget, input.nextSibling);
        sync(true);
    }

    /**
     * Read the current HTML out of an editor, whichever editor is in use.
     *
     * @param {HTMLTextAreaElement} textarea the editor's backing textarea
     * @return {String}
     */
    function editorContent(textarea) {
        var tiny = window.tinyMCE && window.tinyMCE.get ? window.tinyMCE.get(textarea.id) : null;
        return tiny ? tiny.getContent() : textarea.value;
    }

    /**
     * Write HTML into an editor, whichever editor is in use.
     *
     * @param {HTMLTextAreaElement} textarea the editor's backing textarea
     * @param {String} html
     * @return {void}
     */
    function setEditorContent(textarea, html) {
        var tiny = window.tinyMCE && window.tinyMCE.get ? window.tinyMCE.get(textarea.id) : null;
        textarea.value = html;
        if (tiny) {
            tiny.setContent(html);
        }
    }

    /**
     * Run `callback` once the TinyMCE instance for this textarea exists.
     *
     * The editor boots asynchronously and may not be there yet when this module
     * runs, so poll briefly and give up quietly if the field turns out to be a
     * plain textarea (the "plain text area" editor plugin).
     *
     * @param {HTMLTextAreaElement} textarea
     * @param {Function} callback
     * @return {void}
     */
    function whenEditorReady(textarea, callback) {
        var attempts = 0;
        var tick = function() {
            if (!textarea.isConnected) {
                return;
            }
            if (window.tinyMCE && window.tinyMCE.get && window.tinyMCE.get(textarea.id)) {
                callback();
                return;
            }
            if (++attempts < 100) {
                window.setTimeout(tick, 200);
            }
        };
        tick();
    }

    /**
     * Add a language tab strip to one rich text editor.
     *
     * The editor only ever holds ONE language at a time — the selected tab — which
     * keeps the toolbar, file picker and HTML source view behaving normally. The
     * composed multilang value is carried by a hidden input that takes over the
     * textarea's submit name, so nothing depends on winning a race with TinyMCE's
     * own "copy me into the textarea on submit" handler.
     *
     * @param {HTMLTextAreaElement} textarea the editor's backing textarea
     * @return {void}
     */
    function enhanceEditor(textarea) {
        textarea.setAttribute('data-nitml', '1');

        var container = textarea.closest('[data-fieldtype="editor"]') || textarea.parentNode;
        var submitname = textarea.name;

        var parsed = parse(textarea.value);
        var values = parsed.values;
        var extras = parsed.extras;
        var current = cfg.langs[0].code;

        // The textarea stops being the submitted field; a hidden input takes its
        // name and carries the composed value.
        var carrier = document.createElement('input');
        carrier.type = 'hidden';
        carrier.name = submitname;
        textarea.name = 'nitmlparked' + (++seq);
        textarea.parentNode.insertBefore(carrier, textarea);

        var tabs = document.createElement('div');
        tabs.className = 'nitml-tabs';
        tabs.setAttribute('data-nitml-widget', 'editor');
        tabs.setAttribute('role', 'tablist');
        tabs.setAttribute('aria-label', cfg.strings.translations);

        var buttons = {};

        var sync = function() {
            values[current] = editorContent(textarea);
            carrier.value = compose(values, extras);
            cfg.langs.forEach(function(lang) {
                buttons[lang.code].classList.toggle(
                    'nitml-tab--filled',
                    !isBlank(values[lang.code])
                );
            });
        };

        var select = function(code) {
            if (code === current) {
                return;
            }
            values[current] = editorContent(textarea);
            current = code;
            setEditorContent(textarea, values[current] || '');
            cfg.langs.forEach(function(lang) {
                var active = lang.code === current;
                buttons[lang.code].classList.toggle('nitml-tab--active', active);
                buttons[lang.code].setAttribute('aria-selected', active ? 'true' : 'false');
            });
            sync();
        };

        cfg.langs.forEach(function(lang) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'nitml-tab' + (lang.code === current ? ' nitml-tab--active' : '');
            button.textContent = lang.name + ' (' + lang.code + ')';
            button.setAttribute('role', 'tab');
            button.setAttribute('lang', lang.code);
            button.setAttribute('aria-selected', lang.code === current ? 'true' : 'false');
            button.addEventListener('click', function() {
                select(lang.code);
            });
            buttons[lang.code] = button;
            tabs.appendChild(button);
        });

        container.insertBefore(tabs, container.firstChild);

        // Show only the selected language in the editor from the very first paint,
        // before TinyMCE has had a chance to read the textarea.
        textarea.value = values[current] || '';
        sync();

        whenEditorReady(textarea, function() {
            var tiny = window.tinyMCE.get(textarea.id);
            setEditorContent(textarea, values[current] || '');
            // Seeding the editor is not an edit: reset the undo/dirty state so the
            // unsaved-changes warning does not fire on a form nobody has touched.
            try {
                tiny.undoManager.clear();
                tiny.setDirty(false);
            } catch (e) {
                // Older or alternative editor build without these APIs — harmless.
            }
            tiny.on('change input keyup blur ExecCommand', sync);
            sync();
        });
        textarea.addEventListener('input', sync);
        flushers.push({textarea: textarea, sync: sync});
    }

    /**
     * Enhance every field in `root` that qualifies.
     *
     * @param {ParentNode} root
     * @return {void}
     */
    function scan(root) {
        var inputs = root.querySelectorAll('input[type="text"]:not([data-nitml])');
        Array.prototype.forEach.call(inputs, function(input) {
            if (skip(input) ||
                !matchesAny(input.name, cfg.textfields) ||
                isExcluded(input.name, cfg.textexcludes)) {
                return;
            }
            // An "inline" field puts its two language boxes side by side rather
            // than one under the other. Only the custom profile fields the server
            // names: the rest of the site keeps the layout it already had.
            enhanceText(input, matchesAny(input.name, cfg.inlinefields || []));
        });

        // Nothing to do on the editor side when both the global switch is off and
        // the server named no editor explicitly. Worth the check: this runs again
        // on every DOM mutation.
        if (!cfg.editors && !(cfg.forceeditors && cfg.forceeditors.length)) {
            return;
        }

        var areas = root.querySelectorAll('[data-fieldtype="editor"] textarea[id]:not([data-nitml])');
        Array.prototype.forEach.call(areas, function(textarea) {
            if (skip(textarea)) {
                return;
            }
            // The editor element submits as `<name>[text]`; rules are written
            // against the form element's own name, so strip the suffix.
            var base = textarea.name.replace(/\[text\]$/, '');
            // A forced editor is one the server named explicitly — an instructor's
            // Biography and the like. It ignores both the global "include rich text
            // editors" switch and the exclusion list, because it was not matched by
            // a pattern that either of those could have over-reached on.
            if (matchesAny(base, cfg.forceeditors || [])) {
                enhanceEditor(textarea);
                return;
            }
            if (!cfg.editors || isExcluded(base, cfg.editorexcludes)) {
                return;
            }
            enhanceEditor(textarea);
        });
    }

    /**
     * Entry point.
     *
     * @param {Object} config language list, field registry and page type
     * @return {void}
     */
    var init = function(config) {
        if (cfg) {
            return;
        }
        cfg = config;

        var pending = false;
        var rescan = function() {
            if (pending) {
                return;
            }
            pending = true;
            window.requestAnimationFrame(function() {
                pending = false;
                scan(document);
            });
        };

        scan(document);

        // Modal and dynamic forms, repeated elements and the activity chooser all
        // add their fields after this point.
        new MutationObserver(rescan).observe(document.body, {childList: true, subtree: true});

        // Final flush: catch keystrokes TinyMCE has not raised a change event for.
        // Capturing at the document means this runs before any handler bound to the
        // form itself, including the editor's own.
        document.addEventListener('submit', function(event) {
            flushers = flushers.filter(function(entry) {
                return entry.textarea.isConnected;
            });
            flushers.forEach(function(entry) {
                if (event.target.contains(entry.textarea)) {
                    entry.sync();
                }
            });
        }, true);
    };

    return {init: init};
});
