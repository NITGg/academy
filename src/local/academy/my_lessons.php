<?php
// Teacher "My Lessons" UI (US-TR-1-2). The logged-in teacher views and manages their lessons via the API.

require('../../config.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');

require_login();

global $DB, $OUTPUT, $CFG, $PAGE, $USER;

// Teachers only — this page manages a teacher's lessons.
if (!\local_academy\teacher_manager::is_teacher($USER->id)) {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/local/academy/my_lessons.php'));
    $PAGE->set_title(get_string('mylessons', 'local_academy'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('notateacher', 'local_academy'), 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

$service = $DB->get_record('external_services', array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
$token = external_generate_token_for_current_user($service)->token;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/academy/my_lessons.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mylessons', 'local_academy'));
$PAGE->set_heading(get_string('mylessons', 'local_academy'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mylessons', 'local_academy'));
echo html_writer::script('window.ACADEMY_ML = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'token'    => $token,
)) . ';');
?>
<style>
#ml-app{max-width:860px}
#ml-toolbar{display:flex;gap:.5rem;align-items:center;margin-bottom:1rem;flex-wrap:wrap}
#ml-toolbar .form-control{max-width:220px}
.ml-card{border:1px solid #dee2e6;border-radius:.5rem;padding:1rem;margin-bottom:.75rem}
.ml-card .ml-top{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem}
.ml-subject{font-weight:600;font-size:1.05rem}
.ml-meta{color:#6c757d;font-size:.9rem;margin-top:.25rem}
.ml-badge{display:inline-block;padding:.15rem .55rem;border-radius:1rem;font-size:.8rem;font-weight:600;white-space:nowrap}
.ml-actions{margin-top:.75rem;display:flex;gap:.4rem;flex-wrap:wrap}
.ml-reschedule{margin-top:.6rem;padding:.5rem .75rem;background:#fff8e1;border:1px solid #ffe082;border-radius:.4rem;font-size:.9rem}
.ml-empty{color:#6c757d;padding:2rem;text-align:center}
.ml-modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;align-items:center;justify-content:center;z-index:1050}
.ml-modal{background:#fff;border-radius:.5rem;padding:1.25rem;max-width:420px;width:90%}
.ml-modal h5{margin-top:0}
.ml-modal .form-group{margin-bottom:.75rem}
.ml-modal-actions{display:flex;justify-content:flex-end;gap:.5rem;margin-top:.5rem}
/* status colours */
.s-pending{background:#fff3cd;color:#856404}
.s-waiting_student,.s-waiting_teacher{background:#cce5ff;color:#004085}
.s-confirmed{background:#d4edda;color:#155724}
.s-in_progress{background:#d1ecf1;color:#0c5460}
.s-completed{background:#e2e3e5;color:#383d41}
.s-student_absent,.s-teacher_absent,.s-rejected,.s-cancelled,.s-cancelled_teacher{background:#f8d7da;color:#721c24}
</style>
<div id="ml-app">
  <div id="ml-msg" class="alert" style="display:none"></div>
  <div id="ml-toolbar">
    <label class="m-0" for="ml-filter">Status</label>
    <select id="ml-filter" class="form-control">
      <option value="">All</option>
      <option value="pending">Pending</option>
      <option value="waiting_student">Waiting for student</option>
      <option value="waiting_teacher">Waiting for teacher</option>
      <option value="confirmed">Confirmed</option>
      <option value="in_progress">In progress</option>
      <option value="completed">Completed</option>
      <option value="student_absent">Student absent</option>
      <option value="teacher_absent">Teacher absent</option>
      <option value="cancelled">Cancelled (student)</option>
      <option value="cancelled_teacher">Cancelled (teacher)</option>
      <option value="rejected">Rejected</option>
    </select>
    <button id="ml-refresh" class="btn btn-outline-secondary">Refresh</button>
  </div>
  <div id="ml-list"></div>
</div>

<div class="ml-modal-bg" id="ml-modal-bg">
  <div class="ml-modal">
    <h5 id="ml-modal-title"></h5>
    <div id="ml-modal-body"></div>
    <div class="ml-modal-actions">
      <button class="btn btn-outline-secondary" id="ml-modal-cancel">Cancel</button>
      <button class="btn btn-primary" id="ml-modal-ok">Confirm</button>
    </div>
  </div>
</div>
<?php
echo html_writer::script(<<<'JS'
(function () {
  var CFG = window.ACADEMY_ML;
  function $(id){return document.getElementById(id);}
  function el(tag,attrs,html){var e=document.createElement(tag);for(var k in (attrs||{})){e.setAttribute(k,attrs[k]);}if(html!=null){e.innerHTML=html;}return e;}
  function msg(t,k){var e=$('ml-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';if(k==='success'){setTimeout(function(){e.style.display='none';},3000);}}
  function parse(r){return r.text().then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error('Session expired — reload the page.');}if(j.status!=='success'){throw new Error(j.error||'Failed');}return j.data;});}

  var STATUS_LABEL={pending:'Pending',waiting_student:'Waiting for student',waiting_teacher:'Waiting for teacher',confirmed:'Confirmed',in_progress:'In progress',completed:'Completed',student_absent:'Student absent',teacher_absent:'Teacher absent',cancelled:'Cancelled (student)',cancelled_teacher:'Cancelled (teacher)',rejected:'Rejected'};
  var ACTION_LABEL={accept:'Accept',reject:'Reject',suggest:'Suggest time',start:'Start',complete:'Complete',report_student_absent:'Student absent',cancel:'Cancel',request_time_update:'Reschedule',respond_time_update:'Respond to reschedule'};

  function fmt(ts){if(!ts){return '—';}var d=new Date(ts*1000);return d.toLocaleString();}

  // GET/POST helpers against the dispatcher.
  function apiGet(fn,params){
    var q=new URLSearchParams(Object.assign({function:fn,token:CFG.token},params||{}));
    return fetch(CFG.endpoint+'?'+q.toString()).then(parse);
  }
  function apiPost(fn,params){
    var body=new URLSearchParams(Object.assign({function:fn,token:CFG.token},params||{}));
    return fetch(CFG.endpoint,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()}).then(parse);
  }

  // ── Simple modal that collects optional fields and resolves with their values ──
  function modal(opts){
    return new Promise(function(resolve){
      $('ml-modal-title').textContent=opts.title||'';
      var body=$('ml-modal-body');body.innerHTML='';
      var inputs={};
      (opts.fields||[]).forEach(function(f){
        var g=el('div',{class:'form-group'});
        g.appendChild(el('label',{},f.label));
        var inp=el('input',{class:'form-control',type:f.type||'text'});
        if(f.value){inp.value=f.value;}
        g.appendChild(inp);body.appendChild(g);inputs[f.name]=inp;
      });
      if(opts.text){body.appendChild(el('p',{class:'text-muted'},opts.text));}
      var bg=$('ml-modal-bg');bg.style.display='flex';
      var ok=$('ml-modal-ok'),cancel=$('ml-modal-cancel');
      $('ml-modal-ok').textContent=opts.okLabel||'Confirm';
      function close(){bg.style.display='none';ok.onclick=null;cancel.onclick=null;}
      ok.onclick=function(){var out={};for(var k in inputs){out[k]=inputs[k].value;}close();resolve(out);};
      cancel.onclick=function(){close();resolve(null);};
    });
  }

  function toUnix(localval){ // datetime-local string -> unix seconds
    if(!localval){return 0;}
    var t=new Date(localval).getTime();return isNaN(t)?0:Math.floor(t/1000);
  }
  // default datetime-local value: tomorrow 09:00 in local time, formatted for the input.
  function tomorrow0900(){
    var d=new Date();d.setDate(d.getDate()+1);d.setHours(9,0,0,0);
    function p(n){return (n<10?'0':'')+n;}
    return d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate())+'T'+p(d.getHours())+':'+p(d.getMinutes());
  }

  function pendingReschedule(lesson){
    return (lesson.proposals||[]).filter(function(p){return p.type==='reschedule'&&p.status==='pending';})[0]||null;
  }

  function doAction(lesson,action){
    var id=lesson.id;
    var run=function(p){return p.then(function(){msg('Done.','success');load();}).catch(function(e){msg(e.message,'danger');});};
    switch(action){
      case 'accept':
        return run(apiPost('teacher_respond_lesson',{lessonid:id,action:'accept'}));
      case 'start':
        return run(apiPost('start_lesson',{lessonid:id}));
      case 'report_student_absent':
        return modal({title:'Report student absent',text:'Confirm the student did not attend? The Flex will be consumed.'}).then(function(r){if(r){return run(apiPost('report_student_absent',{lessonid:id}));}});
      case 'reject':
        return modal({title:'Reject request',fields:[{name:'reject_reason',label:'Reason (optional)'}]}).then(function(r){if(r){return run(apiPost('teacher_respond_lesson',{lessonid:id,action:'reject',reject_reason:r.reject_reason||''}));}});
      case 'complete':
        return modal({title:'Complete lesson',fields:[{name:'note',label:'Note (optional)'}]}).then(function(r){if(r){return run(apiPost('complete_lesson',{lessonid:id,note:r.note||''}));}});
      case 'cancel':
        return modal({title:'Cancel lesson',fields:[{name:'reason',label:'Reason (required)'}],text:'The reserved Flex will be returned to the student.'}).then(function(r){if(r){if(!(r.reason||'').trim()){msg('A reason is required.','danger');return;}return run(apiPost('cancel_lesson_teacher',{lessonid:id,reason:r.reason}));}});
      case 'suggest':
        return modal({title:'Suggest another time',fields:[{name:'t',label:'Suggested date & time',type:'datetime-local',value:tomorrow0900()}]}).then(function(r){if(r){var u=toUnix(r.t);if(!u){msg('Pick a valid time.','danger');return;}return run(apiPost('teacher_respond_lesson',{lessonid:id,action:'suggest',suggested_time:u}));}});
      case 'request_time_update':
        return modal({title:'Request a new time',fields:[{name:'t',label:'New date & time',type:'datetime-local',value:tomorrow0900()}]}).then(function(r){if(r){var u=toUnix(r.t);if(!u){msg('Pick a valid time.','danger');return;}return run(apiPost('request_time_update',{lessonid:id,proposed_time:u}));}});
      case 'respond_time_update_accept':
        return run(apiPost('respond_time_update',{lessonid:id,action:'accept'}));
      case 'respond_time_update_reject':
        return run(apiPost('respond_time_update',{lessonid:id,action:'reject'}));
    }
  }

  function card(lesson){
    var c=el('div',{class:'ml-card'});
    var top=el('div',{class:'ml-top'});
    var left=el('div',{});
    left.appendChild(el('div',{class:'ml-subject'},lesson.subject+' · with '+(lesson.student_name||('student #'+lesson.studentid))));
    var when=lesson.confirmed_time?('Confirmed: '+fmt(lesson.confirmed_time)):('Requested: '+fmt(lesson.requested_time));
    left.appendChild(el('div',{class:'ml-meta'},when+' · '+lesson.duration+' min'));
    if(lesson.note){left.appendChild(el('div',{class:'ml-meta'},'Note: '+escapeHtml(lesson.note)));}
    if(lesson.reject_reason){left.appendChild(el('div',{class:'ml-meta'},'Reject reason: '+escapeHtml(lesson.reject_reason)));}
    if(lesson.cancel_reason){left.appendChild(el('div',{class:'ml-meta'},'Cancel reason: '+escapeHtml(lesson.cancel_reason)));}
    top.appendChild(left);
    top.appendChild(el('span',{class:'ml-badge s-'+lesson.status},STATUS_LABEL[lesson.status]||lesson.status));
    c.appendChild(top);

    var resched=pendingReschedule(lesson);
    if(resched){
      var box=el('div',{class:'ml-reschedule'});
      var who=resched.role==='teacher'?'You':'The student';
      box.innerHTML=who+' requested to move this lesson to <b>'+fmt(resched.proposed_time)+'</b>.';
      c.appendChild(box);
    }

    var actions=el('div',{class:'ml-actions'});
    (lesson.actions||[]).forEach(function(a){
      if(a==='view'){return;}
      if(a==='respond_time_update'){
        // Only the party who did NOT request may respond.
        if(resched && resched.role!=='teacher'){
          actions.appendChild(button('Accept new time','btn-success',function(){doAction(lesson,'respond_time_update_accept');}));
          actions.appendChild(button('Reject new time','btn-outline-danger',function(){doAction(lesson,'respond_time_update_reject');}));
        }
        return;
      }
      var cls=(a==='accept'||a==='start'||a==='complete')?'btn-primary':(a==='reject'||a==='cancel'||a==='report_student_absent'?'btn-outline-danger':'btn-outline-secondary');
      actions.appendChild(button(ACTION_LABEL[a]||a,cls,function(){doAction(lesson,a);}));
    });
    c.appendChild(actions);
    return c;
  }
  function button(label,cls,onclick){var b=el('button',{type:'button',class:'btn btn-sm '+cls},label);b.onclick=onclick;return b;}
  function escapeHtml(s){return (s||'').replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}

  function load(){
    var status=$('ml-filter').value;
    apiGet('get_my_lessons',{role:'teacher',status:status}).then(function(rows){
      var list=$('ml-list');list.innerHTML='';
      if(!rows.length){list.appendChild(el('div',{class:'ml-empty'},'No lessons to show.'));return;}
      rows.forEach(function(l){list.appendChild(card(l));});
    }).catch(function(e){msg(e.message,'danger');});
  }

  $('ml-filter').onchange=load;
  $('ml-refresh').onclick=load;
  load();
})();
JS
);
echo $OUTPUT->footer();
