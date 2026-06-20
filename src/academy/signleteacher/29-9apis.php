<?php

require_once('../../config.php');
require_once($CFG->dirroot . '/course/externallib.php');
require_once($CFG->dirroot . '/user/externallib.php');
require_once($CFG->libdir . "/weblib.php");
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

header('Content-Type: application/json');

define('PARAM_STRING', 'string');
$function = optional_param('function', '', PARAM_RAW);
$token = optional_param('token',  0, PARAM_TEXT);

$email = optional_param('email',  0, PARAM_TEXT);
$password = optional_param('password', 0, PARAM_TEXT);
$fname = optional_param('fname', 0, PARAM_TEXT);
$lname = optional_param('lname', 0, PARAM_TEXT);
$phone1 = optional_param('phone1', 0, PARAM_TEXT);
$phone2 = optional_param('phone2', 0, PARAM_TEXT);
$city = optional_param('city', '', PARAM_RAW);
$school = optional_param('school', '', PARAM_RAW);
$center = optional_param('center', '', PARAM_RAW);
$year  = optional_param('year',  0, PARAM_INT);

$role = optional_param('role', 0, PARAM_TEXT);
$oldPassword = optional_param('oldpassword', 0, PARAM_TEXT);

$newPassword = optional_param('newpassword', 0, PARAM_TEXT);

$id  = optional_param('id',  0, PARAM_INT);
$teacherid  = optional_param('teacherid',  0, PARAM_INT);
$lang = optional_param('lang',  0, PARAM_TEXT);

$imageId = optional_param('imageId',  0, PARAM_INT);

$course = optional_param('course',  0, PARAM_INT);
$activityid = optional_param('activityid',  0, PARAM_INT);
$code = optional_param('code', '', PARAM_RAW);

if ($function == 'login') {
    $user = new User();
    echo $user->login($email, $password);
}
elseif($function == 'confirm_signup') {
    echo confirm_signup();
}
elseif ($function == 'register') {
    $user = new User();
    echo $user->register($fname, $lname, $email, $email, $password, $phone1, $phone2, $role, $year, $city, $school, $center,$course);
} elseif ($function == 'aboutInfo') {
    echo aboutInfo();
} elseif ($function == 'getGovernments') {
    echo getGovernments($lang);
} elseif ($function == 'delete_teacher_images') {
    echo delete_teacher_images($imageId);
}
// elseif ($function == 'change_image') {
//     $user = new User();
//     echo $user->change_image();
// }

if (!empty($token)) {

    $api = new webservice();
    $array = array();
    try {
        $array = $api->authenticate_user($token);
        if (!empty($array)) {
            $array = json_encode($api->authenticate_user($token));
            //echo $array;
            $arr = json_decode($array, true);
            $userID = $arr['user']['id'];
            if ($function == 'get_user_courses') {
                $user = new User();
                echo $user->get_user_courses($userID, $teacherid);
            } elseif ($function == 'change_password') {
                $user = new User();
                echo $user->change_password($userID);
            } elseif ($function == 'upload_image') {
                $user = new User();
                echo $user->upload_image($userID);
            } 
            elseif ($function == 'add_new_view') {
                $user = new User();
                echo $user->add_new_view($userID,$id);
            }
            elseif ($function == 'edit_user') {

                $user = new User();
                echo $user->edit_user_data($userID, $fname, $lname, $phone1, $phone2, $email, $city, $center, $school);
            } elseif ($function == 'change_pas') {
                $user = new User();
                echo $user->change($userID, $oldPassword, $newPassword);
            } elseif ($function == 'get_parent_data') {
                $user = new User();
                echo $user->get_parent_data($userID);
            } elseif ($function == 'check_codes') {

                echo check_codes($course, $activityid, $code, $userID);
            }
        }
    } catch (Exception $e) {
        echo json_encode(['status' => "fail", 'data' => null, "error" => $e->errorcode]);

        // echo json_encode(['status' => "fail",'data' => null, "error" => $e]);
    }
}

class User
{
    public $id;
    public $name;
    public $email;
    public $password;
    public $role;
    public $phone;
    public $year;
    public $deleted_at;
    public  function login($email, $password)
    {
        global $DB, $CFG;
        $check = strpos($email, '@');
        if ($check == true) {
            $user = $DB->get_record('user', array('email' => $email));
        } else {
            $user = $DB->get_record('user', array('username' => $email));
        }
        try {
            if ($user) {
                if (password_verify($password, $user->password)) {
                    $roleassignments = $DB->get_record('role_assignments', ['userid' => $user->id]);
                    $role = $DB->get_record('role', ['id' => $roleassignments->roleid]);
                    if ($role->id == 3) {
                        $this->role = "teacher";
                    } elseif ($role->id == 5) {
                        $this->role = "student";
                    } elseif ($role->id == 6) {
                        $this->role = "Guest";
                    } elseif ($role->id == 9) {
                        $this->role = "Parent";
                    }
                    elseif ($role->id == 4){
                        $this->role = "assistant";

                    }
                    $user_info_data = $DB->get_record('user_info_data', ['userid' => $user->id, "fieldid" => "1"]);
                    $optional_data = $DB->get_record('optional_data_aibrahim', ['userid' => $user->id]);
                    $yearMap = array(1 => "primary 1", 2 => "primary 2", 3 => "primary 3", 4 => "primary 4", 5 => "primary 5", 6 => "primary 6", 7 => "preparatory 1", 8 => "preparatory 2", 9 => "preparatory 3", 10 => "Secondary 1", 11 => "Secondary 2", 12 => "Secondary 3");
                    $key = array_search($user_info_data->data, $yearMap);
                    if (empty($key)) {
                        $key = 0;
                    }
                    if (empty($optional_data->school)) {
                        $optional_data->school = "";
                    }
                    if (empty($optional_data->empty)) {
                        $optional_data->empty = "";
                    }
                    $url = "";
                    // if (empty($user->url)) {
                    //     $url = "https://academy.nitg-eg.com/theme/edumy/images/teachers/user.png";
                    // } else {
                    //     $url = "https://academy.nitg-eg.com/theme/edumy/images/teachers/" . $user->url;
                    // }
                    // if ($user->picture != 0 || !empty($user->picture)) {
                        $user_context = $DB->get_record('context',array('instanceid'=>$user->id,'contextlevel'=>30));
                        $fs = get_file_storage();
                        $files = $fs->get_area_files($user_context->id, 'user', 'icon', 0, 'sortorder DESC, id ASC', false);
                        if (count($files) < 1) {
                            $url = ''.$CFG->wwwroot.'/pluginfile.php/'.$user_context->id.'/user/icon/0/f1.jpg?rev=0';

                            // $url = '';
                        } else {
                            $file = reset($files);
                            unset($files);
                            $path = '/' . $user_context->id . '/user/icon/0' . $file->get_filepath() . $file->get_filename();
                            $url = $CFG->wwwroot . '/pluginfile.php' . $path."?rev=".$user->picture;;
                        }
                    // }
                    // $url = $image;
                    $token = file_get_contents("" . $CFG->wwwroot . "/login/token.php?username=$email&password=$password&service=moodle_mobile_app");
                    $token = json_decode($token);
                    $userData = array(
                        "id" => (int)$user->id, "username" => $user->username,
                        "firstName" => $user->firstname, "lastName" => $user->lastname, "image" => $url,
                        "token" => $token->token,
                        "email" => $user->email, "role" =>  $this->role, "year" => $key,
                        "phone" => $user->phone1, "phone2" => $user->phone2,
                        "city" => $user->city, "school" => $optional_data->school, "center" => $optional_data->empty
                    );
                    return json_encode(['status' => "success", 'data' => $userData, "error" => '']);
                } else {

                    return json_encode(['status' => "fail", "data" => null, 'error' => 'Email or username is not valid']);
                }
            } else {
                return json_encode(['status' => "fail", "data" => null, 'error' => 'User not found']);
            }
        } catch (Exception $e) {
            return json_encode(['status' => "fail", "data" => null, 'error' => $e->getMessage()]);
        }
    }
    function enrol_student($id, $userid, $roleid, $enrolmethod = 'manual')
{
    global $DB;
    $user = $DB->get_record('user', array('id' => $userid, 'deleted' => 0), '*', MUST_EXIST);
    $studentRole = $DB->get_field('role', 'id', array('shortname' => 'student'));
    $isStudent = $DB->record_exists('role_assignments', ['userid' => $user->id, 'roleid' => $studentRole]);
    try {
        if ($isStudent) {
            $course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
            $context = context_course::instance($course->id);
            if (!is_enrolled($context, $user)) {
                $enrol = enrol_get_plugin($enrolmethod);
                if ($enrol === null) {
                    return 'false';
                }
                $instances = enrol_get_instances($course->id, true);
                $manualinstance = null;
                foreach ($instances as $instance) {
                    if ($instance->enrol == $enrolmethod) {
                        $manualinstance = $instance;
                        break;
                    }
                }
                if ($manualinstance == null) {
                    $instanceid = $enrol->add_default_instance($course);
                    if ($instanceid === null) {
                        $instanceid = $enrol->add_instance($course);
                    }
                    $instance = $DB->get_record('enrol', array('id' => $instanceid));
                }
                $enrol->enrol_user($instance, $userid, $roleid);
            }
            return 'true';
        }
    } catch (Exception $e) {
        return 'false';
    }
}
    function register($firstname, $lastname, $username, $email, $password, $phone = null, $phone2 = null, $role, $year = null, $city = null, $school = null, $center = null,$courseID)
    {
        global $DB, $CFG;
        $userInfo = new stdClass();
        $yearInfo = new stdClass();
        $roleAssignment = new stdClass();
        $record = new stdClass();
        $data="hi";
        $check_userrname = $DB->get_record('user', array('username' => $username));
        $check_email = $DB->get_record('user', array('email' => $email));
        if (!empty($check_userrname)) {
            return json_encode(['status' => "fail", "data" => null, "error" => 'username exists']);
        } elseif (!empty($check_email)) {
            return json_encode(['status' => "fail", "data" => null, "error" => 'Email exists']);
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return json_encode(['status' => "fail", "data" => null, "error" => 'invalid email']);
        } else {
            try {
                $userInfo->firstname = $firstname;
                $userInfo->lastname = $lastname;
                $userInfo->username = $username;
                $userInfo->email = $email;
                $hashPass = hash_internal_user_password($password);
                $userInfo->password = $hashPass;
                $userInfo->phone1 = $phone;
                $userInfo->phone2 = $phone2;
                $userInfo->confirmed = 1;
                $userInfo->mnethostid = 1;
                // $userInfo->url = "user.png";
                if ($role == 9) {
                    $userInfo->id = $DB->insert_record('user', $userInfo);
                } elseif ($year != null && $role != 9) {
                    if ($city != null) {
                        $userInfo->city = $city;
                    }
                    $userInfo->country = "EG";
                    $userInfo->lang = "ar";
                    // if ($school != null && $center != null) {
                        $userInfo->id = $DB->insert_record('user', $userInfo);
                        $yearMap = array("primary 1" => 1, "primary 2" => 2, "primary 3" => 3, "primary 4" => 4, "primary 5" => 5, "primary 6" => 6, "preparatory 1" => 7, "preparatory 2" => 8, "preparatory 3" => 9, "Secondary 1" => 10, "Secondary 2" => 11, "Secondary 3" => 12);
                        $key = array_search(intval($year), $yearMap);
                        $yearInfo->userid = $userInfo->id;
                        $yearInfo->fieldid = 1;
                        $yearInfo->data = $key;
                        $yearInfo->dataformat = 0;
                        $yearInfo->id = $DB->insert_record('user_info_data', $yearInfo);

                        $optional_data = new stdClass();
                        $optional_data->userid = $userInfo->id;
                        $optional_data->school = $school;
                        $optional_data->empty = $center;
                        $optional_data->id = $DB->insert_record('optional_data_aibrahim', $optional_data);
                    // } else {
                    //     return json_encode(['status' => "fail", "data" => null, "error" => 'you have to write your school and your center']);
                    // }
                } elseif ($year == null && $role != 9) {
                    return json_encode(['status' => "fail", "data" => null, "error" => 'you have to add a year']);
                }
                $record->contextlevel = 30;
                $record->instanceid   =  $userInfo->id;
                $record->depth        = 0;
                $record->path         = null; //not known before insert
                $record->locked       = 0;
                $record->id = $DB->insert_record('context', $record);
                $parentpath = '/1';
                $record->path = $parentpath . '/' . $record->id;
                $record->depth = substr_count($record->path, '/');
                $DB->update_record('context', $record);
                $roleAssignment->roleid = $role;
                $roleAssignment->contextid = $record->id;
                $roleAssignment->userid = $userInfo->id;
                $roleAssignment->timemodified = time();
                $roleAssignment->modifierid = $userInfo->id;
                $roleAssignment->id = $DB->insert_record('role_assignments', $roleAssignment);
                // $token= \core\session\manager::get_login_token();
                $token = file_get_contents("" . $CFG->wwwroot . "/login/token.php?username=$username&password=$password&service=moodle_mobile_app");
                $token = json_decode($token);
                if ($role == 5) {
                    $this->role = "student";
                    $data= $this->enrol_student($courseID,$userInfo->id,5);

                } elseif ($role == 9) {
                    $this->role = "parent";
                }
                if (empty($school)) {
                    $school = "";
                }
                if (empty($center)) {
                    $center = "";
                }
                $url = "";
                // $url = "https://academy.nitg-eg.com/theme/edumy/images/teachers/" . $userInfo->url;
                $user_context = $DB->get_record('context',array('instanceid'=>$userInfo->id,'contextlevel'=>30));
                $fs = get_file_storage();
                $files = $fs->get_area_files($user_context->id, 'user', 'icon', 0, 'sortorder DESC, id ASC', false);
                if (count($files) < 1) {
                    $image = ''.$CFG->wwwroot.'/pluginfile.php/'.$user_context->id.'/user/icon/0/f1.jpg?rev=0';
                } else {
                        $file = reset($files);
                        unset($files);
                        $path = '/' . $user_context->id . '/user/icon/0' . $file->get_filepath() . $file->get_filename();
                        $image = $CFG->wwwroot . '/pluginfile.php' . $path."?rev=".$userInfo->picture;
                }
                $url = $image;
                $userData = array(
                    "id" => $userInfo->id, "username" => $userInfo->username, "firstName" => $userInfo->firstname,
                    "lastName" => $userInfo->lastname, "image" => $url, "token" => $token->token, "email" => $userInfo->email, "role" => $this->role, "year" => $year,
                    "phone" => $userInfo->phone1, "phone2" => $userInfo->phone2,
                    "city" => $city, "school" => $school, "center" => $center
                );
                return json_encode(['status' => "success", "data" => $userData, "error" => '']);
            } catch (Exception $e) {
                return json_encode(['status' => "fail", "data" => null, "error" => $e->getMessage()]);
            }
        }
    }
    function edit_description($text)
    {
        $cleaner_input = strip_tags($text);
        return $cleaner_input;
    }
    function get_user_courses($userid, $teacherid, $token = 0)
    {
        global $DB;
        try {
            $sql = "SELECT u.firstname AS name,u.lastname AS lastname, cat.description as catDesc,cat.name as catname,c.category as cat_id,c.summary As course_desc, c.fullname as coursename ,c.id as courseid
            FROM   mdl_course c
           LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
           LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
           LEFT OUTER JOIN   mdl_course_categories as cat ON c.category=cat.id
           LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id WHERE cx.contextlevel = '50' AND u.id= " . $teacherid . "";
            // $courses = $DB->get_recordset_sql("");
            $courses_data = array();

            $studentRole = $DB->get_field('role', 'id', array('shortname' => 'student'));
            $isStudent = $DB->record_exists('role_assignments', ['userid' => $userid, 'roleid' => $studentRole]);

            $assistantRole = $DB->get_field('role', 'id', array('shortname' => 'teacher'));
            $isAssistant = $DB->record_exists('role_assignments', ['userid' => $userid, 'roleid' => $assistantRole]);
            $courses = $DB->get_recordset_sql($sql);
            foreach ($courses as $key => $record) {
                // Do whatever you want with this record
                $context = context_course::instance($record->courseid, MUST_EXIST);
                $enrol = is_enrolled($context, $userid, '', true);
                $status = 0;
                $endtime = "";
                $enrolment = $DB->get_records_sql("SELECT ue.status as status ,ue.timeend as endtime from mdl_user_enrolments as ue join mdl_enrol as e on ue.enrolid=e.id where ue.userid=" . $userid . " and e.courseid=" . $record->courseid . "");
                foreach ($enrolment as $enrol) {
                    $status = $enrol->status;
                    $endtime = $enrol->endtime;
                }
                if ($enrol && $isStudent) {
                    $user = $DB->get_record('user', array('id' => $userid));
                    if ($user->suspended == 1) {
                        return json_encode(['status' => "fail", "data" => null, "error" => "This user is susbended from the site"]);
                    } elseif ($status == 1) {
                        return json_encode(['status' => "fail", "data" => null, "error" => "This user is susbended from course " . $record->courseid . ""]);
                    } elseif ($_SERVER['REQUEST_TIME'] > $endtime && $endtime != 0) {
                        return json_encode(['status' => "fail", "data" => null, "error" => "the enrolment has expired"]);
                    }
                }
                $price = $DB->get_record('customfield_data', array('fieldid' => 12, 'instanceid' => $record->courseid));
                if (empty($price)) {
                    $price = 'free';
                }
                $rating = $DB->get_records_sql("SELECT ceil(AVG(rate)) as rate FROM `mdl_course_rates` WHERE courseid=$record->courseid");
                $rate = 0;
                foreach ($rating as $rate) {
                    $rate = $rate->rate;
                }
             
                // return json_encode(['status' => "success", "data" =>, "error" => '']);

                // foreach($rating as)
                // $rating = array_values($rating);
                $view = $DB->get_record('course_views', array('courseid' => $record->courseid));
                if ($view->visit == null) {
                    $view->visit = '0';
                }
                $imges = $DB->get_record("course", array('id' => $record->courseid));
                $courselist = new core_course_list_element($imges);

                $url = "";
                $overviewfiles = array();
                foreach ($courselist->get_course_overviewfiles() as $file) {
                    $fileurl = moodle_url::make_webservice_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        null,
                        $file->get_filepath(),
                        $file->get_filename()
                    )->out(false);
                    $overviewfiles[] = array(
                        'filename' => $file->get_filename(),
                        'fileurl' => $fileurl,
                        'filesize' => $file->get_filesize(),
                        'filepath' => $file->get_filepath(),
                        'mimetype' => $file->get_mimetype(),
                        'timemodified' => $file->get_timemodified(),
                    );
                }
                $overviewfiles = $overviewfiles[0]['fileurl'];

                if ($enrol && ($isStudent||$isAssistant)) {
                    // $courses[$key]->enrolled = true;

                    $courses_data[] = array(
                        "courseId" => $record->courseid, "courseName" => $record->coursename,
                        "courseDesc" => format_string($record->course_desc),
                        "cat_id" => $record->cat_id,
                        "catname" => format_string($record->catname), "rate" => (int)$rate, "course_image" => $overviewfiles,
                        'price' => $price->value, 'views' =>$view->visit, "catDesc" => format_string($record->catDesc)
                    );
                    return json_encode(['status' => "success", "data" => $courses_data, "error" => '']);
                } elseif ($userid == $teacherid && ($endtime == 0 || $_SERVER['REQUEST_TIME'] < $endtime)) {
                    if ($status == 0) {
                        $courses_data[] = array(
                            "courseId" => $record->courseid, "courseName" => $record->coursename,
                            "courseDesc" => format_string($record->course_desc),
                            "cat_id" => $record->cat_id,
                            "catname" => format_string($record->catname), "rate" => (int)$rate, "course_image" => $overviewfiles,
                            'price' => $price->value, 'views' =>$view->visit, "catDesc" => format_string($record->catDesc),

                        );
                    }
                }
            }
            if (empty($courses_data)) {
                return json_encode(['status' => "fail", "data" => null, "error" => "this user is not enrolled in any course with this teacher"]);
            } else {
                return json_encode(['status' => "success", "data" => $courses_data, "error" => '']);
            }
        } catch (Exception $e) {
            return json_encode(['status' => "fail", "data" => null, "error" => "this user is not enrolled in any course with this teacher"]);
        }
    }
    function change_password($userid)
    {
        // return json_encode(['status' => "success", "data" => "updated", "error" => '']);

        global $DB;
        if ($_SERVER["REQUEST_METHOD"] == "POST") {

            $oldPassword = $_REQUEST['oldpassword'];
            $newPassword = $_REQUEST['newpassword'];
            // $name= $_REQUEST['name'];
            // $data = json_decode();
            // return file_get_contents("php://input");
            // return json_encode(['status' => "fail", "data" =>array('old'=>$oldPassword), "error" => "passwords are not the same"]);
            // return json_encode(['status' => "fail", "data" =>array('old'=>$oldPassword,'new'=>$newPassword,'name'=>$name), "error" => "passwords are not the same"]);

            $user = $DB->get_record('user', array('id' => $userid));
            try {
                if (password_verify($oldPassword, $user->password)) {
                    $new = new stdClass();
                    $new->id = $user->id;
                    $new->password = hash_internal_user_password($newPassword);
                    $new->id = $DB->update_record('user', $new);
                    if (!empty($new->id)) {
                        return json_encode(['status' => "success", "data" => "updated", "error" => '']);
                    } else {
                        return json_encode(['status' => "fail", "data" => '', "error" => "password not changed"]);
                    }
                } else {
                    return json_encode(['status' => "fail", "data" => array('old' => $oldPassword, 'new' => $newPassword), "error" => "passwords are not the same"]);
                }
            } catch (Exception $e) {
                return json_encode(['status' => "fail", "data" => '', "error" => $e->getMessage()]);
            }
        } else {
            return json_encode(['status' => "fail", "data" => '', "error" => "The  Method should be POST"]);
        }
    }

    function change($userid, $oldPassword, $newPassword)
    {

        global $DB;

        // $oldPassword = $_POST['oldpassword'];
        // $newPassword = $_POST['newpassword'];
        $user = $DB->get_record('user', array('id' => $userid));
        try {
            if (password_verify($oldPassword, $user->password)) {
                $new = new stdClass();
                $new->id = $user->id;
                $new->password = hash_internal_user_password($newPassword);
                $new->id = $DB->update_record('user', $new);
                if (!empty($new->id)) {
                    return json_encode(['status' => "success", "data" => "updated", "error" => '']);
                } else {
                    return json_encode(['status' => "fail", "data" => '', "error" => "password not changed"]);
                }
            } else {
                return json_encode(['status' => "fail", "data" => '', "error" => "passwords are not the same"]);
            }
        } catch (Exception $e) {
            return json_encode(['status' => "fail", "data" => '', "error" => $e->getMessage()]);
        }
    }
    function change_image()
    {
        global $DB;
        // $data = file_get_contents("php://input");
        // // echo $data;
        // $data=json_decode($data,true);
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $name = $_POST['name'];
            return json_encode(['status' => "success", "data" => $_FILES, "error" => '']);
        } else {
            return json_encode(['status' => "success", "data" => $_SERVER, "error" => '']);
        }

        // return $data;

        // if(isset($_FILES['image'])){
        //     return json_encode(['status' => "success", "data" =>$_FILES['image']['tmp_name'], "error" => '']);

        // }
        // else{
        //     return json_encode(['status' => "fail", "data" =>$_REQUEST, "error" => "passwords are not the same"]);

        // }
    }
    function upload_user_image($id)
{
    global $DB, $CFG;
    $user=$DB->get_record('user',array('id'=>$id));
    $user_context = $DB->get_record('context',array('instanceid'=>$id,'contextlevel'=>30));
    $fs = get_file_storage();
    $files = $fs->get_area_files($user_context->id, 'user', 'icon', 0, 'sortorder DESC, id ASC', false);
    if (count($files) < 1) {
        $image = ''.$CFG->wwwroot.'/pluginfile.php/'.$user_context->id.'/user/icon/0/f1.jpg?rev=0';
    } else {
            $file = reset($files);
            unset($files);
            $path = '/' . $user_context->id . '/user/icon/0' . $file->get_filepath() . $file->get_filename();
            $image = $CFG->wwwroot . '/pluginfile.php' . $path."?rev=".$user->picture;
    }
    return $image;
}
    function get_parent_data($parent)
    {
        global $DB, $CFG;
        try {
            $childs = $DB->get_records_sql('SELECT  p.childid,u.firstname,u.lastname  from mdl_parent_child as p join mdl_user as u on u.id=p.childid where parentid=' . $parent . '');
            foreach ($childs as $key => $value) {
        
                    $childs[$key]->url = $this->upload_user_image($value->childid);
                
            }

            return json_encode(['status' => "success", 'data' => array_values($childs), "error" => '']);
        } catch (Exception $e) {
            return json_encode(['status' => "fail", "data" => null, "error" => $e->getMessage()]);
        }
    }
    function edit_user_data($userid, $firstname = null, $lastname = null, $phone1 = null, $phone2 = null, $email = null, $city = null, $center = null, $school = null)
    {
        global $DB;
        try {
            $user = $DB->get_record('user', array('id' => $userid));
            $adittional_data = $DB->get_record('optional_data_aibrahim', array('userid' => $userid));
            if (empty($adittional_data)) {
                $adittional_data = new stdClass();
                $adittional_data->userid = $userid;
                $adittional_data->empty = "";
                $adittional_data->school = "";
                $adittional_data->id = $DB->insert_record('optional_data_aibrahim', $adittional_data);
                // return json_encode(['status' => "success", "data" =>$adittional_data , "error" => '']);

            }
            $new = new stdClass();
            $additional = new stdClass();
            $new->id = $user->id;
            $additional->id = $adittional_data->id;
            if (!empty($firstname)) {
                $new->firstname = $firstname;
            }
            if (!empty($lastname)) {
                $new->lastname = $lastname;
            }
            if (!empty($phone1)) {
                $new->phone1 = $phone1;
            }
            if (!empty($phone2)) {
                $new->phone2 = $phone2;
            }
            if (!empty($email)) {
                $new->username = $email;
                $new->email = $email;
            }
            if (!empty($city)) {
                $new->city = $city;
            }
            if (!empty($center)) {
                $additional->empty = $center;
            }
            if (!empty($school)) {
                $additional->school = $school;
            }
            if (!empty($firstname) || !empty($lastname) || !empty($phone1) || !empty($phone2) || !empty($email) || !empty($city)) {
                $new->id = $DB->update_record('user', $new);
            }
            if (!empty($center) || !empty($school)) {
                $additional->id = $DB->update_record('optional_data_aibrahim', $additional);
            }

            return json_encode(['status' => "success", "data" => "updated", "error" => '']);
        } catch (Exception $e) {
            return json_encode(['status' => "fail", "data" => null, "error" => $e->getMessage()]);
        }
    }
    function add_new_view($userid,$pdfid)
    {
        global $DB;
        $studentRole = $DB->get_field('role', 'id', array('shortname' => 'student'));
        $isStudent = $DB->record_exists('role_assignments', ['userid' => $userid, 'roleid' => $studentRole]);
        if($isStudent){
            $checkUser=$DB->get_record('testnew_logs',array('user'=>$userid,'testnewid'=>$pdfid));
            if(empty($checkUser)){
                $ins=new stdClass();
                $ins->views=1;
                $ins->testnewid=$pdfid;
                $ins->user=$userid;
                $ins->id=$DB->insert_record('testnew_logs',$ins);
                if(!empty($ins->id)){
                    return json_encode(['data' => "done"]);
                }
                else{
                    return json_encode(['data' => "error"]);
                }
            }else{
                $upd=new stdClass();
                $upd->id=$checkUser->id;
                $upd->views=$checkUser->views+1;
                $upd->id=$DB->update_record('testnew_logs',$upd);
                if(!empty($upd->id)){
                    return json_encode(['data' => "done"]);
                }
                else{
                    return json_encode(['data' => "error"]);
    
                }
            }
        }
        else{
            return json_encode(['data' => "NotStudent"]);
    
        }
        
    }
    function upload_image($id)
    {
        // global $DB, $OUTPUT, $CFG;
        // $postImageName = $_FILES['image']['name'];
        // $postImageTemp = $_FILES['image']['tmp_name'];
        // $postImage = rand(0, 1000) . "_" . $postImageName;
        // $uploadFiles = move_uploaded_file($postImageTemp, $CFG->dirroot . "/theme/edumy/images/teachers/" . $postImage);
        // $checkUser = $DB->get_records_sql("SELECT * FROM mdl_user WHERE id = '$id'");
        // if ($uploadFiles) {
        //     $DB->execute(" UPDATE mdl_user SET url= '$postImage' WHERE id = '$id' ");
        //     return json_encode(['status' => "success", "data" => $CFG->wwwroot . "/theme/edumy/images/teachers/" . $postImage, "error" => '']);;
        // } else {
        //     return json_encode(['status' => "fail", "data" => '', "error" => "error uploading the image"]);
        // }


        global $DB, $CFG;
        // $img=$_FILES['image']['tmp_name'];
        $user = $DB->get_record('user', array('id' => $id));
        if (isset($_FILES['image'])) {
            // 

            if (!empty($user)) {
                // var_dump($_FILES['image']['tmp_name']);

                require_once($CFG->libdir . '/gdlib.php');
                // path to the image
                $tempfile = $_FILES['image']['tmp_name'];

                if (file_exists($tempfile)) {

                    $usericonid = process_new_icon(context_user::instance($id, MUST_EXIST), 'user', 'icon', 0, $tempfile);


                    if ($usericonid) {

                        $DB->set_field('user', 'picture', $usericonid, array('id' => $id));
                    }
                }
                unset($tempfile);
                // $path = '/' . $user_context->id . '/user/icon/0' . $file->get_filepath() . $file->get_filename();
                // $image = $CFG->wwwroot . '/pluginfile.php' . $path;
                // return json_encode(['status' => "success", "data" => $CFG->wwwroot . "/pluginfile.php/" . $usericonid . "/user/icon0/f1.jpg", "error" => '']);
                // return json_encode(['status' => "success", "data" =>, "error" => '']);
                $url='';
                $user_context = $DB->get_record('context',array('instanceid'=>$id,'contextlevel'=>30));
                $fs = get_file_storage();
                $files = $fs->get_area_files($user_context->id, 'user', 'icon', 0, 'sortorder DESC, id ASC', false);
                if (count($files) < 1) {
                    $url = '';
                } else {
                    $file = reset($files);
                    unset($files);
                    $path = '/' . $user_context->id . '/user/icon/0' . $file->get_filepath() . $file->get_filename();
                    $url = $CFG->wwwroot . '/pluginfile.php' . $path."?rev=".$usericonid;
                }
                                return json_encode(['status' => "success", "data" =>$url, "error" => '']);

            } else {
                return json_encode(['status' => "fail", "data" => '', "error" => "User not found!"]);
            }
        } else {
            return json_encode(['status' => "fail", "data" => '', "error" => "error uploading the image"]);
        }
    }
}
function aboutInfo()
{
    $string = "
    
    <p style='color:black;'> 
    طلابي الأعزاء كل عام وأنتم بخير .... 
    التطبيق محاولة جادة ومتخصصة لتفعيل النظام الجديد بكل تفاصيله بمعنى أنك ستتدرب في كل حصة على الامتحانات والأسئلة وطرق الحل الصحيحة والتي تحقق لك التفوق بإذن الله في النظام التعليمي الجديد .
    التطبيق ليس بنكا لتخزين الأسئلة والتدريبات ، ولكنه موقع تعليمي متكامل ( سنتر الكتروني ) يحقق كل أهداف العملية التعليمية وعلى رأسها التعود على أسئلة الامتحانات بأشكالها المختلفة ، والمتابعة الجادة مني شخصيا ومن فريق العمل بشكل عام لكل إجاباتكم وحلولكم في جميع التقويمات 
    والهدف الأكبر للتطبيق أنه يجعلك على تواصل معي ومع فريق العمل على مدار 24 ساعة من خلال الأسئلة والاستفسارات المختلفة .
    سيكون بمقدورك أيضا من خلال التطبيق أن تقيم نفسك وتعرف مستواك الحقيقي من خلال الواجبات والاختبارات الجزئية والاختبارات العامة .
    طبعا سيكون الهاتف المحمول ( الموبايل ) والتاب الوسيلة التي نستخدمها في الاختبار الاسبوعي الذي سيكون في بداية كل حصة أو في المنزل في الموعد المحدد لكل الطلاب في نفس الوقت .
    تستطيع أيضا عرض أي مشكلة خاصة بعدم قدرتك على التحصيل والفهم لأي نوع من أنواع المسائل وسوف أقوم شخصيا بمتابعة هذه الحالات ووضعها على الطريق الصحيح .
    أيضا يحقق التطبيق التواصل الفعال بين ولي الأمر وبيننا لتحقيق الصالح العام للطالب في المقام الأول . من خلال متابعة درجات الطالب ومتابعة الحضور والغياب . 
    كما نقوم أيضا بعمل خطط لرفع مستوى الضعاف من خلال واجبات خاصة وتدريبات متميزة لتحسين المستوى .
    مع ملاحظة أن غياب ثلاث حصص يعرضك لغلق التطبيق والحرمان من خدماته بشكل كامل .
    على كل حال ... أهلا بكم  في هذا الملتقى التعليمي المتميز  لنتعاون جميعا من خلال منظومة إلكترونية متخصصة للوصول إلى أعلى الدرجات في مادة الرياضيات  مع مستر مدحت نبيل.
    </p>
    
    ";

    return json_encode(['status' => "success", "data" => $string, "error" => ""]);
}
function getGovernments($lang = '')
{
    global $DB, $USER;
    if ($lang == "en") {
        $governorates = $DB->get_records('governorates', array(), $sort = '', $fields = 'id ,governorate_name_en name', $limitfrom = 0, $limitnum = 0);
    } else {
        $governorates = $DB->get_records('governorates', array(), $sort = '', $fields = 'id ,governorate_name_ar name', $limitfrom = 0, $limitnum = 0);
    }

    if (!empty($governorates)) {
        return json_encode(["data" => array_values($governorates)]);
    } else {
        return json_encode(["error" => "No Governorates"]);
    }
}
function delete_teacher_images($imageId)
{
    global $DB, $OUTPUT, $CFG;
    $image = $DB->get_record('teachersphotos', array('id' => $imageId));
    if (!empty($image)) {
        $file = $CFG->dirroot . '/theme/edumy/images/teachers/' . $image->image;
        if (file_exists($file)) {
            unlink($file);
        }
        $DB->delete_records('teachersphotos', array('id' => $imageId));
        return json_encode(["status" => "success", "data" => "deleted", "error" => ""]);
    } else {
        return json_encode(["status" => "error", "data" => "", "error" => "No Image Found"]);
    }
}
function check_codes($course, $actid, $code, $userid)
{
    global $DB;
    try {
        $cm = $DB->get_record('course_modules', array('id' => $actid));
        $avail = json_decode($cm->availability);
        $id = '';
        // $x=json_decode($mod->availability);
        // var_dump($x->c[0]->type);
        for ($i = 0; $i < sizeof($avail->c); $i++) {
            if ($avail->c[$i]->type == 'group') {
                // =$_POST['group'];
                $group = $DB->get_record('groups', array('id' => $avail->c[$i]->id));
                if (strpos($group->name, 'week') !== false || strpos($group->name, 'اسبوع') !== false) {
                    $id = $avail->c[$i]->id;
                    break;
                }
            }
        }

        $groupCode = $DB->get_record('groups_attendence_codes', array('code' => $code, 'used' => 0));
        if (!empty($groupCode)) {
            $getGroupId = $DB->get_record('groups_attendence_patch', array('id' => $groupCode->patchid, 'courseid' => $course));
            if (!empty($getGroupId)) {
                if ($getGroupId->empty1 == 1) {
                    $checkAdding = groups_add_member($id, $userid);
                    if ($checkAdding) {
                        $upd = new stdClass();
                        $upd->id = $groupCode->id;
                        $upd->used = 1;
                        $upd->empty1 = $id;
                        $upd->empty2 = $userid;
                        $DB->update_record('groups_attendence_codes', $upd);
                        return json_encode(["status" => "success", "data" => "added", "error" => ""]);
                    } else {
                        return json_encode(['status' => "fail", "data" => '', "error" => "error adding to a group"]);
                    }
                } else {
                    if ($id == $getGroupId->groupid) {
                        $checkAdding = groups_add_member($getGroupId->groupid, $userid);
                        if ($checkAdding) {
                            $upd = new stdClass();
                            $upd->id = $groupCode->id;
                            $upd->used = 1;
                            $upd->empty2 = $userid; //userid
                            $DB->update_record('groups_attendence_codes', $upd);
                            return json_encode(["status" => "success", "data" => "added", "error" => ""]);
                        } else {
                            return json_encode(['status' => "fail", "data" => '', "error" => "error adding to a group"]);
                        }
                    } else {
                        return json_encode(['status' => "fail", "data" => '', "error" => "this code doesn't belong to this course or this group"]);
                    }
                }
            } else {

                return json_encode(['status' => "fail", "data" => '', "error" => "this code doesn't belong to this course or this group"]);
            }
        } else {
            return json_encode(['status' => "fail", "data" => '', "error" => "Group Code is not valid"]);
        }
    } catch (Exception $e) {
        return json_encode(['status' => "fail", "data" => '', "error" => $e->getMessage()]);
    }
}
function confirm_signup()
{
    return "false";
}