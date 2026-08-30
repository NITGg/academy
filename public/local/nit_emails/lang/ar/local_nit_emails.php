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
 * Arabic strings for local_nit_emails.
 *
 * @package    local_nit_emails
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'رسائل الشراء والتسجيل';
$string['nit_emails:manage'] = 'تحرير قوالب رسائل الشراء والتسجيل';
$string['privacy:metadata'] = 'لا يخزّن هذا الإضافة أي بيانات شخصية، وإنما يرسل بيانات المستخدم والشراء الموجودة أصلًا في الموقع إلى مستلم كل رسالة.';

// Page.
$string['intro'] = 'هذه هي الرسائل التي يستلمها الطالب بعد شراء دورة، وبعد الاشتراك في إحدى الباقات، وبعد إتمام التسجيل. تُكتب كل رسالة مرتين — بالعربية وبالإنجليزية — ويُختار الإصدار المُرسل بحسب لغة المستلم نفسه (أو لغة الموقع الافتراضية إن لم يحدد لغة).';
$string['off'] = 'متوقفة';
$string['enabled'] = 'إرسال هذه الرسالة';
$string['enabled_help'] = 'أزل العلامة لإيقاف إرسال هذه الرسالة. يظل القالب محفوظًا، فيمكنك إعادة تفعيلها لاحقًا دون إعادة كتابتها.';
$string['subject'] = 'عنوان الرسالة';
$string['body'] = 'نص الرسالة';
$string['lang_en'] = 'النسخة الإنجليزية';
$string['lang_ar'] = 'النسخة العربية';
$string['resetdefaults'] = 'استعادة الصيغة الافتراضية';
$string['resetdone'] = 'تمت استعادة الصيغة الافتراضية لهذه الرسالة.';

// Events.
$string['event_course_purchase'] = 'شراء دورة';
$string['event_course_purchase_desc'] = 'تُرسل فور تأكيد دفع قيمة الدورة وتسجيل الطالب فيها، وتتضمن ملخص ملف الدورة: عدد الساعات، والمحاضر، والفئة المستهدفة، والمتطلبات الأساسية، وهيكل البرنامج، ومخرجات التعلم المستهدفة.';
$string['event_subscription_purchase'] = 'شراء اشتراك';
$string['event_subscription_purchase_desc'] = 'تُرسل فور تفعيل باقة الاشتراك، وتتضمن تفاصيل الباقة: ما تشمله، ومدتها، وتاريخ انتهائها، والمبلغ المدفوع.';
$string['event_registration'] = 'إتمام التسجيل';
$string['event_registration_desc'] = 'تُرسل بعد تأكيد الحساب الجديد وتسجيل صاحبه الدخول لأول مرة، وتؤكد بيانات الحساب وتوجهه إلى خطواته الأولى.';

// Preview and test.
$string['previewlang'] = 'معاينة: {$a}';
$string['sendtestlang'] = 'إرسال نسخة تجريبية: {$a}';
$string['sendtest_desc'] = 'تُعبّأ النسخة التجريبية ببيانات نموذجية وتُرسل إلى بريدك أنت، {$a}.';
$string['test'] = 'تجريبية';
$string['testsent'] = 'تم إرسال نسخة تجريبية إلى {$a}.';
$string['testfailed'] = 'تعذّر إرسال الرسالة التجريبية إلى {$a}. راجع إعدادات البريد الصادر للموقع.';

// Placeholder reference.
$string['placeholders'] = 'الحقول المتغيرة التي يمكنك استخدامها';
$string['placeholders_desc'] = 'اكتب أي حقل متغير في العنوان أو في نص الرسالة، وسيُستبدل بقيمته الحقيقية عند الإرسال. أما الحقل الذي لا توجد له قيمة — كدورة لم يُحدد لها عدد ساعات — فيُستبدل بشَرطة.';
$string['placeholder'] = 'الحقل المتغير';

$string['ph_firstname'] = 'الاسم الأول للمستلم.';
$string['ph_lastname'] = 'اسم عائلة المستلم.';
$string['ph_fullname'] = 'اسم المستلم بالكامل.';
$string['ph_username'] = 'اسم المستخدم الخاص بالمستلم.';
$string['ph_email'] = 'البريد الإلكتروني للمستلم.';
$string['ph_sitename'] = 'اسم الموقع.';
$string['ph_siteurl'] = 'عنوان الموقع.';
$string['ph_loginurl'] = 'رابط صفحة تسجيل الدخول.';
$string['ph_dashboardurl'] = 'رابط لوحة تحكم المستلم.';
$string['ph_date'] = 'تاريخ اليوم بلغة المستلم.';
$string['ph_supportemail'] = 'بريد الدعم الفني للموقع.';

$string['ph_coursename'] = 'عنوان الدورة المشتراة.';
$string['ph_courseurl'] = 'رابط يفتح الدورة.';
$string['ph_coursesummary'] = 'نص ملخص الدورة.';
$string['ph_coursestartdate'] = 'تاريخ بدء الدورة.';
$string['ph_totalhours'] = 'إجمالي عدد الساعات (حقل الدورة "total_number_of_hours").';
$string['ph_instructors'] = 'أسماء المحاضرين في الدورة.';
$string['ph_targetaudience'] = 'الفئة المستهدفة (حقل الدورة "target_audience") في صورة قائمة.';
$string['ph_prerequisites'] = 'المتطلبات الأساسية (حقل الدورة "prerequisites") في صورة قائمة.';
$string['ph_coursecontent'] = 'محتوى الدورة وهيكل البرنامج: أقسام الدورة وعدد الأنشطة في كل قسم.';
$string['ph_ilos'] = 'مخرجات التعلم المستهدفة (حقل الدورة "ilos" أو "by_the_end_of_training") في صورة قائمة.';
$string['ph_amount'] = 'المبلغ المدفوع.';
$string['ph_currency'] = 'عملة المبلغ المدفوع.';
$string['ph_orderid'] = 'رقم طلب الدفع.';

$string['ph_subscriptionname'] = 'اسم باقة الاشتراك.';
$string['ph_subscriptiondescription'] = 'وصف ما تشمله الباقة.';
$string['ph_durationdays'] = 'مدة سريان الباقة.';
$string['ph_startdate'] = 'تاريخ تفعيل الاشتراك.';
$string['ph_expirydate'] = 'تاريخ انتهاء الاشتراك.';
$string['ph_seats'] = 'عدد المقاعد (لباقات الشركات فقط).';
$string['ph_subscriptiontype'] = 'باقة أفراد أم باقة شركات.';
$string['ph_coursesurl'] = 'رابط قائمة الدورات.';
$string['ph_mysubscriptionsurl'] = 'رابط صفحة سجل مشتريات المستلم.';

$string['ph_profileurl'] = 'رابط إعدادات الملف الشخصي للمستلم.';
$string['ph_browsecoursesurl'] = 'رابط قائمة الدورات.';

// Values used inside the rendered emails.
$string['subtype_normal'] = 'اشتراك أفراد';
$string['subtype_b2b'] = 'اشتراك شركات';
$string['nday'] = 'يوم واحد';
$string['ndays'] = '{$a} يومًا';
$string['nhour'] = 'ساعة واحدة';
$string['nhours'] = '{$a} ساعة';
$string['nactivity'] = 'نشاط واحد';
$string['nactivities'] = '{$a} من الأنشطة';
$string['footer_automated'] = 'أُرسلت هذه الرسالة تلقائيًا، ولا داعي للرد عليها.';

// Sample data used by the preview and the test send.
$string['sample_coursename'] = 'أساسيات إدارة المشروعات';
$string['sample_coursesummary'] = 'مقدمة عملية في تخطيط المشروعات وتنفيذها وإغلاقها.';
$string['sample_instructor'] = 'د. منى عادل';
$string['sample_audience1'] = 'قادة الفرق المعيّنون حديثًا';
$string['sample_audience2'] = 'المهندسون المنتقلون إلى أدوار تنسيقية';
$string['sample_prereq'] = 'إلمام عملي بجداول البيانات';
$string['sample_ilo1'] = 'إعداد جدول زمني وميزانية واقعية للمشروع';
$string['sample_ilo2'] = 'تحديد مخاطر المشروع وترتيبها ومعالجتها';
$string['sample_module1'] = 'الوحدة الأولى — بدء المشروع';
$string['sample_module2'] = 'الوحدة الثانية — تخطيط النطاق والزمن والتكلفة';
$string['sample_planname'] = 'باقة الوصول الكامل السنوية';
$string['sample_plandesc'] = 'وصول غير محدود إلى جميع دورات المنصة لمدة عام كامل.';

// AC-4.5.5 - تفضيلات البريد الإلكتروني للمتعلّم.
$string['prefstitle'] = 'تفضيلات البريد الإلكتروني';
$string['prefsintro'] = 'اختر الرسائل التي تودّ أن تصلك منّا. أما الرسائل الخاصة بحسابك وبأمانه فتُرسَل دائمًا.';
$string['prefssaved'] = 'تم حفظ تفضيلات البريد الإلكتروني.';

$string['group_marketing'] = 'العروض والأخبار';
$string['group_transactional'] = 'حسابك ومشترياتك';
$string['group_security'] = 'الأمان';

$string['kind_offers'] = 'الخصومات والعروض';
$string['kind_offers_desc'] = 'أكواد الخصم والعروض محدودة المدة على الدورات والباقات.';
$string['kind_newcourses'] = 'الدورات الجديدة';
$string['kind_newcourses_desc'] = 'رسالة عند نشر دورة جديدة في مجال سبق أن درست فيه.';
$string['kind_newsletter'] = 'النشرة البريدية';
$string['kind_newsletter_desc'] = 'أخبار الأكاديمية من حين إلى آخر.';

$string['kind_registration'] = 'رسالة الترحيب';
$string['kind_registration_desc'] = 'تُرسَل مرة واحدة عند تأكيد بريدك الإلكتروني.';
$string['kind_course_purchase'] = 'تأكيد شراء دورة';
$string['kind_course_purchase_desc'] = 'يؤكّد الدورة التي اشتريتها وكيفية البدء فيها.';
$string['kind_subscription_purchase'] = 'تأكيد شراء باقة';
$string['kind_subscription_purchase_desc'] = 'يؤكّد الباقة التي اشتريتها وما تتيحه ولأي مدة.';
$string['kind_invoice'] = 'الفواتير والإيصالات';
$string['kind_invoice_desc'] = 'سجلّ ما دفعته، وقد تحتاجه لحساباتك الخاصة.';
$string['kind_expiry'] = 'تنبيهات قرب انتهاء الاشتراك';
$string['kind_expiry_desc'] = 'تنبيه قبل انتهاء وصولك إلى دورة أو باقة.';
$string['kind_accountsecurity'] = 'تنبيهات أمان الحساب';
$string['kind_accountsecurity_desc'] = 'تغيير كلمة المرور، ومحاولات الدخول التي رفضناها، وقفل الحساب. وكثيرًا ما تكون هذه هي العلامة الوحيدة على أن شخصًا آخر يستخدم حسابك.';
