<?php
// Admin UI: assign a package to a student after an offline payment (US-AD-4-1).

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/academy/lib.php'); // local_academy_string_map()

admin_externalpage_setup('local_academy_assignpackage');
require_capability('local/academy:manageplatform', context_system::instance());

global $DB, $OUTPUT, $CFG, $PAGE;

$service = $DB->get_record('external_services', array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
$token = external_generate_token_for_current_user($service)->token;

$PAGE->set_title(get_string('assignpackage', 'local_academy'));
$PAGE->set_heading(get_string('assignpackage', 'local_academy'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('assignpackage', 'local_academy'));

// Localised strings: server-rendered HTML reads $STR['key']; the JS reads window.ACADEMY_STR.
$STR = local_academy_string_map(array(
    'ap_student_label', 'ap_student_help', 'ap_student_placeholder', 'ap_package_label',
    'ap_amount_label', 'ap_amount_placeholder', 'ap_method_label', 'ap_method_offline',
    'ap_method_bank', 'ap_method_wallet', 'ap_reference_label', 'ap_reference_placeholder',
    'ap_note_label', 'ap_submit', 'ap_pkg_option', 'ap_no_packages', 'ap_enter_student', 'ap_assigned',
    'err_sessionexpired', 'err_requestfailed',
));
echo html_writer::script('window.ACADEMY_AP = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'token'    => $token,
    'lang'     => optional_param('lang', current_language(), PARAM_LANG),
)) . ';');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');
?>
<style>
#ap-app{max-width:560px}
#ap-app .form-group{margin-bottom:.75rem}
</style>
<div id="ap-app">
  <div id="ap-msg" class="alert" style="display:none"></div>
  <div class="card"><div class="card-body">
    <div class="form-group"><label for="ap-student"><?php echo $STR['ap_student_label']; ?></label>
      <input class="form-control" id="ap-student" type="number" min="1" placeholder="<?php echo s($STR['ap_student_placeholder']); ?>">
      <small class="text-muted"><?php echo $STR['ap_student_help']; ?></small></div>
    <div class="form-group"><label for="ap-package"><?php echo $STR['ap_package_label']; ?></label>
      <select class="form-control" id="ap-package"></select></div>
    <div class="form-group"><label for="ap-amount"><?php echo $STR['ap_amount_label']; ?></label>
      <input class="form-control" id="ap-amount" type="number" min="0" step="0.01" placeholder="<?php echo s($STR['ap_amount_placeholder']); ?>">
    </div>
    <div class="form-group"><label for="ap-method"><?php echo $STR['ap_method_label']; ?></label>
      <select class="form-control" id="ap-method">
        <option value="offline"><?php echo $STR['ap_method_offline']; ?></option>
        <option value="bank"><?php echo $STR['ap_method_bank']; ?></option>
        <option value="wallet"><?php echo $STR['ap_method_wallet']; ?></option>
      </select></div>
    <div class="form-group"><label for="ap-reference"><?php echo $STR['ap_reference_label']; ?></label>
      <input class="form-control" id="ap-reference" placeholder="<?php echo s($STR['ap_reference_placeholder']); ?>"></div>
    <div class="form-group"><label for="ap-note"><?php echo $STR['ap_note_label']; ?></label>
      <input class="form-control" id="ap-note"></div>
    <button id="ap-submit" class="btn btn-primary"><?php echo $STR['ap_submit']; ?></button>
  </div></div>
</div>
<?php
echo html_writer::script(<<<'JS'
(function () {
  var CFG = window.ACADEMY_AP;
  var STR = window.ACADEMY_STR || {};
  function str(k){return (k in STR)?STR[k]:k;}
  function strf(k,params){var s=str(k);if(params==null){return s;}if(typeof params!=='object'){return s.replace(/\{\$a\}/g,params);}return s.replace(/\{\$a->(\w+)\}/g,function(m,name){return (name in params)?params[name]:m;});}
  function $(id){return document.getElementById(id);}
  function msg(t,k){var e=$('ap-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';}
  function parse(r){return r.text().then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error(str('err_sessionexpired'));}if(j.status!=='success'){throw new Error(j.error||str('err_requestfailed'));}return j.data;});}
  function apiGet(fn,p){var base={function:fn,token:CFG.token};if(CFG.lang){base.alang=CFG.lang;}return fetch(CFG.endpoint+'?'+new URLSearchParams(Object.assign(base,p||{}))).then(parse);}
  function apiPost(fn,p){var base={function:fn,token:CFG.token};if(CFG.lang){base.alang=CFG.lang;}var b=new URLSearchParams(Object.assign(base,p));return fetch(CFG.endpoint,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:b.toString()}).then(parse);}

  // Populate active packages.
  apiGet('get_packages',{status:'active'}).then(function(rows){
    var sel=$('ap-package');
    rows.forEach(function(p){var o=document.createElement('option');o.value=p.id;o.textContent=strf('ap_pkg_option',{name:p.name,flex:p.flex_count,price:p.price});sel.appendChild(o);});
    if(!rows.length){msg(str('ap_no_packages'),'danger');}
  }).catch(function(e){msg(e.message,'danger');});

  $('ap-submit').onclick=function(){
    var sid=$('ap-student').value;
    if(!sid){msg(str('ap_enter_student'),'danger');return;}
    apiPost('assign_package',{studentid:sid,packageid:$('ap-package').value,amount:$('ap-amount').value||'0',
      method:$('ap-method').value,reference:$('ap-reference').value,note:$('ap-note').value})
      .then(function(d){msg(strf('ap_assigned',{name:d.package_name,flex:d.flex_balance,student:d.student_name}),'success');
        $('ap-amount').value='';$('ap-reference').value='';$('ap-note').value='';})
      .catch(function(e){msg(e.message,'danger');});
  };
})();
JS
);
echo $OUTPUT->footer();
