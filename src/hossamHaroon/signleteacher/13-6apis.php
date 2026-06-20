<?php

require_once("../config.php");
require_once($CFG->dirroot . '/course/externallib.php');
require_once($CFG->dirroot . '/user/externallib.php');
require_once($CFG->libdir . "/weblib.php");
require_once($CFG->dirroot . '/webservice/lib.php');

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

$id  = optional_param('id',  0, PARAM_INT);
$teacherid  = optional_param('teacherid',  0, PARAM_INT);


if ($function == 'login') {
    $user = new User();
    echo $user->login($email, $password);
} elseif ($function == 'register') {
    $user = new User();
    echo $user->register($fname, $lname, $email, $email, $password, $phone1, $phone2, $role, $year, $city, $school, $center);
}
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
            }
        }
    } catch (Exception $e) {
        echo json_encode(['status' => "fail", 'data' => null, "exception" => $e->errorcode]);

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
        global $DB;
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
                    $token = $DB->get_record('external_tokens', ['userid' => $user->id], $fields = "token");
                    $user_info_data = $DB->get_record('user_info_data', ['userid' => $user->id, "fieldid" => "1"]);
                    $optional_data = $DB->get_record('optional_data_aibrahim', ['userid' => $user->id]);
                    $yearMap = array(1 => "primary 1", 2 => "primary 2", 3 => "primary 3", 4 => "primary 4", 5 => "primary 5", 6 => "primary 6", 7 => "preparatory 1", 8 => "preparatory 2", 9 => "preparatory 3", 10 => "Secondary 1", 11 => "Secondary 2", 12 => "Secondary 3");
                    $key = array_search($user_info_data->data, $yearMap);
                    if (empty($key)) {
                        $key = '';
                    }
                    $userData = array(
                        "id" => (int)$user->id, "username" => $user->username,
                        "firstName" => $user->firstname, "lastName" => $user->lastname,
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
    function register($firstname, $lastname, $username, $email, $password, $phone = null, $phone2 = null, $role, $year = null, $city = null, $school = null, $center = null)
    {
        global $DB;
        $userInfo = new stdClass();
        $yearInfo = new stdClass();
        $roleAssignment = new stdClass();
        $record = new stdClass();
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
                if ($role == 9) {
                    $userInfo->id = $DB->insert_record('user', $userInfo);
                } elseif ($year != null && $role != 9) {
                    if ($city != null) {
                        $userInfo->city = $city;
                    }
                    $userInfo->country = "EG";
                    $userInfo->lang = "ar";
                    if ($school != null && $center != null) {
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
                    } else {
                        return json_encode(['status' => "fail", "data" => null, "error" => 'you have to write your school and your center']);
                    }
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
                $token = file_get_contents("https://academy.nitg-eg.com/login/token.php?username=$username&password=$password&service=moodle_mobile_app");
                $token = json_decode($token);
                if ($role == 5) {
                    $this->role = "student";
                } elseif ($role == 9) {
                    $this->role = "parent";
                }

                $userData = array(
                    "id" => $userInfo->id, "username" => $userInfo->username, "firstName" => $userInfo->firstname,
                    "lastName" => $userInfo->lastname, "token" => $token->token, "email" => $userInfo->email, "role" => $this->role, "year" => $year,
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
            $courses = $DB->get_recordset_sql($sql);
            foreach ($courses as $key => $record) {
                // Do whatever you want with this record
                $context = context_course::instance($record->courseid, MUST_EXIST);
                $enrol = is_enrolled($context, $userid, '', true);
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
                $overviewfiles= $overviewfiles[0]['fileurl'];
                if ($enrol && $isStudent) {
                    // $courses[$key]->enrolled = true;

                    $courses_data = array(
                        "courseId" => $record->courseid, "courseName" => $record->coursename,
                        "courseDesc" => format_string($record->course_desc),
                        "cat_id" => $record->cat_id,
                        "catname" => format_string($record->catname), "rate" => (int)$rate, "course_image" => $overviewfiles,
                        'price' => $price->value, 'views' => $view->visit, "catDesc" => format_string($record->catDesc)
                    );
                    return json_encode(['status' => "success", "data" => $courses_data, "error" => '']);
                } elseif ($userid == $teacherid) {

                    $courses_data[] = array(
                        "courseId" => $record->courseid, "courseName" => $record->coursename,
                        "courseDesc" => format_string($record->course_desc),
                        "cat_id" => $record->cat_id,
                        "catname" => format_string($record->catname), "rate" => (int)$rate, "course_image" => $overviewfiles,
                        'price' => $price->value, 'views' => $view->visit, "catDesc" => format_string($record->catDesc)
                    );
                }
                
                
                else {
                    return json_encode(['status' => "fail", "data" => null, "error" => "this user is not enrolled in any course with this teacher"]);
                }
            }

            return json_encode(['status' => "success", "data" => $courses_data, "error" => '']);

        } catch (Exception $e) {
            return json_encode(['status' => "fail", "data" => null, "error" => $e->getMessage()]);
        }
    }
}
