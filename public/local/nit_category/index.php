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

$categoryid = required_param('id', PARAM_INT);      // The category this page is about.
$subid      = optional_param('sub', 0, PARAM_INT);  // Initial filter: 0 = "All", else one direct child.

// The category.
$category = core_course_category::get($categoryid, MUST_EXIST);
$context  = $category->get_context();

// Direct subcategories -> the clickable filter bar.
$subcategories = $category->get_children();

// `sub` names a direct child to start filtered to. The filter itself is client-side —
// every subtree is rendered and the bar shows/hides them without a round trip — so this
// only decides what is visible on first paint (and keeps old ?sub= links working).
if ($subid && !isset($subcategories[$subid])) {
    $subid = 0; // Unknown sub id -> behave as "All".
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
} else {
    // Courses that live directly under the category (not inside any child) get their own
    // section first so nothing is dropped, then every subcategory subtree. All of them are
    // rendered whatever `sub` says: the filter bar hides the others client-side.
    $directcourses = $fetchcourses($category, false);
    if (!empty($directcourses)) {
        $rootnodes[] = ['cat' => $category, 'courses' => $directcourses, 'children' => []];
    }
    foreach ($subcategories as $sc) {
        $rootnodes[] = $buildnode($sc);
    }
}

// Drop empty subtrees.
$rootnodes = array_values(array_filter($rootnodes, static fn($n) => $counttree($n) > 0));

// ── Course levels ─────────────────────────────────────────────────────────────────────────
//
// The third tier of the view. A category holds sub-categories, a sub-category holds
// courses, and every course sits on a rung of the site's Level ladder (the "Level" field
// under "Course File Summary" on the course settings form). The page groups each
// category's courses rung by rung and prints the rung on every card. Read once here for
// every course on the page.
$collectids = function (array $node) use (&$collectids): array {
    $ids = array_map(static fn($c) => (int) $c->id, array_values($node['courses']));
    foreach ($node['children'] as $child) {
        $ids = array_merge($ids, $collectids($child));
    }
    return $ids;
};
$allcourseids = [];
foreach ($rootnodes as $node) {
    $allcourseids = array_merge($allcourseids, $collectids($node));
}
$courselevels = \local_nit_category\course_level::for_courses($allcourseids);
$ladder       = \local_nit_category\course_level::ladder();   // rung => label (select field)
$levelmax     = count($ladder);                                // 0 = a text field / no ladder

// Every level a course on this page carries, keyed the way the filter bar and the URL
// name it: a select field's rung number, or — for a text field, whose labels have no
// order — a running number in alphabetical order. A select field lists EVERY rung of the
// ladder, holding one or not, so the bar always reads as the same ladder on every page.
$levelkeys = [];   // label => key
$levelinfo = [];   // key => ['index' => rung, 'label' => label, 'count' => n]
if ($levelmax > 0) {
    foreach ($ladder as $index => $label) {
        $levelkeys[$label] = (string) $index;
        $levelinfo[(string) $index] = ['index' => $index, 'label' => $label, 'count' => 0];
    }
} else {
    $labels = array_values(array_unique(array_map(static fn($l) => $l['label'], $courselevels)));
    usort($labels, [\local_nit_category\text_util::class, 'collate']);
    foreach ($labels as $i => $label) {
        $levelkeys[$label] = (string) ($i + 1);
        $levelinfo[(string) ($i + 1)] = ['index' => $i + 1, 'label' => $label, 'count' => 0];
    }
}
foreach ($courselevels as $lv) {
    $key = $levelkeys[$lv['label']] ?? null;
    if ($key !== null) {
        $levelinfo[$key]['count']++;
    }
}

// The level a course is on, as the page keys it — 'none' when it has no level.
$levelkeyof = function (int $courseid) use ($courselevels, $levelkeys): string {
    $lv = $courselevels[$courseid] ?? null;
    return $lv ? ($levelkeys[$lv['label']] ?? 'none') : 'none';
};


// The rungs the courses of a subtree stand on, lowest first (ladder fields only — a text
// field's labels have no rungs). Feeds the "Levels 1–3" summary under a section title.
$collectrungs = function (array $node) use (&$collectrungs, $courselevels, $levelmax): array {
    if ($levelmax <= 0) {
        return [];
    }
    $rungs = [];
    foreach ($node['courses'] as $course) {
        $lv = $courselevels[(int) $course->id] ?? null;
        if ($lv && $lv['index'] > 0) {
            $rungs[$lv['index']] = true;
        }
    }
    foreach ($node['children'] as $child) {
        foreach ($collectrungs($child) as $r) {
            $rungs[$r] = true;
        }
    }
    ksort($rungs);
    return array_keys($rungs);
};

// A course's level as a little meter: rung N of the ladder, lit up to N. Nothing for a
// text-field level (no ladder to climb) or a course with no level.
//
// Drawn as the design's "signal" glyph (Figma "No Connection"): one outlined, rounded bar
// per rung, rising left to right; the bars up to N are filled. A 24px box whatever the
// ladder's length, so a 4-rung ladder just gets narrower bars.
$levelmeter = function (int $index) use ($levelmax): string {
    if ($levelmax <= 0 || $index <= 0) {
        return '';
    }
    $gap = 2.5;
    $w = (20 - ($levelmax - 1) * $gap) / $levelmax;
    $bars = '';
    for ($i = 1; $i <= $levelmax; $i++) {
        $h = $levelmax === 1 ? 16 : 8 + 12 * ($i - 1) / ($levelmax - 1);
        $x = 2 + ($i - 1) * ($w + $gap);
        $bars .= sprintf('<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="1.2"%s/>',
            $x + 0.6, 21.4 - $h, $w - 1.2, $h, $i <= $index ? ' fill="currentColor"' : '');
    }
    return '<span class="nit-lvl-meter" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24"'
        . ' fill="none" stroke="currentColor" stroke-width="1.6">' . $bars . '</svg></span>';
};

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
//
// --ctext4 is the ink drawn ON a --cbg4 (Primary) fill, and is the palette's own
// "Text on main button" role — the pair .btn-primary uses. It is NOT Text primary:
// that is the ink for the page surface, and a light group's surface ink is near-black
// while its primary fill is a dark blue, so the two land on each other unreadably.
$stylevars =
    '--cbg1: var(--nit-brand-background); '
  . '--cbg2: var(--nit-brand-surface); '
  . '--cbg3: color-mix(in srgb, var(--nit-brand-surface) 88%, var(--nit-brand-textprimary)); '
  . '--cbg4: var(--nit-brand-primary); '
  . '--ctext1: var(--nit-brand-textprimary); '
  . '--ctext2: var(--nit-brand-textsecondary); '
  . '--ctext3: var(--nit-brand-accenttext); '
  . '--caccent: var(--nit-brand-accent); '
  . '--ctext4: var(--nit-brand-onprimary); '
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

// The hero's three buttons may wear their own colours, apart from the rest of the
// group: theme_nit config `nit_hero_buttons` = {"g6": {"primary": "#3e8541", …}, …},
// written by theme/nit/cli/apply_figma_palette.php --hero. Each group's set re-declares
// the --nit-brand-* button roles on .nit-hero__btns only, keyed on the group's <html>
// class, so the light/dark switch still moves between the category's two sets. No row
// (or --hero-remove) means the hero buttons follow the group like every other button.
$herobtncss = '';
$herobtns = json_decode((string) get_config('theme_nit', 'nit_hero_buttons'), true);
if (is_array($herobtns) && function_exists('theme_nit_brand_group_class')) {
    foreach ($herobtns as $gkey => $roles) {
        $groupclass = theme_nit_brand_group_class((string) $gkey);
        if ($groupclass === '' || !is_array($roles)) {
            continue;
        }
        $decl = '';
        foreach ($roles as $role => $hex) {
            if (preg_match('/^[a-z0-9]+$/', (string) $role) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $hex)) {
                $decl .= "--nit-brand-{$role}: {$hex}; ";
            }
        }
        if ($decl !== '') {
            $herobtncss .= "html.{$groupclass} .nit-hero__btns { {$decl}}\n";
        }
    }
}

// Bilingual inline helper (site is en/ar); mirrors the theme's {mlang} pairs.
$isar = (strpos(current_language(), 'ar') === 0);
$t = function (string $en, string $ar) use ($isar) {
    return $isar ? $ar : $en;
};
// "3 courses" in either language. English is one/many; Arabic has its own count forms
// (one, two, 3–10, 11+), and for one and two the number lives in the word itself.
$ncount = function (int $n, array $en, array $ar) use ($isar): string {
    if (!$isar) {
        return $n . ' ' . ($n === 1 ? $en[0] : $en[1]);
    }
    if ($n === 1) {
        return $ar[0];
    }
    if ($n === 2) {
        return $ar[1];
    }
    return $n . ' ' . ($n <= 10 ? $ar[2] : $ar[3]);
};

// "Why thousands choose us" — the section under the hero. Site-wide content the admin
// edits on the "Why choose us" tab of the Site pages manager
// (/local/profilefields/manage.php?tab=whychoose); null when there is nothing to show.
$whychoose = \local_nit_category\whychoose::for_display();

// Subcategory filter buttons reuse the site's gallery button components (Components
// tab): the active filter is a solid .btn-primary, the rest are .btn-outline-primary.
// $icon is the category's own icon HTML (or '' for the "All" button, which is not a
// category); it prints inside the button, before the label.
//
// Each pill is still a real link (?sub=ID), so it works without JavaScript and can be
// opened in a new tab; with JavaScript the click is intercepted and the filter happens in
// place — the sections are all on the page already, the bar only shows and hides them.
// data-nit-sub carries the child id (0 = All) that the script keys on.
$pill = function (moodle_url $url, string $label, bool $active, int $subid, string $icon = ''): string {
    $cls = $active ? 'btn btn-primary' : 'btn btn-outline-primary';
    return '<a href="' . $url->out() . '" class="' . $cls . ' fw-bold nit-cat-pill" data-nit-sub="' . $subid . '"'
        . ' aria-pressed="' . ($active ? 'true' : 'false') . '">'
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
// "Free"). One helper because the same tags print in every card state that has a price — next
// to the "Purchased" and "In your subscription" badges and above the "Buy now" button — so no
// two states can drift apart. The one state that prints no price is "Enrolled": the learner
// already has the course, so there is nothing to quote.
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
      /* Figma "الوجيستيات": the hero is 491px tall, straight under the bar. It grows
         past that only when its copy needs the room (a long description, a phone). */
      min-height: 491px;
      display: flex; align-items: center; justify-content: center; flex-direction: column;
      text-align: center;
      padding: 48px 5%;
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
      font-size: 18px; font-weight: 500 !important; /* beats the pill's own .fw-bold */
    }

    /* Category image — IS the section's backdrop (.nit-hero--art), not a tile above the
       badge. One full-bleed copy of the picture, `cover`-cropped to the section like a
       banner, and a scrim in the surface colour rising from the start side and the
       bottom, so the copy sits on solid ground while the picture stays whole on the
       end side and the section melts into the page below. Decorative (alt="") — the
       badge already names the category. An <img>, not CSS url(), so the file name never
       has to survive CSS-string escaping. */
    .nit-hero--art {
      min-height: 491px;
      align-items: flex-start;
      text-align: start;
      padding-inline: 6%;
    }
    .nit-hero__art, .nit-hero__scrim {
      position: absolute; inset: 0; pointer-events: none; user-select: none;
    }
    .nit-hero__art { z-index: 0; animation: nit-fadedown 1s ease both; }
    .nit-hero__art img {
      display: block; width: 100%; height: 100%;
      object-fit: cover; object-position: center;
    }
    /* The scrim is BLACK whatever the mode — the same one the course page's hero
       wears (theme_nit _coursepage.scss --cr-scrim): a photograph under a dark veil is
       a surface whose luminance is fixed, so its ink is fixed too (white), and the
       section reads the same in light and dark. The brand only supplies the accents.
       Solid on the text side, clearing to the picture on the other; a lighter band
       along the bottom keeps the buttons and any print baked into the banner apart. */
    .nit-hero--art {
      --hero-ink: #fff;
      --hero-muted: rgba(255, 255, 255, .74);
      --hero-scrim:
        linear-gradient(90deg,
          rgba(0, 0, 0, .84) 0%,
          rgba(0, 0, 0, .84) 34%,
          rgba(0, 0, 0, .48) 52%,
          rgba(0, 0, 0, .14) 72%,
          rgba(0, 0, 0, .06) 100%),
        linear-gradient(0deg,
          rgba(0, 0, 0, .45) 0%,
          transparent 40%);
      color: var(--hero-ink);
    }
    .nit-hero__scrim { inset: 0; background: var(--hero-scrim); }
    /* Gradients have no logical direction: flip the drawn scrim as a whole for RTL. */
    .nit-hero--art[dir="rtl"] .nit-hero__scrim { transform: scaleX(-1); }
    /* Ink on the veil: fixed white where the mode palette would otherwise pick a dark
       Text role in light mode. The whole H1 — count and subtitle — is one colour, set
       on the heading; the spans inherit it. */
    .nit-hero--art .nit-hero__title,
    .nit-hero--art .nit-hero__stat-num { color: var(--hero-ink); }
    .nit-hero--art .nit-hero__sub,
    .nit-hero--art .nit-hero__sub * { color: var(--hero-muted); }
    .nit-hero--art .nit-hero__stat-label { color: var(--hero-ink); }
    /* Figma: a frosted tag over the picture — 8% white fill, a 4% white hairline. */
    .nit-hero--art .nit-hero__badge {
      background: rgba(255, 255, 255, .08);
      border-color: rgba(255, 255, 255, .04);
      color: var(--hero-ink);
    }
    /* The copy column: start-aligned on the solid side of the scrim, capped so the
       title wraps at three or four words a line and never runs under the picture. */
    .nit-hero--art .nit-hero__inner { margin: 0; max-width: min(680px, 56%); }
    .nit-hero--art .nit-hero__title { font-size: clamp(26px, 2.5vw, 36px); max-width: 24ch; }
    .nit-hero--art .nit-hero__sub { margin-inline: 0; max-width: 560px; }
    .nit-hero--art .nit-hero__stats { justify-content: flex-start; margin: 1.5rem 0 1.25rem; }
    .nit-hero--art .nit-hero__btns { justify-content: flex-start; }
    @media (max-width: 900px) {
      /* One column: the picture stays full-bleed behind the copy, and the scrim becomes
         a top-to-bottom fade — clear at the top so the banner shows, solid where the
         text sits — so it stays legible over whichever part of the picture shows. */
      .nit-hero--art { padding-inline: 5%; min-height: 0; }
      .nit-hero--art .nit-hero__inner { max-width: 100%; }
      .nit-hero--art {
        --hero-scrim: linear-gradient(180deg,
          rgba(0, 0, 0, .40) 0%,
          rgba(0, 0, 0, .74) 34%,
          rgba(0, 0, 0, .82) 100%);
      }
    }
    @media (prefers-reduced-motion: reduce) {
      .nit-hero *, .nit-hero__grid { animation: none !important; }
    }

    /* Badge — Figma: a 42px tag with 12px corners (not a pill), 22px Medium. */
    .nit-hero__badge {
      display: inline-flex; align-items: center; gap: 0.5rem;
      background: color-mix(in srgb, var(--cbg4) 12%, transparent);
      border: 1px solid color-mix(in srgb, var(--cbg4) 30%, transparent);
      border-radius: 12px; min-height: 42px; padding: 0 16px; box-sizing: border-box;
      font-size: clamp(16px, 1.6vw, 22px); color: var(--ctext3); font-weight: 500;
      margin-bottom: 1.5rem;
      animation: nit-fadedown 0.8s ease both;
    }
    .nit-hero__badge-dot {
      width: 8px; height: 8px; background: var(--csuccess); border-radius: 50%;
      animation: nit-hpulse 2s infinite; flex-shrink: 0;
    }

    /* H1 — Figma "الوجيستيات": 36px Bold at the 1440 design width, 26px on a phone. */
    .nit-hero__title {
      font-size: clamp(26px, 2.5vw, 36px);
      font-weight: 700; line-height: 1.5; margin: 0;
      color: var(--ctext1);
      animation: nit-fadeup 0.9s ease 0.1s both;
    }
    /* The count line ("21 برنامجًا تدريبيًا") wears the Accent Words role — the same
       colour as "لماذا يختار الآلاف" below — and the rest of the heading its ink. */
    .nit-hero__title .nit-hero__n2 { color: inherit; }
    .nit-hero__title .nit-hero__n1,
    .nit-hero--art .nit-hero__title .nit-hero__n1 { color: var(--nit-brand-accentwords, var(--ctext3)); }

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

    /* Stats — Figma: 157px columns 16px apart, number over label, both centred, each
       column closed by a 1px slate rule on its inline-end side (left in Arabic). */
    .nit-hero__stats {
      display: flex; gap: 16px; margin: 2rem 0;
      justify-content: center; flex-wrap: wrap;
      animation: nit-fadeup 0.9s ease 0.4s both;
    }
    .nit-hero__stats > div {
      min-width: 157px; box-sizing: border-box; padding: 4px 12px;
      text-align: center;
      border-inline-end: 1px solid #64748b;
    }
    /* A divider BETWEEN columns only — nothing after the last one. */
    .nit-hero__stats > div:last-child { border-inline-end: 0; }
    .nit-hero__stat-num { font-size: clamp(28px, 2.5vw, 36px); font-weight: 500; color: var(--ctext3); display: block; line-height: 1.2; }
    .nit-hero__stat-label { font-size: 16px; color: var(--ctext2); font-weight: 500; }

    /* Buttons — the site's own .btn components, as in the Figma hero: "Explore
       specializations" is the Main button (.btn-primary), "Flexible plans" and "Coupon
       plans" the Outline button (brand) (.btn-outline-primary) with the design's 2px
       ring. Only size and shape are set here; every colour — fill, label, ring, and the
       three again under the cursor or pressed — is those blocks' roles in the group's
       Brand Colors editor, wired in theme_nit scss/foundation/_brand.scss. The course,
       plan and coupon cards below use the same two buttons, so an administrator
       recolours every button on this page from those two blocks and nothing else.
       Nothing is pinned any more (neither a ghost fill for the veil nor the hero ink as
       a label): a colour an admin cannot reach from the editor is exactly what this
       page must not carry. A label that has to be white over the picture is the
       block's "Text on main button" / "Brand outline button text" card, set there. */
    .nit-hero__btns {
      display: flex; gap: 16px; flex-wrap: wrap; justify-content: center;
      animation: nit-fadeup 0.9s ease 0.55s both;
    }
    .nit-hero__btns .btn {
      padding: 12px 32px; border-radius: 8px;
      font-size: 18px; font-weight: 500;
    }
    .nit-hero__btns .btn-outline-primary { border-width: 2px; padding: 11px 31px; }
<?= $herobtncss ?>
  </style>
  <div class="nit-hero<?= $hasrealimage ? ' nit-hero--art' : '' ?>" dir="<?= $isar ? 'rtl' : 'ltr' ?>">
    <?php if ($hasrealimage): ?>
    <!-- Category image (local_nit_category) as the section's backdrop: full-bleed cover,
         scrim on top. Only when this category really has one — the site logo is never
         a backdrop. -->
    <div class="nit-hero__art" aria-hidden="true"><img src="<?= s($categoryimage) ?>" alt=""></div>
    <div class="nit-hero__scrim"></div>
    <?php else: ?>
    <div class="nit-hero__grid"></div>
    <div class="nit-hero__glow-a"></div>
    <div class="nit-hero__glow-b"></div>
    <?php endif; ?>

    <div class="nit-hero__inner">

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
        <?php if (!empty($subcategories)): ?>
        <div>
          <span class="nit-hero__stat-num"><?= count($subcategories) ?></span>
          <span class="nit-hero__stat-label"><?= $t('Main specializations', 'تخصص رئيسي') ?></span>
        </div>
        <?php endif; ?>
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
      font-size: clamp(30px, 3.4vw, 48px); font-weight: 700; line-height: 1.3;
      color: var(--nit-brand-accentwords, var(--ctext3)); margin: 0;
    }
    .nit-why__h2wrap { display: inline-block; position: relative; padding-bottom: 16px; }
    .nit-why__h2 {
      font-size: clamp(22px, 2.5vw, 36px); font-weight: 600; line-height: 1.4;
      color: var(--ctext1); margin: 0;
    }
    /* The hand-drawn underline sits under the first word (the inline-start end). */
    .nit-why__squiggle {
      position: absolute; bottom: 0; inset-inline-start: 0;
      width: 177px; max-width: 60%; height: 10px; display: block;
      color: var(--nit-brand-accentunderline, var(--nit-brand-accentwords, var(--ctext3)));
    }
    .nit-why__desc {
      font-size: clamp(16px, 1.6vw, 22px); font-weight: 500; line-height: 1.4;
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
    .nit-why__body  { font-size: 14px; font-weight: 500; color: var(--ctext2); margin: 0; line-height: 1.65; }
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

  <!-- Subcategory Filter Bar (All + children). Filters in place — see the script below. -->
  <?php if (!empty($subcategories)): ?>
  <div id="nit-cat-filters" style="padding: 32px 16px 0;" data-nit-catfilter data-category="<?= (int) $categoryid ?>">
    <div style="max-width: 1200px; margin: 0 auto; display: flex; flex-wrap: wrap; justify-content: center; gap: 12px;">
      <?php
        $allurl = new moodle_url('/local/nit_category/index.php', ['id' => $categoryid]);
        echo $pill($allurl, $t('All', 'الكل'), $subid === 0, 0);
        foreach ($subcategories as $sc) {
            $suburl = new moodle_url('/local/nit_category/index.php', ['id' => $categoryid, 'sub' => $sc->id]);
            $scname = $sc->get_formatted_name();
            echo $pill(
                $suburl,
                $scname,
                $subid === (int) $sc->id,
                (int) $sc->id,
                local_nit_category_render_icon((int) $sc->id, 'nit-cat-icon nit-cat-icon--pill', $scname)
            );
        }
      ?>
    </div>

    <?php
      // Every subcategory's description is on the page from the start, hidden; the filter
      // reveals the chosen one. Nothing is fetched when a pill is clicked.
      foreach ($subcategories as $sc) {
          $subdescription = format_text($sc->description, $sc->descriptionformat, ['context' => $sc->get_context()]);
          if (trim(strip_tags($subdescription)) === '') {
              continue;
          }
          $shown = $subid === (int) $sc->id;
          echo '<div class="nit-cat-subdesc" data-nit-subdesc="' . (int) $sc->id . '"' . ($shown ? '' : ' hidden')
              . ' style="max-width: 900px; margin: 24px auto 0; text-align: center; color: var(--ctext2); font-size: 16px; font-weight: 500; line-height: 1.7;">'
              . $subdescription . '</div>';
      }
    ?>
  </div>
  <?php endif; ?>

  <!-- Courses Section (the "Explore specializations" landing spot on a leaf category) -->
  <div id="nit-cat-courses" style="padding: 32px 16px 16px;">
    <div style="max-width: 1280px; margin: 0 auto;">

      <?php
        // One card renderer, shared by every section. The card itself (picture, titles,
        // stats) is local_nit_category\course_card — the catalogue prints the same one —
        // and only the footer, which needs this page's price helpers, is written here. The
        // section heading already names the category, so the card does not repeat it.
        $rendercard = function (core_course_list_element $course) use ($t, $nitcourseinfo, $nitpricetags, $nitcountrynotice) {
            $coursename = $course->get_formatted_name();

            // AC-4.5.17: the instructor's name links to their public profile where
            // they have one. The helper returns already-escaped HTML - a link, or a
            // plain name when there is nothing to link to - so it is passed on as HTML.
            $teacher    = function_exists('theme_nit_course_teacher_link')
                ? theme_nit_course_teacher_link((int) $course->id)
                : '';
            $info       = $nitcourseinfo($course->id);

            $detailsurl = (new moodle_url('/course/view.php', ['id' => $course->id]))->out();
            $enrolurl   = (new moodle_url('/local/nit_subscriptions/enrol.php',
                ['courseid' => $course->id, 'sesskey' => sesskey()]))->out(false);

            echo \local_nit_category\course_card::open($course, ['teacherhtml' => $teacher]);
        ?>
          <!-- Footer: pinned to the bottom. A fixed-height status/price row sits above the
               buttons so the buttons never move — a free course simply leaves it empty,
               a paid course shows its price in the SAME reserved slot. -->
          <div class="nitcc__foot">
            <div class="nitcc__status">
              <?php if ($info['enrolled']): ?>
                <span style="display: inline-flex; align-items: center; gap: 5px; background: color-mix(in srgb, var(--csuccess) 16%, transparent); color: var(--csuccess); border: 1px solid color-mix(in srgb, var(--csuccess) 45%, transparent); font-size: 12px; font-weight: bold; padding: 4px 12px; border-radius: 50px;">
                  ✓ <?= $t('Enrolled', 'مُسجَّل') ?>
                </span>
                <?php // No price beside "Enrolled": the learner already has the course, and a
                      // number next to that badge reads as something still owed. The slot keeps
                      // its reserved height, so the buttons below do not move. ?>
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

            <!-- Actions: the gallery's two button components and no other — the Main button
                 (.btn-primary) for the one thing to do next and the Outline button (brand)
                 (.btn-outline-primary) for "Course details" — so a card's buttons are the hero's
                 and the plan / coupon cards' buttons, recoloured from the same two blocks
                 of the Brand Colors editor. Enrolled shows one button; every other state
                 shows two. -->
            <div class="nitcc__actions">
              <?php if ($info['enrolled'] || $info['purchased']): ?>
                <a href="<?= $detailsurl ?>" class="btn btn-outline-primary"><?= $t('Course details', 'تفاصيل الكورس') ?></a>
              <?php elseif ($info['covered']): ?>
                <a href="<?= $enrolurl ?>" class="btn btn-primary"><?= $t('Enroll', 'التحاق') ?></a>
                <a href="<?= $detailsurl ?>" class="btn btn-outline-primary"><?= $t('Course details', 'تفاصيل الكورس') ?></a>
              <?php elseif (!empty($info['countryrequired']) && $nitcountrynotice): // No profile
                     // country: buying is refused server-side anyway, so offer the fix instead
                     // of a Buy button that can only fail. ?>
                <a href="<?= s($nitcountrynotice['url']) ?>" class="btn btn-primary"><?= s($nitcountrynotice['action']) ?></a>
                <a href="<?= $detailsurl ?>" class="btn btn-outline-primary"><?= $t('Course details', 'تفاصيل الكورس') ?></a>
              <?php elseif ($info['haspricing']): ?>
                <button type="button" class="btn btn-primary" data-nit-buy-course
                  data-courseid="<?= (int) $course->id ?>" data-name="<?= s($coursename) ?>"
                  data-price="<?= s((string) $info['price']) ?>" data-currency="<?= s($info['currency']) ?>"><?= $t('Buy now', 'اشترِ الآن') ?></button>
                <a href="<?= $detailsurl ?>" class="btn btn-outline-primary"><?= $t('Course details', 'تفاصيل الكورس') ?></a>
              <?php else: // Free course. ?>
                <a href="<?= $enrolurl ?>" class="btn btn-primary"><?= $t('Enroll', 'التحاق') ?></a>
                <a href="<?= $detailsurl ?>" class="btn btn-outline-primary"><?= $t('Course details', 'تفاصيل الكورس') ?></a>
              <?php endif; ?>
            </div>
          </div>
        <?= \local_nit_category\course_card::close() ?>
        <?php
        };

        // Recursive section renderer: each category (at any depth) gets an X-Trade
        // style "specialty" title — pin icon + gradient text + a coloured start-border —
        // then its own course grid, then its child subcategories nested underneath with
        // the same title UI (indented to show the hierarchy). Titles are headings, not
        // links: a subcategory is browsed here, under its parent, never on a page of
        // its own.
        //
        // A top-level block carries data-nit-block=<category id> for the filter bar, and
        // starts hidden when the page opened filtered to a different child (?sub=).
        $rendernode = function (array $node, int $depth) use (&$rendernode, $rendercard, $counttree, $collectrungs,
                $subid, $t, $ncount, $levelkeyof, $levelinfo, $levelmeter, $levelmax): void {
            $cat   = $node['cat'];
            $name  = $cat->get_formatted_name();
            $count = $counttree($node);
            $blockclass = 'nit-spec-block' . ($depth > 0 ? ' nit-spec-block--nested' : '');
            // A top-level block carries data-nit-block=<category id> for the filter bar, and
            // starts hidden when the page opened filtered to a different child (?sub=).
            $blockattrs = '';
            if ($depth === 0) {
                $blockattrs = ' data-nit-block="' . (int) $cat->id . '"'
                    . (($subid && (int) $cat->id !== $subid) ? ' hidden' : '');
            }

            // The node's own courses, rung by rung: lowest level first, then the courses
            // with no level. A node whose courses carry no level at all keeps a plain grid;
            // as soon as one of them has a level, every group gets its rung header so the
            // reader can see the ladder (and where a course without a level falls off it).
            $groups = [];
            foreach ($node['courses'] as $course) {
                $key = $levelkeyof((int) $course->id);
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'key'     => $key,
                        'index'   => $key === 'none' ? PHP_INT_MAX : ($levelinfo[$key]['index'] ?? PHP_INT_MAX),
                        'label'   => $key === 'none' ? $t('Other courses', 'دورات أخرى') : ($levelinfo[$key]['label'] ?? ''),
                        'courses' => [],
                    ];
                }
                $groups[$key]['courses'][] = $course;
            }
            uasort($groups, static fn($a, $b) => $a['index'] <=> $b['index']);
            $showrungs = count($groups) > 1 || (count($groups) === 1 && !isset($groups['none']));

            // The one-line structure summary under a top-level title: how many
            // sub-categories and courses sit inside, and which rungs of the ladder they
            // span — so the shape of a specialization is readable before scrolling it.
            $subcount = count($node['children']);
            $rungs = $collectrungs($node);
            $levelspan = '';
            if (!empty($rungs)) {
                $levelspan = count($rungs) === 1
                    ? $t('Level', 'المستوى') . ' ' . $rungs[0]
                    : $t('Levels', 'المستويات') . ' ' . $rungs[0] . '–' . end($rungs);
            }
            // What stands in for the generic pin/dot, best first: the category's own
            // icon, else its own image, else nothing (the pin/dot stays). Inheritance is
            // OFF on purpose — with it on, every subcategory of an imaged parent would
            // repeat the parent's artwork and the whole list would look identical.
            $seciconclass = 'nit-cat-icon nit-cat-icon--spec'; // same title at every depth
            $secicon  = local_nit_category_render_icon((int) $cat->id, $seciconclass, $name);
            $secimage = $secicon === '' ? local_nit_category_get_image_url((int) $cat->id, false) : '';
        ?>
        <div class="<?= $blockclass ?>"<?= $blockattrs ?>>
          <div class="nit-spec-head">
            <!-- Category title, the same at every depth — a sub-sub-category is a category
                 too. One row, as in the design: the icon in an outlined box (its own icon,
                 else its image, else a generic glyph), the name, then "( courses · levels )".
                 Depth shows in the indent and the rail, not in a different kind of heading. -->
            <h3 class="nit-spec-title">
              <span class="nit-spec-iconbox">
                <?php if ($secicon !== ''): ?>
                <?= $secicon ?>
                <?php elseif ($secimage !== ''): ?>
                <img class="nit-spec-img" src="<?= s($secimage) ?>" alt="">
                <?php else: ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 8-4 4 4 4M15 8l4 4-4 4"/></svg>
                <?php endif; ?>
              </span>
              <span class="nit-spec-name"><?= $name ?></span>
            </h3>
            <?php if ($count > 0 || $subcount > 0 || $levelspan !== ''): ?>
            <!-- Structure line: what is inside, before it is scrolled. -->
            <div class="nit-spec-meta">
              <?php if ($subcount > 0): ?>
              <span class="nit-spec-meta__item">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7h5l2 2h11v10H3z"/></svg>
                <?= $ncount($subcount, ['sub-category', 'sub-categories'], ['تصنيف فرعي واحد', 'تصنيفان فرعيان', 'تصنيفات فرعية', 'تصنيفًا فرعيًا']) ?>
              </span>
              <?php endif; ?>
              <span class="nit-spec-meta__item">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19V5a2 2 0 0 1 2-2h14v16H6a2 2 0 0 0-2 2z"/><path d="M4 19a2 2 0 0 0 2 2h14"/></svg>
                <?= $ncount($count, ['course', 'courses'], ['دورة واحدة', 'دورتان', 'دورات', 'دورة']) ?>
              </span>
              <?php if ($levelspan !== ''): ?>
              <span class="nit-spec-meta__item nit-spec-meta__item--lvl">
                <?= $levelmeter(end($rungs)) ?>
                <?= $levelspan ?>
              </span>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>

          <?php if (!empty($groups)): ?>
          <div class="nit-lvl-groups">
            <?php foreach ($groups as $group): ?>
            <?php $isnone = $group['key'] === 'none'; ?>
            <!-- One rung of the ladder: its name in the brand colour with the meter beside
                 it, then the cards that stand on it. -->
            <section class="nit-lvl-group<?= $isnone ? ' nit-lvl-group--none' : '' ?>" data-nit-lvlgroup="<?= s($group['key']) ?>">
              <?php if ($showrungs): ?>
              <h4 class="nit-spec-title nit-spec-title--sub nit-lvl-title">
                <?= $isnone ? '' : $levelmeter($group['index']) ?>
                <span class="nit-spec-subname"><?= $group['label'] ?></span>
              </h4>
              <?php endif; ?>
              <div class="nit-spec-grid">
                <?php foreach ($group['courses'] as $course): ?>
                  <?php $rendercard($course); ?>
                <?php endforeach; ?>
              </div>
            </section>
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
        /* Category heading — Figma "الوجيستيات": one row of icon box, name (22 Bold) and
           the muted "( 3 courses · levels 1–2 )" line, wrapping only on a narrow screen. */
        .nit-spec-head {
          display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px;
          margin-bottom: 22px;
        }
        .nit-spec-title {
          display: inline-flex; align-items: center; gap: 12px;
          margin: 0; font-size: 22px; font-weight: 700; line-height: 1.4;
        }
        .nit-spec-title .nit-spec-name { color: var(--ctext1); }
        /* The outlined square the icon sits in. Whatever the category has — an emoji, an
           uploaded icon, its image, or the generic glyph — fits the same box. */
        .nit-spec-iconbox {
          display: inline-flex; align-items: center; justify-content: center;
          width: 40px; height: 40px; flex: 0 0 auto; box-sizing: border-box;
          border: 1.5px solid var(--cbg4); border-radius: 8px;
          color: var(--cbg4); overflow: hidden;
        }
        .nit-spec-iconbox .nit-cat-icon--spec { width: 26px; height: 26px; font-size: 21px; }
        .nit-spec-iconbox .nit-spec-img { width: 100%; height: 100%; object-fit: contain; padding: 4px; box-sizing: border-box; }

        /* A level heading under a category: the rung's name in the brand's accent text
           colour (18 Bold) with its meter beside it — plain text, no chip. */
        .nit-spec-title--sub {
          font-size: 18px; font-weight: 700; gap: 8px;
          color: var(--ctext3);
        }
        .nit-spec-title--sub .nit-spec-subname { color: var(--ctext3); }

        /* Three 400px cards to a 1280px row in the design (40px gutters); fewer as the
           viewport narrows, one on a phone. */
        .nit-spec-grid {
          display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 330px), 1fr));
          gap: 32px; align-items: stretch;
        }
        /* Nested subcategory groups sit indented under their parent. */
        .nit-spec-children { margin-top: 28px; display: flex; flex-direction: column; gap: 8px; }
        .nit-spec-block--nested {
          margin-bottom: 32px;
          margin-inline-start: 20px; padding-inline-start: 16px;
          border-inline-start: 2px solid color-mix(in srgb, var(--cbg4) 18%, transparent);
        }

        /* ── Levels ─────────────────────────────────────────────────────────────
           The third tier. A category (any depth) gets the big title above; its
           courses are then grouped rung by rung, and each rung wears the soft accent
           chip a nested category used to wear — dot, label, and a small meter lit up
           to that rung. */

        .nitcc[hidden], .nit-lvl-group[hidden], .nit-spec-block[hidden] { display: none !important; }

        /* The meter: one bar per rung of the ladder, lit up to the rung in question. */
        .nit-lvl-meter { display: inline-flex; flex: 0 0 auto; color: var(--ctext2); }
        .nit-lvl-meter svg { display: block; width: 24px; height: 24px; }
        .nit-spec-meta .nit-lvl-meter svg { width: 18px; height: 18px; }

        /* Rung chip inside a category. */
        .nit-lvl-groups { display: flex; flex-direction: column; gap: 28px; }
        .nit-lvl-title { margin-bottom: 18px; }
        .nit-lvl-group--none .nit-lvl-title .nit-spec-subname { color: var(--ctext2); }

        /* Structure line beside a category title, in brackets as in the design:
           ( sub-categories · courses · level span ). Brackets are mirrored characters,
           so the same CSS reads right in Arabic. */
        .nit-spec-meta {
          display: inline-flex; flex-wrap: wrap; align-items: center; gap: 4px 0;
          margin: 0;
          font-size: 16px; font-weight: 500; color: var(--ctext2);
        }
        .nit-spec-meta::before { content: "("; margin-inline-end: 6px; }
        .nit-spec-meta::after { content: ")"; margin-inline-start: 6px; }
        .nit-spec-meta__item + .nit-spec-meta__item { margin-inline-start: 14px; }
        .nit-spec-meta__item { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
        .nit-spec-meta__item svg { flex: 0 0 auto; opacity: .8; width: 16px; height: 16px; }
      </style>

      <?php foreach ($rootnodes as $node): ?>
        <?php $rendernode($node, 0); ?>
      <?php endforeach; ?>

      <?php
        // Shown when nothing is visible: no courses at all, or the filter picked a
        // subcategory with no (visible) courses — whose block was never rendered.
        $anyvisible = false;
        foreach ($rootnodes as $node) {
            if (!$subid || (int) $node['cat']->id === $subid) {
                $anyvisible = true;
                break;
            }
        }
      ?>
      <div data-nit-empty<?= $anyvisible ? ' hidden' : '' ?> style="text-align: center; color: var(--ctext2); padding: 40px;">
        <?= $t('No courses found in this category.', 'لا توجد دورات في هذا التصنيف.') ?>
      </div>

    </div>
  </div>

  <?php if (!empty($subcategories)): ?>
  <script>
    (function() {
      /* The subcategory filter bar, without a round trip. Every subcategory's section is
         already on the page (rendered whatever ?sub= said); a pill only decides which of
         them is shown. The URL is kept in step with history.pushState so the filtered view
         can still be bookmarked, shared, or reached with Back/Forward — and the same
         ?sub= link works with JavaScript off, because each pill is a real link. */
      var bar = document.querySelector('[data-nit-catfilter]');
      if (!bar) {
        return;
      }
      var pills  = Array.prototype.slice.call(bar.querySelectorAll('[data-nit-sub]'));
      var blocks = Array.prototype.slice.call(document.querySelectorAll('[data-nit-block]'));
      var descs  = Array.prototype.slice.call(bar.querySelectorAll('[data-nit-subdesc]'));
      var empty  = document.querySelector('[data-nit-empty]');

      function apply(sub) {
        var shown = 0;
        var all = (sub === '0');
        blocks.forEach(function(b) {
          var on = all || b.getAttribute('data-nit-block') === sub;
          b.hidden = !on;
          if (on) {
            shown++;
          }
        });
        descs.forEach(function(d) {
          d.hidden = d.getAttribute('data-nit-subdesc') !== sub;
        });
        pills.forEach(function(p) {
          var on = p.getAttribute('data-nit-sub') === sub;
          p.classList.toggle('btn-primary', on);
          p.classList.toggle('btn-outline-primary', !on);
          p.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        if (empty) {
          empty.hidden = shown > 0;
        }
      }

      function current() {
        var m = /[?&]sub=(\d+)/.exec(window.location.search);
        return m && m[1] !== '0' ? m[1] : '0';
      }

      pills.forEach(function(p) {
        p.addEventListener('click', function(e) {
          /* A modifier click means "open in a new tab": leave that to the browser. */
          if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) {
            return;
          }
          e.preventDefault();
          var sub = p.getAttribute('data-nit-sub');
          if (sub === current()) {
            return;
          }
          apply(sub);
          var url = new URL(window.location.href);
          if (sub === '0') {
            url.searchParams.delete('sub');
          } else {
            url.searchParams.set('sub', sub);
          }
          window.history.pushState({nitsub: sub}, '', url.toString());
        });
      });

      window.addEventListener('popstate', function() {
        apply(current());
      });
    })();
  </script>
  <?php endif; ?>

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
