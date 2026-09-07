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
$string['notranscript'] = 'مفيش نص مرفوع. ارفع واحد من إعدادات النشاط عشان تشغّل المساعد.';
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
