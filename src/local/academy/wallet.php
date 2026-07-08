<?php
// Teacher "My earnings / wallet" UI (US-FN-2-1). The logged-in teacher sees their balance, requests
// withdrawals, and tracks status. Calls api.php from JS with a minted token.

require('../../config.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/academy/lib.php'); // local_academy_string_map()

require_login();

global $DB, $OUTPUT, $CFG, $PAGE, $USER;

// Teachers only.
if (!\local_academy\teacher_manager::is_teacher($USER->id)) {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/local/academy/wallet.php'));
    $PAGE->set_title(get_string('mywallet', 'local_academy'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('notateacher', 'local_academy'), 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

$service = $DB->get_record('external_services', array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
$token = external_generate_token_for_current_user($service)->token;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/academy/wallet.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mywallet', 'local_academy'));
$PAGE->set_heading(get_string('mywallet', 'local_academy'));

// Shared UI helpers (AcademyUI.paginate) — inhead so it is ready before the page's inline script runs.
$PAGE->requires->js(new moodle_url('/local/academy/ui.js'), true);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mywallet', 'local_academy'));
// Localised strings: server-rendered HTML reads $STR['key']; the JS reads window.ACADEMY_STR.
$STR = local_academy_string_map(array(
    'ui_export_csv', 'ui_request', 'ui_cancel',
    'w_withdraw', 'w_withdrawals_heading', 'w_earnings_heading', 'w_col_noteref', 'w_col_student',
    'w_col_lessondate', 'w_col_flexvalue', 'w_col_yourshare', 'w_amount', 'w_method', 'w_method_cash',
    'w_account', 'w_account_ph', 'w_available_balance', 'w_total_earned', 'w_pending_withdrawals',
    'w_total_withdrawn', 'w_no_withdrawals', 'w_no_earnings', 'w_ref', 'w_requested', 'w_share',
    'wstat_pending', 'wstat_approved', 'wstat_paid', 'wstat_rejected', 'wstat_active', 'wstat_reversed',
    'st_col_date', 'st_col_amount', 'st_col_method', 'st_col_status', 'st_col_lesson',
    'ap_method_bank', 'ap_method_wallet', 'err_sessionexpired', 'err_requestfailed', 'ui_pager_info',
));
echo html_writer::script('window.ACADEMY_W = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'export'   => $CFG->wwwroot . '/local/academy/export.php',
    'token'    => $token,
    'lang'     => optional_param('lang', current_language(), PARAM_LANG),
)) . ';');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');
?>
<style>
#w-app{max-width:860px}
#w-cards{display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1rem}
.w-card{flex:1 1 160px;border:1px solid #dee2e6;border-radius:.5rem;padding:.9rem 1rem}
.w-card .w-label{color:#6c757d;font-size:.85rem}
.w-card .w-value{font-size:1.4rem;font-weight:700;margin-top:.2rem}
.w-card.primary{background:#eaf3ff;border-color:#b6d4fe}
table.w-table{width:100%;border-collapse:collapse;margin-top:.5rem}
table.w-table th,table.w-table td{border-bottom:1px solid #eee;padding:.45rem .5rem;text-align:left;font-size:.92rem}
.w-badge{display:inline-block;padding:.1rem .5rem;border-radius:1rem;font-size:.8rem;font-weight:600}
.s-pending{background:#fff3cd;color:#856404}.s-approved{background:#cce5ff;color:#004085}
.s-paid{background:#d4edda;color:#155724}.s-rejected{background:#f8d7da;color:#721c24}
.s-active{background:#d4edda;color:#155724}.s-reversed{background:#f8d7da;color:#721c24}
.w-section{margin-top:1.5rem}
.w-modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;align-items:center;justify-content:center;z-index:1050}
.w-modal{background:#fff;border-radius:.5rem;padding:1.25rem;max-width:420px;width:90%}
.w-modal .form-group{margin-bottom:.75rem}
.w-modal-actions{display:flex;justify-content:flex-end;gap:.5rem;margin-top:.5rem}
</style>
<div id="w-app">
  <div id="w-msg" class="alert" style="display:none"></div>
  <div id="w-cards"></div>
  <button id="w-withdraw" class="btn btn-primary"><?php echo $STR['w_withdraw']; ?></button>

  <div class="w-section">
    <h5><?php echo $STR['w_withdrawals_heading']; ?> <a id="w-exp-wd" class="btn btn-sm btn-outline-secondary" target="_blank" style="float:right"><?php echo $STR['ui_export_csv']; ?></a></h5>
    <table class="w-table"><thead><tr><th><?php echo $STR['st_col_date']; ?></th><th><?php echo $STR['st_col_amount']; ?></th><th><?php echo $STR['st_col_method']; ?></th><th><?php echo $STR['st_col_status']; ?></th><th><?php echo $STR['w_col_noteref']; ?></th></tr></thead>
      <tbody id="w-withdrawals"></tbody></table>
    <div id="w-withdrawals-pager" class="acad-pager"></div>
  </div>

  <div class="w-section">
    <h5><?php echo $STR['w_earnings_heading']; ?> <a id="w-exp-earn" class="btn btn-sm btn-outline-secondary" target="_blank" style="float:right"><?php echo $STR['ui_export_csv']; ?></a></h5>
    <table class="w-table"><thead><tr><th><?php echo $STR['st_col_date']; ?></th><th><?php echo $STR['st_col_lesson']; ?></th><th><?php echo $STR['w_col_student']; ?></th><th><?php echo $STR['w_col_lessondate']; ?></th><th><?php echo $STR['w_col_flexvalue']; ?></th><th><?php echo $STR['w_col_yourshare']; ?></th><th><?php echo $STR['st_col_status']; ?></th></tr></thead>
      <tbody id="w-earnings"></tbody></table>
    <div id="w-earnings-pager" class="acad-pager"></div>
  </div>
</div>

<div class="w-modal-bg" id="w-modal-bg">
  <div class="w-modal">
    <h5><?php echo $STR['w_withdraw']; ?></h5>
    <div class="form-group"><label for="w-amount"><?php echo $STR['w_amount']; ?></label>
      <input type="number" min="0" step="0.01" class="form-control" id="w-amount"></div>
    <div class="form-group"><label for="w-method"><?php echo $STR['w_method']; ?></label>
      <select class="form-control" id="w-method">
        <option value="bank"><?php echo $STR['ap_method_bank']; ?></option>
        <option value="wallet"><?php echo $STR['ap_method_wallet']; ?></option>
        <option value="cash"><?php echo $STR['w_method_cash']; ?></option>
      </select></div>
    <div class="form-group"><label for="w-account"><?php echo $STR['w_account']; ?></label>
      <input class="form-control" id="w-account" placeholder="<?php echo s($STR['w_account_ph']); ?>"></div>
    <div class="w-modal-actions">
      <button class="btn btn-outline-secondary" id="w-cancel"><?php echo $STR['ui_cancel']; ?></button>
      <button class="btn btn-primary" id="w-submit"><?php echo $STR['ui_request']; ?></button>
    </div>
  </div>
</div>
<?php
echo html_writer::script(<<<'JS'
(function () {
  var CFG = window.ACADEMY_W;
  var STR = window.ACADEMY_STR || {};
  function str(k){return (k in STR)?STR[k]:k;}
  function strf(k,params){var s=str(k);if(params==null){return s;}if(typeof params!=='object'){return s.replace(/\{\$a\}/g,params);}return s.replace(/\{\$a->(\w+)\}/g,function(m,name){return (name in params)?params[name]:m;});}
  function $(id){return document.getElementById(id);}
  function msg(t,k){var e=$('w-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';if(k==='success'){setTimeout(function(){e.style.display='none';},3000);}}
  function parse(r){return r.text().then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error(str('err_sessionexpired'));}if(j.status!=='success'){throw new Error(j.error||str('err_requestfailed'));}return j.data;});}
  function money(n){return Number(n||0).toFixed(2);}
  function fmt(ts){return ts?new Date(ts*1000).toLocaleString():'—';}
  function esc(s){return (s||'').replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
  // Withdrawal + earning status → localised label.
  function wstat(s){return str('wstat_'+s)!=='wstat_'+s?str('wstat_'+s):s;}

  var PAGE_SIZE=10;
  function tablePager(tbodyId,pagerId,rows,rowHtmlFn,colspan,emptyMsg){
    var tb=$(tbodyId),pg=$(pagerId);
    if(!rows||!rows.length){tb.innerHTML='<tr><td colspan="'+colspan+'" class="text-muted">'+esc(emptyMsg)+'</td></tr>';if(pg){pg.innerHTML='';}return;}
    AcademyUI.paginate({rows:rows,pageSize:PAGE_SIZE,pagerEl:pg,labels:{info:str('ui_pager_info')},
      render:function(items){tb.innerHTML=items.map(rowHtmlFn).join('');}});
  }

  function apiGet(fn){var base={function:fn,token:CFG.token};if(CFG.lang){base.alang=CFG.lang;}return fetch(CFG.endpoint+'?'+new URLSearchParams(base)).then(parse);}
  function apiPost(fn,p){var base={function:fn,token:CFG.token};if(CFG.lang){base.alang=CFG.lang;}var b=new URLSearchParams(Object.assign(base,p));return fetch(CFG.endpoint,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:b.toString()}).then(parse);}

  function card(label,value,cls){return '<div class="w-card '+(cls||'')+'"><div class="w-label">'+label+'</div><div class="w-value">'+value+'</div></div>';}

  function render(w){
    $('w-cards').innerHTML =
      card(str('w_available_balance'), money(w.available_balance), 'primary') +
      card(str('w_total_earned'), money(w.total_earned)) +
      card(str('w_pending_withdrawals'), money(w.pending_withdrawals)) +
      card(str('w_total_withdrawn'), money(w.total_withdrawn));

    tablePager('w-withdrawals','w-withdrawals-pager',w.withdrawals,function(x){
      var note = x.status==='rejected' ? esc(x.reason||'') : (x.status==='paid' ? strf('w_ref',esc(x.reference||'—')) : '');
      return '<tr><td>'+fmt(x.timecreated)+'</td><td>'+money(x.amount)+'</td><td>'+esc(x.method)+'</td>'+
        '<td><span class="w-badge s-'+x.status+'">'+esc(wstat(x.status))+'</span></td><td>'+note+'</td></tr>';
    },5,str('w_no_withdrawals'));

    tablePager('w-earnings','w-earnings-pager',w.earnings,function(x){
      return '<tr><td>'+fmt(x.timecreated)+'</td><td>#'+x.lessonid+'</td><td>'+esc(x.student_name||'')+'</td>'+
        '<td>'+fmt(x.lesson_time)+'</td><td>'+money(x.flex_value)+'</td>'+
        '<td>'+strf('w_share',{amount:money(x.teacher_amount),percent:x.teacher_percent})+'</td>'+
        '<td><span class="w-badge s-'+x.status+'">'+esc(wstat(x.status))+'</span></td></tr>';
    },7,str('w_no_earnings'));
  }

  function load(){apiGet('get_teacher_wallet').then(render).catch(function(e){msg(e.message,'danger');});}

  // CSV export links (US-TR-2-1) — teacher exports their own data.
  $('w-exp-wd').href=CFG.export+'?'+new URLSearchParams({type:'my_withdrawals',token:CFG.token,alang:CFG.lang||''}).toString();
  $('w-exp-earn').href=CFG.export+'?'+new URLSearchParams({type:'my_earnings',token:CFG.token,alang:CFG.lang||''}).toString();

  $('w-withdraw').onclick=function(){$('w-modal-bg').style.display='flex';};
  $('w-cancel').onclick=function(){$('w-modal-bg').style.display='none';};
  $('w-submit').onclick=function(){
    var amount=$('w-amount').value;
    apiPost('request_withdrawal',{amount:amount,method:$('w-method').value,account:$('w-account').value})
      .then(function(){$('w-modal-bg').style.display='none';$('w-amount').value='';$('w-account').value='';msg(str('w_requested'),'success');load();})
      .catch(function(e){msg(e.message,'danger');});
  };

  load();
})();
JS
);
echo $OUTPUT->footer();
