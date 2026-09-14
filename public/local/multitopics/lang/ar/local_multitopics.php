<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'واجهة محتوى الكورسات متعددة المحاور';

// صفحة إعدادات تطبيق الموبايل (تُقدَّم عبر getsettings.php).
$string['appsettings'] = 'إعدادات تطبيق الموبايل';
$string['heading_access'] = 'الوصول';
$string['heading_access_desc'] = 'كل ما في هذه الصفحة يُنشر بدون مصادقة لأي شخص يطلب <code>/local/multitopics/getsettings.php</code>. لا تضع هنا أي بيانات سرية.';
$string['admin_token'] = 'توكن تصفح الضيوف';
$string['admin_token_desc'] = 'توكن الخدمات الذي يستخدمه التطبيق قبل تسجيل الدخول (الكتالوج، الخطط، الكوبونات). لأنه عام، يجب أن يكون التوكن المخصص للقراءة فقط الذي يُنشأ عبر <code>php local/multitopics/cli/app_guest_token.php --create</code>، وليس توكن مدير أبدًا.';
$string['google_client_id'] = 'معرّف عميل Google (ويب)';
$string['google_client_id_desc'] = 'معرّف عميل OAuth من نوع <strong>تطبيق ويب</strong> (…apps.googleusercontent.com) الذي يسجّل التطبيق الدخول به. يجب إدراجه أيضًا ضمن المعرّفات المقبولة في إضافة مصادقة Google.';
$string['server_timeout_duration'] = 'مهلة الخادم (ثوانٍ)';
$string['server_timeout_duration_desc'] = 'كم ينتظر التطبيق تحميل ملف PDF قبل التوقف.';
$string['heading_protection'] = 'حماية التشغيل';
$string['prevent_screen_recording'] = 'منع لقطات الشاشة وتسجيلها';
$string['prevent_screen_recording_desc'] = 'آمن افتراضيًا في التطبيق: لا يُعطَّل إلا بإيقافه صراحةً هنا.';
$string['watermark'] = 'علامة مائية متحركة على الفيديو';
$string['watermark_desc'] = 'عرض هوية المشاهد فوق الفيديو أثناء التشغيل داخل التطبيق.';
$string['watermark_speed'] = 'سرعة حركة العلامة المائية';
$string['watermark_speed_desc'] = 'رقم عشري، مثل 0.002.';
$string['watermark_fontsize'] = 'حجم خط العلامة المائية';
$string['watermark_fontsize_desc'] = 'بالنقاط، مثل 16.';
$string['watermark_color'] = 'لون العلامة المائية';
$string['watermark_color_desc'] = 'اتركه فارغًا لتدرّج الألوان المتحرك في التطبيق.';
$string['heading_store'] = 'إصدارات المتاجر';
$string['heading_store_desc'] = 'عندما يكون الإصدار هنا أعلى من المثبَّت، يعرض التطبيق نافذة <strong>إجبارية</strong> "تحديث مطلوب". لا تضعه إلا بعد نشر الإصدار الجديد فعليًا على المتجر.';
$string['android_version'] = 'أندرويد – أحدث إصدار';
$string['android_url'] = 'أندرويد – رابط Play Store';
$string['ios_version'] = 'iOS – أحدث إصدار';
$string['ios_url'] = 'iOS – رابط App Store';
$string['version_desc'] = 'ثلاثة أرقام، مثل <code>1.4.2</code>. أي صيغة أخرى لا تُنشر.';
$string['heading_support'] = 'الدعم';
$string['whatsapp_phone'] = 'رقم واتساب الدعم';
$string['whatsapp_phone_desc'] = 'بالصيغة الدولية؛ يُحذف كل ما ليس رقمًا (مثل 201001234567).';
$string['whatsapp_message'] = 'رسالة واتساب المعبّأة مسبقًا';
$string['whatsapp_message_desc'] = 'نص عادي؛ يُرمَّز للرابط تلقائيًا.';
