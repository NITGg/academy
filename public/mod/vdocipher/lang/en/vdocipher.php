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
$string['videofile_help']    = 'Choose a video file to upload to VdoCipher. Best for modest file sizes — for very large videos, upload them on the VdoCipher dashboard (or the app) and paste the video ID below instead. If you both upload a file and paste an ID, the uploaded file wins.';
$string['videoid']           = '…or VdoCipher video ID';
$string['videoid_help']      = 'Paste the ID of a video that already exists in your VdoCipher account. Leave the upload field empty when using this.';
$string['err_novideosource'] = 'Add a video: upload a file or paste a VdoCipher video ID.';

// Index.
$string['noinstances']       = 'There are no VdoCipher Videos in this course.';

// Privacy.
$string['privacy:metadata']  = 'The VdoCipher Video plugin does not store personal data itself. When a video is played, the viewer\'s name and email are sent to VdoCipher to render a watermark on the stream.';
