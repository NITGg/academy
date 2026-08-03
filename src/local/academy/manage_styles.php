<?php
require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/course/lib.php');

$PAGE->set_url(new moodle_url('/local/academy/manage_styles.php'));
$PAGE->set_context(context_system::instance());
require_login();
require_capability('moodle/site:config', context_system::instance());
admin_externalpage_setup('local_academy_managestyles');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $presets = $_POST['preset'] ?? [];
    foreach ($presets as $catid => $preset_id) {
        $catid = (int)$catid;
        $preset_id = clean_param($preset_id, PARAM_ALPHANUMEXT);
        
        // Ensure table exists (temporary safeguard during dev)
        $dbman = $DB->get_manager();
        $table = new xmldb_table('academy_cat_presets');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('preset_id', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, '');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('categoryid_uix', XMLDB_INDEX_UNIQUE, ['categoryid']);
            $dbman->create_table($table);
        }

        $existing = $DB->get_record('academy_cat_presets', ['categoryid' => $catid]);
        if ($existing) {
            $existing->preset_id = $preset_id;
            $existing->timemodified = time();
            $DB->update_record('academy_cat_presets', $existing);
        } else {
            $newrecord = new stdClass();
            $newrecord->categoryid = $catid;
            $newrecord->preset_id = $preset_id;
            $newrecord->timecreated = time();
            $newrecord->timemodified = time();
            $DB->insert_record('academy_cat_presets', $newrecord);
        }
    }
    redirect($PAGE->url, 'تم حفظ الأنماط بنجاح', null, \core\output\notification::NOTIFY_SUCCESS);
}

// Get categories
$categories = $DB->get_records('course_categories', [], 'sortorder ASC', 'id, name, depth');
// Get current presets if table exists
$current_presets = [];
$dbman = $DB->get_manager();
if ($dbman->table_exists('academy_cat_presets')) {
    $current_presets = $DB->get_records('academy_cat_presets');
}
$preset_map = [];
foreach ($current_presets as $cp) {
    $preset_map[$cp->categoryid] = $cp->preset_id;
}

$styles = [
    '' => 'الافتراضي (شبكة كروت عادية)',
    'style-1' => 'النمط 1 — كروت مربعة بسكرول أفقي',
    'style-2' => 'النمط 2 — دوائر',
    'style-3' => 'النمط 3 — قائمة أفقية عريضة'
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managestyles', 'local_academy'));
?>
<div class="container-fluid">
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="training-map-tab" data-toggle="tab" data-target="#training-map" type="button" role="tab" aria-controls="training-map" aria-selected="true">الخريطة التدريبية</button>
        </li>
        <!-- Add more tabs here in the future -->
    </ul>
    <div class="tab-content mt-3" id="myTabContent">
        <div class="tab-pane fade show active" id="training-map" role="tabpanel" aria-labelledby="training-map-tab">
            <form method="POST" action="manage_styles.php">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <table class="table table-striped table-bordered mt-3">
                    <thead>
                        <tr>
                            <th>التصنيف</th>
                            <th>شكل العرض (النمط)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): 
                            $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $cat->depth - 1);
                            $current = $preset_map[$cat->id] ?? '';
                        ?>
                        <tr>
                            <td><?php echo $indent . format_string($cat->name); ?></td>
                            <td>
                                <select name="preset[<?php echo $cat->id; ?>]" class="custom-select">
                                    <?php foreach ($styles as $val => $label): ?>
                                        <option value="<?php echo $val; ?>" <?php echo ($current === $val) ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
echo $OUTPUT->footer();
