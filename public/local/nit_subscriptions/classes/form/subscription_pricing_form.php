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
 * Add/edit form for a per-country subscription price override.
 *
 * Mirrors local_payments\form\course_pricing_form. There is no "*" default option here — the plan's
 * base price (nit_subscription.price/currency) is the default; this form only adds country overrides.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_subscriptions\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Country/currency/price form for a subscription price override.
 */
class subscription_pricing_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $subscriptionid = $this->_customdata['subscriptionid'] ?? 0;
        $priceid = $this->_customdata['priceid'] ?? 0;
        $data = $this->_customdata['data'] ?? null;

        $mform->addElement('hidden', 'subscriptionid', $subscriptionid);
        $mform->setType('subscriptionid', PARAM_INT);

        $mform->addElement('hidden', 'priceid', $priceid);
        $mform->setType('priceid', PARAM_INT);

        // Country (real countries only — the base price is the default).
        $countries = get_string_manager()->get_list_of_countries();
        $mform->addElement('select', 'country', get_string('price_country', 'local_nit_subscriptions'), $countries);
        $mform->addRule('country', null, 'required', null, 'client');

        // Currency.
        $currencies = [
            'USD' => 'USD - US Dollar',
            'EGP' => 'EGP - Egyptian Pound',
            'EUR' => 'EUR - Euro',
            'GBP' => 'GBP - British Pound',
            'SAR' => 'SAR - Saudi Riyal',
            'AED' => 'AED - UAE Dirham',
            'KWD' => 'KWD - Kuwaiti Dinar',
            'BHD' => 'BHD - Bahraini Dinar',
            'QAR' => 'QAR - Qatari Riyal',
            'OMR' => 'OMR - Omani Rial',
        ];
        $mform->addElement('select', 'currency', get_string('price_currency', 'local_nit_subscriptions'), $currencies);
        $mform->addRule('currency', null, 'required', null, 'client');

        // Price.
        $mform->addElement('text', 'price', get_string('price_amount', 'local_nit_subscriptions'));
        $mform->setType('price', PARAM_FLOAT);
        $mform->addRule('price', null, 'required', null, 'client');
        $mform->addRule('price', null, 'numeric', null, 'client');

        // Is active.
        $mform->addElement('advcheckbox', 'is_active', get_string('price_is_active', 'local_nit_subscriptions'));
        $mform->setDefault('is_active', 1);

        $this->add_action_buttons();

        if ($data) {
            $this->set_data($data);
        }
    }

    /**
     * Server-side validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['price']) || (float) $data['price'] <= 0) {
            $errors['price'] = get_string('err_pricepositive', 'local_nit_subscriptions');
        }

        // Only one row per (plan, country).
        if (!empty($data['country'])) {
            global $DB;
            $existing = $DB->get_record_select(
                'nit_sub_price',
                'subscriptionid = :sid AND country = :country AND id != :id',
                [
                    'sid'     => $data['subscriptionid'],
                    'country' => strtoupper($data['country']),
                    'id'      => $data['priceid'] ?? 0,
                ]
            );
            if ($existing) {
                $errors['country'] = get_string('err_priceonepercountry', 'local_nit_subscriptions');
            }
        }

        return $errors;
    }
}
