<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'الأكاديمية';
$string['academy:manageplatform'] = 'إدارة منصة الأكاديمية';

// Welcome notification (sent on first login after email signup).
$string['messageprovider:welcome'] = 'رسالة الترحيب';
$string['welcome_subject'] = 'أهلاً بك في {$a}!';
$string['welcome_small'] = 'أهلاً بك في {$a}!';
$string['welcome_body'] = 'مرحباً {$a->name}،

أهلاً بك في {$a->site}! حسابك أصبح مفعّلاً الآن.

يمكنك تصفّح الدورات والاشتراك فيها والبدء في التعلّم فوراً. سعداء بانضمامك إلينا.';

// Dispatcher / envelope.
$string['err_postrequired']     = 'هذا الإجراء يتطلب طلب POST';
$string['err_authrequired']     = 'مطلوب تسجيل الدخول';
$string['err_invalidtoken']     = 'رمز وصول غير صالح';
$string['err_permissiondenied'] = 'ليس لديك صلاحية';
$string['err_unknownfunction']  = 'دالة غير معروفة';
$string['err_internal']         = 'حدث خطأ داخلي. من فضلك حاول مرة أخرى لاحقاً.';
$string['err_teachernotfound']  = 'لم يتم العثور على المدرّس.';

// Password reset (OTP).
$string['err_invalidemail']     = 'من فضلك أدخل بريداً إلكترونياً صحيحاً.';
$string['err_toomanyrequests']  = 'عدد كبير من طلبات الرمز. من فضلك انتظر بضع دقائق ثم حاول مرة أخرى.';
$string['err_otpexpired']       = 'انتهت صلاحية هذا الرمز. من فضلك اطلب رمزاً جديداً.';
$string['err_otplocked']        = 'عدد كبير من المحاولات الخاطئة. من فضلك اطلب رمزاً جديداً.';
$string['err_otpinvalid']       = 'الرمز الذي أدخلته غير صحيح.';
$string['err_resetexpired']     = 'انتهت صلاحية جلسة إعادة التعيين. من فضلك ابدأ من جديد.';
$string['err_weakpassword']     = 'كلمة المرور الجديدة لا تستوفي المتطلبات.';
$string['err_wrongpassword']    = 'كلمة المرور الحالية غير صحيحة.';
$string['err_authnochange']     = 'لا يمكن تغيير كلمة المرور لهذا الحساب من هنا (فهو يسجّل الدخول عبر جوجل).';
$string['otp_subject']          = '{$a}: رمز إعادة تعيين كلمة المرور';
$string['otp_body']             = 'مرحباً {$a->name}،

رمز إعادة تعيين كلمة المرور الخاص بك في {$a->site} هو: {$a->code}

الرمز صالح لمدة {$a->mins} دقيقة. إذا لم تكن أنت من طلب ذلك، يمكنك تجاهل هذه الرسالة.';

// Quiz manager.
$string['notenrolled'] = 'أنت غير مسجّل في هذه الدورة';

// Account lockout (AC-4.3.2 / AC-4.3.4).
$string['lockout_blocked'] = 'تم قفل حسابك مؤقتاً بعد {$a->attempts} محاولات دخول فاشلة. من فضلك حاول مرة أخرى بعد {$a->wait}، أو استخدم رابط فك القفل الذي أرسلناه إلى بريدك الإلكتروني.';
$string['lockout_blocked_nowait'] = 'تم قفل حسابك بعد {$a} محاولات دخول فاشلة. من فضلك استخدم رابط فك القفل الذي أرسلناه إلى بريدك الإلكتروني، أو تواصل مع الدعم.';

// Administrator settings.
$string['settings_passwordreset'] = 'رموز إعادة تعيين كلمة المرور';
$string['settings_passwordreset_desc'] = 'حدود الرمز المؤقت الذي يُرسل عند طلب إعادة تعيين كلمة مرور منسية. أما قفل الحساب بعد محاولات الدخول الفاشلة فهو إعداد منفصل يُضبط من الأمان &gt; إعدادات أمان الموقع.';
$string['settings_otprequestmax'] = 'الحد الأقصى لطلبات إعادة التعيين';
$string['settings_otprequestmax_desc'] = 'عدد الرموز التي يمكن لبريد إلكتروني واحد طلبها خلال المدة المحددة أدناه. الطلبات الإضافية تُرفض حتى تنتهي المدة.';
$string['settings_otprequestwindow'] = 'مدة احتساب الطلبات';
$string['settings_otprequestwindow_desc'] = 'الفترة الزمنية التي يُحسب خلالها حد الطلبات.';
$string['settings_otpmaxattempts'] = 'الحد الأقصى لإدخال الرمز بشكل خاطئ';
$string['settings_otpmaxattempts_desc'] = 'عدد مرات إدخال الرمز بشكل خاطئ قبل إبطال الرمز ووجوب طلب رمز جديد.';
$string['settings_otpttl'] = 'صلاحية الرمز';
$string['settings_otpttl_desc'] = 'المدة التي يظل فيها رمز إعادة التعيين صالحاً للاستخدام بعد إرساله.';

// Login.
$string['err_invalidlogin'] = 'بيانات الدخول غير صحيحة، من فضلك حاول مرة أخرى';
$string['err_usernotconfirmed'] = 'لم يتم تأكيد حسابك بعد. من فضلك افتح رابط التأكيد المُرسل إلى بريدك الإلكتروني ثم سجّل الدخول مرة أخرى.';
