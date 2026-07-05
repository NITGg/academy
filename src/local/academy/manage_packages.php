<?php
// Admin UI to manage lesson (Flex) packages. Uses the local_academy HTTP API (api.php) from the
// browser, authenticated with a web-service token minted for the logged-in admin.

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/academy/lib.php'); // local_academy_string_map()

admin_externalpage_setup('local_academy_managepackages');
require_capability('local/academy:managepackages', context_system::instance());

global $DB, $OUTPUT, $CFG, $PAGE;

// Mint (or reuse) a token for the current admin on the built-in mobile service.
$service = $DB->get_record('external_services', array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
$tokenobj = external_generate_token_for_current_user($service);
$token = $tokenobj->token;

$PAGE->set_title(get_string('managepackages', 'local_academy'));
$PAGE->set_heading(get_string('managepackages', 'local_academy'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managepackages', 'local_academy'));

// Localised strings for this page — used in the server-rendered HTML below (via $STR['key']) and
// shipped to the browser as window.ACADEMY_STR for the JS (via the str()/strf() helpers).
$STR = local_academy_string_map(array(
    // Static HTML labels / headers.
    'pkg_new', 'ui_refresh', 'ui_loading', 'ui_active', 'ui_save', 'ui_cancel', 'ui_optional',
    'pkg_col_id', 'pkg_col_name', 'pkg_col_flexes', 'pkg_col_price', 'pkg_col_expdays',
    'pkg_col_status', 'pkg_col_actions', 'pkg_field_name', 'pkg_field_description',
    'pkg_field_name_en', 'pkg_field_name_ar', 'pkg_field_desc_en', 'pkg_field_desc_ar',
    'pkg_field_flexcount', 'pkg_field_price', 'pkg_field_expdays',
    'pkg_userpackages', 'pkg_userpackages_desc', 'pkg_col_user', 'pkg_col_package', 'pkg_col_flex',
    'pkg_col_pricepaid', 'pkg_col_expiresat', 'pkg_unassign_title', 'pkg_unassign_refund',
    'pkg_unassign',
    // Dynamic strings used only from JS.
    'ui_edit', 'ui_delete', 'ui_activate', 'ui_deactivate', 'ui_never',
    'pkg_none', 'pkg_edit_titled', 'pkg_confirm_delete', 'pkg_users_none',
    'pkg_unassign_confirm', 'pkg_unassign_paid',
    'msg_package_created', 'msg_package_updated', 'msg_package_activated',
    'msg_package_deactivated', 'msg_package_deleted', 'msg_package_unassigned',
    'err_requestfailed', 'err_sessionexpired',
));

// Pass config + localised strings to JS.
echo html_writer::script('window.ACADEMY_PKG = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'token'    => $token,
    'lang'     => current_language(),
)) . ';');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');

// Page markup.
?>
<div id="academy-pkg-app">
    <div class="mb-3">
        <button id="pkg-new" class="btn btn-primary"><?php echo $STR['pkg_new']; ?></button>
        <button id="pkg-refresh" class="btn btn-secondary"><?php echo $STR['ui_refresh']; ?></button>
    </div>

    <div id="pkg-message" class="alert" style="display:none"></div>

    <table class="table table-striped" id="pkg-table">
        <thead>
            <tr>
                <th><?php echo $STR['pkg_col_id']; ?></th><th><?php echo $STR['pkg_col_name']; ?></th>
                <th><?php echo $STR['pkg_col_flexes']; ?></th><th><?php echo $STR['pkg_col_price']; ?></th>
                <th><?php echo $STR['pkg_col_expdays']; ?></th><th><?php echo $STR['pkg_col_status']; ?></th>
                <th><?php echo $STR['pkg_col_actions']; ?></th>
            </tr>
        </thead>
        <tbody><tr><td colspan="7"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
    </table>

    <!-- Create / edit form (hidden by default) -->
    <div id="pkg-form-card" class="card" style="display:none; max-width:560px;">
        <div class="card-body">
            <h4 id="pkg-form-title" class="card-title"><?php echo $STR['pkg_new']; ?></h4>
            <input type="hidden" id="f-id">
            <div class="form-group">
                <label for="f-name-en"><?php echo $STR['pkg_field_name_en']; ?></label>
                <input type="text" class="form-control" id="f-name-en" dir="ltr">
            </div>
            <div class="form-group">
                <label for="f-name-ar"><?php echo $STR['pkg_field_name_ar']; ?></label>
                <input type="text" class="form-control" id="f-name-ar" dir="rtl">
            </div>
            <div class="form-group">
                <label for="f-desc-en"><?php echo $STR['pkg_field_desc_en']; ?></label>
                <textarea class="form-control" id="f-desc-en" rows="2" dir="ltr"></textarea>
            </div>
            <div class="form-group">
                <label for="f-desc-ar"><?php echo $STR['pkg_field_desc_ar']; ?></label>
                <textarea class="form-control" id="f-desc-ar" rows="2" dir="rtl"></textarea>
            </div>
            <div class="form-group">
                <label for="f-flex"><?php echo $STR['pkg_field_flexcount']; ?></label>
                <input type="number" class="form-control" id="f-flex" min="1">
            </div>
            <div class="form-group">
                <label for="f-price"><?php echo $STR['pkg_field_price']; ?></label>
                <input type="number" class="form-control" id="f-price" min="0" step="0.01">
            </div>
            <div class="form-group">
                <label for="f-exp"><?php echo $STR['pkg_field_expdays']; ?></label>
                <input type="number" class="form-control" id="f-exp" min="0" value="0">
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="f-active" checked>
                <label class="form-check-label" for="f-active"><?php echo $STR['ui_active']; ?></label>
            </div>
            <button id="pkg-save" class="btn btn-primary"><?php echo $STR['ui_save']; ?></button>
            <button id="pkg-cancel" class="btn btn-link"><?php echo $STR['ui_cancel']; ?></button>
        </div>
    </div>

    <!-- ── User Packages ── -->
    <h4 class="mt-4"><?php echo $STR['pkg_userpackages']; ?></h4>
    <p class="text-muted"><?php echo $STR['pkg_userpackages_desc']; ?></p>
    <button id="refresh-users" class="btn btn-secondary mb-2"><?php echo $STR['ui_refresh']; ?></button>
    <table class="table table-striped" id="users-table">
        <thead>
            <tr>
                <th><?php echo $STR['pkg_col_user']; ?></th>
                <th><?php echo $STR['pkg_col_package']; ?></th>
                <th><?php echo $STR['pkg_col_flex']; ?></th>
                <th><?php echo $STR['pkg_col_pricepaid']; ?></th>
                <th><?php echo $STR['pkg_col_status']; ?></th>
                <th><?php echo $STR['pkg_col_expiresat']; ?></th>
                <th><?php echo $STR['pkg_col_actions']; ?></th>
            </tr>
        </thead>
        <tbody><tr><td colspan="7"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
    </table>

    <!-- ── Unassign confirmation modal ── -->
    <div id="unassign-modal-backdrop" class="academy-modal-backdrop" style="display:none;">
        <div class="academy-modal">
            <h5 class="academy-modal-title"><?php echo $STR['pkg_unassign_title']; ?></h5>
            <p id="unassign-modal-text"></p>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="unassign-refund-checkbox">
                <label class="form-check-label" for="unassign-refund-checkbox">
                    <?php echo $STR['pkg_unassign_refund']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span>
                </label>
            </div>
            <div class="academy-modal-actions">
                <button id="unassign-modal-cancel" class="btn btn-link"><?php echo $STR['ui_cancel']; ?></button>
                <button id="unassign-modal-confirm" class="btn btn-danger"><?php echo $STR['pkg_unassign']; ?></button>
            </div>
        </div>
    </div>
</div>
<style>
    .academy-modal-backdrop {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1050;
    }
    .academy-modal {
        background: #fff;
        border-radius: 10px;
        padding: 1.5rem;
        max-width: 440px;
        width: 90%;
        box-shadow: 0 12px 30px rgba(0,0,0,0.25);
    }
    .academy-modal-title {
        margin-bottom: 0.75rem;
        font-weight: 600;
    }
    .academy-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
        margin-top: 1.25rem;
    }
</style>
<?php

echo html_writer::script(<<<'JS'
(function () {
    var CFG = window.ACADEMY_PKG;
    var STR = window.ACADEMY_STR || {};

    function $(id) { return document.getElementById(id); }

    // Localised string lookup; falls back to the key so a missing string is visible, not blank.
    function str(k) { return (k in STR) ? STR[k] : k; }
    // Like str() but fills Moodle {$a} / {$a->name} placeholders from a params object.
    function strf(k, params) {
        var s = str(k);
        if (params == null) { return s; }
        if (typeof params !== 'object') { return s.replace(/\{\$a\}/g, params); }
        return s.replace(/\{\$a->(\w+)\}/g, function (m, name) {
            return (name in params) ? params[name] : m;
        });
    }

    function msg(text, type) {
        var el = $('pkg-message');
        el.textContent = text;
        el.className = 'alert alert-' + (type || 'info');
        el.style.display = 'block';
        if (type === 'success') { setTimeout(function () { el.style.display = 'none'; }, 3000); }
    }

    // Call the package API. Resolves with `data`, rejects with an Error(message).
    function api(func, params, method) {
        params = params || {};
        method = method || 'GET';
        var data = new URLSearchParams({ function: func, token: CFG.token });
        if (CFG.lang) { data.append('lang', CFG.lang); }
        Object.keys(params).forEach(function (k) { data.append(k, params[k]); });
        var opts, url = CFG.endpoint;
        if (method === 'POST') {
            opts = { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: data.toString() };
        } else {
            url = CFG.endpoint + '?' + data.toString();
            opts = {};
        }
        return fetch(url, opts)
            .then(function (r) { return r.text(); })
            .then(function (text) {
                var json;
                try { json = JSON.parse(text); }
                catch (e) { throw new Error(str('err_sessionexpired')); }
                if (json.status !== 'success') { throw new Error(json.error || str('err_requestfailed')); }
                return json.data;
            });
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    // ── Multilang helpers ──────────────────────────────────────────────────────
    // The DB keeps a SINGLE name/description field. To hold two languages in it we use Moodle's
    // multilang markup: <span lang="en" class="multilang">…</span><span lang="ar" class="multilang">…</span>.
    // These helpers let the admin edit two clean boxes (EN / AR) that map to that one field.

    // Pull the {en, ar} values out of a stored multilang string. A plain value (no spans) is treated
    // as the English text so legacy/simple entries still load into the form.
    function parseMultilang(value) {
        var out = { en: '', ar: '' };
        var raw = String(value == null ? '' : value);
        var re = /<span[^>]*\bclass\s*=\s*"[^"]*\bmultilang\b[^"]*"[^>]*\blang\s*=\s*"([a-zA-Z0-9_-]+)"[^>]*>([\s\S]*?)<\/span>|<span[^>]*\blang\s*=\s*"([a-zA-Z0-9_-]+)"[^>]*\bclass\s*=\s*"[^"]*\bmultilang\b[^"]*"[^>]*>([\s\S]*?)<\/span>/g;
        var m, found = false;
        while ((m = re.exec(raw)) !== null) {
            found = true;
            var code = (m[1] || m[3] || '').toLowerCase();
            var text = (m[1] ? m[2] : m[4]);
            if (code.indexOf('ar') === 0) { out.ar = text; }
            else if (code.indexOf('en') === 0) { out.en = text; }
        }
        if (!found) { out.en = raw; } // plain text → English box
        return out;
    }

    // Combine the two boxes back into one field value. Two langs → multilang spans; a single lang →
    // plain text (so it still works even when the multilang filter is off); both empty → ''.
    function buildMultilang(en, ar) {
        en = String(en == null ? '' : en).trim();
        ar = String(ar == null ? '' : ar).trim();
        if (en && ar) {
            return '<span lang="en" class="multilang">' + en + '</span>' +
                   '<span lang="ar" class="multilang">' + ar + '</span>';
        }
        return en || ar;
    }

    // Readable label for the admin table: "English / Arabic" (whichever are present).
    function displayName(value) {
        var v = parseMultilang(value);
        return [v.en, v.ar].filter(function (x) { return x; }).join(' / ') || value || '';
    }

    function loadPackages() {
        var tbody = $('pkg-table').querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="7">' + esc(str('ui_loading')) + '</td></tr>';
        api('get_packages').then(function (rows) {
            if (!rows.length) { tbody.innerHTML = '<tr><td colspan="7">' + esc(str('pkg_none')) + '</td></tr>'; return; }
            tbody.innerHTML = '';
            rows.forEach(function (p) {
                var tr = document.createElement('tr');
                var toggle = p.status === 'active'
                    ? '<button class="btn btn-sm btn-warning" data-act="deactivate" data-id="' + p.id + '">' + esc(str('ui_deactivate')) + '</button>'
                    : '<button class="btn btn-sm btn-success" data-act="activate" data-id="' + p.id + '">' + esc(str('ui_activate')) + '</button>';
                tr.innerHTML =
                    '<td>' + esc(p.id) + '</td>' +
                    '<td>' + esc(displayName(p.name)) + '</td>' +
                    '<td>' + esc(p.flex_count) + '</td>' +
                    '<td>' + esc(p.price) + '</td>' +
                    '<td>' + esc(p.expiration_days) + '</td>' +
                    '<td>' + esc(p.status) + '</td>' +
                    '<td>' +
                        '<button class="btn btn-sm btn-secondary" data-act="edit" data-id="' + p.id + '">' + esc(str('ui_edit')) + '</button> ' +
                        toggle + ' ' +
                        '<button class="btn btn-sm btn-danger" data-act="delete" data-id="' + p.id + '">' + esc(str('ui_delete')) + '</button>' +
                    '</td>';
                tr._pkg = p;
                tbody.appendChild(tr);
            });
        }).catch(function (e) { msg(e.message, 'danger'); });
    }

    function showForm(pkg) {
        $('pkg-form-title').textContent = pkg ? strf('pkg_edit_titled', pkg.id) : str('pkg_new');
        var nm = parseMultilang(pkg ? pkg.name : '');
        var ds = parseMultilang(pkg ? (pkg.description || '') : '');
        $('f-id').value          = pkg ? pkg.id : '';
        $('f-name-en').value     = nm.en;
        $('f-name-ar').value     = nm.ar;
        $('f-desc-en').value     = ds.en;
        $('f-desc-ar').value     = ds.ar;
        $('f-flex').value        = pkg ? pkg.flex_count : '';
        $('f-price').value       = pkg ? pkg.price : '';
        $('f-exp').value         = pkg ? pkg.expiration_days : 0;
        $('f-active').checked    = pkg ? (pkg.status === 'active') : true;
        $('pkg-form-card').style.display = 'block';
    }
    function hideForm() { $('pkg-form-card').style.display = 'none'; }

    function save() {
        var id = $('f-id').value;
        var params = {
            name: buildMultilang($('f-name-en').value, $('f-name-ar').value),
            description: buildMultilang($('f-desc-en').value, $('f-desc-ar').value),
            flex_count: $('f-flex').value,
            price: $('f-price').value,
            expiration_days: $('f-exp').value || 0
        };
        var p;
        if (id) {
            params.id = id;
            params.status = $('f-active').checked ? 'active' : 'inactive';
            p = api('update_package', params);
        } else {
            params.active = $('f-active').checked ? 1 : 0;
            p = api('create_package', params);
        }
        p.then(function () {
            msg(id ? str('msg_package_updated') : str('msg_package_created'), 'success');
            hideForm();
            loadPackages();
        }).catch(function (e) { msg(e.message, 'danger'); });
    }

    // Table action buttons (event delegation).
    $('pkg-table').addEventListener('click', function (ev) {
        var btn = ev.target.closest('button[data-act]');
        if (!btn) { return; }
        var id = btn.getAttribute('data-id');
        var act = btn.getAttribute('data-act');
        var row = btn.closest('tr');

        if (act === 'edit') { showForm(row._pkg); return; }
        if (act === 'activate') {
            api('activate_package', { id: id }).then(function () { msg(str('msg_package_activated'), 'success'); loadPackages(); }).catch(function (e) { msg(e.message, 'danger'); });
        } else if (act === 'deactivate') {
            api('deactivate_package', { id: id }).then(function () { msg(str('msg_package_deactivated'), 'success'); loadPackages(); }).catch(function (e) { msg(e.message, 'danger'); });
        } else if (act === 'delete') {
            if (!confirm(str('pkg_confirm_delete'))) { return; }
            api('delete_package', { id: id }).then(function () { msg(str('msg_package_deleted'), 'success'); loadPackages(); }).catch(function (e) { msg(e.message, 'danger'); });
        }
    });

    $('pkg-new').addEventListener('click', function () { showForm(null); });
    $('pkg-refresh').addEventListener('click', loadPackages);
    $('pkg-save').addEventListener('click', save);
    $('pkg-cancel').addEventListener('click', hideForm);

    // ── User Packages ──
    function loadUsers() {
        var tbody = $('users-table').querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="7">' + esc(str('ui_loading')) + '</td></tr>';
        api('get_all_user_packages').then(function(rows) {
            if (!rows.length) { tbody.innerHTML = '<tr><td colspan="7">' + esc(str('pkg_users_none')) + '</td></tr>'; return; }
            tbody.innerHTML = '';
            rows.forEach(function(r) {
                var tr = document.createElement('tr');
                var toggle = '';
                if (r.status === 'active') {
                    toggle = '<button class="btn btn-sm btn-danger btn-unassign" data-id="' + r.id + '">' + esc(str('pkg_unassign')) + '</button>';
                }
                var expires = r.expires_at > 0 ? new Date(r.expires_at * 1000).toLocaleString() : str('ui_never');
                tr.innerHTML =
                    '<td>' + esc(r.user_fullname) + ' <br><small class="text-muted">' + esc(r.user_email) + '</small></td>' +
                    '<td>' + esc(r.name) + '</td>' +
                    '<td>' + esc(r.remaining_flex) + ' / ' + esc(r.total_flex) + '</td>' +
                    '<td>' + esc(r.price_paid) + '</td>' +
                    '<td>' + esc(r.status) + '</td>' +
                    '<td>' + expires + '</td>' +
                    '<td>' + toggle + '</td>';
                tr._row = r;
                tbody.appendChild(tr);
            });
        }).catch(function(e) { msg(e.message, 'danger'); });
    }

    // ── Unassign confirmation modal ──
    var pendingUnassign = null;

    function openUnassignModal(row) {
        pendingUnassign = row;
        var priceText = row.price_paid ? strf('pkg_unassign_paid', esc(row.price_paid)) : '';
        $('unassign-modal-text').innerHTML = strf('pkg_unassign_confirm', {
            name: esc(row.name),
            user: esc(row.user_fullname),
            price: priceText
        });
        $('unassign-refund-checkbox').checked = false;
        $('unassign-modal-backdrop').style.display = 'flex';
    }

    function closeUnassignModal() {
        pendingUnassign = null;
        $('unassign-modal-backdrop').style.display = 'none';
    }

    $('users-table').addEventListener('click', function(ev) {
        var btn = ev.target.closest('.btn-unassign');
        if (!btn) return;
        openUnassignModal(btn.closest('tr')._row);
    });

    $('unassign-modal-cancel').addEventListener('click', closeUnassignModal);
    $('unassign-modal-backdrop').addEventListener('click', function(ev) {
        if (ev.target === this) { closeUnassignModal(); }
    });
    document.addEventListener('keydown', function(ev) {
        if (ev.key === 'Escape' && $('unassign-modal-backdrop').style.display !== 'none') { closeUnassignModal(); }
    });
    $('unassign-modal-confirm').addEventListener('click', function() {
        var row = pendingUnassign;
        if (!row) { return; }
        var refund = $('unassign-refund-checkbox').checked;
        api('unassign_package', {
            purchaseid: row.id,
            refund: refund ? 1 : 0
        }, 'POST').then(function() {
            msg(str('msg_package_unassigned'), 'success');
            closeUnassignModal();
            loadUsers();
        }).catch(function(e) { msg(e.message, 'danger'); });
    });

    $('refresh-users').addEventListener('click', loadUsers);

    loadPackages();
    loadUsers();
})();
JS
);

echo $OUTPUT->footer();
