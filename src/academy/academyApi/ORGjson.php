<?php

// $json=$DB->get_records_sql("SELECT * from mdl_user ");
require_once('../config.php');
$PAGE->set_url($CFG->wwwroot.'/json/json.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/course/externallib.php');
require_once($CFG->dirroot . '/user/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/enrol/externallib.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once('PHPMailer/src/Exception.php');
require_once('PHPMailer/src/PHPMailer.php');
require_once('PHPMailer/src/SMTP.php');

define('PARAM_STRING','string');
// echo PARAM_STRING;
$function= required_param('function', PARAM_RAW);
$id= optional_param('id',-1, PARAM_INT);
$token = optional_param('token',  0, PARAM_TEXT);
$username= optional_param('username',  0, PARAM_TEXT);
$year  = optional_param('year',  0, PARAM_INT);
$teacherRating  = optional_param('rating',  0, PARAM_INT);
$feedBackText = optional_param('feedback', 0,PARAM_TEXT);
$courseId = optional_param('courseID', -1, PARAM_INT);
$categoryId = optional_param('categoryId', -1, PARAM_INT);
$teacherId= optional_param('teacherId',-1, PARAM_INT);
$userids= optional_param('userids',array(), PARAM_INT);
$feedBackId= optional_param('feedbackId',-1, PARAM_INT);
$userFirstName = optional_param('firstname', 0,PARAM_TEXT);
$userlastName = optional_param('lastname', 0,PARAM_TEXT);
$imageId = optional_param('imageID',-1,PARAM_INT);
$videoID = optional_param('videoID',-1,PARAM_INT);
$email = optional_param('email',  0, PARAM_TEXT);
$requestedid= optional_param('requestedid',-1, PARAM_INT);

  // Token
  if($function=='check'){
    echo check_mail();
}
// if($function=='check'){
//     echo login();
// }
//forget password api 
if($function=='forget_password'){
    echo forget_password($username);
}//end

//get teacher profile image 
if ($function == 'get_teacher_image'){
	echo get_teacher_image($id);
}

if ($function == 'sign_up'){
	echo signUp();
}

if ($function == 'signUpParent'){
	echo signUpParent();
}

if($function == 'get_teacher_years'){
    echo get_teacher_years($teacherId);
}

//check if token is valide or not 
if(!empty($token) ){

    $api = new webservice();
    $array = array();
    try{
        $array = $api->authenticate_user($token);
    if (!empty($array)){
        $array = json_encode( $api->authenticate_user($token));
        //echo $array;
        $arr = json_decode($array,true);
        $userID= $arr['user']['id'];
//check user is student or teacher
       if($function=='check_isStudent'){
            echo check_isStudent($userID);
        }
		//get all teachers in database api 
        if($function=='teachers'){
            echo teachers();
        }
		
		//get all related courses by student year levele 
      if ($function == 'get_all_related_courses'){
          echo get_related_courses($year,$userID);
      }
	  //get all student feedbacks 
        if ($function == 'get_user_feedbacks'){
            echo get_user_feedbacks($userID);
        }
		
		if ($function == 'get_enrol_courses'){
	        echo get_enrol_courses($userID);
        }
		
		if ($function == 'teacher_data'){
	       echo get_teacher_profile_data($id);
       }
	   if ($function == "add_teacher_rating"){
		   echo add_teacher_rating($userID,$id,$teacherRating);
	   }
	   if ($function== 'add_student_feedback'){
		   echo add_student_feedback($userID,$teacherId,$courseId,$feedBackText);
	   }
	   if ($function== 'delete_student_feedback'){
		   echo delete_student_feedback($feedBackId);
	   }
       if($function == 'upload_image'){
           echo upload_image($userID);
       }
       if($function == 'add_teacher_images'){
           echo add_teacher_images($userID);
       }
       if($function == 'delete_teacher_images'){
            echo delete_teacher_images($imageId);
       }
	   if($function == 'edit_user_data'){
           echo edit_user_data($userID,$userFirstName,$userlastName);
       }
	   if($function == 'add_teacher_videos'){
	       echo add_teacher_videos($userID);
       }
	   if($function == 'delete_teacher_videos'){
	       echo delete_teacher_videos($videoID);
       }
	   if ($function == 'get_course_descriptions'){
		   echo get_course_descriptions($courseId);
	   }
	   if ($function == 'get_user_name'){
	       echo get_user_name($userID);
       }
	   if($function == 'search_user_by_course'){
	       echo  search_user_by_course($email,$courseId);
       }
       if($function == 'search_user'){
           echo search_user($email,$courseId);
       }
       if($function == 'get_contact_requests_sent'){
           echo get_contact_requests_sent($userID,$requestedid);
       }
       if($function == 'delete_contact_request'){
        echo delete_contact_request($userID,$requestedid);
      }
	  if ($function == 'change_password'){
		  echo change_password($userID);
	  }
	  if ($function == 'get_user_by_id'){
		  echo get_user_by_id($id);
	  }
      if($function == 'create_conversation'){
          echo create_conversation($userids);
      }
      if($function == 'get_h5p_result'){
          echo get_h5p_result($courseId);
      }
	  if($function == 'insert_course_reservation'){
		  $userid = intval($userID);
          echo insert_course_reservation($userid,$courseId);
      }
	  if($function == 'is_course_reserved'){
		  $userid = intval($userID);
          echo is_course_reserved($userid,$courseId);
      }
	  if($function == 'delete_course_reservation'){
		  $userid = intval($userID);
          echo delete_course_reservation($userid,$courseId);
      }
	  
	 if ($function == 'all_course_reservations'){

	     echo all_course_reservations();
    }
	if ($function == 'all_accept_course_reservations'){

	     echo all_accept_course_reservations();
    }
	if ($function == 'accept_user_reservation'){

	     echo accept_user_reservation($id,$courseId);
    }
	if ($function == 'get_courses_by_category'){

	     echo get_courses_by_category($categoryId);
    }
    if ($function == 'create_child'){

        echo create_child($email,$userID,$username,1);
    }
    if ($function == 'get_parent_data'){

       echo get_parent_data($userID);
    }
	if ($function == 'get_child_courses'){
	     echo get_child_courses($id);
    }
      
    }
    else 
       echo json_encode( ['message'=>'invalide token']);

    }catch(Exception $e){
        echo json_encode( ['message'=>'invalide token',"exception"=>$e]);
    }
    

}
function change_password($userID){
	global $DB;
	$user=$DB->get_record('user',array("id"=>$userID));
	if(!empty($user)){
		$userPassword = $user->password;
		$oldPassword = $_GET["oldpassword"];
		$newPassword = $_GET["newpassword"];
		if (!empty($oldPassword) && !empty($newPassword)){
			$reason = null;
			$usercheck = authenticate_user_login($user->username, $oldPassword, false, $reason, false);
            $userupdate = new stdClass();
			if (!empty($usercheck)){
				$hashnewPassword = hash_internal_user_password($newPassword);
				$userupdate->id = $user->id;
                $userupdate->password=$hashnewPassword;
                $userupdate->id = $DB->update_record('user', $userupdate);
				if (!empty($userupdate->id)){
					return json_encode( ['message'=>'passwordchanged']);
				}
			}
			else{
				return json_encode( ['message'=>'incorrectpassword']);
			}
		}
		
    }
    return json_encode( ['message'=>'passworderror']);
}

function signUp(){
	global $DB;
	$userInfo = new stdClass();
	
	$yearInfo = new stdClass();
    $context=new stdClass();
	
	$yearMap=array("primary 1"=>1, "primary 2"=>2, "primary 3"=>3, "primary 4"=>4,"primary 5"=>5,"primary 6"=>6,"preparatory 1"=>7,"preparatory 2"=>8,"preparatory 3"=>9,"Secondary 1"=>10,"Secondary 2"=>11,"Secondary 3"=>12);
	
	try{
		
		$firstname = $_GET["firstname"];
        $lastname = $_GET["lastname"];
	    $username = $_GET["username"];
	    $email = $_GET["email"];
	    $password = $_GET["password"];
	    $uYear = $_GET["year"];
		$Phone = $_GET["studentPhone"];
		$parentPhone = $_GET["parentPhone"];
		//return json_encode([$firstname,$lastname,$username,$email,$uYear,$Phone,$parentPhone]);
		
	} catch(Exception $e){
             echo json_encode( ['message'=>$e]);
     }
	
	if (!empty($firstname)&&!empty($lastname) && !empty($username) && !empty($email) && !empty($password) && !empty($uYear) && !empty($Phone) && !empty($parentPhone)){
		
		try{

			$getuserbyusername = $DB->get_record('user',array('username'=>$username));
            if ($getuserbyusername != null){
                return json_encode(["message"=> "error usernameisused"]);
            }
            
			$getuserbyemail = $DB->get_record('user',array('email'=>$email));
            if ($getuserbyemail != null){
                return json_encode(["message"=> "error emailisused"]);
            }


			$hashPass=hash_internal_user_password($password);
		
		    $userInfo->firstname = $firstname;
            $userInfo->lastname = $lastname;
	        $userInfo->username = $username;
    	    $userInfo->email = $email;
		    $userInfo->password = $hashPass;
			$userInfo->phone1 = $Phone;
			$userInfo->phone2 = $parentPhone;
			$userInfo->confirmed = 1;
			$userInfo->mnethostid = 1;
			
			
	        $userInfo->id = $DB->insert_record('user', $userInfo);
			
			$key = array_search(intval($uYear), $yearMap);
			
			$yearInfo->userid = $userInfo->id;
			$yearInfo->fieldid = 1;
			$yearInfo->data = $key;
			$yearInfo->dataformat = 0;
			

			$yearInfo->id = $DB->insert_record('user_info_data', $yearInfo);
			
		   $context->contextlevel=30;
		   
		   $context->instanceid=$userInfo->id;
			$context->depth=2;
			$context->locked=0;
            $context->id= $DB->insert_record('context', $context);
            $context->path='/1/'.$context->id;
            $context->id=$DB->update_record('context',$context);
			
			$getContextid = $DB->get_record("context",array("instanceid"=>$userInfo->id));
			
			$createSudent = new stdClass();
            $createSudent->roleid=  5;
            $createSudent->contextid = $getContextid->id;
            $createSudent->userid = $userInfo->id;
            $createSudent->modifierid = $userInfo->id;
              
            $createSudent->id = $DB->insert_record('role_assignments', $createSudent);
                        
		
		return json_encode(["message"=> "successful"]);
			
		} catch(Exception $e){
             echo json_encode( ['message'=>$e]);
         }
	}
	else {
		return json_encode(["message"=> "error empty"]);
	}
	
}

//signUp parant 

function signUpParent(){
	global $DB;
	$userInfo = new stdClass();
	$context=new stdClass();
	try{
		
		$firstname = $_GET["firstname"];
        $lastname = $_GET["lastname"];
	    $username = $_GET["username"];
	    $email = $_GET["email"];
	    $password = $_GET["password"];
	    //$studentEmail = $_GET["studentEmail"];
        $parentPhone = $_GET["parentPhone"];

	} catch(Exception $e){
             echo json_encode( ['message'=>$e]);
     }
	
	if (!empty($firstname)&&!empty($lastname) && !empty($username) && !empty($email) && !empty($password) /*&& !empty($studentEmail)*/ &&!empty($parentPhone) ){
		
		try{
			
			$getuserbyusername = $DB->get_record('user',array('username'=>$username));
            if ($getuserbyusername != null){
                return json_encode(["message"=> "error usernameisused"]);
            }
            
			$getuserbyemail = $DB->get_record('user',array('email'=>$email));
            if ($getuserbyemail != null){
                return json_encode(["message"=> "error emailisused"]);
            }
            
            //$selectStudent = $DB->get_record("user",array("email"=>$studentEmail));

            //if($selectStudent){
          
                //$selectStudent->id));
                   
                    //if($getContextid->id !== null){
                        $hashPass=hash_internal_user_password($password);
		
                        $userInfo->firstname = $firstname;
                        $userInfo->lastname = $lastname;
                        $userInfo->username = $username;
                        $userInfo->email = $email;
                        $userInfo->password = $hashPass;
                        $userInfo->phone1 = $parentPhone;
                        $userInfo->confirmed = 1;
                        $userInfo->mnethostid = 1;
                        
                        
                       $userInfo->id=$DB->insert_record('user', $userInfo);
					   	$get_user=$DB->get_record('user',array('username'=>$userInfo->username));
							$context->contextlevel=30;
							$context->instanceid=$get_user->id;
							$context->depth=2;
							$context->locked=0;
							 $context->id= $DB->insert_record('context', $context);
                             $context->path='/1/'.$context->id;
                             $context->id=$DB->update_record('context',$context);
                        //$selectParentid = $DB->get_field('user', 'MAX(id)', array());
						$getContextid = $DB->get_record("context",array("instanceid"=>$get_user->id));
                        $createParent = new stdClass();
                        $createParent->roleid=  9;
                        $createParent->contextid = $getContextid->id;
                        $createParent->userid = $get_user->id;//$selectParentid;
                        $createParent->modifierid = $get_user->id;//$selectStudent->id;
                        
                        $create_result = $DB->insert_record('role_assignments', $createParent);
                        
                        if($create_result){
                            //$get_user=$DB->get_record('user',array('username'=>$userInfo->username));
                            //$res=create_child($studentEmail,$get_user->id,0);
                        
                            //if($res!='Error'){
                                return json_encode(["message"=> "successful"]);
                            //}
                        }
                        //var_dump($createParent);
                        //redirect($CFG->wwwroot."/login/index.php");
			
                      
                    /*}else{
                        return json_encode(["message"=> "error empty"]);
                    }
                
            /*}else{
                return json_encode(["message"=> "error empty"]);
            }*/

		} catch(Exception $e){
              echo json_encode( ['message'=>$e]);
         }
	}
	else {
        
		return json_encode(["message"=> "error empty"]);
	}
	return json_encode(["message"=> "error empty"]);
	
}

//check if the user is a teacher or is a student

function check_isStudent($id){
    global $DB;
    $studentRole = $DB->get_field('role', 'id', array('shortname' => 'student'));
    $isStudent = $DB->record_exists('role_assignments', ['userid' => $id, 'roleid' => $studentRole]);
    $teacherRole = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'));
    $isTeacher = $DB->record_exists('role_assignments', ['userid' => $id, 'roleid' => $teacherRole]);
	$parentRole = $DB->get_field('role', 'id', array('shortname' => 'parent'));
    $isParent = $DB->record_exists('role_assignments', ['userid' => $id, 'roleid' => $parentRole]);
	$admins = get_admins();
    $isadmin = false;
    foreach($admins as $admin) {
        if ($id == $admin->id) {
         $isadmin = true;
         break;
        }
    }
	$roleassignments = $DB->get_records('role_assignments', ['userid' => $id]);
    $manager=false;
    foreach($roleassignments as $role){
		if($role->roleid==1){
			$manager=true;
			break;
		}
	}
	
    if($isadmin || $manager){
		return json_encode(["message"=>'admin','id'=>$id],200);
	}
    elseif ($isStudent){
        return json_encode(["message"=>'student','id'=>$id],200);
    }
    elseif($isTeacher){
        return json_encode(["message"=>'teacher','id'=>$id],200);
    }
	elseif($isParent){
        return json_encode(["message"=>'parent','id'=>$id],200);
    }
    else{
        return json_encode(["message"=>'Not Teacher Or a Student'],200);
    }

}
//get all teachers in the site
 function teachers(){
    global $DB,$OUTPUT,$CFG;
    $array=array();
    $teachers=$DB->get_records_sql("SELECT DISTINCT u.*  FROM mdl_user as u INNER JOIN mdl_role_assignments as role ON role.userid=u.id and role.roleid=3");
    $fs = get_file_storage();
    foreach($teachers as $teacher){
        $context = context_user::instance($teacher->id);
        //$url=$CFG->wwwroot.'/pluginfile.php/'.$context->id.'/user/icon/edumy/f3?rev='.$teacher->picture.'';
		if (empty($teacher->url)){
			$url=$CFG->wwwroot.'/pluginfile.php/'.$context->id.'/user/icon/edumy/f3?rev='.$teacher->picture.'';
		}
		else{
			$url=$CFG->wwwroot.'/theme/edumy/images/teachers/'.$teacher->url.'';
		}
		
		
            $teacher->src=$url;
         array_push($array, $teacher);
    }
    return json_encode(["message"=>array_values($array)]);
}

function generate_random_code($username){
    global $DB,$OUTPUT,$CFG;

    $ins = new stdClass();
    $code="";
    $record=$DB->get_record('random_code',array('username'=>$username));
    if(empty($record)){
        $ins->user=$record->id;
        $ins->code=random_string(10);
        $code= $ins->code;
        $ins->id = $DB->insert_record('random_code', $ins);
    }
    else{
        $ins->id=$record->id;
        $ins->user=$USER->id;
        $ins->code=random_string(10);
        $code= $ins->code;
        $ins->id = $DB->update_record('random_code', $ins);
    }
    return $code;

}

//forget password function 
function forget_password($username){
    global $DB,$OUTPUT,$CFG;
    $user=$DB->get_record('user',array("username"=>$username));
    // $code=generate_random_code($username);
    if(!empty($user)){
        $phpmailer = new PHPMailer();
$phpmailer->SMTPOptions = array(
    'ssl' => array(
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    )
);
$phpmailer->isSMTP();
$phpmailer->Host = 'smtp.nitg-eg.com';
$phpmailer->SMTPAuth = true; 
$phpmailer->Port = 26;
$phpmailer->SMTPSecure = 'tls';
$phpmailer->Username = 'noreply@nitg-eg.com';
$phpmailer->Password = 'N.IT@202106';
 $phpmailer->setFrom('noreply@nitg-eg.com', 'Success Academy');
$phpmailer->addAddress($user->email, $username);
$phpmailer->Subject = 'Reset Password';
$message='You should go to these link to change password <a href= '. $CFG->wwwroot.'/json/confirm.php>Confirm Password</a>';
$phpmailer->Body=$message;
$phpmailer->IsHTML(true); 
  if(!$phpmailer->send()){
    echo "Mailer Error: " . $phpmailer->ErrorInfo;
}
else{
    echo json_encode(["message"=>"Sent"],true);
}
    }
    else{
        echo json_encode(["message"=>"username is not exist"]);
    }

  
}


function login(){
  echo  \core\session\manager::get_login_token();
}

//get related courses 
function get_related_courses($year,$id){
    global $DB,$OUTPUT,$CFG;
    $all_related_courses=$DB->get_records_sql("SELECT instanceid from mdl_customfield_data where value='$year' ");
    $array = array();
    foreach($all_related_courses as $courses){
       
       array_push($array ,$courses->instanceid);

    }
    $arrayImploded = implode(", ", $array);
   
    $courses = core_course_external::get_courses_by_field('ids', $arrayImploded);
    return json_encode(["relatedCourses"=>array_values($courses["courses"]),"warning_course"=>array_values($courses["warnings"])]);

}

//get all user feedback 
function get_user_feedbacks($userID){
    global $DB,$OUTPUT,$CFG;
    $checkfeedback = $DB->get_records_sql("SELECT * FROM mdl_feedbacks WHERE user ='$userID'  ");
    $feedbacks = array();
    foreach ($checkfeedback as $feedback){
        $returnedusers = core_user_external::get_users_by_field('id',
            array($feedback->teacher_id));
        $feedback->teacher = $returnedusers[0];
        array_push($feedbacks,$feedback);
    }
    return json_encode(["feedbacks"=>$feedbacks]);

}

function get_teacher_profile_data($teacherID){
	//teacher image link https://academy.nitg-eg.com/theme/edumy/images/teachers/656_f3.jpg
	global $DB,$OUTPUT,$CFG;
	//$userData = get_complete_user_data('id', $teacherID);
	$getTeacher = $DB->get_records_sql('SELECT * FROM mdl_user WHERE id='.$teacherID.' ');
	$userEnroledCourses = enrol_get_users_courses($teacherID);
	//$userEnroledCourses = core_enrol_external::get_users_courses($teacherID,true);
	$rating=$DB->get_records_sql("SELECT ceil(AVG(rating)) as rating FROM `mdl_teacher_rating` WHERE teacher_id=$teacherID");
	
	$teacherCourses = array();
	foreach ($userEnroledCourses as $course){
		
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
        foreach ($courselist->get_course_contacts() as $contact) {
            $coursecontacts[] = array(
                'id' => $contact['user']->id,
                'fullname' => $contact['username'],
                'roles' => array_map(function($role){
                    return array('id' => $role->id, 'name' => $role->displayname);
                }, $contact['roles']),
                'role' => array('id' => $contact['role']->id, 'name' => $contact['role']->displayname),
                'rolename' => $contact['rolename']
            );
        }
        $course->contacts = $coursecontacts;
			$teacherCourses[] = $course;
	}
	//$userData->courses = array_values($userEnroledCourses);
	$getTeacher = array_values($getTeacher);
    $getTeacher[0]->courses = $teacherCourses; //concatinate course with teacher data
	
	//attach feedbacks to teacher data
	$feedBacks = array();
	$checkfeedback = $DB->get_records_sql("SELECT * FROM mdl_feedbacks WHERE teacher_id ='$teacherID'  ");
	$checkfeedback = array_values($checkfeedback);
	foreach($checkfeedback as $feedback){
		$userData = get_complete_user_data('id', $feedback->user);
		$feedback->username = $userData->firstname;
		$feedback->userimage = $userData->url;
		$feedBacks[]= $feedback;
	}
	$getTeacher[0]->feedbacks = $feedBacks;
	
	//attach photos
	$getTeacherPhotos = $DB->get_records_sql("SELECT * FROM mdl_teachersphotos WHERE teacher_id= '$teacherID'");
	$getTeacher[0]->photos = array_values($getTeacherPhotos);
	
	//attach videos
	// get videos for a teacher
    $getVideos = $DB->get_records_sql("SELECT* FROM mdl_vimeovedios WHERE teacher_id='$teacherID'");
	$getTeacher[0]->videos = array_values($getVideos);
	
	//attach rating
	$rating=$DB->get_records_sql("SELECT ceil(AVG(rating)) as rating FROM `mdl_teacher_rating` WHERE teacher_id=$teacherID");
	$rating=array_values($rating);
	$getTeacher[0]->rating = $rating[0]->rating;
	
    return json_encode(['teacher'=>$getTeacher[0]]);
	
}


function search_user_by_course($email,$courseId){
    global $DB,$OUTPUT,$CFG;
    
    $user=$DB->get_record('user',array('email'=>$email));
   
    $userEnroledCourses = array();
    $userEnroledCourses = enrol_get_users_courses($user->id);
    $userEnroledCourses=array_values($userEnroledCourses);
  
    foreach($userEnroledCourses as $course ){
        if($course->id == $courseId){
            return json_encode(["user"=>$user]);
        }
    }
    return json_encode(["error"=>'no user found']);

}

function get_user_by_id($userId){
    global $DB,$OUTPUT,$CFG;
    
    $user=$DB->get_record('user',array('id'=>$userId));
   
   if (!empty($user)){
	   return json_encode(["user"=>$user]);
   }
    return json_encode(["error"=>'no user found']);

}

function delete_contact_request($id,$requestedid){
    global $DB,$OUTPUT,$CFG;
    $DB->delete_records('message_contact_requests', array( 'userid'=> $id , 'requesteduserid'=>$requestedid));
    $user=$DB->get_records_sql("SELECT * FROM mdl_message_contact_requests WHERE userid= '$id' and requesteduserid='$requestedid' ");
    if(empty($user) ){
        return json_encode(["message"=> 'deleted']);
    }
    else{
        return json_encode(["message"=> 'error']);
    }

}

function get_contact_requests_sent($id,$requestedid){
    global $DB,$OUTPUT,$CFG;
    $user=$DB->get_records_sql("SELECT * FROM mdl_message_contact_requests WHERE userid= '$id' and requesteduserid='$requestedid' ");

    if(empty($user) ){
        return json_encode(["message"=> 'no']);
    }
    else{
        return json_encode(["message"=> 'yes']);
    }

}

function get_enrol_courses($id){
	global $DB,$OUTPUT,$CFG;
	$i=0;
	$userEnroledCourses = core_enrol_external::get_users_courses($id,true);
	//return json_encode(['teacher'=>$userEnroledCourses]);
	$EnroledCourses = enrol_get_users_courses($id);
	//return json_encode(["courses"=>$EnroledCourses]);
	$enrolCourses = array();
	foreach ($EnroledCourses as $course){
		
		$courselist = new core_course_list_element($course);
            /*$overviewfiles = array();
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
			$course->overviewfiles = $overviewfiles;*/
			
			$coursecontacts = array();
            foreach ($courselist->get_course_contacts() as $contact) {
             $coursecontacts[] = array(
                'id' => $contact['user']->id,
                'fullname' => $contact['username'],
                'roles' => array_map(function($role){
                    return array('id' => $role->id, 'name' => $role->displayname);
                }, $contact['roles']),
                'role' => array('id' => $contact['role']->id, 'name' => $contact['role']->displayname),
                'rolename' => $contact['rolename']
             );
        }
		//$course->contacts = $coursecontacts;
		$userEnroledCourses[$i]['contacts'] = $coursecontacts;
		$i++;
		//$enrolCourses[] = $course;
	}
	
	return json_encode(["courses"=>$userEnroledCourses]);
	//return json_encode(["courses"=>$enrolCourses]);
}

function get_child_courses($id){
	global $DB,$OUTPUT,$CFG;
	$i=0;
	//$userEnroledCourses = core_enrol_external::get_users_courses($id,true);
	//return json_encode(['teacher'=>$userEnroledCourses]);
	$EnroledCourses = enrol_get_users_courses($id);
	//return json_encode(["courses"=>$EnroledCourses]);
	$enrolCourses = array();
	foreach ($EnroledCourses as $course){
		
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
            foreach ($courselist->get_course_contacts() as $contact) {
             $coursecontacts[] = array(
                'id' => $contact['user']->id,
                'fullname' => $contact['username'],
                'roles' => array_map(function($role){
                    return array('id' => $role->id, 'name' => $role->displayname);
                }, $contact['roles']),
                'role' => array('id' => $contact['role']->id, 'name' => $contact['role']->displayname),
                'rolename' => $contact['rolename']
             );
        }
		$course->contacts = $coursecontacts;
		$enrolCourses[] = $course;
		//$enrolCourses[$i]['contacts'] = $coursecontacts;
		$i++;
		//$enrolCourses[] = $course;
	}
	
	//return json_encode(["courses"=>$userEnroledCourses]);
	return json_encode(["courses"=>$enrolCourses]);
}

function add_teacher_rating($studentID,$teacherID,$rating){
	global $DB,$OUTPUT,$CFG;
	$ins = new stdClass();
	
	$ins->rating = $rating;
        
        $ins->teacher_id = $teacherID;
        $ins->user = $studentID;
        $record=$DB->get_record('teacher_rating',array('user'=>$studentID,'teacher_id'=>$teacherID));
		if(empty($record)){
            $ins->id = $DB->insert_record('teacher_rating', $ins);
			//return json_encode(['message'=>$ins]);
        }
        else{
            $ins->id=$record->id;
            $ins->id = $DB->update_record('teacher_rating', $ins);
        }
		
		return json_encode(['message'=>'ratingadded']);
}

function add_student_feedback($studentID,$teacherID,$courseID,$feedBack){
	global $DB,$OUTPUT,$CFG;
		
	$ins = new stdClass();
    $ins->feedback = $feedBack;
    $ins->title = 'feedBack';
    $ins->course = $courseID;
    $ins->teacher_id = $teacherID;
    $ins->user = $studentID;
    $ins->id = $DB->insert_record('feedbacks', $ins);
	
	return json_encode(['message'=>'feedbackadded']);
}

function delete_student_feedback($feedBackID){
	global $DB,$OUTPUT,$CFG;
		
	$ins = new stdClass();
	
    $ins->id = $DB->delete_records('feedbacks', array('id' => $feedBackID));
	
	return json_encode(['message'=>'feedbackdeleted']);
}

function add_teacher_images($id){
    global $DB,$OUTPUT,$CFG;
    $postImageName = $_FILES['image']['name'];
    $postImageTemp = $_FILES['image']['tmp_name'];
    $postImage = rand(0,1000)."_".$postImageName;
    $uploadFiles = move_uploaded_file($postImageTemp,$CFG->dirroot."/theme/edumy/images/teachers/".$postImage);
    if($uploadFiles){
        $insertData = $DB->execute("INSERT INTO mdl_teachersphotos(teacher_id,photos) VALUE('$id', '$postImage')   ");

        return json_encode(['message'=>'image added']);
    }
    else{
        return json_encode(['message'=>'error']);

    }

}

function add_teacher_videos($id){
    global $DB,$OUTPUT,$CFG;
    $video=$_POST['videoLink'];
    $tag = '<div style="padding:55% 0 0 0;position:relative;">';
    $closingTag = "</div>";
    if(strpos($video,$closingTag)==true ){
        $video = str_replace($closingTag," ",$video );
        $video = str_replace($tag," ",$video );
    }
    $ins = new stdClass();
    $ins->videos=  $video;
    $ins->teacher_id = $id;
    $ins->id = $DB->insert_record('teachervideos', $ins);
    if($ins){
        return json_encode(['message'=>'video uploaded']);
    }
    else{
        return json_encode(['message'=>'error']);

    }
}

function delete_teacher_videos($id){
    global $DB,$OUTPUT,$CFG;
    $sql = "DELETE FROM mdl_vimeovedios WHERE id=$id";
    $DB->execute($sql);
    return json_encode(['message'=>'video deleted']);
}

function delete_teacher_images($id){
    global $DB,$OUTPUT,$CFG;
        $sql = "DELETE FROM mdl_teachersphotos WHERE id=$id";
        $DB->execute($sql);
        return json_encode(['message'=>'image deleted']);
}

function upload_image($id){
    global $DB,$OUTPUT,$CFG;
    $postImageName = $_FILES['image']['name'];
    $postImageTemp = $_FILES['image']['tmp_name'];
    $postImage = rand(0,1000)."_".$postImageName;
    $uploadFiles = move_uploaded_file($postImageTemp,$CFG->dirroot."/theme/edumy/images/teachers/".$postImage);
    $checkUser = $DB->get_records_sql("SELECT * FROM mdl_user WHERE id = '$id'");
    if($uploadFiles){
        $DB->execute(" UPDATE mdl_user SET url= '$postImage' WHERE id = '$id' ");
        return json_encode(['message'=>'image uploaded']);
    }
    else{
        return json_encode(['message'=>'error']);

    }



}

function get_teacher_image($teacherid){
	global $DB,$OUTPUT,$CFG;
	$getTeacher = $DB->get_records_sql('SELECT url,firstname,lastname FROM mdl_user WHERE id='.$teacherid.' ');
	$getTeacher = array_values($getTeacher);

	$url = $CFG->wwwroot.'/theme/edumy/images/teachers/'.$getTeacher[0]->url.'';

	return json_encode(['image'=>$url,'fullname'=>$getTeacher[0]->firstname." ".$getTeacher[0]->lastname]);

}

function edit_user_data($userid,$firstname,$lastname){
	global $DB,$OUTPUT,$CFG;
	try{
		if (!empty($firstname)){
		$DB->execute(" UPDATE mdl_user SET firstname= '$firstname' WHERE id = '$userid' ");
	    }
	if (!empty($lastname)){
		$DB->execute(" UPDATE mdl_user SET lastname= '$lastname' WHERE id = '$userid' ");
	   }
	}catch(Exception $e){
        return json_encode( ['message'=>'error']);
    }
	
	return json_encode( ['message'=>'done']);
	
}

function get_course_descriptions($coursId){
	global $DB,$OUTPUT,$CFG;
	$ins = new stdClass();
	
	$cDesc = $DB->get_record('customfield_data',array('instanceid'=>$coursId,'fieldid'=>15));
	$ins->courseDesc = $cDesc->value;
	
	$obj = array();
	$obj[0]=$DB->get_record('customfield_data',array('instanceid'=>$coursId,'fieldid'=>2));
	$obj[1]=$DB->get_record('customfield_data',array('instanceid'=>$coursId,'fieldid'=>3));
	$obj[2]=$DB->get_record('customfield_data',array('instanceid'=>$coursId,'fieldid'=>4));
	$obj[3]=$DB->get_record('customfield_data',array('instanceid'=>$coursId,'fieldid'=>5));
	$obj[4]=$DB->get_record('customfield_data',array('instanceid'=>$coursId,'fieldid'=>6));
	$obj[5]=$DB->get_record('customfield_data',array('instanceid'=>$coursId,'fieldid'=>7));
	$obj[6]=$DB->get_record('customfield_data',array('instanceid'=>$coursId,'fieldid'=>8));
	$obj[7]=$DB->get_record('customfield_data',array('instanceid'=>$coursId,'fieldid'=>9));
	$obj[8]=$DB->get_record('customfield_data',array('instanceid'=>$coursId,'fieldid'=>10));
	$obj[9]=$DB->get_record('customfield_data',array('instanceid'=>$coursId,'fieldid'=>11));
	
	$coursePrice = $DB->get_record('customfield_data',array('instanceid'=>$coursId,'fieldid'=>12));
	$CourseDuration= $DB->get_record('customfield_data',array('instanceid'=>$coursId,'fieldid'=>13));
	$ins->coursePrice = $coursePrice->value;
	$ins->CourseDuration = $CourseDuration->value;
	
    $Allenrolusers=$DB->get_records_sql("SELECT count(*)as c From mdl_user_enrolments x,mdl_enrol y where x.enrolid=y.id and y.courseid='$coursId'");
	$Allenrolusers = array_values($Allenrolusers);
	$ins->allenrolusers = $Allenrolusers[0]->c;
	
	
	$objects = array();
	
	foreach($obj as $element){
		if ($element->value != null){
			$ins_obj = new stdClass();
			$ins_obj->value = $element->value;
			array_push($objects, $ins_obj);
		}
	}
	$ins->objectives = $objects;
	
	$promo = $DB->get_record('customfield_data',array('instanceid'=>$coursId,'fieldid'=>22));
	if ($promo != null){
		$ins->promo = 'true';
	}
	else {
		$ins->promo= 'false';
	}
	

   return json_encode( ['message'=>$ins]);
}

function get_user_name($userId){
    global $DB,$OUTPUT,$CFG;
    //$userData = get_complete_user_data('id', $teacherID);
    $getUser = $DB->get_records_sql('SELECT firstname,lastname,username FROM mdl_user WHERE id='.$userId.' ');
    $getUser = array_values($getUser);
    return json_encode( ['data'=>$getUser[0]]);
}


function create_conversation($userids){
    global $DB,$OUTPUT,$CFG;
    //return \core_message\api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL;
    sort($userids);
    $conversation = new stdClass();
    $conversation->convhash = null;
    $conversation->convhash = sha1(implode('-', $userids));
    if ($record = $DB->get_record('message_conversations', ['convhash' => $conversation->convhash])) {
        return json_encode([$record->id]);
    }
    $conversation->type =1;
    $conversation->enabled = 1;
    $conversation->timecreated = time();
    $conversation->timemodified = $conversation->timecreated;
    $conversation->id = $DB->insert_record('message_conversations', $conversation);
    $arrmembers = [];
    foreach ($userids as $userid) {
        $member = new stdClass();
        $member->conversationid = $conversation->id;
        $member->userid = $userid;
        $member->timecreated = time();
        $member= $DB->insert_record('message_conversation_members', $member);

        $arrmembers[] = $member;
    }

	$conversation->members = $arrmembers;

    return json_encode([$conversation->id]);


}

function get_teacher_years($teacherId){
    global $DB,$OUTPUT,$CFG;
    $years = $DB->get_records_sql("SELECT * FROM mdl_teacher_years WHERE teacherID= '$teacherId' ");
    $years = array_values($years);
    return json_encode($years);

}

function get_h5p_result($courseId){
    global $DB,$OUTPUT,$CFG;
    $h5p = new stdClass();
    $h5p = $DB->get_records_sql("SELECT * FROM mdl_hvp WHERE course= '$courseId' ");
    $h5p = array_values($h5p);
    $id = $h5p[0]->id;
    $result = new stdClass();
    $result = $DB->get_records_sql("SELECT * FROM mdl_hvp_xapi_results WHERE content_id= '$id' "); 
    return json_encode($result);

}

function insert_course_reservation($userId,$courseId){
	global $DB;
	
	$ins = new stdClass();
	$ins->userid =$userId;
	$ins->course =$courseId;
	//$ins->timecreated=date('Y-d-m H:i:s',time());
    // $yearMap=array("primary 1"=>1, "primary 2"=>2, "primary 3"=>3, "primary 4"=>4,"primary 5"=>5,"primary 6"=>6,"preparatory 1"=>7,"preparatory 2"=>8,"preparatory 3"=>9,"Secondary 1"=>10,"Secondary 2"=>11,"Secondary 3"=>12);
    $yearMap=array(1=>"primary 1", 2=>"primary 2", 3=>"primary 3", 4=>"primary 4",5=>"primary 5",6=>"primary 6",7=>"preparatory 1",8=>"preparatory 2",9=>"preparatory 3",10=>"Secondary 1",11=>"Secondary 2",12=>"Secondary 3");
    $userYear=$DB->get_record('user_info_data',array('userid' =>$userId,'fieldid'=>1));
    $key = array_search($userYear->data, $yearMap);
    // return $key;
    $courseYear=$DB->get_record('customfield_data',array('instanceid'=>$courseId,'fieldid'=>1));
    // return $courseYear;
    if($key==$courseYear->value){
        $res = $DB->insert_record('course_reservation',$ins);
        if ($res){
            return json_encode(["data"=> 'Successfully']);
        }
    }
    else{
        return json_encode(["data"=> 'NotAllowed']);
    }

	

	
	return json_encode(["data"=> 'Error']);
}

function delete_course_reservation($userId,$courseId){
	global $DB;
	
	$res = $DB->delete_records('course_reservation', array( 'userid'=> $userId , 'course'=>$courseId));
	
	if ($res){
		return json_encode(["data"=> 'deleted']);
	}
	
	return json_encode(["data"=> 'Error']);
}

function is_course_reserved($userId,$courseId){
	global $DB;
	
	$record=$DB->get_record('course_reservation',array('userid'=>$userId,'course'=>$courseId,'accept'=>0));
	
	if (!empty($record)){
		return json_encode(["data"=> 'true']);
	}
	
	return json_encode(["data"=> 'false']);
}

function all_course_reservations(){
	global $DB;
	
	 $usersReserve = $DB->get_records_sql("SELECT courser.id,
                            user.id as userid,concat(user.firstname , ' ', user.lastname)as fullname ,user.url as image,user.phone1 as phone ,user.email,courser.course
                            FROM mdl_course_reservation courser
                            JOIN mdl_user user ON courser.userid = user.id
                            WHERE courser.accept=0 and user.deleted = 0

	    ");
	$usersReserve = array_values($usersReserve);
	$final_data = array();

	foreach ($usersReserve as $user){
		
		$teachers = $DB->get_records_sql("SELECT u.id As id,concat(u.firstname , ' ', u.lastname)as teachername, c.fullname As coursename
                           FROM   mdl_course c
                           LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
                           LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
                           LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id 
						   WHERE cx.contextlevel = '50' AND c.id= '$user->course'");
        $teachers = array_values($teachers);
		$teacher = $teachers[0];
		
		$user->teachername=$teacher->teachername;
		$user->coursename = $teacher->coursename;
		
		array_push($final_data,$user);
	}
	
	
	return json_encode(['data'=>$final_data]);
	
}

function all_accept_course_reservations(){
	global $DB;
	
	 $usersReserve = $DB->get_records_sql("SELECT courser.id,
                            user.id as userid,concat(user.firstname , ' ', user.lastname)as fullname ,user.url as image,user.phone1 as phone ,user.email,courser.course
                            FROM mdl_course_reservation courser
                            JOIN mdl_user user ON courser.userid = user.id
                            WHERE courser.accept=1 and user.deleted = 0

	    ");
	$usersReserve = array_values($usersReserve);
	$final_data = array();

	foreach ($usersReserve as $user){
		
		$teachers = $DB->get_records_sql("SELECT u.id As id,concat(u.firstname , ' ', u.lastname)as teachername, c.fullname As coursename
                           FROM   mdl_course c
                           LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
                           LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
                           LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id 
						   WHERE cx.contextlevel = '50' AND c.id= '$user->course'");
        $teachers = array_values($teachers);
		$teacher = $teachers[0];
		
		$user->teachername=$teacher->teachername;
		$user->coursename = $teacher->coursename;
		
		array_push($final_data,$user);
	}
	
	
	return json_encode(['data'=>$final_data]);
	
}

function accept_user_reservation($userId,$courseId){
	global $DB;
	
	$record=$DB->get_record('course_reservation',array('userid'=>$userId,'course'=>$courseId,'accept'=>0));
	if (!empty($record)){
		$res=new stdClass();
	    $res->id=$record->id;
		$res->accept=1;
		$state = $DB->update_record('course_reservation',$res);
				// return $response_end;
		if ($state){
			return json_encode(["data"=> 'Successfully']);
		}
	}
	
	return json_encode(["data"=> 'error']);
}

function get_courses_by_category($categoryid){
	global $DB;
	$courses = $DB->get_records_sql("SELECT * FROM mdl_course WHERE category= $categoryid");
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
		$teachers = $DB->get_records_sql("SELECT u.id As id,concat(u.firstname , ' ', u.lastname)as fullname, c.fullname As coursename
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
function get_count_students($course){
    global $DB;
    $Allenrolusers=$DB->get_records_sql("SELECT u.id  from mdl_enrol as enroll join mdl_user_enrolments as ue on enroll.id=ue.enrolid
    join mdl_user as u on u.id=ue.userid join mdl_role_assignments as ra on u.id=ra.userid
    where ra.roleid=5 and enroll.courseid=".$course."
    GROUP by u.id");
    return count($Allenrolusers);
}
function create_child($email,$parentid,$username,$check=0){
    global $DB;
    $user=$DB->get_record('user',array('email'=>$email,'username'=>$username));
    $studentRole = $DB->get_field('role', 'id', array('shortname' => 'student'));
    $isStudent = $DB->record_exists('role_assignments', ['userid' => $user->id, 'roleid' => $studentRole]);
    if($isStudent){
        $check_id=$DB->get_record('parent_child',array('parentid'=>$parentid,'childid'=>$user->id));
        if(!$check_id){
            $ins = new stdClass();
            $ins->parentid =$parentid;
            $ins->childid =$user->id;
            $res=$DB->insert_record('parent_child',$ins);
            $getContextid = $DB->get_record("context",array("instanceid"=>$user->id));
                        $createParent = new stdClass();
                        $createParent->roleid=  9;
                        $createParent->contextid = $getContextid->id;
                        $createParent->userid = $parentid;//$selectParentid;
                        $createParent->modifierid = $user->id;//$selectStudent->id;
                        $create_result = $DB->insert_record('role_assignments', $createParent);
            if($create_result){
                if($check==0){
                    return 'Successfully created';
                }
                else{
                    return json_encode(['data'=>'Successfully']);
                }
            }
        }
       
       
    }
    if($check==0){
        return 'Error';
    }
    else{
        return json_encode(['data'=>'Error']);
    }
}

function get_parent_data($parent){
    global $DB;
    $childs=$DB->get_records_sql('SELECT  p.childid,u.firstname,u.lastname,u.url from mdl_parent_child as p join mdl_user as u on u.id=p.childid where parentid='.$parent.'');
    return json_encode(['childs'=>array_values($childs)]);
}


?>