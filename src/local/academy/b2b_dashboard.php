<?php
// B2B administrator dashboard (US-B2B-1-2, 1-5..1-8): capacity, members, invitation links.
// Self-service page — any logged-in user; the API enforces that the caller owns the B2B subscription.

require('../../config.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/academy/lib.php'); // local_academy_string_map()

require_login();
if (isguestuser()) {
    redirect(new moodle_url('/login/index.php'));
}

global $DB, $OUTPUT, $CFG, $PAGE;

$service = $DB->get_record('external_services', array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
$token = external_generate_token_for_current_user($service)->token;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/academy/b2b_dashboard.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('b2b_dashboard_title', 'local_academy'));
$PAGE->set_heading(get_string('b2b_dashboard_title', 'local_academy'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('b2b_dashboard_title', 'local_academy'));

$STR = local_academy_string_map(array(
    'b2b_dashboard_title', 'b2b_no_subs', 'b2b_purchased', 'b2b_consumed', 'b2b_available', 'b2b_expires',
    'b2b_pending', 'b2b_approved', 'b2b_rejected', 'b2b_removed', 'b2b_expired', 'b2b_removed_returned', 'b2b_removed_kept',
    'b2b_invite_heading', 'b2b_generate', 'b2b_revoke', 'b2b_copy', 'b2b_copied', 'b2b_link_none', 'b2b_link_active',
    'b2b_members', 'b2b_col_user', 'b2b_col_status', 'b2b_col_seat', 'b2b_col_actions',
    'b2b_approve', 'b2b_reject', 'b2b_remove', 'b2b_seat_yes', 'b2b_seat_no', 'b2b_none',
    'b2b_reason_prompt', 'b2b_confirm_remove', 'b2b_tab_all', 'b2b_action_done', 'b2b_never', 'ui_loading',
    'err_sessionexpired', 'err_requestfailed',
));
echo html_writer::script('window.ACADEMY_B2B = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'token'    => $token,
    'lang'     => optional_param('lang', current_language(), PARAM_LANG),
)) . ';');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');
?>
<style>
#b2b-tabs { display:flex; gap:.25rem; border-bottom:1px solid #dee2e6; margin:1rem 0; flex-wrap:wrap; }
#b2b-tabs button { border:none; background:none; padding:.5rem .9rem; border-bottom:3px solid transparent; cursor:pointer; }
#b2b-tabs button.active { border-bottom-color:#0f6cbf; font-weight:600; color:#0f6cbf; }
.b2b-stats { display:flex; gap:1rem; flex-wrap:wrap; margin:1rem 0; }
.b2b-stat { background:#f8f9fa; border:1px solid #e5e7eb; border-radius:.6rem; padding:.8rem 1.2rem; min-width:120px; }
.b2b-stat .n { font-size:1.6rem; font-weight:800; color:#1c1d1f; }
.b2b-stat .l { font-size:.85rem; color:#6a6f73; }
#b2b-invite-link { word-break:break-all; background:#faf5ff; border:1px solid #ecdcfb; border-radius:.4rem; padding:.5rem .7rem; }
</style>
<div id="b2b-app">
    <div id="b2b-msg" class="alert" style="display:none"></div>
    <div class="form-group" id="b2b-sub-row" style="display:none; max-width:420px;">
        <label for="b2b-sub-select"><?php echo $STR['b2b_dashboard_title']; ?></label>
        <select id="b2b-sub-select" class="form-control"></select>
    </div>

    <div id="b2b-body" style="display:none;">
        <div class="b2b-stats" id="b2b-stats"></div>

        <div class="card" style="max-width:720px;">
            <div class="card-body">
                <h5><?php echo $STR['b2b_invite_heading']; ?></h5>
                <div id="b2b-invite-area"></div>
                <button id="b2b-generate" class="btn btn-primary btn-sm mt-2"><?php echo $STR['b2b_generate']; ?></button>
            </div>
        </div>

        <h5 class="mt-4"><?php echo $STR['b2b_members']; ?></h5>
        <div id="b2b-tabs">
            <button data-f="" class="active"><?php echo $STR['b2b_tab_all']; ?></button>
            <button data-f="pending"><?php echo $STR['b2b_pending']; ?></button>
            <button data-f="approved"><?php echo $STR['b2b_approved']; ?></button>
            <button data-f="rejected"><?php echo $STR['b2b_rejected']; ?></button>
            <button data-f="removed"><?php echo $STR['b2b_removed']; ?></button>
        </div>
        <table class="table table-striped" id="b2b-members-table">
            <thead>
                <tr>
                    <th><?php echo $STR['b2b_col_user']; ?></th>
                    <th><?php echo $STR['b2b_col_status']; ?></th>
                    <th><?php echo $STR['b2b_col_seat']; ?></th>
                    <th><?php echo $STR['b2b_col_actions']; ?></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <div id="b2b-empty" style="display:none;" class="alert alert-info"><?php echo $STR['b2b_no_subs']; ?></div>
</div>
<?php
echo html_writer::script(<<<'JS'
(function () {
    var CFG = window.ACADEMY_B2B;
    var STR = window.ACADEMY_STR || {};
    function str(k){return (k in STR)?STR[k]:k;}
    function $(id){return document.getElementById(id);}
    function esc(v){return (v==null?'':String(v)).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
    function fmtDate(ts){ if(!ts){return '—';} return new Date(ts*1000).toLocaleDateString(); }
    function msg(t,k){var e=$('b2b-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';if(k==='success'){setTimeout(function(){e.style.display='none';},3000);}}

    function api(fn, params, method){
        params = params || {}; method = method || 'GET';
        var data = new URLSearchParams({function: fn, token: CFG.token});
        if (CFG.lang){ data.append('alang', CFG.lang); }
        Object.keys(params).forEach(function(k){ data.append(k, params[k]); });
        var url = CFG.endpoint, opts = {};
        if (method === 'POST'){ opts = {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:data.toString()}; }
        else { url = CFG.endpoint + '?' + data.toString(); }
        return fetch(url, opts).then(function(r){return r.text();}).then(function(t){
            var j; try{ j = JSON.parse(t); }catch(e){ throw new Error(str('err_sessionexpired')); }
            if (j.status !== 'success'){ throw new Error(j.error || str('err_requestfailed')); }
            return j.data;
        });
    }

    var CURRENT = null;   // selected purchaseid
    var FILTER = '';      // members status filter
    var DASH = null;      // last dashboard payload

    function statusLabel(s){ var k='b2b_'+s; return str(k)!==k?str(k):s; }

    function renderStats(c){
        $('b2b-stats').innerHTML =
            '<div class="b2b-stat"><div class="n">' + esc(c.capacity) + '</div><div class="l">' + esc(str('b2b_purchased')) + '</div></div>' +
            '<div class="b2b-stat"><div class="n">' + esc(c.consumed) + '</div><div class="l">' + esc(str('b2b_consumed')) + '</div></div>' +
            '<div class="b2b-stat"><div class="n">' + esc(c.available) + '</div><div class="l">' + esc(str('b2b_available')) + '</div></div>' +
            '<div class="b2b-stat"><div class="n">' + esc(c.pending) + '</div><div class="l">' + esc(str('b2b_pending')) + '</div></div>' +
            '<div class="b2b-stat"><div class="n">' + esc(fmtDate(c.expires_at)) + '</div><div class="l">' + esc(str('b2b_expires')) + '</div></div>';
    }

    function renderInvites(invites){
        var active = (invites || []).filter(function(i){ return i.status === 'active'; })[0];
        var area = $('b2b-invite-area');
        if (!active){ area.innerHTML = '<span class="text-muted">' + esc(str('b2b_link_none')) + '</span>'; return; }
        // The raw token is shown only once (on generation) — we store just its hash. For an existing
        // active link we confirm it exists and offer revoke; "Generate" mints a fresh, shareable link.
        area.innerHTML =
            '<span class="text-muted">' + esc(str('b2b_link_active')) + '</span> ' +
            '<button class="btn btn-outline-secondary btn-sm ml-2" data-b2bact="revoke-invite" data-id="' + active.id + '">' + esc(str('b2b_revoke')) + '</button>';
    }

    function renderMembers(members){
        var tbody = $('b2b-members-table').querySelector('tbody');
        var rows = (members || []).filter(function(m){ return !FILTER || m.status === FILTER; });
        if (!rows.length){ tbody.innerHTML = '<tr><td colspan="4">' + esc(str('b2b_none')) + '</td></tr>'; return; }
        tbody.innerHTML = '';
        rows.forEach(function(m){
            var seat = m.consumes_seat ? str('b2b_seat_yes') : str('b2b_seat_no');
            var actions = '';
            if (m.status === 'pending'){
                actions = '<button class="btn btn-sm btn-success" data-b2bact="approve" data-id="' + m.id + '">' + esc(str('b2b_approve')) + '</button> ' +
                          '<button class="btn btn-sm btn-danger" data-b2bact="reject" data-id="' + m.id + '">' + esc(str('b2b_reject')) + '</button>';
            } else if (m.status === 'approved'){
                actions = '<button class="btn btn-sm btn-warning" data-b2bact="remove" data-id="' + m.id + '">' + esc(str('b2b_remove')) + '</button>';
            }
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + esc(m.user_fullname) + '<br><small class="text-muted">' + esc(m.user_email) + '</small></td>' +
                '<td>' + esc(statusLabel(m.status)) + '</td>' +
                '<td>' + esc(seat) + '</td>' +
                '<td>' + actions + '</td>';
            tbody.appendChild(tr);
        });
    }

    function loadDashboard(){
        if (!CURRENT){ return; }
        api('get_b2b_dashboard', {purchaseid: CURRENT}).then(function(d){
            DASH = d;
            $('b2b-body').style.display = 'block';
            renderStats(d.capacity);
            renderInvites(d.invitations);
            renderMembers(d.members);
        }).catch(function(e){ msg(e.message, 'danger'); });
    }

    // Tabs
    Array.prototype.forEach.call(document.querySelectorAll('#b2b-tabs button'), function(b){
        b.onclick = function(){
            Array.prototype.forEach.call(document.querySelectorAll('#b2b-tabs button'), function(x){ x.classList.remove('active'); });
            b.classList.add('active');
            FILTER = b.getAttribute('data-f');
            if (DASH){ renderMembers(DASH.members); }
        };
    });

    // Member + invite actions (event delegation on the app root).
    $('b2b-app').addEventListener('click', function(ev){
        var btn = ev.target.closest('button[data-b2bact]');
        if (!btn){ return; }
        var act = btn.getAttribute('data-b2bact');
        var id = btn.getAttribute('data-id');
        if (act === 'approve'){
            api('b2b_approve_member', {membershipid: id}, 'POST').then(function(){ msg(str('b2b_action_done'),'success'); loadDashboard(); }).catch(function(e){ msg(e.message,'danger'); });
        } else if (act === 'reject'){
            var reason = window.prompt(str('b2b_reason_prompt'), '');
            if (reason === null){ return; }
            api('b2b_reject_member', {membershipid: id, reason: reason}, 'POST').then(function(){ msg(str('b2b_action_done'),'success'); loadDashboard(); }).catch(function(e){ msg(e.message,'danger'); });
        } else if (act === 'remove'){
            if (!window.confirm(str('b2b_confirm_remove'))){ return; }
            api('b2b_remove_member', {membershipid: id}, 'POST').then(function(){ msg(str('b2b_action_done'),'success'); loadDashboard(); }).catch(function(e){ msg(e.message,'danger'); });
        } else if (act === 'revoke-invite'){
            api('b2b_revoke_invite', {invitationid: id}, 'POST').then(function(){ msg(str('b2b_action_done'),'success'); loadDashboard(); }).catch(function(e){ msg(e.message,'danger'); });
        }
    });

    $('b2b-generate').addEventListener('click', function(){
        if (!CURRENT){ return; }
        api('b2b_generate_invite', {purchaseid: CURRENT}, 'POST').then(function(d){
            // Show the freshly generated link immediately (the list endpoint never returns raw tokens).
            $('b2b-invite-area').innerHTML =
                '<div id="b2b-invite-link">' + esc(d.url) + '</div>' +
                '<button class="btn btn-outline-secondary btn-sm mt-2" data-b2bact="revoke-invite" data-id="' + d.id + '">' + esc(str('b2b_revoke')) + '</button>';
            msg(str('b2b_action_done'), 'success');
        }).catch(function(e){ msg(e.message, 'danger'); });
    });

    $('b2b-sub-select').addEventListener('change', function(){ CURRENT = this.value; loadDashboard(); });

    // Bootstrap: load the B2B subscriptions this user administers.
    api('get_my_b2b_subscriptions').then(function(subs){
        if (!subs.length){ $('b2b-empty').style.display = 'block'; return; }
        var sel = $('b2b-sub-select');
        subs.forEach(function(s){
            var o = document.createElement('option');
            o.value = s.purchaseid;
            o.textContent = s.name + ' (' + s.available + '/' + s.seats + ')';
            sel.appendChild(o);
        });
        if (subs.length > 1){ $('b2b-sub-row').style.display = 'block'; }
        CURRENT = String(subs[0].purchaseid);
        loadDashboard();
    }).catch(function(e){ msg(e.message, 'danger'); });
})();
JS
);
echo $OUTPUT->footer();
