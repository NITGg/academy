<?php
require_once('../config.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/json/quizreport.php');
require_once($CFG->dirroot . '/mod/quiz/classes/external.php');
$PAGE->set_context(get_system_context());
$PAGE->set_pagelayout('site');
$PAGE->set_title("Quiz Report");
$PAGE->set_heading("Quiz Report");
$id=$_GET['quiz_id'];
$course_id=$_GET['course'];
$enrolled=core_enrol_external::get_enrolled_users($course_id);
$options[0]['name']='modname';
$options[0]['value']='quiz';

$var = core_course_external::get_course_contents($course_id,$options);
echo $OUTPUT->header();
$teacher=$_GET['teacher'];
$admins = get_admins();
$isadmin = false;
foreach($admins as $admin) {
  if ($USER->id == $admin->id) {
      $isadmin = true;
      break;
  }
}
$roleassignments = $DB->get_records('role_assignments', ['userid' => $USER->id]);
$manager=0;
foreach($roleassignments as $role){
  if($role->roleid==1){
    $manager=1;
    break;
  }
}
if($USER->id==$teacher ||$isadmin ||$manager==1){
$name="";
$q=$DB->get_record('quiz',array('id'=>$id));

$name=$q->name;
echo'<a href="'.$CFG->wwwroot.'/my_reports/all_quiz_data.php?courseid='.$course_id.'&teacher='.$teacher.'" class="btn btn-warning">'.get_string("back","theme_edumy").'</a>';
echo '<button onclick="" class="btn btn-primary ml-2 " id="show">'.get_string("tries","theme_edumy").'</button>';

echo'<div style="text-align: center;"><h3>'.$name.'</h3></div>
<table class="table table-success table-striped mt-2" id="graded">
<tr>
  <th class="table-primary">'.get_string("quiz_name","theme_edumy").'</th>
  <th class="table-success">'.get_string("student_name","theme_edumy").' </th>
  <th class="table-danger">'.get_string("quiz_grade","theme_edumy").'</th>
</tr>
';
 
    // echo $_POST['quiz_id'];
    // $count=0;
    // for($j=0;$j<count($var[$count-1]['modules']);$j++){
    //     for($i=0;$i<count($enrolled);$i++){
    //     $attempid=$data['data'][$i]['quizattemptid'];
    //     $var = mod_quiz_external::get_attempt_review($attempid);
    //     echo '<tr>
    //     <td class="table-primary">'.$q->name.'</td>
    //     <td class="table-success">'.$data['data'][$i]['fullname'].'</td>
    //     <td class="table-danger">'.$var["grade"].'</td>
    //     <td class="table-secondary">'.$data['data'][$i]['attempt'].' </td>
    //     </tr>';}}
       $data = json_decode(quiz_report($id),TRUE);
//     // echo $_POST['quiz_id'];
      // var_dump($data);

      $ids=array();
  
      for($w=0;$w<$var[$w]['modules'];$w++){
    for($j=0;$j<count($enrolled);$j++){
      if($enrolled[$j]['roles'][$w]['shortname']=='student'){
      $flag=false;
      $index=0;
      for($i=0;$i<count($data['data']);$i++){
        if($enrolled[$j]['id']==$data['data'][$i]['userid']){
      echo '<tr>
      <td class="table-primary">'.$q->name.'</td>
      <td class="table-success">'.$enrolled[$j]['fullname'].'</td>';

        $flag=true;
        $index=$i;
        break;
      }
     
   }
        if($flag==true){
          $attempid=$data['data'][$index]['quizattemptid'];
          // $var = mod_quiz_external::get_attempt_review($attempid);
         echo' <td class="table-danger">'.$data['data'][$index]['grade'].'</td>';
     
        }
        else{
         
          array_push($ids,$enrolled[$j]['id']);
        }
        echo '</tr>';
      }}}
        
echo '</table>';

echo '<table class="table table-success table-striped" style="display:none" id="not_graded">
<tr>
<th class="table-primary">'.get_string("quiz_name","theme_edumy").'</th>
<th class="table-success">'.get_string("student_name","theme_edumy").' </th>
<th class="table-danger">'.get_string("quiz_grade","theme_edumy").'</th>
</tr>
';
for($i=0;$i<count($ids);$i++){
    echo'<tr>';
    $user=$DB->get_record('user',array('id'=>$ids[$i]));
    echo '
    <td class="table-primary" >'.$q->name.'</td>
    <td class="table-success" >'.$user->firstname.' '.$user->lastname.'</td>
    <td class="table-danger" > '.get_string("no_grades","theme_edumy").'</td>
    ';
    echo'</tr>';
}


echo'</table>
<script>
$( document ).ready(function() {
    $("#show").click(function(){
        $("#graded").toggle();
        $("#not_graded").toggle();
        if ($(this).text() == "Show Students with no Tries")
        $(this).text("Show Students with Tries")
        else if($(this).text() == "Show Students with Tries")
        $(this).text("Show Students with no Tries");
        else if ($(this).text() == "عرض الطلاب بدون محاولات")
        $(this).text("عرض محاولات الطلاب")
     else
        $(this).text("عرض الطلاب بدون محاولات");

    });
});
</script>
';
}
echo $OUTPUT->footer();