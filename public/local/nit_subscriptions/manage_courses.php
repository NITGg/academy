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

/**
 * Admin UI for course access, in two tabs:
 *
 *  1. "Course purchases" — who bought which single course, and "unbuy" (revoke) a purchase,
 *     unenrolling the buyer. Sibling of manage_subscriptions.php (sesskey + inline JS +
 *     AcademyUI.paginate). Reuses the subscriptions capability so no new capability/DB migration
 *     is required.
 *
 *  2. "Enrolment sources" (AC-4.10.5) — every enrolment with the source that produced it: direct
 *     purchase, package, coupon, offer or administrator grant. The two tabs belong together
 *     because they answer the same question from opposite ends: the first is the money that came
 *     in, the second is the access that went out and why.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/nit_subscriptions/lib.php');

use local_nit_subscriptions\enrolment_source;

admin_externalpage_setup('local_nit_subscriptions_managecourses');
require_capability('local/nit_subscriptions:managesubscriptions', context_system::instance());

global $OUTPUT, $CFG, $PAGE;

// ── CSV export of the enrolment-source report ────────────────────────────────────────────
// Answered before any output, because a download is not a page. It runs the same query the tab
// runs, under the same filters, but without the display cap: "for reporting" means the whole
// list has to be gettable, not just the first screenful.
if (optional_param('export', '', PARAM_ALPHA) === 'csv') {
    require_sesskey();
    require_once($CFG->libdir . '/csvlib.class.php');

    $report = enrolment_source::get_report([
        'source'   => optional_param('source', '', PARAM_ALPHA),
        'courseid' => optional_param('courseid', 0, PARAM_INT),
        'from'     => optional_param('from', 0, PARAM_INT),
        'to'       => optional_param('to', 0, PARAM_INT),
        'q'        => optional_param('q', '', PARAM_TEXT),
    ], 0);

    $csv = new csv_export_writer();
    $csv->set_filename('enrolment-sources-' . userdate(time(), '%Y-%m-%d'));
    $csv->add_data([
        get_string('es_col_user', 'local_nit_subscriptions'),
        get_string('email'),
        get_string('es_col_course', 'local_nit_subscriptions'),
        get_string('es_col_source', 'local_nit_subscriptions'),
        get_string('es_col_detail', 'local_nit_subscriptions'),
        get_string('es_col_amount', 'local_nit_subscriptions'),
        get_string('currency'),
        get_string('es_col_enrolled', 'local_nit_subscriptions'),
        get_string('es_inferred', 'local_nit_subscriptions'),
    ]);
    foreach ($report['rows'] as $row) {
        $csv->add_data([
            $row['user_fullname'],
            $row['user_email'],
            $row['course_fullname'],
            $row['source_label'],
            $row['detail'],
            $row['amount'] > 0 ? number_format($row['amount'], 2, '.', '') : '',
            $row['amount'] > 0 ? $row['currency'] : '',
            userdate($row['timecreated']),
            $row['inferred'] ? '1' : '0',
        ]);
    }
    $csv->download_file();
    exit;
}

$PAGE->set_title(get_string('managecourses', 'local_nit_subscriptions'));
$PAGE->set_heading(get_string('managecourses', 'local_nit_subscriptions'));
$PAGE->requires->js(new moodle_url('/local/nit_subscriptions/ui.js',
    ['v' => get_config('local_nit_subscriptions', 'version')]), true);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecourses', 'local_nit_subscriptions'));

// Localised strings: server-rendered HTML reads $STR['key']; the JS reads window.ACADEMY_STR.
$STR = local_nit_subscriptions_string_map(array(
    'ui_refresh', 'ui_loading', 'ui_cancel', 'ui_optional', 'ui_pager_info', 'ui_search',
    'pkg_col_user', 'pkg_col_pricepaid', 'pkg_col_status', 'pkg_col_actions',
    'mc_desc', 'mc_col_course', 'mc_col_purchased', 'mc_none',
    'mc_status_enrolled', 'mc_status_norole', 'mc_status_revoked', 'mc_status_refunded',
    'mc_unbuy', 'mc_unbuy_title', 'mc_unbuy_confirm', 'mc_unbuy_confirm_norole',
    'mc_unbuy_refund', 'mc_unbuy_success',
    'tab_mc_purchases', 'tab_mc_sources',
    'es_heading', 'es_desc', 'es_none', 'es_total',
    'es_col_user', 'es_col_course', 'es_col_source', 'es_col_detail', 'es_col_amount',
    'es_col_enrolled',
    'es_filter_course', 'es_filter_from', 'es_filter_to',
    'es_filter_allcourses', 'es_filter_apply', 'es_filter_reset',
    'es_search_ph', 'es_export', 'es_inferred', 'es_inferred_help',
    'es_truncated', 'es_pending',
    'err_sessionexpired', 'err_requestfailed',
));

echo html_writer::script('window.ACADEMY_MC = ' . json_encode(array(
    'endpoint' => (new moodle_url('/local/nit_subscriptions/api.php'))->out(false),
    'pageurl'  => (new moodle_url('/local/nit_subscriptions/manage_courses.php'))->out(false),
    'sesskey'  => sesskey(),
    'lang'     => optional_param('lang', current_language(), PARAM_LANG),
)) . ';');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');
?>
<style>
    /* Every colour here reads from the theme_nit brand palette (--nit-brand-*), like the sibling
       manage_subscriptions.php page: the hard-coded #fff modal and #0f6cbf pager were a white
       island on the dark theme, and unreadable in it. */
    .academy-modal-backdrop { position: fixed; top:0; left:0; right:0; bottom:0;
        background: color-mix(in srgb, var(--nit-brand-background) 50%, transparent);
        display:flex; align-items:center; justify-content:center; z-index:1050; }
    .academy-modal { background: var(--nit-brand-surface); color: var(--nit-brand-textprimary);
        border: 1px solid var(--nit-brand-borderprimary); border-radius:10px; padding:1.5rem;
        max-width:440px; width:90%; box-shadow:0 12px 30px rgba(0,0,0,0.35); }
    .academy-modal-title { margin-bottom:.75rem; font-weight:600; color: var(--nit-brand-textprimary); }
    .academy-modal-actions { display:flex; justify-content:flex-end; gap:.5rem; margin-top:1.25rem; }
    .academy-modal .form-check-label { color: var(--nit-brand-textprimary); }
    .academy-modal .text-muted { color: var(--nit-brand-textsecondary) !important; }
    .acad-pager { display:flex; flex-wrap:wrap; align-items:center; gap:.35rem; margin:1rem 0; }
    .acad-pager__info { margin-inline-end:auto; color:var(--nit-brand-textsecondary); font-size:.9rem; }
    .acad-pager button { border:1px solid var(--nit-brand-borderprimary); background:var(--nit-brand-surface);
        color:var(--nit-brand-textprimary); border-radius:6px; padding:.25rem .6rem; cursor:pointer; }
    .acad-pager button.is-active { background:var(--nit-brand-primary); border-color:var(--nit-brand-primary);
        color:var(--nit-brand-textprimary); }
    .acad-pager button:disabled { opacity:.5; cursor:default; }
    /* The "how this page works" note: a brand-tinted info panel instead of a bootstrap-yellow bar. */
    .mc-note { background: color-mix(in srgb, var(--nit-brand-info) 14%, var(--nit-brand-surface));
        border: 1px solid color-mix(in srgb, var(--nit-brand-info) 45%, transparent);
        color: var(--nit-brand-textprimary); padding: 10px 14px; border-radius: 8px; }

    /* ── Enrolment sources tab ───────────────────────────────────────────────────────── */
    /* The counts read as a row of totals, so they are cards rather than another table: the
       question this tab exists for ("how many came from where?") is answered before any
       scrolling happens, and clicking one filters the table to it. */
    .es-cards { display:flex; flex-wrap:wrap; gap:.6rem; margin:0 0 1rem; }
    .es-card { flex:1 1 8.5rem; min-width:8.5rem; text-align:start; cursor:pointer;
        border:1px solid var(--nit-brand-borderprimary); border-radius:10px; padding:.6rem .8rem;
        background:var(--nit-brand-surface); color:var(--nit-brand-textprimary); }
    .es-card:hover { border-color:var(--nit-brand-primary); }
    .es-card.is-active { border-color:var(--nit-brand-primary);
        background:color-mix(in srgb, var(--nit-brand-primary) 14%, var(--nit-brand-surface)); }
    .es-card__count { display:block; font-size:1.5rem; font-weight:700; line-height:1.2; }
    .es-card__label { display:block; font-size:.85rem; color:var(--nit-brand-textsecondary); }
    .es-filters { display:flex; flex-wrap:wrap; gap:.6rem; align-items:flex-end; margin-bottom:1rem; }
    .es-filters .es-field { display:flex; flex-direction:column; gap:.2rem; }
    .es-filters label { font-size:.8rem; color:var(--nit-brand-textsecondary); margin:0; }
    .es-filters .form-control, .es-filters .form-select { min-width:11rem; }
    .es-badge { display:inline-block; padding:.2rem .5rem; border-radius:999px; font-size:.8rem;
        border:1px solid color-mix(in srgb, var(--nit-brand-primary) 45%, transparent);
        background:color-mix(in srgb, var(--nit-brand-primary) 14%, var(--nit-brand-surface));
        color:var(--nit-brand-textprimary); white-space:nowrap; }
    .es-badge--admin { border-color:color-mix(in srgb, var(--nit-brand-info) 45%, transparent);
        background:color-mix(in srgb, var(--nit-brand-info) 14%, var(--nit-brand-surface)); }
    .es-badge--other { border-color:var(--nit-brand-borderprimary); background:transparent;
        color:var(--nit-brand-textsecondary); }
    .es-inferred { font-size:.75rem; color:var(--nit-brand-textsecondary); margin-inline-start:.35rem;
        border-bottom:1px dotted currentColor; cursor:help; }
    .es-table-wrap { overflow-x:auto; }
</style>
<div id="academy-mc-app">
    <div id="mc-message" class="alert" style="display:none"></div>

    <!-- ── Tabs ─────────────────────────────────────────────────────────────────────
         Panes, not separate pages, exactly as manage_subscriptions.php does it: the choice
         rides in the URL hash so a reload — and the admin's own bookmark — lands back where
         they left off. -->
    <ul class="nav nav-tabs mb-3" id="mc-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link active" role="tab"
                    data-mctab="purchases"><?php echo $STR['tab_mc_purchases']; ?></button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link" role="tab"
                    data-mctab="sources"><?php echo $STR['tab_mc_sources']; ?></button>
        </li>
    </ul>

    <!-- ══ TAB 1: single-course purchases ══════════════════════════════════════════ -->
    <div data-mctabpane="purchases" role="tabpanel">

    <p class="mc-note"><?php echo $STR['mc_desc']; ?></p>
    <button id="mc-refresh" class="btn btn-secondary mb-2"><?php echo $STR['ui_refresh']; ?></button>
    <div class="es-table-wrap">
    <table class="table table-striped" id="mc-table">
        <thead>
            <tr>
                <th><?php echo $STR['pkg_col_user']; ?></th>
                <th><?php echo $STR['mc_col_course']; ?></th>
                <th><?php echo $STR['pkg_col_pricepaid']; ?></th>
                <th><?php echo $STR['pkg_col_status']; ?></th>
                <th><?php echo $STR['mc_col_purchased']; ?></th>
                <th><?php echo $STR['pkg_col_actions']; ?></th>
            </tr>
        </thead>
        <tbody><tr><td colspan="6"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
    </table>
    </div>
    <div id="mc-table-pager" class="acad-pager"></div>

    </div><!-- /tab: purchases -->

    <!-- ══ TAB 2: enrolment sources (AC-4.10.5) ════════════════════════════════════ -->
    <div data-mctabpane="sources" role="tabpanel" hidden>

    <h4><?php echo $STR['es_heading']; ?></h4>
    <p class="mc-note"><?php echo $STR['es_desc']; ?></p>

    <div id="es-notice" class="alert alert-warning" style="display:none"></div>

    <div class="es-cards" id="es-cards"></div>

    <div class="es-filters">
        <div class="es-field">
            <label for="es-course"><?php echo $STR['es_filter_course']; ?></label>
            <select id="es-course" class="form-select">
                <option value="0"><?php echo $STR['es_filter_allcourses']; ?></option>
            </select>
        </div>
        <div class="es-field">
            <label for="es-from"><?php echo $STR['es_filter_from']; ?></label>
            <input type="date" id="es-from" class="form-control">
        </div>
        <div class="es-field">
            <label for="es-to"><?php echo $STR['es_filter_to']; ?></label>
            <input type="date" id="es-to" class="form-control">
        </div>
        <div class="es-field">
            <label for="es-q"><?php echo $STR['ui_search']; ?></label>
            <input type="search" id="es-q" class="form-control"
                   placeholder="<?php echo s($STR['es_search_ph']); ?>">
        </div>
        <div class="es-field">
            <button id="es-apply" class="btn btn-primary"><?php echo $STR['es_filter_apply']; ?></button>
        </div>
        <div class="es-field">
            <button id="es-reset" class="btn btn-link"><?php echo $STR['es_filter_reset']; ?></button>
        </div>
        <div class="es-field ms-auto">
            <a id="es-export" class="btn btn-secondary" href="#"><?php echo $STR['es_export']; ?></a>
        </div>
    </div>

    <div class="es-table-wrap">
    <table class="table table-striped" id="es-table">
        <thead>
            <tr>
                <th><?php echo $STR['es_col_user']; ?></th>
                <th><?php echo $STR['es_col_course']; ?></th>
                <th><?php echo $STR['es_col_source']; ?></th>
                <th><?php echo $STR['es_col_detail']; ?></th>
                <th><?php echo $STR['es_col_amount']; ?></th>
                <th><?php echo $STR['es_col_enrolled']; ?></th>
            </tr>
        </thead>
        <tbody><tr><td colspan="6"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
    </table>
    </div>
    <div id="es-table-pager" class="acad-pager"></div>

    </div><!-- /tab: sources -->

    <!-- ── Unbuy confirmation modal ── -->
    <div id="mc-modal-backdrop" class="academy-modal-backdrop" style="display:none;">
        <div class="academy-modal">
            <h5 class="academy-modal-title"><?php echo $STR['mc_unbuy_title']; ?></h5>
            <p id="mc-modal-text"></p>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="mc-refund-checkbox">
                <label class="form-check-label" for="mc-refund-checkbox">
                    <?php echo $STR['mc_unbuy_refund']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span>
                </label>
            </div>
            <div class="academy-modal-actions">
                <button id="mc-modal-cancel" class="btn btn-link"><?php echo $STR['ui_cancel']; ?></button>
                <button id="mc-modal-confirm" class="btn btn-danger"><?php echo $STR['mc_unbuy']; ?></button>
            </div>
        </div>
    </div>
</div>
<?php

echo html_writer::script(<<<'JS'
(function () {
    var CFG = window.ACADEMY_MC;
    var STR = window.ACADEMY_STR || {};
    function str(k){ return (k in STR) ? STR[k] : k; }
    function strf(k, params){
        var s = str(k);
        if (params == null){ return s; }
        if (typeof params !== 'object'){ return s.replace(/\{\$a\}/g, params); }
        return s.replace(/\{\$a->(\w+)\}/g, function(m, name){ return (name in params) ? params[name] : m; });
    }
    function $(id){ return document.getElementById(id); }

    var PAGE_SIZE = 10, pager = null;

    function msg(text, type){
        var el = $('mc-message');
        el.textContent = text;
        el.className = 'alert alert-' + (type || 'info');
        el.style.display = 'block';
        if (type === 'success'){ setTimeout(function(){ el.style.display = 'none'; }, 3000); }
    }
    function esc(s){
        return String(s == null ? '' : s).replace(/[&<>"]/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
        });
    }
    // GET for reads, POST for mutations (the API requires POST + sesskey for state changes).
    function api(func, params, method){
        params = params || {}; method = method || 'GET';
        var data = new URLSearchParams({ function: func, sesskey: CFG.sesskey });
        if (CFG.lang){ data.append('alang', CFG.lang); }
        Object.keys(params).forEach(function(k){ data.append(k, params[k]); });
        var opts, url = CFG.endpoint;
        if (method === 'POST'){
            opts = { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:data.toString() };
        } else { url = CFG.endpoint + '?' + data.toString(); opts = {}; }
        return fetch(url, opts).then(function(r){ return r.text(); }).then(function(text){
            var json;
            try { json = JSON.parse(text); } catch(e){ throw new Error(str('err_sessionexpired')); }
            if (json.status !== 'success'){ throw new Error(json.error || str('err_requestfailed')); }
            return json.data;
        });
    }
    function money(n){ return Number(n || 0).toFixed(2); }
    function fmtDate(ts){ return ts ? new Date(ts * 1000).toLocaleString() : '—'; }

    function renderRows(items){
        var tbody = $('mc-table').querySelector('tbody');
        tbody.innerHTML = '';
        items.forEach(function(r){
            var tr = document.createElement('tr');
            // Four states: live purchase + enrolled, live purchase without a role (payment landed
            // but enrolment did not), and the two revoked ends — cancelled ("Revoked") / refunded.
            var statusLabel, statusClass;
            if (!r.active){
                var refunded = (r.status === 'refunded');
                statusLabel = refunded ? str('mc_status_refunded') : str('mc_status_revoked');
                statusClass = refunded ? 'badge-warning' : 'badge-secondary';
            } else {
                statusLabel = r.enrolled ? str('mc_status_enrolled') : str('mc_status_norole');
                statusClass = r.enrolled ? 'badge-success' : 'badge-secondary';
            }
            // Unbuy revokes the PURCHASE, so it stays available for any live purchase — including one
            // whose buyer is not enrolled. (Gating it on r.enrolled hid the button whenever the
            // enrolment was removed elsewhere, leaving the purchase impossible to revoke.)
            var action = r.active
                ? '<button class="btn btn-sm btn-danger btn-unbuy" data-id="' + r.id + '">' + esc(str('mc_unbuy')) + '</button>'
                : '';
            tr.innerHTML =
                '<td>' + esc(r.user_fullname) + ' <br><small class="text-muted">' + esc(r.user_email) + '</small></td>' +
                '<td>' + esc(r.course_fullname) + '</td>' +
                '<td>' + esc(money(r.amount)) + ' ' + esc(r.currency || '') + '</td>' +
                '<td><span class="badge ' + statusClass + '">' + esc(statusLabel) + '</span></td>' +
                '<td>' + esc(fmtDate(r.timecreated)) + '</td>' +
                '<td>' + action + '</td>';
            tr._row = r;
            tbody.appendChild(tr);
        });
    }

    function load(){
        var tbody = $('mc-table').querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="6">' + esc(str('ui_loading')) + '</td></tr>';
        api('get_all_course_purchases').then(function(rows){
            if (!rows.length){
                tbody.innerHTML = '<tr><td colspan="6">' + esc(str('mc_none')) + '</td></tr>';
                $('mc-table-pager').innerHTML = '';
                return;
            }
            if (pager){ pager.setRows(rows); }
            else {
                pager = AcademyUI.paginate({ rows:rows, pageSize:PAGE_SIZE, pagerEl:$('mc-table-pager'),
                    labels:{ info:str('ui_pager_info') }, render:renderRows });
            }
        }).catch(function(e){ msg(e.message, 'danger'); });
    }

    // ── Unbuy confirmation modal ──
    var pending = null;
    function openModal(row){
        pending = row;
        // The buyer may already have been unenrolled elsewhere — don't promise to unenrol them again.
        $('mc-modal-text').innerHTML = strf(row.enrolled ? 'mc_unbuy_confirm' : 'mc_unbuy_confirm_norole', {
            user: esc(row.user_fullname), course: esc(row.course_fullname)
        });
        $('mc-refund-checkbox').checked = false;
        $('mc-modal-backdrop').style.display = 'flex';
    }
    function closeModal(){ pending = null; $('mc-modal-backdrop').style.display = 'none'; }

    $('mc-table').addEventListener('click', function(ev){
        var btn = ev.target.closest('.btn-unbuy');
        if (!btn){ return; }
        openModal(btn.closest('tr')._row);
    });
    $('mc-modal-cancel').addEventListener('click', closeModal);
    $('mc-modal-backdrop').addEventListener('click', function(ev){ if (ev.target === this){ closeModal(); } });
    document.addEventListener('keydown', function(ev){
        if (ev.key === 'Escape' && $('mc-modal-backdrop').style.display !== 'none'){ closeModal(); }
    });
    $('mc-modal-confirm').addEventListener('click', function(){
        var row = pending;
        if (!row){ return; }
        var refund = $('mc-refund-checkbox').checked;
        api('revoke_course_purchase', { transactionid: row.id, refund: refund ? 1 : 0 }, 'POST').then(function(){
            msg(str('mc_unbuy_success'), 'success');
            closeModal();
            load();
            // The revoked purchase takes the enrolment with it, so the other tab is now stale.
            esLoaded = false;
        }).catch(function(e){ msg(e.message, 'danger'); });
    });

    $('mc-refresh').addEventListener('click', load);
    load();

    // ══ Enrolment sources (AC-4.10.5) ═══════════════════════════════════════════════
    var esPager = null, esLoaded = false, esCourses = null;
    var esFilter = { source: '', courseid: 0, from: 0, to: 0, q: '' };

    // A <input type=date> is a local calendar day; the server stores instants. "To" therefore
    // means the END of that day, or a same-day filter would match nothing.
    function dayStart(value){
        if (!value){ return 0; }
        var d = new Date(value + 'T00:00:00');
        return isNaN(d.getTime()) ? 0 : Math.floor(d.getTime() / 1000);
    }
    function dayEnd(value){
        var start = dayStart(value);
        return start ? start + 86399 : 0;
    }

    function readFilters(){
        esFilter.courseid = parseInt($('es-course').value, 10) || 0;
        esFilter.from = dayStart($('es-from').value);
        esFilter.to = dayEnd($('es-to').value);
        esFilter.q = $('es-q').value.trim();
    }

    function badgeClass(source){
        if (source === 'admin' || source === 'self'){ return 'es-badge es-badge--admin'; }
        if (source === 'other'){ return 'es-badge es-badge--other'; }
        return 'es-badge';
    }

    function renderEsRows(items){
        var tbody = $('es-table').querySelector('tbody');
        tbody.innerHTML = '';
        items.forEach(function(r){
            var tr = document.createElement('tr');
            var flag = r.inferred
                ? ' <span class="es-inferred" title="' + esc(str('es_inferred_help')) + '">' +
                  esc(str('es_inferred')) + '</span>'
                : '';
            var paid = (r.amount > 0)
                ? esc(money(r.amount) + ' ' + (r.currency || ''))
                : '<span class="text-muted">—</span>';
            tr.innerHTML =
                '<td>' + esc(r.user_fullname) + '<br><small class="text-muted">' + esc(r.user_email) + '</small></td>' +
                '<td>' + esc(r.course_fullname) + '</td>' +
                '<td><span class="' + badgeClass(r.source) + '">' + esc(r.source_label) + '</span>' + flag + '</td>' +
                '<td>' + (r.detail ? esc(r.detail) : '<span class="text-muted">—</span>') + '</td>' +
                '<td>' + paid + '</td>' +
                '<td>' + esc(fmtDate(r.timecreated)) + '</td>';
            tbody.appendChild(tr);
        });
    }

    function renderEsCards(summary){
        var box = $('es-cards');
        box.innerHTML = '';
        var cards = [{ source:'', label:str('es_total'), count:summary.total }].concat(summary.items);
        cards.forEach(function(c){
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'es-card' + (esFilter.source === c.source ? ' is-active' : '');
            b.innerHTML = '<span class="es-card__count">' + esc(c.count) + '</span>' +
                '<span class="es-card__label">' + esc(c.label) + '</span>';
            b.addEventListener('click', function(){
                // Clicking the card that is already on clears it, so the row of cards doubles as
                // the source filter and never traps the admin in one slice.
                esFilter.source = (esFilter.source === c.source) ? '' : c.source;
                loadEs();
            });
            box.appendChild(b);
        });
    }

    // Filled once: the list only ever grows with new courses, and refilling it would throw away
    // whatever the admin had selected.
    function fillCourses(courses){
        if (esCourses){ return; }
        esCourses = courses || [];
        var sel = $('es-course');
        esCourses.forEach(function(c){
            var opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name;
            sel.appendChild(opt);
        });
        if (esFilter.courseid){ sel.value = String(esFilter.courseid); }
    }

    function exportUrl(){
        var p = new URLSearchParams({ export:'csv', sesskey:CFG.sesskey });
        if (esFilter.source){ p.append('source', esFilter.source); }
        if (esFilter.courseid){ p.append('courseid', esFilter.courseid); }
        if (esFilter.from){ p.append('from', esFilter.from); }
        if (esFilter.to){ p.append('to', esFilter.to); }
        if (esFilter.q){ p.append('q', esFilter.q); }
        return CFG.pageurl + '?' + p.toString();
    }

    function loadEs(){
        var tbody = $('es-table').querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="6">' + esc(str('ui_loading')) + '</td></tr>';
        $('es-export').setAttribute('href', exportUrl());

        api('get_enrolment_sources', {
            source: esFilter.source,
            courseid: esFilter.courseid,
            from: esFilter.from,
            to: esFilter.to,
            q: esFilter.q
        }).then(function(data){
            esLoaded = true;
            fillCourses(data.courses);
            renderEsCards(data.summary);

            // Two honest caveats, never silent: the list was cut short, and/or older enrolments
            // are still being classified in the background.
            var notes = [];
            if (data.truncated){
                notes.push(strf('es_truncated', { shown: data.rows.length, total: data.total }));
            }
            if (data.pending > 0){ notes.push(strf('es_pending', data.pending)); }
            var notice = $('es-notice');
            notice.textContent = notes.join(' ');
            notice.style.display = notes.length ? 'block' : 'none';

            if (!data.rows.length){
                tbody.innerHTML = '<tr><td colspan="6">' + esc(str('es_none')) + '</td></tr>';
                $('es-table-pager').innerHTML = '';
                esPager = null;
                return;
            }
            if (esPager){ esPager.setRows(data.rows); }
            else {
                esPager = AcademyUI.paginate({ rows:data.rows, pageSize:PAGE_SIZE,
                    pagerEl:$('es-table-pager'), labels:{ info:str('ui_pager_info') },
                    render:renderEsRows });
            }
        }).catch(function(e){ msg(e.message, 'danger'); });
    }

    $('es-apply').addEventListener('click', function(){ readFilters(); loadEs(); });
    $('es-q').addEventListener('keydown', function(ev){
        if (ev.key === 'Enter'){ ev.preventDefault(); readFilters(); loadEs(); }
    });
    $('es-reset').addEventListener('click', function(){
        esFilter = { source:'', courseid:0, from:0, to:0, q:'' };
        $('es-course').value = '0';
        $('es-from').value = '';
        $('es-to').value = '';
        $('es-q').value = '';
        loadEs();
    });

    // ── Tabs ────────────────────────────────────────────────────────────────────
    var TABS = ['purchases', 'sources'];

    function showTab(name){
        if (TABS.indexOf(name) === -1){ name = TABS[0]; }
        TABS.forEach(function(t){
            var pane = document.querySelector('[data-mctabpane="' + t + '"]');
            var btn = document.querySelector('[data-mctab="' + t + '"]');
            if (pane){ pane.hidden = (t !== name); }
            if (btn){
                btn.classList.toggle('active', t === name);
                btn.setAttribute('aria-selected', t === name ? 'true' : 'false');
            }
        });
        // The report classifies older enrolments as it goes, so it is fetched when the tab is
        // first opened rather than on every page load of the purchases list.
        if (name === 'sources' && !esLoaded){ loadEs(); }
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-mctab]'), function(btn){
        btn.addEventListener('click', function(){
            var name = btn.getAttribute('data-mctab');
            if (window.history && history.replaceState){ history.replaceState(null, '', '#' + name); }
            else { location.hash = name; }
            showTab(name);
        });
    });

    showTab((location.hash || '').replace('#', ''));
})();
JS
);

echo $OUTPUT->footer();
