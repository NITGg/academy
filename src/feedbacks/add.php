<?php

require_once('../config.php');

$PAGE->set_context(get_system_context());
 $PAGE->set_pagelayout('site');
$PAGE->set_title("Add Feedback ");
$PAGE->set_heading("Add Feedback ");
echo $OUTPUT->header();
$teachers=$DB->get_records_sql("SELECT DISTINCT u.*  FROM mdl_user as u INNER JOIN mdl_role_assignments as role ON role.userid=u.id and role.roleid=3");
$courses = $DB->get_records_sql("SELECT * FROM mdl_course ");
$id=$_GET['id'];


$userEnroledCourses = enrol_get_users_courses($id);

$id=$_GET['id'];
echo'<body>
<h1>Add A Feedback</h1>
<form  action="add.php" method="post">
<div class="form-group" >
  <label for="title">Feadback Title </label>
  <input  name="title" class="form-control"placeholder="Please enter the a title" id="title">
  </div>
<div class="form-group" >
  <label for="feedback">Feadback </label>
  <input  name="feedback" class="form-control"placeholder="Please enter the a Feadback" id="feadback" required>
  </div>
  <div class="form-group" >
  <label for="feedback">Choose a Course</label>
    <select class="form-control" name="course">';
    $admins = get_admins();
  $isadmin = false;
foreach($admins as $admin) {
    if ($USER->id == $admin->id) {
        $isadmin = true;
        break;
    }
}

    // foreach($courses as $course){
    //     $context = context_course::instance($course->id);
    //     $roles = get_user_roles($context, $id, true);
    //     $role = key($roles);
    //     $rolename = $roles[$role]->shortname;
    //     if($rolename == "student"||$isadmin||$rolename == "teacher"||$rolename == "editingteacher" ){
    //         echo '<option value="'.$course->id.'">'.$course->fullname.'</option>';
    //     }
       
    // }
    
foreach($userEnroledCourses as $course){
    // $context = context_course::instance($course->id);
    // $roles = get_user_roles($context, $id, true);
    // $role = key($roles);
    // $rolename = $roles[$role]->shortname;
    
    echo '<option value="'.$course->id.'">'.$course->fullname.'</option>';
    
   
}

    
    echo'</select>
  </div>
  <div class="form-group" >
  <label for="feedback">Choose a Teacher</label>
    <select class="form-control"name="teacher_id">';
        $admins = get_admins();
  $isadmin = false;
foreach($admins as $admin) {
    if ($USER->id == $admin->id) {
        $isadmin = true;
        break;
    }
}

$get_id=$DB->get_records_sql("SELECT  CONCAT(u.firstname, ' ', u.lastname)  AS name, u.id as id from mdl_user as u where id='$id'");

    // foreach($get_id as $teacher){
        
    //     echo '<option value="'.$teacher->id.'">'.$teacher->username.'</option>';
    // }


    
           foreach($get_id as $teacher)
       { echo '<option value="'.$teacher->id.'">'.$teacher->name.'</option>';}
         
        
       
    


    echo'</select>
  </div>
  <button type="submit" class="btn btn-primary mr-2" onClick="">Add</button>
</form>
</body>';
if (isset($_POST["feedback"]) && $_POST['feedback'] != null  ) {
    $id=$_GET['id'];

    $ins = new stdClass();
    $ins->feedback = $_POST["feedback"];
    $ins->title = $_POST["title"];
    $ins->course = $_POST["course"];
    $ins->teacher_id = $_POST["teacher_id"];
    $ins->user = $USER->id;
    $ins->id = $DB->insert_record('feedbacks', $ins);
    redirect($CFG->wwwroot.'/teacherprofile/profile.php?id='.$_POST["teacher_id"]);
}
echo $OUTPUT->footer();
?>