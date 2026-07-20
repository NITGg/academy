<?php
// Admin UI to price enrol_programs programs. The third-party plugin is never modified: prices live
// in academy_program_prices and buyers are allocated through the plugin's own public API.
//
// A program with no price stays free and keeps the plugin's own signup behaviour.
//
// The page also warns when a priced program still has the plugin's free self-signup source enabled,
// because that source publishes a URL which allocates a student for free no matter what the
// catalogue shows — hiding the Buy button would not close it.

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/academy/lib.php'); // local_academy_string_map()

admin_externalpage_setup('local_academy_manageprograms');
require_capability('local/academy:manageplatform', context_system::instance());

global $DB, $OUTPUT, $CFG, $PAGE;

$service = $DB->get_record('external_services', array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
$token = external_generate_token_for_current_user($service)->token;

$PAGE->set_title(get_string('manageprograms', 'local_academy'));
$PAGE->set_heading(get_string('manageprograms', 'local_academy'));
$PAGE->requires->js(new moodle_url('/local/academy/ui.js'), true);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageprograms', 'local_academy'));

$available = \local_academy\program_purchase_manager::available();
if (!$available) {
    echo $OUTPUT->notification(get_string('err_programsunavailable', 'local_academy'), 'warning');
    echo $OUTPUT->footer();
    die;
}

$STR = local_academy_string_map(array(
    'ui_refresh', 'ui_save', 'ui_loading', 'ui_pager_info',
    'prg_intro', 'prg_col_program', 'prg_col_price', 'prg_col_status', 'prg_col_sales',
    'prg_col_actions', 'prg_free', 'prg_paid', 'prg_archived', 'prg_notpublic',
    'prg_saved', 'prg_makefree_hint',
    'prg_bypass_warning', 'prg_bypass_badge', 'prg_close_free', 'prg_closed_free',
    'prg_needsopen_badge', 'prg_open_free', 'prg_opened_free',
    'prg_none', 'err_sessionexpired', 'err_requestfailed',
    // Page tabs + program settings (mirrors the settings tab on manage_packages.php / manage_subscriptions.php).
    'prg_tab_programs', 'prg_tab_settings',
    'set_program_expiry_reminder', 'set_program_expiry_reminder_help', 'set_save', 'set_saved',
));
echo html_writer::script('window.ACADEMY_PRG = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'token'    => $token,
    'lang'     => optional_param('lang', current_language(), PARAM_LANG),
)) . ';');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');
?>
<style>
#prg-app{max-width:1000px}
table.prg-table{width:100%;border-collapse:collapse}
table.prg-table th,table.prg-table td{border-bottom:1px solid #eee;padding:.5rem;text-align:left;font-size:.9rem;vertical-align:middle}
table.prg-table td.num,table.prg-table th.num{text-align:right;white-space:nowrap}
.prg-price-input{max-width:120px;display:inline-block}
.prg-badge{display:inline-block;padding:.1rem .5rem;border-radius:1rem;font-size:.78rem;font-weight:600}
.b-free{background:#e9ecef;color:#495057}.b-paid{background:#d4edda;color:#155724}
.b-warn{background:#f8d7da;color:#721c24}.b-muted{background:#f1f3f5;color:#868e96}
.prg-warn{border:1px solid #f5c2c7;background:#f8d7da;color:#58151c;border-radius:.5rem;padding:.75rem 1rem;margin-bottom:1rem;font-size:.9rem}
/* Page-level tabs: Programs / Program settings (mirrors manage_packages.php / manage_subscriptions.php). */
#prg-tabs{display:flex;gap:.25rem;border-bottom:1px solid #dee2e6;margin-bottom:1rem;flex-wrap:wrap}
#prg-tabs button{border:none;background:none;padding:.5rem .9rem;border-bottom:3px solid transparent;cursor:pointer}
#prg-tabs button.active{border-bottom-color:#0f6cbf;font-weight:600;color:#0f6cbf}
.prg-pane{display:none}
.prg-pane.active{display:block}
</style>
<div id="prg-app">
  <div id="prg-tabs">
    <button data-tab="programs" class="active"><?php echo $STR['prg_tab_programs']; ?></button>
    <button data-tab="settings"><?php echo $STR['prg_tab_settings']; ?></button>
  </div>

  <div class="prg-pane active" data-pane="programs">
  <div id="prg-msg" class="alert" style="display:none"></div>
  <p class="text-muted"><?php echo $STR['prg_intro']; ?></p>
  <div id="prg-bypass-warning" class="prg-warn" style="display:none"></div>
  <button id="prg-refresh" class="btn btn-outline-secondary mb-2"><?php echo $STR['ui_refresh']; ?></button>
  <table class="prg-table" id="prg-table"></table>
  <div id="prg-table-pager" class="acad-pager"></div>
  </div><!-- /pane programs -->

  <div class="prg-pane" data-pane="settings">
  <div id="prg-set-msg" class="alert" style="display:none"></div>
  <div class="card" style="max-width:560px;">
    <div class="card-body">
      <div class="form-group">
        <label for="s-program_expiry_reminder_days"><?php echo $STR['set_program_expiry_reminder']; ?></label>
        <input class="form-control" id="s-program_expiry_reminder_days" type="number" min="0">
        <small class="text-muted"><?php echo $STR['set_program_expiry_reminder_help']; ?></small>
      </div>
      <button id="prg-set-save" class="btn btn-primary"><?php echo $STR['set_save']; ?></button>
    </div>
  </div>
  </div><!-- /pane settings -->
</div>
<?php
echo html_writer::script(<<<'JS'
(function () {
  var CFG = window.ACADEMY_PRG;
  var STR = window.ACADEMY_STR || {};
  function str(k){return (k in STR)?STR[k]:k;}
  function $(id){return document.getElementById(id);}
  function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
  function money(n){return Number(n||0).toFixed(2);}
  function msg(t,k){var e=$('prg-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';if(k==='success'){setTimeout(function(){e.style.display='none';},3000);}}
  function parse(r){return r.text().then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error(str('err_sessionexpired'));}if(j.status!=='success'){throw new Error(j.error||str('err_requestfailed'));}return j.data;});}
  function apiGet(fn,p){var b={function:fn,token:CFG.token};if(CFG.lang){b.alang=CFG.lang;}return fetch(CFG.endpoint+'?'+new URLSearchParams(Object.assign(b,p||{}))).then(parse);}
  function apiPost(fn,p){var b={function:fn,token:CFG.token};if(CFG.lang){b.alang=CFG.lang;}var body=new URLSearchParams(Object.assign(b,p));
    return fetch(CFG.endpoint,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()}).then(parse);}

  var PAGE_SIZE = 10;

  function statusBadge(r){
    if(r.archived){return '<span class="prg-badge b-muted">'+esc(str('prg_archived'))+'</span>';}
    var out = r.paid
      ? '<span class="prg-badge b-paid">'+esc(str('prg_paid'))+'</span>'
      : '<span class="prg-badge b-free">'+esc(str('prg_free'))+'</span>';
    // A paid program whose free signup path is still open can be taken without paying.
    if(r.bypassable){out += ' <span class="prg-badge b-warn">'+esc(str('prg_bypass_badge'))+'</span>';}
    // A free program with no signup path open has no way in at all — worth flagging just as loudly.
    if(r.needsopen){out += ' <span class="prg-badge b-warn">'+esc(str('prg_needsopen_badge'))+'</span>';}
    if(!r.public){out += ' <span class="prg-badge b-muted">'+esc(str('prg_notpublic'))+'</span>';}
    return out;
  }

  function render(rows){
    var tb = $('prg-table').querySelector('tbody');
    tb.innerHTML = '';
    if(!rows.length){
      tb.innerHTML = '<tr><td colspan="5" class="text-muted">'+esc(str('prg_none'))+'</td></tr>';
      return;
    }
    rows.forEach(function(r){
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td>'+esc(r.fullname)+(r.idnumber?'<br><small class="text-muted">'+esc(r.idnumber)+'</small>':'')+'</td>'+
        '<td class="num"><input type="number" min="0" step="0.01" class="form-control prg-price-input" value="'+money(r.price)+'"> '+esc(r.currency)+'</td>'+
        '<td>'+statusBadge(r)+'</td>'+
        '<td class="num">'+r.sales+'</td>';
      var td = document.createElement('td');

      var save = document.createElement('button');
      save.className = 'btn btn-sm btn-primary';
      save.textContent = str('ui_save');
      save.onclick = function(){
        var val = tr.querySelector('.prg-price-input').value;
        apiPost('set_program_price',{programid:r.id, price:val}).then(function(){
          msg(str('prg_saved'),'success'); load();
        }).catch(function(e){msg(e.message,'danger');});
      };
      td.appendChild(save);

      // Only offered where it is actually needed — a paid program with the free path still open.
      if(r.bypassable){
        var close = document.createElement('button');
        close.className = 'btn btn-sm btn-danger ml-1';
        close.textContent = str('prg_close_free');
        close.onclick = function(){
          apiPost('disable_program_free_signup',{programid:r.id}).then(function(){
            msg(str('prg_closed_free'),'success'); load();
          }).catch(function(e){msg(e.message,'danger');});
        };
        td.appendChild(close);
      }
      // Symmetric case: a free program with no signup path open — nobody can join it at all.
      if(r.needsopen){
        var open = document.createElement('button');
        open.className = 'btn btn-sm btn-success ml-1';
        open.textContent = str('prg_open_free');
        open.onclick = function(){
          apiPost('enable_program_free_signup',{programid:r.id}).then(function(){
            msg(str('prg_opened_free'),'success'); load();
          }).catch(function(e){msg(e.message,'danger');});
        };
        td.appendChild(open);
      }
      tr.appendChild(td);
      tb.appendChild(tr);
    });
  }

  function load(){
    apiGet('list_program_prices').then(function(rows){
      $('prg-table').innerHTML =
        '<thead><tr><th>'+esc(str('prg_col_program'))+'</th><th class="num">'+esc(str('prg_col_price'))+'</th>'+
        '<th>'+esc(str('prg_col_status'))+'</th><th class="num">'+esc(str('prg_col_sales'))+'</th>'+
        '<th>'+esc(str('prg_col_actions'))+'</th></tr></thead><tbody></tbody>';

      // Surface the bypass risk once at the top too — it is easy to miss a badge in a long table.
      var risky = rows.filter(function(r){return r.bypassable;});
      var warn = $('prg-bypass-warning');
      if(risky.length){
        warn.textContent = str('prg_bypass_warning').replace('{$a}', risky.length);
        warn.style.display = 'block';
      } else {
        warn.style.display = 'none';
      }

      $('prg-table-pager').innerHTML = '';
      AcademyUI.paginate({rows:rows,pageSize:PAGE_SIZE,pagerEl:$('prg-table-pager'),
        labels:{info:str('ui_pager_info')},render:render});
    }).catch(function(e){msg(e.message,'danger');});
  }

  $('prg-refresh').onclick = load;
  load();
})();
JS
);

// ── Page-level tab switching (Programs / Program settings) ──
echo html_writer::script(<<<'JS'
(function () {
    var tabs = document.querySelectorAll('#prg-tabs button');
    Array.prototype.forEach.call(tabs, function (b) {
        b.onclick = function () {
            Array.prototype.forEach.call(tabs, function (x) { x.classList.remove('active'); });
            b.classList.add('active');
            var tab = b.getAttribute('data-tab');
            Array.prototype.forEach.call(document.querySelectorAll('.prg-pane'), function (p) {
                p.classList.toggle('active', p.getAttribute('data-pane') === tab);
            });
        };
    });
})();
JS
);

// ── "Program settings" tab ──
echo html_writer::script(<<<'JS'
(function () {
  var CFG = window.ACADEMY_PRG;
  var STR = window.ACADEMY_STR || {};
  function str(k){return (k in STR)?STR[k]:k;}
  function $(id){return document.getElementById(id);}
  function msg(t,k){var e=$('prg-set-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';if(k==='success'){setTimeout(function(){e.style.display='none';},3000);}}
  function api(func,params){var qs=new URLSearchParams({function:func,token:CFG.token});if(CFG.lang){qs.append('alang',CFG.lang);}Object.keys(params||{}).forEach(function(k){qs.append(k,params[k]);});
    return fetch(CFG.endpoint+'?'+qs.toString()).then(function(r){return r.text();}).then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error(str('err_sessionexpired'));}if(j.status!=='success'){throw new Error(j.error||str('err_requestfailed'));}return j.data;});}

  function load(){
    api('get_lesson_settings',{}).then(function(d){
      $('s-program_expiry_reminder_days').value = d.program_expiry_reminder_days;
    }).catch(function(e){msg(e.message,'danger');});
  }
  function save(){
    api('update_lesson_settings',{program_expiry_reminder_days:$('s-program_expiry_reminder_days').value}).then(function(d){
      $('s-program_expiry_reminder_days').value = d.program_expiry_reminder_days;
      msg(str('set_saved'),'success');
    }).catch(function(e){msg(e.message,'danger');});
  }
  $('prg-set-save').addEventListener('click', save);
  load();
})();
JS
);

echo $OUTPUT->footer();
