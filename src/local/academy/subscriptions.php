<?php
// Student subscriptions UI for the Academy Flex platform. Exercises the same api.php endpoints the
// mobile app uses: browse available subscriptions (US-SB-1-1), buy one (US-SB-1-2), and track my
// subscriptions + payment history (US-SB-2-1). Mirrors student.php: mints a mobile web-service
// token and drives /local/academy/api.php from vanilla JS.

require('../../config.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');

require_login();

global $DB, $OUTPUT, $CFG, $PAGE;

$service = $DB->get_record('external_services', array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
$token = external_generate_token_for_current_user($service)->token;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/academy/subscriptions.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('subscriptionhub', 'local_academy'));
$PAGE->set_heading(get_string('subscriptionhub', 'local_academy'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('subscriptionhub', 'local_academy'));
echo html_writer::script('window.ACADEMY_SUBST = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'token'    => $token,
)) . ';');
?>
<style>
#sb-app{max-width:920px}
#sb-tabs{display:flex;gap:.4rem;border-bottom:2px solid #dee2e6;margin-bottom:1rem;flex-wrap:wrap}
.sb-tab{padding:.5rem 1rem;border:none;background:none;font-weight:600;color:#6c757d;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px}
.sb-tab.active{color:#0d6efd;border-bottom-color:#0d6efd}
.sb-panel{display:none}
.sb-panel.active{display:block}
.sb-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:.75rem}
.sb-card{border:1px solid #dee2e6;border-radius:.5rem;padding:1rem}
.sb-title{font-weight:600;font-size:1.05rem}
.sb-price{font-size:1.4rem;font-weight:700;color:#084298;margin:.25rem 0}
.sb-meta{color:#6c757d;font-size:.9rem}
.sb-courses{margin-top:.4rem;display:flex;gap:.3rem;flex-wrap:wrap}
.sb-chip{background:#e9ecef;border-radius:1rem;padding:.1rem .55rem;font-size:.8rem}
table.sb-table{width:100%;border-collapse:collapse;margin-top:.5rem}
table.sb-table th,table.sb-table td{border-bottom:1px solid #eee;padding:.45rem .5rem;text-align:left;font-size:.9rem}
.sb-badge{display:inline-block;padding:.15rem .55rem;border-radius:1rem;font-size:.8rem;font-weight:600}
.s-active,.s-success{background:#d4edda;color:#155724}
.s-expired,.s-cancelled{background:#e2e3e5;color:#383d41}
.s-pending{background:#fff3cd;color:#856404}
.s-payment_failed,.s-failed{background:#f8d7da;color:#721c24}
.sb-empty{color:#6c757d;padding:1.5rem;text-align:center}
.sb-section{margin-top:1.5rem}
</style>
<div id="sb-app">
  <div id="sb-msg" class="alert" style="display:none"></div>

  <div id="sb-tabs">
    <button class="sb-tab active" data-tab="browse">Available subscriptions</button>
    <button class="sb-tab" data-tab="mine">My subscriptions</button>
  </div>

  <!-- ── Browse (US-SB-1-1 / US-SB-1-2) ── -->
  <div class="sb-panel active" id="panel-browse">
    <div id="sb-available" class="sb-grid"></div>
  </div>

  <!-- ── Mine + payments (US-SB-2-1) ── -->
  <div class="sb-panel" id="panel-mine">
    <h5>My subscriptions</h5>
    <table class="sb-table">
      <thead><tr><th>Subscription</th><th>Status</th><th>Activated</th><th>Expires</th><th>Days left</th><th>Courses</th></tr></thead>
      <tbody id="sb-mine"></tbody>
    </table>

    <div class="sb-section">
      <h5>Payment history</h5>
      <table class="sb-table">
        <thead><tr><th>Subscription</th><th>Amount</th><th>Date</th><th>Method</th><th>Status</th><th>Transaction</th></tr></thead>
        <tbody id="sb-payments"></tbody>
      </table>
    </div>
  </div>
</div>
<?php

echo html_writer::script(<<<'JS'
(function () {
    var CFG = window.ACADEMY_SUBST;
    function $(id) { return document.getElementById(id); }

    function msg(text, type) {
        var el = $('sb-msg');
        el.textContent = text;
        el.className = 'alert alert-' + (type || 'info');
        el.style.display = 'block';
        if (type === 'success') { setTimeout(function () { el.style.display = 'none'; }, 3500); }
    }

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
    function fmtDate(ts) { return (ts && ts > 0) ? new Date(ts * 1000).toLocaleDateString() : '—'; }
    function chips(courses) {
        if (!courses || !courses.length) { return '<span class="text-muted">—</span>'; }
        return '<span class="sb-courses">' + courses.map(function (c) {
            return '<span class="sb-chip">' + esc(c.fullname) + '</span>';
        }).join('') + '</span>';
    }

    function loadAvailable() {
        var box = $('sb-available');
        box.innerHTML = 'Loading…';
        api('get_available_subscriptions').then(function (rows) {
            if (!rows.length) { box.innerHTML = '<div class="sb-empty">No subscriptions available right now.</div>'; return; }
            box.innerHTML = '';
            rows.forEach(function (s) {
                var card = document.createElement('div');
                card.className = 'sb-card';
                card.innerHTML =
                    '<div class="sb-title">' + esc(s.name) + '</div>' +
                    '<div class="sb-price">' + esc(s.price) + ' EGP</div>' +
                    '<div class="sb-meta">' + esc(s.duration_days) + ' days</div>' +
                    (s.description ? '<div class="sb-meta">' + esc(s.description) + '</div>' : '') +
                    '<div class="sb-meta" style="margin-top:.4rem">Courses:</div>' + chips(s.courses) +
                    '<div style="margin-top:.75rem"><button class="btn btn-primary btn-sm" data-buy="' + s.id + '">Buy subscription</button></div>';
                box.appendChild(card);
            });
        }).catch(function (e) { msg(e.message, 'danger'); });
    }

    function buy(subscriptionid) {
        if (!confirm('Buy this subscription? (Payment is assumed successful in this demo.)')) { return; }
        api('purchase_subscription', { subscriptionid: subscriptionid, method: 'online' }, 'POST')
            .then(function (d) {
                msg('Subscription active. Transaction ' + d.transaction_no + '. You now have access to ' +
                    (d.courses ? d.courses.length : 0) + ' course(s).', 'success');
                loadMine();
                loadPayments();
            }).catch(function (e) { msg(e.message, 'danger'); });
    }

    function loadMine() {
        var tb = $('sb-mine');
        tb.innerHTML = '<tr><td colspan="6">Loading…</td></tr>';
        api('get_my_subscriptions').then(function (rows) {
            if (!rows.length) { tb.innerHTML = '<tr><td colspan="6" class="sb-empty">No subscriptions yet.</td></tr>'; return; }
            tb.innerHTML = '';
            rows.forEach(function (s) {
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>' + esc(s.name) + '</td>' +
                    '<td><span class="sb-badge s-' + esc(s.status) + '">' + esc(s.status) + '</span></td>' +
                    '<td>' + fmtDate(s.timeactivated) + '</td>' +
                    '<td>' + fmtDate(s.expires_at) + '</td>' +
                    '<td>' + (s.status === 'active' ? esc(s.remaining_days) : '—') + '</td>' +
                    '<td>' + chips(s.courses) + '</td>';
                tb.appendChild(tr);
            });
        }).catch(function (e) { msg(e.message, 'danger'); });
    }

    function loadPayments() {
        var tb = $('sb-payments');
        tb.innerHTML = '<tr><td colspan="6">Loading…</td></tr>';
        api('get_subscription_payment_history').then(function (rows) {
            if (!rows.length) { tb.innerHTML = '<tr><td colspan="6" class="sb-empty">No payments yet.</td></tr>'; return; }
            tb.innerHTML = '';
            rows.forEach(function (p) {
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>' + esc(p.name) + '</td>' +
                    '<td>' + esc(p.amount) + ' EGP</td>' +
                    '<td>' + fmtDate(p.timecreated) + '</td>' +
                    '<td>' + esc(p.method) + '</td>' +
                    '<td><span class="sb-badge s-' + esc(p.status) + '">' + esc(p.status) + '</span></td>' +
                    '<td>' + esc(p.transaction_no) + '</td>';
                tb.appendChild(tr);
            });
        }).catch(function (e) { msg(e.message, 'danger'); });
    }

    $('sb-available').addEventListener('click', function (ev) {
        var btn = ev.target.closest('button[data-buy]');
        if (btn) { buy(btn.getAttribute('data-buy')); }
    });

    document.querySelectorAll('.sb-tab').forEach(function (b) {
        b.onclick = function () {
            var name = b.getAttribute('data-tab');
            document.querySelectorAll('.sb-tab').forEach(function (x) { x.classList.toggle('active', x === b); });
            document.querySelectorAll('.sb-panel').forEach(function (p) { p.classList.toggle('active', p.id === 'panel-' + name); });
        };
    });

    loadAvailable();
    loadMine();
    loadPayments();
})();
JS
);

echo $OUTPUT->footer();
