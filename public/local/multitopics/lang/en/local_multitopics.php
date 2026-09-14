<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Multitopics course content API';

// Mobile app settings page (served by getsettings.php).
$string['appsettings'] = 'Mobile app settings';
$string['heading_access'] = 'Access';
$string['heading_access_desc'] = 'Everything on this page is published, without authentication, to anyone who requests <code>/local/multitopics/getsettings.php</code>. Do not put secrets here.';
$string['admin_token'] = 'Guest browsing token';
$string['admin_token_desc'] = 'Web-service token the app uses before sign-in (course catalogue, plans, coupons). Because it is public, it must be the dedicated read-only token created by <code>php local/multitopics/cli/app_guest_token.php --create</code>, never an administrator\'s token.';
$string['google_client_id'] = 'Google web client ID';
$string['google_client_id_desc'] = 'The <strong>Web application</strong> OAuth client ID (…apps.googleusercontent.com) the app signs in with. It must also be listed among the accepted client IDs of the Google auth plugin.';
$string['server_timeout_duration'] = 'Server timeout (seconds)';
$string['server_timeout_duration_desc'] = 'How long the app waits for a PDF to load before giving up.';
$string['heading_protection'] = 'Playback protection';
$string['prevent_screen_recording'] = 'Block screenshots and screen recording';
$string['prevent_screen_recording_desc'] = 'Fail-secure on the app side: only an explicit "off" here disables it.';
$string['watermark'] = 'Moving watermark on video';
$string['watermark_desc'] = 'Overlay the viewer\'s identity on video playback inside the app.';
$string['watermark_speed'] = 'Watermark drift speed';
$string['watermark_speed_desc'] = 'Decimal, e.g. 0.002.';
$string['watermark_fontsize'] = 'Watermark font size';
$string['watermark_fontsize_desc'] = 'Points, e.g. 16.';
$string['watermark_color'] = 'Watermark colour';
$string['watermark_color_desc'] = 'Leave empty for the app\'s animated colour cycle.';
$string['heading_store'] = 'Store versions';
$string['heading_store_desc'] = 'When a version here is higher than the installed one, the app shows a <strong>blocking</strong> "update required" dialog. Set it only when the new build is live on the store.';
$string['android_version'] = 'Android – latest version';
$string['android_url'] = 'Android – Play Store URL';
$string['ios_version'] = 'iOS – latest version';
$string['ios_url'] = 'iOS – App Store URL';
$string['version_desc'] = 'Three numbers, e.g. <code>1.4.2</code>. Anything else is not published.';
$string['heading_support'] = 'Support';
$string['whatsapp_phone'] = 'WhatsApp support number';
$string['whatsapp_phone_desc'] = 'International format; anything that is not a digit is stripped (e.g. 201001234567).';
$string['whatsapp_message'] = 'WhatsApp prefilled message';
$string['whatsapp_message_desc'] = 'Plain text; it is URL-encoded for the app automatically.';
