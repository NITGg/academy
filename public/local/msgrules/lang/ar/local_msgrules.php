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
 * Arabic strings for local_msgrules.
 *
 * @package    local_msgrules
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'قيود مراسلة الطلاب';
$string['settings'] = 'الإعدادات';
$string['managecourses'] = 'القيود لكل مقرر';
// The ticks. "No restriction" is the master switch; the other three combine freely, and none
// of them ticked means the students on that course may message nobody at all.
$string['modeopen'] = 'بدون قيود';
$string['modenobody'] = 'لا أحد — الطلاب لا يقدروا يراسلوا أي حد';
$string['modeallowlist'] = 'فقط: {$a}';
$string['allowteachers'] = 'المدرسون';
$string['allowadmins'] = 'مديرو الموقع';
$string['allowpeers'] = 'زملاء الكورس';
$string['usedefault'] = 'استخدم إعداد «كل الكورسات»';
$string['followsdefault'] = 'حالياً: {$a}';
$string['allcourses'] = 'كل الكورسات';
$string['allcourses_help'] = 'بينطبق على أي كورس مالوش إعداد خاص بيه تحت.';

// Settings.
$string['enabled'] = 'تفعيل القيود';
$string['enabled_desc'] = 'لما تكون مفعّلة، الطلاب بيتقيدوا بالإعداد المحدّد لكل كورس من كورساتهم. '
    . 'وإيقافها بيرجّع كل المحادثات اللي الإضافة قفلتها، مع الإبقاء على أي حظر عمله المستخدم بنفسه. '
    . 'اظبط الإعدادات الأول، وبعدين فعّل من هنا.';
$string['maxusers'] = 'الحد الأقصى لعدد الحسابات';
$string['maxusers_desc'] = 'الطالب المقيَّد بيحتاج صف لكل شخص في الموقع مش مسموح له يراسله، يعني الشغل بيزيد '
    . 'مع حجم الموقع. فوق الرقم ده العملية بترفض تشتغل بدل ما تقعد ساعات في الـ cron. زوّده عن قصد.';

// Management screen.
$string['coursesintro'] = 'كل مقرر بيحدّد طلابه يعملوا إيه. المدرسون مش متقيدين أبداً — يقدروا دايماً يراسلوا طلابهم — '
    . 'والطالب اللي في أكتر من مقرر بياخد أوسع صلاحية يسمح بيها أي مقرر من مقرراته.';
$string['ticksintro'] = 'علّم «بدون قيود» عشان تسيب الكورس زي ما هو. غير كده علّم كل مجموعة لسه مسموح لطلابه '
    . 'يراسلوها — وتقدر تجمع أكتر من واحدة، يعني لما تعلّم «المدرسون» و«مديرو الموقع» الطالب هيقدر يوصل للاتنين وبس. '
    . 'ولو ماعلّمتش ولا واحدة من التلاتة، يبقى مش هيقدر يراسل حد خالص.';
$string['course'] = 'الكورس';
$string['restriction'] = 'طلاب الكورس ده مسموح لهم يراسلوا';
$string['searchcourses'] = 'ابحث في المقررات';
$string['nocoursesfound'] = 'مفيش مقررات مطابقة.';
$string['rebuildnow'] = 'إعادة التطبيق الآن';
$string['rebuildqueued'] = 'اتجدولت وهتتطبّق مع أول تشغيل للـ cron. الموقع أكبر من إنه يتطبّق عليه وإنت مستني.';
$string['rebuildapplied'] = 'شغّالة دلوقتي: {$a->students} طالب مقيَّد، اتقفلت {$a->added} محادثة، واتفتحت {$a->removed}. '
    . 'ادخل بحساب طالب تجريبي وجرّب.';
$string['currentstate'] = 'الحالة الحالية';
$string['managedblocks'] = 'عدد المحادثات المقفولة حالياً بسبب القيود دي: {$a}';
$string['disabledwarning'] = 'القيود دي مش مطبَّقة حالياً. فعّل خيار «تفعيل القيود» من الإعدادات '
    . 'بعد ما تظبط المقررات تحت زي ما إنت عايز.';
$string['messagingoffwarning'] = 'نظام الرسائل في الموقع مقفول أصلاً، فمفيش حاجة ممكن تتبعت. القيود دي هيبقى ليها '
    . 'معنى بعد ما تفعّل الرسائل من: إدارة الموقع ← ميزات متقدمة.';

// Bypass diagnostics.
$string['bypassheading'] = 'الأدوار اللي بتتخطى القيود دي';
$string['bypassintro'] = 'القيود بتشتغل من خلال قائمة الحظر بتاعة المستلِم، ومودل بيسمح لصلاحيتين إنهم يتجاهلوا '
    . 'القائمة دي تماماً. أي حد معاه واحدة منهم يقدر يراسل اللي هو عايزه مهما كان المقرر بيقول إيه. '
    . 'ده عادةً المطلوب للمدرسين؛ لو مش عايز كده، اسحب الصلاحية من الدور.';
$string['bypassnone'] = 'مفيش أي دور غير مدير الموقع يقدر يتخطى القيود.';
$string['bypassrole'] = '{$a->role} — عن طريق {$a->capability}';
$string['adminexempt'] = 'مديرو الموقع يقدروا يراسلوا أي حد دايماً، مهما كانت الإعدادات دي. أما إن الطالب يرد '
    . 'عليهم فده اللي بتحدده علامة «مديرو الموقع» — لو شيلتها من كل مكان، الطلاب مش هيبقى قدامهم طريق '
    . 'للتواصل مع الدعم من جوه مودل.';

// Tasks.
$string['tasksyncblocks'] = 'إعادة تطبيق قيود مراسلة الطلاب';
$string['tasksyncuser'] = 'تطبيق قيود المراسلة على مستخدم واحد';

// Errors.
$string['errortoomanyusers'] = 'الموقع فيه {$a->count} حساب، وده أكبر من الحد المضبوط {$a->max}. '
    . 'زوّد «الحد الأقصى لعدد الحسابات» من إعدادات الإضافة لو فعلاً عايز تعيد البناء على العدد ده.';

// Capabilities.
$string['msgrules:manage'] = 'إدارة قيود مراسلة الطلاب';

// Privacy.
$string['privacy:metadata:local_msgrules_managed'] = 'تحديد أي من صفوف الحظر في حساب المستخدم اتحطّت بواسطة '
    . 'قيود المراسلة بدل ما يكون المستخدم هو اللي عملها.';
$string['privacy:metadata:local_msgrules_managed:userid'] = 'المستخدم صاحب قائمة الحظر اللي فيها الصف.';
$string['privacy:metadata:local_msgrules_managed:blockeduserid'] = 'المستخدم الممنوع من المحادثة.';
$string['privacy:metadata:local_msgrules_managed:timecreated'] = 'وقت إضافة الصف بواسطة القيود.';
