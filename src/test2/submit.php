<?php

require_once('../config.php');


$PAGE->set_context(get_system_context());
$PAGE->set_pagelayout('site');
$PAGE->set_title("Add Files to vimeo ");
$PAGE->set_heading("Add Files to vimeo");
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/test2/js/moxie.min.js'), true);
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/test2/js/plupload.full.min.js'), true);

// $id=required_param('id',PARAM_INT);

$id = $_GET['resource_id'];
$update = $_GET['update'];

echo $OUTPUT->header();
// echo $id;

$admins = get_admins();
$isadmin = false;
foreach ($admins as $admin) {
  if ($USER->id == $admin->id) {
    $isadmin = true;
    break;
  }
}
$roleassignments = $DB->get_records('role_assignments', ['userid' => $USER->id]);
$manager=0;
foreach($roleassignments as $role){
  if($role->roleid==1){
    $manager=1;
    break;
  }
}
$course_module = $DB->get_record('course_modules', array('instance' => $id, 'module' => 25));
// var_dump($course_module);
// if ($isadmin || $manager==1) {

  $url = new moodle_url("/mod/resource2/view.php", array('id' => $course_module->id, 'forceview' => 1));

  // var_dump($course_module->id);
  $record = $DB->get_record('resource2', array('id' => $id));
  $video = $DB->get_record('vimeo_files2', array('resource2_id' => $id));
  
  if ($update == 1) {
    $typeData=$DB->get_record('reda_video_type2',array('resource2_id'=>$id));
    $type=$typeData->type;
	require_once('submit2_update_html.php');
  } else {
	require_once('submit2_new_html.php');
  }
  $allowedExts = array("mp3", "mp4", "wma");
  $extension = pathinfo($_FILES['url']['name'], PATHINFO_EXTENSION);
  // if ((($_FILES["file"]["type"] == "video/mp4")||($_FILES["file"]["type"] == "video/wma")&& ($_FILES["file"]["size"] < 20000)
  // && in_array($extension, $allowedExts))){
    
  if (isset($_POST['embed_upload_update'])) {

    $ins = new stdClass();
    $ins->id = $video->id;
    if (empty($_POST['embed_update'])) {
      $ins->url = $video->url;
    } else {
      $ins->url = $_POST['embed_update'];
    }
    $ins->id = $DB->update_record('vimeo_files2', $ins);
    $url = new moodle_url("/mod/resource2/view.php", array('id' => $course_module->id, 'forceview' => 1));
    redirect($url);
  } elseif (isset($_POST['embed_upload'])) {
    $ins = new stdClass();
    $ins->name = "test";
    $ins->description = "test";
    $ins->resource2_id = $id;
    $ins->url = $_POST['embed'];
    $ins->id = $DB->insert_record('vimeo_files2', $ins);

    $data = new stdClass();
    $data->resource2_id = $id;
    $data->type = $_POST['video_type'];
    $data->id = $DB->insert_record('reda_video_type2', $data);

    $url = new moodle_url("/mod/resource2/view.php", array('id' => $course_module->id, 'forceview' => 1));

    redirect($url);
  } 

echo $OUTPUT->footer();
