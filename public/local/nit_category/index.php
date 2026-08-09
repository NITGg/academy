<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/filelib.php');

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
$PAGE->set_pagelayout('standard');

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

// Bilingual inline helper (site is en/ar); mirrors the theme's {mlang} pairs.
$isar = (strpos(current_language(), 'ar') === 0);
$t = function (string $en, string $ar) use ($isar) {
    return $isar ? $ar : $en;
};

// A pill/label for the subcategory filter bar.
$pill = function (moodle_url $url, string $label, bool $active): string {
    $base = 'display:inline-block; padding:9px 22px; border-radius:50px; font-size:14px; '
          . 'font-weight:bold; text-decoration:none; white-space:nowrap; transition:all .25s ease;';
    if ($active) {
        $style = $base . 'background:linear-gradient(135deg,var(--nit-accentgolddark,#c9922a),'
               . 'var(--nit-accentgold,#e8b84b)); color:var(--nit-darkbackground,#0a1628); '
               . 'border:1px solid transparent; box-shadow:0 6px 18px color-mix(in srgb,'
               . 'var(--nit-accentgold,#e8b84b) 25%,transparent);';
    } else {
        $style = $base . 'background:var(--nit-darksurface,#0f1e33); color:var(--nit-darktextsecondary,#8a9ab5); '
               . 'border:1px solid color-mix(in srgb,var(--nit-darktextprimary,#ffffff) 12%,transparent);';
    }
    return '<a href="' . $url->out() . '" style="' . $style . '">' . $label . '</a>';
};

$description  = format_text($category->description, $category->descriptionformat, ['context' => $context]);
$categoryname = $category->get_formatted_name();

echo $OUTPUT->header();
?>

<div dir="auto" style="background: var(--nit-darkbackground, #0a1628); min-height: 100vh; margin: -20px; padding-bottom: 40px;">

  <!-- Category Header Banner -->
  <div style="background: var(--nit-darksurface, #0f1e33); padding: 64px 16px; border-bottom: 1px solid color-mix(in srgb, var(--nit-darktextprimary, #ffffff) 6%, transparent); position: relative; overflow: hidden;">
    <div style="position: absolute; top: -50%; left: -10%; width: 50%; height: 200%; background: radial-gradient(circle, color-mix(in srgb, var(--nit-accentteal, #00a99d) 5%, transparent) 0%, transparent 70%); pointer-events: none;"></div>
    <div style="position: absolute; bottom: -50%; right: -10%; width: 50%; height: 200%; background: radial-gradient(circle, color-mix(in srgb, var(--nit-accentgold, #e8b84b) 5%, transparent) 0%, transparent 70%); pointer-events: none;"></div>

    <div style="max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; text-align: center; position: relative; z-index: 1;">

      <?php if ($categoryimage !== ''): ?>
      <!-- Category image (site logo when the category has none) -->
      <div style="width: 110px; height: 110px; border-radius: 24px; background: var(--nit-darksurfacevariant, #13293f) url('<?= s($categoryimage) ?>') center/contain no-repeat; border: 1px solid color-mix(in srgb, var(--nit-accentgold, #e8b84b) 25%, transparent); margin-bottom: 24px; box-shadow: 0 10px 30px rgba(0,0,0,.35);"></div>
      <?php endif; ?>

      <h1 style="font-size: clamp(32px, 5vw, 48px); font-weight: 800; color: var(--nit-darktextprimary, #ffffff); margin: 0 0 16px;">
        <?= $categoryname ?>
      </h1>

      <?php if (trim(strip_tags($description)) !== ''): ?>
      <div style="font-size: 16px; color: var(--nit-darktextsecondary, #8a9ab5); max-width: 800px; line-height: 1.7; margin: 0;">
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
  </div>
  <?php endif; ?>

  <!-- Courses Section -->
  <div style="padding: 40px 16px 16px;">
    <div style="max-width: 1200px; margin: 0 auto;">

      <!-- Section Header -->
      <div style="margin-bottom: 32px;">
        <h2 style="font-size: 26px; font-weight: bold; color: var(--nit-darktextprimary, #ffffff); margin: 0 0 8px;">
          <?= $t('Available Courses', 'الدورات المتاحة') ?>
        </h2>
        <div style="color: var(--nit-accentgold, #e8b84b); font-weight: bold; font-size: 14px;">
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
        ?>
        <!-- Course Card -->
        <div style="background: var(--nit-darksurface, #0f1e33); border: 1px solid color-mix(in srgb, var(--nit-darktextprimary, #ffffff) 6%, transparent); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; height: 100%; min-height: 380px; transition: transform 0.3s ease, box-shadow 0.3s ease;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 24px rgba(0,0,0,0.35)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">

          <!-- Image + price -->
          <div style="position: relative; flex: 0 0 160px; height: 160px;">
            <div style="width: 100%; height: 160px; background: var(--nit-darksurfacevariant, #13293f) url('<?= s($courseimage) ?>') center/cover no-repeat;">&nbsp;</div>
            <span style="position: absolute; top: 12px; inset-inline-end: 12px; background: var(--nit-accentteal, #00a99d); color: var(--nit-darktextprimary, #ffffff); font-size: 12px; font-weight: bold; padding: 4px 12px; border-radius: 50px;">
              <?= s($pricelabel) ?>
            </span>
          </div>

          <!-- Content -->
          <div style="padding: 18px; display: flex; flex-direction: column; flex: 1; min-height: 0;">
            <h3 style="font-size: 17px; font-weight: bold; color: var(--nit-darktextprimary, #ffffff); margin: 0 0 4px; line-height: 1.4;">
              <?= $coursename ?>
            </h3>

            <?php if ($teacher !== ''): ?>
            <div style="font-size: 12px; color: var(--nit-accentgold, #e8b84b); margin: 0 0 8px;">
              👤 <?= s($teacher) ?>
            </div>
            <?php endif; ?>

            <p style="font-size: 13px; color: var(--nit-darktextsecondary, #8a9ab5); line-height: 1.7; margin: 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
              <?= s($summary) ?>
            </p>

            <a href="<?= $courseurl->out() ?>" style="display: block; width: 100%; box-sizing: border-box; margin-top: 16px; text-align: center; background: linear-gradient(135deg, var(--nit-accentgolddark, #c9922a), var(--nit-accentgold, #e8b84b)); color: var(--nit-darkbackground, #0a1628); font-weight: bold; padding: 10px 12px; border-radius: 8px; text-decoration: none;" onmouseover="this.style.opacity='0.9';" onmouseout="this.style.opacity='1';">
              <?= $t('View More', 'المزيد') ?>
            </a>
          </div>
        </div>
        <?php endforeach; ?>

      </div>

      <!-- Pagination -->
      <div style="margin-top: 40px; display: flex; justify-content: center;">
        <?= $OUTPUT->paging_bar($totalcourses, $page, $perpage, new moodle_url('/local/nit_category/index.php', ['id' => $categoryid, 'sub' => $subid])) ?>
      </div>

      <?php else: ?>
      <div style="text-align: center; color: var(--nit-darktextsecondary, #8a9ab5); padding: 40px;">
        <?= $t('No courses found in this category.', 'لا توجد دورات في هذا التصنيف.') ?>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php
echo $OUTPUT->footer();
