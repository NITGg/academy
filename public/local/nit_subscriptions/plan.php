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
 * Public details page for one subscription plan: what it costs, how long it lasts, what it
 * says about itself, and — the reason this page exists — the full list of courses it unlocks.
 *
 * The home-page plan cards used to print that list inside the card, which made every card as
 * tall as the longest plan and told a visitor nothing they could act on. The cards now carry a
 * "View details" button that lands here instead, so the card stays a price tag and the course
 * list gets the room it needs.
 *
 * Pricing is deliberately not recomputed here: the page reads the same
 * {@see nit_subscriptions_available()} array the home block and the mobile app read, so a plan
 * shows the same price, the same offer and the same country notice wherever it is displayed.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/nit_subscriptions/lib.php');

use local_nit_subscriptions\subscription_purchase_manager;

// Respect the site's forced-login policy, exactly as the catalogue does: if browsing needs an
// account here, so does this page. Otherwise a guest may read it — a shop with a product page
// nobody can open is not a shop.
if (!empty($CFG->forcelogin)) {
    require_login();
}

$id = required_param('id', PARAM_INT);

$context = context_system::instance();
$pageurl = new moodle_url('/local/nit_subscriptions/plan.php', ['id' => $id]);
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('nit_fullwidth');

// One plan out of the shared public list. A plan that is inactive, or (for a signed-in account
// with no profile country) priced out of reach, is simply not in that list — and this page then
// says so rather than inventing a page for it.
$plan = null;
foreach (nit_subscriptions_available() as $row) {
    if ((int) $row['id'] === $id) {
        $plan = $row;
        break;
    }
}

$heading = $plan ? $plan['name'] : get_string('plan_details', 'local_nit_subscriptions');
$PAGE->set_title($heading);
$PAGE->set_heading($heading);

// ── Colour slots ────────────────────────────────────────────────────────────────
// The same eight-slot mapping the catalogue uses, so this page and the catalogue re-skin
// together off one brand palette and are correct in light and dark without a second rule set.
$stylevars =
    '--cbg1: var(--nit-brand-background); '
  . '--cbg2: var(--nit-brand-surface); '
  . '--cbg3: color-mix(in srgb, var(--nit-brand-surface) 88%, var(--nit-brand-textprimary)); '
  . '--cbg4: var(--nit-brand-primary); '
  . '--ctext1: var(--nit-brand-textprimary); '
  . '--ctext2: var(--nit-brand-textsecondary); '
  . '--ctext3: var(--nit-brand-accenttext); '
  . '--caccent: var(--nit-brand-accent); '
  . '--cborder: var(--nit-brand-borderprimary); '
  . '--csuccess: var(--nit-brand-success); '
  . '--cerror: var(--nit-brand-error); ';

/**
 * An amount with its currency, in the reader's locale.
 *
 * @param float $amount
 * @param string $currency ISO code, may be empty while a country price is unresolved
 * @return string
 */
$money = function ($amount, string $currency): string {
    $formatted = number_format((float) $amount, 2);
    return $currency !== '' ? ($formatted . ' ' . $currency) : $formatted;
};

if (!$plan) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('plan_notavailable', 'local_nit_subscriptions'),
        \core\output\notification::NOTIFY_ERROR);
    echo $OUTPUT->continue_button(new moodle_url('/'));
    echo $OUTPUT->footer();
    die();
}

// ── What the visitor already holds ──────────────────────────────────────────────
// Computed here rather than fetched by JS the way the home block does it: on a single-plan
// page the answer is one record, and knowing it before the first paint means the call to
// action is never briefly wrong.
$activeplanid = 0;
$activedaysleft = 0;
$hasotheractive = false;
$renewdue = false;
$renewedexpiry = 0;
$isrenewed = false;
$activeexpiry = 0;
if (isloggedin() && !isguestuser()) {
    $active = subscription_purchase_manager::get_active_subscription($USER->id);
    if ($active) {
        // Quote the purchase that runs LONGEST on this plan, not the most recently activated
        // one — after a renewal they can differ, and the wrong one understates the time the
        // user has paid for. See subscription_purchase_manager::longest_active().
        $active = subscription_purchase_manager::longest_active($USER->id, (int) $active->subscriptionid)
            ?: $active;
        $activeplanid = (int) $active->subscriptionid;
        $activeexpiry = (int) $active->expires_at;
        $activedaysleft = ($activeexpiry > 0)
            ? max(0, (int) ceil(($activeexpiry - time()) / DAYSECS)) : 0;
        $hasotheractive = ($activeplanid !== $id);

        // The same window the home-page card uses, so the two never disagree about whether
        // this plan can be renewed today — both ask reminder_manager, and so does the
        // checkout that would take the money.
        if (!$hasotheractive) {
            $renewdue = \local_nit_subscriptions\reminder_manager::renew_due($active);
            $renewedexpiry = ((int) $active->expires_at > 0 && (int) $active->duration_days > 0)
                ? (int) $active->expires_at + ((int) $active->duration_days * DAYSECS) : 0;

            // More than one live purchase on this plan means the days-left figure spans the
            // period running now AND a renewal already paid for. The number cannot explain
            // that on its own, so the page prints the end date and says so — see the same
            // reasoning in api.php's get_my_active_subscription.
            $stacked = 0;
            foreach (subscription_purchase_manager::get_active_subscriptions($USER->id) as $p) {
                if ((int) $p->subscriptionid === $activeplanid) {
                    $stacked++;
                }
            }
            $isrenewed = ($stacked > 1);
        }
    }
}
$iscurrent = ($activeplanid === $id);

// ── Courses this plan unlocks ───────────────────────────────────────────────────
// The stored list is filtered down to what this visitor is allowed to know exists: a hidden
// course counts towards the plan for someone who buys it, but naming it on a public page
// would leak a course the site has deliberately not published yet.
$courseids = [];
foreach ($plan['courses'] as $entry) {
    $courseid = (int) (is_array($entry) ? ($entry['id'] ?? 0) : ($entry->id ?? 0));
    if ($courseid) {
        $courseids[] = $courseid;
    }
}
$courses = [];
if ($courseids) {
    // One query, then core's own visibility test per row — the same test the catalogue and
    // the course listings use, so this page can never name a course they would hide.
    foreach ($DB->get_records_list('course', 'id', $courseids) as $course) {
        if (core_course_category::can_view_course_info($course)) {
            $courses[] = $course;
        }
    }
    core_collator::asort_objects_by_property($courses, 'fullname');
}

$hasoffer = ($plan['offer_label'] !== '' && $plan['offer_final'] > 0);
$countryblocked = !empty($plan['country_required']);
$seats = $plan['seat_options'] ?? [];
$description = trim(strip_tags((string) $plan['description'])) !== '' ? $plan['description'] : '';

// Everything plan.js needs, handed over on the wrapper rather than printed as a script block:
// it keeps the page free of inline script (CSP-safe) and the file cacheable.
$jsconfig = json_encode([
    'planid'       => $id,
    'currency'     => (string) $plan['currency'],
    'subsurl'      => (new moodle_url('/local/nit_subscriptions/api.php'))->out(false),
    'commerceurl'  => (new moodle_url('/local/nit_commerce/api.php'))->out(false),
    'sesskey'      => sesskey(),
    'returnurl'    => $pageurl->out(false),
    'couponfailed' => get_string('co_coupon_failed', 'local_nit_commerce'),
], JSON_UNESCAPED_UNICODE);

// Only a visitor who can actually buy needs the checkout script — and it has to be required
// before the header goes out, which is why every decision above is made before this point.
$canbuy = !$countryblocked && isloggedin() && !isguestuser()
    && ((!$iscurrent && !$hasotheractive) || $renewdue);
if ($canbuy) {
    $PAGE->requires->js(new moodle_url('/local/nit_subscriptions/plan.js'), true);
}

echo $OUTPUT->header();
?>

<div dir="auto" class="nitplan" style="<?= $stylevars ?>" data-nitplan="<?= s($jsconfig) ?>">
  <div class="nitplan__wrap">

    <nav class="nitplan__crumbs" aria-label="<?= s(get_string('breadcrumb', 'access')) ?>">
      <a href="<?= s((new moodle_url('/'))->out()) ?>"><?= s(get_string('home')) ?></a>
      <span aria-hidden="true">›</span>
      <span aria-current="page"><?= s(get_string('plan_details', 'local_nit_subscriptions')) ?></span>
    </nav>

    <!-- ── Hero: the plan, its price and the one button that matters ──────────── -->
    <header class="nitplan__hero">
      <div class="nitplan__heroinfo">
        <div class="nitplan__badges">
          <?php if ($iscurrent): ?>
            <span class="nitplan__badge nitplan__badge--ok">✓ <?= s(get_string('plan_current', 'local_nit_subscriptions')) ?></span>
          <?php endif; ?>
          <?php if (!empty($plan['b2b_enabled'])): ?>
            <span class="nitplan__badge nitplan__badge--b2b"><?= s(get_string('plan_b2b', 'local_nit_subscriptions')) ?></span>
          <?php endif; ?>
          <?php if ($hasoffer): ?>
            <span class="nitplan__badge nitplan__badge--offer"><?= s($plan['offer_label']) ?></span>
          <?php endif; ?>
        </div>

        <h1 class="nitplan__title"><?= s($plan['name']) ?></h1>

        <?php if ($description !== ''): ?>
          <div class="nitplan__intro"><?= $description ?></div>
        <?php endif; ?>

        <ul class="nitplan__facts">
          <li>
            <span class="nitplan__factlabel"><?= s(get_string('plan_duration', 'local_nit_subscriptions')) ?></span>
            <strong><?= s(get_string('plan_days', 'local_nit_subscriptions', (int) $plan['duration_days'])) ?></strong>
          </li>
          <li>
            <span class="nitplan__factlabel"><?= s(get_string('plan_included', 'local_nit_subscriptions')) ?></span>
            <strong><?= count($courses) === 1
                ? s(get_string('plan_included_one', 'local_nit_subscriptions'))
                : s(get_string('plan_included_count', 'local_nit_subscriptions', count($courses))) ?></strong>
          </li>
        </ul>
      </div>

      <!-- Price panel: sticky on a wide screen so the price stays with the course list. -->
      <aside class="nitplan__buy">
        <?php if ($countryblocked): ?>
          <p class="nitplan__countryshort"><?= s((string) $plan['country_short']) ?></p>
          <p class="nitplan__countrymsg"><?= s((string) $plan['country_message']) ?></p>
          <a class="nitplan__cta" href="<?= s((string) $plan['country_url']) ?>"><?= s((string) $plan['country_action']) ?></a>
        <?php else: ?>
          <div class="nitplan__pricebox">
            <?php if ($hasoffer): ?>
              <span class="nitplan__was"><?= s($money($plan['price'], $plan['currency'])) ?></span>
            <?php endif; ?>
            <span class="nitplan__now">
              <?= s($money($hasoffer ? $plan['offer_final'] : $plan['price'], $plan['currency'])) ?>
            </span>
            <span class="nitplan__per"><?= s(get_string('plan_perdays', 'local_nit_subscriptions',
                (int) $plan['duration_days'])) ?></span>
          </div>

          <?php if ($iscurrent && $renewdue): ?>
            <!-- Close enough to the end that renewing is offered. The new period is added to
                 the current one, so the button is never a reason to wait for the plan to lapse. -->
            <button type="button" class="nitplan__cta" data-nitplan-buy>
              ↻ <?= s(get_string('sub_renew', 'local_nit_subscriptions')) ?>
            </button>
            <p class="nitplan__note">
              <?= $activedaysleft > 0
                  ? s(get_string('sub_renew_endsin', 'local_nit_subscriptions', $activedaysleft)) . ' — ' : '' ?>
              <?= s(get_string('sub_renew_note', 'local_nit_subscriptions')) ?>
              <?php if ($renewedexpiry > 0): ?>
                <br><strong><?= s(get_string('sub_renew_newexpiry', 'local_nit_subscriptions')) ?>:</strong>
                <?= s(userdate($renewedexpiry, get_string('strftimedaydate'))) ?>
              <?php endif; ?>
            </p>
          <?php elseif ($iscurrent): ?>
            <div class="nitplan__cta nitplan__cta--owned">
              <?= $activedaysleft > 0
                  ? s(get_string('plan_daysleft', 'local_nit_subscriptions', $activedaysleft))
                  : s(get_string('plan_activenow', 'local_nit_subscriptions')) ?>
            </div>
            <?php if ($activeexpiry > 0): ?>
              <!-- The days-left figure never stands alone: on a renewed plan it covers the
                   period running now plus the one already paid for behind it, which the
                   number cannot say for itself. -->
              <p class="nitplan__note">
                <?= s(get_string('plan_accessuntil', 'local_nit_subscriptions',
                    userdate($activeexpiry, get_string('strftimedaydate')))) ?><?php
                  if ($isrenewed): ?> — <?= s(get_string('plan_includesrenewal', 'local_nit_subscriptions')) ?><?php
                  endif; ?>
              </p>
            <?php endif; ?>
          <?php elseif ($hasotheractive): ?>
            <button type="button" class="nitplan__cta" disabled>
              <?= s(get_string('sub_buy', 'local_nit_subscriptions')) ?>
            </button>
            <p class="nitplan__note"><?= s(get_string('plan_otheractive', 'local_nit_subscriptions')) ?></p>
          <?php elseif (!isloggedin() || isguestuser()): ?>
            <a class="nitplan__cta" href="<?= s((new moodle_url('/login/index.php'))->out()) ?>">
              <?= s(get_string('plan_login_tosubscribe', 'local_nit_subscriptions')) ?>
            </a>
          <?php else: ?>
            <button type="button" class="nitplan__cta" data-nitplan-buy>
              <?= s(get_string('sub_buy', 'local_nit_subscriptions')) ?>
            </button>
          <?php endif; ?>

          <p class="nitplan__secure">🔒 <?= s(get_string('sub_secure_kashier', 'local_nit_subscriptions')) ?></p>
        <?php endif; ?>
      </aside>
    </header>

    <!-- ── The course list the cards no longer carry ──────────────────────────── -->
    <section class="nitplan__section">
      <h2 class="nitplan__h2"><?= s(get_string('plan_included', 'local_nit_subscriptions')) ?></h2>
      <?php if (!$courses): ?>
        <p class="nitplan__empty"><?= s(get_string('plan_included_none', 'local_nit_subscriptions')) ?></p>
      <?php else: ?>
        <ul class="nitplan__courses">
          <?php foreach ($courses as $course): ?>
            <?php $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]); ?>
            <li class="nitplan__course">
              <!-- One link filling the whole card, rather than a link on the title and another
                   on "Open": the card looked clickable everywhere but only was in two places,
                   and screen readers had to hear the same destination announced twice. -->
              <a class="nitplan__courselink" href="<?= s($courseurl->out()) ?>">
                <span class="nitplan__tick" aria-hidden="true">✓</span>
                <span class="nitplan__coursename"><?= s(format_string($course->fullname)) ?></span>
                <span class="nitplan__courseopen" aria-hidden="true"><?=
                  s(get_string('plan_opencourse', 'local_nit_subscriptions')) ?> ›</span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <?php if (!$countryblocked && !empty($plan['b2b_enabled']) && $seats): ?>
      <!-- ── Team pricing ─────────────────────────────────────────────────────── -->
      <section class="nitplan__section">
        <h2 class="nitplan__h2"><?= s(get_string('plan_seats', 'local_nit_subscriptions')) ?></h2>
        <p class="nitplan__empty"><?= s(get_string('plan_seats_intro', 'local_nit_subscriptions')) ?></p>
        <div class="nitplan__seats">
          <?php foreach ($seats as $seat): ?>
            <div class="nitplan__seat">
              <span class="nitplan__seatcount"><?= (int) $seat['seats'] ?></span>
              <span class="nitplan__seatlabel"><?= s(get_string('plan_seats_col', 'local_nit_subscriptions')) ?></span>
              <span class="nitplan__seatprice"><?= s($money($seat['b2b_price'], $plan['currency'])) ?></span>
              <?php if ((float) $seat['discount_amount'] > 0): ?>
                <span class="nitplan__seatsave"><?= s(get_string('plan_seats_save', 'local_nit_subscriptions')) ?>
                  <?= s($money($seat['discount_amount'], $plan['currency'])) ?></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

  </div>

  <?php if ($canbuy): ?>
  <!-- ── Checkout confirmation ───────────────────────────────────────────────────
       The same two endpoints the home-page block calls: preview_discount to price a coupon
       and create_subscription_checkout to hand the visitor to the gateway. -->
  <div class="nitplan__modal" data-nitplan-modal hidden>
    <div class="nitplan__dialog" role="dialog" aria-modal="true"
         aria-label="<?= s(get_string('sub_confirm_title', 'local_nit_subscriptions')) ?>">
      <h3 class="nitplan__dialogtitle"><?= s(get_string(
        $renewdue ? 'sub_renew_confirm' : 'sub_confirm_title', 'local_nit_subscriptions')) ?></h3>
      <p class="nitplan__dialogintro"><?= s(get_string(
        $renewdue ? 'sub_renew_note' : 'sub_confirm_intro', 'local_nit_subscriptions')) ?></p>

      <div class="nitplan__summary">
        <div class="nitplan__row">
          <span><?= s(get_string('sub_duration_label', 'local_nit_subscriptions')) ?></span>
          <strong><?= s(get_string('plan_days', 'local_nit_subscriptions', (int) $plan['duration_days'])) ?></strong>
        </div>
        <div class="nitplan__row">
          <span><?= s(get_string('sub_total_label', 'local_nit_subscriptions')) ?></span>
          <strong data-nitplan-original>—</strong>
        </div>
        <div class="nitplan__row" data-nitplan-offerrow hidden>
          <span><?= s(get_string('plan_offer', 'local_nit_subscriptions')) ?></span>
          <strong class="nitplan__good" data-nitplan-offer>—</strong>
        </div>

        <div class="nitplan__coupon">
          <label for="nitplan-coupon"><?= s(get_string('sub_coupon_label', 'local_nit_subscriptions')) ?></label>
          <input type="text" id="nitplan-coupon" autocomplete="off" data-nitplan-coupon>
          <button type="button" data-nitplan-apply><?= s(get_string('sub_coupon_apply', 'local_nit_subscriptions')) ?></button>
        </div>
        <p class="nitplan__err" data-nitplan-couponerr hidden></p>

        <div class="nitplan__row">
          <span><?= s(get_string('sub_discount_label', 'local_nit_subscriptions')) ?></span>
          <strong class="nitplan__good" data-nitplan-discount>—</strong>
        </div>
        <div class="nitplan__row nitplan__row--total">
          <span><?= s(get_string('sub_total_label', 'local_nit_subscriptions')) ?></span>
          <strong data-nitplan-final>—</strong>
        </div>
      </div>

      <p class="nitplan__err" data-nitplan-error hidden></p>

      <div class="nitplan__actions">
        <button type="button" class="nitplan__ghost" data-nitplan-cancel><?= s(get_string('cancel')) ?></button>
        <button type="button" class="nitplan__cta nitplan__cta--inline" data-nitplan-proceed><?=
          s(get_string('sub_proceed_payment', 'local_nit_subscriptions')) ?></button>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();
