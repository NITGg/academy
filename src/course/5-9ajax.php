<?php
require_once("../config.php");
require_once('../createuser/PHPExcel/Classes/PHPExcel.php');
function getSheets($fileName)
{
    try {
        $fileType = PHPExcel_IOFactory::identify($fileName);
        $objReader = PHPExcel_IOFactory::createReader($fileType);
        $objPHPExcel = $objReader->load($fileName);
        $sheets = [];
        foreach ($objPHPExcel->getAllSheets() as $sheet) {
            $sheets[$sheet->getTitle()] = $sheet->toArray();
        }
        return $sheets;
    } catch (Exception $e) {
        die($e->getMessage());
    }
}
function get_all_courses($year, $teacher)
{
    global $DB;
    $coursesData = array();

    $data_courses = $DB->get_records_sql("SELECT  c.id AS courseId,c.visible as visible, c.fullname as courseName,c.category as catId,cat.name as catName ,cinfo.value as year
    FROM   mdl_course c
    LEFT OUTER JOIN mdl_customfield_data cinfo ON c.id=cinfo.instanceid

     LEFT OUTER JOIN mdl_course_categories  cat   ON c.category=cat.id 
      LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
    LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
     LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id 
     WHERE cx.contextlevel = '50' AND cinfo.fieldid=1 AND u.id= '$teacher' ");
    $yearMap = array(1 => "primary 1", 2 => "primary 2", 3 => "primary 3", 4 => "primary 4", 5 => "primary 5", 6 => "primary 6", 7 => "preparatory 1", 8 => "preparatory 2", 9 => "preparatory 3", 10 => "Secondary 1", 11 => "Secondary 2", 12 => "Secondary 3");
    $key = array_search($year, $yearMap);
    foreach ($data_courses as $course) {

        if ($key == $course->year) {
            $coursesData[] = $course->courseid;
        }
    }
    return $coursesData;
}
if (isset($_FILES['excel']['tmp_name'])) {
    $success = array();
    $failed = array();
    try {
        $extension = pathinfo($_FILES['excel']['name'], PATHINFO_EXTENSION);
        if ($extension != "csv") {
            echo json_encode(['state' => 0, 'failure' => $extension]);
        } else {
            for ($i = 1; $i < count(getSheets($_FILES['excel']['tmp_name'])['Worksheet']); $i++) {
                $userInfo = new stdClass();
                $parentInfo = new stdClass();
                $yearInfo = new stdClass();
                $roleAssignment = new stdClass();
                $record = new stdClass();
                $optional_data = new stdClass();
                $firstname = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][0];
                $lastname = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][1];
                $email = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][3];
                $password = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][2];
                $phone1 = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][4];
                $phone2 = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][5];
                $role = 5;
                $yearMap = array("primary 1" => 1, "primary 2" => 2, "primary 3" => 3, "primary 4" => 4, "primary 5" => 5, "primary 6" => 6, "preparatory 1" => 7, "preparatory 2" => 8, "preparatory 3" => 9, "Secondary 1" => 10, "Secondary 2" => 11, "Secondary 3" => 12);
                $courseYear = $DB->get_record('customfield_data', array('instanceid' => $_POST['course']));
                $year = array_search($courseYear->value, $yearMap);
                $city = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][6];
                $school = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][7];
                $center = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][8];
                $parentFirstName = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][9];
                $parentLastName = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][10];
                $parentPassword = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][11];
                $parentEmail = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][12];
                $parentPhone = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][13];
                $parentRole = 9;
                $check_parent = 1;
                $checkEmail = $DB->get_record('user', array('email' => $email));
                if (empty($email)) {
                    $failed[] = array('name' => $email, "reasonS" => "Empty Mail","reasonP"=>"-");
                } elseif (!empty($checkEmail)) {
                    $failed[] = array('name' => $email, "reasonS" => "Username Exists","reasonP"=>"-");
                } elseif (empty($firstname)) {
                    $failed[] = array('name' => $email, "reasonS" => "Empty First Name","reasonP"=>"-");
                } elseif (empty($lastname)) {
                    $failed[] = array('name' => $email, "reasonS" => "Empty Last Name","reasonP"=>"-");
                } elseif (empty($password)||strlen($password) < 6) {
                    $failed[] = array('name' => $email, "reasonS" => "Empty Password OR the password should be greater than 6 digits","reasonP"=>"-");
                } elseif ($role == 5 && empty($year)) {
                    $failed[] = array('name' => $email, "reasonS" => "no year specified","reasonP"=>"-");
                } else {
                    $notice = "";
                    if (!empty($phone1) && strlen($phone1) < 11) {
                        $notice .= " -Student Phone should be 11 digits ";
                    }
                    if (empty($phone2) || (!empty($phone2) && $phone2 != $parentPhone)) {
                        $check_parent = 0;
                        $notice .= " -no parents will be added for this user ";
                    }
                    $userInfo->firstname = $firstname;
                    $userInfo->lastname = $lastname;
                    $userInfo->username = $email;
                    $userInfo->email = $email;
                    $hashPass = hash_internal_user_password($password);
                    $userInfo->password = $hashPass;
                    if ($phone1 != null) {
                        $userInfo->phone1 = $phone1;
                    }
                    if ($phone2 != null) {
                        $userInfo->phone2 = $phone2;
                    }
                    $userInfo->confirmed = 1;
                    $userInfo->mnethostid = 1;
                    if ($city != null) {
                        $userInfo->city = $city;
                    }
                    $userInfo->id = $DB->insert_record('user', $userInfo);
                    $yearMap = array(1 => "primary 1", 2 => "primary 2", 3 => "primary 3", 4 => "primary 4", 5 => "primary 5", 6 => "primary 6", 7 => "preparatory 1", 8 => "preparatory 2", 9 => "preparatory 3", 10 => "Secondary 1", 11 => "Secondary 2", 12 => "Secondary 3");
                    $key = array_search($year, $yearMap);
                    $yearInfo->userid = $userInfo->id;
                    $yearInfo->fieldid = 1;
                    $yearInfo->data = $key;
                    $yearInfo->dataformat = 0;
                    $yearInfo->id = $DB->insert_record('user_info_data', $yearInfo);
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
                    $roleAssignment->roleid =  $role;
                    $roleAssignment->contextid = $record->id;
                    $roleAssignment->userid = $userInfo->id;
                    $roleAssignment->timemodified = time();
                    $roleAssignment->modifierid = $userInfo->id;
                    $roleAssignment->id = $DB->insert_record('role_assignments', $roleAssignment);
                    $optional_data->userid = $userInfo->id;
                    $optional_data->school = $school;
                    $optional_data->empty = $center;
                    $optional_data->id = $DB->insert_record('optional_data_aibrahim', $optional_data);
                    if ($_POST['teacherID'] != 72) {
                        $course = $DB->get_record('course', array('id' => $_POST['course']), '*', MUST_EXIST);
                        $context = context_course::instance($course->id);
                        $enrolmethod = 'manual';
                        if (!is_enrolled($context, $userInfo->id)) {
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
                            $enrol->enrol_user($instance, $userInfo->id, 5);
                        }
                        $failed[] = array('name' => $email, "reasonS" => "success " . $notice,"reasonP"=>"-");
                    } else {
                        $checks = array();
                        $user = $userInfo->id;
                        $courses = get_all_courses($year, 72);
                        $count = count($courses);
                        for ($j = 0; $j < $count; $j++) {
                            $course = $DB->get_record('course', array('id' => $courses[$j]), '*', MUST_EXIST);
                            $context = context_course::instance($course->id);
                            $enrolmethod = 'manual';
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
                                $enrol->enrol_user($instance, $user, 5);
                            }
                        }
                        $failed[] = array('name' => $email, "reasonS" => "success " . $notice,"reasonP"=>"-");
                    }
                    if ($check_parent == 1) {
                        $parentId = 0;
                        if (empty($parentEmail)) {
                            $failed[] = array('name' => $parentEmail,"reasonS" => "-" , "reasonP" => "Empty parent Mail For User " .$email);
                        } else {
                            $checkParentEmail = $DB->get_record('user', array('email' => $parentEmail));
                            if (empty($checkParentEmail)) {
                                if (empty($parentFirstName)) {
                                    $failed[] = array('name' => $parentEmail,"reasonS"=>"-", "reasonP" => "Empty parent First Name" );
                                } elseif (empty($parentLastName)) {
                                    $failed[] = array('name' => $parentEmail,"reasonS"=>"-", "reasonP" => "Empty parent Last Name" );
                                } elseif (empty($parentPassword)||strlen($parentPassword) < 6) {
                                    $failed[] = array('name' => $parentEmail,"reasonS"=>"-", "reasonP" => "Empty parent Password Or Password should be greater than or equal 6"  );
                                } elseif (empty($parentPhone) || (!empty($parentPhone) && strlen($parentPhone) < 11)) {
                                    $failed[] = array('name' => $parentEmail,"reasonS"=>"-", "reasonP" => "Empty parent Last Name" );
                                } else {
                                    $parentInfo->firstname = $parentFirstName;
                                    $parentInfo->lastname = $parentLastName;
                                    $parentInfo->username = $parentEmail;
                                    $parentInfo->email = $parentEmail;
                                    $hashPass = hash_internal_user_password($parentPassword);
                                    $parentInfo->password = $hashPass;
                                    $parentInfo->phone1 =  $parentPhone;
                                    $parentInfo->phone2 =  $parentPhone;
                                    $parentInfo->confirmed = 1;
                                    $parentInfo->mnethostid = 1;
                                    $parentInfo->id = $DB->insert_record('user', $parentInfo);
                                    $parentId = $parentInfo->id;
                                    $failed[] = array('name' => $parentEmail,"reasonS"=>"-", "reasonP" => "success");
                                }
                            } else {
                                $parentId = $checkParentEmail->id;
                            }
                            $ins = new stdClass();
                            $ins->parentid = $parentId;
                            $ins->childid = $userInfo->id;
                            $res = $DB->insert_record('parent_child', $ins);
                            $getContextid = $DB->get_record("context", array("instanceid" => $userInfo->id));
                            $createParent = new stdClass();
                            $createParent->roleid =  9;
                            $createParent->contextid = $getContextid->id;
                            $createParent->userid = $parentId; //$selectParentid;
                            $createParent->modifierid = $userInfo->id; //$selectStudent->id;
                            $create_result = $DB->insert_record('role_assignments', $createParent);
                        }
                    }
                }
            }
            echo json_encode(['state' => 1, 'failure' => $failed, 'success' => $success]);
        }
    } catch (Exception $e) {
        echo  "failure " . $e;
    }
}
