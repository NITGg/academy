<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']         = 'فيديو VdoCipher';
$string['modulename']         = 'فيديو VdoCipher';
$string['modulenameplural']   = 'فيديوهات VdoCipher';
$string['modulename_help']    = 'نشاط فيديو VdoCipher بيعرض فيديو محمي بـ DRM. التحميل وتسجيل الشاشة ممنوعين، وكل مشاهد بيشوف اسمه وإيميله كعلامة مائية بتتحرك على الفيديو.';
$string['pluginadministration'] = 'إدارة فيديو VdoCipher';

// Capabilities.
$string['vdocipher:addinstance'] = 'إضافة نشاط فيديو VdoCipher جديد';
$string['vdocipher:view']        = 'مشاهدة فيديو VdoCipher';

// Form.
$string['videosource']       = 'الفيديو';
$string['videofile']         = 'رفع فيديو';
$string['videofile_help']    = 'اختار ملف الفيديو. بيترفع من المتصفح على VdoCipher مباشرةً (مش بيعدّي على السيرفر)، فالملفات الكبيرة والنت البطيء مش مشكلة — تابع شريط التقدّم. لما يخلص، خانة رقم الفيديو اللي تحت بتتملي لوحدها؛ وبعدين اضغط حفظ.';
$string['videoid']           = 'رقم الفيديو على VdoCipher';
$string['videoid_help']      = 'بيتملي لوحده بعد ما الرفع يخلص. وممكن بدل الرفع تحط رقم فيديو موجود أصلًا في حساب VdoCipher.';
$string['err_novideosource'] = 'ضيف فيديو: ارفع ملف أو حط رقم فيديو من VdoCipher.';

// Uploader messages (shown by the form's script).
$string['upload_choose']     = 'اختار فيديو عشان يترفع بأمان على VdoCipher.';
$string['upload_requesting'] = 'جاري تجهيز رفع آمن…';
$string['upload_progress']   = 'جاري الرفع… {$a}%';
$string['upload_done']       = 'اترفع ✓ — رقم الفيديو اتحط. اضغط حفظ عشان تخلّص.';
$string['upload_failed']     = 'الرفع فشل (HTTP {$a}). جرّب تاني، أو حط رقم فيديو.';
$string['upload_neterror']   = 'حصل خطأ في الرفع (الشبكة). جرّب تاني، أو حط رقم فيديو.';
$string['upload_nocreds']    = 'مرجعتش بيانات الرفع.';
$string['upload_error']      = 'خطأ: {$a}';

// Index.
$string['noinstances']       = 'مفيش فيديوهات VdoCipher في الكورس ده.';

// Privacy.
$string['privacy:metadata']  = 'بلجن فيديو VdoCipher مبيخزّنش بيانات شخصية بنفسه. لما الفيديو يشتغل، اسم المشاهد وإيميله بيتبعتوا لـ VdoCipher عشان يظهروا كعلامة مائية على الفيديو.';
