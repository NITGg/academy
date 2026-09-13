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

namespace local_payments\local\hooks;

use local_payments\country_detector;
use local_payments\course_pricing;
use local_payments\price_resolver;
use local_payments\refund_policy;

defined('MOODLE_INTERNAL') || die();

/**
 * The "Course pricing" section of the course settings form (course/edit.php).
 *
 * Core dispatches three hooks around course_edit_form — definition, validation
 * and submission — and this class answers all three, so the price of a course
 * is set where the rest of the course is set instead of on a page of its own.
 * The section is deliberately small: the local price, the Default price and
 * the refund terms. Per-country rows beyond those two were dropped from the
 * form on 2026-09-13 ("two is enough for now").
 * Every element is prefixed `lpp_` so nothing collides with a course column,
 * a custom field or another plugin. The rules themselves live in
 * {@see course_pricing}; this class only draws the form and passes data through.
 *
 * @package    local_payments
 */
class course_form {

    /**
     * Add the pricing section to the course form.
     *
     * Runs after core has added every section of its own (custom fields
     * included), so the section lands last, just above the buttons.
     *
     * @param \core_course\hook\after_form_definition $hook
     * @return void
     */
    public static function after_form_definition(\core_course\hook\after_form_definition $hook): void {
        $form = $hook->formwrapper;
        $mform = $hook->mform;

        // For a course being created the context is the category's, which is
        // where the creator's capabilities come from anyway.
        if (!has_capability('local/payments:managecoursepricing', $form->get_context())) {
            return;
        }

        $course = $form->get_course();
        $courseid = (int) ($course->id ?? 0);
        $current = course_pricing::load($courseid);

        $currencies = course_pricing::currencies();
        $home = country_detector::fallback_country();
        $homecurrency = course_pricing::home_currency();
        $homename = get_string_manager()->get_list_of_countries()[$home] ?? $home;

        $mform->addElement('header', 'lpp_pricing', get_string('coursepricing', 'local_payments'));
        // Opened through course_pricing::settings_url() — the old pricing URL and
        // the diagnose page both send the admin here for the prices — so the
        // section is what they came for and arrives expanded.
        if (optional_param('pricing', 0, PARAM_BOOL)) {
            $mform->setExpanded('lpp_pricing', true, true);
        }

        // The marker the validation and save hooks look for: no marker, no section
        // on this form (no capability, or a save that did not come from this form).
        $mform->addElement('hidden', 'lpp_present', 1);
        $mform->setType('lpp_present', PARAM_INT);

        // No explanation of the country ladder up here — the admin asked for the
        // section to open straight on the prices. How a buyer's price is chosen is
        // documented on country_diagnose.php, which also shows what THIS server sees.
        //
        // With no way to place a guest, the local price below is unreachable for
        // signed-out visitors: they all get the Default row. Said here, where the
        // prices are being typed, because from the outside it looks exactly like
        // a wrong price rule.
        if (!country_detector::geolocation_available()) {
            $mform->addElement('static', 'lpp_geowarn', '',
                \html_writer::div(
                    \html_writer::tag('strong', get_string('pricing_geo_off', 'local_payments')) . ' '
                    . get_string('pricing_geo_off_desc', 'local_payments') . ' '
                    . \html_writer::link(
                        new \moodle_url('/local/payments/country_diagnose.php', $courseid ? ['courseid' => $courseid] : []),
                        get_string('pricing_geo_check', 'local_payments')),
                    'alert alert-warning mb-0'));
        }

        // A course priced before the two-price rule existed can be missing one of
        // the two rows, or have its Default row in local money. It will not save
        // again until repaired (or every price cleared), so say so up front rather
        // than only in the error.
        $gaps = $courseid ? price_resolver::pricing_gaps($courseid) : null;
        if ($gaps && $gaps['selling'] && !$gaps['complete']) {
            $mform->addElement('static', 'lpp_incomplete', '',
                \html_writer::div(
                    \html_writer::tag('strong', get_string('pricing_incomplete', 'local_payments')) . ' '
                    . get_string('pricing_incomplete_form', 'local_payments'),
                    'alert alert-warning mb-0'));
        }

        // ── 1. The home country, in the currency of the admin's choosing ─────
        $group = [
            $mform->createElement('select', 'lpp_homecurrency', get_string('currency', 'local_payments'), $currencies),
            $mform->createElement('text', 'lpp_homeprice', get_string('price', 'local_payments'),
                ['size' => 8, 'placeholder' => get_string('price', 'local_payments')]),
        ];
        $mform->addGroup($group, 'lpp_homegrp',
            get_string('pricing_first_homehdr', 'local_payments', $homename), ' ', false);
        $mform->setType('lpp_homecurrency', PARAM_ALPHA);
        $mform->setType('lpp_homeprice', PARAM_RAW_TRIMMED);
        $mform->setDefault('lpp_homecurrency', $current->home->currency ?? $homecurrency);
        $mform->setDefault('lpp_homeprice', course_pricing::display_price($current->home->price ?? null));
        $mform->addElement('static', 'lpp_homehelp', '',
            get_string('pricing_first_homehelp', 'local_payments', $homename));

        // ── 2. Everybody else ────────────────────────────────────────────────
        // The home currency is left out of this list rather than rejected after
        // the fact: this row exists to quote the world in international money.
        // A legacy row already in that currency is still shown as it is — the
        // validation then refuses the save until the admin picks another.
        $foreign = $currencies;
        unset($foreign[$homecurrency]);
        $defaultcurrency = $current->default->currency ?? (isset($foreign['USD']) ? 'USD' : (string) key($foreign));
        if (!isset($foreign[$defaultcurrency]) && isset($currencies[$defaultcurrency])) {
            $foreign = [$defaultcurrency => $currencies[$defaultcurrency]] + $foreign;
        }
        $group = [
            $mform->createElement('select', 'lpp_defaultcurrency', get_string('currency', 'local_payments'), $foreign),
            $mform->createElement('text', 'lpp_defaultprice', get_string('price', 'local_payments'),
                ['size' => 8, 'placeholder' => get_string('price', 'local_payments')]),
        ];
        $mform->addGroup($group, 'lpp_defaultgrp',
            get_string('pricing_first_defaulthdr', 'local_payments'), ' ', false);
        $mform->setType('lpp_defaultcurrency', PARAM_ALPHA);
        $mform->setType('lpp_defaultprice', PARAM_RAW_TRIMMED);
        $mform->setDefault('lpp_defaultcurrency', $defaultcurrency);
        $mform->setDefault('lpp_defaultprice', course_pricing::display_price($current->default->price ?? null));
        $mform->addElement('static', 'lpp_defaulthelp', '',
            get_string('pricing_first_defaulthelp', 'local_payments', $homename));

        // ── 3. Refund terms ──────────────────────────────────────────────────
        // Terms of the same sale as the price, so they sit with it — but not on
        // each row, because a percentage is the same number whatever the currency.
        $sitepolicy = refund_policy::site_policy('course');
        $intro = get_string('refund_terms_intro', 'local_payments',
            (object) ['hours' => $sitepolicy->hours, 'fee' => format_float($sitepolicy->feepercent, 2, true, true)]);
        if (!refund_policy::enabled()) {
            $intro .= ' ' . \html_writer::tag('strong', get_string('refund_terms_offsitewide', 'local_payments'));
        }
        $mform->addElement('static', 'lpp_refundintro', get_string('refund_terms_heading', 'local_payments'), $intro);

        $mform->addElement('text', 'lpp_refund_hours', get_string('refund_hours', 'local_payments'),
            ['size' => 12, 'placeholder' => get_string('refund_terms_inherit', 'local_payments')]);
        $mform->setType('lpp_refund_hours', PARAM_RAW_TRIMMED);
        $mform->setDefault('lpp_refund_hours',
            $current->refund->hours === null ? '' : (string) $current->refund->hours);

        $mform->addElement('text', 'lpp_refund_fee', get_string('refund_feepercent', 'local_payments'),
            ['size' => 12, 'placeholder' => get_string('refund_terms_inherit', 'local_payments')]);
        $mform->setType('lpp_refund_fee', PARAM_RAW_TRIMMED);
        $mform->setDefault('lpp_refund_fee',
            $current->refund->feepercent === null ? '' : format_float($current->refund->feepercent, 2, true, true));

        $mform->addElement('static', 'lpp_refundhelp', '', get_string('refund_terms_help', 'local_payments'));
    }

    /**
     * Refuse a set of prices the course could not sell on.
     *
     * @param \core_course\hook\after_form_validation $hook
     * @return void
     */
    public static function after_form_validation(\core_course\hook\after_form_validation $hook): void {
        $data = $hook->get_data();
        if (empty($data['lpp_present'])) {
            return;
        }
        $hook->add_errors(course_pricing::validate($data));
    }

    /**
     * Write the prices and refund terms once the course itself has been saved.
     *
     * The same hook fires for every save of a course — web services, the upload
     * tool, a course restore — and those carry no pricing at all. The marker
     * keeps them from wiping the course's prices.
     *
     * @param \core_course\hook\after_form_submission $hook
     * @return void
     */
    public static function after_form_submission(\core_course\hook\after_form_submission $hook): void {
        global $USER;

        $data = $hook->get_data();
        if (empty($data->lpp_present) || empty($data->id)) {
            return;
        }
        course_pricing::save((int) $data->id, $data, (int) $USER->id);
    }
}
