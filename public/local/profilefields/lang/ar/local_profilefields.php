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
$string['tabprofile_intro'] = 'اختر الحقول التي يراها المستخدم عند تعديل ملفه الشخصي، وهل كل حقل إجباري أم فريد أم يمكن للمستخدم تعديله.';

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

// Username.
$string['usernameheading'] = 'اسم المستخدم';
$string['usernamefromemail'] = 'توليد اسم المستخدم من البريد الإلكتروني (وإخفاء خانة اسم المستخدم)';
$string['usernamesource'] = 'مصدر اسم المستخدم';
$string['usernamesourceemail'] = 'البريد الإلكتروني كاملًا';
$string['usernamesourcelocalpart'] = 'الجزء الذي يسبق علامة "@"';

// Terms & privacy.
$string['termsheading'] = 'الشروط وسياسة الخصوصية';
$string['termsmanage'] = 'إدارة مستندات السياسات';
$string['consentenable'] = 'إظهار مربّع موافقة في صفحة التسجيل';
$string['consentenable_desc'] = 'عند التفعيل يُضاف إلى صفحة التسجيل نفسها مربّع إجباري «أوافق على السياسات» بدلًا من صفحة الموافقة المنفصلة. تبقى مستندات السياسات مكتوبة ومُؤرشَفة في أداة السياسات بمودل، وينتقل مجرّد التأشير إلى النموذج.';
$string['consentlabel'] = 'أوافق على {$a}.';
$string['consentlabelplain'] = 'أوافق على شروط الاستخدام وسياسة الخصوصية.';
$string['consentrequired'] = 'يجب الموافقة على السياسات قبل إنشاء الحساب.';
$string['and'] = 'و';
$string['termsdocsfound'] = 'سيرتبط المربّع بمستندات السياسات التالية:';
$string['termsdocsnone'] = 'لا توجد بعدُ مستندات سياسات للضيوف. أنشئها بالأسفل وستُربَط بالمربّع تلقائيًا؛ وحتى ذلك الحين يعرض المربّع نصًّا عامًّا.';
$string['termsdoubleask'] = 'أداة السياسات في مودل مضبوطة أيضًا على السؤال في صفحة منفصلة، فيُسأل المستخدم مرّتين. أثناء تفعيل المربّع المُضمَّن، افتح <a href="{$a}">إعدادات الخصوصية</a> واضبط مُعالِج سياسات الموقع على «غير محدّد».';

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
