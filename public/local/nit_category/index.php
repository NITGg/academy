<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/filelib.php');

// Respect the site's forced-login policy: if the site requires login to browse,
// gate this catalogue page too (core_course_category visibility checks below
// still apply either way).
if (!empty($CFG->forcelogin)) {
    require_login();
}

$categoryid = required_param('id', PARAM_INT);      // Parent category (drives header + labels).
$subid      = optional_param('sub', 0, PARAM_INT);  // 0 = "All" (every subcategory as its own section).

// Parent category.
$category = core_course_category::get($categoryid, MUST_EXIST);
$context  = $category->get_context();

// Direct subcategories -> the clickable label bar.
$subcategories = $category->get_children();

// Which category's courses are we listing? "All" -> parent; otherwise the chosen child.
$targetcat = $category;
if ($subid) {
    $found = null;
    foreach ($subcategories as $sc) {
        if ((int) $sc->id === $subid) {
            $found = $sc;
            break;
        }
    }
    if ($found) {
        $targetcat = $found;
    } else {
        $subid = 0; // Unknown sub id -> behave as "All".
    }
}

$PAGE->set_url(new moodle_url('/local/nit_category/index.php', ['id' => $categoryid, 'sub' => $subid]));
$PAGE->set_context($context);
$PAGE->set_title($category->get_formatted_name());
$PAGE->set_heading($category->get_formatted_name());
// NIT full-width layout: navbar + footer only, no page heading / secondary nav.
$PAGE->set_pagelayout('nit_fullwidth');

// Build the sections to render. Each section = one category header + the cards of
// its courses, so courses always sit under their own category name:
//   * "All" (sub = 0)  -> one section per direct subcategory, plus a leading
//                         section for any course sitting directly under the parent.
//   * a chosen subcat  -> just that one section.
//   * no subcategories -> a single section for the (leaf) parent itself.
// Each course's category is thus its enclosing section, matching the header above it.
$fetchcourses = function (core_course_category $cat, bool $recursive): array {
    return $cat->get_courses([
        'recursive'      => $recursive,
        'sort'           => ['sortorder' => 1],
        'summary'        => true,
        'coursecontacts' => true,
    ]);
};

$sections = [];
if (empty($subcategories)) {
    $sections[] = ['cat' => $category, 'courses' => $fetchcourses($category, true)];
} else if ($subid) {
    $sections[] = ['cat' => $targetcat, 'courses' => $fetchcourses($targetcat, true)];
} else {
    // Courses that live directly under the parent (not inside any child) get their
    // own section first, so nothing is dropped from the "All" view.
    $directcourses = $fetchcourses($category, false);
    if (!empty($directcourses)) {
        $sections[] = ['cat' => $category, 'courses' => $directcourses];
    }
    foreach ($subcategories as $sc) {
        $sections[] = ['cat' => $sc, 'courses' => $fetchcourses($sc, true)];
    }
}

// Drop empty sections and tally the visible total.
$sections = array_values(array_filter($sections, static fn($s) => !empty($s['courses'])));
$totalcourses = 0;
foreach ($sections as $s) {
    $totalcourses += count($s['courses']);
}

// Hero banner always shows the grand total for the whole parent category, regardless
// of any subcategory filter currently selected.
$bannertotal = $category->get_courses_count(['recursive' => true]);

// Category image: Moodle categories have no image of their own, so fall back to
// the site logo ("if the category has no image, show the site logo").
$logo = $OUTPUT->get_logo_url() ?: $OUTPUT->get_compact_logo_url();
$categoryimage = $logo ? $logo->out(false) : '';

// Colour palette: this page reads entirely from the site Brand Colors palette
// (theme_nit's --nit-brand-* custom properties), so it re-skins with the rest of
// the site and honours RTL/LTR + dark/light automatically. The 8 local slots map
// to brand roles by job: backgrounds -> Background/Surface, the call-to-action fill
// -> Primary, text -> Text primary/secondary, highlights -> Accent. --cbg3 (the tile
// behind category/course images) is Surface lifted a touch so logos read cleanly.
$stylevars =
    '--cbg1: var(--nit-brand-background); '
  . '--cbg2: var(--nit-brand-surface); '
  . '--cbg3: color-mix(in srgb, var(--nit-brand-surface) 88%, var(--nit-brand-textprimary)); '
  . '--cbg4: var(--nit-brand-primary); '
  . '--ctext1: var(--nit-brand-textprimary); '
  . '--ctext2: var(--nit-brand-textsecondary); '
  . '--ctext3: var(--nit-brand-accent); '
  . '--ctext4: var(--nit-brand-textprimary); '
  . '--cborder: var(--nit-brand-borderprimary); '
  . '--csuccess: var(--nit-brand-success); ';

// Brand group for this category (gallery "Category styles" tab). Group 1 is the
// default layer (no class); groups 2/3 add the .nit-brand-2 / .nit-brand-3 switch
// class to the wrapper, so every --nit-brand-* the page reads (and hence every
// --cbg*/--ctext* above) resolves from that group instead.
$brandgroupclass = '';
if (function_exists('theme_nit_category_brand_group')) {
    $brandgroupclass = theme_nit_brand_group_class(theme_nit_category_brand_group((int) $category->id));
}

// Bilingual inline helper (site is en/ar); mirrors the theme's {mlang} pairs.
$isar = (strpos(current_language(), 'ar') === 0);
$t = function (string $en, string $ar) use ($isar) {
    return $isar ? $ar : $en;
};

// A pill/label for the subcategory filter bar (colours from the active style).
$pill = function (moodle_url $url, string $label, bool $active): string {
    $base = 'display:inline-block; padding:9px 22px; border-radius:50px; font-size:14px; '
          . 'font-weight:bold; text-decoration:none; white-space:nowrap; transition:all .25s ease;';
    if ($active) {
        $style = $base . 'background:var(--cbg4); color:var(--ctext4); border:1px solid transparent; '
               . 'box-shadow:0 6px 18px color-mix(in srgb, var(--cbg4) 30%, transparent);';
    } else {
        $style = $base . 'background:var(--cbg2); color:var(--ctext2); '
               . 'border:1px solid color-mix(in srgb, var(--ctext1) 14%, transparent);';
    }
    return '<a href="' . $url->out() . '" style="' . $style . '">' . $label . '</a>';
};

$description  = format_text($category->description, $category->descriptionformat, ['context' => $context]);
$categoryname = $category->get_formatted_name();

// NIT: checkout modal + course offer/price support (guarded — degrade if the plugins are absent).
$nitcheckout = class_exists('\local_payments\price_resolver')
    && file_exists($CFG->dirroot . '/local/nit_commerce/lib.php')
    && class_exists('\local_nit_commerce\discount_manager');
if ($nitcheckout) {
    require_once($CFG->dirroot . '/local/nit_commerce/lib.php');
    $PAGE->requires->js(new moodle_url('/local/nit_commerce/checkout_modal.js'), true);
}
// Per-course state for a card: enrolment, subscription coverage, pricing, offer.
$nitcourseinfo = function ($courseid) use ($nitcheckout) {
    global $USER;
    $out = ['enrolled' => false, 'covered' => false, 'free' => true, 'haspricing' => false,
        'price' => 0.0, 'offerlabel' => '', 'offerfinal' => 0.0];
    $uid = (int) ($USER->id ?? 0);
    $ctx = context_course::instance($courseid);
    $out['enrolled'] = $uid > 0 && is_enrolled($ctx, $uid, '', true);

    if (!$nitcheckout) {
        return $out;
    }
    $out['haspricing'] = (bool) \local_payments\price_resolver::has_pricing($courseid);
    $out['free'] = !$out['haspricing'];

    // Covered by an active subscription (grants access without buying). Only relevant when not
    // already enrolled and the course is paid (a free course is just "enrol").
    if (!$out['enrolled'] && $out['haspricing']
            && class_exists('\local_nit_subscriptions\subscription_purchase_manager')) {
        $out['covered'] = (bool) \local_payments\price_resolver::is_covered_by_active_subscription($courseid, $uid);
    }

    if ($out['haspricing']) {
        try {
            $pricing = \local_payments\price_resolver::resolve($courseid, $uid);
            $base = (float) $pricing->price;
            $out['price'] = $base;
            $summary = \local_nit_commerce\discount_manager::offer_summary('course', (int) $courseid, $base);
            if ($summary) {
                $out['offerlabel'] = $summary['label'];   // e.g. "-40%"
                $out['offerfinal'] = (float) $summary['final'];
            }
        } catch (\Throwable $e) {
            // Leave defaults on any pricing error.
        }
    }
    return $out;
};

echo $OUTPUT->header();
?>

<div dir="auto" class="nit-cat-details<?= $brandgroupclass !== '' ? ' ' . $brandgroupclass : '' ?>" style="<?= $stylevars ?>background: var(--cbg1); min-height: 100vh; padding-bottom: 40px; width: 100vw; max-width: 100vw; margin-inline: calc(50% - 50vw); margin-top: 0;">

  <!-- Category Hero Banner -->
  <style>
    @keyframes nit-gridshift { 0% { transform: translateY(0); } 100% { transform: translateY(60px); } }
    @keyframes nit-hpulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
    @keyframes nit-fadeup { from { opacity: 0; transform: translateY(28px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes nit-fadedown { from { opacity: 0; transform: translateY(-18px); } to { opacity: 1; transform: translateY(0); } }
  </style>
  <div style="background: var(--cbg2); min-height: 85vh; display: flex; align-items: center; justify-content: center; flex-direction: column; text-align: center; padding: 120px 5% 80px; position: relative; overflow: hidden; border-bottom: 1px solid color-mix(in srgb, var(--cbg4) 20%, transparent);">

    <!-- Animated grid background -->
    <div style="position: absolute; inset: 0; background-image: linear-gradient(color-mix(in srgb, var(--cbg4) 6%, transparent) 1px, transparent 1px), linear-gradient(90deg, color-mix(in srgb, var(--cbg4) 6%, transparent) 1px, transparent 1px); background-size: 60px 60px; animation: nit-gridshift 20s linear infinite; pointer-events: none;"></div>

    <!-- Radial glows -->
    <div style="position: absolute; top: -30%; left: 50%; transform: translateX(-50%); width: 80%; height: 80%; background: radial-gradient(ellipse 80% 60% at 50% 0%, color-mix(in srgb, var(--cborder) 30%, transparent) 0%, transparent 70%); pointer-events: none;"></div>
    <div style="position: absolute; bottom: -20%; inset-inline-end: -5%; width: 35%; height: 70%; background: radial-gradient(ellipse 40% 40% at 80% 80%, color-mix(in srgb, var(--cbg4) 12%, transparent) 0%, transparent 60%); pointer-events: none;"></div>

    <div style="max-width: 860px; margin: 0 auto; position: relative; z-index: 1;">

      <!-- Badge: category name with pulsing dot -->
      <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: color-mix(in srgb, var(--cbg4) 12%, transparent); border: 1px solid color-mix(in srgb, var(--cbg4) 30%, transparent); border-radius: 50px; padding: 0.4rem 1.2rem; font-size: 0.85rem; color: var(--ctext3); font-weight: 600; margin-bottom: 2rem; position: relative; z-index: 1; animation: nit-fadedown 0.8s ease both;">
        <span style="width: 8px; height: 8px; background: var(--csuccess); border-radius: 50%; animation: nit-hpulse 2s infinite; flex-shrink: 0;"></span>
        <?= $categoryname ?>
      </div>

      <!-- H1: count in accent color, subtitle with secondary accent -->
      <h1 style="font-size: clamp(2.4rem, 6vw, 4.5rem); font-weight: 800; line-height: 1.15; margin: 0; color: var(--ctext1); position: relative; z-index: 1; animation: nit-fadeup 0.9s ease 0.1s both;">
        <span style="color: var(--ctext3);"><?= $bannertotal ?> <?= $t('Training programs', 'برنامجًا تدريبيًا') ?></span><br>
        <span><?= $t('Diplomas and certificates', 'دبلومات وشهادات') ?></span> <span style="color: var(--cbg4);"><?= $t('professional', 'احترافية') ?></span>
      </h1>

      <!-- Description -->
      <?php if (trim(strip_tags($description)) !== ''): ?>
      <div style="font-size: clamp(1rem, 2vw, 1.25rem); color: var(--ctext2); max-width: 680px; margin: 1.5rem auto; line-height: 1.8; position: relative; z-index: 1; animation: nit-fadeup 0.9s ease 0.25s both;">
        <?= $description ?>
      </div>
      <?php endif; ?>

      <!-- Stats: floating flex, no border box -->
      <div style="display: flex; gap: 3rem; margin: 2.5rem 0; justify-content: center; flex-wrap: wrap; position: relative; z-index: 1; animation: nit-fadeup 0.9s ease 0.4s both;">
        <div style="text-align: center;">
          <span style="font-size: 2.2rem; font-weight: 800; color: var(--ctext3); display: block; line-height: 1;"><?= $bannertotal ?></span>
          <span style="font-size: 0.8rem; color: var(--ctext2); font-weight: 500;"><?= $t('Courses and diplomas', 'دورة ودبلوم') ?></span>
        </div>
        <div style="text-align: center;">
          <span style="font-size: 2.2rem; font-weight: 800; color: var(--ctext3); display: block; line-height: 1;"><?= count($subcategories) ?></span>
          <span style="font-size: 0.8rem; color: var(--ctext2); font-weight: 500;"><?= $t('Main specializations', 'تخصص رئيسي') ?></span>
        </div>
        <div style="text-align: center;">
          <span style="font-size: 2.2rem; font-weight: 800; color: var(--ctext3); display: block; line-height: 1;">4</span>
          <span style="font-size: 0.8rem; color: var(--ctext2); font-weight: 500;"><?= $t('Educational levels', 'مستويات تعليمية') ?></span>
        </div>
      </div>

      <!-- Buttons -->
      <div style="display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center; animation: nit-fadeup 0.9s ease 0.55s both;">
        <a href="#nit-cat-filters"
           style="background: linear-gradient(135deg, var(--cbg4), var(--ctext3)); color: var(--cbg1); padding: 1rem 2.5rem; border-radius: 8px; font-weight: 700; font-size: 1rem; text-decoration: none; box-shadow: 0 8px 30px color-mix(in srgb, var(--cbg4) 40%, transparent); display: inline-block;"
           onclick="event.preventDefault(); document.getElementById('nit-cat-filters').scrollIntoView({behavior:'smooth'});">
          <?= $t('Explore specializations', 'استكشف التخصصات') ?>
        </a>
        <a href="#nit-cat-filters"
           style="background: transparent; border: 1px solid color-mix(in srgb, var(--cbg4) 40%, transparent); color: var(--ctext3); padding: 1rem 2.5rem; border-radius: 8px; font-weight: 600; font-size: 1rem; text-decoration: none; display: inline-block;"
           onclick="event.preventDefault(); document.getElementById('nit-cat-filters').scrollIntoView({behavior:'smooth'});">
          <?= $t('Flexible plans', 'خطط مرنة') ?>
        </a>
      </div>

    </div>
  </div>

  <!-- Subcategory Filter Bar (All + children) -->
  <?php if (!empty($subcategories)): ?>
  <div id="nit-cat-filters" style="padding: 32px 16px 0;">
    <div style="max-width: 1200px; margin: 0 auto; display: flex; flex-wrap: wrap; justify-content: center; gap: 12px;">
      <?php
        $allurl = new moodle_url('/local/nit_category/index.php', ['id' => $categoryid]);
        echo $pill($allurl, $t('All', 'الكل'), $subid === 0);
        foreach ($subcategories as $sc) {
            $suburl = new moodle_url('/local/nit_category/index.php', ['id' => $categoryid, 'sub' => $sc->id]);
            echo $pill($suburl, $sc->get_formatted_name(), $subid === (int) $sc->id);
        }
      ?>
    </div>

    <?php
      // When a subcategory is selected, show its description.
      if ($subid) {
          $subdescription = format_text($targetcat->description, $targetcat->descriptionformat, ['context' => $targetcat->get_context()]);
          if (trim(strip_tags($subdescription)) !== '') {
              echo '<div style="max-width: 900px; margin: 24px auto 0; text-align: center; color: var(--ctext2); font-size: 15px; line-height: 1.7;">' . $subdescription . '</div>';
          }
      }
    ?>
  </div>
  <?php endif; ?>

  <!-- Courses Section -->
  <div style="padding: 32px 16px 16px;">
    <div style="max-width: 1200px; margin: 0 auto;">

      <?php
        // One card renderer, shared by every section. $sectioncat is the category the
        // card lives under (its header), so the card shows that category's name. The
        // dot colour is inherited from the section wrapper via the --dot custom prop.
        $rendercard = function (core_course_list_element $course, string $sectionname) use ($t, $nitcourseinfo) {
            $courseurl  = new moodle_url('/course/view.php', ['id' => $course->id]);
            $coursename = $course->get_formatted_name();

            // Short plain-text summary (no course image is used in this design).
            $summary = '';
            if ($course->has_summary()) {
                $coursecontext = context_course::instance($course->id);
                $plain = html_to_text(
                    format_text($course->summary, $course->summaryformat, ['context' => $coursecontext, 'noclean' => true]),
                    0,
                    false
                );
                $summary = shorten_text(trim($plain), 160);
            }

            $price      = function_exists('theme_nit_course_price') ? theme_nit_course_price((int) $course->id) : '';
            $teacher    = function_exists('theme_nit_course_teacher') ? theme_nit_course_teacher((int) $course->id) : '';
            $pricelabel = $price !== '' ? $price : $t('Free', 'مجانًا');
            $info       = $nitcourseinfo($course->id);

            $detailsurl = $courseurl->out();
            $enrolurl   = (new moodle_url('/local/nit_subscriptions/enrol.php',
                ['courseid' => $course->id, 'sesskey' => sesskey()]))->out(false);
        ?>
        <!-- Course Card: fixed min-height + stretch grid => every card is the same size. -->
        <div style="background: var(--cbg2); border: 1px solid color-mix(in srgb, var(--cborder) 55%, transparent); border-radius: 16px; padding: 22px; display: flex; flex-direction: column; height: 100%; min-height: 320px; transition: transform 0.3s ease, box-shadow 0.3s ease;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 12px 28px rgba(0,0,0,0.38)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">

          <!-- Category name pill (with the section's colour dot) -->
          <div style="align-self: flex-start; display: inline-flex; align-items: center; gap: 8px; background: color-mix(in srgb, var(--dot) 14%, transparent); border: 1px solid color-mix(in srgb, var(--dot) 40%, transparent); color: var(--ctext1); padding: 6px 14px; border-radius: 50px; font-size: 12px; font-weight: bold; margin-bottom: 16px;">
            <span style="width: 9px; height: 9px; border-radius: 50%; background: var(--dot); box-shadow: 0 0 8px var(--dot); flex: 0 0 auto;"></span>
            <span><?= $sectionname ?></span>
          </div>

          <!-- Course name -->
          <h3 style="font-size: 18px; font-weight: bold; color: var(--ctext1); margin: 0 0 10px; line-height: 1.4;">
            <?= $coursename ?>
          </h3>

          <?php if ($teacher !== ''): ?>
          <div style="font-size: 12px; color: var(--ctext2); margin: 0 0 10px;">
            👤 <?= s($teacher) ?>
          </div>
          <?php endif; ?>

          <!-- Course description -->
          <?php if ($summary !== ''): ?>
          <p style="font-size: 13px; color: var(--ctext2); line-height: 1.7; margin: 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
            <?= s($summary) ?>
          </p>
          <?php endif; ?>

          <!-- Footer: pinned to the bottom. A fixed-height status/price row sits above the
               buttons so the buttons never move — a free course simply leaves it empty,
               a paid course shows its price in the SAME reserved slot. -->
          <div style="margin-top: auto; padding-top: 18px;">
            <div style="min-height: 30px; display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 12px;">
              <?php if ($info['enrolled']): ?>
                <span style="display: inline-flex; align-items: center; gap: 5px; background: color-mix(in srgb, var(--csuccess) 16%, transparent); color: var(--csuccess); border: 1px solid color-mix(in srgb, var(--csuccess) 45%, transparent); font-size: 12px; font-weight: bold; padding: 4px 12px; border-radius: 50px;">
                  ✓ <?= $t('Enrolled', 'مُسجَّل') ?>
                </span>
              <?php elseif ($info['covered']): ?>
                <span style="display: inline-flex; align-items: center; gap: 5px; background: color-mix(in srgb, var(--ctext3) 16%, transparent); color: var(--ctext3); border: 1px solid color-mix(in srgb, var(--ctext3) 45%, transparent); font-size: 12px; font-weight: bold; padding: 4px 12px; border-radius: 50px;">
                  ★ <?= $t('In your subscription', 'ضمن اشتراكك') ?>
                </span>
              <?php elseif ($info['offerlabel'] !== '' && $info['offerfinal'] > 0): ?>
                <span style="font-size: 13px; color: var(--ctext2); text-decoration: line-through; opacity: 0.7;"><?= s($pricelabel) ?></span>
                <span style="font-size: 16px; font-weight: bold; color: var(--ctext1);"><?= s(number_format($info['offerfinal'], 0)) ?> <?= $t('EGP', 'ج.م') ?></span>
                <span style="background: var(--cbg4); color: var(--ctext4); font-size: 11px; font-weight: bold; padding: 3px 10px; border-radius: 50px;"><?= s($info['offerlabel']) ?></span>
              <?php elseif ($info['haspricing']): ?>
                <span style="font-size: 16px; font-weight: bold; color: var(--ctext1);"><?= s($pricelabel) ?></span>
              <?php else: // Free course: the slot stays empty (reserved) so buttons stay put. ?>
                <span style="font-size: 13px; font-weight: bold; color: var(--csuccess);"><?= $t('Free', 'مجانًا') ?></span>
              <?php endif; ?>
            </div>

            <!-- Actions: gallery button components (.btn-primary / .btn-outline-primary).
                 Enrolled shows one button; every other state shows two. -->
            <div class="d-grid gap-2">
              <?php if ($info['enrolled']): ?>
                <a href="<?= $detailsurl ?>" class="btn btn-outline-primary fw-bold"><?= $t('Course details', 'تفاصيل الكورس') ?></a>
              <?php elseif ($info['covered']): ?>
                <a href="<?= $enrolurl ?>" class="btn btn-primary fw-bold"><?= $t('Enroll', 'التحاق') ?></a>
                <a href="<?= $detailsurl ?>" class="btn btn-outline-primary fw-bold"><?= $t('Course details', 'تفاصيل الكورس') ?></a>
              <?php elseif ($info['haspricing']): ?>
                <button type="button" class="btn btn-primary fw-bold" data-nit-buy-course
                  data-courseid="<?= (int) $course->id ?>" data-name="<?= s($coursename) ?>"
                  data-price="<?= s((string) $info['price']) ?>"><?= $t('Buy now', 'اشترِ الآن') ?></button>
                <a href="<?= $detailsurl ?>" class="btn btn-outline-primary fw-bold"><?= $t('Course details', 'تفاصيل الكورس') ?></a>
              <?php else: // Free course. ?>
                <a href="<?= $enrolurl ?>" class="btn btn-primary fw-bold"><?= $t('Enroll', 'التحاق') ?></a>
                <a href="<?= $detailsurl ?>" class="btn btn-outline-primary fw-bold"><?= $t('Course details', 'تفاصيل الكورس') ?></a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php
        };

        // Palette of dot colours, cycled per section (decorative — pure brand variables,
        // no new data). Green / red / blue / amber all read well on the dark surface.
        $dotvars = ['--nit-brand-success', '--nit-brand-accent', '--nit-brand-bordersecondary', '--nit-brand-warning'];
      ?>

      <?php if (!empty($sections)): ?>
        <?php foreach ($sections as $i => $section): ?>
        <?php
          $sectioncat  = $section['cat'];
          $sectionname = $sectioncat->get_formatted_name();
          $sectiondot  = $dotvars[$i % count($dotvars)];
        ?>
        <!-- Category section: header pill + this category's course cards -->
        <section style="--dot: var(<?= $sectiondot ?>); margin-bottom: 44px;">
          <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 22px;">
            <div style="display: inline-flex; align-items: center; gap: 10px; background: var(--cbg2); border: 1px solid color-mix(in srgb, var(--dot) 45%, transparent); padding: 10px 22px; border-radius: 50px;">
              <span style="width: 11px; height: 11px; border-radius: 50%; background: var(--dot); box-shadow: 0 0 10px var(--dot); flex: 0 0 auto;"></span>
              <span style="font-size: 16px; font-weight: bold; color: var(--ctext1);"><?= $sectionname ?></span>
              <span style="font-size: 13px; font-weight: bold; color: var(--ctext3);">(<?= count($section['courses']) ?>)</span>
            </div>
            <div style="flex: 1; height: 1px; background: color-mix(in srgb, var(--ctext1) 8%, transparent);"></div>
          </div>

          <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px; align-items: stretch;">
            <?php foreach ($section['courses'] as $course): ?>
              <?php $rendercard($course, $sectionname); ?>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endforeach; ?>
      <?php else: ?>
      <div style="text-align: center; color: var(--ctext2); padding: 40px;">
        <?= $t('No courses found in this category.', 'لا توجد دورات في هذا التصنيف.') ?>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php
// NIT: wire the course Buy buttons to the shared checkout modal (coupon + auto offer → Kashier).
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
        'loggedin' => isloggedin() && !isguestuser(),
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
