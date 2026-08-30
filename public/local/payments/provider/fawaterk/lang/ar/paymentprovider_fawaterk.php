<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'مزود الدفع فواتيرك';
$string['webhook_heading'] = 'رابط الويب هوك';
$string['webhook_heading_desc'] = 'في لوحة تحكم فواتيرك، اضبط رابط الويب هوك على:<br><code>{$a}</code><br>'
    . 'يجب أن ينتهي المسار بـ <code>_json</code> — فبذلك يرسل فواتيرك البيانات بصيغة JSON بدلاً من صيغة النموذج.';
$string['sandbox_mode'] = 'الوضع التجريبي';
$string['sandbox_mode_desc'] = 'استخدام بيئة الاختبار الخاصة بفواتيرك بدلاً من البيئة الفعلية.';
$string['vendor_key'] = 'مفتاح التاجر (مفتاح API)';
$string['vendor_key_desc'] = 'مفتاح التاجر في فواتيرك. يُستخدم كرمز Bearer في طلبات API وكمفتاح سري للتحقق من توقيع hashKey في الويب هوك.';
$string['provider_key'] = 'مفتاح المزود';
$string['provider_key_desc'] = 'مفتاح المزود في فواتيرك. مطلوب فقط لواجهة الدفع المدمجة (iframe)؛ اتركه فارغاً عند استخدام رابط الفاتورة.';
$string['base_url'] = 'رابط API الفعلي';
$string['base_url_desc'] = 'رابط API الفعلي لفواتيرك. الافتراضي: https://app.fawaterk.com';
$string['sandbox_url'] = 'رابط API التجريبي';
$string['sandbox_url_desc'] = 'رابط API التجريبي لفواتيرك. الافتراضي: https://staging.fawaterk.com';
$string['default_phone'] = 'رقم الهاتف الاحتياطي';
$string['default_phone_desc'] = 'يتطلب فواتيرك رقم هاتف في كل فاتورة. تُرسل هذه القيمة عندما لا يكون لدى المشتري رقم هاتف في ملفه الشخصي.';
$string['send_email'] = 'إرسال الفاتورة بالبريد';
$string['send_email_desc'] = 'السماح لفواتيرك بإرسال الفاتورة إلى المشتري عبر البريد الإلكتروني.';
$string['send_sms'] = 'إرسال الفاتورة برسالة نصية';
$string['send_sms_desc'] = 'السماح لفواتيرك بإرسال الفاتورة إلى المشتري عبر رسالة نصية.';
$string['default_address'] = 'العنوان الاحتياطي';
$string['default_address_desc'] = 'يتطلب فواتيرك عنواناً في كل فاتورة. تُرسل هذه القيمة عندما لا يكون لدى المشتري عنوان أو مدينة في ملفه الشخصي.';
