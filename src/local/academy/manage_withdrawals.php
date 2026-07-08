<?php
// Admin UI for teacher withdrawals (US-FN-2-2) + Flex reversal (US-FN-1-5). Uses the local_academy API.

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

$PAGE->set_title(get_string('managewithdrawals', 'local_academy'));
$PAGE->set_heading(get_string('managewithdrawals', 'local_academy'));

// Shared UI helpers (AcademyUI.picker) — inhead so it is ready before the page's inline script runs.
$PAGE->requires->js(new moodle_url('/local/academy/ui.js'), true);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managewithdrawals', 'local_academy'));
// Localised strings: server-rendered HTML reads $STR['key']; the JS reads window.ACADEMY_STR.
$STR = local_academy_string_map(array(
    'st_status', 'lf_all', 'ui_refresh', 'ui_cancel', 'ui_confirm',
    'st_col_date', 'st_col_amount', 'st_col_status', 'pkg_col_actions',
    'wstat_pending', 'wstat_approved', 'wstat_paid', 'wstat_rejected',
    'wd_col_teacher', 'wd_col_methodaccount', 'wd_reversal_title', 'wd_reversal_help', 'wd_lesson_id',
    'wd_reason', 'wd_return_flex', 'wd_updated', 'wd_approve', 'wd_reject', 'wd_markpaid',
    'wd_reject_title', 'wd_reason_required_field', 'wd_markpaid_title', 'wd_payref_optional',
    'wd_reason_required', 'wd_card_current', 'wd_card_undistributed', 'wd_card_teachers',
    'wd_card_platform', 'wd_none', 'wd_enter_lesson', 'wd_flex_returned', 'w_ref', 'err_reasonrequired',
    'ui_picker_lesson_ph', 'ui_picker_searching', 'ui_picker_none', 'ui_picker_hint', 'ui_currency_egp',
    'err_sessionexpired', 'err_requestfailed',
));
echo html_writer::script('window.ACADEMY_WD = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'token'    => $token,
    'lang'     => optional_param('lang', current_language(), PARAM_LANG),
)) . ';');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');
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

  <table class="wd-table">
    <thead><tr><th><?php echo $STR['st_col_date']; ?></th><th><?php echo $STR['wd_col_teacher']; ?></th><th><?php echo $STR['st_col_amount']; ?></th><th><?php echo $STR['wd_col_methodaccount']; ?></th><th><?php echo $STR['st_col_status']; ?></th><th><?php echo $STR['pkg_col_actions']; ?></th></tr></thead>
    <tbody id="wd-rows"></tbody>
  </table>

  <div class="wd-reversal">
    <h6><?php echo $STR['wd_reversal_title']; ?></h6>
    <p class="text-muted" style="font-size:.88rem"><?php echo $STR['wd_reversal_help']; ?></p>
    <div class="form-group"><label for="wd-rev-lesson"><?php echo $STR['wd_lesson_id']; ?></label><div id="wd-rev-lesson"></div></div>
    <div class="form-group"><label for="wd-rev-reason"><?php echo $STR['wd_reason']; ?></label><input class="form-control" id="wd-rev-reason"></div>
    <button id="wd-rev-btn" class="btn btn-warning"><?php echo $STR['wd_return_flex']; ?></button>
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
  function esc(s){return (s||'').replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
  function apiGet(fn,p){var base={function:fn,token:CFG.token};if(CFG.lang){base.alang=CFG.lang;}return fetch(CFG.endpoint+'?'+new URLSearchParams(Object.assign(base,p||{}))).then(parse);}
  function apiPost(fn,p){var base={function:fn,token:CFG.token};if(CFG.lang){base.alang=CFG.lang;}var b=new URLSearchParams(Object.assign(base,p));return fetch(CFG.endpoint,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:b.toString()}).then(parse);}

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
      .then(function(){msg(str('wd_updated'),'success');load();}).catch(function(e){msg(e.message,'danger');});
  }

  function actionButtons(w){
    var box=document.createElement('div');box.className='wd-actions';
    function btn(label,cls,fn){var b=document.createElement('button');b.className='btn btn-sm '+cls;b.textContent=label;b.onclick=fn;box.appendChild(b);}
    if(w.status==='pending'){
      btn(str('wd_approve'),'btn-primary',function(){process(w.id,'approve');});
      btn(str('wd_reject'),'btn-outline-danger',function(){modal({title:str('wd_reject_title'),fields:[{name:'reason',label:str('wd_reason_required_field')}]}).then(function(r){if(r){if(!(r.reason||'').trim()){msg(str('wd_reason_required'),'danger');return;}process(w.id,'reject',{reason:r.reason});}});});
    } else if(w.status==='approved'){
      btn(str('wd_markpaid'),'btn-success',function(){modal({title:str('wd_markpaid_title'),fields:[{name:'reference',label:str('wd_payref_optional')}]}).then(function(r){if(r){process(w.id,'pay',{reference:r.reference||''});}});});
      btn(str('wd_reject'),'btn-outline-danger',function(){modal({title:str('wd_reject_title'),fields:[{name:'reason',label:str('wd_reason_required_field')}]}).then(function(r){if(r){if(!(r.reason||'').trim()){msg(str('wd_reason_required'),'danger');return;}process(w.id,'reject',{reason:r.reason});}});});
    }
    return box;
  }

  function renderCards(p){
    $('wd-cards').innerHTML=
      '<div class="wd-card"><div class="l">'+esc(str('wd_card_current'))+'</div><div class="v">'+money(p.current_money)+'</div></div>'+
      '<div class="wd-card"><div class="l">'+esc(str('wd_card_undistributed'))+'</div><div class="v">'+money(p.undistributed_money)+'</div></div>'+
      '<div class="wd-card"><div class="l">'+esc(str('wd_card_teachers'))+'</div><div class="v">'+money(p.teachers_money)+'</div></div>'+
      '<div class="wd-card"><div class="l">'+esc(str('wd_card_platform'))+'</div><div class="v">'+money(p.platform_earnings)+'</div></div>';
  }

  function load(){
    apiGet('get_platform_wallet').then(renderCards).catch(function(e){msg(e.message,'danger');});
    apiGet('list_withdrawals',{status:$('wd-filter').value}).then(function(rows){
      var tb=$('wd-rows');tb.innerHTML='';
      if(!rows.length){tb.innerHTML='<tr><td colspan="6" class="text-muted">'+esc(str('wd_none'))+'</td></tr>';return;}
      rows.forEach(function(w){
        var tr=document.createElement('tr');
        var note=w.status==='rejected'?('<br><small>'+esc(w.reason||'')+'</small>'):(w.status==='paid'&&w.reference?('<br><small>'+strf('w_ref',esc(w.reference))+'</small>'):'');
        tr.innerHTML='<td>'+fmt(w.timecreated)+'</td><td>'+esc(w.teacher_name||('#'+w.teacherid))+'<br><small>'+esc(w.teacher_email||'')+'</small></td>'+
          '<td>'+money(w.amount)+'</td><td>'+esc(w.method)+'<br><small>'+esc(w.account||'')+'</small></td>'+
          '<td><span class="wd-badge s-'+w.status+'">'+esc(wstat(w.status))+'</span>'+note+'</td>';
        var td=document.createElement('td');td.appendChild(actionButtons(w));tr.appendChild(td);
        tb.appendChild(tr);
      });
    }).catch(function(e){msg(e.message,'danger');});
  }

  // Searchable lesson picker for the Flex reversal (replaces the old numeric lesson-id input).
  var lessonPicker=AcademyUI.picker({
    mount:$('wd-rev-lesson'),
    placeholder:str('ui_picker_lesson_ph'),
    labels:{searching:str('ui_picker_searching'),none:str('ui_picker_none'),hint:str('ui_picker_hint')},
    search:function(q){return apiGet('list_reversible_lessons',{query:q});},
    primary:function(l){return '#'+l.id+' — '+(l.subject||'');},
    secondary:function(l){return [l.student_name,l.teacher_name,fmt(l.lesson_time),money(l.flex_value)+' '+str('ui_currency_egp')].filter(function(x){return x;}).join(' • ');}
  });

  $('wd-filter').onchange=load;
  $('wd-refresh').onclick=load;
  $('wd-rev-btn').onclick=function(){
    var lid=lessonPicker.value(), reason=$('wd-rev-reason').value;
    if(!lid){msg(str('wd_enter_lesson'),'danger');return;}
    if(!reason.trim()){msg(str('err_reasonrequired'),'danger');return;}
    apiPost('reverse_flex',{lessonid:lid,reason:reason}).then(function(){msg(str('wd_flex_returned'),'success');lessonPicker.clear();$('wd-rev-reason').value='';load();}).catch(function(e){msg(e.message,'danger');});
  };

  load();
})();
JS
);
echo $OUTPUT->footer();
