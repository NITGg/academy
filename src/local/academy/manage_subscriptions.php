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

    <!-- ── Course access (US-AD-6-1) ── -->
    <h4 class="mt-4">Course subscription availability</h4>
    <p class="text-muted">Choose which subscriptions can access a course. Enter a course ID and load it.</p>
    <div class="form-inline mb-3">
        <input type="number" class="form-control mr-2" id="ca-course" placeholder="Course ID" min="1">
        <button id="ca-load" class="btn btn-secondary">Load</button>
    </div>

    <div id="ca-editor" class="card" style="display:none; max-width:560px;">
        <div class="card-body">
            <h5 id="ca-title" class="card-title"></h5>
            <div class="form-check">
                <input type="radio" class="form-check-input" name="ca-mode" id="ca-mode-all" value="all">
                <label class="form-check-label" for="ca-mode-all">All subscriptions</label>
            </div>
            <div class="form-check mb-2">
                <input type="radio" class="form-check-input" name="ca-mode" id="ca-mode-specific" value="specific">
                <label class="form-check-label" for="ca-mode-specific">Specific subscriptions</label>
            </div>
            <div id="ca-sublist" class="mb-3" style="padding-left:1.5rem"></div>
            <button id="ca-save" class="btn btn-primary">Save access</button>
        </div>
    </div>
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
            if (!rows.length) { tbody.innerHTML = '<tr><td colspan="7">No subscriptions yet.</td></tr>'; return; }
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

    // ── Course access (US-AD-6-1) ──
    function renderSubChecks(selectedIds) {
        var sel = {};
        (selectedIds || []).forEach(function (i) { sel[i] = true; });
        var box = $('ca-sublist');
        if (!ALL_SUBS.length) { box.innerHTML = '<span class="text-muted">No subscriptions defined.</span>'; return; }
        box.innerHTML = ALL_SUBS.map(function (s) {
            return '<div class="form-check">' +
                '<input type="checkbox" class="form-check-input ca-sub" id="ca-sub-' + s.id + '" value="' + s.id + '"' +
                (sel[s.id] ? ' checked' : '') + '>' +
                '<label class="form-check-label" for="ca-sub-' + s.id + '">' + esc(s.name) + ' (#' + s.id + ')</label>' +
                '</div>';
        }).join('');
    }

    function loadCourseAccess() {
        var courseid = $('ca-course').value;
        if (!courseid) { msg('Enter a course ID.', 'danger'); return; }
        // Make sure ALL_SUBS is populated for the checkboxes.
        var ensure = ALL_SUBS.length ? Promise.resolve() : api('get_subscriptions').then(function (r) { ALL_SUBS = r; });
        ensure.then(function () { return api('get_course_access', { courseid: courseid }); })
            .then(function (access) {
                $('ca-title').textContent = 'Course #' + courseid + ' access';
                var mode = access.mode === 'all' ? 'all' : 'specific';
                $('ca-mode-all').checked = (mode === 'all');
                $('ca-mode-specific').checked = (mode !== 'all');
                renderSubChecks(access.subscriptionids);
                $('ca-editor').style.display = 'block';
            }).catch(function (e) { msg(e.message, 'danger'); });
    }

    function saveCourseAccess() {
        var courseid = $('ca-course').value;
        var mode = $('ca-mode-all').checked ? 'all' : 'specific';
        var ids = [];
        document.querySelectorAll('.ca-sub:checked').forEach(function (c) { ids.push(parseInt(c.value, 10)); });
        api('set_course_subscriptions', {
            courseid: courseid,
            mode: mode,
            subscriptionids: JSON.stringify(ids)
        }, 'POST').then(function () {
            msg('Course access saved.', 'success');
            loadSubs(); // refresh the plans' course lists
        }).catch(function (e) { msg(e.message, 'danger'); });
    }

    $('ca-load').addEventListener('click', loadCourseAccess);
    $('ca-save').addEventListener('click', saveCourseAccess);

    loadSubs();
})();
JS
);

echo $OUTPUT->footer();
