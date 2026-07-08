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
    'b2b_reason_prompt', 'b2b_confirm_remove', 'b2b_confirm_remove_title',
    'b2b_confirm_reject_title', 'b2b_confirm_reject_body',
    'b2b_confirm_revoke_title', 'b2b_confirm_revoke_body',
    'b2b_tab_all', 'b2b_action_done', 'b2b_never', 'ui_loading', 'ui_cancel',
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

/* Confirm/reject/revoke dialogs — replace the native window.confirm()/prompt() ugliness. */
.b2b-modal-bg{position:fixed;inset:0;background:rgba(28,29,36,.55);display:none;align-items:center;justify-content:center;z-index:10000;padding:1rem;opacity:0;transition:opacity .18s ease}
.b2b-modal-bg.open{display:flex;opacity:1}
.b2b-modal{background:#fff;border-radius:1rem;max-width:420px;width:100%;box-shadow:0 24px 60px rgba(0,0,0,.28);overflow:hidden;transform:translateY(12px) scale(.98);transition:transform .18s ease}
.b2b-modal-bg.open .b2b-modal{transform:none}
.b2b-modal-head{display:flex;align-items:center;gap:.7rem;padding:1.25rem 1.4rem 0}
.b2b-modal-icon{width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.b2b-modal-icon svg{width:22px;height:22px}
.b2b-modal-icon.danger{background:#fdecec}
.b2b-modal-icon.danger svg{fill:#dc3545}
.b2b-modal-icon.warn{background:#fff4e5}
.b2b-modal-icon.warn svg{fill:#e07c00}
.b2b-modal-head h4{margin:0;font-size:1.15rem;font-weight:800;color:#1c1d1f}
.b2b-modal-body{padding:.9rem 1.4rem 0;color:#3c3c3c;font-size:.95rem;line-height:1.5}
.b2b-modal-body p{margin:0}
.b2b-modal-body label{display:block;font-size:.82rem;color:#6a6f73;margin-top:.9rem;margin-bottom:.3rem}
.b2b-modal-body textarea{width:100%;box-sizing:border-box;border:1px solid #d1d7dc;border-radius:.5rem;padding:.55rem .7rem;font-size:.9rem;resize:vertical;min-height:70px;font-family:inherit}
.b2b-modal-body textarea:focus{outline:none;border-color:#0f6cbf;box-shadow:0 0 0 3px rgba(15,108,191,.15)}
.b2b-modal-foot{display:flex;justify-content:flex-end;gap:.6rem;padding:1.2rem 1.4rem 1.3rem}
.b2b-modal-cancel{background:#fff;border:1px solid #d1d7dc;color:#3c3c3c;font-weight:600;font-size:.92rem;padding:.6rem 1.1rem;border-radius:.5rem;cursor:pointer}
.b2b-modal-cancel:hover{background:#f6f7f8}
.b2b-modal-ok{border:none;color:#fff;font-weight:700;font-size:.92rem;padding:.6rem 1.3rem;border-radius:.5rem;cursor:pointer}
.b2b-modal-ok.danger{background:#dc3545}
.b2b-modal-ok.danger:hover{background:#c82333}
.b2b-modal-ok.warn{background:#e07c00}
.b2b-modal-ok.warn:hover{background:#c26b00}

/* Existing invitation link — viewable + one-click copy. */
.b2b-invite-box{display:flex;align-items:center;gap:.5rem;background:#faf5ff;border:1px solid #ecdcfb;border-radius:.5rem;padding:.45rem .55rem;margin-bottom:.6rem}
.b2b-invite-box code{flex:1;min-width:0;word-break:break-all;font-size:.85rem;color:#4b2e83;background:none;padding:0}
.b2b-copy-btn{flex-shrink:0;border:1px solid #d1c4e9;background:#fff;border-radius:.4rem;padding:.32rem .55rem;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;font-size:.8rem;font-weight:600;color:#6a1b9a}
.b2b-copy-btn:hover{background:#f3e9fb}
.b2b-copy-btn svg{width:15px;height:15px;fill:currentColor}
.b2b-copy-btn.copied{color:#1f9d55;border-color:#b7e4c7;background:#eafaf0}
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

    var COPY_ICON = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M16 1H4a2 2 0 00-2 2v14h2V3h12V1zm3 4H8a2 2 0 00-2 2v14a2 2 0 002 2h11a2 2 0 002-2V7a2 2 0 00-2-2zm0 16H8V7h11v14z"/></svg>';
    // The link box shown for an active invitation (also reused right after generating a fresh link).
    function inviteBox(url, id){
        return '<div class="b2b-invite-box">' +
                   '<code id="b2b-invite-link">' + esc(url) + '</code>' +
                   '<button type="button" class="b2b-copy-btn" data-b2bact="copy" data-url="' + esc(url) + '">' + COPY_ICON + '<span>' + esc(str('b2b_copy')) + '</span></button>' +
               '</div>' +
               '<button class="btn btn-outline-secondary btn-sm" data-b2bact="revoke-invite" data-id="' + id + '">' + esc(str('b2b_revoke')) + '</button>';
    }
    function fallbackCopy(text){
        var ta = document.createElement('textarea');
        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.focus(); ta.select();
        try { document.execCommand('copy'); } catch(e){}
        document.body.removeChild(ta);
    }

    function renderInvites(invites){
        var active = (invites || []).filter(function(i){ return i.status === 'active'; })[0];
        var area = $('b2b-invite-area');
        if (!active){ area.innerHTML = '<span class="text-muted">' + esc(str('b2b_link_none')) + '</span>'; return; }
        if (active.url){
            // Existing active link — show it with a copy button + revoke.
            area.innerHTML = inviteBox(active.url, active.id);
        } else {
            // Link created before raw tokens were stored (pre-upgrade): can't re-display it, only revoke.
            area.innerHTML =
                '<span class="text-muted">' + esc(str('b2b_link_active')) + '</span> ' +
                '<button class="btn btn-outline-secondary btn-sm ml-2" data-b2bact="revoke-invite" data-id="' + active.id + '">' + esc(str('b2b_revoke')) + '</button>';
        }
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
                          '<button class="btn btn-sm btn-danger" data-b2bact="reject" data-id="' + m.id + '" data-name="' + esc(m.user_fullname) + '">' + esc(str('b2b_reject')) + '</button>';
            } else if (m.status === 'approved'){
                actions = '<button class="btn btn-sm btn-warning" data-b2bact="remove" data-id="' + m.id + '" data-name="' + esc(m.user_fullname) + '">' + esc(str('b2b_remove')) + '</button>';
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

    // ── Cool confirm/reject dialogs (replace window.confirm()/window.prompt()) ──
    var MODAL_ICONS = {
        warn: '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>',
        trash: '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M6 7h12l-1 14H7L6 7zm3-4h6l1 2h4v2H2V5h4l1-2z"/></svg>',
        link: '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M3.9 12a5 5 0 015-5h3v2h-3a3 3 0 000 6h3v2h-3a5 5 0 01-5-5zm7-1h4v2h-4v-2zm3-4h3a5 5 0 010 10h-3v-2h3a3 3 0 000-6h-3V7z"/></svg>'
    };
    function openModal(html){
        var bg = document.createElement('div');
        bg.className = 'b2b-modal-bg';
        bg.innerHTML = html;
        document.body.appendChild(bg);
        void bg.offsetWidth;
        bg.classList.add('open');
        return bg;
    }
    function closeModal(bg, cb){
        bg.classList.remove('open');
        setTimeout(function(){ if (bg.parentNode){ bg.parentNode.removeChild(bg); } cb(); }, 180);
    }
    // Simple yes/no confirmation. tone: 'danger' | 'warn'.
    function confirmAction(opts){
        return new Promise(function(resolve){
            var bg = openModal(
                '<div class="b2b-modal" role="dialog" aria-modal="true">' +
                    '<div class="b2b-modal-head"><span class="b2b-modal-icon ' + opts.tone + '">' + MODAL_ICONS[opts.icon] + '</span><h4>' + esc(opts.title) + '</h4></div>' +
                    '<div class="b2b-modal-body"><p>' + opts.body + '</p></div>' +
                    '<div class="b2b-modal-foot">' +
                        '<button type="button" class="b2b-modal-cancel">' + esc(str('ui_cancel')) + '</button>' +
                        '<button type="button" class="b2b-modal-ok ' + opts.tone + '">' + esc(opts.okLabel) + '</button>' +
                    '</div>' +
                '</div>'
            );
            function onKey(e){ if (e.key === 'Escape'){ finish(false); } }
            document.addEventListener('keydown', onKey);
            function finish(v){ document.removeEventListener('keydown', onKey); closeModal(bg, function(){ resolve(v); }); }
            bg.querySelector('.b2b-modal-cancel').onclick = function(){ finish(false); };
            bg.querySelector('.b2b-modal-ok').onclick = function(){ finish(true); };
            bg.onclick = function(e){ if (e.target === bg){ finish(false); } };
        });
    }
    // Reject with an optional reason textarea. Resolves the reason string, or null if cancelled.
    function promptReject(name){
        return new Promise(function(resolve){
            var bg = openModal(
                '<div class="b2b-modal" role="dialog" aria-modal="true">' +
                    '<div class="b2b-modal-head"><span class="b2b-modal-icon warn">' + MODAL_ICONS.warn + '</span><h4>' + esc(str('b2b_confirm_reject_title')) + '</h4></div>' +
                    '<div class="b2b-modal-body"><p>' + str('b2b_confirm_reject_body').replace('{name}', esc(name)) + '</p>' +
                        '<label>' + esc(str('b2b_reason_prompt')) + '</label>' +
                        '<textarea class="b2b-modal-reason"></textarea>' +
                    '</div>' +
                    '<div class="b2b-modal-foot">' +
                        '<button type="button" class="b2b-modal-cancel">' + esc(str('ui_cancel')) + '</button>' +
                        '<button type="button" class="b2b-modal-ok warn">' + esc(str('b2b_reject')) + '</button>' +
                    '</div>' +
                '</div>'
            );
            var ta = bg.querySelector('.b2b-modal-reason');
            setTimeout(function(){ ta.focus(); }, 60);
            function onKey(e){ if (e.key === 'Escape'){ finish(null); } }
            document.addEventListener('keydown', onKey);
            function finish(v){ document.removeEventListener('keydown', onKey); closeModal(bg, function(){ resolve(v); }); }
            bg.querySelector('.b2b-modal-cancel').onclick = function(){ finish(null); };
            bg.querySelector('.b2b-modal-ok').onclick = function(){ finish(ta.value); };
            bg.onclick = function(e){ if (e.target === bg){ finish(null); } };
        });
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
        var name = btn.getAttribute('data-name') || '';
        if (act === 'approve'){
            api('b2b_approve_member', {membershipid: id}, 'POST').then(function(){ msg(str('b2b_action_done'),'success'); loadDashboard(); }).catch(function(e){ msg(e.message,'danger'); });
        } else if (act === 'reject'){
            promptReject(name).then(function(reason){
                if (reason === null){ return; }
                api('b2b_reject_member', {membershipid: id, reason: reason}, 'POST').then(function(){ msg(str('b2b_action_done'),'success'); loadDashboard(); }).catch(function(e){ msg(e.message,'danger'); });
            });
        } else if (act === 'remove'){
            confirmAction({
                icon: 'trash', tone: 'danger',
                title: str('b2b_confirm_remove_title'),
                body: str('b2b_confirm_remove').replace('{name}', esc(name)),
                okLabel: str('b2b_remove')
            }).then(function(ok){
                if (!ok){ return; }
                api('b2b_remove_member', {membershipid: id}, 'POST').then(function(){ msg(str('b2b_action_done'),'success'); loadDashboard(); }).catch(function(e){ msg(e.message,'danger'); });
            });
        } else if (act === 'revoke-invite'){
            confirmAction({
                icon: 'link', tone: 'danger',
                title: str('b2b_confirm_revoke_title'),
                body: esc(str('b2b_confirm_revoke_body')),
                okLabel: str('b2b_revoke')
            }).then(function(ok){
                if (!ok){ return; }
                api('b2b_revoke_invite', {invitationid: id}, 'POST').then(function(){ msg(str('b2b_action_done'),'success'); loadDashboard(); }).catch(function(e){ msg(e.message,'danger'); });
            });
        } else if (act === 'copy'){
            var url = btn.getAttribute('data-url') || '';
            function flash(){
                btn.classList.add('copied');
                var s = btn.querySelector('span'); if (s){ s.textContent = str('b2b_copied'); }
                setTimeout(function(){ btn.classList.remove('copied'); var s2 = btn.querySelector('span'); if (s2){ s2.textContent = str('b2b_copy'); } }, 1600);
            }
            if (navigator.clipboard && navigator.clipboard.writeText){
                navigator.clipboard.writeText(url).then(flash).catch(function(){ fallbackCopy(url); flash(); });
            } else { fallbackCopy(url); flash(); }
        }
    });

    $('b2b-generate').addEventListener('click', function(){
        if (!CURRENT){ return; }
        api('b2b_generate_invite', {purchaseid: CURRENT}, 'POST').then(function(d){
            // Show the freshly generated link immediately with the same view/copy box.
            $('b2b-invite-area').innerHTML = inviteBox(d.url, d.id);
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
