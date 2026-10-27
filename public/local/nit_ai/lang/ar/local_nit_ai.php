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
 * Arabic strings for local_nit_ai.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'مساعد الفيديو الذكي';

// Capabilities.
$string['nit_ai:use'] = 'سؤال مساعد الفيديو';
$string['nit_ai:manage'] = 'رفع واعتماد نصوص الفيديو';

// Activity form.
$string['formheader'] = 'المساعد الذكي';
$string['enabled'] = 'تفعيل المساعد الذكي لهذا الفيديو';
$string['enabled_help'] = 'الطالب هيلاقي لوحة محادثة جنب المشغّل يسأل منها عن الدرس. مش هتظهر غير بعد رفع نص الفيديو واعتماده.';
$string['transcriptfile'] = 'ملف النص (Transcript)';
$string['transcriptfile_help'] = 'ملف ‎.vtt أو ‎.srt أو ‎.json أو ‎.txt لنص هذا الفيديو. يُفضّل بشدة أن يكون بتوقيتات: بيها المساعد يعرف الطالب واقف عند أنهي لحظة ويقدر يرجّعه للجزء اللي اتشرحت فيه الفكرة. رفع ملف جديد بيلغي الاعتماد.';
$string['hasonscreen'] = 'النص يشمل الكلام المكتوب على الشاشة (كود، سلايدات)';
$string['hasonscreen_help'] = 'علّم هنا لو النص بيغطّي اللي مكتوب على الشاشة مش الكلام المنطوق بس. لو صوت فقط، المساعد هيقول للطالب إنه سامع الدرس بس مش شايف الشاشة بدل ما يخمّن.';

// Review panel.
$string['reviewtitle'] = 'المساعد الذكي — مراجعة النص';
$string['reviewintro'] = 'ده اللي قريناه من الملف. راجعه واعتمده.';
$string['detectedformat'] = 'الصيغة';
$string['detectedsegments'] = 'عدد المقاطع';
$string['detectedtimestamps'] = 'التوقيتات';
$string['detectedlanguage'] = 'اللغة';
$string['detectedlast'] = 'آخر توقيت';
$string['videolength'] = 'طول الفيديو';
$string['lengthunknown'] = 'لسه مش متسجّل';
$string['lengthpassed'] = 'النص بيغطّي الفيديو كله';
$string['lengthfailed'] = 'النص مش متطابق مع طول الفيديو';
$string['lengthskipped'] = 'فحص الطول مأجّل';
$string['approve'] = 'اعتماد وتفعيل';
$string['approved'] = 'معتمد — الطلبة يقدروا يستخدموا المساعد';
$string['notapproved'] = 'في انتظار اعتمادك — الطلبة لسه مش شايفين المساعد';
$string['approvedblocked'] = 'معتمد، بس المساعد لسه مش شغّال — شوف اللي بيمنعه تحت.';
$string['notenabled'] = 'المساعد متوقّف لهذا النشاط من إعداداته.';
$string['notranscript'] = 'مفيش نص مرفوع. من إعدادات النشاط ارفع ملف النص، أو ارفع فيديو الدرس ويتفرّغ تلقائيًا، عشان تشغّل المساعد.';
$string['problemsfound'] = 'راجع دول قبل الاعتماد';
$string['langar'] = 'عربي';
$string['langen'] = 'إنجليزي';
$string['langmixed'] = 'عربي وإنجليزي';
$string['langunknown'] = 'مش واضحة';

// Checks.
$string['check_stale'] = 'الفيديو اتغيّر بعد رفع النص ده. المساعد متوقّف لحد ما يترفع نص مطابق للفيديو الجديد.';
$string['check_notimestamps'] = 'مفيش توقيتات في الملف. المساعد هيفضل يجاوب، بس مش هيعرف الطالب واقف فين ولا هيقدر يرجّعه للحظة معيّنة.';
$string['check_empty'] = 'مفيش نص مقروء في الملف.';
$string['check_noprovider'] = 'مفيش مزوّد ذكاء اصطناعي معدّ على الموقع لسه، فالمساعد مش هيقدر يجاوب. الأدمن لازم يظبط واحد من إدارة الموقع > الذكاء الاصطناعي.';
$string['check_placementoff'] = 'مساعد الفيديو الذكي متوقّف على مستوى الموقع كله. شغّله من إدارة الموقع > الذكاء الاصطناعي > AI placements.';
$string['check_contextoff'] = 'أدوات الذكاء الاصطناعي متوقّفة في الكورس ده أو في النشاط ده، فالمساعد مش هيشتغل هنا.';
$string['check_lengthmismatch'] = 'النص بينتهي عند {$a->transcript} والفيديو طوله {$a->video}. ده غالباً معناه إن النص ناقص، أو إنه بتاع فيديو تاني.';

// Chat.
$string['chattitle'] = 'اسأل عن الدرس';
$string['chatintro'] = 'اسأل أي حاجة عن الفيديو ده. بجاوب من اللي اتقال فيه فعلاً.';
$string['chatplaceholder'] = 'اكتب سؤالك…';
$string['chatsend'] = 'إرسال';
$string['chatthinking'] = 'بفكّر…';
$string['chatopen'] = 'افتح المساعد';
$string['chatclose'] = 'اقفل المساعد';
$string['chatdisclaimer'] = 'إجابات مولّدة بالذكاء الاصطناعي. المحادثة دي مش بتتحفظ — بتبدأ من أول كل ما تفتح الصفحة.';
$string['jumpto'] = 'روح للدقيقة {$a}';

// Errors.
$string['err_emptyquestion'] = 'اكتب سؤالك الأول.';
$string['err_aifailed'] = 'المساعد مقدرش يجاوب دلوقتي. جرّب تاني.';
$string['err_unavailable'] = 'المساعد مش متاح للفيديو ده.';

$string['privacy:metadata'] = 'مساعد الفيديو الذكي مبيخزّنش المحادثات. الأسئلة بتتبعت لمزوّد الذكاء الاصطناعي المعدّ للموقع عشان يجاوب عليها، ومش بتتحفظ بعدها.';

// Quiz generator.
$string['nit_ai:generatequiz'] = 'إنشاء اختبار من نص الفيديو';
$string['quizgen_formlabel'] = 'اختبار من الفيديو ده';
$string['quizgen_button'] = 'إنشاء اختبار بالذكاء الاصطناعي';
$string['quizgen_buttonnote'] = 'بيفتح في تبويب جديد وبيشتغل على النص المحفوظ. لو غيّرت الملف فوق دلوقتي، احفظ الفورم الأول.';
$string['quizgen_paneltitle'] = 'اختبار بالذكاء الاصطناعي';
$string['quizgen_lastquiz'] = 'آخر اختبار اتعمل:';
$string['quizgen_lastquizstale'] = 'الاختبار ده اتكتب من نسخة أقدم من نص الفيديو. لو الفيديو اتغيّر، اعمل واحد جديد.';
$string['quizgen_title'] = 'إنشاء اختبار من الفيديو ده';
$string['quizgen_intro'] = 'نص فيديو «{$a->video}» مدته {$a->length} واتقرا على {$a->parts} جزء. كل جزء بيشرح حاجة هياخد سؤال على الأقل، يعني عدد الأسئلة بيتحدد من الفيديو نفسه مش من رقم بتحطه.';
$string['quizgen_quizname'] = 'اسم الاختبار';
$string['quizgen_quizsuffix'] = 'اختبار';
$string['quizgen_language'] = 'لغة الأسئلة';
$string['quizgen_language_help'] = 'الافتراضي هو اللغة اللي اتكشفت في نص الفيديو. المصطلحات التقنية بتفضل بالإنجليزي زي ما الفيديو بيقولها.';
$string['quizgen_types'] = 'أنواع الأسئلة';
$string['quizgen_types_help'] = 'الأنواع اللي بتتصحّح لوحدها بس. الاختيار من متعدد بيشيل الأسئلة الصعبة، وصح/خطأ سريع ومناسب للمستوى السهل.';
$string['quizgen_type_multichoice'] = 'اختيار من متعدد (إجابة واحدة صحيحة)';
$string['quizgen_type_truefalse'] = 'صح أو خطأ';
$string['quizgen_startbutton'] = 'ابدأ التوليد';
$string['quizgen_level_easy'] = 'سهل';
$string['quizgen_level_medium'] = 'متوسط';
$string['quizgen_level_hard'] = 'صعب';
$string['quizgen_part'] = 'الجزء {$a}';
$string['quizgen_working'] = 'بنكتب الأسئلة من نص الفيديو';
$string['quizgen_workingnote'] = 'سيب الصفحة مفتوحة. كل جزء بيتحفظ أول ما يتكتب، فلو المزوّد بطيء هتخسر وقت مش شغل.';
$string['quizgen_slice'] = 'الجزء {a} من {b}';
$string['quizgen_fillinggaps'] = 'بنرجع للأجزاء اللي مااتسألش عنها حاجة';
$string['quizgen_questionssofar'] = 'الأسئلة لحد دلوقتي';
$string['quizgen_coverage'] = 'المغطّى من الفيديو';
$string['quizgen_atlimit'] = 'الجولة دي وصلت للحد المسموح بيه في الموقع، فالتوليد وقف هنا.';
$string['quizgen_reviewintro'] = 'لسه مفيش حاجة هنا بقت سؤال. شيل علامة أي سؤال مش عايزه، وبعدين اعمل الاختبار.';
$string['quizgen_coveragesummary'] = '{$a->total} سؤال، بيغطّوا {$a->percent}% من اللي ينفع يتسأل عنه في الفيديو.';
$string['quizgen_gapstitle'] = 'الأجزاء دي مااتكتبش عنها سؤال';
$string['quizgen_skippedtitle'] = 'أجزاء اتقال إن مفيهاش حاجة تتسأل';
$string['quizgen_keep'] = 'خليه';
$string['quizgen_marks'] = 'درجة للسؤال';
$string['quizgen_createbutton'] = 'اعمل الاختبار';
$string['quizgen_createdhidden'] = 'الاختبار بيتعمل مخفي، في نفس قسم الفيديو، وكل مستوى في صفحة. اظهره لما تطمن عليه.';
$string['quizgen_created'] = 'الاختبار اتعمل بـ {$a} سؤال، ومخفي لحد ما تظهره.';
$string['quizgen_categoryinfo'] = 'اتكتبت بمولّد الاختبارات الذكي من نص الفيديو ده.';
$string['quizgen_quizintro'] = 'أسئلة على الفيديو اللي في القسم ده.';
$string['quizgen_seenat'] = 'الكلام ده في الفيديو عند {$a}.';

// Quiz generator checks and errors.
$string['quizgen_check_notapproved'] = 'نص الفيديو لسه مااتعتمدش. اعتمده الأول — الأسئلة مش هتبقى أحسن من النص اللي اتكتبت منه.';
$string['quizgen_check_placementoff'] = 'مولّد الاختبارات الذكي مقفول على مستوى الموقع كله. شغّله من إدارة الموقع > الذكاء الاصطناعي > أماكن الذكاء الاصطناعي.';
$string['quizgen_err_unreadable'] = 'رد الذكاء الاصطناعي جه بصيغة مقدرناش نقراها. الجزء ده مااتضافش منه حاجة.';
$string['quizgen_err_norun'] = 'الجولة دي مابقتش موجودة. ابدأ من أول وجديد من صفحة النشاط.';
$string['quizgen_err_alreadybuilt'] = 'فيه اختبار اتعمل من الجولة دي خلاص.';
$string['quizgen_err_notypes'] = 'اختار نوع سؤال واحد على الأقل.';
$string['quizgen_err_nothingtobuild'] = 'مفيش أسئلة نعمل منها اختبار.';
$string['quizgen_err_nothingkept'] = 'شيلت العلامة من كل الأسئلة، فمفيش حاجة تتعمل.';
$string['quizgen_err_nobank'] = 'الكورس ده مفيهوش بنك أسئلة نكتب فيه الأسئلة.';
$string['quizgen_err_importfailed'] = 'مقدرناش نكتب الأسئلة في بنك الأسئلة. مااتعملش أي حاجة.';

// Site settings.
$string['setting_quizgenheading'] = 'مولّد الاختبارات الذكي';
$string['setting_quizgenheading_desc'] = 'حدود إنشاء اختبار من نص الفيديو. عدد الأسئلة المفروض يتبع كمية اللي الفيديو بيشرحه؛ الأرقام دي حماية للتكلفة مش أهداف نوصلها.';
$string['setting_maxquestions'] = 'أقصى عدد أسئلة في الجولة';
$string['setting_maxquestions_desc'] = 'التوليد بيقف لما الجولة توصل للعدد ده، مهما كان الباقي من الفيديو.';
$string['setting_maxgappasses'] = 'تمريرات سد الفجوات';
$string['setting_maxgappasses_desc'] = 'بعد التمريرة الأولى، كام طلب إضافي مسموح نصرفه عشان نرجع للأجزاء اللي مااتسألش عنها.';
$string['quizgen_span'] = 'اتقرا من نص مقسوم لـ {$a->parts} جزء وبينتهي عند {$a->end}، لفيديو مدته {$a->video}.';

// Automatic transcripts (our own Whisper service).
$string['sttmedia'] = 'فيديو الدرس للتفريغ التلقائي';
$string['sttmedia_help'] = 'مفيش ملف نص؟ ارفع فيديو الدرس (أو الصوت بتاعه) هنا والموقع هيكتب منه نص بتوقيتات على السيرفر بتاعنا — من غير مزوّد ذكاء اصطناعي ومن غير تكلفة على كل فيديو. بياخد تقريبًا قد مدة الفيديو، وممكن يكون فيه فيديوهات قبله في الطابور. لما يخلص، النص بيحل محل الملف اللي فوق، وبيوصلك إشعار، ويستنى اعتمادك زي أي نص مرفوع. الفيديو بيتمسح بعد التفريغ.';
$string['stt_status_pending'] = 'الفيديو وصل — جاري إرساله للتفريغ.';
$string['stt_status_retrying'] = 'لسه مقدرناش نوصل لخدمة التفريغ؛ هنحاول تاني كمان شوية. ({$a})';
$string['stt_status_queued'] = 'مستني دوره في التفريغ — رقم {$a} في الطابور.';
$string['stt_status_running'] = 'جاري تفريغ الفيديو… خلص {$a}%.';
$string['stt_status_failed'] = 'التفريغ التلقائي فشل: {$a} ارفع الفيديو تاني، أو ارفع ملف نص.';
$string['stt_err_service'] = 'خدمة التفريغ مقبلتش الطلب: {$a}';
$string['stt_err_nomedia'] = 'الفيديو المرفوع مبقاش موجود عشان يتبعت.';
$string['stt_err_lost'] = 'خدمة التفريغ ضيّعت الفيديو ده (غالبًا اتعملها إعادة تشغيل).';
$string['stt_err_unreadable'] = 'الملف المرفوع مش فيديو أو صوت نقدر نقراه.';
$string['stt_msg_done_subject'] = 'النص جاهز للمراجعة: {$a->name}';
$string['stt_msg_done_body'] = 'نص "{$a->name}" اتكتب من الفيديو. افتح النشاط عشان تراجعه وتعتمده.';
$string['stt_msg_failed_subject'] = 'معرفناش نطلّع النص: {$a->name}';
$string['stt_msg_failed_body'] = 'معرفناش نكتب نص "{$a->name}" من الفيديو: {$a->error}';
$string['messageprovider:transcriptready'] = 'نص اتكتب من فيديو درس وجاهز للمراجعة';
$string['task_sttsubmit'] = 'إرسال فيديو درس لخدمة التفريغ';
$string['task_sttpoll'] = 'استلام النصوص الجاهزة من خدمة التفريغ';
$string['setting_sttheading'] = 'التفريغ التلقائي';
$string['setting_sttheading_desc'] = 'تحويل فيديوهات الدروس لنصوص بـ Whisper، شغّال في container لوحده على السيرفر ده (services/whisper في الكود). سيب العنوان أو التوكن فاضي عشان تخفي رفع الفيديو عن المدرسين.';
$string['setting_stturl'] = 'عنوان خدمة التفريغ';
$string['setting_stturl_desc'] = 'المكان اللي Moodle بيوصل منه للـ container، عادةً اسم الخدمة في Docker زي http://whisper:9000. متفتحش البورت ده على الإنترنت أبدًا.';
$string['setting_stttoken'] = 'توكن خدمة التفريغ';
$string['setting_stttoken_desc'] = 'نفس قيمة WHISPER_TOKEN في إعدادات الـ container.';
