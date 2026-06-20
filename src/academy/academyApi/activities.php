<?php
require_once('../config.php');
require_once($CFG->dirroot . '/files/externallib.php');

// require_once($CFG->dirroot .'/mod/assign/externallib.php');
require '../vimeo/vendor/autoload.php';

use Vimeo\Vimeo;
// $PAGE->set_url($CFG->wwwroot.'/academyApi/activities.php');
// require_once('../vimeo/vendor/autoload.php');
$function = required_param('function', PARAM_RAW);
$id = optional_param('id', -1, PARAM_INT);
$user = optional_param('user', -1, PARAM_INT);
$courseid = optional_param('courseid', -1, PARAM_INT);
$token = optional_param('token', '', PARAM_RAW);

if ($function == 'get_file_content') {
    echo get_file_content($id);
}
if ($function == 'get_page_content') {
    echo get_page_content($id, $courseid);
}
if ($function == 'add_update_submission') {
    echo add_update_submission($id, $user);
}

if ($function == 'assign_data') {
    echo assign_data($id, $courseid, $token);
}
if ($function == 'get_old_submission') {
    echo get_old_submission($token, $id, $user);
}
if ($function == 'upload') {
    upload_files();
}
function get_file_content($id)
{
    global $DB;
    $record = $DB->get_record("vimeo_files", array('resource_id' => $id));
    $client = new Vimeo("4dad588b7f47a44426afc26f398fe2367ea49c92", "IHRxCFjq5qvsKlU6DjWGfNQwtZGHGmK1pByyCYWGrkWnE9F91BbNqPdqXY+dHVyvKjvRWYTu3ba2A8KM1GR2gcqqYiz+jXAx6uLrsEb0jFJrUSMIi3KMIyS+Je+nsN3s", "195c95a4e775fca8d6e70cb8db4aca73");
    $uri = "/videos/" . $record->url;
    $response = $client->request($uri, [], 'GET');
    $status = $response['body']['transcode']['status'];
    $content = $response['body']['embed']['html'];
    preg_match('/src="([^"]+)"/', $content, $match);
    $url = $match[1];
    return json_encode(["status" => $status, "content" => $url]);
}
function get_page_content($id, $course)
{
    global $DB;
    $record = $DB->get_record("page", array('id' => $id));
    $options = $DB->get_record("course_modules", array('instance' => $id, 'module' => 16, 'course' => $course));
    if ($options->visible == "1") {
        if ($options->showdescription == "1") {
            return json_encode(["title" => $record->name, "discription" => $record->intro, "content" => $record->content]);
        } else {
            return json_encode(["title" => $record->name, "content" => $record->content]);
        }
    } else {
        return json_encode(['data' => "Sorry these module is not visible right now"]);
    }
}
// function get_submitions($id){
//     get_submissions($id);
// }
function add_update_submission($id, $logged_user)
{
    global $DB;
    $assign = new stdClass();
    $record = $DB->get_record("assign_submission", array('assignment' => $id, 'userid' => $logged_user, 'status' => "submitted"));
    if (!empty($record)) {
        $assign->timecreated = time();
        $assign->timemodified = time();
        $assign->id = $record->id;
        $assign->id = $DB->update_record('assign_submission', $assign);
        return json_encode(['data' => "updated"]);
    } else {
        $assign->assignment = $id;
        $assign->userid = $logged_user;
        $assign->timecreated = time();
        $assign->timemodified = time();
        $assign->status = "submitted";
        $assign->id = $DB->insert_record('assign_submission', $assign);
        if (!empty($assign->id)) {
            return json_encode(['data' => "added"]);
        }
    }
}
function get_old_submission($token,$id,$userId){
    $submissions = 'https://' . $_SERVER['SERVER_NAME'] . '/webservice/rest/server.php?wstoken=' . $token . '&wsfunction=mod_assign_get_submissions&assignmentids[0]=' . $id . '&moodlewsrestformat=json';
    $response = file_get_contents($submissions);
    $response = json_decode($response);

    for ($i = 0; $i < count($response->assignments[0]->submissions); $i++) {
        if($response->assignments[0]->submissions[$i]->userid==$userId){
          $data=  $response->assignments[0]->submissions[$i]->plugins[0]->fileareas[0]->files[0]->fileurl;
          $data.="?token=".$token;
          return json_encode($data);

        }
    }
}

// update submission 
//add new file with update and with add
//get old submission 
//get assign data

function assign_data($id, $course, $token)
{
    // $token='771addf74ab5b159570d40afcdf6c8ee';
    global $DB;
    $record = $DB->get_record("assign", array('id' => $id));
    $options = $DB->get_record("course_modules", array('instance' => $id, 'module' => 1, 'course' => $course));
    if ($options->visible == "1") {
        $data = 'https://' . $_SERVER['SERVER_NAME'] . '/webservice/rest/server.php?wstoken=' . $token . '&wsfunction=mod_assign_get_assignments&courseids[0]=' . $course . '&moodlewsrestformat=json';
        $response = file_get_contents($data);
        $response = json_decode($response);
        for ($i = 0; $i < count($response->courses[0]->assignments); $i++) {
            if ($response->courses[0]->assignments[$i]->id == $id) {
                $response->courses[0]->assignments[$i]->introattachments[0]->fileurl .= "?token=" . $token;
                return json_encode($response->courses[0]->assignments[$i]);
            }
        }
    } else {
        return json_encode(['data' => "Sorry these module is not visible right now"]);
    }
}
function upload_files(){

//     $token = '695b234c00e539fd39567737b6f2a738  ';
// $domainname = 'https://' .$_SERVER['SERVER_NAME'];
// $imagepath ='E:/Aya/new.medadaa/FB_IMG_1640452605539.jpg';
// $filepath = '/';
// $params = array('file_box' => "@".$imagepath,'filepath' => $filepath, 'token' => $token);
// $ch = curl_init();
// curl_setopt($ch, CURLOPT_HEADER, 0);
// curl_setopt($ch, CURLOPT_VERBOSE, 0);
// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)");
// curl_setopt($ch, CURLOPT_URL, $domainname . '/webservice/upload.php');
// curl_setopt($ch, CURLOPT_POST, true);
// curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
// $response = curl_exec($ch);
// print_r($response);


$token = '695b234c00e539fd39567737b6f2a738';
$domainname = 'https://' .$_SERVER['SERVER_NAME'];
$functionname = 'core_files_upload()';

//////// core_files_upload() ////////

/// Parameters
$file = new stdClass();
$file ->contextid = 1;
$file ->component = 'user';
$file ->filearea = 'draft';
$file ->itemid = 1;
$file ->filepath = 'E:/Aya/new.medadaa/';
$file ->filename = 'FB_IMG_1640452605539.jpg';
// $file ->url = 'E:/Aya/new.medadaa/FB_IMG_1640452605539.jpg';
$params = array($file);

/// SOAP CALL
$serverurl = $domainname . '/webservice/soap/server.php'. '?wsdl=1&wstoken=' . $token;

$client = new SoapClient($serverurl);

// try {
// $resp = $client->__soapCall($functionname, array($params));
// } catch (Exception $e) {
// print_r($e);
// }
// if (isset($resp)) {
// print_r($resp);
// }
// echo $serverurl;
var_dump($client);


}