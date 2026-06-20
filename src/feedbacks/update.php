<?php

require_once('../config.php');

$PAGE->set_context(get_system_context());
 $PAGE->set_pagelayout('site');
$PAGE->set_title("Update Feadbacks Page ");
$PAGE->set_heading("Update Feadbacks page");
$PAGE->set_url($CFG->wwwroot.'/feadbacks/update.php');
echo $OUTPUT->header();
$q=[];



if (isset($_GET["id"])) {
    $id=$_GET["id"];

    $q=$DB->get_records_sql("SELECT * FROM mdl_feedbacks where id='$id'");
  }
  $courses = $DB->get_records_sql("SELECT * FROM mdl_course");
$teachers=$DB->get_records_sql("SELECT DISTINCT u.*  FROM mdl_user as u INNER JOIN mdl_role_assignments as role ON role.userid=u.id and role.roleid=3");
  foreach($q as $data){
    echo '<body>
    <h1>Update Promocode</h1>
    <form  action="update.php" method="post">
    <div class="form-group" style="display:none;">
    <label for="id">Feadback Id</label>
    <input  name="id" class="form-control"value="'. $data->id.'">
    </div>
    <div class="form-group"style="display:none;">
<label for="user">Feadback  user</label>
<input  name="user"class="form-control" value="'. $data->user.'">
</div>
    <div class="form-group">
<label for="title">Feadback Title </label>
<input  name="title"class="form-control" value="'. $data->title.'" id="title" >
</div>
<div class="form-group">
<label for="feedback">Feadback  </label>
<input  name="feedback"class="form-control" value="'. $data->feedback.'" id="feedback" required >
</div>
<div class="form-group">
<label for="course">Feadback  Related Course </label>
<select name="course"class="form-control"  id="course">';

$admins = get_admins();
$isadmin = false;
foreach($admins as $admin) {
  if ($USER->id == $admin->id) {
      $isadmin = true;
      break;
  }
}

      foreach($courses as $course){
          $context = context_course::instance($course->id);
          $roles = get_user_roles($context, $USER->id, true);
          $role = key($roles);
          $rolename = $roles[$role]->shortname;
          if($rolename == "student"||$isadmin ||$rolename == "teacher"||$rolename == "editingteacher"){
            echo '<option value="'.$course->id.'"';
            if($course->id== $data->course)
            {echo "selected";}
            echo'>'.$course->fullname.'</option>';
          }
         
      }


echo'</select>
</div>
<div class="form-group">
<label for="teacher_id">Feadback  Related Course </label>
<select name="teacher_id"class="form-control" id="teacher_id">';
$admins = get_admins();
$isadmin = false;
foreach($admins as $admin) {
  if ($USER->id == $admin->id) {
      $isadmin = true;
      break;
  }
}if($isadmin){
  foreach($teachers as $teacher){
      
      echo '<option value="'.$teacher->id.'">'.$teacher->username.'</option>';
  }
}
else{
  foreach($courses as $course){
      $context = context_course::instance($course->id);
      $roles = get_user_roles($context, $USER->id, true);
      $role = key($roles);
      $rolename = $roles[$role]->shortname;
      $t_id=0;
      if($rolename == "editingteacher" || $rolename == "teacher" || $rolename == "student"){
          $teachers2 = $DB->get_records_sql("SELECT CONCAT(u.firstname, ' ', u.lastname)  AS name, u.id as id
          FROM   mdl_course c 
         LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid 
         LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3' 
         LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id WHERE cx.contextlevel = '50' AND c.id= '$course->id';");
         foreach($teachers2 as $teacher)
     { echo '<option value="'.$teacher->id.'">'.$teacher->name.'</option>';
    $t_id=$teacher_id;
    }
       
      }
     
  }
}
   echo'</select></div>
   <button type="submit" class="btn btn-primary mr-2" onClick="">Update</button>
   </form>
    </body>';
  }


  if (isset($_POST["id"]) && $_POST['feedback'] != null   ) {
      $id=$_GET["id"];

        $ins = new stdClass();
      $ins->id=$_POST["id"];
      $ins->user = $_POST["user"];
      $ins->title = $_POST["title"];
      $ins->feedback = $_POST["feedback"];
      $ins->course = $_POST["course"];
      $ins->teacher_id = $_POST["teacher_id"];
      $ins->id = $DB->update_record('feedbacks', $ins);
      redirect($CFG->wwwroot."/teacherprofile/profile.php?id=". $_POST["teacher_id"]."");
    
      }
echo $OUTPUT->footer();

?>