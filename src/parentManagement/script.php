<?php
require_once('../config.php');
if (isset($_POST['userid'])) {
    $parentId=0;
    $comeFrom=0;
    try {
        $id = $_POST['userid'];
        $parentFirstName = $_POST['firstname'];
        $parentLastName = $_POST['lastname'];
        $parentEmail = $_POST['email'];
        $parentPassword = $_POST['password'];
        $parentPhone = $_POST['phone'];
        // echo json_encode(['state'=>"1","error"=>'hi']);
        // echo $phone;
        if (!filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['state' => "1", "error" => "enter a valid Mail".$parentEmail]);
        }
        
        elseif (strlen($parentPassword) <=5) {
            echo json_encode(['state' => "1", "error" => "password should be more than or equal 6 digits".$parentPassword]);
        }
        
        
        elseif (strlen($parentPhone) != 11) {
            echo json_encode(['state' => "1", "error" => "Phone should be more than or equal 11 digits".$parentPhone]);
        } elseif (!is_numeric($parentPhone)) {
            echo json_encode(['state' => "1", "error" => "Phone should be numeric".$parentPhone]);
        } elseif (empty($parentFirstName)) {
            echo json_encode(['state' => "1", "error" => "First name Shouldn't be empty".$parentFirstName]);
        } elseif (empty($parentLastName)) {
            echo json_encode(['state' => "1", "error" => "last name Shouldn't be empty".$parentLastName]);
        } else {
            $checkParentEmail = $DB->get_record('user', array('email' => $parentEmail));
            if (empty($checkParentEmail)) {
                    // $parentId ="empty";
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
                $comeFrom=1;
                $child = $DB->get_record('user', array('id' => $id));
                $childPhone = new stdClass();
                $childPhone->id = $child->id;
                $childPhone->phone2 = $parentPhone;
                $DB->update_record('user', $childPhone);
            } else {
                $parentRole = $DB->get_field('role', 'id', array('shortname' => 'parent'));
                $isParent = $DB->record_exists('role_assignments', ['userid' => $checkParentEmail->id, 'roleid' => $parentRole]);
                if($isParent){
                    $parentId = $checkParentEmail->id;
                    $comeFrom=2;
                }
            }
            // echo json_encode(['state' => "1", "error" =>"parentid" .$parentId]);
            if($parentId!=0){
                $ins = new stdClass();
                $ins->parentid = $parentId;
                $ins->childid = $id;
                $res = $DB->insert_record('parent_child', $ins);
                $getContextid = $DB->get_record("context", array("instanceid" => $id));
                $createParent = new stdClass();
                $createParent->roleid =  9;
                $createParent->contextid = $getContextid->id;
                $createParent->userid = $parentId; //$selectParentid;
                $createParent->modifierid = $id; //$selectStudent->id;
                $create_result = $DB->insert_record('role_assignments', $createParent);
                echo json_encode(['state' => "0", "error" => "Parent is added successfully for the student",'comefrom'=>$comeFrom,'phone'=>$checkParentEmail->phone1]);
            }
            else{
                echo json_encode(['state' => "1", "error" => "Username Exists"]);

            }
        }
    } catch (Exception $e) {
        echo json_encode(['state' => "1", "error" => $e->getMessage()]);
    }
}
if (isset($_POST['useridEdit'])) {
    $phoneData=0;
    try {
        $id = $_POST['useridEdit'];
        $parentFirstName = $_POST['firstname'];
        $parentLastName = $_POST['lastname'];
        $parentEmail = $_POST['email'];
        $parentPassword = $_POST['password'];
        $parentPhone = $_POST['phone'];
        $ins = new stdClass();
        $parent = $DB->get_record('parent_child', array('childid' => $id));
        $userParent = $DB->get_record('user', array('id' => $parent->parentid));
        $ins->id = $userParent->id;

        $output = 'Data That will not be edited : ';
        if (!empty($parentFirstName)) {
            $ins->firstname = $parentFirstName;
        } else {
            $output .= "First name (Empty) . ";
        }
        if (!empty($parentLastName)) {
            $ins->lastname = $parentLastName;
        } else {
            $output .= "Last name (Empty) . ";
        }
        if (!empty($parentPassword)&&strlen($parentPassword) >=5) {
            $ins->password = hash_internal_user_password($parentPassword);
        } else {
            $output .= "Password (Empty) or the length of the password is less than 6 digits . ";
        }
        if (!empty($parentPhone)) {
            if (strlen($parentPhone) == 11 && is_numeric($parentPhone)) {
                $ins->phone1 = $parentPhone;
                $child=$DB->get_record('user',array('id'=>$id));
                $childPhone=new stdClass();
                $childPhone->id=$child->id;
                $childPhone->phone2=$parentPhone;
                $DB->update_record('user',$childPhone);
                $phoneData=$parentPhone;
            } else {
                $output .= "Phone  (the Length of th phone is not equal 11 or it's not numeric) .   ";
            }
        } else {
            $output .= "Phone (Empty) . ";
        }
        if (!empty($email)) {
            if($email!=$userParent->email){
                $checkMail=$DB->get_record('user',array('email'=>$email));
                if(!empty($checkMail)){
                    $output .= "Email (Email is already exists) . ";
                }
                else{
                    $ins->email=$email;
                }
            }

        }
        $DB->update_record('user',$ins);
        echo json_encode(['state' => "0", "error" => "Parent is updated successfully ".$output,'parentPhone'=>$phoneData]);

    } catch (Exception $e) {
        echo json_encode(['state' => "1", "error" => $e->getMessage()]);
    }
}
if(isset($_POST['parentEdit'])){
    $id=$_POST['parentEdit'];
    $parent = $DB->get_record('parent_child', array('childid' => $id));
    $userParent = $DB->get_record('user', array('id' => $parent->parentid));
    if(!empty($userParent)){
        echo json_encode(['state' => "0", "fname" =>$userParent->firstname,'lname'=>$userParent->lastname,'phone'=>$userParent->phone1,'email'=>$userParent->email]);

    }
    else{
        echo json_encode(['state' => "1", "error" =>"no parent"]);

    }
}
if(isset($_POST['warId'])){
    $id=$_POST['warId'];
    $parent = $DB->get_record('parent_child', array('childid' => $id));
    $DB->delete_records('parent_child',array('id'=>$parent->id));
    echo $id;
    // $userParent = $DB->get_record('user', array('id' => $parent->parentid));
}
if(isset($_POST['dangId'])){
    $courseid=$_POST['course'];
    $userid=$_POST['dangId'];
    $instances = $DB->get_records('enrol', array('courseid' => $courseid));
    foreach ($instances as $instance) {
        $plugin = enrol_get_plugin($instance->enrol);
        $plugin->unenrol_user($instance, $userid);
    }
    echo $userid;
}