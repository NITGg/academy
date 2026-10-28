<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']         = 'VdoCipher Video';
$string['modulename']         = 'VdoCipher Video';
$string['modulenameplural']   = 'VdoCipher Videos';
$string['modulename_help']    = 'The VdoCipher Video activity embeds a secure, DRM-protected video. Downloads and screen recording are blocked, and each viewer sees their name and email as a moving watermark.';
$string['pluginadministration'] = 'VdoCipher Video administration';

// Capabilities.
$string['vdocipher:addinstance'] = 'Add a new VdoCipher Video activity';
$string['vdocipher:view']        = 'View a VdoCipher Video';

// Form.
$string['videosource']       = 'Video';
$string['videofile']         = 'Upload a video';
$string['videofile_help']    = 'Choose a video file. It uploads directly from your browser to VdoCipher (it does not pass through the server), so large files and slow connections are fine — watch the progress bar. When it finishes, the video ID field below is filled automatically; then click Save.';
$string['videoid']           = 'VdoCipher video ID';
$string['videoid_help']      = 'Filled automatically after an upload finishes. You can also paste the ID of a video that already exists in your VdoCipher account instead of uploading.';
$string['err_novideosource'] = 'Add a video: upload a file or paste a VdoCipher video ID.';

// Uploader messages (shown by the form's script).
$string['upload_choose']     = 'Choose a video to upload it securely to VdoCipher.';
$string['upload_requesting'] = 'Requesting a secure upload…';
$string['upload_progress']   = 'Uploading… {$a}%';
$string['upload_done']       = 'Uploaded ✓ — the video ID is set. Click Save to finish.';
$string['upload_failed']     = 'Upload failed (HTTP {$a}). Try again, or paste a video ID.';
$string['upload_neterror']   = 'Upload error (network). Try again, or paste a video ID.';
$string['upload_nocreds']    = 'No upload credentials were returned.';
$string['upload_error']      = 'Error: {$a}';

// Index.
$string['noinstances']       = 'There are no VdoCipher Videos in this course.';

// Privacy.
$string['privacy:metadata']  = 'The VdoCipher Video plugin does not store personal data itself. When a video is played, the viewer\'s name and email are sent to VdoCipher to render a watermark on the stream.';
