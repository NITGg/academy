<?php
defined('MOODLE_INTERNAL') || die();

// Plugin.
$string['pluginname'] = 'المدفوعات';
$string['subplugintype_paymentprovider'] = 'مزود الدفع';
$string['subplugintype_paymentprovider_plural'] = 'مزودي الدفع';

// Settings.
$string['generalsettings'] = 'إعدادات الدفع';
$string['default_country'] = 'الدولة الافتراضية';
$string['default_country_desc'] = 'رمز الدولة الافتراضي (ISO 3166-1 alpha-2) عند فشل الاكتشاف التلقائي.';
$string['default_currency'] = 'العملة الافتراضية';
$string['default_currency_desc'] = 'رمز العملة الافتراضي (ISO 4217).';
$string['payment_ttl'] = 'مهلة الدفع (بالثواني)';
$string['payment_ttl_desc'] = 'المدة التي يظل فيها الدفع المعلق صالحاً قبل الانتهاء تلقائياً. الافتراضي: 1800 (30 دقيقة).';
$string['show_sale_badge'] = 'إظهار شارة التخفيض';
$string['show_sale_badge_desc'] = 'عرض شارة نسبة الخصم على بطاقات الدورات.';
$string['auto_expire_enabled'] = 'انتهاء المدفوعات المعلقة تلقائياً';
$string['auto_expire_enabled_desc'] = 'إنهاء المدفوعات المعلقة تلقائياً بعد انتهاء المهلة.';
$string['course_preview'] = 'معاينة الكورس مع قفل الأنشطة';
$string['course_preview_desc'] = 'السماح لأي زائر — حتى غير المسجَّل دخولاً — بفتح صفحة الكورس وقراءة وصفه وقائمة أنشطته، مع بقاء كل الأنشطة مقفلة حتى يلتحق أو يشتري. عطِّل الخيار للعودة إلى تحويله إلى صفحة الدخول أو الشراء.';

// Locked course preview.
$string['preview_notice'] = 'أنت تشاهد معاينة لهذا الكورس. تُفتح الأنشطة بعد حصولك على صلاحية الوصول.';
$string['preview_unlock'] = 'افتح هذا الكورس';
$string['preview_login'] = 'سجّل الدخول للمتابعة';
$string['preview_locked'] = 'هذا النشاط مقفل حتى تحصل على صلاحية الوصول إلى الكورس.';
$string['manageproviders'] = 'إدارة مزودي الدفع';
$string['providersettings'] = 'إعدادات المزود';
$string['reports'] = 'تقارير المدفوعات';

// Course pricing.
$string['coursepricing'] = 'تسعير الدورة';
$string['addprice'] = 'إضافة قاعدة تسعير';
$string['editprice'] = 'تعديل قاعدة التسعير';
$string['noprices'] = 'لم يتم تكوين قواعد تسعير لهذه الدورة.';
$string['pricesaved'] = 'تم حفظ قاعدة التسعير.';
$string['pricedeleted'] = 'تم حذف قاعدة التسعير.';
$string['confirmdeleteprice'] = 'هل أنت متأكد من حذف قاعدة التسعير هذه؟';
$string['defaultprice'] = 'الافتراضي (جميع الدول)';

// Form fields.
$string['country'] = 'الدولة';
$string['currency'] = 'العملة';
$string['price'] = 'السعر';
$string['sale_price'] = 'سعر التخفيض';
$string['start_date'] = 'تاريخ البداية';
$string['end_date'] = 'تاريخ النهاية';
$string['is_default'] = 'السعر الافتراضي';
$string['is_active'] = 'نشط';
$string['priority'] = 'الأولوية';
$string['actions'] = 'الإجراءات';

// Validation.
$string['error_price_positive'] = 'يجب أن يكون السعر أكبر من صفر.';
$string['error_sale_price_lower'] = 'يجب أن يكون سعر التخفيض أقل من السعر العادي.';
$string['error_end_after_start'] = 'يجب أن يكون تاريخ النهاية بعد تاريخ البداية.';
$string['error_one_default'] = 'مسموح بسعر افتراضي واحد فقط لكل دورة.';
$string['error_one_active_per_country'] = 'يوجد بالفعل قاعدة تسعير مفعّلة لهذه الدولة. قم بإلغاء تفعيلها أولاً أو عدّل تلك القاعدة.';

// Course display.
$string['enrolled'] = 'مسجل';
$string['purchased'] = 'تم الشراء';
$string['sale'] = 'تخفيض';
$string['buynow'] = 'اشترك';
$string['buycourse'] = 'اشترك الآن';
$string['free'] = 'مجاني';
$string['entercourse'] = 'انضم';
$string['already_enrolled'] = 'أنت مسجل في هذه الدورة';
$string['already_purchased'] = 'لقد قمت بشراء هذه الدورة';
$string['secure_checkout'] = 'دفع آمن عبر مزودي دفع موثوقين';

// Payment flow.
$string['paymentfor'] = 'دفع مقابل: {$a}';
$string['payment_success'] = 'تم الدفع بنجاح';
$string['payment_success_message'] = 'تمت معالجة دفعتك بنجاح. يمكنك الآن الوصول إلى الدورة.';
$string['payment_failure'] = 'فشل الدفع';
$string['payment_failure_message'] = 'لم يتم معالجة دفعتك. يرجى المحاولة مرة أخرى أو الاتصال بالدعم.';
$string['gotocourse'] = 'الذهاب للدورة';
$string['viewhistory'] = 'عرض سجل المدفوعات';
$string['tryagain'] = 'حاول مرة أخرى';

// History.
$string['paymenthistory'] = 'سجل المدفوعات';
$string['nopayments'] = 'لا توجد سجلات دفع.';
$string['orderid'] = 'رقم الطلب';
$string['course'] = 'الدورة';
$string['amount'] = 'المبلغ';
$string['status'] = 'الحالة';
$string['paymentmethod'] = 'طريقة الدفع';
$string['invoice'] = 'الفاتورة';
$string['date'] = 'التاريخ';

// Reports.
$string['total_revenue'] = 'إجمالي الإيرادات';
$string['total_transactions'] = 'إجمالي المعاملات';
$string['pending'] = 'معلق';
$string['failedpayments'] = 'فاشل';
$string['refunds'] = 'المبالغ المستردة';
$string['revenue_by_country'] = 'الإيرادات حسب الدولة';
$string['revenue_by_provider'] = 'الإيرادات حسب المزود';
$string['top_selling_courses'] = 'الدورات الأكثر مبيعاً';
$string['transactions'] = 'المعاملات';
$string['revenue'] = 'الإيرادات';
$string['provider'] = 'المزود';
$string['purchases'] = 'المشتريات';

// Events.
$string['event_payment_completed'] = 'اكتمل الدفع';
$string['event_payment_created'] = 'تم إنشاء الدفع';
$string['event_payment_failed'] = 'فشل الدفع';
$string['event_refund_processed'] = 'تمت معالجة الاسترداد';

// Tasks.
$string['task_expire_pending'] = 'إنهاء المدفوعات المعلقة';

// Messages.
$string['payment_confirmation_subject'] = 'تأكيد الدفع: {$a}';
$string['payment_confirmation_body'] = 'تم تأكيد دفعتك لدورة "{$a->coursename}".

المبلغ: {$a->amount} {$a->currency}
رقم الطلب: {$a->order_id}

يمكنك الآن الوصول الكامل للدورة.';
$string['payment_confirmation_html'] = '<p>تم تأكيد دفعتك لدورة <strong>{$a->coursename}</strong>.</p>
<p>المبلغ: <strong>{$a->amount} {$a->currency}</strong><br>رقم الطلب: <code>{$a->order_id}</code></p>
<p>يمكنك الآن الوصول الكامل للدورة.</p>';
$string['payment_confirmation_small'] = 'تم تأكيد الدفع لـ {$a}';
$string['messageprovider:payment_confirmation'] = 'إشعارات تأكيد الدفع';

// Errors.
$string['nopricefound'] = 'لم يتم العثور على قاعدة تسعير لهذه الدورة في منطقتك.';
$string['noproviderfound'] = 'لا يوجد مزود دفع متاح لمنطقتك.';
$string['alreadypurchased'] = 'لقد قمت بشراء هذه الدورة بالفعل.';
$string['alreadyenrolled'] = 'أنت مسجل بالفعل في هذه الدورة.';
$string['paymentinitiationfailed'] = 'لم يتم بدء الدفع: {$a}';
$string['transactionnotfound'] = 'لم يتم العثور على المعاملة.';
$string['enrolpluginnotinstalled'] = 'إضافة التسجيل "{$a}" غير مثبتة.';

// Privacy.
$string['privacy:metadata:transactions'] = 'سجلات معاملات الدفع.';
$string['privacy:metadata:transactions:userid'] = 'المستخدم الذي قام بالدفع.';
$string['privacy:metadata:transactions:courseid'] = 'الدورة التي يتم شراؤها.';
$string['privacy:metadata:transactions:amount'] = 'مبلغ الدفع.';
$string['privacy:metadata:transactions:currency'] = 'عملة الدفع.';
$string['privacy:metadata:transactions:status'] = 'حالة الدفع.';
$string['privacy:metadata:transactions:ip_address'] = 'عنوان IP للمستخدم.';
$string['privacy:metadata:transactions:customer_email'] = 'البريد الإلكتروني المرسل لمزود الدفع.';
$string['privacy:metadata:invoices'] = 'سجلات فواتير الدفع.';
$string['privacy:metadata:invoices:userid'] = 'المستخدم الذي تنتمي له الفاتورة.';
$string['privacy:metadata:invoices:invoice_number'] = 'رقم الفاتورة.';
$string['privacy:metadata:invoices:amount'] = 'مبلغ الفاتورة.';

// Capabilities.
$string['payments:purchasecourse'] = 'شراء دورة';
$string['payments:viewownhistory'] = 'عرض سجل المدفوعات الخاصة';
$string['payments:managecoursepricing'] = 'إدارة تسعير الدورات';
$string['payments:viewreports'] = 'عرض تقارير المدفوعات';
$string['payments:managerefunds'] = 'إدارة المبالغ المستردة';
$string['payments:manageproviders'] = 'إدارة مزودي الدفع';
$string['payments:viewalltransactions'] = 'عرض جميع المعاملات';
$string['payments:viewauditlogs'] = 'عرض سجل التدقيق';

// Country-gated pricing: a signed-in account with no profile country is shown no price at all
// (see local_payments\country_detector::pricing_blocked) and cannot reach checkout.
$string['countryrequired'] = 'حدِّد دولتك لعرض السعر';
$string['countryrequired_desc'] = 'تختلف الأسعار من دولة لأخرى. أضِف دولتك إلى ملفك الشخصي لعرض سعر هذا الكورس ومتابعة الشراء.';
$string['countryrequired_action'] = 'أضِف دولتك';

// Offline payment reference (Fawry / Meeza / wallet codes).
$string['reference_title'] = 'خطوة أخيرة — ادفع بهذا الكود';
$string['reference_lead'] = 'تم حجز طلبك. ادفع الكود التالي لإتمام العملية.';
$string['reference_lead_method'] = 'تم حجز طلبك. ادفع الكود التالي عبر {$a} لإتمام العملية.';
$string['reference_copy'] = 'نسخ';
$string['reference_copied'] = 'تم النسخ';
$string['reference_amount'] = 'المبلغ';
$string['reference_item'] = 'مقابل';
$string['reference_expires'] = 'ادفع قبل';
$string['reference_order'] = 'رقم الطلب';
$string['reference_note'] = 'يتم منح الوصول تلقائياً فور تأكيد الدفع — عادةً خلال دقائق من السداد. يمكنك إغلاق هذه الصفحة؛ الكود محفوظ في سجل مدفوعاتك.';
$string['reference_check'] = 'لقد دفعت — تحقق الآن';
$string['reference_history'] = 'سجل المدفوعات';
$string['reference_pending'] = 'لم نستلم هذه الدفعة بعد. إذا كنت قد دفعت للتو، انتظر بضع دقائق ثم تحقق مرة أخرى.';

// Invoice PDF.
$string['invoice_download'] = 'تحميل الفاتورة';
$string['invoice_download_en'] = 'تحميل الفاتورة بالإنجليزية';
$string['invoice_download_ar'] = 'تحميل الفاتورة بالعربية';
$string['invoice_lang_en'] = 'English';
$string['invoice_lang_ar'] = 'العربية';
$string['invoice_notavailable'] = 'لا توجد فاتورة لهذا الطلب لأن عملية الدفع لم تكتمل.';
$string['invoice_title'] = 'فاتورة';
$string['invoice_number'] = 'رقم الفاتورة';
$string['invoice_date'] = 'تاريخ الفاتورة';
$string['invoice_from'] = 'من';
$string['invoice_to'] = 'إلى';
$string['invoice_description'] = 'البيان';
$string['invoice_item'] = 'شراء مقرر';
$string['invoice_subtotal'] = 'المجموع الفرعي';
$string['invoice_discount'] = 'الخصم';
$string['invoice_total'] = 'الإجمالي المدفوع';
$string['invoice_paid_on'] = 'تاريخ السداد';
$string['invoice_footer_default'] = 'شكراً لشرائك.';
$string['invoice_seller_name'] = 'الفاتورة: اسم البائع';
$string['invoice_seller_name_desc'] = 'الاسم الذي يُطبع كجهة إصدار في ملفات الفواتير. اتركه فارغاً لاستخدام اسم الموقع.';
$string['invoice_seller_details'] = 'الفاتورة: بيانات البائع';
$string['invoice_seller_details_desc'] = 'العنوان والرقم الضريبي وأي بيانات أخرى تظهر أسفل اسم البائع. كل بند في سطر.';
$string['invoice_footer'] = 'الفاتورة: ملاحظة التذييل';
$string['invoice_footer_desc'] = 'تُطبع بخط صغير أسفل كل فاتورة. اتركها فارغة لعبارة شكر بسيطة.';
$string['invoice_logo'] = 'الفاتورة: الشعار';
$string['invoice_logo_desc'] = 'يُطبع في أعلى زاوية كل فاتورة. بصيغة PNG أو JPG؛ يُضبط ارتفاعه على 18 مم، لذا الشعار العريض مناسب أما الطويل فسيظهر صغيراً. اتركه فارغاً لترويسة نصية فقط.';

// Payment lists for staff.
$string['alltransactions'] = 'كل المدفوعات';
$string['coursepayments'] = 'مدفوعات المقرر';
$string['searchpayments'] = 'اسم الطالب أو بريده أو رقم الطلب';
$string['allstatuses'] = 'كل الحالات';
$string['student'] = 'الطالب';
$string['provider'] = 'بوابة الدفع';
$string['payments:viewcoursepayments'] = 'عرض المدفوعات الخاصة بمقرر';

// Payment history: filters and status labels.
$string['searchmypayments'] = 'رقم الطلب أو رقم الفاتورة';
$string['allcourses'] = 'كل الدورات';
$string['datefrom'] = 'من';
$string['dateto'] = 'إلى';
$string['nopaymentsmatch'] = 'لا توجد مدفوعات مطابقة لهذه التصفية.';
$string['status_pending'] = 'قيد الانتظار';
$string['status_completed'] = 'مكتمل';
$string['status_failed'] = 'فشل';
$string['status_cancelled'] = 'ملغي';
$string['status_expired'] = 'منتهي الصلاحية';
$string['status_timed_out'] = 'انتهت المهلة';
$string['status_refunded'] = 'مسترد';
$string['status_partially_refunded'] = 'مسترد جزئياً';
$string['status_voided'] = 'ملغى';
$string['status_chargeback'] = 'رد مالي';
$string['status_duplicate'] = 'مكرر';

// Refunds.
$string['refund_heading'] = 'الاسترداد';
$string['refund_heading_desc'] = 'المدة المتاحة للمشتري لتغيير رأيه، وتكلفة ذلك. لكل نوع تبيعه نافذته الخاصة لأنها غير متكافئة: الاشتراك يبدأ فوراً، أما المقرر فيُستهلك على مدى أسابيع. وخارج النافذة يظل بإمكان المشتري تقديم طلب يبتّ فيه أحد الموظفين.';
$string['refund_enabled'] = 'السماح بالاسترداد';
$string['refund_enabled_desc'] = 'متوقف افتراضياً. عند إيقافه لا يظهر أي زر استرداد ولا يمكن تقديم أي طلب.';
$string['refund_group_course'] = 'الاسترداد: المقررات';
$string['refund_group_course_desc'] = 'ينطبق على شراء المقرر لمرة واحدة.';
$string['refund_group_subscription'] = 'الاسترداد: الاشتراكات';
$string['refund_group_subscription_desc'] = 'ينطبق على خطط الاشتراك، بما فيها شراء المقاعد للشركات.';
$string['refund_group_default'] = 'الاسترداد: كل ما عدا ذلك';
$string['refund_group_default_desc'] = 'يُستخدم لأي نوع شراء آخر — الباقات اليوم، وأي شيء يُضاف لاحقاً. فلا يبقى أي شيء يقبل الدفع بلا سياسة.';
$string['refund_hours'] = 'نافذة الاسترداد (بالساعات)';
$string['refund_hours_desc'] = 'عدد الساعات من لحظة اكتمال الدفع التي يستطيع خلالها المشتري استرداد المبلغ بنفسه دون تدخل الموظفين. بالساعات لا بالأيام حتى يمكن التعبير عن «أول 24 ساعة» و«أول أسبوعين» (336) معاً. <b>اضبطها على 0 لإلغاء النافذة التلقائية</b> — عندها يقدّم المشتري طلباً يبتّ فيه أحد الموظفين.';
$string['refund_feetype'] = 'نوع الرسوم';
$string['refund_feetype_desc'] = 'النسبة المئوية تتبع السعر وتصلح مع تعدد العملات؛ أما المبلغ الثابت فهو الأنسب لرسوم بنكية ثابتة لكنه محدد بعملة واحدة.';
$string['refund_feetype_percent'] = 'نسبة مئوية من المبلغ المدفوع';
$string['refund_feetype_fixed'] = 'مبلغ ثابت';
$string['refund_fee'] = 'رسوم الاسترداد';
$string['refund_fee_desc'] = 'ما تحتفظ به المنصة عند تنفيذ الاسترداد. <b>القيمة 0 تعني استرداداً كاملاً.</b> وأي رسوم تتجاوز المبلغ المدفوع تُخفَّض إليه، فلا يُحاسَب أحد على مجرد الطلب.';

// Refunds, buyer-facing.
$string['refund_column'] = 'الاسترداد';
$string['refund_request'] = 'استرداد';
$string['refund_now_title'] = 'استرداد هذه الدفعة';
$string['refund_ask_title'] = 'طلب استرداد';
$string['refund_now_button'] = 'استرداد';
$string['refund_ask_button'] = 'طلب استرداد';
$string['refund_now_notice'] = 'هذه الدفعة داخل نافذة الاسترداد التي تنتهي {$a}. يتم الاسترداد فوراً ويُلغى الوصول.';
$string['refund_ask_notice_closed'] = 'انتهت نافذة الاسترداد التلقائي لهذه الدفعة في {$a}، لذا سيُحال طلبك إلى فريقنا للبتّ فيه. وسنُعلمك بالنتيجة في الحالتين.';
$string['refund_ask_notice_nowindow'] = 'لا توجد نافذة استرداد تلقائي لهذا الشراء، لذا سيُحال طلبك إلى فريقنا للبتّ فيه. وسنُعلمك بالنتيجة في الحالتين.';
$string['refund_paid'] = 'المبلغ المدفوع';
$string['refund_youget'] = 'المبلغ المسترد';
$string['refund_after_fee'] = 'بعد رسوم {$a}';
$string['refund_reason_optional'] = 'السبب (اختياري)';
$string['refund_reason_required'] = 'ما سبب طلبك للاسترداد؟';
$string['refund_requested'] = 'تم إرسال طلب الاسترداد. سنُعلمك بالقرار.';
$string['refund_done'] = 'تم استرداد {$a->amount}. من المتوقع وصوله إلى حسابك خلال أيام عمل قليلة.';
$string['refund_rejected'] = 'تم رفض الطلب وإبلاغ المشتري.';

// Refunds, staff-facing.
$string['refund_requests'] = 'طلبات الاسترداد';
$string['refund_norequests'] = 'لا يوجد شيء هنا.';
$string['refund_decide'] = 'القرار';
$string['refund_decision'] = 'بتّ بواسطة';
$string['refund_approve'] = 'موافقة';
$string['refund_reject'] = 'رفض';
$string['refund_note_placeholder'] = 'ملاحظة للمشتري';
$string['refund_status_pending'] = 'بانتظار القرار';
$string['refund_status_approved'] = 'تمت الموافقة';
$string['refund_status_rejected'] = 'مرفوض';

// Refunds, refusals.
$string['refund_err_disabled'] = 'خدمة الاسترداد غير متاحة حالياً.';
$string['refund_err_notrefundable'] = 'لا يمكن استرداد هذه الدفعة. فالاسترداد متاح للدفعات المكتملة فقط ولمرة واحدة.';
$string['refund_err_alreadyasked'] = 'يوجد بالفعل طلب استرداد لهذه الدفعة بانتظار القرار.';
$string['refund_err_windowclosed'] = 'انتهت نافذة الاسترداد لهذه الدفعة. يرجى تقديم طلب استرداد بدلاً من ذلك.';
$string['refund_err_needreason'] = 'يرجى ذكر سبب طلب الاسترداد.';
$string['refund_err_decided'] = 'تم البتّ في هذا الطلب من قبل.';
$string['refund_err_gateway'] = 'بوابة الدفع المستخدمة في هذه الدفعة لا تدعم الاسترداد التلقائي. نفّذ الاسترداد من لوحة تحكم البوابة.';
$string['refund_err_gatewayfailed'] = 'رفضت بوابة الدفع عملية الاسترداد. لم يُخصم أي شيء؛ راجع سجلات المدفوعات.';
$string['refund_err_noreference'] = 'لا يوجد مرجع مسجَّل لهذه الدفعة لدى البوابة، لذا يتعذر استردادها تلقائياً.';

// Refunds, notifications.
$string['messageprovider:refund_decision'] = 'قرارات طلبات الاسترداد';
$string['refund_msg_approved_subject'] = 'تمت الموافقة على طلب الاسترداد';
$string['refund_msg_approved_body'] = 'تمت الموافقة على استرداد مبلغ الطلب {$a->order} والمبلغ في طريقه إليك. ملاحظة: {$a->note}';
$string['refund_msg_rejected_subject'] = 'بخصوص طلب الاسترداد';
$string['refund_msg_rejected_body'] = 'لم تتم الموافقة على طلب استرداد مبلغ الطلب {$a->order}. السبب: {$a->note}';

// Refunds, the policy as a sentence (refund_policy::describe).
$string['refund_policy_norwindow'] = 'لا يوجد استرداد تلقائي. يمكنك تقديم طلب وسيبتّ فيه فريقنا.';
$string['refund_policy_windowfree'] = 'استرداد كامل خلال {$a->hours} ساعة من الشراء.';
$string['refund_policy_windowfee'] = 'قابل للاسترداد خلال {$a->hours} ساعة من الشراء، بعد خصم رسوم {$a->fee}.';
