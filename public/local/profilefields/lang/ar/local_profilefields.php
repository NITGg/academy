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

// Management page.
$string['managefields'] = 'تنظيم حقول التسجيل والملف الشخصي';
$string['manageintro'] = 'حدّد الحقول التي يملؤها المستخدم الجديد في <a href="{$a}">صفحة إنشاء حساب</a>، والحقول التي يراها المستخدم الحالي عند تعديل ملفه الشخصي. استخدم صفحة <em>حقول الملف الشخصي</em> الأصلية لإنشاء الحقول المخصصة وتعديلها وترتيبها، واستخدم هذه الصفحة لتحديد مكان ظهورها.';

// Username.
$string['usernameheading'] = 'اسم المستخدم';
$string['usernameintro'] = 'يحتاج مودل دائمًا إلى اسم مستخدم، لكنه لا يحتاج دائمًا إلى سؤال المستخدم عنه.';
$string['usernamefromemail'] = 'توليد اسم المستخدم من البريد الإلكتروني';
$string['usernamefromemail_help'] = 'عند اختيار "نعم" يُحذف حقل اسم المستخدم من صفحة إنشاء الحساب، ويُولَّد اسم المستخدم من البريد الإلكتروني الذي يكتبه المستخدم، ثم يسجّل الدخول ببريده الإلكتروني.

لا يتأثر أي حساب قائم، ويستطيع المدير كما هو الحال دائمًا تحديد اسم المستخدم يدويًا عند إنشاء حساب.';
$string['usernamesource'] = 'مصدر اسم المستخدم';
$string['usernamesource_help'] = 'إما البريد الإلكتروني كاملًا (فيصبح ali@example.com اسم المستخدم) أو الجزء الذي يسبق علامة "@" فقط (فيصبح ali). وفي الحالتين يُضاف رقم إذا كان الاسم مستخدمًا من قبل.';
$string['usernamesourceemail'] = 'البريد الإلكتروني كاملًا';
$string['usernamesourcelocalpart'] = 'الجزء الذي يسبق علامة "@"';

// Core fields.
$string['corefieldsheading'] = 'حقول مودل الأساسية';
$string['corefieldsintro'] = 'الحقول التي يأتي بها مودل. اترك خانة التسمية فارغة للإبقاء على التسمية الأصلية، ورقم الترتيب يؤثر في صفحة إنشاء الحساب فقط، حيث تظهر الحقول من الرقم الأصغر إلى الأكبر. أما الحقول التي لا يعمل الحساب بدونها — كلمة المرور والبريد الإلكتروني والاسم — فلا يمكن إيقافها.';
$string['optionalcorefields'] = 'القسم الاختياري (الرقم التعريفي، المؤسسة، القسم، الهاتف، العنوان)';
$string['labeloverrideplaceholder'] = 'التسمية';
$string['orderplaceholder'] = 'ترتيب';

// Custom fields.
$string['customfieldsheading'] = 'حقول الملف الشخصي المخصصة';
$string['customfieldsintro'] = 'الحقول المعرَّفة في هذا الموقع.';
$string['customfieldsnone'] = 'لم يتم إنشاء أي حقول مخصصة بعد.';
$string['createnewfield'] = 'إنشاء حقل جديد:';

// Placement.
$string['modeboth'] = 'التسجيل والملف الشخصي';
$string['modesignup'] = 'التسجيل فقط';
$string['modeprofile'] = 'الملف الشخصي فقط';
$string['modehidden'] = 'مخفي';
$string['modehiddencustom'] = 'مخفي (للمديرين فقط)';

$string['privacy:metadata'] = 'إضافة حقول التسجيل والملف الشخصي تخزّن فقط إعدادات عرض الحقول، ولا تخزّن أي بيانات شخصية.';
