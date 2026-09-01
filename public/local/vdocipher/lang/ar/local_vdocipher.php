<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'فيديوهات VdoCipher';

// Capabilities.
$string['vdocipher:manage'] = 'إنشاء فيديوهات VdoCipher وتعديلها وحذفها';
$string['vdocipher:view']   = 'تشغيل فيديوهات VdoCipher';

// Settings — credentials.
$string['credsheading']      = 'بيانات اعتماد VdoCipher';
$string['credsheading_desc'] = 'بيانات اعتماد واجهة برمجة التطبيقات لحسابك في VdoCipher. يُستخدم المفتاح السرّي على الخادم فقط لتوقيع الطلبات وإنشاء أكواد تشغيل قصيرة الأجل، ولا يُرسل إلى أي عميل إطلاقًا.';
$string['apisecret']         = 'المفتاح السرّي للواجهة';
$string['apisecret_desc']    = 'المفتاح السرّي من لوحة تحكم VdoCipher (Config ← API keys). يُرسَل في ترويسة "Authorization: Apisecret &lt;key&gt;".';
$string['apibase']           = 'رابط الواجهة الأساسي';
$string['apibase_desc']      = 'الرابط الأساسي لواجهة VdoCipher. الافتراضي: https://dev.vdocipher.com/api';

// Settings — playback / security.
$string['playbackheading']      = 'التشغيل والحماية';
$string['playbackheading_desc'] = 'يتحكم في كود التشغيل قصير الأجل وفي العلامة المائية الديناميكية التي تُطبع على الفيديو باسم المشاهد.';
$string['otpttl']               = 'مدة صلاحية كود التشغيل (بالثواني)';
$string['otpttl_desc']          = 'المدة التي يظل فيها كود التشغيل صالحًا. اجعلها قصيرة — إذ يطلب العميل كودًا جديدًا قبل التشغيل مباشرة.';
$string['watermarktext']        = 'نص العلامة المائية';
$string['watermarktext_desc']   = 'النص الذي يظهر فوق الفيديو. تُستبدل العناصر {fullname} و{email} و{userid} ببيانات المشاهد على الخادم، فلا يمكن للعميل تزويرها أو إزالتها.';
$string['watermarkenabled']     = 'تفعيل العلامة المائية';
$string['watermarkenabled_desc']= 'طباعة هوية المشاهد على الفيديو كطبقة متحركة.';
$string['watermarkalpha']       = 'شفافية العلامة المائية';
$string['watermarkalpha_desc']  = 'درجة وضوح نص العلامة المائية، من 0 (غير مرئية) إلى 1 (صريحة). الافتراضي 0.60.';
$string['watermarksize']        = 'حجم خط العلامة المائية';
$string['watermarksize_desc']   = 'حجم خط نص العلامة المائية بالبكسل. الافتراضي 15.';

// Diagnostics.
$string['diagnose']             = 'تشخيص VdoCipher';

// Errors.
$string['err_nosecret']         = 'لم يتم ضبط المفتاح السرّي لـ VdoCipher. اضبطه من إدارة الموقع ← الإضافات ← الإضافات المحلية ← فيديوهات VdoCipher.';
$string['err_apifailed']        = 'فشل طلب واجهة VdoCipher: {$a}';
$string['err_novideo']          = 'لا يوجد فيديو VdoCipher مرتبط بهذا النشاط.';
$string['err_noaccess']         = 'ليس لديك صلاحية الوصول إلى هذا الفيديو.';
