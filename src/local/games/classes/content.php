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
 * What the games are actually made of.
 *
 * The word bank, the question bank and the rest live in the language pack
 * rather than in code, so translating the corner also translates the material
 * the games are built from. This class is the one place that turns those
 * pipe-delimited tables into rows.
 *
 * It exists because two front ends read them now: play.php for the browser and
 * the external functions for the mobile app. A second copy of the parsing would
 * be a second place for the tables to drift.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content {

    /**
     * Turn one of the pipe-delimited lang-string tables into rows.
     *
     * Blank lines and short rows are dropped instead of throwing: a typo in a
     * translation must never take a game down.
     *
     * @param string $key lang string holding the table
     * @param int $columns how many fields each row must have
     * @param int|null $max the most fields to keep; defaults to $columns. Rows may
     *        carry fewer than this and more than $columns - a quiz question with
     *        two wrong answers is as valid as one with three.
     * @return array[] one array per row
     */
    public static function parse_table(string $key, int $columns, ?int $max = null): array {
        $rows = [];
        $max = $max ?? $columns;

        // The line break is spelled out rather than written as \R on purpose.
        // Without the /u modifier PCRE walks bytes, and in that mode \R also
        // matches the single byte 0x85 - which is the second byte of the Arabic
        // letter meem (U+0645, D9 85) and of every other Arabic letter ending in
        // 85. The bank came back cut through the middle of its meems, and the
        // resulting half characters made json_encode fail outright, taking every
        // game's config with it. This pattern can only ever match ASCII breaks.
        foreach (preg_split('/\r\n|\r|\n/', get_string($key, 'local_games')) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < $columns) {
                continue;
            }
            $row = array_slice($parts, 0, $max);
            // Pad short rows so every caller can index by position without checking.
            while (count($row) < $max) {
                $row[] = '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * The picture words: word|emoji|clue.
     *
     * @return array[] [{word, emoji, clue}]
     */
    public static function words(): array {
        return array_map(static function (array $row): array {
            return ['word' => $row[0], 'emoji' => $row[1], 'clue' => $row[2]];
        }, self::parse_table('wordbank', 3));
    }

    /**
     * The shop shelf: emoji|name.
     *
     * @return array[] [{emoji, name}]
     */
    public static function shopitems(): array {
        return array_map(static function (array $row): array {
            return ['emoji' => $row[0], 'name' => $row[1]];
        }, self::parse_table('shopitems', 2));
    }

    /**
     * The question bank six games are built on:
     * topic|question|right|wrong[|wrong][|wrong].
     *
     * @return array[] [{topic, question, answer, wrong[]}]
     */
    public static function quiz(): array {
        return array_map(static function (array $row): array {
            return [
                'topic'    => $row[0],
                'question' => $row[1],
                'answer'   => $row[2],
                'wrong'    => array_values(array_filter(array_slice($row, 3), static function (string $option): bool {
                    return $option !== '';
                })),
            ];
        }, self::parse_table('quizbank', 4, 6));
    }

    /**
     * True or False: statement|1 or 0|why.
     *
     * @return array[] [{text, true, why}]
     */
    public static function truefalse(): array {
        return array_map(static function (array $row): array {
            return ['text' => $row[0], 'true' => $row[1] === '1', 'why' => $row[2]];
        }, self::parse_table('tfbank', 3));
    }

    /**
     * Who Am I: answer|emoji|clue|clue|clue.
     *
     * @return array[] [{answer, emoji, clues[]}]
     */
    public static function whoami(): array {
        return array_map(static function (array $row): array {
            return ['answer' => $row[0], 'emoji' => $row[1], 'clues' => array_slice($row, 2)];
        }, self::parse_table('whoami', 5));
    }

    /**
     * Colour Challenge: name|hex.
     *
     * @return array[] [{name, hex}]
     */
    public static function colours(): array {
        return array_map(static function (array $row): array {
            return ['name' => $row[0], 'hex' => $row[1]];
        }, self::parse_table('colourbank', 2));
    }

    /**
     * The wider vocabulary Word Builder validates against - whitespace
     * separated, across as many lines as the translator finds readable.
     *
     * @return string[]
     */
    public static function wordlist(): array {
        return array_values(array_filter(
            preg_split('/\s+/', get_string('wordlist', 'local_games')),
            static function (string $word): bool {
                return $word !== '';
            }
        ));
    }

    /**
     * Every string the game side needs, in one bag, with the js_ prefix off.
     *
     * The games never build a sentence out of fragments - each message is its
     * own string, so a translator can move the words around freely.
     *
     * @return array<string, string>
     */
    public static function strings(): array {
        $out = [];
        foreach (get_string_manager()->load_component_strings('local_games', current_language()) as $key => $value) {
            if (strpos($key, 'js_') === 0) {
                $out[substr($key, 3)] = $value;
            }
        }
        $out['playagain'] = get_string('playagain', 'local_games');
        $out['backtohub'] = get_string('backtohub', 'local_games');

        return $out;
    }

    /**
     * Whether numbers should be shown in Arabic-Indic digits.
     *
     * The maths itself always runs on real numbers; this is presentation only.
     *
     * @return bool
     */
    public static function arabic_digits(): bool {
        return strpos(current_language(), 'ar') === 0;
    }
}
