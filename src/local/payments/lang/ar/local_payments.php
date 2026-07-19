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

// Invoices (student-facing page).
$string['myinvoices'] = 'الفواتير';
$string['noinvoices'] = 'لا توجد لديك فواتير حتى الآن.';
$string['noinvoices_filtered'] = 'لا توجد فواتير مطابقة لهذه الفلاتر.';
$string['filter_invoicenumber'] = 'رقم الفاتورة';
$string['filter_invoicenumber_placeholder'] = 'مثال: INV-2026-0000001';
$string['filter_item'] = 'العنصر';
$string['filter_item_placeholder'] = 'ابحث باسم العنصر';
$string['filter_amount_min'] = 'المبلغ من';
$string['filter_amount_max'] = 'المبلغ إلى';
$string['filter_date_from'] = 'التاريخ من';
$string['filter_date_to'] = 'التاريخ إلى';
$string['filter_apply'] = 'فلترة';
$string['filter_clear'] = 'مسح الفلاتر';
$string['actions'] = 'إجراءات';
$string['back'] = 'رجوع';
$string['invoice_number'] = 'رقم الفاتورة';
$string['invoice_type'] = 'النوع';
$string['invoice_item'] = 'العنصر';
$string['invoice_view'] = 'عرض';
$string['invoice_downloadpdf'] = 'تحميل PDF';
$string['invoice_from'] = 'من';
$string['invoice_billedto'] = 'صادرة إلى';
$string['invoice_subtotal'] = 'المجموع الفرعي';
$string['invoice_discount'] = 'الخصم';
$string['invoice_total'] = 'الإجمالي';
$string['invoice_taxid'] = 'الرقم الضريبي';
$string['invoice_item_course'] = 'دورة';
$string['invoice_item_package'] = 'باقة';
$string['invoice_item_subscription'] = 'اشتراك';
$string['invstatus_issued'] = 'صادرة';
$string['invstatus_void'] = 'ملغاة';
$string['invstatus_draft'] = 'مسودة';

// Invoice settings.
$string['invoice_settings'] = 'بيانات الفاتورة';
$string['invoice_seller_name'] = 'اسم البائع';
$string['invoice_seller_name_desc'] = 'اسم الجهة الظاهر على الفواتير. يُستخدم اسم الموقع عند تركه فارغًا.';
$string['invoice_seller_details'] = 'بيانات البائع';
$string['invoice_seller_details_desc'] = 'العنوان / بيانات التواصل الظاهرة على الفواتير (سطر لكل بند).';
$string['invoice_seller_taxid'] = 'الرقم الضريبي للبائع';
$string['invoice_seller_taxid_desc'] = 'رقم التسجيل الضريبي الظاهر على الفواتير.';
$string['invoice_footer'] = 'تذييل الفاتورة';
$string['invoice_footer_desc'] = 'ملاحظة اختيارية تظهر أسفل كل فاتورة.';

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
