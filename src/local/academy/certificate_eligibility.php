<?php
// Admin UI to configure certificate eligibility for enrol_programs PROGRAMS. Course certificates are
// handled elsewhere (the customcert activity inside the course) — this page is program-only.
//
// Flow: pick a program from the list -> see its certificates -> create / update / delete. Each
// certificate carries exactly ONE eligibility rule (no AND/OR operator). Plugin-agnostic: it only
// defines WHO is eligible; it does not issue certificates. Uses the local_academy HTTP API (api.php)
// with a mobile web-service token minted for the admin.

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/academy/lib.php');

admin_externalpage_setup('local_academy_certeligibility');
require_capability('local/academy:manageplatform', context_system::instance());

global $DB, $OUTPUT, $CFG, $PAGE;

$service = $DB->get_record('external_services', array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
$token = external_generate_token_for_current_user($service)->token;

$PAGE->set_title(get_string('certeligibility', 'local_academy'));
$PAGE->set_heading(get_string('certeligibility', 'local_academy'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('certeligibility', 'local_academy'));

$STR = local_academy_string_map(array(
    'ui_save', 'ui_loading', 'ui_delete', 'ui_cancel', 'ui_edit',
    'certeligibility', 'cert_desc', 'cert_enabled', 'cert_saved', 'cert_deleted',
    'cert_rule', 'cert_pick', 'cert_note', 'cert_new', 'cert_none', 'cert_name', 'cert_type',
    'cert_type_completion', 'cert_type_attendance', 'cert_type_excellence', 'cert_type_custom',
    'cert_confirm_delete', 'cert_programs_heading', 'cert_manage', 'cert_back', 'cert_prog_certs',
    'err_sessionexpired', 'err_requestfailed',
));

echo html_writer::script('window.ACADEMY_CFG = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'token'    => $token,
    'lang'     => optional_param('lang', current_language(), PARAM_LANG),
)) . ';');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');
?>
<div id="cert-app" style="max-width:860px;">
    <p class="text-muted"><?php echo $STR['cert_desc']; ?></p>
    <div id="cert-message" class="alert" style="display:none;"></div>

    <!-- Program picker: the list of programs to attach certificates to. -->
    <div id="cert-programs-wrap">
        <h4><?php echo $STR['cert_programs_heading']; ?></h4>
        <div id="cert-programs"></div>
    </div>

    <!-- Certificates of the selected program. -->
    <div id="cert-list-wrap" style="display:none;">
        <button id="cert-back" class="btn btn-link px-0 mb-2">&larr; <?php echo $STR['cert_back']; ?></button>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 class="m-0" id="cert-prog-name"></h4>
            <button id="cert-new" class="btn btn-primary btn-sm"><?php echo $STR['cert_new']; ?></button>
        </div>
        <div id="cert-list"></div>
    </div>

    <!-- Editor: one certificate = one rule. -->
    <div id="cert-editor" class="card mt-3" style="display:none;">
        <div class="card-body">
            <input type="hidden" id="c-id">
            <div class="form-row">
                <div class="form-group col-md-7">
                    <label for="c-name"><?php echo $STR['cert_name']; ?></label>
                    <input type="text" class="form-control" id="c-name">
                </div>
                <div class="form-group col-md-5">
                    <label for="c-type"><?php echo $STR['cert_type']; ?></label>
                    <select class="form-control" id="c-type">
                        <option value="completion"><?php echo $STR['cert_type_completion']; ?></option>
                        <option value="attendance"><?php echo $STR['cert_type_attendance']; ?></option>
                        <option value="excellence"><?php echo $STR['cert_type_excellence']; ?></option>
                        <option value="custom"><?php echo $STR['cert_type_custom']; ?></option>
                    </select>
                </div>
            </div>

            <div class="form-row align-items-end">
                <div class="form-group col-md-6">
                    <label for="c-rule-type"><strong><?php echo $STR['cert_rule']; ?></strong></label>
                    <select class="form-control" id="c-rule-type"></select>
                </div>
                <div class="form-row col-md-6" id="c-rule-fields"></div>
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="c-enabled" checked>
                <label class="form-check-label" for="c-enabled"><?php echo $STR['cert_enabled']; ?></label>
            </div>

            <p class="text-muted small"><?php echo $STR['cert_note']; ?></p>
            <button id="c-save" class="btn btn-primary"><?php echo $STR['ui_save']; ?></button>
            <button id="c-cancel" class="btn btn-link"><?php echo $STR['ui_cancel']; ?></button>
        </div>
    </div>
</div>

<script>
(function () {
    var CFG = window.ACADEMY_CFG, STR = window.ACADEMY_STR;
    var catalogue = [], certs = [], programs = [];
    var currentProgram = null; // {id, fullname}

    var $ = function (id) { return document.getElementById(id); };

    function msg(text, kind) {
        var m = $('cert-message');
        m.className = 'alert alert-' + (kind || 'info');
        m.textContent = text;
        m.style.display = 'block';
    }
    function clearMsg() { $('cert-message').style.display = 'none'; }

    function api(fn, params, method) {
        var url = CFG.endpoint + '?function=' + fn + '&token=' + encodeURIComponent(CFG.token)
            + '&alang=' + encodeURIComponent(CFG.lang);
        var opts = {method: method || 'GET'};
        if (method === 'POST') {
            var body = new URLSearchParams();
            Object.keys(params || {}).forEach(function (k) { body.append(k, params[k]); });
            opts.body = body;
        } else {
            Object.keys(params || {}).forEach(function (k) {
                url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
            });
        }
        return fetch(url, opts).then(function (r) { return r.json(); }).then(function (j) {
            if (!j || j.status !== 'success') { throw new Error((j && j.error) || STR.err_requestfailed); }
            return j.data;
        });
    }

    function ruleDef(type) {
        for (var i = 0; i < catalogue.length; i++) { if (catalogue[i].type === type) { return catalogue[i]; } }
        return null;
    }

    // Render the config inputs for the currently chosen rule (program rules only need numbers/none).
    function renderRuleFields(config) {
        var container = $('c-rule-fields');
        container.innerHTML = '';
        var def = ruleDef($('c-rule-type').value);
        (def && def.fields || []).forEach(function (field) {
            var wrap = document.createElement('div');
            wrap.className = 'form-group col-12';
            var label = document.createElement('label');
            label.textContent = field.label;
            wrap.appendChild(label);

            var input = document.createElement('input');
            input.type = 'number';
            input.className = 'form-control cert-field';
            if (field.min !== undefined && field.min !== null) { input.min = field.min; }
            if (field.max !== undefined && field.max !== null) { input.max = field.max; }
            input.setAttribute('data-key', field.name);
            var val = config && config[field.name] !== undefined ? config[field.name]
                : (field.default !== undefined ? field.default : '');
            input.value = val;
            wrap.appendChild(input);
            container.appendChild(wrap);
        });
    }

    function collectRule() {
        var type = $('c-rule-type').value;
        var config = {};
        $('c-rule-fields').querySelectorAll('.cert-field').forEach(function (inp) {
            config[inp.getAttribute('data-key')] = inp.value === '' ? 0 : inp.value;
        });
        return {type: type, config: config};
    }

    // ── Programs list ──
    function renderPrograms() {
        var box = $('cert-programs');
        box.innerHTML = '';
        if (!programs.length) { box.innerHTML = '<p class="text-muted">—</p>'; return; }
        programs.forEach(function (p) {
            var row = document.createElement('div');
            row.className = 'card mb-2';
            row.innerHTML =
                '<div class="card-body d-flex justify-content-between align-items-center py-2">'
              + '  <strong></strong>'
              + '  <button class="btn btn-outline-primary btn-sm c-manage"></button>'
              + '</div>';
            row.querySelector('strong').textContent = p.fullname + ' (#' + p.id + ')';
            var btn = row.querySelector('.c-manage');
            btn.textContent = STR.cert_manage;
            btn.addEventListener('click', function () { openProgram(p); });
            box.appendChild(row);
        });
    }

    function loadPrograms() {
        clearMsg();
        api('list_programs_for_cert', {}).then(function (data) {
            programs = data.programs || [];
            renderPrograms();
        }).catch(function (e) { msg(e.message, 'danger'); });
    }

    function openProgram(program) {
        currentProgram = program;
        clearMsg();
        api('get_certificates', {scope: 'program', programid: program.id}).then(function (data) {
            catalogue = data.catalogue || [];
            certs = data.certificates || [];
            $('cert-prog-name').textContent = STR.cert_prog_certs.replace('{$a}', program.fullname);
            renderList();
            $('cert-programs-wrap').style.display = 'none';
            $('cert-list-wrap').style.display = 'block';
            $('cert-editor').style.display = 'none';
        }).catch(function (e) { msg(e.message, 'danger'); });
    }

    function backToPrograms() {
        currentProgram = null;
        $('cert-list-wrap').style.display = 'none';
        $('cert-editor').style.display = 'none';
        $('cert-programs-wrap').style.display = 'block';
    }

    // ── Certificates list ──
    function renderList() {
        var box = $('cert-list');
        box.innerHTML = '';
        if (!certs.length) {
            box.innerHTML = '<p class="text-muted">' + STR.cert_none + '</p>';
            return;
        }
        certs.forEach(function (c) {
            var row = document.createElement('div');
            row.className = 'card mb-2';
            var status = c.enabled ? '' : ' <span class="badge badge-secondary">off</span>';
            var rulelabel = (c.rules && c.rules[0]) ? ruleLabelOf(c.rules[0].type) : '';
            row.innerHTML =
                '<div class="card-body d-flex justify-content-between align-items-center">'
              + '  <div><strong></strong> '
              + '    <span class="badge badge-info"></span><span class="c-status"></span>'
              + '    <div class="text-muted small c-rulelabel"></div>'
              + '  </div>'
              + '  <div>'
              + '    <button class="btn btn-outline-secondary btn-sm c-edit"></button> '
              + '    <button class="btn btn-outline-danger btn-sm c-del"></button>'
              + '  </div>'
              + '</div>';
            row.querySelector('strong').textContent = c.name || ('#' + c.id);
            row.querySelector('.badge-info').textContent = c.type;
            row.querySelector('.c-status').innerHTML = status;
            row.querySelector('.c-rulelabel').textContent = rulelabel;
            var edit = row.querySelector('.c-edit'); edit.textContent = STR.ui_edit;
            var del = row.querySelector('.c-del'); del.textContent = STR.ui_delete;
            edit.addEventListener('click', function () { openEditor(c); });
            del.addEventListener('click', function () { removeCert(c); });
            box.appendChild(row);
        });
    }

    function ruleLabelOf(type) {
        var d = ruleDef(type);
        return d ? d.label : type;
    }

    // ── Editor ──
    function buildRuleTypeSelect() {
        var sel = $('c-rule-type');
        sel.innerHTML = '';
        catalogue.forEach(function (c) {
            var o = document.createElement('option');
            o.value = c.type; o.textContent = c.label;
            sel.appendChild(o);
        });
    }

    function openEditor(cert) {
        clearMsg();
        buildRuleTypeSelect();
        $('c-id').value = cert ? cert.id : '';
        $('c-name').value = cert ? (cert.name || '') : '';
        $('c-type').value = cert ? cert.type : 'completion';
        $('c-enabled').checked = cert ? !!cert.enabled : true;

        var rule = cert && cert.rules && cert.rules[0] ? cert.rules[0] : null;
        if (rule && rule.type) { $('c-rule-type').value = rule.type; }
        renderRuleFields(rule ? rule.config : null);

        $('cert-editor').style.display = 'block';
        $('cert-editor').scrollIntoView({behavior: 'smooth'});
    }

    function save() {
        clearMsg();
        if (!currentProgram) { return; }
        var payload = {
            id: $('c-id').value || 0,
            programid: currentProgram.id,
            name: $('c-name').value,
            type: $('c-type').value,
            operator: 'and', // single rule — operator is irrelevant but the API expects the field.
            enabled: $('c-enabled').checked ? 1 : 0,
            rules: JSON.stringify([collectRule()])
        };
        api('save_certificate', payload, 'POST').then(function () {
            msg(STR.cert_saved, 'success');
            openProgram(currentProgram);
        }).catch(function (e) { msg(e.message, 'danger'); });
    }

    function removeCert(cert) {
        if (!window.confirm(STR.cert_confirm_delete)) { return; }
        api('delete_certificate', {id: cert.id}, 'POST').then(function () {
            msg(STR.cert_deleted, 'success');
            openProgram(currentProgram);
        }).catch(function (e) { msg(e.message, 'danger'); });
    }

    $('c-rule-type').addEventListener('change', function () { renderRuleFields(null); });
    $('cert-back').addEventListener('click', backToPrograms);
    $('cert-new').addEventListener('click', function () { openEditor(null); });
    $('c-save').addEventListener('click', save);
    $('c-cancel').addEventListener('click', function () { $('cert-editor').style.display = 'none'; });

    loadPrograms();
})();
</script>
<?php
echo $OUTPUT->footer();
