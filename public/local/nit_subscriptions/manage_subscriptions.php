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

admin_externalpage_setup('local_nit_subscriptions_managesubscriptions');
require_capability('local/nit_subscriptions:managesubscriptions', context_system::instance());

global $OUTPUT, $CFG, $PAGE;

$PAGE->set_title(get_string('managesubscriptions', 'local_nit_subscriptions'));
$PAGE->set_heading(get_string('managesubscriptions', 'local_nit_subscriptions'));
$PAGE->requires->js(new moodle_url('/local/nit_subscriptions/ui.js'), true);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managesubscriptions', 'local_nit_subscriptions'));

// Admin note: explain how the price a user sees is chosen. Three key ideas to make clear:
// pricing is ALWAYS by country (the IP only *identifies* a country, it carries no price of its
// own); a SIGNED-IN account is priced on its profile country and nothing else, so an empty one
// means no price and no purchase at all rather than a borrowed price; and every remaining dead
// end — guest, no usable IP — resolves to the plan's default price rather than to some other
// country's price. Same rules as the course pricing page; see
// subscription_manager::resolve_price() / local_payments\country_detector.
// Rendered in the admin's current language (site is en/ar).
// Name the plan's base-price field exactly as the add/edit form labels it, so "default price"
// in the note points at a field the admin can actually see.
$STR_PRICE_LABEL = s(get_string('pkg_field_price', 'local_nit_subscriptions'));
$pricingnote_isar = (strpos(current_language(), 'ar') === 0);
if ($pricingnote_isar) {
    $pricingnote =
        '<strong>كيف يظهر سعر الاشتراك للمستخدم؟</strong><br>'
        . 'السعر يعتمد دائمًا على <strong>دولة المستخدم</strong>. تُحدَّد الدولة بالترتيب التالي، '
        . 'وأوّل خطوة تنجح هي المعتمدة:'
        . '<ol style="margin:.5rem 0 .25rem; padding-inline-start:1.25rem;">'
        . '<li>مستخدم <strong>مسجّل دخول ولبروفايله دولة</strong>: تُؤخذ الدولة من بروفايله '
        . '(الدولة التي سجّل بها).</li>'
        . '<li>مستخدم <strong>مسجّل دخول لكن بروفايله بلا دولة</strong>: <strong>لا يُعرض له أي سعر '
        . 'ولا يستطيع الاشتراك</strong>. تظهر الخطط بأسمائها ومدّتها وكورساتها، لكن مكان السعر تظهر '
        . 'رسالة تطلب تحديد دولته مع رابط لصفحة بياناته، وبمجرد حفظ الدولة تعمل الأسعار والاشتراك '
        . 'فورًا. (لا نُخمّن دولته من الـ IP: الحساب الذي نستطيع سؤاله لا يُسعَّر بالتخمين.)</li>'
        . '<li>زائر <strong>غير مسجّل دخول</strong>: نُخمّن الدولة من عنوان الـ IP (موقعه التقريبي) — '
        . 'فلا بروفايل لديه لنسأله.</li>'
        . '<li>إذا <strong>تعذّر تحديد الدولة</strong> للزائر (فشل تحديد الموقع من الـ IP، أو عنوان داخلي/غير '
        . 'صالح، أو خدمة تحديد الموقع غير مُفعّلة): يظهر <strong>السعر الافتراضي</strong> للخطة مباشرةً.</li>'
        . '</ol>'
        . 'بعد تحديد الدولة: إذا كان للخطة سعرٌ مضبوطٌ <strong>ومفعّل</strong> لتلك الدولة يظهر هذا السعر، '
        . 'وإذا لم يوجد سعرٌ لتلك الدولة يظهر <strong>السعر الافتراضي</strong> للخطة '
        . '(حقل «' . $STR_PRICE_LABEL . '» في نموذج الخطة).<br>'
        . '<span style="opacity:.85;">ملاحظة: عنوان الـ IP نفسه ليس له سعر — هو فقط يحدّد الدولة، '
        . 'ثم يُطبَّق سعر تلك الدولة إن وُجد. ولأن السعر الافتراضي هو المُستخدَم في كل الحالات '
        . 'غير المُغطّاة، يجب أن يكون لكل خطة سعرٌ افتراضيٌّ صحيح.</span><br>'
        . 'تضبط أسعار الدول من داخل نموذج إضافة/تعديل الاشتراك في قسم «Country prices».';
} else {
    $pricingnote =
        '<strong>How is a user&rsquo;s subscription price chosen?</strong><br>'
        . 'The price is always based on the <strong>user&rsquo;s country</strong>. The country is worked out '
        . 'in this order, and the first step that succeeds wins:'
        . '<ol style="margin:.5rem 0 .25rem; padding-inline-start:1.25rem;">'
        . '<li><strong>Logged-in user whose profile has a country</strong>: that profile country is used '
        . '(the country used at registration).</li>'
        . '<li><strong>Logged-in user whose profile has no country</strong>: <strong>no price is shown and '
        . 'they cannot subscribe</strong>. The plans still list &mdash; name, duration, included courses &mdash; '
        . 'but where the price would be they get a short message asking them to set their country, with a link '
        . 'to their profile; saving it switches prices and checkout back on immediately. (Their IP is '
        . 'deliberately not used: an account we can simply ask is not priced by a guess.)</li>'
        . '<li>A visitor who is <strong>not logged in</strong>: the country is guessed from their IP address '
        . '(approximate location) &mdash; there is no profile to ask.</li>'
        . '<li>If a visitor&rsquo;s country <strong>still cannot be determined</strong> (IP lookup fails, the '
        . 'address is private/invalid, or geolocation is not configured): the plan&rsquo;s <strong>default '
        . 'price</strong> is shown straight away.</li>'
        . '</ol>'
        . 'Once the country is known: if the plan has an <strong>active</strong> price set for that country, '
        . 'it is shown; otherwise the plan&rsquo;s <strong>default price</strong> is shown (the '
        . '&ldquo;' . $STR_PRICE_LABEL . '&rdquo; field on the plan itself).<br>'
        . '<span style="opacity:.85;">Note: an IP address has no price of its own &mdash; it only identifies the country, '
        . 'then that country&rsquo;s price is applied if one exists. Because the default price covers every '
        . 'case not matched above, every plan should always carry a valid default price.</span><br>'
        . 'Set per-country prices inside the add/edit subscription form, under &ldquo;Country prices&rdquo;.';
}
echo html_writer::div($pricingnote, 'alert alert-info',
    ['dir' => $pricingnote_isar ? 'rtl' : 'ltr']);

// Localised strings: server-rendered HTML reads $STR['key']; the JS reads window.ACADEMY_STR.
$STR = local_nit_subscriptions_string_map(array(
    'sub_plans_heading', 'sub_new', 'ui_refresh', 'ui_loading', 'ui_save', 'ui_cancel', 'ui_active',
    'ui_activate', 'ui_deactivate', 'ui_edit', 'ui_delete', 'ui_never', 'ui_optional',
    'pkg_col_id', 'pkg_col_name', 'pkg_col_price', 'sub_col_days', 'sub_col_courses', 'pkg_col_status',
    'pkg_col_actions', 'pkg_field_name', 'pkg_field_price', 'pkg_col_user', 'sub_col_subscription',
    'pkg_field_name_en', 'pkg_field_name_ar', 'pkg_field_desc_en', 'pkg_field_desc_ar',
    'pkg_field_currency',
    'sub_prices_heading', 'sub_prices_help', 'sub_price_add', 'sub_price_country',
    'sub_price_currency', 'sub_price_amount', 'sub_price_active', 'sub_price_remove',
    'sub_price_pickcountry',
    'pkg_col_pricepaid', 'pkg_col_expiresat',
    'sub_field_desc', 'sub_field_days', 'sub_courseavail_heading', 'sub_courseavail_desc', 'sub_target',
    'sub_select_placeholder', 'sub_save_courses', 'sub_usersubs_heading', 'sub_usersubs_desc',
    'sub_unsub_title', 'sub_unsub_refund', 'sub_unsubscribe', 'sub_none_admin', 'sub_inactive',
    'sub_edit_titled', 'sub_updated', 'sub_created', 'sub_activated', 'sub_deactivated', 'sub_deleted',
    'sub_confirm_delete', 'sub_no_categories', 'sub_select_target', 'sub_courses_assigned',
    'sub_no_usersubs', 'sub_unsub_confirm', 'sub_unsub_success', 'pkg_unassign_paid',
    'sstat_active', 'sstat_expired', 'sstat_cancelled', 'sstat_pending', 'sstat_payment_failed',
    'ui_pager_info',
    'ui_search', 'sub_courses_search', 'sub_selectall', 'sub_clear',
    'err_sessionexpired', 'err_requestfailed',
));

echo html_writer::script('window.ACADEMY_SUB = ' . json_encode(array(
    'endpoint'   => (new moodle_url('/local/nit_subscriptions/api.php'))->out(false),
    'sesskey'    => sesskey(),
    'lang'       => optional_param('lang', current_language(), PARAM_LANG),
    // Lists for the in-form per-country price editor.
    'countries'  => get_string_manager()->get_list_of_countries(),
    'currencies' => array('EGP', 'USD', 'EUR', 'GBP', 'SAR', 'AED', 'KWD', 'BHD', 'QAR', 'OMR'),
)) . ';');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');
?>
<div id="academy-sub-app">
    <div id="sub-message" class="alert" style="display:none"></div>

    <!-- ── Subscription plans ── -->
    <h4><?php echo $STR['sub_plans_heading']; ?></h4>
    <div class="mb-3">
        <button id="sub-new" class="btn btn-primary"><?php echo $STR['sub_new']; ?></button>
        <button id="sub-refresh" class="btn btn-secondary"><?php echo $STR['ui_refresh']; ?></button>
    </div>

    <table class="table table-striped" id="sub-table">
        <thead>
            <tr>
                <th><?php echo $STR['pkg_col_id']; ?></th><th><?php echo $STR['pkg_col_name']; ?></th><th><?php echo $STR['pkg_col_price']; ?></th><th><?php echo $STR['sub_col_days']; ?></th>
                <th><?php echo $STR['sub_col_courses']; ?></th><th><?php echo $STR['pkg_col_status']; ?></th><th><?php echo $STR['pkg_col_actions']; ?></th>
            </tr>
        </thead>
        <tbody><tr><td colspan="7"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
    </table>
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
            <div class="form-group">
                <label for="f-price"><?php echo $STR['pkg_field_price']; ?></label>
                <input type="number" class="form-control" id="f-price" min="0" step="0.01">
            </div>
            <div class="form-group">
                <label for="f-currency"><?php echo $STR['pkg_field_currency']; ?></label>
                <select class="form-control" id="f-currency">
                    <option value="EGP">EGP - Egyptian Pound</option>
                    <option value="USD">USD - US Dollar</option>
                    <option value="EUR">EUR - Euro</option>
                    <option value="GBP">GBP - British Pound</option>
                    <option value="SAR">SAR - Saudi Riyal</option>
                    <option value="AED">AED - UAE Dirham</option>
                    <option value="KWD">KWD - Kuwaiti Dinar</option>
                    <option value="BHD">BHD - Bahraini Dinar</option>
                    <option value="QAR">QAR - Qatari Riyal</option>
                    <option value="OMR">OMR - Omani Rial</option>
                </select>
            </div>
            <!-- ── Per-country price overrides (optional) ── -->
            <div class="sub-prices-box">
                <div class="sub-prices-head">
                    <strong><?php echo $STR['sub_prices_heading']; ?></strong>
                    <button type="button" id="sub-price-add" class="btn btn-sm btn-outline-primary"><?php echo $STR['sub_price_add']; ?></button>
                </div>
                <p class="sub-prices-help text-muted"><?php echo $STR['sub_prices_help']; ?></p>
                <div class="sub-prices-cols">
                    <span><?php echo $STR['sub_price_country']; ?></span>
                    <span><?php echo $STR['sub_price_currency']; ?></span>
                    <span><?php echo $STR['sub_price_amount']; ?></span>
                    <span><?php echo $STR['sub_price_active']; ?></span>
                    <span></span>
                </div>
                <div id="sub-prices-list"></div>
            </div>
            <div class="form-group">
                <label for="f-days"><?php echo $STR['sub_field_days']; ?></label>
                <input type="number" class="form-control" id="f-days" min="1">
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="f-active" checked>
                <label class="form-check-label" for="f-active"><?php echo $STR['ui_active']; ?></label>
            </div>
            <button id="sub-save" class="btn btn-primary"><?php echo $STR['ui_save']; ?></button>
            <button id="sub-cancel" class="btn btn-link"><?php echo $STR['ui_cancel']; ?></button>
        </div>
    </div>

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
        .academy-modal-backdrop {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: color-mix(in srgb,var(--nit-brand-background) 50%,transparent);
            display: flex; align-items: center; justify-content: center; z-index: 1050;
        }
        .academy-modal { background: var(--nit-brand-surface); color: var(--nit-brand-textprimary); border-radius: 10px; padding: 1.5rem; max-width: 440px; width: 90%; box-shadow: 0 12px 30px rgba(0,0,0,0.35); }
        .academy-modal-title { margin-bottom: 0.75rem; font-weight: 600; }
        .academy-modal-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.25rem; }

        /* In-form per-country price editor. */
        .sub-prices-box {
            border: 1px solid var(--nit-brand-borderprimary); border-radius: 0.6rem;
            padding: 0.9rem 1rem; margin-bottom: 1rem;
            background: color-mix(in srgb, var(--nit-brand-background) 40%, var(--nit-brand-surface));
        }
        .sub-prices-head { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
        .sub-prices-help { font-size: 0.8rem; margin: 0.35rem 0 0.6rem; }
        .sub-prices-cols, .sub-price-row {
            display: grid; grid-template-columns: 1fr 0.9fr 0.8fr auto auto;
            gap: 0.5rem; align-items: center;
        }
        .sub-prices-cols { font-size: 0.75rem; font-weight: 600; color: var(--nit-brand-textsecondary); margin-bottom: 0.35rem; }
        .sub-prices-cols span:nth-child(4) { text-align: center; }
        .sub-price-row { margin-bottom: 0.5rem; }
        .sub-price-row select, .sub-price-row input[type="number"] { padding: 0.25rem 0.4rem; height: auto; }
        .sub-price-row .sub-price-active { display: flex; justify-content: center; }
        .sub-price-row .sub-price-del {
            border: 0; background: transparent; color: var(--nit-brand-error);
            cursor: pointer; font-size: 1.1rem; line-height: 1; padding: 0 0.25rem;
        }
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
                <label for="course-search">&nbsp;</label>
                <input type="search" id="course-search" class="form-control" placeholder="<?php echo s($STR['sub_courses_search']); ?>">
            </div>
            <div class="ca-actions">
                <button id="courses-select-all" type="button" class="btn btn-outline-secondary btn-sm"><?php echo $STR['sub_selectall']; ?></button>
                <button id="courses-clear" type="button" class="btn btn-outline-secondary btn-sm"><?php echo $STR['sub_clear']; ?></button>
                <button id="save-course-selection" class="btn btn-primary"><?php echo $STR['sub_save_courses']; ?></button>
            </div>
        </div>
        <div id="categories-container" class="mb-4"></div>
    </div>

    <!-- ── User Subscriptions ── -->
    <h4 class="mt-4"><?php echo $STR['sub_usersubs_heading']; ?></h4>
    <p class="text-muted"><?php echo $STR['sub_usersubs_desc']; ?></p>
    <button id="refresh-users" class="btn btn-secondary mb-2"><?php echo $STR['ui_refresh']; ?></button>
    <table class="table table-striped" id="users-table">
        <thead>
            <tr>
                <th><?php echo $STR['pkg_col_user']; ?></th>
                <th><?php echo $STR['sub_col_subscription']; ?></th>
                <th><?php echo $STR['pkg_col_pricepaid']; ?></th>
                <th><?php echo $STR['pkg_col_status']; ?></th>
                <th><?php echo $STR['pkg_col_expiresat']; ?></th>
                <th><?php echo $STR['pkg_col_actions']; ?></th>
            </tr>
        </thead>
        <tbody><tr><td colspan="6"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
    </table>
    <div id="users-table-pager" class="acad-pager"></div>

    <!-- ── Unsubscribe confirmation modal ── -->
    <div id="unsub-modal-backdrop" class="academy-modal-backdrop" style="display:none;">
        <div class="academy-modal">
            <h5 class="academy-modal-title"><?php echo $STR['sub_unsub_title']; ?></h5>
            <p id="unsub-modal-text"></p>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="unsub-refund-checkbox">
                <label class="form-check-label" for="unsub-refund-checkbox">
                    <?php echo $STR['sub_unsub_refund']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span>
                </label>
            </div>
            <div class="academy-modal-actions">
                <button id="unsub-modal-cancel" class="btn btn-link"><?php echo $STR['ui_cancel']; ?></button>
                <button id="unsub-modal-confirm" class="btn btn-danger"><?php echo $STR['sub_unsubscribe']; ?></button>
            </div>
        </div>
    </div>
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
    var subPager = null, usersPager = null;
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
        return en || ar;
    }

    function displayName(value) {
        var v = parseMultilang(value);
        return [v.en, v.ar].filter(function (x) { return x; }).join(' / ') || value || '';
    }

    function courseNames(sub) {
        if (!sub.courses || !sub.courses.length) { return '<span class="text-muted">—</span>'; }
        return sub.courses.map(function (c) { return esc(c.fullname); }).join(', ');
    }

    var ALL_SUBS = [];

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
                '<td>' + esc(displayName(s.name)) + '</td>' +
                '<td>' + esc(s.price) + '</td>' +
                '<td>' + esc(s.duration_days) + '</td>' +
                '<td>' + courseNames(s) + '</td>' +
                '<td>' + esc(sstat(s.status)) + '</td>' +
                '<td>' +
                    '<button class="btn btn-sm btn-secondary" data-act="edit" data-id="' + s.id + '">' + esc(str('ui_edit')) + '</button> ' +
                    toggle + ' ' +
                    '<button class="btn btn-sm btn-danger" data-act="delete" data-id="' + s.id + '">' + esc(str('ui_delete')) + '</button>' +
                '</td>';
            tr._sub = s;
            tbody.appendChild(tr);
        });
    }

    function loadSubs() {
        var tbody = $('sub-table').querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="7">' + esc(str('ui_loading')) + '</td></tr>';
        api('get_subscriptions').then(function (rows) {
            ALL_SUBS = rows;
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="7">' + esc(str('sub_none_admin')) + '</td></tr>';
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

    // ── In-form per-country price editor ──
    var COUNTRIES = CFG.countries || {};
    var CURRENCIES = CFG.currencies || ['EGP'];

    function countryOptions(selected) {
        var html = '<option value="">' + esc(str('sub_price_pickcountry')) + '</option>';
        Object.keys(COUNTRIES).forEach(function (code) {
            html += '<option value="' + esc(code) + '"' + (code === selected ? ' selected' : '') + '>' +
                esc(COUNTRIES[code]) + '</option>';
        });
        return html;
    }

    function currencyOptions(selected) {
        return CURRENCIES.map(function (c) {
            return '<option value="' + esc(c) + '"' + (c === selected ? ' selected' : '') + '>' + esc(c) + '</option>';
        }).join('');
    }

    function addPriceRow(p) {
        p = p || {};
        var list = $('sub-prices-list');
        var row = document.createElement('div');
        row.className = 'sub-price-row';
        row.innerHTML =
            '<select class="form-control sub-price-country">' + countryOptions(p.country || '') + '</select>' +
            '<select class="form-control sub-price-cur">' + currencyOptions(p.currency || 'EGP') + '</select>' +
            '<input type="number" class="form-control sub-price-val" min="0" step="0.01" value="' +
                (p.price != null ? esc(p.price) : '') + '">' +
            '<span class="sub-price-active"><input type="checkbox" class="sub-price-on"' +
                ((p.is_active == null || Number(p.is_active)) ? ' checked' : '') + '></span>' +
            '<button type="button" class="sub-price-del" title="' + esc(str('sub_price_remove')) + '">&times;</button>';
        row.querySelector('.sub-price-del').addEventListener('click', function () { row.remove(); });
        list.appendChild(row);
    }

    function fillPrices(prices) {
        var list = $('sub-prices-list');
        list.innerHTML = '';
        (prices || []).forEach(addPriceRow);
    }

    // Collect the price rows into an array, skipping blank rows (no country chosen).
    function collectPrices() {
        var out = [];
        document.querySelectorAll('#sub-prices-list .sub-price-row').forEach(function (row) {
            var country = row.querySelector('.sub-price-country').value;
            var price = row.querySelector('.sub-price-val').value;
            if (!country || price === '') { return; }
            out.push({
                country: country,
                currency: row.querySelector('.sub-price-cur').value,
                price: price,
                is_active: row.querySelector('.sub-price-on').checked ? 1 : 0
            });
        });
        return out;
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
        $('f-price').value    = sub ? sub.price : '';
        $('f-currency').value = (sub && sub.currency) ? sub.currency : 'EGP';
        $('f-days').value     = sub ? sub.duration_days : '';
        fillPrices(sub ? sub.prices : []);
        $('f-active').checked = sub ? (sub.status === 'active') : true;
        $('sub-form-card').style.display = 'block';
    }
    function hideForm() { $('sub-form-card').style.display = 'none'; }

    function save() {
        var id = $('f-id').value;
        var params = {
            name: buildMultilang($('f-name-en').value, $('f-name-ar').value),
            description: buildMultilang($('f-desc-en').value, $('f-desc-ar').value),
            price: $('f-price').value,
            currency: $('f-currency').value,
            duration_days: $('f-days').value,
            prices: JSON.stringify(collectPrices())
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
    $('sub-price-add').addEventListener('click', function () { addPriceRow({}); });

    // ── Course access ──
    function loadCategories() {
        api('get_categories_with_courses').then(function(categories) {
            var container = $('categories-container');
            if (!categories.length) {
                container.innerHTML = '<span class="text-muted">' + esc(str('sub_no_categories')) + '</span>';
                return;
            }
            var html = '';
            categories.forEach(function(cat) {
                html += '<div class="card category-card">';
                html += '<div class="card-header"><strong>' + esc(cat.name) + '</strong>' +
                    '<span class="cat-count">' + cat.courses.length + '</span></div>';
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
            filterCourses();
        }).catch(function(e) { msg(e.message, 'danger'); });
    }

    function filterCourses() {
        var q = ($('course-search').value || '').trim().toLowerCase();
        document.querySelectorAll('#categories-container .category-card').forEach(function(card) {
            var visible = 0;
            card.querySelectorAll('.course-chip').forEach(function(chip) {
                var match = !q || (chip.getAttribute('data-course-name') || '').indexOf(q) !== -1;
                chip.style.display = match ? '' : 'none';
                if (match) { visible++; }
            });
            card.classList.toggle('ca-empty', visible === 0);
        });
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
        applySubscriptionCourseSelection();
    }

    function applySubscriptionCourseSelection() {
        var subId = $('target-subscription').value;
        var checkedIds = {};
        if (subId) {
            var sub = ALL_SUBS.find(function(s) { return String(s.id) === String(subId); });
            if (sub && sub.courses) {
                sub.courses.forEach(function(c) { checkedIds[c.id] = true; });
            }
        }
        document.querySelectorAll('.course-checkbox').forEach(function(cb) {
            cb.checked = !!checkedIds[parseInt(cb.value, 10)];
        });
    }

    $('target-subscription').addEventListener('change', applySubscriptionCourseSelection);
    $('course-search').addEventListener('input', filterCourses);
    $('courses-select-all').addEventListener('click', function() {
        document.querySelectorAll('.course-chip').forEach(function(chip) {
            if (chip.style.display !== 'none') { chip.querySelector('.course-checkbox').checked = true; }
        });
    });
    $('courses-clear').addEventListener('click', function() {
        document.querySelectorAll('.course-chip').forEach(function(chip) {
            if (chip.style.display !== 'none') { chip.querySelector('.course-checkbox').checked = false; }
        });
    });

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
            loadSubs();
        }).catch(function(e) { msg(e.message, 'danger'); });
    });

    // ── User Subscriptions ──
    function renderUserRows(items) {
        var tbody = $('users-table').querySelector('tbody');
        tbody.innerHTML = '';
        items.forEach(function(r) {
            var tr = document.createElement('tr');
            var toggle = '';
            if (r.status === 'active') {
                toggle = '<button class="btn btn-sm btn-danger btn-unsubscribe" data-id="' + r.id + '">' + esc(str('sub_unsubscribe')) + '</button>';
            }
            var expires = r.expires_at > 0 ? new Date(r.expires_at * 1000).toLocaleString() : str('ui_never');
            tr.innerHTML =
                '<td>' + esc(r.user_fullname) + ' <br><small class="text-muted">' + esc(r.user_email) + '</small></td>' +
                '<td>' + esc(displayName(r.name)) + '</td>' +
                '<td>' + esc(r.price_paid) + '</td>' +
                '<td>' + esc(sstat(r.status)) + '</td>' +
                '<td>' + esc(expires) + '</td>' +
                '<td>' + toggle + '</td>';
            tr._row = r;
            tbody.appendChild(tr);
        });
    }

    function loadUsers() {
        var tbody = $('users-table').querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="6">' + esc(str('ui_loading')) + '</td></tr>';
        api('get_all_user_subscriptions').then(function(rows) {
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="6">' + esc(str('sub_no_usersubs')) + '</td></tr>';
                $('users-table-pager').innerHTML = '';
                return;
            }
            if (usersPager) {
                usersPager.setRows(rows);
            } else {
                usersPager = AcademyUI.paginate({
                    rows: rows, pageSize: PAGE_SIZE, pagerEl: $('users-table-pager'),
                    labels: pagerLabels(), render: renderUserRows
                });
            }
        }).catch(function(e) { msg(e.message, 'danger'); });
    }

    // ── Unsubscribe confirmation modal ──
    var pendingUnsubscribe = null;

    function openUnsubscribeModal(row) {
        pendingUnsubscribe = row;
        var priceText = row.price_paid ? strf('pkg_unassign_paid', esc(row.price_paid)) : '';
        $('unsub-modal-text').innerHTML = strf('sub_unsub_confirm', {
            user: esc(row.user_fullname), name: esc(displayName(row.name)), price: priceText
        });
        $('unsub-refund-checkbox').checked = false;
        $('unsub-modal-backdrop').style.display = 'flex';
    }

    function closeUnsubscribeModal() {
        pendingUnsubscribe = null;
        $('unsub-modal-backdrop').style.display = 'none';
    }

    $('users-table').addEventListener('click', function(ev) {
        var btn = ev.target.closest('.btn-unsubscribe');
        if (!btn) return;
        openUnsubscribeModal(btn.closest('tr')._row);
    });

    $('unsub-modal-cancel').addEventListener('click', closeUnsubscribeModal);
    $('unsub-modal-backdrop').addEventListener('click', function(ev) {
        if (ev.target === this) { closeUnsubscribeModal(); }
    });
    document.addEventListener('keydown', function(ev) {
        if (ev.key === 'Escape' && $('unsub-modal-backdrop').style.display !== 'none') { closeUnsubscribeModal(); }
    });
    $('unsub-modal-confirm').addEventListener('click', function() {
        var row = pendingUnsubscribe;
        if (!row) { return; }
        api('unsubscribe_user', { purchaseid: row.id }, 'POST').then(function() {
            msg(str('sub_unsub_success'), 'success');
            closeUnsubscribeModal();
            loadUsers();
        }).catch(function(e) { msg(e.message, 'danger'); });
    });

    $('refresh-users').addEventListener('click', loadUsers);

    loadCategories();
    loadUsers();
    loadSubs();
})();
JS
);

echo $OUTPUT->footer();
