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
 * Public course details page — the "Course details" button on the category catalogue.
 *
 * A shop needs a product page every visitor can read, and neither core page can be one:
 *
 *   * /course/view.php requires login, so a browsing guest lands on the login form;
 *   * for a logged-in but unenrolled student, local_payments' before_http_headers hook
 *     redirects /course/view.php (and /enrol/index.php) to local/payments/buy.php, so
 *     they get a checkout instead of any description of what they are being sold.
 *
 * This page is that product page: course picture, summary, teacher, price (and any
 * offer) plus a syllabus preview, shown to ANYONE who may see the course's info —
 * exactly core's own "can view course info" rule, so hidden courses and hidden
 * categories stay hidden. It never grants access: enrolling / buying still goes through
 * local_nit_subscriptions and local_payments, and a guest clicking Buy is sent to log in.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/local/nit_category/lib.php');
require_once($CFG->libdir . '/filelib.php');

// Respect the site's forced-login policy: if the site requires login to browse, gate
// this page too — exactly as the catalogue (index.php) and core's course/info.php do.
if (!empty($CFG->forcelogin)) {
    require_login();
}

$id = required_param('id', PARAM_INT);

$course = get_course($id);
if ((int) $course->id === (int) SITEID) {
    redirect(new moodle_url('/'));
}
$context = context_course::instance($course->id);

// The one access rule, borrowed from core's course/info.php: anybody allowed to see the
// course's info may read this page (guests included). A hidden course, or a course in a
// hidden category, still hits the same core exception it always did.
if (!core_course_category::can_view_course_info($course) && !is_enrolled($context, null, '', true)) {
    throw new moodle_exception('cannotviewcategory', '', $CFG->wwwroot . '/');
}

$PAGE->set_url(new moodle_url('/local/nit_category/course.php', ['id' => $course->id]));
$PAGE->set_context($context);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
// NIT full-width layout: navbar + footer only, no page heading / secondary nav.
$PAGE->set_pagelayout('nit_fullwidth');

// Bilingual inline helper (site is en/ar); mirrors the catalogue's {mlang} pairs.
$isar = (strpos(current_language(), 'ar') === 0);
$t = function (string $en, string $ar) use ($isar) {
    return $isar ? $ar : $en;
};

// Colour palette: identical to the catalogue's, so a course page is visibly the same
// surface as the card it was opened from and re-skins with the site Brand Colors.
$stylevars =
    '--cbg1: var(--nit-brand-background); '
  . '--cbg2: var(--nit-brand-surface); '
  . '--cbg3: color-mix(in srgb, var(--nit-brand-surface) 88%, var(--nit-brand-textprimary)); '
  . '--cbg4: var(--nit-brand-primary); '
  . '--ctext1: var(--nit-brand-textprimary); '
  . '--ctext2: var(--nit-brand-textsecondary); '
  . '--ctext3: var(--nit-brand-accenttext); '
  . '--caccent: var(--nit-brand-accent); '
  . '--ctext4: var(--nit-brand-textprimary); '
  . '--cborder: var(--nit-brand-borderprimary); '
  . '--csuccess: var(--nit-brand-success); ';

// Brand group of the course's category (gallery "Category styles" tab), so the page
// wears the same layer as that category's catalogue.
$brandgroupclass = '';
if (function_exists('theme_nit_category_brand_group')) {
    $brandgroupclass = theme_nit_brand_group_class(theme_nit_category_brand_group((int) $course->category));
}

// The category this course sits in — its name is the chip above the title and the link
// back to the catalogue. A category the visitor may not see resolves to null, in which
// case the chip is simply dropped.
$category     = core_course_category::get((int) $course->category, IGNORE_MISSING);
$categoryname = $category ? $category->get_formatted_name() : '';
$categoryurl  = $category
    ? (new moodle_url('/local/nit_category/index.php', ['id' => $category->id]))->out(false)
    : '';

$courseimage = local_nit_category_course_image_url((int) $course->id, (int) $course->category);
$coursename  = format_string($course->fullname, true, ['context' => $context]);
$teacher     = function_exists('theme_nit_course_teacher') ? theme_nit_course_teacher((int) $course->id) : '';
$summary     = $course->summary
    ? format_text($course->summary, $course->summaryformat, ['context' => $context, 'noclean' => true])
    : '';

// Price / enrolment state — the SAME resolver the catalogue cards use, so the price a
// visitor saw on the card is the price they see here and the one checkout will charge.
$info       = local_nit_category_course_info((int) $course->id);
$pricelabel = local_nit_category_money($info['price'], $info['currency']);
$hasoffer   = ($info['offerlabel'] !== '' && $info['offerfinal'] > 0);
$finallabel = $hasoffer ? local_nit_category_money($info['offerfinal'], $info['currency']) : $pricelabel;

$loggedin      = isloggedin() && !isguestuser();
$courseviewurl = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
// A guest cannot be sent to enrol.php: it requires login AND a sesskey, and the sesskey
// minted for the guest session is dead by the time they come back from logging in. Send
// them to log in first — the catalogue's Buy button does the same.
$enrolurl = $loggedin
    ? (new moodle_url('/local/nit_subscriptions/enrol.php',
        ['courseid' => $course->id, 'sesskey' => sesskey()]))->out(false)
    : (new moodle_url('/login/index.php'))->out(false);

// Syllabus preview: section names and the activities inside them, names only (no links —
// the visitor has no access yet). Only items an editor made visible are listed, and any
// failure to build modinfo just drops the preview instead of breaking the page.
$outline = [];
$activitycount = 0;
try {
    $modinfo  = get_fast_modinfo($course);
    $sections = method_exists($modinfo, 'get_listed_section_info_all')
        ? $modinfo->get_listed_section_info_all()
        : $modinfo->get_section_info_all();
    foreach ($sections as $sectioninfo) {
        if (!$sectioninfo->visible) {
            continue;
        }
        $items = [];
        foreach ($sectioninfo->get_sequence_cm_infos() as $cm) {
            if (!$cm->visible || !$cm->visibleoncoursepage || $cm->modname === 'label') {
                continue;
            }
            $items[] = [
                'name' => $cm->get_formatted_name(),
                'type' => $cm->modfullname,
                'icon' => $cm->get_icon_url()->out(false),
            ];
        }
        if (!$items) {
            continue;
        }
        $activitycount += count($items);
        $outline[] = [
            'name'  => get_section_name($course, $sectioninfo),
            'items' => $items,
        ];
    }
} catch (\Throwable $e) {
    $outline = [];
    $activitycount = 0;
}

// NIT: checkout modal + course offer/price support (guarded — degrade if the plugins are absent).
$nitcheckout = local_nit_category_checkout_available();
if ($nitcheckout) {
    require_once($CFG->dirroot . '/local/nit_commerce/lib.php');
    $PAGE->requires->js(new moodle_url('/local/nit_commerce/checkout_modal.js'), true);
}

echo $OUTPUT->header();
?>

<div dir="auto" class="nit-course-details<?= $brandgroupclass !== '' ? ' ' . $brandgroupclass : '' ?>" style="<?= $stylevars ?>background: var(--cbg1); min-height: 100vh; padding-bottom: 48px; width: 100vw; max-width: 100vw; margin-inline: calc(50% - 50vw); margin-top: 0;">

  <style>
    .nit-course-details { color: var(--ctext1); }
    .nit-cd-wrap { max-width: 1200px; margin: 0 auto; padding: 0 16px; }

    /* Hero: picture beside the title block on desktop, stacked on phones. */
    .nit-cd-hero {
      background: var(--cbg2);
      border-bottom: 1px solid color-mix(in srgb, var(--cborder) 55%, transparent);
      padding: 36px 0 32px;
    }
    .nit-cd-hero-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 32px; align-items: center; }
    .nit-cd-title { font-size: 34px; font-weight: 800; line-height: 1.3; margin: 0 0 14px; color: var(--ctext1); }
    .nit-cd-figure {
      background: var(--cbg3); border-radius: 18px; overflow: hidden;
      aspect-ratio: 16 / 10; display: flex; align-items: center; justify-content: center;
    }
    .nit-cd-figure img { width: 100%; height: 100%; object-fit: cover; display: block; }

    /* Category chip — same tint pill + circle dot as the catalogue cards. */
    .nit-cd-cat {
      display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
      background: color-mix(in srgb, var(--caccent) 70%, transparent);
      color: var(--ctext1); padding: 6px 14px; border-radius: 4px;
      font-size: 12px; font-weight: bold; margin-bottom: 16px;
    }
    .nit-cd-cat:hover { color: var(--ctext1); filter: brightness(1.08); }
    .nit-cd-cat-dot { width: 9px; height: 9px; border-radius: 50%; background: var(--ctext1); flex: 0 0 auto; }

    .nit-cd-meta { display: flex; flex-wrap: wrap; gap: 10px 22px; color: var(--ctext2); font-size: 13px; }

    /* Body: article + sticky purchase card. */
    .nit-cd-body { display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(280px, 1fr); gap: 28px; padding-top: 32px; }
    .nit-cd-card {
      background: var(--cbg2); border: 1px solid color-mix(in srgb, var(--cborder) 55%, transparent);
      border-radius: 16px; padding: 22px; margin-bottom: 24px;
    }
    .nit-cd-aside .nit-cd-card { position: sticky; top: 88px; }
    .nit-cd-h2 {
      font-size: 21px; font-weight: 800; margin: 0 0 16px; color: var(--ctext1);
      border-inline-start: 4px solid var(--cbg4); padding-inline-start: 12px;
    }
    .nit-cd-summary { color: var(--ctext2); font-size: 15px; line-height: 1.8; }
    .nit-cd-summary :where(h1, h2, h3, h4, h5, strong) { color: var(--ctext1); }
    .nit-cd-summary img { max-width: 100%; height: auto; border-radius: 10px; }

    /* Syllabus preview. */
    .nit-cd-section + .nit-cd-section { margin-top: 18px; }
    .nit-cd-section-name {
      display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: 700;
      color: var(--ctext3); margin: 0 0 10px;
    }
    .nit-cd-section-count { font-size: 12px; font-weight: 700; color: var(--ctext2); }
    .nit-cd-items { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
    .nit-cd-item {
      display: flex; align-items: center; gap: 10px;
      background: color-mix(in srgb, var(--caccent) 14%, transparent);
      border: 1px solid color-mix(in srgb, var(--cborder) 45%, transparent);
      border-radius: 10px; padding: 10px 14px; font-size: 14px; color: var(--ctext1);
    }
    .nit-cd-item img { width: 20px; height: 20px; flex: 0 0 auto; }
    .nit-cd-item-type { margin-inline-start: auto; font-size: 11px; color: var(--ctext2); white-space: nowrap; }

    /* Purchase card. */
    .nit-cd-price-row { display: flex; align-items: baseline; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
    .nit-cd-price { font-size: 30px; font-weight: 800; color: var(--ctext1); }
    .nit-cd-price-was { font-size: 15px; color: var(--ctext2); text-decoration: line-through; opacity: 0.75; }
    .nit-cd-offer {
      background: var(--cbg4); color: var(--ctext4); font-size: 11px; font-weight: bold;
      padding: 3px 10px; border-radius: 50px;
    }
    .nit-cd-status {
      display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: bold;
      padding: 5px 13px; border-radius: 50px; margin-bottom: 12px;
    }
    .nit-cd-status--ok {
      background: color-mix(in srgb, var(--csuccess) 16%, transparent);
      color: var(--csuccess); border: 1px solid color-mix(in srgb, var(--csuccess) 45%, transparent);
    }
    .nit-cd-status--sub {
      background: color-mix(in srgb, var(--caccent) 16%, transparent);
      color: var(--ctext3); border: 1px solid color-mix(in srgb, var(--caccent) 45%, transparent);
    }
    .nit-cd-facts {
      list-style: none; margin: 16px 0 0; padding: 16px 0 0;
      border-top: 1px solid color-mix(in srgb, var(--cborder) 45%, transparent);
    }
    .nit-cd-facts li { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; color: var(--ctext2); padding: 5px 0; }
    .nit-cd-facts strong { color: var(--ctext1); font-weight: 700; text-align: end; }
    .nit-cd-note { font-size: 12px; color: var(--ctext2); margin: 12px 0 0; text-align: center; }

    @media (max-width: 991px) {
      .nit-cd-hero-grid { grid-template-columns: 1fr; }
      .nit-cd-body { grid-template-columns: 1fr; }
      .nit-cd-aside .nit-cd-card { position: static; }
      .nit-cd-title { font-size: 27px; }
    }
  </style>

  <!-- Hero -->
  <div class="nit-cd-hero">
    <div class="nit-cd-wrap">
      <div class="nit-cd-hero-grid">
        <div>
          <?php if ($categoryname !== ''): ?>
            <a class="nit-cd-cat" href="<?= $categoryurl ?>">
              <span class="nit-cd-cat-dot"></span>
              <span><?= $categoryname ?></span>
            </a>
          <?php endif; ?>

          <h1 class="nit-cd-title"><?= $coursename ?></h1>

          <div class="nit-cd-meta">
            <?php if ($teacher !== ''): ?>
              <span>👤 <?= s($teacher) ?></span>
            <?php endif; ?>
            <?php if ($activitycount > 0): ?>
              <span>📚 <?= $activitycount . ' ' . ($activitycount === 1 ? $t('lesson', 'درس') : $t('lessons', 'دروس')) ?></span>
            <?php endif; ?>
            <?php if (!empty($course->startdate)): ?>
              <span>🗓 <?= $t('Starts', 'يبدأ') ?> <?= userdate($course->startdate, get_string('strftimedatefullshort')) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($courseimage !== ''): ?>
        <div class="nit-cd-figure">
          <img src="<?= s($courseimage) ?>" alt="<?= s($coursename) ?>">
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Body -->
  <div class="nit-cd-wrap">
    <div class="nit-cd-body">

      <div>
        <?php if ($summary !== ''): ?>
        <div class="nit-cd-card">
          <h2 class="nit-cd-h2"><?= $t('About this course', 'عن الكورس') ?></h2>
          <div class="nit-cd-summary"><?= $summary ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($outline)): ?>
        <div class="nit-cd-card">
          <h2 class="nit-cd-h2"><?= $t('Course content', 'محتوى الكورس') ?></h2>
          <?php foreach ($outline as $section): ?>
            <div class="nit-cd-section">
              <h3 class="nit-cd-section-name">
                <span><?= $section['name'] ?></span>
                <span class="nit-cd-section-count">(<?= count($section['items']) ?>)</span>
              </h3>
              <ul class="nit-cd-items">
                <?php foreach ($section['items'] as $item): ?>
                  <li class="nit-cd-item">
                    <img src="<?= s($item['icon']) ?>" alt="" aria-hidden="true">
                    <span><?= $item['name'] ?></span>
                    <span class="nit-cd-item-type"><?= s($item['type']) ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($summary === '' && empty($outline)): ?>
        <div class="nit-cd-card" style="color: var(--ctext2);">
          <?= $t('No description has been added for this course yet.', 'لم تتم إضافة وصف لهذا الكورس بعد.') ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Purchase / access card -->
      <aside class="nit-cd-aside">
        <div class="nit-cd-card">

          <?php if ($info['enrolled']): ?>
            <span class="nit-cd-status nit-cd-status--ok">✓ <?= $t('Enrolled', 'مُسجَّل') ?></span>
          <?php elseif ($info['purchased']): ?>
            <span class="nit-cd-status nit-cd-status--ok">✓ <?= $t('Purchased', 'تم الشراء') ?></span>
          <?php elseif ($info['covered']): ?>
            <span class="nit-cd-status nit-cd-status--sub">★ <?= $t('In your subscription', 'ضمن اشتراكك') ?></span>
          <?php elseif ($hasoffer): ?>
            <div class="nit-cd-price-row">
              <span class="nit-cd-price"><?= s($finallabel) ?></span>
              <span class="nit-cd-price-was"><?= s($pricelabel) ?></span>
              <span class="nit-cd-offer"><?= s($info['offerlabel']) ?></span>
            </div>
          <?php elseif ($info['haspricing'] && $info['price'] > 0): ?>
            <div class="nit-cd-price-row">
              <span class="nit-cd-price"><?= s($pricelabel) ?></span>
            </div>
          <?php elseif (!$info['haspricing']): ?>
            <div class="nit-cd-price-row">
              <span class="nit-cd-price" style="color: var(--csuccess);"><?= $t('Free', 'مجانًا') ?></span>
            </div>
          <?php endif; ?>

          <div class="d-grid gap-2">
            <?php if ($info['enrolled'] || $info['purchased']): ?>
              <a href="<?= $courseviewurl ?>" class="btn btn-primary fw-bold"><?= $t('Go to course', 'الذهاب إلى الكورس') ?></a>
            <?php elseif ($info['covered']): ?>
              <a href="<?= $enrolurl ?>" class="btn btn-primary fw-bold"><?= $t('Enroll', 'التحاق') ?></a>
            <?php elseif ($info['haspricing']): ?>
              <button type="button" class="btn btn-primary fw-bold" data-nit-buy-course
                data-courseid="<?= (int) $course->id ?>" data-name="<?= s($coursename) ?>"
                data-price="<?= s((string) $info['price']) ?>" data-currency="<?= s($info['currency']) ?>"><?= $t('Buy now', 'اشترِ الآن') ?></button>
            <?php else: ?>
              <a href="<?= $enrolurl ?>" class="btn btn-primary fw-bold"><?= $t('Enroll', 'التحاق') ?></a>
            <?php endif; ?>

            <?php if ($categoryurl !== ''): ?>
              <a href="<?= $categoryurl ?>" class="btn btn-outline-primary fw-bold"><?= $t('Browse the category', 'تصفح التصنيف') ?></a>
            <?php endif; ?>
          </div>

          <ul class="nit-cd-facts">
            <?php if ($categoryname !== ''): ?>
              <li><span><?= $t('Category', 'التصنيف') ?></span><strong><?= $categoryname ?></strong></li>
            <?php endif; ?>
            <?php if ($teacher !== ''): ?>
              <li><span><?= $t('Teacher', 'المدرّس') ?></span><strong><?= s($teacher) ?></strong></li>
            <?php endif; ?>
            <?php if ($activitycount > 0): ?>
              <li><span><?= $t('Lessons', 'الدروس') ?></span><strong><?= $activitycount ?></strong></li>
            <?php endif; ?>
            <?php if (!empty($outline)): ?>
              <li><span><?= $t('Sections', 'الأقسام') ?></span><strong><?= count($outline) ?></strong></li>
            <?php endif; ?>
            <?php if (!empty($course->startdate)): ?>
              <li><span><?= $t('Start date', 'تاريخ البدء') ?></span><strong><?= userdate($course->startdate, get_string('strftimedatefullshort')) ?></strong></li>
            <?php endif; ?>
          </ul>

          <?php if (!$loggedin): ?>
            <p class="nit-cd-note"><?= $t('You need an account to enrol — browsing is open to everyone.',
                'تحتاج إلى حساب للالتحاق — التصفح متاح للجميع.') ?></p>
          <?php endif; ?>
        </div>
      </aside>

    </div>
  </div>
</div>

<?php
// NIT: wire the Buy button to the shared checkout modal (coupon + auto offer → Kashier),
// the same wiring the catalogue cards use. A guest is sent to log in first.
if ($nitcheckout) {
    $costr = local_nit_commerce_string_map([
        'co_title', 'co_intro', 'co_total', 'co_offer', 'co_coupon', 'co_apply', 'co_discount',
        'co_secure', 'co_proceed', 'co_cancel', 'co_loading', 'co_coupon_failed', 'co_currency',
    ]);
    echo html_writer::script('window.NIT_CO = ' . json_encode([
        'wwwroot'  => $CFG->wwwroot,
        'sesskey'  => sesskey(),
        'commerce' => '/local/nit_commerce/api.php',
        'str'      => $costr,
        'loggedin' => $loggedin,
    ]) . ';');
    echo html_writer::script(<<<'JS'
(function () {
    function init() {
        if (!window.NitCheckout || !window.NIT_CO) { return; }
        NitCheckout.init(window.NIT_CO);
        document.addEventListener('click', function (ev) {
            var btn = ev.target.closest('[data-nit-buy-course]');
            if (!btn) { return; }
            ev.preventDefault();
            if (!window.NIT_CO.loggedin) { window.location.href = window.NIT_CO.wwwroot + '/login/index.php'; return; }
            var id = btn.getAttribute('data-courseid');
            NitCheckout.open({
                itemType: 'course',
                itemId: parseInt(id, 10),
                name: btn.getAttribute('data-name'),
                price: parseFloat(btn.getAttribute('data-price')) || 0,
                currency: btn.getAttribute('data-currency') || '',
                proceed: function (code) {
                    window.location.href = window.NIT_CO.wwwroot + '/local/payments/checkout.php?courseid=' + id +
                        '&sesskey=' + encodeURIComponent(window.NIT_CO.sesskey) + '&coupon_code=' + encodeURIComponent(code);
                }
            });
        });
    }
    if (document.readyState !== 'loading') { init(); }
    else { document.addEventListener('DOMContentLoaded', init); }
})();
JS
    );
}

echo $OUTPUT->footer();
