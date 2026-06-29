<?php
// Admin UI for teacher withdrawals (US-FN-2-2) + Flex reversal (US-FN-1-5). Uses the local_academy API.

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');

admin_externalpage_setup('local_academy_managewithdrawals');
require_capability('local/academy:manageplatform', context_system::instance());

global $DB, $OUTPUT, $CFG, $PAGE;

$service = $DB->get_record('external_services', array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
$token = external_generate_token_for_current_user($service)->token;

$PAGE->set_title(get_string('managewithdrawals', 'local_academy'));
$PAGE->set_heading(get_string('managewithdrawals', 'local_academy'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managewithdrawals', 'local_academy'));
echo html_writer::script('window.ACADEMY_WD = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'token'    => $token,
)) . ';');
?>
<style>
#wd-app{max-width:980px}
#wd-cards{display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1rem}
.wd-card{flex:1 1 150px;border:1px solid #dee2e6;border-radius:.5rem;padding:.8rem 1rem}
.wd-card .l{color:#6c757d;font-size:.82rem}.wd-card .v{font-size:1.25rem;font-weight:700;margin-top:.2rem}
#wd-toolbar{display:flex;gap:.5rem;align-items:center;margin-bottom:.75rem;flex-wrap:wrap}
#wd-toolbar .form-control{max-width:200px}
table.wd-table{width:100%;border-collapse:collapse}
table.wd-table th,table.wd-table td{border-bottom:1px solid #eee;padding:.45rem .5rem;text-align:left;font-size:.9rem;vertical-align:top}
.wd-badge{display:inline-block;padding:.1rem .5rem;border-radius:1rem;font-size:.78rem;font-weight:600}
.s-pending{background:#fff3cd;color:#856404}.s-approved{background:#cce5ff;color:#004085}
.s-paid{background:#d4edda;color:#155724}.s-rejected{background:#f8d7da;color:#721c24}
.wd-actions{display:flex;gap:.3rem;flex-wrap:wrap}
.wd-reversal{margin-top:1.5rem;border:1px solid #ffe082;background:#fff8e1;border-radius:.5rem;padding:1rem;max-width:560px}
.wd-reversal .form-group{margin-bottom:.6rem}
.wd-modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;align-items:center;justify-content:center;z-index:1050}
.wd-modal{background:#fff;border-radius:.5rem;padding:1.25rem;max-width:420px;width:90%}
.wd-modal .form-group{margin-bottom:.75rem}
.wd-modal-actions{display:flex;justify-content:flex-end;gap:.5rem;margin-top:.5rem}
</style>
<div id="wd-app">
  <div id="wd-msg" class="alert" style="display:none"></div>
  <div id="wd-cards"></div>

  <div id="wd-toolbar">
    <label class="m-0" for="wd-filter">Status</label>
    <select id="wd-filter" class="form-control">
      <option value="">All</option>
      <option value="pending">Pending</option>
      <option value="approved">Approved</option>
      <option value="paid">Paid</option>
      <option value="rejected">Rejected</option>
    </select>
    <button id="wd-refresh" class="btn btn-outline-secondary">Refresh</button>
  </div>

  <table class="wd-table">
    <thead><tr><th>Date</th><th>Teacher</th><th>Amount</th><th>Method / account</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody id="wd-rows"></tbody>
  </table>

  <div class="wd-reversal">
    <h6>Reverse a completed lesson's Flex (US-FN-1-5)</h6>
    <p class="text-muted" style="font-size:.88rem">Returns one consumed Flex to the student and reverses the teacher/platform earning. A reason is required.</p>
    <div class="form-group"><label for="wd-rev-lesson">Lesson ID</label><input class="form-control" id="wd-rev-lesson" type="number" min="1" style="max-width:200px"></div>
    <div class="form-group"><label for="wd-rev-reason">Reason</label><input class="form-control" id="wd-rev-reason"></div>
    <button id="wd-rev-btn" class="btn btn-warning">Return Flex</button>
  </div>
</div>

<div class="wd-modal-bg" id="wd-modal-bg">
  <div class="wd-modal">
    <h5 id="wd-modal-title"></h5>
    <div id="wd-modal-body"></div>
    <div class="wd-modal-actions">
      <button class="btn btn-outline-secondary" id="wd-modal-cancel">Cancel</button>
      <button class="btn btn-primary" id="wd-modal-ok">Confirm</button>
    </div>
  </div>
</div>
<?php
echo html_writer::script(<<<'JS'
(function () {
  var CFG = window.ACADEMY_WD;
  function $(id){return document.getElementById(id);}
  function msg(t,k){var e=$('wd-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';if(k==='success'){setTimeout(function(){e.style.display='none';},3000);}}
  function parse(r){return r.text().then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error('Session expired — reload the page.');}if(j.status!=='success'){throw new Error(j.error||'Failed');}return j.data;});}
  function money(n){return Number(n||0).toFixed(2);}
  function fmt(ts){return ts?new Date(ts*1000).toLocaleString():'—';}
  function esc(s){return (s||'').replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
  function apiGet(fn,p){return fetch(CFG.endpoint+'?'+new URLSearchParams(Object.assign({function:fn,token:CFG.token},p||{}))).then(parse);}
  function apiPost(fn,p){var b=new URLSearchParams(Object.assign({function:fn,token:CFG.token},p));return fetch(CFG.endpoint,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:b.toString()}).then(parse);}

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
      .then(function(){msg('Updated.','success');load();}).catch(function(e){msg(e.message,'danger');});
  }

  function actionButtons(w){
    var box=document.createElement('div');box.className='wd-actions';
    function btn(label,cls,fn){var b=document.createElement('button');b.className='btn btn-sm '+cls;b.textContent=label;b.onclick=fn;box.appendChild(b);}
    if(w.status==='pending'){
      btn('Approve','btn-primary',function(){process(w.id,'approve');});
      btn('Reject','btn-outline-danger',function(){modal({title:'Reject withdrawal',fields:[{name:'reason',label:'Reason (required)'}]}).then(function(r){if(r){if(!(r.reason||'').trim()){msg('Reason required.','danger');return;}process(w.id,'reject',{reason:r.reason});}});});
    } else if(w.status==='approved'){
      btn('Mark paid','btn-success',function(){modal({title:'Mark as paid',fields:[{name:'reference',label:'Payment reference (optional)'}]}).then(function(r){if(r){process(w.id,'pay',{reference:r.reference||''});}});});
      btn('Reject','btn-outline-danger',function(){modal({title:'Reject withdrawal',fields:[{name:'reason',label:'Reason (required)'}]}).then(function(r){if(r){if(!(r.reason||'').trim()){msg('Reason required.','danger');return;}process(w.id,'reject',{reason:r.reason});}});});
    }
    return box;
  }

  function renderCards(p){
    $('wd-cards').innerHTML=
      '<div class="wd-card"><div class="l">Platform current money</div><div class="v">'+money(p.current_money)+'</div></div>'+
      '<div class="wd-card"><div class="l">Undistributed (unused Flex)</div><div class="v">'+money(p.undistributed_money)+'</div></div>'+
      '<div class="wd-card"><div class="l">Teachers\' money (unpaid)</div><div class="v">'+money(p.teachers_money)+'</div></div>'+
      '<div class="wd-card"><div class="l">Platform earnings</div><div class="v">'+money(p.platform_earnings)+'</div></div>';
  }

  function load(){
    apiGet('get_platform_wallet').then(renderCards).catch(function(e){msg(e.message,'danger');});
    apiGet('list_withdrawals',{status:$('wd-filter').value}).then(function(rows){
      var tb=$('wd-rows');tb.innerHTML='';
      if(!rows.length){tb.innerHTML='<tr><td colspan="6" class="text-muted">No withdrawal requests.</td></tr>';return;}
      rows.forEach(function(w){
        var tr=document.createElement('tr');
        var note=w.status==='rejected'?('<br><small>'+esc(w.reason||'')+'</small>'):(w.status==='paid'&&w.reference?('<br><small>Ref: '+esc(w.reference)+'</small>'):'');
        tr.innerHTML='<td>'+fmt(w.timecreated)+'</td><td>'+esc(w.teacher_name||('#'+w.teacherid))+'<br><small>'+esc(w.teacher_email||'')+'</small></td>'+
          '<td>'+money(w.amount)+'</td><td>'+esc(w.method)+'<br><small>'+esc(w.account||'')+'</small></td>'+
          '<td><span class="wd-badge s-'+w.status+'">'+w.status+'</span>'+note+'</td>';
        var td=document.createElement('td');td.appendChild(actionButtons(w));tr.appendChild(td);
        tb.appendChild(tr);
      });
    }).catch(function(e){msg(e.message,'danger');});
  }

  $('wd-filter').onchange=load;
  $('wd-refresh').onclick=load;
  $('wd-rev-btn').onclick=function(){
    var lid=$('wd-rev-lesson').value, reason=$('wd-rev-reason').value;
    if(!lid){msg('Enter a lesson ID.','danger');return;}
    if(!reason.trim()){msg('A reason is required.','danger');return;}
    apiPost('reverse_flex',{lessonid:lid,reason:reason}).then(function(){msg('Flex returned and earning reversed.','success');$('wd-rev-lesson').value='';$('wd-rev-reason').value='';load();}).catch(function(e){msg(e.message,'danger');});
  };

  load();
})();
JS
);
echo $OUTPUT->footer();
