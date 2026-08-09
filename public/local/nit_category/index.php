<?php
require_once(__DIR__ . '/../../config.php');

$categoryid = optional_param('id', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/nit_category/index.php', array('id' => $categoryid)));
$PAGE->set_context(context_system::instance()); 
$PAGE->set_title(get_string('category'));
$PAGE->set_heading(get_string('category'));

// Set up the layout
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

// ==============================================================================
// START OF CATEGORY DETAILS HTML
// You can replace the static text below with dynamic data using $DB and language strings
// ==============================================================================
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
    
    <!-- Optional Background Decoration -->
    <div style="position: absolute; top: -50%; left: -10%; width: 50%; height: 200%; background: radial-gradient(circle, color-mix(in srgb, var(--nit-accentteal, #00a99d) 5%, transparent) 0%, transparent 70%); pointer-events: none;"></div>
    <div style="position: absolute; bottom: -50%; right: -10%; width: 50%; height: 200%; background: radial-gradient(circle, color-mix(in srgb, var(--nit-accentgold, #e8b84b) 5%, transparent) 0%, transparent 70%); pointer-events: none;"></div>

    <div style="max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; text-align: center; position: relative; z-index: 1;">
      <span style="display: inline-flex; align-items: center; gap: 8px; background: color-mix(in srgb, var(--nit-accentteal, #00a99d) 15%, transparent); border: 1px solid color-mix(in srgb, var(--nit-accentteal, #00a99d) 30%, transparent); border-radius: 50px; padding: 6px 18px; font-size: 14px; color: var(--nit-accentteal, #00a99d); font-weight: bold; margin-bottom: 16px;">
        <span>💻</span> {mlang en}Category{mlang} {mlang ar}التصنيف{mlang}
      </span>
      <h1 style="font-size: clamp(32px, 5vw, 48px); font-weight: 800; color: var(--nit-darktextprimary, #ffffff); margin: 0 0 20px;">
        {mlang en}Information Technology{mlang} {mlang ar}تقنية المعلومات{mlang}
      </h1>
      <p style="font-size: 16px; color: var(--nit-darktextsecondary, #8a9ab5); max-width: 800px; line-height: 1.7; margin: 0;">
        {mlang en}Explore our wide range of Information Technology courses, designed to help you master programming, networking, cybersecurity, and more. Upgrade your skills with expert-led training.{mlang} 
        {mlang ar}استكشف مجموعتنا الواسعة من دورات تقنية المعلومات، المصممة لمساعدتك على احتراف البرمجة، الشبكات، الأمن السيبراني، والمزيد. ارتقِ بمهاراتك مع تدريب يقوده الخبراء.{mlang}
      </p>
    </div>
  </div>

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
            {mlang en}15 Courses Found{mlang} {mlang ar}تم العثور على 15 دورة{mlang}
          </div>
        </div>
      </div>

      <!-- Courses Grid -->
      <div class="nit-course-grid" data-nit-category-courses="<?= htmlspecialchars($categoryid) ?>">
        
        <!-- Course Card 1 (Static Placeholder) -->
        <div class="nit-course-card">
          <div style="position: relative;">
            <div style="width: 100%; height: 180px; background: var(--nit-darksurfacevariant, #13293f) center/cover no-repeat;" data-nit-course-image=""></div>
            <span style="position: absolute; top: 12px; inset-inline-end: 12px; background: var(--nit-accentteal, #00a99d); color: var(--nit-darktextprimary, #ffffff); font-size: 12px; font-weight: bold; padding: 6px 14px; border-radius: 50px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
              {mlang en}Free{mlang}{mlang ar}مجانًا{mlang}
            </span>
          </div>
          <div style="padding: 24px; display: flex; flex-direction: column; flex: 1;">
            <h3 style="font-size: 18px; font-weight: bold; color: var(--nit-darktextprimary, #ffffff); margin: 0 0 8px; line-height: 1.4;">
              Introduction to Cybersecurity
            </h3>
            <div style="font-size: 13px; color: var(--nit-accentgold, #e8b84b); margin: 0 0 12px; display: flex; align-items: center; gap: 6px;">
              <span>👤</span> Dr. Ahmed Ali
            </div>
            <p style="font-size: 14px; color: var(--nit-darktextsecondary, #8a9ab5); line-height: 1.7; margin: 0 0 24px; flex: 1;">
              Learn the fundamentals of cybersecurity, network protection, and how to safeguard digital assets from modern threats.
            </p>
            <a style="display: block; text-align: center; background: linear-gradient(135deg,var(--nit-accentgolddark, #c9922a),var(--nit-accentgold, #e8b84b)); color: var(--nit-darkbackground, #0a1628); font-weight: bold; padding: 12px; border-radius: 8px; text-decoration: none; transition: opacity 0.3s ease;" href="#" onmouseover="this.style.opacity='0.9';" onmouseout="this.style.opacity='1';">
              {mlang en}View Course{mlang} {mlang ar}عرض الدورة{mlang}
            </a>
          </div>
        </div>
      </div>
      
    </div>
  </div>
</div>

<?php
// ==============================================================================
// END OF CATEGORY DETAILS HTML
// ==============================================================================

echo $OUTPUT->footer();
