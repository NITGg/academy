<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

$categoryid = required_param('id', PARAM_INT);

// Fetch the category
$category = core_course_category::get($categoryid, MUST_EXIST);

// Subcategories to drill into, and the courses in this category and all its
// descendants (recursive) so a main category still lists its related courses.
$subcategories = $category->get_children();
$courses = $category->get_courses([
    'recursive' => true,
    'sort' => ['sortorder' => 1],
]);

$PAGE->set_url(new moodle_url('/local/nit_category/index.php', array('id' => $categoryid)));
$PAGE->set_context($category->get_context()); 
$PAGE->set_title($category->get_formatted_name());
$PAGE->set_heading($category->get_formatted_name());
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

// Format category description
$description = format_text($category->description, $category->descriptionformat, array('context' => $category->get_context()));
$categoryname = $category->get_formatted_name();
?>

<style>
  .nit-course-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 24px;
  }
  .nit-course-card {
    background: var(--nit-darksurface, #0f1e33);
    border: 1px solid color-mix(in srgb, var(--nit-darktextprimary, #ffffff) 6%, transparent);
    border-radius: 16px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }
  .nit-course-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 24px rgba(0,0,0,0.3);
  }
</style>

<div dir="auto" style="background: var(--nit-darkbackground, #0a1628); min-height: 100vh; margin: -20px; padding-bottom: 40px;">
  
  <!-- Category Header Banner -->
  <div style="background: var(--nit-darksurface, #0f1e33); padding: 80px 16px; border-bottom: 1px solid color-mix(in srgb, var(--nit-darktextprimary, #ffffff) 6%, transparent); position: relative; overflow: hidden;">
    <div style="position: absolute; top: -50%; left: -10%; width: 50%; height: 200%; background: radial-gradient(circle, color-mix(in srgb, var(--nit-accentteal, #00a99d) 5%, transparent) 0%, transparent 70%); pointer-events: none;"></div>
    <div style="position: absolute; bottom: -50%; right: -10%; width: 50%; height: 200%; background: radial-gradient(circle, color-mix(in srgb, var(--nit-accentgold, #e8b84b) 5%, transparent) 0%, transparent 70%); pointer-events: none;"></div>

    <div style="max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; text-align: center; position: relative; z-index: 1;">
      <span style="display: inline-flex; align-items: center; gap: 8px; background: color-mix(in srgb, var(--nit-accentteal, #00a99d) 15%, transparent); border: 1px solid color-mix(in srgb, var(--nit-accentteal, #00a99d) 30%, transparent); border-radius: 50px; padding: 6px 18px; font-size: 14px; color: var(--nit-accentteal, #00a99d); font-weight: bold; margin-bottom: 16px;">
        <span>💻</span> {mlang en}Category{mlang} {mlang ar}التصنيف{mlang}
      </span>
      <h1 style="font-size: clamp(32px, 5vw, 48px); font-weight: 800; color: var(--nit-darktextprimary, #ffffff); margin: 0 0 20px;">
        <?= $categoryname ?>
      </h1>
      <div style="font-size: 16px; color: var(--nit-darktextsecondary, #8a9ab5); max-width: 800px; line-height: 1.7; margin: 0;">
        <?= $description ?>
      </div>
    </div>
  </div>

  <?php if (!empty($subcategories)): ?>
  <!-- Subcategories Section -->
  <div style="padding: 56px 16px 0;">
    <div style="max-width: 1200px; margin: 0 auto;">
      <div style="margin-bottom: 32px;">
        <h2 style="font-size: 28px; font-weight: bold; color: var(--nit-darktextprimary, #ffffff); margin: 0 0 8px;">
          {mlang en}Subcategories{mlang} {mlang ar}التصنيفات الفرعية{mlang}
        </h2>
        <div style="color: var(--nit-accentgold, #e8b84b); font-weight: bold; font-size: 14px;">
          <?= count($subcategories) ?> {mlang en}Subcategories{mlang} {mlang ar}تصنيف فرعي{mlang}
        </div>
      </div>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px;">
        <?php
          $suicons = ['💻', '📊', '🎨', '🗣️', '🔬', '💡', '📚', '🎯'];
          $suidx = 0;
        ?>
        <?php foreach ($subcategories as $subcat): ?>
        <?php
            $suburl = new moodle_url('/local/nit_category/index.php', array('id' => $subcat->id));
            $subcount = $subcat->get_courses_count(array('recursive' => true));
            $subicon = $suicons[$suidx % count($suicons)];
            $suidx++;
        ?>
        <a href="<?= $suburl ?>" style="display: flex; align-items: center; gap: 16px; background: var(--nit-darksurface, #0f1e33); border: 1px solid color-mix(in srgb, var(--nit-darktextprimary, #ffffff) 6%, transparent); border-radius: 14px; padding: 18px 20px; text-decoration: none; transition: transform 0.3s ease, border-color 0.3s ease;" onmouseover="this.style.transform='translateY(-4px)'; this.style.borderColor='color-mix(in srgb, var(--nit-accentteal, #00a99d) 40%, transparent)';" onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='color-mix(in srgb, var(--nit-darktextprimary, #ffffff) 6%, transparent)';">
          <span style="font-size: 34px; line-height: 1;"><?= $subicon ?></span>
          <span style="display: flex; flex-direction: column; min-width: 0;">
            <span style="font-size: 16px; font-weight: bold; color: var(--nit-darktextprimary, #ffffff); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= $subcat->get_formatted_name() ?></span>
            <span style="font-size: 13px; color: var(--nit-darktextsecondary, #8a9ab5);"><?= $subcount ?> {mlang en}Courses{mlang} {mlang ar}دورة{mlang}</span>
          </span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($courses) || empty($subcategories)): ?>
  <!-- Courses List Section -->
  <div style="padding: 64px 16px;">
    <div style="max-width: 1200px; margin: 0 auto;">
      
      <!-- Section Header -->
      <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div>
          <h2 style="font-size: 28px; font-weight: bold; color: var(--nit-darktextprimary, #ffffff); margin: 0 0 8px;">
            {mlang en}Available Courses{mlang} {mlang ar}الدورات المتاحة{mlang}
          </h2>
          <div style="color: var(--nit-accentgold, #e8b84b); font-weight: bold; font-size: 14px;">
            <?= count($courses) ?> {mlang en}Courses Found{mlang} {mlang ar}دورة{mlang}
          </div>
        </div>
      </div>

      <!-- Courses Grid -->
      <div class="nit-course-grid">
        
        <?php foreach ($courses as $course): ?>
        <?php
            $coursecontext = context_course::instance($course->id);
            $courseurl = new moodle_url('/course/view.php', array('id' => $course->id));
            $coursefullname = format_string($course->fullname, true, array('context' => $coursecontext));
            
            // Get course image
            $courseimage = '';
            $courseobj = new core_course_list_element($course);
            foreach ($courseobj->get_course_overviewfiles() as $file) {
                if ($file->is_valid_image()) {
                    $courseimage = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(), null, $file->get_filepath(), $file->get_filename())->out();
                    break;
                }
            }
            if (empty($courseimage)) {
                $courseimage = $OUTPUT->image_url('patterns/default-course-image', 'core'); // Fallback
            }
        ?>
        <div class="nit-course-card">
          <div style="position: relative;">
            <div style="width: 100%; height: 180px; background: var(--nit-darksurfacevariant, #13293f) url('<?= $courseimage ?>') center/cover no-repeat;"></div>
          </div>
          <div style="padding: 24px; display: flex; flex-direction: column; flex: 1;">
            <h3 style="font-size: 18px; font-weight: bold; color: var(--nit-darktextprimary, #ffffff); margin: 0 0 8px; line-height: 1.4;">
              <?= $coursefullname ?>
            </h3>
            <p style="font-size: 14px; color: var(--nit-darktextsecondary, #8a9ab5); line-height: 1.7; margin: 0 0 24px; flex: 1; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;">
              <?= strip_tags(format_text($course->summary, $course->summaryformat, array('context' => $coursecontext))) ?>
            </p>
            <a style="display: block; text-align: center; background: linear-gradient(135deg,var(--nit-accentgolddark, #c9922a),var(--nit-accentgold, #e8b84b)); color: var(--nit-darkbackground, #0a1628); font-weight: bold; padding: 12px; border-radius: 8px; text-decoration: none; transition: opacity 0.3s ease;" href="<?= $courseurl ?>" onmouseover="this.style.opacity='0.9';" onmouseout="this.style.opacity='1';">
              {mlang en}View Course{mlang} {mlang ar}عرض الدورة{mlang}
            </a>
          </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($courses)): ?>
            <div style="grid-column: 1 / -1; text-align: center; color: var(--nit-darktextsecondary, #8a9ab5); padding: 40px;">
                {mlang en}No courses found in this category.{mlang} {mlang ar}لا توجد دورات في هذا التصنيف.{mlang}
            </div>
        <?php endif; ?>

      </div>

    </div>
  </div>
  <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();
