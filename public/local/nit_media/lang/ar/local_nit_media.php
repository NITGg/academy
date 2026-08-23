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
 * Arabic strings for local_nit_media.
 *
 * Admin setting descriptions are rendered as MARKDOWN, so these are written in
 * markdown, not HTML, and no continuation line may be indented — four leading
 * spaces would turn the paragraph into a code block.
 *
 * @package    local_nit_media
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'وسائط الموقع';

$string['herovideoheading'] = 'فيديو القسم الرئيسي في الصفحة الرئيسية';

$string['herovideoheading_desc'] =
    'الفيديو الذي يعمل عند الضغط على "شاهد كيف يعمل" في القسم الرئيسي بالصفحة الرئيسية.'
    . "\n\n"
    . 'القسم الرئيسي عبارة عن بلوك HTML عادي، فلا يمكنه معرفة اسم الملف. هو يشير دائمًا إلى عنوان ثابت '
    . 'واحد، وهذه الصفحة هي التي تحدد ما يقدّمه ذلك العنوان: {$a}'
    . "\n\n"
    . 'ارفع ملفًا لنشره، أو احذفه لإزالة الفيديو، أو نفّذ الاثنين معًا لاستبداله. '
    . 'لا يحتاج البلوك إلى أي تعديل في كل الحالات.';

$string['herovideo'] = 'ملف الفيديو';

$string['herovideo_desc'] =
    'استخدم **MP4 بترميز H.264 للفيديو و AAC للصوت**. هذه هي التركيبة التي تستطيع كل المتصفحات تشغيلها. '
    . 'ملف ‎.mov‎، أو MP4 بترميز H.265/HEVC، سيُقبل عند الرفع لكنه لن يعمل في كروم، وسيذكر المشغّل أن '
    . 'الصيغة غير مدعومة.'
    . "\n\n"
    . 'استخدم `-movflags +faststart` عند الترميز حتى يبدأ التشغيل قبل اكتمال تنزيل الملف بالكامل.'
    . "\n\n"
    . 'الملف يُقدَّم عبر PHP وليس عبر شبكة توزيع محتوى، لذا اجعله قصيرًا. أما المقاطع الطويلة فالأفضل '
    . 'رفعها على يوتيوب أو Vimeo وتضمينها في البلوك.';

$string['privacy:metadata'] =
    'إضافة وسائط الموقع تخزّن فقط الملفات التي يرفعها المشرف لعرضها في الموقع، ولا تخزّن أي بيانات شخصية.';

$string['diagnostics'] = 'تشخيص فيديو القسم الرئيسي';

$string['diagnostics_desc'] =
    'لست متأكدًا هل المشكلة في الملف أم في الترميز؟ افتح '
    . '{$a} '
    . 'بحساب مشرف الموقع. ستعرض ما هو مخزَّن فعلًا على الخادم — اسم الملف وحجمه ونوعه — '
    . 'دون تنزيل الفيديو.';
