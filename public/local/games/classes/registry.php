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

namespace local_games;

/**
 * The catalogue of games in the corner.
 *
 * The whole 24-game plan from "docs/Games Doc/games.md" lives here from day one,
 * so the hub page shows the full shape of the corner while only the finished
 * games are playable. Shipping a game = writing js/<slug>.js and flipping its
 * status to STATUS_LIVE.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registry {

    /** @var string Playable now. */
    const STATUS_LIVE = 'live';

    /** @var string On the plan, not built yet - the card renders as "coming soon". */
    const STATUS_SOON = 'soon';

    /**
     * The sections of the hub page, in display order.
     *
     * @return array<string, string> category key => emoji
     */
    public static function get_categories(): array {
        return [
            'numbers' => '🔢',
            'letters' => '🔤',
            'quiz'    => '❓',
            'memory'  => '🧩',
            'motion'  => '🏃',
            'worlds'  => '🗺️',
        ];
    }

    /**
     * Every game in the corner, keyed by slug.
     *
     * Each entry carries:
     *  - num       the number it has in the design doc (keeps the two in sync)
     *  - emoji     the card face; no image assets to manage
     *  - category  which hub section it sits in
     *  - level     1..3, rendered as one to three stars
     *  - minutes   the intended round length, from the doc
     *  - status    STATUS_LIVE or STATUS_SOON
     *  - badges    badge key => rule, checked server-side in progress::submit()
     *
     * A badge rule is a set of thresholds ANDed together:
     *  streak   longest run of correct answers in the round must be >= n
     *  correct  correct answers in the round must be >= n
     *  maxwrong wrong answers in the round must be <= n
     *
     * @return array<string, array>
     */
    public static function get_games(): array {
        return [
            // -- Numbers ------------------------------------------------------
            'math-race' => [
                'num' => 1, 'emoji' => '🔢', 'category' => 'numbers',
                'level' => 1, 'minutes' => '3-5', 'status' => self::STATUS_LIVE,
                'badges' => [
                    // "sharp calculator" - 10 correct in a row.
                    'fast-calculator' => ['streak' => 10],
                ],
            ],
            'math-catcher' => [
                'num' => 2, 'emoji' => '🧮', 'category' => 'numbers',
                'level' => 2, 'minutes' => '4-6', 'status' => self::STATUS_LIVE,
                'badges' => [
                    // "skilled hunter" - 20 correct with no mistake.
                    'sharp-hunter' => ['correct' => 20, 'maxwrong' => 0],
                ],
            ],
            'math-shop' => [
                'num' => 3, 'emoji' => '💰', 'category' => 'numbers',
                'level' => 2, 'minutes' => '4-6', 'status' => self::STATUS_SOON,
            ],

            // -- Letters and words --------------------------------------------
            'letter-order'  => ['num' => 4, 'emoji' => '🔤', 'category' => 'letters', 'level' => 1, 'minutes' => '3-5', 'status' => self::STATUS_SOON],
            'word-builder'  => ['num' => 5, 'emoji' => '🐝', 'category' => 'letters', 'level' => 2, 'minutes' => '4-6', 'status' => self::STATUS_SOON],
            'match-connect' => ['num' => 6, 'emoji' => '🧩', 'category' => 'letters', 'level' => 1, 'minutes' => '3-5', 'status' => self::STATUS_SOON],
            'crossword'     => ['num' => 7, 'emoji' => '🔡', 'category' => 'letters', 'level' => 2, 'minutes' => '5-8', 'status' => self::STATUS_SOON],
            'word-search'   => ['num' => 8, 'emoji' => '🔎', 'category' => 'letters', 'level' => 2, 'minutes' => '5-8', 'status' => self::STATUS_SOON],
            'speak-words'   => ['num' => 9, 'emoji' => '🎤', 'category' => 'letters', 'level' => 2, 'minutes' => '4-6', 'status' => self::STATUS_SOON],

            // -- Quiz ----------------------------------------------------------
            'quiz'          => ['num' => 10, 'emoji' => '🧠', 'category' => 'quiz', 'level' => 1, 'minutes' => '3-5', 'status' => self::STATUS_SOON],
            'true-false'    => ['num' => 11, 'emoji' => '✅', 'category' => 'quiz', 'level' => 1, 'minutes' => '3-5', 'status' => self::STATUS_SOON],
            'xo-quiz'       => ['num' => 12, 'emoji' => '⭕', 'category' => 'quiz', 'level' => 1, 'minutes' => '4-6', 'status' => self::STATUS_SOON],
            'target-answer' => ['num' => 13, 'emoji' => '🎯', 'category' => 'quiz', 'level' => 1, 'minutes' => '3-5', 'status' => self::STATUS_SOON],
            'balloon-pop'   => ['num' => 14, 'emoji' => '🎈', 'category' => 'quiz', 'level' => 1, 'minutes' => '3-5', 'status' => self::STATUS_SOON],
            'wheel'         => ['num' => 15, 'emoji' => '🏆', 'category' => 'quiz', 'level' => 1, 'minutes' => '4-6', 'status' => self::STATUS_SOON],
            'space-quiz'    => ['num' => 16, 'emoji' => '🚀', 'category' => 'quiz', 'level' => 2, 'minutes' => '4-6', 'status' => self::STATUS_SOON],
            'who-am-i'      => ['num' => 17, 'emoji' => '🐶', 'category' => 'quiz', 'level' => 1, 'minutes' => '3-5', 'status' => self::STATUS_SOON],

            // -- Memory and thinking -------------------------------------------
            'memory-cards'    => ['num' => 18, 'emoji' => '🃏', 'category' => 'memory', 'level' => 1, 'minutes' => '3-5', 'status' => self::STATUS_SOON],
            'puzzle'          => ['num' => 19, 'emoji' => '🧱', 'category' => 'memory', 'level' => 2, 'minutes' => '5-8', 'status' => self::STATUS_SOON],
            'find-difference' => ['num' => 20, 'emoji' => '🔍', 'category' => 'memory', 'level' => 2, 'minutes' => '4-6', 'status' => self::STATUS_SOON],
            'color-challenge' => ['num' => 21, 'emoji' => '🎨', 'category' => 'memory', 'level' => 1, 'minutes' => '3-5', 'status' => self::STATUS_SOON],

            // -- Motion ---------------------------------------------------------
            'runner' => ['num' => 22, 'emoji' => '🏃', 'category' => 'motion', 'level' => 2, 'minutes' => '4-6', 'status' => self::STATUS_SOON],

            // -- Worlds (built after the games, per the doc) ----------------------
            'knowledge-map' => ['num' => 23, 'emoji' => '🗺️', 'category' => 'worlds', 'level' => 3, 'minutes' => '8+', 'status' => self::STATUS_SOON],
            'adventure'     => ['num' => 24, 'emoji' => '🏰', 'category' => 'worlds', 'level' => 3, 'minutes' => '8+', 'status' => self::STATUS_SOON],
        ];
    }

    /**
     * One game, or null when the slug is unknown.
     *
     * @param string $id game slug
     * @return array|null
     */
    public static function get_game(string $id): ?array {
        $games = self::get_games();
        if (!isset($games[$id])) {
            return null;
        }
        $game = $games[$id];
        $game['id'] = $id;
        return $game;
    }

    /**
     * Whether this slug is a finished, playable game.
     *
     * @param string $id game slug
     * @return bool
     */
    public static function is_live(string $id): bool {
        $game = self::get_game($id);
        return $game !== null && $game['status'] === self::STATUS_LIVE;
    }

    /**
     * Lang-string suffix for a slug: dashes are not valid in string keys.
     *
     * @param string $id game slug, e.g. math-race
     * @return string e.g. math_race
     */
    public static function key(string $id): string {
        return str_replace('-', '_', $id);
    }
}
