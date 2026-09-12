<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/local/nit_category/lib.php');
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

// Build a category "node": its own (direct) courses plus a node for every child
// category, recursively. This lets each subcategory — at any depth — render as its
// own titled group under its parent, instead of a parent lumping every descendant
// course into one flat list.
$buildnode = function (core_course_category $cat) use (&$buildnode, $fetchcourses): array {
    $children = [];
    foreach ($cat->get_children() as $child) {
        $children[] = $buildnode($child);
    }
    return [
        'cat'      => $cat,
        'courses'  => $fetchcourses($cat, false), // direct only; descendants are child nodes
        'children' => $children,
    ];
};

// Total courses in a node's whole subtree (its own + every descendant's).
$counttree = function (array $node) use (&$counttree): int {
    $n = count($node['courses']);
    foreach ($node['children'] as $child) {
        $n += $counttree($child);
    }
    return $n;
};

$rootnodes = [];
if (empty($subcategories)) {
    // Flat category (no children): just its own courses.
    $rootnodes[] = $buildnode($category);
} else if ($subid) {
    // One subcategory selected: render that subtree (its courses + nested subcategories).
    $rootnodes[] = $buildnode($targetcat);
} else {
    // "All": courses that live directly under the parent (not inside any child) get
    // their own section first so nothing is dropped, then every subcategory subtree.
    $directcourses = $fetchcourses($category, false);
    if (!empty($directcourses)) {
        $rootnodes[] = ['cat' => $category, 'courses' => $directcourses, 'children' => []];
    }
    foreach ($subcategories as $sc) {
        $rootnodes[] = $buildnode($sc);
    }
}

// Drop empty subtrees and tally the visible total.
$rootnodes = array_values(array_filter($rootnodes, static fn($n) => $counttree($n) > 0));
$totalcourses = 0;
foreach ($rootnodes as $n) {
    $totalcourses += $counttree($n);
}

// Hero banner always shows the grand total for the whole parent category, regardless
// of any subcategory filter currently selected.
$bannertotal = $category->get_courses_count(['recursive' => true]);

// Category image: Moodle categories have no image of their own, so this plugin owns
// one (uploaded on the "Category image" tab of the category). local_nit_category_get_image_url()
// walks the chain uploaded image -> first image in the category description -> nearest
// ancestor's image; if the whole chain comes up empty we still fall back to the site
// logo ("if the category has no image, show the site logo").
$categoryimage = local_nit_category_get_image_url((int) $category->id);
// Whether that image is genuinely this category's own (or an ancestor's) rather than
// the site-wide logo. The hero only shows a picture when there is a real one — a big
// site logo on top of every un-imaged category would be noise, not branding.
$hasrealimage = ($categoryimage !== '');
if (!$hasrealimage) {
    $logo = $OUTPUT->get_logo_url() ?: $OUTPUT->get_compact_logo_url();
    $categoryimage = $logo ? $logo->out(false) : '';
}

// Colour palette: this page reads entirely from the site Brand Colors palette
// (theme_nit's --nit-brand-* custom properties), so it re-skins with the rest of
// the site and honours RTL/LTR + dark/light automatically. The 8 local slots map
// to brand roles by job: backgrounds -> Background/Surface, the call-to-action fill
// -> Primary, text -> Text primary/secondary, accent TEXT (hero counts, stat numbers,
// labels) -> Accent Text (--ctext3), and non-text accent tints/pills/borders -> Accent
// (--caccent). --cbg3 (the tile behind category/course images) is Surface lifted a
// touch so logos read cleanly.
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

// This category's Brand Colors group is NOT applied here. The theme puts the
// group's switch class (.nit-brand-2 …) on the <html> element for every page in
// a styled category — this page's context IS that category — so the --nit-brand-*
// that the --cbg*/--ctext* above read already resolve from it. A second copy on
// the wrapper below would be one pinned to the mode this request rendered in, and
// the navbar light/dark button, which moves between the category's own light and
// dark styles, would leave that copy behind.
//
// theme/nit/lib.php is still required here: it is only auto-included when the
// theme is initialised (theme_config::__construct, at $OUTPUT->header(), later
// than this), and the course cards below ask it for a teacher link.
$themenitlib = $CFG->dirroot . '/theme/nit/lib.php';
if (file_exists($themenitlib)) {
    require_once($themenitlib);
}

// Bilingual inline helper (site is en/ar); mirrors the theme's {mlang} pairs.
$isar = (strpos(current_language(), 'ar') === 0);
$t = function (string $en, string $ar) use ($isar) {
    return $isar ? $ar : $en;
};

// "Why thousands choose us" — the section under the hero. Site-wide content the admin
// edits on the "Why choose us" tab of the Site pages manager
// (/local/profilefields/manage.php?tab=whychoose); null when there is nothing to show.
$whychoose = \local_nit_category\whychoose::for_display();

// Subcategory filter buttons reuse the site's gallery button components (Components
// tab): the active filter is a solid .btn-primary, the rest are .btn-outline-primary.
// $icon is the category's own icon HTML (or '' for the "All" button, which is not a
// category); it prints inside the button, before the label.
$pill = function (moodle_url $url, string $label, bool $active, string $icon = ''): string {
    $cls = $active ? 'btn btn-primary' : 'btn btn-outline-primary';
    return '<a href="' . $url->out() . '" class="' . $cls . ' fw-bold nit-cat-pill">'
        . $icon . '<span>' . $label . '</span></a>';
};

$description  = format_text($category->description, $category->descriptionformat, ['context' => $context]);
$categoryname = $category->get_formatted_name();

// Does this page have anything to advertise below the courses? The hero's "Flexible plans" and
// "Coupon plans" buttons scroll to the plans / coupons sections, so each button exists only when
// its section will. The answer is worked out HERE, with the same rule the two blocks' feeds
// apply (subscription_manager::matches_category(), coupon_manager::get_available_coupons()),
// rather than by watching the blocks fill in: a button that appeared a second after the hero
// painted, or scrolled to a section that then said "nothing here", would be worse than none.
// The plan test deliberately stops short of pricing — the block lists a plan whether or not
// this visitor can be quoted for it, so "has plans" must not depend on the price either.
$hasplans = false;
if (class_exists('\local_nit_subscriptions\subscription_manager')) {
    $activeplans = \local_nit_subscriptions\subscription_manager::get_subscriptions(
        \local_nit_subscriptions\subscription_manager::STATUS_ACTIVE);
    foreach ($activeplans as $plan) {
        if (\local_nit_subscriptions\subscription_manager::matches_category((int) $plan->id, $categoryid)) {
            $hasplans = true;
            break;
        }
    }
}
$hascoupons = class_exists('\local_nit_commerce\coupon_manager')
    && !empty(\local_nit_commerce\coupon_manager::get_available_coupons(null, $categoryid));

// Where "Explore specializations" lands: the subcategory filter bar when the category has
// children, otherwise straight at the course list (a leaf category has no bar to show).
$exploretarget = !empty($subcategories) ? 'nit-cat-filters' : 'nit-cat-courses';

// NIT: checkout modal + course offer/price support (guarded — degrade if the plugins are absent).
$nitcheckout = local_nit_category_require_checkout();
// Per-course state for a card: enrolment, purchase, subscription coverage, pricing, offer.
//
// This is the ONE place a card's price is resolved. It used to be resolved twice — here for
// the Buy button and again through theme_nit_course_price() for the printed label — and the
// two disagreed whenever a rule failed to resolve, so a paid course printed "Free" right next
// to its own "Buy now" button. Everything the card shows now comes out of this array.
//
// Guest vs logged in: price_resolver::resolve() is country-aware and keyed on the viewer, so
// the user id is passed through in BOTH states. A logged-in user is priced by their profile
// country and by nothing else — with that field empty the card shows no price and no Buy
// button at all ('countryrequired' below), because a price they were never quoted is worse
// than no price. A guest (id 0) is priced by IP geolocation, and when that yields nothing the
// course's default price is used; see local_payments\country_detector::detect_for_pricing().
//
// The answer itself now lives in local_payments\price_resolver::course_state(), because the
// course page's own hero asks exactly the same question and the two must not be allowed to
// answer it differently — a course that says "5.00 USD / Buy now" on this page and "Free" on
// its own page is worse than either answer alone. This closure is what is left: the enrolment
// probe that still works when local_payments is absent, and the plugin guard.
$nitcourseinfo = function ($courseid) use ($nitcheckout) {
    global $USER;

    if (!$nitcheckout) {
        // No payments plugin: nothing is priced, so the card offers a plain "Enroll".
        $uid = (int) ($USER->id ?? 0);
        $ctx = context_course::instance($courseid);
        return ['enrolled' => $uid > 0 && is_enrolled($ctx, $uid, '', true),
            'purchased' => false, 'covered' => false, 'free' => true, 'haspricing' => false,
            'price' => 0.0, 'currency' => '', 'offerlabel' => '', 'offerfinal' => 0.0,
            'countryrequired' => false];
    }

    return \local_payments\price_resolver::course_state((int) $courseid);
};

// One money formatter for every price a card prints: the base price, the struck-through
// original and the discounted final all go through it, so they can never disagree on
// decimals or currency. Digits stay unlocalised (matching the rest of the shop).
$nitmoney = function (float $amount, string $currency) use ($t): string {
    return format_float($amount, 2, false) . ' ' . ($currency !== '' ? $currency : $t('EGP', 'ج.م'));
};

// The price tags a card prints in its status row. With a live offer that is the original struck
// through + the discounted amount + the "-40%" pill; otherwise the plain price; and nothing at all
// when the course is priced but no rule resolves to an amount (saying nothing beats claiming
// "Free"). One helper because the same tags print in EVERY card state — next to the "Enrolled",
// "Purchased" and "In your subscription" badges and above the "Buy now" button — so a priced
// course always shows what it costs and no two states can drift apart.
// The "set your country" notice, built once per page (it is the same for every card) and only
// when a card actually needs it. Empty array = this viewer is priced normally.
$nitcountrynotice = (class_exists('\local_payments\country_detector')
    && \local_payments\country_detector::pricing_blocked())
    ? \local_payments\country_detector::country_required_notice() : [];

$nitpricetags = function (array $info) use ($nitmoney, $nitcountrynotice): string {
    // No profile country: the price slot carries the reason there is no price, not an amount.
    if (!empty($info['countryrequired']) && $nitcountrynotice) {
        return '<span style="font-size: 13px; font-weight: bold; color: var(--ctext2);">'
            . s($nitcountrynotice['short']) . '</span>';
    }
    if ($info['offerlabel'] !== '' && $info['offerfinal'] > 0) {
        return '<span style="font-size: 13px; color: var(--ctext2); text-decoration: line-through; opacity: 0.7;">'
            . s($nitmoney($info['price'], $info['currency'])) . '</span>'
            . '<span style="font-size: 16px; font-weight: bold; color: var(--ctext1);">'
            . s($nitmoney($info['offerfinal'], $info['currency'])) . '</span>'
            . '<span style="background: var(--cbg4); color: var(--ctext4); font-size: 11px; font-weight: bold;'
            . ' padding: 3px 10px; border-radius: 50px;">' . s($info['offerlabel']) . '</span>';
    }
    if ($info['price'] > 0) {
        return '<span style="font-size: 16px; font-weight: bold; color: var(--ctext1);">'
            . s($nitmoney($info['price'], $info['currency'])) . '</span>';
    }
    return '';
};

echo $OUTPUT->header();
?>

<div dir="auto" class="nit-cat-details" style="<?= $stylevars ?>background: var(--cbg1); min-height: 100vh; padding-bottom: 40px; width: 100vw; max-width: 100vw; margin-inline: calc(50% - 50vw); margin-top: 0;">

  <!-- Category Hero Banner (X-Trade style) -->
  <style>
    @keyframes nit-gridshift { 0% { transform: translateY(0); } 100% { transform: translateY(60px); } }
    @keyframes nit-hpulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
    @keyframes nit-fadeup { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes nit-fadedown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }

    .nit-hero {
      background: var(--cbg2);
      min-height: 85vh;
      display: flex; align-items: center; justify-content: center; flex-direction: column;
      text-align: center;
      padding: 120px 5% 80px;
      position: relative; overflow: hidden;
      border-bottom: 1px solid color-mix(in srgb, var(--cbg4) 20%, transparent);
    }
    .nit-hero__grid {
      content: ''; position: absolute; inset: 0; pointer-events: none;
      background-image:
        linear-gradient(color-mix(in srgb, var(--cbg4) 6%, transparent) 1px, transparent 1px),
        linear-gradient(90deg, color-mix(in srgb, var(--cbg4) 6%, transparent) 1px, transparent 1px);
      background-size: 60px 60px;
      animation: nit-gridshift 20s linear infinite;
    }
    .nit-hero__glow-a {
      position: absolute; top: -30%; left: 50%; transform: translateX(-50%);
      width: 80%; height: 80%; pointer-events: none;
      background: radial-gradient(ellipse 80% 60% at 50% 0%, color-mix(in srgb, var(--cborder) 30%, transparent) 0%, transparent 70%);
    }
    .nit-hero__glow-b {
      position: absolute; bottom: -20%; inset-inline-end: -5%;
      width: 35%; height: 70%; pointer-events: none;
      background: radial-gradient(ellipse 40% 40% at 80% 80%, color-mix(in srgb, var(--cbg4) 12%, transparent) 0%, transparent 60%);
    }
    .nit-hero__inner { max-width: 860px; margin: 0 auto; position: relative; z-index: 1; }

    /* Category ICON — the small glyph beside a category NAME (badge, filter pill,
       section heading). One base rule for both variants the renderer can emit: an
       <img> for an uploaded icon, or a <span> holding an emoji. Sizing with both
       width/height and font-size keeps the two visually identical, and `contain`
       stops a non-square upload from being squashed. */
    .nit-cat-icon {
      display: inline-block; vertical-align: middle;
      object-fit: contain; text-align: center;
      flex: 0 0 auto; line-height: 1;
    }
    .nit-cat-icon--badge   { width: 20px; height: 20px; font-size: 17px; }
    .nit-cat-icon--pill    { width: 20px; height: 20px; font-size: 17px; }
    .nit-cat-icon--spec    { width: 34px; height: 34px; font-size: 29px; }
    .nit-cat-icon--specsub { width: 24px; height: 24px; font-size: 20px; }

    /* Keep the pill's icon and label on one line with a real gap. */
    .nit-cat-pill {
      display: inline-flex; align-items: center; gap: 8px;
    }

    /* Category image — sits above the badge. `contain` so wide banners and square
       logos both read correctly, on a lifted surface tile like the course cards. */
    .nit-hero__logo {
      display: block; margin: 0 auto 1.5rem;
      max-width: min(320px, 70vw); max-height: 140px;
      width: auto; height: auto;
      object-fit: contain;
      border-radius: 16px;
      background: var(--cbg3);
      padding: 14px;
      box-sizing: border-box;
      animation: nit-fadedown 0.8s ease both;
    }

    /* Badge — X-Trade .hero-badge */
    .nit-hero__badge {
      display: inline-flex; align-items: center; gap: 0.5rem;
      background: color-mix(in srgb, var(--cbg4) 12%, transparent);
      border: 1px solid color-mix(in srgb, var(--cbg4) 30%, transparent);
      border-radius: 50px; padding: 6px 19px;
      font-size: 14px; color: var(--ctext3); font-weight: 600;
      margin-bottom: 2rem;
      animation: nit-fadedown 0.8s ease both;
    }
    .nit-hero__badge-dot {
      width: 8px; height: 8px; background: var(--csuccess); border-radius: 50%;
      animation: nit-hpulse 2s infinite; flex-shrink: 0;
    }

    /* H1 — X-Trade .hero h1 : clamp(2.4rem, 6vw, 4.5rem) @16px root = 38/72px */
    .nit-hero__title {
      font-size: clamp(38px, 6vw, 72px);
      font-weight: 800; line-height: 1.15; margin: 0;
      color: var(--ctext1);
      animation: nit-fadeup 0.9s ease 0.1s both;
    }
    .nit-hero__title .nit-hero__n1 { color: var(--ctext3); }
    .nit-hero__title .nit-hero__n2 { color: var(--ctext1); }

    /* Description — X-Trade .hero-sub : clamp(1rem, 2vw, 1.25rem) = 16/20px.
       format_text() wraps this in its own <div>/<p> that carries the theme's
       default size, so force every descendant to the intended size. */
    .nit-hero__sub {
      max-width: 680px; margin: 1.5rem auto;
      animation: nit-fadeup 0.9s ease 0.25s both;
    }
    .nit-hero__sub,
    .nit-hero__sub * {
      font-size: clamp(16px, 2vw, 20px) !important;
      color: var(--ctext2);
      line-height: 1.8;
    }
    .nit-hero__sub p, .nit-hero__sub div { margin: 0; }

    /* Stats — X-Trade .hero-stats : gap 3rem, .stat-num 2.2rem, .stat-label 0.8rem */
    .nit-hero__stats {
      display: flex; gap: 48px; margin: 2.5rem 0;
      justify-content: center; flex-wrap: wrap;
      animation: nit-fadeup 0.9s ease 0.4s both;
    }
    .nit-hero__stat-num { font-size: 35px; font-weight: 800; color: var(--ctext3); display: block; line-height: 1; }
    .nit-hero__stat-label { font-size: 13px; color: var(--ctext2); font-weight: 500; }

    /* Buttons — reuse the site's .btn components (gallery.php); only size/shape
       here, colour + hover come from the theme's Bootstrap button tokens. */
    .nit-hero__btns {
      display: flex; gap: 16px; flex-wrap: wrap; justify-content: center;
      animation: nit-fadeup 0.9s ease 0.55s both;
    }
    .nit-hero__btns .btn {
      padding: 14px 40px; border-radius: 8px;
      font-size: 16px; font-weight: 700;
    }
  </style>
  <div class="nit-hero">
    <div class="nit-hero__grid"></div>
    <div class="nit-hero__glow-a"></div>
    <div class="nit-hero__glow-b"></div>

    <div class="nit-hero__inner">

      <!-- Category image (local_nit_category): only when this category really has one. -->
      <?php if ($hasrealimage): ?>
      <?php // $categoryname comes from format_string(), so it is already attribute-safe. ?>
      <img class="nit-hero__logo" src="<?= s($categoryimage) ?>" alt="<?= $categoryname ?>">
      <?php endif; ?>

      <!-- Badge: category name with pulsing dot, and this category's icon if it has one -->
      <div class="nit-hero__badge">
        <span class="nit-hero__badge-dot"></span>
        <?= local_nit_category_render_icon((int) $category->id, 'nit-cat-icon nit-cat-icon--badge', $categoryname) ?>
        <?= $categoryname ?>
      </div>

      <!-- H1: count in accent colour, subtitle with secondary accent -->
      <h1 class="nit-hero__title">
        <span class="nit-hero__n1"><?= $bannertotal ?> <?= $t('Training programs', 'برنامجًا تدريبيًا') ?></span><br>
        <span><?= $t('Diplomas and certificates', 'دبلومات وشهادات') ?></span> <span class="nit-hero__n2"><?= $t('professional', 'احترافية') ?></span>
      </h1>

      <!-- Description -->
      <?php if (trim(strip_tags($description)) !== ''): ?>
      <div class="nit-hero__sub"><?= $description ?></div>
      <?php endif; ?>

      <!-- Stats: floating flex, no border box -->
      <div class="nit-hero__stats">
        <div>
          <span class="nit-hero__stat-num"><?= $bannertotal ?></span>
          <span class="nit-hero__stat-label"><?= $t('Courses and diplomas', 'دورة ودبلوم') ?></span>
        </div>
        <div>
          <span class="nit-hero__stat-num"><?= count($subcategories) ?></span>
          <span class="nit-hero__stat-label"><?= $t('Main specializations', 'تخصص رئيسي') ?></span>
        </div>
        <div>
          <span class="nit-hero__stat-num">4</span>
          <span class="nit-hero__stat-label"><?= $t('Educational levels', 'مستويات تعليمية') ?></span>
        </div>
      </div>

      <!-- Buttons: the site's own .btn components. Each one scrolls to a section of this
           page; the plans / coupons buttons print only when their section does ($hasplans /
           $hascoupons above), so a button never points at nothing. -->
      <div class="nit-hero__btns">
        <a href="#<?= $exploretarget ?>" class="btn btn-primary" data-nit-scrollto="<?= $exploretarget ?>">
          <?= $t('Explore specializations', 'استكشف التخصصات') ?>
        </a>
        <?php if ($hasplans): ?>
        <a href="#nit-cat-plans" class="btn btn-outline-primary" data-nit-scrollto="nit-cat-plans">
          <?= $t('Flexible plans', 'خطط مرنة') ?>
        </a>
        <?php endif; ?>
        <?php if ($hascoupons): ?>
        <a href="#nit-cat-coupons" class="btn btn-outline-primary" data-nit-scrollto="nit-cat-coupons">
          <?= $t('Coupon plans', 'كوبونات الخصم') ?>
        </a>
        <?php endif; ?>
      </div>
      <script>
        (function() {
          /* The site's top bar is fixed (.navbar.fixed-top, 100px or taller when the logo
             needs it), so a plain scrollIntoView() lands the section's heading underneath
             it. Scroll to the section's top minus the bar's LIVE height instead — measured
             at click time, so a taller bar or a resized window is still right. */
          function navbarHeight() {
            var bar = document.querySelector('.navbar.fixed-top');
            return bar ? Math.ceil(bar.getBoundingClientRect().height) : 0;
          }
          function scrollTo(el) {
            var top = el.getBoundingClientRect().top + window.pageYOffset - navbarHeight() - 16;
            window.scrollTo({top: Math.max(0, top), behavior: 'smooth'});
          }
          /* The coupons block ships hidden and reveals itself once its feed answers, so a
             click that beats the feed would measure a section with no height. Wait for it
             to have one (a few seconds at most) rather than scrolling to the wrong place. */
          function whenVisible(el, tries) {
            if (el.offsetParent !== null || el.getClientRects().length) {
              scrollTo(el);
            } else if (tries > 0) {
              setTimeout(function() { whenVisible(el, tries - 1); }, 150);
            }
          }
          document.addEventListener('click', function(ev) {
            var btn = ev.target.closest('[data-nit-scrollto]');
            if (!btn) {
              return;
            }
            var el = document.getElementById(btn.getAttribute('data-nit-scrollto'));
            if (!el) {
              return;
            }
            ev.preventDefault();
            whenVisible(el, 30);
          });
        })();
      </script>

    </div>
  </div>

  <?php if ($whychoose): ?>
  <!-- "Why thousands choose us" (Figma frame 593:2017): two headings + description, then
       one numbered card per row of local_nit_cat_whycard. Colours are the page's brand
       slots: the red heading is the palette's Accent Words role, the squiggle its Accent
       Underline, the card corner strokes its Accent, the number badge border its Border. -->
  <style>
    .nit-why { padding: 56px 16px 40px; }
    .nit-why__inner { max-width: 1280px; margin: 0 auto; }
    .nit-why__head { text-align: center; max-width: 660px; margin: 0 auto 44px; }
    .nit-why__h1 {
      font-size: clamp(30px, 4vw, 48px); font-weight: 600; line-height: 1.3;
      color: var(--nit-brand-accentwords, var(--ctext3)); margin: 0;
    }
    .nit-why__h2wrap { display: inline-block; position: relative; padding-bottom: 16px; }
    .nit-why__h2 {
      font-size: clamp(22px, 3vw, 36px); font-weight: 600; line-height: 1.4;
      color: var(--ctext1); margin: 0;
    }
    /* The hand-drawn underline sits under the first word (the inline-start end). */
    .nit-why__squiggle {
      position: absolute; bottom: 0; inset-inline-start: 0;
      width: 177px; max-width: 60%; height: 10px; display: block;
      color: var(--nit-brand-accentunderline, var(--nit-brand-accentwords, var(--ctext3)));
    }
    .nit-why__desc {
      font-size: clamp(16px, 1.8vw, 22px); font-weight: 500; line-height: 1.45;
      color: var(--ctext2); margin: 14px auto 0; max-width: 630px;
    }

    /* Cards: a centred wrapping row, so any number of cards lays out — 4 across at the
       design width, 3/2 as the viewport narrows, orphans centred on the last row, one per
       row on a phone. */
    .nit-why__grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 21px; }
    .nit-why__card {
      position: relative; box-sizing: border-box;
      flex: 1 1 260px; max-width: 304px; min-height: 236px;
      padding: 36px 28px 30px;
      border-radius: 13px;
      background: color-mix(in srgb, var(--cbg2) 55%, transparent);
      box-shadow: 0 4px 4px rgba(0, 0, 0, 0.25);
      display: flex; flex-direction: column; align-items: flex-start; gap: 16px;
      text-align: start;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .nit-why__card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(0, 0, 0, 0.35); }
    /* The two accent strokes: one hugging the top inline-start corner, one the bottom
       inline-end corner (top-right and bottom-left in Arabic, mirrored in English). */
    .nit-why__card::before, .nit-why__card::after {
      content: ''; position: absolute; pointer-events: none;
      border: 0 solid var(--caccent);
    }
    .nit-why__card::before {
      top: 0; inset-inline-start: 0; width: 114px; height: 81px;
      border-top-width: 3px; border-inline-start-width: 3px;
      border-start-start-radius: 13px;
    }
    .nit-why__card::after {
      bottom: 0; inset-inline-end: 0; width: 114px; height: 99px;
      border-bottom-width: 3px; border-inline-end-width: 3px;
      border-end-end-radius: 13px;
    }
    .nit-why__num {
      width: 43px; height: 43px; box-sizing: border-box;
      border: 1px solid var(--cborder); border-radius: 13px;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 22px; font-weight: 600; color: var(--ctext1); line-height: 1;
      font-variant-numeric: tabular-nums;
    }
    /* A card with a picture shows it where the number would be. */
    .nit-why__img {
      width: 64px; height: 64px; box-sizing: border-box; padding: 4px;
      border: 1px solid var(--cborder); border-radius: 13px;
      object-fit: contain; display: block; background: color-mix(in srgb, var(--cbg2) 70%, transparent);
    }
    .nit-why__text { display: flex; flex-direction: column; gap: 4px; width: 100%; }
    .nit-why__title { font-size: 16px; font-weight: 600; color: var(--ctext1); margin: 0; line-height: 1.5; }
    .nit-why__body  { font-size: 14px; font-weight: 400; color: var(--ctext2); margin: 0; line-height: 1.65; }
    @media (max-width: 640px) {
      .nit-why { padding-top: 40px; }
      .nit-why__head { margin-bottom: 28px; }
      .nit-why__card { max-width: 100%; min-height: 0; }
    }
  </style>
  <section class="nit-why" dir="<?= $isar ? 'rtl' : 'ltr' ?>" aria-labelledby="nit-why-h1">
    <div class="nit-why__inner">

      <?php $wt = $whychoose['texts']; ?>
      <?php if ($wt['header1'] !== '' || $wt['header2'] !== '' || $wt['description'] !== ''): ?>
      <div class="nit-why__head">
        <?php if ($wt['header1'] !== ''): ?>
        <h2 class="nit-why__h1" id="nit-why-h1"><?= s($wt['header1']) ?></h2>
        <?php endif; ?>
        <?php if ($wt['header2'] !== ''): ?>
        <div class="nit-why__h2wrap">
          <h3 class="nit-why__h2"><?= s($wt['header2']) ?></h3>
          <svg class="nit-why__squiggle" viewBox="0 0 177 10" fill="none" preserveAspectRatio="none" aria-hidden="true">
            <path d="M2 6.5 C 28 1, 52 10, 80 5.5 S 132 1, 152 5 S 168 7, 175 3.5"
                  stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
          </svg>
        </div>
        <?php endif; ?>
        <?php if ($wt['description'] !== ''): ?>
        <p class="nit-why__desc"><?= s($wt['description']) ?></p>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($whychoose['cards'])): ?>
      <div class="nit-why__grid">
        <?php foreach ($whychoose['cards'] as $wc): ?>
        <article class="nit-why__card">
          <?php if ($wc['image'] !== ''): ?>
          <img class="nit-why__img" src="<?= s($wc['image']) ?>" alt="">
          <?php else: ?>
          <span class="nit-why__num" aria-hidden="true"><?= $wc['number'] ?></span>
          <?php endif; ?>
          <div class="nit-why__text">
            <?php if ($wc['title'] !== ''): ?>
            <h4 class="nit-why__title"><?= s($wc['title']) ?></h4>
            <?php endif; ?>
            <?php if ($wc['body'] !== ''): ?>
            <p class="nit-why__body"><?= nl2br(s($wc['body'])) ?></p>
            <?php endif; ?>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </div>
  </section>
  <?php endif; ?>

  <!-- Subcategory Filter Bar (All + children) -->
  <?php if (!empty($subcategories)): ?>
  <div id="nit-cat-filters" style="padding: 32px 16px 0;">
    <div style="max-width: 1200px; margin: 0 auto; display: flex; flex-wrap: wrap; justify-content: center; gap: 12px;">
      <?php
        $allurl = new moodle_url('/local/nit_category/index.php', ['id' => $categoryid]);
        echo $pill($allurl, $t('All', 'الكل'), $subid === 0);
        foreach ($subcategories as $sc) {
            $suburl = new moodle_url('/local/nit_category/index.php', ['id' => $categoryid, 'sub' => $sc->id]);
            $scname = $sc->get_formatted_name();
            echo $pill(
                $suburl,
                $scname,
                $subid === (int) $sc->id,
                local_nit_category_render_icon((int) $sc->id, 'nit-cat-icon nit-cat-icon--pill', $scname)
            );
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

  <?php
  // ── "Continue learning", narrowed to this category ────────────────────────────────────────
  //
  // The same block the home page uses, asked a narrower question: the courses this learner
  // already owns INSIDE this category. It is rendered from the theme's block file rather than
  // written out again here, so the card design has exactly one definition — see
  // local_nit_category_render_home_block(). The only things changed are the block's own data-*
  // attributes, which are the contract between a block and the page hosting it.
  //
  // data-empty="hide": on the home page a learner with nothing enrolled is invited to browse
  // the catalogue, but they are already standing in the catalogue here, so an empty section
  // simply stays away. The block hides itself until the feed answers, so there is never a gap.
  echo local_nit_category_render_home_block('home_my_course_block.html', [
      'data-nit-mycourse=""' => 'data-nit-mycourse="" data-category="' . (int) $categoryid . '"',
      'data-limit="2"'       => 'data-limit="3"',
      'data-empty="show"'    => 'data-empty="hide"',
      'data-viewall="/local/nit_category/mycourses.php"'
          => 'data-viewall="/local/nit_category/mycourses.php?categoryid=' . (int) $categoryid . '"',
  ], $context);
  ?>

  <!-- Courses Section (the "Explore specializations" landing spot on a leaf category) -->
  <div id="nit-cat-courses" style="padding: 32px 16px 16px;">
    <div style="max-width: 1200px; margin: 0 auto;">

      <?php
        // One card renderer, shared by every section. $sectionname is the category the
        // card lives under (its header), so the card can show that category's name.
        $rendercard = function (core_course_list_element $course, string $sectionname) use ($t, $nitcourseinfo, $nitpricetags, $nitcountrynotice) {
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

            // AC-4.5.17: the instructor's name links to their public profile where
            // they have one. The helper returns already-escaped HTML - a link, or a
            // plain name when there is nothing to link to - so the echo below must
            // not escape it a second time.
            $teacher    = function_exists('theme_nit_course_teacher_link')
                ? theme_nit_course_teacher_link((int) $course->id)
                : '';
            $info       = $nitcourseinfo($course->id);

            $detailsurl = $courseurl->out();
            $enrolurl   = (new moodle_url('/local/nit_subscriptions/enrol.php',
                ['courseid' => $course->id, 'sesskey' => sesskey()]))->out(false);
        ?>
        <!-- Course Card: fixed min-height + stretch grid => every card is the same size. -->
        <div style="background: var(--cbg2); border: 1px solid color-mix(in srgb, var(--cborder) 55%, transparent); border-radius: 16px; padding: 22px; display: flex; flex-direction: column; height: 100%; min-height: 320px; transition: box-shadow 0.3s ease;" onmouseover="this.style.boxShadow='0 12px 28px rgba(0,0,0,0.38)';" onmouseout="this.style.boxShadow='none';">

          <!-- Category name pill: rounded tint + circle icon (matches nested titles) -->
          <div class="nit-card-cat">
            <span class="nit-card-cat-dot"></span>
            <span><?= $sectionname ?></span>
          </div>

          <!-- Course name -->
          <h3 style="font-size: 18px; font-weight: bold; color: var(--ctext1); margin: 0 0 10px; line-height: 1.4;">
            <?= $coursename ?>
          </h3>

          <?php if ($teacher !== ''): ?>
          <div style="font-size: 12px; color: var(--ctext2); margin: 0 0 10px;">
            👤 <?= $teacher ?>
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
                <?php // Enrolled still shows what the course costs — the badge says they have it,
                      // the price says what it is worth. Free courses print nothing extra here. ?>
                <?= $nitpricetags($info) ?>
              <?php elseif ($info['purchased']): ?>
                <span style="display: inline-flex; align-items: center; gap: 5px; background: color-mix(in srgb, var(--csuccess) 16%, transparent); color: var(--csuccess); border: 1px solid color-mix(in srgb, var(--csuccess) 45%, transparent); font-size: 12px; font-weight: bold; padding: 4px 12px; border-radius: 50px;">
                  ✓ <?= $t('Purchased', 'تم الشراء') ?>
                </span>
                <?php // Same rule as "Enrolled": the badge says they own it, the price says
                      // what it is worth. ?>
                <?= $nitpricetags($info) ?>
              <?php elseif ($info['covered']): ?>
                <span style="display: inline-flex; align-items: center; gap: 5px; background: color-mix(in srgb, var(--caccent) 16%, transparent); color: var(--ctext3); border: 1px solid color-mix(in srgb, var(--caccent) 45%, transparent); font-size: 12px; font-weight: bold; padding: 4px 12px; border-radius: 50px;">
                  ★ <?= $t('In your subscription', 'ضمن اشتراكك') ?>
                </span>
                <?php // Subscription coverage hides the Buy button, not the price: the card
                      // still prints what the course costs on its own, so the value of the
                      // subscription stays visible. A priced course shows a price in EVERY state. ?>
                <?= $nitpricetags($info) ?>
              <?php elseif ($info['haspricing']): // Priced: offer tags, plain price, or — when no rule
                                                  // resolves to an amount — nothing rather than "Free". ?>
                <?= $nitpricetags($info) ?>
              <?php else: // Free course: the slot stays empty (reserved) so buttons stay put. ?>
                <span style="font-size: 13px; font-weight: bold; color: var(--csuccess);"><?= $t('Free', 'مجانًا') ?></span>
              <?php endif; ?>
            </div>

            <!-- Actions: gallery button components (.btn-primary / .btn-outline-primary).
                 Enrolled shows one button; every other state shows two. -->
            <div class="d-grid gap-2">
              <?php if ($info['enrolled'] || $info['purchased']): ?>
                <a href="<?= $detailsurl ?>" class="btn btn-outline-primary fw-bold"><?= $t('Course details', 'تفاصيل الكورس') ?></a>
              <?php elseif ($info['covered']): ?>
                <a href="<?= $enrolurl ?>" class="btn btn-primary fw-bold"><?= $t('Enroll', 'التحاق') ?></a>
                <a href="<?= $detailsurl ?>" class="btn btn-outline-primary fw-bold"><?= $t('Course details', 'تفاصيل الكورس') ?></a>
              <?php elseif (!empty($info['countryrequired']) && $nitcountrynotice): // No profile
                     // country: buying is refused server-side anyway, so offer the fix instead
                     // of a Buy button that can only fail. ?>
                <a href="<?= s($nitcountrynotice['url']) ?>" class="btn btn-primary fw-bold"><?= s($nitcountrynotice['action']) ?></a>
                <a href="<?= $detailsurl ?>" class="btn btn-outline-primary fw-bold"><?= $t('Course details', 'تفاصيل الكورس') ?></a>
              <?php elseif ($info['haspricing']): ?>
                <button type="button" class="btn btn-primary fw-bold" data-nit-buy-course
                  data-courseid="<?= (int) $course->id ?>" data-name="<?= s($coursename) ?>"
                  data-price="<?= s((string) $info['price']) ?>" data-currency="<?= s($info['currency']) ?>"><?= $t('Buy now', 'اشترِ الآن') ?></button>
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

        // Recursive section renderer: each category (at any depth) gets an X-Trade
        // style "specialty" title — pin icon + gradient text + a coloured start-border —
        // then its own course grid, then its child subcategories nested underneath with
        // the same title UI (indented to show the hierarchy).
        $rendernode = function (array $node, int $depth) use (&$rendernode, $rendercard, $counttree): void {
            $cat   = $node['cat'];
            $name  = $cat->get_formatted_name();
            $count = $counttree($node);
            $blockclass = 'nit-spec-block' . ($depth > 0 ? ' nit-spec-block--nested' : '');
            // What stands in for the generic pin/dot, best first: the category's own
            // icon, else its own image, else nothing (the pin/dot stays). Inheritance is
            // OFF on purpose — with it on, every subcategory of an imaged parent would
            // repeat the parent's artwork and the whole list would look identical.
            $seciconclass = 'nit-cat-icon ' . ($depth === 0 ? 'nit-cat-icon--spec' : 'nit-cat-icon--specsub');
            $secicon  = local_nit_category_render_icon((int) $cat->id, $seciconclass, $name);
            $secimage = $secicon === '' ? local_nit_category_get_image_url((int) $cat->id, false) : '';
        ?>
        <div class="<?= $blockclass ?>">
          <div class="nit-spec-head">
            <?php if ($depth === 0): ?>
            <!-- Top-level subcategory: image (or pin) + gradient text + coloured start-border. -->
            <h3 class="nit-spec-title">
              <?php if ($secicon !== ''): ?>
              <?= $secicon ?>
              <?php elseif ($secimage !== ''): ?>
              <img class="nit-spec-img" src="<?= s($secimage) ?>" alt="<?= $name ?>">
              <?php else: ?>
              <span class="nit-spec-pin">📌</span>
              <?php endif; ?>
              <span class="nit-spec-name"><?= $name ?></span>
              <span class="nit-spec-count">(<?= $count ?>)</span>
            </h3>
            <?php else: ?>
            <!-- Nested subcategory: rounded tint pill + image (or circle icon). -->
            <h3 class="nit-spec-title nit-spec-title--sub">
              <?php if ($secicon !== ''): ?>
              <?= $secicon ?>
              <?php elseif ($secimage !== ''): ?>
              <img class="nit-spec-img" src="<?= s($secimage) ?>" alt="<?= $name ?>">
              <?php else: ?>
              <span class="nit-spec-dot"></span>
              <?php endif; ?>
              <span class="nit-spec-subname"><?= $name ?></span>
              <span class="nit-spec-count">(<?= $count ?>)</span>
            </h3>
            <?php endif; ?>
          </div>

          <?php if (!empty($node['courses'])): ?>
          <div class="nit-spec-grid">
            <?php foreach ($node['courses'] as $course): ?>
              <?php $rendercard($course, $name); ?>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php if (!empty($node['children'])): ?>
          <div class="nit-spec-children">
            <?php foreach ($node['children'] as $child): ?>
              <?php if ($counttree($child) > 0): ?>
                <?php $rendernode($child, $depth + 1); ?>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php
        };
      ?>

      <style>
        /* The local_payments course_cards.js appends its own price badge to the end
           of every card with a /course/view.php link. This page already renders the
           price in its footer status row (above the buttons), so that injected badge
           is a redundant duplicate here — hide it (scoped to this page only, the
           shared badge is untouched everywhere else). */
        .nit-cat-details .lp-card-badge { display: none !important; }

        /* Top-level subcategory title — X-Trade .specialty-title, on brand vars. */
        .nit-spec-block { margin-bottom: 44px; }
        .nit-spec-head { margin-bottom: 22px; }
        .nit-spec-title {
          display: inline-flex; align-items: center; gap: 10px;
          margin: 0; font-size: 29px; font-weight: 800; line-height: 1.3;
          border-inline-start: 4px solid var(--cbg4);
          padding-inline-start: 14px;
        }
        .nit-spec-title .nit-spec-name {
          color: var(--ctext3);
        }
        .nit-spec-title .nit-spec-pin { font-size: 24px; line-height: 1; }
        .nit-spec-title .nit-spec-count { font-size: 15px; font-weight: 700; color: var(--ctext3); }
        /* A subcategory's own image, standing in for the pin when it has one. */
        .nit-spec-title .nit-spec-img {
          width: 40px; height: 40px; flex: 0 0 auto;
          object-fit: contain; border-radius: 10px;
          background: var(--cbg3); padding: 5px; box-sizing: border-box;
        }

        /* Nested subcategory title — rounded tint pill (50% of the accent) + circle
           icon instead of the pin; no gradient / start-border so it reads as a chip. */
        .nit-spec-title--sub {
          border-inline-start: none; padding: 8px 20px; border-radius: 50px;
          background: color-mix(in srgb, var(--caccent) 70%, transparent);
          font-size: 20px; color: var(--ctext1);
        }
        .nit-spec-title--sub .nit-spec-subname { color: var(--ctext1); }
        .nit-spec-title--sub .nit-spec-count { color: var(--ctext1); font-size: 14px; }
        .nit-spec-title--sub .nit-spec-dot {
          width: 12px; height: 12px; border-radius: 50%;
          background: var(--ctext1); flex: 0 0 auto;
        }
        /* Smaller inside the chip, and round to match the dot it replaces. */
        .nit-spec-title--sub .nit-spec-img {
          width: 28px; height: 28px; border-radius: 50%; padding: 3px;
          background: color-mix(in srgb, var(--ctext1) 15%, transparent);
        }

        .nit-spec-grid {
          display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
          gap: 24px; align-items: stretch;
        }
        /* Nested subcategory groups sit indented under their parent. */
        .nit-spec-children { margin-top: 28px; display: flex; flex-direction: column; gap: 8px; }
        .nit-spec-block--nested {
          margin-bottom: 32px;
          margin-inline-start: 20px; padding-inline-start: 16px;
          border-inline-start: 2px solid color-mix(in srgb, var(--cbg4) 18%, transparent);
        }

        /* Course-card category chip — same tint pill + circle icon as nested titles. */
        .nit-card-cat {
          align-self: flex-start; display: inline-flex; align-items: center; gap: 8px;
          background: color-mix(in srgb, var(--caccent) 70%, transparent);
          color: var(--ctext1); padding: 6px 14px; border-radius: 4px;
          font-size: 12px; font-weight: bold; margin-bottom: 16px;
        }
        .nit-card-cat-dot {
          width: 9px; height: 9px; border-radius: 50%;
          background: var(--ctext1); flex: 0 0 auto;
        }
      </style>

      <?php if (!empty($rootnodes)): ?>
        <?php foreach ($rootnodes as $node): ?>
          <?php $rendernode($node, 0); ?>
        <?php endforeach; ?>
      <?php else: ?>
      <div style="text-align: center; color: var(--ctext2); padding: 40px;">
        <?= $t('No courses found in this category.', 'لا توجد دورات في هذا التصنيف.') ?>
      </div>
      <?php endif; ?>

    </div>
  </div>

  <?php
  // ── This category's plans and coupons ─────────────────────────────────────────────────────
  //
  // Both are the home page's own blocks, rendered from the theme's files and handed the
  // category id — so a category advertises the plans that unlock ITS courses and the coupons
  // that can be spent on them, instead of the whole site's price list. What "belongs to this
  // category" means is decided server-side and in one place for each:
  // subscription_manager::matches_category() and discount_manager::matches_category().
  //
  // Each block prints only when $hasplans / $hascoupons (decided above, by the same rule its
  // feed applies) says it will have cards, so a category with no plans of its own simply ends
  // after the courses — and the hero's "Flexible plans" / "Coupon plans" buttons, which exist
  // under the same condition, always have a section to scroll to. The id each block is given
  // here is that scroll target.
  if ($hasplans) {
      echo local_nit_category_render_home_block('home_subscriptions_block.html', [
          'data-nit-subs=""' => 'id="nit-cat-plans" data-nit-subs="" data-category="' . (int) $categoryid . '"',
      ], $context);
  }

  if ($hascoupons) {
      echo local_nit_category_render_home_block('home_coupons_block.html', [
          'data-nit-coupons=""' => 'id="nit-cat-coupons" data-nit-coupons="" data-category="' . (int) $categoryid . '"',
      ], $context);
  }
  ?>
</div>

<?php
// The subscriptions block moves its confirm dialog to <body> so it can sit above the page.
// That used to take it out of the wrapper carrying this category's Brand Colors group, and a
// script had to put the class back on it. Nothing to put back now: the group's switch class
// lives on <html>, and <body> is inside it, so the dialog is already in the right palette
// wherever it is moved to — and it follows the light/dark button like the rest of the page.

// NIT: wire the course Buy buttons to the shared checkout modal (coupon + auto offer -> Kashier).
// Shared with the catalogue page so a Buy button behaves identically on both.
local_nit_category_checkout_footer();

echo $OUTPUT->footer();
