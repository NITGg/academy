<?php
// Student hub UI for the Academy Flex platform. Mirrors the same APIs the student mobile app uses
// (see docs/academy_plugin_api.md): browse teachers + book lessons (US-LS-1-1), manage my lessons
// (US-ST-2-2 + the lesson lifecycle), and buy / track Flex packages (US-PK-1-1, US-PK-1-2, US-PK-2-1).
// Like my_lessons.php / wallet.php it mints a mobile web-service token and drives /local/academy/api.php
// from vanilla JS, so the web UI and the mobile app exercise exactly the same endpoints.

require('../../config.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');

require_login();

global $DB, $OUTPUT, $CFG, $PAGE, $USER;

$service = $DB->get_record('external_services', array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
$token = external_generate_token_for_current_user($service)->token;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/academy/student.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('studenthub', 'local_academy'));
$PAGE->set_heading(get_string('studenthub', 'local_academy'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('studenthub', 'local_academy'));
echo html_writer::script('window.ACADEMY_ST = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'token'    => $token,
)) . ';');
?>
<style>
#st-app{max-width:920px}
#st-flexbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;background:#eaf3ff;border:1px solid #b6d4fe;border-radius:.5rem;padding:.6rem 1rem;margin-bottom:1rem;flex-wrap:wrap}
#st-flexbar .st-flexval{font-size:1.5rem;font-weight:700;color:#084298}
#st-tabs{display:flex;gap:.4rem;border-bottom:2px solid #dee2e6;margin-bottom:1rem;flex-wrap:wrap}
.st-tab{padding:.5rem 1rem;border:none;background:none;font-weight:600;color:#6c757d;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px}
.st-tab.active{color:#0d6efd;border-bottom-color:#0d6efd}
.st-panel{display:none}
.st-panel.active{display:block}
.st-card{border:1px solid #dee2e6;border-radius:.5rem;padding:1rem;margin-bottom:.75rem}
.st-card .st-top{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem}
.st-title{font-weight:600;font-size:1.05rem}
.st-meta{color:#6c757d;font-size:.9rem;margin-top:.25rem}
.st-badge{display:inline-block;padding:.15rem .55rem;border-radius:1rem;font-size:.8rem;font-weight:600;white-space:nowrap}
.st-actions{margin-top:.75rem;display:flex;gap:.4rem;flex-wrap:wrap}
.st-reschedule{margin-top:.6rem;padding:.5rem .75rem;background:#fff8e1;border:1px solid #ffe082;border-radius:.4rem;font-size:.9rem}
.st-empty{color:#6c757d;padding:2rem;text-align:center}
.st-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:.75rem}
.st-subjects{margin-top:.4rem;display:flex;gap:.3rem;flex-wrap:wrap}
.st-chip{background:#e9ecef;border-radius:1rem;padding:.1rem .55rem;font-size:.8rem}
table.st-table{width:100%;border-collapse:collapse;margin-top:.5rem}
table.st-table th,table.st-table td{border-bottom:1px solid #eee;padding:.45rem .5rem;text-align:left;font-size:.9rem}
.st-toolbar{display:flex;gap:.5rem;align-items:center;margin-bottom:1rem;flex-wrap:wrap}
.st-toolbar .form-control{max-width:240px}
.st-section{margin-top:1.5rem}
/* status colours (shared by lessons + packages + payments) */
.s-pending{background:#fff3cd;color:#856404}
.s-waiting_student,.s-waiting_teacher{background:#cce5ff;color:#004085}
.s-confirmed,.s-active,.s-completed_ok,.s-success{background:#d4edda;color:#155724}
.s-in_progress{background:#d1ecf1;color:#0c5460}
.s-completed,.s-fully_used,.s-expired{background:#e2e3e5;color:#383d41}
.s-student_absent,.s-teacher_absent,.s-rejected,.s-cancelled,.s-cancelled_teacher,.s-failed{background:#f8d7da;color:#721c24}
/* shared modal */
.st-modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;align-items:center;justify-content:center;z-index:1050}
.st-modal{background:#fff;border-radius:.5rem;padding:1.25rem;max-width:460px;width:90%}
.st-modal h5{margin-top:0}
.st-modal .form-group{margin-bottom:.75rem}
.st-modal-actions{display:flex;justify-content:flex-end;gap:.5rem;margin-top:.5rem}
.st-flex-pill{font-weight:700;color:#084298}
</style>
<div id="st-app">
  <div id="st-msg" class="alert" style="display:none"></div>

  <div id="st-flexbar">
    <div><div class="st-meta" style="margin:0">Available Flex credits</div>
      <span class="st-flexval" id="st-flex">—</span></div>
    <div id="st-flexnote" class="st-meta" style="margin:0"></div>
  </div>

  <div id="st-tabs">
    <button class="st-tab active" data-tab="book">Book a lesson</button>
    <button class="st-tab" data-tab="lessons">My lessons</button>
    <button class="st-tab" data-tab="packages">Packages &amp; Flex</button>
    <button class="st-tab" data-tab="subavailable">Available subscriptions</button>
    <button class="st-tab" data-tab="mysubs">My subscriptions</button>
  </div>

  <!-- ── Tab 1: Book a lesson ── -->
  <div class="st-panel active" id="panel-book">
    <div class="st-toolbar">
      <input id="st-teacher-search" class="form-control" placeholder="Search by subject…">
      <button id="st-teacher-refresh" class="btn btn-outline-secondary">Search</button>
    </div>
    <div id="st-teachers" class="st-grid"></div>
  </div>

  <!-- ── Tab 2: My lessons ── -->
  <div class="st-panel" id="panel-lessons">
    <div class="st-toolbar">
      <label class="m-0" for="st-filter">Status</label>
      <select id="st-filter" class="form-control">
        <option value="">All</option>
        <option value="pending">Pending</option>
        <option value="waiting_student">Waiting for me</option>
        <option value="waiting_teacher">Waiting for teacher</option>
        <option value="confirmed">Confirmed</option>
        <option value="in_progress">In progress</option>
        <option value="completed">Completed</option>
        <option value="student_absent">I was absent</option>
        <option value="teacher_absent">Teacher absent</option>
        <option value="cancelled">Cancelled</option>
        <option value="cancelled_teacher">Cancelled (teacher)</option>
        <option value="rejected">Rejected</option>
      </select>
      <button id="st-lessons-refresh" class="btn btn-outline-secondary">Refresh</button>
    </div>
    <div id="st-lessons"></div>
  </div>

  <!-- ── Tab 3: Packages & Flex ── -->
  <div class="st-panel" id="panel-packages">
    <h5>Available packages</h5>
    <div id="st-available" class="st-grid"></div>

    <div class="st-section">
      <h5>My packages</h5>
      <table class="st-table">
        <thead><tr><th>Package</th><th>Flex (used / total)</th><th>Status</th><th>Activated</th><th>Expires</th></tr></thead>
        <tbody id="st-mypackages"></tbody>
      </table>
    </div>

    <div class="st-section">
      <h5>Payment history</h5>
      <table class="st-table">
        <thead><tr><th>Date</th><th>Package</th><th>Amount</th><th>Method</th><th>Transaction</th><th>Status</th></tr></thead>
        <tbody id="st-payments"></tbody>
      </table>
    </div>

    <div class="st-section">
      <h5>Flex history</h5>
      <table class="st-table">
        <thead><tr><th>Date</th><th>Type</th><th>Change</th><th>Balance</th><th>Lesson</th><th>Note</th></tr></thead>
        <tbody id="st-flexhistory"></tbody>
      </table>
    </div>
  </div>

  <!-- ── Tab 4: Available subscriptions ── -->
  <div class="st-panel" id="panel-subavailable">
    <h5>Available subscriptions</h5>
    <div id="st-subavailable" class="st-grid"></div>
  </div>

  <!-- ── Tab 5: My subscriptions ── -->
  <div class="st-panel" id="panel-mysubs">
    <h5>My subscriptions</h5>
    <table class="st-table">
      <thead><tr><th>Subscription</th><th>Status</th><th>Activated</th><th>Expires</th><th>Days left</th><th>Courses</th></tr></thead>
      <tbody id="st-mysubs"></tbody>
    </table>

    <div class="st-section">
      <h5>Subscription payments</h5>
      <table class="st-table">
        <thead><tr><th>Date</th><th>Subscription</th><th>Amount</th><th>Method</th><th>Transaction</th><th>Status</th></tr></thead>
        <tbody id="st-subpayments"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- shared modal -->
<div class="st-modal-bg" id="st-modal-bg">
  <div class="st-modal">
    <h5 id="st-modal-title"></h5>
    <div id="st-modal-body"></div>
    <div class="st-modal-actions">
      <button class="btn btn-outline-secondary" id="st-modal-cancel">Cancel</button>
      <button class="btn btn-primary" id="st-modal-ok">Confirm</button>
    </div>
  </div>
</div>
<?php
echo html_writer::script(<<<'JS'
(function () {
  var CFG = window.ACADEMY_ST;
  function $(id){return document.getElementById(id);}
  function el(tag,attrs,html){var e=document.createElement(tag);for(var k in (attrs||{})){e.setAttribute(k,attrs[k]);}if(html!=null){e.innerHTML=html;}return e;}
  function msg(t,k){var e=$('st-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';window.scrollTo(0,0);if(k==='success'){setTimeout(function(){e.style.display='none';},3500);}}
  function parse(r){return r.text().then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error('Session expired — reload the page.');}if(j.status!=='success'){throw new Error(j.error||'Request failed');}return j.data;});}
  function esc(s){return (s==null?'':String(s)).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
  function fmt(ts){if(!ts){return '—';}var d=new Date(ts*1000);return d.toLocaleString();}
  function money(n){return Number(n||0).toFixed(2);}

  function apiGet(fn,params){
    var q=new URLSearchParams(Object.assign({function:fn,token:CFG.token},params||{}));
    return fetch(CFG.endpoint+'?'+q.toString()).then(parse);
  }
  function apiPost(fn,params){
    var body=new URLSearchParams(Object.assign({function:fn,token:CFG.token},params||{}));
    return fetch(CFG.endpoint,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()}).then(parse);
  }

  // datetime-local <-> unix seconds
  function toUnix(localval){if(!localval){return 0;}var t=new Date(localval).getTime();return isNaN(t)?0:Math.floor(t/1000);}
  function tomorrow0900(){var d=new Date();d.setDate(d.getDate()+1);d.setHours(9,0,0,0);function p(n){return (n<10?'0':'')+n;}return d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate())+'T'+p(d.getHours())+':'+p(d.getMinutes());}

  // ── Shared modal: collects fields, resolves with their values (or null on cancel) ──
  function modal(opts){
    return new Promise(function(resolve){
      $('st-modal-title').textContent=opts.title||'';
      var body=$('st-modal-body');body.innerHTML='';
      var inputs={};
      if(opts.text){body.appendChild(el('p',{class:'text-muted'},esc(opts.text)));}
      (opts.fields||[]).forEach(function(f){
        var g=el('div',{class:'form-group'});
        g.appendChild(el('label',{},f.label));
        var inp;
        if(f.type==='select'){
          inp=el('select',{class:'form-control'});
          (f.options||[]).forEach(function(o){var op=el('option',{value:o.value},esc(o.label));inp.appendChild(op);});
        }else if(f.type==='textarea'){
          inp=el('textarea',{class:'form-control',rows:'2'});
        }else{
          inp=el('input',{class:'form-control',type:f.type||'text'});
        }
        if(f.value){inp.value=f.value;}
        if(f.placeholder){inp.setAttribute('placeholder',f.placeholder);}
        g.appendChild(inp);body.appendChild(g);inputs[f.name]=inp;
      });
      var bg=$('st-modal-bg');bg.style.display='flex';
      var ok=$('st-modal-ok'),cancel=$('st-modal-cancel');
      ok.textContent=opts.okLabel||'Confirm';
      function close(){bg.style.display='none';ok.onclick=null;cancel.onclick=null;}
      ok.onclick=function(){var out={};for(var k in inputs){out[k]=inputs[k].value;}close();resolve(out);};
      cancel.onclick=function(){close();resolve(null);};
    });
  }

  function button(label,cls,onclick,disabled){
    var b=el('button',{type:'button',class:'btn btn-sm '+cls},label);
    if(disabled){b.disabled=true;}else{b.onclick=onclick;}
    return b;
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Flex balance banner (sum of remaining Flex across active packages)
  // ──────────────────────────────────────────────────────────────────────────
  function refreshFlex(){
    return apiGet('get_my_packages').then(function(pkgs){
      var bal=0,active=false;
      (pkgs||[]).forEach(function(p){if(p.status==='active'){bal+=Number(p.remaining_flex||0);active=true;}});
      $('st-flex').textContent=bal;
      $('st-flexnote').innerHTML = active
        ? ('You can book up to <b>'+bal+'</b> lesson'+(bal===1?'':'s')+'.')
        : 'No active package — buy one in the <b>Packages</b> tab to start booking.';
      return bal;
    }).catch(function(){/* balance is best-effort */});
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Tab 1: Book a lesson
  // ──────────────────────────────────────────────────────────────────────────
  function teacherCard(t){
    var c=el('div',{class:'st-card'});
    c.appendChild(el('div',{class:'st-title'},esc(t.fullname||('Teacher #'+t.userid))));
    if(t.headline){c.appendChild(el('div',{class:'st-meta'},esc(t.headline)));}
    var subs=el('div',{class:'st-subjects'});
    (t.subjects||[]).forEach(function(s){subs.appendChild(el('span',{class:'st-chip'},esc(s.subject)));});
    c.appendChild(subs);
    var actions=el('div',{class:'st-actions'});
    actions.appendChild(button('Request a lesson','btn-primary',function(){bookWith(t);}));
    c.appendChild(actions);
    return c;
  }

  function bookWith(teacher){
    var subs=(teacher.subjects||[]).map(function(s){return {value:s.subject,label:s.subject};});
    if(!subs.length){msg('This teacher has not listed any subjects yet.','danger');return;}
    modal({
      title:'Request a lesson with '+(teacher.fullname||('teacher #'+teacher.userid)),
      okLabel:'Send request',
      fields:[
        {name:'subject',label:'Subject',type:'select',options:subs},
        {name:'t',label:'Preferred date & time',type:'datetime-local',value:tomorrow0900()},
        {name:'note',label:'Note to the teacher (required)',type:'textarea',placeholder:'What do you need help with?'}
      ]
    }).then(function(r){
      if(!r){return;}
      var u=toUnix(r.t);
      if(!u){msg('Please pick a valid date and time.','danger');return;}
      if(!(r.note||'').trim()){msg('A note is required to request a lesson.','danger');return;}
      apiPost('request_lesson',{teacherid:teacher.userid,subject:r.subject,requested_time:u,note:r.note.trim()})
        .then(function(){msg('Lesson requested. Track it in “My lessons”.','success');switchTab('lessons');loadLessons();})
        .catch(function(e){msg(e.message,'danger');});
    });
  }

  function loadTeachers(){
    var subject=$('st-teacher-search').value.trim();
    apiGet('browse_teachers',subject?{subject:subject}:null).then(function(rows){
      var box=$('st-teachers');box.innerHTML='';
      if(!rows.length){box.appendChild(el('div',{class:'st-empty'},'No teachers found.'));return;}
      rows.forEach(function(t){box.appendChild(teacherCard(t));});
    }).catch(function(e){msg(e.message,'danger');});
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Tab 2: My lessons
  // ──────────────────────────────────────────────────────────────────────────
  var STATUS_LABEL={pending:'Pending teacher response',waiting_student:'Waiting for you',waiting_teacher:'Waiting for teacher',confirmed:'Confirmed',in_progress:'In progress',completed:'Completed',student_absent:'You were absent',teacher_absent:'Teacher absent',cancelled:'Cancelled',cancelled_teacher:'Cancelled by teacher',rejected:'Rejected'};

  function pendingReschedule(lesson){
    return (lesson.proposals||[]).filter(function(p){return p.type==='reschedule'&&p.status==='pending';})[0]||null;
  }

  function run(p){return p.then(function(){msg('Done.','success');loadLessons();refreshFlex();}).catch(function(e){msg(e.message,'danger');});}

  function doAction(lesson,action){
    var id=lesson.id;
    switch(action){
      case 'accept':
        return run(apiPost('student_respond_lesson',{lessonid:id,action:'accept'}));
      case 'reject':
        return modal({title:'Reject the suggested time',fields:[{name:'reject_reason',label:'Reason (optional)'}]}).then(function(r){if(r){return run(apiPost('student_respond_lesson',{lessonid:id,action:'reject',reject_reason:r.reject_reason||''}));}});
      case 'suggest':
        return modal({title:'Suggest another time',fields:[{name:'t',label:'Suggested date & time',type:'datetime-local',value:tomorrow0900()}]}).then(function(r){if(r){var u=toUnix(r.t);if(!u){msg('Pick a valid time.','danger');return;}return run(apiPost('student_respond_lesson',{lessonid:id,action:'suggest',suggested_time:u}));}});
      case 'cancel_request':
        return modal({title:'Withdraw request',text:'Withdraw this lesson request? No Flex has been reserved yet.',fields:[{name:'reason',label:'Reason (optional)'}]}).then(function(r){if(r){return run(apiPost('cancel_lesson_request',{lessonid:id,reason:r.reason||''}));}});
      case 'cancel':
        return modal({title:'Cancel lesson',text:'Cancelling before the deadline returns your Flex; cancelling late consumes it.',fields:[{name:'reason',label:'Reason (optional)'}]}).then(function(r){if(r){return run(apiPost('cancel_lesson_student',{lessonid:id,reason:r.reason||''}));}});
      case 'report_teacher_absent':
        return modal({title:'Report teacher absent',text:'Confirm the teacher did not show up? Your Flex will be returned.'}).then(function(r){if(r){return run(apiPost('report_teacher_absent',{lessonid:id}));}});
      case 'request_time_update':
        return modal({title:'Request a new time',fields:[{name:'t',label:'New date & time',type:'datetime-local',value:tomorrow0900()}]}).then(function(r){if(r){var u=toUnix(r.t);if(!u){msg('Pick a valid time.','danger');return;}return run(apiPost('request_time_update',{lessonid:id,proposed_time:u}));}});
      case 'respond_time_update_accept':
        return run(apiPost('respond_time_update',{lessonid:id,action:'accept'}));
      case 'respond_time_update_reject':
        return run(apiPost('respond_time_update',{lessonid:id,action:'reject'}));
      case 'join':
        if(!lesson.join_url){msg('The meeting room is not ready yet.','danger');return;}
        window.open(lesson.join_url,'_blank');
        return;
    }
  }

  var ACTION_LABEL={accept:'Accept',reject:'Reject',suggest:'Suggest time',cancel_request:'Withdraw request',cancel:'Cancel lesson',report_teacher_absent:'Report teacher absent',request_time_update:'Reschedule',join:'Join lesson'};

  function lessonCard(lesson){
    var c=el('div',{class:'st-card'});
    var top=el('div',{class:'st-top'});
    var left=el('div',{});
    left.appendChild(el('div',{class:'st-title'},esc(lesson.subject)+' · with '+esc(lesson.teacher_name||('teacher #'+lesson.teacherid))));
    var when=lesson.confirmed_time?('Confirmed: '+fmt(lesson.confirmed_time)):('Requested: '+fmt(lesson.requested_time));
    left.appendChild(el('div',{class:'st-meta'},when+' · '+lesson.duration+' min'));
    if(lesson.note){left.appendChild(el('div',{class:'st-meta'},'Your note: '+esc(lesson.note)));}
    if(lesson.reject_reason){left.appendChild(el('div',{class:'st-meta'},'Reject reason: '+esc(lesson.reject_reason)));}
    if(lesson.cancel_reason){left.appendChild(el('div',{class:'st-meta'},'Cancel reason: '+esc(lesson.cancel_reason)));}
    if(lesson.flex_state&&lesson.flex_state!=='none'){left.appendChild(el('div',{class:'st-meta'},'Flex: '+esc(lesson.flex_state)));}
    top.appendChild(left);
    top.appendChild(el('span',{class:'st-badge s-'+lesson.status},STATUS_LABEL[lesson.status]||lesson.status));
    c.appendChild(top);

    // Pending reschedule banner.
    var resched=pendingReschedule(lesson);
    if(resched){
      var box=el('div',{class:'st-reschedule'});
      var who=resched.role==='student'?'You':'The teacher';
      box.innerHTML=who+' requested to move this lesson to <b>'+fmt(resched.proposed_time)+'</b>.';
      c.appendChild(box);
    }

    var actions=el('div',{class:'st-actions'});
    (lesson.actions||[]).forEach(function(a){
      if(a==='view'){return;}
      if(a==='respond_time_update'){
        // Only the party who did NOT propose may respond.
        if(resched && resched.role!=='student'){
          actions.appendChild(button('Accept new time','btn-success',function(){doAction(lesson,'respond_time_update_accept');}));
          actions.appendChild(button('Reject new time','btn-outline-danger',function(){doAction(lesson,'respond_time_update_reject');}));
        }
        return;
      }
      var cls=(a==='join')?'btn-success':((a==='accept')?'btn-primary':((a==='cancel'||a==='reject'||a==='cancel_request'||a==='report_teacher_absent')?'btn-outline-danger':'btn-outline-secondary'));
      actions.appendChild(button(ACTION_LABEL[a]||a,cls,function(){doAction(lesson,a);}));
    });
    c.appendChild(actions);
    return c;
  }

  function loadLessons(){
    var status=$('st-filter').value;
    apiGet('get_my_lessons',{role:'student',status:status}).then(function(rows){
      var list=$('st-lessons');list.innerHTML='';
      if(!rows.length){list.appendChild(el('div',{class:'st-empty'},'No lessons yet — book one from the “Book a lesson” tab.'));return;}
      rows.forEach(function(l){list.appendChild(lessonCard(l));});
    }).catch(function(e){msg(e.message,'danger');});
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Tab 3: Packages & Flex
  // ──────────────────────────────────────────────────────────────────────────
  function packageCard(p,hasActive){
    var c=el('div',{class:'st-card'});
    c.appendChild(el('div',{class:'st-title'},esc(p.name)));
    c.appendChild(el('div',{class:'st-price',style:'font-size:1.3rem;font-weight:700;color:#084298'},money(p.price)+' EGP'));
    var meta=p.flex_count+' Flex';
    meta += (Number(p.expiration_days)>0) ? (' · valid '+p.expiration_days+' days') : ' · never expires';
    c.appendChild(el('div',{class:'st-meta'},meta));
    if(p.description){c.appendChild(el('div',{class:'st-meta'},esc(p.description)));}
    if(hasActive){c.appendChild(el('div',{class:'st-meta',style:'color:#856404;margin-top:.4rem'},'You already have an active package.'));}
    var actions=el('div',{class:'st-actions'});
    actions.appendChild(button('Buy package','btn-primary',function(){buyPackage(p);},hasActive));
    c.appendChild(actions);
    return c;
  }

  function buyPackage(p){
    modal({
      title:'Buy “'+p.name+'”',
      text:'You will get '+p.flex_count+' Flex for '+money(p.price)+' via Kashier secure checkout.',
      okLabel:'Proceed to Payment'
    }).then(function(r){
      if(r === null){return;}
      apiPost('create_package_checkout',{packageid:p.id})
        .then(function(d){ window.location.href = d.checkout_url; })
        .catch(function(e){msg(e.message,'danger');});
    });
  }

  var PKG_STATUS={active:'Active',fully_used:'Fully used',expired:'Expired',cancelled:'Cancelled',pending:'Pending'};
  var PAY_STATUS={success:'Completed',completed:'Completed',pending:'Pending',failed:'Failed',refunded:'Refunded'};
  var FLEX_TYPE={reserve:'Reserved',consume:'Consumed',return:'Returned',purchase:'Purchased',assign:'Assigned',expire:'Expired',adjust:'Adjusted'};

  function loadPackages(){
    Promise.all([apiGet('get_available_packages'), apiGet('get_my_packages')]).then(function(results){
      var rows=results[0], myrows=results[1];
      var hasActive=myrows.some(function(p){return p.status==='active';});

      var box=$('st-available');box.innerHTML='';
      if(!rows.length){box.appendChild(el('div',{class:'st-empty'},'No packages available right now.'));}
      else{rows.forEach(function(p){box.appendChild(packageCard(p,hasActive));});}

      var tb=$('st-mypackages');tb.innerHTML='';
      if(!myrows.length){tb.innerHTML='<tr><td colspan="5" class="text-muted">No packages yet.</td></tr>';return;}
      myrows.forEach(function(p){
        tb.innerHTML+='<tr><td>'+esc(p.name)+'</td>'+
          '<td><span class="st-flex-pill">'+p.remaining_flex+'</span> left ('+p.used_flex+' / '+p.total_flex+')</td>'+
          '<td><span class="st-badge s-'+p.status+'">'+(PKG_STATUS[p.status]||p.status)+'</span></td>'+
          '<td>'+fmt(p.timeactivated)+'</td>'+
          '<td>'+(Number(p.expires_at)>0?fmt(p.expires_at):'Never')+'</td></tr>';
      });
    }).catch(function(e){msg(e.message,'danger');});

    apiGet('get_payment_history').then(function(rows){
      var tb=$('st-payments');tb.innerHTML='';
      if(!rows.length){tb.innerHTML='<tr><td colspan="6" class="text-muted">No payments yet.</td></tr>';return;}
      rows.forEach(function(x){
        tb.innerHTML+='<tr><td>'+fmt(x.timecreated)+'</td><td>'+esc(x.name)+'</td><td>'+money(x.amount)+'</td>'+
          '<td>'+esc(x.method)+'</td><td>'+esc(x.transaction_no)+'</td>'+
          '<td><span class="st-badge s-'+x.status+'">'+(PAY_STATUS[x.status]||x.status)+'</span></td></tr>';
      });
    }).catch(function(e){msg(e.message,'danger');});

    apiGet('get_flex_history').then(function(rows){
      var tb=$('st-flexhistory');tb.innerHTML='';
      if(!rows.length){tb.innerHTML='<tr><td colspan="6" class="text-muted">No Flex activity yet.</td></tr>';return;}
      rows.forEach(function(x){
        var sign=x.amount>0?('+'+x.amount):String(x.amount);
        tb.innerHTML+='<tr><td>'+fmt(x.timecreated)+'</td>'+
          '<td><span class="st-badge s-'+(x.type==='consume'?'rejected':(x.type==='reserve'?'pending':'active'))+'">'+(FLEX_TYPE[x.type]||x.type)+'</span></td>'+
          '<td>'+sign+'</td><td>'+x.balance_after+'</td>'+
          '<td>'+(x.lessonid?('#'+x.lessonid):'—')+'</td><td>'+esc(x.reason)+'</td></tr>';
      });
    }).catch(function(e){msg(e.message,'danger');});
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Tab 4: Subscriptions (US-SB-1-1, US-SB-1-2, US-SB-2-1)
  // ──────────────────────────────────────────────────────────────────────────
  var SUB_STATUS={active:'Active',expired:'Expired',cancelled:'Cancelled',pending:'Pending',payment_failed:'Payment failed'};

  function courseChips(courses){
    var subs=el('div',{class:'st-subjects'});
    if(!courses||!courses.length){subs.appendChild(el('span',{class:'st-meta'},'—'));return subs;}
    courses.forEach(function(c){subs.appendChild(el('span',{class:'st-chip'},esc(c.fullname)));});
    return subs;
  }

  function subCard(s,hasActive){
    var c=el('div',{class:'st-card'});
    c.appendChild(el('div',{class:'st-title'},esc(s.name)));
    c.appendChild(el('div',{class:'st-price',style:'font-size:1.3rem;font-weight:700;color:#084298'},money(s.price)+' EGP'));
    c.appendChild(el('div',{class:'st-meta'},s.duration_days+' days'));
    if(s.description){c.appendChild(el('div',{class:'st-meta'},esc(s.description)));}
    c.appendChild(el('div',{class:'st-meta',style:'margin-top:.4rem'},'Courses:'));
    c.appendChild(courseChips(s.courses));
    if(hasActive){c.appendChild(el('div',{class:'st-meta',style:'color:#856404;margin-top:.4rem'},'You already have an active subscription.'));}
    var actions=el('div',{class:'st-actions'});
    actions.appendChild(button('Buy subscription','btn-primary',function(){buySubscription(s);},hasActive));
    c.appendChild(actions);
    return c;
  }

  function buySubscription(s){
    modal({
      title:'Buy “'+s.name+'”',
      text:'You will get '+s.duration_days+' days of course access for '+money(s.price)+' via Kashier secure checkout.',
      okLabel:'Proceed to Payment'
    }).then(function(r){
      if(r === null){return;}
      apiPost('create_subscription_checkout',{subscriptionid:s.id})
        .then(function(d){ window.location.href = d.checkout_url; })
        .catch(function(e){msg(e.message,'danger');});
    });
  }

  function loadSubscriptions(){
    Promise.all([apiGet('get_available_subscriptions'), apiGet('get_my_subscriptions')]).then(function(results){
      var rows=results[0], myrows=results[1];
      var hasActive=myrows.some(function(s){return s.status==='active';});

      var box=$('st-subavailable');box.innerHTML='';
      if(!rows.length){box.appendChild(el('div',{class:'st-empty'},'No subscriptions available right now.'));}
      else{rows.forEach(function(s){box.appendChild(subCard(s,hasActive));});}

      var tb=$('st-mysubs');tb.innerHTML='';
      if(!myrows.length){tb.innerHTML='<tr><td colspan="6" class="text-muted">No subscriptions yet.</td></tr>';return;}
      myrows.forEach(function(s){
        var courses=(s.courses||[]).map(function(c){return esc(c.fullname);}).join(', ')||'—';
        tb.innerHTML+='<tr><td>'+esc(s.name)+'</td>'+
          '<td><span class="st-badge s-'+s.status+'">'+(SUB_STATUS[s.status]||s.status)+'</span></td>'+
          '<td>'+fmt(s.timeactivated)+'</td>'+
          '<td>'+(Number(s.expires_at)>0?fmt(s.expires_at):'—')+'</td>'+
          '<td>'+(s.status==='active'?s.remaining_days:'—')+'</td>'+
          '<td>'+courses+'</td></tr>';
      });
    }).catch(function(e){msg(e.message,'danger');});

    apiGet('get_subscription_payment_history').then(function(rows){
      var tb=$('st-subpayments');tb.innerHTML='';
      if(!rows.length){tb.innerHTML='<tr><td colspan="6" class="text-muted">No payments yet.</td></tr>';return;}
      rows.forEach(function(x){
        tb.innerHTML+='<tr><td>'+fmt(x.timecreated)+'</td><td>'+esc(x.name)+'</td><td>'+money(x.amount)+'</td>'+
          '<td>'+esc(x.method)+'</td><td>'+esc(x.transaction_no)+'</td>'+
          '<td><span class="st-badge s-'+x.status+'">'+(PAY_STATUS[x.status]||x.status)+'</span></td></tr>';
      });
    }).catch(function(e){msg(e.message,'danger');});
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Tabs + init
  // ──────────────────────────────────────────────────────────────────────────
  var loaded={book:false,lessons:false,packages:false,subscriptions:false};
  function switchTab(name){
    document.querySelectorAll('.st-tab').forEach(function(b){b.classList.toggle('active',b.getAttribute('data-tab')===name);});
    document.querySelectorAll('.st-panel').forEach(function(p){p.classList.toggle('active',p.id==='panel-'+name);});
    if(name==='book' && !loaded.book){loaded.book=true;loadTeachers();}
    if(name==='lessons' && !loaded.lessons){loaded.lessons=true;loadLessons();}
    if(name==='packages' && !loaded.packages){loaded.packages=true;loadPackages();}
    if((name==='subavailable'||name==='mysubs') && !loaded.subscriptions){loaded.subscriptions=true;loadSubscriptions();}
  }
  document.querySelectorAll('.st-tab').forEach(function(b){b.onclick=function(){switchTab(b.getAttribute('data-tab'));};});

  $('st-teacher-refresh').onclick=loadTeachers;
  $('st-teacher-search').addEventListener('keydown',function(e){if(e.key==='Enter'){loadTeachers();}});
  $('st-filter').onchange=loadLessons;
  $('st-lessons-refresh').onclick=loadLessons;

  // First paint.
  refreshFlex();
  loaded.book=true;
  loadTeachers();
})();
JS
);
echo $OUTPUT->footer();
