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
 * Arabic strings for local_nit_category.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'تصنيفات NIT';

$string['categorymedia'] = 'صورة وأيقونة التصنيف';
$string['mediasaved'] = 'تم حفظ صورة وأيقونة التصنيف.';

$string['categoryimage'] = 'صورة التصنيف';
$string['categoryimagefile'] = 'ملف الصورة';
$string['categoryimage_help'] = 'الصورة التي تظهر لهذا التصنيف في صفحة التصنيف. صورة واحدة متوافقة مع الويب (JPG أو PNG أو GIF أو SVG أو WebP).

إذا تركت هذا الحقل فارغًا، سيتم استخدام أول صورة موجودة داخل وصف التصنيف، ثم صورة أقرب تصنيف أب، وأخيرًا شعار الموقع.';
$string['currentimage'] = 'المعروضة حاليًا';
$string['fallbackinfo'] = 'تصنيفات مودل لا تملك صورة خاصة بها، وهذه الصفحة تضيفها. صفحة التصنيف تختار أول متاح من: هذه الصورة المرفوعة، ثم أول صورة داخل وصف التصنيف، ثم صورة أقرب تصنيف أب، ثم شعار الموقع.';
$string['sourceuploaded'] = 'المصدر: مرفوعة من هذه الصفحة.';
$string['sourcedescription'] = 'المصدر: أول صورة داخل وصف هذا التصنيف. ارفع ملفًا بالأسفل لتجاوزها.';
$string['sourceinherited'] = 'المصدر: موروثة من تصنيف أب. ارفع ملفًا بالأسفل لإعطاء هذا التصنيف صورته الخاصة.';
$string['sourcelogo'] = 'المصدر: شعار الموقع (هذا التصنيف ليس له صورة خاصة).';

// الأيقونة: الرمز الصغير الذي يُطبع بجوار اسم التصنيف.
$string['categoryicon'] = 'أيقونة التصنيف';
$string['categoryiconemoji'] = 'أيقونة إيموجي';
$string['categoryiconemoji_help'] = 'إيموجي واحد يظهر بجوار اسم هذا التصنيف — في شارة الصفحة وأزرار الفلترة وعناوين الأقسام. الصق إيموجي واحدًا، مثل 💻 أو 🎨.

اتركه فارغًا لعدم عرض أيقونة. وإذا رفعت ملف أيقونة بالأسفل أيضًا، فسيتم استخدام الملف بدلًا منه.';
$string['categoryiconfile'] = 'صورة الأيقونة';
$string['categoryiconfile_help'] = 'صورة صغيرة تُستخدم بدلًا من الإيموجي — يُفضّل أن تكون مربّعة وشفافة بصيغة PNG أو SVG، لأنها تُعرض بحجم 32 بكسل تقريبًا بجوار اسم التصنيف.

أمّا الصورة الكبيرة التي تظهر على الكروت وفي رأس صفحة التصنيف فاستخدم لها حقل صورة التصنيف بالأعلى.';
$string['currenticon'] = 'الأيقونة بجوار الاسم';
$string['noicon'] = 'لا توجد أيقونة لهذا التصنيف.';

$string['privacy:metadata'] = 'إضافة تصنيفات NIT تخزّن صور التصنيفات فقط، ولا تخزّن أي بيانات شخصية.';

// ── دليل الكورسات (catalogue.php) ───────────────────────────────────────────────
$string['catalogue'] = 'دليل الكورسات';
$string['breadcrumb'] = 'مسار التصفّح';
$string['searchcourses'] = 'ابحث في الكورسات';
$string['coursesinscope'] = '{$a} كورس';
$string['coursesfound'] = '{$a} كورس';
$string['filters'] = 'التصفية';
$string['clearall'] = 'مسح الكل';
$string['applyfilters'] = 'تطبيق التصفية';
$string['showall'] = 'عرض الكل ({$a})';
$string['showfewer'] = 'عرض أقل';
$string['from'] = 'من';
$string['to'] = 'إلى';
$string['price'] = 'السعر';
$string['freeonly'] = 'الكورسات المجانية فقط';
$string['sortby'] = 'ترتيب';
$string['sortpopular'] = 'الأكثر رواجًا';
$string['sortnewest'] = 'الأحدث';
$string['sortname'] = 'الاسم (أ–ي)';
$string['sortpricelow'] = 'السعر: من الأقل للأعلى';
$string['sortpricehigh'] = 'السعر: من الأعلى للأقل';
$string['nomatches'] = 'لا توجد كورسات مطابقة لهذه التصفية';
$string['nomatcheshint'] = 'جرّب إزالة أحد عوامل التصفية أو البحث بكلمة أعم.';
$string['pagination'] = 'صفحات النتائج';
$string['perpage'] = 'عدد الكورسات في الصفحة:';

// نصوص البطاقة — نفس ألفاظ صفحة التصنيف حتى يقرأ الكورس بالطريقة نفسها في الصفحتين.
$string['enrolled'] = 'مُسجَّل';
$string['purchased'] = 'تم الشراء';
$string['insubscription'] = 'ضمن اشتراكك';
$string['free'] = 'مجانًا';
$string['coursedetails'] = 'تفاصيل الكورس';
$string['enrol'] = 'التحاق';
$string['buynow'] = 'اشترِ الآن';
$string['defaultcurrency'] = 'ج.م';

// الإعدادات.
$string['catalogueheading'] = 'دليل الكورسات';
$string['cataloguedesc'] = 'يبني دليل الكورسات في /local/nit_category/catalogue.php عوامل التصفية من الحقول المخصّصة للكورسات الموجودة فعلًا في الموقع: حقول الاختيار والنص القصير تصبح قوائم اختيار متعدّد، وحقل الاختيار الثنائي يصبح مفتاحًا واحدًا، والحقل الرقمي يصبح نطاقًا من/إلى. ولا يظهر عامل التصفية إلا إذا كانت الكورسات المعروضة تحمل ذلك الحقل بالفعل، فلا حاجة لضبط أي شيء هنا حتى تعمل الصفحة.';
$string['excludefilterfields'] = 'حقول لا تُستخدم في التصفية أبدًا';
$string['excludefilterfields_desc'] = 'الأسماء المختصرة للحقول المخصّصة، مفصولة بفواصل، التي يتجاهلها الدليل حتى لو كان نوعها صالحًا للتصفية. اتركه فارغًا لعرض كل الحقول المناسبة.';
$string['onecourse'] = 'كورس واحد';

// ── التصنيفات ─────────────────────────────────────────────────────────────
// ما بقي بعد إزالة صفحة كل التصنيفات: مفتاح التصنيفات الفرعية وعنوان العمق اللذان
// ما زالت لوحة تصفية الدليل ترسمهما، وترتيب قراءة قائمة التصنيفات.
$string['includesubcategories'] = 'إظهار التصنيفات الفرعية';
$string['categorydepth'] = 'مدى ما يُعرض من الشجرة';
$string['sortmostcourses'] = 'الأكثر كورسات';

// Home-page "My courses" card (theme_nit home_my_course_block).
$string['homelesson'] = 'الدرس {$a->num} : {$a->name}';
$string['mycourses'] = 'كورساتي';
$string['mycoursesunavailable'] = 'تعذّر عرض بطاقات الكورسات: ملف البلوك الخاص بقالب theme_nit غير موجود أو غير قابل للقراءة.';

// ── البحث في الموقع (SRS 4.22) ──────────────────────────────────────────────────
// عنصر بحث واحد في الشريط العلوي يبحث في الكورسات والتصنيفات معًا، والنتائج مجمَّعة
// حسب نوعها ومعها العدد.
$string['searchtitle'] = 'البحث';
$string['searchplaceholder'] = 'ابحث في الكورسات والتصنيفات';
$string['searchsite'] = 'ابحث في الموقع';
$string['searchopen'] = 'فتح البحث';
$string['searchclose'] = 'إغلاق البحث';
$string['searchhint'] = 'ابحث في الأكاديمية كلها: أسماء الكورسات والمجالات وما يغطيه كل كورس.';
$string['searchtooshort'] = 'اكتب حرفين على الأقل للبحث.';
$string['searchresults'] = '{$a->count} نتيجة لـ «{$a->query}»';
$string['searchoneresult'] = 'نتيجة واحدة لـ «{$a}»';
$string['searchgroupcourses'] = 'الكورسات';
$string['searchgroupcategories'] = 'التصنيفات';
$string['searchnothing'] = 'لا توجد نتائج لـ «{$a}»';
$string['searchnothinghint'] = 'راجع الإملاء، أو جرّب كلمة واحدة أعمّ، أو تصفّح الدليل.';
$string['searchseeall'] = 'اعرض كل النتائج ({$a})';
$string['searchseeallone'] = 'اعرض النتيجة';
$string['searchmoreresults'] = 'اعرض الـ {$a} الأخرى';
$string['searchrefine'] = 'ضيِّق النتائج:';
$string['searchrefinecourses'] = 'تصفية هذه الكورسات';
$string['searchsearching'] = 'جارٍ البحث…';

// ── تقرير عمليات البحث بلا نتائج (AC-4.22.4) ────────────────────────────────────
$string['searchlog'] = 'عمليات بحث بلا نتائج';
$string['searchlogintro'] = 'كل كلمة بحث عنها المتدربون ولم تُرجع أي كورس أو تصنيف. صيغ الكتابة المختلفة للكلمة الواحدة تُحتسب كلمةً واحدة. لا شيء هنا يدل على هوية الباحث.';
$string['searchlogempty'] = 'لا شيء بعد: كل عمليات البحث حتى الآن وجدت نتائج.';
$string['searchlogsummary'] = '{$a->terms} كلمة، بحث عنها {$a->searches} مرة إجمالًا.';
$string['searchlogterm'] = 'كلمة البحث';
$string['searchloghits'] = 'عدد مرات البحث';
$string['searchlogfirst'] = 'أول بحث';
$string['searchloglast'] = 'آخر بحث';
$string['searchlogsorthits'] = 'الأكثر بحثًا';
$string['searchlogsortrecent'] = 'الأحدث';
$string['searchlogsortterm'] = 'أبجديًا';
$string['searchlogdeleted'] = 'تم حذف الكلمة من التقرير.';
$string['searchlogpurge'] = 'إفراغ التقرير';
$string['searchlogpurgeconfirm'] = 'هل تريد حذف كل الكلمات من التقرير؟ هذا هو السجل الوحيد لما لم يجده المتدربون.';
$string['searchlogpurged'] = 'أصبح التقرير فارغًا.';

// ── الخصوصية ────────────────────────────────────────────────────────────────────
$string['privacy:metadata'] = 'صفحات الدليل لا تخزّن أي شيء عن قارئها. وسجل عمليات البحث بلا نتائج يحتفظ بالكلمة وعدد مرات البحث فقط، وليس فيه عمود للمستخدم.';

// ── لوحة التصفية (SRS §4.8: ستة عوامل بالضبط، بهذا الترتيب) ─────────────────────
// عناوين اللوحة نفسها، تُستخدم بدلًا من اسم الحقل المخصّص حتى يظهر حقل اسمه
// "Total Number of Hours" تحت العنوان الذي يطلبه التصميم.
$string['filtercategory'] = 'التصنيف';
$string['filterlevel'] = 'المستوى';
$string['filterprice'] = 'نطاق السعر';
$string['filterlanguage'] = 'اللغة';
$string['filterduration'] = 'المدة';
$string['filtercertificate'] = 'يمنح شهادة';
$string['durationshort'] = 'أقل من 10 ساعات';
$string['durationmedium'] = '10 – 25 ساعة';
$string['durationlong'] = 'أكثر من 25 ساعة';
$string['hoursshort'] = '{$a} س';
$string['pricefrom'] = 'أقل سعر';
$string['priceto'] = 'أعلى سعر';

// الإعدادات: أي حقل كورس يجيب عن أي عامل تصفية.
$string['filterfieldsheading'] = 'حقول التصفية';
$string['filterfieldsdesc'] = 'يعرض الدليل عوامل التصفية الستة في SRS §4.8 بالضبط — التصنيف والمستوى والسعر واللغة والمدة والشهادة. التصنيف والسعر مدمجان؛ والأربعة الباقية تقرأ حقلًا مخصّصًا للكورس تُسمّيه بالأسفل. اترك الخانة فارغة لاستخدام الاسم المختصر الافتراضي، وإذا سمّيت حقلًا غير موجود فلن يظهر عامل التصفية هذا في اللوحة أصلًا.';
$string['filterfield_level'] = 'حقل المستوى';
$string['filterfield_level_desc'] = 'الاسم المختصر لحقل الكورس المخصّص الذي يحمل المستوى. حقل الاختيار يعطي قائمة محكومة، وهو ما يتطلّبه AC-4.8.5. الافتراضي: level';
$string['filterfield_language'] = 'حقل اللغة';
$string['filterfield_language_desc'] = 'الاسم المختصر لحقل الكورس المخصّص الذي يحمل لغة التقديم. الافتراضي: language';
$string['filterfield_duration'] = 'حقل المدة';
$string['filterfield_duration_desc'] = 'الاسم المختصر للحقل الرقمي الذي يحمل طول الكورس بالساعات. يُعرض كثلاث فئات — أقل من 10، ومن 10 إلى 25، وأكثر من 25 — بدلًا من خانتَي من/إلى. الافتراضي: total_number_of_hours';
$string['filterfield_certificate'] = 'حقل الشهادة';
$string['filterfield_certificate_desc'] = 'الاسم المختصر لحقل الاختيار الثنائي الذي يحدّد أن الكورس يمنح شهادة. الافتراضي: certificate';
