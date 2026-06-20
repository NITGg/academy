<?php
require_once("../config.php");

if (isset($_POST['teacher'])) {
    $teacherID = $_POST['teacher'];
    $userEnroledCourses = enrol_get_users_courses($teacherID);
    $user = $DB->get_record('user', array('id' => $teacherID));
    echo json_encode(['data' => array_values($userEnroledCourses), 'teachername' => $user->firstname . ' ' . $user->lastname]);
} else if (isset($_POST['course'])) {

    try {

        $groups = $DB->get_records('groups', array('courseid' => $_POST['course'], 'descriptionformat' => 1));
        $course = $DB->get_record('course', array('id' => $_POST['course']));
        echo json_encode(['data' => $groups, 'coursename' => $course->fullname]);
    } catch (Exception $e) {
        echo $e->getMessage();
    }
} else if (isset($_POST['dataCourse'])) {
    $course = $_POST['dataCourse'];
    $group = $_POST['dataGroup'];
    $teacher = $_POST['dataTeacher'];
    $temp = $_POST['temp'];
    $state = $_POST['state'];
    $stateData = '';
    $out = array();
    $time = 0;
    $used_codes = 0;
  
    if ($temp == 0) {
        $getPatches = $DB->get_records('groups_attendence_patch', array('courseid' => $course));
        foreach ($getPatches as $patch) {
            if ($state == '1') {
                $getCodes = $DB->get_records('groups_attendence_codes', array('patchid' => $patch->id,'used'=>1));
            } else if ($state == '2') {
                $getCodes = $DB->get_records('groups_attendence_codes', array('patchid' => $patch->id,'used'=>0));
            }
            else{
                $getCodes = $DB->get_records('groups_attendence_codes', array('patchid' => $patch->id));
            }
            $group_name = $DB->get_record('groups', array('id' => $patch->groupid));
            foreach ($getCodes as $code) {
                if ($code->used == 1) {
                    $time = $code->timemodified;
                    $used_codes++;
                } else {
                    $time = 0;
                }
                $out[] = array('id' => $code->id, 'groupname' => $group_name->name, 'code' => $code->code, 'used' => $code->used, 'time' => $time);
            }
        }
        echo json_encode(['data' => $out, 'codes' => count($out), 'used_codes' => $used_codes]);
    } else {
        $getPatches = $DB->get_records('groups_attendence_patch', array('courseid' => $course, 'groupid' => $group));
        foreach ($getPatches as $patch) {
            if ($state == '1') {
                $getCodes = $DB->get_records('groups_attendence_codes', array('patchid' => $patch->id,'used'=>1));
            } else if ($state == '2') {
                $getCodes = $DB->get_records('groups_attendence_codes', array('patchid' => $patch->id,'used'=>0));
            }
            else{
                $getCodes = $DB->get_records('groups_attendence_codes', array('patchid' => $patch->id));
            }            $group_name = $DB->get_record('groups', array('id' => $patch->groupid));
            foreach ($getCodes as $code) {
                if ($code->used == 1) {
                    $time = $code->timemodified;
                    $used_codes++;
                } else {
                    $time = 0;
                }
                $out[] = array('id' => $code->id, 'groupname' => $group_name->name, 'code' => $code->code, 'used' => $code->used, 'time' => $time);
            }
        }
        echo json_encode(['data' => $out, 'codes' => count($out), 'used_codes' => $used_codes]);

        // echo json_encode( $temp );

    }
} else if (isset($_POST['delete'])) {
    $id = $_POST['delete'];
    $getCode = $DB->get_record('groups_attendence_codes', array('id' => $_POST['delete']));
    $used = 0;
    if ($getCode->used == 1) {
        $used = 1;
    }

    $DB->delete_records('groups_attendence_codes', array('id' => $id));
    echo $used;
} else if (isset($_POST['update'])) {
    $used = 0;
    $temp = $_POST['temp'];
    if ($temp == 1) {
        $used = 1;
    } else {
        $used = 0;
    }
    $getCode = $DB->get_record('groups_attendence_codes', array('id' => $_POST['update']));
    $upd = new stdClass();
    $upd->id = $getCode->id;
    $upd->used = $used;
    $upd->empty2=0;
    $DB->update_record('groups_attendence_codes', $upd);
    $getCode = $DB->get_record('groups_attendence_codes', array('id' => $_POST['update']));
    $time = $getCode->timemodified;
    echo $time;
}
