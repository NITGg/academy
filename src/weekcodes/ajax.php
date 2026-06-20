<?php
require_once("../config.php");
require_once($CFG->dirroot . '/lib/enrollib.php');


if (isset($_POST['teacher'])) {
    $teacherID=$_POST['teacher'];
    $userEnroledCourses = enrol_get_users_courses($teacherID);//Get teacher enrolled courses
echo json_encode(array_values( $userEnroledCourses));//fill course drop down list
}

//Get groups in specisic course
else if(isset($_POST['course'])){
    try{
        $groups=$DB->get_records('groups',array('courseid'=>$_POST['course']));
        echo json_encode($groups);
    }
catch(Exception $e){
    echo $e->getMessage();
}
}

 
