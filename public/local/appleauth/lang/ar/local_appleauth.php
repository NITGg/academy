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
 * Sign in with Apple (mobile) - Arabic language strings.
 *
 * @package    local_appleauth
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'تسجيل الدخول عبر Apple (التطبيق)';
$string['enabled'] = 'تفعيل نقطة توكن Apple للتطبيق';
$string['enabled_desc'] = 'السماح للتطبيق باستبدال توكن هوية Apple الموثّق بتوكن خدمات Moodle عبر /local/appleauth/token.php.';
$string['clientids'] = 'معرّفات عملاء Apple المسموح بها';
$string['clientids_desc'] = 'قيم الـ "aud" المقبولة في توكن هوية Apple، واحدة في كل سطر أو مفصولة بفواصل: <strong>Bundle ID</strong> لتطبيق iOS (الدخول الأصلي)، و<strong>Services ID</strong> إن وُجد (الدخول من أندرويد / الويب).';
$string['allowcreate'] = 'إنشاء المستخدمين تلقائيًا';
$string['allowcreate_desc'] = 'إنشاء حساب Moodle جديد إذا لم يكن هناك حساب مرتبط بمعرّف Apple أو مطابق لبريده الموثّق. إذا عُطّل تُرفض معرّفات Apple غير المعروفة.';
$string['newuserauth'] = 'طريقة المصادقة للمستخدمين الجدد';
$string['newuserauth_desc'] = 'إضافة المصادقة التي تُعيَّن للحسابات المُنشأة تلقائيًا (مثل oauth2 أو manual). يجب أن تكون مفعّلة.';
$string['allowedlinkauth'] = 'طرق المصادقة القابلة للربط';
$string['allowedlinkauth_desc'] = 'قائمة مفصولة بفواصل بطرق المصادقة التي يمكن الدخول لحساباتها عبر Apple (مثل oauth2). يمنع هذا الاستيلاء على حساب بكلمة مرور محلية يشترك في نفس البريد. الافتراضي oauth2.';
$string['ratelimit'] = 'حد الطلبات (طلب/دقيقة لكل IP)';
$string['ratelimit_desc'] = 'أقصى عدد من طلبات التوكن يُقبل من عنوان IP واحد في الدقيقة. الطلبات الزائدة ترجع HTTP 429. ضع 0 لإلغاء الحد. الافتراضي 20.';
$string['cachedef_ratelimit'] = 'عدّادات طلبات الدخول عبر Apple';
$string['cachedef_jwks'] = 'مفاتيح التوقيع العامة لـ Apple';
$string['privacy:metadata:local_appleauth_links'] = 'معرّف Apple الذي يدخل به كل حساب.';
$string['privacy:metadata:local_appleauth_links:userid'] = 'حساب Moodle.';
$string['privacy:metadata:local_appleauth_links:sub'] = 'معرّف Apple للمستخدم.';
$string['privacy:metadata:local_appleauth_links:email'] = 'آخر بريد شاركته Apple (قد يكون عنوان ترحيل خاص).';
$string['privacy:metadata:local_appleauth_links:timecreated'] = 'وقت أول استخدام لمعرّف Apple.';
$string['privacy:metadata:local_appleauth_links:timemodified'] = 'وقت آخر استخدام لمعرّف Apple.';
