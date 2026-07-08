<?php
// Admin UI: Flex platform reports (US-AD-3-1..3-4) with CSV export.

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/academy/lib.php'); // local_academy_string_map()

admin_externalpage_setup('local_academy_reports');
require_capability('local/academy:manageplatform', context_system::instance());

global $DB, $OUTPUT, $CFG, $PAGE;

$service = $DB->get_record('external_services', array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
$token = external_generate_token_for_current_user($service)->token;

$PAGE->set_title(get_string('reports', 'local_academy'));
$PAGE->set_heading(get_string('reports', 'local_academy'));

// Shared UI helpers (AcademyUI.paginate) — inhead so it is ready before the page's inline script runs.
$PAGE->requires->js(new moodle_url('/local/academy/ui.js'), true);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reports', 'local_academy'));
// Localised strings: server-rendered HTML reads $STR['key']; the JS reads window.ACADEMY_STR.
$STR = local_academy_string_map(array(
    'rp_tab_lessons', 'rp_tab_platform', 'rp_tab_packages', 'rp_tab_studentflex', 'rp_tab_useractivity',
    'rp_f_userid', 'rp_f_email', 'rp_ua_registered', 'rp_ua_lastlogin', 'rp_ua_status', 'rp_ua_roles',
    'rp_ua_subs', 'rp_ua_memberships', 'rp_ua_courses', 'rp_ua_actions', 'rp_ua_none', 'rp_enter_user',
    'rp_f_status', 'rp_f_teacherid', 'rp_f_studentid', 'rp_f_from', 'rp_f_to', 'rp_f_earnstatus',
    'rp_f_source', 'rp_f_studentid_req', 'rp_run', 'ui_export_csv',
    'rp_c_id', 'rp_c_student', 'rp_c_teacher', 'rp_c_subject', 'rp_c_status', 'rp_c_confirmed',
    'rp_c_flex', 'rp_c_lesson', 'rp_c_date', 'rp_c_flexvalue', 'rp_c_platpct', 'rp_c_platform',
    'rp_c_package', 'rp_c_source', 'rp_c_price', 'rp_c_rem', 'rp_c_resv', 'rp_c_used', 'rp_c_type',
    'rp_c_amount', 'rp_c_before', 'rp_c_after', 'rp_c_by', 'rp_c_reason', 'rp_c_timeline',
    'rp_s_available', 'rp_s_reserved', 'rp_s_consumed', 'rp_s_package', 'rp_s_expires',
    'rp_timeline_title', 'rp_close', 'rp_tl_num', 'rp_tl_action', 'rp_tl_by', 'rp_tl_role',
    'rp_tl_time', 'rp_tl_title_full', 'rp_tl_joinedroom', 'rp_tl_started', 'rp_tl_ended', 'rp_tl_none',
    'rp_no_data', 'rp_enter_student', 'rp_enter_student_run',
    'rp_sum_total', 'rp_sum_completed', 'rp_sum_student_absent', 'rp_sum_teacher_absent',
    'rp_sum_attendance_rate', 'rp_sum_total_platform_earnings', 'rp_sum_total_teacher_earnings',
    'rp_sum_total_consumed_value', 'rp_sum_completed_lessons', 'rp_sum_total_purchases',
    'rp_sum_total_sales_amount', 'rp_sum_online_count', 'rp_sum_assigned_count',
    'rp_sum_total_flex_added', 'rp_sum_total_flex_consumed', 'rp_sum_total_flex_returned',
    'rp_sum_reversals',
    'rp_act_requested', 'rp_act_teacher_accepted', 'rp_act_teacher_rejected', 'rp_act_teacher_suggested',
    'rp_act_student_accepted', 'rp_act_student_rejected', 'rp_act_student_suggested', 'rp_act_started',
    'rp_act_teacher_joined', 'rp_act_student_joined', 'rp_act_completed', 'rp_act_student_absent_reported',
    'rp_act_teacher_absent_reported', 'rp_act_request_cancelled', 'rp_act_cancelled_by_student',
    'rp_act_cancelled_by_teacher', 'rp_act_time_update_requested', 'rp_act_time_update_accepted',
    'rp_act_time_update_rejected',
    'err_sessionexpired', 'err_requestfailed', 'ui_pager_info',
));
echo html_writer::script('window.ACADEMY_RP = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'export'   => $CFG->wwwroot . '/local/academy/export.php',
    'token'    => $token,
    'lang'     => optional_param('lang', current_language(), PARAM_LANG),
)) . ';');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');
?>
<style>
#rp-app{max-width:1040px}
#rp-tabs{display:flex;gap:.25rem;border-bottom:1px solid #dee2e6;margin-bottom:1rem;flex-wrap:wrap}
#rp-tabs button{border:none;background:none;padding:.5rem .9rem;border-bottom:3px solid transparent;cursor:pointer}
#rp-tabs button.active{border-bottom-color:#0f6cbf;font-weight:600;color:#0f6cbf}
#rp-filters{display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:.75rem}
#rp-filters .fg{display:flex;flex-direction:column;font-size:.82rem;color:#6c757d}
#rp-filters .form-control{max-width:180px}
#rp-summary{display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:.75rem}
.rp-chip{border:1px solid #dee2e6;border-radius:.5rem;padding:.4rem .7rem;font-size:.85rem}
.rp-chip b{display:block;font-size:1.1rem}
table.rp-table{width:100%;border-collapse:collapse}
table.rp-table th,table.rp-table td{border-bottom:1px solid #eee;padding:.4rem .5rem;text-align:left;font-size:.86rem}
.rp-badge{display:inline-block;padding:.05rem .45rem;border-radius:1rem;font-size:.76rem;font-weight:600;background:#eef}
</style>
<div id="rp-app">
  <div id="rp-msg" class="alert" style="display:none"></div>
  <div id="rp-tabs">
    <button data-tab="lessons" class="active"><?php echo $STR['rp_tab_lessons']; ?></button>
    <button data-tab="platform_earnings"><?php echo $STR['rp_tab_platform']; ?></button>
    <button data-tab="packages"><?php echo $STR['rp_tab_packages']; ?></button>
    <button data-tab="student_flex"><?php echo $STR['rp_tab_studentflex']; ?></button>
    <button data-tab="user_activity"><?php echo $STR['rp_tab_useractivity']; ?></button>
  </div>
  <div id="rp-filters"></div>
  <div id="rp-summary"></div>
  <div id="rp-generic"><div style="overflow-x:auto"><table class="rp-table"><thead id="rp-head"></thead><tbody id="rp-body"></tbody></table></div><div id="rp-body-pager" class="acad-pager"></div></div>
  <div id="rp-useractivity" style="display:none"></div>
  <div id="rp-timeline" style="display:none;margin-top:1rem;border:1px solid #dee2e6;border-radius:.5rem;padding:.75rem">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
      <strong id="rp-tl-title"><?php echo $STR['rp_timeline_title']; ?></strong>
      <button id="rp-tl-close" class="btn btn-sm btn-outline-secondary"><?php echo $STR['rp_close']; ?></button>
    </div>
    <div id="rp-tl-meta" style="margin-bottom:.6rem;display:flex;gap:.5rem;flex-wrap:wrap"></div>
    <table class="rp-table"><thead><tr><th><?php echo $STR['rp_tl_num']; ?></th><th><?php echo $STR['rp_tl_action']; ?></th><th><?php echo $STR['rp_tl_by']; ?></th><th><?php echo $STR['rp_tl_role']; ?></th><th><?php echo $STR['rp_tl_time']; ?></th></tr></thead><tbody id="rp-tl-body"></tbody></table>
  </div>
</div>
<?php
echo html_writer::script(<<<'JS'
(function () {
  var CFG = window.ACADEMY_RP;
  var STR = window.ACADEMY_STR || {};
  function str(k){return (k in STR)?STR[k]:k;}
  function strf(k,params){var s=str(k);if(params==null){return s;}if(typeof params!=='object'){return s.replace(/\{\$a\}/g,params);}return s.replace(/\{\$a->(\w+)\}/g,function(m,name){return (name in params)?params[name]:m;});}
  function $(id){return document.getElementById(id);}
  function msg(t,k){var e=$('rp-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display=t?'block':'none';}
  function parse(r){return r.text().then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error(str('err_sessionexpired'));}if(j.status!=='success'){throw new Error(j.error||str('err_requestfailed'));}return j.data;});}
  function money(n){return Number(n||0).toFixed(2);}
  function fmt(ts){return ts?new Date(ts*1000).toLocaleString():'—';}
  function esc(s){return (s==null?'':String(s)).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
  function apiGet(fn,p){var base={function:fn,token:CFG.token};if(CFG.lang){base.alang=CFG.lang;}return fetch(CFG.endpoint+'?'+new URLSearchParams(Object.assign(base,p||{}))).then(parse);}

  var current='lessons';
  var FILTERS={
    lessons:[['status',str('rp_f_status'),'text'],['teacherid',str('rp_f_teacherid'),'number'],['studentid',str('rp_f_studentid'),'number'],['from',str('rp_f_from'),'number'],['to',str('rp_f_to'),'number']],
    platform_earnings:[['status',str('rp_f_earnstatus'),'text'],['teacherid',str('rp_f_teacherid'),'number'],['from',str('rp_f_from'),'number'],['to',str('rp_f_to'),'number']],
    packages:[['source',str('rp_f_source'),'text'],['studentid',str('rp_f_studentid'),'number'],['from',str('rp_f_from'),'number'],['to',str('rp_f_to'),'number']],
    student_flex:[['studentid',str('rp_f_studentid_req'),'number']],
    user_activity:[['userid',str('rp_f_userid'),'number'],['email',str('rp_f_email'),'text'],['from',str('rp_f_from'),'number'],['to',str('rp_f_to'),'number']]
  };
  var FN={lessons:'report_lessons',platform_earnings:'report_platform_earnings',packages:'report_packages',student_flex:'report_student_flex',user_activity:'report_user_activity'};
  var EXPORTTYPE={lessons:'lessons',platform_earnings:'platform_earnings',packages:'packages',student_flex:'student_flex'};

  function readFilters(){
    var p={};(FILTERS[current]||[]).forEach(function(f){var v=$('f-'+f[0]).value;if(v!==''){p[f[0]]=v;}});return p;
  }
  function renderFilters(){
    var box=$('rp-filters');box.innerHTML='';
    (FILTERS[current]||[]).forEach(function(f){
      var fg=document.createElement('div');fg.className='fg';
      fg.innerHTML='<label>'+f[1]+'</label>';
      var inp=document.createElement('input');inp.className='form-control';inp.id='f-'+f[0];inp.type=f[2];
      fg.appendChild(inp);box.appendChild(fg);
    });
    var run=document.createElement('button');run.className='btn btn-primary';run.textContent=str('rp_run');run.onclick=load;box.appendChild(run);
    if(EXPORTTYPE[current]){
      var exp=document.createElement('a');exp.className='btn btn-outline-secondary';exp.textContent=str('ui_export_csv');exp.id='rp-export';exp.target='_blank';box.appendChild(exp);
    }
  }
  function updateExportLink(){
    if(!EXPORTTYPE[current]||!$('rp-export')){return;}
    var p=Object.assign({type:EXPORTTYPE[current],token:CFG.token},readFilters());
    if(CFG.lang){p.alang=CFG.lang;}
    $('rp-export').href=CFG.export+'?'+new URLSearchParams(p).toString();
  }

  var COLS={
    lessons:[['id',str('rp_c_id')],['student_name',str('rp_c_student')],['teacher_name',str('rp_c_teacher')],['subject',str('rp_c_subject')],['status',str('rp_c_status')],['confirmed_time',str('rp_c_confirmed'),fmt],['flex_state',str('rp_c_flex')]],
    platform_earnings:[['lessonid',str('rp_c_lesson')],['teacher_name',str('rp_c_teacher')],['student_name',str('rp_c_student')],['lesson_time',str('rp_c_date'),fmt],['flex_value',str('rp_c_flexvalue'),money],['platform_percent',str('rp_c_platpct')],['platform_amount',str('rp_c_platform'),money],['status',str('rp_c_status')]],
    packages:[['id',str('rp_c_id')],['student_name',str('rp_c_student')],['package_name',str('rp_c_package')],['source',str('rp_c_source')],['price_paid',str('rp_c_price'),money],['flex_count',str('rp_c_flex')],['remaining_flex',str('rp_c_rem')],['reserved_flex',str('rp_c_resv')],['consumed_flex',str('rp_c_used')],['status',str('rp_c_status')]],
    student_flex:[['timecreated',str('rp_c_date'),fmt],['type',str('rp_c_type')],['amount',str('rp_c_amount')],['balance_before',str('rp_c_before')],['balance_after',str('rp_c_after')],['package',str('rp_c_package')],['lessonid',str('rp_c_lesson')],['performed_by',str('rp_c_by')],['reason',str('rp_c_reason')]]
  };

  function renderSummary(d){
    var box=$('rp-summary');box.innerHTML='';
    var s=d.summary; if(!s && d.balance){ // student_flex
      var b=d.balance;
      box.innerHTML='<div class="rp-chip">'+esc(str('rp_s_available'))+'<b>'+b.available_flex+'</b></div>'+
        '<div class="rp-chip">'+esc(str('rp_s_reserved'))+'<b>'+b.reserved_flex+'</b></div>'+
        '<div class="rp-chip">'+esc(str('rp_s_consumed'))+'<b>'+b.consumed_flex+'</b></div>'+
        '<div class="rp-chip">'+esc(str('rp_s_package'))+'<b>'+esc(b.active_package||'—')+'</b></div>'+
        '<div class="rp-chip">'+esc(str('rp_s_expires'))+'<b>'+fmt(b.expires_at)+'</b></div>';
      return;
    }
    if(!s){return;}
    Object.keys(s).forEach(function(k){
      if(k==='by_status'){return;}
      var v=s[k]; if(typeof v==='number' && String(k).indexOf('amount')>=0 || String(k).indexOf('earnings')>=0 || String(k).indexOf('value')>=0){v=money(v);}
      // Localised chip label (rp_sum_<field>), falling back to the humanised field name.
      var label=str('rp_sum_'+k); if(label==='rp_sum_'+k){label=k.replace(/_/g,' ');}
      box.innerHTML+='<div class="rp-chip">'+esc(label)+'<b>'+esc(v)+'</b></div>';
    });
  }

  function rowsOf(d){
    if(d.rows){return d.rows;}
    if(d.history){return d.history;}
    return [];
  }
  // Pretty labels for the audit-trail action keys (resolved from lang pack: rp_act_<action>).
  function actionLabel(a){var k='rp_act_'+a;return str(k)!==k?str(k):a;}

  function render(d){
    renderSummary(d);
    $('rp-timeline').style.display='none';
    var cols=COLS[current];
    var tl=(current==='lessons'); // lessons rows get an action-timeline button
    $('rp-head').innerHTML='<tr>'+cols.map(function(c){return '<th>'+esc(c[1])+'</th>';}).join('')+(tl?'<th>'+esc(str('rp_c_timeline'))+'</th>':'')+'</tr>';
    var rows=rowsOf(d);
    var pg=$('rp-body-pager');
    if(!rows.length){$('rp-body').innerHTML='<tr><td colspan="'+(cols.length+(tl?1:0))+'" class="text-muted">'+esc(str('rp_no_data'))+'</td></tr>';if(pg){pg.innerHTML='';}return;}
    function rowHtml(r){
      var tds=cols.map(function(c){var v=r[c[0]];if(c[2]){v=c[2](v);}return '<td>'+esc(v)+'</td>';}).join('');
      if(tl){tds+='<td><button type="button" class="btn btn-sm btn-outline-secondary rp-tl" data-id="'+r.id+'">'+esc(str('rp_c_timeline'))+'</button></td>';}
      return '<tr>'+tds+'</tr>';
    }
    AcademyUI.paginate({rows:rows,pageSize:15,pagerEl:pg,labels:{info:str('ui_pager_info')},
      render:function(items){$('rp-body').innerHTML=items.map(rowHtml).join('');}});
  }

  function showTimeline(id){
    apiGet('report_lesson_events',{lessonid:id}).then(function(d){
      var l=d.lesson||{};
      $('rp-tl-title').textContent=strf('rp_tl_title_full',{id:id,subject:l.subject||'',student:l.student_name||'',teacher:l.teacher_name||''});
      $('rp-tl-meta').innerHTML=
        '<div class="rp-chip">'+esc(str('rp_tl_joinedroom'))+'<b>'+fmt(l.teacher_joined_at)+'</b></div>'+
        '<div class="rp-chip">'+esc(str('rp_tl_started'))+'<b>'+fmt(l.actual_start)+'</b></div>'+
        '<div class="rp-chip">'+esc(str('rp_tl_ended'))+'<b>'+fmt(l.actual_end)+'</b></div>';
      var ev=d.events||[];
      $('rp-tl-body').innerHTML=ev.length?ev.map(function(e,i){
        return '<tr><td>'+(i+1)+'</td><td>'+esc(actionLabel(e.action))+'</td><td>'+esc(e.actor_name||'—')+'</td><td>'+esc(e.role)+'</td><td>'+(e.time?fmt(e.time):'—')+'</td></tr>';
      }).join(''):'<tr><td colspan="5" class="text-muted">'+esc(str('rp_tl_none'))+'</td></tr>';
      $('rp-timeline').style.display='block';
      $('rp-timeline').scrollIntoView({behavior:'smooth',block:'nearest'});
    }).catch(function(e){msg(e.message,'danger');});
  }
  $('rp-tl-close').onclick=function(){$('rp-timeline').style.display='none';};
  $('rp-body').addEventListener('click',function(e){
    var b=e.target.closest&&e.target.closest('.rp-tl');
    if(b){showTimeline(b.getAttribute('data-id'));}
  });

  // US-B2B-1-9: custom panel for the nested user-activity payload (profile + subs + memberships + courses + actions).
  function renderUserActivity(d){
    var u=d.user||{};
    function tbl(title, head, rows){
      var h='<h5 class="mt-3">'+esc(title)+'</h5>';
      if(!rows.length){return h+'<p class="text-muted">'+esc(str('rp_ua_none'))+'</p>';}
      h+='<div style="overflow-x:auto"><table class="rp-table"><thead><tr>'+head.map(function(c){return '<th>'+esc(c)+'</th>';}).join('')+'</tr></thead><tbody>';
      h+=rows.map(function(r){return '<tr>'+r.map(function(c){return '<td>'+esc(c)+'</td>';}).join('')+'</tr>';}).join('');
      return h+'</tbody></table></div>';
    }
    var html='<div id="rp-summary" class="rp-summary" style="display:flex;gap:.6rem;flex-wrap:wrap;margin:.5rem 0">'+
      '<div class="rp-chip">'+esc(u.name||'')+'<b>'+esc(u.email||'')+'</b></div>'+
      '<div class="rp-chip">'+esc(str('rp_ua_registered'))+'<b>'+fmt(u.registered)+'</b></div>'+
      '<div class="rp-chip">'+esc(str('rp_ua_lastlogin'))+'<b>'+fmt(u.last_login)+'</b></div>'+
      '<div class="rp-chip">'+esc(str('rp_ua_status'))+'<b>'+esc(u.account_status)+'</b></div>'+
      '<div class="rp-chip">'+esc(str('rp_ua_roles'))+'<b>'+esc((u.roles||[]).join(', ')||'—')+'</b></div>'+
    '</div>';
    html+=tbl(str('rp_ua_subs'),[str('rp_c_package'),str('rp_c_type'),str('rp_c_status'),str('rp_s_expires')],
      (d.subscriptions||[]).map(function(s){return [s.name,s.type+(s.seats?(' ('+s.seats+')'):''),s.status,fmt(s.expires_at)];}));
    html+=tbl(str('rp_ua_memberships'),[str('rp_c_package'),str('rp_c_status'),str('rp_c_date')],
      (d.memberships||[]).map(function(m){return [m.subscription,m.status,fmt(m.timecreated)];}));
    html+=tbl(str('rp_ua_courses'),[str('rp_c_id'),str('rp_c_package')],
      (d.courses||[]).map(function(c){return [c.id,c.fullname];}));
    html+=tbl(str('rp_ua_actions'),[str('rp_c_date'),str('rp_c_type'),str('rp_c_amount'),str('rp_c_status')],
      (d.actions||[]).map(function(a){return [fmt(a.timecreated),a.detail,money(a.amount),a.status];}));
    $('rp-useractivity').innerHTML=html;
  }

  function load(){
    msg('');
    if(current==='student_flex'){
      var sid=$('f-studentid').value;
      if(!sid){msg(str('rp_enter_student'),'info');$('rp-body').innerHTML='';$('rp-head').innerHTML='';$('rp-summary').innerHTML='';return;}
    }
    if(current==='user_activity'){
      var uid=$('f-userid').value, em=$('f-email').value;
      if(!uid && !em){msg(str('rp_enter_user'),'info');$('rp-useractivity').innerHTML='';return;}
      apiGet(FN[current],readFilters()).then(renderUserActivity).catch(function(e){msg(e.message,'danger');});
      return;
    }
    updateExportLink();
    apiGet(FN[current],readFilters()).then(render).catch(function(e){msg(e.message,'danger');});
  }

  function togglePanels(){
    var ua=(current==='user_activity');
    $('rp-generic').style.display=ua?'none':'block';
    $('rp-summary').style.display=ua?'none':'flex';
    $('rp-useractivity').style.display=ua?'block':'none';
  }

  Array.prototype.forEach.call(document.querySelectorAll('#rp-tabs button'),function(b){
    b.onclick=function(){
      Array.prototype.forEach.call(document.querySelectorAll('#rp-tabs button'),function(x){x.classList.remove('active');});
      b.classList.add('active');current=b.getAttribute('data-tab');
      renderFilters();updateExportLink();togglePanels();
      if(current==='student_flex'){$('rp-body').innerHTML='';$('rp-head').innerHTML='';$('rp-summary').innerHTML='';msg(str('rp_enter_student_run'),'info');}
      else if(current==='user_activity'){$('rp-useractivity').innerHTML='';msg(str('rp_enter_user'),'info');}
      else{load();}
    };
  });

  renderFilters();togglePanels();load();
})();
JS
);
echo $OUTPUT->footer();
