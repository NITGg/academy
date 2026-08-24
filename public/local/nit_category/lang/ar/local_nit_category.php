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

$string['categoryimage'] = 'صورة التصنيف';
$string['categoryimage_help'] = 'الصورة التي تظهر لهذا التصنيف في صفحة التصنيف. صورة واحدة متوافقة مع الويب (JPG أو PNG أو GIF أو SVG أو WebP).

إذا تركت هذا الحقل فارغًا، سيتم استخدام أول صورة موجودة داخل وصف التصنيف، ثم صورة أقرب تصنيف أب، وأخيرًا شعار الموقع.';
$string['currentimage'] = 'المعروضة حاليًا';
$string['imagesaved'] = 'تم حفظ صورة التصنيف.';
$string['fallbackinfo'] = 'تصنيفات مودل لا تملك صورة خاصة بها، وهذه الصفحة تضيفها. صفحة التصنيف تختار أول متاح من: هذه الصورة المرفوعة، ثم أول صورة داخل وصف التصنيف، ثم صورة أقرب تصنيف أب، ثم شعار الموقع.';
$string['sourceuploaded'] = 'المصدر: مرفوعة من هذه الصفحة.';
$string['sourcedescription'] = 'المصدر: أول صورة داخل وصف هذا التصنيف. ارفع ملفًا بالأسفل لتجاوزها.';
$string['sourceinherited'] = 'المصدر: موروثة من تصنيف أب. ارفع ملفًا بالأسفل لإعطاء هذا التصنيف صورته الخاصة.';
$string['sourcelogo'] = 'المصدر: شعار الموقع (هذا التصنيف ليس له صورة خاصة).';

$string['privacy:metadata'] = 'إضافة تصنيفات NIT تخزّن صور التصنيفات فقط، ولا تخزّن أي بيانات شخصية.';
