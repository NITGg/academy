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
 * English strings for local_games.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Games Corner';

// Capabilities.
$string['games:play'] = 'Open the Games Corner and play';
$string['games:viewreports'] = 'See what other learners have played';

// Hub page.
$string['hubtitle'] = 'Games Corner';
$string['hubintro'] = 'Short games that teach something. Play, collect points, earn badges.';
$string['yourpoints'] = 'Your points';
$string['yourbadges'] = 'Your badges';
$string['comingsoon'] = 'Coming soon';
$string['play'] = 'Play';
$string['playagain'] = 'Play again';
$string['backtohub'] = 'Back to the games';
$string['minutes'] = '{$a} min';
$string['bestscore'] = 'Best: {$a}';
$string['nogamesyet'] = 'No games here yet.';

// Hub sections.
$string['cat_numbers'] = 'Numbers';
$string['cat_letters'] = 'Letters and words';
$string['cat_quiz'] = 'Questions';
$string['cat_memory'] = 'Memory and thinking';
$string['cat_motion'] = 'Moving games';
$string['cat_worlds'] = 'Big worlds';
$string['cat_worlds_note'] = 'These are not games - they gather the games above into one journey. They come after the games are done.';

// The catalogue.
$string['game_math_race'] = 'Math Race';
$string['gamedesc_math_race'] = 'Add, subtract and multiply - pick the right answer and keep the race going.';
$string['game_math_catcher'] = 'Number Catcher';
$string['gamedesc_math_catcher'] = 'Numbers fall like rain. Move the basket and catch only the ones that fit.';
$string['game_math_shop'] = 'Math Shop';
$string['gamedesc_math_shop'] = 'Buy things, work out the price and the change.';
$string['game_letter_order'] = 'Letter Order';
$string['gamedesc_letter_order'] = 'The letters of a word are jumbled - put them back in order.';
$string['game_word_builder'] = 'Word Builder';
$string['gamedesc_word_builder'] = 'A pile of letters. Build as many words as you can.';
$string['game_match_connect'] = 'Match the Picture';
$string['gamedesc_match_connect'] = 'Drag each word under the picture it belongs to.';
$string['game_crossword'] = 'Crossword';
$string['gamedesc_crossword'] = 'Clues across and down.';
$string['game_word_search'] = 'Word Search';
$string['gamedesc_word_search'] = 'A grid of letters with words hidden inside it.';
$string['game_speak_words'] = 'Say the Word';
$string['gamedesc_speak_words'] = 'Say the word out loud and the microphone checks it.';
$string['game_quiz'] = 'General Knowledge';
$string['gamedesc_quiz'] = 'A question and three or four answers.';
$string['game_true_false'] = 'True or False';
$string['gamedesc_true_false'] = 'A sentence - is it true or false?';
$string['game_xo_quiz'] = 'Tic-Tac-Toe Quiz';
$string['gamedesc_xo_quiz'] = 'Every right answer earns you a square.';
$string['game_target_answer'] = 'Pick the Answer';
$string['gamedesc_target_answer'] = 'Answers move across targets - hit the right one.';
$string['game_balloon_pop'] = 'Balloon Pop';
$string['gamedesc_balloon_pop'] = 'Balloons carry numbers and letters. Pop the one asked for.';
$string['game_wheel'] = 'Question Wheel';
$string['gamedesc_wheel'] = 'The wheel spins and picks the topic of your question.';
$string['game_space_quiz'] = 'Space Trip';
$string['gamedesc_space_quiz'] = 'Every right answer moves the rocket further.';
$string['game_who_am_i'] = 'Who Am I?';
$string['gamedesc_who_am_i'] = 'Clues appear one by one - guess before they run out.';
$string['game_memory_cards'] = 'Memory Cards';
$string['gamedesc_memory_cards'] = 'Face-down cards. Find the matching pairs.';
$string['game_puzzle'] = 'Jigsaw';
$string['gamedesc_puzzle'] = 'Put a picture back together piece by piece.';
$string['game_find_difference'] = 'Spot the Difference';
$string['gamedesc_find_difference'] = 'Two pictures - find what changed.';
$string['game_color_challenge'] = 'Colour Challenge';
$string['gamedesc_color_challenge'] = 'Pick the right colour, or match colours together.';
$string['game_runner'] = 'Learning Run';
$string['gamedesc_runner'] = 'Collect the right answers and dodge the wrong ones.';
$string['game_knowledge_map'] = 'Knowledge Map';
$string['gamedesc_knowledge_map'] = 'A map of regions, each one holding its own challenges.';
$string['game_adventure'] = 'Adventure Journey';
$string['gamedesc_adventure'] = 'Home, forest, castle, space - one journey through them all.';

// Badges.
$string['badge_fast_calculator'] = 'Sharp Calculator';
$string['badgehint_fast_calculator'] = '10 correct answers in a row';
$string['badge_sharp_hunter'] = 'Skilled Hunter';
$string['badgehint_sharp_hunter'] = '20 numbers caught without a single mistake';

// Shared in-game wording.
$string['js_start'] = 'Start';
$string['js_correct'] = 'Well done!';
$string['js_wrong'] = 'Try again';
$string['js_score'] = 'Score';
$string['js_streak'] = 'In a row';
$string['js_lives'] = 'Tries';
$string['js_roundover'] = 'Round finished!';
$string['js_yougot'] = 'You collected {$a} points';
$string['js_newbadge'] = 'New badge!';
$string['js_saving'] = 'Saving...';
$string['js_savefailed'] = 'Your points could not be saved - the game still counts.';
$string['js_sound_on'] = 'Sound on';
$string['js_sound_off'] = 'Sound off';

// Math Race. The operator words are what the voice reads out loud - a screen
// reader and a speech engine both make nothing of "x".
$string['js_op_plus'] = 'plus';
$string['js_op_minus'] = 'minus';
$string['js_op_times'] = 'times';
$string['js_race_question'] = 'What is the answer?';
$string['js_math_race_ready'] = 'Ready for the race?';
$string['js_math_race_howto'] = 'A sum appears, three answers under it. Tap the right one and the runner moves forward.';

// Number Catcher.
$string['js_math_catcher_ready'] = 'Ready to hunt?';
$string['js_math_catcher_howto'] = 'Move the basket with the arrows, your finger or the big buttons. Catch only the numbers that match what is asked.';
$string['js_catch_rule_equals'] = 'Catch the ones equal to {$a}';
$string['js_catch_rule_divisible'] = 'Catch the ones that divide by {$a}';
$string['js_catch_rule_greater'] = 'Catch the ones bigger than {$a}';
$string['js_catch_rule_less'] = 'Catch the ones smaller than {$a}';
$string['js_catch_rule_even'] = 'Catch the even numbers';
$string['js_catch_rule_odd'] = 'Catch the odd numbers';
$string['js_catch_left'] = 'Left';
$string['js_catch_right'] = 'Right';

// Errors.
$string['errorunknowngame'] = 'That game does not exist, or is not ready yet.';

// Privacy.
$string['privacy:metadata:progress'] = 'Points and play counts per game.';
$string['privacy:metadata:progress:userid'] = 'The user who played.';
$string['privacy:metadata:progress:gameid'] = 'Which game was played.';
$string['privacy:metadata:progress:points'] = 'Points collected in this game.';
$string['privacy:metadata:progress:plays'] = 'How many rounds were finished.';
$string['privacy:metadata:progress:bestscore'] = 'The best single-round score.';
$string['privacy:metadata:progress:beststreak'] = 'The longest run of correct answers.';
$string['privacy:metadata:progress:timemodified'] = 'When the last round was played.';
$string['privacy:metadata:badge'] = 'Badges earned in the Games Corner.';
$string['privacy:metadata:badge:userid'] = 'The user who earned the badge.';
$string['privacy:metadata:badge:gameid'] = 'The game the badge came from.';
$string['privacy:metadata:badge:badge'] = 'Which badge was earned.';
$string['privacy:metadata:badge:timeawarded'] = 'When the badge was earned.';
