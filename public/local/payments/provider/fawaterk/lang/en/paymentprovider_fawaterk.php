<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Fawaterk Payment Provider';
$string['webhook_heading'] = 'Webhook URL';
$string['webhook_heading_desc'] = 'In the Fawaterk dashboard, set the webhook URL to:<br><code>{$a}</code><br>'
    . 'The path must end in <code>_json</code> — that is how Fawaterk decides to POST JSON instead of form data.';
$string['sandbox_mode'] = 'Sandbox mode';
$string['sandbox_mode_desc'] = 'Use the Fawaterk staging environment instead of live. <b>Leave this off unless you have a separate staging account.</b> Staging has its own credentials, and anything copied from app.fawaterk.com is a live credential that will be rejected here. OAuth clients in particular can only be created on the live dashboard, so OAuth and sandbox mode cannot be combined at all.';
$string['auth_heading'] = 'API authentication';
$string['auth_heading_desc'] = 'How Moodle talks to the Fawaterk API. Both credential sets come from the Fawaterk dashboard: <b>Integrations</b> in the left menu. Note that OAuth clients can only be created on the live dashboard, so OAuth and sandbox mode cannot be combined.';
$string['auth_mode'] = 'Authentication method';
$string['auth_mode_desc'] = 'This picks the API generation as well as the credential, because the two go together. OAuth uses the current v3 API: it takes a per-request webhook URL and supports refunds. The HASH API key uses the older v2 API, which has neither. Leave this on OAuth unless v3 is unavailable on your account.';
// These two are dropdown options, not descriptions: Moodle escapes them, so an
// HTML entity here renders literally on the settings page.
$string['auth_mode_oauth'] = 'OAuth 2.0 client credentials — API v3 (recommended)';
$string['auth_mode_apikey'] = 'HASH API key — API v2 (fallback, no refunds)';
$string['client_id'] = 'OAuth client ID';
$string['client_id_desc'] = 'From the Fawaterk dashboard: Integrations → machine-to-machine credentials → Client ID. Looks like a UUID.';
$string['client_secret'] = 'OAuth client secret';
$string['client_secret_desc'] = 'The secret shown when the client was created. Fawaterk shows it once — if it was not saved, revoke the client and create a new one.';
$string['token_url'] = 'OAuth token URL';
$string['token_url_desc'] = 'Leave empty to use /oauth/token on the API base URL for the current mode, which is almost always right. Set it only if Fawaterk gave you a different token endpoint.';
$string['hash_heading'] = 'Iframe / webhook credentials';
$string['hash_heading_desc'] = 'From the same Integrations page, under <b>Iframe/Webhook integrations settings</b>. The HASH API key is required whichever authentication method you use above, because webhooks are signed with it rather than with an access token.';
$string['vendor_key'] = 'HASH API key';
$string['vendor_key_desc'] = 'The secret Fawaterk signs webhook hashKey values with — without it, incoming webhooks cannot be verified and no payment will ever complete. It is also used as the Bearer token when the authentication method above is set to the legacy static key.';
$string['provider_key'] = 'providerKey';
$string['provider_key_desc'] = 'Only required for the JavaScript iframe checkout, which this plugin does not use. Safe to leave empty.';
$string['base_url'] = 'Live API base URL';
$string['base_url_desc'] = 'Fawaterk live API base URL. Default: https://app.fawaterk.com';
$string['sandbox_url'] = 'Sandbox API base URL';
$string['sandbox_url_desc'] = 'Fawaterk staging API base URL. Default: https://staging.fawaterk.com';
$string['default_phone'] = 'Fallback phone number';
$string['default_phone_desc'] = 'Fawaterk requires a phone number on every invoice. This value is sent when the buyer has no phone on their Moodle profile.';
$string['send_email'] = 'Email the invoice';
$string['send_email_desc'] = 'Let Fawaterk send the invoice to the buyer by email.';
$string['send_sms'] = 'SMS the invoice';
$string['send_sms_desc'] = 'Let Fawaterk send the invoice to the buyer by SMS.';
$string['default_address'] = 'Fallback address';
$string['default_address_desc'] = 'Fawaterk requires an address on every invoice. This value is sent when the buyer has no address or city on their Moodle profile.';
$string['auto_select_method'] = 'Charge a payment method directly';
$string['auto_select_method_desc'] = 'Recommended. Instead of sending the buyer to Fawaterk\'s hosted page to choose, charge one method server-to-server. When the account has several methods enabled, the one highest in the priority list below is used. Turn this off to always use the hosted page.';
$string['method_priority'] = 'Payment method priority';
$string['method_priority_desc'] = 'Comma-separated Fawaterk payment method ids, best first. The first one the account actually has enabled is used. Default 2,4,3 = Visa/Mastercard, then Meeza, then Fawry. Anything not listed is tried last.';
$string['reference_ttl_days'] = 'Reference code validity (days)';
$string['reference_ttl_days_desc'] = 'How long an order stays open when the buyer is given an offline code (Fawry, Meeza) instead of paying immediately. They often pay the next day, and the order must still be open when Fawaterk confirms it. Set 0 to use the site-wide checkout timeout instead.';
$string['due_date_days'] = 'Payment link validity (days)';
$string['due_date_days_desc'] = 'The due date shown on the Fawaterk payment page. Left at 0 Fawaterk applies its own default of 2 days, which is why a link for a 30-minute order can show a due date days away. The Moodle order may expire sooner; a payment that arrives after that is still fulfilled.';
