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
 * Arabic strings for local_nit_instructors.
 *
 * @package    local_nit_instructors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'الخلفية المهنية للمدرّس';

// المجموعة نفسها (AC-4.5.9).
$string['background'] = 'الخلفية الأكاديمية والمهنية';
$string['editbackground'] = 'تعديل خلفيتي';
$string['viewpublic'] = 'عرض ملفي العام';
$string['nobackground'] = 'لم يضِف هذا المدرّس خلفيته بعد.';
$string['notaninstructor'] = 'هذه الصفحة للمدرّسين فقط.';
$string['nosuchinstructor'] = 'لا يوجد مدرّس بهذا المعرّف.';

// الحقول.
$string['specialty'] = 'التخصص الدقيق';
$string['specialty_en'] = 'التخصص الدقيق (بالإنجليزية)';
$string['specialty_ar'] = 'التخصص الدقيق (بالعربية)';
$string['specialtytoolong'] = 'يرجى ألا يتجاوز هذا الحقل {$a} حرفًا.';
$string['years'] = 'سنوات الخبرة التدريسية';
$string['years_help'] = 'سنوات صحيحة من ٠ إلى ٦٠. اتركها صفرًا إن لم ترد ذكرها - فالحقل اختياري مثل بقية حقول هذه المجموعة.';
$string['yearsvalue'] = '{$a} سنة';
$string['yearsrange'] = 'يرجى إدخال عدد سنوات صحيح بين ٠ و{$a}.';
$string['coursestaught'] = 'المقررات التي يقوم بتدريسها';
$string['coursestaught_help'] = 'تأتي هذه القائمة من المقررات التي أسندها إليك المسؤول. لا يمكن تعديلها هنا ولا إضافة مقرر يدويًا - فالقائمة تعكس دائمًا ما تُدرّسه فعلًا.';
$string['nocoursestaught'] = 'لم تُسنَد إليك مقررات بعد.';

// الإدخالات المتكررة (AC-4.5.12).
$string['type_qualification'] = 'المؤهلات العلمية';
$string['type_position'] = 'أهم المناصب التي عمل بها';
$string['type_certification'] = 'الشهادات المهنية والجوائز';
$string['entry_title_en'] = 'المسمّى (بالإنجليزية)';
$string['entry_title_ar'] = 'المسمّى (بالعربية)';
$string['entry_org_en'] = 'الجهة المانحة / المؤسسة (بالإنجليزية)';
$string['entry_org_ar'] = 'الجهة المانحة / المؤسسة (بالعربية)';
$string['entry_period_en'] = 'السنة أو الفترة (بالإنجليزية)';
$string['entry_period_ar'] = 'السنة أو الفترة (بالعربية)';
$string['entrynote'] = 'لحذف إدخال، أفرغ جميع خاناته. وتظهر الإدخالات للمتعلّمين بالترتيب الذي تظهر به هنا، فلإعادة ترتيبها انقل النص.';
$string['addmore'] = 'إضافة إدخالات أخرى';
$string['bilingualnote'] = 'كل الحقول هنا اختيارية، ويمكن كتابة كل منها بلغتين. وإذا ملأت لغة واحدة فقط، فهي التي ستُعرض للجميع بدلًا من ترك الحقل فارغًا.';
$string['submitforreview'] = 'إرسال للمراجعة';

// دورة المراجعة (AC-4.5.14، AC-4.5.15).
$string['pendingnotice'] = 'تم إرسال تعديلاتك للمراجعة وستظهر بعد الموافقة عليها.';
$string['rejectednotice'] = 'لم تتم الموافقة على تعديلاتك. السبب: {$a}';
$string['noreasongiven'] = 'لم يُذكر سبب';
$string['reviewqueue'] = 'مراجعة خلفيات المدرّسين';
$string['queueintro'] = 'يُعرض كل تعديل بجوار النسخة التي سيحلّ محلها. ويستمر المتعلّمون في رؤية النسخة الحالية حتى توافق عليه.';
$string['queueempty'] = 'لا يوجد ما ينتظر المراجعة.';
$string['currentversion'] = 'المنشور حاليًا';
$string['proposedversion'] = 'التعديل المقترَح';
$string['approve'] = 'الموافقة والنشر';
$string['reject'] = 'رفض';
$string['approved'] = 'تم نشر التعديل.';
$string['rejected'] = 'تم رفض التعديل وإبلاغ المدرّس بالسبب.';
$string['decisionfailed'] = 'تعذّر اتخاذ إجراء على هذا التعديل - ربما تم سحبه أو البتّ فيه بالفعل.';
$string['decisionnote'] = 'السبب';
$string['decisionnoteplaceholder'] = 'مطلوب عند الرفض، ويُعرَض على المدرّس.';
$string['reasonrequired'] = 'يرجى ذكر سبب. فالمدرّس يراه، والرفض بلا سبب لا يخبره بشيء.';

// الصلاحية.
$string['nit_instructors:review'] = 'مراجعة تعديلات خلفيات المدرّسين ونشرها';

// الخصوصية.
$string['privacy:metadata:local_nit_instructors_profile'] = 'الخلفية الأكاديمية والمهنية التي ينشرها المدرّس عن نفسه.';
$string['privacy:metadata:local_nit_instructors_profile:userid'] = 'المدرّس صاحب الخلفية.';
$string['privacy:metadata:local_nit_instructors_profile:specialtyen'] = 'التخصص الدقيق للمدرّس بالإنجليزية.';
$string['privacy:metadata:local_nit_instructors_profile:specialtyar'] = 'التخصص الدقيق للمدرّس بالعربية.';
$string['privacy:metadata:local_nit_instructors_profile:years'] = 'عدد سنوات الخبرة التدريسية.';
$string['privacy:metadata:local_nit_instructors_profile:status'] = 'ما إذا كانت هذه النسخة منشورة أو قيد المراجعة أو مرفوضة.';
$string['privacy:metadata:local_nit_instructors_profile:decisionnote'] = 'السبب الذي ذكره المسؤول لقراره.';
$string['privacy:metadata:local_nit_instructors_entry'] = 'المؤهلات والمناصب والجوائز المدرجة في خلفية المدرّس.';
$string['privacy:metadata:local_nit_instructors_entry:titleen'] = 'المؤهل أو المنصب أو الجائزة بالإنجليزية.';
$string['privacy:metadata:local_nit_instructors_entry:titlear'] = 'المؤهل أو المنصب أو الجائزة بالعربية.';
$string['privacy:metadata:local_nit_instructors_entry:orgen'] = 'الجهة المانحة أو المؤسسة بالإنجليزية.';
$string['privacy:metadata:local_nit_instructors_entry:orgar'] = 'الجهة المانحة أو المؤسسة بالعربية.';
$string['privacy:metadata:local_nit_instructors_entry:perioden'] = 'السنة أو الفترة بالإنجليزية.';
$string['privacy:metadata:local_nit_instructors_entry:periodar'] = 'السنة أو الفترة بالعربية.';
