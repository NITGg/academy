<?php
// Admin UI to configure certificates and their eligibility rules, per course. A course may have
// several certificates (Completion, Attendance, Excellence, ...), each with its own AND/OR ruleset.
// Plugin-agnostic: it only defines WHO is eligible; it does not touch mod_customcert or any
// certificate plugin. `externalref` optionally maps a certificate to the real activity later. Uses
// the local_academy HTTP API (api.php) with a mobile web-service token minted for the admin.

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
    'certeligibility', 'cert_desc', 'cert_course', 'cert_load', 'cert_operator', 'cert_op_and',
    'cert_op_or', 'cert_enabled', 'cert_add_rule', 'cert_no_rules', 'cert_saved', 'cert_deleted',
    'cert_rule', 'cert_pick', 'cert_note', 'cert_new', 'cert_none', 'cert_name', 'cert_type',
    'cert_type_completion', 'cert_type_attendance', 'cert_type_excellence', 'cert_type_custom',
    'cert_externalref', 'cert_externalref_help', 'cert_confirm_delete',
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

    <div class="form-row align-items-end mb-3">
        <div class="form-group col-md-6">
            <label for="cert-courseid"><?php echo $STR['cert_course']; ?></label>
            <input type="number" min="1" class="form-control" id="cert-courseid" placeholder="e.g. 12">
        </div>
        <div class="form-group col-md-3">
            <button id="cert-load" class="btn btn-secondary"><?php echo $STR['cert_load']; ?></button>
        </div>
    </div>

    <div id="cert-list-wrap" style="display:none;">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 class="m-0"><?php echo $STR['certeligibility']; ?></h4>
            <button id="cert-new" class="btn btn-primary btn-sm"><?php echo $STR['cert_new']; ?></button>
        </div>
        <div id="cert-list"></div>
    </div>

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

            <div class="form-group">
                <label for="c-externalref"><?php echo $STR['cert_externalref']; ?></label>
                <input type="number" min="0" class="form-control" id="c-externalref" value="0">
                <small class="form-text text-muted"><?php echo $STR['cert_externalref_help']; ?></small>
            </div>

            <div class="form-group">
                <label class="d-block"><strong><?php echo $STR['cert_operator']; ?></strong></label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="c-op" id="c-op-and" value="and" checked>
                    <label class="form-check-label" for="c-op-and"><?php echo $STR['cert_op_and']; ?></label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="c-op" id="c-op-or" value="or">
                    <label class="form-check-label" for="c-op-or"><?php echo $STR['cert_op_or']; ?></label>
                </div>
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="c-enabled" checked>
                <label class="form-check-label" for="c-enabled"><?php echo $STR['cert_enabled']; ?></label>
            </div>

            <div id="c-rules"></div>
            <div class="mb-3">
                <button id="c-add" class="btn btn-outline-primary btn-sm"><?php echo $STR['cert_add_rule']; ?></button>
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
    var catalogue = [], activities = {quizzes: [], assigns: []}, certs = [];

    var $ = function (id) { return document.getElementById(id); };

    function msg(text, kind) {
        var m = $('cert-message');
        m.className = 'alert alert-' + (kind || 'info');
        m.textContent = text;
        m.style.display = 'block';
    }
    function clearMsg() { $('cert-message').style.display = 'none'; }
    function courseId() { return parseInt($('cert-courseid').value, 10) || 0; }

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

    function renderFields(container, def, config) {
        container.innerHTML = '';
        (def.fields || []).forEach(function (field) {
            var wrap = document.createElement('div');
            wrap.className = 'form-group col-md-6';
            var label = document.createElement('label');
            label.textContent = field.label;
            wrap.appendChild(label);

            var input;
            if (field.type === 'select_quiz' || field.type === 'select_assign') {
                input = document.createElement('select');
                input.className = 'form-control cert-field';
                var list = field.type === 'select_quiz' ? activities.quizzes : activities.assigns;
                var idkey = field.type === 'select_quiz' ? 'quizid' : 'cmid';
                var ph = document.createElement('option');
                ph.value = ''; ph.textContent = STR.cert_pick;
                input.appendChild(ph);
                list.forEach(function (a) {
                    var o = document.createElement('option');
                    o.value = a[idkey]; o.textContent = a.name;
                    input.appendChild(o);
                });
            } else {
                input = document.createElement('input');
                input.type = 'number';
                input.className = 'form-control cert-field';
                if (field.min !== undefined && field.min !== null) { input.min = field.min; }
                if (field.max !== undefined && field.max !== null) { input.max = field.max; }
            }
            input.setAttribute('data-key', field.name);
            var val = config && config[field.name] !== undefined ? config[field.name]
                : (field.default !== undefined ? field.default : '');
            input.value = val;
            wrap.appendChild(input);
            container.appendChild(wrap);
        });
    }

    function addRuleRow(rule) {
        var row = document.createElement('div');
        row.className = 'card mb-2 cert-rule-row';
        row.innerHTML =
            '<div class="card-body">'
          + '  <div class="form-row align-items-end">'
          + '    <div class="form-group col-md-5">'
          + '      <label>' + STR.cert_rule + '</label>'
          + '      <select class="form-control cert-type"></select>'
          + '    </div>'
          + '    <div class="cert-fields form-row col-md-5"></div>'
          + '    <div class="form-group col-md-2 text-right">'
          + '      <button type="button" class="btn btn-outline-danger btn-sm cert-remove">' + STR.ui_delete + '</button>'
          + '    </div>'
          + '  </div>'
          + '</div>';
        var typeSel = row.querySelector('.cert-type');
        catalogue.forEach(function (c) {
            var o = document.createElement('option');
            o.value = c.type; o.textContent = c.label;
            typeSel.appendChild(o);
        });
        var fieldsBox = row.querySelector('.cert-fields');
        function refresh(config) { renderFields(fieldsBox, ruleDef(typeSel.value), config); }
        typeSel.addEventListener('change', function () { refresh(null); });
        row.querySelector('.cert-remove').addEventListener('click', function () { row.remove(); });

        if (rule && rule.type) { typeSel.value = rule.type; }
        refresh(rule ? rule.config : null);
        $('c-rules').appendChild(row);
    }

    function collectRules() {
        var rules = [];
        document.querySelectorAll('.cert-rule-row').forEach(function (row) {
            var type = row.querySelector('.cert-type').value;
            var config = {};
            row.querySelectorAll('.cert-field').forEach(function (inp) {
                config[inp.getAttribute('data-key')] = inp.value === '' ? 0 : inp.value;
            });
            rules.push({type: type, config: config});
        });
        return rules;
    }

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
            row.innerHTML =
                '<div class="card-body d-flex justify-content-between align-items-center">'
              + '  <div><strong>' + (c.name || ('#' + c.id)) + '</strong> '
              + '    <span class="badge badge-info">' + c.type + '</span>' + status
              + '    <div class="text-muted small">' + (c.rules.length) + ' rule(s), ' + c.operator.toUpperCase() + '</div>'
              + '  </div>'
              + '  <div>'
              + '    <button class="btn btn-outline-secondary btn-sm c-edit">' + STR.ui_edit + '</button> '
              + '    <button class="btn btn-outline-danger btn-sm c-del">' + STR.ui_delete + '</button>'
              + '  </div>'
              + '</div>';
            row.querySelector('.c-edit').addEventListener('click', function () { openEditor(c); });
            row.querySelector('.c-del').addEventListener('click', function () { del(c); });
            box.appendChild(row);
        });
    }

    function openEditor(cert) {
        clearMsg();
        $('c-id').value = cert ? cert.id : '';
        $('c-name').value = cert ? (cert.name || '') : '';
        $('c-type').value = cert ? cert.type : 'completion';
        $('c-externalref').value = cert ? cert.externalref : 0;
        $('c-op-and').checked = !cert || cert.operator !== 'or';
        $('c-op-or').checked = cert && cert.operator === 'or';
        $('c-enabled').checked = cert ? !!cert.enabled : true;
        $('c-rules').innerHTML = '';
        ((cert && cert.rules) || []).forEach(function (r) { addRuleRow(r); });
        if (!cert || !cert.rules || !cert.rules.length) { addRuleRow(null); }
        $('cert-editor').style.display = 'block';
        $('cert-editor').scrollIntoView({behavior: 'smooth'});
    }

    function load() {
        clearMsg();
        if (!courseId()) { msg(STR.cert_course, 'warning'); return; }
        api('get_certificates', {courseid: courseId()}).then(function (data) {
            catalogue = data.catalogue || [];
            activities = data.activities || {quizzes: [], assigns: []};
            certs = data.certificates || [];
            renderList();
            $('cert-list-wrap').style.display = 'block';
            $('cert-editor').style.display = 'none';
        }).catch(function (e) { msg(e.message, 'danger'); });
    }

    function save() {
        clearMsg();
        if (!courseId()) { return; }
        var payload = {
            id: $('c-id').value || 0,
            courseid: courseId(),
            name: $('c-name').value,
            type: $('c-type').value,
            externalref: parseInt($('c-externalref').value, 10) || 0,
            operator: $('c-op-or').checked ? 'or' : 'and',
            enabled: $('c-enabled').checked ? 1 : 0,
            rules: JSON.stringify(collectRules())
        };
        api('save_certificate', payload, 'POST').then(function () {
            msg(STR.cert_saved, 'success');
            load();
        }).catch(function (e) { msg(e.message, 'danger'); });
    }

    function del(cert) {
        if (!window.confirm(STR.cert_confirm_delete)) { return; }
        api('delete_certificate', {id: cert.id}, 'POST').then(function () {
            msg(STR.cert_deleted, 'success');
            load();
        }).catch(function (e) { msg(e.message, 'danger'); });
    }

    $('cert-load').addEventListener('click', load);
    $('cert-new').addEventListener('click', function () { openEditor(null); });
    $('c-add').addEventListener('click', function () { addRuleRow(null); });
    $('c-save').addEventListener('click', save);
    $('c-cancel').addEventListener('click', function () { $('cert-editor').style.display = 'none'; });
})();
</script>
<?php
echo $OUTPUT->footer();
