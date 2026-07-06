<?php
// "My teacher profile" UI (US-TR-1-1). The logged-in teacher views/updates their own profile via the API.

require('../../config.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/academy/lib.php'); // local_academy_string_map()

require_login();

global $DB, $OUTPUT, $CFG, $PAGE, $USER;

// Teachers only — this page edits a teacher profile.
if (!\local_academy\teacher_manager::is_teacher($USER->id)) {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/local/academy/teacher_profile.php'));
    $PAGE->set_title(get_string('myteacherprofile', 'local_academy'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('notateacher', 'local_academy'), 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

$service = $DB->get_record('external_services', array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
$token = external_generate_token_for_current_user($service)->token;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/academy/teacher_profile.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('myteacherprofile', 'local_academy'));
$PAGE->set_heading(get_string('myteacherprofile', 'local_academy'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('myteacherprofile', 'local_academy'));
// Localised strings: server-rendered HTML reads $STR['key']; the JS reads window.ACADEMY_STR.
$STR = local_academy_string_map(array(
    'tp_headline', 'tp_headline_ph', 'tp_bio', 'tp_experience', 'tp_available', 'tp_subjects',
    'tp_add_subject', 'tp_working_hours', 'tp_add_slot', 'tp_subject_ph', 'tp_to', 'tp_saved',
    'tp_day_sun', 'tp_day_mon', 'tp_day_tue', 'tp_day_wed', 'tp_day_thu', 'tp_day_fri', 'tp_day_sat',
    'set_save', 'err_sessionexpired', 'err_requestfailed',
));
echo html_writer::script('window.ACADEMY_TP = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'token'    => $token,
    'lang'     => optional_param('lang', current_language(), PARAM_LANG),
)) . ';');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');
?>
<style>
#tp-app{max-width:720px}
#tp-app .tp-row{display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem}
#tp-app .tp-row .form-control{margin:0}
#tp-app .tp-day{max-width:140px}
#tp-app .tp-time{max-width:130px}
#tp-app .tp-remove{flex:0 0 auto}
#tp-app .tp-sectionhead{display:flex;justify-content:space-between;align-items:center;margin:1.25rem 0 .5rem}
</style>
<div id="tp-app">
  <div id="tp-msg" class="alert" style="display:none"></div>
  <div class="card"><div class="card-body">

    <div class="form-group"><label for="t-headline"><?php echo $STR['tp_headline']; ?></label>
      <input class="form-control" id="t-headline" placeholder="<?php echo s($STR['tp_headline_ph']); ?>"></div>

    <div class="form-group"><label for="t-bio"><?php echo $STR['tp_bio']; ?></label>
      <textarea class="form-control" id="t-bio" rows="3"></textarea></div>

    <div class="form-row">
      <div class="form-group col-md-6"><label for="t-experience"><?php echo $STR['tp_experience']; ?></label>
        <input type="number" min="0" max="60" step="1" class="form-control" id="t-experience" placeholder="0"></div>
      <div class="form-group col-md-6 d-flex align-items-end">
        <div class="form-check"><input class="form-check-input" type="checkbox" id="t-available" checked>
          <label class="form-check-label" for="t-available"><?php echo $STR['tp_available']; ?></label></div></div>
    </div>

    <div class="tp-sectionhead"><h5 class="m-0"><?php echo $STR['tp_subjects']; ?></h5>
      <button type="button" class="btn btn-sm btn-outline-primary" id="t-addsubject"><?php echo $STR['tp_add_subject']; ?></button></div>
    <div id="t-subjects"></div>

    <div class="tp-sectionhead"><h5 class="m-0"><?php echo $STR['tp_working_hours']; ?></h5>
      <button type="button" class="btn btn-sm btn-outline-primary" id="t-addhour"><?php echo $STR['tp_add_slot']; ?></button></div>
    <div id="t-hours"></div>

    <hr>
    <button id="tp-save" class="btn btn-primary"><?php echo $STR['set_save']; ?></button>
  </div></div>
</div>
<?php
echo html_writer::script(<<<'JS'
(function () {
  var CFG = window.ACADEMY_TP;
  var STR = window.ACADEMY_STR || {};
  function str(k){return (k in STR)?STR[k]:k;}
  var DAYS = [str('tp_day_sun'),str('tp_day_mon'),str('tp_day_tue'),str('tp_day_wed'),str('tp_day_thu'),str('tp_day_fri'),str('tp_day_sat')];
  function $(id){return document.getElementById(id);}
  function el(tag,attrs,html){var e=document.createElement(tag);for(var k in (attrs||{})){e.setAttribute(k,attrs[k]);}if(html!=null){e.innerHTML=html;}return e;}
  function msg(t,k){var e=$('tp-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';if(k==='success'){setTimeout(function(){e.style.display='none';},3000);}}
  function parse(r){return r.text().then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error(str('err_sessionexpired'));}if(j.status!=='success'){throw new Error(j.error||str('err_requestfailed'));}return j.data;});}

  function subjectRow(subject){
    var row=el('div',{class:'tp-row'});
    var s=el('input',{class:'form-control tp-subject',placeholder:str('tp_subject_ph')}); s.value=subject||'';
    var rm=el('button',{type:'button',class:'btn btn-outline-danger tp-remove'},'&times;');
    rm.onclick=function(){row.remove();};
    row.appendChild(s);row.appendChild(rm);
    return row;
  }
  function hourRow(day, start, end){
    var row=el('div',{class:'tp-row'});
    var d=el('select',{class:'form-control tp-day'});
    DAYS.forEach(function(name,i){var o=el('option',{value:i},name);if(i===(day==null?1:day)){o.setAttribute('selected','');}d.appendChild(o);});
    var st=el('input',{type:'time',class:'form-control tp-time tp-start'}); st.value=start||'09:00';
    var sep=el('span',{},str('tp_to'));
    var en=el('input',{type:'time',class:'form-control tp-time tp-end'}); en.value=end||'10:00';
    var rm=el('button',{type:'button',class:'btn btn-outline-danger tp-remove'},'&times;');
    rm.onclick=function(){row.remove();};
    [d,st,sep,en,rm].forEach(function(x){row.appendChild(x);});
    return row;
  }

  function fill(d){
    $('t-headline').value=d.headline||'';
    $('t-bio').value=d.bio||'';
    $('t-experience').value=(d.experience||'').toString().replace(/[^0-9]/g,'');
    $('t-available').checked=(d.available==1);
    var subs=$('t-subjects'); subs.innerHTML='';
    (d.subjects||[]).forEach(function(s){subs.appendChild(subjectRow(s.subject));});
    if(!(d.subjects||[]).length){subs.appendChild(subjectRow(''));}
    var hrs=$('t-hours'); hrs.innerHTML='';
    (d.hours||[]).forEach(function(h){hrs.appendChild(hourRow(h.dayofweek,h.starttime,h.endtime));});
  }

  function collectSubjects(){
    return Array.prototype.map.call($('t-subjects').querySelectorAll('.tp-row'),function(r){
      return {subject:r.querySelector('.tp-subject').value.trim()};
    }).filter(function(s){return s.subject;});
  }
  function collectHours(){
    return Array.prototype.map.call($('t-hours').querySelectorAll('.tp-row'),function(r){
      return {dayofweek:parseInt(r.querySelector('.tp-day').value,10),starttime:r.querySelector('.tp-start').value,endtime:r.querySelector('.tp-end').value};
    }).filter(function(h){return h.starttime&&h.endtime;});
  }

  function save(){
    var body=new URLSearchParams();
    body.append('function','update_teacher_profile');body.append('token',CFG.token);
    body.append('headline',$('t-headline').value);body.append('bio',$('t-bio').value);
    body.append('experience',$('t-experience').value||'0');body.append('available',$('t-available').checked?1:0);
    body.append('subjects',JSON.stringify(collectSubjects()));body.append('hours',JSON.stringify(collectHours()));
    fetch(CFG.endpoint,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()})
      .then(parse).then(function(d){fill(d);msg(str('tp_saved'),'success');}).catch(function(e){msg(e.message,'danger');});
  }

  $('t-addsubject').onclick=function(){$('t-subjects').appendChild(subjectRow(''));};
  $('t-addhour').onclick=function(){$('t-hours').appendChild(hourRow(1,'09:00','10:00'));};
  $('tp-save').onclick=save;

  fetch(CFG.endpoint+'?'+new URLSearchParams({function:'get_teacher_profile',token:CFG.token}).toString())
    .then(parse).then(fill).catch(function(e){msg(e.message,'danger');});
})();
JS
);
echo $OUTPUT->footer();
