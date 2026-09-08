<?php
namespace local_payments\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class course_pricing_form extends \moodleform {

    /**
     * The currencies a price may be set in. One list, used by both shapes of the form.
     *
     * @return array<string, string>
     */
    public static function currencies(): array {
        return [
            'USD' => 'USD - US Dollar',
            'EGP' => 'EGP - Egyptian Pound',
            'EUR' => 'EUR - Euro',
            'GBP' => 'GBP - British Pound',
            'SAR' => 'SAR - Saudi Riyal',
            'AED' => 'AED - UAE Dirham',
            'KWD' => 'KWD - Kuwaiti Dinar',
            'BHD' => 'BHD - Bahraini Dinar',
            'QAR' => 'QAR - Qatari Rial',
            'OMR' => 'OMR - Omani Rial',
        ];
    }

    protected function definition() {
        $mform = $this->_form;
        $courseid = $this->_customdata['courseid'] ?? 0;
        $priceid = $this->_customdata['priceid'] ?? 0;
        $data = $this->_customdata['data'] ?? null;

        // A course with no prices at all is about to become a course that sells,
        // and that decision has to produce BOTH required rows in one save. Adding
        // them one at a time is what let a course exist priced only in dollars
        // with no Egyptian row, or only in pounds with nothing for the rest of the
        // world — and no single-row form can refuse that, because between the
        // first save and the second every course is necessarily half-priced.
        // Asking for both here is the only place the rule can be enforced without
        // making the second row impossible to reach.
        $bootstrap = !empty($this->_customdata['bootstrap']);

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'priceid', $priceid);
        $mform->setType('priceid', PARAM_INT);

        $mform->addElement('hidden', 'bootstrap', $bootstrap ? 1 : 0);
        $mform->setType('bootstrap', PARAM_INT);

        if ($bootstrap) {
            $this->definition_first_prices($mform);
            $this->add_action_buttons();
            return;
        }

        // The country and currency a "add the missing price" link asked for. Only a
        // starting position for the selects: setDefault() is overridden by
        // set_data() at the bottom, so an EDIT still shows the row's own values.
        $prefillcountry = strtoupper((string) ($this->_customdata['prefillcountry'] ?? ''));
        $prefillcurrency = strtoupper((string) ($this->_customdata['prefillcurrency'] ?? ''));

        // Country.
        $countries = array_merge(
            ['*' => get_string('defaultprice', 'local_payments')],
            get_string_manager()->get_list_of_countries()
        );
        $mform->addElement('select', 'country', get_string('country', 'local_payments'), $countries);
        $mform->setDefault('country', isset($countries[$prefillcountry]) ? $prefillcountry : '*');
        $mform->addRule('country', null, 'required', null, 'client');

        // Currency.
        $currencies = self::currencies();
        $mform->addElement('select', 'currency', get_string('currency', 'local_payments'), $currencies);
        if (isset($currencies[$prefillcurrency])) {
            $mform->setDefault('currency', $prefillcurrency);
        }
        $mform->addRule('currency', null, 'required', null, 'client');

        // Price.
        $mform->addElement('text', 'price', get_string('price', 'local_payments'));
        $mform->setType('price', PARAM_FLOAT);
        $mform->addRule('price', null, 'required', null, 'client');
        $mform->addRule('price', null, 'numeric', null, 'client');

        // Is default. The first price added to a course is the default by default.
        global $DB;
        $hasprices = $DB->record_exists('local_payments_course_prices', ['courseid' => $courseid]);
        $mform->addElement('advcheckbox', 'is_default', get_string('is_default', 'local_payments'));
        if (!$hasprices) {
            $mform->setDefault('is_default', 1);
        }

        // Is active.
        $mform->addElement('advcheckbox', 'is_active', get_string('is_active', 'local_payments'));
        $mform->setDefault('is_active', 1);

        $this->add_action_buttons();

        if ($data) {
            $this->set_data($data);
        }
    }

    /**
     * The form a course sees the first time it is given a price: both required rows
     * at once, so it can never come out of this screen half-priced.
     *
     * @param \MoodleQuickForm $mform
     * @return void
     */
    protected function definition_first_prices($mform): void {
        $currencies = self::currencies();
        $home = \local_payments\country_detector::fallback_country();
        $countries = get_string_manager()->get_list_of_countries();
        $homename = $countries[$home] ?? $home;

        $mform->addElement('static', 'firstpricesnote', '',
            get_string('pricing_first_note', 'local_payments', $homename));

        // 1. The home country, in local money.
        $mform->addElement('header', 'hdrhome',
            get_string('pricing_first_homehdr', 'local_payments', $homename));
        $mform->setExpanded('hdrhome', true);

        $mform->addElement('select', 'homecurrency', get_string('currency', 'local_payments'), $currencies);
        $mform->setDefault('homecurrency', isset($currencies['EGP']) ? 'EGP' : key($currencies));

        $mform->addElement('text', 'homeprice', get_string('price', 'local_payments'));
        $mform->setType('homeprice', PARAM_FLOAT);
        $mform->addRule('homeprice', null, 'required', null, 'client');
        $mform->addRule('homeprice', null, 'numeric', null, 'client');
        $mform->addElement('static', 'homehelp', '',
            get_string('pricing_first_homehelp', 'local_payments', $homename));

        // 2. Everybody else, in international money.
        $mform->addElement('header', 'hdrdefault',
            get_string('pricing_first_defaulthdr', 'local_payments'));
        $mform->setExpanded('hdrdefault', true);

        $mform->addElement('select', 'defaultcurrency', get_string('currency', 'local_payments'), $currencies);
        $mform->setDefault('defaultcurrency', 'USD');

        $mform->addElement('text', 'defaultprice', get_string('price', 'local_payments'));
        $mform->setType('defaultprice', PARAM_FLOAT);
        $mform->addRule('defaultprice', null, 'required', null, 'client');
        $mform->addRule('defaultprice', null, 'numeric', null, 'client');
        $mform->addElement('static', 'defaulthelp', '',
            get_string('pricing_first_defaulthelp', 'local_payments', $homename));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // ── The course's first prices: both, or neither ─────────────────────
        if (!empty($data['bootstrap'])) {
            if (empty($data['homeprice']) || $data['homeprice'] <= 0) {
                $errors['homeprice'] = get_string('error_price_positive', 'local_payments');
            }
            if (empty($data['defaultprice']) || $data['defaultprice'] <= 0) {
                $errors['defaultprice'] = get_string('error_price_positive', 'local_payments');
            }
            // Two rows in the same currency are one row twice over: the Default
            // row exists to quote everybody OUTSIDE the home country, and in the
            // home currency it is not doing that job.
            if (!empty($data['homecurrency']) && !empty($data['defaultcurrency'])
                    && $data['homecurrency'] === $data['defaultcurrency']) {
                $errors['defaultcurrency'] = get_string('error_same_currency', 'local_payments');
            }
            return $errors;
        }

        if (empty($data['price']) || $data['price'] <= 0) {
            $errors['price'] = get_string('error_price_positive', 'local_payments');
        }

        // Validate only one default per course.
        if (!empty($data['is_default'])) {
            global $DB;
            $existing = $DB->get_record_select(
                'local_payments_course_prices',
                'courseid = :courseid AND is_default = 1 AND id != :id',
                ['courseid' => $data['courseid'], 'id' => $data['priceid'] ?? 0]
            );
            if ($existing) {
                $errors['is_default'] = get_string('error_one_default', 'local_payments');
            }
        }

        // Validate only one active rule per country (one active price per country).
        if (!empty($data['is_active'])) {
            global $DB;
            $existing = $DB->get_record_select(
                'local_payments_course_prices',
                'courseid = :courseid AND country = :country AND is_active = 1 AND id != :id',
                [
                    'courseid' => $data['courseid'],
                    'country' => $data['country'],
                    'id' => $data['priceid'] ?? 0,
                ]
            );
            if ($existing) {
                $errors['country'] = get_string('error_one_active_per_country', 'local_payments');
            }
        }

        // ── The block ────────────────────────────────────────────────────────
        // A course that is priced correctly may not be saved into a state where
        // it is not. The three ways to do it from this form are unticking Active
        // on the home-country row, turning the Default row's currency into the
        // home one, and moving the Default flag onto the home row.
        //
        // Asked about the rows as they WOULD BE, not as they are — the row being
        // edited is swapped for what is about to be written. And only ever
        // applied when the course is complete right now: a course priced before
        // this rule existed has one row, and refusing every save on the grounds
        // that it is incomplete would make it impossible to repair.
        if (empty($errors)) {
            global $DB;
            $rows = $DB->get_records('local_payments_course_prices',
                ['courseid' => (int) $data['courseid']]);

            $editing = (int) ($data['priceid'] ?? 0);
            if ($editing && isset($rows[$editing])) {
                unset($rows[$editing]);
            }
            $rows['pending'] = (object) [
                'country' => $data['country'],
                'currency' => $data['currency'],
                'is_default' => !empty($data['is_default']) ? 1 : 0,
                'is_active' => !empty($data['is_active']) ? 1 : 0,
            ];

            if (\local_payments\price_resolver::would_break_pricing((int) $data['courseid'], $rows)) {
                $errors['is_active'] = get_string('error_would_break_pricing', 'local_payments');
            }
        }

        return $errors;
    }
}
