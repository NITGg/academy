<?php 
require('../config.php');
require_once('../academy/PHPMailer/src/Exception.php');
require_once('../academy/PHPMailer/src/PHPMailer.php');
require_once('../academy/PHPMailer/src/SMTP.php');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once($CFG->dirroot . '/course/externallib.php');
if(isset($_POST['checkMail'])){
    $email=$_POST['checkMail'];
    $user = $DB->get_record('user', array('email' => $email));
    if (!empty($user)) {
        echo "false";
    } else{
        echo "true"; 
    }
}
if(isset($_POST['forget'])){
    $email=$_POST['forget'];
    // $user = $DB->get_record('user', array('email' => $email));
    $result=forget_password($email);
    echo $result;
}
function forget_password($username)
{
    global $DB, $OUTPUT, $CFG;
    $user = "";
    $userData = $DB->get_records_sql("SELECT * FROM mdl_user WHERE username='$username' or email='$username'");
    foreach ($userData as $userd) {
        $user = $userd;
    }

    // $user= $userData[0];

    // $code=generate_random_code($username);
    if (!empty($user)) {
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
        $message = 'You should go to these link to change password <a href= ' . $CFG->wwwroot . '/academy/academyApi/confirm.php?id='.$CFG->userId.'>Confirm Password</a>';
        $phpmailer->Body = $message;
        $phpmailer->IsHTML(true);
        if (!$phpmailer->send()) {
            return $phpmailer->ErrorInfo;
            // echo "Mailer Error: " . $phpmailer->ErrorInfo;
        } else {
            return "Sent";
        }
    } else {
       return "username is not exist";
    }
}
function get_all_courses($user, $teacher, $lang = '')
{
    global $DB, $CFG;
    $coursesData = array();
    $studentRole = $DB->get_field('role', 'id', array('shortname' => 'student'));
    $isStudent = $DB->record_exists('role_assignments', ['userid' => $user, 'roleid' => $studentRole]);
    $teacherRole = $DB->get_field('role', 'id', array('shortname' => 'student'));
    $teacherRole = $DB->record_exists('role_assignments', ['userid' => $user, 'roleid' => $teacherRole]);
    $assisstantRole = $DB->get_field('role', 'id', array('shortname' => 'teacher'));
    $assisstantRole = $DB->record_exists('role_assignments', ['userid' => $user, 'roleid' => $assisstantRole]);
    if ($lang == "ar") {
        $lang = "and c.lang='ar'";
    } elseif ($lang == "en") {
        $lang = "and c.lang='en'";
    }
    $data_courses = $DB->get_records_sql("SELECT  c.id AS courseid,c.visible as visible, c.fullname as courseName,c.category as catId,cat.name as catName ,cinfo.value as year
   FROM   mdl_course c
   LEFT OUTER JOIN mdl_customfield_data cinfo ON c.id=cinfo.instanceid
    LEFT OUTER JOIN mdl_course_categories  cat   ON c.category=cat.id 
     LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
   LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
    LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id 
    WHERE cx.contextlevel = '50' AND cinfo.fieldid=1 AND u.id= '$teacher' $lang;");
    if ($isStudent || ($teacherRole && $teacher != $user)) {
        $student = $DB->get_record('user_info_data', array('userid' => $user, 'fieldid' => 1));
        $yearMap = array(1 => "primary 1", 2 => "primary 2", 3 => "primary 3", 4 => "primary 4", 5 => "primary 5", 6 => "primary 6", 7 => "preparatory 1", 8 => "preparatory 2", 9 => "preparatory 3", 10 => "Secondary 1", 11 => "Secondary 2", 12 => "Secondary 3");
        $key = array_search($student->data, $yearMap);
        foreach ($data_courses as $course) {
            if ($key == $course->year) {
                $coursesData = $course->courseid;
            }
        }
    }
    return $coursesData;
}
function enrol_student($id, $userid, $roleid, $enrolmethod = 'manual')
{
    global $DB;
    // Nothing to enrol into (e.g. the student's year matches no course for this teacher).
    // Guard against get_record(..., MUST_EXIST) throwing "record not found in table course".
    if (empty($id) || !is_numeric($id)) {
        return 'false';
    }
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
function register($firstname, $lastname, $username, $email, $password, $phone = null, $phone2 = null, $role, $year = null, $city = null, $school = null, $center = null)
{
    global $DB, $CFG;
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
                $role = "student";
            } elseif ($role == 9) {
                $role = "parent";
            }
            if (empty($school)) {
                $school = "";
            }
            if (empty($center)) {
                $center = "";
            }
            $url = "";
            // $url = "https://academy.nitg-eg.com/theme/edumy/images/teachers/" . $userInfo->url;
            $user_context = $DB->get_record('context', array('instanceid' => $userInfo->id, 'contextlevel' => 30));
            $fs = get_file_storage();
            $files = $fs->get_area_files($user_context->id, 'user', 'icon', 0, 'sortorder DESC, id ASC', false);
            if (count($files) < 1) {
                $image = '' . $CFG->wwwroot . '/pluginfile.php/' . $user_context->id . '/user/icon/0/f1.jpg?rev=0';
            } else {
                $file = reset($files);
                unset($files);
                $path = '/' . $user_context->id . '/user/icon/0' . $file->get_filepath() . $file->get_filename();
                $image = $CFG->wwwroot . '/pluginfile.php' . $path . "?rev=" . $userInfo->picture;
            }
            $url = $image;
            $courseData = get_all_courses($userInfo->id, 14); //change the teacher id on each api
            $enrol = enrol_student($courseData, $userInfo->id, 5);
            $userData = array(
                "id" => $userInfo->id, "username" => $userInfo->username, "firstName" => $userInfo->firstname,
                "lastName" => $userInfo->lastname, "image" => $url, "token" => $token->token, "email" => $userInfo->email, "role" => 5, "year" => $year,
                "phone" => $userInfo->phone1, "phone2" => $userInfo->phone2,
                "city" => $city, "school" => $school, "center" => $center
            );
            return "success";
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}
?>