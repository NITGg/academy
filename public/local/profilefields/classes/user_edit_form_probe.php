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

namespace local_profilefields;

use MoodleQuickForm;

defined('MOODLE_INTERNAL') || die();

/**
 * The profile edit form, with its element list readable from outside.
 *
 * `profile_api::describe()` answers "what does /user/edit.php look like for this
 * user right now" by building the real form and reading it, rather than by
 * re-implementing the layout a second time. `moodleform` keeps its
 * `MoodleQuickForm` protected, so this subclass - which changes nothing else -
 * is the accessor.
 *
 * `finalise()` matters as much as the accessor: `definition_after_data()` is
 * where core applies the auth-plugin field locks (`hardFreeze` + `setConstant`)
 * and where each custom profile field gets its own locking and default handling.
 * A form read before that step would tell a client a locked field is editable.
 *
 * The parent lives in `user/edit_form.php` and is not autoloadable, so this
 * class must only be referenced after that file has been required - which
 * `profile_api::require_libs()` does.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_edit_form_probe extends \user_edit_form {

    /**
     * The underlying QuickForm, with every core box, custom profile field and
     * auth-plugin lock already applied.
     *
     * @return MoodleQuickForm
     */
    public function get_quickform(): MoodleQuickForm {
        return $this->_form;
    }

    /**
     * Run `definition_after_data()` once, exactly as displaying the form would.
     *
     * @return void
     */
    public function finalise(): void {
        if (!$this->_definition_finalized) {
            $this->_definition_finalized = true;
            $this->definition_after_data();
        }
    }
}
