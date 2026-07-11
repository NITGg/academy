<?php
// Student "Coupons & Offers" UI. Any logged-in user sees the coupons they can use (US-US-CP-1-1),
// the active automatic offers (US-US-OF-1-1), and their own redemption/application history
// (US-US-CP-1-3, US-US-OF-1-3). Calls api.php from JS with a minted mobile token.

require('../../config.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/academy/lib.php'); // local_academy_string_map()

require_login();

global $DB, $OUTPUT, $CFG, $PAGE, $USER;

$service = $DB->get_record('external_services', array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
$token = external_generate_token_for_current_user($service)->token;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/academy/coupons.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mycoupons_title', 'local_academy'));
$PAGE->set_heading(get_string('mycoupons_title', 'local_academy'));
$PAGE->requires->js(new moodle_url('/local/academy/ui.js'), true);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mycoupons_title', 'local_academy'));

$STR = local_academy_string_map(array(
    'ui_loading', 'ui_never', 'ui_pager_info',
    'cpn_col_code', 'cpn_col_type', 'cpn_col_value', 'cpn_col_scope', 'cpn_col_dates',
    'cpn_type_percent', 'cpn_type_fixed', 'cpn_unlimited', 'ofr_col_name',
    'cpn_avail_heading', 'cpn_avail_desc', 'cpn_none_avail',
    'ofr_avail_heading', 'ofr_avail_desc', 'ofr_none_avail',
    'cpn_hist_heading', 'cpn_hist_desc', 'cpn_no_history',
    'ofr_hist_heading', 'ofr_hist_desc', 'ofr_no_history',
    'cpn_col_max', 'usg_col_item', 'usg_col_original', 'usg_col_discount', 'usg_col_final', 'usg_col_date',
    'err_sessionexpired', 'err_requestfailed',
));
echo html_writer::script('window.ACADEMY_CFG = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'token'    => $token,
    'lang'     => optional_param('lang', current_language(), PARAM_LANG),
)) . ';');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');
?>
<div id="academy-coupons-app">
    <div id="cp-message" class="alert" style="display:none"></div>

    <h4><?php echo $STR['cpn_avail_heading']; ?></h4>
    <p class="text-muted"><?php echo $STR['cpn_avail_desc']; ?></p>
    <table class="table table-striped" id="cp-avail-table">
        <thead><tr>
            <th><?php echo $STR['cpn_col_code']; ?></th>
            <th><?php echo $STR['cpn_col_type']; ?></th>
            <th><?php echo $STR['cpn_col_value']; ?></th>
            <th><?php echo $STR['cpn_col_max']; ?></th>
            <th><?php echo $STR['cpn_col_scope']; ?></th>
            <th><?php echo $STR['cpn_col_dates']; ?></th>
        </tr></thead>
        <tbody><tr><td colspan="6"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
    </table>

    <h4 class="mt-4"><?php echo $STR['ofr_avail_heading']; ?></h4>
    <p class="text-muted"><?php echo $STR['ofr_avail_desc']; ?></p>
    <table class="table table-striped" id="of-avail-table">
        <thead><tr>
            <th><?php echo $STR['ofr_col_name']; ?></th>
            <th><?php echo $STR['cpn_col_type']; ?></th>
            <th><?php echo $STR['cpn_col_value']; ?></th>
            <th><?php echo $STR['cpn_col_scope']; ?></th>
            <th><?php echo $STR['cpn_col_dates']; ?></th>
        </tr></thead>
        <tbody><tr><td colspan="5"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
    </table>

    <h4 class="mt-4"><?php echo $STR['cpn_hist_heading']; ?></h4>
    <p class="text-muted"><?php echo $STR['cpn_hist_desc']; ?></p>
    <table class="table table-striped" id="cp-hist-table">
        <thead><tr>
            <th><?php echo $STR['cpn_col_code']; ?></th>
            <th><?php echo $STR['usg_col_item']; ?></th>
            <th><?php echo $STR['usg_col_original']; ?></th>
            <th><?php echo $STR['usg_col_discount']; ?></th>
            <th><?php echo $STR['usg_col_final']; ?></th>
            <th><?php echo $STR['usg_col_date']; ?></th>
        </tr></thead>
        <tbody><tr><td colspan="6"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
    </table>

    <h4 class="mt-4"><?php echo $STR['ofr_hist_heading']; ?></h4>
    <p class="text-muted"><?php echo $STR['ofr_hist_desc']; ?></p>
    <table class="table table-striped" id="of-hist-table">
        <thead><tr>
            <th><?php echo $STR['ofr_col_name']; ?></th>
            <th><?php echo $STR['usg_col_item']; ?></th>
            <th><?php echo $STR['usg_col_original']; ?></th>
            <th><?php echo $STR['usg_col_discount']; ?></th>
            <th><?php echo $STR['usg_col_final']; ?></th>
            <th><?php echo $STR['usg_col_date']; ?></th>
        </tr></thead>
        <tbody><tr><td colspan="6"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
    </table>
</div>
<?php

echo html_writer::script(<<<'JS'
(function () {
    var CFG = window.ACADEMY_CFG;
    var STR = window.ACADEMY_STR || {};
    function str(k){return (k in STR)?STR[k]:k;}
    function $(id){return document.getElementById(id);}
    function esc(s){
        return String(s == null ? '' : s).replace(/[&<>"]/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
        });
    }
    function apiGet(func){
        var data = new URLSearchParams({ function: func, token: CFG.token });
        if (CFG.lang){ data.append('alang', CFG.lang); }
        return fetch(CFG.endpoint + '?' + data.toString()).then(function(r){ return r.text(); }).then(function(text){
            var json;
            try { json = JSON.parse(text); } catch(e){ throw new Error(str('err_sessionexpired')); }
            if (json.status !== 'success'){ throw new Error(json.error || str('err_requestfailed')); }
            return json.data;
        });
    }
    function msg(t){ var el = $('cp-message'); el.textContent = t; el.className = 'alert alert-danger'; el.style.display = 'block'; }

    function dtype(t){ return t === 'fixed' ? str('cpn_type_fixed') : str('cpn_type_percent'); }
    function valueLabel(x){ return x.discount_type === 'percent' ? (x.discount_value + '%') : x.discount_value; }
    function fmtDate(ts){ return ts ? new Date(ts*1000).toLocaleDateString() : str('ui_never'); }
    function scopeLabel(x){
        if (!x.applies_to || !x.applies_to.length){ return '—'; }
        return x.applies_to.map(function(a){ return esc(a.label); }).join(', ');
    }
    function dates(x){ return esc(fmtDate(x.startdate)) + ' → ' + esc(fmtDate(x.enddate)); }
    function fill(tableId, cols, rows, empty){
        var tbody = $(tableId).querySelector('tbody');
        if (!rows.length){ tbody.innerHTML = '<tr><td colspan="' + cols + '">' + esc(empty) + '</td></tr>'; return; }
        tbody.innerHTML = rows.map(function(r){ return '<tr>' + r + '</tr>'; }).join('');
    }

    apiGet('get_available_coupons').then(function(rows){
        fill('cp-avail-table', 6, rows.map(function(c){
            return '<td><code>'+esc(c.code)+'</code></td>'+
                '<td>'+esc(dtype(c.discount_type))+'</td>'+
                '<td>'+esc(valueLabel(c))+'</td>'+
                '<td>'+(c.max_discount!=null?esc(c.max_discount):'—')+'</td>'+
                '<td>'+scopeLabel(c)+'</td>'+
                '<td>'+dates(c)+'</td>';
        }), str('cpn_none_avail'));
    }).catch(function(e){ msg(e.message); });

    apiGet('get_available_offers').then(function(rows){
        fill('of-avail-table', 5, rows.map(function(o){
            return '<td>'+esc(o.name)+'</td>'+
                '<td>'+esc(dtype(o.discount_type))+'</td>'+
                '<td>'+esc(valueLabel(o))+'</td>'+
                '<td>'+scopeLabel(o)+'</td>'+
                '<td>'+dates(o)+'</td>';
        }), str('ofr_none_avail'));
    }).catch(function(e){ msg(e.message); });

    apiGet('get_my_coupon_usages').then(function(rows){
        fill('cp-hist-table', 6, rows.map(function(u){
            return '<td><code>'+esc(u.code)+'</code></td>'+
                '<td>'+esc(u.item_label)+'</td>'+
                '<td>'+esc(u.original_amount)+'</td>'+
                '<td>-'+esc(u.discount_amount)+'</td>'+
                '<td>'+esc(u.final_amount)+'</td>'+
                '<td>'+esc(fmtDate(u.timecreated))+'</td>';
        }), str('cpn_no_history'));
    }).catch(function(e){ msg(e.message); });

    apiGet('get_my_offer_usages').then(function(rows){
        fill('of-hist-table', 6, rows.map(function(u){
            return '<td>'+esc(u.name)+'</td>'+
                '<td>'+esc(u.item_label)+'</td>'+
                '<td>'+esc(u.original_amount)+'</td>'+
                '<td>-'+esc(u.discount_amount)+'</td>'+
                '<td>'+esc(u.final_amount)+'</td>'+
                '<td>'+esc(fmtDate(u.timecreated))+'</td>';
        }), str('ofr_no_history'));
    }).catch(function(e){ msg(e.message); });
})();
JS
);

echo $OUTPUT->footer();
