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
 * Arabic strings for block_nit_offers.
 *
 * @package    block_nit_offers
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'شريط عروض NIT';
$string['nit_offers:addinstance'] = 'إضافة كتلة شريط عروض NIT';
$string['nit_offers:myaddinstance'] = 'إضافة كتلة شريط عروض NIT إلى لوحة التحكم';

// Bar content.
$string['offerflag'] = 'عرض';
$string['ends'] = 'ينتهي {$a}';
$string['starts'] = 'يبدأ {$a}';
$string['endstoday'] = 'ينتهي اليوم';
$string['endstomorrow'] = 'ينتهي غدًا';
$string['endsindays'] = 'ينتهي خلال {$a} أيام';
$string['seecourses'] = 'تصفّح الكورسات';
$string['appliesto'] = 'يشمل: {$a}';
$string['amountoff'] = 'خصم {$a}';
$string['dismiss'] = 'إغلاق هذا الإعلان';
$string['previousoffer'] = 'العرض السابق';
$string['nextoffer'] = 'العرض التالي';
$string['showoffer'] = 'عرض رقم';
$string['nooffers'] = 'لا توجد عروض سارية حاليًا.';
$string['editingnooffers'] = 'لا توجد عروض سارية حاليًا، لذلك لا يظهر هذا الشريط لبقية الزوّار. يمكنك إنشاء عرض من: إدارة الموقع &gt; الإضافات &gt; الإضافات المحلية &gt; NIT Commerce &gt; إدارة العروض.';

// Settings.
$string['showtitle'] = 'إظهار عنوان الكتلة';
$string['showtitle_help'] = 'مغلق افتراضيًا: الشريط مصمّم ليظهر بعرض الصفحة بدون رأس أو إطار للكتلة.';
$string['blocktitle'] = 'عنوان الكتلة';
$string['source'] = 'محتوى الشريط';
$string['source_help'] = 'العروض السارية: يبني الشريط نفسه من العروض الفعّالة في NIT Commerce، فلا يحتاج تعديلًا يدويًا. رسالة مخصّصة: تكتب نص الشريط بنفسك ويظل كما هو حتى تغيّره.';
$string['source_auto'] = 'العروض السارية (تلقائي)';
$string['source_custom'] = 'رسالة مخصّصة';
$string['customhtml'] = 'الرسالة المخصّصة';
$string['customhtml_help'] = 'العنوان الذي يظهر في الشريط. نص عادي أو HTML بسيط.';
$string['maxoffers'] = 'أقصى عدد عروض معروضة';
$string['maxoffers_help'] = 'عدد العروض التي يتنقّل بينها الشريط. تظهر الأحدث أولًا.';
$string['ctalabel'] = 'نص الرابط';
$string['ctalabel_help'] = 'اتركه فارغًا لاستخدام «تصفّح الكورسات».';
$string['ctaurl'] = 'عنوان الرابط';
$string['ctaurl_help'] = 'وجهة الرابط في نهاية الشريط. اتركه فارغًا للانتقال إلى دليل الكورسات. أمّا العرض المرتبط بكورس واحد فيوجّه دائمًا إلى ذلك الكورس.';
$string['rotate'] = 'التنقّل بين العروض تلقائيًا';
$string['rotate_help'] = 'عند وجود أكثر من عرض، ينتقل الشريط كل بضع ثوانٍ. ويتوقّف عند مرور المؤشر فوقه، ولا يتحرّك لمن يطلب تقليل الحركة.';
$string['dismissible'] = 'السماح للزائر بإغلاق الشريط';
$string['dismissible_help'] = 'يضيف زر إغلاق. يبقى الشريط مخفيًا في هذا المتصفّح حتى تتغيّر العروض السارية.';
$string['hidewhenempty'] = 'إخفاء الكتلة عند عدم وجود عروض';
$string['hidewhenempty_help'] = 'مستحسن. عند إيقافه يعرض الشريط ملاحظة قصيرة بدل الاختفاء.';
$string['appearance'] = 'المظهر';
$string['tone'] = 'لون الشريط';
$string['tone_help'] = 'لون الهوية الذي يصبغ الشريط.';
$string['tone_accent'] = 'اللون المميّز';
$string['tone_primary'] = 'اللون الأساسي';
$string['tone_success'] = 'لون النجاح';
$string['tone_warning'] = 'لون التنبيه';
