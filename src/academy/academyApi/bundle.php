<?php
require_once('../config.php');
// $PAGE->set_url($CFG->wwwroot.'/json/quizreport.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/course/externallib.php');
require_once($CFG->dirroot . '/user/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/enrol/externallib.php');

define('PARAM_STRING','string');
$function= optional_param('function', "",PARAM_RAW);
$meetingID = optional_param('meetingID',  null, PARAM_TEXT);
$internalMeetingID = optional_param('internalMeetingID',  null, PARAM_TEXT);
$parentMeetingID = optional_param('parentMeetingID',  null, PARAM_TEXT);
$attendeePW = optional_param('attendeePW',  null, PARAM_TEXT);
$moderatorPW = optional_param('moderatorPW',  null, PARAM_TEXT);
$createTime = optional_param('createTime',  null, PARAM_TEXT);
$voiceBridge = optional_param('voiceBridge',  null, PARAM_TEXT);
$dialNumber = optional_param('dialNumber',  null, PARAM_TEXT);
$createDate = optional_param('createDate',  null, PARAM_TEXT);
$hasUserJoined = optional_param('hasUserJoined',  null, PARAM_TEXT);
$duration = optional_param('duration',  null, PARAM_TEXT);
$hasBeenForciblyEnded = optional_param('hasBeenForciblyEnded',  null, PARAM_TEXT);
$messageKey = optional_param('messageKey',  null, PARAM_TEXT);
$message = optional_param('message',  null, PARAM_TEXT);
$courseid = optional_param('courseid',null,PARAM_TEXT);
$isRunning = optional_param('isRunning',null,PARAM_TEXT);

$teacherid = optional_param('teacherid',0,PARAM_TEXT);
$user = optional_param('user',0,PARAM_TEXT);
$roomid = optional_param('roomid',0,PARAM_TEXT);
$maxUser = optional_param('maxUser',0,PARAM_TEXT);
$name= optional_param('name', "",PARAM_RAW);
$discount= optional_param('discount', "",PARAM_RAW);
$courses= optional_param('courses', "",PARAM_RAW);
$bundle = optional_param('bundle',  null, PARAM_TEXT);
$list =optional_param_array('array', null, PARAM_RAW);

if($function == 'add_bundle'){
    echo add_bundle($name,$discount,$courses);
}
if($function == 'update_bundle'){
    echo update_bundle($bundle,$name,$discount,$courses);
}
if($function == 'remove_bundle'){
    echo remove_bundle($bundle);
}
if($function == 'add_new_user_bundle'){
    echo add_new_user_bundle($list,$user);
}
function add_bundle($name ,$discount,$courses){
global $DB;
$ins = new stdClass();
$ins->name=$name;
$ins->discount=$discount;
$ins->courses=$courses;
$res=$DB->insert_record('bundles', $ins);
if($res){
    return 'Successfully';
}
return 'error';
}
function update_bundle($id,$name ,$discount,$courses){
    global $DB;
    $ins = new stdClass();
    $record=$DB->get_record('bundles',array('id'=>$id));
    if(!empty($record)){
        $ins->id=$id;
        $ins->name=$name;
        $ins->discount=$discount;
        $ins->courses=$courses;
        $res=$DB->update_record('bundles', $ins);
        if($res){
            return 'Successfully';
        }
        return 'error';
        }
    }
    function remove_bundle($id){
        global $DB;
        $res=$DB->delete_records('bundles', array('id'=>$id));
        if($res){
            return 'Successfully';
        }
        return 'error';
    }
    function add_new_user_bundle(Array $list,$user){
        global $DB;
        $count=count($list);
        $bundle_id=0;
        $bundles=$DB->get_records_sql('SELECT * FROM `mdl_bundles` where courses <='.$count.' ORDER BY courses DESC limit 1');
        foreach ($bundles as $bundle) {
           $bundle_id=$bundle->id;
           break;
        }
        $flag=false;
        if(!empty($bundle_id)){
           for($i=0;$i<$count;$i++){
            
            $ins = new stdClass();
            $ins->course=$list[$i];
            $ins->user=$user;
            $ins->bundle=$bundle_id;
            $res=$DB->insert_record('user_bundle', $ins);
            if($res){
               $flag=true;
            }
           }
           if($flag==true){
            return 'Successfully'; 
           }
           return 'error';

        }
        else{
            return 'No bundle Available';
        }
    }
