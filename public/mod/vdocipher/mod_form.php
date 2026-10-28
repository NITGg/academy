<?php
/**
 * The edit form for a VdoCipher Video activity.
 *
 * Video files are uploaded straight from the browser to VdoCipher's S3 (see the
 * inline uploader below) — the bytes never pass through PHP, so there is no
 * memory/timeout limit and large files work. The upload fills the read-only
 * "video id" field, which is all the form actually saves. A teacher can also
 * paste an existing id instead.
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_vdocipher_mod_form extends moodleform_mod {

    public function definition() {
        global $COURSE, $CFG;

        $mform = $this->_form;

        // ── General ──────────────────────────────────────────────────────────
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // ── Video source ─────────────────────────────────────────────────────
        $mform->addElement('header', 'videosource', get_string('videosource', 'vdocipher'));
        $mform->setExpanded('videosource', true);

        // Browser → S3 uploader (no bytes through PHP) — unless the AI assistant
        // is to write the transcript from this video, in which case local_nit_ai's
        // chunked uploader takes the file to our server instead (see the script).
        $credurl = (new moodle_url('/local/vdocipher/upload_credentials.php'))->out(false);
        $courseid = (int) $COURSE->id;
        $sesskey  = sesskey();

        // The script's messages, in the page language; {$a} is filled in by the script.
        $strings = [];
        foreach (['choose', 'requesting', 'progress', 'done', 'failed', 'neterror', 'nocreds', 'error'] as $key) {
            $strings[$key] = get_string('upload_' . $key, 'vdocipher', '{$a}');
        }

        $uploaderhtml = '
<div id="vdo-uploader" data-credurl="' . s($credurl) . '" data-courseid="' . $courseid . '" data-sesskey="' . s($sesskey) . '"
     data-strings="' . s(json_encode($strings, JSON_UNESCAPED_UNICODE)) . '"
     style="border:1px dashed var(--bs-border-color,#adb5bd);border-radius:.5rem;padding:1rem;margin:.25rem 0;">
  <input type="file" id="vdo-file" accept="video/*" style="display:block;margin-bottom:.5rem;">
  <div style="background:var(--bs-secondary-bg,#e9ecef);border-radius:.25rem;height:8px;overflow:hidden;margin:.5rem 0;">
    <div id="vdo-bar" style="height:8px;width:0;background:var(--bs-primary,#0d6efd);transition:width .2s;"></div>
  </div>
  <div id="vdo-status" style="font-size:.9em;color:var(--bs-secondary-color,#6c757d);">'
            . s(get_string('upload_choose', 'vdocipher')) . '</div>
</div>';
        $mform->addElement('static', 'videouploader', get_string('videofile', 'vdocipher'), $uploaderhtml);
        $mform->addHelpButton('videouploader', 'videofile', 'vdocipher');

        // The video id — filled by the uploader, or pasted manually.
        $mform->addElement('text', 'videoid', get_string('videoid', 'vdocipher'), ['size' => 48]);
        $mform->setType('videoid', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('videoid', 'videoid', 'vdocipher');

        // ── AI assistant ─────────────────────────────────────────────────────
        // Owned by local_nit_ai: the transcript is keyed on the course module,
        // not on VdoCipher, so the same fields drop into any video activity.
        \local_nit_ai\ui::add_form_elements($mform, $this->_cm);

        // ── Standard elements ────────────────────────────────────────────────
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();

        // Wire up the uploader (vanilla JS; no AMD build step needed).
        $mform->addElement('html', $this->uploader_script());
    }

    /**
     * Load the stored transcript back into the form.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        $cmid = (int) ($this->_cm->id ?? 0);
        $context = $cmid ? context_module::instance($cmid) : null;

        \local_nit_ai\ui::prepare_form_defaults($defaultvalues, $context, $cmid);
    }

    /**
     * Require a video id (uploaded or pasted), unless editing keeps the existing one.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $hasid   = !empty(trim($data['videoid'] ?? ''));
        $editing = !empty($this->_instance);
        if (!$hasid && !$editing) {
            $errors['videoid'] = get_string('err_novideosource', 'vdocipher');
        }
        return $errors;
    }

    /**
     * Self-contained uploader script: file → credentials → direct S3 POST → set id.
     *
     * @return string
     */
    protected function uploader_script(): string {
        return <<<'HTML'
<script>
(function () {
    var box = document.getElementById('vdo-uploader');
    if (!box) { return; }
    var input  = document.getElementById('vdo-file');
    var status = document.getElementById('vdo-status');
    var bar    = document.getElementById('vdo-bar');
    var field  = document.querySelector('input[name="videoid"]');
    var credurl  = box.getAttribute('data-credurl');
    var courseid = box.getAttribute('data-courseid');
    var sesskey  = box.getAttribute('data-sesskey');

    var str = JSON.parse(box.getAttribute('data-strings') || '{}');

    function say(msg) { status.textContent = msg; }
    function t(key, a) { return (str[key] || key).replace('{$a}', a === undefined ? '' : a); }

    // The AI assistant (local_nit_ai/js/upload.js) takes the video through our
    // server when the transcript is to be written from it.
    function viaserver() { return !!(window.NITAI_UPLOAD && window.NITAI_UPLOAD.wants()); }

    // Before anything is chosen, say which way the file will go — it changes with
    // the AI assistant's settings further down the form.
    var busy = false;
    function idle() {
        if (!busy) { say(viaserver() ? window.NITAI_UPLOAD.route : t('choose')); }
    }
    document.addEventListener('change', function (e) {
        if (e.target && /^nitai_(enabled|source)$/.test(e.target.name || '')) { idle(); }
    });
    window.addEventListener('load', idle);

    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) { return; }
        busy = true;
        bar.style.width = '0';

        var nameEl = document.querySelector('input[name="name"]');
        var title = (nameEl && nameEl.value.trim()) ? nameEl.value.trim() : file.name;

        if (viaserver()) {
            window.NITAI_UPLOAD.upload(file, title, {
                status: say,
                progress: function (pct, msg) { bar.style.width = pct + '%'; say(msg); },
                done: function (videoid, msg) { field.value = videoid; bar.style.width = '100%'; say(msg); },
                error: say
            });
            return;
        }

        say(t('requesting'));

        var cred = new FormData();
        cred.append('sesskey', sesskey);
        cred.append('courseid', courseid);
        cred.append('title', title);

        fetch(credurl, { method: 'POST', body: cred, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.error || !d.videoId || !d.clientPayload || !d.clientPayload.uploadLink) {
                    throw new Error(d.error || t('nocreds'));
                }
                var p = d.clientPayload;
                var s3 = new FormData();
                Object.keys(p).forEach(function (k) {
                    if (k !== 'uploadLink') { s3.append(k, p[k]); }
                });
                if (!('success_action_redirect' in p)) { s3.append('success_action_redirect', ''); }
                if (!('success_action_status' in p)) { s3.append('success_action_status', '201'); }
                s3.append('file', file);

                var xhr = new XMLHttpRequest();
                xhr.open('POST', p.uploadLink, true);
                xhr.upload.onprogress = function (e) {
                    if (e.lengthComputable) {
                        var pct = Math.round(e.loaded / e.total * 100);
                        bar.style.width = pct + '%';
                        say(t('progress', pct));
                    }
                };
                xhr.onload = function () {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        field.value = d.videoId;
                        bar.style.width = '100%';
                        say(t('done'));
                    } else {
                        say(t('failed', xhr.status));
                    }
                };
                xhr.onerror = function () { say(t('neterror')); };
                xhr.send(s3);
            })
            .catch(function (err) { say(t('error', err.message)); });
    });
})();
</script>
HTML;
    }
}
