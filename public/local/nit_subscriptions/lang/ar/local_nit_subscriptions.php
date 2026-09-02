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
 * Covers both the student-facing pages and the admin screens of this plugin.
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
$string['plan_accessuntil']  = 'الوصول حتى {$a}';
$string['plan_includesrenewal'] = 'يشمل التجديد الذي دفعته بالفعل';

// ── Plugin identity and admin navigation (Site administration + navbar gear menu) ──
$string['pluginname']          = 'الاشتراكات (NIT)';
$string['managesubscriptions'] = 'إدارة الاشتراكات';
$string['managecourses']       = 'إدارة شراء الكورسات';
$string['privacy:metadata'] = 'يخزّن مكوّن الاشتراكات خطط الاشتراك وقواعد إتاحة الكورسات التي ينشئها المشرفون فقط، ولا يخزّن أي بيانات شخصية بذاته.';

// Manage courses (single-course purchases: list + "Unbuy").
$string['mc_desc']            = 'المستخدمون الذين اشتروا كورسًا مفردًا. استخدم «إلغاء الشراء» لإلغاء تسجيل المستخدم وسحب عملية الشراء. كما أن إلغاء تسجيل المشتري من الكورس نفسه يسحب عملية الشراء أيضًا، فيصبح بإمكانه شراؤه من جديد.';
$string['mc_col_course']      = 'الكورس';
$string['mc_col_purchased']   = 'تاريخ الشراء';
$string['mc_none']            = 'لا توجد عمليات شراء كورسات بعد.';
$string['mc_status_enrolled'] = 'مسجَّل';
$string['mc_status_norole']   = 'بلا صلاحية وصول';
$string['mc_status_revoked']  = 'مسحوب';
$string['mc_status_refunded'] = 'مسترد';
$string['mc_unbuy']           = 'إلغاء الشراء';
$string['mc_unbuy_title']     = 'سحب شراء الكورس';
$string['mc_unbuy_confirm']   = 'هل تريد إلغاء تسجيل <b>{$a->user}</b> من <b>{$a->course}</b> وسحب عملية الشراء هذه؟';
$string['mc_unbuy_confirm_norole'] = 'هل تريد سحب شراء <b>{$a->user}</b> لكورس <b>{$a->course}</b>؟ تسجيله في الكورس ملغى بالفعل.';
$string['mc_unbuy_refund']    = 'اعتبار عملية الشراء هذه مستردة';
$string['mc_unbuy_success']   = 'تم سحب شراء الكورس.';
$string['mc_course_deleted']  = '(كورس محذوف)';
$string['mc_txn_notfound']    = 'عملية الشراء غير موجودة.';
$string['mc_not_active']      = 'عملية الشراء هذه غير نشطة ولا يمكن سحبها.';

// إدارة الكورسات — التبويبان.
$string['tab_mc_purchases'] = 'مشتريات الكورسات';
$string['tab_mc_sources']   = 'مصادر التسجيل';

// AC-4.10.5 — تقرير مصادر التسجيل.
$string['es_heading'] = 'من أين جاءت عمليات التسجيل';
$string['es_desc']    = 'كل تسجيل في كورس مع المصدر الذي أنتجه، حتى يمكن قراءة أرقام التسجيل حسب القناة. الشراء المباشر والباقات والكوبونات والعروض تُقرأ من عملية الدفع المرتبطة بالتسجيل؛ وما لم يدفع فيه أحد يكون إما منحًا من مسؤول أو تسجيلًا ذاتيًا من المتعلم.';
$string['es_source_purchase'] = 'شراء مباشر';
$string['es_source_package']  = 'باقة';
$string['es_source_coupon']   = 'كوبون';
$string['es_source_offer']    = 'عرض';
$string['es_source_admin']    = 'منح من مسؤول';
$string['es_source_self']     = 'تسجيل ذاتي';
$string['es_source_other']    = 'أخرى';
$string['es_col_user']     = 'المستخدم';
$string['es_col_course']   = 'الكورس';
$string['es_col_source']   = 'المصدر';
$string['es_col_detail']   = 'التفاصيل';
$string['es_col_amount']   = 'المدفوع';
$string['es_col_enrolled'] = 'تاريخ التسجيل';
$string['es_none']         = 'لا توجد عمليات تسجيل لعرضها بعد.';
$string['es_total']        = 'كل عمليات التسجيل';
$string['es_filter_course']     = 'الكورس';
$string['es_filter_from']       = 'من';
$string['es_filter_to']         = 'إلى';
$string['es_filter_allcourses'] = 'كل الكورسات';
$string['es_filter_apply']      = 'تطبيق';
$string['es_filter_reset']      = 'إعادة ضبط';
$string['es_search_ph']         = 'الاسم أو البريد أو الكورس أو كود الكوبون';
$string['es_export']            = 'تنزيل CSV';
$string['es_inferred']      = 'مُستنتج';
$string['es_inferred_help'] = 'الصفوف المعلَّمة تخص تسجيلات تمت قبل وجود هذا السجل. عملية الدفع والباقة والكوبون والعرض خلفها تُقرأ من المعاملة الفعلية، فهي دقيقة؛ ولم يتعذر استرجاع سوى اسم المسؤول صاحب المنح اليدوي.';
$string['es_truncated'] = 'يتم عرض أحدث {$a->shown} من أصل {$a->total} تسجيل مطابق. ضيّق نطاق التصفية أو نزّل ملف CSV للقائمة كاملة.';
$string['es_pending']   = 'ما زال هناك {$a} تسجيل قديم لم يُصنَّف بعد — أعد فتح هذا التبويب للمتابعة.';

// Shared UI.
$string['ui_refresh']      = 'تحديث';
$string['ui_loading']      = 'جارٍ التحميل…';
$string['ui_save']         = 'حفظ';
$string['ui_cancel']       = 'إلغاء';
$string['ui_edit']         = 'تعديل';
$string['ui_delete']       = 'حذف';
$string['ui_activate']     = 'تفعيل';
$string['ui_deactivate']   = 'إيقاف';
$string['ui_active']       = 'مفعّل';
$string['ui_never']        = 'بلا تاريخ';
$string['ui_optional']     = '(اختياري)';
$string['ui_remove']       = 'إزالة';
$string['ui_search']       = 'بحث';
$string['ui_showmore']     = '+{$a} أخرى';
$string['ui_showless']     = 'عرض أقل';
$string['ui_pager_info']   = 'عرض {from}–{to} من {total}';

// Package-shared column/field labels reused by the subscriptions page.
$string['pkg_col_id']        = 'المعرّف';
$string['pkg_col_name']      = 'الاسم';
$string['pkg_col_price']     = 'السعر';
$string['pkg_col_status']    = 'الحالة';
$string['pkg_col_actions']   = 'إجراءات';
$string['pkg_col_user']      = 'المستخدم';
$string['pkg_col_pricepaid'] = 'المبلغ المدفوع';
$string['pkg_col_expiresat'] = 'تاريخ الانتهاء';
$string['pkg_field_name']    = 'الاسم';
$string['pkg_field_price']   = 'السعر الافتراضي';
$string['pkg_field_name_en'] = 'الاسم (بالإنجليزية)';
$string['pkg_field_name_ar'] = 'الاسم (بالعربية)';
$string['pkg_field_desc_en'] = 'الوصف (بالإنجليزية)';
$string['pkg_field_desc_ar'] = 'الوصف (بالعربية)';
$string['pkg_field_currency'] = 'العملة الافتراضية';
$string['pkg_unassign_paid'] = ' — مدفوع <strong>{$a}</strong>';

// Per-country pricing (managed inside the create/edit subscription form).
$string['sub_prices_heading']     = 'أسعار حسب الدولة';
$string['sub_prices_help']        = 'اختياري. حدّد سعرًا لدول بعينها؛ ومن يشتري من غيرها يدفع السعر الافتراضي أعلاه.';
$string['sub_price_add']          = '+ إضافة سعر لدولة';
$string['sub_price_country']      = 'الدولة';
$string['sub_price_currency']     = 'العملة';
$string['sub_price_amount']       = 'السعر';
$string['sub_price_active']       = 'مفعّل';
$string['sub_price_remove']       = 'إزالة';
$string['sub_price_pickcountry']  = 'اختر الدولة…';

// (Legacy strings from the old standalone pricing page — kept for compatibility.)
$string['sub_pricing']            = 'الأسعار';
$string['subscriptionpricing']    = 'أسعار الاشتراكات';
$string['backtosubscriptions']    = 'العودة إلى الاشتراكات';
$string['price_country']          = 'الدولة';
$string['price_currency']         = 'العملة';
$string['price_amount']           = 'السعر';
$string['price_is_active']        = 'مفعّل';
$string['price_add']              = 'إضافة سعر لدولة';
$string['price_edit']             = 'تعديل سعر الدولة';
$string['price_none']             = 'لا توجد أسعار خاصة بدول بعد. يدفع المشترون السعر الافتراضي أعلاه.';
$string['price_saved']            = 'تم حفظ السعر.';
$string['price_deleted']          = 'تم حذف السعر.';
$string['price_confirmdelete']    = 'هل تريد حذف سعر هذه الدولة؟';
$string['price_default_notice']   = 'السعر الافتراضي (يُستخدم عندما لا يكون لدولة المشتري سعر أدناه): <strong>{$a->price} {$a->currency}</strong>. يمكنك تعديله من خطة الاشتراك نفسها.';

// Subscription plans.
$string['sub_plans_heading']  = 'خطط الاشتراك';
$string['sub_new']            = 'اشتراك جديد';
$string['sub_col_days']       = 'الأيام';
$string['sub_col_courses']    = 'الكورسات';
$string['sub_col_subscription'] = 'الاشتراك';
$string['sub_field_desc']     = 'الوصف (اختياري)';
$string['sub_field_days']     = 'عدد الأيام';
$string['sub_field_b2b']      = 'متاح للشراء من الشركات';
$string['sub_seat_options']   = 'خيارات المقاعد';
$string['sub_seat_options_help'] = 'أضف خيارًا أو أكثر لعدد المستخدمين، لكل منها نسبة خصم خاصة. يُحسب سعر الشركات كالآتي: (السعر العادي × عدد المقاعد) − الخصم.';
$string['sub_col_seats']      = 'المقاعد';
$string['sub_col_discount']   = 'نسبة الخصم %';
$string['sub_col_b2bprice']   = 'سعر الشركات';
$string['sub_seat_add']       = 'إضافة خيار مقاعد';
$string['sub_b2b_badge']      = 'شركات';

// Course availability.
$string['sub_courseavail_heading'] = 'إتاحة الكورسات للاشتراكات';
$string['sub_courseavail_desc']    = 'اختر الكورسات وأضفها إلى اشتراك بعينه.';
$string['sub_target']              = 'الاشتراك المستهدف:';
$string['sub_select_placeholder']  = 'اختر اشتراكًا...';
$string['sub_save_courses']        = 'حفظ الكورسات في الاشتراك';
$string['sub_courses_search']      = 'ابحث في الكورسات…';
$string['sub_selectall']           = 'تحديد الكل';
$string['sub_clear']               = 'مسح التحديد';
$string['sub_ca_pickplan']        = 'اختر خطة اشتراك أعلاه لتحديد الكورسات التي تتيحها.';
$string['sub_ca_counter']         = 'تم تحديد {$a->selected} من {$a->total} كورس';
$string['sub_ca_unsaved']         = 'تغييرات غير محفوظة';
$string['sub_ca_reset']           = 'استعادة';
$string['sub_ca_onlyselected']    = 'المحدَّد فقط';
$string['sub_ca_nomatch']         = 'لا توجد كورسات مطابقة لهذا الفلتر.';
$string['sub_ca_catall']          = 'الكل';
$string['sub_ca_catnone']         = 'لا شيء';
$string['sub_ca_discard']         = 'لديك تغييرات غير محفوظة على كورسات هذه الخطة. هل تريد التبديل وفقدانها؟';
$string['sub_ca_catcount']        = '{$a->selected}/{$a->total}';

// User subscriptions.
$string['sub_usersubs_heading']    = 'اشتراكات المستخدمين';
$string['sub_usersubs_desc']       = 'إدارة اشتراكات المستخدمين النشطة والمنتهية.';
$string['sub_unsub_title']         = 'إلغاء اشتراك المستخدم';
$string['sub_unsub_refund']        = 'رد المبلغ إلى الطالب';
$string['sub_unsubscribe']         = 'إلغاء الاشتراك';
$string['sub_none_admin']          = 'لا توجد اشتراكات بعد.';
$string['sub_inactive']            = 'موقوف';
$string['sub_edit_titled']         = 'تعديل الاشتراك رقم {$a}';
$string['sub_updated']             = 'تم تحديث الاشتراك.';
$string['sub_created']             = 'تم إنشاء الاشتراك.';
$string['sub_activated']           = 'تم التفعيل.';
$string['sub_deactivated']         = 'تم الإيقاف.';
$string['sub_deleted']             = 'تم الحذف.';
$string['sub_confirm_delete']      = 'هل تريد حذف هذا الاشتراك؟ الحذف ممكن فقط إذا لم يُشترَ من قبل، ولا يمكن التراجع عنه.';
$string['sub_no_categories']       = 'لا توجد تصنيفات تحتوي على كورسات.';
$string['sub_select_target']       = 'برجاء اختيار الاشتراك المستهدف.';
$string['sub_courses_assigned']    = 'تم إسناد الكورسات بنجاح.';
$string['sub_no_usersubs']         = 'لا توجد اشتراكات مستخدمين.';
$string['sub_unsub_confirm']       = 'هل تريد إلغاء اشتراك <strong>{$a->user}</strong> في <strong>{$a->name}</strong>{$a->price}؟ لا يمكن التراجع عن هذا الإجراء.';
$string['sub_unsub_success']       = 'تم إلغاء اشتراك المستخدم بنجاح.';

// Subscription statuses.
$string['sstat_active']         = 'نشط';
$string['sstat_expired']        = 'منتهٍ';
$string['sstat_cancelled']      = 'ملغى';
$string['sstat_pending']        = 'قيد الانتظار';
$string['sstat_payment_failed'] = 'فشل الدفع';

// Errors.
$string['err_subnamerequired']  = 'اسم الاشتراك مطلوب';
$string['err_subnameempty']     = 'لا يمكن ترك اسم الاشتراك فارغًا';
$string['err_pricenegative']    = 'لا يمكن أن يكون السعر بالسالب';
$string['err_pricepositive']    = 'يجب أن يكون السعر أكبر من صفر';
$string['err_pricecountry']     = 'برجاء اختيار دولة صالحة';
$string['err_priceonepercountry'] = 'هذا الاشتراك له بالفعل سعر لتلك الدولة';
$string['err_currency']         = 'برجاء اختيار عملة صالحة';
$string['err_durationpositive'] = 'يجب أن يكون عدد الأيام أكبر من صفر';
$string['err_subnotfound']      = 'الاشتراك غير موجود';
$string['err_subhaspurchases']  = 'هذا الاشتراك له عمليات شراء مسجّلة ولا يمكن حذفه. أوقفه بدلًا من ذلك.';
$string['err_coursenotfound']   = 'الكورس غير موجود';
$string['err_seatspositive']    = 'يجب أن يكون عدد المقاعد أكبر من صفر';
$string['err_discountrange']    = 'يجب أن تكون نسبة الخصم بين 0 و100';
$string['err_status']           = 'يجب أن تكون الحالة "مفعّل" أو "موقوف"';
$string['err_postrequired']     = 'هذا الإجراء يتطلب طلب POST';
$string['err_permissiondenied'] = 'ليس لديك صلاحية لهذا الإجراء';
$string['err_unknownfunction']  = 'دالة غير معروفة';
$string['err_requestfailed']    = 'فشل تنفيذ الطلب';
$string['err_sessionexpired']   = 'انتهت الجلسة — برجاء إعادة تحميل الصفحة وتسجيل الدخول من جديد.';
$string['err_paymentsunavailable'] = 'بوابة الدفع غير متاحة على هذا الموقع.';
$string['err_alreadyhassubscription'] = 'لديك اشتراك نشط بالفعل.';
$string['err_checkoutfailed']   = '{$a}';

$string['enrolled']            = 'تم تسجيلك في هذا الكورس.';

// Scheduled task: end subscriptions past their deadline.
$string['task_expire_subscriptions'] = 'إنهاء الاشتراكات المنتهية وإلغاء تسجيل الطلاب';

// Country-gated pricing fallbacks (normally the wording comes from local_payments).
$string['countryrequired'] = 'حدّد دولتك لعرض السعر';
$string['countryrequired_desc'] = 'تُحدَّد الأسعار حسب الدولة. أضف دولتك إلى ملفك الشخصي لعرض سعر هذه الخطة والاشتراك فيها.';
$string['countryrequired_action'] = 'أضف دولتك';
$string['sub_refund_rule'] = 'سياسة الاسترداد';
$string['sub_field_refundhours'] = 'مهلة الاسترداد (بالساعات)';
$string['sub_field_refundfee'] = 'رسوم الاسترداد (%)';
$string['sub_refundfee_help'] = 'نسبة من المبلغ الذي دفعه المشتري فعليًا، فيكفي رقم واحد لكل العملات ويتبع أي خصم. اترك الحقلين فارغين لاستخدام سياسة الاسترداد العامة للموقع.';
