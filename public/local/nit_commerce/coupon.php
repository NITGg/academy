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
 * Public details page for one coupon: what it takes off, how long it lasts, and — the reason
 * this page exists — exactly what it can be spent on.
 *
 * The home-page coupon cards used to squeeze the scope into one truncated line ("Course A,
 * Course B, All subscriptions"), which is unreadable at three items and useless at thirty. The
 * cards now carry a "View details" button that lands here, and the scope is expanded properly:
 * a coupon that covers a plan lists the courses that plan unlocks, so a visitor can see what
 * the code is actually worth before they go looking for something to spend it on.
 *
 * The coupon is read from the same {@see coupon_manager::get_available_coupons()} list the home
 * block reads, so a coupon that is inactive, not started or expired has no page here either.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_nit_commerce\coupon_manager;

// Respect the site's forced-login policy, as the catalogue and the plan page do.
if (!empty($CFG->forcelogin)) {
    require_login();
}

$id = required_param('id', PARAM_INT);

$context = context_system::instance();
$pageurl = new moodle_url('/local/nit_commerce/coupon.php', ['id' => $id]);
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('nit_fullwidth');

$coupon = null;
foreach (coupon_manager::get_available_coupons() as $row) {
    if ((int) $row['id'] === $id) {
        $coupon = $row;
        break;
    }
}

$heading = ($coupon && $coupon['name'] !== '') ? $coupon['name']
    : ($coupon ? $coupon['code'] : get_string('cpn_details', 'local_nit_commerce'));
$PAGE->set_title($heading);
$PAGE->set_heading($heading);

// The same eight colour slots the catalogue and the plan page use.
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

if (!$coupon) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('cpn_notavailable', 'local_nit_commerce'),
        \core\output\notification::NOTIFY_ERROR);
    echo $OUTPUT->continue_button(new moodle_url('/'));
    echo $OUTPUT->footer();
    die();
}

// Coupons carry no currency of their own — a fixed discount is stated in the site's default.
$currency = (string) get_config('local_payments', 'default_currency');
if ($currency === '' || $currency === '0') {
    $currency = get_string('co_currency', 'local_nit_commerce');
}

$ispercent = ($coupon['discount_type'] === 'percent');
$valuenum = (float) $coupon['discount_value'];
$valuetext = $ispercent
    ? get_string('cpn_off_percent', 'local_nit_commerce', rtrim(rtrim(number_format($valuenum, 2), '0'), '.'))
    : get_string('cpn_off_fixed', 'local_nit_commerce', number_format($valuenum, 2) . ' ' . $currency);

/**
 * A course row the page may name, or null when the visitor is not allowed to know it exists.
 *
 * @param int $courseid
 * @return \stdClass|null
 */
$visiblecourse = function (int $courseid) {
    global $DB;
    static $cache = [];
    if (array_key_exists($courseid, $cache)) {
        return $cache[$courseid];
    }
    $course = $DB->get_record('course', ['id' => $courseid]);
    $cache[$courseid] = ($course && core_course_category::can_view_course_info($course)) ? $course : null;
    return $cache[$courseid];
};

// ── What the coupon buys ────────────────────────────────────────────────────────
// Each scope row becomes a group: a heading (what the admin ticked) plus, where the group
// stands for something that contains courses, the courses themselves. Two groups are
// deliberately NOT enumerated — "all courses" and "all subscriptions" cover the whole site,
// and a list of everything is a worse answer than a link to the catalogue.
$hassubs = class_exists('\local_nit_subscriptions\subscription_manager');
$groups = [];

foreach ($coupon['applies_to'] as $scope) {
    $type = (string) $scope['item_type'];
    $itemid = (int) $scope['item_id'];

    $group = [
        'label'    => (string) $scope['label'],
        'type'     => $type,
        'link'     => null,
        'linktext' => '',
        'courses'  => [],
        'plans'    => [],
    ];

    if ($type === 'course' && $itemid > 0) {
        $course = $visiblecourse($itemid);
        if (!$course) {
            continue;   // A hidden course does not get named here just because a coupon covers it.
        }
        $group['courses'][] = $course;
        $group['link'] = new moodle_url('/course/view.php', ['id' => $course->id]);
        $group['linktext'] = get_string('cpn_open_course', 'local_nit_commerce');

    } else if ($type === 'course' && $itemid === 0) {
        $group['link'] = new moodle_url('/local/nit_category/catalogue.php');
        $group['linktext'] = get_string('cpn_browse_all', 'local_nit_commerce');

    } else if ($type === 'subscription' && $itemid > 0 && $hassubs) {
        foreach (\local_nit_subscriptions\subscription_manager::courses_detail($itemid) as $entry) {
            $course = $visiblecourse((int) $entry['id']);
            if ($course) {
                $group['courses'][] = $course;
            }
        }
        $group['link'] = new moodle_url('/local/nit_subscriptions/plan.php', ['id' => $itemid]);
        $group['linktext'] = get_string('cpn_view_plan', 'local_nit_commerce');

    } else if ($type === 'subscription' && $itemid === 0 && $hassubs) {
        // Every active plan, each with its own link — a short list that is worth printing,
        // unlike "every course on the site".
        foreach (\local_nit_subscriptions\subscription_manager::get_subscriptions(
                \local_nit_subscriptions\subscription_manager::STATUS_ACTIVE) as $plan) {
            $group['plans'][] = [
                'name' => format_string(\local_nit_subscriptions\subscription_manager::resolve_mlang($plan->name)),
                'url'  => new moodle_url('/local/nit_subscriptions/plan.php', ['id' => (int) $plan->id]),
            ];
        }
    }

    if (!empty($group['courses'])) {
        core_collator::asort_objects_by_property($group['courses'], 'fullname');
    }
    $groups[] = $group;
}

// Usage figures, said the way a visitor reads them: how many are left, not how many were spent.
$usesleft = null;
if ((int) $coupon['usage_limit'] > 0) {
    $usesleft = max(0, (int) $coupon['usage_limit'] - (int) $coupon['usage_count']);
}

$description = trim(strip_tags((string) $coupon['description'])) !== '' ? $coupon['description'] : '';
$name = ($coupon['name'] !== '') ? $coupon['name'] : $coupon['code'];

$PAGE->requires->js(new moodle_url('/local/nit_commerce/coupon.js'), true);

echo $OUTPUT->header();
?>

<div dir="auto" class="nitcpn" style="<?= $stylevars ?>"
     data-nitcpn-copied="<?= s(get_string('cpn_copied', 'local_nit_commerce')) ?>">
  <div class="nitcpn__wrap">

    <nav class="nitcpn__crumbs" aria-label="<?= s(get_string('breadcrumb', 'access')) ?>">
      <a href="<?= s((new moodle_url('/'))->out()) ?>"><?= s(get_string('home')) ?></a>
      <span aria-hidden="true">›</span>
      <span aria-current="page"><?= s(get_string('cpn_details', 'local_nit_commerce')) ?></span>
    </nav>

    <!-- ── Ticket: the value, the code, and how to spend it ───────────────────── -->
    <header class="nitcpn__ticket">
      <div class="nitcpn__stub">
        <span class="nitcpn__stubvalue"><?= $ispercent
            ? s(rtrim(rtrim(number_format($valuenum, 2), '0'), '.')) . '<small>%</small>'
            : s(number_format($valuenum, 2)) ?></span>
        <span class="nitcpn__stublabel"><?= $ispercent
            ? s(get_string('cpn_stub_off', 'local_nit_commerce'))
            : s($currency . ' ' . get_string('cpn_stub_off', 'local_nit_commerce')) ?></span>
      </div>

      <div class="nitcpn__body">
        <h1 class="nitcpn__title"><?= s($name) ?></h1>
        <p class="nitcpn__value"><?= s($valuetext) ?></p>

        <?php if ($description !== ''): ?>
          <div class="nitcpn__intro"><?= $description ?></div>
        <?php endif; ?>

        <div class="nitcpn__codewrap">
          <span class="nitcpn__codelabel"><?= s(get_string('cpn_code_label', 'local_nit_commerce')) ?></span>
          <button type="button" class="nitcpn__code" data-nitcpn-copy="<?= s($coupon['code']) ?>">
            <span class="nitcpn__codetext"><?= s($coupon['code']) ?></span>
            <span class="nitcpn__codeicon" aria-hidden="true">⧉</span>
          </button>
        </div>
        <p class="nitcpn__howto"><?= s(get_string('cpn_howto', 'local_nit_commerce')) ?></p>
      </div>
    </header>

    <!-- ── Terms ──────────────────────────────────────────────────────────────── -->
    <section class="nitcpn__section">
      <h2 class="nitcpn__h2"><?= s(get_string('cpn_terms', 'local_nit_commerce')) ?></h2>
      <ul class="nitcpn__terms">
        <li>
          <span class="nitcpn__termlabel"><?= s(get_string('cpn_col_usage', 'local_nit_commerce')) ?></span>
          <strong><?= $coupon['usage_type'] === 'once'
              ? s(get_string('cpn_usage_once', 'local_nit_commerce'))
              : s(get_string('cpn_usage_multiple', 'local_nit_commerce')) ?></strong>
          <?php if ($usesleft !== null): ?>
            <span class="nitcpn__termnote<?= $usesleft === 0 ? ' nitcpn__termnote--out' : '' ?>"><?=
              $usesleft > 0
                ? s(get_string('cpn_uses_left', 'local_nit_commerce', $usesleft))
                : s(get_string('cpn_uses_none', 'local_nit_commerce')) ?></span>
          <?php else: ?>
            <span class="nitcpn__termnote"><?= s(get_string('cpn_unlimited', 'local_nit_commerce')) ?></span>
          <?php endif; ?>
        </li>

        <?php if ($coupon['max_discount'] !== null && (float) $coupon['max_discount'] > 0): ?>
          <li>
            <span class="nitcpn__termlabel"><?= s(get_string('cpn_col_max', 'local_nit_commerce')) ?></span>
            <strong><?= s(number_format((float) $coupon['max_discount'], 2) . ' ' . $currency) ?></strong>
          </li>
        <?php endif; ?>

        <?php if ((int) $coupon['startdate'] > 0): ?>
          <li>
            <span class="nitcpn__termlabel"><?= s(get_string('cpn_starts', 'local_nit_commerce')) ?></span>
            <strong><?= s(userdate((int) $coupon['startdate'], get_string('strftimedaydate'))) ?></strong>
          </li>
        <?php endif; ?>

        <li>
          <span class="nitcpn__termlabel"><?= s(get_string('cpn_expires', 'local_nit_commerce')) ?></span>
          <strong><?= (int) $coupon['enddate'] > 0
              ? s(userdate((int) $coupon['enddate'], get_string('strftimedaydate')))
              : s(get_string('cpn_no_expiry', 'local_nit_commerce')) ?></strong>
        </li>
      </ul>
    </section>

    <!-- ── The scope the cards no longer carry ────────────────────────────────── -->
    <section class="nitcpn__section">
      <h2 class="nitcpn__h2"><?= s(get_string('cpn_where', 'local_nit_commerce')) ?></h2>

      <?php if (!$groups): ?>
        <p class="nitcpn__empty"><?= s(get_string('cpn_where_none', 'local_nit_commerce')) ?></p>
      <?php else: ?>
        <?php foreach ($groups as $group): ?>
          <div class="nitcpn__group">
            <div class="nitcpn__grouphead">
              <h3 class="nitcpn__grouptitle"><?= s($group['label']) ?></h3>
              <?php if ($group['link']): ?>
                <a class="nitcpn__grouplink" href="<?= s($group['link']->out()) ?>"><?=
                  s($group['linktext']) ?> ›</a>
              <?php endif; ?>
            </div>

            <?php if (!empty($group['plans'])): ?>
              <ul class="nitcpn__items">
                <?php foreach ($group['plans'] as $plan): ?>
                  <li class="nitcpn__item">
                    <span class="nitcpn__tick" aria-hidden="true">✓</span>
                    <a href="<?= s($plan['url']->out()) ?>"><?= s($plan['name']) ?></a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <?php if (!empty($group['courses'])): ?>
              <?php if ($group['type'] === 'subscription'): ?>
                <p class="nitcpn__grouphint"><?=
                  s(get_string('cpn_courses_under', 'local_nit_commerce', $group['label'])) ?></p>
              <?php endif; ?>
              <ul class="nitcpn__items">
                <?php foreach ($group['courses'] as $course): ?>
                  <li class="nitcpn__item">
                    <span class="nitcpn__tick" aria-hidden="true">✓</span>
                    <a href="<?= s((new moodle_url('/course/view.php', ['id' => $course->id]))->out()) ?>"><?=
                      s(format_string($course->fullname)) ?></a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

  </div>
</div>

<?php
echo $OUTPUT->footer();
