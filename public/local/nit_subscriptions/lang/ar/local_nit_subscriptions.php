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
 * Arabic strings for local_nit_subscriptions.
 *
 * Only the student-facing wording is translated here; every other key falls back to
 * English, which is what the admin screens of this plugin are written in.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Public plan-details page (/local/nit_subscriptions/plan.php).
$string['plan_details']        = 'تفاصيل الخطة';
$string['plan_viewdetails']    = 'عرض التفاصيل';
$string['plan_allplans']       = 'خطط الاشتراك';
$string['plan_notavailable']   = 'خطة الاشتراك هذه غير متاحة.';
$string['plan_about']          = 'عن هذه الخطة';
$string['plan_included']       = 'الكورسات المشمولة في هذه الخطة';
$string['plan_included_none']  = 'لم تتم إضافة أي كورسات إلى هذه الخطة بعد.';
$string['plan_included_count'] = '{$a} كورس متاح';
$string['plan_included_one']   = 'كورس واحد متاح';
$string['plan_perdays']        = 'لمدة {$a} يوم';
$string['plan_duration']       = 'المدة';
$string['plan_days']           = '{$a} يوم';
$string['plan_b2b']            = 'متاحة للشركات';
$string['plan_seats']          = 'أسعار الفرق';
$string['plan_seats_intro']    = 'اشترِ عدة مقاعد دفعة واحدة وادفع أقل لكل مقعد.';
$string['plan_seats_col']      = 'عدد المقاعد';
$string['plan_seats_save']     = 'توفّر';
$string['plan_seats_price']    = 'الإجمالي';
$string['plan_current']        = 'خطتك الحالية';
$string['plan_daysleft']       = 'متبقٍ {$a} يوم';
$string['plan_activenow']      = 'مفعّلة';
$string['plan_otheractive']    = 'لديك اشتراك نشط بالفعل، لذا لا يمكن شراء هذه الخطة الآن.';
$string['plan_opencourse']     = 'فتح';
$string['plan_price_from']     = 'السعر';
$string['plan_login_tosubscribe'] = 'سجّل الدخول للاشتراك';

// Buy modal (shared with the home-page block).
$string['sub_confirm_title']   = 'تأكيد الاشتراك';
$string['sub_confirm_intro']   = 'أنت على وشك الاشتراك في هذه الخطة. سيتم تحويلك إلى صفحة الدفع الآمن لإتمام العملية.';
$string['sub_duration_label']  = 'المدة';
$string['sub_total_label']     = 'الإجمالي';
$string['sub_coupon_label']    = 'كوبون';
$string['sub_coupon_apply']    = 'تطبيق';
$string['sub_discount_label']  = 'الخصم';
$string['sub_secure_kashier']  = 'دفع آمن عبر Kashier';
$string['sub_proceed_payment'] = 'متابعة الدفع';
$string['sub_buy']             = 'اشترك الآن';
$string['plan_offer']          = 'عرض';

// ── Renewal reminders ───────────────────────────────────────────────────────────
$string['messageprovider:subscriptionreminder'] = 'قرب انتهاء الاشتراك';
$string['task_send_subscription_reminders'] = 'إرسال تذكيرات قرب انتهاء الاشتراك';

$string['tab_plans']     = 'الخطط والأسعار';
$string['tab_courses']   = 'إتاحة الكورسات';
$string['tab_users']     = 'اشتراكات المستخدمين';
$string['tab_reminders'] = 'تذكيرات التجديد';

$string['rem_heading']   = 'تذكيرات التجديد';
$string['rem_desc']      = 'نبّه المشتركين قبل انتهاء خطتهم، وأتِح لهم التجديد مبكرًا. يُرسل تذكير واحد لكل مهلة أدناه، ويظهر زر التجديد في بطاقة الخطة اعتبارًا من أكبر مهلة.';
$string['rem_enabled']   = 'إرسال تذكيرات قرب الانتهاء';
$string['rem_enabled_help'] = 'أوقف هذا الخيار لإيقاف كل التذكيرات وإخفاء زر التجديد. لن تفقد شيئًا — تبقى المهل محفوظة.';
$string['rem_days']      = 'أرسل تذكيرًا قبل انتهاء الخطة بهذا العدد من الأيام';
$string['rem_days_help'] = 'أضف مدخلًا لكل تنبيه، مثلًا 7 و3 و1. من يوم واحد حتى {$a} يومًا.';
$string['rem_days_add']  = 'إضافة مهلة';
$string['rem_days_none'] = 'لا توجد مهل بعد — أضف واحدة على الأقل.';
$string['rem_day_unit']  = 'يوم قبل الانتهاء';
$string['rem_remove']    = 'حذف';
$string['rem_save']      = 'حفظ وتطبيق الآن';
$string['rem_applied']   = 'تم الحفظ. أُرسل {$a->sent} تذكير الآن، وأُزيل {$a->cleared} سجل تذكير قديم.';
$string['rem_preview']   = 'حاليًا سيصل التذكير إلى {$a->due} من {$a->active} مشترك نشط.';
$string['rem_window_note'] = 'يعتمد زر التجديد على أكبر مهلة: {$a} يوم قبل انتهاء الخطة.';
$string['rem_window_off']  = 'التذكيرات موقوفة، لذا لا يظهر زر التجديد.';
$string['rem_recalc_note'] = 'الحفظ يعيد فحص كل الاشتراكات النشطة فورًا: كل من تشمله المهلة الجديدة يصله التذكير الآن، وتُمسح سجلات التذكير للمهل التي حذفتها حتى يمكن إرسالها مجددًا إذا أعدتها.';
$string['rem_col_days']  = 'المهلة';

$string['err_reminderdaysrequired'] = 'أضف مهلة واحدة على الأقل، أو أوقف التذكيرات.';

$string['reminder_msg_subject'] = 'اشتراكك "{$a->plan}" ينتهي خلال {$a->days} يوم';
$string['reminder_msg_body']    = 'اشتراكك "{$a->plan}" ينتهي في {$a->expires} — أي بعد {$a->days} يوم من الآن. جدّد قبل ذلك وستبدأ الفترة الجديدة من يوم انتهاء الفترة الحالية، فلا تفقد أي وقت.';
$string['reminder_msg_small']   = 'اشتراكك ينتهي خلال {$a->days} يوم.';
$string['reminder_msg_action']  = 'جدّد اشتراكك';

$string['sub_renew']            = 'جدّد الآن';
$string['sub_renew_endsin']     = 'ينتهي خلال {$a} يوم';
$string['sub_renew_note']       = 'التجديد الآن يمدّد خطتك من يوم انتهائها الحالي — لن تفقد أيًّا من المدة المتبقية.';
$string['sub_renew_confirm']    = 'تأكيد التجديد';
$string['sub_renew_newexpiry']  = 'تاريخ الانتهاء الجديد';
