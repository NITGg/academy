<?php
// Admin UI: assign a package to a student after an offline payment (US-AD-4-1).

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');

admin_externalpage_setup('local_academy_assignpackage');
require_capability('local/academy:manageplatform', context_system::instance());

global $DB, $OUTPUT, $CFG, $PAGE;

$service = $DB->get_record('external_services', array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
$token = external_generate_token_for_current_user($service)->token;

$PAGE->set_title(get_string('assignpackage', 'local_academy'));
$PAGE->set_heading(get_string('assignpackage', 'local_academy'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('assignpackage', 'local_academy'));
echo html_writer::script('window.ACADEMY_AP = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'token'    => $token,
)) . ';');
?>
<style>
#ap-app{max-width:560px}
#ap-app .form-group{margin-bottom:.75rem}
</style>
<div id="ap-app">
  <div id="ap-msg" class="alert" style="display:none"></div>
  <div class="card"><div class="card-body">
    <div class="form-group"><label for="ap-student">Student user ID</label>
      <input class="form-control" id="ap-student" type="number" min="1" placeholder="e.g. 4770">
      <small class="text-muted">The numeric Moodle user id of the student.</small></div>
    <div class="form-group"><label for="ap-package">Package</label>
      <select class="form-control" id="ap-package"></select></div>
    <div class="form-group"><label for="ap-amount">Amount paid (offline)</label>
      <input class="form-control" id="ap-amount" type="number" min="0" step="0.01" placeholder="defaults to package price">
    </div>
    <div class="form-group"><label for="ap-method">Payment method</label>
      <select class="form-control" id="ap-method">
        <option value="offline">Offline / cash</option>
        <option value="bank">Bank transfer</option>
        <option value="wallet">Mobile wallet</option>
      </select></div>
    <div class="form-group"><label for="ap-reference">Payment reference</label>
      <input class="form-control" id="ap-reference" placeholder="receipt / transfer no."></div>
    <div class="form-group"><label for="ap-note">Note (optional)</label>
      <input class="form-control" id="ap-note"></div>
    <button id="ap-submit" class="btn btn-primary">Assign package</button>
  </div></div>
</div>
<?php
echo html_writer::script(<<<'JS'
(function () {
  var CFG = window.ACADEMY_AP;
  function $(id){return document.getElementById(id);}
  function msg(t,k){var e=$('ap-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';}
  function parse(r){return r.text().then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error('Session expired — reload the page.');}if(j.status!=='success'){throw new Error(j.error||'Failed');}return j.data;});}
  function apiGet(fn,p){return fetch(CFG.endpoint+'?'+new URLSearchParams(Object.assign({function:fn,token:CFG.token},p||{}))).then(parse);}
  function apiPost(fn,p){var b=new URLSearchParams(Object.assign({function:fn,token:CFG.token},p));return fetch(CFG.endpoint,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:b.toString()}).then(parse);}

  // Populate active packages.
  apiGet('get_packages',{status:'active'}).then(function(rows){
    var sel=$('ap-package');
    rows.forEach(function(p){var o=document.createElement('option');o.value=p.id;o.textContent=p.name+' — '+p.flex_count+' Flex / '+p.price;sel.appendChild(o);});
    if(!rows.length){msg('No active packages exist. Create one first.','danger');}
  }).catch(function(e){msg(e.message,'danger');});

  $('ap-submit').onclick=function(){
    var sid=$('ap-student').value;
    if(!sid){msg('Enter a student user id.','danger');return;}
    apiPost('assign_package',{studentid:sid,packageid:$('ap-package').value,amount:$('ap-amount').value||'0',
      method:$('ap-method').value,reference:$('ap-reference').value,note:$('ap-note').value})
      .then(function(d){msg('Assigned "'+d.package_name+'" ('+d.flex_balance+' Flex) to '+d.student_name+'.','success');
        $('ap-amount').value='';$('ap-reference').value='';$('ap-note').value='';})
      .catch(function(e){msg(e.message,'danger');});
  };
})();
JS
);
echo $OUTPUT->footer();
