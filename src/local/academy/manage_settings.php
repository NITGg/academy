<?php
// Admin UI for platform settings (US-AD-2-1). Three tabs: Lesson Deadlines, Financial, B2B.
// Uses the local_academy API from the browser.

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
    'set_complete_allowed', 'set_absence_report', 'set_lesson_start_reminder', 'set_lesson_start_reminder_help',
    'set_expiry_reminder', 'set_expiry_reminder_help',
    'set_teacher_percent', 'set_platform_percent', 'set_percent_help', 'set_save', 'set_saved',
    'set_tab_deadlines', 'set_tab_financial', 'set_tab_b2b',
    'set_reminder_add', 'set_reminder_placeholder',
    'set_b2b_auto_approve', 'set_b2b_auto_approve_help', 'set_b2b_return_seat', 'set_b2b_return_seat_help',
    'set_enabled', 'set_disabled',
    'err_sessionexpired', 'err_requestfailed',
));
echo html_writer::script('window.ACADEMY_SET = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'token'    => $token,
    'lang'     => optional_param('lang', current_language(), PARAM_LANG),
)) . ';');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');
?>
<style>
#set-tabs { display:flex; gap:.25rem; border-bottom:1px solid #dee2e6; margin-bottom:1rem; flex-wrap:wrap; }
#set-tabs button { border:none; background:none; padding:.5rem .9rem; border-bottom:3px solid transparent; cursor:pointer; }
#set-tabs button.active { border-bottom-color:#0f6cbf; font-weight:600; color:#0f6cbf; }
.set-pane { display:none; }
.set-pane.active { display:block; }
</style>
<div id="set-msg" class="alert" style="display:none"></div>
<div id="set-tabs">
  <button data-tab="deadlines" class="active"><?php echo $STR['set_tab_deadlines']; ?></button>
  <button data-tab="financial"><?php echo $STR['set_tab_financial']; ?></button>
  <button data-tab="b2b"><?php echo $STR['set_tab_b2b']; ?></button>
</div>
<div class="card" style="max-width:560px;">
  <div class="card-body">

    <div class="set-pane active" data-pane="deadlines">
      <div class="form-group"><label><?php echo $STR['set_min_booking']; ?></label><input class="form-control" id="s-min_booking_minutes" type="number" min="0"></div>
      <div class="form-group"><label><?php echo $STR['set_cancel_deadline']; ?></label><input class="form-control" id="s-cancel_deadline_minutes" type="number" min="0"></div>
      <div class="form-group"><label><?php echo $STR['set_update_deadline']; ?></label><input class="form-control" id="s-update_deadline_minutes" type="number" min="0"></div>
      <div class="form-group"><label><?php echo $STR['set_start_allowed']; ?></label><input class="form-control" id="s-start_allowed_minutes" type="number" min="0"></div>
      <div class="form-group"><label><?php echo $STR['set_complete_allowed']; ?></label><input class="form-control" id="s-complete_allowed_minutes" type="number" min="0"></div>
      <div class="form-group"><label><?php echo $STR['set_absence_report']; ?></label><input class="form-control" id="s-absence_report_minutes" type="number" min="0"></div>
      <div class="form-group">
        <label><?php echo $STR['set_lesson_start_reminder']; ?></label>
        <div id="reminder-tags" style="margin-bottom:0.5rem; display:flex; gap:0.5rem; flex-wrap:wrap;"></div>
        <div class="input-group" style="max-width:200px; margin-bottom:0.25rem;">
          <input class="form-control" id="reminder-add-input" type="number" min="1" placeholder="<?php echo s($STR['set_reminder_placeholder']); ?>">
          <div class="input-group-append">
            <button class="btn btn-outline-secondary" type="button" id="reminder-add-btn"><?php echo $STR['set_reminder_add']; ?></button>
          </div>
        </div>
        <input type="hidden" id="s-lesson_start_reminder_minutes">
        <small class="text-muted"><?php echo $STR['set_lesson_start_reminder_help']; ?></small>
      </div>
      <div class="form-group"><label><?php echo $STR['set_expiry_reminder']; ?></label><input class="form-control" id="s-expiry_reminder_days" type="number" min="0"><small class="text-muted"><?php echo $STR['set_expiry_reminder_help']; ?></small></div>
    </div>

    <div class="set-pane" data-pane="financial">
      <div class="form-group"><label><?php echo $STR['set_teacher_percent']; ?></label><input class="form-control" id="s-teacher_percent" type="number" min="0" max="100"></div>
      <div class="form-group"><label><?php echo $STR['set_platform_percent']; ?></label><input class="form-control" id="s-platform_percent" type="number" min="0" max="100"></div>
      <small class="text-muted"><?php echo $STR['set_percent_help']; ?></small>
    </div>

    <div class="set-pane" data-pane="b2b">
      <div class="form-group">
        <label><?php echo $STR['set_b2b_auto_approve']; ?></label>
        <select class="form-control" id="s-b2b_auto_approve_invited_users">
          <option value="0"><?php echo $STR['set_disabled']; ?></option>
          <option value="1"><?php echo $STR['set_enabled']; ?></option>
        </select>
        <small class="text-muted"><?php echo $STR['set_b2b_auto_approve_help']; ?></small>
      </div>
      <div class="form-group">
        <label><?php echo $STR['set_b2b_return_seat']; ?></label>
        <select class="form-control" id="s-b2b_return_seat_after_user_removal">
          <option value="0"><?php echo $STR['set_disabled']; ?></option>
          <option value="1"><?php echo $STR['set_enabled']; ?></option>
        </select>
        <small class="text-muted"><?php echo $STR['set_b2b_return_seat_help']; ?></small>
      </div>
    </div>

    <br>
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
    'teacher_percent','platform_percent',
    'b2b_auto_approve_invited_users','b2b_return_seat_after_user_removal'];
  function $(id){return document.getElementById(id);}
  function msg(t,k){var e=$('set-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';if(k==='success'){setTimeout(function(){e.style.display='none';},3000);}}
  function api(func,params){var qs=new URLSearchParams({function:func,token:CFG.token});if(CFG.lang){qs.append('alang',CFG.lang);}Object.keys(params||{}).forEach(function(k){qs.append(k,params[k]);});
    return fetch(CFG.endpoint+'?'+qs.toString()).then(function(r){return r.text();}).then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error(str('err_sessionexpired'));}if(j.status!=='success'){throw new Error(j.error||str('err_requestfailed'));}return j.data;});}

  // Tabs.
  Array.prototype.forEach.call(document.querySelectorAll('#set-tabs button'), function(b){
    b.onclick = function(){
      Array.prototype.forEach.call(document.querySelectorAll('#set-tabs button'), function(x){x.classList.remove('active');});
      b.classList.add('active');
      var tab = b.getAttribute('data-tab');
      Array.prototype.forEach.call(document.querySelectorAll('.set-pane'), function(p){
        p.classList.toggle('active', p.getAttribute('data-pane') === tab);
      });
    };
  });

  function renderReminders() {
    var remTags = $('reminder-tags');
    var remInput = $('s-lesson_start_reminder_minutes');
    if (!remTags || !remInput) return;
    remTags.innerHTML = '';
    var vals = (remInput.value || '').split(',').map(function(s){return s.trim();}).filter(function(s){return s !== '';});
    vals.forEach(function(val, idx) {
      var tag = document.createElement('span');
      tag.className = 'badge badge-primary';
      tag.style.cssText = 'font-size:0.9rem; padding:0.4rem 0.6rem; display:inline-flex; align-items:center; gap:0.4rem;';
      tag.innerHTML = val + ' min <span style="cursor:pointer; font-weight:bold;" data-idx="'+idx+'">&times;</span>';
      remTags.appendChild(tag);
    });
  }

  $('reminder-tags').addEventListener('click', function(e) {
    if (e.target.tagName === 'SPAN' && e.target.hasAttribute('data-idx')) {
      var idx = parseInt(e.target.getAttribute('data-idx'), 10);
      var remInput = $('s-lesson_start_reminder_minutes');
      var vals = (remInput.value || '').split(',').map(function(s){return s.trim();}).filter(function(s){return s !== '';});
      vals.splice(idx, 1);
      remInput.value = vals.join(',');
      renderReminders();
    }
  });

  $('reminder-add-btn').addEventListener('click', function() {
    var remAddInput = $('reminder-add-input');
    var remInput = $('s-lesson_start_reminder_minutes');
    var val = remAddInput.value.trim();
    if (val && parseInt(val, 10) > 0) {
      var vals = (remInput.value || '').split(',').map(function(s){return s.trim();}).filter(function(s){return s !== '';});
      if (vals.indexOf(val) === -1) {
        vals.push(val);
        vals.sort(function(a,b){return parseInt(b,10) - parseInt(a,10);});
        remInput.value = vals.join(',');
        renderReminders();
      }
      remAddInput.value = '';
    }
  });

  function setVal(k, v){ var el=$('s-'+k); if(el){ el.value = v; } }
  function getVal(k){ var el=$('s-'+k); return el ? el.value : ''; }

  function load(){api('get_lesson_settings',{}).then(function(d){KEYS.forEach(function(k){setVal(k, d[k]);});renderReminders();}).catch(function(e){msg(e.message,'danger');});}
  function save(){var p={};KEYS.forEach(function(k){p[k]=getVal(k);});api('update_lesson_settings',p).then(function(d){KEYS.forEach(function(k){setVal(k, d[k]);});renderReminders();msg(str('set_saved'),'success');}).catch(function(e){msg(e.message,'danger');});}
  $('set-save').addEventListener('click',save);
  load();
})();
JS
);
echo $OUTPUT->footer();
