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
 * How a learner gets to the Games Corner.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Put "Games Corner" in the gear menu.
 *
 * The gear the theme draws in the page header is
 * {@see core_renderer::context_header_settings_menu()}, and on the site front
 * page it is built from the settings-navigation node keyed `frontpage`. So
 * adding a child there is what puts an entry in the gear - there is no separate
 * "gear" API.
 *
 * Two things make this reach a child rather than only an administrator:
 *
 *  - `frontpage` is created for everyone who loads the front page, not only for
 *    users who can edit it, and an entry with an action is never pruned. A
 *    student with no front-page rights normally sees no gear at all; with this
 *    node they get one, holding this single link.
 *  - The capability check is `local/games:play`, which the `user` archetype
 *    holds. Every logged-in account passes it.
 *
 * The hook itself runs last in {@see settings_navigation::initialise()}, after
 * the front-page and course nodes have been built, so `find()` can rely on
 * them being there.
 *
 * @param settings_navigation $settingsnav
 * @param context $context the context the page is in
 * @return void
 */
function local_games_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    // The corner lives at the system level, so that is where the capability is
    // held - not in whatever course the page happens to be about.
    if (!has_capability('local/games:play', context_system::instance())) {
        return;
    }

    $parent = $settingsnav->find('frontpage', navigation_node::TYPE_SETTING);
    if (!$parent) {
        // Not a front-page gear. Leaving it here rather than falling back to
        // the course gear is deliberate: the corner has nothing to do with any
        // one course, and an entry in every course's settings menu is noise.
        return;
    }

    $parent->add(
        get_string('pluginname', 'local_games'),
        new moodle_url('/local/games/index.php'),
        navigation_node::TYPE_SETTING,
        null,
        'local_games',
        new pix_icon('i/competencies', '')
    );
}
