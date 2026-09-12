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
 * Arabic language strings for local_nit_finance.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT للماليات';
$string['local_nit_finance:manage'] = 'إدارة ماليات منصة NIT وطلبات السحب';

// Admin page.
$string['financialreports'] = 'التقارير المالية';
$string['platformwallet'] = 'محفظة المنصة';
$string['currentmoney'] = 'الرصيد الحالي';
$string['undistributedmoney'] = 'أموال الباقات غير الموزَّعة';
$string['teachersmoney'] = 'أموال المعلّمين';
$string['platformearnings'] = 'أرباح المنصة';
$string['totalpaidout'] = 'إجمالي المدفوع';
$string['withdrawals'] = 'طلبات السحب';
$string['nowithdrawals'] = 'لا توجد طلبات سحب.';
$string['teacher'] = 'المعلّم';
$string['amount'] = 'المبلغ';
$string['method'] = 'الطريقة';
$string['status'] = 'الحالة';
$string['actions'] = 'الإجراءات';
$string['approve'] = 'موافقة';
$string['reject'] = 'رفض';
$string['pay'] = 'تعليم كمدفوع';

// Statuses.
$string['status_pending'] = 'قيد الانتظار';
$string['status_approved'] = 'مُعتمَد';
$string['status_rejected'] = 'مرفوض';
$string['status_paid'] = 'مدفوع';

// Errors.
$string['err_amountpositive'] = 'يجب أن يكون المبلغ أكبر من صفر.';
$string['err_busy'] = 'يوجد طلب سحب آخر قيد المعالجة لهذا المعلّم. من فضلك حاول مرة أخرى بعد لحظات.';
$string['err_insufficientbalance'] = 'المبلغ المطلوب يتجاوز الرصيد المتاح.';
$string['err_withdrawalnotfound'] = 'لم يُعثَر على طلب السحب.';
$string['err_withdrawalstate'] = 'طلب السحب ليس في حالة تسمح بهذا الإجراء.';
$string['err_reasonrequired'] = 'السبب مطلوب.';
$string['err_badaction'] = 'إجراء غير معروف.';
$string['err_notdistributed'] = 'تعذّر توزيع الدرس (لا توجد عملية شراء صالحة).';
$string['err_lessonnotfound'] = 'لم يُعثَر على الدرس.';
$string['err_earningnotfound'] = 'لا يوجد ربح نشط لهذا الدرس.';
$string['err_alreadyreversed'] = 'تم عكس هذا الربح بالفعل.';

// Privacy.
$string['privacy:metadata:nit_earning'] = 'سجلّات توزيع الإيراد التي تُضاف لرصيد المعلّم مقابل درس مكتمل.';
$string['privacy:metadata:nit_earning:teacherid'] = 'المعلّم المُضاف لرصيده الربح.';
$string['privacy:metadata:nit_earning:teacher_amount_minor'] = 'حصة المعلّم، بوحدات العملة الصغرى.';
$string['privacy:metadata:nit_earning:timecreated'] = 'وقت تسجيل الربح.';
$string['privacy:metadata:nit_withdrawal'] = 'طلبات المعلّمين لسحب الأموال المكتسبة.';
$string['privacy:metadata:nit_withdrawal:teacherid'] = 'المعلّم مُقدِّم طلب السحب.';
$string['privacy:metadata:nit_withdrawal:amount_minor'] = 'المبلغ المطلوب، بوحدات العملة الصغرى.';
$string['privacy:metadata:nit_withdrawal:account'] = 'تفاصيل حساب الاستلام التي يوفّرها المعلّم.';
$string['privacy:metadata:nit_withdrawal:timecreated'] = 'وقت تقديم الطلب.';

// ── التقرير المالي المشترك (لوحة واحدة، خمس صفحات) ────────────────────────────────
$string['rep_tab'] = 'التقارير';
$string['rep_noaccess'] = 'ليست لديك صلاحية الاطّلاع على الأرقام المالية.';
$string['rep_loading'] = 'جارٍ التحميل…';
$string['rep_none'] = 'لا توجد نتائج مطابقة لهذه الفلاتر.';
$string['rep_error'] = 'تعذّر تحميل التقرير. من فضلك حاول مرة أخرى.';
$string['rep_truncated'] = 'يتم عرض أحدث الطلبات فقط. ضيّق النطاق الزمني، أو صدّر ملف CSV للحصول على القائمة كاملة.';

// النطاقات.
$string['rep_scope_all'] = 'كل الإيرادات';
$string['rep_scope_courses'] = 'الدورات';
$string['rep_scope_subscriptions'] = 'الاشتراكات';
$string['rep_scope_coupons'] = 'الكوبونات';
$string['rep_scope_offers'] = 'العروض';
$string['rep_scope_refunds'] = 'المبالغ المستردة';

// ماذا يعرض كل نطاق، في سطر واحد.
$string['rep_intro'] = 'ما حقّقته المنصة من أرباح، ومصدر كل جنيه منها.';
$string['rep_intro_all'] = 'كل جنيه دخل المنصة، ومصدره: دورة أو باقة، بالسعر الكامل أو عبر كوبون أو عرض. اضغط أي صف في جدول التقسيم لعرض طلباته وحدها.';
$string['rep_intro_courses'] = 'أرباح شراء الدورات المفردة: ما حقّقته كل دورة، وما كلّفته الخصومات، وما تم استرداده.';
$string['rep_intro_subscriptions'] = 'أرباح باقات الاشتراك: ما حقّقته كل باقة، وما كلّفته الخصومات، وما تم استرداده.';
$string['rep_intro_coupons'] = 'الطلبات التي خُصم عليها كوبون فعليًّا — ما حقّقه كل كود، وما تنازل عنه مقابل ذلك.';
$string['rep_intro_offers'] = 'الطلبات التي خُصم عليها عرض تلقائي فعليًّا — ما حقّقه كل عرض، وما تنازل عنه مقابل ذلك.';
$string['rep_intro_refunds'] = 'الأموال التي خرجت مرة أخرى، وكيف خرجت: عبر بوابة الدفع، أو بإلغاء مدير للشراء.';

// الفلاتر.
$string['rep_apply'] = 'تطبيق';
$string['rep_reset'] = 'إعادة تعيين';
$string['rep_export'] = 'تصدير CSV';
$string['rep_search'] = 'بحث';
$string['rep_search_ph'] = 'المشتري أو البريد أو رقم الطلب';
$string['rep_from'] = 'من';
$string['rep_to'] = 'إلى';
$string['rep_state'] = 'الطلبات';
$string['rep_currency'] = 'العملة';
$string['rep_narrow'] = 'تصفية على';
$string['rep_all'] = 'الكل';
$string['rep_state_paid'] = 'المدفوعة (بما فيها المستردة)';
$string['rep_state_completed'] = 'المدفوعة والقائمة';
$string['rep_state_refunded'] = 'المستردة فقط';
$string['rep_state_lost'] = 'التي لم تُدفع';
$string['rep_state_all'] = 'كل المحاولات';
$string['rep_clearsource'] = 'عرض كل المصادر مرة أخرى';

// الإجماليات.
$string['rep_kpi_gross'] = 'القيمة قبل الخصم';
$string['rep_kpi_gross_help'] = 'ما كانت ستكلّفه هذه الطلبات بدون خصم.';
$string['rep_kpi_discount'] = 'الخصومات';
$string['rep_kpi_discount_help'] = 'ما تم التنازل عنه عبر الكوبونات والعروض.';
$string['rep_kpi_net'] = 'المُحصَّل';
$string['rep_kpi_net_help'] = 'ما دفعه المشترون فعليًّا.';
$string['rep_kpi_refunded'] = 'المُسترَد';
$string['rep_kpi_refunded_help'] = 'أموال أُعيدت إلى المشترين.';
$string['rep_kpi_earned'] = 'صافي الربح';
$string['rep_kpi_earned_help'] = 'المُحصَّل بعد خصم المستردات. هذا هو الربح.';
$string['rep_kpi_orders'] = 'الطلبات';
$string['rep_kpi_orders_help'] = '{$a->learners} مشترٍ · متوسط {$a->average}';

// محاور التقسيم.
$string['rep_by_source'] = 'مصدر الأرباح';
$string['rep_by_course'] = 'حسب الدورة';
$string['rep_by_plan'] = 'حسب الباقة';
$string['rep_by_coupon'] = 'حسب الكوبون';
$string['rep_by_offer'] = 'حسب العرض';
$string['rep_by_refundkind'] = 'حسب طريقة الاسترداد';

// الأعمدة.
$string['rep_col_share'] = 'النسبة';
$string['rep_col_orders'] = 'الطلبات';
$string['rep_col_learners'] = 'المشترون';
$string['rep_col_gross'] = 'قبل الخصم';
$string['rep_col_discount'] = 'الخصم';
$string['rep_col_net'] = 'المُحصَّل';
$string['rep_col_refunded'] = 'المُسترَد';
$string['rep_col_earned'] = 'صافي الربح';
$string['rep_col_date'] = 'التاريخ';
$string['rep_col_order'] = 'رقم الطلب';
$string['rep_col_user'] = 'المشتري';
$string['rep_col_item'] = 'المُشترى';
$string['rep_col_source'] = 'المصدر';
$string['rep_col_detail'] = 'سبب تغيّر السعر';
$string['rep_col_status'] = 'الحالة';
$string['rep_col_total'] = 'الإجمالي';
$string['rep_orders_heading'] = 'الطلبات';
$string['rep_pager'] = 'عرض {$a->first}–{$a->last} من {$a->total}';
$string['rep_prev'] = 'السابق';
$string['rep_next'] = 'التالي';

// ماذا بيع، وما الذي جلب المشتري.
$string['rep_item_course'] = 'دورة';
$string['rep_item_subscription'] = 'اشتراك';
$string['rep_source_full'] = '{$a} — السعر الكامل';
$string['rep_source_coupon'] = '{$a} — كوبون';
$string['rep_source_offer'] = '{$a} — عرض';
$string['rep_detail_coupon'] = 'كوبون {$a->code} (−{$a->amount})';
$string['rep_detail_offer'] = 'عرض {$a->name} (−{$a->amount})';
$string['rep_detail_couponbeaten'] = 'الكوبون {$a} تفوّق عليه العرض';
$string['rep_offer'] = 'عرض';
$string['rep_nocode'] = 'بدون كود';
$string['rep_itemgone'] = '(محذوف)';

// كيف تم الاسترداد.
$string['rep_refund_gateway'] = 'عبر بوابة الدفع';
$string['rep_refund_manual'] = 'إلغاء بواسطة مدير';
$string['rep_refund_unrecorded'] = 'مُعلَّم كمسترد بدون سجلّ';

// الصفحة الرئيسية.
$string['rep_masterintro'] = 'كل الأرقام المالية للمنصة في مكان واحد. ونفس هذا التقرير يظهر — مُصفّى مسبقًا — في تبويب "التقارير" في صفحات الدورات والاشتراكات والكوبونات والعروض.';

// الطلبات التي لم تُدفع. لا تظهر إلا عند اختيار حالة تشملها، ولهذا تظهر البطاقة والعمود معًا
// أو يختفيان معًا.
$string['rep_kpi_lost'] = 'لم تُدفع';
$string['rep_kpi_lost_help'] = 'طلبات لم تكتمل: {$a->orders}';
$string['rep_col_lost'] = 'لم تُدفع';
