<?php
require_once("config.php");
// require_once('twoteachers/academyApi/json.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/enrol/externallib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

if (isset($_POST['group'])) {

    try {
        $cm = $DB->get_record('course_modules', array('id' => $_POST['clickedAct']));
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

        $groupCode = $DB->get_record('groups_attendence_codes', array('code' => $_POST['group'], 'used' => 0));
        if (!empty($groupCode)) {

            // $getGroupId = $DB->get_record('groups_attendence_patch', array('id' => $groupCode->patchid,'courseid'=>$_POST['courseGroup'],'groupid'=>$id));
            $getGroupId = $DB->get_record('groups_attendence_patch', array('id' => $groupCode->patchid, 'courseid' => $_POST['courseGroup']));

            if (!empty($getGroupId)) {
                $getCodeGroupId = $DB->get_record('groups', array('courseid' => $_POST['courseGroup']));
                if ($getGroupId->empty1 == 1) {
                    $checkAdding = groups_add_member($id, $_POST['userid']);
                    $addToCodeGroup = groups_add_member($getCodeGroupId->id, $_POST['userid']);
                    if ($checkAdding) {
                        $upd = new stdClass();
                        $upd->id = $groupCode->id;
                        $upd->used = 1;
                        $upd->empty1 = $id;
                        $upd->empty2 = $_POST['userid'];
                        $DB->update_record('groups_attendence_codes', $upd);
                        echo "success";
                    } else {
                        echo 'error adding to a group';
                    }
                } else {
                    if ($id == $getGroupId->groupid) {
                        $checkAdding = groups_add_member($getGroupId->groupid, $_POST['userid']);
                        $addToCodeGroup = groups_add_member($getCodeGroupId->id, $_POST['userid']);
                        if ($checkAdding) {
                            $upd = new stdClass();
                            $upd->id = $groupCode->id;
                            $upd->used = 1;
                            $upd->empty2 = $_POST['userid'];
                            $DB->update_record('groups_attendence_codes', $upd);
                            echo "success";
                        } else {
                            echo 'error adding to a group';
                        }
                    } else {

                        echo "this code doesn't belong to this course or this group";
                    }
                }
            } else {
                echo "this code doesn't belong to this course or this group";
            }
        } else {
            echo "Group Code is not valid";
        }
    } catch (Exception $e) {
        echo $e->getMessage();
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
if (isset($_POST['action'])) {
    $course = $_POST['id'];
    $user = $_POST['userid'];
    $result = enrol_student($course, $user, 5);
    $sign = $DB->get_record('signup_options', array('userid' => $user));
    $signup_options = new stdClass();
    $signup_options->id = $sign->id;
    $signup_options->empty1 = 1;
    $signup_options->id = $DB->update_record('signup_options', $signup_options);
    echo $result;
} elseif (isset($_POST['sectionOnePragValue'])) {
    $home_teacher_data = $DB->get_record('home_teacher_data', array('teacherid' => $_POST['teacherId']));
    if (empty($home_teacher_data)) {
        $ins = new stdClass();
        $ins->teacherid = $_POST['teacherId'];
        $ins->section1body = $_POST['sectionOnePragValue'];
        $DB->insert_record('home_teacher_data', $ins);
    } else {
        $upd = new stdClass();
        $upd->id = $home_teacher_data->id;
        $upd->section1body = $_POST['sectionOnePragValue'];
        $DB->update_record('home_teacher_data', $upd);
    }
} elseif (isset($_POST['section1leftValue'])) {
    $home_teacher_data = $DB->get_record('home_teacher_data', array('teacherid' => $_POST['teacherId']));
    if (empty($home_teacher_data)) {
        $ins = new stdClass();
        $ins->teacherid = $_POST['teacherId'];
        $ins->section1left = $_POST['section1leftValue'];
        $DB->insert_record('home_teacher_data', $ins);
    } else {
        $upd = new stdClass();
        $upd->id = $home_teacher_data->id;
        $upd->section1left = $_POST['section1leftValue'];
        $DB->update_record('home_teacher_data', $upd);
    }
    // $upd=new stdClass();
    // $upd->id=$home_teacher_data->id;
    // $upd->section1left=$_POST['section1leftValue'];
    // $DB->update_record('home_teacher_data',$upd);
} elseif (isset($_POST['sectionOneHeadValue'])) {
    $home_teacher_data = $DB->get_record('home_teacher_data', array('teacherid' => $_POST['teacherId']));
    if (empty($home_teacher_data)) {
        $ins = new stdClass();
        $ins->teacherid = $_POST['teacherId'];
        $ins->section1head = $_POST['sectionOneHeadValue'];
        $DB->insert_record('home_teacher_data', $ins);
    } else {
        $upd = new stdClass();
        $upd->id = $home_teacher_data->id;
        $upd->section1head = $_POST['sectionOneHeadValue'];
        $DB->update_record('home_teacher_data', $upd);
    }
    // $upd=new stdClass();
    // $upd->id=$home_teacher_data->id;
    // $upd->section1head=$_POST['sectionOneHeadValue'];
    // $DB->update_record('home_teacher_data',$upd);
} elseif (isset($_POST['editPhone'])) {
    $user = $DB->get_record('user', array('id' => $_POST['teacherId']));
    $upd = new stdClass();
    $upd->id = $user->id;
    $upd->phone1 = $_POST['editPhone'];
    $DB->update_record('user', $upd);
} elseif (isset($_POST['divOne'])) {
    $home_teacher_data = $DB->get_record('home_teacher_data', array('teacherid' => $_POST['teacherId']));
    if (empty($home_teacher_data)) {
        $ins = new stdClass();
        $ins->teacherid = $_POST['teacherId'];
        $ins->section3 = $_POST['divOne'];
        $DB->insert_record('home_teacher_data', $ins);
    } else {
        $upd = new stdClass();
        $upd->id = $home_teacher_data->id;
        $upd->section3 = $_POST['divOne'];
        $DB->update_record('home_teacher_data', $upd);
    }
    // $upd=new stdClass();
    // $upd->id=$home_teacher_data->id;
    // $upd->section3=$_POST['divOne'];
    // $DB->update_record('home_teacher_data',$upd);
} elseif (isset($_POST['divTwo'])) {
    $home_teacher_data = $DB->get_record('home_teacher_data', array('teacherid' => $_POST['teacherId']));
    if (empty($home_teacher_data)) {
        $ins = new stdClass();
        $ins->teacherid = $_POST['teacherId'];
        $ins->empty1 = $_POST['divTwo'];
        $DB->insert_record('home_teacher_data', $ins);
    } else {
        $upd = new stdClass();
        $upd->id = $home_teacher_data->id;
        $upd->empty1 = $_POST['divTwo'];
        $DB->update_record('home_teacher_data', $upd);
    }
    // $upd=new stdClass();
    // $upd->id=$home_teacher_data->id;
    // $upd->empty1=$_POST['divTwo'];
    // $DB->update_record('home_teacher_data',$upd);
} elseif (isset($_POST['divThree'])) {
    $home_teacher_data = $DB->get_record('home_teacher_data', array('teacherid' => $_POST['teacherId']));
    if (empty($home_teacher_data)) {
        $ins = new stdClass();
        $ins->teacherid = $_POST['teacherId'];
        $ins->empty2 = $_POST['divThree'];
        $DB->insert_record('home_teacher_data', $ins);
    } else {
        $upd = new stdClass();
        $upd->id = $home_teacher_data->id;
        $upd->empty2 = $_POST['divThree'];
        $DB->update_record('home_teacher_data', $upd);
    }
    // $upd=new stdClass();
    // $upd->id=$home_teacher_data->id;
    // $upd->empty2=$_POST['divThree'];
    // $DB->update_record('home_teacher_data',$upd);
} elseif (isset($_POST['divFour'])) {
    $home_teacher_data = $DB->get_record('home_teacher_data', array('teacherid' => $_POST['teacherId']));
    if (empty($home_teacher_data)) {
        $ins = new stdClass();
        $ins->teacherid = $_POST['teacherId'];
        $ins->empty3 = $_POST['divFour'];
        $DB->insert_record('home_teacher_data', $ins);
    } else {
        $upd = new stdClass();
        $upd->id = $home_teacher_data->id;
        $upd->empty3 = $_POST['divFour'];
        $DB->update_record('home_teacher_data', $upd);
    }
    // $upd=new stdClass();
    // $upd->id=$home_teacher_data->id;
    // $upd->empty3=$_POST['divFour'];
    // $DB->update_record('home_teacher_data',$upd);
} elseif (isset($_POST['divFive'])) {
    $home_teacher_data = $DB->get_record('home_teacher_data', array('teacherid' => $_POST['teacherId']));
    if (empty($home_teacher_data)) {
        $ins = new stdClass();
        $ins->teacherid = $_POST['teacherId'];
        $ins->empty4 = $_POST['divFive'];
        $DB->insert_record('home_teacher_data', $ins);
    } else {
        $upd = new stdClass();
        $upd->id = $home_teacher_data->id;
        $upd->empty4 = $_POST['divFive'];
        $DB->update_record('home_teacher_data', $upd);
    }
    // $upd=new stdClass();
    // $upd->id=$home_teacher_data->id;
    // $upd->empty4=$_POST['divFive'];
    // $DB->update_record('home_teacher_data',$upd);
} elseif (isset($_POST['facebookLink'])) {
    $home_teacher_data = $DB->get_record('home_teacher_data', array('teacherid' => $_POST['teacherId']));
    $face = $DB->get_record('social_teacher', array('patch' =>   $home_teacher_data->id));
    if (empty($face)) {
        $ins = new stdClass();
        $ins->patch = $home_teacher_data->id;
        $ins->facebook = $_POST['facebookLink'];
        $DB->insert_record('social_teacher', $ins);
    } else {
        $upd = new stdClass();
        $upd->id = $face->id;
        $upd->facebook = $_POST['facebookLink'];
        $DB->update_record('social_teacher', $upd);
    }
    // $face=new stdClass();

} elseif (isset($_POST['youtubeLink'])) {
    $home_teacher_data = $DB->get_record('home_teacher_data', array('teacherid' => $_POST['teacherId']));
    $face = $DB->get_record('social_teacher', array('patch' =>   $home_teacher_data->id));
    if (empty($face)) {
        $ins = new stdClass();
        $ins->patch = $home_teacher_data->id;
        $ins->youtube = $_POST['youtubeLink'];
        $DB->insert_record('social_teacher', $ins);
    } else {
        $upd = new stdClass();
        $upd->id = $face->id;
        $upd->youtube = $_POST['youtubeLink'];
        $DB->update_record('social_teacher', $upd);
    }
    // $face=new stdClass();

} elseif (isset($_POST['linkedinLink'])) {
    $home_teacher_data = $DB->get_record('home_teacher_data', array('teacherid' => $_POST['teacherId']));
    $face = $DB->get_record('social_teacher', array('patch' =>   $home_teacher_data->id));
    if (empty($face)) {
        $ins = new stdClass();
        $ins->patch = $home_teacher_data->id;
        $ins->empty1 = $_POST['linkedinLink'];
        $DB->insert_record('social_teacher', $ins);
    } else {
        $upd = new stdClass();
        $upd->id = $face->id;
        $upd->empty1 = $_POST['linkedinLink'];
        $DB->update_record('social_teacher', $upd);
    }
    // $face=new stdClass();

}
elseif (isset($_POST['telegramLink'])) {
    $home_teacher_data = $DB->get_record('home_teacher_data', array('teacherid' => $_POST['teacherId']));
    $face = $DB->get_record('social_teacher', array('patch' =>   $home_teacher_data->id));
    if (empty($face)) {
        $ins = new stdClass();
        $ins->patch = $home_teacher_data->id;
        $ins->empty2 = $_POST['telegramLink'];
        $DB->insert_record('social_teacher', $ins);
    } else {
        $upd = new stdClass();
        $upd->id = $face->id;
        $upd->empty2 = $_POST['telegramLink'];
        $DB->update_record('social_teacher', $upd);
    }
    // $face=new stdClass();

}
if(isset($_POST['delSocial'])){
    $home_teacher_data = $DB->get_record('home_teacher_data', array('teacherid' => $_POST['teacherId']));
    $social = $DB->get_record('social_teacher', array('patch' =>   $home_teacher_data->id));
    if(!empty($social)){
        if($_POST['delSocial']==1){
            $upd = new stdClass();
            $upd->id = $social->id;
            $upd->facebook ='';
            $DB->update_record('social_teacher', $upd);
        }
        elseif($_POST['delSocial']==2){
            $upd = new stdClass();
            $upd->id = $social->id;
            $upd->youtube ='';
            $DB->update_record('social_teacher', $upd);
        }
        elseif($_POST['delSocial']==3){
            $upd = new stdClass();
            $upd->id = $social->id;
            $upd->empty1 ='';
            $DB->update_record('social_teacher', $upd);
        }
        elseif($_POST['delSocial']==4){
            $upd = new stdClass();
            $upd->id = $social->id;
            $upd->empty2 ='';
            $DB->update_record('social_teacher', $upd);
        }
    }
}