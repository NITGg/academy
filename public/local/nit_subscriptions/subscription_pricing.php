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
 * Admin UI to manage per-country price overrides for a subscription plan.
 *
 * Mirrors local/payments/course_pricing.php. The plan's base price/currency (set on the plan itself)
 * is always the default; the rows managed here override it for specific countries.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_subscriptions\subscription_manager;

$subscriptionid = required_param('subscriptionid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$priceid = optional_param('priceid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

admin_externalpage_setup('local_nit_subscriptions_pricing');
$context = context_system::instance();
require_capability('local/nit_subscriptions:managesubscriptions', $context);

$sub = subscription_manager::get_subscription($subscriptionid);
$planname = format_string(subscription_manager::resolve_mlang($sub->name));

$baseurl = new moodle_url('/local/nit_subscriptions/subscription_pricing.php', ['subscriptionid' => $subscriptionid]);
$PAGE->set_url($baseurl);
$PAGE->set_title(get_string('subscriptionpricing', 'local_nit_subscriptions'));
$PAGE->set_heading($planname);

// Handle delete.
if ($action === 'delete' && $priceid && confirm_sesskey()) {
    if ($confirm) {
        subscription_manager::delete_price($priceid, $subscriptionid);
        redirect($baseurl, get_string('price_deleted', 'local_nit_subscriptions'));
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('price_confirmdelete', 'local_nit_subscriptions'),
        new moodle_url($baseurl, ['action' => 'delete', 'priceid' => $priceid, 'confirm' => 1, 'sesskey' => sesskey()]),
        $baseurl
    );
    echo $OUTPUT->footer();
    exit;
}

// Handle add/edit form.
if ($action === 'edit' || $action === 'add') {
    $data = null;
    if ($priceid) {
        $data = $DB->get_record('nit_sub_price', ['id' => $priceid, 'subscriptionid' => $subscriptionid], '*', MUST_EXIST);
    }

    $formurl = new moodle_url($baseurl, ['action' => $action, 'priceid' => $priceid]);
    $form = new \local_nit_subscriptions\form\subscription_pricing_form($formurl, [
        'subscriptionid' => $subscriptionid,
        'priceid' => $priceid,
        'data' => $data,
    ]);

    if ($form->is_cancelled()) {
        redirect($baseurl);
    }

    if ($formdata = $form->get_data()) {
        subscription_manager::save_price([
            'subscriptionid' => $subscriptionid,
            'priceid'        => $priceid,
            'country'        => $formdata->country,
            'currency'       => $formdata->currency,
            'price'          => $formdata->price,
            'is_active'      => !empty($formdata->is_active) ? 1 : 0,
        ], $USER->id);
        redirect($baseurl, get_string('price_saved', 'local_nit_subscriptions'));
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string($priceid ? 'price_edit' : 'price_add', 'local_nit_subscriptions'));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// List overrides.
$prices = subscription_manager::get_prices($subscriptionid);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('subscriptionpricing', 'local_nit_subscriptions'));

// Base/default price banner (set on the plan itself).
$basecurrency = $sub->currency ?? 'EGP';
echo $OUTPUT->notification(
    get_string('price_default_notice', 'local_nit_subscriptions', (object) [
        'price' => number_format((float) $sub->price, 2),
        'currency' => $basecurrency,
    ]),
    'info'
);

$addurl = new moodle_url($baseurl, ['action' => 'add']);
echo html_writer::link($addurl, get_string('price_add', 'local_nit_subscriptions'), ['class' => 'btn btn-primary mb-3']);

if (empty($prices)) {
    echo $OUTPUT->notification(get_string('price_none', 'local_nit_subscriptions'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('price_country', 'local_nit_subscriptions'),
        get_string('price_currency', 'local_nit_subscriptions'),
        get_string('price_amount', 'local_nit_subscriptions'),
        get_string('price_is_active', 'local_nit_subscriptions'),
        get_string('actions'),
    ];
    $table->attributes['class'] = 'generaltable';

    $countries = get_string_manager()->get_list_of_countries();

    foreach ($prices as $p) {
        $countryname = $countries[$p->country] ?? $p->country;
        $editurl = new moodle_url($baseurl, ['action' => 'edit', 'priceid' => $p->id]);
        $deleteurl = new moodle_url($baseurl, ['action' => 'delete', 'priceid' => $p->id, 'sesskey' => sesskey()]);
        $actions = html_writer::link($editurl, $OUTPUT->pix_icon('t/edit', get_string('edit'))) . ' ' .
            html_writer::link($deleteurl, $OUTPUT->pix_icon('t/delete', get_string('delete')));

        $table->data[] = [
            $countryname,
            $p->currency,
            number_format((float) $p->price, 2),
            $p->is_active ? get_string('yes') : get_string('no'),
            $actions,
        ];
    }

    echo html_writer::table($table);
}

echo html_writer::link(
    new moodle_url('/local/nit_subscriptions/manage_subscriptions.php'),
    get_string('backtosubscriptions', 'local_nit_subscriptions'),
    ['class' => 'btn btn-secondary mt-3']
);

echo $OUTPUT->footer();
