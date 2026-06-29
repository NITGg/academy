<?php
// Teacher "My earnings / wallet" UI (US-FN-2-1). The logged-in teacher sees their balance, requests
// withdrawals, and tracks status. Calls api.php from JS with a minted token.

require('../../config.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');

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

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mywallet', 'local_academy'));
echo html_writer::script('window.ACADEMY_W = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'export'   => $CFG->wwwroot . '/local/academy/export.php',
    'token'    => $token,
)) . ';');
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
  <button id="w-withdraw" class="btn btn-primary">Withdraw earnings</button>

  <div class="w-section">
    <h5>Withdrawals <a id="w-exp-wd" class="btn btn-sm btn-outline-secondary" target="_blank" style="float:right">Export CSV</a></h5>
    <table class="w-table"><thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th><th>Note / ref</th></tr></thead>
      <tbody id="w-withdrawals"></tbody></table>
  </div>

  <div class="w-section">
    <h5>Earnings <a id="w-exp-earn" class="btn btn-sm btn-outline-secondary" target="_blank" style="float:right">Export CSV</a></h5>
    <table class="w-table"><thead><tr><th>Date</th><th>Lesson</th><th>Student</th><th>Lesson date</th><th>Flex value</th><th>Your share</th><th>Status</th></tr></thead>
      <tbody id="w-earnings"></tbody></table>
  </div>
</div>

<div class="w-modal-bg" id="w-modal-bg">
  <div class="w-modal">
    <h5>Withdraw earnings</h5>
    <div class="form-group"><label for="w-amount">Amount</label>
      <input type="number" min="0" step="0.01" class="form-control" id="w-amount"></div>
    <div class="form-group"><label for="w-method">Method</label>
      <select class="form-control" id="w-method">
        <option value="bank">Bank transfer</option>
        <option value="wallet">Mobile wallet</option>
        <option value="cash">Cash</option>
      </select></div>
    <div class="form-group"><label for="w-account">Account / payout details</label>
      <input class="form-control" id="w-account" placeholder="IBAN / phone / note"></div>
    <div class="w-modal-actions">
      <button class="btn btn-outline-secondary" id="w-cancel">Cancel</button>
      <button class="btn btn-primary" id="w-submit">Request</button>
    </div>
  </div>
</div>
<?php
echo html_writer::script(<<<'JS'
(function () {
  var CFG = window.ACADEMY_W;
  function $(id){return document.getElementById(id);}
  function msg(t,k){var e=$('w-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';if(k==='success'){setTimeout(function(){e.style.display='none';},3000);}}
  function parse(r){return r.text().then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error('Session expired — reload the page.');}if(j.status!=='success'){throw new Error(j.error||'Failed');}return j.data;});}
  function money(n){return Number(n||0).toFixed(2);}
  function fmt(ts){return ts?new Date(ts*1000).toLocaleString():'—';}
  function esc(s){return (s||'').replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}

  function apiGet(fn){return fetch(CFG.endpoint+'?'+new URLSearchParams({function:fn,token:CFG.token})).then(parse);}
  function apiPost(fn,p){var b=new URLSearchParams(Object.assign({function:fn,token:CFG.token},p));return fetch(CFG.endpoint,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:b.toString()}).then(parse);}

  function card(label,value,cls){return '<div class="w-card '+(cls||'')+'"><div class="w-label">'+label+'</div><div class="w-value">'+value+'</div></div>';}

  function render(w){
    $('w-cards').innerHTML =
      card('Available balance', money(w.available_balance), 'primary') +
      card('Total earned', money(w.total_earned)) +
      card('Pending withdrawals', money(w.pending_withdrawals)) +
      card('Total withdrawn', money(w.total_withdrawn));

    var wb=$('w-withdrawals'); wb.innerHTML='';
    if(!w.withdrawals.length){wb.innerHTML='<tr><td colspan="5" class="text-muted">No withdrawals yet.</td></tr>';}
    w.withdrawals.forEach(function(x){
      var note = x.status==='rejected' ? esc(x.reason||'') : (x.status==='paid' ? ('Ref: '+esc(x.reference||'—')) : '');
      wb.innerHTML += '<tr><td>'+fmt(x.timecreated)+'</td><td>'+money(x.amount)+'</td><td>'+esc(x.method)+'</td>'+
        '<td><span class="w-badge s-'+x.status+'">'+x.status+'</span></td><td>'+note+'</td></tr>';
    });

    var eb=$('w-earnings'); eb.innerHTML='';
    if(!w.earnings.length){eb.innerHTML='<tr><td colspan="7" class="text-muted">No earnings yet.</td></tr>';}
    w.earnings.forEach(function(x){
      eb.innerHTML += '<tr><td>'+fmt(x.timecreated)+'</td><td>#'+x.lessonid+'</td><td>'+esc(x.student_name||'')+'</td>'+
        '<td>'+fmt(x.lesson_time)+'</td><td>'+money(x.flex_value)+'</td>'+
        '<td>'+money(x.teacher_amount)+' ('+x.teacher_percent+'%)</td>'+
        '<td><span class="w-badge s-'+x.status+'">'+x.status+'</span></td></tr>';
    });
  }

  function load(){apiGet('get_teacher_wallet').then(render).catch(function(e){msg(e.message,'danger');});}

  // CSV export links (US-TR-2-1) — teacher exports their own data.
  $('w-exp-wd').href=CFG.export+'?'+new URLSearchParams({type:'my_withdrawals',token:CFG.token}).toString();
  $('w-exp-earn').href=CFG.export+'?'+new URLSearchParams({type:'my_earnings',token:CFG.token}).toString();

  $('w-withdraw').onclick=function(){$('w-modal-bg').style.display='flex';};
  $('w-cancel').onclick=function(){$('w-modal-bg').style.display='none';};
  $('w-submit').onclick=function(){
    var amount=$('w-amount').value;
    apiPost('request_withdrawal',{amount:amount,method:$('w-method').value,account:$('w-account').value})
      .then(function(){$('w-modal-bg').style.display='none';$('w-amount').value='';$('w-account').value='';msg('Withdrawal requested.','success');load();})
      .catch(function(e){msg(e.message,'danger');});
  };

  load();
})();
JS
);
echo $OUTPUT->footer();
