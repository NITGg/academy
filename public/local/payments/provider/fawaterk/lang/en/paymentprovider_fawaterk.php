<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Fawaterk Payment Provider';
$string['webhook_heading'] = 'Webhook URL';
$string['webhook_heading_desc'] = 'In the Fawaterk dashboard, set the webhook URL to:<br><code>{$a}</code><br>'
    . 'The path must end in <code>_json</code> — that is how Fawaterk decides to POST JSON instead of form data.';
$string['sandbox_mode'] = 'Sandbox mode';
$string['sandbox_mode_desc'] = 'Use the Fawaterk staging environment instead of live.';
$string['vendor_key'] = 'Vendor key (API key)';
$string['vendor_key_desc'] = 'Fawaterk vendor key. Used as the Bearer token for API calls and as the secret for verifying webhook hashKey signatures.';
$string['provider_key'] = 'Provider key';
$string['provider_key_desc'] = 'Fawaterk provider key. Only required for the JavaScript iframe checkout; leave empty for the hosted invoice link.';
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
