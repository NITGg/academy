<?php
// Admin UI to manage lesson (Flex) packages. Uses the local_academy HTTP API (api.php) from the
// browser, authenticated with a web-service token minted for the logged-in admin.

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');

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

// Pass config to JS.
echo html_writer::script('window.ACADEMY_PKG = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'token'    => $token,
)) . ';');

// Page markup.
?>
<div id="academy-pkg-app">
    <div class="mb-3">
        <button id="pkg-new" class="btn btn-primary">New package</button>
        <button id="pkg-refresh" class="btn btn-secondary">Refresh</button>
    </div>

    <div id="pkg-message" class="alert" style="display:none"></div>

    <table class="table table-striped" id="pkg-table">
        <thead>
            <tr>
                <th>ID</th><th>Name</th><th>Flexes</th><th>Price</th>
                <th>Expiration (days)</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody><tr><td colspan="7">Loading…</td></tr></tbody>
    </table>

    <!-- Create / edit form (hidden by default) -->
    <div id="pkg-form-card" class="card" style="display:none; max-width:560px;">
        <div class="card-body">
            <h4 id="pkg-form-title" class="card-title">New package</h4>
            <input type="hidden" id="f-id">
            <div class="form-group">
                <label for="f-name">Name</label>
                <input type="text" class="form-control" id="f-name">
            </div>
            <div class="form-group">
                <label for="f-description">Description</label>
                <textarea class="form-control" id="f-description" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label for="f-flex">Flex count</label>
                <input type="number" class="form-control" id="f-flex" min="1">
            </div>
            <div class="form-group">
                <label for="f-price">Price (EGP)</label>
                <input type="number" class="form-control" id="f-price" min="0" step="0.01">
            </div>
            <div class="form-group">
                <label for="f-exp">Expiration days (0 = unlimited)</label>
                <input type="number" class="form-control" id="f-exp" min="0" value="0">
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="f-active" checked>
                <label class="form-check-label" for="f-active">Active</label>
            </div>
            <button id="pkg-save" class="btn btn-primary">Save</button>
            <button id="pkg-cancel" class="btn btn-link">Cancel</button>
        </div>
    </div>

    <!-- ── User Packages ── -->
    <h4 class="mt-4">User Packages</h4>
    <p class="text-muted">Manage active and expired user packages.</p>
    <button id="refresh-users" class="btn btn-secondary mb-2">Refresh</button>
    <table class="table table-striped" id="users-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Package</th>
                <th>Flex</th>
                <th>Price Paid</th>
                <th>Status</th>
                <th>Expires At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody><tr><td colspan="7">Loading...</td></tr></tbody>
    </table>

    <!-- ── Unassign confirmation modal ── -->
    <div id="unassign-modal-backdrop" class="academy-modal-backdrop" style="display:none;">
        <div class="academy-modal">
            <h5 class="academy-modal-title">Unassign package</h5>
            <p id="unassign-modal-text"></p>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="unassign-refund-checkbox">
                <label class="form-check-label" for="unassign-refund-checkbox">
                    Refund payment to student <span class="text-muted">(optional)</span>
                </label>
            </div>
            <div class="academy-modal-actions">
                <button id="unassign-modal-cancel" class="btn btn-link">Cancel</button>
                <button id="unassign-modal-confirm" class="btn btn-danger">Unassign</button>
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

    function $(id) { return document.getElementById(id); }

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
                catch (e) { throw new Error('Session expired — please reload the page and log in again.'); }
                if (json.status !== 'success') { throw new Error(json.error || 'Request failed'); }
                return json.data;
            });
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    function loadPackages() {
        var tbody = $('pkg-table').querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="7">Loading…</td></tr>';
        api('get_packages').then(function (rows) {
            if (!rows.length) { tbody.innerHTML = '<tr><td colspan="7">No packages yet.</td></tr>'; return; }
            tbody.innerHTML = '';
            rows.forEach(function (p) {
                var tr = document.createElement('tr');
                var toggle = p.status === 'active'
                    ? '<button class="btn btn-sm btn-warning" data-act="deactivate" data-id="' + p.id + '">Deactivate</button>'
                    : '<button class="btn btn-sm btn-success" data-act="activate" data-id="' + p.id + '">Activate</button>';
                tr.innerHTML =
                    '<td>' + esc(p.id) + '</td>' +
                    '<td>' + esc(p.name) + '</td>' +
                    '<td>' + esc(p.flex_count) + '</td>' +
                    '<td>' + esc(p.price) + '</td>' +
                    '<td>' + esc(p.expiration_days) + '</td>' +
                    '<td>' + esc(p.status) + '</td>' +
                    '<td>' +
                        '<button class="btn btn-sm btn-secondary" data-act="edit" data-id="' + p.id + '">Edit</button> ' +
                        toggle + ' ' +
                        '<button class="btn btn-sm btn-danger" data-act="delete" data-id="' + p.id + '">Delete</button>' +
                    '</td>';
                tr._pkg = p;
                tbody.appendChild(tr);
            });
        }).catch(function (e) { msg(e.message, 'danger'); });
    }

    function showForm(pkg) {
        $('pkg-form-title').textContent = pkg ? ('Edit package #' + pkg.id) : 'New package';
        $('f-id').value          = pkg ? pkg.id : '';
        $('f-name').value        = pkg ? pkg.name : '';
        $('f-description').value = pkg ? (pkg.description || '') : '';
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
            name: $('f-name').value.trim(),
            description: $('f-description').value,
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
            msg(id ? 'Package updated.' : 'Package created.', 'success');
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
            api('activate_package', { id: id }).then(function () { msg('Activated.', 'success'); loadPackages(); }).catch(function (e) { msg(e.message, 'danger'); });
        } else if (act === 'deactivate') {
            api('deactivate_package', { id: id }).then(function () { msg('Deactivated.', 'success'); loadPackages(); }).catch(function (e) { msg(e.message, 'danger'); });
        } else if (act === 'delete') {
            if (!confirm('Delete this package? This cannot be undone.')) { return; }
            api('delete_package', { id: id }).then(function () { msg('Deleted.', 'success'); loadPackages(); }).catch(function (e) { msg(e.message, 'danger'); });
        }
    });

    $('pkg-new').addEventListener('click', function () { showForm(null); });
    $('pkg-refresh').addEventListener('click', loadPackages);
    $('pkg-save').addEventListener('click', save);
    $('pkg-cancel').addEventListener('click', hideForm);

    // ── User Packages ──
    function loadUsers() {
        var tbody = $('users-table').querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="7">Loading...</td></tr>';
        api('get_all_user_packages').then(function(rows) {
            if (!rows.length) { tbody.innerHTML = '<tr><td colspan="7">No user packages found.</td></tr>'; return; }
            tbody.innerHTML = '';
            rows.forEach(function(r) {
                var tr = document.createElement('tr');
                var toggle = '';
                if (r.status === 'active') {
                    toggle = '<button class="btn btn-sm btn-danger btn-unassign" data-id="' + r.id + '">Unassign</button>';
                }
                var expires = r.expires_at > 0 ? new Date(r.expires_at * 1000).toLocaleString() : 'Never';
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
        var priceText = row.price_paid ? (' — <strong>' + esc(row.price_paid) + '</strong> paid') : '';
        $('unassign-modal-text').innerHTML =
            'Unassign <strong>' + esc(row.name) + '</strong> from <strong>' + esc(row.user_fullname) + '</strong>' +
            priceText + '? This cannot be undone.';
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
            msg('Package unassigned successfully.', 'success');
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
