<?php
require_once('../../config.php');
header('Content-Type: application/json');

// $PAGE->set_url($CFG->wwwroot.'/json/quizreport.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/course/externallib.php');
require_once($CFG->dirroot . '/user/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/enrol/externallib.php');
require_once($CFG->dirroot . '/course/externallib.php');

define('PARAM_STRING', 'string');

$function = optional_param('function', ' ', PARAM_RAW);
$token = optional_param('token', ' ', PARAM_RAW);

$quizId = optional_param('id', -1, PARAM_INT);
$courseid = optional_param('courseid', -1, PARAM_INT);
$objectid = optional_param('objectid', -1, PARAM_INT);
$teacherid = optional_param('teacherid', -1, PARAM_INT);
$userid = optional_param('userid', -1, PARAM_INT);
$option = optional_param('option', ' ', PARAM_RAW);
$quizids = optional_param('quizids', array(), PARAM_INT);
$physical = optional_param("physical", " ", PARAM_RAW);
$man = optional_param("man", " ", PARAM_RAW);
$model = optional_param("model", " ", PARAM_RAW);
$aspects = optional_param("aspects", " ", PARAM_RAW);
$deviceid = optional_param("deviceid", " ", PARAM_RAW);
if ($function == 'quiz_report') {

	echo quiz_report($quizId);
} else if ($function == 'course_page_report') {

	echo course_page_report($courseid, $objectid);
} else if ($function == 'course_file_report') {

	echo course_file_report($courseid, $objectid);
} else if ($function == 'course_assign_report') {

	echo course_assign_report($courseid, $objectid);
} else if ($function == 'check_teacher_has_reports') {

	echo check_teacher_has_reports($teacherid, $courseid);
} else if ($function == 'get_teacher_reports_item') {

	echo get_teacher_reports_item($teacherid, $courseid);
} else if ($function == 'all_quizes') {
	echo all_quizes($courseid);
} else if ($function == 'all_exams') {
	echo all_exams($courseid);
} else if ($function == 'quiz_report_course') {
	echo quiz_report_course($quizId, $option);
} else if ($function == 'course_report_quiz_user') {

	echo course_report_quiz_user($courseid, $userid);
} else if ($function == 'download_page_report') {

	echo download_page_report($courseid, $objectid, $option);
} else if ($function == 'download_page_report_again') {

	echo download_page_report_again($courseid, $objectid, $option);
} elseif ($function == 'download_resource_report') {

	echo download_resource_report($courseid, $objectid, $option);
} elseif ($function == 'download_resource_report_again') {

	echo download_resource_report_again($courseid, $objectid, $option);
} elseif ($function == 'course_report_quiz_user_average') {

	echo course_report_quiz_user_average($courseid, $option, $quizids);
} elseif ($function == 'device_information') {
	echo device_information($userid, $deviceid, $aspects, $model, $man, $physical);
}
elseif($function=="pdfReport"){
	echo pdfReport($objectid);
}
elseif($function=="allPdfs"){
	echo allPdfs($courseid);
}
elseif ($function == "assignReport") {
	echo assignReport($objectid, $token, $option);
}
elseif ($function == "last_access_users") {
	echo last_access_users($courseid);
}
function all_quizes($courseid)
{
	global $DB;
	$records = $DB->get_records_sql("SELECT q.* FROM mdl_quiz as q
	join mdl_course_modules as cm ON cm.instance=q.id
 where cm.course=$courseid and reviewcorrectness<>0 and reviewspecificfeedback<>0 and
  reviewgeneralfeedback<>0 and reviewrightanswer<>0 and reviewoverallfeedback<>0 and cm.module=17 and cm.deletioninprogress=0");
	return  json_encode(['data' => array_values($records)]);
}
function all_exams($courseid)
{
	global $DB;
	$records = $DB->get_records_sql("SELECT q.* FROM mdl_quiz as q
	join mdl_course_modules as cm ON cm.instance=q.id
	where cm.course=$courseid and q.reviewcorrectness=0 and q.reviewspecificfeedback=0 and
	 q.reviewgeneralfeedback=0 and q.reviewrightanswer=0 and q.reviewoverallfeedback=0 and cm.module=17 and cm.deletioninprogress=0");
	return  json_encode(['data' => array_values($records)]);
}
// function quiz_report_course($id)
// {
// 	global $DB,$CFG;
// 	$quiz = $DB->get_record('quiz', array('id' => $id));
// 	$enrolled_students = $DB->get_records_sql("SELECT u.id As id,concat(u.firstname , ' ', u.lastname)as studentname, c.fullname As coursename
//  			FROM mdl_course c INNER JOIN mdl_context cx ON c.id = cx.instanceid 
// 			 INNER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
//  			 INNER JOIN mdl_user u ON ra.userid = u.id WHERE cx.contextlevel = '50' AND c.id=$quiz->course");

// 	$allquizAttempts = $DB->get_records_sql("SELECT quiza.id AS quizattemptid, quiza.userid as userid, quiza.quiz as quizid, quiza.state as state FROM mdl_quiz_attempts quiza JOIN mdl_user user ON quiza.userid = user.id WHERE quiza.quiz =$id and user.deleted = 0 and quiza.preview = 0 GROUP BY userid ORDER BY quizattemptid

// 					");
// 	$final_data = array();
// 	$inprogress = array();
// 	$res = new stdClass();
// 	$ids = array();
// 	$notsubmitted = array();
// 	foreach ($enrolled_students as $en_s) {
// 		if(!empty($allquizAttempts)){
// 			foreach ($allquizAttempts as $key) {
// 				if ($en_s->id == $key->userid) {

// 					$attempts = quiz_get_user_attempts($id, $key->userid);
// 					$bestgrade = quiz_calculate_best_grade($quiz, $attempts);
// 					$res->state = $key->state;
// 					$res->userid = $key->userid;
// 					$res->quizid = $id;
// 					$res->fullname = $en_s->studentname;
// 					if(!empty($en_s->image)){
// 						$res->image=$CFG->wwwroot . '/theme/edumy/images/teachers/' . $en_s->image . '';

// 					}
// 					else{
// 						$res->image="";
// 					}
// 					if ($key->state == "inprogress") {
// 						$res->grade = "0";
// 						$inprogress[] = $res;
// 						break;
// 					} else {
// 						$res->grade = $bestgrade;
// 						$final_data[] = $res;
// 						break;
// 					}

// 				} else {
// 					array_push($ids, $en_s->id);
// 					break;
// 				}
// 			}
// 		}
// 		else {
// 					array_push($ids, $en_s->id);

// 				}

// 	}
// 	foreach ($ids as $ida) {
// 		$record = $DB->get_record('user', array('id' => $ida));
// 		$data = new stdClass();
// 		$data->state = "notsubmitted";
// 		$data->userid = $ida;
// 		$data->quiz = $id;
// 		$data->fullname = $record->firstname . " " . $record->lastname;
// 		if(!empty($record->url)){
// 			$data->image=$CFG->wwwroot . '/theme/edumy/images/teachers/' . $record->url . '';

// 		}
// 		else{
// 			$data->image="";
// 		}

// 		$notsubmitted[] = $data;
// 	}

// 	return json_encode(['submitted' => $final_data, 'inprogress' => $inprogress, 'notsubmitted' => array_values($notsubmitted)]);
// }
// function compare_some_objects($a, $b) { // Make sure to give this a more meaningful name!
//     return $b->id - $a->id;
// }
function get_members_array($groupID)
{
	global $DB;
	$members = array();
	/*
    SELECT $fields
                                   FROM {user} u
                                     INNER JOIN {groups_members} gm ON u.id = gm.userid
                                     INNER JOIN {groupings_groups} gg ON gm.groupid = gg.groupid
                                  WHERE  gg.groupingid = ?
                               ORDER BY $sort", array($groupingid));
    */

	$members = $DB->get_records_sql("SELECT u.id As id,concat(u.firstname , ' ', u.lastname)as fullname,u.url as image,gm.timeadded as added,op.empty as center_name ,op.school as school_name
  FROM mdl_user u
  INNER join mdl_optional_data_aibrahim as op ON op.userid=u.id

    INNER JOIN mdl_groups_members gm ON u.id = gm.userid WHERE  gm.groupid =   $groupID ORDER BY u.id ASC");
	$members = array_values($members);
	foreach ($members as $member) {
		$member->added = date('m/d/Y H:i:s', $member->added);
	}
	return $members;
}
function quiz_report_course($id, $option)
{
	global $DB, $CFG;
	$quiz = $DB->get_record('quiz', array('id' => $id));
	// $group = $DB->get_record('groups', array('courseid' => $quiz->course, "name" => "Enrolled Users"));

	// $group_members = get_members_array($group->id);
	if ($option == "atoz") {
		$option = "order by studentname ASC";
		$enrolled_students = $DB->get_records_sql("SELECT u.id As id,concat(u.firstname , ' ', u.lastname)as studentname, c.fullname As coursename
		FROM mdl_course c INNER JOIN mdl_context cx ON c.id = cx.instanceid 
		INNER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
		 INNER JOIN mdl_user u ON ra.userid = u.id WHERE cx.contextlevel = '50' AND c.id=$quiz->course $option");
	} elseif ($option == "ztoa") {
		$option = "order by studentname DESC";
		$enrolled_students = $DB->get_records_sql("SELECT u.id As id,concat(u.firstname , ' ', u.lastname)as studentname, c.fullname As coursename
		FROM mdl_course c INNER JOIN mdl_context cx ON c.id = cx.instanceid 
		INNER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
		 INNER JOIN mdl_user u ON ra.userid = u.id WHERE cx.contextlevel = '50' AND c.id=$quiz->course $option");
	} else {
		$enrolled_students = $DB->get_records_sql("SELECT u.id As id,concat(u.firstname , ' ', u.lastname)as studentname, c.fullname As coursename
		FROM mdl_course c INNER JOIN mdl_context cx ON c.id = cx.instanceid 
		INNER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
		 INNER JOIN mdl_user u ON ra.userid = u.id WHERE cx.contextlevel = '50' AND c.id=$quiz->course ");
	}



	$allquizAttempts = $DB->get_records_sql("SELECT quiza.id AS quizattemptid, quiza.userid as userid, quiza.quiz as quizid, quiza.state as state 
	FROM mdl_quiz_attempts quiza JOIN mdl_user user ON quiza.userid = user.id 
	WHERE quiza.quiz =$id and user.deleted = 0 GROUP BY userid ORDER BY quizattemptid");

	$final_data = array();
	$inprogress = array();
	$res = array();
	$ids = array();
	$notsubmitted = array();
	$submitted = array();
	foreach ($enrolled_students as $en_s) {
		$flag = 0;
		if (!empty($allquizAttempts)) {
			foreach ($allquizAttempts as $key) {
				if ($en_s->id == $key->userid) {
					if ($key->state == "inprogress") {
						$flag = 1;
					} elseif ($key->state == "finished") {
						$flag = 2;
					}
				}
			}
		}
		if ($flag == 1) {
			array_push($inprogress, $en_s->id);
		} elseif ($flag == 2) {
			array_push($submitted, $en_s->id);
		} else {
			array_push($notsubmitted, $en_s->id);
		}
		// array_push($ids,$en_s->id);

	}

	foreach ($inprogress as $inprog) {
		$record = $DB->get_record('user', array('id' => $inprog));
		$data = new stdClass();
		$data->state = "inprogress";
		$data->userid = $inprog;
		$data->quiz = $id;
		$data->fullname = $record->firstname . " " . $record->lastname;
		// if (!empty($record->url)) {
		// 	$data->image = $CFG->wwwroot . '/theme/edumy/images/teachers/' . $record->url . '';
		// } else {
		// 	$data->image = "";
		// }
		$center = $DB->get_record('optional_data_aibrahim', array('userid' => $inprog));
		if(empty($center->empty))
		{
			$data->centername ='';
		}
		else{
			$data->centername = $center->empty;
		}
		if(empty($center->school )){
			$data->schoolname ='';
		}
		else{
			$data->schoolname = $center->school;
		}
	 //center name
		$res[] = $data;
	}
	foreach ($submitted as $sub) {
		$record = $DB->get_record('user', array('id' => $sub));
		$attempts = quiz_get_user_attempts($id, $sub);
		$bestgrade = quiz_calculate_best_grade($quiz, $attempts);
		$data = new stdClass();
		$data->state = "submitted";
		$data->userid = $sub;
		$data->quiz = $id;
		$time = array_values($attempts);
		$data->timefinished = date('m/d/Y H:i:s', $time[0]->timefinish);
		$data->fullname = $record->firstname . " " . $record->lastname;
		if ($bestgrade != null) {
			$data->grade = round($bestgrade, 2) . '/' . round($quiz->grade, 2);
		} else {
			$data->grade = "";
		}
		// if (!empty($record->url)) {
		// 	$data->image = $CFG->wwwroot . '/theme/edumy/images/teachers/' . $record->url . '';
		// } else {
		// 	$data->image = "";
		// }
		$center = $DB->get_record('optional_data_aibrahim', array('userid' => $sub));
		if(empty($center->empty))
		{
			$data->centername ='';
		}
		else{
			$data->centername = $center->empty;
		}
		if(empty($center->school )){
			$data->schoolname ='';
		}
		else{
			$data->schoolname = $center->school;
		}
		// $data->centername = $center->empty; //center name
		// $data->schooname = $center->school;
		$ids[] = $data;
	}

	foreach ($notsubmitted as $notsub) {
		$flag = 0;


		$record = $DB->get_record('user', array('id' => $notsub));
		$data = new stdClass();
		$data->state = "notsubmitted";
		$data->userid = $notsub;
		$data->quiz = $id;
		$data->fullname = $record->firstname . " " . $record->lastname;
		// if (!empty($record->url)) {
		// 	$data->image = $CFG->wwwroot . '/theme/edumy/images/teachers/' . $record->url . '';
		// } else {
		// 	$data->image = "";
		// }
		$center = $DB->get_record('optional_data_aibrahim', array('userid' => $notsub));
		if(empty($center->empty))
		{
			$data->centername ='';
		}
		else{
			$data->centername = $center->empty;
		}
		if(empty($center->school )){
			$data->schoolname ='';
		}
		else{
			$data->schoolname = $center->school;
		}
		// $data->centername = $center->empty; //center name
		// $data->schooname = $center->school;
		$final_data[] = $data;
	}

	// uasort($ids, array($ids,  fn($a, $b) => strcmp($a['fullname'], $b['fullname'])));
	// var_dump($ids[0]->fullname) ;
	// uasort($ids, function($a, $b)
	//          {

	//              return (($a["grade"] < $b["grade"]) ? -1 : 1);
	//          });

	if ($option == "maxGrade" || $option == " ") {
		usort($ids, function ($a, $b) {
			return $b->grade - $a->grade;
		});
	} elseif ($option == "minGrade") {
		usort($ids, function ($a, $b) {
			return $a->grade - $b->grade;
		});
	}
	// elseif($option=="ztoa"){
	// 	usort($ids, function($a, $b) { return strtolower($a->fullname) < strtolower($b->fullname); });
	// 	usort($res, function($a, $b) { return strtolower($a->fullname) < strtolower($b->fullname); });
	// 	usort($final_data, function($a, $b) { return strtolower($a->fullname) < strtolower($b->fullname); });

	// }
	elseif ($option == "centername") {
		usort($ids, function ($a, $b) {
			return strtolower($a->centername) > strtolower($b->centername);
		});

		usort($res, function ($a, $b) {
			return strtolower($a->centername) > strtolower($b->centername);
		});

		usort($final_data, function ($a, $b) {
			return strtolower($a->centername) > strtolower($b->centername);
		});
	} elseif ($option == "centernamedesc") {
		usort($ids, function ($a, $b) {
			return strtolower($a->centername) < strtolower($b->centername);
		});

		usort($res, function ($a, $b) {
			return strtolower($a->centername) < strtolower($b->centername);
		});

		usort($final_data, function ($a, $b) {
			return strtolower($a->centername) < strtolower($b->centername);
		});
	} elseif ($option == "timefinishAsc") {
		usort($ids, function ($a, $b) {
			return $a->timefinished > $b->timefinished;
		});
	} elseif ($option == "timefinishDesc") {
		usort($ids, function ($a, $b) {
			return $a->timefinished < $b->timefinished;
		});
	}
	// var_dump($ids) ;

	return json_encode(['submitted' => $ids, 'inprogress' => $res, 'notsubmitted' => array_values($final_data)]);
}
// function quiz_report_course($id)
// {
// 	global $DB,$CFG;
// 	$quiz = $DB->get_record('quiz', array('id' => $id));
// 	$enrolled_students = $DB->get_records_sql("SELECT u.id As id,concat(u.firstname , ' ', u.lastname)as studentname, c.fullname As coursename
//  			FROM mdl_course c INNER JOIN mdl_context cx ON c.id = cx.instanceid 
// 			 INNER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
//  			 INNER JOIN mdl_user u ON ra.userid = u.id WHERE cx.contextlevel = '50' AND c.id=$quiz->course");

// 	$allquizAttempts = $DB->get_records_sql("SELECT
// 				quiza.id AS quizattemptid,
// 				quiza.userid as userid,
// 				quiza.quiz as quizid,
// 				quiza.state as state
// 				FROM mdl_quiz_attempts quiza
// 				JOIN mdl_user user ON quiza.userid = user.id
// 				WHERE quiza.quiz =$id  and user.deleted = 0  and quiza.preview = 0
// 				GROUP BY userid ORDER BY quizattemptid
// 					");
// 	$final_data = array();
// 	$inprogress = array();
// 	$res = new stdClass();
// 	$ids = array();
// 	$notsubmitted = array();
// 	foreach ($enrolled_students as $en_s) {
// 		foreach ($allquizAttempts as $key) {
// 			if ($en_s->id == $key->userid) {

// 				$attempts = quiz_get_user_attempts($id, $key->userid);
// 				$bestgrade = quiz_calculate_best_grade($quiz, $attempts);
// 				$res->state = $key->state;
// 				$res->userid = $key->userid;
// 				$res->quizid = $id;
// 				$res->fullname = $en_s->studentname;
// 				if(!empty($en_s->image)){
// 					$res->image=$CFG->wwwroot . '/theme/edumy/images/teachers/' . $en_s->image . '';

// 				}
// 				else{
// 					$res->image="";
// 				}
// 				if ($key->state == "inprogress") {
// 					$res->grade = "0";
// 					$inprogress[] = $res;
// 				} else {
// 					$res->grade = $bestgrade;
// 					$final_data[] = $res;
// 				}
// 				break;
// 			} else {
// 				array_push($ids, $en_s->id);
// 				break;
// 			}
// 		}
// 	}
// 	foreach ($ids as $ida) {
// 		$record = $DB->get_record('user', array('id' => $ida));
// 		$data = new stdClass();
// 		$data->state = "notsubmitted";
// 		$data->userid = $ida;
// 		$data->quiz = $id;
// 		$data->fullname = $record->firstname . " " . $record->lastname;
// 		if(!empty($record->url)){
// 			$data->image=$CFG->wwwroot . '/theme/edumy/images/teachers/' . $record->url . '';

// 		}
// 		else{
// 			$data->image="";
// 		}

// 		$notsubmitted[] = $data;
// 	}

// 	return json_encode(['submitted' => $final_data, 'inprogress' => $inprogress, 'notsubmitted' => array_values($notsubmitted)]);
// }
function quiz_report($id)
{
	global $DB;

	$quiz = $DB->get_record('quiz', array('id' => $id));

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
		$res = new stdClass();
		$attempts = quiz_get_user_attempts($id, $key->userid);
		$bestgrade = quiz_calculate_best_grade($quiz, $attempts);
		if ($bestgrade != null) {
			$records = $DB->get_records_sql('SELECT user.id as userid,concat(user.firstname , " ", user.lastname)as fullname ,user.url as image FROM `mdl_user` as user where user.id = ' . $key->userid . ' and user.deleted=0 ');
			$records = array_values($records);
			$res->userid = $key->userid;
			$res->quizid = $id;
			$res->grade = $bestgrade;
			$res->fullname = $records[0]->fullname;
			$res->image = $records[0]->image;
			$records->quizid = $id;
			$records->grade = $bestgrade;
			array_push($final_data, $res);
		}
	}
	return json_encode(['data' => $final_data]);
}

// function course_page_report($courseid,$objectid){
// global $DB;
// $records=$DB->get_records_sql('SELECT user.id as userid,COUNT(*)as number ,concat(user.firstname , " ", user.lastname)as fullname ,user.url as image FROM `mdl_logstore_standard_log` as logs INNER JOIN mdl_user as user ON logs.userid=user.id where courseid='.$courseid.' AND action="viewed" and objecttable ="page" and user.deleted=0 and objectid='.$objectid.' GROUP BY userid ');
// return  json_encode(['data'=>array_values($records)]);


// }

// function course_file_report($courseid,$objectid){
// global $DB;
// $records=$DB->get_records_sql('SELECT user.id as userid,COUNT(*)as number ,concat(user.firstname , " ", user.lastname)as fullname ,user.url as image FROM `mdl_logstore_standard_log` as logs INNER JOIN mdl_user as user ON logs.userid=user.id where courseid='.$courseid.' AND action="viewed" and objecttable ="resource" and user.deleted=0 and objectid='.$objectid.' GROUP BY userid');
// return  json_encode(['data'=>array_values($records)]);


// }
function course_page_report($courseid, $objectid)
{
	global $DB;
	$records = $DB->get_records_sql('SELECT user.id as userid,COUNT(*)as number ,concat(user.firstname , " ", user.lastname)as fullname ,user.url as image FROM `mdl_logstore_standard_log` as logs INNER JOIN mdl_user as user ON logs.userid=user.id where courseid=' . $courseid . ' AND action="viewed" and objecttable ="page" and user.deleted=0 and objectid=' . $objectid . ' GROUP BY userid ');
	$context = context_course::instance($courseid, MUST_EXIST);
	$page_views = array();
	foreach ($records as $record) {
		$studentRole = $DB->get_field('role', 'id', array('shortname' => 'student'));
		$isStudent = $DB->record_exists('role_assignments', ['userid' => $record->userid, 'roleid' => $studentRole]);
		$enrol = is_enrolled($context, $record->userid, '', true);
		if ($enrol && $isStudent) {

			$page_views[] = $record;
		}
	}
	return  json_encode(['data' => array_values($page_views)]);
}

function course_file_report($courseid, $objectid)
{
	global $DB;
	$records = $DB->get_records_sql('SELECT user.id as userid,COUNT(*)as number ,concat(user.firstname , " ", user.lastname)as fullname ,user.url as image FROM `mdl_logstore_standard_log` as logs INNER JOIN mdl_user as user ON logs.userid=user.id where courseid=' . $courseid . ' AND action="viewed" and objecttable ="resource" and user.deleted=0 and objectid=' . $objectid . ' GROUP BY userid');
	$context = context_course::instance($courseid, MUST_EXIST);
	$file_views = array();
	foreach ($records as $record) {
		$studentRole = $DB->get_field('role', 'id', array('shortname' => 'student'));
		$isStudent = $DB->record_exists('role_assignments', ['userid' => $record->userid, 'roleid' => $studentRole]);
		$enrol = is_enrolled($context, $record->userid, '', true);
		if ($enrol && $isStudent) {

			$file_views[] = $record;
		}
	}
	return  json_encode(['data' => array_values($file_views)]);
}

function course_assign_report($courseid, $objectid)
{
	global $DB;
	$records = $DB->get_records_sql('SELECT user.id as userid,COUNT(*)as number ,concat(user.firstname , " ", user.lastname)as fullname ,user.url as image FROM `mdl_logstore_standard_log` as logs INNER JOIN mdl_user as user ON logs.userid=user.id where courseid=' . $courseid . ' AND action="viewed" and objecttable ="assign" and user.deleted=0 and objectid=' . $objectid . ' GROUP BY userid');
	return  json_encode(['data' => array_values($records)]);
}

function check_teacher_has_reports($teacherid, $courseid)
{
	global $DB, $OUTPUT, $CFG;
	$check_availability = $DB->get_record('activities_control', array('course' => $courseid, 'teacher_id' => $teacherid));

	$page = $check_availability->page;
	$file = $check_availability->file;
	$assign = $check_availability->assign;
	$quiz = $check_availability->quiz;

	$room = $DB->get_record('course_bbb', array('course' => $courseid));


	if ($page == 1 || $file == 1 || $assign == 1 || $quiz == 1) {
		return json_encode(["activity" => 'true']);
	}

	if (!empty($room)) {
		if ($room->bbb == true) {
			return json_encode(["activity" => 'true']);
		}
	}
	return json_encode(["activity" => 'false']);
}

function get_teacher_reports_item($teacherid, $courseid)
{
	global $DB, $OUTPUT, $CFG;
	$ins = new stdClass();
	$check_availability = $DB->get_record('activities_control', array('course' => $courseid, 'teacher_id' => $teacherid));
	$room = $DB->get_record('course_bbb', array('course' => $courseid));

	$ins->page = intval($check_availability->page);
	$ins->file = intval($check_availability->file);
	$ins->assign = intval($check_availability->assign);
	$ins->quiz = intval($check_availability->quiz);


	if (!empty($room)) {
		if ($room->bbb == true) {
			$ins->bbb = 1;
		} else {
			$ins->bbb = 0;
		}
	} else {
		$ins->bbb = 0;
	}
	return json_encode(["reports" => $ins]);
}



function test_old_quiz_report($id)
{
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
	$quiz = $DB->get_record('quiz', array('id' => $id));
	// return json_encode(['data'=>$quiz->grademethod]);
	if ($quiz->grademethod == "4") {
		$data = $DB->get_records_sql("SELECT
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
			$data2 = $DB->get_records_sql("SELECT 
	     quiza.id AS quizattemptid,quiza.userid as
		 userid, CONCAT(user.firstname ,' ',user.lastname) as fullname, user.url,
	     quiza.quiz,(quiza.sumgrades) as grade
	FROM mdl_quiz_attempts  as quiza 
	JOIN mdl_user user ON quiza.userid = user.id 
	WHERE quiza.attempt=$key->max_value	and quiza.quiz=$id");
			array_push($final_data, $data2);
		}

		if (empty($data2)) {
			return json_encode(['data' => 'No Data']);
		}
		return json_encode(['data' => array_values($final_data)]);
	} elseif ($quiz->grademethod == "3") {
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
			$data2 = $DB->get_records_sql("SELECT 
		 quiza.id AS quizattemptid,quiza.userid asuserid, 
		 CONCAT(user.firstname ,' ',user.lastname) as fullname, user.url,
	     quiza.quiz,(quiza.sumgrades) as grade 
		 FROM mdl_quiz_attempts  as quiza JOIN mdl_user user ON quiza.userid = user.id
	     WHERE quiza.attempt='.$key->min_value.' and quiza.quiz=$id");

			array_push($final_data, $data2);
		}
		if (empty($final_data)) {
			return json_encode(['data' => 'No Data']);
		}
		return json_encode(['data' => array_values($final_data)]);
	} elseif ($quiz->grademethod == "2") {
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

		if (empty($data)) {
			return json_encode(['data' => 'No Data']);
		}

		return json_encode(['data' => array_values($data)]);
	} elseif ($quiz->grademethod == "1") {
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

		if (empty($data)) {
			return json_encode(['data' => 'No Data']);
		}

		return json_encode(['data' => array_values($data)]);
	}
	return json_encode(['data' => 'No Data']);
}
function quiz_report_user($id, $user)
{
	global $DB;

	$quiz = $DB->get_record('quiz', array('id' => $id));

	$allquizAttempts = $DB->get_records_sql("SELECT
	quiza.id AS quizattemptid,
	quiza.userid as userid,
	quiza.quiz as quizid,
	quiz.name as quizname
	FROM mdl_quiz_attempts quiza
	JOIN mdl_user user ON quiza.userid = user.id
	Join mdl_quiz quiz ON quiz.id=$id
	WHERE quiza.quiz =$id and user.id=$user and user.deleted = 0 and quiza.state='finished' and quiza.preview = 0
	GROUP BY userid ORDER BY quizattemptid
	");

	$final_data = array();
	foreach ($allquizAttempts as $key) {
		$res = new stdClass();
		$attempts = quiz_get_user_attempts($id, $key->userid);
		$bestgrade = quiz_calculate_best_grade($quiz, $attempts);
		if ($bestgrade != null) {
			$records = $DB->get_records_sql('SELECT user.id as userid,concat(user.firstname , " ", user.lastname)as fullname  FROM `mdl_user` as user where user.id = ' . $key->userid . ' and user.deleted=0 ');
			$records = array_values($records);
			$res->status = "submitted";
			$res->quizname = $key->quizname;
			$res->userid = $key->userid;
			$res->quizid = $id;
			$res->grade = round($bestgrade, 2) . "/" . round($quiz->grade, 2);
			$res->fullName = $records[0]->fullname;
			$res->image = $records[0]->image;
			$records->quizid = $id;
			$records->grade = $bestgrade;
			$final_data = $res;
			// array_push(,$res);
		}
	}
	return $final_data;
}
function page_count($page, $course, $user)
{
	global $DB;
	$records = $DB->get_records_sql('SELECT COUNT(*)as number FROM `mdl_logstore_standard_log` as logs INNER JOIN mdl_user as user ON logs.userid=user.id where courseid=' . $course . ' AND user.id=' . $user . ' AND action="viewed" and objecttable ="page" and user.deleted=0 and objectid=' . $page . ' GROUP BY userid
	');
	$views = 0;

	foreach ($records as $record) {
		$views = $record->number;
	}
	return $views;
}
function resource_count($resource, $course, $user)
{
	global $DB;
	$records = $DB->get_records_sql('SELECT COUNT(*)as number FROM `mdl_logstore_standard_log` as logs INNER JOIN mdl_user as user ON logs.userid=user.id where courseid=' . $course . ' AND user.id=' . $user . ' AND action="viewed" and objecttable ="resource" and user.deleted=0 and objectid=' . $resource . ' GROUP BY userid
	');
	$views = 0;

	foreach ($records as $record) {
		$views = $record->number;
	}
	return $views;
}
function last_seen_user_page($user, $page, $course)
{
	global $DB;
	$records = $DB->get_records_sql('SELECT logs.timecreated as seen FROM `mdl_logstore_standard_log` as logs INNER JOIN mdl_user as user ON logs.userid=user.id where courseid=' . $course . ' AND user.id=' . $user . ' AND action="viewed" and objecttable ="page" and user.deleted=0 and objectid=' . $page . ' GROUP BY userid
	');
	$last_seen = 0;

	foreach ($records as $record) {
		$last_seen = $record->seen;
	}
	return date('m/d/Y H:i:s', $last_seen);
}
function last_seen_user_resource($user, $resource, $course)
{
	global $DB;
	$records = $DB->get_records_sql('SELECT logs.timecreated as seen FROM `mdl_logstore_standard_log` as logs INNER JOIN mdl_user as user ON logs.userid=user.id where courseid=' . $course . ' AND user.id=' . $user . ' AND action="viewed" and objecttable ="resource" and user.deleted=0 and objectid=' . $resource . ' GROUP BY userid
	');
	$last_seen = 0;

	foreach ($records as $record) {
		$last_seen = $record->seen;
	}
	return date('m/d/Y H:i:s', $last_seen);
}
function download_page_report($courseid, $objectid, $option)
{
	global $DB, $CFG;
	$seen = array();
	$notseen = array();
	$seenData = array();
	$notseenData = array();
	$option2 = "";
	// $record = $DB->get_record('groups', array('courseid' => $courseid, "name" => "Enrolled Users"));
	if ($option == "viewsDesc" || $option == " ") {
		$option = "order by number DESC";
	} else if ($option == "viewsAsc") {
		$option = "order by number ASC";
	} elseif ($option == "lastseenDesc") {

		$option = "order by seen DESC";
	} elseif ($option == "lastseenAsc") {

		$option = "order by seen ASC";
	} elseif ($option == "atoz") {
		$option = "order by fullname ASC";
		$option2 = "order by fullname ASC";
	} elseif ($option == "ztoa") {
		$option = "order by fullname DESC";
		$option2 = "order by fullname DESC";
	} elseif ($option == "centername") {
		$option = "order by center ASC";
		$option2 = "order by center ASC";
	} elseif ($option == "centernamedesc") {
		$option = "order by center DESC";
		$option2 = "order by center DESC";
	}
	$enrolled_students = $DB->get_records_sql("SELECT u.id As id ,CONCAT(u.firstname,' ',u.lastname )as fullname,
	COUNT(logs.id)as number,logs.timecreated as seen ,
	op.empty as center ,op.school as school
	 FROM mdl_course c 
	 INNER JOIN mdl_context cx ON c.id = cx.instanceid 
	 INNER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
	  INNER JOIN mdl_user u ON ra.userid =  u.id 
	   INNER JOIN mdl_logstore_standard_log as logs ON logs.userid=u.id 
	   LEFT OUTER JOIN mdl_optional_data_aibrahim as op ON u.id=op.userid

	   WHERE cx.contextlevel = '50' AND c.id=$courseid and logs.objectid=$objectid
	    and logs.objecttable='page' AND logs.action='viewed' GROUP BY logs.userid $option");

	// $records = $DB->get_records_sql('SELECT user.id as userid
	//  FROM `mdl_logstore_standard_log` as logs
	//   INNER JOIN mdl_user as user ON logs.userid=user.id
	//    where courseid=' . $courseid . ' AND action="viewed" and objecttable ="page" and user.deleted=0 and objectid=' . $objectid . ' 
	//    GROUP BY userid ');
	foreach ($enrolled_students as $en_s) {
		// if($en_s->act=="viewed"){
		// $center=$DB->get_record('optional_data_aibrahim',array('userid'=>$en_s->id));
		if(empty( $en_s->center))
		{
			$centername ='';
		}
		else{
			$centername = $en_s->center; //center name
		}
		if(empty($en_s->school )){
			$schoolname = '';
		}
		else{
			$schoolname = $en_s->school;
		}
		// $centername = $en_s->center; //center name
		// $schoolname = $en_s->school;
		$image = "";

		$seen[] = array(
			'state' => 'seen',
			'userid' => $en_s->id, 'fullname' => $en_s->fullname, 'number' => $en_s->number,
			"lastseen" => date('m/d/Y H:i:s', $en_s->seen), "image" => $image, "centername" => $centername,
			'schoolname' => $schoolname
		);
		array_push($seenData, $en_s->id);
	}

	$enrolled_students = $DB->get_records_sql("SELECT u.id As id
	,CONCAT(u.firstname,' ',u.lastname )as fullname ,op.empty as center ,op.school as school
	FROM mdl_course c 
	INNER JOIN mdl_context cx ON c.id = cx.instanceid 
	INNER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
	 INNER JOIN mdl_user u ON ra.userid = u.id 
	 LEFT OUTER JOIN mdl_optional_data_aibrahim as op ON u.id=op.userid
	WHERE cx.contextlevel = '50' AND c.id=$courseid $option2 ");
	foreach ($enrolled_students as $en_s) {
		if (!in_array($en_s->id, $seenData)) {
			// $center=$DB->get_record('optional_data_aibrahim',array('userid'=>$en_s->id));
			if(empty( $en_s->center))
		{
			$centername='';
		}
		else{
			$centername = $en_s->center; //center name
		}
		if(empty($en_s->school )){
			$schoolname = '';
		}
		else{
			$schoolname = $en_s->school;
		}
			// $centername = $en_s->center; //center name
			// $schoolname = $en_s->school;
			$image = "";

			$notseen[] = array(
				'state' => 'notseen', 'userid' => $en_s->id,
				'fullname' => $en_s->fullname, 'number' => "0", "image" =>  $image,
				"centername" => $centername, "schoolname" => $schoolname
			);
		}
	}

	return json_encode(["seen" => $seen, "notseen" => $notseen]);
}
function download_page_report_again($courseid, $objectid, $option)
{
	global $DB, $CFG;
	$seen = array();
	$notseen = array();
	$seenData = array();
	$notseenData = array();
	$record = $DB->get_record('groups', array('courseid' => $courseid, "name" => "Enrolled Users"));
	if ($option == "atoz") {
		$option = "order by studentname ASC";
		$enrolled_students = $DB->get_records_sql("SELECT u.id As id FROM mdl_course c 
		INNER JOIN mdl_context cx ON c.id = cx.instanceid 
		INNER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
		 INNER JOIN mdl_user u ON ra.userid = u.id 
		INNER JOIN mdl_groups_members gm ON u.id = gm.userid 
		WHERE cx.contextlevel = '50' AND c.id=$courseid and gm.groupid=$record->id $option ");
	} elseif ($option == "ztoa") {
		$option = "order by studentname DESC";
		$enrolled_students = $DB->get_records_sql("SELECT u.id As id FROM mdl_course c 
	INNER JOIN mdl_context cx ON c.id = cx.instanceid 
	INNER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
	 INNER JOIN mdl_user u ON ra.userid = u.id 
	INNER JOIN mdl_groups_members gm ON u.id = gm.userid 
	WHERE cx.contextlevel = '50' AND c.id=$courseid and gm.groupid=$record->id $option ");
	} else {
		$enrolled_students = $DB->get_records_sql("SELECT u.id As id FROM mdl_course c 
	INNER JOIN mdl_context cx ON c.id = cx.instanceid 
	INNER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
	 INNER JOIN mdl_user u ON ra.userid = u.id 
	INNER JOIN mdl_groups_members gm ON u.id = gm.userid 
	WHERE cx.contextlevel = '50' AND c.id=$courseid and gm.groupid=$record->id ");
	}
	$enrolled_students = $DB->get_records_sql("SELECT u.id As id FROM mdl_course c 
	INNER JOIN mdl_context cx ON c.id = cx.instanceid 
	INNER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
	 INNER JOIN mdl_user u ON ra.userid = u.id 
	INNER JOIN mdl_groups_members gm ON u.id = gm.userid 
	WHERE cx.contextlevel = '50' AND c.id=$courseid and gm.groupid=$record->id ");
	$records = $DB->get_records_sql('SELECT user.id as userid FROM `mdl_logstore_standard_log` as logs INNER JOIN mdl_user as user ON logs.userid=user.id where courseid=' . $courseid . ' AND action="viewed" and objecttable ="page" and user.deleted=0 and objectid=' . $objectid . ' GROUP BY userid ');
	foreach ($enrolled_students as $en_s) {
		$flag = 0;
		foreach ($records as $record) {
			if ($record->userid == $en_s->id) {
				$flag = 1;
			}
		}
		if ($flag == 1) {
			array_push($seen, $en_s->id);
		} else {
			array_push($notseen, $en_s->id);
		}
	}
	foreach ($seen as $see) {
		$user = $DB->get_record('user', array('id' => $see));
		$views = page_count($objectid, $courseid, $see);
		$lastseen = last_seen_user_page($see, $objectid, $courseid);
		$fullname = $user->firstname . " " . $user->lastname;
		if (empty($user->url)) {
			$image = "";
		} else {
			$image = $CFG->wwwroot . '/theme/edumy/images/teachers/' . $user->url;
		}
		$center = $DB->get_record('optional_data_aibrahim', array('userid' => $see));
		$centername = $center->empty; //center name
		$schoolname = $center->school;
		$seenData[] = array('state' => 'seen', 'userid' => $see, 'fullname' => $fullname, 'number' => $views, "lastseen" => $lastseen, "image" => $image, "centername" => $centername, 'schoolname' => $schoolname);
	}
	foreach ($notseen as $notsee) {
		$user = $DB->get_record('user', array('id' => $notsee));
		$fullName = $user->firstname . " " . $user->lastname;
		if (empty($user->url)) {
			$image = "";
		} else {
			$image = $CFG->wwwroot . '/theme/edumy/images/teachers/' . $user->url;
		}
		$center = $DB->get_record('optional_data_aibrahim', array('userid' => $notsee));
		$centername = $center->empty; //center name
		$schoolname = $center->school;
		$notseenData[] = array('state' => 'notseen', 'userid' => $notsee, 'fullname' => $fullName, 'number' => "0", "image" => $image, "centername" => $centername, "schoolname" => $schoolname);
	}
	// if ($option == "viewsDesc" || $option == " ") {
	// 	usort($seenData, function ($a, $b) {
	// 		return $b['number'] > $a['number'];
	// 	});
	// } elseif ($option == "viewsAsc") {
	// 	usort($seenData, function ($a, $b) {
	// 		return $b['number'] < $a['number'];
	// 	});
	// } elseif ($option == "lastseenAsc") {
	// 	usort($seenData, function ($a, $b) {
	// 		return $b['lastseen'] < $a['lastseen'];
	// 	});
	// } elseif ($option == "lastseenDesc") {
	// 	usort($seenData, function ($a, $b) {
	// 		return $b['lastseen'] > $a['lastseen'];
	// 	});
	// }
	// 	elseif($option=="atoz"){

	// 			usort($seenData, function($a, $b) { return strtolower($a['fullname']) > strtolower($b['fullname']); });
	// 			usort($notseenData, function($a, $b) { return strtolower($a['fullname']) > strtolower($b['fullname']); });	

	// 	}
	// 	elseif($option=="ztoa"){

	// 		usort($seenData, function($a, $b) { return strtolower($a['fullname']) < strtolower($b['fullname']); });
	// 		usort($notseenData, function($a, $b) { return strtolower($a['fullname']) < strtolower($b['fullname']); });	

	// }
	// var_dump($seenData);
	return json_encode(["seen" => $seenData, "notseen" => $notseenData]);
}
function download_resource_report($courseid, $objectid, $option)
{
	global $DB, $CFG;
	$seen = array();
	$notseen = array();
	$seenData = array();
	$notseenData = array();
	// $record = $DB->get_record('groups', array('courseid' => $courseid, "name" => "Enrolled Users"));
	if ($option == "viewsDesc" || $option == " ") {
		$option = "order by number DESC";
	} else if ($option == "viewsAsc") {
		$option = "order by number ASC";
	} elseif ($option == "lastseenDesc") {

		$option = "order by seen DESC";
	} elseif ($option == "lastseenAsc") {

		$option = "order by seen ASC";
	} elseif ($option == "atoz") {
		$option = "order by fullname ASC";
		$option2 = "order by fullname ASC";
	} elseif ($option == "ztoa") {
		$option = "order by fullname DESC";
		$option2 = "order by fullname DESC";
	} elseif ($option == "centername") {
		$option = "order by center ASC";
		$option2 = "order by center ASC";
	} elseif ($option == "centernamedesc") {
		$option = "order by center DESC";
		$option2 = "order by center DESC";
	}
	// $check_type = $DB->get_record('reda_video_type', array('resource_id' => $objectid));
	// if ($check_type->type == 2) {

	// 	$all_views = $DB->get_records_sql("SELECT * from mdl_activity_restrict_views_code_device_check where activityid=$objectid");
	// 	// return json_encode(["seen" => $check_type]);

	// 	foreach ($all_views as $allViews) {
	// 		$user = $DB->get_record('user', array('id' => $allViews->userid));
	// 		$views = $allViews->number_of_views;
	// 		$lastseen = $allViews->timemodified;
	// 		$fullname = $user->firstname . " " . $user->lastname;
	// 		// if (empty($user->url)) {
	// 		// 	$image = "";
	// 		// } else {
	// 		// 	$image = $CFG->wwwroot . '/theme/edumy/images/teachers/' . $user->url;
	// 		// }
	// 		$center = $DB->get_record('optional_data_aibrahim', array('userid' => $allViews->userid));
	// 		$centername = $center->empty; //center name
	// 		$schoolname = $center->school;
	// 		$seen[] = array('state' => 'seen', 'userid' => $allViews->userid,
	// 		 'fullname' => $fullname, 'number' => $views, "lastseen" => $lastseen,
	// 		  "image" =>  "", "centername" => $centername, "schoolname" => $schoolname);
	// 	}
	// } else {
	$enrolled_students = $DB->get_records_sql("SELECT u.id As id ,CONCAT(u.firstname,' ',u.lastname )as fullname,
		COUNT(logs.id)as number,logs.timecreated as seen 
 ,op.empty as center,op.school as school
		 FROM mdl_course c 
		 INNER JOIN mdl_context cx ON c.id = cx.instanceid 
		 INNER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
		  INNER JOIN mdl_user u ON ra.userid = u.id 
		   INNER JOIN mdl_logstore_standard_log as logs ON logs.userid=u.id 
		   LEFT OUTER JOIN mdl_optional_data_aibrahim as op ON u.id=op.userid

		   WHERE cx.contextlevel = '50' AND c.id=$courseid  and logs.objectid=$objectid
			and (logs.objecttable='resource' or logs.objecttable='resource2') AND logs.action='viewed' GROUP BY logs.userid $option");

	foreach ($enrolled_students as $en_s) {
		// if($en_s->act=="viewed"){
		// $center=$DB->get_record('optional_data_aibrahim',array('userid'=>$en_s->id));
		if(empty( $en_s->center))
		{
			$centername ='';
		}
		else{
			$centername = $en_s->center; //center name
		}
		if(empty($en_s->school )){
			$schoolname = '';
		}
		else{
			$schoolname = $en_s->school;
		}
		// $centername = $en_s->center; //center name
		// $schoolname = $en_s->school;
		$image = "";
		// if (empty($en_s->url)) {
		// 	$image = "";
		// } else {
		// 	$image = $CFG->wwwroot . '/theme/edumy/images/teachers/' . $en_s->url;
		// }
		$seen[] = array(
			'state' => 'seen',
			'userid' => $en_s->id, 'fullname' => $en_s->fullname, 'number' => $en_s->number,
			"lastseen" => date('m/d/Y H:i:s', $en_s->seen), "image" => $image, "centername" => $centername,
			'schoolname' => $schoolname
		);
		array_push($seenData, $en_s->id);
	}
	$enrolled_students = $DB->get_records_sql("SELECT u.id As id 
	,CONCAT(u.firstname,' ',u.lastname )as fullname ,op.empty as center,op.school as school
	FROM mdl_course c 
	INNER JOIN mdl_context cx ON c.id = cx.instanceid 
	INNER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
	 INNER JOIN mdl_user u ON ra.userid = u.id 
	 LEFT OUTER JOIN mdl_optional_data_aibrahim as op ON u.id=op.userid

	WHERE cx.contextlevel = '50' AND c.id=$courseid  $option2");
	foreach ($enrolled_students as $en_s) {
		if (!in_array($en_s->id, $seenData)) {
			// $center=$DB->get_record('optional_data_aibrahim',array('userid'=>$en_s->id));
			if(empty( $en_s->center))
		{
			$centername ='';
		}
		else{
			$centername = $en_s->center; //center name
		}
		if(empty($en_s->school )){
			$schoolname = '';
		}
		else{
			$schoolname = $en_s->school;
		}
			// $centername = $en_s->center; //center name
			// $schoolname = $en_s->school;
			$image = "";
			// if (empty($en_s->url)) {
			// 	$image = "";
			// } else {
			// 	$image = $CFG->wwwroot . '/theme/edumy/images/teachers/' . $en_s->url;
			// }
			$notseen[] = array(
				'state' => 'notseen', 'userid' => $en_s->id,
				'fullname' => $en_s->fullname, 'number' => "0", "image" => $image,
				"centername" => $centername, "schoolname" => $schoolname
			);
		}
	}
	

	return json_encode(["seen" => $seen, "notseen" => $notseen]);
}
function download_resource_report_again($courseid, $objectid, $option)
{
	global $DB, $CFG;
	$seen = array();
	$notseen = array();
	$seenData = array();
	$notseenData = array();
	$record = $DB->get_record('groups', array('courseid' => $courseid, "name" => "Enrolled Users"));
	$enrolled_students = $DB->get_records_sql("SELECT u.id As id FROM mdl_course c 
	INNER JOIN mdl_context cx ON c.id = cx.instanceid 
	INNER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
	 INNER JOIN mdl_user u ON ra.userid = u.id 
	INNER JOIN mdl_groups_members gm ON u.id = gm.userid 
	WHERE cx.contextlevel = '50' AND c.id=$courseid and gm.groupid=$record->id");
	$records = $DB->get_records_sql('SELECT user.id as userid FROM 
	 `mdl_logstore_standard_log` as logs 
	 INNER JOIN mdl_user as user ON logs.userid=user.id
	  where courseid=' . $courseid . ' AND action="viewed" and objecttable ="resource" and user.deleted=0 and objectid=' . $objectid . ' GROUP BY userid ');
	$check_type = $DB->get_record('reda_video_type', array('resource_id' => $objectid));
	if ($check_type->type == 2) {

		$all_views = $DB->get_records_sql("SELECT * from mdl_activity_restrict_views_code_device_check where activityid=$objectid");
		// return json_encode(["seen" => $check_type]);

		foreach ($all_views as $allViews) {
			$user = $DB->get_record('user', array('id' => $allViews->userid));
			$views = $allViews->number_of_views;
			$lastseen = $allViews->timemodified;
			$fullname = $user->firstname . " " . $user->lastname;
			if (empty($user->url)) {
				$image = "";
			} else {
				$image = $CFG->wwwroot . '/theme/edumy/images/teachers/' . $user->url;
			}
			$center = $DB->get_record('optional_data_aibrahim', array('userid' => $allViews->userid));
			$centername = $center->empty; //center name
			$schoolname = $center->school;
			$seenData[] = array('state' => 'seen', 'userid' => $allViews->userid, 'fullname' => $fullname, 'number' => $views, "lastseen" => $lastseen, "image" => $image, "centername" => $centername, "schoolname" => $schoolname);
		}
	} else {
		foreach ($enrolled_students as $en_s) {
			$flag = 0;
			foreach ($records as $record) {
				if ($record->userid == $en_s->id) {
					$flag = 1;
				}
			}
			if ($flag == 1) {
				array_push($seen, $en_s->id);
			} else {
				array_push($notseen, $en_s->id);
			}
		}
		foreach ($seen as $see) {
			$user = $DB->get_record('user', array('id' => $see));
			$views = resource_count($objectid, $courseid, $see);
			$lastseen = last_seen_user_resource($see, $objectid, $courseid);
			$fullname = $user->firstname . " " . $user->lastname;
			if (empty($user->url)) {
				$image = "";
			} else {
				$image = $CFG->wwwroot . '/theme/edumy/images/teachers/' . $user->url;
			}
			$center = $DB->get_record('optional_data_aibrahim', array('userid' => $see));
			$centername = $center->empty; //center name
			$schoolname = $center->school;
			$seenData[] = array('state' => 'seen', 'userid' => $see, 'fullname' => $fullname, 'number' => $views, "lastseen" => $lastseen, "image" => $image, "centername" => $centername, "schoolname" => $schoolname);
		}
		foreach ($notseen as $notsee) {
			$user = $DB->get_record('user', array('id' => $notsee));
			$fullName = $user->firstname . " " . $user->lastname;
			if (empty($user->url)) {
				$image = "";
			} else {
				$image = $CFG->wwwroot . '/theme/edumy/images/teachers/' . $user->url;
			}
			$center = $DB->get_record('optional_data_aibrahim', array('userid' => $notsee));
			$centername = $center->empty; //center name
			$schoolname = $center->school;
			$notseenData[] = array('state' => 'notseen', 'userid' => $notsee, 'fullname' => $fullName, 'number' => "0", "image" => $image, "centername" => $centername, "schoolname" => $schoolname);
		}
	}
	if ($option == "viewsDesc" || $option == " ") {
		usort($seenData, function ($a, $b) {
			return $b['number'] > $a['number'];
		});
	} elseif ($option == "viewsAsc") {
		usort($seenData, function ($a, $b) {
			return $b['number'] < $a['number'];
		});
	} elseif ($option == "lastseenAsc") {
		usort($seenData, function ($a, $b) {
			return $b['lastseen'] < $a['lastseen'];
		});
	} elseif ($option == "lastseenDesc") {
		usort($seenData, function ($a, $b) {
			return $b['lastseen'] > $a['lastseen'];
		});
	} elseif ($option == "atoz") {

		usort($seenData, function ($a, $b) {
			return strtolower($a['fullname']) > strtolower($b['fullname']);
		});
		usort($notseenData, function ($a, $b) {
			return strtolower($a['fullname']) > strtolower($b['fullname']);
		});
	} elseif ($option == "ztoa") {

		usort($seenData, function ($a, $b) {
			return strtolower($a['fullname']) < strtolower($b['fullname']);
		});
		usort($notseenData, function ($a, $b) {
			return strtolower($a['fullname']) < strtolower($b['fullname']);
		});
	}
	return json_encode(["seen" => $seenData, "notseen" => $notseenData]);
}
function course_report_quiz_user($courseid, $user)
{
	global $DB;
	$quizData = array();
	$examData = array();
	$pageData = array();
	$pageResult = array();
	$fileData = array();
	$fileResult = array();
	$homeworkResult = array();
	$summaryResult = array();
	$revisionResult = array();
	$codeResult = array();
	$assignResult = array();
	$pdfResult = array();
	$viewed = "false";
	$fileViewed = "false";
	// $records=$DB->get_records_sql('SELECT user.id as userid,COUNT(*)as number ,concat(user.firstname , " ", user.lastname)as fullname ,user.url as image 
	// FROM `mdl_logstore_standard_log` as logs INNER JOIN mdl_user as user ON logs.userid=user.id 
	// where courseid='.$courseid.' AND action="viewed" and objecttable ="page" and user.deleted=0 and user.id='.$user.' GROUP BY userid ');
	// return  json_encode(['data'=>array_values($records)]);
	$exams = $DB->get_records_sql("SELECT q.* FROM mdl_quiz as q Join mdl_course_modules as cm ON cm.instance=q.id where cm.course=$courseid and cm.module=17 and cm.deletioninprogress=0 and 
	 q.reviewcorrectness=0 and q.reviewspecificfeedback=0 and
	 q.reviewgeneralfeedback=0 and q.reviewrightanswer=0 and q.reviewoverallfeedback=0
	");
	$quizes  = $DB->get_records_sql("SELECT q.* FROM mdl_quiz as q Join mdl_course_modules as cm ON cm.instance=q.id where cm.course=$courseid and cm.module=17 and cm.deletioninprogress=0 and 
	q.reviewcorrectness<>0 and q.reviewspecificfeedback<>0 and
	q.reviewgeneralfeedback<>0 and q.reviewrightanswer<>0 and q.reviewoverallfeedback<>0
   ");
	foreach ($exams as $record) {
		$data = quiz_report_user($record->id, $user);
		if (empty($data)) {
			$examData[] = array('status' => 'notsubmitted', 'quizid' => $record->id, 'userid' => $user, 'grade' => "", 'quizname' => $record->name);
		} else {
			$examData[] = $data;
		}
	}
	foreach ($quizes as $record) {
		$data = quiz_report_user($record->id, $user);
		if (empty($data)) {
			$quizData[] = array('status' => 'notsubmitted', 'quizid' => $record->id, 'userid' => $user, 'grade' => "", 'quizname' => $record->name);
		} else {
			$quizData[] = $data;
		}
	}
	$page_table = $DB->get_records_sql("SELECT p.* FROM mdl_page as p join mdl_course_modules as cm ON cm.instance=p.id where cm.course=$courseid and cm.module=16 and cm.deletioninprogress=0");
	$pages = $DB->get_records_sql("SELECT page.name as pagename ,page.id as id ,COUNT(*) as number FROM `mdl_logstore_standard_log` as logs INNER JOIN mdl_user as user ON logs.userid=user.id INNER JOIN mdl_page as page ON page.id=logs.objectid where courseid=$courseid AND user.id=$user and action='viewed' and objecttable ='page' and user.deleted=0 GROUP BY logs.objectid");

	if (!empty($pages)) {
		foreach ($pages as $page) {
			array_push($pageData, $page->id);
		}
	}
	foreach ($page_table as $pt) {
		if (in_array($pt->id, $pageData)) {
			$views = page_count($pt->id, $courseid, $user);
			$pageResult[] = array('page_viewed' => 'true', "pageName" => $pt->name, "views" => $views);
		} else {
			$pageResult[] = array('page_viewed' => 'false', "pageName" => $pt->name, "views" => "0");
		}
	}

	$resources = $DB->get_records_sql("SELECT r.* ,rv.type as type FROM mdl_resource as r 
	join mdl_course_modules as cm ON cm.instance=r.id 
	join mdl_reda_video_type as rv on rv.resource_id=r.id
	where cm.course=$courseid and cm.module=18 and cm.deletioninprogress=0   ");
	$files = $DB->get_records_sql("SELECT res.name as name,res.id as id
	FROM `mdl_logstore_standard_log` as logs
 	INNER JOIN mdl_user as user ON logs.userid=user.id 
 	INNER JOIN mdl_resource as res ON res.id=logs.objectid
	where courseid=$courseid AND user.id=$user AND action='viewed' and objecttable ='resource' and user.deleted=0  GROUP BY logs.objectid");
	if (!empty($files)) {
		foreach ($files as $file) {
			array_push($fileData, $file->id);
		}
	}
	foreach ($resources as $resource) {
		if (in_array($resource->id, $fileData)) {
			$views = resource_count($resource->id, $courseid, $user);
			if ($resource->type == 1) {
				$fileResult[] = array('file_viewed' => 'true', "fileName" => $resource->name, "views" => $views);
			} elseif ($resource->type == 2) {
				$codeResult[] = array('file_viewed' => 'true', "fileName" => $resource->name, "views" => $views);
			} elseif ($resource->type == 3) {
				$homeworkResult[] = array('file_viewed' => 'true', "fileName" => $resource->name, "views" => $views);
			} elseif ($resource->type == 4) {
				$summaryResult[] = array('file_viewed' => 'true', "fileName" => $resource->name, "views" => $views);
			} elseif ($resource->type == 5) {
				$revisionResult[] = array('file_viewed' => 'true', "fileName" => $resource->name, "views" => $views);
			}
			// $fileResult[] = array('file_viewed' => 'true', "fileName" => $resource->name, "views" => $views);
		} else {
			if ($resource->type == 1) {
				$fileResult[] = array('file_viewed' => 'false', "fileName" => $resource->name, "views" => "0");
			} elseif ($resource->type == 2) {
				$codeResult[] = array('file_viewed' => 'false', "fileName" => $resource->name, "views" => "0");
			} elseif ($resource->type == 3) {
				$homeworkResult[] = array('file_viewed' => 'false', "fileName" => $resource->name, "views" => "0");
			} elseif ($resource->type == 4) {
				$summaryResult[] = array('file_viewed' => 'false', "fileName" => $resource->name, "views" => "0");
			}
			elseif ($resource->type == 5) {
				$revisionResult[] = array('file_viewed' => 'false', "fileName" => $resource->name, "views" => "0");
			}
		}
	}

	$assigns = $DB->get_records_sql("SELECT r.* FROM mdl_assign as r 
	join mdl_course_modules as cm ON cm.instance=r.id 
	where cm.course=$courseid and cm.module=1 and cm.deletioninprogress=0;
	");
	$teachers = $DB->get_records_sql("SELECT u.id As id
    FROM   mdl_course c
    LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
    LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
    LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id 
    WHERE cx.contextlevel = '50' AND c.id= '$courseid'");
	$teacherId = 0;
	$assistant_data = array();
	foreach ($teachers as $teach) {
		$teacherId = $teach->id;
	}
	$token = $DB->get_record('external_tokens', ['userid' => $teacherId]);
	$grade = '0';
	$grader = '0';
	// return json_encode(["seen" =>$assigns]);

	if (!empty($assigns)) {
		foreach ($assigns as $assign) {
			$get_grades = $DB->get_records('assign_grades', ['userid' => $user, 'assignment' => $assign->id]);
			if (!empty($get_grades)) {
				foreach ($get_grades as $gg) {
					if ($gg->grader == "-1") {
						$submittedState = "submitted but not graded";
					} else {
						$submittedState = "submitted";
					}
					$assignResult[] = array(
						'assignId' => $assign->id, 'assignName' => $assign->name, 'submissionState' => $submittedState, 'grade' => $gg->grade, 'grader' => $gg->grader, 'timecreated' => $gg->timecreated, 'timemodified' => $gg->timemodified
					);
				}
			} else {
				$assignResult[] = array(
					'assignId' => $assign->id, 'assignName' => $assign->name, 'submissionState' => "notsubmited", 'grade' => '-1.00000', 'grader' => '-1', 'timecreated' => '0', 'timemodified' => '0'
				);
			}
		}
	}
	$pdf_table = $DB->get_records_sql("SELECT p.* FROM mdl_testnew as p 
	join mdl_course_modules as cm ON cm.instance=p.id 
	where cm.course=$courseid and cm.module=24 and cm.deletioninprogress=0");
	foreach ($pdf_table as $pdf) {
		$get_views = $DB->get_record('testnew_logs', ['user' => $user, 'testnewid' => $pdf->id]);
		if (!empty($get_views)) {
			$pdfResult[] = array('pdfId' => $pdf->id, 'pdfName' => $pdf->name, 'views' => $get_views->views);
		} else {
			$pdfResult[] = array('pdfId' => $pdf->id, 'pdfName' => $pdf->name, 'views' => "0");
		}
	}
	return json_encode(['quiz' => $quizData, 'exam' => $examData,
	 'page' => $pageResult, 'file' => $fileResult,
	  'lesson' => $codeResult,
	  'homework' => $homeworkResult,'summary' => $summaryResult,'revision' => $revisionResult,'assign'=>$assignResult,'pdf'=>$pdfResult]);
}

function course_report_quiz_user_average($courseid, $option, $quizids = array())
{
	global $DB, $CFG;
	$quizData = array();
	$noaverage = array();
	$data1 = array();
	$data2 = array();
	$all_quiz_grades = 0;
	$enrolled_students = $DB->get_records_sql("SELECT u.id As id,u.firstname as fname , u.lastname as lname
	FROM mdl_course c INNER JOIN mdl_context cx ON c.id = cx.instanceid 
	INNER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
 	INNER JOIN mdl_user u ON ra.userid = u.id WHERE cx.contextlevel = '50' AND c.id=$courseid");
	foreach ($quizids as $id) {
		$exams = $DB->get_records_sql("SELECT q.* FROM mdl_quiz as q Join mdl_course_modules as cm ON cm.instance=q.id where cm.course=$courseid and cm.module=17 and cm.deletioninprogress=0 and 
		q.reviewcorrectness=0 and q.reviewspecificfeedback=0 and
		q.reviewgeneralfeedback=0 and q.reviewrightanswer=0 and q.reviewoverallfeedback=0 and q.id=$id
	   ");
		foreach ($exams as $ex) {
			$data1[] = $ex;
			$all_quiz_grades += $ex->grade;
		}

		$quizes = $DB->get_records_sql("SELECT q.* FROM mdl_quiz as q Join mdl_course_modules as cm ON cm.instance=q.id where cm.course=$courseid and cm.module=17 and cm.deletioninprogress=0 and 
	   q.reviewcorrectness<>0 and q.reviewspecificfeedback<>0 and
	   q.reviewgeneralfeedback<>0 and q.reviewrightanswer<>0 and q.reviewoverallfeedback<>0 and q.id=$id
	  ");
		foreach ($quizes as $qui) {
			$data2[] = $qui;
			$all_quiz_grades += $qui->grade;
		}
	}

	$quizes_count = sizeof($data1) + sizeof($data2);
	$user_grades_count = 0;
	$user_grades_count_total = 0;
	foreach ($enrolled_students as $user) {
		foreach ($data1 as $record) {
			$data = quiz_report_user($record->id, $user->id);
			if (empty($data)) {
				$user_grades_count += 0;
			} else {
				$user_grades_count += $data->grade;
			}
		}
		foreach ($data2 as $record) {
			$data = quiz_report_user($record->id, $user->id);
			if (empty($data)) {
				$user_grades_count += 0;
			} else {
				$user_grades_count += $data->grade;
			}
		}
		if ($user_grades_count != 0) {
			$count = $user_grades_count / $all_quiz_grades;
			$user_grades_count = (round($count, 2)) * 100 . "%";
			$fullname = $user->fname . " " . $user->lname;
			$image = "";
			// if (!empty($user->url)) {
			// 	$image = $CFG->wwwroot . '/theme/edumy/images/teachers/' . $user->url;
			// }
			$user_optional_data = $DB->get_record('optional_data_aibrahim', array('userid' => $user->id));
			if(empty( $user_optional_data->empty))
		{
			$centername ='';
		}
		else{
			$centername =$user_optional_data->empty; //center name
		}
		if(empty($user_optional_data->school )){
			$schoolname = '';
		}
		else{
			$schoolname=$user_optional_data->school;
		}
			$quizData[] = array('user' => $user->id, 'fullname' => $fullname, "center_name" =>$centername , "school_name" => $schoolname, 'image' => $image, 'user_grades' => $user_grades_count);
			$user_grades_count = 0;
		} else {
			$fullname = $user->fname . " " . $user->lname;
			$user_grades_count = "0";
			$image = "";
			// if (!empty($user->url)) {
			// 	$image = $CFG->wwwroot . '/theme/edumy/images/teachers/' . $user->url;
			// }
			$user_optional_data = $DB->get_record('optional_data_aibrahim', array('userid' => $user->id));
			if(empty( $user_optional_data->empty))
			{
				$centername ='';
			}
			else{
				$centername =$user_optional_data->empty; //center name
			}
			if(empty($user_optional_data->school )){
				$schoolname = '';
			}
			else{
				$schoolname=$user_optional_data->school;
			}

			$noaverage[] = array('user' => $user->id, 'fullname' => $fullname, "center_name" => $centername, "school_name" => $schoolname, 'image' => $image, 'user_grades' => $user_grades_count);
		}
	}
	if ($option == "averageDesc" || $option == " ") {
		usort($quizData, function ($a, $b) {
			return $b['user_grades'] > $a['user_grades'];
		});
	} elseif ($option == "averageAsc") {
		usort($quizData, function ($a, $b) {
			return $b['user_grades'] < $a['user_grades'];
		});
	}
	return json_encode(['count' => $quizes_count, 'quizes_grade' => $all_quiz_grades, 'average' => $quizData, 'noaverage' => $noaverage]);
}

function device_information($userid, $deviceid, $aspects, $model, $man, $physical)
{
	global $DB;
	$device_data = $DB->get_record('mobile_device_information', array('userid' => $userid));
	if (empty($device_data)) {
		$ins = new stdClass();
		$ins->userid = $userid;
		$ins->deviceid = $deviceid;
		$ins->aspects = $aspects;
		$ins->model = $model;
		$ins->manufacturer = $man;
		$ins->phsical = $physical;
		$ins->id = $DB->insert_record('mobile_device_information', $ins);
		if (!empty($ins->id)) {
		} else {
			return json_encode(['data' => "error_created"]);
		}
		return json_encode(['data' => "done"]);
	} else {
		$ins = new stdClass();

		$ins->id = $device_data->id;
		$ins->deviceid = $deviceid;
		$ins->aspects = $aspects;
		$ins->model = $model;
		$ins->manufacturer = $man;
		$ins->phsical = $physical;
		$ins->id = $DB->update_record('mobile_device_information', $ins);
		if (!empty($ins->id)) {
			return json_encode(['data' => "done"]);
		} else {
			return json_encode(['data' => "error_updated"]);
		}
	}
}
function upload_user_image($id)
{
    global $DB, $CFG;
    $user = $DB->get_record('user', array('id' => $id));
    $user_context = $DB->get_record('context', array('instanceid' => $id, 'contextlevel' => 30));
    $fs = get_file_storage();
    $files = $fs->get_area_files($user_context->id, 'user', 'icon', 0, 'sortorder DESC, id ASC', false);
    if (count($files) < 1) {
        $image = '' . $CFG->wwwroot . '/pluginfile.php/' . $user_context->id . '/user/icon/0/f1.jpg?rev=0';
    } else {
        $file = reset($files);
        unset($files);
        $path = '/' . $user_context->id . '/user/icon/0' . $file->get_filepath() . $file->get_filename();
        $image = $CFG->wwwroot . '/pluginfile.php' . $path . "?rev=" . $user->picture;
    }
    return $image;
}
function pdfReport($id)
{
	global $DB;

	$views=$DB->get_records_sql("SELECT u.firstname,u.lastname ,lo.views,u.id  FROM `mdl_testnew_logs` as lo join mdl_user as u on u.id=lo.user where lo.testnewid=$id	");

	$course=$DB->get_record('testnew',array('id'=>$id));
	$enrolled_students = $DB->get_records_sql("SELECT u.id As id,u.firstname as fname , u.lastname as lname
	FROM mdl_course c INNER JOIN mdl_context cx ON c.id = cx.instanceid 
	INNER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
 	INNER JOIN mdl_user u ON ra.userid = u.id WHERE cx.contextlevel = '50' AND c.id=$course->course");
	$seenUsers=array();
	$notSeenUsers=array();
	$users=array();
	foreach($views as $view){
		array_push($users,$view->id);
		$seenUsers[]=array('userid'=>$view->id,'name'=>$view->firstname.' '.$view->lastname,'image'=>upload_user_image($view->id),'views'=>$view->views);
	}
	foreach($enrolled_students as $en){
		if(!in_array($en->id, $users)){
			$notSeenUsers[]=array('userid'=>$en->id,'name'=>$en->fname.' '.$en->lname,'image'=>upload_user_image($en->id),'views'=>0);
		}
	}
	return json_encode(['seenData' =>$seenUsers,'notSeenData'=>$notSeenUsers]);
}
function allPdfs($course){
	global $DB;
$pdfs=$DB->get_records('testnew',array('course'=>$course));
if(!empty($pdfs)){
	foreach($pdfs as $pdf){
		$data[]=array('id'=>$pdf->id,'name'=>$pdf->name);
	}
	return json_encode(['data' => $data]);

}
else{
	return json_encode(['data' =>null]);

}
}
function get_user_by_id($userId)
{
	global $DB, $OUTPUT, $CFG;

	$user = $DB->get_record('user', array('id' => $userId));

	if (!empty($user)) {
		return $user->firstname . " " . $user->lastname;
	}
	return  'no user found';
}
function assignReport($assignID, $token, $option = '')
{
	global $DB, $CFG;
	$submitted = array();
	$users = array();
	$notSubmitted = array();
	$course = $DB->get_record('assign', array('id' => $assignID));
	$get_grades = file_get_contents("$CFG->wwwroot/webservice/rest/server.php?wstoken=".$token."&wsfunction=mod_assign_get_grades&moodlewsrestformat=json&assignmentids[]=$assignID");
	$get_submissions = file_get_contents("$CFG->wwwroot/webservice/rest/server.php?wstoken=".$token."&wsfunction=mod_assign_get_submissions&moodlewsrestformat=json&assignmentids[]=$assignID");
	$get_grades = json_decode($get_grades, true);
	$get_submissions = json_decode($get_submissions, true);
	// return json_encode(['data' => $get_submissions]);

	if (empty($get_submissions['assignments'][0]) && !empty($get_submissions['warnings'][0])) {
		return json_encode(['state' => "fail", 'warnings' => $get_submissions['warnings'][0]]);
	}
	$get_submissions_count=count($get_submissions['assignments'][0]['submissions']);
	for ($i = 0; $i <$get_submissions_count ; $i++) {
		$check = $get_submissions['assignments'][0]['submissions'][$i]['plugins'][0]['fileareas'][0]['files'];
		if (!empty($check)) {
			$userid = $get_submissions['assignments'][0]['submissions'][$i]['userid'];
			$timecreated = $get_submissions['assignments'][0]['submissions'][$i]['timecreated'];
			$timemodified = $get_submissions['assignments'][0]['submissions'][$i]['timemodified'];
			$fileName = $check[0]['filename'];
			$fileurl = $check[0]['fileurl'];
			$submissionState = $get_submissions['assignments'][0]['submissions'][$i]['gradingstatus'];
			$grade = "-1.00000";
			$grader = -1;
			if ($submissionState == "graded") {
				$get_grades_count=count($get_grades['assignments'][0]['grades']);
				for ($j = 0; $j <$get_grades_count ; $j++) {
					$gradeUserId = $get_grades['assignments'][0]['grades'][$j]['userid'];
					if ($gradeUserId == $userid) {
						$grade = $get_grades['assignments'][0]['grades'][$j]['grade'];
						$grader = $get_grades['assignments'][0]['grades'][$j]['grader'];
					}
				}
			}
			array_push($users, $userid);
			$submitted[] = array(
				'userid' => $userid, 'name' => get_user_by_id($userid), 'image' => upload_user_image($userid), 'submissionState' => $submissionState, 'grade' => $grade, 'grader' => $grader, 'timecreated' => $timecreated, 'timemodified' => $timemodified,
				'fileName' => $fileName, 'fileurl' => $fileurl
			);
		}
	}
	$enrolled_students = $DB->get_recordset_sql("SELECT u.id As id,u.firstname as fname , u.lastname as lname
	FROM mdl_course c INNER JOIN mdl_context cx ON c.id = cx.instanceid 
	INNER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
 	INNER JOIN mdl_user u ON ra.userid = u.id WHERE cx.contextlevel = '50' AND c.id=$course->course");
	foreach ($enrolled_students as $en) {
		if (!in_array($en->id, $users)) {
			$notSubmitted[] = array('userid' => $en->id, 'name' => $en->fname . ' ' . $en->lname, 'image' => upload_user_image($en->id));
		}
	}
	if ($option == "atoz" || $option == " ") {
		usort($submitted, function ($a, $b) {
			return $b['name'] < $a['name'];
		});
		usort($notSubmitted, function ($a, $b) {
			return $b['name'] < $a['name'];
		});
	} elseif ($option == "ztoa") {
		usort($submitted, function ($a, $b) {
			return $b['name'] > $a['name'];
		});
		usort($notSubmitted, function ($a, $b) {
			return $b['name'] > $a['name'];
		});
	} elseif ($option == "maxGrade") {
		usort($submitted, function ($a, $b) {
			return $b['grade'] > $a['grade'];
		});
	} elseif ($option == "minGrade") {
		usort($submitted, function ($a, $b) {
			return $b['grade'] < $a['grade'];
		});
	} elseif ($option == "timecreated") {
		usort($submitted, function ($a, $b) {
			return $b['timecreated'] < $a['timecreated'];
		});
	}
	return json_encode(['state' => "done", 'submitted' => $submitted, 'notSubmitted' => $notSubmitted]);
}
function last_access_users($course,$option='')
{
	global $DB;
	try{
		$Last_access_users=$DB->get_records_sql('select mu.id, mula.timeaccess,op.empty as center,mu.firstname,mu.lastname
		from mdl_user_lastaccess mula
		  inner join mdl_user mu on mula.userid = mu.id
		  inner join mdl_course mc on mc.id = mula.courseid
		  LEFT OUTER JOIN mdl_optional_data_aibrahim as op ON mu.id=op.userid
		  
		where mc.id ='.$course.';');
		$enrolled_students = $DB->get_records_sql("SELECT u.id As id 
		,CONCAT(u.firstname,' ',u.lastname )as fullname ,op.empty as center,op.school as school
		FROM mdl_course c 
		INNER JOIN mdl_context cx ON c.id = cx.instanceid 
		INNER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
		 INNER JOIN mdl_user u ON ra.userid = u.id 
		 LEFT OUTER JOIN mdl_optional_data_aibrahim as op ON u.id=op.userid
		WHERE cx.contextlevel = '50' AND c.id=$course");
		$viewList=array();
		$users=array();
		$notViewList=array();
		foreach ($Last_access_users as $last){
			if(empty($last->center)){
				$centername="";
			}
			else{
				$centername=$last->center;
			}
			$studentRole = $DB->get_field('role', 'id', array('shortname' => 'student'));
			$isStudent = $DB->record_exists('role_assignments', ['userid' => $last->id, 'roleid' => $studentRole]);
			if($isStudent){

				$lastAccess='0';
				if(date('m', $last->timeaccess) === date('m')) {
				$lastAccess=date('m/d/Y H:i:s', $last->timeaccess);
				$viewList[]=array('id'=>$last->id ,'name'=>$last->firstname.' '.$last->lastname,'lastaccess'=>$lastAccess,'centername'=>$centername);
				array_push($users,$last->id);
				}
				
		
			}
		}
	foreach($enrolled_students as $en_s){
		if(!in_array($en_s->id,$users)){
			if(empty($en_s->center)){
				$centername="";
			}
			else{
				$centername=$en_s->center;
			}
			$checkLastAccess=$DB->get_record('user_lastaccess',array('userid'=>$en_s->id,'courseid'=>$course));
			$lastAccess='0';
			if(!empty($checkLastAccess)){
				$lastAccess=date('m/d/Y H:i:s', $checkLastAccess->timeaccess);

			}
			$notViewList[]=array('id'=>$en_s->id ,'name'=>$en_s->fullname,'lastaccess'=>$lastAccess,'centername'=>$centername);
	
		}
	}
	if ($option == "asc"||$option==" ") {
		usort($viewList, function ($a, $b) {
			return $b['lastaccess'] < $a['lastaccess'];
		});
		usort($notViewList, function ($a, $b) {
			return $b['lastaccess'] > $a['lastaccess'];
		});
	}
	elseif($option=="desc"){
		usort($viewList, function ($a, $b) {
			return $b['lastaccess'] > $a['lastaccess'];
		});
		usort($notViewList, function ($a, $b) {
			return $b['lastaccess'] > $a['lastaccess'];
		});
	}
	return json_encode(['state' => "done", 'viewed' => $viewList, 'notViewed' => $notViewList]);
	}
	catch(Exception $e){
		return json_encode(['state' => "fail", 'viewed' => [], 'notViewed' => [],"error"=>$e->getMessage() ]);

	}
	

}
