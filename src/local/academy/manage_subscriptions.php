<?php
// Admin UI to manage course-access subscription plans (US-AD-5-*) and per-course subscription
// availability (US-AD-6-1). Uses the local_academy HTTP API (api.php) from the browser,
// authenticated with a web-service token minted for the logged-in admin. Mirrors manage_packages.php.

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');

admin_externalpage_setup('local_academy_managesubscriptions');
require_capability('local/academy:managesubscriptions', context_system::instance());

global $DB, $OUTPUT, $CFG, $PAGE;

$service = $DB->get_record('external_services', array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
$token = external_generate_token_for_current_user($service)->token;

$PAGE->set_title(get_string('managesubscriptions', 'local_academy'));
$PAGE->set_heading(get_string('managesubscriptions', 'local_academy'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managesubscriptions', 'local_academy'));

echo html_writer::script('window.ACADEMY_SUB = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'token'    => $token,
)) . ';');
?>
<div id="academy-sub-app">
    <div id="sub-message" class="alert" style="display:none"></div>

    <!-- ── Subscription plans (US-AD-5-*) ── -->
    <h4>Subscription plans</h4>
    <div class="mb-3">
        <button id="sub-new" class="btn btn-primary">New subscription</button>
        <button id="sub-refresh" class="btn btn-secondary">Refresh</button>
    </div>

    <table class="table table-striped" id="sub-table">
        <thead>
            <tr>
                <th>ID</th><th>Name</th><th>Price</th><th>Days</th>
                <th>Courses</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody><tr><td colspan="7">Loading…</td></tr></tbody>
    </table>

    <div id="sub-form-card" class="card" style="display:none; max-width:560px;">
        <div class="card-body">
            <h4 id="sub-form-title" class="card-title">New subscription</h4>
            <input type="hidden" id="f-id">
            <div class="form-group">
                <label for="f-name">Name</label>
                <input type="text" class="form-control" id="f-name">
            </div>
            <div class="form-group">
                <label for="f-description">Description (optional)</label>
                <textarea class="form-control" id="f-description" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label for="f-price">Price (EGP)</label>
                <input type="number" class="form-control" id="f-price" min="0" step="0.01">
            </div>
            <div class="form-group">
                <label for="f-days">Number of days</label>
                <input type="number" class="form-control" id="f-days" min="1">
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="f-active" checked>
                <label class="form-check-label" for="f-active">Active</label>
            </div>
            <button id="sub-save" class="btn btn-primary">Save</button>
            <button id="sub-cancel" class="btn btn-link">Cancel</button>
        </div>
    </div>

    <!-- ── Course access (New UI) ── -->
    <h4 class="mt-4">Course subscription availability</h4>
    <p class="text-muted">Choose courses and append them to a specific subscription.</p>

    <style>
        .course-chip {
            display: inline-flex;
            align-items: center;
            background: #f1f3f5;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            padding: 8px 16px;
            margin: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.95rem;
            color: #495057;
            user-select: none;
        }
        .course-chip:hover {
            background: #e9ecef;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            transform: translateY(-1px);
        }
        .course-chip input[type="checkbox"] {
            margin-right: 10px;
            cursor: pointer;
            width: 1.1rem;
            height: 1.1rem;
        }
        .category-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-bottom: 1.5rem !important;
            background: #fff;
        }
        .category-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.08);
        }
        .category-card .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #dee2e6;
            font-size: 1.15rem;
            color: #212529;
            padding: 1rem 1.25rem;
        }
        .category-card .card-body {
            padding: 1.25rem;
            display: flex;
            flex-wrap: wrap;
        }
    </style>

    <div id="course-selector-area" style="display:none;">
        <div id="categories-container" class="mb-4"></div>
        <div class="mb-3">
            <label for="target-subscription">Target Subscription:</label>
            <select id="target-subscription" class="form-control" style="max-width: 300px; display: inline-block;">
                <option value="">Select a subscription...</option>
            </select>
            <button id="save-course-selection" class="btn btn-primary ml-2">Save courses to subscription</button>
        </div>
    </div>

    <!-- ── User Subscriptions (New UI) ── -->
    <h4 class="mt-4">User Subscriptions</h4>
    <p class="text-muted">Manage active and expired user subscriptions.</p>
    <button id="refresh-users" class="btn btn-secondary mb-2">Refresh</button>
    <table class="table table-striped" id="users-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Subscription</th>
                <th>Price Paid</th>
                <th>Status</th>
                <th>Expires At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody><tr><td colspan="6">Loading...</td></tr></tbody>
    </table>
</div>
<?php

echo html_writer::script(<<<'JS'
(function () {
    var CFG = window.ACADEMY_SUB;
    function $(id) { return document.getElementById(id); }

    function msg(text, type) {
        var el = $('sub-message');
        el.textContent = text;
        el.className = 'alert alert-' + (type || 'info');
        el.style.display = 'block';
        if (type === 'success') { setTimeout(function () { el.style.display = 'none'; }, 3000); }
    }

    // GET for reads, POST for mutations (the API requires POST for state changes).
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

    function courseNames(sub) {
        if (!sub.courses || !sub.courses.length) { return '<span class="text-muted">—</span>'; }
        return sub.courses.map(function (c) { return esc(c.fullname); }).join(', ');
    }

    var ALL_SUBS = []; // cache for the course-access checkboxes

    function loadSubs() {
        var tbody = $('sub-table').querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="7">Loading…</td></tr>';
        api('get_subscriptions').then(function (rows) {
            ALL_SUBS = rows;
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="7">No subscriptions yet.</td></tr>';
                populateSubscriptionDropdown();
                return;
            }
            tbody.innerHTML = '';
            rows.forEach(function (s) {
                var tr = document.createElement('tr');
                var toggle = s.status === 'active'
                    ? '<button class="btn btn-sm btn-warning" data-act="deactivate" data-id="' + s.id + '">Deactivate</button>'
                    : '<button class="btn btn-sm btn-success" data-act="activate" data-id="' + s.id + '">Activate</button>';
                tr.innerHTML =
                    '<td>' + esc(s.id) + '</td>' +
                    '<td>' + esc(s.name) + '</td>' +
                    '<td>' + esc(s.price) + '</td>' +
                    '<td>' + esc(s.duration_days) + '</td>' +
                    '<td>' + courseNames(s) + '</td>' +
                    '<td>' + esc(s.status) + '</td>' +
                    '<td>' +
                        '<button class="btn btn-sm btn-secondary" data-act="edit" data-id="' + s.id + '">Edit</button> ' +
                        toggle + ' ' +
                        '<button class="btn btn-sm btn-danger" data-act="delete" data-id="' + s.id + '">Delete</button>' +
                    '</td>';
                tr._sub = s;
                tbody.appendChild(tr);
            });
            populateSubscriptionDropdown();
        }).catch(function (e) { msg(e.message, 'danger'); });
    }

    function showForm(sub) {
        $('sub-form-title').textContent = sub ? ('Edit subscription #' + sub.id) : 'New subscription';
        $('f-id').value          = sub ? sub.id : '';
        $('f-name').value        = sub ? sub.name : '';
        $('f-description').value = sub ? (sub.description || '') : '';
        $('f-price').value       = sub ? sub.price : '';
        $('f-days').value        = sub ? sub.duration_days : '';
        $('f-active').checked    = sub ? (sub.status === 'active') : true;
        $('sub-form-card').style.display = 'block';
    }
    function hideForm() { $('sub-form-card').style.display = 'none'; }

    function save() {
        var id = $('f-id').value;
        var params = {
            name: $('f-name').value.trim(),
            description: $('f-description').value,
            price: $('f-price').value,
            duration_days: $('f-days').value
        };
        var p;
        if (id) {
            params.id = id;
            params.status = $('f-active').checked ? 'active' : 'inactive';
            p = api('update_subscription', params, 'POST');
        } else {
            params.active = $('f-active').checked ? 1 : 0;
            p = api('create_subscription', params, 'POST');
        }
        p.then(function () {
            msg(id ? 'Subscription updated.' : 'Subscription created.', 'success');
            hideForm();
            loadSubs();
        }).catch(function (e) { msg(e.message, 'danger'); });
    }

    $('sub-table').addEventListener('click', function (ev) {
        var btn = ev.target.closest('button[data-act]');
        if (!btn) { return; }
        var id = btn.getAttribute('data-id');
        var act = btn.getAttribute('data-act');
        var row = btn.closest('tr');
        if (act === 'edit') { showForm(row._sub); return; }
        if (act === 'activate') {
            api('activate_subscription', { id: id }, 'POST').then(function () { msg('Activated.', 'success'); loadSubs(); }).catch(function (e) { msg(e.message, 'danger'); });
        } else if (act === 'deactivate') {
            api('deactivate_subscription', { id: id }, 'POST').then(function () { msg('Deactivated.', 'success'); loadSubs(); }).catch(function (e) { msg(e.message, 'danger'); });
        } else if (act === 'delete') {
            if (!confirm('Delete this subscription? Only possible if it was never purchased. This cannot be undone.')) { return; }
            api('delete_subscription', { id: id }, 'POST').then(function () { msg('Deleted.', 'success'); loadSubs(); }).catch(function (e) { msg(e.message, 'danger'); });
        }
    });

    $('sub-new').addEventListener('click', function () { showForm(null); });
    $('sub-refresh').addEventListener('click', loadSubs);
    $('sub-save').addEventListener('click', save);
    $('sub-cancel').addEventListener('click', hideForm);

    // ── Course access (New UI) ──
    function loadCategories() {
        api('get_categories_with_courses').then(function(categories) {
            var container = $('categories-container');
            if (!categories.length) {
                container.innerHTML = '<span class="text-muted">No categories with courses found.</span>';
                return;
            }
            var html = '';
            categories.forEach(function(cat) {
                html += '<div class="card category-card">';
                html += '<div class="card-header"><strong>' + esc(cat.name) + '</strong></div>';
                html += '<div class="card-body">';
                cat.courses.forEach(function(c) {
                    html += '<label class="course-chip">' +
                        '<input type="checkbox" class="course-checkbox" id="cb-course-' + c.id + '" value="' + c.id + '">' +
                        esc(c.fullname) + '</label>';
                });
                html += '</div></div>';
            });
            container.innerHTML = html;
            $('course-selector-area').style.display = 'block';
        }).catch(function(e) { msg(e.message, 'danger'); });
    }

    function populateSubscriptionDropdown() {
        var select = $('target-subscription');
        select.innerHTML = '<option value="">Select a subscription...</option>';
        ALL_SUBS.forEach(function(sub) {
            if (sub.status === 'active') {
                select.innerHTML += '<option value="' + sub.id + '">' + esc(sub.name) + ' (#' + sub.id + ')</option>';
            }
        });
    }

    $('save-course-selection').addEventListener('click', function() {
        var subId = $('target-subscription').value;
        if (!subId) {
            msg('Please select a target subscription.', 'danger');
            return;
        }
        var courseIds = [];
        document.querySelectorAll('.course-checkbox:checked').forEach(function(cb) {
            courseIds.push(parseInt(cb.value, 10));
        });
        if (!courseIds.length) {
            msg('Please select at least one course.', 'danger');
            return;
        }
        api('set_subscription_courses', {
            subscriptionid: subId,
            courseids: JSON.stringify(courseIds)
        }, 'POST').then(function() {
            msg('Courses assigned successfully.', 'success');
            loadSubs();
            document.querySelectorAll('.course-checkbox:checked').forEach(function(cb) { cb.checked = false; });
            $('target-subscription').value = '';
        }).catch(function(e) { msg(e.message, 'danger'); });
    });

    // ── User Subscriptions (New UI) ──
    function loadUsers() {
        var tbody = $('users-table').querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="6">Loading...</td></tr>';
        api('get_all_user_subscriptions').then(function(rows) {
            if (!rows.length) { tbody.innerHTML = '<tr><td colspan="6">No user subscriptions found.</td></tr>'; return; }
            tbody.innerHTML = '';
            rows.forEach(function(r) {
                var tr = document.createElement('tr');
                var toggle = '';
                if (r.status === 'active') {
                    toggle = '<button class="btn btn-sm btn-danger btn-unsubscribe" data-id="' + r.id + '">Unsubscribe</button>';
                }
                var expires = r.expires_at > 0 ? new Date(r.expires_at * 1000).toLocaleString() : 'Never';
                tr.innerHTML = 
                    '<td>' + esc(r.user_fullname) + ' <br><small class="text-muted">' + esc(r.user_email) + '</small></td>' +
                    '<td>' + esc(r.name) + '</td>' +
                    '<td>' + esc(r.price_paid) + '</td>' +
                    '<td>' + esc(r.status) + '</td>' +
                    '<td>' + expires + '</td>' +
                    '<td>' + toggle + '</td>';
                tbody.appendChild(tr);
            });
        }).catch(function(e) { msg(e.message, 'danger'); });
    }

    $('users-table').addEventListener('click', function(ev) {
        var btn = ev.target.closest('.btn-unsubscribe');
        if (!btn) return;
        var purchaseId = btn.getAttribute('data-id');
        var refund = confirm('Do you want to refund the money for this subscription?');
        if (confirm('Are you sure you want to unsubscribe this user? This cannot be undone.')) {
            api('unsubscribe_user', {
                purchaseid: purchaseId,
                refund: refund ? 1 : 0
            }, 'POST').then(function() {
                msg('User unsubscribed successfully.', 'success');
                loadUsers();
            }).catch(function(e) { msg(e.message, 'danger'); });
        }
    });

    $('refresh-users').addEventListener('click', loadUsers);

    loadCategories();
    loadUsers();

    loadSubs();
})();
JS
);

echo $OUTPUT->footer();
