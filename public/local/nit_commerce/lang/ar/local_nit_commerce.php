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
 * Arabic strings for local_nit_commerce.
 *
 * Only the student-facing wording is translated here; every other key falls back to
 * English, which is what the admin screens of this plugin are written in.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Scope labels, shown on the public coupon page as well as in the admin table.
$string['scope_all_course']       = 'كل الكورسات';
$string['scope_all_package']      = 'كل الباقات';
$string['scope_all_subscription'] = 'كل الاشتراكات';
$string['scope_all_program']      = 'كل البرامج';

$string['cpn_type_percent']   = 'نسبة مئوية';
$string['cpn_type_fixed']     = 'مبلغ ثابت';
$string['cpn_usage_once']     = 'استخدام لمرة واحدة';
$string['cpn_usage_multiple'] = 'استخدام متعدد';
$string['cpn_unlimited']      = 'غير محدود';
$string['cpn_col_max']        = 'أقصى خصم';
$string['cpn_col_usage']      = 'الاستخدام';

// Public coupon-details page (/local/nit_commerce/coupon.php).
$string['cpn_details']       = 'تفاصيل الكوبون';
$string['cpn_viewdetails']   = 'عرض التفاصيل';
$string['cpn_allcoupons']    = 'الكوبونات المتاحة';
$string['cpn_notavailable']  = 'هذا الكوبون غير متاح.';
$string['cpn_about']         = 'عن هذا الكوبون';
$string['cpn_code_label']    = 'كود الكوبون';
$string['cpn_copy']          = 'نسخ الكود';
$string['cpn_copied']        = 'تم النسخ';
$string['cpn_off_percent']   = 'خصم {$a}%';
$string['cpn_off_fixed']     = 'خصم {$a}';
$string['cpn_where']         = 'أين يمكنك استخدامه';
$string['cpn_where_none']    = 'لم تتم إضافة أي عناصر إلى هذا الكوبون بعد.';
$string['cpn_courses_under'] = 'الكورسات المشمولة في {$a}';
$string['cpn_browse_all']    = 'تصفّح الكورسات';
$string['cpn_view_plan']     = 'عرض الخطة';
$string['cpn_open_course']   = 'فتح';
$string['cpn_terms']         = 'الشروط';
$string['cpn_uses_left']     = 'متبقٍ {$a} استخدام';
$string['cpn_uses_none']     = 'تم استهلاكه بالكامل';
$string['cpn_no_expiry']     = 'بدون تاريخ انتهاء';
$string['cpn_starts']        = 'يبدأ';
$string['cpn_expires']       = 'ينتهي';
$string['cpn_howto']         = 'أدخل هذا الكود عند الدفع للحصول على الخصم.';
$string['cpn_stub_off']      = 'خصم';
