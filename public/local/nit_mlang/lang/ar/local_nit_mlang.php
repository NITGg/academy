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
 * Arabic strings for local_nit_mlang.
 *
 * @package    local_nit_mlang
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'الحقول متعددة اللغات';
$string['privacy:metadata'] = 'لا يخزّن مكوّن الحقول متعددة اللغات أي بيانات شخصية.';

$string['nit_mlang:edit'] = 'تعبئة الحقول القابلة للترجمة لكل لغة على حدة';

$string['status'] = 'الحالة';
$string['statuslangs'] = 'ستظهر الحقول القابلة للترجمة بحقل إدخال لكل حزمة لغة مثبّتة: {$a}. ثبّت أو احذف حزمة لغة لتغيير هذه القائمة.';
$string['statusnofilter'] = 'لم يتم تفعيل مرشّح «المحتوى متعدد اللغات (الإصدار 2)» ولا «المحتوى متعدد اللغات»، لذا ستظهر صيغة {mlang} كما هي بدل تحويلها إلى لغة القارئ. فعّل أحدهما من إدارة الموقع ← الإضافات ← المرشّحات ← إدارة المرشّحات.';

$string['enabled'] = 'تفعيل الحقول متعددة اللغات';
$string['enabled_desc'] = 'إظهار حقل إدخال لكل لغة مثبّتة بدلاً من حقل واحد، في كل حقل قابل للترجمة. لن يحتاج المحرّر إلى كتابة صيغة {mlang} يدويًا؛ يتم تكوينها تلقائيًا عند حفظ النموذج.';

$string['editors'] = 'تضمين محرّرات النص الغني';
$string['editors_desc'] = 'إضافة مبدّل لغة أيضًا إلى محرّرات الوصف والملخّص والمقدّمة ونص السؤال. أوقف هذا الخيار لقصر الميزة على الحقول النصية البسيطة مثل الاسم والعنوان.';

$string['extratextfields'] = 'حقول نصية قابلة للترجمة إضافية';
$string['extratextfields_desc'] = 'اسم حقل واحد في كل سطر (يطابق «*» أي نص)، يُضاف إلى القائمة الافتراضية. القائمة الافتراضية:<pre>{$a}</pre>';

$string['extraexcludes'] = 'الاستثناءات';
$string['extraexcludes_desc'] = 'قاعدة <code>نوع_الصفحة|اسم_الحقل</code> في كل سطر (يطابق «*» أي نص)، تُضاف إلى القائمة الافتراضية. القائمة الافتراضية:<pre>{$a}</pre>';

$string['translations'] = 'الترجمات';
