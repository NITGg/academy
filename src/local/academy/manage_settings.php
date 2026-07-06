<?php
// Admin UI for lesson settings (US-AD-2-1). Uses the local_academy API from the browser.

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/academy/lib.php'); // local_academy_string_map()

admin_externalpage_setup('local_academy_managesettings');
require_capability('local/academy:manageplatform', context_system::instance());

global $DB, $OUTPUT, $CFG, $PAGE;

$service = $DB->get_record('external_services', array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
$token = external_generate_token_for_current_user($service)->token;

$PAGE->set_title(get_string('managesettings', 'local_academy'));
$PAGE->set_heading(get_string('managesettings', 'local_academy'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managesettings', 'local_academy'));

// Localised strings: server-rendered HTML reads $STR['key']; the JS reads window.ACADEMY_STR.
$STR = local_academy_string_map(array(
    'set_min_booking', 'set_cancel_deadline', 'set_update_deadline', 'set_start_allowed',
    'set_complete_allowed', 'set_absence_report', 'set_lesson_start_reminder', 'set_lesson_start_reminder_help', 'set_expiry_reminder', 'set_expiry_reminder_help',
    'set_teacher_percent', 'set_platform_percent', 'set_percent_help', 'set_save', 'set_saved',
    'err_sessionexpired', 'err_requestfailed',
));
echo html_writer::script('window.ACADEMY_SET = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'token'    => $token,
    'lang'     => optional_param('lang', current_language(), PARAM_LANG),
)) . ';');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');
?>
<div id="set-msg" class="alert" style="display:none"></div>
<div class="card" style="max-width:560px;">
  <div class="card-body">
    <div class="form-group"><label><?php echo $STR['set_min_booking']; ?></label><input class="form-control" id="s-min_booking_minutes" type="number" min="0"></div>
    <div class="form-group"><label><?php echo $STR['set_cancel_deadline']; ?></label><input class="form-control" id="s-cancel_deadline_minutes" type="number" min="0"></div>
    <div class="form-group"><label><?php echo $STR['set_update_deadline']; ?></label><input class="form-control" id="s-update_deadline_minutes" type="number" min="0"></div>
    <div class="form-group"><label><?php echo $STR['set_start_allowed']; ?></label><input class="form-control" id="s-start_allowed_minutes" type="number" min="0"></div>
    <div class="form-group"><label><?php echo $STR['set_complete_allowed']; ?></label><input class="form-control" id="s-complete_allowed_minutes" type="number" min="0"></div>
    <div class="form-group"><label><?php echo $STR['set_absence_report']; ?></label><input class="form-control" id="s-absence_report_minutes" type="number" min="0"></div>
    <div class="form-group"><label><?php echo $STR['set_lesson_start_reminder']; ?></label><input class="form-control" id="s-lesson_start_reminder_minutes" type="text"><small class="text-muted"><?php echo $STR['set_lesson_start_reminder_help']; ?></small></div>
    <div class="form-group"><label><?php echo $STR['set_expiry_reminder']; ?></label><input class="form-control" id="s-expiry_reminder_days" type="number" min="0"><small class="text-muted"><?php echo $STR['set_expiry_reminder_help']; ?></small></div>
    <hr>
    <div class="form-group"><label><?php echo $STR['set_teacher_percent']; ?></label><input class="form-control" id="s-teacher_percent" type="number" min="0" max="100"></div>
    <div class="form-group"><label><?php echo $STR['set_platform_percent']; ?></label><input class="form-control" id="s-platform_percent" type="number" min="0" max="100"></div>
    <small class="text-muted"><?php echo $STR['set_percent_help']; ?></small><br><br>
    <button id="set-save" class="btn btn-primary"><?php echo $STR['set_save']; ?></button>
  </div>
</div>
<?php
echo html_writer::script(<<<'JS'
(function () {
  var CFG = window.ACADEMY_SET;
  var STR = window.ACADEMY_STR || {};
  function str(k){return (k in STR)?STR[k]:k;}
  var KEYS = ['min_booking_minutes','cancel_deadline_minutes','update_deadline_minutes',
    'start_allowed_minutes','complete_allowed_minutes','absence_report_minutes','lesson_start_reminder_minutes','expiry_reminder_days',
    'teacher_percent','platform_percent'];
  function $(id){return document.getElementById(id);}
  function msg(t,k){var e=$('set-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';if(k==='success'){setTimeout(function(){e.style.display='none';},3000);}}
  function api(func,params){var qs=new URLSearchParams({function:func,token:CFG.token});if(CFG.lang){qs.append('alang',CFG.lang);}Object.keys(params||{}).forEach(function(k){qs.append(k,params[k]);});
    return fetch(CFG.endpoint+'?'+qs.toString()).then(function(r){return r.text();}).then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error(str('err_sessionexpired'));}if(j.status!=='success'){throw new Error(j.error||str('err_requestfailed'));}return j.data;});}
  function load(){api('get_lesson_settings',{}).then(function(d){KEYS.forEach(function(k){$('s-'+k).value=d[k];});}).catch(function(e){msg(e.message,'danger');});}
  function save(){var p={};KEYS.forEach(function(k){p[k]=$('s-'+k).value;});api('update_lesson_settings',p).then(function(d){KEYS.forEach(function(k){$('s-'+k).value=d[k];});msg(str('set_saved'),'success');}).catch(function(e){msg(e.message,'danger');});}
  $('set-save').addEventListener('click',save);
  load();
})();
JS
);
echo $OUTPUT->footer();
