<?php
namespace local_payments\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * Returns courses matching a field filter, each enriched with country-resolved
 * pricing. Mirrors the field/value API of core_course_get_courses_by_field so
 * the Flutter app can replace two round-trips (get courses + get price per
 * course) with a single call.
 *
 * Supported field values: id, ids (comma-separated), shortname, idnumber, category.
 */
class get_courses_with_pricing extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'field' => new \external_value(
                PARAM_ALPHA,
                'Field to filter by: id | ids | shortname | idnumber | category',
                VALUE_DEFAULT,
                ''
            ),
            'value' => new \external_value(
                PARAM_RAW,
                'Value for the filter field (for ids, comma-separated integers)',
                VALUE_DEFAULT,
                ''
            ),
            'country' => new \external_value(
                PARAM_ALPHA,
                'ISO-3166-1 alpha-2 country code from the app (overrides auto-detection)',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    public static function execute(string $field = '', string $value = '', string $country = ''): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'field'   => $field,
            'value'   => $value,
            'country' => $country,
        ]);

        self::validate_context(\context_system::instance());

        $app_country = !empty($params['country']) ? $params['country'] : null;
        $courses     = self::fetch_courses($params['field'], $params['value']);

        $result = [];
        foreach ($courses as $course) {
            // Skip the site course.
            if ((int) $course->id === SITEID) {
                continue;
            }

            $is_purchased = \local_payments\price_resolver::is_purchased($course->id, $USER->id);
            $is_enrolled  = \local_payments\enrollment_handler::is_enrolled($USER->id, $course->id);

            $pricing_data = [
                'country'             => '',
                'currency'            => '',
                'price'               => 0.0,
                'sale_price'          => 0.0,
                'original_price'      => 0.0,
                'discount_percentage' => 0,
                'is_sale_active'      => false,
                'sale_ends_at'        => 0,
                'is_free'             => true,
            ];

            if (\local_payments\price_resolver::has_pricing($course->id)) {
                try {
                    $pricing = \local_payments\price_resolver::resolve($course->id, $USER->id, $app_country);
                    $pricing_data = [
                        'country'             => $pricing->country,
                        'currency'            => $pricing->currency,
                        'price'               => $pricing->price,
                        'sale_price'          => $pricing->sale_price ?? 0.0,
                        'original_price'      => $pricing->original_price,
                        'discount_percentage' => $pricing->discount_pct,
                        'is_sale_active'      => $pricing->is_sale_active,
                        'sale_ends_at'        => (int) ($pricing->sale_ends_at ?? 0),
                        'is_free'             => false,
                    ];
                } catch (\moodle_exception $e) {
                    // No matching pricing row for this country — treat as free.
                }
            }

            $result[] = array_merge([
                'id'          => (int) $course->id,
                'fullname'    => $course->fullname,
                'shortname'   => $course->shortname,
                'summary'     => $course->summary ?? '',
                'categoryid'  => (int) $course->category,
                'visible'     => (bool) $course->visible,
                'image_url'   => self::get_course_image_url($course->id),
                'is_purchased' => $is_purchased,
                'is_enrolled'  => $is_enrolled,
            ], $pricing_data);
        }

        return ['courses' => $result];
    }

    public static function execute_returns(): \external_single_structure {
        $course_structure = new \external_single_structure([
            'id'                  => new \external_value(PARAM_INT,   'Course ID'),
            'fullname'            => new \external_value(PARAM_TEXT,  'Course full name'),
            'shortname'           => new \external_value(PARAM_TEXT,  'Course short name'),
            'summary'             => new \external_value(PARAM_RAW,   'Course summary'),
            'categoryid'          => new \external_value(PARAM_INT,   'Category ID'),
            'visible'             => new \external_value(PARAM_BOOL,  'Visible to students'),
            'image_url'           => new \external_value(PARAM_URL,   'Course image URL or empty', VALUE_DEFAULT, ''),
            'country'             => new \external_value(PARAM_ALPHA, 'Detected/resolved country code'),
            'currency'            => new \external_value(PARAM_ALPHA, 'Currency code'),
            'price'               => new \external_value(PARAM_FLOAT, 'Effective price (sale or original)'),
            'sale_price'          => new \external_value(PARAM_FLOAT, 'Sale price or 0'),
            'original_price'      => new \external_value(PARAM_FLOAT, 'Original price'),
            'discount_percentage' => new \external_value(PARAM_INT,   'Discount percentage'),
            'is_sale_active'      => new \external_value(PARAM_BOOL,  'Whether a sale is currently active'),
            'sale_ends_at'        => new \external_value(PARAM_INT,   'Sale end timestamp or 0'),
            'is_free'             => new \external_value(PARAM_BOOL,  'No active pricing — open access'),
            'is_purchased'        => new \external_value(PARAM_BOOL,  'User has a completed purchase'),
            'is_enrolled'         => new \external_value(PARAM_BOOL,  'User is enrolled'),
        ]);

        return new \external_single_structure([
            'courses' => new \external_multiple_structure($course_structure),
        ]);
    }

    // -------------------------------------------------------------------------

    private static function fetch_courses(string $field, string $value): array {
        global $DB;

        $select_fields = 'id, fullname, shortname, summary, category, visible';

        switch ($field) {
            case 'id':
                $id = clean_param($value, PARAM_INT);
                if (!$id) {
                    return [];
                }
                $record = $DB->get_record('course', ['id' => $id], $select_fields);
                return $record ? [$record] : [];

            case 'ids':
                $ids = array_filter(array_map('intval', explode(',', $value)));
                if (empty($ids)) {
                    return [];
                }
                list($in_sql, $in_params) = $DB->get_in_or_equal($ids);
                return array_values($DB->get_records_select('course', "id {$in_sql}", $in_params, 'sortorder ASC', $select_fields));

            case 'shortname':
                $sn = clean_param($value, PARAM_TEXT);
                $record = $DB->get_record('course', ['shortname' => $sn], $select_fields);
                return $record ? [$record] : [];

            case 'idnumber':
                $idn = clean_param($value, PARAM_TEXT);
                $record = $DB->get_record('course', ['idnumber' => $idn], $select_fields);
                return $record ? [$record] : [];

            case 'category':
                $catid = clean_param($value, PARAM_INT);
                if (!$catid) {
                    return [];
                }
                return array_values($DB->get_records('course', ['category' => $catid], 'sortorder ASC', $select_fields));

            default:
                // No field or unrecognised — return empty; caller should use a specific field.
                return [];
        }
    }

    private static function get_course_image_url(int $courseid): string {
        try {
            $context = \context_course::instance($courseid);
            $fs      = get_file_storage();
            $files   = $fs->get_area_files(
                $context->id, 'course', 'overviewfiles', false, 'filename', false
            );
            foreach ($files as $f) {
                $mime = $f->get_mimetype();
                if (strpos($mime, 'image/') === 0) {
                    return \moodle_url::make_pluginfile_url(
                        $f->get_contextid(),
                        $f->get_component(),
                        $f->get_filearea(),
                        null,
                        $f->get_filepath(),
                        $f->get_filename()
                    )->out(false);
                }
            }
        } catch (\Exception $e) {
            // Context may not exist for deleted/invisible courses — silently skip.
        }
        return '';
    }
}
