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
// AC-4.6.4 يحدّد نص هذه الرسالة، ويضيف قاعدة عمّا لا يجوز أن تحتويه: «لا تكشف
// الرسالة عن الدولة التي اكتشفها النظام». فذكر الدولة المكتشفة يخبر من يختبر
// الفحص بأي دولة يدّعيها في المحاولة التالية، وهو الالتفاف الذي وُجدت GEO-3 لمنعه.
$string['ipmismatch'] = 'تعذّر إتمام التسجيل. الدولة التي اخترتها لا تطابق موقعك الحالي. يرجى اختيار الدولة التي تسجّل منها، أو التواصل مع الدعم.';
$string['blockunresolvedip'] = 'ورفض التسجيل أيضًا إذا تعذّر تحديد الموقع';
$string['blockunresolvedip_desc'] = 'يعمل هذا الخيار طالما كان التحقق أعلاه مُفعَّلًا. عند تفعيله يُرفَض أي عنوان لا يستطيع البحث تحديد دولته بدل السماح له بالمرور. انتبه: الموقع الذي يعمل خلف بروكسي عكسي يجب أن يكون فيه $CFG->getremoteaddrconf مضبوطًا، وإلا بدا كل الزوار وكأنهم البروكسي فتعذّر تحديد موقع أي أحد.';
// GEO-5 ترفض العنوان الذي تعذّر تحديده «كما لو أن الفحص قد فشل»، فتقول ما يقوله
// الفحص الفاشل بالضبط. وإخبار الزائر بأن موقعه لم يُحدَّد أكثر إفادة مما ينبغي،
// وفيه تلميح بأن الـVPN يعطّل الفحص بدل أن يوقعه.
$string['ipunresolved'] = 'تعذّر إتمام التسجيل. الدولة التي اخترتها لا تطابق موقعك الحالي. يرجى اختيار الدولة التي تسجّل منها، أو التواصل مع الدعم.';
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

// -----------------------------------------------------------------------------
// نصوص الفصل الرابع من كراسة المواصفات.
//
// المواصفة تحدّد الجملة المعروضة عند كل حالة فشل بنصّها، والقبول يُختبر على هذا
// النص بالذات، فجُمعت هنا بدل تفريقها على الأصناف التي تثيرها: أي تعديل يُتفق
// عليه مع EAAC يصبح سطرًا واحدًا في ملفين.
// -----------------------------------------------------------------------------

// AC-4.1.6 / AC-4.4.1 - تعقيد كلمة المرور، رسالة لكل قاعدة.
$string['pwtooshort'] = 'يجب ألا تقل كلمة المرور عن ٨ أحرف.';
$string['pwnoupper'] = 'يجب أن تحتوي كلمة المرور على حرف إنجليزي كبير واحد على الأقل.';
$string['pwnolower'] = 'يجب أن تحتوي كلمة المرور على حرف إنجليزي صغير واحد على الأقل.';
$string['pwnodigit'] = 'يجب أن تحتوي كلمة المرور على رقم واحد على الأقل.';

// AC-4.1.15 - تحقق الحقول.
$string['errfirstnameempty'] = 'يرجى إدخال الاسم الأول.';
$string['errlastnameempty'] = 'يرجى إدخال اسم العائلة.';
$string['errnamelength'] = 'يجب أن يتراوح الاسم بين حرفين و٥٠ حرفًا.';
$string['errnamechars'] = 'يُسمح بالحروف والمسافات والشرطات والفواصل العليا فقط.';
$string['erremailempty'] = 'يرجى إدخال بريدك الإلكتروني.';
$string['erremailformat'] = 'يرجى إدخال بريد إلكتروني صحيح، مثل name@example.com.';
$string['errcountryempty'] = 'يرجى اختيار الدولة.';
$string['errphoneempty'] = 'يرجى إدخال رقم الهاتف.';
$string['errphonedigits'] = 'يرجى إدخال أرقام فقط.';
$string['errtermsempty'] = 'يجب الموافقة على الشروط والأحكام للمتابعة.';

// AC-4.1.2 - البريد مسجّل بالفعل.
$string['emailexistsloginhint'] = 'يوجد حساب مسجّل بالفعل بهذا البريد الإلكتروني. {$a}';
$string['emailexistsloginlink'] = 'تسجيل الدخول';

// AC-4.2 - تأكيد البريد الإلكتروني (مسار الرابط).
$string['verifysent'] = 'تم إرسال رسالة إلى بريدك الإلكتروني {$a}';
$string['verifysentdetail'] = 'تحتوي على تعليمات بسيطة لاستكمال تسجيلك.';
$string['verifyresendtoomany'] = 'تم إرسال عدد كبير من الطلبات. يرجى المحاولة مرة أخرى بعد ساعة.';
$string['verifylinkexpired'] = 'لم يعد رابط التأكيد هذا صالحًا. يرجى طلب رابط جديد.';
$string['verifyalreadydone'] = 'تم تأكيد حسابك بالفعل. يرجى تسجيل الدخول.';
$string['verifyresend'] = 'إعادة إرسال الرسالة';
$string['verifyresendwait'] = 'إعادة إرسال الرسالة ({$a} ث)';
$string['verifyresent'] = 'تم إرسال رسالة تأكيد جديدة.';
$string['verifychangeemail'] = 'تغيير البريد الإلكتروني';
$string['verifychangeemailsaved'] = 'تم تحديث بريدك الإلكتروني وإرسال رسالة تأكيد جديدة إليه.';
$string['verifyemailtaken'] = 'يوجد حساب مسجّل بالفعل بهذا البريد الإلكتروني.';

// AC-4.3 - تسجيل الدخول.
$string['loginbadcredentials'] = 'البريد الإلكتروني أو كلمة المرور غير صحيح.';
$string['loginlockedout'] = 'عدد كبير من المحاولات غير الناجحة. تم قفل حسابك لمدة ١٥ دقيقة.';
$string['loginsuspended'] = 'تم إيقاف هذا الحساب. يرجى التواصل مع الدعم.';
$string['loginunverified'] = 'يرجى تأكيد بريدك الإلكتروني للمتابعة. أرسلنا لك رسالة تأكيد جديدة.';

// AC-4.4 - إعادة تعيين كلمة المرور.
$string['resetsamepassword'] = 'يرجى اختيار كلمة مرور لم تستخدمها من قبل.';
$string['resetuseprovider'] = 'هذا الحساب يسجّل الدخول عبر {$a}. يرجى استخدام هذا الزر في شاشة الدخول.';
$string['resetdonesubject'] = 'تم تغيير كلمة المرور الخاصة بك';
$string['resetdonebody'] = 'مرحبًا {$a->firstname}،

تم تغيير كلمة المرور الخاصة بحسابك في {$a->sitename} للتو، وتم تسجيل الخروج من جميع الأجهزة التي كانت مسجّلة الدخول.

إذا لم تكن أنت من قام بذلك، يرجى التواصل مع الدعم فورًا.';

// AC-4.5 - الملف الشخصي وإعدادات الحساب.
$string['countryofrecord'] = 'دولة السجل';
$string['requestchange'] = 'طلب تعديل';
$string['requestchangeintro'] = 'أخبرنا بما ينبغي تعديله وسببه، وسيقوم أحد المسؤولين بمراجعة طلبك.';
$string['requestchangesent'] = 'تم إرسال طلبك. سيقوم أحد المسؤولين بمراجعته قريبًا.';
$string['requestchangepending'] = 'لديك بالفعل طلب تعديل قيد المراجعة.';
$string['lockedmessages'] = 'لا يمكن إيقاف رسائل المعاملات ورسائل الأمان.';
$string['deleteaccount'] = 'حذف حسابي';
$string['deleteaccountwarning'] = 'حذف حسابك يلغي وصولك إلى جميع الدورات التي اشتريتها وإلى الشهادات التي حصلت عليها. لا يمكن التراجع عن هذا الإجراء.';
$string['deleteaccountconfirm'] = 'أدخل كلمة المرور للتأكيد';
$string['deleteaccountdone'] = 'تم حذف حسابك.';
$string['deleteaccountwrongpassword'] = 'كلمة المرور غير صحيحة.';

// AC-4.6 - قواعد الاتساق الجغرافي.
$string['ipservicedown'] = 'التسجيل غير متاح مؤقتًا. يرجى المحاولة مرة أخرى بعد قليل.';
$string['ipallowlist'] = 'العناوين المستثناة';
$string['ipallowlistintro'] = 'العناوين المدرجة هنا تتخطى فحص الموقع تمامًا - لمكاتب الأكاديمية وللاختبار. إدخال واحد في كل سطر: عنوان مفرد، أو كتلة CIDR، أو عنوان جزئي، أو نطاق.';
$string['ipallowlistempty'] = 'لا يوجد عنوان مستثنى من فحص الموقع.';
$string['ipallowlistadd'] = 'استثناء عنوان';
$string['alreadyallowed'] = 'مستثنى بالفعل';
$string['reasonservicedown'] = 'خدمة تحديد الموقع غير متاحة';
$string['servicedownalert'] = 'تعذّر الوصول إلى خدمة تحديد الموقع، ولذلك يُرفض التسجيل حاليًا. أما التصفّح والتسعير فقد رجعا إلى السعر الافتراضي. يرجى فحص مسار الشبكة إلى خدمات البحث، أو إعداد قاعدة GeoIP2 محلية من إعدادات المكان.';

// AC-4.3.5 - تذكّرني.
$string['rememberme'] = 'تذكّرني';
$string['remembermedesc'] = 'يبقيك مسجّل الدخول على هذا الجهاز لمدة ٣٠ يومًا.';
$string['remembermeenabled'] = 'إظهار خيار «تذكّرني» في شاشة الدخول';
$string['remembermeenabled_desc'] = 'يضيف خانة «تذكّرني» إلى شاشة الدخول. من يفعّلها يبقى مسجّل الدخول على ذلك الجهاز للمدة المحددة أدناه، حتى بعد انتهاء الجلسة العادية. الرمز يُستخدم مرة واحدة ويُستبدل عند كل زيارة، ويُلغى عند تسجيل الخروج وعند تغيير كلمة المرور وعند إيقاف الحساب.';
$string['remembermedays'] = 'مدة التذكّر';
$string['remembermedays_desc'] = 'المدة التي يظل فيها رمز «تذكّرني» صالحًا. المواصفة تطلب ٣٠ يومًا.';
$string['remembermestolen'] = 'تم رفض محاولة دخول إلى {$a}';
$string['remembermestolenbody'] = 'مرحبًا {$a->firstname}،

حاول أحدهم تسجيل الدخول إلى حسابك في {$a->sitename} باستخدام رمز «تذكّرني» منتهي الصلاحية. وكإجراء احترازي قمنا بتسجيل الخروج من جميع الأجهزة، وسيلزمك تسجيل الدخول من جديد.

إذا لم تكن أنت من قام بذلك، يرجى تغيير كلمة المرور.';

// إعدادات منقولة من نواة مودل.
$string['securityheading'] = 'أمان تسجيل الدخول';
$string['securityintro'] = 'هذه الإعدادات تخص مودل نفسه، وقد كُرّرت هنا ليجتمع كل ما يحكم شاشة الدخول في مكان واحد. الحفظ من هذه الصفحة يكتب مباشرةً في إعداد النواة.';
$string['lockoutthreshold'] = 'عدد المحاولات الفاشلة قبل القفل';
$string['lockoutthreshold_desc'] = 'المواصفة تطلب ٥. القيمة صفر تُلغي القفل تمامًا.';
$string['lockoutduration'] = 'مدة القفل';
$string['lockoutduration_desc'] = 'المواصفة تطلب ١٥ دقيقة. ويرسل مودل إلى صاحب الحساب رسالة بها رابط لفك القفل بمجرد تطبيقه.';
$string['sessiontimeoutlabel'] = 'تسجيل الخروج بعد خمول';
$string['sessiontimeout_desc'] = 'المواصفة تطلب ٢٤ ساعة. هذا إعداد على مستوى الموقع كله ويؤثر على جميع المستخدمين.';
$string['gatebuttons'] = 'تعطيل أزرار الإرسال حتى تصح بيانات النموذج';
$string['gatebuttons_desc'] = 'يُطبَّق على شاشات الأكاديمية وحدها - التسجيل والدخول والملف الشخصي وإعادة تعيين كلمة المرور والدفع. أما نماذج مودل الإدارية فتُترك عمدًا، لأن حقولها كثيرًا ما تكون شرطية، وزر معطّل فيها يترك المسؤول محبوسًا بلا تفسير.';

// حدود التأكيد.
$string['verifyheading'] = 'تأكيد البريد الإلكتروني';
$string['verifyintro'] = 'المدة التي يظل فيها رابط التأكيد صالحًا، وعدد المرات المسموح بطلب رابط جديد فيها.';
$string['linkttl'] = 'انتهاء صلاحية رابط التأكيد بعد';
$string['linkttl_desc'] = 'المواصفة تطلب ٢٤ ساعة. وطلب رابط جديد يُلغي دائمًا كل رابط صدر قبله.';
$string['resendcooldown'] = 'المهلة بين كل إرسال وآخر';
$string['resendcooldown_desc'] = 'المواصفة تطلب ٦٠ ثانية، تُعرض للمستخدم كعدّاد تنازلي حيّ.';
$string['resendmax'] = 'أقصى عدد لإعادة الإرسال في الساعة';
$string['resendmax_desc'] = 'المواصفة تطلب ٥. والطلب السادس خلال ساعة واحدة يُرفض.';

// الخصوصية - الجداول المضافة للفصل الرابع.
$string['privacy:metadata:local_profilefields_remember'] = 'رموز «تذكّرني» التي تُبقي المتعلّم مسجّل الدخول على جهاز اختار أن يثق به.';
$string['privacy:metadata:local_profilefields_remember:userid'] = 'الحساب الذي يسجّل الرمز الدخول إليه.';
$string['privacy:metadata:local_profilefields_remember:lastip'] = 'العنوان الذي استُخدم منه الرمز آخر مرة.';
$string['privacy:metadata:local_profilefields_remember:useragent'] = 'بصمة للمتصفح الذي صدر له الرمز، حتى يُرفض الرمز إذا قدّمه متصفح آخر.';
$string['privacy:metadata:local_profilefields_remember:expires'] = 'موعد انتهاء صلاحية الرمز.';
$string['privacy:metadata:local_profilefields_remember:timecreated'] = 'وقت إصدار الرمز.';
$string['privacy:metadata:local_profilefields_request'] = 'طلبات تعديل حقل لا يملك المتعلّم تعديله بنفسه، وقرار المسؤول في كل طلب.';
$string['privacy:metadata:local_profilefields_request:userid'] = 'المتعلّم صاحب الطلب.';
$string['privacy:metadata:local_profilefields_request:field'] = 'الحقل موضوع الطلب.';
$string['privacy:metadata:local_profilefields_request:oldvalue'] = 'القيمة قبل الطلب.';
$string['privacy:metadata:local_profilefields_request:newvalue'] = 'القيمة التي طلبها المتعلّم.';
$string['privacy:metadata:local_profilefields_request:reason'] = 'السبب الذي ذكره المتعلّم.';
$string['privacy:metadata:local_profilefields_request:decidedby'] = 'المسؤول الذي وافق على الطلب أو رفضه.';
$string['privacy:metadata:local_profilefields_request:decisionnote'] = 'السبب الذي ذكره المسؤول لقراره.';
$string['privacy:metadata:local_profilefields_request:timecreated'] = 'وقت تقديم الطلب.';

$string['taskpurgetokens'] = 'حذف رموز «تذكّرني» منتهية الصلاحية';

// تظهر عندما يرفض الخادم إعادة إرسال وصلت داخل مهلة الانتظار - وعادةً يمنعها الزر
// المعطّل، فهذا هو المسار عند تعطيل الجافاسكربت.
$string['verifyresendtoosoon'] = 'يرجى الانتظار {$a} ثانية قبل طلب رسالة جديدة.';

// AC-4.5.7 - حذف الحساب.
$string['deleteaccountword'] = 'حذف';
$string['deleteaccounttype'] = 'اكتب {$a} للتأكيد';
$string['deleteaccountwrongword'] = 'يرجى كتابة {$a} بالضبط للتأكيد.';
$string['deleteaccountrefused'] = 'لا يمكن حذف هذا الحساب من هنا. يرجى التواصل مع الدعم.';
$string['deleteaccountdonesubject'] = 'تم حذف حسابك';
$string['deleteaccountdonebody'] = 'مرحبًا {$a->firstname}،

تم حذف حسابك في {$a->sitename} بناءً على طلبك. وانتهى وصولك إلى الدورات التي كانت لديك وإلى شهاداتك، ولا يمكن التراجع عن ذلك.

أما الشهادات التي حصلت عليها بالفعل فتظل قابلة للتحقق من صحتها لمن يملك رمزها.

إذا لم تكن أنت من طلب ذلك، يرجى التواصل مع الدعم فورًا.';

// الملاحظة المحفوظة مع قبول السياسة المسجَّل من نموذج التسجيل، ليعرف المسؤول
// عند مراجعة سجل القبول من أين جاء.
$string['consentnote'] = 'تمت الموافقة من نموذج التسجيل.';

// Password reset tab (AC-4.4.4, AC-4.4.5).
$string['tabpasswordreset'] = 'إعادة تعيين كلمة المرور';
$string['tabpasswordreset_intro'] = 'حدود الرمز المؤقت الذي يُرسل عند طلب إعادة تعيين كلمة مرور منسية من التطبيق. لو تُركت خانة فارغة أو خارج المدى يُستخدم الافتراضي.';
$string['reset_otprequestmax'] = 'الحد الأقصى لطلبات إعادة التعيين';
$string['reset_otprequestmax_desc'] = 'عدد الرموز التي يمكن لبريد إلكتروني واحد طلبها خلال المدة أدناه، وبعدها تُرفض الطلبات حتى تنتهي المدة. الحساب يتم لكل بريد حتى لو لم يكن له حساب، حتى لا يكشف الرد عن وجود الحساب من عدمه.';
$string['reset_otprequestwindow'] = 'مدة احتساب الطلبات (بالدقائق)';
$string['reset_otprequestwindow_desc'] = 'الفترة الزمنية التي يُحسب خلالها حد الطلبات.';
$string['reset_otpmaxattempts'] = 'الحد الأقصى لإدخال الرمز بشكل خاطئ';
$string['reset_otpmaxattempts_desc'] = 'عدد مرات إدخال الرمز بشكل خاطئ قبل إبطال الرمز ووجوب طلب رمز جديد.';
$string['reset_otpttl'] = 'صلاحية الرمز (بالدقائق)';
$string['reset_otpttl_desc'] = 'المدة التي يظل فيها الرمز صالحًا للاستخدام بعد إرساله.';
$string['resetnotlockout'] = 'هذه الحدود تخص رمز إعادة تعيين كلمة المرور فقط. أما قفل الحساب بعد محاولات دخول فاشلة متكررة فهو آلية منفصلة تُضبط من تاب «صفحة الدخول» في خانة «عدد المحاولات الفاشلة قبل القفل».';
$string['resetnoacademy'] = 'هذه الإعدادات تخص إضافة الأكاديمية (local_academy) وهي غير مثبَّتة على هذا الموقع.';

// شاشة الحساب - WF-5.1 إلى WF-5.6.
$string['accounttitle'] = 'حسابي';
$string['navprofile'] = 'الملف الشخصي';
$string['navsecurity'] = 'الأمان';
$string['navmylearning'] = 'تعلُّمي';
$string['navcertificates'] = 'الشهادات';
$string['navinvoices'] = 'الفواتير';
$string['navdelete'] = 'حذف حسابي';
$string['profilepicture'] = 'الصورة الشخصية';
$string['picturehelp'] = 'JPG أو PNG، بحد أقصى 2 ميجابايت، مقصوصة مربعة.';
$string['namehelp'] = 'الاسم المطبوع على الشهادات الصادرة هو الاسم المسجَّل لحظة الإصدار.';
$string['changeemailbutton'] = 'تغيير';
$string['changeemailtitle'] = 'تغيير البريد الإلكتروني';
$string['emailchangehelp'] = 'تغييره يُرسل رابط تأكيد إلى العنوان الجديد. ولا يسري التغيير إلا عند فتح ذلك الرابط.';
$string['changeemailsent'] = 'تم إرسال رابط تأكيد إلى {$a}. ولا يتغير عنوانك إلا عند فتح ذلك الرابط.';
$string['changesaved'] = 'تم حفظ التغييرات.';
$string['countryhelp'] = 'هذه الدولة تحدّد الأسعار المعروضة لك.';
$string['nationalityhelp'] = 'تُجمع هنا فقط، ولا تُطلب عند التسجيل. يمكن تركها فارغة دون قيد. ولا تؤثر على الأسعار.';
$string['preferredlanguagehelp'] = 'تُطبَّق على الويب والجوال عند تحميل الصفحة التالية.';
$string['notset'] = 'غير محدَّد';
$string['lockedfield'] = 'لا يمكن تغيير هذا إلا بواسطة المسؤول.';
$string['passwordlastchanged'] = 'آخر تغيير {$a}';
$string['passwordlastchangedunknown'] = 'هذا الموقع لا يسجّل تاريخ آخر تغيير لكلمة المرور.';
$string['passwordchangehelp'] = 'يتطلب كلمة المرور الحالية، ويُنهي جميع الجلسات النشطة الأخرى.';
$string['emailchangeexternal'] = 'بريدك الإلكتروني يأتي من الحساب الذي تسجّل الدخول به، لذا لا يمكن تغييره من هنا.';
$string['emailchangelocked'] = 'لا يمكن تغيير بريدك الإلكتروني إلا بواسطة المسؤول.';
$string['passwordexternal'] = 'أنت تسجّل الدخول عبر حساب خارجي، لذا لا توجد كلمة مرور محفوظة هنا.';
$string['deleteaccountcannotbeundone'] = 'لا يمكن التراجع عن هذا الإجراء';
$string['deleteaccountretained'] = 'تُحفظ السجلات المالية. وتظل الشهادات الصادرة قابلة للتحقق العلني.';

// عناوين مجموعات حقول الملف الشخصي وأسماء حقول المدرّب.
$string['instructorcategory'] = 'بيانات المدرّب';

$string['ifieldcoverimage'] = 'صورة الغلاف';
$string['ifieldbiography'] = 'نبذة تعريفية';
$string['ifieldqualifications'] = 'المؤهلات';
$string['ifieldcertificates'] = 'الشهادات';
$string['ifieldexperience'] = 'الخبرات';
$string['ifieldspecialization'] = 'التخصص';
$string['ifieldlanguages'] = 'اللغات';
$string['ifieldlinkedin'] = 'LinkedIn';
$string['ifieldwebsite'] = 'الموقع الإلكتروني';
$string['ifieldfacebook'] = 'Facebook';
$string['ifieldinstagram'] = 'Instagram';
$string['ifieldtwitter'] = 'Twitter';
$string['ifieldyoutube'] = 'YouTube';
$string['ifieldawards'] = 'الجوائز';
$string['ifieldyearsofexperience'] = 'سنوات الخبرة';
$string['ifieldresume'] = 'السيرة الذاتية';
