/**
 * Chunked upload of a lesson video through this server (local_nit_ai/upload.php).
 *
 * Used by a video activity's own uploader when the teacher wants the transcript
 * written from the video: the file then goes once, to us, and the server hands it
 * on to the video provider and to the transcription service. It travels in chunks
 * because Cloudflare refuses any single request over 100 MB.
 *
 * Exposes window.NITAI_UPLOAD:
 *   wants()                 is the form asking for that right now?
 *   route                   one line saying so, for the uploader to show
 *   upload(file, title, cb) cb.status(msg), cb.progress(pct, msg), cb.done(videoid, msg), cb.error(msg)
 *
 * Plain script rather than AMD: this codebase avoids the grunt build step.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var box = document.getElementById('nitai-upload');
    if (!box) {
        return;
    }

    var url = box.getAttribute('data-url');
    var courseid = box.getAttribute('data-courseid');
    var sesskey = box.getAttribute('data-sesskey');
    var str = JSON.parse(box.getAttribute('data-strings') || '{}');

    /** A chunk that failed is tried again this many times before giving up. */
    var RETRIES = 3;

    function t(key, a) {
        return (str[key] || key).replace('{$a}', a === undefined ? '' : a);
    }

    function wants() {
        var on = document.getElementById('id_nitai_enabled');
        var source = document.querySelector('input[name="nitai_source"]:checked');
        return !!(on && on.checked && source && source.value === 'auto');
    }

    function post(fields, part) {
        var data = new FormData();
        data.append('sesskey', sesskey);
        data.append('courseid', courseid);
        Object.keys(fields).forEach(function(key) {
            data.append(key, fields[key]);
        });
        if (part) {
            data.append('chunk', part, 'chunk');
        }
        return fetch(url, {method: 'POST', body: data, credentials: 'same-origin'})
            .then(function(response) {
                return response.json();
            })
            .then(function(reply) {
                // An offset mismatch carries where to carry on from; anything else is a failure.
                if (reply.error && reply.received === undefined) {
                    throw new Error(reply.error);
                }
                return reply;
            });
    }

    function upload(file, title, cb) {
        var token = '';
        var chunk = 0;
        var offset = 0;
        var attempts = 0;
        var tokenfield = document.querySelector('input[name="nitai_uploadtoken"]');

        if (tokenfield) {
            tokenfield.value = ''; // Whatever was uploaded before is not this file.
        }
        cb.status(t('starting'));

        function fail(err) {
            cb.error(t('failed', err && err.message ? err.message : ''));
        }

        // Every request gets the same patience: a few retries, each waiting longer.
        function retry(step, err) {
            attempts++;
            if (attempts > RETRIES) {
                fail(err);
                return;
            }
            cb.status(t('retrying', attempts));
            setTimeout(step, 2000 * attempts);
        }

        function next() {
            if (offset >= file.size) {
                finish();
                return;
            }
            var pct = Math.floor(offset / file.size * 100);
            cb.progress(pct, t('progress', pct));

            post({action: 'chunk', token: token, offset: offset}, file.slice(offset, Math.min(offset + chunk, file.size)))
                .then(function(reply) {
                    attempts = 0;
                    offset = reply.received; // Also where to resume after an out-of-order chunk.
                    next();
                })
                .catch(function(err) {
                    retry(next, err);
                });
        }

        // Every byte is in: the server opens the video on the provider and says its id.
        function finish() {
            cb.progress(100, t('finishing'));
            post({action: 'finish', token: token, title: title})
                .then(function(reply) {
                    if (tokenfield) {
                        tokenfield.value = token;
                    }
                    cb.done(reply.videoid, t('done'));
                })
                .catch(function(err) {
                    retry(finish, err);
                });
        }

        post({action: 'start', filename: file.name, filesize: file.size})
            .then(function(reply) {
                token = reply.token;
                chunk = reply.chunk;
                next();
            })
            .catch(fail);
    }

    window.NITAI_UPLOAD = {wants: wants, upload: upload, route: t('route')};
})();
