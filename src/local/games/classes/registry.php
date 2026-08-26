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
 * The whole 22-game plan from "docs/Games Doc/games.md" lives here from day one,
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
     *  - status    STATUS_LIVE or STATUS_SOON
     *  - badges    badge key => rule, checked server-side in progress::submit()
     *
     * A badge rule is a set of thresholds ANDed together:
     *  streak   longest run of correct answers in the round must be >= n
     *  correct  correct answers in the round must be >= n
     *  maxwrong wrong answers in the round must be <= n
     *  goal     the game's own goal, however it counts it, must be >= n
     *
     * @return array<string, array>
     */
    public static function get_games(): array {
        return [
            // -- Numbers ------------------------------------------------------
            'math-race' => [
                'num' => 1, 'emoji' => '🔢', 'category' => 'numbers',
                'level' => 1, 'status' => self::STATUS_LIVE,
                'badges' => [
                    // "sharp calculator" - 10 correct in a row.
                    'fast-calculator' => ['streak' => 10],
                ],
            ],
            'math-catcher' => [
                'num' => 2, 'emoji' => '🧮', 'category' => 'numbers',
                'level' => 2, 'status' => self::STATUS_LIVE,
                'badges' => [
                    // "skilled hunter" - 20 correct with no mistake.
                    'sharp-hunter' => ['correct' => 20, 'maxwrong' => 0],
                ],
            ],
            'math-shop' => [
                'num' => 3, 'emoji' => '💰', 'category' => 'numbers',
                'level' => 2, 'status' => self::STATUS_LIVE,
                'badges' => [
                    // "smart shopper" - 5 purchases, both sums right each time.
                    'smart-shopper' => ['correct' => 10, 'maxwrong' => 0],
                ],
            ],

            // -- Letters and words --------------------------------------------
            'letter-order' => [
                'num' => 4, 'emoji' => '🔤', 'category' => 'letters',
                'level' => 1, 'status' => self::STATUS_LIVE,
                'badges' => [
                    // "letter king" - 15 words spelled right.
                    'letter-king' => ['correct' => 15],
                ],
            ],
            'word-builder' => [
                'num' => 5, 'emoji' => '🐝', 'category' => 'letters',
                'level' => 2, 'status' => self::STATUS_LIVE,
                'badges' => [
                    // "word builder" - 10 words in one round. The rule counts
                    // words, not points: a long word scores more but still
                    // reports one correct event.
                    'word-builder' => ['correct' => 10],
                ],
            ],
            'match-connect' => [
                'num' => 6, 'emoji' => '🧩', 'category' => 'letters',
                'level' => 1, 'status' => self::STATUS_LIVE,
                'badges' => [
                    // "sharp eye" - every board of the round with no mistake.
                    'sharp-eye' => ['correct' => 16, 'maxwrong' => 0],
                ],
            ],
            'crossword' => [
                'num' => 7, 'emoji' => '🔡', 'category' => 'letters',
                'level' => 2, 'status' => self::STATUS_LIVE,
                'badges' => [
                    // "puzzle solver" - the whole grid with no wrong word.
                    'puzzle-solver' => ['correct' => 5, 'maxwrong' => 0],
                ],
            ],
            'word-search' => [
                'num' => 8, 'emoji' => '🔎', 'category' => 'letters',
                'level' => 2, 'status' => self::STATUS_LIVE,
                'badges' => [
                    // "falcon eye" - every hidden word found.
                    'falcon-eye' => ['correct' => 6],
                ],
            ],
            'speak-words' => [
                'num' => 9, 'emoji' => '🎤', 'category' => 'letters',
                'level' => 2, 'status' => self::STATUS_LIVE,
                'badges' => [
                    // "clear voice" - 10 words said correctly.
                    'clear-voice' => ['correct' => 10],
                ],
            ],

            // -- Quiz ----------------------------------------------------------
            // Every game in this section is built on the same question bank,
            // which is why the doc calls game 10 the important one.
            'quiz' => [
                'num' => 10, 'emoji' => '🧠', 'category' => 'quiz',
                'level' => 1, 'status' => self::STATUS_LIVE,
                'badges' => ['know-it-all' => ['correct' => 20]],
            ],
            'true-false' => [
                'num' => 11, 'emoji' => '✅', 'category' => 'quiz',
                'level' => 1, 'status' => self::STATUS_LIVE,
                'badges' => ['focused' => ['streak' => 15]],
            ],
            'xo-quiz' => [
                'num' => 12, 'emoji' => '⭕', 'category' => 'quiz',
                'level' => 1, 'status' => self::STATUS_LIVE,
                // Three matches won - the round is three matches long.
                'badges' => ['xo-champion' => ['goal' => 3]],
            ],
            'target-answer' => [
                'num' => 13, 'emoji' => '🎯', 'category' => 'quiz',
                'level' => 1, 'status' => self::STATUS_LIVE,
                'badges' => ['sharp-shot' => ['streak' => 10]],
            ],
            'balloon-pop' => [
                'num' => 14, 'emoji' => '🎈', 'category' => 'quiz',
                'level' => 1, 'status' => self::STATUS_LIVE,
                'badges' => ['pop-master' => ['correct' => 25]],
            ],
            'wheel' => [
                'num' => 15, 'emoji' => '🏆', 'category' => 'quiz',
                'level' => 1, 'status' => self::STATUS_LIVE,
                // One right answer from each of the wheel's four topics.
                'badges' => ['encyclopedia' => ['goal' => 4]],
            ],
            'space-quiz' => [
                'num' => 16, 'emoji' => '🚀', 'category' => 'quiz',
                'level' => 2, 'status' => self::STATUS_LIVE,
                // Reaching the last planet.
                'badges' => ['astronaut' => ['goal' => 1]],
            ],
            'who-am-i' => [
                'num' => 17, 'emoji' => '🐶', 'category' => 'quiz',
                'level' => 1, 'status' => self::STATUS_LIVE,
                // Five answers guessed from the first clue alone.
                'badges' => ['good-detective' => ['goal' => 5]],
            ],

            // -- Memory and thinking -------------------------------------------
            'memory-cards' => [
                'num' => 18, 'emoji' => '🃏', 'category' => 'memory',
                'level' => 1, 'status' => self::STATUS_LIVE,
                // A full board cleared in under twenty flips.
                'badges' => ['strong-memory' => ['goal' => 1]],
            ],
            'puzzle' => [
                'num' => 19, 'emoji' => '🧱', 'category' => 'memory',
                'level' => 2, 'status' => self::STATUS_LIVE,
                // Finishing the sixteen-piece board.
                'badges' => ['picture-builder' => ['goal' => 1]],
            ],
            'find-difference' => [
                'num' => 20, 'emoji' => '🔍', 'category' => 'memory',
                'level' => 2, 'status' => self::STATUS_LIVE,
                'badges' => ['detective-eye' => ['correct' => 15, 'maxwrong' => 0]],
            ],
            'color-challenge' => [
                'num' => 21, 'emoji' => '🎨', 'category' => 'memory',
                'level' => 1, 'status' => self::STATUS_LIVE,
                // Every colour on the board answered correctly.
                'badges' => ['little-artist' => ['goal' => 1]],
            ],

            // -- Motion ---------------------------------------------------------
            'runner' => [
                'num' => 22, 'emoji' => '🏃', 'category' => 'motion',
                'level' => 2, 'status' => self::STATUS_LIVE,
                // A whole stage without losing a heart.
                'badges' => ['fast-runner' => ['goal' => 1]],
            ],

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
