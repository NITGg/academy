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

$string['pluginname'] = 'قواعد المراسلة';
$string['settings'] = 'الإعدادات';
$string['managematrix'] = 'مَن يراسل مَن';

// Settings.
$string['enabled'] = 'تفعيل قواعد المراسلة';
$string['enabled_desc'] = 'لما تكون مفعّلة، المستخدم مش هيقدر يبدأ محادثة إلا مع شخص تسمح بيه المصفوفة اللي تحت. '
    . 'وإيقافها بيرجّع كل المحادثات اللي الإضافة قفلتها، مع الإبقاء على أي حظر عمله المستخدم بنفسه. '
    . 'ارسم المصفوفة الأول، وبعدين فعّل الخيار ده.';
$string['maxusers'] = 'الحد الأقصى لعدد الحسابات';
$string['maxusers_desc'] = 'كل قاعدة بتتخزّن كصف حظر لكل اتجاه ممنوع، يعني الشغل بيزيد بمربع عدد الحسابات. '
    . 'فوق الرقم ده عملية إعادة البناء بترفض تشتغل بدل ما تقعد ساعات في الـ cron. زوّده عن قصد، '
    . 'وتوقّع إن إعادة البناء على موقع كبير هتكون بطيئة.';

// Management screen.
// "Cohort" is "الزُمرة" in the Arabic pack, not "المجموعة" - which is what core's own Users
// menu says, so anything else here sends the reader looking for a link that does not exist.
$string['matrixintro'] = 'علّم على المربع عشان تسمح لأعضاء الزُمرة اللي في الصف إنهم يبدأوا محادثة مع أعضاء الزُمرة '
    . 'اللي في العمود. الاتجاه مهم: لما تسمح للطلاب يراسلوا المدربين، ده مابيقولش حاجة عن الرد — '
    . 'الرد محتاج علامة في الخانة المقابلة.';
$string['sendercohort'] = 'المُرسِل';
$string['recipientcohort'] = 'مسموح يراسل';
$string['nocohort'] = 'خارج أي زُمرة';
$string['nocohort_help'] = 'بيشمل كل حساب مش تابع لأي زُمرة، ومنهم المستخدمين الجدد اللي لسه مسجّلين.';
$string['rulessaved'] = 'تم حفظ القواعد.';
$string['rebuildnow'] = 'تطبيق القواعد الآن';
$string['rebuildqueued'] = 'اتجدولت عملية تطبيق هتتنفّذ مع أول تشغيل للـ cron. الموقع أكبر من إنه يتطبّق عليه '
    . 'وإنت مستني.';
$string['rebuildapplied'] = 'اتطبّقت على {$a->users} حساب: تم إغلاق {$a->added} محادثة، وفتح {$a->removed}. '
    . 'القواعد شغّالة دلوقتي — ادخل بحساب تجريبي وجرّب.';
$string['currentstate'] = 'الحالة الحالية';
$string['managedblocks'] = 'عدد المحادثات المقفولة حالياً بسبب القواعد دي: {$a}';
$string['nocohortsyet'] = 'مفيش زُمر (Cohorts) على الموقع لحد دلوقتي، يعني مفيش حاجة نرسم عليها قواعد. '
    . 'اعمل الزُمر اللي عايز تفصل بينها من: إدارة الموقع ← المستخدمون ← الزُمر، وبعدين ارجع هنا.';
$string['disabledwarning'] = 'القواعد دي مش مطبَّقة حالياً. فعّل خيار «تفعيل قواعد المراسلة» من الإعدادات '
    . 'بعد ما تتأكد إن المصفوفة بتقول اللي إنت عايزه.';
$string['messagingoffwarning'] = 'نظام الرسائل في الموقع مقفول أصلاً، فمفيش حاجة ممكن تتبعت. القواعد دي هيبقى ليها '
    . 'معنى بعد ما تفعّل الرسائل من: إدارة الموقع ← ميزات متقدمة.';

// Bypass diagnostics.
$string['bypassheading'] = 'الأدوار اللي بتتخطى القواعد دي';
$string['bypassintro'] = 'القواعد بتشتغل من خلال قائمة الحظر بتاعة المستلِم، ومودل بيسمح لصلاحيتين إنهم يتجاهلوا '
    . 'القائمة دي تماماً. أي حد معاه واحدة منهم يقدر يراسل اللي هو عايزه مهما كانت المصفوفة بتقول إيه. '
    . 'اسحب الصلاحية من الأدوار اللي تحت لو عايز القواعد تطبَّق عليهم كمان.';
$string['bypassnone'] = 'مفيش أي دور غير مدير الموقع يقدر يتخطى القواعد.';
$string['bypassrole'] = '{$a->role} — عن طريق {$a->capability}';
$string['adminexempt'] = 'مديرو الموقع مستثنون دايماً: مابيتحظروش، ومحدش بيتمنع من مراسلتهم، '
    . 'عشان يفضل في طريق مفتوح للتواصل مع الدعم.';

// Tasks.
$string['tasksyncblocks'] = 'إعادة بناء قواعد المراسلة';
$string['tasksyncuser'] = 'تطبيق قواعد المراسلة على مستخدم واحد';

// Errors.
$string['errortoomanyusers'] = 'الموقع فيه {$a->count} حساب، وده أكبر من الحد المضبوط {$a->max}. '
    . 'زوّد «الحد الأقصى لعدد الحسابات» من إعدادات الإضافة لو فعلاً عايز تعيد البناء على العدد ده.';

// Capabilities.
$string['msgrules:manage'] = 'إدارة مصفوفة قواعد المراسلة';

// Privacy.
$string['privacy:metadata:local_msgrules_managed'] = 'تحديد أي من صفوف الحظر في حساب المستخدم اتحطّت بواسطة '
    . 'قواعد المراسلة في الموقع بدل ما يكون المستخدم هو اللي عملها.';
$string['privacy:metadata:local_msgrules_managed:userid'] = 'المستخدم صاحب قائمة الحظر اللي فيها الصف.';
$string['privacy:metadata:local_msgrules_managed:blockeduserid'] = 'المستخدم الممنوع من المحادثة.';
$string['privacy:metadata:local_msgrules_managed:timecreated'] = 'وقت إضافة الصف بواسطة القواعد.';
