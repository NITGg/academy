<?php
namespace local_payments\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir  . '/externallib.php');
require_once($CFG->dirroot . '/course/externallib.php');

/**
 * Wraps core_course_external::get_courses_by_field and appends country-resolved
 * pricing fields to every course in the result. The course data is identical to
 * what the core function returns (same fields, visibility rules, formatting).
 */
class get_courses_with_pricing extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'field' => new \external_value(
                PARAM_ALPHA,
                'Field to filter by: id | ids | shortname | idnumber | category. Empty = all courses.',
                VALUE_DEFAULT,
                ''
            ),
            'value' => new \external_value(
                PARAM_RAW,
                'Value for the filter field. For ids, comma-separated integers.',
                VALUE_DEFAULT,
                ''
            ),
            'country' => new \external_value(
                PARAM_ALPHA,
                'ISO-3166-1 alpha-2 country code from the app (overrides auto-detection).',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    public static function execute(string $field = '', string $value = '', string $country = ''): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'field'   => $field,
            'value'   => $value,
            'country' => $country,
        ]);

        self::validate_context(\context_system::instance());

        // Delegate to the core function — gets all standard fields, visibility
        // filtering, capability checks, and formatted output for free.
        $core_result = \core_course_external::get_courses_by_field(
            $params['field'],
            $params['value']
        );

        $app_country = !empty($params['country']) ? $params['country'] : null;
        $courses     = [];

        foreach ($core_result['courses'] as $course_data) {
            $courseid = (int) $course_data['id'];

            if ($courseid === SITEID) {
                continue;
            }

            $is_purchased = \local_payments\price_resolver::is_purchased($courseid, $USER->id);
            $is_enrolled  = \local_payments\enrollment_handler::is_enrolled($USER->id, $courseid);

            $pricing = [
                'pricing_country'     => '',
                'currency'            => '',
                'price'               => 0.0,
                'sale_price'          => 0.0,
                'original_price'      => 0.0,
                'discount_percentage' => 0,
                'is_sale_active'      => false,
                'sale_ends_at'        => 0,
                'is_free'             => true,
                'is_purchased'        => $is_purchased,
                'is_enrolled'         => $is_enrolled,
                'offer_name'          => '',
            ];

            if (\local_payments\price_resolver::has_pricing($courseid)) {
                try {
                    $resolved = \local_payments\price_resolver::resolve($courseid, $USER->id, $app_country);
                    $pricing['pricing_country']     = $resolved->country;
                    $pricing['currency']            = $resolved->currency;
                    $pricing['price']               = $resolved->price;
                    $pricing['sale_price']          = $resolved->sale_price ?? 0.0;
                    $pricing['original_price']      = $resolved->original_price;
                    $pricing['discount_percentage'] = $resolved->discount_pct;
                    $pricing['is_sale_active']      = $resolved->is_sale_active;
                    $pricing['sale_ends_at']        = (int) ($resolved->sale_ends_at ?? 0);
                    $pricing['is_free']             = false;

                    // Fold in any active local_academy automatic offer (US-US-OF-1-2) so the
                    // catalog shows the same discounted price the student will actually be
                    // charged at checkout — mirrors price_resolver::display_fields().
                    if (class_exists('\local_academy\discount_manager')) {
                        $offer = \local_academy\discount_manager::offer_summary(
                            'course', $courseid, (float) $resolved->price
                        );
                        if ($offer) {
                            $original = (float) $resolved->original_price;
                            $final    = (float) $offer['final'];
                            $pricing['is_sale_active']      = true;
                            $pricing['sale_price']          = $final;
                            $pricing['discount_percentage'] = $original > 0
                                ? (int) round((($original - $final) / $original) * 100)
                                : 0;
                            $pricing['offer_name'] = $offer['name'];
                        }
                    }
                } catch (\moodle_exception $e) {
                    // No pricing rule for this country — treat as free.
                }
            }

            $courses[] = array_merge($course_data, $pricing);
        }

        return [
            'courses'  => $courses,
            'warnings' => $core_result['warnings'],
        ];
    }

    public static function execute_returns(): \external_single_structure {
        // Full course structure — mirrors core_course_external::get_course_structure(false).
        $course_structure = [
            // --- Public fields (always returned) ---
            'id'           => new \external_value(PARAM_INT, 'course id'),
            'fullname'     => new \external_value(PARAM_RAW, 'course full name'),
            'displayname'  => new \external_value(PARAM_RAW, 'course display name'),
            'shortname'    => new \external_value(PARAM_RAW, 'course short name'),
            'categoryid'   => new \external_value(PARAM_INT, 'category id'),
            'categoryname' => new \external_value(PARAM_RAW, 'category name'),
            'sortorder'    => new \external_value(PARAM_INT, 'sort order in the category', VALUE_OPTIONAL),
            'summary'      => new \external_value(PARAM_RAW, 'summary'),
            'summaryformat' => new \external_format_value('summary'),
            'summaryfiles' => new \external_files('summary files', VALUE_OPTIONAL),
            'overviewfiles' => new \external_files('overview files attached to this course'),
            'showactivitydates' => new \external_value(PARAM_BOOL, 'whether activity dates are shown'),
            'showcompletionconditions' => new \external_value(PARAM_BOOL, 'whether completion conditions are shown'),
            'contacts' => new \external_multiple_structure(
                new \external_single_structure([
                    'id'       => new \external_value(PARAM_INT,    'contact user id'),
                    'fullname' => new \external_value(PARAM_NOTAGS, 'contact user fullname'),
                ]),
                'contact users'
            ),
            'enrollmentmethods' => new \external_multiple_structure(
                new \external_value(PARAM_PLUGIN, 'enrollment method'),
                'enrollment methods list'
            ),
            'customfields' => new \external_multiple_structure(
                new \external_single_structure([
                    'name'      => new \external_value(PARAM_RAW,        'custom field name'),
                    'shortname' => new \external_value(PARAM_RAW,        'custom field shortname'),
                    'type'      => new \external_value(PARAM_ALPHANUMEXT,'custom field type'),
                    'valueraw'  => new \external_value(PARAM_RAW,        'custom field raw value'),
                    'value'     => new \external_value(PARAM_RAW,        'custom field value'),
                ]),
                'custom fields',
                VALUE_OPTIONAL
            ),
            // --- Fields returned for users who can access the course ---
            'idnumber'           => new \external_value(PARAM_RAW,    'id number',           VALUE_OPTIONAL),
            'format'             => new \external_value(PARAM_PLUGIN,  'course format',       VALUE_OPTIONAL),
            'showgrades'         => new \external_value(PARAM_INT,    '1 if grades shown',   VALUE_OPTIONAL),
            'newsitems'          => new \external_value(PARAM_INT,    'number of news items', VALUE_OPTIONAL),
            'startdate'          => new \external_value(PARAM_INT,    'course start timestamp', VALUE_OPTIONAL),
            'enddate'            => new \external_value(PARAM_INT,    'course end timestamp',   VALUE_OPTIONAL),
            'maxbytes'           => new \external_value(PARAM_INT,    'max upload file size',   VALUE_OPTIONAL),
            'showreports'        => new \external_value(PARAM_INT,    'activity reports shown', VALUE_OPTIONAL),
            'visible'            => new \external_value(PARAM_INT,    '1: available, 0: not',   VALUE_OPTIONAL),
            'groupmode'          => new \external_value(PARAM_INT,    'group mode',             VALUE_OPTIONAL),
            'groupmodeforce'     => new \external_value(PARAM_INT,    '1: forced, 0: no',       VALUE_OPTIONAL),
            'defaultgroupingid'  => new \external_value(PARAM_INT,    'default grouping id',    VALUE_OPTIONAL),
            'enablecompletion'   => new \external_value(PARAM_INT,    'completion enabled',     VALUE_OPTIONAL),
            'completionnotify'   => new \external_value(PARAM_INT,    'notify on completion',   VALUE_OPTIONAL),
            'lang'               => new \external_value(PARAM_SAFEDIR,'forced language',        VALUE_OPTIONAL),
            'theme'              => new \external_value(PARAM_PLUGIN,  'forced theme',           VALUE_OPTIONAL),
            'marker'             => new \external_value(PARAM_INT,    'current course marker',  VALUE_OPTIONAL),
            'legacyfiles'        => new \external_value(PARAM_INT,    'legacy files enabled',   VALUE_OPTIONAL),
            'calendartype'       => new \external_value(PARAM_PLUGIN,  'calendar type',          VALUE_OPTIONAL),
            'timecreated'        => new \external_value(PARAM_INT,    'time course was created', VALUE_OPTIONAL),
            'timemodified'       => new \external_value(PARAM_INT,    'time course was updated', VALUE_OPTIONAL),
            'requested'          => new \external_value(PARAM_INT,    'is a requested course',   VALUE_OPTIONAL),
            'cacherev'           => new \external_value(PARAM_INT,    'cache revision number',   VALUE_OPTIONAL),
            'filters' => new \external_multiple_structure(
                new \external_single_structure([
                    'filter'         => new \external_value(PARAM_PLUGIN, 'filter plugin name'),
                    'localstate'     => new \external_value(PARAM_INT,    'filter state: 1 on, -1 off, 0 inherit'),
                    'inheritedstate' => new \external_value(PARAM_INT,    '1 or 0 for inherited state'),
                ]),
                'course filters',
                VALUE_OPTIONAL
            ),
            'courseformatoptions' => new \external_multiple_structure(
                new \external_single_structure([
                    'name'  => new \external_value(PARAM_RAW, 'course format option name'),
                    'value' => new \external_value(PARAM_RAW, 'course format option value'),
                ]),
                'additional course format options',
                VALUE_OPTIONAL
            ),
            // --- Pricing fields (added by this function) ---
            'pricing_country'     => new \external_value(PARAM_ALPHA, 'resolved country code for pricing'),
            'currency'            => new \external_value(PARAM_ALPHA, 'currency code (e.g. EGP)'),
            'price'               => new \external_value(PARAM_FLOAT, 'effective price — sale price if active, otherwise original'),
            'sale_price'          => new \external_value(PARAM_FLOAT, 'sale price, or 0 if no active sale'),
            'original_price'      => new \external_value(PARAM_FLOAT, 'original price before any discount'),
            'discount_percentage' => new \external_value(PARAM_INT,   'discount percentage 0–100'),
            'is_sale_active'      => new \external_value(PARAM_BOOL,  'whether a sale is currently active'),
            'sale_ends_at'        => new \external_value(PARAM_INT,   'sale end unix timestamp, or 0'),
            'is_free'             => new \external_value(PARAM_BOOL,  'true if no active pricing rule — open access'),
            'is_purchased'        => new \external_value(PARAM_BOOL,  'current user has a completed purchase'),
            'is_enrolled'         => new \external_value(PARAM_BOOL,  'current user is enrolled in the course'),
            'offer_name'          => new \external_value(PARAM_RAW,   'name(s) of active automatic offer(s) applied, or empty string'),
        ];

        return new \external_single_structure([
            'courses'  => new \external_multiple_structure(
                new \external_single_structure($course_structure),
                'courses with pricing'
            ),
            'warnings' => new \external_warnings(),
        ]);
    }
}
