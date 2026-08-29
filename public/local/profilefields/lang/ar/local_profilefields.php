<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Arabic strings for local_profilefields.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'حقول التسجيل والملف الشخصي';
$string['managefields'] = 'تنظيم حقول التسجيل والملف الشخصي';

// Tabs.
$string['tabregister'] = 'صفحة التسجيل';
$string['tablogin'] = 'صفحة الدخول';
$string['tabprofile'] = 'صفحة الملف الشخصي';
$string['tabregister_intro'] = 'اختر الحقول التي يملؤها المستخدم الجديد عند إنشاء حساب، وترتيبها، واسم كل حقل.';
$string['tablogin_intro'] = 'صفحة الدخول لا تطلب سوى معرّف وكلمة مرور. هذه هي الأشياء القليلة حولها التي يمكن تشغيلها أو إيقافها.';
$string['tabprofile_intro'] = 'اختر الحقول التي يُسمَح للمستخدم بتعديلها بنفسه في ملفه الشخصي. أما إظهار الحقل أو كونه إجباريًا أو فريدًا فيُدار من تاب التسجيل (لنموذج إنشاء الحساب) ومن صفحة «تعديل حقل الملف الشخصي» الخاصة بكل حقل.';

// Table columns.
$string['colfield'] = 'الحقل';
$string['colshow'] = 'إظهار';
$string['colrequired'] = 'إجباري';
$string['colunique'] = 'فريد';
$string['colcanedit'] = 'يمكن للمستخدم التعديل';
$string['colrename'] = 'التسمية';
$string['renamefield'] = 'إعادة تسمية';
$string['renameoncore'] = 'إعادة التسمية من صفحة الحقل';
$string['fixedbycore'] = 'ثابت في مودل - غير قابل للتعديل هنا.';

// Row badges.
$string['badgebuiltin'] = 'أساسي';
$string['badgecustom'] = 'مخصص';
$string['badgespecial'] = 'سلوك';

// Country from phone (task3).
$string['countryfromphone'] = 'تعبئة الدولة من حقل الهاتف';
$string['countryfromphone_desc'] = 'في صفحة التسجيل، تتبع خانة الدولة الدولةَ المختارة في حقل الهاتف، فيختارها المستخدم مرة واحدة.';

// IP match (task4).
$string['ipmatchheading'] = 'التحقق من الموقع';
$string['ipmatchphone'] = 'اشتراط تطابق دولة التسجيل مع موقع الزائر';
$string['ipmatchphone_desc'] = 'عند التفعيل لا يُنشأ الحساب إلا إذا كان عنوان IP الخاص بالزائر يشير إلى نفس دولة رقم الهاتف المُدخَل. سيُمنع مستخدمو VPN أو المتنقّلون، فاستخدمه بحذر.';
$string['ipmatchonline'] = 'لا يحتاج أي إعداد: يستخدم خدمة مجانية أونلاين لتحديد دولة الزائر. وللحصول على بحث أسرع ومُستضاف ذاتيًا يمكنك تركيب قاعدة GeoIP محلية من <a href="{$a}">الموقع الجغرافي ← البحث عن عنوان IP</a> وستُستخدَم بدلًا منها.';
$string['ipmatchgeoip'] = 'توجد قاعدة GeoIP محلية مُعَدّة، لذا تُستخدَم في البحث.';
$string['ipmismatch'] = 'موقعك لا يطابق دولة رقم الهاتف الذي أدخلته.';
$string['blockunresolvedip'] = 'ورفض التسجيل أيضًا إذا تعذّر تحديد الموقع';
$string['blockunresolvedip_desc'] = 'يعمل هذا الخيار طالما كان التحقق أعلاه مُفعَّلًا. عند تفعيله يُرفَض أي عنوان لا يستطيع البحث تحديد دولته بدل السماح له بالمرور. انتبه: الموقع الذي يعمل خلف بروكسي عكسي يجب أن يكون فيه $CFG->getremoteaddrconf مضبوطًا، وإلا بدا كل الزوار وكأنهم البروكسي فتعذّر تحديد موقع أي أحد.';
$string['ipunresolved'] = 'لم نتمكن من تحديد موقعك، لذلك تعذّر إنشاء الحساب. إذا كنت تستخدم VPN أو بروكسي فأوقفه ثم حاول مرة أخرى.';
$string['ipblocked'] = 'لا يمكن إنشاء حسابات جديدة من الشبكة التي تتصل منها حاليًا.';
$string['seereports'] = 'استعرض المحاولات المرفوضة في «تقارير التسجيل»';

// Username.
$string['usernameheading'] = 'اسم المستخدم';
$string['usernamefromemail'] = 'توليد اسم المستخدم من البريد الإلكتروني (وإخفاء خانة اسم المستخدم)';
$string['usernamesource'] = 'مصدر اسم المستخدم';
$string['usernamesourceemail'] = 'البريد الإلكتروني كاملًا';
$string['usernamesourcelocalpart'] = 'الجزء الذي يسبق علامة "@"';

// Terms & privacy.
$string['termsheading'] = 'الشروط وسياسة الخصوصية';
$string['termsnative'] = 'الموافقة على الشروط وسياسة الخصوصية تديرها أداة السياسات المدمجة في مودل، وهي تعرض المستندات على المستخدم الجديد قبل إنشاء الحساب. هذه الإضافة لا تغيّر هذا السلوك.';
$string['termson'] = 'السياسات مفعّلة (مُعالِج سياسات الموقع مضبوط على أداة السياسات). يجب على المستخدمين الجدد الموافقة عليها أثناء التسجيل.';
$string['termsoff'] = 'أداة السياسات مثبّتة لكنها غير مختارة كمُعالِج لسياسات الموقع، فالمستخدمون الجدد لا يُطالَبون بالموافقة على شيء بعد.';
$string['termsmanage'] = 'إدارة مستندات السياسات';
$string['termssettings'] = 'إعدادات سياسات الموقع';
$string['termsnotool'] = 'أداة السياسات في مودل غير مثبّتة، لذا سيعرض المربّع نصًّا عامًّا بدون روابط للمستندات.';
$string['consentenable'] = 'إظهار مربّع موافقة في صفحة التسجيل';
$string['consentenable_desc'] = 'عند التفعيل يُضاف إلى صفحة التسجيل نفسها مربّع إجباري «أوافق على السياسات» (مع روابط المستندات) بدلًا من صفحة الموافقة المنفصلة في مودل. تبقى المستندات مكتوبة ومُؤرشَفة في أداة السياسات بمودل.';
$string['consentlabel'] = 'أوافق على {$a}.';
$string['consentlabelplain'] = 'أوافق على شروط الاستخدام وسياسة الخصوصية.';
$string['consentrequired'] = 'يجب الموافقة على السياسات قبل إنشاء الحساب.';
$string['and'] = 'و';
$string['termsdocsfound'] = 'سيرتبط المربّع بمستندات السياسات التالية:';
$string['termsdocsnone'] = 'لا توجد بعدُ مستندات سياسات للضيوف. أنشئها (بالأسفل) وستُربَط بالمربّع تلقائيًا؛ وحتى ذلك الحين يعرض المربّع نصًّا عامًّا.';
$string['termsdoubleask'] = 'أداة السياسات في مودل مضبوطة أيضًا على السؤال في صفحة منفصلة، فيُسأل المستخدم مرّتين. أثناء تفعيل المربّع المُضمَّن، افتح <a href="{$a}">المستخدمون &gt; الخصوصية والسياسات &gt; إعدادات السياسات</a> واضبط «مُعالِج سياسات الموقع» على «الافتراضي (حسب إعداد سياسة الموقع)».';
$string['termspolicysettings'] = 'إعدادات السياسات';

// Login tab.
$string['loginselfregister'] = 'السماح للمستخدمين الجدد بإنشاء حساب بأنفسهم';
$string['loginselfregister_desc'] = 'يُظهر زر «إنشاء حساب جديد» في صفحة الدخول (التسجيل الذاتي عبر البريد الإلكتروني).';
$string['loginguest'] = 'إظهار زر «الدخول كضيف»';
$string['loginguest_desc'] = 'يتيح للزوار تصفّح المقررات المتاحة للضيوف دون حساب.';
$string['loginremember'] = 'تذكّر اسم المستخدم';
$string['loginremember_desc'] = 'يحدّد ويملأ اسم المستخدم مسبقًا في صفحة الدخول عند الزيارة التالية.';

// Provisioning.
$string['provisionheading'] = 'الحقول الموصى بها';
$string['provisionintro'] = 'المجموعة الموصى بها ليست كاملة بعد (ناقص {$a}). أنشئ الحقول الناقصة بخطوة واحدة؛ الحقول الموجودة تبقى كما هي.';
$string['provisionbutton'] = 'إنشاء الحقول الموصى بها';
$string['provisiondone'] = 'تم إنشاء {$a} حقل/حقول.';
$string['provisionallset'] = 'كل الحقول الموصى بها موجودة.';
$string['provisionnophone'] = 'إضافة نوع حقل الهاتف (profilefield_phone) غير مثبّتة، لذا لا يمكن إنشاء حقل الهاتف. ثبّتها ثم أعد المحاولة.';
$string['academycategory'] = 'بيانات إضافية';

// Core headings.
$string['corefieldsheading'] = 'حقول مودل الأساسية';
$string['customfieldsheading'] = 'حقول الملف الشخصي المخصصة';
$string['optionalcorefields'] = 'القسم الاختياري (الرقم التعريفي، المؤسسة، القسم، الهاتف، العنوان)';

// Provisioned field names.
$string['fieldphone'] = 'الهاتف';
$string['fieldnationality'] = 'الجنسية';
$string['fieldgender'] = 'النوع';
$string['fielddateofbirth'] = 'تاريخ الميلاد';
$string['fieldjobtitle'] = 'المسمّى الوظيفي';
$string['fieldcompany'] = 'الشركة';
$string['fieldindustry'] = 'المجال';
$string['fieldeducation'] = 'المؤهل التعليمي';
$string['fieldnationalid'] = 'الرقم القومي';
$string['fieldpassport'] = 'جواز السفر';

$string['privacy:metadata'] = 'إضافة حقول التسجيل والملف الشخصي تخزّن فقط إعدادات عرض الحقول، ولا تخزّن أي بيانات شخصية.';

// استكمال تسجيل لم يمر بنموذج إنشاء الحساب.
$string['completetitle'] = 'أكمل بيانات حسابك';
$string['completeintro'] = 'محتاجين منك بيانات بسيطة قبل ما تكمّل.';
$string['completesave'] = 'حفظ ومتابعة';
$string['completedone'] = 'شكرًا لك — تم استكمال بيانات حسابك.';
$string['completiongate'] = 'إيقاف الحسابات الناقصة';
$string['completiongate_desc'] = 'توجيه أي مستخدم مسجّل ينقصه حقل إجباري من حقول التسجيل إلى صفحة تجمع هذه البيانات. يغطي الحسابات التي أُنشئت خارج نموذج التسجيل، مثل الدخول عبر جوجل.';

// Register reports: the refused-attempt log and the IP deny list.
$string['reportstitle'] = 'تقارير التسجيل';
$string['tabattempts'] = 'المحاولات المحظورة';
$string['tabblacklist'] = 'قائمة حظر عناوين IP';
$string['tabattempts_intro'] = 'كل محاولة إنشاء حساب رفضتها قواعد الموقع الجغرافي: الدولة التي أعلنها الزائر، والدولة التي تم اكتشافها فعليًا من عنوانه، وسبب الرفض، وعنوان IP نفسه.';
$string['tabblacklist_intro'] = 'العناوين المدرجة هنا لا تستطيع إنشاء حساب مهما كانت الدولة التي تعلنها. ولا يتأثر بها أصحاب الحسابات القائمة، فهي تحكم التسجيل فقط لا تسجيل الدخول ولا تصفّح الموقع.';

// Report columns.
$string['colwhen'] = 'التوقيت';
$string['colip'] = 'عنوان IP';
$string['coldeclared'] = 'الدولة المُعلَنة';
$string['coldetected'] = 'الدولة المكتشَفة';
$string['colreason'] = 'السبب';
$string['colorigin'] = 'مصدر المحاولة';
$string['colactions'] = 'إجراءات';
$string['colnote'] = 'ملاحظة';
$string['coladded'] = 'تاريخ الإضافة';

// Reasons an attempt was refused.
$string['reasonany'] = 'كل الأسباب';
$string['reasonmismatch'] = 'اختلاف الدولة';
$string['reasonunresolved'] = 'الموقع غير معروف';
$string['reasonblocked'] = 'عنوان محظور';

// Which registration page the attempt came from.
$string['originsignup'] = 'صفحة التسجيل';
$string['origincomplete'] = 'استكمال التسجيل';
$string['originapp'] = 'تطبيق الموبايل';

// Deny-list editor.
$string['blockipaddress'] = 'عنوان IP';
$string['blockipaddress_help'] = 'مُدخَل واحد لكل عنوان. الصيغ المقبولة هي نفسها التي يستخدمها مودل في أماكن أخرى:

* عنوان مفرد - `1.2.3.4` أو `2001:db8::1`
* شبكة بصيغة CIDR - `1.2.3.0/24`
* بداية عنوان - `1.2.3.`
* نطاق في المجموعة الأخيرة - `1.2.3.4-16`';
$string['blockipnote'] = 'ملاحظة (اختياري)';
$string['blockipadd'] = 'إضافة إلى قائمة الحظر';
$string['blockipinvalid'] = 'هذا ليس عنوانًا ولا شبكة ولا بداية عنوان ولا نطاقًا يمكن لهذه القائمة مطابقته.';
$string['blockipduplicate'] = 'هذا المُدخَل موجود بالفعل في قائمة الحظر.';
$string['blockipadded'] = 'تمت إضافة {$a} إلى قائمة الحظر.';
$string['blockipremoved'] = 'تمت الإزالة من قائمة الحظر.';
$string['blockipfromreport'] = 'أُضيف من تقرير المحاولات المحظورة';
$string['blockthisip'] = 'حظر هذا العنوان';
$string['alreadyblocked'] = 'محظور';
$string['blocklistempty'] = 'قائمة الحظر فارغة، فلا يُرفَض أحد بسبب عنوانه وحده.';

// Report furniture.
$string['repeatoffenders'] = 'عناوين تكرّرت محاولاتها:';
$string['attemptcount'] = '{$a} محاولة';
$string['clearlog'] = 'مسح السجل';
$string['clearlogconfirm'] = 'حذف كل المحاولات المرفوضة المسجّلة؟ قائمة الحظر نفسها لن تتأثر.';
$string['logcleared'] = 'تم حذف {$a} محاولة مسجّلة.';
$string['guardoff'] = 'التحقق من الموقع مُعطَّل، فلا يُرفَض أحد ولن تظهر صفوف جديدة هنا. فعّله من <a href="{$a}">تنظيم حقول التسجيل والملف الشخصي ← صفحة التسجيل</a>.';
$string['guardonstrict'] = 'التحقق من الموقع مُفعَّل، ويُرفَض أيضًا الزوار الذين يتعذّر تحديد مواقعهم. ويظهر هنا النوعان معًا. الإعدادات في تاب <a href="{$a}">صفحة التسجيل</a>.';
$string['guardonlenient'] = 'التحقق من الموقع مُفعَّل، لكن الزوار الذين يتعذّر تحديد مواقعهم يُسمَح لهم بالمرور. ولا يظهر هنا سوى اختلاف الدولة والعناوين المحظورة. الإعدادات في تاب <a href="{$a}">صفحة التسجيل</a>.';

// Privacy.
$string['privacy:metadata:local_profilefields_log'] = 'سجل بمحاولات التسجيل التي رفضتها قواعد الموقع الجغرافي. لا يوجد حساب مرتبط بهذه الصفوف - فهي سجل لحسابات لم تُنشأ أصلًا - ومن ثمّ لا يمكن ربطها بمستخدم.';
$string['privacy:metadata:local_profilefields_log:ip'] = 'عنوان IP الذي جاءت منه المحاولة المرفوضة.';
$string['privacy:metadata:local_profilefields_log:declared'] = 'الدولة التي أعلنتها المحاولة.';
$string['privacy:metadata:local_profilefields_log:detected'] = 'الدولة التي تم اكتشافها من عنوان IP.';
$string['privacy:metadata:local_profilefields_log:reason'] = 'سبب رفض المحاولة.';
$string['privacy:metadata:local_profilefields_log:timecreated'] = 'وقت المحاولة.';
