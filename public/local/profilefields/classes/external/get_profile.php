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
use core_external\external_warnings;
use local_profilefields\profile_api;

defined('MOODLE_INTERNAL') || die();

/**
 * The profile page, for a client that cannot render it.
 *
 * The replacement for `core_user_get_users_by_field` on the profile screen.
 * That function answers "tell me about this user" for a directory listing: a
 * fixed handful of columns, custom fields filtered by what the *caller* may
 * see, and nothing at all of what `/user/profile.php` actually puts on the
 * page. This one answers "show me this profile", and returns the page's own
 * category/node tree alongside the fields, so the app shows what the website
 * shows.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_profile extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT,
                'Whose profile to read. 0 (the default) means the calling user.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Read the profile.
     *
     * @param int $userid whose profile to read, 0 for the caller's own
     * @return array
     */
    public static function execute($userid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), ['userid' => $userid]);

        $user = profile_api::get_user((int) $params['userid']);

        // The context every format_text()/format_string() in the payload runs in,
        // and the one the capability checks below are asked about.
        self::validate_context(context_user::instance($user->id));

        profile_api::require_can_view($user);

        return profile_api::view($user);
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'User id'),
            'fullname' => new external_value(PARAM_NOTAGS, 'The name the site displays for this user'),
            'firstname' => new external_value(PARAM_NOTAGS, 'First name'),
            'lastname' => new external_value(PARAM_NOTAGS, 'Last name'),
            'username' => new external_value(PARAM_RAW, 'Username - only returned for the caller\'s own profile'),
            'email' => new external_value(PARAM_RAW,
                'Email address, or "" when this viewer is not allowed to see it'),
            'city' => new external_value(PARAM_NOTAGS, 'Home city'),
            'country' => new external_value(PARAM_RAW, 'Home country code (ISO alpha-2)'),
            'countryname' => new external_value(PARAM_NOTAGS, 'The country name, already localised'),
            'timezone' => new external_value(PARAM_RAW, 'The timezone in force for this user'),
            'lang' => new external_value(PARAM_RAW, 'Preferred language'),
            'description' => new external_value(PARAM_RAW,
                'The "About me" text, formatted and with file urls resolved - ready to render'),
            'descriptionformat' => new external_value(PARAM_INT, 'The description format (FORMAT_* constant)'),
            'interests' => new external_value(PARAM_RAW, 'Interests, comma separated'),
            'profileimageurl' => new external_value(PARAM_URL, 'Profile picture, large'),
            'profileimageurlsmall' => new external_value(PARAM_URL, 'Profile picture, small'),
            'firstaccess' => new external_value(PARAM_INT, 'First access to the site, as a unix timestamp'),
            'lastaccess' => new external_value(PARAM_INT, 'Last access to the site, as a unix timestamp'),
            'canedit' => new external_value(PARAM_BOOL,
                'True when this caller may edit the profile - show the Edit button only then'),
            'editurl' => new external_value(PARAM_URL,
                'The web edit page, for a client that would rather open it in a browser'),
            'customfields' => new external_multiple_structure(
                new external_single_structure([
                    'shortname' => new external_value(PARAM_RAW, 'Field shortname'),
                    'name' => new external_value(PARAM_RAW, 'The label the site shows, already localised'),
                    'datatype' => new external_value(PARAM_RAW, 'text, textarea, menu, checkbox, datetime, phone, ...'),
                    'value' => new external_value(PARAM_RAW,
                        'The stored value - this is what local_profilefields_update_profile takes back'),
                    'displayvalue' => new external_value(PARAM_RAW, 'The value as the profile page prints it'),
                    'categoryname' => new external_value(PARAM_RAW, 'The category the field belongs to'),
                ]), 'The custom profile fields this viewer is allowed to see.'
            ),
            'categories' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_RAW, 'Category name (an id, not for display)'),
                    'title' => new external_value(PARAM_RAW, 'The heading the page shows'),
                    'nodes' => new external_multiple_structure(
                        new external_single_structure([
                            'name' => new external_value(PARAM_RAW, 'Node name (an id, not for display)'),
                            'title' => new external_value(PARAM_RAW, 'The text of the row'),
                            'content' => new external_value(PARAM_RAW, 'Extra content under the row, when any'),
                            'url' => new external_value(PARAM_RAW, 'Where the row links to, "" when it is not a link'),
                            'classes' => new external_value(PARAM_RAW, 'CSS classes the web page puts on the row'),
                        ]), 'The rows in this section.'
                    ),
                ]), 'The profile page itself, section by section - the same tree /user/profile.php renders.'
            ),
            'warnings' => new external_warnings(),
        ]);
    }
}
