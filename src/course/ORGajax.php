<?php
require_once("../config.php");
require_once('../createuser/PHPExcel/Classes/PHPExcel.php') ;
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
if(isset($_FILES['excel']['tmp_name'])){
    $success = array();
    $failed = array();
    try{
        $extension = pathinfo($_FILES['excel']['name'], PATHINFO_EXTENSION);
        if($extension!="csv"){
            echo json_encode(['state'=>0,'failure'=>$extension]);

        }
        else{
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
                $role = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][6];
                $year = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][7];
                $city = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][8];
                $school = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][9];
                $center = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][10];
                $parentFirstName = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][11];
                $parentLastName = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][12];
                $parentPassword = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][13];
                $parentEmail = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][14];
                $parentPhone = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][15];
                $parentRole = getSheets($_FILES['excel']['tmp_name'])['Worksheet'][$i][16];
                $checkEmail = $DB->get_record('user', array('email' => $email));
                if (!empty($checkEmail)) {
                    $failed[] = array('name' => $email, "reason" => "Username Exists");
                } elseif (!empty($phone1) && strlen($phone1) < 11) {
                    $failed[] = array('name' => $email, "reason" => "Phone should be 11 digits");
                } elseif ($role == 5 && empty($year)) {
                    $failed[] = array('name' => $email, "reason" => "no year specified");
                } elseif (empty($school)) {
                    $failed[] = array('name' => $email, "reason" => "no school specified");
                } elseif (empty($center)) {
                    $failed[] = array('name' => $email, "reason" => "no center specified");
                } elseif ($phone2 != $parentPhone) {
                    $failed[] = array('name' => $email, "reason" => "student's parent phone is not the same with student phone");
                } else {
                    $userInfo->firstname = $firstname;
                    $userInfo->lastname = $lastname;
                    $userInfo->username = $email;
                    $userInfo->email = $email;
                    $hashPass = hash_internal_user_password($password);
                    $userInfo->password = $hashPass;
                    $userInfo->phone1 =  $phone1;
                    $userInfo->phone2 =  $phone2;
                    $userInfo->confirmed = 1;
                    $userInfo->mnethostid = 1;
                    if ($city != null) {
                        $userInfo->city = $city;
                    }
        
                    $userInfo->id = $DB->insert_record('user', $userInfo);
                    $yearMap = array("primary 1" => 1, "primary 2" => 2, "primary 3" => 3, "primary 4" => 4, "primary 5" => 5, "primary 6" => 6, "preparatory 1" => 7, "preparatory 2" => 8, "preparatory 3" => 9, "Secondary 1" => 10, "Secondary 2" => 11, "Secondary 3" => 12);
                    $key = array_search(intval($year), $yearMap);
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
                    $success[] = array('name' => $email, "reason" => "success");
                    $checkParentEmail = $DB->get_record('user', array('email' => $parentEmail));
                    $parentId = 0;
                    if (empty($checkParentEmail)) {
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
                    $success[] = array('name' => $parentEmail, "reason" => "success");
                }
            }
            echo json_encode(['state'=>1,'failure'=>$failed,'success'=>$success]);
        }


    }
    catch (Exception $e){
        echo  "failure ".$e;

    }


}

