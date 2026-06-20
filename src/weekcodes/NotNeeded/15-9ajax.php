<?php
require_once("../config.php");
require_once($CFG->dirroot . '/lib/enrollib.php');
if (isset($_POST['teacher'])) {
    $teacherID=$_POST['teacher'];
    $userEnroledCourses = enrol_get_users_courses($teacherID);
echo json_encode(array_values( $userEnroledCourses));
}
else if(isset($_POST['course'])){
    // $data=json_decode(course_content($_POST['course']));
    // $returnArr=array();
    // for($i=0;$i<count($data['contents']);$i++){
    //    $returnArr[$i]=$data['contents'][$i]->name;
    // }
    try{
        // $data=get_course_contents_data($_POST['course'],array());
        // echo json_encode($data);
        $groups=$DB->get_records('groups',array('courseid'=>$_POST['course']));
        echo json_encode($groups);
    }
catch(Exception $e){
    echo $e->getMessage();
}
}

 
