<?php
require_once('../config.php');
// $PAGE->set_url($CFG->wwwroot.'/json/quizreport.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot .'/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/course/externallib.php');
require_once($CFG->dirroot . '/user/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/enrol/externallib.php');

define('PARAM_STRING','string');

$function= optional_param('function',' ',PARAM_RAW);
$quizId = optional_param('id',-1, PARAM_INT);
$courseid = optional_param('courseid',-1, PARAM_INT);
$objectid = optional_param('objectid',-1, PARAM_INT);
$teacherid = optional_param('teacherid',-1, PARAM_INT);
$userid = optional_param('userid',-1, PARAM_INT);

if ($function == 'get_course_teacher'){

	echo get_course_teacher($userid,$courseid);
}
if ($function == 'quiz_report'){

	echo quiz_report($quizId);
}
if ($function == 'course_page_report'){

 echo course_page_report($courseid,$objectid);
}
if ($function == 'course_file_report'){

 echo course_file_report($courseid,$objectid);
}
if ($function == 'course_assign_report'){

	echo course_assign_report($courseid,$objectid);
}
   
if($function == 'check_teacher_has_reports'){
		 
   echo check_teacher_has_reports($teacherid,$courseid);
}
if($function == 'get_teacher_reports_item'){
		 
   echo get_teacher_reports_item($teacherid,$courseid);
}

function get_course_teacher($userId,$courseId){
	global $DB;
	$courses = $DB->get_records_sql("SELECT * FROM mdl_course WHERE category= 10");
	$courses = array_values($courses);
	$final_data = array();
	$teacherCourses = array();
	foreach ($courses as $course){
		
		$courselist = new core_course_list_element($course);
            $overviewfiles = array();
            foreach ($courselist->get_course_overviewfiles() as $file) {
                $fileurl = moodle_url::make_webservice_pluginfile_url($file->get_contextid(), $file->get_component(),
                                                                        $file->get_filearea(), null, $file->get_filepath(),
                                                                        $file->get_filename())->out(false);
                $overviewfiles[] = array(
                    'filename' => $file->get_filename(),
                    'fileurl' => $fileurl,
                    'filesize' => $file->get_filesize(),
                    'filepath' => $file->get_filepath(),
                    'mimetype' => $file->get_mimetype(),
                    'timemodified' => $file->get_timemodified(),
                );
            }
			$course->overviewfiles = $overviewfiles;

        $coursecontacts = array();
        /*foreach ($courselist->get_course_contacts() as $contact) {
            $coursecontacts[] = array(
                'id' => $contact['user']->id,
                'fullname' => $contact['username'],
                'roles' => array_map(function($role){
                    return array('id' => $role->id, 'name' => $role->displayname);
                }, $contact['roles']),
                'role' => array('id' => $contact['role']->id, 'name' => $contact['role']->displayname),
                'rolename' => $contact['rolename']
            );
        }*/
		$teachers = $DB->get_records_sql("SELECT u.id As id,concat(u.firstname , ' ', u.lastname)as teachername, c.fullname As coursename
                           FROM   mdl_course c
                           LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
                           LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
                           LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id 
						   WHERE cx.contextlevel = '50' AND c.id= '$course->id'");
        $teachers = array_values($teachers);
		$teacher = $teachers[0];
		if (!empty($teacher->id)){
			$coursecontacts[]=$teacher;
		}
        $course->contacts = $coursecontacts;
			$teacherCourses[] = $course;
	}
	return json_encode(['data'=>$teacherCourses]);
}

function quiz_report($id){
	global $DB;

	$quiz=$DB->get_record('quiz',array('id'=>$id));

$allquizAttempts = $DB->get_records_sql("SELECT
quiza.id AS quizattemptid,
quiza.userid as userid,
quiza.quiz as quizid
FROM mdl_quiz_attempts quiza
JOIN mdl_user user ON quiza.userid = user.id
WHERE quiza.quiz =$id  and user.deleted = 0 and quiza.state='finished' and quiza.preview = 0
GROUP BY userid ORDER BY quizattemptid
	");

	$final_data = array();
	foreach ($allquizAttempts as $key) {
		$res=new stdClass();
		$attempts = quiz_get_user_attempts($id, $key->userid);
		$bestgrade = quiz_calculate_best_grade($quiz, $attempts);
		//if ($bestgrade != null){
			$records=$DB->get_records_sql('SELECT user.id as userid,concat(user.firstname , " ", user.lastname)as fullname ,user.url as image FROM `mdl_user` as user where user.id = '.$key->userid.' and user.deleted=0 ');
			$records = array_values($records);
			$res->userid = $key->userid;
		    $res->quizid = $id;
		    $res->grade = $bestgrade;
			$res->fullName = $records[0]->fullname;
			$res->image = $records[0]->image;
			$records->quizid = $id;
			$records->grade = $bestgrade;
		    array_push($final_data,$res);
		//}
	   
	}
   return json_encode(['data'=>$final_data]);
}

function course_page_report($courseid,$objectid){
global $DB;
$records=$DB->get_records_sql('SELECT user.id as userid,COUNT(*)as number ,concat(user.firstname , " ", user.lastname)as fullname ,user.url as image FROM `mdl_logstore_standard_log` as logs INNER JOIN mdl_user as user ON logs.userid=user.id where courseid='.$courseid.' AND action="viewed" and objecttable ="page" and user.deleted=0 and objectid='.$objectid.' GROUP BY userid ');
return  json_encode(['data'=>array_values($records)]);


}

function course_file_report($courseid,$objectid){
global $DB;
$records=$DB->get_records_sql('SELECT user.id as userid,COUNT(*)as number ,concat(user.firstname , " ", user.lastname)as fullname ,user.url as image FROM `mdl_logstore_standard_log` as logs INNER JOIN mdl_user as user ON logs.userid=user.id where courseid='.$courseid.' AND action="viewed" and objecttable ="resource" and user.deleted=0 and objectid='.$objectid.' GROUP BY userid');
return  json_encode(['data'=>array_values($records)]);


}

function course_assign_report($courseid,$objectid){
	global $DB;
	$records=$DB->get_records_sql('SELECT user.id as userid,COUNT(*)as number ,concat(user.firstname , " ", user.lastname)as fullname ,user.url as image FROM `mdl_logstore_standard_log` as logs INNER JOIN mdl_user as user ON logs.userid=user.id where courseid='.$courseid.' AND action="viewed" and objecttable ="assign" and user.deleted=0 and objectid='.$objectid.' GROUP BY userid');
	return  json_encode(['data'=>array_values($records)]);
	
	
	}
	
	function check_teacher_has_reports($teacherid,$courseid){
	global $DB,$OUTPUT,$CFG;
	$check_availability=$DB->get_record('activities_control',array('course'=>$courseid,'teacher_id'=>$teacherid));
	
	$page=$check_availability->page;
	$file=$check_availability->file;
	$assign=$check_availability->assign;
	$quiz=$check_availability->quiz;
	
	$room=$DB->get_record('course_bbb',array('course'=>$courseid));
				
			
	if ($page == 1 || $file == 1|| $assign == 1 || $quiz==1){
		return json_encode(["activity"=> 'true']);
	}
	
	if (!empty($room)){
		if ($room->bbb == true){
			return json_encode(["activity"=> 'true']);
		}
	}
	return json_encode(["activity"=> 'false']);
}

function get_teacher_reports_item($teacherid,$courseid){
	global $DB,$OUTPUT,$CFG;
	$ins = new stdClass();
	$check_availability=$DB->get_record('activities_control',array('course'=>$courseid,'teacher_id'=>$teacherid));
	$room=$DB->get_record('course_bbb',array('course'=>$courseid));
	
	$ins->page=intval($check_availability->page);
	$ins->file=intval($check_availability->file);
	$ins->assign=intval($check_availability->assign);
	$ins->quiz=intval($check_availability->quiz);
	
	
	if (!empty($room)){
		if ($room->bbb == true){
			$ins->bbb=1;
		}
		else{
			$ins->bbb=0;
		}
	}
	else{
		$ins->bbb=0;
	}
	return json_encode(["reports"=> $ins]);
}


function old_quiz_report($id){
	global $DB;

	
	/*$data = $DB->get_records_sql("SELECT
	ROW_NUMBER() OVER( ORDER BY quiza.userid ) AS rownumber,
	qa.variant, quiza.userid as userid, user.firstname,
	quiza.quiz as quiz, quiza.id AS quizattemptid,
	quiza.attempt, quiza.sumgrades, qa.slot, qa.questionid,
	qa.maxmark, qa.minfraction, qas.sequencenumber, qas.state,
	qas.fraction, (qa.maxmark * qas.fraction) as grade,
	FROM_UNIXTIME(qas.timecreated) As timecreated, qasd.name, ques.name
	FROM mdl_quiz_attempts quiza JOIN mdl_question_usages qu ON qu.id = quiza.uniqueid
	JOIN mdl_question_attempts qa ON qa.questionusageid = qu.id
	JOIN mdl_question_attempt_steps qas ON qas.questionattemptid = qa.id
	JOIN mdl_question ques ON ques.id = qa.questionid
    JOIN mdl_user user ON quiza.userid = user.id
	LEFT JOIN mdl_question_attempt_step_data qasd ON qasd.attemptstepid = qas.id
	WHERE quiza.quiz = $id and qas.sequencenumber = 2 and user.deleted = 0
	ORDER BY quiza.userid, quiza.attempt, qa.slot, qas.sequencenumber, qasd.name");*/

	

$quiz=$DB->get_record('quiz',array('id'=>$id));

$allquizAttempts = $DB->get_records_sql("SELECT
quiza.id AS quizattemptid,
quiza.userid as userid,
quiza.quiz as quizid
FROM mdl_quiz_attempts quiza
JOIN mdl_user user ON quiza.userid = user.id
WHERE quiza.quiz =$id  and user.deleted = 0 and quiza.state='finished'
GROUP BY userid ORDER BY quizattemptid
	");


	$userAttemptsArray = array();
	$final_data = array();
	foreach ($allquizAttempts as $key) {
		$res=new stdClass();
		$attempts = quiz_get_user_attempts($id, $key->userid);
		$bestgrade = quiz_calculate_best_grade($quiz, $attempts);
		//return json_encode(['data'=>$attempts,'grade'=>$bestgrade]);
		
		$res->userid = $key->userid;
		$res->quizid = $id;
		$res->grade = $bestgrade;
		array_push($final_data,$res);
		/*$alluserAttempts = $DB->get_records_sql("SELECT
	    quiza.id AS quizattemptid, quiza.attempt AS attempt,
        quiza.userid as userid, CONCAT(user.firstname ,' ',user.lastname) as fullname, user.url,
	    quiza.quiz as quizid,quiza.sumgrades as grade
	    FROM mdl_quiz_attempts quiza
        JOIN mdl_user user ON quiza.userid = user.id
	    WHERE quiza.quiz =$id  and user.deleted = 0 and quiza.state='finished' and quiza.userid = '.$key->userid.'
        ORDER BY quiza.attempt
	   ");*/
	   
	}
   return json_encode(['data'=>$final_data]);




if($quiz->grademethod=="4"){
	/*$data = $DB->get_records_sql("SELECT
	quiza.id AS quizattemptid,
    quiza.userid as userid, CONCAT(user.firstname ,' ',user.lastname) as fullname, user.url,
	quiza.quiz as quizid,MAX(quiza.attempt) as max_value
	FROM mdl_quiz_attempts quiza
    JOIN mdl_user user ON quiza.userid = user.id
	WHERE quiza.quiz =$id  and user.deleted = 0 and quiza.state='finished'
	GROUP BY userid ORDER BY quiza.userid, quiza.attempt
	");
	$final_data = array();
	foreach ($data as $key) {
	$data2=$DB->get_records_sql("SELECT 
	     quiza.id AS quizattemptid,quiza.userid as
		 userid, CONCAT(user.firstname ,' ',user.lastname) as fullname, user.url,
	     quiza.quiz
	FROM mdl_quiz_attempts  as quiza 
	JOIN mdl_user user ON quiza.userid = user.id 
	WHERE quiza.attempt=$key->max_value	and quiza.quiz=$id");
	array_push($final_data,$data2);
	}
	
	if (empty($data2)){
		return json_encode(['data'=>'No Data']);
	}
	return json_encode(['data'=>array_values($data2)]);*/

	$allquizAttempts = $DB->get_records_sql("SELECT
	quiza.id AS quizattemptid,
    quiza.userid as userid,
	quiza.quiz as quizid
	FROM mdl_quiz_attempts quiza
	WHERE quiza.quiz =$id  and user.deleted = 0 and quiza.state='finished'
	GROUP BY userid ORDER BY quiza.userid, quiza.attempt
	");

	$userAttemptsArray = array();
	$final_data = array();
	foreach ($allquizAttempts as $key) {
		$res=new stdClass();
		$attempts = quiz_get_user_attempts($id, $key->userid);
		$bestgrade = quiz_calculate_best_grade($quiz, $attempts);

		$res->userid = $key->userid;
		$res->quizid = $id;
		$res->grade = $bestgrade;
		array_push($final_data,$res);
		/*$alluserAttempts = $DB->get_records_sql("SELECT
	    quiza.id AS quizattemptid, quiza.attempt AS attempt,
        quiza.userid as userid, CONCAT(user.firstname ,' ',user.lastname) as fullname, user.url,
	    quiza.quiz as quizid,quiza.sumgrades as grade
	    FROM mdl_quiz_attempts quiza
        JOIN mdl_user user ON quiza.userid = user.id
	    WHERE quiza.quiz =$id  and user.deleted = 0 and quiza.state='finished' and quiza.userid = '.$key->userid.'
        ORDER BY quiza.attempt
	   ");*/
	   
	}

}
elseif($quiz->grademethod=="3"){
	$data = $DB->get_records_sql("SELECT
	quiza.id AS quizattemptid,
    quiza.userid as userid, CONCAT(user.firstname ,' ',user.lastname) as fullname, user.url,
	quiza.quiz as quizid,MIN(quiza.attempt) as min_value
	FROM mdl_quiz_attempts quiza
    JOIN mdl_user user ON quiza.userid = user.id
	WHERE quiza.quiz =$id  and user.deleted = 0 and quiza.state='finished'
	GROUP BY userid ORDER BY quiza.userid, quiza.attempt
	");
	
	$final_data = array();
	foreach ($data as $key) {
		$data2=$DB->get_records_sql("SELECT 
		 quiza.id as quizattemptid,quiza.userid as
		 userid, CONCAT(user.firstname ,' ',user.lastname) as fullname, user.url,
	     quiza.quiz FROM mdl_quiz_attempts  as quiza JOIN mdl_user user ON quiza.userid = user.id
	     WHERE quiza.attempt='.$key->min_value.' and quiza.quiz='.$id.'");
		 
		 array_push($final_data,$data2);
		 
		
		}
		if (empty($final_data)){
			return json_encode(['data'=>'No Data']);
		}
		return json_encode(['data'=>array_values($final_data)]);

}
elseif($quiz->grademethod=="2"){
	$data = $DB->get_records_sql("SELECT
	quiza.id AS quizattemptid,
	AVG(quiza.sumgrades) as grade,
    quiza.userid as userid, CONCAT(user.firstname ,' ',user.lastname) as fullname, user.url,
	quiza.quiz as quizid,quiza.attempt
	FROM mdl_quiz_attempts quiza
    JOIN mdl_user user ON quiza.userid = user.id
	WHERE quiza.quiz =$id  and user.deleted = 0 and quiza.state='finished'
	GROUP BY userid  ORDER BY quiza.userid, quiza.attempt
	");
	
	if (empty($data)){
		return json_encode(['data'=>'No Data']);
	}
	
	return json_encode(['data'=>array_values($data)]);

}
elseif($quiz->grademethod=="1"){
	$data = $DB->get_records_sql("SELECT
	quiza.id AS quizattemptid,
	MAX(quiza.sumgrades) as grade,
    quiza.userid as userid, CONCAT(user.firstname ,' ',user.lastname) as fullname, user.url,
	quiza.quiz as quizid,quiza.attempt
	FROM mdl_quiz_attempts quiza
    JOIN mdl_user user ON quiza.userid = user.id
	WHERE quiza.quiz =$id  and user.deleted = 0 and quiza.state='finished'
	GROUP BY userid  ORDER BY quiza.userid, quiza.attempt
	");
	
	if (empty($data)){
		return json_encode(['data'=>'No Data']);
	}
	
	return json_encode(['data'=>array_values($data)]);

}
return json_encode(['data'=>'No Data']);

}


?>
