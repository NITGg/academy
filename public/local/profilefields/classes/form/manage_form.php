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

namespace local_profilefields\form;

use html_writer;
use local_profilefields\custom_fields;
use local_profilefields\manager;
use moodle_url;
use moodleform;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * One page that answers "which fields does a new account fill in, and which does
 * an existing account see".
 *
 * Core fields and custom fields are asked the same question in the same words,
 * even though they are stored in completely different places - plugin config for
 * the first, `user_info_field` rows for the second.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_form extends moodleform {

    /**
     * Build the page.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        $this->define_username_section($mform);
        $this->define_core_section($mform);
        $this->define_custom_section($mform);

        $this->add_action_buttons(false, get_string('savechanges'));
    }

    /**
     * The "username comes from the email address" switch.
     *
     * @param \MoodleQuickForm $mform the form being defined
     * @return void
     */
    protected function define_username_section(\MoodleQuickForm $mform): void {
        $mform->addElement('header', 'usernamehdr', get_string('usernameheading', 'local_profilefields'));
        $mform->setExpanded('usernamehdr', true);

        $mform->addElement('static', 'usernameintro', '',
            get_string('usernameintro', 'local_profilefields'));

        $mform->addElement('selectyesno', 'usernamefromemail',
            get_string('usernamefromemail', 'local_profilefields'));
        $mform->addHelpButton('usernamefromemail', 'usernamefromemail', 'local_profilefields');

        $mform->addElement('select', 'usernamesource', get_string('usernamesource', 'local_profilefields'), [
            manager::USERNAME_EMAIL     => get_string('usernamesourceemail', 'local_profilefields'),
            manager::USERNAME_LOCALPART => get_string('usernamesourcelocalpart', 'local_profilefields'),
        ]);
        $mform->addHelpButton('usernamesource', 'usernamesource', 'local_profilefields');
        $mform->hideIf('usernamesource', 'usernamefromemail', 'eq', 0);
    }

    /**
     * One row per built-in Moodle field.
     *
     * @param \MoodleQuickForm $mform the form being defined
     * @return void
     */
    protected function define_core_section(\MoodleQuickForm $mform): void {
        $modelabels = self::mode_labels();

        $mform->addElement('header', 'corehdr', get_string('corefieldsheading', 'local_profilefields'));
        $mform->setExpanded('corehdr', true);

        $mform->addElement('static', 'coreintro', '',
            get_string('corefieldsintro', 'local_profilefields'));

        foreach (manager::core_fields() as $name => $meta) {
            $group = [];

            if (count($meta['modes']) > 1) {
                $options = [];
                foreach ($meta['modes'] as $mode) {
                    $options[$mode] = $modelabels[$mode];
                }
                $group[] = $mform->createElement('select', 'mode_' . $name, '', $options);
            } else {
                $group[] = $mform->createElement('static', 'modefixed_' . $name, '',
                    html_writer::span($modelabels[$meta['modes'][0]], 'text-muted'));
            }

            if (!empty($meta['canrequire'])) {
                $group[] = $mform->createElement('advcheckbox', 'required_' . $name, '',
                    get_string('required'), ['class' => 'ms-2']);
            }

            // Label and position only mean anything where we can actually rebuild the
            // form - the sign-up page. The profile edit form is left as core draws it.
            if (!empty($meta['onsignup'])) {
                $group[] = $mform->createElement('text', 'label_' . $name, '', [
                    'size'        => 18,
                    'placeholder' => get_string('labeloverrideplaceholder', 'local_profilefields'),
                ]);
                $group[] = $mform->createElement('text', 'order_' . $name, '', [
                    'size'        => 3,
                    'placeholder' => get_string('orderplaceholder', 'local_profilefields'),
                ]);
            }

            $mform->addGroup($group, 'row_' . $name, self::core_row_label($name, $meta), ' ', false);
            $mform->setType('label_' . $name, PARAM_TEXT);
            $mform->setType('order_' . $name, PARAM_INT);
        }
    }

    /**
     * One row per custom profile field, grouped by profile field category.
     *
     * @param \MoodleQuickForm $mform the form being defined
     * @return void
     */
    protected function define_custom_section(\MoodleQuickForm $mform): void {
        $fields = custom_fields::get_all();
        $modeoptions = custom_fields::mode_options();
        $visibilityoptions = custom_fields::visibility_options();

        $mform->addElement('header', 'customhdr', get_string('customfieldsheading', 'local_profilefields'));
        $mform->setExpanded('customhdr', true);

        $mform->addElement('static', 'customintro', '',
            get_string('customfieldsintro', 'local_profilefields') . ' ' . self::add_field_links());

        if (empty($fields)) {
            $mform->addElement('static', 'customnone', '',
                html_writer::span(get_string('customfieldsnone', 'local_profilefields'), 'text-muted'));
            return;
        }

        $currentcategory = null;
        foreach ($fields as $field) {
            if ($currentcategory !== $field->categoryid) {
                $currentcategory = $field->categoryid;
                $mform->addElement('static', 'cfcat_' . $field->categoryid, '',
                    html_writer::tag('strong', format_string($field->categoryname)));
            }

            $group = [];
            $group[] = $mform->createElement('select', 'cfmode_' . $field->id, '', $modeoptions);
            $group[] = $mform->createElement('select', 'cfvisible_' . $field->id, '', $visibilityoptions);
            $group[] = $mform->createElement('advcheckbox', 'cfrequired_' . $field->id, '',
                get_string('required'), ['class' => 'ms-2']);
            $group[] = $mform->createElement('advcheckbox', 'cflocked_' . $field->id, '',
                get_string('profilelocked', 'admin'), ['class' => 'ms-2']);
            $group[] = $mform->createElement('static', 'cflinks_' . $field->id, '',
                self::custom_field_links($field));

            $mform->addGroup($group, 'cfrow_' . $field->id, self::custom_row_label($field), ' ', false);

            // A field nobody can see cannot be on the sign-up page, so the audience
            // menu is only a question while the field is visible at all.
            $mform->hideIf('cfvisible_' . $field->id, 'cfmode_' . $field->id, 'eq', manager::MODE_HIDDEN);
        }
    }

    /**
     * Current settings, flattened into the element names used above.
     *
     * @return void
     */
    public function load_current_values(): void {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $config = manager::get_config();

        $data = [
            'usernamefromemail' => manager::username_from_email() ? 1 : 0,
            'usernamesource'    => manager::username_source(),
        ];

        foreach (manager::core_fields() as $name => $meta) {
            $data['mode_' . $name] = $config[$name]['mode'];
            $data['required_' . $name] = !empty($config[$name]['required']) ? 1 : 0;
            $data['label_' . $name] = $config[$name]['label'];
            $data['order_' . $name] = $config[$name]['order'];
        }

        foreach (custom_fields::get_all() as $field) {
            $data['cfmode_' . $field->id] = custom_fields::mode($field);
            // A hidden field keeps its old audience in the menu, so switching it back
            // on does not silently reset who could see it.
            $data['cfvisible_' . $field->id] = (int) $field->visible ?: (int) PROFILE_VISIBLE_ALL;
            $data['cfrequired_' . $field->id] = (int) $field->required;
            $data['cflocked_' . $field->id] = (int) $field->locked;
        }

        $this->set_data($data);
    }

    /**
     * The row heading for a core field: its name, plus the database column it writes.
     *
     * @param string $name core field name
     * @param array $meta the registry entry for that field
     * @return string HTML
     */
    protected static function core_row_label(string $name, array $meta): string {
        $label = get_string($meta['label'], $meta['labelcomponent'] ?? 'moodle');

        return html_writer::span(s($label), 'fw-semibold') . ' ' .
            html_writer::span(s($name), 'text-muted small');
    }

    /**
     * The row heading for a custom field: its name, shortname and type.
     *
     * @param stdClass $field a user_info_field record
     * @return string HTML
     */
    protected static function custom_row_label(stdClass $field): string {
        $datatypes = custom_fields::datatypes();
        $type = $datatypes[$field->datatype] ?? $field->datatype;

        return html_writer::span(format_string($field->name), 'fw-semibold') . ' ' .
            html_writer::span(s($field->shortname) . ' &middot; ' . s($type), 'text-muted small');
    }

    /**
     * Edit / delete / reorder links, pointing at the core screens that own them.
     *
     * There is no reason to reimplement field editing here; this page is about
     * placement, and hands the rest back to `user/profile/index.php`.
     *
     * @param stdClass $field a user_info_field record
     * @return string HTML
     */
    protected static function custom_field_links(stdClass $field): string {
        $edit = new moodle_url('/user/profile/index.php', ['id' => $field->id, 'action' => 'editfield']);
        $delete = new moodle_url('/user/profile/index.php', [
            'id' => $field->id, 'action' => 'deletefield', 'sesskey' => sesskey(),
        ]);

        return html_writer::span(
            html_writer::link($edit, get_string('edit')) . ' &middot; ' .
            html_writer::link($delete, get_string('delete')),
            'ms-2 small'
        );
    }

    /**
     * "New field" / "New category" links back to the core profile fields screen.
     *
     * @return string HTML
     */
    protected static function add_field_links(): string {
        $links = [];
        foreach (custom_fields::datatypes() as $datatype => $name) {
            $url = new moodle_url('/user/profile/index.php', [
                'action' => 'editfield', 'datatype' => $datatype,
            ]);
            $links[] = html_writer::link($url, s($name));
        }

        $category = new moodle_url('/user/profile/index.php', ['action' => 'editcategory']);

        return html_writer::span(
            get_string('createnewfield', 'local_profilefields') . ' ' . implode(', ', $links) .
            ' &nbsp;|&nbsp; ' . html_writer::link($category, get_string('profilecreatecategory', 'admin')),
            'small'
        );
    }

    /**
     * Human-readable names for the MODE_* values.
     *
     * @return array MODE_* value => label
     */
    protected static function mode_labels(): array {
        return [
            manager::MODE_BOTH    => get_string('modeboth', 'local_profilefields'),
            manager::MODE_SIGNUP  => get_string('modesignup', 'local_profilefields'),
            manager::MODE_PROFILE => get_string('modeprofile', 'local_profilefields'),
            manager::MODE_HIDDEN  => get_string('modehidden', 'local_profilefields'),
        ];
    }

    /**
     * Store everything the page collected.
     *
     * @param stdClass $data submitted form data
     * @return void
     */
    public static function save(stdClass $data): void {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        require_once($CFG->dirroot . '/user/profile/definelib.php');

        set_config('usernamefromemail', empty($data->usernamefromemail) ? 0 : 1, manager::COMPONENT);
        set_config('usernamesource', $data->usernamesource ?? manager::USERNAME_EMAIL, manager::COMPONENT);

        $config = manager::get_config();
        foreach (manager::core_fields() as $name => $meta) {
            if (count($meta['modes']) > 1 && isset($data->{'mode_' . $name})
                    && in_array($data->{'mode_' . $name}, $meta['modes'], true)) {
                $config[$name]['mode'] = $data->{'mode_' . $name};
            }
            if (!empty($meta['canrequire'])) {
                $config[$name]['required'] = empty($data->{'required_' . $name}) ? 0 : 1;
            }
            if (!empty($meta['onsignup'])) {
                $config[$name]['label'] = trim((string) ($data->{'label_' . $name} ?? ''));
                $config[$name]['order'] = (int) ($data->{'order_' . $name} ?? $config[$name]['order']);
            }
        }
        manager::save_config($config);

        $changed = false;
        foreach (custom_fields::get_all() as $field) {
            $mode = $data->{'cfmode_' . $field->id} ?? null;
            if ($mode === null) {
                continue;
            }
            $changed = custom_fields::apply(
                $field,
                $mode,
                (int) ($data->{'cfvisible_' . $field->id} ?? $field->visible),
                (int) ($data->{'cfrequired_' . $field->id} ?? 0),
                (int) ($data->{'cflocked_' . $field->id} ?? 0)
            ) || $changed;
        }
        if ($changed) {
            profile_purge_user_fields_cache();
        }
    }
}
