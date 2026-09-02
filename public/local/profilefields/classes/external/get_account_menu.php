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

namespace local_profilefields\external;

use context_user;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_profilefields\account;
use local_profilefields\account_api;

defined('MOODLE_INTERNAL') || die();

/**
 * The account screen's own navigation, as data.
 *
 * The list down the left of `/local/profilefields/account.php`, which is not a
 * fixed list: two of its entries appear only when the plugin behind them is
 * installed, and all of them are localised. An app that hard-codes the six
 * entries will show a Certificates tab on a site with no certificates.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_account_menu extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'active' => new external_value(PARAM_ALPHANUMEXT,
                'Which entry to mark as current: profile, security, mylearning, certificates, '
                . 'invoices or delete.', VALUE_DEFAULT, account::SECTION_PROFILE),
        ]);
    }

    /**
     * The navigation entries, in the order the screen lists them.
     *
     * @param string $active the key of the entry to mark current
     * @return array
     */
    public static function execute($active = account::SECTION_PROFILE): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['active' => $active]);

        self::validate_context(context_user::instance($USER->id));

        return [
            'items' => account_api::menu((string) $params['active']),
            'warnings' => [],
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'key' => new external_value(PARAM_ALPHANUMEXT,
                        'Stable id for the entry - branch on this, never on the label.'),
                    'label' => new external_value(PARAM_RAW, 'The wording to show, already localised.'),
                    'url' => new external_value(PARAM_URL,
                        'Where the web screen sends the browser. An app routes to its own screen instead.'),
                    'active' => new external_value(PARAM_BOOL, 'True for the entry asked for in `active`.'),
                    'danger' => new external_value(PARAM_BOOL,
                        'True for the entry that destroys something - drawn apart from the rest, in red.'),
                ]), 'The entries, in order.'
            ),
            'warnings' => new \core_external\external_warnings(),
        ]);
    }
}
