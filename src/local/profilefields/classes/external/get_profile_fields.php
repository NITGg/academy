<?php
namespace local_profilefields\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class get_profile_fields extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([]);
    }

    public static function execute(): array {
        global $DB;

        self::validate_context(\context_system::instance());

        $fields = $DB->get_records_sql("
            SELECT f.id, f.shortname, f.name, f.datatype, f.description, f.required,
                   f.visible, f.param1, f.defaultdata, f.categoryid, c.name AS categoryname
              FROM {user_info_field} f
              JOIN {user_info_category} c ON c.id = f.categoryid
          ORDER BY c.sortorder, f.sortorder
        ");

        $result = [];
        foreach ($fields as $field) {
            $options = [];
            if ($field->datatype === 'menu' && !empty($field->param1)) {
                foreach (explode("\n", $field->param1) as $opt) {
                    $opt = trim($opt);
                    if ($opt !== '') {
                        $options[] = $opt;
                    }
                }
            }

            $result[] = [
                'id'           => (int) $field->id,
                'shortname'    => $field->shortname,
                'name'         => $field->name,
                'datatype'     => $field->datatype,
                'description'  => $field->description ?? '',
                'required'     => (bool) $field->required,
                'visible'      => (int) $field->visible,
                'defaultvalue' => $field->defaultdata ?? '',
                'categoryid'   => (int) $field->categoryid,
                'categoryname' => $field->categoryname,
                'options'      => $options,
            ];
        }

        return $result;
    }

    public static function execute_returns(): \external_multiple_structure {
        return new \external_multiple_structure(
            new \external_single_structure([
                'id'           => new \external_value(PARAM_INT,  'Field ID'),
                'shortname'    => new \external_value(PARAM_TEXT, 'Field shortname (use this when creating users)'),
                'name'         => new \external_value(PARAM_TEXT, 'Display name'),
                'datatype'     => new \external_value(PARAM_TEXT, 'Field type: text, textarea, menu, checkbox, datetime'),
                'description'  => new \external_value(PARAM_RAW,  'Field description', VALUE_OPTIONAL),
                'required'     => new \external_value(PARAM_BOOL, 'Whether the field is required on signup'),
                'visible'      => new \external_value(PARAM_INT,  '0=hidden, 1=hidden in profile, 2=visible'),
                'defaultvalue' => new \external_value(PARAM_RAW,  'Default value', VALUE_OPTIONAL),
                'categoryid'   => new \external_value(PARAM_INT,  'Category ID'),
                'categoryname' => new \external_value(PARAM_TEXT, 'Category name'),
                'options'      => new \external_multiple_structure(
                    new \external_value(PARAM_TEXT, 'Option value'),
                    'Valid options for menu fields, empty for other types'
                ),
            ])
        );
    }
}
