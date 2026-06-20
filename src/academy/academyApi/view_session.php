<?php
require_once('../config.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/academyApi/api.php');
require_once($CFG->dirroot . '/course/externallib.php');
$PAGE->set_title("View Session");
$PAGE->set_heading("View Session");
$sharedSecret='IWTfvHyGkpDln3jZbwoYvQ2JJgW9pgCUfSCarQ5eYw';
session_start();
$course=$_GET['course'];
$create=$_SESSION['create'];
$name="Session";

if($create==1){
    $response=create_new_bbb_session($course,$USER->id);

    }

else{

    $response=join_moderator($course,$USER->id);

 }

echo $OUTPUT->header();
// $_SESSION['meetingID']=$meetingID;
// $_SESSION['moderatorPW']=$moderatorPW;
// $_SESSION['attendeePW']=$attendeePW;
// $_SESSION['course']=$course;
unset($_SESSION['create']);
$course_data=$DB->get_record('course',array('id'=>$course));
    $admins = get_admins();
    $isadmin = false;
    foreach($admins as $admin) {
      if ($USER->id == $admin->id) {
          $isadmin = true;
          break;
      }
    }
    $report1=array();
    $report2=array();
    $result1=array();
    $result2=array();
    $teachers = $DB->get_records_sql("SELECT u.firstname AS name, u.url as picture,u.description,u.email,ra.contextid,u.id As id, c.fullname as coursename
    FROM   mdl_course c
    LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
    LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
    LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id WHERE cx.contextlevel = '50' AND c.id= ".$course.";");
    $teach=0;
    foreach ($teachers as $teacher) {
      $teach=$teacher->id;
    }
if($USER->id==$teach||$isadmin){

    echo '<form action="end_view_session.php?course='.$course.'" method="post">
    <button class="btn btn-primary" type="submit" name="submit">'.get_string("end_meeting","theme_edumy").'</button>
       </form>';
       $counter=0;
   
    //    echo $_SESSION['course_data'];
       if(isset($_POST['submit'])){
        // $_SESSION['course_data']=$_SESSION["course_id"];

        $_SESSION['view_end_course_id']=$course;
 redirect($CFG->wwwroot.'end_view_session.php?course='.$course.'');
       }
       echo '<div class="row ">
       <iframe src="'.$response.'" height="500" width="300" name="frame1" allowfullscreen allow="camera; microphone" title="Iframe Example" id="myIframe" ></iframe>
       </div>
       <script>
       $( document ).ready(function() {
      
       });
       </script>
   
       ';
       insert_create_time($course);

}
else{
    redirect($CFG->wwwroot.'/course/view.php?id='.$course.'');
}



    echo $OUTPUT->footer();
    

