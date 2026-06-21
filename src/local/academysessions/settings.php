<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_academysessions', get_string('pluginname', 'local_academysessions'));

    $settings->add(new admin_setting_heading(
        'local_academysessions/jitsi_heading',
        'Jitsi Meet Settings',
        'Configure your self-hosted Jitsi Meet server'
    ));

    $settings->add(new admin_setting_configtext(
        'local_academysessions/jitsi_host',
        'Jitsi Host',
        'Hostname and port of your Jitsi server (e.g. localhost:8443 or jitsi.yourdomain.com)',
        'localhost:8443'
    ));

    $settings->add(new admin_setting_configtext(
        'local_academysessions/excalidraw_host',
        'Excalidraw Room Server (WebSocket relay)',
        'Hostname and port of the Socket.IO relay server (e.g. localhost:9090)',
        'localhost:9090'
    ));

    $settings->add(new admin_setting_configtext(
        'local_academysessions/excalidraw_app',
        'Excalidraw App URL',
        'Full URL of the Excalidraw frontend app served on port 9091 (e.g. http://localhost:9091)',
        'http://localhost:9091'
    ));

    $settings->add(new admin_setting_heading(
        'local_academysessions/minio_heading',
        'MinIO Storage Settings',
        'S3-compatible storage for Jitsi recordings'
    ));

    $settings->add(new admin_setting_configtext(
        'local_academysessions/minio_endpoint',
        'MinIO Endpoint',
        'MinIO server URL (e.g. http://minio:9000)',
        'http://minio:9000'
    ));

    $settings->add(new admin_setting_configtext(
        'local_academysessions/minio_access_key',
        'MinIO Access Key',
        '',
        'minioadmin'
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_academysessions/minio_secret_key',
        'MinIO Secret Key',
        '',
        'minioadmin123'
    ));

    $settings->add(new admin_setting_configtext(
        'local_academysessions/minio_bucket',
        'MinIO Bucket',
        'Bucket name for recordings',
        'academy-recordings'
    ));

    $settings->add(new admin_setting_heading(
        'local_academysessions/bunny_heading',
        'Bunny Stream Settings',
        'CDN delivery for recorded sessions'
    ));

    $settings->add(new admin_setting_configtext(
        'local_academysessions/bunny_api_key',
        'Bunny Stream API Key',
        'API key from bunny.net Account Settings',
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_academysessions/bunny_library_id',
        'Bunny Stream Library ID',
        'Video Library ID from Bunny Stream dashboard',
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_academysessions/bunny_cdn_hostname',
        'Bunny CDN Hostname',
        'CDN hostname for video playback (e.g. vz-abcdef-123.b-cdn.net)',
        ''
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_academysessions/bunny_token_key',
        'Bunny Token Authentication Key',
        'From Bunny Dashboard → Stream → Security → Token Authentication. Enables signed embed URLs.',
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_academysessions/recording_expiry_days',
        'Recording Expiry (days)',
        'Number of days before recordings are automatically archived',
        '30',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
