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
$subid      = optional_param('sub', 0, PARAM_INT);  // 0 = "All" (parent, recursive).
$page       = optional_param('page', 0, PARAM_INT); // Course pagination page (0-based).
$perpage    = 12;

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

// Courses in the target category and all its descendants, paginated.
$totalcourses = $targetcat->get_courses_count(['recursive' => true]);
$courses = $targetcat->get_courses([
    'recursive'      => true,
    'sort'           => ['sortorder' => 1],
    'offset'         => $page * $perpage,
    'limit'          => $perpage,
    'summary'        => true,
    'coursecontacts' => true,
]);

// Category image: Moodle categories have no image of their own, so fall back to
// the site logo ("if the category has no image, show the site logo").
$logo = $OUTPUT->get_logo_url() ?: $OUTPUT->get_compact_logo_url();
$categoryimage = $logo ? $logo->out(false) : '';

// Colour style: each category details page cycles through the 3 category styles
// (gallery: Categories -> Style 1/2/3). The style is chosen by the position of
// this category's top-level ancestor, so every page under it stays consistent.
$ancestors = $category->get_parents();                      // top-most first, excludes self.
$rootid    = !empty($ancestors) ? (int) $ancestors[0] : (int) $category->id;
$toplevels = core_course_category::top()->get_children();
$position  = 0;
$i = 0;
foreach ($toplevels as $tl) {
    if ((int) $tl->id === $rootid) {
        $position = $i;
        break;
    }
    $i++;
}
$stylenum = ($position % 3) + 1;                            // 1, 2, 3, 1, 2, 3, ...

// Map the 8 style tokens to short local custom properties on the page wrapper;
// every descendant then reads var(--cbg1..4) / var(--ctext1..4).
$slots = ['text1', 'text2', 'text3', 'text4', 'bg1', 'bg2', 'bg3', 'bg4'];
$stylevars = '';
foreach ($slots as $slot) {
    $stylevars .= '--c' . $slot . ': var(--nit-cat-style' . $stylenum . '-' . $slot . '); ';
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
// Per-course price + offer info for a card: [haspricing, price, offerlabel, offerfinal].
$nitcourseinfo = function ($courseid) use ($nitcheckout) {
    global $USER;
    $out = ['haspricing' => false, 'price' => 0.0, 'offerlabel' => '', 'offerfinal' => 0.0];
    if (!$nitcheckout || !\local_payments\price_resolver::has_pricing($courseid)) {
        return $out;
    }
    try {
        $pricing = \local_payments\price_resolver::resolve($courseid, (int) ($USER->id ?? 0));
        $base = (float) $pricing->price;
        $out['haspricing'] = true;
        $out['price'] = $base;
        $summary = \local_nit_commerce\discount_manager::offer_summary('course', (int) $courseid, $base);
        if ($summary) {
            $out['offerlabel'] = $summary['label'];   // e.g. "-40%"
            $out['offerfinal'] = (float) $summary['final'];
        }
    } catch (\Throwable $e) {
        // Leave defaults on any pricing error.
    }
    return $out;
};

echo $OUTPUT->header();
?>

<div dir="auto" class="nit-cat-details" style="<?= $stylevars ?>background: var(--cbg1); min-height: 100vh; padding-bottom: 40px; width: 100vw; max-width: 100vw; margin-inline: calc(50% - 50vw); margin-top: 0;">

  <!-- Category Header Banner -->
  <div style="background: var(--cbg2); padding: 64px 16px; border-bottom: 1px solid color-mix(in srgb, var(--ctext1) 6%, transparent); position: relative; overflow: hidden;">
    <div style="position: absolute; top: -50%; left: -10%; width: 50%; height: 200%; background: radial-gradient(circle, color-mix(in srgb, var(--ctext3) 6%, transparent) 0%, transparent 70%); pointer-events: none;"></div>
    <div style="position: absolute; bottom: -50%; right: -10%; width: 50%; height: 200%; background: radial-gradient(circle, color-mix(in srgb, var(--cbg4) 8%, transparent) 0%, transparent 70%); pointer-events: none;"></div>

    <div style="max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; text-align: center; position: relative; z-index: 1;">

      <?php if ($categoryimage !== ''): ?>
      <!-- Category image (site logo when the category has none) -->
      <div style="width: 110px; height: 110px; border-radius: 24px; background: var(--cbg3) url('<?= s($categoryimage) ?>') center/contain no-repeat; border: 1px solid color-mix(in srgb, var(--ctext3) 25%, transparent); margin-bottom: 24px; box-shadow: 0 10px 30px rgba(0,0,0,.35);"></div>
      <?php endif; ?>

      <h1 style="font-size: clamp(32px, 5vw, 48px); font-weight: 800; color: var(--ctext1); margin: 0 0 16px;">
        <?= $categoryname ?>
      </h1>

      <?php if (trim(strip_tags($description)) !== ''): ?>
      <div style="font-size: 16px; color: var(--ctext2); max-width: 800px; line-height: 1.7; margin: 0;">
        <?= $description ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Subcategory Filter Bar (All + children) -->
  <?php if (!empty($subcategories)): ?>
  <div style="padding: 32px 16px 0;">
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
  <div style="padding: 40px 16px 16px;">
    <div style="max-width: 1200px; margin: 0 auto;">

      <!-- Section Header -->
      <div style="margin-bottom: 32px;">
        <h2 style="font-size: 26px; font-weight: bold; color: var(--ctext1); margin: 0 0 8px;">
          <?= $t('Available Courses', 'الدورات المتاحة') ?>
        </h2>
        <div style="color: var(--ctext3); font-weight: bold; font-size: 14px;">
          <?= $totalcourses ?> <?= $t('Courses', 'دورة') ?>
        </div>
      </div>

      <?php if (!empty($courses)): ?>
      <!-- Courses Grid -->
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 24px; align-items: stretch;">

        <?php foreach ($courses as $course): ?>
        <?php
            $coursecontext = context_course::instance($course->id);
            $courseurl     = new moodle_url('/course/view.php', ['id' => $course->id]);
            $coursename    = $course->get_formatted_name();

            // Course image: overview file, else a generated pattern.
            $courseimage = '';
            foreach ($course->get_course_overviewfiles() as $file) {
                if ($file->is_valid_image()) {
                    $courseimage = moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        null,
                        $file->get_filepath(),
                        $file->get_filename()
                    )->out(false);
                    break;
                }
            }
            if ($courseimage === '') {
                $courseimage = $OUTPUT->get_generated_image_for_id($course->id);
            }

            // Short plain-text summary.
            $summary = '';
            if ($course->has_summary()) {
                $plain = html_to_text(
                    format_text($course->summary, $course->summaryformat, ['context' => $coursecontext, 'noclean' => true]),
                    0,
                    false
                );
                $summary = shorten_text(trim($plain), 120);
            }

            $price      = function_exists('theme_nit_course_price') ? theme_nit_course_price((int) $course->id) : '';
            $teacher    = function_exists('theme_nit_course_teacher') ? theme_nit_course_teacher((int) $course->id) : '';
            $pricelabel = $price !== '' ? $price : $t('Free', 'مجانًا');
            $info       = $nitcourseinfo($course->id);
        ?>
        <!-- Course Card -->
        <div style="background: var(--cbg2); border: 1px solid color-mix(in srgb, var(--ctext1) 6%, transparent); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; height: 100%; min-height: 380px; transition: transform 0.3s ease, box-shadow 0.3s ease;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 24px rgba(0,0,0,0.35)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">

          <!-- Image + price -->
          <div style="position: relative; flex: 0 0 160px; height: 160px;">
            <div style="width: 100%; height: 160px; background: var(--cbg3) url('<?= s($courseimage) ?>') center/cover no-repeat;">&nbsp;</div>
            <span style="position: absolute; top: 12px; inset-inline-end: 12px; background: var(--cbg4); color: var(--ctext4); font-size: 12px; font-weight: bold; padding: 4px 12px; border-radius: 50px;">
              <?= s($pricelabel) ?>
            </span>
            <?php if ($info['offerlabel'] !== ''): ?>
            <span style="position: absolute; top: 12px; inset-inline-start: 12px; background: var(--nit-accentteal, #00a99d); color: #ffffff; font-size: 12px; font-weight: bold; padding: 4px 12px; border-radius: 50px; box-shadow: 0 2px 8px rgba(0,0,0,.3);">
              <?= s($info['offerlabel']) ?>
            </span>
            <?php endif; ?>
          </div>

          <!-- Content -->
          <div style="padding: 18px; display: flex; flex-direction: column; flex: 1; min-height: 0;">
            <h3 style="font-size: 17px; font-weight: bold; color: var(--ctext1); margin: 0 0 4px; line-height: 1.4;">
              <?= $coursename ?>
            </h3>

            <?php if ($teacher !== ''): ?>
            <div style="font-size: 12px; color: var(--ctext3); margin: 0 0 8px;">
              👤 <?= s($teacher) ?>
            </div>
            <?php endif; ?>

            <p style="font-size: 13px; color: var(--ctext2); line-height: 1.7; margin: 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
              <?= s($summary) ?>
            </p>

            <!-- Actions: pinned to the card bottom so every card's buttons align, regardless of summary length. -->
            <div style="margin-top: auto; padding-top: 16px; display: flex; flex-direction: column; gap: 8px;">
              <?php if ($info['haspricing']): ?>
              <button type="button" data-nit-buy-course data-courseid="<?= (int) $course->id ?>"
                data-name="<?= s($coursename) ?>" data-price="<?= s((string) $info['price']) ?>"
                style="display: block; width: 100%; box-sizing: border-box; text-align: center; background: var(--cbg4); color: var(--ctext4); font-weight: bold; padding: 10px 12px; border: 0; border-radius: 8px; cursor: pointer; font-size: 15px;"
                onmouseover="this.style.opacity='0.9';" onmouseout="this.style.opacity='1';">
                <?= $t('Buy now', 'اشترِ الآن') ?>
              </button>
              <a href="<?= $courseurl->out() ?>" style="display: block; width: 100%; box-sizing: border-box; text-align: center; background: transparent; color: var(--ctext1); font-weight: bold; padding: 8px 12px; border: 1px solid color-mix(in srgb, var(--ctext1) 20%, transparent); border-radius: 8px; text-decoration: none;">
                <?= $t('View More', 'المزيد') ?>
              </a>
              <?php else: ?>
              <a href="<?= $courseurl->out() ?>" style="display: block; width: 100%; box-sizing: border-box; text-align: center; background: var(--cbg4); color: var(--ctext4); font-weight: bold; padding: 10px 12px; border-radius: 8px; text-decoration: none;" onmouseover="this.style.opacity='0.9';" onmouseout="this.style.opacity='1';">
                <?= $t('View More', 'المزيد') ?>
              </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>

      </div>

      <!-- Pagination -->
      <div style="margin-top: 40px; display: flex; justify-content: center;">
        <?= $OUTPUT->paging_bar($totalcourses, $page, $perpage, new moodle_url('/local/nit_category/index.php', ['id' => $categoryid, 'sub' => $subid])) ?>
      </div>

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
