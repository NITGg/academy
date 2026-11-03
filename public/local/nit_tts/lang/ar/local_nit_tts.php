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
 * Arabic strings for local_nit_tts.
 *
 * @package    local_nit_tts
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'تحويل النص إلى صوت';
$string['settings'] = 'إعدادات تحويل النص إلى صوت';
$string['privacy:metadata'] = 'صفحة تحويل النص إلى صوت لا تحفظ أي بيانات شخصية. النص المكتوب يُرسل إلى Google Gemini لتحويله إلى صوت ولا يُحفظ.';

// Settings.
$string['setting_apikey'] = 'مفتاح Gemini API';
$string['setting_apikey_desc'] = 'المفتاح من Google AI Studio المستخدم لاستدعاء نموذج تحويل النص إلى صوت في Gemini. اتركه فارغاً لاستخدام مفتاح مزوّد Gemini الموجود في إدارة الموقع > الذكاء الاصطناعي > مزوّدو الذكاء الاصطناعي.';
$string['setting_model'] = 'نموذج Gemini للصوت';
$string['setting_model_desc'] = 'اسم نموذج Gemini الذي ينطق النص، مثل gemini-2.5-flash-preview-tts أو gemini-2.5-pro-preview-tts.';

// Page.
$string['text'] = 'النص';
$string['voice'] = 'الصوت';
$string['voice_female'] = 'أنثى';
$string['voice_male'] = 'ذكر';
$string['style'] = 'تعليمات الأسلوب';
$string['style_help'] = 'اختياري. يحدد طريقة القراءة، مثل "اقرأ بنبرة هادئة بالفصحى" أو "قلها بمرح باللهجة المصرية". اتركه فارغاً لقراءة محايدة.';
$string['convert'] = 'حوّل إلى صوت';
$string['converting'] = 'جارٍ التحويل…';
$string['download'] = 'تحميل الصوت';

// Errors.
$string['err_nokey'] = 'لم يتم ضبط مفتاح Gemini API لتحويل النص إلى صوت.';
$string['err_nokey_link'] = 'لم يتم ضبط مفتاح Gemini API بعد. <a href="{$a}">أضفه من الإعدادات</a>.';
$string['err_api'] = 'تعذّر على Gemini تحويل النص: {$a}';
$string['err_toolong'] = 'النص طويل جداً. الحد الأقصى {$a} حرف.';
