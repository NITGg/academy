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
 * Student hub — Packages & Flex: browse and buy packages, and track packages / payments / Flex.
 * Drives /local/nit_flex/api.php from vanilla JS. UI labels match the reference.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/nit_flex/lib.php');

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/nit_flex/student.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('studenthub', 'local_nit_flex'));
$PAGE->set_heading(get_string('studenthub', 'local_nit_flex'));
$PAGE->requires->js(new moodle_url('/local/nit_flex/ui.js'), true);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('studenthub', 'local_nit_flex'));

$str = local_nit_flex_string_map([
    'st_available_flex', 'st_book_up_to', 'st_no_active_pkg', 'tab_packages',
    'ui_cancel', 'ui_confirm', 'ui_never', 'ui_currency_egp', 'ui_pager_info',
    'availpkgs_heading', 'mypackages', 'st_payment_history', 'st_flex_history',
    'st_col_package', 'st_col_flexusedtot', 'st_col_status', 'st_col_activated', 'st_col_expires',
    'st_col_date', 'st_col_amount', 'st_col_method', 'st_col_transaction',
    'st_col_type', 'st_col_change', 'st_col_balance', 'st_col_lesson', 'st_col_note',
    'st_pkg_none_available', 'st_pkg_none', 'st_pay_none', 'st_flex_none',
    'st_already_active_pkg', 'st_buy_package', 'st_buy_title', 'st_buy_text', 'st_proceed_payment',
    'st_pkgmeta_flex', 'st_pkgmeta_validdays', 'st_pkgmeta_neverexp', 'st_flex_left',
    'pstat_active', 'pstat_fully_used', 'pstat_expired', 'pstat_cancelled', 'pstat_pending',
    'pay_completed', 'pay_pending', 'pay_failed', 'pay_refunded',
    'flx_reserve', 'flx_consume', 'flx_return', 'flx_purchase', 'flx_assign', 'flx_expire', 'flx_adjust',
    'err_sessionexpired', 'err_requestfailed', 'msg_package_purchased',
]);

echo html_writer::script('window.NIT_ST = ' . json_encode([
    'endpoint' => (new moodle_url('/local/nit_flex/api.php'))->out(false),
    'sesskey'  => sesskey(),
    'lang'     => optional_param('lang', current_language(), PARAM_LANG),
]) . ';');
echo html_writer::script('window.NIT_STR = ' . json_encode($str) . ';');
?>
<style>
#st-app{max-width:920px}
#st-flexbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;background:#eaf3ff;border:1px solid #b6d4fe;border-radius:.5rem;padding:.6rem 1rem;margin-bottom:1rem;flex-wrap:wrap}
#st-flexbar .st-flexval{font-size:1.5rem;font-weight:700;color:#084298}
.st-meta{color:#6c757d;font-size:.9rem;margin-top:.25rem}
.st-section{margin-top:1.5rem}
.st-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:.75rem}
.st-card{border:1px solid #dee2e6;border-radius:.5rem;padding:1rem;margin-bottom:.75rem}
.st-title{font-weight:600;font-size:1.05rem}
.st-actions{margin-top:.75rem;display:flex;gap:.4rem;flex-wrap:wrap}
.st-empty{color:#6c757d;padding:2rem;text-align:center}
.st-badge{display:inline-block;padding:.15rem .55rem;border-radius:1rem;font-size:.8rem;font-weight:600;white-space:nowrap}
.st-flex-pill{font-weight:700;color:#084298}
table.st-table{width:100%;border-collapse:collapse;margin-top:.5rem}
table.st-table th,table.st-table td{border-bottom:1px solid #eee;padding:.45rem .5rem;text-align:left;font-size:.9rem}
.s-active,.s-success,.s-completed_ok{background:#d4edda;color:#155724}
.s-pending{background:#fff3cd;color:#856404}
.s-fully_used,.s-expired,.s-completed{background:#e2e3e5;color:#383d41}
.s-cancelled,.s-failed{background:#f8d7da;color:#721c24}
.st-modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;align-items:center;justify-content:center;z-index:1050}
.st-modal{background:#fff;border-radius:.5rem;padding:1.25rem;max-width:460px;width:90%}
.st-modal-actions{display:flex;justify-content:flex-end;gap:.5rem;margin-top:1rem}
</style>
<div id="st-app">
  <div id="st-msg" class="alert" style="display:none"></div>
  <div id="st-flexbar">
    <div><div class="st-meta" style="margin:0"><?php echo $str['st_available_flex']; ?></div>
      <span class="st-flexval" id="st-flex">—</span></div>
    <div id="st-flexnote" class="st-meta" style="margin:0"></div>
  </div>

  <h5><?php echo $str['availpkgs_heading']; ?></h5>
  <div id="st-available" class="st-grid"></div>
  <div id="st-available-pager" class="acad-pager"></div>

  <div class="st-section">
    <h5><?php echo $str['mypackages']; ?></h5>
    <table class="st-table"><thead><tr>
      <th><?php echo $str['st_col_package']; ?></th><th><?php echo $str['st_col_flexusedtot']; ?></th>
      <th><?php echo $str['st_col_status']; ?></th><th><?php echo $str['st_col_activated']; ?></th>
      <th><?php echo $str['st_col_expires']; ?></th></tr></thead>
      <tbody id="st-mypackages"></tbody></table>
    <div id="st-mypackages-pager" class="acad-pager"></div>
  </div>

  <div class="st-section">
    <h5><?php echo $str['st_payment_history']; ?></h5>
    <table class="st-table"><thead><tr>
      <th><?php echo $str['st_col_date']; ?></th><th><?php echo $str['st_col_package']; ?></th>
      <th><?php echo $str['st_col_amount']; ?></th><th><?php echo $str['st_col_method']; ?></th>
      <th><?php echo $str['st_col_transaction']; ?></th><th><?php echo $str['st_col_status']; ?></th></tr></thead>
      <tbody id="st-payments"></tbody></table>
    <div id="st-payments-pager" class="acad-pager"></div>
  </div>

  <div class="st-section">
    <h5><?php echo $str['st_flex_history']; ?></h5>
    <table class="st-table"><thead><tr>
      <th><?php echo $str['st_col_date']; ?></th><th><?php echo $str['st_col_type']; ?></th>
      <th><?php echo $str['st_col_change']; ?></th><th><?php echo $str['st_col_balance']; ?></th>
      <th><?php echo $str['st_col_lesson']; ?></th><th><?php echo $str['st_col_note']; ?></th></tr></thead>
      <tbody id="st-flexhistory"></tbody></table>
    <div id="st-flexhistory-pager" class="acad-pager"></div>
  </div>
</div>

<div class="st-modal-bg" id="st-modal-bg">
  <div class="st-modal">
    <h5 id="st-modal-title"></h5>
    <div id="st-modal-body"></div>
    <div class="st-modal-actions">
      <button class="btn btn-outline-secondary" id="st-modal-cancel"><?php echo $str['ui_cancel']; ?></button>
      <button class="btn btn-primary" id="st-modal-ok"><?php echo $str['ui_confirm']; ?></button>
    </div>
  </div>
</div>
<?php
echo html_writer::script(<<<'JS'
(function () {
  var CFG=window.NIT_ST, STR=window.NIT_STR||{};
  function $(id){return document.getElementById(id);}
  function el(tag,attrs,html){var e=document.createElement(tag);for(var k in (attrs||{})){e.setAttribute(k,attrs[k]);}if(html!=null){e.innerHTML=html;}return e;}
  function str(k){return (k in STR)?STR[k]:k;}
  function strf(k,params){var s=str(k);if(params==null){return s;}if(typeof params!=='object'){return s.replace(/\{\$a\}/g,params);}return s.replace(/\{\$a->(\w+)\}/g,function(m,n){return (n in params)?params[n]:m;});}
  function esc(s){return (s==null?'':String(s)).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
  function fmt(ts){if(!ts){return '—';}return new Date(ts*1000).toLocaleString();}
  function money(n){return Number(n||0).toFixed(2);}
  function msg(t,k){var e=$('st-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';window.scrollTo(0,0);if(k==='success'){setTimeout(function(){e.style.display='none';},3500);}}
  var PAGE_SIZE=8;
  function pagerLabels(){return {info:str('ui_pager_info')};}
  function gridPager(boxId,pagerId,rows,cardFn,emptyMsg){var box=$(boxId),pg=$(pagerId);if(!rows||!rows.length){box.innerHTML='';if(pg){pg.innerHTML='';}box.appendChild(el('div',{class:'st-empty'},emptyMsg));return;}NitUI.paginate({rows:rows,pageSize:PAGE_SIZE,pagerEl:pg,labels:pagerLabels(),render:function(items){box.innerHTML='';items.forEach(function(r){box.appendChild(cardFn(r));});}});}
  function tablePager(tbodyId,pagerId,rows,rowHtmlFn,colspan,emptyMsg){var tb=$(tbodyId),pg=$(pagerId);if(!rows||!rows.length){tb.innerHTML='<tr><td colspan="'+colspan+'" class="text-muted">'+esc(emptyMsg)+'</td></tr>';if(pg){pg.innerHTML='';}return;}NitUI.paginate({rows:rows,pageSize:PAGE_SIZE,pagerEl:pg,labels:pagerLabels(),render:function(items){tb.innerHTML=items.map(rowHtmlFn).join('');}});}

  function api(fn,params,method){
    params=params||{};method=method||'GET';
    var data=new URLSearchParams({function:fn,sesskey:CFG.sesskey});
    if(CFG.lang){data.append('alang',CFG.lang);}
    Object.keys(params).forEach(function(k){data.append(k,params[k]);});
    var url=CFG.endpoint,opts={};
    if(method==='POST'){opts={method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:data.toString()};}
    else{url=CFG.endpoint+'?'+data.toString();}
    return fetch(url,opts).then(function(r){return r.text();}).then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error(str('err_sessionexpired'));}if(j.status!=='success'){throw new Error(j.error||str('err_requestfailed'));}return j.data;});
  }

  function modal(opts){
    return new Promise(function(resolve){
      $('st-modal-title').textContent=opts.title||'';
      $('st-modal-body').innerHTML=opts.text?('<p class="text-muted">'+esc(opts.text)+'</p>'):'';
      var bg=$('st-modal-bg');bg.style.display='flex';
      var ok=$('st-modal-ok'),cancel=$('st-modal-cancel');
      ok.textContent=opts.okLabel||str('ui_confirm');
      function close(){bg.style.display='none';ok.onclick=null;cancel.onclick=null;}
      ok.onclick=function(){close();resolve(true);};
      cancel.onclick=function(){close();resolve(null);};
    });
  }
  function button(label,cls,onclick,disabled){var b=el('button',{type:'button',class:'btn btn-sm '+cls},label);if(disabled){b.disabled=true;}else{b.onclick=onclick;}return b;}

  function refreshFlex(){
    return api('get_my_packages').then(function(pkgs){
      var bal=0,active=false;(pkgs||[]).forEach(function(p){if(p.status==='active'){bal+=Number(p.remaining_flex||0);active=true;}});
      $('st-flex').textContent=bal;
      $('st-flexnote').innerHTML=active?strf('st_book_up_to',{count:bal}):str('st_no_active_pkg');
      return bal;
    }).catch(function(){});
  }

  var PKG_STATUS={active:str('pstat_active'),fully_used:str('pstat_fully_used'),expired:str('pstat_expired'),cancelled:str('pstat_cancelled'),pending:str('pstat_pending')};
  var PAY_STATUS={success:str('pay_completed'),completed:str('pay_completed'),pending:str('pay_pending'),failed:str('pay_failed'),refunded:str('pay_refunded')};
  var FLEX_TYPE={reserve:str('flx_reserve'),consume:str('flx_consume'),return:str('flx_return'),purchase:str('flx_purchase'),assign:str('flx_assign'),expire:str('flx_expire'),adjust:str('flx_adjust')};

  function packageCard(p,hasActive){
    var c=el('div',{class:'st-card'});
    c.appendChild(el('div',{class:'st-title'},esc(p.name)));
    c.appendChild(el('div',{style:'font-size:1.3rem;font-weight:700;color:#084298'},money(p.price)+' '+str('ui_currency_egp')));
    var meta=strf('st_pkgmeta_flex',p.flex_count)+((Number(p.expiration_days)>0)?strf('st_pkgmeta_validdays',p.expiration_days):str('st_pkgmeta_neverexp'));
    c.appendChild(el('div',{class:'st-meta'},meta));
    if(p.description){c.appendChild(el('div',{class:'st-meta'},esc(p.description)));}
    if(hasActive){c.appendChild(el('div',{class:'st-meta',style:'color:#856404;margin-top:.4rem'},str('st_already_active_pkg')));}
    var actions=el('div',{class:'st-actions'});
    actions.appendChild(button(str('st_buy_package'),'btn-primary',function(){buyPackage(p);},hasActive));
    c.appendChild(actions);
    return c;
  }
  function buyPackage(p){
    modal({title:strf('st_buy_title',p.name),text:strf('st_buy_text',{flex:p.flex_count,price:money(p.price)+' '+str('ui_currency_egp')}),okLabel:str('st_proceed_payment')})
      .then(function(r){if(r===null){return;}
        api('create_package_checkout',{packageid:p.id},'POST').then(function(){msg(str('msg_package_purchased'),'success');refreshFlex();loadPackages();}).catch(function(e){msg(e.message,'danger');});
      });
  }

  function loadPackages(){
    Promise.all([api('get_available_packages'),api('get_my_packages')]).then(function(res){
      var rows=res[0],my=res[1],hasActive=my.some(function(p){return p.status==='active';});
      gridPager('st-available','st-available-pager',rows,function(p){return packageCard(p,hasActive);},str('st_pkg_none_available'));
      tablePager('st-mypackages','st-mypackages-pager',my,function(p){
        return '<tr><td>'+esc(p.name)+'</td><td>'+strf('st_flex_left',{remaining:esc(p.remaining_flex),used:esc(p.used_flex),total:esc(p.total_flex)})+'</td>'+
          '<td><span class="st-badge s-'+p.status+'">'+esc(PKG_STATUS[p.status]||p.status)+'</span></td>'+
          '<td>'+fmt(p.timeactivated)+'</td><td>'+(Number(p.expires_at)>0?fmt(p.expires_at):esc(str('ui_never')))+'</td></tr>';
      },5,str('st_pkg_none'));
    }).catch(function(e){msg(e.message,'danger');});
    api('get_payment_history').then(function(rows){
      tablePager('st-payments','st-payments-pager',rows,function(x){
        return '<tr><td>'+fmt(x.timecreated)+'</td><td>'+esc(x.name)+'</td><td>'+money(x.amount)+'</td><td>'+esc(x.method)+'</td>'+
          '<td>'+esc(x.transaction_no)+'</td><td><span class="st-badge s-'+x.status+'">'+esc(PAY_STATUS[x.status]||x.status)+'</span></td></tr>';
      },6,str('st_pay_none'));
    }).catch(function(e){msg(e.message,'danger');});
    api('get_flex_history').then(function(rows){
      tablePager('st-flexhistory','st-flexhistory-pager',rows,function(x){
        var sign=x.amount>0?('+'+x.amount):String(x.amount);
        return '<tr><td>'+fmt(x.timecreated)+'</td><td><span class="st-badge s-'+(x.type==='consume'?'failed':(x.type==='reserve'?'pending':'active'))+'">'+esc(FLEX_TYPE[x.type]||x.type)+'</span></td>'+
          '<td>'+sign+'</td><td>'+x.balance_after+'</td><td>'+(x.lessonid?('#'+x.lessonid):'—')+'</td><td>'+esc(x.reason||'')+'</td></tr>';
      },6,str('st_flex_none'));
    }).catch(function(e){msg(e.message,'danger');});
  }

  refreshFlex();
  loadPackages();
})();
JS
);
echo $OUTPUT->footer();
