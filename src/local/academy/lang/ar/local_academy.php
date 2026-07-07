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

// API success messages — subscriptions.
$string['msg_subscription_created']     = 'تم إنشاء الاشتراك.';
$string['msg_subscription_updated']     = 'تم تحديث الاشتراك.';
$string['msg_subscription_activated']   = 'تم تفعيل الاشتراك.';
$string['msg_subscription_deactivated'] = 'تم تعطيل الاشتراك.';
$string['msg_subscription_deleted']     = 'تم حذف الاشتراك.';
$string['msg_subscription_courses_set'] = 'تم تعيين المقررات بنجاح.';
$string['msg_user_unsubscribed']        = 'تم إلغاء اشتراك المستخدم بنجاح.';

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

// ── manage_settings.php (admin lesson settings) ──
$string['set_min_booking']          = 'الحد الأدنى لوقت الحجز (دقائق)';
$string['set_cancel_deadline']      = 'الموعد النهائي لإلغاء الطالب (دقائق)';
$string['set_update_deadline']      = 'الموعد النهائي لتعديل وقت الدرس (دقائق)';
$string['set_start_allowed']        = 'الوقت المسموح لبدء الدرس (دقائق)';
$string['set_complete_allowed']     = 'أقل عدد دقائق بعد البدء قبل الإكمال';
$string['set_absence_report']       = 'وقت الإبلاغ عن الغياب (دقائق)';
$string['set_expiry_reminder']      = 'تذكير انتهاء الباقة (أيام قبل الانتهاء)';
$string['set_expiry_reminder_help'] = 'إشعار الطالب قبل انتهاء باقته بهذا العدد من الأيام. القيمة 0 تعطّل التذكير.';
$string['set_teacher_percent']      = 'نسبة أرباح المعلّم %';
$string['set_platform_percent']     = 'نسبة أرباح المنصة %';
$string['set_percent_help']         = 'يجب أن يكون مجموع نسبة المعلّم + نسبة المنصة 100.';
$string['set_save']                 = 'حفظ التغييرات';
$string['set_saved']                = 'تم الحفظ.';

// ── assign_package.php (admin: assign package to student) ──
$string['ap_student_label']       = 'معرّف المستخدم (الطالب)';
$string['ap_student_help']        = 'معرّف مستخدم Moodle الرقمي للطالب.';
$string['ap_student_placeholder'] = 'مثال: 4770';
$string['ap_package_label']       = 'الباقة';
$string['ap_amount_label']        = 'المبلغ المدفوع (خارج المنصة)';
$string['ap_amount_placeholder']  = 'يُحدَّد افتراضيًا بسعر الباقة';
$string['ap_method_label']        = 'طريقة الدفع';
$string['ap_method_offline']      = 'نقدًا / خارج المنصة';
$string['ap_method_bank']         = 'تحويل بنكي';
$string['ap_method_wallet']       = 'محفظة إلكترونية';
$string['ap_reference_label']     = 'مرجع الدفع';
$string['ap_reference_placeholder'] = 'رقم الإيصال / التحويل';
$string['ap_note_label']          = 'ملاحظة (اختياري)';
$string['ap_submit']              = 'تعيين الباقة';
$string['ap_pkg_option']          = '{$a->name} — {$a->flex} فلكس / {$a->price}';
$string['ap_no_packages']         = 'لا توجد باقات نشطة. أنشئ واحدة أولًا.';
$string['ap_enter_student']       = 'أدخل معرّف مستخدم الطالب.';
$string['ap_assigned']            = 'تم تعيين "{$a->name}" ({$a->flex} فلكس) إلى {$a->student}.';

// ── teacher_profile.php (teacher's own profile editor) ──
$string['tp_headline']      = 'العنوان الوظيفي';
$string['tp_headline_ph']   = 'مثال: معلّم رياضيات أول';
$string['tp_bio']           = 'نبذة عني';
$string['tp_experience']    = 'سنوات الخبرة';
$string['tp_available']     = 'متاح للدروس';
$string['tp_subjects']      = 'المواد';
$string['tp_add_subject']   = '+ إضافة مادة';
$string['tp_working_hours'] = 'ساعات العمل';
$string['tp_add_slot']      = '+ إضافة فترة زمنية';
$string['tp_subject_ph']    = 'المادة (مثال: رياضيات)';
$string['tp_to']            = 'إلى';
$string['tp_saved']         = 'تم حفظ الملف الشخصي.';
$string['tp_day_sun']       = 'الأحد';
$string['tp_day_mon']       = 'الاثنين';
$string['tp_day_tue']       = 'الثلاثاء';
$string['tp_day_wed']       = 'الأربعاء';
$string['tp_day_thu']       = 'الخميس';
$string['tp_day_fri']       = 'الجمعة';
$string['tp_day_sat']       = 'السبت';

// ── wallet.php (teacher earnings / withdrawals) ──
$string['ui_export_csv']         = 'تصدير CSV';
$string['ui_request']            = 'طلب';
$string['w_withdraw']            = 'سحب الأرباح';
$string['w_withdrawals_heading'] = 'عمليات السحب';
$string['w_earnings_heading']    = 'الأرباح';
$string['w_col_noteref']         = 'ملاحظة / مرجع';
$string['w_col_student']         = 'الطالب';
$string['w_col_lessondate']      = 'تاريخ الدرس';
$string['w_col_flexvalue']       = 'قيمة الفلكس';
$string['w_col_yourshare']       = 'نصيبك';
$string['w_amount']              = 'المبلغ';
$string['w_method']              = 'الطريقة';
$string['w_method_cash']         = 'نقدًا';
$string['w_account']             = 'الحساب / بيانات الدفع';
$string['w_account_ph']          = 'IBAN / هاتف / ملاحظة';
$string['w_available_balance']   = 'الرصيد المتاح';
$string['w_total_earned']        = 'إجمالي المكتسب';
$string['w_pending_withdrawals'] = 'عمليات سحب معلّقة';
$string['w_total_withdrawn']     = 'إجمالي المسحوب';
$string['w_no_withdrawals']      = 'لا توجد عمليات سحب بعد.';
$string['w_no_earnings']         = 'لا توجد أرباح بعد.';
$string['w_ref']                 = 'مرجع: {$a}';
$string['w_requested']           = 'تم طلب السحب.';
$string['w_share']               = '{$a->amount} ({$a->percent}%)';
$string['wstat_pending']         = 'قيد الانتظار';
$string['wstat_approved']        = 'موافق عليه';
$string['wstat_paid']            = 'مدفوع';
$string['wstat_rejected']        = 'مرفوض';
$string['wstat_active']          = 'نشط';
$string['wstat_reversed']        = 'معكوس';

// ── manage_withdrawals.php (admin withdrawals + Flex reversal) ──
$string['wd_col_teacher']        = 'المعلّم';
$string['wd_col_methodaccount']  = 'الطريقة / الحساب';
$string['wd_reversal_title']     = 'عكس فلكس درس مكتمل (US-FN-1-5)';
$string['wd_reversal_help']      = 'يعيد فلكس واحدًا مستهلكًا إلى الطالب ويعكس أرباح المعلّم/المنصة. السبب مطلوب.';
$string['wd_lesson_id']          = 'معرّف الدرس';
$string['wd_reason']             = 'السبب';
$string['wd_return_flex']        = 'إعادة الفلكس';
$string['wd_updated']            = 'تم التحديث.';
$string['wd_approve']            = 'موافقة';
$string['wd_reject']             = 'رفض';
$string['wd_markpaid']           = 'تحديد كمدفوع';
$string['wd_reject_title']       = 'رفض السحب';
$string['wd_reason_required_field'] = 'السبب (مطلوب)';
$string['wd_markpaid_title']     = 'تحديد كمدفوع';
$string['wd_payref_optional']    = 'مرجع الدفع (اختياري)';
$string['wd_reason_required']    = 'السبب مطلوب.';
$string['wd_card_current']       = 'الرصيد الحالي للمنصة';
$string['wd_card_undistributed'] = 'غير موزّع (فلكس غير مستخدم)';
$string['wd_card_teachers']      = 'أموال المعلّمين (غير مدفوعة)';
$string['wd_card_platform']      = 'أرباح المنصة';
$string['wd_none']               = 'لا توجد طلبات سحب.';
$string['wd_enter_lesson']       = 'أدخل معرّف الدرس.';
$string['wd_flex_returned']      = 'تمت إعادة الفلكس وعكس الأرباح.';

// ── manage_reports.php (admin reports, 4 tabs) ──
// Tabs.
$string['rp_tab_lessons']     = 'الدروس والحضور';
$string['rp_tab_platform']    = 'أرباح المنصة';
$string['rp_tab_packages']    = 'الباقات والفلكس';
$string['rp_tab_studentflex'] = 'فلكس الطالب';
// Filters.
$string['rp_f_status']        = 'الحالة';
$string['rp_f_teacherid']     = 'معرّف المعلّم';
$string['rp_f_studentid']     = 'معرّف الطالب';
$string['rp_f_from']          = 'من (unix)';
$string['rp_f_to']            = 'إلى (unix)';
$string['rp_f_earnstatus']    = 'حالة الربح';
$string['rp_f_source']        = 'المصدر';
$string['rp_f_studentid_req'] = 'معرّف الطالب (مطلوب)';
$string['rp_run']             = 'تشغيل';
// Column headers.
$string['rp_c_id']         = 'المعرّف';
$string['rp_c_student']    = 'الطالب';
$string['rp_c_teacher']    = 'المعلّم';
$string['rp_c_subject']    = 'المادة';
$string['rp_c_status']     = 'الحالة';
$string['rp_c_confirmed']  = 'مؤكّد';
$string['rp_c_flex']       = 'الفلكس';
$string['rp_c_lesson']     = 'الدرس';
$string['rp_c_date']       = 'التاريخ';
$string['rp_c_flexvalue']  = 'قيمة الفلكس';
$string['rp_c_platpct']    = 'نسبة المنصة %';
$string['rp_c_platform']   = 'المنصة';
$string['rp_c_package']    = 'الباقة';
$string['rp_c_source']     = 'المصدر';
$string['rp_c_price']      = 'السعر';
$string['rp_c_rem']        = 'متبقٍ';
$string['rp_c_resv']       = 'محجوز';
$string['rp_c_used']       = 'مستخدم';
$string['rp_c_type']       = 'النوع';
$string['rp_c_amount']     = 'المبلغ';
$string['rp_c_before']     = 'قبل';
$string['rp_c_after']      = 'بعد';
$string['rp_c_by']         = 'بواسطة';
$string['rp_c_reason']     = 'السبب';
$string['rp_c_timeline']   = 'المسار الزمني';
// Student-Flex summary chips.
$string['rp_s_available']  = 'متاح';
$string['rp_s_reserved']   = 'محجوز';
$string['rp_s_consumed']   = 'مستهلك';
$string['rp_s_package']    = 'الباقة';
$string['rp_s_expires']    = 'تنتهي';
// Timeline.
$string['rp_timeline_title'] = 'المسار الزمني للإجراءات';
$string['rp_close']          = 'إغلاق';
$string['rp_tl_num']         = '#';
$string['rp_tl_action']      = 'الإجراء';
$string['rp_tl_by']          = 'بواسطة';
$string['rp_tl_role']        = 'الدور';
$string['rp_tl_time']        = 'الوقت';
$string['rp_tl_title_full']  = 'المسار الزمني — الدرس رقم {$a->id} ({$a->subject}، {$a->student} ↔ {$a->teacher})';
$string['rp_tl_joinedroom']  = 'انضم المعلّم للغرفة';
$string['rp_tl_started']     = 'بدأ الدرس';
$string['rp_tl_ended']       = 'انتهى الدرس';
$string['rp_tl_none']        = 'لا توجد إجراءات مسجّلة.';
// Messages.
$string['rp_no_data']            = 'لا توجد بيانات.';
$string['rp_enter_student']      = 'أدخل معرّف الطالب.';
$string['rp_enter_student_run']  = 'أدخل معرّف الطالب ثم اضغط تشغيل.';
// Summary chip labels (keyed off the report summary field names).
$string['rp_sum_total']                   = 'الإجمالي';
$string['rp_sum_completed']               = 'مكتملة';
$string['rp_sum_student_absent']          = 'غياب الطالب';
$string['rp_sum_teacher_absent']          = 'غياب المعلّم';
$string['rp_sum_attendance_rate']         = 'نسبة الحضور';
$string['rp_sum_total_platform_earnings'] = 'إجمالي أرباح المنصّة';
$string['rp_sum_total_teacher_earnings']  = 'إجمالي أرباح المعلّمين';
$string['rp_sum_total_consumed_value']    = 'إجمالي القيمة المستهلكة';
$string['rp_sum_completed_lessons']       = 'الدروس المكتملة';
$string['rp_sum_total_purchases']         = 'إجمالي المشتريات';
$string['rp_sum_total_sales_amount']      = 'إجمالي قيمة المبيعات';
$string['rp_sum_online_count']            = 'مشتريات عبر الإنترنت';
$string['rp_sum_assigned_count']          = 'المخصّصة';
$string['rp_sum_total_flex_added']        = 'إجمالي الفلكس المضاف';
$string['rp_sum_total_flex_consumed']     = 'إجمالي الفلكس المستهلك';
$string['rp_sum_total_flex_returned']     = 'إجمالي الفلكس المُرجَع';
$string['rp_sum_reversals']               = 'عمليات الاسترجاع';
// Audit-trail action labels.
$string['rp_act_requested']               = 'طلب الطالب';
$string['rp_act_teacher_accepted']        = 'قبل المعلّم';
$string['rp_act_teacher_rejected']        = 'رفض المعلّم';
$string['rp_act_teacher_suggested']       = 'اقترح المعلّم وقتًا';
$string['rp_act_student_accepted']        = 'قبل الطالب';
$string['rp_act_student_rejected']        = 'رفض الطالب';
$string['rp_act_student_suggested']       = 'اقترح الطالب وقتًا';
$string['rp_act_started']                 = 'بدأ المعلّم الدرس (تم إنشاء الغرفة)';
$string['rp_act_teacher_joined']          = 'انضم المعلّم للاجتماع';
$string['rp_act_student_joined']          = 'انضم الطالب للاجتماع';
$string['rp_act_completed']               = 'اكتمل الدرس';
$string['rp_act_student_absent_reported'] = 'تم الإبلاغ عن غياب الطالب';
$string['rp_act_teacher_absent_reported'] = 'تم الإبلاغ عن غياب المعلّم';
$string['rp_act_request_cancelled']       = 'تم سحب الطلب';
$string['rp_act_cancelled_by_student']    = 'ألغاه الطالب';
$string['rp_act_cancelled_by_teacher']    = 'ألغاه المعلّم';
$string['rp_act_time_update_requested']   = 'طُلب تعديل الوقت';
$string['rp_act_time_update_accepted']    = 'قُبل تعديل الوقت';
$string['rp_act_time_update_rejected']    = 'رُفض تعديل الوقت';

// ── my_lessons.php (teacher's lessons; reuses many lesson keys) ──
$string['mlf_waiting_student']        = 'بانتظار الطالب';
$string['mlf_student_absent']         = 'الطالب غائب';
$string['mlf_cancelled']              = 'ملغى (الطالب)';
$string['ml_act_start']               = 'بدء';
$string['ml_act_join']                = 'الانضمام للاجتماع';
$string['ml_act_complete']            = 'إكمال';
$string['ml_act_report_student_absent'] = 'الطالب غائب';
$string['ml_act_cancel']              = 'إلغاء';
$string['ml_act_respond']             = 'الرد على إعادة الجدولة';
$string['ml_report_absent_title']     = 'الإبلاغ عن غياب الطالب';
$string['ml_report_absent_text']      = 'تأكيد أن الطالب لم يحضر؟ سيُستهلك الفلكس.';
$string['ml_reject_title']            = 'رفض الطلب';
$string['ml_complete_title']          = 'إكمال الدرس';
$string['ml_note_optional']           = 'ملاحظة (اختياري)';
$string['ml_cancel_text']             = 'سيُعاد الفلكس المحجوز إلى الطالب.';
$string['ml_card_title']              = '{$a->subject} · مع {$a->student}';
$string['ml_student_num']             = 'طالب رقم {$a}';
$string['ml_note']                    = 'ملاحظة: {$a}';
$string['ml_the_student']             = 'الطالب';
$string['ml_no_lessons']              = 'لا توجد دروس لعرضها.';

// ── manage_subscriptions.php (admin subscription plans + user subs) ──
$string['sub_plans_heading']       = 'خطط الاشتراك';
$string['sub_new']                 = 'اشتراك جديد';
$string['sub_col_days']            = 'الأيام';
$string['sub_field_desc']          = 'الوصف (اختياري)';
$string['sub_field_days']          = 'عدد الأيام';
$string['sub_courseavail_heading'] = 'إتاحة المقررات للاشتراك';
$string['sub_courseavail_desc']    = 'اختر المقررات وأضِفها إلى اشتراك محدد.';
$string['sub_target']              = 'الاشتراك المستهدف:';
$string['sub_select_placeholder']  = 'اختر اشتراكًا...';
$string['sub_save_courses']        = 'حفظ المقررات في الاشتراك';
$string['sub_usersubs_heading']    = 'اشتراكات المستخدمين';
$string['sub_usersubs_desc']       = 'إدارة اشتراكات المستخدمين النشطة والمنتهية.';
$string['sub_unsub_title']         = 'إلغاء اشتراك المستخدم';
$string['sub_unsub_refund']        = 'استرداد المبلغ للطالب';
$string['sub_unsubscribe']         = 'إلغاء الاشتراك';
$string['sub_none_admin']          = 'لا توجد اشتراكات بعد.';
$string['sub_inactive']            = 'غير نشط';
$string['sub_edit_titled']         = 'تعديل الاشتراك رقم {$a}';
$string['sub_updated']             = 'تم تحديث الاشتراك.';
$string['sub_created']             = 'تم إنشاء الاشتراك.';
$string['sub_activated']           = 'تم التفعيل.';
$string['sub_deactivated']         = 'تم التعطيل.';
$string['sub_deleted']             = 'تم الحذف.';
$string['sub_confirm_delete']      = 'حذف هذا الاشتراك؟ ممكن فقط إن لم يُشترَ من قبل. لا يمكن التراجع عن ذلك.';
$string['sub_no_categories']       = 'لا توجد فئات تحتوي على مقررات.';
$string['sub_select_target']       = 'يرجى اختيار الاشتراك المستهدف.';
$string['sub_courses_assigned']    = 'تم تعيين المقررات بنجاح.';
$string['sub_no_usersubs']         = 'لا توجد اشتراكات مستخدمين.';
$string['sub_unsub_confirm']       = 'إلغاء اشتراك <strong>{$a->user}</strong> من <strong>{$a->name}</strong>{$a->price}؟ لا يمكن التراجع عن ذلك.';
$string['sub_unsub_success']       = 'تم إلغاء اشتراك المستخدم بنجاح.';
$string['err_timeconflict']        = '??? ?????? ??? ????? ?? ??? ?????.';

// ── Page titles / headings / capabilities ──
$string['academy:managepackages']      = 'إدارة باقات الدروس (فلكس)';
$string['academy:managesubscriptions'] = 'إدارة اشتراكات المقررات';
$string['managesubscriptions'] = 'إدارة الاشتراكات';
$string['managesettings']      = 'إعدادات الدروس';
$string['managewithdrawals']   = 'عمليات سحب المعلّمين';
$string['assignpackage']       = 'تعيين باقة لطالب';
$string['reports']             = 'تقارير منصة فلكس';
$string['mywallet']            = 'أرباحي';
$string['mylessons']           = 'دروسي';
$string['myteacherprofile']    = 'ملفي كمعلّم';
$string['teacherprofile']      = 'ملف المعلّم';
$string['editmyteacherprofile'] = 'تعديل ملفي كمعلّم';
$string['notateacher']         = 'هذه الصفحة متاحة للمعلّمين فقط.';
$string['studenthubdesc']      = 'احجز درسًا، وتابع دروسك، وأدر باقاتك والفلكس والاشتراكات — كل ذلك في مكان واحد.';
$string['availpkgs_desc']      = 'اشترِ باقة فلكس لحجز دروس فردية مع معلّمينا.';
$string['availsubs_desc']      = 'اشترك لفتح وصول كامل لمجموعة من المقررات لمدة محددة.';
// نصوص بطاقات الاشتراكات/الباقات في الصفحة الرئيسية (تُعرض عبر JS في lib.php). {n} عنصر نائب للرقم.
$string['hp_days']            = '{$a} يوم';
$string['hp_flex']            = '{$a} فلكس';
$string['hp_active']          = 'نشط';
$string['hp_subscribe']       = 'اشترك';
$string['hp_subscribed']      = 'مشترك';
$string['hp_login_to_subscribe'] = 'سجّل الدخول للاشتراك';
$string['hp_buy_package']     = 'شراء الباقة';
$string['hp_login_to_buy']    = 'سجّل الدخول للشراء';
$string['hp_purchased']       = 'تم الشراء';
$string['hp_redirecting']     = 'جارٍ التحويل…';
$string['hp_cancel']          = 'إلغاء';
$string['hp_proceed']         = 'المتابعة إلى الدفع';
$string['hp_total']           = 'الإجمالي';
$string['hp_secure']          = 'دفع آمن عبر Kashier';
$string['hp_egp']             = 'ج.م';
$string['hp_sess_expired']    = 'انتهت الجلسة — أعد تحميل الصفحة.';
$string['hp_req_failed']      = 'فشل الطلب';
$string['hp_sub_confirm_title'] = 'تأكيد اشتراكك';
$string['hp_sub_confirm_body']  = 'أنت على وشك الاشتراك في هذه الخطة. سيتم تحويلك إلى صفحة دفع آمنة لإتمام العملية.';
$string['hp_duration']        = 'المدة';
$string['hp_start_date']      = 'تاريخ البدء';
$string['hp_end_date']        = 'تاريخ الانتهاء';
$string['hp_never']           = 'أبدًا';
$string['hp_sub_active_note'] = 'لديك بالفعل اشتراك نشط. يمكنك الاشتراك في خطة أخرى بعد انتهاء اشتراكك الحالي.';
$string['hp_pkg_confirm_title'] = 'تأكيد شراء الباقة';
$string['hp_pkg_confirm_body']  = 'أنت على وشك شراء هذه الباقة. سيتم تحويلك إلى صفحة دفع آمنة لإتمام العملية.';
$string['hp_flex_count']      = 'عدد الفلكس';
$string['hp_flex_used_total'] = 'الفلكس (المستخدَم / الإجمالي)';
$string['hp_activated']       = 'تاريخ التفعيل';
$string['hp_expires']         = 'تاريخ الانتهاء';
$string['hp_never_expires']   = 'لا ينتهي أبدًا';
$string['hp_valid_for']       = 'صالحة لمدة {$a} يوم بعد التفعيل';
$string['hp_pkg_active_note'] = 'لديك بالفعل باقة نشطة. يمكنك شراء باقة جديدة بعد استهلاكها بالكامل أو انتهاء صلاحيتها.';

// ── Error messages surfaced in API JSON / UI alerts ──
$string['err_settingnegative']     = 'يجب أن تكون قيم الإعدادات صفرًا أو أكبر';
$string['err_percenttotal']        = 'يجب أن يكون مجموع نسبة المعلّم ونسبة المنصة 100';
$string['err_badhours']            = 'ساعات العمل غير صالحة (استخدم HH:MM وأن ينتهي الوقت بعد بدايته)';
$string['err_hoursoverlap']        = 'يجب ألا تتداخل ساعات العمل';
$string['err_teachernotfound']     = 'المعلّم غير موجود';
$string['err_subjectrequired']     = 'المادة مطلوبة';
$string['err_subjectunsupported']  = 'هذا المعلّم لا يقدّم المادة المختارة';
$string['err_selfbooking']         = 'لا يمكنك طلب درس مع نفسك';
$string['err_noflex']              = 'تحتاج إلى باقة نشطة بها فلكس متاح';
$string['err_minbooking']          = 'يجب حجز الدرس قبل الموعد بوقت أطول';
$string['err_notime']              = 'الوقت الصحيح مطلوب';
$string['err_forbidden']           = 'غير مسموح لك بتنفيذ هذا الإجراء';
$string['err_badstate']            = 'هذا الإجراء غير مسموح للحالة الحالية للدرس';
$string['err_badaction']           = 'إجراء غير معروف';
$string['err_lessonnotfound']      = 'الدرس غير موجود';
$string['err_tooearlytostart']     = 'لا يمكن بدء الدرس بعد';
$string['err_completetooearly']    = 'لا يمكن إكمال الدرس بعد (لم تُستوفَ المدة الدنيا)';
$string['err_noterequired']        = 'الملاحظة مطلوبة لطلب الدرس';
$string['err_reasonrequired']      = 'السبب مطلوب';
$string['err_absencetooearly']     = 'من المبكر جدًا الإبلاغ عن غياب';
$string['err_updatedeadline']      = 'انقضى الموعد النهائي لتعديل الوقت';
$string['err_updatepending']       = 'يوجد بالفعل طلب تعديل وقت قيد الانتظار';
$string['err_noupdaterequest']     = 'لا يوجد طلب تعديل وقت قيد الانتظار للرد عليه';
$string['err_nolessonscourse']     = 'لم يُضبط مقرر الدروس الخاص بغرف الاجتماعات';
$string['err_notdistributed']      = 'لا يوجد للدرس عملية شراء لتوزيع الأرباح منها';
$string['err_earningnotfound']     = 'لا يوجد ربح نشط لهذا الدرس';
$string['err_alreadyreversed']     = 'تم بالفعل عكس فلكس هذا الدرس';
$string['err_amountpositive']      = 'يجب أن يكون المبلغ أكبر من صفر';
$string['err_insufficientbalance'] = 'المبلغ يتجاوز رصيدك المتاح';
$string['err_withdrawalnotfound']  = 'طلب السحب غير موجود';
$string['err_withdrawalstate']     = 'هذا الإجراء غير مسموح للحالة الحالية للسحب';
$string['err_durationpositive']    = 'يجب أن يكون عدد الأيام أكبر من صفر';
$string['err_subnamerequired']     = 'اسم الاشتراك مطلوب';
$string['err_subnameempty']        = 'لا يمكن ترك اسم الاشتراك فارغًا';
$string['err_subnotfound']         = 'الاشتراك غير موجود';
$string['err_subhaspurchases']     = 'هذا الاشتراك لديه سجلات شراء ولا يمكن حذفه. قم بتعطيله بدلاً من ذلك.';
$string['err_subnotavailable']     = 'هذا الاشتراك غير متاح للشراء';
$string['err_alreadyhassubscription'] = 'لديك بالفعل اشتراك نشط';
$string['err_coursenotfound']      = 'المقرر غير موجود';

// ── Scheduled task names ──
$string['task_expiry_reminder']      = 'إرسال تذكيرات انتهاء الباقات للطلاب';
$string['task_subscription_expiry']  = 'إنهاء الاشتراكات وإرسال تذكيرات الانتهاء';

// ── Lesson-lifecycle notifications (in-app + email) ──
$string['messageprovider:lessonnotification'] = 'تحديثات الدروس (الطلبات والردود والتذكيرات)';
$string['notif_requested_subject'] = 'طلب درس جديد من {$a->student}';
$string['notif_requested_body']    = 'طلب {$a->student} درس {$a->subject} في {$a->time}. ملاحظة: {$a->note}. افتح "دروسي" للقبول أو الرفض أو اقتراح وقت آخر.';
$string['notif_confirmed_by_teacher_subject'] = 'تم تأكيد الدرس: {$a->subject}';
$string['notif_confirmed_by_teacher_body']    = 'أكّد {$a->teacher} درس {$a->subject} الخاص بك في {$a->time}.';
$string['notif_rejected_by_teacher_subject'] = 'تم رفض طلب الدرس: {$a->subject}';
$string['notif_rejected_by_teacher_body']    = 'رفض {$a->teacher} طلب درس {$a->subject} الخاص بك. السبب: {$a->reason}';
$string['notif_teacher_suggested_subject'] = 'تم اقتراح وقت جديد: {$a->subject}';
$string['notif_teacher_suggested_body']    = 'اقترح {$a->teacher} وقتًا جديدًا لدرس {$a->subject}: {$a->time}. افتح "دروسي" للقبول أو الرفض أو اقتراح وقت آخر.';
$string['notif_confirmed_by_student_subject'] = 'تم تأكيد الدرس: {$a->subject}';
$string['notif_confirmed_by_student_body']    = 'قبِل {$a->student} الوقت المقترح. تم تأكيد درس {$a->subject} في {$a->time}.';
$string['notif_rejected_by_student_subject'] = 'تم رفض الوقت المقترح: {$a->subject}';
$string['notif_rejected_by_student_body']    = 'رفض {$a->student} الوقت المقترح لدرس {$a->subject}. السبب: {$a->reason}';
$string['notif_student_suggested_subject'] = 'اقترح الطالب وقتًا جديدًا: {$a->subject}';
$string['notif_student_suggested_body']    = 'اقترح {$a->student} وقتًا جديدًا لدرس {$a->subject}: {$a->time}. افتح "دروسي" للقبول أو الرفض.';
$string['notif_started_subject'] = 'بدأ درسك: {$a->subject}';
$string['notif_started_body']    = 'بدأ {$a->teacher} درس {$a->subject}. افتح الدرس واضغط "الانضمام للدرس" للدخول إلى غرفة الاجتماع.';
$string['notif_completed_subject'] = 'اكتمل الدرس: {$a->subject}';
$string['notif_completed_body']    = 'اكتمل درس {$a->subject} مع {$a->teacher}. {$a->reason}';
$string['notif_student_absent_subject'] = 'تم تسجيلك كغائب: {$a->subject}';
$string['notif_student_absent_body']    = 'أبلغ {$a->teacher} بأنك لم تحضر درس {$a->subject} المقرر في {$a->time}.';
$string['notif_teacher_absent_subject'] = 'تم الإبلاغ عن غياب: {$a->subject}';
$string['notif_teacher_absent_body']    = 'أبلغ {$a->student} بأنك لم تحضر درس {$a->subject} المقرر في {$a->time}. تمت إعادة فلكس الطالب.';
$string['notif_teacher_absent_admin_subject'] = 'تم الإبلاغ عن غياب معلّم: {$a->subject}';
$string['notif_teacher_absent_admin_body']    = 'أبلغ {$a->student} عن غياب المعلّم {$a->teacher} في درس {$a->subject} المقرر في {$a->time}.';
$string['notif_request_cancelled_subject'] = 'تم سحب طلب الدرس: {$a->subject}';
$string['notif_request_cancelled_body']    = 'سحب {$a->student} طلب درس {$a->subject} المقرر في {$a->time}. السبب: {$a->reason}';
$string['notif_cancelled_by_student_subject'] = 'تم إلغاء الدرس: {$a->subject}';
$string['notif_cancelled_by_student_body']    = 'ألغى {$a->student} درس {$a->subject} المقرر في {$a->time}. السبب: {$a->reason}';
$string['notif_cancelled_by_teacher_subject'] = 'ألغى المعلّم الدرس: {$a->subject}';
$string['notif_cancelled_by_teacher_body']    = 'ألغى {$a->teacher} درس {$a->subject} المقرر في {$a->time}. تمت إعادة الفلكس الخاص بك. السبب: {$a->reason}';
$string['notif_time_update_requested_subject'] = 'تم طلب وقت جديد: {$a->subject}';
$string['notif_time_update_requested_body']    = 'طلب {$a->actor} نقل درس {$a->subject} إلى {$a->time}. افتح الدرس للقبول أو الرفض.';
$string['notif_time_update_accepted_subject'] = 'تم قبول الوقت الجديد: {$a->subject}';
$string['notif_time_update_accepted_body']    = 'قبِل {$a->actor} الوقت الجديد. أصبح درس {$a->subject} مقررًا في {$a->time}.';
$string['notif_time_update_rejected_subject'] = 'تم رفض الوقت الجديد: {$a->subject}';
$string['notif_time_update_rejected_body']    = 'رفض {$a->actor} الوقت الجديد. يبقى درس {$a->subject} في {$a->time}.';
$string['notif_package_expiring_subject'] = 'تنتهي باقتك خلال {$a->days} يوم';
$string['notif_package_expiring_body']    = 'تنتهي باقة "{$a->package}" في {$a->date} (باقٍ {$a->days} يوم). لا يزال لديك {$a->flex} فلكس — احجز درسًا قبل انتهائها حتى لا تفقدها.';
$string['notif_subscription_expiring_subject'] = 'ينتهي اشتراكك خلال {$a->days} يوم';
$string['notif_subscription_expiring_body']    = 'ينتهي اشتراك "{$a->subscription}" في {$a->date} (باقٍ {$a->days} يوم). جدّده للحفاظ على وصولك إلى مقرراتك.';
// US: تذكير ببدء الدرس.
$string['set_lesson_start_reminder'] = 'تذكير ببدء الدرس (بالدقائق)';
$string['set_lesson_start_reminder_help'] = 'أشعر الطالب قبل هذا العدد من الدقائق من بدء درسه (0 للتعطيل).';
$string['notif_lesson_reminder_subject'] = 'درسك سيبدأ قريبًا!';
$string['notif_lesson_reminder_body'] = 'مرحبًا {$a->studentname}،

سيبدأ درسك "{$a->subject}" مع {$a->teachername} خلال {$a->time}.

يرجى الانضمام إلى غرفة الدرس في الوقت المحدد.';

// ─────────────────────────────────────────────────────────────────────────────
// B2B subscriptions (US-B2B-1-*), settings tabs, and user activity report.
// ─────────────────────────────────────────────────────────────────────────────
$string['managesettings'] = 'إعدادات المنصة';
$string['set_tab_deadlines']        = 'مواعيد الدروس';
$string['set_tab_financial']        = 'الإعدادات المالية';
$string['set_tab_b2b']              = 'إعدادات اشتراك الأعمال (B2B)';
$string['set_b2b_auto_approve']     = 'الموافقة التلقائية على المستخدمين المدعوين';
$string['set_b2b_auto_approve_help'] = 'عند التفعيل، تتم الموافقة على المستخدم المدعو تلقائيًا إذا توفر مقعد شاغر؛ وإلا يبقى قيد الانتظار حتى يوافق عليه مدير الأعمال.';
$string['set_b2b_return_seat']      = 'إرجاع المقعد عند إزالة مستخدم';
$string['set_b2b_return_seat_help'] = 'عند التفعيل، تؤدي إزالة مستخدم معتمد إلى تحرير مقعده. عند التعطيل، يبقى المقعد مستهلكًا حتى انتهاء الاشتراك.';
$string['set_enabled']              = 'مُفعّل';
$string['set_disabled']             = 'مُعطّل';

$string['err_seatspositive']    = 'يجب أن يكون عدد المقاعد أكبر من صفر';
$string['err_discountrange']    = 'يجب أن تكون نسبة الخصم بين 0 و100';
$string['err_b2bnotenabled']    = 'هذا الاشتراك غير متاح للشراء كاشتراك أعمال';
$string['err_seatoptioninvalid'] = 'السعة المختارة غير متاحة لهذا الاشتراك';
$string['err_b2bnotowner']      = 'أنت لا تدير اشتراك الأعمال هذا';
$string['err_b2bnotactive']     = 'اشتراك الأعمال هذا غير نشط';
$string['err_b2bexpired']       = 'انتهى اشتراك الأعمال هذا';
$string['err_nofreeseats']      = 'لا توجد مقاعد متاحة في اشتراك الأعمال هذا';
$string['err_invalidinvite']    = 'رابط الدعوة هذا غير صالح أو منتهٍ أو معطّل أو ملغى';
$string['err_membershipnotfound'] = 'العضوية غير موجودة';
$string['err_notpending']       = 'هذه العضوية ليست قيد الموافقة';
$string['err_notapproved']      = 'هذه العضوية ليست معتمدة حاليًا';
$string['err_b2brole_missing']  = 'دور مدير الأعمال غير مُهيأ في هذا الموقع';

$string['sub_field_b2b']           = 'إتاحة الشراء كاشتراك أعمال (B2B)';
$string['sub_seat_options']        = 'خيارات المقاعد';
$string['sub_seat_options_help']   = 'أضف خيارًا واحدًا أو أكثر لسعة المستخدمين، لكل منها نسبة خصم خاصة. يُحسب سعر الأعمال كالتالي: (السعر العادي × المقاعد) − الخصم.';
$string['sub_col_seats']           = 'المقاعد';
$string['sub_col_discount']        = 'نسبة الخصم %';
$string['sub_col_b2bprice']        = 'سعر الأعمال';
$string['sub_seat_add']            = 'إضافة خيار مقاعد';
$string['sub_b2b_badge']           = 'أعمال';
$string['ui_remove']               = 'إزالة';

$string['hp_b2b_business']      = 'اشتراك أعمال (B2B)';
$string['hp_b2b_confirm_title'] = 'شراء اشتراك أعمال';
$string['hp_b2b_confirm_body']  = 'اختر عدد المقاعد التي تحتاجها. ستتمكن من إدارة المقاعد ودعوة المستخدمين بعد الشراء.';
$string['hp_b2b_capacity']      = 'السعة';
$string['hp_b2b_users']         = '{n} مستخدم';
$string['hp_b2b_base']          = 'السعر الأساسي';
$string['hp_b2b_discount']      = 'الخصم';
$string['hp_b2b_total']         = 'إجمالي الأعمال';
$string['hp_b2b_success']       = 'تم شراء اشتراك الأعمال. أنت الآن مدير أعمال.';
$string['hp_b2b_manage']        = 'إدارة اشتراك الأعمال';

$string['messageprovider:b2bnotification'] = 'تحديثات اشتراك الأعمال (الشراء، طلبات الانضمام، الموافقات)';
$string['notif_b2b_purchased_subject'] = 'اشتراك الأعمال الخاص بك نشط';
$string['notif_b2b_purchased_body']    = 'أصبح اشتراك الأعمال "{$a->subscription}" بعدد {$a->seats} مقعد نشطًا. أنت الآن مدير أعمال ويمكنك دعوة المستخدمين والموافقة عليهم.';
$string['notif_b2b_pending_subject']   = 'مستخدم بانتظار موافقتك';
$string['notif_b2b_pending_body']      = 'طلب {$a->user} الانضمام إلى اشتراك الأعمال "{$a->subscription}". افتح لوحة الأعمال للموافقة أو الرفض.';
$string['notif_b2b_approved_subject']  = 'تمت الموافقة على عضويتك في الأعمال';
$string['notif_b2b_approved_body']     = 'أصبح لديك الآن وصول إلى مقررات اشتراك "{$a->subscription}".';
$string['notif_b2b_rejected_subject']  = 'تم رفض طلب انضمامك';
$string['notif_b2b_rejected_body']     = 'تم رفض طلبك للانضمام إلى اشتراك "{$a->subscription}". {$a->reason}';
$string['notif_b2b_removed_subject']   = 'تمت إزالة وصولك في الأعمال';
$string['notif_b2b_removed_body']      = 'تمت إزالة وصولك عبر اشتراك الأعمال "{$a->subscription}".';

$string['b2b_dashboard_title'] = 'اشتراك الأعمال';
$string['b2b_no_subs']         = 'أنت لا تدير أي اشتراك أعمال.';
$string['b2b_purchased']       = 'المقاعد المشتراة';
$string['b2b_consumed']        = 'المقاعد المستهلكة';
$string['b2b_available']       = 'المقاعد المتاحة';
$string['b2b_expires']         = 'ينتهي في';
$string['b2b_pending']         = 'قيد الانتظار';
$string['b2b_approved']        = 'معتمد';
$string['b2b_rejected']        = 'مرفوض';
$string['b2b_removed']         = 'مُزال';
$string['b2b_expired']         = 'منتهٍ';
$string['b2b_removed_returned'] = 'مُزال (أُرجع المقعد)';
$string['b2b_removed_kept']    = 'مُزال (بقي المقعد)';
$string['b2b_invite_heading']  = 'رابط الدعوة';
$string['b2b_generate']        = 'إنشاء رابط';
$string['b2b_revoke']          = 'إلغاء';
$string['b2b_copy']            = 'نسخ';
$string['b2b_copied']          = 'تم النسخ';
$string['b2b_link_none']       = 'لا يوجد رابط دعوة نشط. أنشئ رابطًا لدعوة المستخدمين.';
$string['b2b_link_active']     = 'يوجد رابط دعوة نشط.';
$string['b2b_members']         = 'الأعضاء';
$string['b2b_col_user']        = 'المستخدم';
$string['b2b_col_status']      = 'الحالة';
$string['b2b_col_seat']        = 'المقعد';
$string['b2b_col_actions']     = 'إجراءات';
$string['b2b_approve']         = 'موافقة';
$string['b2b_reject']          = 'رفض';
$string['b2b_remove']          = 'إزالة';
$string['b2b_seat_yes']        = 'يستهلك مقعدًا';
$string['b2b_seat_no']         = 'بدون مقعد';
$string['b2b_none']            = 'لا يوجد أعضاء بعد.';
$string['b2b_reason_prompt']   = 'السبب (اختياري):';
$string['b2b_confirm_remove']  = 'إزالة هذا المستخدم من اشتراك الأعمال الخاص بك؟';
$string['b2b_tab_all']         = 'الكل';
$string['b2b_action_done']     = 'تم.';
$string['b2b_never']           = 'أبدًا';
$string['b2b_join_title']      = 'الانضمام إلى اشتراك أعمال';
$string['b2b_join_login']      = 'يرجى تسجيل الدخول أو التسجيل للانضمام إلى اشتراك الأعمال هذا.';
$string['b2b_join_pending']    = 'تم استلام طلب انضمامك وهو قيد موافقة مدير الأعمال.';
$string['b2b_join_approved']   = 'تمت الموافقة عليك ولديك الآن وصول إلى مقررات الاشتراك.';
$string['b2b_join_rejected']   = 'تم رفض طلبك السابق للانضمام إلى هذا الاشتراك.';
$string['b2b_join_removed']    = 'تمت إزالتك من هذا الاشتراك.';
$string['b2b_join_goto']       = 'الذهاب إلى لوحتي';

$string['rp_tab_useractivity'] = 'نشاط المستخدم';
$string['rp_f_userid']        = 'معرّف المستخدم';
$string['rp_f_email']         = 'البريد الإلكتروني';
$string['rp_ua_registered']   = 'تاريخ التسجيل';
$string['rp_ua_lastlogin']    = 'آخر دخول';
$string['rp_ua_status']       = 'الحساب';
$string['rp_ua_roles']        = 'الأدوار';
$string['rp_ua_subs']         = 'الاشتراكات';
$string['rp_ua_memberships']  = 'عضويات الأعمال';
$string['rp_ua_courses']      = 'المقررات المتاح الوصول إليها';
$string['rp_ua_actions']      = 'الإجراءات الأخيرة';
$string['rp_ua_none']         = 'لا يوجد.';
$string['rp_enter_user']      = 'أدخل معرّف مستخدم أو بريدًا إلكترونيًا ثم اضغط تشغيل.';
