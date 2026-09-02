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
 * Admin UI to manage discount coupons. Drives /local/nit_commerce/api.php from vanilla JS.
 * UI labels match the reference local_academy plugin.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/nit_commerce/lib.php');

admin_externalpage_setup('local_nit_commerce_managecoupons');
require_capability('local/nit_commerce:managecoupons', context_system::instance());

global $OUTPUT, $CFG, $PAGE;

$PAGE->set_title(get_string('managecoupons', 'local_nit_commerce'));
$PAGE->set_heading(get_string('managecoupons', 'local_nit_commerce'));
$PAGE->requires->js(new moodle_url('/local/nit_commerce/ui.js',
    ['v' => get_config('local_nit_commerce', 'version')]), true);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecoupons', 'local_nit_commerce'));

$STR = local_nit_commerce_string_map(array(
    'ui_refresh', 'ui_loading', 'ui_save', 'ui_cancel', 'ui_active', 'ui_activate', 'ui_deactivate',
    'ui_edit', 'ui_delete', 'ui_never', 'ui_optional', 'ui_pager_info',
    'pkg_col_status', 'pkg_col_actions', 'sub_inactive',
    'ofr_col_name', 'pkg_field_name_en', 'pkg_field_name_ar',
    'pkg_field_desc_en', 'pkg_field_desc_ar', 'ui_showmore', 'ui_showless',
    'cpn_new', 'cpn_none', 'cpn_col_code', 'cpn_col_type', 'cpn_col_value', 'cpn_col_scope',
    'cpn_col_usage', 'cpn_col_dates', 'cpn_field_code', 'cpn_field_dtype', 'cpn_field_value',
    'cpn_field_max', 'cpn_field_utype', 'cpn_field_limit', 'cpn_field_start', 'cpn_field_end',
    'cpn_field_scope', 'cpn_type_percent', 'cpn_type_fixed', 'cpn_usage_once', 'cpn_usage_multiple',
    'cpn_scope_courses', 'cpn_scope_subscriptions', 'cpn_scope_all',
    'cpn_scope_specific', 'cpn_created', 'cpn_updated', 'cpn_activated', 'cpn_deactivated',
    'cpn_deleted', 'cpn_confirm_delete', 'cpn_edit_titled', 'cpn_scope_required', 'cpn_unlimited',
    'cpn_used_count',
    'rep_none', 'rep_col_date', 'rep_col_code', 'rep_col_learner', 'rep_col_order',
    'rep_col_orderstatus', 'rep_col_item', 'rep_col_original', 'rep_col_discount', 'rep_col_paid',
    'rep_col_redemptions', 'rep_col_learners', 'rep_col_last', 'rep_total', 'rep_held',
    'rep_noorder', 'rep_filter_all',
    'err_sessionexpired', 'err_requestfailed',
));
echo html_writer::script('window.ACADEMY_CFG = ' . json_encode(array(
    'endpoint' => (new moodle_url('/local/nit_commerce/api.php'))->out(false),
    'export'   => (new moodle_url('/local/nit_commerce/export_redemptions.php'))->out(false),
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
       admin layout, and theme_nit's palette is not guaranteed to be loaded here. */
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
</style>

<ul class="nav nav-tabs mb-3" id="cpn-tabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" href="#manage" data-tab="manage" role="tab"><?php
        echo get_string('tab_manage', 'local_nit_commerce'); ?></a></li>
    <li class="nav-item"><a class="nav-link" href="#reports" data-tab="reports" role="tab"><?php
        echo get_string('tab_reports', 'local_nit_commerce'); ?></a></li>
</ul>

<div id="academy-coupon-app" data-tabpane="manage">
    <div id="cpn-message" class="alert" style="display:none"></div>

    <div class="mb-3">
        <button id="cpn-new" class="btn btn-primary"><?php echo $STR['cpn_new']; ?></button>
        <button id="cpn-refresh" class="btn btn-secondary"><?php echo $STR['ui_refresh']; ?></button>
    </div>

    <div class="acad-table-wrap">
    <table class="table table-striped" id="cpn-table">
        <thead>
            <tr>
                <th><?php echo $STR['cpn_col_code']; ?></th>
                <th><?php echo $STR['ofr_col_name']; ?></th>
                <th><?php echo $STR['cpn_col_type']; ?></th>
                <th><?php echo $STR['cpn_col_value']; ?></th>
                <th class="col-tags"><?php echo $STR['cpn_col_scope']; ?></th>
                <th><?php echo $STR['cpn_col_usage']; ?></th>
                <th class="col-tight"><?php echo $STR['cpn_col_dates']; ?></th>
                <th><?php echo $STR['pkg_col_status']; ?></th>
                <th class="col-tight"><?php echo $STR['pkg_col_actions']; ?></th>
            </tr>
        </thead>
        <tbody><tr><td colspan="9"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
    </table>
    </div>
    <div id="cpn-table-pager" class="acad-pager"></div>

    <div id="cpn-form-card" class="card" style="display:none; max-width:640px;">
        <div class="card-body">
            <h4 id="cpn-form-title" class="card-title"><?php echo $STR['cpn_new']; ?></h4>
            <input type="hidden" id="c-id">
            <div class="form-group">
                <label for="c-name-en"><?php echo $STR['pkg_field_name_en']; ?></label>
                <input type="text" class="form-control" id="c-name-en" dir="ltr">
            </div>
            <div class="form-group">
                <label for="c-name-ar"><?php echo $STR['pkg_field_name_ar']; ?></label>
                <input type="text" class="form-control" id="c-name-ar" dir="rtl">
            </div>
            <div class="form-group">
                <label for="c-desc-en"><?php echo $STR['pkg_field_desc_en']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span></label>
                <textarea class="form-control" id="c-desc-en" rows="2" dir="ltr"></textarea>
            </div>
            <div class="form-group">
                <label for="c-desc-ar"><?php echo $STR['pkg_field_desc_ar']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span></label>
                <textarea class="form-control" id="c-desc-ar" rows="2" dir="rtl"></textarea>
            </div>
            <div class="form-group">
                <label for="c-code"><?php echo $STR['cpn_field_code']; ?></label>
                <input type="text" class="form-control" id="c-code" dir="ltr">
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="c-dtype"><?php echo $STR['cpn_field_dtype']; ?></label>
                    <select class="form-control" id="c-dtype">
                        <option value="percent"><?php echo $STR['cpn_type_percent']; ?></option>
                        <option value="fixed"><?php echo $STR['cpn_type_fixed']; ?></option>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="c-value"><?php echo $STR['cpn_field_value']; ?></label>
                    <input type="number" class="form-control" id="c-value" min="0" step="0.01">
                </div>
            </div>
            <div class="form-group">
                <label for="c-max"><?php echo $STR['cpn_field_max']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span></label>
                <input type="number" class="form-control" id="c-max" min="0" step="0.01">
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="c-utype"><?php echo $STR['cpn_field_utype']; ?></label>
                    <select class="form-control" id="c-utype">
                        <option value="multiple"><?php echo $STR['cpn_usage_multiple']; ?></option>
                        <option value="once"><?php echo $STR['cpn_usage_once']; ?></option>
                    </select>
                </div>
                <div class="form-group col-md-6" id="c-limit-wrap">
                    <label for="c-limit"><?php echo $STR['cpn_field_limit']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span></label>
                    <input type="number" class="form-control" id="c-limit" min="0" step="1">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="c-start"><?php echo $STR['cpn_field_start']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span></label>
                    <input type="datetime-local" class="form-control" id="c-start">
                </div>
                <div class="form-group col-md-6">
                    <label for="c-end"><?php echo $STR['cpn_field_end']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span></label>
                    <input type="datetime-local" class="form-control" id="c-end">
                </div>
            </div>

            <label class="d-block"><strong><?php echo $STR['cpn_field_scope']; ?></strong></label>
            <div id="c-scope" class="mb-3 p-2" style="border:1px solid #dee2e6; border-radius:6px;">
                <?php
                $scopetypes = array(
                    'course'       => $STR['cpn_scope_courses'],
                    'subscription' => $STR['cpn_scope_subscriptions'],
                );
                foreach ($scopetypes as $t => $label) {
                    echo '<div class="scope-block mb-2" data-type="' . $t . '">';
                    echo '<div class="form-check">';
                    echo '<input type="checkbox" class="form-check-input scope-on" id="scope-' . $t . '-on">';
                    echo '<label class="form-check-label" for="scope-' . $t . '-on"><strong>' . $label . '</strong></label>';
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
                <input type="checkbox" class="form-check-input" id="c-active" checked>
                <label class="form-check-label" for="c-active"><?php echo $STR['ui_active']; ?></label>
            </div>
            <button id="cpn-save" class="btn btn-primary"><?php echo $STR['ui_save']; ?></button>
            <button id="cpn-cancel" class="btn btn-link"><?php echo $STR['ui_cancel']; ?></button>
        </div>
    </div>
</div>

<!-- ── Tab 2: the redemption report (AC-4.12.8) ───────────────────────────────────────────────
     Every redemption row already carries the learner, the order, the date and the amount; this
     tab is what makes them reportable without a database client. -->
<div id="academy-coupon-report" data-tabpane="reports" style="display:none">
    <div id="rep-message" class="alert" style="display:none"></div>
    <p class="rep-sub"><?php echo get_string('rep_intro', 'local_nit_commerce'); ?></p>

    <div class="rep-filters">
        <div class="rep-field">
            <label for="r-coupon"><?php echo get_string('rep_filter_coupon', 'local_nit_commerce'); ?></label>
            <select class="form-control" id="r-coupon">
                <option value="0"><?php echo get_string('rep_filter_all', 'local_nit_commerce'); ?></option>
            </select>
        </div>
        <div class="rep-field">
            <label for="r-item"><?php echo get_string('rep_filter_item', 'local_nit_commerce'); ?></label>
            <select class="form-control" id="r-item">
                <option value=""><?php echo get_string('rep_filter_anyitem', 'local_nit_commerce'); ?></option>
                <option value="course"><?php echo $STR['cpn_scope_courses']; ?></option>
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
        echo get_string('rep_heldnote', 'local_nit_commerce'); ?></p>

    <h3 class="rep-h"><?php echo get_string('rep_bycoupon', 'local_nit_commerce'); ?></h3>
    <div class="acad-table-wrap">
        <table class="table table-sm table-striped" id="rep-summary">
            <thead>
                <tr>
                    <th><?php echo $STR['cpn_col_code']; ?></th>
                    <th><?php echo $STR['ofr_col_name']; ?></th>
                    <th><?php echo get_string('rep_col_redemptions', 'local_nit_commerce'); ?></th>
                    <th><?php echo get_string('rep_col_learners', 'local_nit_commerce'); ?></th>
                    <th><?php echo get_string('rep_col_discount', 'local_nit_commerce'); ?></th>
                    <th><?php echo get_string('rep_col_paid', 'local_nit_commerce'); ?></th>
                    <th><?php echo get_string('rep_col_last', 'local_nit_commerce'); ?></th>
                </tr>
            </thead>
            <tbody><tr><td colspan="7"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
        </table>
    </div>

    <h3 class="rep-h"><?php echo get_string('rep_alldetail', 'local_nit_commerce'); ?></h3>
    <div class="acad-table-wrap">
        <table class="table table-sm table-striped" id="rep-table">
            <thead>
                <tr>
                    <th class="col-tight"><?php echo get_string('rep_col_date', 'local_nit_commerce'); ?></th>
                    <th><?php echo get_string('rep_col_code', 'local_nit_commerce'); ?></th>
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
<?php

echo html_writer::script(<<<'JS'
(function () {
    var CFG = window.ACADEMY_CFG;
    var STR = window.ACADEMY_STR || {};
    function str(k){return (k in STR)?STR[k]:k;}
    function strf(k,a){var s=str(k);return s.replace(/\{\$a\}/g,a);}
    function $(id){return document.getElementById(id);}

    var PAGE_SIZE = 10, pager = null, TARGETS = null, COUPONS = [];

    function msg(text, type){
        var el = $('cpn-message');
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

    // -- Multilang helpers (same one-field {mlang} approach as manage_offers.php) --
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
        // Tag an Arabic-only name so re-editing puts it back in the Arabic box, not the English one.
        if (ar){ return '{mlang ar}' + ar + '{mlang}'; }
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
    function valueLabel(c){ return c.discount_type === 'percent' ? (c.discount_value + '%') : c.discount_value; }
    function scopeLabel(c){
        return AcademyUI.tagList((c.applies_to || []).map(function(a){ return a.label; }),
            { more: str('ui_showmore'), less: str('ui_showless') });
    }
    // Name over its description, both resolved from the stored {mlang} markup.
    function titleCell(name, description){
        var title = displayName(name);
        var sub = description ? displayName(description) : '';
        return '<div class="acad-cell-title">' + esc(title) + '</div>' +
            (sub ? '<div class="acad-cell-sub">' + esc(sub) + '</div>' : '');
    }
    function usageLabel(c){
        if (c.usage_type === 'once'){ return str('cpn_usage_once') + ' (' + c.usage_count + ')'; }
        var cap = c.usage_limit > 0 ? c.usage_limit : str('cpn_unlimited');
        return strf('cpn_used_count', c.usage_count) + ' / ' + cap;
    }

    function renderRows(items){
        var tbody = $('cpn-table').querySelector('tbody');
        tbody.innerHTML = '';
        items.forEach(function(c){
            var tr = document.createElement('tr');
            var toggle = c.status === 'active'
                ? '<button class="btn btn-sm btn-warning" data-act="deactivate" data-id="'+c.id+'">'+esc(str('ui_deactivate'))+'</button>'
                : '<button class="btn btn-sm btn-success" data-act="activate" data-id="'+c.id+'">'+esc(str('ui_activate'))+'</button>';
            tr.innerHTML =
                '<td><code>'+esc(c.code)+'</code></td>'+
                '<td>'+titleCell(c.name_raw || c.name, c.description_raw || c.description)+'</td>'+
                '<td>'+esc(dtype(c.discount_type))+'</td>'+
                '<td>'+esc(valueLabel(c))+(c.max_discount!=null?' <small class="text-muted">(max '+esc(c.max_discount)+')</small>':'')+'</td>'+
                '<td class="col-tags">'+scopeLabel(c)+'</td>'+
                '<td>'+esc(usageLabel(c))+'</td>'+
                '<td>'+esc(fmtDate(c.startdate))+' → '+esc(fmtDate(c.enddate))+'</td>'+
                '<td>'+esc(c.status === 'active' ? str('ui_active') : str('sub_inactive'))+'</td>'+
                '<td class="col-tight"><div class="acad-actions">'+
                    '<button class="btn btn-sm btn-secondary" data-act="edit" data-id="'+c.id+'">'+esc(str('ui_edit'))+'</button> '+
                    toggle+' '+
                    '<button class="btn btn-sm btn-danger" data-act="delete" data-id="'+c.id+'">'+esc(str('ui_delete'))+'</button>'+
                '</div></td>';
            tr._c = c;
            tbody.appendChild(tr);
        });
    }

    function load(){
        var tbody = $('cpn-table').querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="9">'+esc(str('ui_loading'))+'</td></tr>';
        api('get_coupons').then(function(rows){
            COUPONS = rows || [];
            fillCouponFilter();
            if (!rows.length){
                tbody.innerHTML = '<tr><td colspan="9">'+esc(str('cpn_none'))+'</td></tr>';
                $('cpn-table-pager').innerHTML = '';
                return;
            }
            if (pager){ pager.setRows(rows); }
            else {
                pager = AcademyUI.paginate({ rows:rows, pageSize:PAGE_SIZE, pagerEl:$('cpn-table-pager'),
                    labels:{ info:str('ui_pager_info') }, render:renderRows });
            }
        }).catch(function(e){ msg(e.message, 'danger'); });
    }

    // ── Scope editor ──
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
            var o = document.createElement('option');
            o.value = it.id; o.textContent = it.name;
            sel.appendChild(o);
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
    function scopeBlocks(){ return Array.prototype.slice.call(document.querySelectorAll('#c-scope .scope-block')); }

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

    function showForm(c){
        var nm = parseMultilang(c ? (c.name_raw || c.name) : '');
        var ds = parseMultilang(c ? (c.description_raw || c.description || '') : '');
        $('cpn-form-title').textContent = c ? strf('cpn_edit_titled', c.code) : str('cpn_new');
        $('c-id').value    = c ? c.id : '';
        $('c-code').value  = c ? c.code : '';
        $('c-name-en').value = nm.en;
        $('c-name-ar').value = nm.ar;
        $('c-desc-en').value = ds.en;
        $('c-desc-ar').value = ds.ar;
        $('c-dtype').value = c ? c.discount_type : 'percent';
        $('c-value').value = c ? c.discount_value : '';
        $('c-max').value   = (c && c.max_discount != null) ? c.max_discount : '';
        $('c-utype').value = c ? c.usage_type : 'multiple';
        $('c-limit').value = (c && c.usage_limit) ? c.usage_limit : '';
        $('c-start').value = toInput(c ? c.startdate : 0);
        $('c-end').value   = toInput(c ? c.enddate : 0);
        $('c-active').checked = c ? (c.status === 'active') : true;
        applyScope(c ? c.applies_to : []);
        $('cpn-form-card').style.display = 'block';
    }
    function hideForm(){ $('cpn-form-card').style.display = 'none'; }

    function save(){
        var items = collectItems();
        if (!items.length){ msg(str('cpn_scope_required'), 'danger'); return; }
        var id = $('c-id').value;
        var params = {
            code: $('c-code').value,
            name: buildMultilang($('c-name-en').value, $('c-name-ar').value),
            description: buildMultilang($('c-desc-en').value, $('c-desc-ar').value),
            discount_type: $('c-dtype').value,
            discount_value: $('c-value').value || 0,
            max_discount: $('c-max').value === '' ? '' : $('c-max').value,
            usage_type: $('c-utype').value,
            usage_limit: $('c-limit').value || 0,
            startdate: fromInput($('c-start').value),
            enddate: fromInput($('c-end').value),
            items: JSON.stringify(items)
        };
        var p;
        if (id){
            params.id = id;
            params.status = $('c-active').checked ? 'active' : 'inactive';
            p = api('update_coupon', params, 'POST');
        } else {
            params.active = $('c-active').checked ? 1 : 0;
            p = api('create_coupon', params, 'POST');
        }
        p.then(function(){
            msg(id ? str('cpn_updated') : str('cpn_created'), 'success');
            hideForm(); load();
        }).catch(function(e){ msg(e.message, 'danger'); });
    }

    $('cpn-table').addEventListener('click', function(ev){
        var btn = ev.target.closest('button[data-act]');
        if (!btn){ return; }
        var id = btn.getAttribute('data-id'), act = btn.getAttribute('data-act');
        if (act === 'edit'){ showForm(btn.closest('tr')._c); return; }
        if (act === 'activate'){
            api('activate_coupon', { id:id }, 'POST').then(function(){ msg(str('cpn_activated'),'success'); load(); }).catch(function(e){ msg(e.message,'danger'); });
        } else if (act === 'deactivate'){
            api('deactivate_coupon', { id:id }, 'POST').then(function(){ msg(str('cpn_deactivated'),'success'); load(); }).catch(function(e){ msg(e.message,'danger'); });
        } else if (act === 'delete'){
            if (!confirm(str('cpn_confirm_delete'))){ return; }
            api('delete_coupon', { id:id }, 'POST').then(function(){ msg(str('cpn_deleted'),'success'); load(); }).catch(function(e){ msg(e.message,'danger'); });
        }
    });

    $('cpn-new').addEventListener('click', function(){ showForm(null); });
    $('cpn-refresh').addEventListener('click', load);
    $('cpn-save').addEventListener('click', save);
    $('cpn-cancel').addEventListener('click', hideForm);

    scopeBlocks().forEach(wireScopeBlock);

    // ══ Report tab ════════════════════════════════════════════════════════════════════════════
    // The redemption log, filtered server-side. Paged there too: the log only ever grows, and a
    // report that dumps every row is the one nobody opens twice.
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

    // The coupon filter is filled from the list the manage tab already fetched, so creating a
    // coupon and switching tabs finds it there without a second round trip.
    function fillCouponFilter(){
        var sel = $('r-coupon');
        if (!sel){ return; }
        var keep = sel.value;
        sel.innerHTML = '<option value="0">' + esc(str('rep_filter_all')) + '</option>';
        COUPONS.forEach(function(c){
            var o = document.createElement('option');
            o.value = c.id;
            o.textContent = c.code + (displayName(c.name_raw || c.name) ? ' — ' + displayName(c.name_raw || c.name) : '');
            sel.appendChild(o);
        });
        if (keep){ sel.value = keep; }
    }

    function repFilters(){
        return {
            couponid:  $('r-coupon').value || 0,
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
            kpi(str('rep_col_redemptions'), esc(String(t.redemptions || 0))) +
            kpi(str('rep_col_learners'), esc(String(t.learners || 0))) +
            // The number the business actually asks for: what the coupons gave away.
            kpi(str('rep_col_discount'), '<span class="rep-cut">-'+esc(money(t.discounted))+'</span>', cur()) +
            kpi(str('rep_col_paid'), esc(money(t.net)), cur());
    }

    function renderSummary(rows){
        var tbody = $('rep-summary').querySelector('tbody');
        if (!rows.length){
            tbody.innerHTML = '<tr><td colspan="7">'+esc(str('rep_none'))+'</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(function(r){
            return '<tr>'+
                '<td><code>'+esc(r.code)+'</code></td>'+
                '<td>'+esc(r.coupon_name || '')+'</td>'+
                '<td>'+esc(String(r.redemptions))+'</td>'+
                '<td>'+esc(String(r.learners))+'</td>'+
                '<td class="rep-amount rep-cut">-'+esc(money(r.discounted))+' '+esc(cur())+'</td>'+
                '<td class="rep-amount">'+esc(money(r.net))+' '+esc(cur())+'</td>'+
                '<td>'+esc(r.last_date || '')+'</td>'+
            '</tr>';
        }).join('');
    }

    function renderDetail(rows){
        var tbody = $('rep-table').querySelector('tbody');
        if (!rows.length){
            tbody.innerHTML = '<tr><td colspan="8">'+esc(str('rep_none'))+'</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(function(r){
            // A row whose order is still unpaid is a reservation, not a redemption. It holds a
            // seat against the usage limit, so it has to be visible — but never silently counted
            // as a sale, which is why it is badged rather than just listed.
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
                '<td><code>'+esc(r.code)+'</code></td>'+
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
        // fills for the coupon table, so both bars read identically.
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
        api('get_coupon_redemptions', params).then(function(d){
            renderKpis(d.totals || {});
            renderSummary(d.summary || []);
            renderDetail(d.rows || []);
            renderRepPager(d.total || 0);
            // The "held rows count against the limit" note only makes sense when held rows can
            // actually be on screen.
            $('rep-heldnote').style.display = (params.state === 'confirmed') ? 'none' : '';
            repLoaded = true;
        }).catch(function(e){ repMsg(e.message, 'danger'); });
    }

    $('rep-apply').addEventListener('click', function(){ repPage = 0; loadReport(); });
    $('rep-reset').addEventListener('click', function(){
        $('r-coupon').value = '0'; $('r-item').value = ''; $('r-from').value = '';
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
    // the admin left off — an admin who filtered a report does not want the coupon list back.
    function showTab(name){
        name = (name === 'reports') ? 'reports' : 'manage';
        Array.prototype.forEach.call(document.querySelectorAll('[data-tabpane]'), function(pane){
            pane.style.display = (pane.getAttribute('data-tabpane') === name) ? '' : 'none';
        });
        Array.prototype.forEach.call(document.querySelectorAll('#cpn-tabs .nav-link'), function(a){
            a.classList.toggle('active', a.getAttribute('data-tab') === name);
        });
        if (name === 'reports' && !repLoaded){ loadReport(); }
    }
    document.getElementById('cpn-tabs').addEventListener('click', function(ev){
        var a = ev.target.closest('a[data-tab]');
        if (!a){ return; }
        ev.preventDefault();
        var name = a.getAttribute('data-tab');
        if (history.replaceState){ history.replaceState(null, '', '#' + name); }
        else { location.hash = name; }
        showTab(name);
    });
    window.addEventListener('hashchange', function(){ showTab(location.hash.replace('#', '')); });

    // Load the selectable courses/packages/subscriptions, then the coupon list.
    api('get_discount_targets').then(function(t){
        TARGETS = t;
        scopeBlocks().forEach(fillList);
    }).catch(function(e){ msg(e.message, 'danger'); }).then(load);

    showTab(location.hash.replace('#', ''));
})();
JS
);

echo $OUTPUT->footer();
