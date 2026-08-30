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
