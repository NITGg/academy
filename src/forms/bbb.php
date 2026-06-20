<?php
require_once('../config.php');
$PAGE->set_pagelayout('site');
$PAGE->set_title("Add Permissions for Activities");
$PAGE->set_heading("Add Permissions for Activities");
 $PAGE->requires->js( new moodle_url($CFG->wwwroot . '/forms/script.js') );
echo $OUTPUT->header();
$admins = get_admins();
$isadmin = false;
foreach($admins as $admin) {
  if ($USER->id == $admin->id) {
      $isadmin = true;
      break;
  }
}
$teachers=$DB->get_records_sql('SELECT distinct u.username, u.firstname, u.lastname,u.id as userid
FROM mdl_course as c, mdl_role_assignments AS ra, mdl_user AS u, mdl_context AS ct
WHERE c.id = ct.instanceid AND ra.roleid =3 AND ra.userid = u.id AND ct.id = ra.contextid;');
$roleassignments = $DB->get_records('role_assignments', ['userid' => $USER->id]);
$manager=0;
foreach($roleassignments as $role){
  if($role->roleid==1){
    $manager=1;
    break;
  }
}
if($isadmin||$manager==1){
echo'<div class="row"><form action="bbb.php" method="post" id="first_form" class="col lg-6">
<div class="form-group">
<label for="teacher">'.get_string('select_from_dropdown','theme_edumy').'</label>
<select  class="form-control" id="teacher" name="teacher">
<option value="0">'.get_string('select_teacher','theme_edumy').'</option>';
foreach($teachers as $teacher){
    echo '<option value="'.$teacher->userid.'">'.$teacher->firstname.' '.$teacher->lastname.' </option>';
}
echo'</select>
</div>

</form>';


// if(isset($_POST['button1'])){
    // $result = $_POST['teacher'];
    // $result_explode = explode(',', $result);
    // $teacher_id=  $result_explode[0];
    // $course=  $result_explode[1];
    // $_SESSION['course_data']=$course;
    // $_SESSION['teacher_id']=$teacher_id;
    // redirect($CFG->wwwroot.'/forms/bbb2.php');
    // echo'<script>
    // $( document ).ready(function() {
    //   $("#first_form").hide();
    //   $("#second_form").show();
    // });
    // </script>';
    if(isset($_POST['teacher'])){
      $teacher = $_POST['teacher'];
      $_SESSION['teacher_id']=$teacher;
      $courses=$DB->get_records_sql('SELECT distinct c.id as cid, c.fullname as cname
      FROM mdl_course as c, mdl_role_assignments AS ra, mdl_user AS u, mdl_context AS ct
      WHERE c.id = ct.instanceid AND ra.roleid =3 AND ra.userid = u.id AND ct.id = ra.contextid AND u.id='.$teacher.' ');
    }
    else{
      $courses=array();
    }

echo'<form  action="bbb.php" method="post" id="second_form" style="" class="col lg-6">
<div class="form-group">
<label for="course">'.get_string('select_from_dropdown','theme_edumy').'</label>
<select  class="form-control" id="course" name="course">';
if(empty($courses)){
  echo '<option value="0">'.get_string('select_teacher2','theme_edumy').'</option>';
}
else{
  echo '<option value="all">'.get_string('all','theme_edumy').'</option>';

  foreach($courses as $course){

    echo '<option value="'.$course->cid.'">'.$course->cname.' </option>';
  }
}

echo'</select>
<button class="btn btn-primary" type="submit" name="button2" id="submit_teacher" >'.get_string('next','theme_edumy').'</button>

</form>
</div>

';

echo '<script>$(document).ready(function(){
  $("#teacher").change(function() {
     $("#first_form").submit();
  });
 
   
      if (  $("#course option").val() != "0") {
        $("#submit_teacher").prop("disabled", false);
      }
      else{
        $("#submit_teacher").prop("disabled", true);
      }   


});
</script>
';
if(isset($_POST['button2'])){

  $_SESSION['course_data']=$_POST['course'];

    redirect($CFG->wwwroot.'/forms/bbb2.php?teacher='.$_SESSION['teacher_id'].'&course='.$_POST['course'].'');
  

}
}
else{
  echo '<div class="alert alert-danger" role="alert">
 You Have to log in .
</div>';
} 


echo $OUTPUT->footer();
