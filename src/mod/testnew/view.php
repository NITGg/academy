<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT);    // Course Module ID
$token = optional_param('token',  0, PARAM_TEXT); //token from mobile api

// $context = context_module::instance($cm->id);
if (!$cm = get_coursemodule_from_id('testnew', $id)) {
    print_error('Course Module ID was incorrect'); // NOTE this is invalid use of print_error, must be a lang string id
}
if (!$course = $DB->get_record('course', array('id'=> $cm->course))) {
    print_error('course is misconfigured');  // NOTE As above
}
if (!$testnew = $DB->get_record('testnew', array('id'=> $cm->instance))) {
    print_error('course module is incorrect'); // NOTE As above
}
// var_dump($testnew);
if(!empty($token) ){

    $api = new webservice();
    $array = array();
    try{
        $array = $api->authenticate_user($token);
    if (!empty($array)){

    }
    else 
       echo json_encode( ['message'=>'invalide token']);

    }catch(Exception $e){
        echo json_encode( ['message'=>'invalide token']);
    }
    

}
require_course_login($course, true, $cm);
echo $OUTPUT->header();
$userid=$USER->id;
$studentRole = $DB->get_field('role', 'id', array('shortname' => 'student'));
$isStudent = $DB->record_exists('role_assignments', ['userid' => $userid, 'roleid' => $studentRole]);
if($isStudent){
    $checkUser=$DB->get_record('testnew_logs',array('user'=>$userid,'testnewid'=>$cm->instance));
    if(empty($checkUser)){
        $ins=new stdClass();
        $ins->views=1;
        $ins->testnewid=$cm->instance;
        $ins->user=$userid;
        $ins->id=$DB->insert_record('testnew_logs',$ins);
        // if(!empty($ins->id)){
        //     return json_encode(['data' => "done"]);
        // }
        // else{
        //     return json_encode(['data' => "error"]);
        // }
    }else{
        $upd=new stdClass();
        $upd->id=$checkUser->id;
        $upd->views=$checkUser->views+1;
        $upd->id=$DB->update_record('testnew_logs',$upd);
        // if(!empty($upd->id)){
        //     return json_encode(['data' => "done"]);
        // }
        // else{
        //     return json_encode(['data' => "error"]);

        // }
    }
}



$i=1;
$context = context_module::instance($cm->id);
$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'mod_testnew', 'content', 0, 'sortorder DESC, id ASC', false); // TODO: this is not very efficient!!
foreach($files as $file){
        $fileurl = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename());
        $download_url = $fileurl->get_port() ? $fileurl->get_scheme() . '://' . $fileurl->get_host() . $fileurl->get_path() . ':' . $fileurl->get_port() : $fileurl->get_scheme() . '://' . $fileurl->get_host() . $fileurl->get_path();
        echo '

        <div>
        <h3 style="text-align: center;">PDF '.$i.'</h3>
        <iframe width="100%" id="frame" height="600px" src="'.$download_url.'#toolbar=0"></iframe></div>
        
        
        ';
        $i++;
}
unset($files);
echo $OUTPUT->footer();
// $file=$DB->get_record('files',array('itemid'=>$testnew->type));
// $context = context_module::instance($cm->id);
// $fs = get_file_storage();
// $files = $fs->get_area_files(5, 'user', 'draft', $file->id);
// $url = moodle_url::make_pluginfile_url($file->contextid, $file->component, $file->filearea, $file->itemid,$file->filepath, $file->filename);
// $url = moodle_url::make_pluginfile_url($file->contextid, $file->component, $file->filearea, $file->itemid,$file->filepath, $file->filename,false);

// $fs = get_file_storage();
// $files = $fs->get_file($file->contextid,  $file->component, $file->filearea, $file->itemid, $file->filepath, $file->filename);

// if (count($files) < 1) {
//     // resource_print_filenotfound($resource, $cm, $course);
//     die;
// } else {
//     $data = reset($files);
//     unset($files);
// }
// var_dump($url);
// $url = moodle_url::make_file_url('/pluginfile.php', array($file->contextid,  $file->component,  $file->filearea,
// $file->itemid, $file->filepath, $file->filename));
// var_dump($files);

    // $file = reset($files);
    // 
    // $fileurl = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename());

// $path = '/'.$context->id.'/mod_testnew/content/0/'.$file->get_filename();
// $fullurl = moodle_url::make_file_url('/pluginfile.php', $path);

// $download_url = $fileurl->get_port() ? $fileurl->get_scheme() . '://' . $fileurl->get_host() . $fileurl->get_path() . ':' . $fileurl->get_port() : $fileurl->get_scheme() . '://' . $fileurl->get_host() . $fileurl->get_path();
// echo '<a href="' . $download_url . '"download >' . $file->get_filename() . '</a><br/>';
// echo '<br>';
// echo '
// <script src="ViewerJS/compatibility.js"></script>
// <script src="ViewerJS/pdf.js"></script>
// <script src="ViewerJS/pdf.worker.js"></script>
// <script src="ViewerJS/pdfjsversion.js"></script>
// <script src="ViewerJS/text_layer_builder.js"></script>
// <script src="ViewerJS/ui_utils.js"></script>
// <script src="ViewerJS/webodf.js"></script>

// ';
// // echo '<iframe src="'.$download_url.'"></iframe>';
// // $PAGE->requires->js(new moodle_url('/'));
// echo '<object data="'.$download_url.'" type="application/pdf" frameborder="0" width="100%" height="600px" style="padding: 20px;">
// <embed src="https://drive.google.com/file/d/1CRFdbp6uBDE-YKJFaqRm4uy9Z4wgMS7H/preview?usp=sharing" width="100%" height="600px"/> 
// </object>';
// require_course_login($course, true, $cm);

// echo $OUTPUT->header();
// echo '<div class="container">';

// echo '<a href="' . $download_url . '" target="_blank">'.$file->get_filename().'</a><br>';

// echo '

// <iframe width="100%" id="frame" height="600px" src="'.$download_url.'#toolbar=0"></iframe>


// ';
// echo $OUTPUT->footer();