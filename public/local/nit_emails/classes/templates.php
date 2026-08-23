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
 * The three transactional email templates: registry, storage and shipped defaults.
 *
 * One template per (event, language). Everything lives in plugin config — there
 * is no table — so a template is a plain string an admin edits on
 * /local/nit_emails/manage.php and nothing has to be migrated on upgrade.
 *
 * A body is an HTML *fragment*: the mailer wraps it in the branded shell
 * (header band, footer, RTL flip for Arabic), so an admin only ever edits the
 * words. Placeholders are written {likethis} and are listed per event by
 * {@see placeholders()}.
 *
 * @package    local_nit_emails
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_emails;

defined('MOODLE_INTERNAL') || die();

/**
 * Template registry + storage.
 */
class templates {

    /** @var string a student paid for (and was enrolled in) a single course. */
    const EVENT_COURSE = 'course_purchase';

    /** @var string a student's subscription plan became active. */
    const EVENT_SUBSCRIPTION = 'subscription_purchase';

    /** @var string a new account finished sign-up. */
    const EVENT_REGISTRATION = 'registration';

    /** @var string[] the two languages every template is authored in. */
    const LANGS = ['en', 'ar'];

    /**
     * The events an admin can edit, in the order the tabs are shown.
     *
     * @return string[]
     */
    public static function events(): array {
        return [self::EVENT_COURSE, self::EVENT_SUBSCRIPTION, self::EVENT_REGISTRATION];
    }

    /**
     * Is $event a known event key?
     *
     * @param string $event
     * @return bool
     */
    public static function is_event(string $event): bool {
        return in_array($event, self::events(), true);
    }

    /**
     * Human name of an event (tab label).
     *
     * @param string $event
     * @return string
     */
    public static function event_name(string $event): string {
        return get_string('event_' . $event, 'local_nit_emails');
    }

    /**
     * Which of the two authored languages an email to $lang should use.
     * Anything Arabic ("ar", "ar_eg", …) gets the Arabic template; everything
     * else — including an unconfigured site — gets English.
     *
     * @param string $lang a Moodle language code
     * @return string 'ar' | 'en'
     */
    public static function normalise_lang(string $lang): string {
        return (strpos(strtolower($lang), 'ar') === 0) ? 'ar' : 'en';
    }

    /**
     * Is this event's email switched on? (Default: yes.)
     *
     * @param string $event
     * @return bool
     */
    public static function is_enabled(string $event): bool {
        $value = get_config('local_nit_emails', 'enabled_' . $event);
        return ($value === false || $value === null) ? true : (bool) $value;
    }

    /**
     * The saved subject, or the shipped default when the admin never touched it.
     *
     * @param string $event
     * @param string $lang 'en' | 'ar'
     * @return string
     */
    public static function subject(string $event, string $lang): string {
        $saved = get_config('local_nit_emails', "subject_{$lang}_{$event}");
        if ($saved === false || trim((string) $saved) === '') {
            return self::default_subject($event, $lang);
        }
        return (string) $saved;
    }

    /**
     * The saved body fragment, or the shipped default when never edited.
     *
     * @param string $event
     * @param string $lang 'en' | 'ar'
     * @return string HTML fragment
     */
    public static function body(string $event, string $lang): string {
        $saved = get_config('local_nit_emails', "body_{$lang}_{$event}");
        if ($saved === false || trim((string) $saved) === '') {
            return self::default_body($event, $lang);
        }
        return (string) $saved;
    }

    /**
     * Persist one event's four fields (+ the on/off switch).
     *
     * @param string $event
     * @param \stdClass $data form data (subject_en, body_en, subject_ar, body_ar, enabled)
     * @return void
     */
    public static function save(string $event, \stdClass $data): void {
        set_config('enabled_' . $event, empty($data->enabled) ? 0 : 1, 'local_nit_emails');
        foreach (self::LANGS as $lang) {
            $subject = trim((string) ($data->{'subject_' . $lang} ?? ''));
            $body = $data->{'body_' . $lang} ?? '';
            $body = is_array($body) ? ($body['text'] ?? '') : $body;
            set_config("subject_{$lang}_{$event}", $subject, 'local_nit_emails');
            set_config("body_{$lang}_{$event}", trim((string) $body), 'local_nit_emails');
        }
    }

    /**
     * Throw the admin's edits away and go back to the shipped wording.
     *
     * @param string $event
     * @return void
     */
    public static function reset(string $event): void {
        foreach (self::LANGS as $lang) {
            unset_config("subject_{$lang}_{$event}", 'local_nit_emails');
            unset_config("body_{$lang}_{$event}", 'local_nit_emails');
        }
    }

    /**
     * The placeholders this event's template may use, in display order. The
     * manage page prints them as a reference table using the ph_* strings.
     *
     * @param string $event
     * @return string[] placeholder names without the braces
     */
    public static function placeholders(string $event): array {
        $common = ['firstname', 'lastname', 'fullname', 'username', 'email',
            'sitename', 'siteurl', 'loginurl', 'dashboardurl', 'date', 'supportemail'];

        $peritem = [
            self::EVENT_COURSE => ['coursename', 'courseurl', 'coursesummary', 'coursestartdate',
                'totalhours', 'instructors', 'targetaudience', 'prerequisites', 'coursecontent',
                'ilos', 'amount', 'currency', 'orderid'],
            self::EVENT_SUBSCRIPTION => ['subscriptionname', 'subscriptiondescription', 'durationdays',
                'startdate', 'expirydate', 'seats', 'subscriptiontype', 'amount', 'currency',
                'orderid', 'coursesurl', 'mysubscriptionsurl'],
            self::EVENT_REGISTRATION => ['profileurl', 'browsecoursesurl'],
        ];

        return array_merge($peritem[$event] ?? [], $common);
    }

    // =========================================================================
    // Shipped defaults
    // =========================================================================

    /**
     * Default subject line per event and language.
     *
     * @param string $event
     * @param string $lang
     * @return string
     */
    public static function default_subject(string $event, string $lang): string {
        $defaults = [
            self::EVENT_COURSE => [
                'en' => 'Your course file summary — {coursename}',
                'ar' => 'ملخص ملف الدورة التدريبية — {coursename}',
            ],
            self::EVENT_SUBSCRIPTION => [
                'en' => 'Your subscription is active — {subscriptionname}',
                'ar' => 'تم تفعيل اشتراكك — {subscriptionname}',
            ],
            self::EVENT_REGISTRATION => [
                'en' => 'Welcome to {sitename} — your account is ready',
                'ar' => 'مرحبًا بك في {sitename} — حسابك جاهز الآن',
            ],
        ];
        return $defaults[$event][$lang] ?? '';
    }

    /**
     * Default body fragment per event and language.
     *
     * The three wordings deliberately differ: the course email is the course
     * file summary from the academy's document, the subscription email is a
     * plan/entitlement summary, and the registration email is an account
     * summary with first steps.
     *
     * @param string $event
     * @param string $lang
     * @return string HTML fragment
     */
    public static function default_body(string $event, string $lang): string {
        $method = 'default_' . $event . '_' . $lang;
        return method_exists(self::class, $method) ? self::$method() : '';
    }

    /**
     * One label/value row of a summary table.
     *
     * @param string $label
     * @param string $value
     * @return string
     */
    private static function row(string $label, string $value): string {
        return '<tr>'
            . '<td style="padding:8px 12px;border-bottom:1px solid #e3e8ef;font-weight:bold;width:38%;'
            . 'vertical-align:top;">' . $label . '</td>'
            . '<td style="padding:8px 12px;border-bottom:1px solid #e3e8ef;vertical-align:top;">'
            . $value . '</td></tr>';
    }

    /**
     * Opening tag of a summary table.
     *
     * @return string
     */
    private static function tableopen(): string {
        return '<table style="width:100%;border-collapse:collapse;border:1px solid #e3e8ef;margin:0 0 20px;">';
    }

    /**
     * English course-purchase body.
     *
     * @return string
     */
    private static function default_course_purchase_en(): string {
        return '<p>Hi {firstname},</p>'
            . '<p>Thank you for your purchase. Your payment has been confirmed and you are now enrolled in '
            . '<strong>{coursename}</strong>. Here is the full course file summary, so you know exactly what to '
            . 'expect before your first session.</p>'
            . '<h3>Course File Summary</h3>'
            . self::tableopen()
            . self::row('Course Title:', '{coursename}')
            . self::row('Total Number of Hours:', '{totalhours}')
            . self::row('Instructor/Facilitator Name:', '{instructors}')
            . self::row('Target Audience:', '{targetaudience}')
            . self::row('Prerequisites (Knowledge &amp; Technical Requirements):', '{prerequisites}')
            . '</table>'
            . '<h3>Course Content &amp; Program Structure</h3>'
            . '{coursecontent}'
            . '<h3>Intended Learning Outcomes (ILOs)</h3>'
            . '<p>By the end of this training program, the trainee will be able to competently:</p>'
            . '{ilos}'
            . '<h3>Your order</h3>'
            . self::tableopen()
            . self::row('Order number:', '{orderid}')
            . self::row('Amount paid:', '{amount} {currency}')
            . self::row('Purchase date:', '{date}')
            . '</table>'
            . '<p><a href="{courseurl}" class="nit-btn">Open the course</a></p>'
            . '<p>Keep this email — it is your record of the programme you enrolled in. If anything here does '
            . 'not match what you expected, write to {supportemail} and we will sort it out.</p>';
    }

    /**
     * Arabic course-purchase body.
     *
     * @return string
     */
    private static function default_course_purchase_ar(): string {
        return '<p>مرحبًا {firstname}،</p>'
            . '<p>شكرًا لشرائك. تم تأكيد عملية الدفع وتم تسجيلك في دورة <strong>{coursename}</strong>. '
            . 'وفيما يلي ملخص ملف الدورة التدريبية كاملًا لتكون على دراية بمحتواها قبل بدء أولى جلساتك.</p>'
            . '<h3>ملخص الدورة التدريبية</h3>'
            . self::tableopen()
            . self::row('عنوان الدورة التدريبية:', '{coursename}')
            . self::row('إجمالي عدد الساعات:', '{totalhours}')
            . self::row('اسم المحاضر:', '{instructors}')
            . self::row('الفئة المستهدفة:', '{targetaudience}')
            . self::row('المتطلبات الأساسية (المعرفية والتقنية):', '{prerequisites}')
            . '</table>'
            . '<h3>محتوى الدورة وهيكل البرنامج</h3>'
            . '{coursecontent}'
            . '<h3>مخرجات التعلم المستهدفة (ILOs)</h3>'
            . '<p>بنهاية هذا البرنامج التدريبي، سيكون المتدرب قادرًا بكفاءة على:</p>'
            . '{ilos}'
            . '<h3>تفاصيل طلبك</h3>'
            . self::tableopen()
            . self::row('رقم الطلب:', '{orderid}')
            . self::row('المبلغ المدفوع:', '{amount} {currency}')
            . self::row('تاريخ الشراء:', '{date}')
            . '</table>'
            . '<p><a href="{courseurl}" class="nit-btn">ابدأ الدورة</a></p>'
            . '<p>يرجى الاحتفاظ بهذه الرسالة، فهي سجلك للبرنامج الذي التحقت به. وإذا لاحظت أي اختلاف عمّا '
            . 'توقعته، راسلنا على {supportemail} وسنتولى الأمر.</p>';
    }

    /**
     * English subscription-purchase body.
     *
     * @return string
     */
    private static function default_subscription_purchase_en(): string {
        return '<p>Hi {firstname},</p>'
            . '<p>Your subscription has been activated. From now until it expires, '
            . '<strong>{subscriptionname}</strong> unlocks every course it covers — with no separate purchase '
            . 'per course. These are the terms of the plan you subscribed to.</p>'
            . '<h3>Subscription Summary</h3>'
            . self::tableopen()
            . self::row('Plan:', '{subscriptionname}')
            . self::row('What it covers:', '{subscriptiondescription}')
            . self::row('Plan type:', '{subscriptiontype}')
            . self::row('Access period:', '{durationdays}')
            . self::row('Active from:', '{startdate}')
            . self::row('Active until:', '{expirydate}')
            . self::row('Seats included:', '{seats}')
            . '</table>'
            . '<h3>Your order</h3>'
            . self::tableopen()
            . self::row('Order number:', '{orderid}')
            . self::row('Amount paid:', '{amount} {currency}')
            . self::row('Subscription date:', '{date}')
            . '</table>'
            . '<h3>How to start using it</h3>'
            . '<ul>'
            . '<li>Open the catalogue and pick any course included in your plan.</li>'
            . '<li>Access is granted the moment you open a covered course — there is nothing else to pay.</li>'
            . '<li>Check the days remaining on your plan at any time from your subscriptions page.</li>'
            . '</ul>'
            . '<p><a href="{coursesurl}" class="nit-btn">Browse the courses</a></p>'
            . '<p>You can review your plan at <a href="{mysubscriptionsurl}">{mysubscriptionsurl}</a>, or write '
            . 'to {supportemail} if you need a hand.</p>';
    }

    /**
     * Arabic subscription-purchase body.
     *
     * @return string
     */
    private static function default_subscription_purchase_ar(): string {
        return '<p>مرحبًا {firstname}،</p>'
            . '<p>تم تفعيل اشتراكك. ومن الآن وحتى تاريخ انتهائه، يتيح لك <strong>{subscriptionname}</strong> '
            . 'الوصول إلى جميع الدورات المشمولة دون الحاجة إلى شراء كل دورة على حدة. وفيما يلي تفاصيل الباقة '
            . 'التي اشتركت بها.</p>'
            . '<h3>ملخص الاشتراك</h3>'
            . self::tableopen()
            . self::row('اسم الباقة:', '{subscriptionname}')
            . self::row('ما تشمله الباقة:', '{subscriptiondescription}')
            . self::row('نوع الاشتراك:', '{subscriptiontype}')
            . self::row('مدة الاشتراك:', '{durationdays}')
            . self::row('تاريخ بدء الاشتراك:', '{startdate}')
            . self::row('تاريخ انتهاء الاشتراك:', '{expirydate}')
            . self::row('عدد المقاعد:', '{seats}')
            . '</table>'
            . '<h3>تفاصيل طلبك</h3>'
            . self::tableopen()
            . self::row('رقم الطلب:', '{orderid}')
            . self::row('المبلغ المدفوع:', '{amount} {currency}')
            . self::row('تاريخ الاشتراك:', '{date}')
            . '</table>'
            . '<h3>كيف تبدأ الاستفادة من اشتراكك</h3>'
            . '<ul>'
            . '<li>تصفّح قائمة الدورات واختر أي دورة مشمولة بباقتك.</li>'
            . '<li>يُمنح لك الوصول فور فتح أي دورة مشمولة، دون أي رسوم إضافية.</li>'
            . '<li>يمكنك متابعة المدة المتبقية من اشتراكك في أي وقت من صفحة اشتراكاتي.</li>'
            . '</ul>'
            . '<p><a href="{coursesurl}" class="nit-btn">تصفّح الدورات</a></p>'
            . '<p>يمكنك مراجعة باقتك عبر <a href="{mysubscriptionsurl}">{mysubscriptionsurl}</a>، أو مراسلتنا على '
            . '{supportemail} إذا احتجت أي مساعدة.</p>';
    }

    /**
     * English registration body.
     *
     * @return string
     */
    private static function default_registration_en(): string {
        return '<p>Hi {firstname},</p>'
            . '<p>Your registration is complete and your account on <strong>{sitename}</strong> is now active. '
            . 'Nothing has been purchased yet — this message simply confirms the account you sign in with.</p>'
            . '<h3>Account Summary</h3>'
            . self::tableopen()
            . self::row('Full name:', '{fullname}')
            . self::row('Username:', '{username}')
            . self::row('Email address:', '{email}')
            . self::row('Registered on:', '{date}')
            . '</table>'
            . '<h3>Your first three steps</h3>'
            . '<ul>'
            . '<li>Complete your profile, so your certificates carry your name exactly as you want it.</li>'
            . '<li>Browse the catalogue and shortlist the programmes that match your goals.</li>'
            . '<li>Enrol in a single course, or subscribe to a plan if you intend to take several.</li>'
            . '</ul>'
            . '<p><a href="{browsecoursesurl}" class="nit-btn">Browse the courses</a></p>'
            . '<p>You can sign in any time at <a href="{loginurl}">{loginurl}</a>. If you did not create this '
            . 'account, please tell us at {supportemail}.</p>';
    }

    /**
     * Arabic registration body.
     *
     * @return string
     */
    private static function default_registration_ar(): string {
        return '<p>مرحبًا {firstname}،</p>'
            . '<p>اكتمل تسجيلك، وأصبح حسابك على <strong>{sitename}</strong> مُفعّلًا الآن. لم يتم شراء أي شيء '
            . 'بعد؛ هذه الرسالة تؤكد فقط بيانات الحساب الذي تسجّل الدخول به.</p>'
            . '<h3>ملخص الحساب</h3>'
            . self::tableopen()
            . self::row('الاسم بالكامل:', '{fullname}')
            . self::row('اسم المستخدم:', '{username}')
            . self::row('البريد الإلكتروني:', '{email}')
            . self::row('تاريخ التسجيل:', '{date}')
            . '</table>'
            . '<h3>خطواتك الثلاث الأولى</h3>'
            . '<ul>'
            . '<li>أكمل ملفك الشخصي حتى تصدر شهاداتك باسمك على النحو الذي تريده تمامًا.</li>'
            . '<li>تصفّح قائمة الدورات واختر البرامج التي تناسب أهدافك.</li>'
            . '<li>سجّل في دورة واحدة، أو اشترك في إحدى الباقات إذا كنت تنوي دراسة عدة دورات.</li>'
            . '</ul>'
            . '<p><a href="{browsecoursesurl}" class="nit-btn">تصفّح الدورات</a></p>'
            . '<p>يمكنك تسجيل الدخول في أي وقت عبر <a href="{loginurl}">{loginurl}</a>. وإذا لم تكن أنت من أنشأ '
            . 'هذا الحساب، فأبلغنا على {supportemail}.</p>';
    }
}
