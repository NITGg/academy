<?php
// Student hub UI for the Academy Flex platform. Mirrors the same APIs the student mobile app uses
// (see docs/academy_plugin_api.md): browse teachers + book lessons (US-LS-1-1), manage my lessons
// (US-ST-2-2 + the lesson lifecycle), and buy / track Flex packages (US-PK-1-1, US-PK-1-2, US-PK-2-1).
// Like my_lessons.php / wallet.php it mints a mobile web-service token and drives /local/academy/api.php
// from vanilla JS, so the web UI and the mobile app exercise exactly the same endpoints.

require('../../config.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/academy/lib.php'); // local_academy_string_map()

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

// Localised strings for the whole student hub. Server-rendered HTML reads $STR['key']; the JS reads
// window.ACADEMY_STR via the str()/strf() helpers.
$STR = local_academy_string_map(array(
    // Tab bar.
    'tab_book', 'tab_lessons', 'tab_packages', 'tab_subavailable', 'tab_mysubs',
    // Flex banner.
    'st_available_flex', 'st_book_up_to', 'st_no_active_pkg',
    // Shared UI + modal.
    'ui_cancel', 'ui_confirm', 'ui_search', 'ui_refresh', 'ui_never', 'ui_currency_egp',
    // Packages tab — section headings + table headers.
    'availpkgs_heading', 'mypackages', 'st_payment_history', 'st_flex_history',
    'st_col_package', 'st_col_flexusedtot', 'st_col_status', 'st_col_activated', 'st_col_expires',
    'st_col_date', 'st_col_amount', 'st_col_method', 'st_col_transaction',
    'st_col_type', 'st_col_change', 'st_col_balance', 'st_col_lesson', 'st_col_note',
    // Packages tab — dynamic JS strings.
    'st_pkg_none_available', 'st_pkg_none', 'st_pay_none',
    'st_flex_none', 'st_already_active_pkg', 'st_buy_package', 'st_buy_title', 'st_buy_text',
    'st_proceed_payment', 'st_pkgmeta_flex', 'st_pkgmeta_validdays', 'st_pkgmeta_neverexp',
    'st_flex_left',
    'pstat_active', 'pstat_fully_used', 'pstat_expired', 'pstat_cancelled', 'pstat_pending',
    'pay_completed', 'pay_pending', 'pay_failed', 'pay_refunded',
    'flx_reserve', 'flx_consume', 'flx_return', 'flx_purchase', 'flx_assign', 'flx_expire', 'flx_adjust',
    'err_sessionexpired', 'err_requestfailed', 'err_timeconflict',
    // Book a lesson tab.
    'st_search_placeholder', 'st_teacher_num', 'st_request_lesson', 'st_no_subjects',
    'st_request_with', 'st_send_request', 'st_field_subject', 'st_field_datetime',
    'st_field_note_req', 'st_note_placeholder', 'st_pick_valid_time', 'st_note_required',
    'st_lesson_requested', 'st_no_teachers',
    'st_slot_pickday', 'st_slot_picktime', 'st_slot_noavail', 'st_slot_nodayslots',
    // My lessons tab — filter, statuses, actions, dialogs, card.
    'st_status', 'lf_all', 'lf_pending', 'lf_waiting_student', 'lf_waiting_teacher', 'lf_confirmed',
    'lf_in_progress', 'lf_completed', 'lf_student_absent', 'lf_teacher_absent', 'lf_cancelled',
    'lf_cancelled_teacher', 'lf_rejected',
    'lstat_pending', 'lstat_waiting_student', 'lstat_waiting_teacher', 'lstat_confirmed',
    'lstat_in_progress', 'lstat_completed', 'lstat_student_absent', 'lstat_teacher_absent',
    'lstat_cancelled', 'lstat_cancelled_teacher', 'lstat_rejected',
    'lact_accept', 'lact_reject', 'lact_suggest', 'lact_cancel_request', 'lact_cancel',
    'lact_report_teacher_absent', 'lact_request_time_update', 'lact_join',
    'lact_accept_newtime', 'lact_reject_newtime',
    'la_done', 'la_reason_optional', 'la_pick_valid_time', 'la_reject_title', 'la_suggest_title',
    'la_suggested_time', 'la_withdraw_title', 'la_withdraw_text', 'la_cancel_title', 'la_cancel_text',
    'la_report_absent_title', 'la_report_absent_text', 'la_newtime_title', 'la_newtime_label',
    'la_room_not_ready',
    'lc_teacher_num', 'lc_title', 'lc_confirmed', 'lc_requested', 'lc_duration', 'lc_your_note',
    'lc_reject_reason', 'lc_cancel_reason', 'lc_flex', 'lc_you', 'lc_the_teacher', 'lc_resched_moved',
    'st_no_lessons',
    // Subscriptions tabs.
    'sub_available_heading', 'sub_my_heading', 'sub_payments_heading', 'sub_col_subscription',
    'sub_col_daysleft', 'sub_col_courses',
    'sstat_active', 'sstat_expired', 'sstat_cancelled', 'sstat_pending', 'sstat_payment_failed',
    'sub_days', 'sub_courses_label', 'sub_already_active', 'sub_buy', 'sub_buy_title', 'sub_buy_text',
    'sub_none_available', 'sub_none_mine', 'sub_no_payments',
));

echo html_writer::script('window.ACADEMY_ST = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'token'    => $token,
    'lang'     => optional_param('lang', current_language(), PARAM_LANG),
    'min_booking_minutes' => (int)\local_academy\settings_manager::get('min_booking_minutes'),
)) . ';');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');
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
/* block-based slot picker (Request a lesson) */
.st-slotpicker{margin-top:.25rem}
.st-slot-sub{font-size:.8rem;font-weight:600;color:#6c757d;margin:.6rem 0 .35rem;text-transform:uppercase;letter-spacing:.03em}
.st-slot-days{display:grid;grid-template-columns:repeat(auto-fill,minmax(88px,1fr));gap:.4rem;max-height:200px;overflow-y:auto}
.st-day{display:flex;flex-direction:column;align-items:center;gap:.1rem;border:1px solid #dee2e6;background:#fff;border-radius:.5rem;padding:.45rem .3rem;cursor:pointer;line-height:1.2;transition:all .12s}
.st-day:hover{border-color:#0d6efd;background:#eaf3ff}
.st-day .st-day-dow{font-size:.72rem;font-weight:600;color:#6c757d;text-transform:uppercase}
.st-day .st-day-date{font-size:.95rem;font-weight:700;color:#212529}
.st-day.active{background:#0d6efd;border-color:#0d6efd}
.st-day.active .st-day-dow,.st-day.active .st-day-date{color:#fff}
.st-slot-times{display:grid;grid-template-columns:repeat(auto-fill,minmax(84px,1fr));gap:.4rem;max-height:220px;overflow-y:auto}
.st-slot{border:1px solid #dee2e6;background:#fff;border-radius:.4rem;padding:.4rem .3rem;font-size:.9rem;font-weight:600;color:#0d6efd;cursor:pointer;text-align:center;transition:all .12s}
.st-slot:hover:not(:disabled){border-color:#0d6efd;background:#eaf3ff}
.st-slot.active{background:#0d6efd;border-color:#0d6efd;color:#fff}
.st-slot:disabled{background:#f1f3f5;border-color:#e9ecef;color:#adb5bd;cursor:not-allowed;text-decoration:line-through}
.st-slot-empty{color:#6c757d;font-size:.9rem;padding:1rem 0;text-align:center}
</style>
<div id="st-app">
  <div id="st-msg" class="alert" style="display:none"></div>

  <div id="st-flexbar">
    <div><div class="st-meta" style="margin:0"><?php echo $STR['st_available_flex']; ?></div>
      <span class="st-flexval" id="st-flex">—</span></div>
    <div id="st-flexnote" class="st-meta" style="margin:0"></div>
  </div>

  <div id="st-tabs">
    <button class="st-tab active" data-tab="book"><?php echo $STR['tab_book']; ?></button>
    <button class="st-tab" data-tab="lessons"><?php echo $STR['tab_lessons']; ?></button>
    <button class="st-tab" data-tab="packages"><?php echo $STR['tab_packages']; ?></button>
    <button class="st-tab" data-tab="subavailable"><?php echo $STR['tab_subavailable']; ?></button>
    <button class="st-tab" data-tab="mysubs"><?php echo $STR['tab_mysubs']; ?></button>
  </div>

  <!-- ── Tab 1: Book a lesson ── -->
  <div class="st-panel active" id="panel-book">
    <div class="st-toolbar">
      <input id="st-teacher-search" class="form-control" placeholder="<?php echo s($STR['st_search_placeholder']); ?>">
      <button id="st-teacher-refresh" class="btn btn-outline-secondary"><?php echo $STR['ui_search']; ?></button>
    </div>
    <div id="st-teachers" class="st-grid"></div>
  </div>

  <!-- ── Tab 2: My lessons ── -->
  <div class="st-panel" id="panel-lessons">
    <div class="st-toolbar">
      <label class="m-0" for="st-filter"><?php echo $STR['st_status']; ?></label>
      <select id="st-filter" class="form-control">
        <option value=""><?php echo $STR['lf_all']; ?></option>
        <option value="pending"><?php echo $STR['lf_pending']; ?></option>
        <option value="waiting_student"><?php echo $STR['lf_waiting_student']; ?></option>
        <option value="waiting_teacher"><?php echo $STR['lf_waiting_teacher']; ?></option>
        <option value="confirmed"><?php echo $STR['lf_confirmed']; ?></option>
        <option value="in_progress"><?php echo $STR['lf_in_progress']; ?></option>
        <option value="completed"><?php echo $STR['lf_completed']; ?></option>
        <option value="student_absent"><?php echo $STR['lf_student_absent']; ?></option>
        <option value="teacher_absent"><?php echo $STR['lf_teacher_absent']; ?></option>
        <option value="cancelled"><?php echo $STR['lf_cancelled']; ?></option>
        <option value="cancelled_teacher"><?php echo $STR['lf_cancelled_teacher']; ?></option>
        <option value="rejected"><?php echo $STR['lf_rejected']; ?></option>
      </select>
      <button id="st-lessons-refresh" class="btn btn-outline-secondary"><?php echo $STR['ui_refresh']; ?></button>
    </div>
    <div id="st-lessons"></div>
  </div>

  <!-- ── Tab 3: Packages & Flex ── -->
  <div class="st-panel" id="panel-packages">
    <h5><?php echo $STR['availpkgs_heading']; ?></h5>
    <div id="st-available" class="st-grid"></div>

    <div class="st-section">
      <h5><?php echo $STR['mypackages']; ?></h5>
      <table class="st-table">
        <thead><tr><th><?php echo $STR['st_col_package']; ?></th><th><?php echo $STR['st_col_flexusedtot']; ?></th><th><?php echo $STR['st_col_status']; ?></th><th><?php echo $STR['st_col_activated']; ?></th><th><?php echo $STR['st_col_expires']; ?></th></tr></thead>
        <tbody id="st-mypackages"></tbody>
      </table>
    </div>

    <div class="st-section">
      <h5><?php echo $STR['st_payment_history']; ?></h5>
      <table class="st-table">
        <thead><tr><th><?php echo $STR['st_col_date']; ?></th><th><?php echo $STR['st_col_package']; ?></th><th><?php echo $STR['st_col_amount']; ?></th><th><?php echo $STR['st_col_method']; ?></th><th><?php echo $STR['st_col_transaction']; ?></th><th><?php echo $STR['st_col_status']; ?></th></tr></thead>
        <tbody id="st-payments"></tbody>
      </table>
    </div>

    <div class="st-section">
      <h5><?php echo $STR['st_flex_history']; ?></h5>
      <table class="st-table">
        <thead><tr><th><?php echo $STR['st_col_date']; ?></th><th><?php echo $STR['st_col_type']; ?></th><th><?php echo $STR['st_col_change']; ?></th><th><?php echo $STR['st_col_balance']; ?></th><th><?php echo $STR['st_col_lesson']; ?></th><th><?php echo $STR['st_col_note']; ?></th></tr></thead>
        <tbody id="st-flexhistory"></tbody>
      </table>
    </div>
  </div>

  <!-- ── Tab 4: Available subscriptions ── -->
  <div class="st-panel" id="panel-subavailable">
    <h5><?php echo $STR['sub_available_heading']; ?></h5>
    <div id="st-subavailable" class="st-grid"></div>
  </div>

  <!-- ── Tab 5: My subscriptions ── -->
  <div class="st-panel" id="panel-mysubs">
    <h5><?php echo $STR['sub_my_heading']; ?></h5>
    <table class="st-table">
      <thead><tr><th><?php echo $STR['sub_col_subscription']; ?></th><th><?php echo $STR['st_col_status']; ?></th><th><?php echo $STR['st_col_activated']; ?></th><th><?php echo $STR['st_col_expires']; ?></th><th><?php echo $STR['sub_col_daysleft']; ?></th><th><?php echo $STR['sub_col_courses']; ?></th></tr></thead>
      <tbody id="st-mysubs"></tbody>
    </table>

    <div class="st-section">
      <h5><?php echo $STR['sub_payments_heading']; ?></h5>
      <table class="st-table">
        <thead><tr><th><?php echo $STR['st_col_date']; ?></th><th><?php echo $STR['sub_col_subscription']; ?></th><th><?php echo $STR['st_col_amount']; ?></th><th><?php echo $STR['st_col_method']; ?></th><th><?php echo $STR['st_col_transaction']; ?></th><th><?php echo $STR['st_col_status']; ?></th></tr></thead>
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
      <button class="btn btn-outline-secondary" id="st-modal-cancel"><?php echo $STR['ui_cancel']; ?></button>
      <button class="btn btn-primary" id="st-modal-ok"><?php echo $STR['ui_confirm']; ?></button>
    </div>
  </div>
</div>
<?php
echo html_writer::script(<<<'JS'
(function () {
  var CFG = window.ACADEMY_ST;
  var STR = window.ACADEMY_STR || {};
  function $(id){return document.getElementById(id);}
  function el(tag,attrs,html){var e=document.createElement(tag);for(var k in (attrs||{})){e.setAttribute(k,attrs[k]);}if(html!=null){e.innerHTML=html;}return e;}
  // Localised string lookup; falls back to the key so a missing string is visible, not blank.
  function str(k){return (k in STR)?STR[k]:k;}
  // Like str() but fills Moodle {$a} / {$a->name} placeholders from a params object (or scalar).
  function strf(k,params){var s=str(k);if(params==null){return s;}if(typeof params!=='object'){return s.replace(/\{\$a\}/g,params);}return s.replace(/\{\$a->(\w+)\}/g,function(m,name){return (name in params)?params[name]:m;});}
  function msg(t,k){var e=$('st-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';window.scrollTo(0,0);if(k==='success'){setTimeout(function(){e.style.display='none';},3500);}}
  function parse(r){return r.text().then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error(str('err_sessionexpired'));}if(j.status!=='success'){throw new Error(j.error||str('err_requestfailed'));}return j.data;});}
  function esc(s){return (s==null?'':String(s)).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
  function fmt(ts){if(!ts){return '—';}var d=new Date(ts*1000);return d.toLocaleString();}
  function money(n){return Number(n||0).toFixed(2);}

  function apiGet(fn,params){
    var base={function:fn,token:CFG.token};if(CFG.lang){base.alang=CFG.lang;}
    var q=new URLSearchParams(Object.assign(base,params||{}));
    return fetch(CFG.endpoint+'?'+q.toString()).then(parse);
  }
  function apiPost(fn,params){
    var base={function:fn,token:CFG.token};if(CFG.lang){base.alang=CFG.lang;}
    var body=new URLSearchParams(Object.assign(base,params||{}));
    return fetch(CFG.endpoint,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()}).then(parse);
  }

  // "Y-m-dTH:i" local string <-> unix seconds
  function toUnix(localval){if(!localval){return 0;}var t=new Date(localval).getTime();return isNaN(t)?0:Math.floor(t/1000);}

  // ── Block-based availability picker ──
  // Renders the teacher's availability as day blocks + hourly time blocks. Slots that
  // fall outside the min-booking lead time or overlap a busy time are shown but disabled.
  // The chosen slot is written into `hidden` as a "Y-m-dTH:i" string (so toUnix() reads it).
  var SLOT_MINUTES = 60;                 // one lesson = 1 hour (lesson_manager::DEFAULT_DURATION)
  var DEFAULT_WINDOW = ['08:00','20:00']; // fallback when a teacher has no working hours set
  function pad2(n){return (n<10?'0':'')+n;}
  function localVal(d){return d.getFullYear()+'-'+pad2(d.getMonth()+1)+'-'+pad2(d.getDate())+'T'+pad2(d.getHours())+':'+pad2(d.getMinutes());}
  function timeToMin(t){var p=String(t).split(':');return (parseInt(p[0],10)||0)*60+(parseInt(p[1],10)||0);}
  function locale(){return CFG.lang||undefined;}

  function buildSlotPicker(field, hidden){
    var hours = field.teacherHours || [];
    var busy = field.busyTimes || [];
    var hasHours = hours.length > 0;
    var earliest = new Date(Date.now() + (CFG.min_booking_minutes || 0) * 60000);

    var wrap = el('div',{class:'st-slotpicker'});

    // Hourly slot objects for a given day, each flagged disabled if it's before the
    // min-booking lead time or overlaps a busy time.
    function daySlots(day){
      var intervals = hasHours
        ? hours.filter(function(h){return h.dayofweek === day.getDay();})
               .map(function(h){return [timeToMin(h.starttime), timeToMin(h.endtime)];})
        : [[timeToMin(DEFAULT_WINDOW[0]), timeToMin(DEFAULT_WINDOW[1])]];
      var mins = [];
      intervals.forEach(function(iv){
        for (var m = iv[0]; m + SLOT_MINUTES <= iv[1]; m += SLOT_MINUTES) { mins.push(m); }
      });
      mins.sort(function(a,b){return a-b;});
      return mins.map(function(m){
        var start = new Date(day.getTime()); start.setHours(0, m, 0, 0);
        var startU = Math.floor(start.getTime() / 1000);
        var endU = startU + SLOT_MINUTES * 60;
        var disabled = start < earliest || busy.some(function(bt){return startU < bt[1] && endU > bt[0];});
        return {start: start, disabled: disabled};
      });
    }

    // Every day in the next fortnight that still has at least one open slot.
    var days = [];
    for (var i = 0; i < 14; i++) {
      var d = new Date(); d.setHours(0,0,0,0); d.setDate(d.getDate() + i);
      if (daySlots(d).some(function(s){return !s.disabled;})) { days.push(d); }
    }
    if (!days.length) {
      wrap.appendChild(el('div',{class:'st-slot-empty'}, esc(str('st_slot_noavail'))));
      return wrap;
    }

    wrap.appendChild(el('div',{class:'st-slot-sub'}, esc(str('st_slot_pickday'))));
    var daysRow = el('div',{class:'st-slot-days'});
    wrap.appendChild(daysRow);
    wrap.appendChild(el('div',{class:'st-slot-sub'}, esc(str('st_slot_picktime'))));
    var timesRow = el('div',{class:'st-slot-times'});
    wrap.appendChild(timesRow);

    function renderTimes(day){
      timesRow.innerHTML = '';
      hidden.value = '';
      var slots = daySlots(day);
      if (!slots.length) {
        timesRow.appendChild(el('div',{class:'st-slot-empty'}, esc(str('st_slot_nodayslots'))));
        return;
      }
      slots.forEach(function(s){
        var b = el('button',{type:'button',class:'st-slot'}, esc(s.start.toLocaleTimeString(locale(), {hour:'2-digit', minute:'2-digit'})));
        if (s.disabled) { b.disabled = true; }
        else {
          b.onclick = function(){
            timesRow.querySelectorAll('.st-slot.active').forEach(function(x){x.classList.remove('active');});
            b.classList.add('active');
            hidden.value = localVal(s.start);
          };
        }
        timesRow.appendChild(b);
      });
    }

    days.forEach(function(day, idx){
      var b = el('button',{type:'button',class:'st-day'},
        '<span class="st-day-dow">'+esc(day.toLocaleDateString(locale(),{weekday:'short'}))+'</span>'+
        '<span class="st-day-date">'+esc(day.toLocaleDateString(locale(),{day:'numeric', month:'short'}))+'</span>');
      b.onclick = function(){
        daysRow.querySelectorAll('.st-day.active').forEach(function(x){x.classList.remove('active');});
        b.classList.add('active');
        renderTimes(day);
      };
      daysRow.appendChild(b);
      if (idx === 0) { b.classList.add('active'); renderTimes(day); }
    });

    return wrap;
  }

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
        if(f.type==='slotpicker'){
          var hidden=el('input',{type:'hidden'});
          inputs[f.name]=hidden;
          g.appendChild(hidden);
          g.appendChild(buildSlotPicker(f, hidden));
          body.appendChild(g);
          return;
        }
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
      ok.textContent=opts.okLabel||str('ui_confirm');

      function close(){
        bg.style.display='none';ok.onclick=null;cancel.onclick=null;
      }
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
        ? strf('st_book_up_to',{count:bal})
        : str('st_no_active_pkg');
      return bal;
    }).catch(function(){/* balance is best-effort */});
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Tab 1: Book a lesson
  // ──────────────────────────────────────────────────────────────────────────
  function teacherCard(t){
    var c=el('div',{class:'st-card'});
    c.appendChild(el('div',{class:'st-title'},esc(t.fullname||strf('st_teacher_num',t.userid))));
    if(t.headline){c.appendChild(el('div',{class:'st-meta'},esc(t.headline)));}
    var subs=el('div',{class:'st-subjects'});
    (t.subjects||[]).forEach(function(s){subs.appendChild(el('span',{class:'st-chip'},esc(s.subject)));});
    c.appendChild(subs);
    var actions=el('div',{class:'st-actions'});
    actions.appendChild(button(str('st_request_lesson'),'btn-primary',function(){bookWith(t);}));
    c.appendChild(actions);
    return c;
  }

  function bookWith(teacher){
    var subs=(teacher.subjects||[]).map(function(s){return {value:s.subject,label:s.subject};});
    if(!subs.length){msg(str('st_no_subjects'),'danger');return;}
    modal({
      title:strf('st_request_with',teacher.fullname||strf('st_teacher_num',teacher.userid)),
      okLabel:str('st_send_request'),
      fields:[
        {name:'subject',label:str('st_field_subject'),type:'select',options:subs},
        {name:'t',label:str('st_field_datetime'),type:'slotpicker', teacherHours: teacher.hours, busyTimes: teacher.busy_times},
        {name:'note',label:str('st_field_note_req'),type:'textarea',placeholder:str('st_note_placeholder')}
      ]
    }).then(function(r){
      if(!r){return;}
      var u=toUnix(r.t);
      if(!u){msg(str('st_pick_valid_time'),'danger');return;}
      if(!(r.note||'').trim()){msg(str('st_note_required'),'danger');return;}
      apiPost('request_lesson',{teacherid:teacher.userid,subject:r.subject,requested_time:u,note:r.note.trim()})
        .then(function(){msg(str('st_lesson_requested'),'success');switchTab('lessons');loadLessons();})
        .catch(function(e){msg(e.message,'danger');});
    });
  }

  function loadTeachers(){
    var subject=$('st-teacher-search').value.trim();
    apiGet('browse_teachers',subject?{subject:subject}:null).then(function(rows){
      var box=$('st-teachers');box.innerHTML='';
      if(!rows.length){box.appendChild(el('div',{class:'st-empty'},str('st_no_teachers')));return;}
      rows.forEach(function(t){box.appendChild(teacherCard(t));});
    }).catch(function(e){msg(e.message,'danger');});
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Tab 2: My lessons
  // ──────────────────────────────────────────────────────────────────────────
  var STATUS_LABEL={pending:str('lstat_pending'),waiting_student:str('lstat_waiting_student'),waiting_teacher:str('lstat_waiting_teacher'),confirmed:str('lstat_confirmed'),in_progress:str('lstat_in_progress'),completed:str('lstat_completed'),student_absent:str('lstat_student_absent'),teacher_absent:str('lstat_teacher_absent'),cancelled:str('lstat_cancelled'),cancelled_teacher:str('lstat_cancelled_teacher'),rejected:str('lstat_rejected')};

  function pendingReschedule(lesson){
    return (lesson.proposals||[]).filter(function(p){return p.type==='reschedule'&&p.status==='pending';})[0]||null;
  }

  function run(p){return p.then(function(){msg(str('la_done'),'success');loadLessons();refreshFlex();}).catch(function(e){msg(e.message,'danger');});}

  function doAction(lesson,action){
    var id=lesson.id;
    switch(action){
      case 'accept':
        return run(apiPost('student_respond_lesson',{lessonid:id,action:'accept'}));
      case 'reject':
        return modal({title:str('la_reject_title'),fields:[{name:'reject_reason',label:str('la_reason_optional')}]}).then(function(r){if(r){return run(apiPost('student_respond_lesson',{lessonid:id,action:'reject',reject_reason:r.reject_reason||''}));}});
      case 'suggest':
        return apiGet('get_teacher', {teacherid: lesson.teacherid}).then(function(t) {
          return modal({title:str('la_suggest_title'),fields:[{name:'t',label:str('la_suggested_time'),type:'slotpicker', teacherHours: t.hours, busyTimes: t.busy_times}]}).then(function(r){if(r){var u=toUnix(r.t);if(!u){msg(str('la_pick_valid_time'),'danger');return;}return run(apiPost('student_respond_lesson',{lessonid:id,action:'suggest',suggested_time:u}));}});
        });
      case 'cancel_request':
        return modal({title:str('la_withdraw_title'),text:str('la_withdraw_text'),fields:[{name:'reason',label:str('la_reason_optional')}]}).then(function(r){if(r){return run(apiPost('cancel_lesson_request',{lessonid:id,reason:r.reason||''}));}});
      case 'cancel':
        return modal({title:str('la_cancel_title'),text:str('la_cancel_text'),fields:[{name:'reason',label:str('la_reason_optional')}]}).then(function(r){if(r){return run(apiPost('cancel_lesson_student',{lessonid:id,reason:r.reason||''}));}});
      case 'report_teacher_absent':
        return modal({title:str('la_report_absent_title'),text:str('la_report_absent_text')}).then(function(r){if(r){return run(apiPost('report_teacher_absent',{lessonid:id}));}});
      case 'request_time_update':
        return apiGet('get_teacher', {teacherid: lesson.teacherid}).then(function(t) {
          return modal({title:str('la_newtime_title'),fields:[{name:'t',label:str('la_newtime_label'),type:'slotpicker', teacherHours: t.hours, busyTimes: t.busy_times}]}).then(function(r){if(r){var u=toUnix(r.t);if(!u){msg(str('la_pick_valid_time'),'danger');return;}return run(apiPost('request_time_update',{lessonid:id,proposed_time:u}));}});
        });
      case 'respond_time_update_accept':
        return run(apiPost('respond_time_update',{lessonid:id,action:'accept'}));
      case 'respond_time_update_reject':
        return run(apiPost('respond_time_update',{lessonid:id,action:'reject'}));
      case 'join':
        if(!lesson.join_url){msg(str('la_room_not_ready'),'danger');return;}
        window.open(lesson.join_url,'_blank');
        return;
    }
  }

  var ACTION_LABEL={accept:str('lact_accept'),reject:str('lact_reject'),suggest:str('lact_suggest'),cancel_request:str('lact_cancel_request'),cancel:str('lact_cancel'),report_teacher_absent:str('lact_report_teacher_absent'),request_time_update:str('lact_request_time_update'),join:str('lact_join')};

  function lessonCard(lesson){
    var c=el('div',{class:'st-card'});
    var top=el('div',{class:'st-top'});
    var left=el('div',{});
    left.appendChild(el('div',{class:'st-title'},strf('lc_title',{subject:esc(lesson.subject),teacher:esc(lesson.teacher_name||strf('lc_teacher_num',lesson.teacherid))})));
    var when=lesson.confirmed_time?strf('lc_confirmed',fmt(lesson.confirmed_time)):strf('lc_requested',fmt(lesson.requested_time));
    left.appendChild(el('div',{class:'st-meta'},when+' · '+strf('lc_duration',lesson.duration)));
    if(lesson.note){left.appendChild(el('div',{class:'st-meta'},strf('lc_your_note',esc(lesson.note))));}
    if(lesson.reject_reason){left.appendChild(el('div',{class:'st-meta'},strf('lc_reject_reason',esc(lesson.reject_reason))));}
    if(lesson.cancel_reason){left.appendChild(el('div',{class:'st-meta'},strf('lc_cancel_reason',esc(lesson.cancel_reason))));}
    if(lesson.flex_state&&lesson.flex_state!=='none'){left.appendChild(el('div',{class:'st-meta'},strf('lc_flex',esc(lesson.flex_state))));}
    top.appendChild(left);
    top.appendChild(el('span',{class:'st-badge s-'+lesson.status},STATUS_LABEL[lesson.status]||lesson.status));
    c.appendChild(top);

    // Pending reschedule banner.
    var resched=pendingReschedule(lesson);
    if(resched){
      var box=el('div',{class:'st-reschedule'});
      var who=resched.role==='student'?str('lc_you'):str('lc_the_teacher');
      box.innerHTML=strf('lc_resched_moved',{who:esc(who),time:fmt(resched.proposed_time)});
      c.appendChild(box);
    }

    var actions=el('div',{class:'st-actions'});
    (lesson.actions||[]).forEach(function(a){
      if(a==='view'){return;}
      if(a==='respond_time_update'){
        // Only the party who did NOT propose may respond.
        if(resched && resched.role!=='student'){
          actions.appendChild(button(str('lact_accept_newtime'),'btn-success',function(){doAction(lesson,'respond_time_update_accept');}));
          actions.appendChild(button(str('lact_reject_newtime'),'btn-outline-danger',function(){doAction(lesson,'respond_time_update_reject');}));
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
      if(!rows.length){list.appendChild(el('div',{class:'st-empty'},str('st_no_lessons')));return;}
      rows.forEach(function(l){list.appendChild(lessonCard(l));});
    }).catch(function(e){msg(e.message,'danger');});
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Tab 3: Packages & Flex
  // ──────────────────────────────────────────────────────────────────────────
  function packageCard(p,hasActive){
    var c=el('div',{class:'st-card'});
    c.appendChild(el('div',{class:'st-title'},esc(p.name)));
    c.appendChild(el('div',{class:'st-price',style:'font-size:1.3rem;font-weight:700;color:#084298'},money(p.price)+' '+str('ui_currency_egp')));
    var meta=strf('st_pkgmeta_flex',p.flex_count);
    meta += (Number(p.expiration_days)>0) ? strf('st_pkgmeta_validdays',p.expiration_days) : str('st_pkgmeta_neverexp');
    c.appendChild(el('div',{class:'st-meta'},meta));
    if(p.description){c.appendChild(el('div',{class:'st-meta'},esc(p.description)));}
    if(hasActive){c.appendChild(el('div',{class:'st-meta',style:'color:#856404;margin-top:.4rem'},str('st_already_active_pkg')));}
    var actions=el('div',{class:'st-actions'});
    actions.appendChild(button(str('st_buy_package'),'btn-primary',function(){buyPackage(p);},hasActive));
    c.appendChild(actions);
    return c;
  }

  function buyPackage(p){
    modal({
      title:strf('st_buy_title',p.name),
      text:strf('st_buy_text',{flex:p.flex_count,price:money(p.price)+' '+str('ui_currency_egp')}),
      okLabel:str('st_proceed_payment')
    }).then(function(r){
      if(r === null){return;}
      apiPost('create_package_checkout',{packageid:p.id})
        .then(function(d){ window.location.href = d.checkout_url; })
        .catch(function(e){msg(e.message,'danger');});
    });
  }

  var PKG_STATUS={active:str('pstat_active'),fully_used:str('pstat_fully_used'),expired:str('pstat_expired'),cancelled:str('pstat_cancelled'),pending:str('pstat_pending')};
  var PAY_STATUS={success:str('pay_completed'),completed:str('pay_completed'),pending:str('pay_pending'),failed:str('pay_failed'),refunded:str('pay_refunded')};
  var FLEX_TYPE={reserve:str('flx_reserve'),consume:str('flx_consume'),return:str('flx_return'),purchase:str('flx_purchase'),assign:str('flx_assign'),expire:str('flx_expire'),adjust:str('flx_adjust')};

  function loadPackages(){
    Promise.all([apiGet('get_available_packages'), apiGet('get_my_packages')]).then(function(results){
      var rows=results[0], myrows=results[1];
      var hasActive=myrows.some(function(p){return p.status==='active';});

      var box=$('st-available');box.innerHTML='';
      if(!rows.length){box.appendChild(el('div',{class:'st-empty'},str('st_pkg_none_available')));}
      else{rows.forEach(function(p){box.appendChild(packageCard(p,hasActive));});}

      var tb=$('st-mypackages');tb.innerHTML='';
      if(!myrows.length){tb.innerHTML='<tr><td colspan="5" class="text-muted">'+esc(str('st_pkg_none'))+'</td></tr>';return;}
      myrows.forEach(function(p){
        tb.innerHTML+='<tr><td>'+esc(p.name)+'</td>'+
          '<td>'+strf('st_flex_left',{remaining:esc(p.remaining_flex),used:esc(p.used_flex),total:esc(p.total_flex)})+'</td>'+
          '<td><span class="st-badge s-'+p.status+'">'+esc(PKG_STATUS[p.status]||p.status)+'</span></td>'+
          '<td>'+fmt(p.timeactivated)+'</td>'+
          '<td>'+(Number(p.expires_at)>0?fmt(p.expires_at):esc(str('ui_never')))+'</td></tr>';
      });
    }).catch(function(e){msg(e.message,'danger');});

    apiGet('get_payment_history').then(function(rows){
      var tb=$('st-payments');tb.innerHTML='';
      if(!rows.length){tb.innerHTML='<tr><td colspan="6" class="text-muted">'+esc(str('st_pay_none'))+'</td></tr>';return;}
      rows.forEach(function(x){
        tb.innerHTML+='<tr><td>'+fmt(x.timecreated)+'</td><td>'+esc(x.name)+'</td><td>'+money(x.amount)+'</td>'+
          '<td>'+esc(x.method)+'</td><td>'+esc(x.transaction_no)+'</td>'+
          '<td><span class="st-badge s-'+x.status+'">'+esc(PAY_STATUS[x.status]||x.status)+'</span></td></tr>';
      });
    }).catch(function(e){msg(e.message,'danger');});

    apiGet('get_flex_history').then(function(rows){
      var tb=$('st-flexhistory');tb.innerHTML='';
      if(!rows.length){tb.innerHTML='<tr><td colspan="6" class="text-muted">'+esc(str('st_flex_none'))+'</td></tr>';return;}
      rows.forEach(function(x){
        var sign=x.amount>0?('+'+x.amount):String(x.amount);
        tb.innerHTML+='<tr><td>'+fmt(x.timecreated)+'</td>'+
          '<td><span class="st-badge s-'+(x.type==='consume'?'rejected':(x.type==='reserve'?'pending':'active'))+'">'+esc(FLEX_TYPE[x.type]||x.type)+'</span></td>'+
          '<td>'+sign+'</td><td>'+x.balance_after+'</td>'+
          '<td>'+(x.lessonid?('#'+x.lessonid):'—')+'</td><td>'+esc(x.reason)+'</td></tr>';
      });
    }).catch(function(e){msg(e.message,'danger');});
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Tab 4: Subscriptions (US-SB-1-1, US-SB-1-2, US-SB-2-1)
  // ──────────────────────────────────────────────────────────────────────────
  var SUB_STATUS={active:str('sstat_active'),expired:str('sstat_expired'),cancelled:str('sstat_cancelled'),pending:str('sstat_pending'),payment_failed:str('sstat_payment_failed')};

  function courseChips(courses){
    var subs=el('div',{class:'st-subjects'});
    if(!courses||!courses.length){subs.appendChild(el('span',{class:'st-meta'},'—'));return subs;}
    courses.forEach(function(c){subs.appendChild(el('span',{class:'st-chip'},esc(c.fullname)));});
    return subs;
  }

  function subCard(s,hasActive){
    var c=el('div',{class:'st-card'});
    c.appendChild(el('div',{class:'st-title'},esc(s.name)));
    c.appendChild(el('div',{class:'st-price',style:'font-size:1.3rem;font-weight:700;color:#084298'},money(s.price)+' '+str('ui_currency_egp')));
    c.appendChild(el('div',{class:'st-meta'},strf('sub_days',s.duration_days)));
    if(s.description){c.appendChild(el('div',{class:'st-meta'},esc(s.description)));}
    c.appendChild(el('div',{class:'st-meta',style:'margin-top:.4rem'},str('sub_courses_label')));
    c.appendChild(courseChips(s.courses));
    if(hasActive){c.appendChild(el('div',{class:'st-meta',style:'color:#856404;margin-top:.4rem'},str('sub_already_active')));}
    var actions=el('div',{class:'st-actions'});
    actions.appendChild(button(str('sub_buy'),'btn-primary',function(){buySubscription(s);},hasActive));
    c.appendChild(actions);
    return c;
  }

  function buySubscription(s){
    modal({
      title:strf('sub_buy_title',s.name),
      text:strf('sub_buy_text',{days:s.duration_days,price:money(s.price)+' '+str('ui_currency_egp')}),
      okLabel:str('st_proceed_payment')
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
      if(!rows.length){box.appendChild(el('div',{class:'st-empty'},str('sub_none_available')));}
      else{rows.forEach(function(s){box.appendChild(subCard(s,hasActive));});}

      var tb=$('st-mysubs');tb.innerHTML='';
      if(!myrows.length){tb.innerHTML='<tr><td colspan="6" class="text-muted">'+esc(str('sub_none_mine'))+'</td></tr>';return;}
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
      if(!rows.length){tb.innerHTML='<tr><td colspan="6" class="text-muted">'+esc(str('sub_no_payments'))+'</td></tr>';return;}
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
