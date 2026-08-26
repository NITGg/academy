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
 * Play one game.
 *
 * The page is the same shell for every game: a HUD, a stage, a start card and
 * an end card. The per-game file in js/ fills the stage and reports the result
 * back to the shell.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_games\progress;
use local_games\registry;

require_login();

$context = context_system::instance();
require_capability('local/games:play', $context);

$id = required_param('id', PARAM_ALPHANUMEXT);

if (!registry::is_live($id)) {
    throw new moodle_exception('errorunknowngame', 'local_games', new moodle_url('/local/games/index.php'));
}

$game = registry::get_game($id);
$key  = registry::key($id);

$PAGE->set_url(new moodle_url('/local/games/play.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('game_' . $key, 'local_games'));
$PAGE->set_heading(get_string('game_' . $key, 'local_games'));
// styles.css is folded into the theme stylesheet by Moodle - see index.php.
$PAGE->add_body_class('local-games local-games-play');

$PAGE->navbar->add(get_string('hubtitle', 'local_games'), new moodle_url('/local/games/index.php'));
$PAGE->navbar->add(get_string('game_' . $key, 'local_games'));

// Every string the browser side needs, in one bag. The games never build a
// sentence out of fragments - each message is its own string.
$jsstrings = [];
foreach (get_string_manager()->load_component_strings('local_games', current_language()) as $stringkey => $value) {
    if (strpos($stringkey, 'js_') === 0) {
        $jsstrings[substr($stringkey, 3)] = $value;
    }
}
$jsstrings['playagain'] = get_string('playagain', 'local_games');
$jsstrings['backtohub'] = get_string('backtohub', 'local_games');

$totals = progress::get_totals($USER->id);

// Arabic-Indic digits when the interface is Arabic, matching the design doc's
// examples. The maths itself always runs on real numbers.
$arabicdigits = strpos(current_language(), 'ar') === 0;

$PAGE->requires->js(new moodle_url('/local/games/js/shell.js'));
$PAGE->requires->js(new moodle_url('/local/games/js/' . $id . '.js'));

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_games/play', [
    'gameid'    => $id,
    'name'      => get_string('game_' . $key, 'local_games'),
    'emoji'     => $game['emoji'],
    'huburl'    => (new moodle_url('/local/games/index.php'))->out(false),
    'backlabel' => get_string('backtohub', 'local_games'),
    'startlabel' => get_string('js_start', 'local_games'),
    'readytitle' => get_string('js_' . $key . '_ready', 'local_games'),
    'howto'      => get_string('js_' . $key . '_howto', 'local_games'),
    'scorelabel' => get_string('js_score', 'local_games'),
    'streaklabel' => get_string('js_streak', 'local_games'),
    'soundonlabel' => get_string('js_sound_on', 'local_games'),
    'config'     => json_encode([
        'gameid'       => $id,
        'wwwroot'      => $CFG->wwwroot,
        'sesskey'      => sesskey(),
        'huburl'       => (new moodle_url('/local/games/index.php'))->out(false),
        'arabicdigits' => $arabicdigits,
        'points'       => $totals['points'],
        'strings'      => $jsstrings,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
]);

echo $OUTPUT->footer();
