<?php
defined('MOODLE_INTERNAL') || die();

// Arabic translation for local_academy.
// Pilot scope: the Packages flow (manage_packages.php + the student Packages & Flex tab) plus the
// API system/success messages. Any key not translated here falls back to en automatically, so the
// remaining pages are progressively translated during the localisation rollout.

$string['pluginname'] = 'منصة أكاديمي فلكس';
$string['managepackages'] = 'إدارة باقات الدروس';
$string['studenthub'] = 'حجز الدروس والفلكس';
$string['mypackages'] = 'باقاتي';
$string['availpkgs_heading'] = 'الباقات المتاحة';
$string['subscriptionhub'] = 'الاشتراكات';
$string['mysubscriptions'] = 'اشتراكاتي';
$string['availsubs_heading'] = 'الاشتراكات المتاحة';

// ── Error messages surfaced in API JSON ──
$string['err_namerequired']  = 'اسم الباقة مطلوب';
$string['err_nameempty']     = 'لا يمكن ترك اسم الباقة فارغًا';
$string['err_flexpositive']  = 'يجب أن يكون عدد الفلكس أكبر من صفر';
$string['err_pricenegative'] = 'لا يمكن أن يكون السعر بالسالب';
$string['err_expnegative']   = 'لا يمكن أن تكون أيام الصلاحية بالسالب';
$string['err_status']        = 'يجب أن تكون الحالة "active" أو "inactive"';
$string['err_notfound']      = 'الباقة غير موجودة';
$string['err_haspurchases']  = 'هذه الباقة لديها سجلات شراء ولا يمكن حذفها. قم بتعطيلها بدلاً من ذلك.';
$string['err_packagenotavailable'] = 'هذه الباقة غير متاحة للشراء';
$string['err_alreadyhaspackage']   = 'لديك بالفعل باقة نشطة';
$string['err_studentnotfound']     = 'الطالب غير موجود';
$string['err_studenthaspackage']   = 'هذا الطالب لديه بالفعل باقة نشطة';

// ── API-level system messages ──
$string['err_postrequired']      = 'يتطلب هذا الإجراء طلب POST';
$string['err_authrequired']      = 'التوثيق مطلوب';
$string['err_invalidtoken']      = 'رمز غير صالح';
$string['err_permissiondenied']  = 'تم رفض الإذن';
$string['err_unknownfunction']   = 'دالة غير معروفة';
$string['err_requestfailed']     = 'فشل الطلب';
$string['err_sessionexpired']    = 'انتهت الجلسة — يرجى إعادة تحميل الصفحة وتسجيل الدخول مرة أخرى.';

// ── API success messages ──
$string['msg_package_created']     = 'تم إنشاء الباقة.';
$string['msg_package_updated']     = 'تم تحديث الباقة.';
$string['msg_package_activated']   = 'تم تفعيل الباقة.';
$string['msg_package_deactivated'] = 'تم تعطيل الباقة.';
$string['msg_package_deleted']     = 'تم حذف الباقة.';
$string['msg_package_unassigned']  = 'تم إلغاء تعيين الباقة بنجاح.';
$string['msg_package_purchased']   = 'تم شراء الباقة.';

// ── Generic / shared UI ──
$string['ui_refresh']      = 'تحديث';
$string['ui_loading']      = 'جارٍ التحميل…';
$string['ui_save']         = 'حفظ';
$string['ui_cancel']       = 'إلغاء';
$string['ui_edit']         = 'تعديل';
$string['ui_delete']       = 'حذف';
$string['ui_activate']     = 'تفعيل';
$string['ui_deactivate']   = 'تعطيل';
$string['ui_active']       = 'نشط';
$string['ui_never']        = 'أبدًا';
$string['ui_optional']     = '(اختياري)';
$string['ui_currency_egp'] = 'ج.م';

// ── manage_packages.php — package CRUD ──
$string['pkg_new']              = 'باقة جديدة';
$string['pkg_edit_titled']      = 'تعديل الباقة رقم {$a}';
$string['pkg_field_name']       = 'الاسم';
$string['pkg_field_description'] = 'الوصف';
$string['pkg_field_name_en']    = 'الاسم (بالإنجليزية)';
$string['pkg_field_name_ar']    = 'الاسم (بالعربية)';
$string['pkg_field_desc_en']    = 'الوصف (بالإنجليزية)';
$string['pkg_field_desc_ar']    = 'الوصف (بالعربية)';
$string['pkg_field_flexcount']  = 'عدد الفلكس';
$string['pkg_field_price']      = 'السعر (ج.م)';
$string['pkg_field_expdays']    = 'أيام الصلاحية (0 = غير محدود)';
$string['pkg_col_id']           = 'المعرف';
$string['pkg_col_name']         = 'الاسم';
$string['pkg_col_flexes']       = 'الفلكس';
$string['pkg_col_price']        = 'السعر';
$string['pkg_col_expdays']      = 'الصلاحية (أيام)';
$string['pkg_col_status']       = 'الحالة';
$string['pkg_col_actions']      = 'الإجراءات';
$string['pkg_none']             = 'لا توجد باقات بعد.';
$string['pkg_confirm_delete']   = 'حذف هذه الباقة؟ لا يمكن التراجع عن ذلك.';

// ── manage_packages.php — user packages + unassign ──
$string['pkg_userpackages']       = 'باقات المستخدمين';
$string['pkg_userpackages_desc']  = 'إدارة باقات المستخدمين النشطة والمنتهية.';
$string['pkg_col_user']           = 'المستخدم';
$string['pkg_col_package']        = 'الباقة';
$string['pkg_col_flex']           = 'الفلكس';
$string['pkg_col_pricepaid']      = 'المبلغ المدفوع';
$string['pkg_col_expiresat']      = 'تنتهي في';
$string['pkg_users_none']         = 'لا توجد باقات مستخدمين.';
$string['pkg_unassign']           = 'إلغاء التعيين';
$string['pkg_unassign_title']     = 'إلغاء تعيين الباقة';
$string['pkg_unassign_refund']    = 'استرداد المبلغ للطالب';
$string['pkg_unassign_confirm']   = 'إلغاء تعيين <strong>{$a->name}</strong> من <strong>{$a->user}</strong>{$a->price}؟ لا يمكن التراجع عن ذلك.';
$string['pkg_unassign_paid']      = ' — <strong>{$a}</strong> مدفوع';

// ── student.php — tab bar ──
$string['tab_book']         = 'حجز درس';
$string['tab_lessons']      = 'دروسي';
$string['tab_packages']     = 'الباقات والفلكس';
$string['tab_subavailable'] = 'الاشتراكات المتاحة';
$string['tab_mysubs']       = 'اشتراكاتي';

// ── student.php — flex banner ──
$string['st_available_flex']   = 'رصيد الفلكس المتاح';
$string['st_book_up_to']       = 'يمكنك حجز حتى <b>{$a->count}</b> درس.';
$string['st_no_active_pkg']    = 'لا توجد باقة نشطة — اشترِ واحدة من تبويب <b>الباقات</b> لبدء الحجز.';

// ── student.php — Packages & Flex tab ──
$string['st_payment_history']    = 'سجل المدفوعات';
$string['st_flex_history']       = 'سجل الفلكس';
$string['st_pkg_none_available'] = 'لا توجد باقات متاحة حاليًا.';
$string['st_pkg_none']           = 'لا توجد باقات بعد.';
$string['st_pay_none']           = 'لا توجد مدفوعات بعد.';
$string['st_flex_none']          = 'لا يوجد نشاط فلكس بعد.';
$string['st_already_active_pkg'] = 'لديك بالفعل باقة نشطة.';
$string['st_buy_package']        = 'شراء الباقة';
$string['st_buy_title']          = 'شراء "{$a}"';
$string['st_buy_text']           = 'ستحصل على {$a->flex} فلكس مقابل {$a->price} عبر بوابة Kashier الآمنة.';
$string['st_proceed_payment']    = 'المتابعة للدفع';
$string['st_pkgmeta_flex']       = '{$a} فلكس';
$string['st_pkgmeta_validdays']  = ' · صالحة لمدة {$a} يوم';
$string['st_pkgmeta_neverexp']   = ' · لا تنتهي';
$string['st_flex_left']          = '<span class="st-flex-pill">{$a->remaining}</span> متبقٍ ({$a->used} / {$a->total})';
$string['st_col_package']     = 'الباقة';
$string['st_col_flexusedtot'] = 'الفلكس (المستخدم / الإجمالي)';
$string['st_col_status']      = 'الحالة';
$string['st_col_activated']   = 'تاريخ التفعيل';
$string['st_col_expires']     = 'تنتهي';
$string['st_col_date']        = 'التاريخ';
$string['st_col_amount']      = 'المبلغ';
$string['st_col_method']      = 'الطريقة';
$string['st_col_transaction'] = 'المعاملة';
$string['st_col_type']        = 'النوع';
$string['st_col_change']      = 'التغيير';
$string['st_col_balance']     = 'الرصيد';
$string['st_col_lesson']      = 'الدرس';
$string['st_col_note']        = 'ملاحظة';
$string['pstat_active']     = 'نشطة';
$string['pstat_fully_used'] = 'مستهلكة بالكامل';
$string['pstat_expired']    = 'منتهية';
$string['pstat_cancelled']  = 'ملغاة';
$string['pstat_pending']    = 'قيد الانتظار';
$string['pay_completed']    = 'مكتملة';
$string['pay_pending']      = 'قيد الانتظار';
$string['pay_failed']       = 'فاشلة';
$string['pay_refunded']     = 'مستردة';
$string['flx_reserve']      = 'محجوز';
$string['flx_consume']      = 'مستهلك';
$string['flx_return']       = 'مُعاد';
$string['flx_purchase']     = 'مشترى';
$string['flx_assign']       = 'مُعيَّن';
$string['flx_expire']       = 'منتهٍ';
$string['flx_adjust']       = 'مُعدَّل';

// ── student.php — Book / My lessons / Subscriptions tabs ──

// Shared UI extras.
$string['ui_confirm']  = 'تأكيد';
$string['ui_search']   = 'بحث';

// Book a lesson tab.
$string['st_search_placeholder'] = 'ابحث حسب المادة…';
$string['st_teacher_num']        = 'معلّم رقم {$a}';
$string['st_request_lesson']     = 'طلب درس';
$string['st_no_subjects']        = 'لم يُدرج هذا المعلّم أي مواد بعد.';
$string['st_request_with']       = 'طلب درس مع {$a}';
$string['st_send_request']       = 'إرسال الطلب';
$string['st_field_subject']      = 'المادة';
$string['st_field_datetime']     = 'التاريخ والوقت المفضّل';
$string['st_field_note_req']     = 'ملاحظة للمعلّم (مطلوبة)';
$string['st_note_placeholder']   = 'بماذا تحتاج المساعدة؟';
$string['st_pick_valid_time']    = 'يرجى اختيار تاريخ ووقت صحيحين.';
$string['st_note_required']      = 'الملاحظة مطلوبة لطلب الدرس.';
$string['st_lesson_requested']   = 'تم إرسال طلب الدرس. تابعه في "دروسي".';
$string['st_no_teachers']        = 'لا يوجد معلّمون.';
$string['st_slot_pickday']       = 'اختر يومًا';
$string['st_slot_picktime']      = 'اختر وقتًا';
$string['st_slot_noavail']       = 'لا تتوفر أوقات لهذا المعلّم في الأيام القادمة.';
$string['st_slot_nodayslots']    = 'لا توجد أوقات متاحة في هذا اليوم.';

// My lessons tab — filter dropdown.
$string['st_status']            = 'الحالة';
$string['lf_all']               = 'الكل';
$string['lf_pending']           = 'قيد الانتظار';
$string['lf_waiting_student']   = 'بانتظار ردّي';
$string['lf_waiting_teacher']   = 'بانتظار المعلّم';
$string['lf_confirmed']         = 'مؤكّد';
$string['lf_in_progress']       = 'جارٍ';
$string['lf_completed']         = 'مكتمل';
$string['lf_student_absent']    = 'كنت غائبًا';
$string['lf_teacher_absent']    = 'المعلّم غائب';
$string['lf_cancelled']         = 'ملغى';
$string['lf_cancelled_teacher'] = 'ملغى (المعلّم)';
$string['lf_rejected']          = 'مرفوض';

// My lessons tab — status badge labels.
$string['lstat_pending']           = 'بانتظار ردّ المعلّم';
$string['lstat_waiting_student']   = 'بانتظار ردّك';
$string['lstat_waiting_teacher']   = 'بانتظار المعلّم';
$string['lstat_confirmed']         = 'مؤكّد';
$string['lstat_in_progress']       = 'جارٍ';
$string['lstat_completed']         = 'مكتمل';
$string['lstat_student_absent']    = 'كنت غائبًا';
$string['lstat_teacher_absent']    = 'المعلّم غائب';
$string['lstat_cancelled']         = 'ملغى';
$string['lstat_cancelled_teacher'] = 'ألغاه المعلّم';
$string['lstat_rejected']          = 'مرفوض';

// My lessons tab — action button labels.
$string['lact_accept']             = 'قبول';
$string['lact_reject']             = 'رفض';
$string['lact_suggest']            = 'اقتراح وقت';
$string['lact_cancel_request']     = 'سحب الطلب';
$string['lact_cancel']             = 'إلغاء الدرس';
$string['lact_report_teacher_absent'] = 'الإبلاغ عن غياب المعلّم';
$string['lact_request_time_update'] = 'إعادة جدولة';
$string['lact_join']               = 'الانضمام للدرس';
$string['lact_accept_newtime']     = 'قبول الوقت الجديد';
$string['lact_reject_newtime']     = 'رفض الوقت الجديد';

// My lessons tab — action dialogs.
$string['la_done']              = 'تم.';
$string['la_reason_optional']   = 'السبب (اختياري)';
$string['la_pick_valid_time']   = 'اختر وقتًا صحيحًا.';
$string['la_reject_title']      = 'رفض الوقت المقترح';
$string['la_suggest_title']     = 'اقتراح وقت آخر';
$string['la_suggested_time']    = 'التاريخ والوقت المقترح';
$string['la_withdraw_title']    = 'سحب الطلب';
$string['la_withdraw_text']     = 'سحب طلب الدرس هذا؟ لم يُحجز أي فلكس بعد.';
$string['la_cancel_title']      = 'إلغاء الدرس';
$string['la_cancel_text']       = 'الإلغاء قبل الموعد النهائي يعيد الفلكس؛ والإلغاء المتأخر يستهلكه.';
$string['la_report_absent_title'] = 'الإبلاغ عن غياب المعلّم';
$string['la_report_absent_text']  = 'تأكيد أن المعلّم لم يحضر؟ سيُعاد الفلكس الخاص بك.';
$string['la_newtime_title']     = 'طلب وقت جديد';
$string['la_newtime_label']     = 'التاريخ والوقت الجديد';
$string['la_room_not_ready']    = 'غرفة الاجتماع غير جاهزة بعد.';

// My lessons tab — lesson card.
$string['lc_teacher_num']    = 'معلّم رقم {$a}';
$string['lc_title']          = '{$a->subject} · مع {$a->teacher}';
$string['lc_confirmed']      = 'مؤكّد: {$a}';
$string['lc_requested']      = 'مطلوب: {$a}';
$string['lc_duration']       = '{$a} دقيقة';
$string['lc_your_note']      = 'ملاحظتك: {$a}';
$string['lc_reject_reason']  = 'سبب الرفض: {$a}';
$string['lc_cancel_reason']  = 'سبب الإلغاء: {$a}';
$string['lc_flex']           = 'فلكس: {$a}';
$string['lc_you']            = 'أنت';
$string['lc_the_teacher']    = 'المعلّم';
$string['lc_resched_moved']  = 'طلب {$a->who} نقل هذا الدرس إلى <b>{$a->time}</b>.';
$string['st_no_lessons']     = 'لا توجد دروس بعد — احجز درسًا من تبويب "حجز درس".';

// Subscriptions tabs — headings + table headers.
$string['sub_available_heading'] = 'الاشتراكات المتاحة';
$string['sub_my_heading']        = 'اشتراكاتي';
$string['sub_payments_heading']  = 'مدفوعات الاشتراكات';
$string['sub_col_subscription']  = 'الاشتراك';
$string['sub_col_daysleft']      = 'الأيام المتبقية';
$string['sub_col_courses']       = 'المقررات';

// Subscriptions tabs — status badges.
$string['sstat_active']         = 'نشط';
$string['sstat_expired']        = 'منتهٍ';
$string['sstat_cancelled']      = 'ملغى';
$string['sstat_pending']        = 'قيد الانتظار';
$string['sstat_payment_failed'] = 'فشل الدفع';

// Subscriptions tabs — cards + dialogs.
$string['sub_days']           = '{$a} يوم';
$string['sub_courses_label']  = 'المقررات:';
$string['sub_already_active'] = 'لديك بالفعل اشتراك نشط.';
$string['sub_buy']            = 'شراء الاشتراك';
$string['sub_buy_title']      = 'شراء "{$a}"';
$string['sub_buy_text']       = 'ستحصل على {$a->days} يومًا من الوصول للمقررات مقابل {$a->price} عبر بوابة Kashier الآمنة.';
$string['sub_none_available'] = 'لا توجد اشتراكات متاحة حاليًا.';
$string['sub_none_mine']      = 'لا توجد اشتراكات بعد.';
$string['sub_no_payments']    = 'لا توجد مدفوعات بعد.';
$string['err_timeconflict']        = '??? ?????? ??? ????? ?? ??? ?????.';
