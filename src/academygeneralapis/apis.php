<?php
require_once("../config.php");
header('Content-Type: application/json');
require_once($CFG->dirroot . '/webservice/lib.php');

$function = optional_param('function', '', PARAM_RAW);
$token = optional_param('token',  0, PARAM_TEXT);
$courseID = optional_param('course', -1, PARAM_INT);
$deviceid = optional_param('deviceid', 'deviceid', PARAM_TEXT);
$model = optional_param('model',  'model', PARAM_TEXT);
$totalmemory = optional_param('totalmemory',  'totalmemory', PARAM_TEXT);
$freememory = optional_param('freememory',  'freememory', PARAM_TEXT);
$appname = optional_param('appname',  'appname', PARAM_TEXT);
$phsical = optional_param('phsical',  '0', PARAM_INT);
if (!empty($token)) {
    $api = new webservice();
    $array = array();
    try {
        $array = $api->authenticate_user($token);
        if (!empty($array)) {
            $array = json_encode($api->authenticate_user($token));
            $arr = json_decode($array, true);
            $userID = $arr['user']['id'];
            if ($function == 'get_course_Codes') {
                echo get_course_Codes($userID, $courseID);
            } elseif ($function == 'set_user_mobile') {
                echo set_user_mobile($userID, $deviceid, $model, $totalmemory, $freememory, $appname, $phsical);
            } elseif ($function == 'get_user_mobile') {
                echo get_user_mobile($userID);
            } elseif ($function == 'get_all_user_mobile') {
                echo get_all_user_mobile();
            } 
        } 
    } catch (Exception $e) {
        echo json_encode(['status' => "fail", 'data' => null, "error" => $e->errorcode]);
    }
}

function get_course_Codes($userID, $courseID)
{
    global $DB;
    $roleassigned = $DB->get_record('role_assignments', ['userid' => $userID]);
    if($roleassigned->roleid == 3 or $roleassigned->roleid == 4)//if teacher or assistant
    {
        // Assure that user have permissions to get number of codes
        $sql = "SELECT * FROM mdl_user_enrolments AS xx JOIN mdl_enrol AS yy ON xx.enrolid=yy.id where yy.courseid=" .$courseID ." and xx.userid =" .$userID ."";
        $enrolment = $DB->get_records_sql($sql);
        if(count($enrolment) > 0) //this user have permission to know Patches and number of codes for this course
        {
            //get different Batches
            $coursePatches = array_values($DB->get_records_sql("SELECT id as patchid, teacherid, centername as patchname FROM `mdl_groups_attendence_patch` WHERE centername LIKE '%Patch%' and courseid=" .$courseID .""));
            //loop on paches to get counts
            foreach($coursePatches as $patch)
            {
                $noOfCodes = array_values($DB->get_records_sql("SELECT count(*) as numberofcodes FROM mdl_groups_attendence_codes WHERE patchid=" .$patch->patchid .""));
                array_push($patch->patchnumberofcodes);
                $patch->patchnumberofcodes = $noOfCodes;
                $usedCodes = array_values($DB->get_records_sql("SELECT count(*) as numberofusedcodes FROM mdl_groups_attendence_codes WHERE used = 1 and patchid=" .$patch->patchid .""));
                array_push($patch->patchnumberofusedcodes);
                $patch->patchnumberofusedcodes = $usedCodes;
                $notusedCodes = array_values($DB->get_records_sql("SELECT count(*) as numberofnotusedcodes FROM mdl_groups_attendence_codes WHERE used = 0 and patchid=" .$patch->patchid .""));
                array_push($patch->patchnumberofnotusedcodes);
                $patch->patchnumberofnotusedcodes = $notusedCodes;
            }
            return json_encode(["state"=>'success', "data"=> $coursePatches]);
        }
        else //if teacher or assistant not enrolled into course
        {
            return json_encode(["state"=>'fail, you are not enrolled in this course']);
        }
    }
    else //if role other than teacher or assistant
    {
        return json_encode(["state"=>'fail, not allowed for this user']);
    }
    
}
function set_user_mobile($userID, $deviceid, $model, $totalmemory, $freememory, $appname, $phsical)
{
    global $DB;
    // check if this user id and device id into DB
    $deviceInfo = $DB->get_record('mobile_devices', array('userid' => $userID, 'deviceid' => $deviceid));
    if (empty($deviceInfo)) //no record returned, then add new device
    {
        //fill array object with received data
        $dataObject->userid = $userID;
        $dataObject->deviceid = $deviceid;
        $dataObject->model = $model;
        $dataObject->totalmemory = $totalmemory;
        $dataObject->freememory = $freememory;
        $dataObject->empty1 = $appname;
        $dataObject->phsical = $phsical;
        $DB->insert_record('mobile_devices', $dataObject);
        return json_encode(["state"=>'device added', "data"=> $dataObject]);
        //echo json_encode($dataObject);
    }
    else //device exists for this user, so update its record
    {
        $dataObject->id = $deviceInfo->id;
        $dataObject->model = $model;
        $dataObject->totalmemory = $totalmemory;
        $dataObject->freememory = $freememory;
        $dataObject->empty1 = $appname;
        $dataObject->phsical = $phsical;
        $DB->update_record('mobile_devices', $dataObject);
        return json_encode(["state"=>'device updated', "data"=> $deviceInfo]);
        //echo json_encode($dataObject);
    } 
    // we can get also number of different devices used by user
}   
function get_user_mobile($userID)
{
    global $DB;
    if($userID != null)
        $deviceInfo = $DB->get_records_sql("SELECT mdl_mobile_devices.timemodified, model,totalmemory,freememory,phsical, firstname, lastname, email FROM mdl_mobile_devices join mdl_user on mdl_mobile_devices.userid=mdl_user.id where mdl_user.id=$userID ORDER BY mdl_mobile_devices.timemodified DESC;");
    else
        $deviceInfo = $DB->get_records_sql("SELECT mdl_mobile_devices.timemodified, model,totalmemory,freememory,phsical, firstname, lastname, email FROM mdl_mobile_devices join mdl_user on mdl_mobile_devices.userid=mdl_user.id ORDER BY mdl_mobile_devices.timemodified DESC;");
    if(count($deviceInfo) > 0)
        return json_encode(["state"=>'success',"count"=>count($deviceInfo), "data"=> array_values($deviceInfo)]);
    else
        return json_encode(["state"=>'No user exists']);
}   
function get_all_user_mobile()
{
    global $DB;
    $deviceInfo = $DB->get_records_sql("SELECT mdl_mobile_devices.timemodified, model,totalmemory,freememory,phsical, firstname, lastname, email FROM mdl_mobile_devices join mdl_user on mdl_mobile_devices.userid=mdl_user.id ORDER BY mdl_mobile_devices.timemodified DESC;");
    if(count($deviceInfo) > 0)
        return json_encode(["state"=>'success',"count"=>count($deviceInfo), "data"=> array_values($deviceInfo)]);
    else
        return json_encode(["state"=>'No user exists']);
}   