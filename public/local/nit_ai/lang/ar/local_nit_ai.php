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
$string['sttmedia'] = 'فيديو للتفريغ (لدرس مرفوع قبل كده)';
$string['sttmedia_help'] = 'لدرس الفيديو بتاعه موجود على VdoCipher أصلًا: مبنقدرش ناخده من هناك، فارفع نفس الفيديو (أو الصوت بس) هنا عشان يتكتب منه النص. مش محتاجه لو رفعت الفيديو في قسم «الفيديو» فوق والاختيار ده متفعّل. الملف بيتمسح بعد التفريغ.';

// File, URL and H5P activities.
$string['autohint_resource'] = 'النص بيتكتب من ملف الفيديو أو الصوت بتاع النشاط نفسه — مش محتاج ترفع حاجة تانية. هيوصلك إشعار لما يبقى جاهز للمراجعة.';
$string['autohint_url'] = 'لو اللينك بيشاور على ملف فيديو مباشر (آخره ‎.mp4 مثلًا)، النص بيتكتب منه تلقائيًا. يوتيوب ومواقع الفيديو التانية مبنقدرش نحمّل منها: ارفع الفيديو (أو الصوت بس) تحت.';
$string['autohint_h5pactivity'] = 'النص بيتكتب من الفيديو اللي جوّه ملف الـ H5P — مش محتاج ترفع حاجة تانية. لو الـ H5P بيشغّل الفيديو من يوتيوب، ارفع الفيديو (أو الصوت بس) تحت.';
$string['sttmedia_external'] = 'فيديو للتفريغ (يوتيوب ومواقع تانية)';
$string['sttmedia_external_help'] = 'محتاجه بس لما مانقدرش ناخد الفيديو من النشاط نفسه: لينك يوتيوب أو أي موقع فيديو، أو H5P بيشغّل الفيديو من يوتيوب. ارفع نفس الفيديو، أو الصوت بس. الملف بيتمسح بعد التفريغ.';
$string['check_notmedia'] = 'ملف النشاط ده مش فيديو ولا صوت، فمفيش حاجة المساعد يتابعها.';
$string['check_display'] = 'النشاط ده مش بيعرض الفيديو في صفحته (المظهر ← طريقة العرض مش «تضمين»)، فمفيش مشغّل المساعد يقعد جنبه. خلّي طريقة العرض «تضمين».';
$string['stt_err_notmedia'] = 'ملف النشاط مش فيديو ولا صوت.';
$string['stt_err_urlneedsupload'] = 'اللينك ده مش ملف فيديو نقدر نحمّله (يوتيوب ومواقع الفيديو مش بتتحمّل). ارفع الفيديو من إعدادات النشاط، في خانة «فيديو للتفريغ».';
$string['stt_err_h5pnovideo'] = 'مفيش فيديو جوّه ملف الـ H5P ده.';
$string['stt_err_h5premote'] = 'الـ H5P ده بيشغّل الفيديو من يوتيوب أو موقع تاني، ومبنقدرش نحمّله. ارفع الفيديو من إعدادات النشاط، في خانة «فيديو للتفريغ».';
$string['stt_err_download'] = 'معرفناش نحمّل الفيديو من اللينك: {$a}';
$string['stt_err_plain'] = '{$a}';

// Where the transcript comes from (activity form).
$string['source'] = 'نص الفيديو';
$string['source_help'] = 'يتكتب من الفيديو: الفيديو اللي بترفعه في قسم «الفيديو» فوق بيعدّي على السيرفر بتاعنا، وبيتكتب منه نص بتوقيتات — من غير مزوّد ذكاء اصطناعي ومن غير تكلفة على كل فيديو. بياخد تقريبًا قد مدة الفيديو؛ بيوصلك إشعار لما يجهز، وتقدر تقراه وتصلّحه قبل ما تعتمده.

ارفع ملف نص: لو عندك أصلًا نص للفيديو ده بصيغة ‎.vtt أو ‎.srt أو ‎.json أو ‎.txt.';
$string['source_auto'] = 'يتكتب من الفيديو تلقائيًا (وإنت تراجعه وتعتمده)';
$string['source_file'] = 'أرفع ملف النص بنفسي';
$string['autohint'] = 'ارفع الفيديو في قسم «الفيديو» فوق: بيترفع مرة واحدة على سيرفرنا، واحنا بنرفعه على VdoCipher وبنكتب منه النص. هيوصلك إشعار لما النص يبقى جاهز للمراجعة.';
$string['currenttranscript'] = 'النص الحالي';
$string['viewedit'] = 'عرض وتعديل النص';
$string['summary'] = '{$a->segments} سطر، لحد {$a->last} — {$a->source}، {$a->status}';
$string['summary_notimes'] = 'نص من غير توقيتات — {$a->source}، {$a->status}';
$string['summary_auto'] = 'اتكتب من الفيديو';
$string['summary_file'] = 'ملف مرفوع';
$string['summary_approved'] = 'معتمد';
$string['summary_notapproved'] = 'مستني اعتمادك';

// Upload through this server (js/upload.js, upload.php).
$string['up_route'] = 'المساعد الذكي هيكتب النص من الفيديو ده، فهيترفع على سيرفرنا الأول ومنه يتبعت لـ VdoCipher.';
$string['up_starting'] = 'جاري تجهيز الرفع…';
$string['up_progress'] = 'جاري الرفع… {$a}%';
$string['up_finishing'] = 'جاري إنهاء الرفع وإنشاء الفيديو على VdoCipher…';
$string['up_done'] = 'اترفع ✓ — رقم الفيديو اتحط. اضغط حفظ: الفيديو هيتبعت لـ VdoCipher والنص هيتكتب منه.';
$string['up_failed'] = 'الرفع فشل: {$a}';
$string['up_retrying'] = 'مشكلة في الاتصال — بنعيد الجزء ده ({$a})…';
$string['upload_err_noprovider'] = 'VdoCipher مش متظبط على الموقع ده، فمش هينفع نرفع الفيديو.';
$string['upload_err_provider'] = 'VdoCipher مقبلش الفيديو الجديد: {$a}';
$string['upload_err_size'] = 'لازم الفيديو يكون أصغر من {$a}.';
$string['upload_err_state'] = 'الرفع ده خلص بالفعل.';
$string['upload_err_offset'] = 'جزء وصل بترتيب غلط؛ بنكمّل من البايت {$a}.';
$string['upload_err_write'] = 'جزء من الفيديو متحفظش على السيرفر.';
$string['upload_err_notfound'] = 'الرفع ده مبقاش موجود. اختار الفيديو تاني.';
$string['task_uploadpush'] = 'إرسال فيديو درس مرفوع لـ VdoCipher';
$string['task_uploadcleanup'] = 'مسح فيديوهات الدروس المرفوعة اللي محدش هيستخدمها';

// Transcript editor (transcript.php).
$string['editortitle'] = 'نص الفيديو';
$string['editorintro'] = 'كل سطر ظاهر قدام توقيته في الفيديو. صلّح أي غلطة في مكانها وبعدين احفظ. أي تعديل محتاج اعتماد تاني قبل ما الطلبة يشوفوه — «حفظ واعتماد» بيعمل الاتنين.';
$string['editor_time'] = 'التوقيت';
$string['editor_text'] = 'السطر عند';
$string['editor_filter'] = 'دوّر على كلمة في النص';
$string['editor_save'] = 'حفظ';
$string['editor_saveapprove'] = 'حفظ واعتماد';
$string['editor_saved'] = 'اتحفظ. اعتمد النص لما تبقى راضي عنه.';
$string['editor_savedapproved'] = 'اتحفظ واتعتمد — الطلبة يقدروا يستخدموا المساعد.';
$string['editor_empty'] = 'النص مينفعش يكون فاضي.';
$string['editor_back'] = 'رجوع للدرس';

// Transcription progress.
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
