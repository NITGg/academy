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
 * Admin UI to manage course-access subscription plans and per-subscription course availability.
 * Drives /local/nit_subscriptions/api.php from vanilla JS. UI labels match the reference.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/nit_subscriptions/lib.php');

use local_nit_finance\output\report_panel;
use local_nit_finance\report\revenue;
use local_nit_subscriptions\subscription_manager;

admin_externalpage_setup('local_nit_subscriptions_managesubscriptions');
require_capability('local/nit_subscriptions:managesubscriptions', context_system::instance());

global $OUTPUT, $CFG, $PAGE;

$PAGE->set_title(get_string('managesubscriptions', 'local_nit_subscriptions'));
$PAGE->set_heading(get_string('managesubscriptions', 'local_nit_subscriptions'));
$PAGE->requires->js(new moodle_url('/local/nit_subscriptions/ui.js',
    ['v' => get_config('local_nit_subscriptions', 'version')]), true);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managesubscriptions', 'local_nit_subscriptions'));

// Localised strings: server-rendered HTML reads $STR['key']; the JS reads window.ACADEMY_STR.
$STR = local_nit_subscriptions_string_map(array(
    'sub_plans_heading', 'sub_new', 'ui_refresh', 'ui_loading', 'ui_save', 'ui_cancel', 'ui_active',
    'ui_activate', 'ui_deactivate', 'ui_edit', 'ui_delete', 'ui_never', 'ui_optional',
    'pkg_col_id', 'pkg_col_name', 'pkg_col_price', 'sub_col_days', 'sub_col_courses', 'pkg_col_status',
    'pkg_col_actions', 'pkg_field_name', 'pkg_col_user', 'sub_col_subscription',
    'pkg_field_name_en', 'pkg_field_name_ar', 'pkg_field_desc_en', 'pkg_field_desc_ar',
    'sub_field_refundhours', 'sub_field_refundfee',
    'sub_refundfee_help',
    'pkg_col_pricepaid', 'pkg_col_expiresat',
    'sub_field_desc', 'sub_field_days', 'sub_courseavail_heading', 'sub_courseavail_desc', 'sub_target',
    'sub_select_placeholder', 'sub_save_courses',
    'sub_none_admin', 'sub_inactive',
    'sub_edit_titled', 'sub_updated', 'sub_created', 'sub_activated', 'sub_deactivated', 'sub_deleted',
    'sub_confirm_delete', 'sub_no_categories', 'sub_select_target', 'sub_courses_assigned',
    'pkg_unassign_paid',
    'sstat_active', 'sstat_expired', 'sstat_cancelled', 'sstat_pending', 'sstat_payment_failed',
    'ui_pager_info',
    'ui_search', 'sub_courses_search', 'sub_selectall', 'sub_clear',
    'ui_showmore', 'ui_showless',
    'tab_plans', 'tab_courses', 'tab_reminders',
    'rem_heading', 'rem_desc', 'rem_enabled', 'rem_enabled_help', 'rem_days', 'rem_days_help',
    'rem_days_add', 'rem_days_none', 'rem_day_unit', 'rem_remove', 'rem_save', 'rem_applied',
    'rem_preview', 'rem_window_note', 'rem_window_off', 'rem_window_none', 'rem_recalc_note',
    'rem_col_days', 'rem_onexpiry', 'rem_onexpiry_help',
    'sub_ca_pickplan', 'sub_ca_counter', 'sub_ca_unsaved', 'sub_ca_reset',
    'sub_ca_onlyselected', 'sub_ca_nomatch', 'sub_ca_catall', 'sub_ca_catnone', 'sub_ca_discard',
    'sub_ca_catcount',
    'sub_field_categories', 'sub_categories_help', 'sub_categories_allopt', 'sub_categories_clear',
    'sub_categories_auto', 'sub_col_categories',
    'err_sessionexpired', 'err_requestfailed',
));

// A plan is priced exactly like a course (local_payments course_pricing): the home
// country's price, always in the home currency, and the Default price —
// everyone else, and anyone the site cannot place — in a different currency.
$homecountry = subscription_manager::home_country();
$homecurrency = subscription_manager::home_currency();
$homename = get_string_manager()->get_list_of_countries()[$homecountry] ?? $homecountry;
$currencies = subscription_manager::currencies();
$foreign = $currencies;
unset($foreign[$homecurrency]);
foreach (['sub_price_home_hdr', 'sub_price_home_help', 'sub_price_default_hdr', 'sub_price_default_help'] as $key) {
    $STR[$key] = get_string($key, 'local_nit_subscriptions', $homename);
}
$currencyoptions = function (array $list) {
    $html = '';
    foreach ($list as $code => $label) {
        $html .= html_writer::tag('option', s($label), ['value' => $code]);
    }
    return $html;
};

echo html_writer::script('window.ACADEMY_SUB = ' . json_encode(array(
    'endpoint'   => (new moodle_url('/local/nit_subscriptions/api.php'))->out(false),
    'sesskey'    => sesskey(),
    'lang'       => optional_param('lang', current_language(), PARAM_LANG),
    // The two price rows: which country the first one is, and what each picker
    // starts on for a new plan. Legacy plans priced in the home currency still
    // show that currency (added to the picker on the fly) until the admin changes it.
    'homecountry'     => $homecountry,
    'homecurrency'    => $homecurrency,
    'defaultcurrency' => isset($foreign['USD']) ? 'USD' : (string) key($foreign),
    'currencies'      => $currencies,
    // Every course category, in tree order with children indented — the choices for the plan's
    // category placement. Server-rendered rather than fetched, because the form needs them
    // before the admin can open it, and the list is short.
    'categories' => \local_nit_core\helper\category::options(),
)) . ';');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');
?>
<div id="academy-sub-app">
    <div id="sub-message" class="alert" style="display:none"></div>

    <!-- ── Tabs ─────────────────────────────────────────────────────────────────────
         Four screens that were one long scroll. Everything below keeps the ids it had,
         so the page script is untouched by the split; only the wrappers are new. The
         chosen tab is remembered in the URL hash, so a reload — and the admin's own
         bookmark — comes back to the same place. -->
    <ul class="nav nav-tabs mb-3" id="sub-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link active" role="tab"
                    data-subtab="plans"><?php echo $STR['tab_plans']; ?></button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link" role="tab"
                    data-subtab="courses"><?php echo $STR['tab_courses']; ?></button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link" role="tab"
                    data-subtab="reports"><?php echo report_panel::tab_label(); ?></button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link" role="tab"
                    data-subtab="reminders"><?php echo $STR['tab_reminders']; ?></button>
        </li>
    </ul>

    <!-- ══ TAB 1: the plans themselves ═══════════════════════════════════════════ -->
    <div data-subtabpane="plans" role="tabpanel">

    <!-- ── Subscription plans ── -->
    <h4><?php echo $STR['sub_plans_heading']; ?></h4>
    <div class="mb-3">
        <button id="sub-new" class="btn btn-primary"><?php echo $STR['sub_new']; ?></button>
        <button id="sub-refresh" class="btn btn-secondary"><?php echo $STR['ui_refresh']; ?></button>
    </div>

    <div class="acad-table-wrap">
    <table class="table table-striped" id="sub-table">
        <thead>
            <tr>
                <th><?php echo $STR['pkg_col_id']; ?></th><th><?php echo $STR['pkg_col_name']; ?></th><th><?php echo $STR['pkg_col_price']; ?></th><th><?php echo $STR['sub_col_days']; ?></th>
                <th class="col-tags"><?php echo $STR['sub_col_courses']; ?></th><th class="col-tags"><?php echo $STR['sub_col_categories']; ?></th><th><?php echo $STR['pkg_col_status']; ?></th><th class="col-tight"><?php echo $STR['pkg_col_actions']; ?></th>
            </tr>
        </thead>
        <tbody><tr><td colspan="8"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
    </table>
    </div>
    <div id="sub-table-pager" class="acad-pager"></div>

    <div id="sub-form-card" class="card" style="display:none; max-width:640px;">
        <div class="card-body">
            <h4 id="sub-form-title" class="card-title"><?php echo $STR['sub_new']; ?></h4>
            <input type="hidden" id="f-id">
            <div class="form-group">
                <label for="f-name-en"><?php echo $STR['pkg_field_name_en']; ?></label>
                <input type="text" class="form-control" id="f-name-en" dir="ltr">
            </div>
            <div class="form-group">
                <label for="f-name-ar"><?php echo $STR['pkg_field_name_ar']; ?></label>
                <input type="text" class="form-control" id="f-name-ar" dir="rtl">
            </div>
            <div class="form-group">
                <label for="f-desc-en"><?php echo $STR['pkg_field_desc_en']; ?></label>
                <textarea class="form-control" id="f-desc-en" rows="2" dir="ltr"></textarea>
            </div>
            <div class="form-group">
                <label for="f-desc-ar"><?php echo $STR['pkg_field_desc_ar']; ?></label>
                <textarea class="form-control" id="f-desc-ar" rows="2" dir="rtl"></textarea>
            </div>
            <!-- ── Prices: the same two rows as a course's "Course pricing" section ──
                 1. the home country at a local price, 2. everyone else at the Default
                 price in a different currency. Both required; no per-country rows. -->
            <div class="form-group">
                <label for="f-homeprice"><?php echo $STR['sub_price_home_hdr']; ?></label>
                <div class="sub-pricerow">
                    <!-- The local price is always in the home currency (2026-09-16): a read-only
                         label plus the code in a hidden field, not a picker. -->
                    <input type="text" class="form-control" id="f-homecurrency-label" readonly
                           value="<?php echo s($currencies[$homecurrency] ?? $homecurrency); ?>">
                    <input type="hidden" id="f-homecurrency" value="<?php echo s($homecurrency); ?>">
                    <input type="number" class="form-control" id="f-homeprice" min="0" step="0.01">
                </div>
                <small class="text-muted"><?php echo $STR['sub_price_home_help']; ?></small>
            </div>
            <div class="form-group">
                <label for="f-price"><?php echo $STR['sub_price_default_hdr']; ?></label>
                <div class="sub-pricerow">
                    <select class="form-control" id="f-currency"><?php echo $currencyoptions($foreign); ?></select>
                    <input type="number" class="form-control" id="f-price" min="0" step="0.01">
                </div>
                <small class="text-muted"><?php echo $STR['sub_price_default_help']; ?></small>
            </div>
            <div class="form-group">
                <label for="f-days"><?php echo $STR['sub_field_days']; ?></label>
                <input type="number" class="form-control" id="f-days" min="1">
            </div>
            <div class="form-group">
                <label for="f-refundhours"><?php echo $STR['sub_field_refundhours']; ?></label>
                <input type="number" class="form-control" id="f-refundhours" min="0" placeholder="0">
            </div>
            <div class="form-group">
                <label for="f-refundfee"><?php echo $STR['sub_field_refundfee']; ?></label>
                <input type="number" class="form-control" id="f-refundfee" min="0" max="100" step="0.01" placeholder="0.00">
                <small class="text-muted"><?php echo $STR['sub_refundfee_help']; ?></small>
            </div>
            <!-- ── Which category pages advertise this plan ──
                 Three answers, and the middle one is the default because it is what an admin
                 would have typed by hand: nothing selected = work it out from the plan's own
                 courses. "All categories" is the deliberate override for a site-wide plan. -->
            <div class="form-group sub-cats-box">
                <label for="f-categories"><strong><?php echo $STR['sub_field_categories']; ?></strong></label>
                <p class="text-muted sub-cats-help"><?php echo $STR['sub_categories_help']; ?></p>
                <select class="form-control" id="f-categories" multiple size="8">
                    <option value="0"><?php echo $STR['sub_categories_allopt']; ?></option>
                </select>
                <button type="button" id="f-categories-clear" class="btn btn-sm btn-link px-0">
                    <?php echo $STR['sub_categories_clear']; ?>
                </button>
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="f-active" checked>
                <label class="form-check-label" for="f-active"><?php echo $STR['ui_active']; ?></label>
            </div>
            <button id="sub-save" class="btn btn-primary"><?php echo $STR['ui_save']; ?></button>
            <button id="sub-cancel" class="btn btn-link"><?php echo $STR['ui_cancel']; ?></button>
        </div>
    </div>

    </div><!-- /tab: plans -->

    <!-- ══ TAB 2: which plan unlocks which course ═══════════════════════════════ -->
    <div data-subtabpane="courses" role="tabpanel" hidden>

    <!-- ── Course access ── -->
    <h4 class="mt-4"><?php echo $STR['sub_courseavail_heading']; ?></h4>
    <p class="text-muted"><?php echo $STR['sub_courseavail_desc']; ?></p>

    <style>
        .acad-pager { display:flex; flex-wrap:wrap; align-items:center; gap:.35rem; margin:1rem 0; }
        .acad-pager__info { margin-inline-end:auto; color:var(--nit-brand-textsecondary); font-size:.9rem; }
        .acad-pager button { border:1px solid var(--nit-brand-borderprimary); background:var(--nit-brand-surface); color:var(--nit-brand-textprimary); border-radius:6px; padding:.25rem .6rem; cursor:pointer; }
        .acad-pager button.is-active { background:var(--nit-brand-primary); border-color:var(--nit-brand-primary); color:var(--nit-brand-textprimary); }
        .acad-pager button:disabled { opacity:.5; cursor:default; }
        .ca-toolbar {
            display: flex; flex-wrap: wrap; align-items: flex-end; gap: 0.75rem;
            padding: 1rem 1.1rem; margin-bottom: 1.25rem;
            background: color-mix(in srgb,var(--nit-brand-background) 40%,var(--nit-brand-surface));
            border: 1px solid var(--nit-brand-borderprimary); border-radius: 0.6rem;
        }
        .ca-toolbar .ca-field { display: flex; flex-direction: column; gap: 0.25rem; }
        .ca-toolbar .ca-field label { margin: 0; font-size: 0.8rem; font-weight: 600; color: var(--nit-brand-textsecondary); }
        .ca-toolbar #target-subscription { min-width: 240px; }
        .ca-toolbar .ca-search { flex: 1 1 220px; }
        .ca-toolbar .ca-search input { width: 100%; }
        .ca-toolbar .ca-actions { display: flex; gap: 0.4rem; margin-inline-start: auto; align-items: center; flex-wrap: wrap; }
        .course-chip {
            display: inline-flex; align-items: center;
            background: color-mix(in srgb,var(--nit-brand-surface) 70%,var(--nit-brand-background));
            border: 1px solid var(--nit-brand-borderprimary);
            border-radius: 20px; padding: 8px 16px; margin: 6px; cursor: pointer;
            transition: all 0.16s ease; font-size: 0.95rem; color: var(--nit-brand-textprimary); user-select: none;
        }
        .course-chip:hover { background: var(--nit-brand-hoverbackground); color: var(--nit-brand-hovertext); box-shadow: 0 2px 4px rgba(0,0,0,0.35); transform: translateY(-1px); }
        .course-chip:has(input:checked) { background: color-mix(in srgb,var(--nit-brand-primary) 15%,transparent); border-color: var(--nit-brand-primary); color: var(--nit-brand-accenttext); font-weight: 600; }
        .course-chip input[type="checkbox"] { margin-inline-end: 10px; cursor: pointer; width: 1.1rem; height: 1.1rem; }
        .category-card {
            border: 1px solid var(--nit-brand-borderprimary); box-shadow: 0 4px 6px rgba(0,0,0,0.35); border-radius: 12px;
            overflow: hidden; margin-bottom: 1.5rem !important; background: var(--nit-brand-surface);
        }
        .category-card .card-header {
            background: color-mix(in srgb,var(--nit-brand-background) 40%,var(--nit-brand-surface));
            border-bottom: 1px solid var(--nit-brand-borderprimary); font-size: 1.05rem; color: var(--nit-brand-textprimary);
            padding: 0.9rem 1.25rem; display: flex; align-items: center; justify-content: space-between;
        }
        .category-card .cat-count {
            font-size: 0.78rem; font-weight: 600; color: var(--nit-brand-textsecondary);
            background: var(--nit-brand-surface); border: 1px solid var(--nit-brand-borderprimary);
            border-radius: 999px; padding: 0.1rem 0.6rem;
        }
        .category-card .card-body { padding: 1.25rem; display: flex; flex-wrap: wrap; }
        .category-card.ca-empty { display: none; }
        .ca-toolbar .ca-toggle {
            display: inline-flex; align-items: center; gap: 0.4rem; margin: 0;
            font-size: 0.85rem; color: var(--nit-brand-textsecondary); cursor: pointer; user-select: none;
        }
        .ca-toolbar .ca-toggle input { width: 1rem; height: 1rem; cursor: pointer; }
        .ca-emptystate {
            padding: 2rem 1.25rem; margin-bottom: 1.25rem; text-align: center;
            border: 1px dashed var(--nit-brand-borderprimary); border-radius: 0.6rem;
            background: color-mix(in srgb,var(--nit-brand-background) 40%,var(--nit-brand-surface));
            color: var(--nit-brand-textsecondary);
        }
        /* Sticks to the bottom of the viewport so Save is reachable from anywhere in a long list. */
        .ca-savebar {
            position: sticky; bottom: 0; z-index: 5;
            display: flex; flex-wrap: wrap; align-items: center; gap: 0.6rem;
            padding: 0.75rem 1.1rem; margin-bottom: 1.5rem;
            border: 1px solid var(--nit-brand-borderprimary); border-radius: 0.6rem;
            background: var(--nit-brand-surface);
            box-shadow: 0 -2px 10px rgba(0,0,0,0.18);
        }
        .ca-savebar .ca-counter { font-size: 0.9rem; color: var(--nit-brand-textsecondary); }
        .ca-savebar #courses-reset { margin-inline-start: auto; }
        .ca-pill {
            font-size: 0.75rem; font-weight: 600; padding: 0.1rem 0.6rem; border-radius: 999px;
            background: color-mix(in srgb,var(--nit-brand-warning) 18%,transparent);
            border: 1px solid color-mix(in srgb,var(--nit-brand-warning) 45%,transparent);
            color: var(--nit-brand-textprimary);
        }
        /* Per-category bulk controls, in the card header next to the count. */
        .category-card .cat-tools { display: flex; align-items: center; gap: 0.4rem; }
        .category-card .cat-bulk {
            border: 1px solid var(--nit-brand-borderprimary); border-radius: 999px;
            background: transparent; color: var(--nit-brand-textsecondary);
            font-size: 0.75rem; line-height: 1.5; padding: 0.05rem 0.55rem; cursor: pointer;
        }
        .category-card .cat-bulk:hover {
            background: var(--nit-brand-hoverbackground); color: var(--nit-brand-hovertext);
            border-color: var(--nit-brand-primary);
        }
        .academy-modal-backdrop {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: color-mix(in srgb,var(--nit-brand-background) 50%,transparent);
            display: flex; align-items: center; justify-content: center; z-index: 1050;
        }
        .academy-modal { background: var(--nit-brand-surface); color: var(--nit-brand-textprimary); border-radius: 10px; padding: 1.5rem; max-width: 440px; width: 90%; box-shadow: 0 12px 30px rgba(0,0,0,0.35); }
        .academy-modal-title { margin-bottom: 0.75rem; font-weight: 600; }
        .academy-modal-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.25rem; }

        /* A price row: the currency picker and the amount side by side. */
        .sub-pricerow { display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.25rem; }
        .sub-pricerow select, .sub-pricerow input[readonly] { flex: 1 1 60%; }
        .sub-pricerow input[type="number"] { flex: 1 1 40%; }
        /* In-form category placement picker, framed so it reads as its own optional panel. */
        .sub-cats-box {
            border: 1px solid var(--nit-brand-borderprimary); border-radius: 0.6rem;
            padding: 0.9rem 1rem; margin-bottom: 1rem;
            background: color-mix(in srgb, var(--nit-brand-background) 40%, var(--nit-brand-surface));
        }
        .sub-cats-box .sub-cats-help { font-size: 0.8rem; margin: 0.35rem 0 0.6rem; }
        .sub-cats-box select { height: auto; }
    </style>

    <div id="course-selector-area" style="display:none;">
        <div class="ca-toolbar">
            <div class="ca-field">
                <label for="target-subscription"><?php echo $STR['sub_target']; ?></label>
                <select id="target-subscription" class="form-control">
                    <option value=""><?php echo $STR['sub_select_placeholder']; ?></option>
                </select>
            </div>
            <div class="ca-field ca-search">
                <label for="course-search"><?php echo $STR['ui_search']; ?></label>
                <input type="search" id="course-search" class="form-control" placeholder="<?php echo s($STR['sub_courses_search']); ?>">
            </div>
            <div class="ca-actions">
                <label class="ca-toggle" for="ca-only-selected">
                    <input type="checkbox" id="ca-only-selected">
                    <?php echo $STR['sub_ca_onlyselected']; ?>
                </label>
                <button id="courses-select-all" type="button" class="btn btn-outline-secondary btn-sm"><?php echo $STR['sub_selectall']; ?></button>
                <button id="courses-clear" type="button" class="btn btn-outline-secondary btn-sm"><?php echo $STR['sub_clear']; ?></button>
            </div>
        </div>

        <!-- Until a plan is picked there is nothing to tick against, so say so instead
             of showing a live-looking grid whose checkboxes go nowhere. -->
        <div id="ca-empty" class="ca-emptystate"><?php echo $STR['sub_ca_pickplan']; ?></div>

        <div id="ca-body" style="display:none;">
            <div id="categories-container" class="mb-2"></div>
            <p id="ca-nomatch" class="text-muted" style="display:none;"><?php echo $STR['sub_ca_nomatch']; ?></p>

            <!-- Sticky so Save stays reachable however far down the course list you are. -->
            <div class="ca-savebar">
                <span id="ca-counter" class="ca-counter"></span>
                <span id="ca-dirty" class="ca-pill" style="display:none;"><?php echo $STR['sub_ca_unsaved']; ?></span>
                <button id="courses-reset" type="button" class="btn btn-outline-secondary btn-sm" disabled><?php echo $STR['sub_ca_reset']; ?></button>
                <button id="save-course-selection" class="btn btn-primary" disabled><?php echo $STR['sub_save_courses']; ?></button>
            </div>
        </div>
    </div>

    </div><!-- /tab: courses -->

    <!-- ══ TAB 3: the shared financial report, narrowed to plan sales ═══════════════
         The same panel as the Reports tab on manage_courses, manage_coupons and
         manage_offers, and the same one the master report runs with every scope; see
         \local_nit_finance\output\report_panel.

         It replaces the old "User subscriptions" list, which showed who held what but not
         what any of it earned. Every plan sale is still listed here, with the money it
         brought in — what is gone with that list is its Unsubscribe button; the API behind
         it (`unsubscribe_user`) is untouched and still callable. -->

    <div data-subtabpane="reports" role="tabpanel" hidden>
        <?php echo report_panel::render(revenue::SCOPE_SUBSCRIPTIONS); ?>
    </div><!-- /tab: reports -->

    <!-- ══ TAB 4: renewal reminders ═════════════════════════════════════════════
         One window, two effects: it is when the warning goes out AND when the Renew
         button appears on the student's plan card. Saving re-runs the calculation over
         every live subscription there and then, which is why the button says so. -->
    <div data-subtabpane="reminders" role="tabpanel" hidden>

        <h4 class="mt-4"><?php echo $STR['rem_heading']; ?></h4>
        <p class="text-muted" style="max-width:70ch;"><?php echo $STR['rem_desc']; ?></p>

        <div class="card" style="max-width:640px;">
            <div class="card-body">

                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="rem-enabled">
                    <label class="form-check-label" for="rem-enabled"><?php echo $STR['rem_enabled']; ?></label>
                    <div><small class="text-muted"><?php echo $STR['rem_enabled_help']; ?></small></div>
                </div>

                <div class="mb-2">
                    <strong><?php echo $STR['rem_days']; ?></strong>
                    <div><small class="text-muted" id="rem-days-help"><?php echo $STR['rem_days_help']; ?></small></div>
                </div>

                <div id="rem-days-list" class="mb-2"></div>

                <button type="button" id="rem-day-add"
                        class="btn btn-sm btn-outline-primary mb-3"><?php echo $STR['rem_days_add']; ?></button>

                <!-- The day-of message is its own tick rather than a "0" row: it is not a
                     lead time at all (it goes out AFTER the plan lapses), and typing 0 into
                     a box labelled "days before expiry" reads like a mistake. -->
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="rem-onexpiry">
                    <label class="form-check-label" for="rem-onexpiry"><?php echo $STR['rem_onexpiry']; ?></label>
                    <div><small class="text-muted"><?php echo $STR['rem_onexpiry_help']; ?></small></div>
                </div>

                <div class="alert alert-secondary" id="rem-window" style="display:none;"></div>
                <div class="alert alert-info" id="rem-preview" style="display:none;"></div>
                <div class="alert alert-warning" style="max-width:60ch;"><small><?php
                    echo $STR['rem_recalc_note']; ?></small></div>

                <button type="button" id="rem-save" class="btn btn-primary"><?php echo $STR['rem_save']; ?></button>
                <button type="button" id="rem-refresh" class="btn btn-link"><?php echo $STR['ui_refresh']; ?></button>
            </div>
        </div>

    </div><!-- /tab: reminders -->

</div>
<?php

echo html_writer::script(<<<'JS'
(function () {
    var CFG = window.ACADEMY_SUB;
    var STR = window.ACADEMY_STR || {};
    function str(k){return (k in STR)?STR[k]:k;}
    function strf(k,params){var s=str(k);if(params==null){return s;}if(typeof params!=='object'){return s.replace(/\{\$a\}/g,params);}return s.replace(/\{\$a->(\w+)\}/g,function(m,name){return (name in params)?params[name]:m;});}
    function sstat(s){return str('sstat_'+s)!=='sstat_'+s?str('sstat_'+s):(s==='inactive'?str('sub_inactive'):(s==='active'?str('ui_active'):s));}
    function $(id) { return document.getElementById(id); }

    var PAGE_SIZE = 10;
    var subPager = null;
    function pagerLabels() { return { info: str('ui_pager_info') }; }

    function msg(text, type) {
        var el = $('sub-message');
        el.textContent = text;
        el.className = 'alert alert-' + (type || 'info');
        el.style.display = 'block';
        if (type === 'success') { setTimeout(function () { el.style.display = 'none'; }, 3000); }
    }

    // GET for reads, POST for mutations (the API requires POST + sesskey for state changes).
    function api(func, params, method) {
        params = params || {};
        method = method || 'GET';
        var data = new URLSearchParams({ function: func, sesskey: CFG.sesskey });
        if (CFG.lang) { data.append('alang', CFG.lang); }
        Object.keys(params).forEach(function (k) { data.append(k, params[k]); });
        var opts, url = CFG.endpoint;
        if (method === 'POST') {
            opts = { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: data.toString() };
        } else {
            url = CFG.endpoint + '?' + data.toString();
            opts = {};
        }
        return fetch(url, opts)
            .then(function (r) { return r.text(); })
            .then(function (text) {
                var json;
                try { json = JSON.parse(text); }
                catch (e) { throw new Error(str('err_sessionexpired')); }
                if (json.status !== 'success') { throw new Error(json.error || str('err_requestfailed')); }
                return json.data;
            });
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    // ── Multilang helpers: a single field holds two languages via {mlang en}…{mlang}{mlang ar}…{mlang}. ──
    function parseMultilang(value) {
        var out = { en: '', ar: '' };
        var raw = String(value == null ? '' : value);
        var m, found = false;
        var re2 = /\{\s*mlang\s+([a-zA-Z0-9_-]+)\s*\}([\s\S]*?)\{\s*mlang\s*\}/g;
        while ((m = re2.exec(raw)) !== null) {
            found = true;
            var code2 = m[1].toLowerCase();
            if (code2.indexOf('ar') === 0) { out.ar = m[2].trim(); }
            else if (code2.indexOf('en') === 0) { out.en = m[2].trim(); }
        }
        if (found) { return out; }
        var re1 = /<span[^>]*\blang\s*=\s*"([a-zA-Z0-9_-]+)"[^>]*>([\s\S]*?)<\/span>/g;
        while ((m = re1.exec(raw)) !== null) {
            found = true;
            var code1 = m[1].toLowerCase();
            if (code1.indexOf('ar') === 0) { out.ar = m[2].trim(); }
            else if (code1.indexOf('en') === 0) { out.en = m[2].trim(); }
        }
        if (!found) { out.en = raw; }
        return out;
    }

    function buildMultilang(en, ar) {
        en = String(en == null ? '' : en).trim();
        ar = String(ar == null ? '' : ar).trim();
        if (en && ar) { return '{mlang en}' + en + '{mlang}{mlang ar}' + ar + '{mlang}'; }
        // Tag an Arabic-only value so re-editing puts it back in the Arabic box, not the English one.
        if (ar) { return '{mlang ar}' + ar + '{mlang}'; }
        return en;
    }

    function displayName(value) {
        var v = parseMultilang(value);
        return [v.en, v.ar].filter(function (x) { return x; }).join(' / ') || value || '';
    }

    function courseNames(sub) {
        return AcademyUI.tagList((sub.courses || []).map(function (c) { return c.fullname; }),
            { more: str('ui_showmore'), less: str('ui_showless') });
    }

    // Plan name over its description, both resolved from the stored {mlang} markup.
    function titleCell(name, description) {
        var title = displayName(name);
        var sub = description ? displayName(description) : '';
        return '<div class="acad-cell-title">' + esc(title) + '</div>' +
            (sub ? '<div class="acad-cell-sub">' + esc(sub) + '</div>' : '');
    }

    var ALL_SUBS = [];

    /* What the "Categories" column says. The three states of the assignment, named:
       an explicit list, the deliberate "all categories", or nothing chosen — which is
       not blank but "worked out from the plan's courses", and saying so is the whole
       point of the column. */
    function categoryCell(s) {
        var ids = s.categories || [];
        if (!ids.length) { return '<span class="text-muted">' + esc(str('sub_categories_auto')) + '</span>'; }
        if (ids.indexOf(0) !== -1) { return esc(str('sub_categories_allopt')); }
        var names = ids.map(function (id) {
            var hit = (CFG.categories || []).filter(function (c) { return c.id === id; })[0];
            /* The indent marks are for the picker, not for a table cell. */
            return hit ? hit.name.replace(/^(?:— )+/, '') : ('#' + id);
        });
        return AcademyUI.tagList(names, { more: str('ui_showmore'), less: str('ui_showless') });
    }

    function renderSubRows(items) {
        var tbody = $('sub-table').querySelector('tbody');
        tbody.innerHTML = '';
        items.forEach(function (s) {
            var tr = document.createElement('tr');
            var toggle = s.status === 'active'
                ? '<button class="btn btn-sm btn-warning" data-act="deactivate" data-id="' + s.id + '">' + esc(str('ui_deactivate')) + '</button>'
                : '<button class="btn btn-sm btn-success" data-act="activate" data-id="' + s.id + '">' + esc(str('ui_activate')) + '</button>';
            tr.innerHTML =
                '<td>' + esc(s.id) + '</td>' +
                '<td>' + titleCell(s.name, s.description) + '</td>' +
                '<td>' + esc(s.price) + '</td>' +
                '<td>' + esc(s.duration_days) + '</td>' +
                '<td class="col-tags">' + courseNames(s) + '</td>' +
                '<td class="col-tags">' + categoryCell(s) + '</td>' +
                '<td>' + esc(sstat(s.status)) + '</td>' +
                '<td class="col-tight"><div class="acad-actions">' +
                    '<button class="btn btn-sm btn-secondary" data-act="edit" data-id="' + s.id + '">' + esc(str('ui_edit')) + '</button> ' +
                    toggle + ' ' +
                    '<button class="btn btn-sm btn-danger" data-act="delete" data-id="' + s.id + '">' + esc(str('ui_delete')) + '</button>' +
                '</div></td>';
            tr._sub = s;
            tbody.appendChild(tr);
        });
    }

    function loadSubs() {
        var tbody = $('sub-table').querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="8">' + esc(str('ui_loading')) + '</td></tr>';
        api('get_subscriptions').then(function (rows) {
            ALL_SUBS = rows;
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="8">' + esc(str('sub_none_admin')) + '</td></tr>';
                $('sub-table-pager').innerHTML = '';
                populateSubscriptionDropdown();
                return;
            }
            if (subPager) {
                subPager.setRows(rows);
            } else {
                subPager = AcademyUI.paginate({
                    rows: rows, pageSize: PAGE_SIZE, pagerEl: $('sub-table-pager'),
                    labels: pagerLabels(), render: renderSubRows
                });
            }
            populateSubscriptionDropdown();
        }).catch(function (e) { msg(e.message, 'danger'); });
    }

    // ── The two price rows ──
    // The home country's row is the plan's nit_sub_price row for that country; the
    // Default price is the plan's own price/currency. Same shape a course has.
    function homeRow(sub) {
        var rows = (sub && sub.prices) || [];
        for (var i = 0; i < rows.length; i++) {
            if (rows[i].country === CFG.homecountry && Number(rows[i].is_active)) { return rows[i]; }
        }
        return null;
    }

    // The Default picker deliberately lacks the home currency. A plan saved before
    // that rule still carries it, so it is shown — flagged — and the server refuses
    // the save until the admin picks another.
    function setDefaultCurrency(code) {
        var sel = $('f-currency');
        Array.prototype.forEach.call(sel.querySelectorAll('option[data-legacy]'), function (o) { o.remove(); });
        if (code && !sel.querySelector('option[value="' + code + '"]')) {
            var o = document.createElement('option');
            o.value = code;
            o.textContent = (CFG.currencies && CFG.currencies[code]) || code;
            o.setAttribute('data-legacy', '1');
            sel.insertBefore(o, sel.firstChild);
        }
        sel.value = code || CFG.defaultcurrency;
    }

    function showForm(sub) {
        $('sub-form-title').textContent = sub ? strf('sub_edit_titled', sub.id) : str('sub_new');
        var nm = parseMultilang(sub ? sub.name : '');
        var ds = parseMultilang(sub ? (sub.description || '') : '');
        $('f-id').value       = sub ? sub.id : '';
        $('f-name-en').value  = nm.en;
        $('f-name-ar').value  = nm.ar;
        $('f-desc-en').value  = ds.en;
        $('f-desc-ar').value  = ds.ar;
        var home = homeRow(sub);
        // Fixed to the home currency whatever a legacy row was saved in.
        $('f-homecurrency').value = CFG.homecurrency;
        $('f-homeprice').value    = home ? home.price : '';
        setDefaultCurrency((sub && sub.currency) ? sub.currency : CFG.defaultcurrency);
        $('f-price').value    = sub ? sub.price : '';
        $('f-days').value     = sub ? sub.duration_days : '';
        $('f-refundhours').value = (sub && sub.refund_hours !== null && sub.refund_hours !== undefined) ? sub.refund_hours : '';
        $('f-refundfee').value   = (sub && sub.refund_fee !== null && sub.refund_fee !== undefined) ? sub.refund_fee : '';
        fillCategories(sub ? sub.categories : []);
        $('f-active').checked = sub ? (sub.status === 'active') : true;
        $('sub-form-card').style.display = 'block';
    }
    function hideForm() { $('sub-form-card').style.display = 'none'; }

    /* ── Category placement ────────────────────────────────────────────────────
       The picker is built once from the categories shipped with the page, then
       re-selected each time the form opens. "All categories" (value 0) is
       mutually exclusive with the rest: picking it clears the others, and
       picking any other clears it, because "all plus one" is not a third state. */
    function buildCategoryOptions() {
        var sel = $('f-categories');
        if (!sel || sel.getAttribute('data-built')) { return; }
        (CFG.categories || []).forEach(function (cat) {
            var o = document.createElement('option');
            o.value = cat.id;
            o.textContent = cat.name;
            sel.appendChild(o);
        });
        sel.setAttribute('data-built', '1');
        sel.addEventListener('change', function () {
            var all = sel.querySelector('option[value="0"]');
            if (!all) { return; }
            var others = Array.prototype.filter.call(sel.selectedOptions, function (o) {
                return o.value !== '0';
            });
            if (all.selected && others.length) {
                /* Whichever the admin just touched wins; the other side is cleared. */
                if (sel._allwas) { all.selected = false; } else {
                    others.forEach(function (o) { o.selected = false; });
                }
            }
            sel._allwas = all.selected;
        });
    }

    function fillCategories(ids) {
        buildCategoryOptions();
        var sel = $('f-categories');
        if (!sel) { return; }
        var want = (ids || []).map(function (v) { return String(v); });
        Array.prototype.forEach.call(sel.options, function (o) {
            o.selected = want.indexOf(o.value) !== -1;
        });
        var all = sel.querySelector('option[value="0"]');
        sel._allwas = !!(all && all.selected);
    }

    function collectCategories() {
        var sel = $('f-categories');
        if (!sel) { return []; }
        return Array.prototype.map.call(sel.selectedOptions, function (o) {
            return parseInt(o.value, 10);
        });
    }

    function save() {
        var id = $('f-id').value;
        var params = {
            name: buildMultilang($('f-name-en').value, $('f-name-ar').value),
            description: buildMultilang($('f-desc-en').value, $('f-desc-ar').value),
            price: $('f-price').value,
            currency: $('f-currency').value,
            home_price: $('f-homeprice').value,
            home_currency: $('f-homecurrency').value,
            duration_days: $('f-days').value,
            refund_hours: $('f-refundhours').value,
            refund_fee: $('f-refundfee').value,
            // Always sent, including as an empty list: "[]" is the instruction that clears the
            // assignment and hands placement back to the plan's courses.
            categories: JSON.stringify(collectCategories())
        };
        var p;
        if (id) {
            params.id = id;
            params.status = $('f-active').checked ? 'active' : 'inactive';
            p = api('update_subscription', params, 'POST');
        } else {
            params.active = $('f-active').checked ? 1 : 0;
            p = api('create_subscription', params, 'POST');
        }
        p.then(function () {
            msg(id ? str('sub_updated') : str('sub_created'), 'success');
            hideForm();
            loadSubs();
        }).catch(function (e) { msg(e.message, 'danger'); });
    }

    $('sub-table').addEventListener('click', function (ev) {
        var btn = ev.target.closest('button[data-act]');
        if (!btn) { return; }
        var id = btn.getAttribute('data-id');
        var act = btn.getAttribute('data-act');
        var row = btn.closest('tr');
        if (act === 'edit') { showForm(row._sub); return; }
        if (act === 'activate') {
            api('activate_subscription', { id: id }, 'POST').then(function () { msg(str('sub_activated'), 'success'); loadSubs(); }).catch(function (e) { msg(e.message, 'danger'); });
        } else if (act === 'deactivate') {
            api('deactivate_subscription', { id: id }, 'POST').then(function () { msg(str('sub_deactivated'), 'success'); loadSubs(); }).catch(function (e) { msg(e.message, 'danger'); });
        } else if (act === 'delete') {
            if (!confirm(str('sub_confirm_delete'))) { return; }
            api('delete_subscription', { id: id }, 'POST').then(function () { msg(str('sub_deleted'), 'success'); loadSubs(); }).catch(function (e) { msg(e.message, 'danger'); });
        }
    });

    $('sub-new').addEventListener('click', function () { showForm(null); });
    $('sub-refresh').addEventListener('click', loadSubs);
    $('sub-save').addEventListener('click', save);
    $('sub-cancel').addEventListener('click', hideForm);
    $('f-categories-clear').addEventListener('click', function () { fillCategories([]); });

    // ── Course access ──
    // The grid only makes sense against a chosen plan, so everything below keys off
    // SAVED_IDS: the set of courses the selected plan currently has in the database.
    // The live checkboxes are the working copy; the difference between the two is
    // what drives the counter, the "unsaved changes" pill and the Save/Reset buttons.
    var SAVED_IDS = null;

    function allCheckboxes() {
        return Array.prototype.slice.call(document.querySelectorAll('.course-checkbox'));
    }

    function checkedIdSet() {
        var out = {};
        allCheckboxes().forEach(function (cb) { if (cb.checked) { out[cb.value] = true; } });
        return out;
    }

    function sameIdSet(a, b) {
        if (!a || !b) { return false; }
        var ka = Object.keys(a), kb = Object.keys(b);
        return ka.length === kb.length && ka.every(function (k) { return b[k]; });
    }

    function isDirty() {
        return SAVED_IDS !== null && !sameIdSet(checkedIdSet(), SAVED_IDS);
    }

    function loadCategories() {
        api('get_categories_with_courses').then(function(categories) {
            var container = $('categories-container');
            if (!categories.length) {
                container.innerHTML = '<span class="text-muted">' + esc(str('sub_no_categories')) + '</span>';
                return;
            }
            var html = '';
            categories.forEach(function(cat) {
                html += '<div class="card category-card" data-cat-id="' + esc(cat.id) + '">';
                html += '<div class="card-header"><strong>' + esc(cat.name) + '</strong>' +
                    '<span class="cat-tools">' +
                        '<span class="cat-count">0/' + cat.courses.length + '</span>' +
                        '<button type="button" class="cat-bulk" data-bulk="all">' + esc(str('sub_ca_catall')) + '</button>' +
                        '<button type="button" class="cat-bulk" data-bulk="none">' + esc(str('sub_ca_catnone')) + '</button>' +
                    '</span></div>';
                html += '<div class="card-body">';
                cat.courses.forEach(function(c) {
                    html += '<label class="course-chip" data-course-name="' + esc(String(c.fullname).toLowerCase()) + '">' +
                        '<input type="checkbox" class="course-checkbox" id="cb-course-' + c.id + '" value="' + c.id + '">' +
                        esc(c.fullname) + '</label>';
                });
                html += '</div></div>';
            });
            container.innerHTML = html;
            $('course-selector-area').style.display = 'block';
            applySubscriptionCourseSelection();
        }).catch(function(e) { msg(e.message, 'danger'); });
    }

    // Search text and the "selected only" toggle are one filter; a category with no
    // surviving chip hides entirely so the page does not fill with empty cards.
    function filterCourses() {
        var q = ($('course-search').value || '').trim().toLowerCase();
        var onlySelected = $('ca-only-selected').checked;
        var shown = 0;
        document.querySelectorAll('#categories-container .category-card').forEach(function(card) {
            var visible = 0;
            card.querySelectorAll('.course-chip').forEach(function(chip) {
                var match = !q || (chip.getAttribute('data-course-name') || '').indexOf(q) !== -1;
                if (match && onlySelected) { match = chip.querySelector('.course-checkbox').checked; }
                chip.style.display = match ? '' : 'none';
                if (match) { visible++; }
            });
            card.classList.toggle('ca-empty', visible === 0);
            shown += visible;
        });
        $('ca-nomatch').style.display = (shown === 0 && SAVED_IDS) ? '' : 'none';
    }

    // Counter, per-category tallies, dirty pill and button states — everything that
    // reflects the current tick marks rather than changing them.
    function refreshCourseState() {
        var boxes = allCheckboxes();
        var selected = boxes.filter(function (cb) { return cb.checked; }).length;
        $('ca-counter').textContent = strf('sub_ca_counter', { selected: selected, total: boxes.length });

        document.querySelectorAll('#categories-container .category-card').forEach(function (card) {
            var inCat = card.querySelectorAll('.course-checkbox').length;
            var on = card.querySelectorAll('.course-checkbox:checked').length;
            var label = card.querySelector('.cat-count');
            if (label) {
                label.textContent = strf('sub_ca_catcount', { selected: on, total: inCat });
            }
        });

        var dirty = isDirty();
        $('ca-dirty').style.display = dirty ? '' : 'none';
        $('save-course-selection').disabled = !dirty;
        $('courses-reset').disabled = !dirty;
    }

    function populateSubscriptionDropdown() {
        var select = $('target-subscription');
        var prevValue = select.value;
        select.innerHTML = '<option value="">' + esc(str('sub_select_placeholder')) + '</option>';
        ALL_SUBS.forEach(function(sub) {
            if (sub.status === 'active') {
                select.innerHTML += '<option value="' + sub.id + '">' + esc(displayName(sub.name)) + ' (#' + sub.id + ')</option>';
            }
        });
        select.value = prevValue;
        lastTarget = select.value;
        applySubscriptionCourseSelection();
    }

    // Reset the working copy to whatever the chosen plan has saved.
    function applySubscriptionCourseSelection() {
        var subId = $('target-subscription').value;
        if (!subId) {
            SAVED_IDS = null;
            allCheckboxes().forEach(function (cb) { cb.checked = false; });
            $('ca-empty').style.display = '';
            $('ca-body').style.display = 'none';
            refreshCourseState();
            return;
        }
        var sub = ALL_SUBS.find(function(s) { return String(s.id) === String(subId); });
        SAVED_IDS = {};
        ((sub && sub.courses) || []).forEach(function(c) { SAVED_IDS[String(c.id)] = true; });
        allCheckboxes().forEach(function(cb) { cb.checked = !!SAVED_IDS[cb.value]; });
        $('ca-empty').style.display = 'none';
        $('ca-body').style.display = '';
        filterCourses();
        refreshCourseState();
    }

    var lastTarget = '';
    $('target-subscription').addEventListener('change', function () {
        // Switching plans throws the working copy away, so ask first when it differs.
        if (isDirty() && !confirm(str('sub_ca_discard'))) {
            this.value = lastTarget;
            return;
        }
        lastTarget = this.value;
        applySubscriptionCourseSelection();
    });

    $('course-search').addEventListener('input', filterCourses);
    $('ca-only-selected').addEventListener('change', filterCourses);

    // Ticking a chip only changes the working copy; the state readout follows it.
    $('categories-container').addEventListener('change', function (ev) {
        if (ev.target.classList.contains('course-checkbox')) {
            refreshCourseState();
            if ($('ca-only-selected').checked) { filterCourses(); }
        }
    });

    // Per-category All / None, limited to the chips the current filter leaves visible.
    $('categories-container').addEventListener('click', function (ev) {
        var btn = ev.target.closest('.cat-bulk');
        if (!btn) { return; }
        var on = btn.getAttribute('data-bulk') === 'all';
        btn.closest('.category-card').querySelectorAll('.course-chip').forEach(function (chip) {
            if (chip.style.display !== 'none') { chip.querySelector('.course-checkbox').checked = on; }
        });
        refreshCourseState();
        if ($('ca-only-selected').checked) { filterCourses(); }
    });

    function bulkVisible(on) {
        document.querySelectorAll('.course-chip').forEach(function(chip) {
            if (chip.style.display !== 'none') { chip.querySelector('.course-checkbox').checked = on; }
        });
        refreshCourseState();
        if ($('ca-only-selected').checked) { filterCourses(); }
    }
    $('courses-select-all').addEventListener('click', function() { bulkVisible(true); });
    $('courses-clear').addEventListener('click', function() { bulkVisible(false); });
    $('courses-reset').addEventListener('click', applySubscriptionCourseSelection);

    $('save-course-selection').addEventListener('click', function() {
        var subId = $('target-subscription').value;
        if (!subId) {
            msg(str('sub_select_target'), 'danger');
            return;
        }
        var courseIds = [];
        document.querySelectorAll('.course-checkbox:checked').forEach(function(cb) {
            courseIds.push(parseInt(cb.value, 10));
        });
        api('set_subscription_courses', {
            subscriptionid: subId,
            courseids: JSON.stringify(courseIds)
        }, 'POST').then(function() {
            msg(str('sub_courses_assigned'), 'success');
            // loadSubs() refreshes ALL_SUBS, which re-seeds SAVED_IDS from the server.
            loadSubs();
        }).catch(function(e) { msg(e.message, 'danger'); });
    });

    // ── Tabs ────────────────────────────────────────────────────────────────────
    // Panes, not separate pages: everything is already loaded, so switching is instant
    // and no in-progress edit in another pane is thrown away. The choice rides in the
    // URL hash so a reload lands where the admin left off.
    var TABS = ['plans', 'courses', 'reports', 'reminders'];

    // #users was this tab's name while it listed user subscriptions. Links to it are already
    // out there — in bookmarks, in tickets — so they keep working.
    var LEGACY = { users: 'reports' };

    function showTab(name) {
        name = LEGACY[name] || name;
        if (TABS.indexOf(name) === -1) { name = TABS[0]; }
        TABS.forEach(function (t) {
            var pane = document.querySelector('[data-subtabpane="' + t + '"]');
            var btn = document.querySelector('[data-subtab="' + t + '"]');
            if (pane) { pane.hidden = (t !== name); }
            if (btn) {
                btn.classList.toggle('active', t === name);
                btn.setAttribute('aria-selected', t === name ? 'true' : 'false');
            }
        });
        if (name === 'reminders') { loadReminders(); }
        if (name === 'reports' && window.NITFR) { window.NITFR.ensure(); }
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-subtab]'), function (btn) {
        btn.addEventListener('click', function () {
            var name = btn.getAttribute('data-subtab');
            if (window.history && history.replaceState) {
                history.replaceState(null, '', '#' + name);
            } else {
                location.hash = name;
            }
            showTab(name);
        });
    });

    // ── Renewal reminders ───────────────────────────────────────────────────────
    var remLoaded = false;
    var remMax = 365;

    // One row per lead time. Kept as inputs rather than a comma-separated box so a typo
    // is visible as its own row and the min/max are enforced by the field itself.
    function remRow(value) {
        var row = document.createElement('div');
        row.className = 'input-group input-group-sm mb-2';
        row.style.maxWidth = '340px';

        var input = document.createElement('input');
        input.type = 'number';
        input.className = 'form-control rem-day';
        input.min = '1';
        input.max = String(remMax);
        input.value = String(value || '');

        var unit = document.createElement('span');
        unit.className = 'input-group-text';
        unit.textContent = str('rem_day_unit');

        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-outline-danger';
        remove.textContent = str('rem_remove');
        remove.addEventListener('click', function () {
            row.parentNode.removeChild(row);
            remPreview();
        });

        input.addEventListener('change', remPreview);

        row.appendChild(input);
        row.appendChild(unit);
        row.appendChild(remove);
        return row;
    }

    // The rows are "days before"; the tick is the day itself, which the server stores as a
    // lead time of 0. Sorted descending, so 0 lands last exactly as it does server-side.
    function remDays() {
        var out = [];
        Array.prototype.forEach.call(document.querySelectorAll('#rem-days-list .rem-day'), function (el) {
            var v = parseInt(el.value, 10);
            if (v >= 1 && v <= remMax && out.indexOf(v) === -1) { out.push(v); }
        });
        if ($('rem-onexpiry').checked) { out.push(0); }
        out.sort(function (a, b) { return b - a; });
        return out;
    }

    // What the numbers on screen mean right now: the renew window, and how many people
    // saving would reach. Asked of the server so the count matches what saving will do.
    function remPreview() {
        var days = remDays();
        var list = $('rem-days-list');
        var win = $('rem-window');

        if (!list.children.length) {
            list.innerHTML = '<p class="text-muted mb-2">' + esc(str('rem_days_none')) + '</p>';
        } else {
            var placeholder = list.querySelector('p.text-muted');
            if (placeholder) { list.removeChild(placeholder); }
        }

        // The Renew button follows the largest lead time BEFORE expiry — the day-of message
        // is sent when it is already too late to renew early, so it opens no window.
        var lead = days.filter(function (d) { return d > 0; })[0] || 0;

        win.style.display = 'block';
        if (!$('rem-enabled').checked) {
            win.textContent = str('rem_window_off');
        } else {
            win.textContent = lead ? strf('rem_window_note', lead) : str('rem_window_none');
        }

        if (!days.length) {
            $('rem-preview').style.display = 'none';
            return;
        }

        api('preview_reminder_settings', { days: days.join(',') }).then(function (d) {
            $('rem-preview').style.display = 'block';
            $('rem-preview').textContent = strf('rem_preview', { due: d.due, active: d.active });
        }).catch(function () { $('rem-preview').style.display = 'none'; });
    }

    function renderReminders(d) {
        remMax = d.max_days || remMax;
        $('rem-enabled').checked = !!d.enabled;
        $('rem-days-help').textContent = strf('rem_days_help', remMax);

        var days = d.days || [];
        $('rem-onexpiry').checked = days.indexOf(0) !== -1;

        var list = $('rem-days-list');
        list.innerHTML = '';
        days.forEach(function (day) {
            if (day > 0) { list.appendChild(remRow(day)); }
        });

        remPreview();
    }

    function loadReminders(force) {
        if (remLoaded && !force) { return; }
        remLoaded = true;
        api('get_reminder_settings').then(renderReminders)
            .catch(function (e) { remLoaded = false; msg(e.message, 'danger'); });
    }

    $('rem-day-add').addEventListener('click', function () {
        var list = $('rem-days-list');
        var placeholder = list.querySelector('p.text-muted');
        if (placeholder) { list.removeChild(placeholder); }
        var row = remRow('');
        list.appendChild(row);
        row.querySelector('.rem-day').focus();
    });

    $('rem-enabled').addEventListener('change', remPreview);
    $('rem-onexpiry').addEventListener('change', remPreview);
    $('rem-refresh').addEventListener('click', function () { loadReminders(true); });

    $('rem-save').addEventListener('click', function () {
        var btn = this;
        btn.disabled = true;
        api('save_reminder_settings', {
            enabled: $('rem-enabled').checked ? 1 : 0,
            days: remDays().join(',')
        }, 'POST').then(function (d) {
            msg(strf('rem_applied', { sent: d.sent, cleared: d.cleared }), 'success');
            renderReminders({
                enabled: d.enabled, days: d.days, max_days: remMax
            });
        }).catch(function (e) {
            msg(e.message, 'danger');
        }).then(function () { btn.disabled = false; });
    });

    showTab((location.hash || '').replace('#', ''));

    loadCategories();

    loadSubs();
})();
JS
);

echo $OUTPUT->footer();
