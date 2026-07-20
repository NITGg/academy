<?php
// Admin "Financial Reports" page — the platform's money in one place, across five tabs:
//   Overview      — platform wallet, revenue, discounts, payouts, monthly trend, and the teacher
//                   withdrawal queue (US-FN-2-2) + Flex reversal (US-FN-1-5).
//   Packages      — sales and unused-Flex liability per package        (manage_packages.php)
//   Subscriptions — sales, seats and B2B discount per plan             (manage_subscriptions.php)
//   Coupons       — redemptions and discount given per coupon          (manage_coupons.php)
//   Offers        — applications and discount given per offer          (manage_offers.php)
// The four area tabs are read-only reporting; creating/editing stays on the manage_*.php pages.
// The file keeps its original name so existing links and bookmarks stay valid.

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/academy/lib.php'); // local_academy_string_map()

admin_externalpage_setup('local_academy_managewithdrawals');
require_capability('local/academy:manageplatform', context_system::instance());

global $DB, $OUTPUT, $CFG, $PAGE;

$service = $DB->get_record('external_services', array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
$token = external_generate_token_for_current_user($service)->token;

$PAGE->set_title(get_string('financialreports', 'local_academy'));
$PAGE->set_heading(get_string('financialreports', 'local_academy'));

// Shared UI helpers (AcademyUI.picker) — inhead so it is ready before the page's inline script runs.
$PAGE->requires->js(new moodle_url('/local/academy/ui.js'), true);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('financialreports', 'local_academy'));
// Localised strings: server-rendered HTML reads $STR['key']; the JS reads window.ACADEMY_STR.
$STR = local_academy_string_map(array(
    'st_status', 'lf_all', 'ui_refresh', 'ui_cancel', 'ui_confirm',
    'st_col_date', 'st_col_amount', 'st_col_status', 'pkg_col_actions',
    'wstat_pending', 'wstat_approved', 'wstat_paid', 'wstat_rejected',
    'wd_col_teacher', 'wd_col_methodaccount',
    'wd_updated', 'wd_approve', 'wd_reject', 'wd_markpaid',
    'wd_reject_title', 'wd_reason_required_field', 'wd_markpaid_title', 'wd_payref_optional',
    'wd_reason_required', 'wd_card_current', 'wd_card_undistributed', 'wd_card_teachers',
    'wd_card_platform', 'wd_none', 'w_ref',
    'wd_withdrawals_title',
    'err_sessionexpired', 'err_requestfailed', 'ui_pager_info',
    // Financial Reports.
    'fr_tab_overview', 'fr_tab_packages', 'fr_tab_subscriptions', 'fr_tab_courses',
    'fr_tab_programs', 'fr_tab_coupons', 'fr_tab_offers',
    'fr_from', 'fr_to', 'fr_apply', 'fr_clear', 'fr_alldates', 'fr_export', 'fr_norows', 'fr_total',
    'fr_sec_wallet', 'fr_sec_wallet_help', 'fr_sec_revenue', 'fr_sec_discounts', 'fr_sec_payouts',
    'fr_sec_volume', 'fr_sec_monthly',
    'fr_rev_packages', 'fr_rev_subscriptions', 'fr_rev_courses', 'fr_rev_programs', 'fr_rev_total',
    'fr_disc_coupons', 'fr_disc_offers', 'fr_disc_total', 'fr_disc_gross',
    'fr_vol_packages', 'fr_vol_subscriptions', 'fr_vol_courses', 'fr_vol_programs',
    'fr_vol_coupons', 'fr_vol_offers', 'fr_c_month', 'fr_c_program',
    'fr_c_name', 'fr_c_price', 'fr_c_status', 'fr_c_sales', 'fr_c_revenue', 'fr_c_avgprice',
    'fr_c_soldprice', 'fr_c_pricechanged', 'fr_pricechanged_help',
    'fr_d_show', 'fr_d_hide', 'fr_d_date', 'fr_d_buyer', 'fr_d_listprice', 'fr_d_paid',
    'fr_d_discount', 'fr_d_source', 'fr_d_source_online', 'fr_d_source_assigned', 'fr_d_seats',
    'fr_d_none', 'fr_d_loading',
    'fr_c_online', 'fr_c_assigned', 'fr_c_flexsold', 'fr_c_flexconsumed', 'fr_c_flexunused',
    'fr_c_unusedvalue', 'fr_unusedvalue_help',
    'fr_c_duration', 'fr_c_normal', 'fr_c_b2b', 'fr_c_seats', 'fr_c_activesubs', 'fr_c_b2bdiscount',
    'fr_c_perseat', 'fr_sub_normal_sales', 'fr_sub_normal_rev', 'fr_sub_b2b_sales', 'fr_sub_b2b_rev',
    'fr_sub_normal_help', 'fr_sub_b2b_help',
    'fr_c_course', 'fr_c_buyers', 'fr_c_netrevenue', 'fr_c_refunded', 'fr_c_revoked', 'fr_c_failed',
    'fr_course_deleted', 'fr_netrevenue_help',
    'fr_c_code', 'fr_c_discount', 'fr_c_uses', 'fr_c_uniqueusers', 'fr_c_original', 'fr_c_discounted',
    'fr_c_final', 'fr_c_avgdiscount', 'fr_c_window', 'fr_c_items', 'fr_never',
));
echo html_writer::script('window.ACADEMY_WD = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'token'    => $token,
    'lang'     => optional_param('lang', current_language(), PARAM_LANG),
)) . ';');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');
?>
<style>
#fr-app{max-width:1200px}
#fr-tabs{display:flex;gap:.25rem;border-bottom:1px solid #dee2e6;margin-bottom:1rem;flex-wrap:wrap}
#fr-tabs button{border:none;background:none;padding:.5rem .9rem;border-bottom:3px solid transparent;cursor:pointer}
#fr-tabs button.active{border-bottom-color:#0f6cbf;font-weight:600;color:#0f6cbf}
.fr-pane{display:none}.fr-pane.active{display:block}
#fr-filter{display:flex;gap:.5rem;align-items:flex-end;margin-bottom:1rem;flex-wrap:wrap}
#fr-filter .form-group{margin-bottom:0}
#fr-filter label{display:block;font-size:.82rem;color:#6c757d;margin-bottom:.15rem}
#fr-filter input{max-width:170px}
.fr-section{margin-bottom:1.5rem}
.fr-section > h5{font-size:1rem;font-weight:600;margin-bottom:.15rem}
.fr-section > .fr-help{color:#6c757d;font-size:.82rem;margin-bottom:.5rem}
.fr-cards{display:flex;gap:.75rem;flex-wrap:wrap}
.wd-card{flex:1 1 160px;border:1px solid #dee2e6;border-radius:.5rem;padding:.8rem 1rem}
.wd-card .l{color:#6c757d;font-size:.82rem}.wd-card .v{font-size:1.25rem;font-weight:700;margin-top:.2rem}
.wd-card.accent{border-color:#0f6cbf;background:#f4f9ff}
#wd-toolbar{display:flex;gap:.5rem;align-items:center;margin-bottom:.75rem;flex-wrap:wrap}
#wd-toolbar .form-control{max-width:200px}
table.wd-table{width:100%;border-collapse:collapse}
table.wd-table th,table.wd-table td{border-bottom:1px solid #eee;padding:.45rem .5rem;text-align:left;font-size:.9rem;vertical-align:top}
table.wd-table tfoot td{font-weight:700;border-top:2px solid #dee2e6}
table.wd-table td.num,table.wd-table th.num{text-align:right;white-space:nowrap}
.fr-scroll{overflow-x:auto}
.wd-badge{display:inline-block;padding:.1rem .5rem;border-radius:1rem;font-size:.78rem;font-weight:600}
.s-pending{background:#fff3cd;color:#856404}.s-approved{background:#cce5ff;color:#004085}
.s-paid{background:#d4edda;color:#155724}.s-rejected{background:#f8d7da;color:#721c24}
.s-active{background:#d4edda;color:#155724}.s-inactive{background:#e9ecef;color:#495057}
.s-normal{background:#e3f0fb;color:#0f5a9c}.s-b2b{background:#efe3fb;color:#5b2d90}
/* Carries a title tooltip explaining the current-vs-historical price mismatch. */
.s-warn{background:#fff3cd;color:#856404;cursor:help}
.fr-toggle{border:none;background:none;cursor:pointer;color:#0f6cbf;font-size:1rem;line-height:1;padding:0 .25rem}
.fr-detail > td{background:#f8f9fa;padding:.5rem .75rem}
.fr-detail-box{max-width:100%;overflow-x:auto}
/* Sits inside a row of the outer table, so it must not inherit its full-width borders. */
.fr-detail-table{background:#fff;border:1px solid #e9ecef;border-radius:.25rem}
.fr-detail-table th{font-size:.8rem;color:#6c757d;font-weight:600}
.fr-detail-table td,.fr-detail-table th{padding:.35rem .5rem}
.fr-types{display:flex;gap:1.5rem;flex-wrap:wrap;margin-bottom:1rem;font-size:.86rem;color:#495057}
.fr-types > div{flex:1 1 320px}
.wd-actions{display:flex;gap:.3rem;flex-wrap:wrap}
.wd-modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;align-items:center;justify-content:center;z-index:1050}
.wd-modal{background:#fff;border-radius:.5rem;padding:1.25rem;max-width:420px;width:90%}
.wd-modal .form-group{margin-bottom:.75rem}
.wd-modal-actions{display:flex;justify-content:flex-end;gap:.5rem;margin-top:.5rem}
.fr-bar{height:.6rem;background:#0f6cbf;border-radius:.3rem;display:inline-block;vertical-align:middle;min-width:2px}
.fr-bar.alt{background:#7cb3e8}
</style>
<div id="fr-app">
  <div id="wd-msg" class="alert" style="display:none"></div>

  <div id="fr-tabs">
    <button data-tab="overview" class="active"><?php echo $STR['fr_tab_overview']; ?></button>
    <button data-tab="courses"><?php echo $STR['fr_tab_courses']; ?></button>
    <button data-tab="programs"><?php echo $STR['fr_tab_programs']; ?></button>
    <button data-tab="packages"><?php echo $STR['fr_tab_packages']; ?></button>
    <button data-tab="subscriptions"><?php echo $STR['fr_tab_subscriptions']; ?></button>
    <button data-tab="coupons"><?php echo $STR['fr_tab_coupons']; ?></button>
    <button data-tab="offers"><?php echo $STR['fr_tab_offers']; ?></button>
  </div>

  <div id="fr-filter">
    <div class="form-group"><label for="fr-from"><?php echo $STR['fr_from']; ?></label>
      <input type="date" id="fr-from" class="form-control"></div>
    <div class="form-group"><label for="fr-to"><?php echo $STR['fr_to']; ?></label>
      <input type="date" id="fr-to" class="form-control"></div>
    <button id="fr-apply" class="btn btn-primary"><?php echo $STR['fr_apply']; ?></button>
    <button id="fr-clear" class="btn btn-outline-secondary"><?php echo $STR['fr_clear']; ?></button>
    <button id="fr-export" class="btn btn-outline-secondary"><?php echo $STR['fr_export']; ?></button>
  </div>

  <!-- Tab 1: overview -->
  <div class="fr-pane active" data-pane="overview">
    <div class="fr-section">
      <h5><?php echo $STR['fr_sec_wallet']; ?></h5>
      <div class="fr-help"><?php echo $STR['fr_sec_wallet_help']; ?></div>
      <div class="fr-cards" id="fr-wallet"></div>
    </div>
    <div class="fr-section">
      <h5><?php echo $STR['fr_sec_revenue']; ?></h5>
      <div class="fr-cards" id="fr-revenue"></div>
    </div>
    <div class="fr-section">
      <h5><?php echo $STR['fr_sec_discounts']; ?></h5>
      <div class="fr-cards" id="fr-discounts"></div>
    </div>
    <div class="fr-section">
      <h5><?php echo $STR['fr_sec_volume']; ?></h5>
      <div class="fr-cards" id="fr-volume"></div>
    </div>
    <div class="fr-section">
      <h5><?php echo $STR['fr_sec_payouts']; ?></h5>
      <div class="fr-cards" id="fr-payouts"></div>
    </div>
    <div class="fr-section">
      <h5><?php echo $STR['fr_sec_monthly']; ?></h5>
      <div class="fr-scroll"><table class="wd-table" id="fr-monthly"></table></div>
    </div>

    <div class="fr-section">
      <h5><?php echo $STR['wd_withdrawals_title']; ?></h5>
      <div id="wd-toolbar">
        <label class="m-0" for="wd-filter"><?php echo $STR['st_status']; ?></label>
        <select id="wd-filter" class="form-control">
          <option value=""><?php echo $STR['lf_all']; ?></option>
          <option value="pending"><?php echo $STR['wstat_pending']; ?></option>
          <option value="approved"><?php echo $STR['wstat_approved']; ?></option>
          <option value="paid"><?php echo $STR['wstat_paid']; ?></option>
          <option value="rejected"><?php echo $STR['wstat_rejected']; ?></option>
        </select>
        <button id="wd-refresh" class="btn btn-outline-secondary"><?php echo $STR['ui_refresh']; ?></button>
      </div>
      <div class="fr-scroll">
      <table class="wd-table">
        <thead><tr><th><?php echo $STR['st_col_date']; ?></th><th><?php echo $STR['wd_col_teacher']; ?></th><th class="num"><?php echo $STR['st_col_amount']; ?></th><th><?php echo $STR['wd_col_methodaccount']; ?></th><th><?php echo $STR['st_col_status']; ?></th><th><?php echo $STR['pkg_col_actions']; ?></th></tr></thead>
        <tbody id="wd-rows"></tbody>
      </table>
      </div>
      <div id="wd-rows-pager" class="acad-pager"></div>
    </div>

  </div>

  <!-- Tabs 2-6: one summary card row + one table each. Pane order follows the tab-button order. -->
  <div class="fr-pane" data-pane="courses">
    <div class="fr-cards" id="fr-courses-cards" style="margin-bottom:1rem"></div>
    <div class="fr-help" style="margin-bottom:.5rem"><?php echo $STR['fr_netrevenue_help']; ?></div>
    <div class="fr-scroll"><table class="wd-table" id="fr-courses-table"></table></div>
  </div>
  <div class="fr-pane" data-pane="programs">
    <div class="fr-cards" id="fr-programs-cards" style="margin-bottom:1rem"></div>
    <div class="fr-help" style="margin-bottom:.5rem"><?php echo $STR['fr_netrevenue_help']; ?></div>
    <div class="fr-scroll"><table class="wd-table" id="fr-programs-table"></table></div>
  </div>
  <div class="fr-pane" data-pane="packages">
    <div class="fr-cards" id="fr-packages-cards" style="margin-bottom:1rem"></div>
    <div class="fr-scroll"><table class="wd-table" id="fr-packages-table"></table></div>
  </div>
  <div class="fr-pane" data-pane="subscriptions">
    <div class="fr-types">
      <div><span class="wd-badge s-normal"><?php echo $STR['fr_c_normal']; ?></span>
        <?php echo $STR['fr_sub_normal_help']; ?></div>
      <div><span class="wd-badge s-b2b"><?php echo $STR['fr_c_b2b']; ?></span>
        <?php echo $STR['fr_sub_b2b_help']; ?></div>
    </div>
    <div class="fr-cards" id="fr-subscriptions-cards" style="margin-bottom:1rem"></div>
    <div class="fr-scroll"><table class="wd-table" id="fr-subscriptions-table"></table></div>
  </div>
  <div class="fr-pane" data-pane="coupons">
    <div class="fr-cards" id="fr-coupons-cards" style="margin-bottom:1rem"></div>
    <div class="fr-scroll"><table class="wd-table" id="fr-coupons-table"></table></div>
  </div>
  <div class="fr-pane" data-pane="offers">
    <div class="fr-cards" id="fr-offers-cards" style="margin-bottom:1rem"></div>
    <div class="fr-scroll"><table class="wd-table" id="fr-offers-table"></table></div>
  </div>
</div>

<div class="wd-modal-bg" id="wd-modal-bg">
  <div class="wd-modal">
    <h5 id="wd-modal-title"></h5>
    <div id="wd-modal-body"></div>
    <div class="wd-modal-actions">
      <button class="btn btn-outline-secondary" id="wd-modal-cancel"><?php echo $STR['ui_cancel']; ?></button>
      <button class="btn btn-primary" id="wd-modal-ok"><?php echo $STR['ui_confirm']; ?></button>
    </div>
  </div>
</div>
<?php
echo html_writer::script(<<<'JS'
(function () {
  var CFG = window.ACADEMY_WD;
  var STR = window.ACADEMY_STR || {};
  function str(k){return (k in STR)?STR[k]:k;}
  function strf(k,params){var s=str(k);if(params==null){return s;}if(typeof params!=='object'){return s.replace(/\{\$a\}/g,params);}return s.replace(/\{\$a->(\w+)\}/g,function(m,name){return (name in params)?params[name]:m;});}
  function wstat(s){return str('wstat_'+s)!=='wstat_'+s?str('wstat_'+s):s;}
  function $(id){return document.getElementById(id);}
  function msg(t,k){var e=$('wd-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';if(k==='success'){setTimeout(function(){e.style.display='none';},3000);}}
  function parse(r){return r.text().then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error(str('err_sessionexpired'));}if(j.status!=='success'){throw new Error(j.error||str('err_requestfailed'));}return j.data;});}
  function money(n){return Number(n||0).toFixed(2);}
  function fmt(ts){return ts?new Date(ts*1000).toLocaleString():'—';}
  function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
  function apiGet(fn,p){var base={function:fn,token:CFG.token};if(CFG.lang){base.alang=CFG.lang;}return fetch(CFG.endpoint+'?'+new URLSearchParams(Object.assign(base,p||{}))).then(parse);}
  function apiPost(fn,p){var base={function:fn,token:CFG.token};if(CFG.lang){base.alang=CFG.lang;}var b=new URLSearchParams(Object.assign(base,p));return fetch(CFG.endpoint,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:b.toString()}).then(parse);}

  // ── Date window shared by every report tab ────────────────────────────────
  // <input type="date"> gives a local Y-M-D; `to` covers the whole selected day.
  function dateFilters(){
    var f={}, from=$('fr-from').value, to=$('fr-to').value;
    if(from){f.from=Math.floor(new Date(from+'T00:00:00').getTime()/1000);}
    if(to){f.to=Math.floor(new Date(to+'T23:59:59').getTime()/1000);}
    return f;
  }

  // ── Rendering helpers ─────────────────────────────────────────────────────
  function cards(mount,items){
    $(mount).innerHTML=items.map(function(c){
      return '<div class="wd-card'+(c.accent?' accent':'')+'"><div class="l">'+esc(c.label)+'</div><div class="v">'+esc(c.value)+'</div></div>';
    }).join('');
  }

  var PAGE_SIZE=10;

  /**
   * The pager bar that belongs to a table, created lazily just after it.
   *
   * Anchored outside the .fr-scroll wrapper: inside it, the bar would sit in the horizontally
   * scrolling area and slide out of view on the wide tables that need paging most.
   */
  function pagerFor(mount){
    var id=mount+'-pager', el=$(id);
    if(!el){
      el=document.createElement('div');
      el.id=id; el.className='acad-pager';
      var anchor=$(mount);
      if(anchor.parentNode.classList.contains('fr-scroll')){anchor=anchor.parentNode;}
      anchor.parentNode.insertBefore(el,anchor.nextSibling);
    }
    return el;
  }

  /**
   * Render a paginated table from a column spec.
   * cols: [{key, label, num:bool, money:bool, get:fn(row)->html}]
   * total: optional object keyed like a row, rendered as a footer.
   *
   * The footer totals and the CSV export always cover the WHOLE result set, not just the visible
   * page — a "Total" row that only added up 10 of 40 rows would be actively misleading.
   */
  function table(mount,cols,rows,total,detailKind){
    var el=$(mount);
    // The expander owns a column of its own rather than riding along inside the name cell: it keeps
    // the caret in a fixed place across tabs whose first column differs, and leaves the name
    // clickable-free so a long product name still wraps normally.
    if(detailKind){
      cols=[{key:'_toggle',label:'',get:function(r){
        return '<button type="button" class="fr-toggle" data-id="'+esc(r.id)+'" '+
          'aria-expanded="false" title="'+esc(str('fr_d_show'))+'">&#9656;</button>';
      }}].concat(cols);
    }
    var head='<thead><tr>'+cols.map(function(c){return '<th'+(c.num?' class="num"':'')+'>'+esc(c.label)+'</th>';}).join('')+'</tr></thead>';
    var foot='';
    if(total&&rows.length){
      foot='<tfoot><tr>'+cols.map(function(c,i){
        if(i===0){return '<td>'+esc(str('fr_total'))+'</td>';}
        var v=total[c.key];
        return '<td'+(c.num?' class="num"':'')+'>'+(v==null?'':esc(c.money?money(v):v))+'</td>';
      }).join('')+'</tr></tfoot>';
    }
    el.innerHTML=head+'<tbody></tbody>'+foot;
    var tb=el.querySelector('tbody');
    var pagerEl=pagerFor(mount);
    pagerEl.innerHTML='';

    function renderPage(pageRows){
      if(!pageRows.length){
        tb.innerHTML='<tr><td colspan="'+cols.length+'" class="text-muted">'+esc(str('fr_norows'))+'</td></tr>';
        return;
      }
      tb.innerHTML=pageRows.map(function(r){
        return '<tr>'+cols.map(function(c){return '<td'+(c.num?' class="num"':'')+'>'+(c.get?c.get(r):esc(r[c.key]))+'</td>';}).join('')+'</tr>';
      }).join('');
      // Re-rendering a page drops any open detail rows with it, so nothing to clean up here.
    }

    if(detailKind){
      // Delegated: renderPage() replaces the whole tbody on every page change, which would strip
      // handlers bound to individual buttons.
      tb.onclick=function(ev){
        var btn=ev.target.closest?ev.target.closest('.fr-toggle'):null;
        if(btn&&tb.contains(btn)){toggleDetail(btn,detailKind,cols.length);}
      };
    }

    // Always paginate: the helper drops the page buttons when everything fits on one page but keeps
    // the "Showing 1–n of n" line, which the other admin pages show too.
    AcademyUI.paginate({rows:rows,pageSize:PAGE_SIZE,pagerEl:pagerEl,
      labels:{info:str('ui_pager_info')},render:renderPage});

    // Stash the full data so "Export CSV" serialises every row, not just the page on screen.
    el._cols=cols; el._rows=rows;
  }

  function statusBadge(s){return '<span class="wd-badge s-'+esc(s)+'">'+esc(wstat(s))+'</span>';}
  function dateTime(ts){return ts?new Date(ts*1000).toLocaleString():'—';}

  /**
   * Expand or collapse the individual sales behind one aggregate row.
   *
   * The detail is fetched on demand and then kept: these tabs can list dozens of products, and
   * loading every product's sales up front would multiply the page's payload for rows the admin
   * will never open. Once fetched it stays in the DOM, so re-opening the same row is instant.
   */
  function toggleDetail(btn,kind,colspan){
    var tr=btn.closest('tr'), open=btn.getAttribute('aria-expanded')==='true';
    if(open){
      if(tr.nextSibling&&tr.nextSibling.classList&&tr.nextSibling.classList.contains('fr-detail')){
        tr.nextSibling.style.display='none';
      }
      btn.setAttribute('aria-expanded','false');
      btn.title=str('fr_d_show'); btn.innerHTML='&#9656;';
      return;
    }
    btn.setAttribute('aria-expanded','true');
    btn.title=str('fr_d_hide'); btn.innerHTML='&#9662;';

    var next=tr.nextSibling;
    if(next&&next.classList&&next.classList.contains('fr-detail')){next.style.display='';return;}

    var det=document.createElement('tr');
    det.className='fr-detail';
    det.innerHTML='<td colspan="'+colspan+'"><div class="fr-detail-box">'+esc(str('fr_d_loading'))+'</div></td>';
    tr.parentNode.insertBefore(det,tr.nextSibling);

    var params=dateFilters();
    params.kind=kind; params.itemid=btn.getAttribute('data-id');
    apiGet('finance_purchases',params).then(function(d){
      det.querySelector('.fr-detail-box').innerHTML=detailTable(d.rows,kind);
    }).catch(function(e){
      det.querySelector('.fr-detail-box').innerHTML='<span class="text-danger">'+esc(e.message)+'</span>';
    });
  }

  /** The sub-table of individual sales rendered inside an expanded row. */
  function detailTable(rows,kind){
    if(!rows.length){return '<span class="text-muted">'+esc(str('fr_d_none'))+'</span>';}
    var head=[str('fr_d_date'),str('fr_d_buyer'),str('fr_d_listprice'),str('fr_d_paid'),
      str('fr_d_discount'),str('st_col_status'),str('fr_d_source')];
    return '<table class="wd-table fr-detail-table"><thead><tr>'+
      head.map(function(h,i){return '<th'+(i>=2&&i<=4?' class="num"':'')+'>'+esc(h)+'</th>';}).join('')+
      '</tr></thead><tbody>'+rows.map(function(r){
        // A B2B row's list price is per seat, so the seat count has to travel with it or the paid
        // total looks like it bears no relation to the price beside it.
        var seats=r.seats>0?' <small class="text-muted">('+esc(str('fr_d_seats').replace('{$a}',r.seats))+')</small>':'';
        var disc=r.discount>0
          ? money(r.discount)+(r.discount_label?' <small class="text-muted">'+esc(r.discount_label)+'</small>':'')
          : '—';
        return '<tr><td>'+esc(dateTime(r.timecreated))+'</td>'+
          '<td>'+esc(r.buyer)+'</td>'+
          '<td class="num">'+money(r.list_price)+seats+'</td>'+
          '<td class="num">'+money(r.paid)+'</td>'+
          '<td class="num">'+disc+'</td>'+
          '<td>'+statusBadge(r.status)+'</td>'+
          '<td>'+esc(str(r.source==='admin_assigned'?'fr_d_source_assigned':'fr_d_source_online'))+'</td></tr>';
      }).join('')+'</tbody></table>';
  }

  /**
   * The current list price, flagged when the sales beside it were not all made at that price.
   *
   * Prices get edited mid-life, and every other money column in the row is historical: revenue and
   * averages come from what each buyer actually paid. Without the flag a row reading "price 300,
   * avg 450" looks like a bug rather than the price history it is.
   */
  function currentPrice(r){
    return money(r.price)+(r.price_changed
      ? ' <span class="wd-badge s-warn" title="'+esc(str('fr_pricechanged_help'))+'">'
        +esc(str('fr_c_pricechanged'))+'</span>' : '');
  }

  /**
   * What was charged, and how often: "1000 x 7", or "500 x 2، 400 x 1" once the price moved.
   *
   * A bare min–max range ("400 – 500") leaves the admin unable to tell whether one sale or nine
   * went at the old price, which is usually the actual question, so the counts are always shown.
   */
  function soldPrice(r){
    var b=r.price_breakdown||[];
    if(!b.length){return '—';} // No sales in this window — nothing was charged.
    return b.map(function(p){return money(p.price)+' &times; '+p.count;}).join('<br>');
  }
  function dateOnly(ts){return ts?new Date(ts*1000).toLocaleDateString():'—';}
  function windowLabel(r){
    if(!r.startdate&&!r.enddate){return str('fr_never');}
    return dateOnly(r.startdate)+' → '+(r.enddate?dateOnly(r.enddate):'∞');
  }
  function discountLabel(r){
    return r.discount_type==='percent' ? (Number(r.discount_value)+'%') : money(r.discount_value);
  }
  function itemsLabel(r){
    var t=r.by_item_type||{};
    return Object.keys(t).map(function(k){return k+' ('+t[k]+')';}).join(', ')||'—';
  }

  // ── Tab 1: overview ───────────────────────────────────────────────────────
  function loadOverview(){
    apiGet('finance_overview',dateFilters()).then(function(d){
      var w=d.wallet;
      cards('fr-wallet',[
        {label:str('wd_card_current'),value:money(w.current_money),accent:true},
        {label:str('wd_card_undistributed'),value:money(w.undistributed_money)},
        {label:str('wd_card_teachers'),value:money(w.teachers_money)},
        {label:str('wd_card_platform'),value:money(w.platform_earnings)}
      ]);
      cards('fr-revenue',[
        {label:str('fr_rev_total'),value:money(d.revenue.total),accent:true},
        {label:str('fr_rev_packages'),value:money(d.revenue.packages)},
        {label:str('fr_rev_subscriptions'),value:money(d.revenue.subscriptions)},
        {label:str('fr_rev_courses'),value:money(d.revenue.courses)},
        {label:str('fr_rev_programs'),value:money(d.revenue.programs)}
      ]);
      cards('fr-discounts',[
        {label:str('fr_disc_total'),value:money(d.discounts.total)},
        {label:str('fr_disc_coupons'),value:money(d.discounts.coupons)},
        {label:str('fr_disc_offers'),value:money(d.discounts.offers)},
        {label:str('fr_disc_gross'),value:money(d.discounts.gross_before_discount)}
      ]);
      cards('fr-volume',[
        {label:str('fr_vol_packages'),value:d.volume.package_purchases},
        {label:str('fr_vol_subscriptions'),value:d.volume.subscription_purchases},
        {label:str('fr_vol_courses'),value:d.volume.course_purchases},
        {label:str('fr_vol_programs'),value:d.volume.program_purchases},
        {label:str('fr_vol_coupons'),value:d.volume.coupon_redemptions},
        {label:str('fr_vol_offers'),value:d.volume.offer_applications}
      ]);
      cards('fr-payouts',['pending','approved','paid','rejected'].map(function(s){
        return {label:wstat(s),value:money(d.payouts[s].amount)+' ('+d.payouts[s].count+')'};
      }));

      // Monthly trend: a bar sized against the biggest month reads faster than raw numbers alone.
      var max=d.monthly.reduce(function(m,r){return Math.max(m,r.total);},0)||1;
      table('fr-monthly',[
        {key:'month',label:str('fr_c_month')},
        {key:'packages',label:str('fr_rev_packages'),num:true,money:true,get:function(r){return money(r.packages);}},
        {key:'subscriptions',label:str('fr_rev_subscriptions'),num:true,money:true,get:function(r){return money(r.subscriptions);}},
        {key:'courses',label:str('fr_rev_courses'),num:true,money:true,get:function(r){return money(r.courses);}},
        {key:'programs',label:str('fr_rev_programs'),num:true,money:true,get:function(r){return money(r.programs);}},
        {key:'total',label:str('fr_rev_total'),num:true,money:true,get:function(r){
          return money(r.total)+' <span class="fr-bar" style="width:'+Math.round(r.total/max*90)+'px"></span>';}}
      ],d.monthly,{
        packages:d.monthly.reduce(function(s,r){return s+r.packages;},0),
        subscriptions:d.monthly.reduce(function(s,r){return s+r.subscriptions;},0),
        courses:d.monthly.reduce(function(s,r){return s+r.courses;},0),
        programs:d.monthly.reduce(function(s,r){return s+r.programs;},0),
        total:d.monthly.reduce(function(s,r){return s+r.total;},0)
      });
    }).catch(function(e){msg(e.message,'danger');});
  }

  // ── Tabs 2–5: one loader each, all sharing table()/cards() ────────────────
  function loadPackages(){
    apiGet('finance_packages',dateFilters()).then(function(d){
      var s=d.summary;
      cards('fr-packages-cards',[
        {label:str('fr_c_revenue'),value:money(s.revenue),accent:true},
        {label:str('fr_c_sales'),value:s.sales},
        {label:str('fr_c_flexsold'),value:s.flex_sold},
        {label:str('fr_c_flexconsumed'),value:s.flex_consumed},
        {label:str('fr_c_unusedvalue'),value:money(s.unused_value)}
      ]);
      table('fr-packages-table',[
        {key:'name',label:str('fr_c_name'),get:function(r){return esc(r.name);}},
        {key:'price',label:str('fr_c_price'),num:true,get:currentPrice},
        {key:'price_min',label:str('fr_c_soldprice'),num:true,get:soldPrice},
        {key:'status',label:str('fr_c_status'),get:function(r){return statusBadge(r.status);}},
        {key:'sales',label:str('fr_c_sales'),num:true},
        {key:'online',label:str('fr_c_online'),num:true},
        {key:'assigned',label:str('fr_c_assigned'),num:true},
        {key:'revenue',label:str('fr_c_revenue'),num:true,money:true,get:function(r){return money(r.revenue);}},
        {key:'avg_price',label:str('fr_c_avgprice'),num:true,get:function(r){return money(r.avg_price);}},
        {key:'flex_sold',label:str('fr_c_flexsold'),num:true},
        {key:'flex_consumed',label:str('fr_c_flexconsumed'),num:true},
        {key:'flex_unused',label:str('fr_c_flexunused'),num:true},
        {key:'unused_value',label:str('fr_c_unusedvalue'),num:true,money:true,get:function(r){return money(r.unused_value);}}
      ],d.rows,s,'package');
    }).catch(function(e){msg(e.message,'danger');});
  }

  function loadSubscriptions(){
    apiGet('finance_subscriptions',dateFilters()).then(function(d){
      var s=d.summary;
      // Normal and B2B are two different products; the cards keep their money apart.
      cards('fr-subscriptions-cards',[
        {label:str('fr_c_revenue'),value:money(s.revenue),accent:true},
        {label:str('fr_sub_normal_rev'),value:money(s.normal_revenue)+' ('+s.normal+')'},
        {label:str('fr_sub_b2b_rev'),value:money(s.b2b_revenue)+' ('+s.b2b+')'},
        {label:str('fr_c_seats'),value:s.seats_sold},
        {label:str('fr_c_activesubs'),value:s.active},
        {label:str('fr_c_b2bdiscount'),value:money(s.b2b_discount)}
      ]);
      table('fr-subscriptions-table',[
        {key:'name',label:str('fr_c_name'),get:function(r){return esc(r.name);}},
        {key:'price',label:str('fr_c_price'),num:true,get:currentPrice},
        // B2B rows contribute their per-seat base price here, not the multi-seat total.
        {key:'price_min',label:str('fr_c_soldprice'),num:true,get:soldPrice},
        {key:'duration_days',label:str('fr_c_duration'),num:true},
        {key:'status',label:str('fr_c_status'),get:function(r){
          // Whether the plan may be sold as B2B at all — explains a 0 in the B2B columns.
          return statusBadge(r.status)+(r.b2b_enabled?' <span class="wd-badge s-b2b">'+esc(str('fr_c_b2b'))+'</span>':'');}},
        {key:'normal',label:str('fr_sub_normal_sales'),num:true},
        {key:'normal_revenue',label:str('fr_sub_normal_rev'),num:true,money:true,get:function(r){return money(r.normal_revenue);}},
        {key:'b2b',label:str('fr_sub_b2b_sales'),num:true},
        {key:'seats_sold',label:str('fr_c_seats'),num:true},
        {key:'b2b_revenue',label:str('fr_sub_b2b_rev'),num:true,money:true,get:function(r){return money(r.b2b_revenue);}},
        {key:'b2b_per_seat',label:str('fr_c_perseat'),num:true,get:function(r){return money(r.b2b_per_seat);}},
        {key:'b2b_discount',label:str('fr_c_b2bdiscount'),num:true,money:true,get:function(r){return money(r.b2b_discount);}},
        {key:'active',label:str('fr_c_activesubs'),num:true},
        {key:'revenue',label:str('fr_c_revenue'),num:true,money:true,get:function(r){return money(r.revenue);}}
      ],d.rows,s,'subscription');
    }).catch(function(e){msg(e.message,'danger');});
  }

  function loadCourses(){
    apiGet('finance_courses',dateFilters()).then(function(d){
      var s=d.summary;
      cards('fr-courses-cards',[
        {label:str('fr_c_netrevenue'),value:money(s.net_revenue),accent:true},
        {label:str('fr_c_revenue'),value:money(s.revenue)},
        {label:str('fr_c_sales'),value:s.sales},
        {label:str('fr_c_discounted'),value:money(s.discount_total)},
        {label:str('fr_c_refunded'),value:money(s.refunded_amount)}
      ]);
      table('fr-courses-table',[
        {key:'name',label:str('fr_c_course'),get:function(r){
          return esc(r.name)+(r.deleted?' <span class="wd-badge s-inactive">'+esc(str('fr_course_deleted'))+'</span>':'');}},
        // Courses carry no plugin-side list price, so only the sold range is shown.
        {key:'price_min',label:str('fr_c_soldprice'),num:true,get:soldPrice},
        {key:'sales',label:str('fr_c_sales'),num:true},
        {key:'unique_buyers',label:str('fr_c_buyers'),num:true},
        {key:'revenue',label:str('fr_c_revenue'),num:true,money:true,get:function(r){return money(r.revenue);}},
        {key:'avg_price',label:str('fr_c_avgprice'),num:true,get:function(r){return money(r.avg_price);}},
        {key:'original_total',label:str('fr_c_original'),num:true,money:true,get:function(r){return money(r.original_total);}},
        {key:'discount_total',label:str('fr_c_discounted'),num:true,money:true,get:function(r){return money(r.discount_total);}},
        {key:'refunded_amount',label:str('fr_c_refunded'),num:true,money:true,get:function(r){
          return money(r.refunded_amount)+(r.refunded_count?' ('+r.refunded_count+')':'');}},
        {key:'revoked_count',label:str('fr_c_revoked'),num:true},
        {key:'failed_count',label:str('fr_c_failed'),num:true},
        {key:'net_revenue',label:str('fr_c_netrevenue'),num:true,money:true,get:function(r){return money(r.net_revenue);}}
      ],d.rows,s,'course');
    }).catch(function(e){msg(e.message,'danger');});
  }

  function loadPrograms(){
    apiGet('finance_programs',dateFilters()).then(function(d){
      var s=d.summary;
      cards('fr-programs-cards',[
        {label:str('fr_c_netrevenue'),value:money(s.net_revenue),accent:true},
        {label:str('fr_c_revenue'),value:money(s.revenue)},
        {label:str('fr_c_sales'),value:s.sales},
        {label:str('fr_c_discounted'),value:money(s.discount_total)},
        {label:str('fr_c_refunded'),value:money(s.refunded_amount)}
      ]);
      table('fr-programs-table',[
        {key:'name',label:str('fr_c_program'),get:function(r){
          return esc(r.name)+(r.deleted?' <span class="wd-badge s-inactive">'+esc(str('fr_course_deleted'))+'</span>':'');}},
        {key:'price',label:str('fr_c_price'),num:true,get:currentPrice},
        {key:'price_min',label:str('fr_c_soldprice'),num:true,get:soldPrice},
        {key:'sales',label:str('fr_c_sales'),num:true},
        {key:'unique_buyers',label:str('fr_c_buyers'),num:true},
        {key:'revenue',label:str('fr_c_revenue'),num:true,money:true,get:function(r){return money(r.revenue);}},
        {key:'avg_price',label:str('fr_c_avgprice'),num:true,get:function(r){return money(r.avg_price);}},
        {key:'original_total',label:str('fr_c_original'),num:true,money:true,get:function(r){return money(r.original_total);}},
        {key:'discount_total',label:str('fr_c_discounted'),num:true,money:true,get:function(r){return money(r.discount_total);}},
        {key:'refunded_amount',label:str('fr_c_refunded'),num:true,money:true,get:function(r){
          return money(r.refunded_amount)+(r.refunded_count?' ('+r.refunded_count+')':'');}},
        {key:'revoked_count',label:str('fr_c_revoked'),num:true},
        {key:'failed_count',label:str('fr_c_failed'),num:true},
        {key:'net_revenue',label:str('fr_c_netrevenue'),num:true,money:true,get:function(r){return money(r.net_revenue);}}
      ],d.rows,s,'program');
    }).catch(function(e){msg(e.message,'danger');});
  }

  // Coupons and offers share a shape; only the first column's label differs.
  function loadDiscounts(kind){
    var fn=kind==='coupons'?'finance_coupons':'finance_offers';
    var label=kind==='coupons'?str('fr_c_code'):str('fr_c_name');
    apiGet(fn,dateFilters()).then(function(d){
      var s=d.summary;
      cards('fr-'+kind+'-cards',[
        {label:str('fr_c_discounted'),value:money(s.discount_total),accent:true},
        {label:str('fr_c_uses'),value:s.uses},
        {label:str('fr_c_original'),value:money(s.original_total)},
        {label:str('fr_c_final'),value:money(s.final_total)}
      ]);
      table('fr-'+kind+'-table',[
        {key:'label',label:label,get:function(r){return esc(r.label);}},
        {key:'discount_value',label:str('fr_c_discount'),num:true,get:discountLabel},
        {key:'status',label:str('fr_c_status'),get:function(r){return statusBadge(r.status);}},
        {key:'window',label:str('fr_c_window'),get:windowLabel},
        {key:'uses',label:str('fr_c_uses'),num:true},
        {key:'unique_users',label:str('fr_c_uniqueusers'),num:true},
        {key:'items',label:str('fr_c_items'),get:function(r){return esc(itemsLabel(r));}},
        {key:'original_total',label:str('fr_c_original'),num:true,money:true,get:function(r){return money(r.original_total);}},
        {key:'discount_total',label:str('fr_c_discounted'),num:true,money:true,get:function(r){return money(r.discount_total);}},
        {key:'final_total',label:str('fr_c_final'),num:true,money:true,get:function(r){return money(r.final_total);}},
        {key:'avg_discount',label:str('fr_c_avgdiscount'),num:true,get:function(r){return money(r.avg_discount);}}
      ],d.rows,s);
    }).catch(function(e){msg(e.message,'danger');});
  }

  // ── Withdrawals (unchanged behaviour, now inside the overview tab) ────────
  function modal(opts){
    return new Promise(function(resolve){
      $('wd-modal-title').textContent=opts.title||'';
      var body=$('wd-modal-body');body.innerHTML='';
      var inputs={};
      (opts.fields||[]).forEach(function(f){
        var g=document.createElement('div');g.className='form-group';
        var lab=document.createElement('label');lab.textContent=f.label;g.appendChild(lab);
        var inp=document.createElement('input');inp.className='form-control';inp.type=f.type||'text';if(f.value){inp.value=f.value;}
        g.appendChild(inp);body.appendChild(g);inputs[f.name]=inp;
      });
      if(opts.text){var p=document.createElement('p');p.className='text-muted';p.textContent=opts.text;body.appendChild(p);}
      var bg=$('wd-modal-bg');bg.style.display='flex';
      var ok=$('wd-modal-ok'),cancel=$('wd-modal-cancel');
      function close(){bg.style.display='none';ok.onclick=null;cancel.onclick=null;}
      ok.onclick=function(){var out={};for(var k in inputs){out[k]=inputs[k].value;}close();resolve(out);};
      cancel.onclick=function(){close();resolve(null);};
    });
  }

  function process(id,action,params){
    apiPost('process_withdrawal',Object.assign({withdrawalid:id,action:action},params||{}))
      .then(function(){msg(str('wd_updated'),'success');loadWithdrawals();loadOverview();}).catch(function(e){msg(e.message,'danger');});
  }

  function actionButtons(w){
    var box=document.createElement('div');box.className='wd-actions';
    function btn(label,cls,fn){var b=document.createElement('button');b.className='btn btn-sm '+cls;b.textContent=label;b.onclick=fn;box.appendChild(b);}
    function reject(){modal({title:str('wd_reject_title'),fields:[{name:'reason',label:str('wd_reason_required_field')}]}).then(function(r){if(r){if(!(r.reason||'').trim()){msg(str('wd_reason_required'),'danger');return;}process(w.id,'reject',{reason:r.reason});}});}
    if(w.status==='pending'){
      btn(str('wd_approve'),'btn-primary',function(){process(w.id,'approve');});
      btn(str('wd_reject'),'btn-outline-danger',reject);
    } else if(w.status==='approved'){
      btn(str('wd_markpaid'),'btn-success',function(){modal({title:str('wd_markpaid_title'),fields:[{name:'reference',label:str('wd_payref_optional')}]}).then(function(r){if(r){process(w.id,'pay',{reference:r.reference||''});}});});
      btn(str('wd_reject'),'btn-outline-danger',reject);
    }
    return box;
  }

  // Built row by row rather than through table(): each row carries live action buttons, which the
  // string-based renderer cannot produce.
  function loadWithdrawals(){
    apiGet('list_withdrawals',{status:$('wd-filter').value}).then(function(rows){
      var tb=$('wd-rows'), pagerEl=$('wd-rows-pager');
      pagerEl.innerHTML='';
      function renderPage(pageRows){
        tb.innerHTML='';
        if(!pageRows.length){tb.innerHTML='<tr><td colspan="6" class="text-muted">'+esc(str('wd_none'))+'</td></tr>';return;}
        pageRows.forEach(function(w){
          var tr=document.createElement('tr');
          var note=w.status==='rejected'?('<br><small>'+esc(w.reason||'')+'</small>'):(w.status==='paid'&&w.reference?('<br><small>'+strf('w_ref',esc(w.reference))+'</small>'):'');
          tr.innerHTML='<td>'+fmt(w.timecreated)+'</td><td>'+esc(w.teacher_name||('#'+w.teacherid))+'<br><small>'+esc(w.teacher_email||'')+'</small></td>'+
            '<td class="num">'+money(w.amount)+'</td><td>'+esc(w.method)+'<br><small>'+esc(w.account||'')+'</small></td>'+
            '<td>'+statusBadge(w.status)+note+'</td>';
          var td=document.createElement('td');td.appendChild(actionButtons(w));tr.appendChild(td);
          tb.appendChild(tr);
        });
      }
      AcademyUI.paginate({rows:rows,pageSize:PAGE_SIZE,pagerEl:pagerEl,
        labels:{info:str('ui_pager_info')},render:renderPage});
    }).catch(function(e){msg(e.message,'danger');});
  }

  // ── Tabs ──────────────────────────────────────────────────────────────────
  var LOADERS={
    overview:function(){loadOverview();loadWithdrawals();},
    packages:loadPackages,
    subscriptions:loadSubscriptions,
    courses:loadCourses,
    programs:loadPrograms,
    coupons:function(){loadDiscounts('coupons');},
    offers:function(){loadDiscounts('offers');}
  };
  var EXPORT_TABLES={overview:'fr-monthly',packages:'fr-packages-table',
    subscriptions:'fr-subscriptions-table',courses:'fr-courses-table',
    programs:'fr-programs-table',coupons:'fr-coupons-table',offers:'fr-offers-table'};
  var current='overview';
  var loaded={}; // Tabs fetch on first view, so opening the page costs one request, not five.

  function show(tab){
    current=tab;
    Array.prototype.forEach.call(document.querySelectorAll('#fr-tabs button'),function(b){
      b.classList.toggle('active',b.getAttribute('data-tab')===tab);});
    Array.prototype.forEach.call(document.querySelectorAll('.fr-pane'),function(p){
      p.classList.toggle('active',p.getAttribute('data-pane')===tab);});
    if(!loaded[tab]){loaded[tab]=true;LOADERS[tab]();}
  }
  Array.prototype.forEach.call(document.querySelectorAll('#fr-tabs button'),function(b){
    b.onclick=function(){show(b.getAttribute('data-tab'));};
  });

  // Changing the window invalidates every tab; reload the visible one and re-fetch the rest on view.
  function reloadAll(){loaded={};loaded[current]=true;LOADERS[current]();}
  $('fr-apply').onclick=reloadAll;
  $('fr-clear').onclick=function(){$('fr-from').value='';$('fr-to').value='';reloadAll();};

  // Export the visible tab's table straight from the rendered column spec.
  $('fr-export').onclick=function(){
    var el=$(EXPORT_TABLES[current]);
    if(!el||!el._cols||!el._rows||!el._rows.length){msg(str('fr_norows'),'info');return;}
    function cell(v){v=String(v==null?'':v);return /[",\n]/.test(v)?'"'+v.replace(/"/g,'""')+'"':v;}
    // The expander column is pure UI — an empty header and empty cells in every exported file.
    var xcols=el._cols.filter(function(c){return c.key!=='_toggle';});
    var lines=[xcols.map(function(c){return cell(c.label);}).join(',')];
    el._rows.forEach(function(r){
      lines.push(xcols.map(function(c){
        // Prefer the raw value; getters return HTML (badges, bars) that would break the CSV.
        // The exceptions are getters that emit plain text a raw column cannot express: a range
        // needs both ends, and price_min alone would read as the only price ever charged.
        if(c.key==='window'){return cell(windowLabel(r));}
        if(c.key==='items'){return cell(itemsLabel(r));}
        if(c.key==='price_min'){
          return cell((r.price_breakdown||[]).map(function(p){
            return p.price+' x '+p.count;}).join(' | ')||'');
        }
        return cell(r[c.key]);
      }).join(','));
    });
    var blob=new Blob(["﻿"+lines.join('\r\n')],{type:'text/csv;charset=utf-8;'});
    var a=document.createElement('a');
    a.href=URL.createObjectURL(blob);
    a.download='financial-'+current+'-'+new Date().toISOString().slice(0,10)+'.csv';
    document.body.appendChild(a);a.click();document.body.removeChild(a);URL.revokeObjectURL(a.href);
  };

  $('wd-filter').onchange=loadWithdrawals;
  $('wd-refresh').onclick=loadWithdrawals;

  loaded.overview=true;
  LOADERS.overview();
})();
JS
);
echo $OUTPUT->footer();
