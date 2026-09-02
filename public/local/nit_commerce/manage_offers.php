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
 * Admin UI to manage automatic offers. Drives /local/nit_commerce/api.php from vanilla JS.
 * Mirrors manage_coupons.php (offers carry no code / max / usage limit). Labels match the reference.
 *
 * Two tabs, same shape as the coupon page:
 *   - Offers: create/edit/activate/delete the offers themselves.
 *   - Reports: how many times each offer was used and the orders it was applied to (AC-4.13.7).
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/nit_commerce/lib.php');

admin_externalpage_setup('local_nit_commerce_manageoffers');
require_capability('local/nit_commerce:manageoffers', context_system::instance());

global $OUTPUT, $CFG, $PAGE;

$PAGE->set_title(get_string('manageoffers', 'local_nit_commerce'));
$PAGE->set_heading(get_string('manageoffers', 'local_nit_commerce'));
$PAGE->requires->js(new moodle_url('/local/nit_commerce/ui.js',
    ['v' => get_config('local_nit_commerce', 'version')]), true);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageoffers', 'local_nit_commerce'));

$STR = local_nit_commerce_string_map(array(
    'ui_refresh', 'ui_loading', 'ui_save', 'ui_cancel', 'ui_active', 'ui_activate', 'ui_deactivate',
    'ui_edit', 'ui_delete', 'ui_never', 'ui_optional', 'ui_pager_info',
    'pkg_col_status', 'pkg_col_actions', 'sub_inactive',
    'cpn_col_type', 'cpn_col_value', 'cpn_col_scope', 'cpn_col_dates', 'cpn_field_dtype',
    'cpn_field_value', 'cpn_field_start', 'cpn_field_end', 'cpn_field_scope', 'cpn_type_percent',
    'cpn_type_fixed', 'cpn_scope_courses', 'cpn_scope_packages', 'cpn_scope_subscriptions', 'cpn_scope_programs',
    'cpn_scope_all', 'cpn_scope_specific', 'cpn_scope_required',
    'ofr_new', 'ofr_none', 'ofr_col_name', 'ofr_field_name', 'ofr_created', 'ofr_updated',
    'ofr_activated', 'ofr_deactivated', 'ofr_deleted', 'ofr_confirm_delete', 'ofr_edit_titled',
    'ofr_delete_title',
    'pkg_field_name_en', 'pkg_field_name_ar',
    'pkg_field_desc_en', 'pkg_field_desc_ar', 'ui_showmore', 'ui_showless',
    // Report tab (AC-4.13.7).
    'ofr_rep_none', 'ofr_col_usage', 'ofr_rep_open',
    'rep_col_date', 'rep_col_learner', 'rep_col_order', 'rep_col_orderstatus', 'rep_col_item',
    'rep_col_original', 'rep_col_discount', 'rep_col_paid', 'rep_col_usages', 'rep_col_learners',
    'rep_col_last', 'rep_col_offer', 'rep_total', 'rep_held', 'rep_noorder', 'rep_filter_alloffers',
    'err_sessionexpired', 'err_requestfailed',
));
echo html_writer::script('window.ACADEMY_CFG = ' . json_encode(array(
    'endpoint' => (new moodle_url('/local/nit_commerce/api.php'))->out(false),
    'export'   => (new moodle_url('/local/nit_commerce/export_offer_usages.php'))->out(false),
    'sesskey'  => sesskey(),
    'lang'     => optional_param('lang', current_language(), PARAM_LANG),
    'currency' => \local_nit_commerce\coupon_manager::default_currency(),
)) . ';');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');
?>
<style>
    .acad-pager { display:flex; flex-wrap:wrap; align-items:center; gap:.35rem; margin:1rem 0; }
    .acad-pager__info { margin-inline-end:auto; color:#6a6f73; font-size:.9rem; }
    .acad-pager button { border:1px solid #dee2e6; background:#fff; border-radius:6px; padding:.25rem .6rem; cursor:pointer; }
    .acad-pager button.is-active { background:#0f6cbf; border-color:#0f6cbf; color:#fff; }
    .acad-pager button:disabled { opacity:.5; cursor:default; }

    /* Report tab. Neutral greys rather than brand tokens: this is an admin screen inside Boost's
       admin layout, and theme_nit's palette is not guaranteed to be loaded here. Kept identical to
       manage_coupons.php so the two reports read as one feature. */
    .rep-filters { display:flex; flex-wrap:wrap; gap:.75rem; align-items:flex-end; margin-bottom:1rem; }
    .rep-filters .rep-field { display:flex; flex-direction:column; gap:.2rem; }
    .rep-filters label { font-size:.8rem; color:#6a6f73; margin:0; }
    .rep-filters .form-control { min-width:150px; }
    .rep-kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:.75rem; margin-bottom:1.25rem; }
    .rep-kpi { border:1px solid #dee2e6; border-radius:8px; padding:.75rem 1rem; background:#fff; }
    .rep-kpi__label { font-size:.78rem; color:#6a6f73; text-transform:uppercase; letter-spacing:.03em; }
    .rep-kpi__value { font-size:1.5rem; font-weight:800; color:#1d2125; line-height:1.3; }
    .rep-kpi__value small { font-size:.8rem; font-weight:600; color:#6a6f73; }
    .rep-sub { font-size:.85rem; color:#6a6f73; margin:.15rem 0 0; }
    .rep-h { font-size:1.05rem; font-weight:700; margin:1.5rem 0 .5rem; }
    .rep-badge { display:inline-block; font-size:.72rem; font-weight:700; border-radius:999px; padding:.1rem .5rem; }
    .rep-badge--held { background:#fff3cd; color:#7a5b00; }
    .rep-badge--none { background:#e9ecef; color:#495057; }
    .rep-amount { white-space:nowrap; font-variant-numeric:tabular-nums; }
    .rep-cut { color:#a94442; font-weight:700; }
    /* An offer with no sales is a real answer, not an empty row — dim it rather than hide it. */
    .rep-row--unused { color:#6a6f73; }
</style>

<ul class="nav nav-tabs mb-3" id="ofr-tabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" href="#manage" data-tab="manage" role="tab"><?php
        echo get_string('tab_offers', 'local_nit_commerce'); ?></a></li>
    <li class="nav-item"><a class="nav-link" href="#reports" data-tab="reports" role="tab"><?php
        echo get_string('tab_reports', 'local_nit_commerce'); ?></a></li>
</ul>

<div id="academy-offer-app" data-tabpane="manage">
    <div id="ofr-message" class="alert" style="display:none"></div>

    <div class="mb-3">
        <button id="ofr-new" class="btn btn-primary"><?php echo $STR['ofr_new']; ?></button>
        <button id="ofr-refresh" class="btn btn-secondary"><?php echo $STR['ui_refresh']; ?></button>
    </div>

    <!-- Offers never stack, so an admin looking at three overlapping campaigns needs to know
         which one a buyer actually gets before they wonder why (AC-4.13.4). -->
    <p class="rep-sub mb-3"><?php echo get_string('ofr_lowest_note', 'local_nit_commerce'); ?></p>

    <div class="acad-table-wrap">
    <table class="table table-striped" id="ofr-table">
        <thead>
            <tr>
                <th><?php echo $STR['ofr_col_name']; ?></th>
                <th><?php echo $STR['cpn_col_type']; ?></th>
                <th><?php echo $STR['cpn_col_value']; ?></th>
                <th class="col-tags"><?php echo $STR['cpn_col_scope']; ?></th>
                <th><?php echo $STR['ofr_col_usage']; ?></th>
                <th class="col-tight"><?php echo $STR['cpn_col_dates']; ?></th>
                <th><?php echo $STR['pkg_col_status']; ?></th>
                <th class="col-tight"><?php echo $STR['pkg_col_actions']; ?></th>
            </tr>
        </thead>
        <tbody><tr><td colspan="8"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
    </table>
    </div>
    <div id="ofr-table-pager" class="acad-pager"></div>

    <div id="ofr-form-card" class="card" style="display:none; max-width:640px;">
        <div class="card-body">
            <h4 id="ofr-form-title" class="card-title"><?php echo $STR['ofr_new']; ?></h4>
            <input type="hidden" id="o-id">
            <div class="form-group">
                <label for="o-name-en"><?php echo $STR['pkg_field_name_en']; ?></label>
                <input type="text" class="form-control" id="o-name-en" dir="ltr">
            </div>
            <div class="form-group">
                <label for="o-name-ar"><?php echo $STR['pkg_field_name_ar']; ?></label>
                <input type="text" class="form-control" id="o-name-ar" dir="rtl">
            </div>
            <div class="form-group">
                <label for="o-desc-en"><?php echo $STR['pkg_field_desc_en']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span></label>
                <textarea class="form-control" id="o-desc-en" rows="2" dir="ltr"></textarea>
            </div>
            <div class="form-group">
                <label for="o-desc-ar"><?php echo $STR['pkg_field_desc_ar']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span></label>
                <textarea class="form-control" id="o-desc-ar" rows="2" dir="rtl"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="o-dtype"><?php echo $STR['cpn_field_dtype']; ?></label>
                    <select class="form-control" id="o-dtype">
                        <option value="percent"><?php echo $STR['cpn_type_percent']; ?></option>
                        <option value="fixed"><?php echo $STR['cpn_type_fixed']; ?></option>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="o-value"><?php echo $STR['cpn_field_value']; ?></label>
                    <input type="number" class="form-control" id="o-value" min="0" step="0.01">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="o-start"><?php echo $STR['cpn_field_start']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span></label>
                    <input type="datetime-local" class="form-control" id="o-start">
                </div>
                <div class="form-group col-md-6">
                    <label for="o-end"><?php echo $STR['cpn_field_end']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span></label>
                    <input type="datetime-local" class="form-control" id="o-end">
                </div>
            </div>

            <label class="d-block"><strong><?php echo $STR['cpn_field_scope']; ?></strong></label>
            <div id="o-scope" class="mb-3 p-2" style="border:1px solid #dee2e6; border-radius:6px;">
                <?php
                $scopetypes = array(
                    'course'       => $STR['cpn_scope_courses'],
                    'package'      => $STR['cpn_scope_packages'],
                    'subscription' => $STR['cpn_scope_subscriptions'],
                    'program'      => $STR['cpn_scope_programs'],
                );
                foreach ($scopetypes as $t => $label) {
                    echo '<div class="scope-block mb-2" data-type="' . $t . '">';
                    echo '<div class="form-check">';
                    echo '<input type="checkbox" class="form-check-input scope-on" id="oscope-' . $t . '-on">';
                    echo '<label class="form-check-label" for="oscope-' . $t . '-on"><strong>' . $label . '</strong></label>';
                    echo '</div>';
                    echo '<div class="scope-detail" style="display:none; margin:.25rem 0 .25rem 1.5rem;">';
                    echo '<select class="form-control form-control-sm scope-mode mb-1" style="max-width:220px">';
                    echo '<option value="all">' . $STR['cpn_scope_all'] . '</option>';
                    echo '<option value="specific">' . $STR['cpn_scope_specific'] . '</option>';
                    echo '</select>';
                    echo '<select class="form-control scope-list" multiple size="5" style="display:none"></select>';
                    echo '</div>';
                    echo '</div>';
                }
                ?>
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="o-active" checked>
                <label class="form-check-label" for="o-active"><?php echo $STR['ui_active']; ?></label>
            </div>
            <button id="ofr-save" class="btn btn-primary"><?php echo $STR['ui_save']; ?></button>
            <button id="ofr-cancel" class="btn btn-link"><?php echo $STR['ui_cancel']; ?></button>
        </div>
    </div>

    <!-- ── Delete confirmation modal ── -->
    <div id="ofr-confirm-backdrop" class="academy-modal-backdrop" style="display:none;">
        <div class="academy-modal">
            <div class="academy-modal-icon">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M6 7h12l-1 14H7L6 7zm3-3h6l1 2H8l1-2zM4 6h16v1H4V6z"/></svg>
            </div>
            <h5 class="academy-modal-title" id="ofr-confirm-title"></h5>
            <p id="ofr-confirm-text" class="academy-modal-text"></p>
            <div class="academy-modal-actions">
                <button id="ofr-confirm-cancel" class="btn btn-light"><?php echo $STR['ui_cancel']; ?></button>
                <button id="ofr-confirm-ok" class="btn btn-danger"><?php echo $STR['ui_delete']; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- ── Report tab (AC-4.13.7) ──
     Every application of an offer is already logged with the learner, the order, the date and the
     amount it took off. This tab is what makes those numbers answerable without a database
     client: how many times each offer was used, and which orders it was used on. -->
<div id="academy-offer-report" data-tabpane="reports" style="display:none">
    <div id="rep-message" class="alert" style="display:none"></div>
    <p class="rep-sub"><?php echo get_string('ofr_rep_intro', 'local_nit_commerce'); ?></p>

    <div class="rep-filters">
        <div class="rep-field">
            <label for="r-offer"><?php echo get_string('rep_filter_offer', 'local_nit_commerce'); ?></label>
            <select class="form-control" id="r-offer">
                <option value="0"><?php echo get_string('rep_filter_alloffers', 'local_nit_commerce'); ?></option>
            </select>
        </div>
        <div class="rep-field">
            <label for="r-item"><?php echo get_string('rep_filter_item', 'local_nit_commerce'); ?></label>
            <select class="form-control" id="r-item">
                <option value=""><?php echo get_string('rep_filter_anyitem', 'local_nit_commerce'); ?></option>
                <option value="course"><?php echo $STR['cpn_scope_courses']; ?></option>
                <option value="package"><?php echo $STR['cpn_scope_packages']; ?></option>
                <option value="subscription"><?php echo $STR['cpn_scope_subscriptions']; ?></option>
            </select>
        </div>
        <div class="rep-field">
            <label for="r-from"><?php echo get_string('rep_filter_from', 'local_nit_commerce'); ?></label>
            <input type="date" class="form-control" id="r-from">
        </div>
        <div class="rep-field">
            <label for="r-to"><?php echo get_string('rep_filter_to', 'local_nit_commerce'); ?></label>
            <input type="date" class="form-control" id="r-to">
        </div>
        <div class="rep-field">
            <label for="r-state"><?php echo get_string('rep_filter_state', 'local_nit_commerce'); ?></label>
            <select class="form-control" id="r-state">
                <option value="confirmed"><?php echo get_string('rep_state_confirmed', 'local_nit_commerce'); ?></option>
                <option value="pending"><?php echo get_string('rep_state_pending', 'local_nit_commerce'); ?></option>
                <option value="all"><?php echo get_string('rep_state_all', 'local_nit_commerce'); ?></option>
            </select>
        </div>
        <div class="rep-field" style="flex:1 1 220px">
            <label for="r-q"><?php echo get_string('rep_search', 'local_nit_commerce'); ?></label>
            <input type="search" class="form-control" id="r-q" style="min-width:100%">
        </div>
        <div class="rep-field">
            <label>&nbsp;</label>
            <div>
                <button id="rep-apply" class="btn btn-primary"><?php
                    echo get_string('rep_apply', 'local_nit_commerce'); ?></button>
                <button id="rep-reset" class="btn btn-link"><?php
                    echo get_string('rep_reset', 'local_nit_commerce'); ?></button>
                <button id="rep-export" class="btn btn-secondary"><?php
                    echo get_string('rep_export', 'local_nit_commerce'); ?></button>
            </div>
        </div>
    </div>

    <div class="rep-kpis" id="rep-kpis"></div>
    <p class="rep-sub" id="rep-heldnote" style="display:none"><?php
        echo get_string('ofr_rep_heldnote', 'local_nit_commerce'); ?></p>

    <h3 class="rep-h"><?php echo get_string('rep_byoffer', 'local_nit_commerce'); ?></h3>
    <div class="acad-table-wrap">
        <table class="table table-sm table-striped" id="rep-summary">
            <thead>
                <tr>
                    <th><?php echo $STR['ofr_col_name']; ?></th>
                    <th><?php echo $STR['cpn_col_value']; ?></th>
                    <th><?php echo $STR['pkg_col_status']; ?></th>
                    <th><?php echo get_string('rep_col_usages', 'local_nit_commerce'); ?></th>
                    <th><?php echo get_string('rep_col_learners', 'local_nit_commerce'); ?></th>
                    <th><?php echo get_string('rep_col_discount', 'local_nit_commerce'); ?></th>
                    <th><?php echo get_string('rep_col_paid', 'local_nit_commerce'); ?></th>
                    <th><?php echo get_string('rep_col_last', 'local_nit_commerce'); ?></th>
                    <th class="col-tight"><?php echo $STR['pkg_col_actions']; ?></th>
                </tr>
            </thead>
            <tbody><tr><td colspan="9"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
        </table>
    </div>

    <h3 class="rep-h"><?php echo get_string('rep_allorders', 'local_nit_commerce'); ?></h3>
    <div class="acad-table-wrap">
        <table class="table table-sm table-striped" id="rep-table">
            <thead>
                <tr>
                    <th class="col-tight"><?php echo get_string('rep_col_date', 'local_nit_commerce'); ?></th>
                    <th><?php echo get_string('rep_col_offer', 'local_nit_commerce'); ?></th>
                    <th><?php echo get_string('rep_col_learner', 'local_nit_commerce'); ?></th>
                    <th><?php echo get_string('rep_col_item', 'local_nit_commerce'); ?></th>
                    <th><?php echo get_string('rep_col_order', 'local_nit_commerce'); ?></th>
                    <th><?php echo get_string('rep_col_original', 'local_nit_commerce'); ?></th>
                    <th><?php echo get_string('rep_col_discount', 'local_nit_commerce'); ?></th>
                    <th><?php echo get_string('rep_col_paid', 'local_nit_commerce'); ?></th>
                </tr>
            </thead>
            <tbody><tr><td colspan="8"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
        </table>
    </div>
    <div id="rep-pager" class="acad-pager"></div>
</div>

<style>
    .academy-modal-backdrop { position: fixed; inset: 0; background: rgba(28,29,36,.55); display: flex;
        align-items: center; justify-content: center; z-index: 1055; padding: 1rem; }
    .academy-modal { background: #fff; border-radius: 12px; padding: 1.75rem 1.5rem 1.4rem; max-width: 420px;
        width: 100%; box-shadow: 0 24px 60px rgba(0,0,0,.28); text-align: center; }
    .academy-modal-icon { width: 54px; height: 54px; margin: 0 auto .9rem; border-radius: 50%;
        background: #fdecef; display: flex; align-items: center; justify-content: center; }
    .academy-modal-icon svg { width: 28px; height: 28px; fill: #e8153b; }
    .academy-modal-title { font-weight: 700; font-size: 1.15rem; margin: 0 0 .4rem; color: #1c1d1f; }
    .academy-modal-text { color: #5a5f66; font-size: .95rem; margin: 0 0 .5rem; line-height: 1.5; }
    .academy-modal-actions { display: flex; justify-content: center; gap: .6rem; margin-top: 1.35rem; }
    .academy-modal-actions .btn { min-width: 108px; }
</style>
<?php

echo html_writer::script(<<<'JS'
(function () {
    var CFG = window.ACADEMY_CFG;
    var STR = window.ACADEMY_STR || {};
    function str(k){return (k in STR)?STR[k]:k;}
    function strf(k,a){var s=str(k);return s.replace(/\{\$a\}/g,a);}
    function $(id){return document.getElementById(id);}

    var PAGE_SIZE = 10, pager = null, TARGETS = null, OFFERS = [];

    function msg(text, type){
        var el = $('ofr-message');
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

    // ── Multilang helpers (same one-field {mlang} approach as manage_subscriptions.php) ──
    function parseMultilang(value){
        var out = { en:'', ar:'' }, raw = String(value == null ? '' : value), m, found = false;
        var re2 = /\{\s*mlang\s+([a-zA-Z0-9_-]+)\s*\}([\s\S]*?)\{\s*mlang\s*\}/g;
        while ((m = re2.exec(raw)) !== null){
            found = true;
            var c = m[1].toLowerCase();
            if (c.indexOf('ar') === 0){ out.ar = m[2].trim(); } else if (c.indexOf('en') === 0){ out.en = m[2].trim(); }
        }
        if (found){ return out; }
        var re1 = /<span[^>]*\blang\s*=\s*"([a-zA-Z0-9_-]+)"[^>]*>([\s\S]*?)<\/span>/g;
        while ((m = re1.exec(raw)) !== null){
            found = true;
            var c1 = m[1].toLowerCase();
            if (c1.indexOf('ar') === 0){ out.ar = m[2].trim(); } else if (c1.indexOf('en') === 0){ out.en = m[2].trim(); }
        }
        if (!found){ out.en = raw; }
        return out;
    }
    function buildMultilang(en, ar){
        en = String(en == null ? '' : en).trim();
        ar = String(ar == null ? '' : ar).trim();
        if (en && ar){ return '{mlang en}' + en + '{mlang}{mlang ar}' + ar + '{mlang}'; }
        // Tag an Arabic-only value so re-editing puts it back in the Arabic box, not the English one.
        if (ar) { return '{mlang ar}' + ar + '{mlang}'; }
        return en;
    }
    function displayName(value){
        var v = parseMultilang(value);
        return [v.en, v.ar].filter(function(x){ return x; }).join(' / ') || value || '';
    }

    function toInput(ts){
        if (!ts){ return ''; }
        var d = new Date(ts * 1000), p = function(n){ return (n<10?'0':'')+n; };
        return d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate())+'T'+p(d.getHours())+':'+p(d.getMinutes());
    }
    function fromInput(v){ return v ? Math.floor(new Date(v).getTime()/1000) : 0; }
    function fmtDate(ts){ return ts ? new Date(ts*1000).toLocaleDateString() : str('ui_never'); }
    function dtype(t){ return t === 'fixed' ? str('cpn_type_fixed') : str('cpn_type_percent'); }
    function valueLabel(o){ return o.discount_type === 'percent' ? (o.discount_value + '%') : o.discount_value; }
    function scopeLabel(o){
        return AcademyUI.tagList((o.applies_to || []).map(function(a){ return a.label; }),
            { more: str('ui_showmore'), less: str('ui_showless') });
    }
    // Name over its description, both resolved from the stored {mlang} markup.
    function titleCell(name, description){
        var title = displayName(name);
        var sub = description ? displayName(description) : '';
        return '<div class="acad-cell-title">' + esc(title) + '</div>' +
            (sub ? '<div class="acad-cell-sub">' + esc(sub) + '</div>' : '');
    }
    // AC-4.13.7 on the offers table itself: uses, and how many of them are still only reserved by
    // an unpaid checkout. The held figure is shown separately rather than added in, because a
    // reservation is not a sale and the two get reconciled against the gateway differently.
    function usageCell(o){
        var paid = Number(o.usage_paid != null ? o.usage_paid : o.usage_count || 0);
        var held = Number(o.usage_held || 0);
        var out = '<span class="rep-amount">' + esc(String(paid)) + '</span>';
        if (held > 0){
            out += ' <span class="rep-badge rep-badge--held">+' + esc(String(held)) + ' ' +
                esc(str('rep_held')) + '</span>';
        }
        return out;
    }

    function renderRows(items){
        var tbody = $('ofr-table').querySelector('tbody');
        tbody.innerHTML = '';
        items.forEach(function(o){
            var tr = document.createElement('tr');
            var toggle = o.status === 'active'
                ? '<button class="btn btn-sm btn-warning" data-act="deactivate" data-id="'+o.id+'">'+esc(str('ui_deactivate'))+'</button>'
                : '<button class="btn btn-sm btn-success" data-act="activate" data-id="'+o.id+'">'+esc(str('ui_activate'))+'</button>';
            tr.innerHTML =
                '<td>'+titleCell(o.name_raw || o.name, o.description_raw || o.description)+'</td>'+
                '<td>'+esc(dtype(o.discount_type))+'</td>'+
                '<td>'+esc(valueLabel(o))+'</td>'+
                '<td class="col-tags">'+scopeLabel(o)+'</td>'+
                '<td>'+usageCell(o)+'</td>'+
                '<td>'+esc(fmtDate(o.startdate))+' → '+esc(fmtDate(o.enddate))+'</td>'+
                '<td>'+esc(o.status === 'active' ? str('ui_active') : str('sub_inactive'))+'</td>'+
                '<td class="col-tight"><div class="acad-actions">'+
                    '<button class="btn btn-sm btn-secondary" data-act="edit" data-id="'+o.id+'">'+esc(str('ui_edit'))+'</button> '+
                    toggle+' '+
                    '<button class="btn btn-sm btn-danger" data-act="delete" data-id="'+o.id+'">'+esc(str('ui_delete'))+'</button>'+
                '</div></td>';
            tr._o = o;
            tbody.appendChild(tr);
        });
    }

    function load(){
        var tbody = $('ofr-table').querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="8">'+esc(str('ui_loading'))+'</td></tr>';
        return api('get_offers').then(function(rows){
            OFFERS = rows || [];
            fillOfferFilter();
            if (!OFFERS.length){
                tbody.innerHTML = '<tr><td colspan="8">'+esc(str('ofr_none'))+'</td></tr>';
                $('ofr-table-pager').innerHTML = '';
                return;
            }
            if (pager){ pager.setRows(OFFERS); }
            else {
                pager = AcademyUI.paginate({ rows:OFFERS, pageSize:PAGE_SIZE, pagerEl:$('ofr-table-pager'),
                    labels:{ info:str('ui_pager_info') }, render:renderRows });
            }
        }).catch(function(e){ msg(e.message, 'danger'); });
    }

    // ── Scope editor (identical to manage_coupons.php) ──
    function targetsFor(type){
        if (!TARGETS){ return []; }
        if (type === 'package'){ return TARGETS.packages || []; }
        if (type === 'subscription'){ return TARGETS.subscriptions || []; }
        if (type === 'program'){ return TARGETS.programs || []; }
        var courses = [];
        (TARGETS.categories || []).forEach(function(cat){
            (cat.courses || []).forEach(function(co){ courses.push({ id:co.id, name:co.fullname }); });
        });
        return courses;
    }
    function fillList(block){
        var type = block.getAttribute('data-type');
        var sel = block.querySelector('.scope-list');
        sel.innerHTML = '';
        targetsFor(type).forEach(function(it){
            var opt = document.createElement('option');
            opt.value = it.id; opt.textContent = it.name;
            sel.appendChild(opt);
        });
    }
    function wireScopeBlock(block){
        var on = block.querySelector('.scope-on');
        var detail = block.querySelector('.scope-detail');
        var mode = block.querySelector('.scope-mode');
        var list = block.querySelector('.scope-list');
        on.addEventListener('change', function(){ detail.style.display = on.checked ? 'block' : 'none'; });
        mode.addEventListener('change', function(){ list.style.display = mode.value === 'specific' ? 'block' : 'none'; });
    }
    function scopeBlocks(){ return Array.prototype.slice.call(document.querySelectorAll('#o-scope .scope-block')); }
    function resetScope(){
        scopeBlocks().forEach(function(block){
            block.querySelector('.scope-on').checked = false;
            block.querySelector('.scope-detail').style.display = 'none';
            block.querySelector('.scope-mode').value = 'all';
            var list = block.querySelector('.scope-list');
            list.style.display = 'none';
            fillList(block);
            Array.prototype.forEach.call(list.options, function(o){ o.selected = false; });
        });
    }
    function applyScope(appliesTo){
        resetScope();
        var byType = {};
        (appliesTo || []).forEach(function(a){ (byType[a.item_type] = byType[a.item_type] || []).push(a.item_id); });
        scopeBlocks().forEach(function(block){
            var type = block.getAttribute('data-type');
            var ids = byType[type];
            if (!ids){ return; }
            block.querySelector('.scope-on').checked = true;
            block.querySelector('.scope-detail').style.display = 'block';
            if (ids.indexOf(0) !== -1){
                block.querySelector('.scope-mode').value = 'all';
            } else {
                block.querySelector('.scope-mode').value = 'specific';
                var list = block.querySelector('.scope-list');
                list.style.display = 'block';
                Array.prototype.forEach.call(list.options, function(o){
                    if (ids.indexOf(parseInt(o.value, 10)) !== -1){ o.selected = true; }
                });
            }
        });
    }
    function collectItems(){
        var items = [];
        scopeBlocks().forEach(function(block){
            if (!block.querySelector('.scope-on').checked){ return; }
            var type = block.getAttribute('data-type');
            if (block.querySelector('.scope-mode').value === 'all'){
                items.push({ item_type:type, item_id:0 });
            } else {
                Array.prototype.forEach.call(block.querySelector('.scope-list').selectedOptions, function(o){
                    items.push({ item_type:type, item_id:parseInt(o.value, 10) });
                });
            }
        });
        return items;
    }

    // ── Reusable confirmation modal ──
    var confirmCb = null;
    function openConfirm(title, text, onOk){
        confirmCb = onOk;
        $('ofr-confirm-title').textContent = title;
        $('ofr-confirm-text').textContent = text;
        $('ofr-confirm-backdrop').style.display = 'flex';
    }
    function closeConfirm(){ confirmCb = null; $('ofr-confirm-backdrop').style.display = 'none'; }
    $('ofr-confirm-cancel').addEventListener('click', closeConfirm);
    $('ofr-confirm-backdrop').addEventListener('click', function(ev){ if (ev.target === this){ closeConfirm(); } });
    document.addEventListener('keydown', function(ev){
        if (ev.key === 'Escape' && $('ofr-confirm-backdrop').style.display !== 'none'){ closeConfirm(); }
    });
    $('ofr-confirm-ok').addEventListener('click', function(){
        var cb = confirmCb;
        closeConfirm();
        if (cb){ cb(); }
    });

    function showForm(o){
        var nm = parseMultilang(o ? (o.name_raw || o.name) : '');
        var ds = parseMultilang(o ? (o.description_raw || o.description || '') : '');
        $('ofr-form-title').textContent = o ? strf('ofr_edit_titled', displayName(o.name_raw || o.name)) : str('ofr_new');
        $('o-id').value    = o ? o.id : '';
        $('o-name-en').value = nm.en;
        $('o-name-ar').value = nm.ar;
        $('o-desc-en').value = ds.en;
        $('o-desc-ar').value = ds.ar;
        $('o-dtype').value = o ? o.discount_type : 'percent';
        $('o-value').value = o ? o.discount_value : '';
        $('o-start').value = toInput(o ? o.startdate : 0);
        $('o-end').value   = toInput(o ? o.enddate : 0);
        $('o-active').checked = o ? (o.status === 'active') : true;
        applyScope(o ? o.applies_to : []);
        $('ofr-form-card').style.display = 'block';
    }
    function hideForm(){ $('ofr-form-card').style.display = 'none'; }

    function save(){
        var items = collectItems();
        if (!items.length){ msg(str('cpn_scope_required'), 'danger'); return; }
        var id = $('o-id').value;
        var params = {
            name: buildMultilang($('o-name-en').value, $('o-name-ar').value),
            description: buildMultilang($('o-desc-en').value, $('o-desc-ar').value),
            discount_type: $('o-dtype').value,
            discount_value: $('o-value').value || 0,
            startdate: fromInput($('o-start').value),
            enddate: fromInput($('o-end').value),
            items: JSON.stringify(items)
        };
        var p;
        if (id){
            params.id = id;
            params.status = $('o-active').checked ? 'active' : 'inactive';
            p = api('update_offer', params, 'POST');
        } else {
            params.active = $('o-active').checked ? 1 : 0;
            p = api('create_offer', params, 'POST');
        }
        p.then(function(){
            msg(id ? str('ofr_updated') : str('ofr_created'), 'success');
            hideForm(); load();
            // An offer whose dates or value just changed prices differently, so any report on
            // screen is now stale.
            repLoaded = false;
        }).catch(function(e){ msg(e.message, 'danger'); });
    }

    $('ofr-table').addEventListener('click', function(ev){
        var btn = ev.target.closest('button[data-act]');
        if (!btn){ return; }
        var id = btn.getAttribute('data-id'), act = btn.getAttribute('data-act');
        if (act === 'edit'){ showForm(btn.closest('tr')._o); return; }
        if (act === 'activate'){
            api('activate_offer', { id:id }, 'POST').then(function(){ msg(str('ofr_activated'),'success'); load(); }).catch(function(e){ msg(e.message,'danger'); });
        } else if (act === 'deactivate'){
            api('deactivate_offer', { id:id }, 'POST').then(function(){ msg(str('ofr_deactivated'),'success'); load(); }).catch(function(e){ msg(e.message,'danger'); });
        } else if (act === 'delete'){
            openConfirm(str('ofr_delete_title'), str('ofr_confirm_delete'), function(){
                api('delete_offer', { id:id }, 'POST').then(function(){ msg(str('ofr_deleted'),'success'); load(); }).catch(function(e){ msg(e.message,'danger'); });
            });
        }
    });

    $('ofr-new').addEventListener('click', function(){ showForm(null); });
    $('ofr-refresh').addEventListener('click', load);
    $('ofr-save').addEventListener('click', save);
    $('ofr-cancel').addEventListener('click', hideForm);

    scopeBlocks().forEach(wireScopeBlock);

    // ── Report tab (AC-4.13.7) ──
    var REP_PAGE_SIZE = 25, repPage = 0, repLoaded = false;

    function repMsg(text, type){
        var el = $('rep-message');
        el.textContent = text;
        el.className = 'alert alert-' + (type || 'info');
        el.style.display = 'block';
    }
    function money(n){
        return Number(n || 0).toLocaleString(undefined, { minimumFractionDigits:2, maximumFractionDigits:2 });
    }
    function cur(){ return CFG.currency || ''; }

    // The offer filter is filled from the list the manage tab already fetched, so creating an
    // offer and switching tabs finds it there without a second round trip.
    function fillOfferFilter(){
        var sel = $('r-offer');
        if (!sel){ return; }
        var keep = sel.value;
        sel.innerHTML = '<option value="0">' + esc(str('rep_filter_alloffers')) + '</option>';
        OFFERS.forEach(function(o){
            var opt = document.createElement('option');
            opt.value = o.id;
            opt.textContent = displayName(o.name_raw || o.name);
            sel.appendChild(opt);
        });
        if (keep){ sel.value = keep; }
    }

    function repFilters(){
        return {
            offerid:   $('r-offer').value || 0,
            item_type: $('r-item').value || '',
            from:      $('r-from').value || '',
            to:        $('r-to').value || '',
            state:     $('r-state').value || 'confirmed',
            q:         $('r-q').value || ''
        };
    }

    function kpi(label, value, sub){
        return '<div class="rep-kpi"><div class="rep-kpi__label">'+esc(label)+'</div>'+
            '<div class="rep-kpi__value">'+value+(sub?' <small>'+esc(sub)+'</small>':'')+'</div></div>';
    }

    function renderKpis(t){
        $('rep-kpis').innerHTML =
            kpi(str('rep_col_usages'), esc(String(t.usages || 0))) +
            kpi(str('rep_col_learners'), esc(String(t.learners || 0))) +
            // The number the business actually asks for: what the offers gave away.
            kpi(str('rep_col_discount'), '<span class="rep-cut">-'+esc(money(t.discounted))+'</span>', cur()) +
            kpi(str('rep_col_paid'), esc(money(t.net)), cur());
    }

    function renderSummary(rows){
        var tbody = $('rep-summary').querySelector('tbody');
        if (!rows.length){
            tbody.innerHTML = '<tr><td colspan="9">'+esc(str('ofr_rep_none'))+'</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(function(r){
            var value = r.discount_type === 'percent' ? (r.discount_value + '%') : money(r.discount_value);
            var status = r.status === 'active' ? str('ui_active') : (r.status ? str('sub_inactive') : '');
            // An offer nobody has bought under is dimmed, not hidden: "this campaign sold nothing"
            // is the answer most worth seeing, and a missing row does not say it.
            return '<tr'+(r.usages ? '' : ' class="rep-row--unused"')+'>'+
                '<td>'+esc(r.offer_name || '')+'</td>'+
                '<td>'+esc(value)+'</td>'+
                '<td>'+esc(status)+'</td>'+
                '<td>'+esc(String(r.usages))+'</td>'+
                '<td>'+esc(String(r.learners))+'</td>'+
                '<td class="rep-amount rep-cut">-'+esc(money(r.discounted))+' '+esc(cur())+'</td>'+
                '<td class="rep-amount">'+esc(money(r.net))+' '+esc(cur())+'</td>'+
                '<td>'+esc(r.last_date || '')+'</td>'+
                '<td class="col-tight">'+(r.usages
                    ? '<button class="btn btn-sm btn-secondary" data-rep-offer="'+r.offerid+'">'+
                        esc(str('ofr_rep_open'))+'</button>'
                    : '')+'</td>'+
            '</tr>';
        }).join('');
    }

    // "View orders" on a summary row narrows the detail table to that offer — the same thing the
    // filter above does, reached from the row that prompted the question.
    $('rep-summary').addEventListener('click', function(ev){
        var btn = ev.target.closest('button[data-rep-offer]');
        if (!btn){ return; }
        $('r-offer').value = btn.getAttribute('data-rep-offer');
        repPage = 0;
        loadReport();
    });

    function renderDetail(rows){
        var tbody = $('rep-table').querySelector('tbody');
        if (!rows.length){
            tbody.innerHTML = '<tr><td colspan="8">'+esc(str('ofr_rep_none'))+'</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(function(r){
            // A row whose order is still unpaid is a reservation, not a use. It is shown so the
            // figures can be reconciled against the gateway, but never silently counted as a sale,
            // which is why it is badged rather than just listed.
            var order = r.order_id
                ? '<code>'+esc(r.order_id)+'</code>'
                : '<span class="rep-badge rep-badge--none">'+esc(str('rep_noorder'))+'</span>';
            if (r.order_id && !r.confirmed){
                order += ' <span class="rep-badge rep-badge--held">'+esc(str('rep_held'))+
                    (r.order_status ? ': '+esc(r.order_status) : '')+'</span>';
            }
            var who = r.learner ? esc(r.learner) : '—';
            if (r.email){ who += '<div class="acad-cell-sub">'+esc(r.email)+'</div>'; }
            return '<tr>'+
                '<td class="col-tight">'+esc(r.date)+'</td>'+
                '<td>'+esc(r.offer_name || '')+'</td>'+
                '<td>'+who+'</td>'+
                '<td>'+esc(r.item_label || '')+'</td>'+
                '<td>'+order+'</td>'+
                '<td class="rep-amount">'+esc(money(r.original_amount))+'</td>'+
                '<td class="rep-amount rep-cut">-'+esc(money(r.discount_amount))+'</td>'+
                '<td class="rep-amount">'+esc(money(r.final_amount))+'</td>'+
            '</tr>';
        }).join('');
    }

    function renderRepPager(total){
        var wrap = $('rep-pager'), pages = Math.ceil(total / REP_PAGE_SIZE);
        wrap.innerHTML = '';
        if (pages <= 1){ return; }
        var info = document.createElement('span');
        info.className = 'acad-pager__info';
        // ui_pager_info is a {from}/{to}/{total} template, not a {$a} one — same shape AcademyUI
        // fills for the offer table, so both bars read identically.
        info.textContent = str('ui_pager_info')
            .replace('{from}', String(repPage * REP_PAGE_SIZE + 1))
            .replace('{to}', String(Math.min((repPage + 1) * REP_PAGE_SIZE, total)))
            .replace('{total}', String(total));
        wrap.appendChild(info);
        var make = function(label, target, disabled, active){
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = label;
            b.disabled = !!disabled;
            if (active){ b.className = 'is-active'; }
            b.addEventListener('click', function(){ repPage = target; loadReport(); });
            wrap.appendChild(b);
        };
        make('‹', Math.max(0, repPage - 1), repPage === 0, false);
        // A window around the current page: 40 numbered buttons is not navigation.
        var first = Math.max(0, repPage - 2), last = Math.min(pages - 1, first + 4);
        first = Math.max(0, last - 4);
        for (var i = first; i <= last; i++){ make(String(i + 1), i, false, i === repPage); }
        make('›', Math.min(pages - 1, repPage + 1), repPage >= pages - 1, false);
    }

    function loadReport(){
        $('rep-message').style.display = 'none';
        $('rep-table').querySelector('tbody').innerHTML =
            '<tr><td colspan="8">'+esc(str('ui_loading'))+'</td></tr>';
        var params = repFilters();
        params.page = repPage;
        params.perpage = REP_PAGE_SIZE;
        api('get_offer_usages', params).then(function(d){
            renderKpis(d.totals || {});
            renderSummary(d.summary || []);
            renderDetail(d.rows || []);
            renderRepPager(d.total || 0);
            // The "held rows are not sales" note only makes sense when held rows can be on screen.
            $('rep-heldnote').style.display = (params.state === 'confirmed') ? 'none' : '';
            repLoaded = true;
        }).catch(function(e){ repMsg(e.message, 'danger'); });
    }

    $('rep-apply').addEventListener('click', function(){ repPage = 0; loadReport(); });
    $('rep-reset').addEventListener('click', function(){
        $('r-offer').value = '0'; $('r-item').value = ''; $('r-from').value = '';
        $('r-to').value = ''; $('r-state').value = 'confirmed'; $('r-q').value = '';
        repPage = 0; loadReport();
    });
    $('r-q').addEventListener('keydown', function(ev){
        if (ev.key === 'Enter'){ repPage = 0; loadReport(); }
    });
    $('rep-export').addEventListener('click', function(){
        // Same filters, no paging — the file is what is on screen, not page one of it.
        var q = new URLSearchParams(repFilters());
        q.append('sesskey', CFG.sesskey);
        if (CFG.lang){ q.append('alang', CFG.lang); }
        window.location = CFG.export + '?' + q.toString();
    });

    // ── Tabs ──
    // The hash carries the active tab so a refresh, a bookmark or the back button all land where
    // the admin left off — an admin who filtered a report does not want the offer list back.
    function showTab(name){
        name = (name === 'reports') ? 'reports' : 'manage';
        Array.prototype.forEach.call(document.querySelectorAll('[data-tabpane]'), function(pane){
            pane.style.display = (pane.getAttribute('data-tabpane') === name) ? '' : 'none';
        });
        Array.prototype.forEach.call(document.querySelectorAll('#ofr-tabs .nav-link'), function(a){
            a.classList.toggle('active', a.getAttribute('data-tab') === name);
        });
        if (name === 'reports' && !repLoaded){ loadReport(); }
    }
    document.getElementById('ofr-tabs').addEventListener('click', function(ev){
        var a = ev.target.closest('a[data-tab]');
        if (!a){ return; }
        ev.preventDefault();
        var name = a.getAttribute('data-tab');
        if (history.replaceState){ history.replaceState(null, '', '#' + name); }
        else { location.hash = name; }
        showTab(name);
    });
    window.addEventListener('hashchange', function(){ showTab(location.hash.replace('#', '')); });

    // Load the selectable courses/packages/subscriptions, then the offer list.
    api('get_discount_targets').then(function(t){
        TARGETS = t;
        scopeBlocks().forEach(fillList);
    }).catch(function(e){ msg(e.message, 'danger'); }).then(load).then(function(){
        showTab(location.hash.replace('#', ''));
    });
})();
JS
);

echo $OUTPUT->footer();
