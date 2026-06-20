<?php

require_once('../config.php');

$PAGE->set_context(get_system_context());
 $PAGE->set_pagelayout('site');
$PAGE->set_title("Remove Feadbacks Page ");
$PAGE->set_heading("Remove Feadbacks  page");
$PAGE->set_url($CFG->wwwroot.'/promocode/remove.php');
echo $OUTPUT->header();

if (isset($_GET["id"])) {
    $id=$_GET["id"];

    $getteacherID = $DB->get_records_sql("SELECT teacher_id FROM mdl_feedbacks WHERE id = '$id' ");
    $ins->id = $DB->delete_records('feedbacks', array('id' => $id));
    foreach($getteacherID as $teachID){
        redirect($CFG->wwwroot."/user/profile.php?id=". $teachID->teacher_id."");

    }
}

echo $OUTPUT->footer();
?>
