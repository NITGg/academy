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
 * Covers both the student-facing pages and the admin screens of this plugin.
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

// ── Plugin identity and admin navigation (Site administration + navbar gear menu) ──
$string['pluginname']       = 'المبيعات والعروض (NIT)';
$string['managecoupons']    = 'إدارة الكوبونات';
$string['manageoffers']     = 'إدارة العروض';
$string['privacy:metadata'] = 'يخزّن مكوّن المبيعات والعروض كوبونات الخصم والعروض التي ينشئها المشرفون فقط، ولا يخزّن أي بيانات شخصية بذاته.';

// Shared UI.
$string['ui_refresh']      = 'تحديث';
$string['ui_loading']      = 'جارٍ التحميل…';
$string['ui_save']         = 'حفظ';
$string['ui_cancel']       = 'إلغاء';
$string['ui_active']       = 'مفعّل';
$string['ui_activate']     = 'تفعيل';
$string['ui_deactivate']   = 'إيقاف';
$string['ui_edit']         = 'تعديل';
$string['ui_delete']       = 'حذف';
$string['ui_never']        = 'بلا تاريخ';
$string['ui_optional']     = '(اختياري)';
$string['ui_pager_info']   = 'عرض {from}–{to} من {total}';
$string['ui_showmore']     = '+{$a} أخرى';
$string['ui_showless']     = 'عرض أقل';
$string['pkg_col_status']  = 'الحالة';
$string['pkg_col_actions'] = 'إجراءات';
$string['pkg_field_name_en'] = 'الاسم (بالإنجليزية)';
$string['pkg_field_name_ar'] = 'الاسم (بالعربية)';
$string['pkg_field_desc_en'] = 'الوصف (بالإنجليزية)';
$string['pkg_field_desc_ar'] = 'الوصف (بالعربية)';
$string['sub_inactive']    = 'موقوف';

// Coupons (admin).
$string['cpn_new']         = 'إنشاء كوبون';
$string['cpn_none']        = 'لا توجد كوبونات بعد.';
$string['cpn_col_code']    = 'الكود';
$string['cpn_col_type']    = 'النوع';
$string['cpn_col_value']   = 'القيمة';
$string['cpn_col_scope']   = 'يُطبَّق على';
$string['cpn_col_dates']   = 'مدة الصلاحية';
$string['cpn_field_code']  = 'كود الكوبون';
$string['cpn_field_dtype'] = 'نوع الخصم';
$string['cpn_field_value'] = 'قيمة الخصم';
$string['cpn_field_max']   = 'الحد الأقصى لمبلغ الخصم';
$string['cpn_field_utype'] = 'نوع الاستخدام';
$string['cpn_field_limit'] = 'حد الاستخدام';
$string['cpn_field_start'] = 'تاريخ البداية';
$string['cpn_field_end']   = 'تاريخ الانتهاء';
$string['cpn_field_scope'] = 'العناصر المشمولة';
$string['cpn_scope_courses']       = 'الكورسات';
$string['cpn_scope_packages']      = 'الباقات';
$string['cpn_scope_subscriptions'] = 'الاشتراكات';
$string['cpn_scope_programs']      = 'البرامج';
$string['cpn_scope_all']      = 'الكل';
$string['cpn_scope_specific'] = 'المحدَّد';
$string['cpn_created']     = 'تم إنشاء الكوبون';
$string['cpn_updated']     = 'تم تحديث الكوبون';
$string['cpn_activated']   = 'تم تفعيل الكوبون';
$string['cpn_deactivated'] = 'تم إيقاف الكوبون';
$string['cpn_deleted']     = 'تم حذف الكوبون';
$string['cpn_confirm_delete'] = 'هل تريد حذف هذا الكوبون؟ لا يمكن التراجع عن هذا الإجراء.';
$string['cpn_edit_titled']    = 'تعديل الكوبون {$a}';
$string['cpn_scope_required'] = 'اختر عنصرًا واحدًا على الأقل ليُطبَّق عليه الكوبون.';
$string['cpn_used_count']     = 'استُخدم {$a}';

// Offers (admin).
$string['ofr_new']        = 'إنشاء عرض';
$string['ofr_none']       = 'لا توجد عروض بعد.';
$string['ofr_col_name']   = 'الاسم';
$string['ofr_field_name'] = 'اسم العرض';
$string['ofr_created']     = 'تم إنشاء العرض';
$string['ofr_updated']     = 'تم تحديث العرض';
$string['ofr_activated']   = 'تم تفعيل العرض';
$string['ofr_deactivated'] = 'تم إيقاف العرض';
$string['ofr_deleted']     = 'تم حذف العرض';
$string['ofr_confirm_delete'] = 'هل تريد حذف هذا العرض؟ لا يمكن التراجع عن هذا الإجراء.';
$string['ofr_edit_titled']    = 'تعديل العرض {$a}';
$string['ofr_delete_title']   = 'حذف العرض';

// Scheduled task.
$string['cleanupreservations'] = 'تحرير حجوزات الكوبونات المهجورة';

// Errors.
$string['err_itemtype']            = 'نوع العنصر غير صالح.';
$string['err_itemnotfound']        = 'العنصر المطلوب غير موجود.';
$string['err_discounttype']        = 'يجب أن يكون نوع الخصم نسبة مئوية أو مبلغًا ثابتًا.';
$string['err_discountvalue']       = 'لا يمكن أن تكون قيمة الخصم بالسالب.';
$string['err_discountpercent']     = 'يجب أن تكون نسبة الخصم بين 0 و100.';
$string['err_maxdiscount']         = 'لا يمكن أن يكون الحد الأقصى للخصم بالسالب.';
$string['err_daterange']           = 'يجب أن يكون تاريخ الانتهاء بعد تاريخ البداية.';
$string['err_usagetype']           = 'يجب أن يكون نوع الاستخدام لمرة واحدة أو متعددًا.';
$string['err_status']              = 'يجب أن تكون الحالة "مفعّل" أو "موقوف"';
$string['err_couponcoderequired']  = 'كود الكوبون مطلوب.';
$string['err_couponcodetaken']     = 'كود الكوبون هذا مستخدم بالفعل.';
$string['err_couponnotfound']      = 'الكوبون غير موجود.';
$string['err_couponinactive']      = 'هذا الكوبون غير مفعّل.';
$string['err_couponnotstarted']    = 'هذا الكوبون لم يبدأ سريانه بعد.';
$string['err_couponexpired']       = 'انتهت صلاحية هذا الكوبون.';
$string['err_couponnotapplicable'] = 'هذا الكوبون لا ينطبق على هذا العنصر.';
$string['err_couponusedup']        = 'وصل هذا الكوبون إلى الحد الأقصى لعدد مرات الاستخدام.';
$string['err_couponalreadyusedbyuser'] = 'لقد استخدمت هذا الكوبون من قبل.';
$string['err_couponbusy']          = 'يجري استخدام هذا الكوبون في طلب آخر. برجاء المحاولة بعد لحظات.';
$string['err_couponhasusages']     = 'تم استخدام هذا الكوبون، ولذلك يمكن إيقافه فقط دون حذفه.';
$string['err_offernamerequired']   = 'اسم العرض مطلوب.';
$string['err_offernotfound']       = 'العرض غير موجود.';
$string['err_offerhasusages']      = 'تم استخدام هذا العرض، ولذلك يمكن إيقافه فقط دون حذفه.';
$string['err_postrequired']        = 'هذا الإجراء يتطلب طلب POST';
$string['err_permissiondenied']    = 'ليس لديك صلاحية لهذا الإجراء';
$string['err_unknownfunction']     = 'دالة غير معروفة';
$string['err_requestfailed']       = 'فشل تنفيذ الطلب';
$string['err_sessionexpired']      = 'انتهت الجلسة — برجاء إعادة تحميل الصفحة وتسجيل الدخول من جديد.';

// Shared checkout modal (course/subscription buy).
$string['co_title']         = 'تأكيد عملية الشراء';
$string['co_intro']         = 'سيتم تحويلك إلى صفحة الدفع الآمن لإتمام العملية.';
$string['co_total']         = 'الإجمالي';
$string['co_offer']         = 'العرض';
$string['co_coupon']        = 'كوبون';
$string['co_apply']         = 'تطبيق';
$string['co_discount']      = 'الخصم';
$string['co_method']        = 'طريقة الدفع';
$string['co_method_code']   = 'الدفع بكود';
$string['co_secure']        = 'دفع آمن';
$string['co_proceed']       = 'متابعة الدفع';
$string['co_cancel']        = 'إلغاء';
$string['co_loading']       = 'جارٍ التحميل…';
$string['co_coupon_failed'] = 'تعذّر تطبيق الكوبون.';
$string['co_currency']      = 'ج.م';
$string['co_buy']           = 'اشترِ الآن';
