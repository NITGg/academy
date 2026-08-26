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
 * Arabic strings for local_games.
 *
 * The wording follows the design doc: it talks to a 7-11 year old, and a wrong
 * answer is never framed as a failure.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'ركن الألعاب';

// Capabilities.
$string['games:play'] = 'دخول ركن الألعاب واللعب';
$string['games:viewreports'] = 'الاطلاع على ما لعبه الطلاب';

// Hub page.
$string['hubtitle'] = 'ركن الألعاب';
$string['hubintro'] = 'ألعاب قصيرة بتعلّمك حاجة. العب، اجمع نقاط، واكسب شارات.';
$string['yourpoints'] = 'نقاطك';
$string['yourbadges'] = 'شاراتك';
$string['comingsoon'] = 'قريباً';
$string['play'] = 'العب';
$string['playagain'] = 'العب تاني';
$string['backtohub'] = 'رجوع للألعاب';
$string['minutes'] = '{$a} دقايق';
$string['bestscore'] = 'أفضل نتيجة: {$a}';
$string['nogamesyet'] = 'مفيش ألعاب هنا لسه.';

// Hub sections.
$string['cat_numbers'] = 'ألعاب الأرقام';
$string['cat_letters'] = 'ألعاب الحروف والكلمات';
$string['cat_quiz'] = 'ألعاب الأسئلة';
$string['cat_memory'] = 'ألعاب الذاكرة والتفكير';
$string['cat_motion'] = 'ألعاب الحركة';
$string['cat_worlds'] = 'العوالم الكبيرة';
$string['cat_worlds_note'] = 'دي مش ألعاب — دي عوالم بتجمع الألعاب اللي فوق في رحلة واحدة، وبتتعمل بعد ما الألعاب تخلص.';

// The catalogue.
$string['game_math_race'] = 'سباق الحساب';
$string['gamedesc_math_race'] = 'مسائل جمع وطرح وضرب، والطفل يختار الإجابة الصح بسرعة.';
$string['game_math_catcher'] = 'صياد الأرقام';
$string['gamedesc_math_catcher'] = 'أرقام بتنزل من فوق، والطفل يمسك المطلوب ويسيب الباقي يقع.';
$string['game_math_shop'] = 'متجر الحساب';
$string['gamedesc_math_shop'] = 'يشتري حاجات ويحسب السعر والباقي.';
$string['game_letter_order'] = 'رتب الحروف';
$string['gamedesc_letter_order'] = 'حروف كلمة مبعثرة والطفل يرتّبها.';
$string['game_word_builder'] = 'كوّن الكلمة';
$string['gamedesc_word_builder'] = 'حروف كتير، يبني منها كلمات.';
$string['game_match_connect'] = 'وصل الصورة بالكلمة';
$string['gamedesc_match_connect'] = 'يسحب الكلمة ويحطها تحت الصورة.';
$string['game_crossword'] = 'الكلمات المتقاطعة';
$string['gamedesc_crossword'] = 'تعريفات أفقي ورأسي.';
$string['game_word_search'] = 'ابحث عن الكلمات';
$string['gamedesc_word_search'] = 'شبكة حروف وكلمات مخبّية جواها.';
$string['game_speak_words'] = 'نطق الكلمات';
$string['gamedesc_speak_words'] = 'الطفل ينطق الكلمة والميكروفون يتأكد.';
$string['game_quiz'] = 'معلومات عامة';
$string['gamedesc_quiz'] = 'سؤال و3 أو 4 اختيارات.';
$string['game_true_false'] = 'صح ولا غلط';
$string['gamedesc_true_false'] = 'جملة والطفل يقول صح ولا غلط.';
$string['game_xo_quiz'] = 'XO التعليمي';
$string['gamedesc_xo_quiz'] = 'كل إجابة صح تخلّيه يحط X أو O.';
$string['game_target_answer'] = 'اختار الإجابة';
$string['gamedesc_target_answer'] = 'أهداف بتتحرك عليها إجابات، يضغط الصح.';
$string['game_balloon_pop'] = 'فرقعة البالونات';
$string['gamedesc_balloon_pop'] = 'بالونات عليها أرقام أو حروف، يفرقع المطلوب.';
$string['game_wheel'] = 'عجلة الأسئلة';
$string['gamedesc_wheel'] = 'عجلة تدور وتختار موضوع السؤال.';
$string['game_space_quiz'] = 'رحلة الفضاء';
$string['gamedesc_space_quiz'] = 'كل إجابة صح المركبة تتقدّم.';
$string['game_who_am_i'] = 'مين أنا؟';
$string['gamedesc_who_am_i'] = 'تلميحات بتظهر واحدة واحدة والطفل يخمّن.';
$string['game_memory_cards'] = 'كروت الذاكرة';
$string['gamedesc_memory_cards'] = 'كروت مقلوبة، يطابق المتشابه.';
$string['game_puzzle'] = 'البازل';
$string['gamedesc_puzzle'] = 'تركيب صورة مقسّمة لأجزاء.';
$string['game_find_difference'] = 'ابحث عن الاختلافات';
$string['gamedesc_find_difference'] = 'صورتين والطفل يلاقي الفروق.';
$string['game_color_challenge'] = 'تحدي الألوان';
$string['gamedesc_color_challenge'] = 'يختار اللون الصح أو يطابق الألوان.';
$string['game_runner'] = 'الجري التعليمي';
$string['gamedesc_runner'] = 'يجمع الإجابات الصح ويتجنّب الغلط.';
$string['game_knowledge_map'] = 'خريطة المعرفة';
$string['gamedesc_knowledge_map'] = 'خريطة عليها مناطق، كل منطقة فيها تحديات.';
$string['game_adventure'] = 'رحلة المغامرة';
$string['gamedesc_adventure'] = 'البيت ثم الغابة ثم القلعة ثم الفضاء — رحلة واحدة.';

// Badges.
$string['badge_fast_calculator'] = 'سريع الحساب';
$string['badgehint_fast_calculator'] = '10 إجابات صح ورا بعض';
$string['badge_sharp_hunter'] = 'صياد ماهر';
$string['badgehint_sharp_hunter'] = '20 رقم صح من غير غلطة';

// Shared in-game wording.
$string['js_start'] = 'يلا نبدأ';
$string['js_correct'] = 'أحسنت!';
$string['js_wrong'] = 'جرّب تاني 💪';
$string['js_score'] = 'النتيجة';
$string['js_streak'] = 'ورا بعض';
$string['js_lives'] = 'محاولات';
$string['js_roundover'] = 'خلصت الجولة!';
$string['js_yougot'] = 'جمعت {$a} نقطة';
$string['js_newbadge'] = 'شارة جديدة!';
$string['js_saving'] = 'بنحفظ...';
$string['js_savefailed'] = 'مقدرناش نحفظ النقاط — بس اللعبة اتحسبت.';
$string['js_sound_on'] = 'الصوت شغال';
$string['js_sound_off'] = 'الصوت مقفول';

// Math Race. The operator words are what the voice reads out loud - a screen
// reader and a speech engine both make nothing of "x".
$string['js_op_plus'] = 'زائد';
$string['js_op_minus'] = 'ناقص';
$string['js_op_times'] = 'في';
$string['js_race_question'] = 'الإجابة كام؟';
$string['js_math_race_ready'] = 'جاهز للسباق؟';
$string['js_math_race_howto'] = 'هتظهر مسألة وتحتها 3 إجابات. اضغط على الصح والعدّاء يتقدّم.';

// Number Catcher.
$string['js_math_catcher_ready'] = 'جاهز للصيد؟';
$string['js_math_catcher_howto'] = 'حرّك السلة بالأسهم أو بصباعك أو بالأزرار الكبيرة، وامسك بس الأرقام اللي بتحقق المطلوب.';
$string['js_catch_rule_equals'] = 'امسك اللي يساوي {$a}';
$string['js_catch_rule_divisible'] = 'امسك اللي يقبل القسمة على {$a}';
$string['js_catch_rule_greater'] = 'امسك اللي أكبر من {$a}';
$string['js_catch_rule_less'] = 'امسك اللي أصغر من {$a}';
$string['js_catch_rule_even'] = 'امسك الأرقام الزوجية';
$string['js_catch_rule_odd'] = 'امسك الأرقام الفردية';
$string['js_catch_left'] = 'شمال';
$string['js_catch_right'] = 'يمين';

// Errors.
$string['errorunknowngame'] = 'اللعبة دي مش موجودة، أو لسه مش جاهزة.';

// Privacy.
$string['privacy:metadata:progress'] = 'النقاط وعدد الجولات لكل لعبة.';
$string['privacy:metadata:progress:userid'] = 'المستخدم اللي لعب.';
$string['privacy:metadata:progress:gameid'] = 'اللعبة اللي اتلعبت.';
$string['privacy:metadata:progress:points'] = 'النقاط المجمّعة في اللعبة دي.';
$string['privacy:metadata:progress:plays'] = 'عدد الجولات اللي خلصت.';
$string['privacy:metadata:progress:bestscore'] = 'أفضل نتيجة في جولة واحدة.';
$string['privacy:metadata:progress:beststreak'] = 'أطول سلسلة إجابات صح.';
$string['privacy:metadata:progress:timemodified'] = 'وقت آخر جولة.';
$string['privacy:metadata:badge'] = 'الشارات المكتسبة في ركن الألعاب.';
$string['privacy:metadata:badge:userid'] = 'المستخدم صاحب الشارة.';
$string['privacy:metadata:badge:gameid'] = 'اللعبة اللي جت منها الشارة.';
$string['privacy:metadata:badge:badge'] = 'الشارة المكتسبة.';
$string['privacy:metadata:badge:timeawarded'] = 'وقت الحصول على الشارة.';
