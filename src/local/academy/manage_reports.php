<?php
// Admin UI: Flex platform reports (US-AD-3-1..3-4) with CSV export.

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');

admin_externalpage_setup('local_academy_reports');
require_capability('local/academy:manageplatform', context_system::instance());

global $DB, $OUTPUT, $CFG, $PAGE;

$service = $DB->get_record('external_services', array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
$token = external_generate_token_for_current_user($service)->token;

$PAGE->set_title(get_string('reports', 'local_academy'));
$PAGE->set_heading(get_string('reports', 'local_academy'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reports', 'local_academy'));
echo html_writer::script('window.ACADEMY_RP = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'export'   => $CFG->wwwroot . '/local/academy/export.php',
    'token'    => $token,
)) . ';');
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
    <button data-tab="lessons" class="active">Lessons &amp; attendance</button>
    <button data-tab="platform_earnings">Platform earnings</button>
    <button data-tab="packages">Packages &amp; Flex</button>
    <button data-tab="student_flex">Student Flex</button>
  </div>
  <div id="rp-filters"></div>
  <div id="rp-summary"></div>
  <div style="overflow-x:auto"><table class="rp-table"><thead id="rp-head"></thead><tbody id="rp-body"></tbody></table></div>
</div>
<?php
echo html_writer::script(<<<'JS'
(function () {
  var CFG = window.ACADEMY_RP;
  function $(id){return document.getElementById(id);}
  function msg(t,k){var e=$('rp-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display=t?'block':'none';}
  function parse(r){return r.text().then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error('Session expired — reload the page.');}if(j.status!=='success'){throw new Error(j.error||'Failed');}return j.data;});}
  function money(n){return Number(n||0).toFixed(2);}
  function fmt(ts){return ts?new Date(ts*1000).toLocaleString():'—';}
  function esc(s){return (s==null?'':String(s)).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
  function apiGet(fn,p){return fetch(CFG.endpoint+'?'+new URLSearchParams(Object.assign({function:fn,token:CFG.token},p||{}))).then(parse);}

  var current='lessons';
  var FILTERS={
    lessons:[['status','Status','text'],['teacherid','Teacher ID','number'],['studentid','Student ID','number'],['from','From (unix)','number'],['to','To (unix)','number']],
    platform_earnings:[['status','Earning status','text'],['teacherid','Teacher ID','number'],['from','From (unix)','number'],['to','To (unix)','number']],
    packages:[['source','Source','text'],['studentid','Student ID','number'],['from','From (unix)','number'],['to','To (unix)','number']],
    student_flex:[['studentid','Student ID (required)','number']]
  };
  var FN={lessons:'report_lessons',platform_earnings:'report_platform_earnings',packages:'report_packages',student_flex:'report_student_flex'};
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
    var run=document.createElement('button');run.className='btn btn-primary';run.textContent='Run';run.onclick=load;box.appendChild(run);
    var exp=document.createElement('a');exp.className='btn btn-outline-secondary';exp.textContent='Export CSV';exp.id='rp-export';exp.target='_blank';box.appendChild(exp);
  }
  function updateExportLink(){
    var p=Object.assign({type:EXPORTTYPE[current],token:CFG.token},readFilters());
    $('rp-export').href=CFG.export+'?'+new URLSearchParams(p).toString();
  }

  var COLS={
    lessons:[['id','ID'],['student_name','Student'],['teacher_name','Teacher'],['subject','Subject'],['status','Status'],['confirmed_time','Confirmed',fmt],['flex_state','Flex']],
    platform_earnings:[['lessonid','Lesson'],['teacher_name','Teacher'],['student_name','Student'],['lesson_time','Date',fmt],['flex_value','Flex value',money],['platform_percent','Plat %'],['platform_amount','Platform',money],['status','Status']],
    packages:[['id','ID'],['student_name','Student'],['package_name','Package'],['source','Source'],['price_paid','Price',money],['flex_count','Flex'],['remaining_flex','Rem'],['reserved_flex','Resv'],['consumed_flex','Used'],['status','Status']],
    student_flex:[['timecreated','Date',fmt],['type','Type'],['amount','Amount'],['balance_before','Before'],['balance_after','After'],['package','Package'],['lessonid','Lesson'],['performed_by','By'],['reason','Reason']]
  };

  function renderSummary(d){
    var box=$('rp-summary');box.innerHTML='';
    var s=d.summary; if(!s && d.balance){ // student_flex
      var b=d.balance;
      box.innerHTML='<div class="rp-chip">Available<b>'+b.available_flex+'</b></div>'+
        '<div class="rp-chip">Reserved<b>'+b.reserved_flex+'</b></div>'+
        '<div class="rp-chip">Consumed<b>'+b.consumed_flex+'</b></div>'+
        '<div class="rp-chip">Package<b>'+esc(b.active_package||'—')+'</b></div>'+
        '<div class="rp-chip">Expires<b>'+fmt(b.expires_at)+'</b></div>';
      return;
    }
    if(!s){return;}
    Object.keys(s).forEach(function(k){
      if(k==='by_status'){return;}
      var v=s[k]; if(typeof v==='number' && String(k).indexOf('amount')>=0 || String(k).indexOf('earnings')>=0 || String(k).indexOf('value')>=0){v=money(v);}
      box.innerHTML+='<div class="rp-chip">'+esc(k.replace(/_/g,' '))+'<b>'+esc(v)+'</b></div>';
    });
  }

  function rowsOf(d){
    if(d.rows){return d.rows;}
    if(d.history){return d.history;}
    return [];
  }
  function render(d){
    renderSummary(d);
    var cols=COLS[current];
    $('rp-head').innerHTML='<tr>'+cols.map(function(c){return '<th>'+c[1]+'</th>';}).join('')+'</tr>';
    var rows=rowsOf(d);
    if(!rows.length){$('rp-body').innerHTML='<tr><td colspan="'+cols.length+'" class="text-muted">No data.</td></tr>';return;}
    $('rp-body').innerHTML=rows.map(function(r){
      return '<tr>'+cols.map(function(c){var v=r[c[0]];if(c[2]){v=c[2](v);}return '<td>'+esc(v)+'</td>';}).join('')+'</tr>';
    }).join('');
  }

  function load(){
    msg('');
    if(current==='student_flex'){
      var sid=$('f-studentid').value;
      if(!sid){msg('Enter a student ID.','info');$('rp-body').innerHTML='';$('rp-head').innerHTML='';$('rp-summary').innerHTML='';return;}
    }
    updateExportLink();
    apiGet(FN[current],readFilters()).then(render).catch(function(e){msg(e.message,'danger');});
  }

  Array.prototype.forEach.call(document.querySelectorAll('#rp-tabs button'),function(b){
    b.onclick=function(){
      Array.prototype.forEach.call(document.querySelectorAll('#rp-tabs button'),function(x){x.classList.remove('active');});
      b.classList.add('active');current=b.getAttribute('data-tab');
      renderFilters();updateExportLink();
      if(current==='student_flex'){$('rp-body').innerHTML='';$('rp-head').innerHTML='';$('rp-summary').innerHTML='';msg('Enter a student ID and click Run.','info');}
      else{load();}
    };
  });

  renderFilters();load();
})();
JS
);
echo $OUTPUT->footer();
