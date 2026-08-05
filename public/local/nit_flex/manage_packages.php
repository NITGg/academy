<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Admin UI to manage lesson (Flex) packages: catalog CRUD, assign a package to a student, and the
 * user-packages table. Drives /local/nit_flex/api.php from vanilla JS. UI labels match the reference.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/nit_flex/lib.php');

admin_externalpage_setup('local_nit_flex_packages');
require_capability('local/nit_flex:managepackages', context_system::instance());

$PAGE->requires->js(new moodle_url('/local/nit_flex/ui.js'), true);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managepackages', 'local_nit_flex'));

$str = local_nit_flex_string_map([
    'pkg_new', 'ui_refresh', 'ui_loading', 'ui_active', 'ui_save', 'ui_cancel', 'ui_optional',
    'pkg_col_id', 'pkg_col_name', 'pkg_col_flexes', 'pkg_col_price', 'pkg_col_expdays',
    'pkg_col_status', 'pkg_col_actions', 'pkg_field_name_en', 'pkg_field_name_ar',
    'pkg_field_desc_en', 'pkg_field_desc_ar', 'pkg_field_flexcount', 'pkg_field_price', 'pkg_field_expdays',
    'pkg_userpackages', 'pkg_userpackages_desc', 'pkg_col_user', 'pkg_col_package', 'pkg_col_flex',
    'pkg_col_pricepaid', 'pkg_col_expiresat', 'pkg_unassign_title', 'pkg_unassign_refund', 'pkg_unassign',
    'ui_edit', 'ui_delete', 'ui_activate', 'ui_deactivate', 'ui_never',
    'pkg_none', 'pkg_edit_titled', 'pkg_confirm_delete', 'pkg_users_none',
    'pkg_unassign_confirm', 'pkg_unassign_paid',
    'msg_package_created', 'msg_package_updated', 'msg_package_activated',
    'msg_package_deactivated', 'msg_package_deleted', 'msg_package_unassigned',
    'err_requestfailed', 'err_sessionexpired', 'ui_pager_info',
    'pkg_tab_packages', 'pkg_tab_assign',
    'ap_student_label', 'ap_student_help', 'ap_package_label', 'ap_amount_label', 'ap_amount_placeholder',
    'ap_method_label', 'ap_method_offline', 'ap_method_bank', 'ap_method_wallet',
    'ap_reference_label', 'ap_reference_placeholder', 'ap_note_label', 'ap_submit',
    'ap_pkg_option', 'ap_no_packages', 'ap_enter_student', 'ap_assigned', 'ui_picker_student_ph',
    'ui_picker_searching', 'ui_picker_none', 'ui_picker_hint',
    'pkg_tab_settings', 'set_min_booking', 'set_cancel_deadline', 'set_update_deadline',
    'set_start_allowed', 'set_complete_allowed', 'set_absence_report',
    'set_lesson_start_reminder', 'set_lesson_start_reminder_help', 'set_reminder_add', 'set_reminder_placeholder',
    'set_expiry_reminder', 'set_expiry_reminder_help', 'set_teacher_percent', 'set_platform_percent',
    'set_percent_help', 'set_save', 'set_saved',
    'wd_reversal_title', 'wd_reversal_help', 'wd_lesson_id', 'wd_reason', 'wd_return_flex',
    'wd_enter_lesson', 'wd_flex_returned', 'err_reasonrequired', 'ui_currency_egp', 'ui_picker_lesson_ph',
]);

echo html_writer::script('window.NIT_PKG = ' . json_encode([
    'endpoint' => (new moodle_url('/local/nit_flex/api.php'))->out(false),
    'lessons'  => (new moodle_url('/local/nit_lessons/api.php'))->out(false),
    'sesskey'  => sesskey(),
    'lang'     => optional_param('lang', current_language(), PARAM_LANG),
]) . ';');
echo html_writer::script('window.NIT_STR = ' . json_encode($str) . ';');
?>
<div id="nit-pkg-app">
  <div id="pkg-tabs">
    <button data-tab="packages" class="active"><?php echo $str['pkg_tab_packages']; ?></button>
    <button data-tab="assign"><?php echo $str['pkg_tab_assign']; ?></button>
    <button data-tab="settings"><?php echo $str['pkg_tab_settings']; ?></button>
  </div>

  <div class="pkg-pane active" data-pane="packages">
    <div class="mb-3">
      <button id="pkg-new" class="btn btn-primary"><?php echo $str['pkg_new']; ?></button>
      <button id="pkg-refresh" class="btn btn-secondary"><?php echo $str['ui_refresh']; ?></button>
    </div>
    <div id="pkg-message" class="alert" style="display:none"></div>
    <table class="table table-striped" id="pkg-table">
      <thead><tr>
        <th><?php echo $str['pkg_col_id']; ?></th><th><?php echo $str['pkg_col_name']; ?></th>
        <th><?php echo $str['pkg_col_flexes']; ?></th><th><?php echo $str['pkg_col_price']; ?></th>
        <th><?php echo $str['pkg_col_expdays']; ?></th><th><?php echo $str['pkg_col_status']; ?></th>
        <th><?php echo $str['pkg_col_actions']; ?></th>
      </tr></thead>
      <tbody><tr><td colspan="7"><?php echo $str['ui_loading']; ?></td></tr></tbody>
    </table>
    <div id="pkg-table-pager" class="acad-pager"></div>

    <div id="pkg-form-card" class="card" style="display:none; max-width:560px;">
      <div class="card-body">
        <h4 id="pkg-form-title" class="card-title"><?php echo $str['pkg_new']; ?></h4>
        <input type="hidden" id="f-id">
        <div class="form-group"><label for="f-name-en"><?php echo $str['pkg_field_name_en']; ?></label>
          <input type="text" class="form-control" id="f-name-en" dir="ltr"></div>
        <div class="form-group"><label for="f-name-ar"><?php echo $str['pkg_field_name_ar']; ?></label>
          <input type="text" class="form-control" id="f-name-ar" dir="rtl"></div>
        <div class="form-group"><label for="f-desc-en"><?php echo $str['pkg_field_desc_en']; ?></label>
          <textarea class="form-control" id="f-desc-en" rows="2" dir="ltr"></textarea></div>
        <div class="form-group"><label for="f-desc-ar"><?php echo $str['pkg_field_desc_ar']; ?></label>
          <textarea class="form-control" id="f-desc-ar" rows="2" dir="rtl"></textarea></div>
        <div class="form-group"><label for="f-flex"><?php echo $str['pkg_field_flexcount']; ?></label>
          <input type="number" class="form-control" id="f-flex" min="1"></div>
        <div class="form-group"><label for="f-price"><?php echo $str['pkg_field_price']; ?></label>
          <input type="number" class="form-control" id="f-price" min="0" step="0.01"></div>
        <div class="form-group"><label for="f-exp"><?php echo $str['pkg_field_expdays']; ?></label>
          <input type="number" class="form-control" id="f-exp" min="0" value="0"></div>
        <div class="form-check mb-3">
          <input type="checkbox" class="form-check-input" id="f-active" checked>
          <label class="form-check-label" for="f-active"><?php echo $str['ui_active']; ?></label>
        </div>
        <button id="pkg-save" class="btn btn-primary"><?php echo $str['ui_save']; ?></button>
        <button id="pkg-cancel" class="btn btn-link"><?php echo $str['ui_cancel']; ?></button>
      </div>
    </div>

    <h4 class="mt-4"><?php echo $str['pkg_userpackages']; ?></h4>
    <p class="text-muted"><?php echo $str['pkg_userpackages_desc']; ?></p>
    <button id="refresh-users" class="btn btn-secondary mb-2"><?php echo $str['ui_refresh']; ?></button>
    <table class="table table-striped" id="users-table">
      <thead><tr>
        <th><?php echo $str['pkg_col_user']; ?></th><th><?php echo $str['pkg_col_package']; ?></th>
        <th><?php echo $str['pkg_col_flex']; ?></th><th><?php echo $str['pkg_col_pricepaid']; ?></th>
        <th><?php echo $str['pkg_col_status']; ?></th><th><?php echo $str['pkg_col_expiresat']; ?></th>
        <th><?php echo $str['pkg_col_actions']; ?></th>
      </tr></thead>
      <tbody><tr><td colspan="7"><?php echo $str['ui_loading']; ?></td></tr></tbody>
    </table>
    <div id="users-table-pager" class="acad-pager"></div>

    <div class="pkg-reversal">
      <h5><?php echo $str['wd_reversal_title']; ?></h5>
      <p class="text-muted" style="font-size:.88rem"><?php echo $str['wd_reversal_help']; ?></p>
      <div class="form-group"><label for="pkg-rev-lesson"><?php echo $str['wd_lesson_id']; ?></label>
        <div id="pkg-rev-lesson"></div></div>
      <div class="form-group"><label for="pkg-rev-reason"><?php echo $str['wd_reason']; ?></label>
        <input class="form-control" id="pkg-rev-reason"></div>
      <button id="pkg-rev-btn" class="btn btn-warning"><?php echo $str['wd_return_flex']; ?></button>
    </div>
  </div>

  <div class="pkg-pane" data-pane="assign">
    <div id="ap-app" style="max-width:560px">
      <div id="ap-msg" class="alert" style="display:none"></div>
      <div class="card"><div class="card-body">
        <div class="form-group"><label for="ap-student"><?php echo $str['ap_student_label']; ?></label>
          <div id="ap-student"></div>
          <small class="text-muted"><?php echo $str['ap_student_help']; ?></small></div>
        <div class="form-group"><label for="ap-package"><?php echo $str['ap_package_label']; ?></label>
          <select class="form-control" id="ap-package"></select></div>
        <div class="form-group"><label for="ap-amount"><?php echo $str['ap_amount_label']; ?></label>
          <input class="form-control" id="ap-amount" type="number" min="0" step="0.01" placeholder="<?php echo s($str['ap_amount_placeholder']); ?>"></div>
        <div class="form-group"><label for="ap-method"><?php echo $str['ap_method_label']; ?></label>
          <select class="form-control" id="ap-method">
            <option value="offline"><?php echo $str['ap_method_offline']; ?></option>
            <option value="bank"><?php echo $str['ap_method_bank']; ?></option>
            <option value="wallet"><?php echo $str['ap_method_wallet']; ?></option>
          </select></div>
        <div class="form-group"><label for="ap-reference"><?php echo $str['ap_reference_label']; ?></label>
          <input class="form-control" id="ap-reference" placeholder="<?php echo s($str['ap_reference_placeholder']); ?>"></div>
        <div class="form-group"><label for="ap-note"><?php echo $str['ap_note_label']; ?></label>
          <input class="form-control" id="ap-note"></div>
        <button id="ap-submit" class="btn btn-primary"><?php echo $str['ap_submit']; ?></button>
      </div></div>
    </div>
  </div>

  <div class="pkg-pane" data-pane="settings">
    <div id="set-msg" class="alert" style="display:none"></div>
    <div class="card" style="max-width:560px;"><div class="card-body">
      <div class="form-group"><label><?php echo $str['set_min_booking']; ?></label><input class="form-control" id="s-min_booking_minutes" type="number" min="0"></div>
      <div class="form-group"><label><?php echo $str['set_cancel_deadline']; ?></label><input class="form-control" id="s-cancel_deadline_minutes" type="number" min="0"></div>
      <div class="form-group"><label><?php echo $str['set_update_deadline']; ?></label><input class="form-control" id="s-update_deadline_minutes" type="number" min="0"></div>
      <div class="form-group"><label><?php echo $str['set_start_allowed']; ?></label><input class="form-control" id="s-start_allowed_minutes" type="number" min="0"></div>
      <div class="form-group"><label><?php echo $str['set_complete_allowed']; ?></label><input class="form-control" id="s-complete_allowed_minutes" type="number" min="0"></div>
      <div class="form-group"><label><?php echo $str['set_absence_report']; ?></label><input class="form-control" id="s-absence_report_minutes" type="number" min="0"></div>
      <div class="form-group">
        <label><?php echo $str['set_lesson_start_reminder']; ?></label>
        <div id="reminder-tags" style="margin-bottom:.5rem;display:flex;gap:.5rem;flex-wrap:wrap;"></div>
        <div class="input-group" style="max-width:200px;margin-bottom:.25rem;">
          <input class="form-control" id="reminder-add-input" type="number" min="1" placeholder="<?php echo s($str['set_reminder_placeholder']); ?>">
          <div class="input-group-append"><button class="btn btn-outline-secondary" type="button" id="reminder-add-btn"><?php echo $str['set_reminder_add']; ?></button></div>
        </div>
        <input type="hidden" id="s-lesson_start_reminder_minutes">
        <small class="text-muted"><?php echo $str['set_lesson_start_reminder_help']; ?></small>
      </div>
      <div class="form-group"><label><?php echo $str['set_expiry_reminder']; ?></label><input class="form-control" id="s-expiry_reminder_days" type="number" min="0"><small class="text-muted"><?php echo $str['set_expiry_reminder_help']; ?></small></div>
      <div class="form-group"><label><?php echo $str['set_teacher_percent']; ?></label><input class="form-control" id="s-teacher_percent" type="number" min="0" max="100"></div>
      <div class="form-group"><label><?php echo $str['set_platform_percent']; ?></label><input class="form-control" id="s-platform_percent" type="number" min="0" max="100"></div>
      <small class="text-muted"><?php echo $str['set_percent_help']; ?></small><br><br>
      <button id="set-save" class="btn btn-primary"><?php echo $str['set_save']; ?></button>
    </div></div>
  </div>

  <div id="unassign-modal-backdrop" class="academy-modal-backdrop" style="display:none;">
    <div class="academy-modal">
      <h5 class="academy-modal-title"><?php echo $str['pkg_unassign_title']; ?></h5>
      <p id="unassign-modal-text"></p>
      <div class="form-check">
        <input type="checkbox" class="form-check-input" id="unassign-refund-checkbox">
        <label class="form-check-label" for="unassign-refund-checkbox">
          <?php echo $str['pkg_unassign_refund']; ?> <span class="text-muted"><?php echo $str['ui_optional']; ?></span></label>
      </div>
      <div class="academy-modal-actions">
        <button id="unassign-modal-cancel" class="btn btn-link"><?php echo $str['ui_cancel']; ?></button>
        <button id="unassign-modal-confirm" class="btn btn-danger"><?php echo $str['pkg_unassign']; ?></button>
      </div>
    </div>
  </div>
</div>
<style>
#pkg-tabs{display:flex;gap:.25rem;border-bottom:1px solid #dee2e6;margin-bottom:1rem;flex-wrap:wrap}
#pkg-tabs button{border:none;background:none;padding:.5rem .9rem;border-bottom:3px solid transparent;cursor:pointer}
#pkg-tabs button.active{border-bottom-color:#0f6cbf;font-weight:600;color:#0f6cbf}
.pkg-pane{display:none}.pkg-pane.active{display:block}
#ap-app .form-group{margin-bottom:.75rem}
.academy-modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:1050}
.academy-modal{background:#fff;border-radius:10px;padding:1.5rem;max-width:440px;width:90%;box-shadow:0 12px 30px rgba(0,0,0,.25)}
.academy-modal-title{margin-bottom:.75rem;font-weight:600}
.academy-modal-actions{display:flex;justify-content:flex-end;gap:.5rem;margin-top:1.25rem}
.pkg-reversal{margin-top:2rem;border:1px solid #ffe082;background:#fff8e1;border-radius:.5rem;padding:1rem;max-width:560px}
.pkg-reversal .form-group{margin-bottom:.6rem}
</style>
<?php
echo html_writer::script(<<<'JS'
(function () {
  var CFG = window.NIT_PKG, STR = window.NIT_STR || {};
  function $(id){return document.getElementById(id);}
  function str(k){return (k in STR)?STR[k]:k;}
  function strf(k,params){var s=str(k);if(params==null){return s;}if(typeof params!=='object'){return s.replace(/\{\$a\}/g,params);}return s.replace(/\{\$a->(\w+)\}/g,function(m,n){return (n in params)?params[n]:m;});}
  function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
  var PAGE_SIZE=10, pkgPager=null, usersPager=null;
  function pagerLabels(){return {info:str('ui_pager_info')};}
  function msg(t,k){var e=$('pkg-message');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';if(k==='success'){setTimeout(function(){e.style.display='none';},3000);}}

  function api(fn,params,method){
    params=params||{};method=method||'GET';
    var data=new URLSearchParams({function:fn,sesskey:CFG.sesskey});
    if(CFG.lang){data.append('alang',CFG.lang);}
    Object.keys(params).forEach(function(k){data.append(k,params[k]);});
    var url=CFG.endpoint,opts={};
    if(method==='POST'){opts={method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:data.toString()};}
    else{url=CFG.endpoint+'?'+data.toString();}
    return fetch(url,opts).then(function(r){return r.text();}).then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error(str('err_sessionexpired'));}if(j.status!=='success'){throw new Error(j.error||str('err_requestfailed'));}return j.data;});
  }

  // ── Multilang EN/AR helpers ──
  function parseMultilang(value){
    var out={en:'',ar:''},raw=String(value==null?'':value),m,found=false;
    var re=/\{\s*mlang\s+([a-zA-Z0-9_-]+)\s*\}([\s\S]*?)\{\s*mlang\s*\}/g;
    while((m=re.exec(raw))!==null){found=true;var c=m[1].toLowerCase();if(c.indexOf('ar')===0){out.ar=m[2].trim();}else if(c.indexOf('en')===0){out.en=m[2].trim();}}
    if(!found){out.en=raw;}
    return out;
  }
  function buildMultilang(en,ar){en=String(en==null?'':en).trim();ar=String(ar==null?'':ar).trim();if(en&&ar){return '{mlang en}'+en+'{mlang}{mlang ar}'+ar+'{mlang}';}return en||ar;}
  function displayName(v){var p=parseMultilang(v);return [p.en,p.ar].filter(function(x){return x;}).join(' / ')||v||'';}

  function renderPkgRows(items){
    var tb=$('pkg-table').querySelector('tbody');tb.innerHTML='';
    items.forEach(function(p){
      var tr=document.createElement('tr');
      var toggle=p.status==='active'
        ?'<button class="btn btn-sm btn-warning" data-act="deactivate" data-id="'+p.id+'">'+esc(str('ui_deactivate'))+'</button>'
        :'<button class="btn btn-sm btn-success" data-act="activate" data-id="'+p.id+'">'+esc(str('ui_activate'))+'</button>';
      tr.innerHTML='<td>'+esc(p.id)+'</td><td>'+esc(displayName(p.name))+'</td><td>'+esc(p.flex_count)+'</td>'+
        '<td>'+esc(Number(p.price).toFixed(2))+'</td><td>'+esc(p.expiration_days)+'</td><td>'+esc(p.status)+'</td>'+
        '<td><button class="btn btn-sm btn-secondary" data-act="edit" data-id="'+p.id+'">'+esc(str('ui_edit'))+'</button> '+
        toggle+' <button class="btn btn-sm btn-danger" data-act="delete" data-id="'+p.id+'">'+esc(str('ui_delete'))+'</button></td>';
      tr._pkg=p;tb.appendChild(tr);
    });
  }
  function loadPackages(){
    var tb=$('pkg-table').querySelector('tbody');tb.innerHTML='<tr><td colspan="7">'+esc(str('ui_loading'))+'</td></tr>';
    api('get_packages').then(function(rows){
      if(!rows.length){tb.innerHTML='<tr><td colspan="7">'+esc(str('pkg_none'))+'</td></tr>';$('pkg-table-pager').innerHTML='';return;}
      if(pkgPager){pkgPager.setRows(rows);}else{pkgPager=NitUI.paginate({rows:rows,pageSize:PAGE_SIZE,pagerEl:$('pkg-table-pager'),labels:pagerLabels(),render:renderPkgRows});}
    }).catch(function(e){msg(e.message,'danger');});
  }
  function showForm(pkg){
    $('pkg-form-title').textContent=pkg?strf('pkg_edit_titled',pkg.id):str('pkg_new');
    var nm=parseMultilang(pkg?pkg.name:''),ds=parseMultilang(pkg?(pkg.description||''):'');
    $('f-id').value=pkg?pkg.id:'';$('f-name-en').value=nm.en;$('f-name-ar').value=nm.ar;
    $('f-desc-en').value=ds.en;$('f-desc-ar').value=ds.ar;
    $('f-flex').value=pkg?pkg.flex_count:'';$('f-price').value=pkg?Number(pkg.price).toFixed(2):'';
    $('f-exp').value=pkg?pkg.expiration_days:0;$('f-active').checked=pkg?(pkg.status==='active'):true;
    $('pkg-form-card').style.display='block';
  }
  function hideForm(){$('pkg-form-card').style.display='none';}
  function save(){
    var id=$('f-id').value;
    var params={name:buildMultilang($('f-name-en').value,$('f-name-ar').value),
      description:buildMultilang($('f-desc-en').value,$('f-desc-ar').value),
      flex_count:$('f-flex').value,price:$('f-price').value,expiration_days:$('f-exp').value||0};
    var p;
    if(id){params.id=id;params.status=$('f-active').checked?'active':'inactive';p=api('update_package',params,'POST');}
    else{params.active=$('f-active').checked?1:0;p=api('create_package',params,'POST');}
    p.then(function(){msg(id?str('msg_package_updated'):str('msg_package_created'),'success');hideForm();loadPackages();}).catch(function(e){msg(e.message,'danger');});
  }
  $('pkg-table').addEventListener('click',function(ev){
    var btn=ev.target.closest('button[data-act]');if(!btn){return;}
    var id=btn.getAttribute('data-id'),act=btn.getAttribute('data-act'),row=btn.closest('tr');
    if(act==='edit'){showForm(row._pkg);return;}
    if(act==='activate'){api('activate_package',{id:id},'POST').then(function(){msg(str('msg_package_activated'),'success');loadPackages();}).catch(function(e){msg(e.message,'danger');});}
    else if(act==='deactivate'){api('deactivate_package',{id:id},'POST').then(function(){msg(str('msg_package_deactivated'),'success');loadPackages();}).catch(function(e){msg(e.message,'danger');});}
    else if(act==='delete'){if(!confirm(str('pkg_confirm_delete'))){return;}api('delete_package',{id:id},'POST').then(function(){msg(str('msg_package_deleted'),'success');loadPackages();}).catch(function(e){msg(e.message,'danger');});}
  });
  $('pkg-new').addEventListener('click',function(){showForm(null);});
  $('pkg-refresh').addEventListener('click',loadPackages);
  $('pkg-save').addEventListener('click',save);
  $('pkg-cancel').addEventListener('click',hideForm);

  // ── User packages ──
  function renderUserRows(items){
    var tb=$('users-table').querySelector('tbody');tb.innerHTML='';
    items.forEach(function(r){
      var tr=document.createElement('tr');
      var toggle=r.status==='active'?'<button class="btn btn-sm btn-danger btn-unassign" data-id="'+r.id+'">'+esc(str('pkg_unassign'))+'</button>':'';
      var expires=r.expires_at>0?new Date(r.expires_at*1000).toLocaleString():str('ui_never');
      tr.innerHTML='<td>'+esc(r.user_fullname)+'<br><small class="text-muted">'+esc(r.user_email)+'</small></td>'+
        '<td>'+esc(displayName(r.name))+'</td><td>'+esc(r.remaining_flex)+' / '+esc(r.total_flex)+'</td>'+
        '<td>'+esc(Number(r.price_paid).toFixed(2))+'</td><td>'+esc(r.status)+'</td><td>'+expires+'</td><td>'+toggle+'</td>';
      tr._row=r;tb.appendChild(tr);
    });
  }
  function loadUsers(){
    var tb=$('users-table').querySelector('tbody');tb.innerHTML='<tr><td colspan="7">'+esc(str('ui_loading'))+'</td></tr>';
    api('get_all_user_packages').then(function(rows){
      if(!rows.length){tb.innerHTML='<tr><td colspan="7">'+esc(str('pkg_users_none'))+'</td></tr>';$('users-table-pager').innerHTML='';return;}
      if(usersPager){usersPager.setRows(rows);}else{usersPager=NitUI.paginate({rows:rows,pageSize:PAGE_SIZE,pagerEl:$('users-table-pager'),labels:pagerLabels(),render:renderUserRows});}
    }).catch(function(e){msg(e.message,'danger');});
  }
  var pendingUnassign=null;
  function openUnassign(row){pendingUnassign=row;var price=row.price_paid?strf('pkg_unassign_paid',Number(row.price_paid).toFixed(2)):'';
    $('unassign-modal-text').innerHTML=strf('pkg_unassign_confirm',{name:esc(displayName(row.name)),user:esc(row.user_fullname),price:price});
    $('unassign-refund-checkbox').checked=false;$('unassign-modal-backdrop').style.display='flex';}
  function closeUnassign(){pendingUnassign=null;$('unassign-modal-backdrop').style.display='none';}
  $('users-table').addEventListener('click',function(ev){var b=ev.target.closest('.btn-unassign');if(!b){return;}openUnassign(b.closest('tr')._row);});
  $('unassign-modal-cancel').addEventListener('click',closeUnassign);
  $('unassign-modal-backdrop').addEventListener('click',function(ev){if(ev.target===this){closeUnassign();}});
  $('unassign-modal-confirm').addEventListener('click',function(){var row=pendingUnassign;if(!row){return;}
    api('unassign_package',{purchaseid:row.id,refund:$('unassign-refund-checkbox').checked?1:0},'POST').then(function(){msg(str('msg_package_unassigned'),'success');closeUnassign();loadUsers();}).catch(function(e){msg(e.message,'danger');});});
  $('refresh-users').addEventListener('click',loadUsers);

  // ── Tabs ──
  var tabs=document.querySelectorAll('#pkg-tabs button');
  Array.prototype.forEach.call(tabs,function(b){b.onclick=function(){
    Array.prototype.forEach.call(tabs,function(x){x.classList.remove('active');});b.classList.add('active');
    var tab=b.getAttribute('data-tab');
    document.querySelectorAll('.pkg-pane').forEach(function(p){p.classList.toggle('active',p.getAttribute('data-pane')===tab);});
  };});

  // ── Assign tab ──
  var studentPicker=NitUI.userPicker({mount:$('ap-student'),placeholder:str('ui_picker_student_ph'),
    labels:{searching:str('ui_picker_searching'),none:str('ui_picker_none'),hint:str('ui_picker_hint')},
    search:function(q){return api('search_users',{query:q});}});
  function apMsg(t,k){var e=$('ap-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';}
  api('get_packages').then(function(rows){
    var sel=$('ap-package');
    rows.filter(function(p){return p.status==='active';}).forEach(function(p){
      var o=document.createElement('option');o.value=p.id;o.textContent=strf('ap_pkg_option',{name:displayName(p.name),flex:p.flex_count,price:Number(p.price).toFixed(2)});sel.appendChild(o);});
    if(!sel.options.length){apMsg(str('ap_no_packages'),'danger');}
  }).catch(function(e){apMsg(e.message,'danger');});
  $('ap-submit').onclick=function(){
    var sid=studentPicker.value();if(!sid){apMsg(str('ap_enter_student'),'danger');return;}
    api('assign_package',{studentid:sid,packageid:$('ap-package').value,amount:$('ap-amount').value||'0',
      method:$('ap-method').value,reference:$('ap-reference').value,note:$('ap-note').value},'POST')
      .then(function(d){apMsg(strf('ap_assigned',{name:d.package_name,flex:d.flex_balance,student:d.student_name}),'success');
        $('ap-amount').value='';$('ap-reference').value='';$('ap-note').value='';loadUsers();})
      .catch(function(e){apMsg(e.message,'danger');});
  };

  // ── Lessons endpoint helper (settings + reversal live in nit_lessons) ──
  function apiL(fn,params,method){
    params=params||{};method=method||'GET';
    var data=new URLSearchParams({function:fn,sesskey:CFG.sesskey});
    if(CFG.lang){data.append('alang',CFG.lang);}
    Object.keys(params).forEach(function(k){data.append(k,params[k]);});
    var url=CFG.lessons,opts={};
    if(method==='POST'){opts={method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:data.toString()};}
    else{url=CFG.lessons+'?'+data.toString();}
    return fetch(url,opts).then(function(r){return r.text();}).then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error(str('err_sessionexpired'));}if(j.status!=='success'){throw new Error(j.error||str('err_requestfailed'));}return j.data;});
  }

  // ── Package settings tab ──
  var SET_KEYS=['min_booking_minutes','cancel_deadline_minutes','update_deadline_minutes',
    'start_allowed_minutes','complete_allowed_minutes','absence_report_minutes',
    'expiry_reminder_days','teacher_percent','platform_percent'];
  function setMsg(t,k){var e=$('set-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';if(k==='success'){setTimeout(function(){e.style.display='none';},3000);}}
  function renderReminders(){
    var tags=$('reminder-tags'),input=$('s-lesson_start_reminder_minutes');if(!tags||!input){return;}
    tags.innerHTML='';
    (input.value||'').split(',').map(function(s){return s.trim();}).filter(function(s){return s!=='';}).forEach(function(val,idx){
      var tag=document.createElement('span');tag.className='badge badge-primary';
      tag.style.cssText='font-size:.9rem;padding:.4rem .6rem;display:inline-flex;align-items:center;gap:.4rem;';
      tag.innerHTML=val+' min <span style="cursor:pointer;font-weight:bold;" data-idx="'+idx+'">&times;</span>';
      tags.appendChild(tag);
    });
  }
  var setLoaded=false;
  function loadSettings(){
    apiL('get_settings').then(function(d){
      SET_KEYS.forEach(function(k){var el=$('s-'+k);if(el){el.value=d[k];}});
      $('s-lesson_start_reminder_minutes').value=d.lesson_start_reminder_minutes||'';
      renderReminders();
    }).catch(function(e){setMsg(e.message,'danger');});
  }
  $('reminder-tags').addEventListener('click',function(e){
    if(e.target.tagName==='SPAN'&&e.target.hasAttribute('data-idx')){
      var idx=parseInt(e.target.getAttribute('data-idx'),10),input=$('s-lesson_start_reminder_minutes');
      var vals=(input.value||'').split(',').map(function(s){return s.trim();}).filter(function(s){return s!=='';});
      vals.splice(idx,1);input.value=vals.join(',');renderReminders();
    }
  });
  $('reminder-add-btn').addEventListener('click',function(){
    var add=$('reminder-add-input'),input=$('s-lesson_start_reminder_minutes');
    var v=parseInt(add.value,10);if(!v||v<1){return;}
    var vals=(input.value||'').split(',').map(function(s){return s.trim();}).filter(function(s){return s!=='';});
    if(vals.indexOf(String(v))===-1){vals.push(String(v));}input.value=vals.join(',');add.value='';renderReminders();
  });
  $('set-save').addEventListener('click',function(){
    var params={lesson_start_reminder_minutes:$('s-lesson_start_reminder_minutes').value||''};
    SET_KEYS.forEach(function(k){params[k]=$('s-'+k).value||0;});
    apiL('update_settings',params,'POST').then(function(){setMsg(str('set_saved'),'success');}).catch(function(e){setMsg(e.message,'danger');});
  });

  // ── Flex reversal (US-FN-1-5) ──
  var lessonPicker=NitUI.picker({mount:$('pkg-rev-lesson'),placeholder:str('ui_picker_lesson_ph'),
    labels:{searching:str('ui_picker_searching'),none:str('ui_picker_none'),hint:str('ui_picker_hint')},
    search:function(q){return apiL('list_reversible_lessons',{query:q});},
    primary:function(l){return '#'+l.id+' — '+(l.subject||'');},
    secondary:function(l){return [l.student_name,l.teacher_name,l.lesson_time?new Date(l.lesson_time*1000).toLocaleString():'',Number(l.flex_value||0).toFixed(2)+' '+str('ui_currency_egp')].filter(function(x){return x;}).join(' • ');}});
  $('pkg-rev-btn').addEventListener('click',function(){
    var lessonid=lessonPicker.value(),reason=$('pkg-rev-reason').value;
    if(!lessonid){msg(str('wd_enter_lesson'),'danger');return;}
    if(!reason.trim()){msg(str('err_reasonrequired'),'danger');return;}
    apiL('reverse_flex',{lessonid:lessonid,reason:reason},'POST').then(function(){msg(str('wd_flex_returned'),'success');lessonPicker.clear();$('pkg-rev-reason').value='';loadUsers();}).catch(function(e){msg(e.message,'danger');});
  });

  // Load settings when the tab is first opened.
  Array.prototype.forEach.call(tabs,function(b){
    if(b.getAttribute('data-tab')==='settings'){
      b.addEventListener('click',function(){if(!setLoaded){setLoaded=true;loadSettings();}});
    }
  });

  loadPackages();
  loadUsers();
})();
JS
);
echo $OUTPUT->footer();
