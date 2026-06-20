<?php

require_once('../../config.php');
require_once("../../mod/quiz/locallib.php");
require_once("../../mod/quiz/attemptlib.php");
header('Content-Type: application/json');

$token = optional_param('token',  0, PARAM_TEXT);

$function = optional_param('function', ' ', PARAM_RAW);
$courseId = optional_param('courseID', -1, PARAM_INT);
$questionattemptids = optional_param('questionattemptids', array(), PARAM_INT);
$quiz = optional_param('quiz', -1, PARAM_INT);
$cm = optional_param('cm', -1, PARAM_INT);
$answer = optional_param('answer', '', PARAM_RAW);
$index = optional_param('index', -1, PARAM_INT);
$attemptid = optional_param('attemptid', -1, PARAM_INT);
$userID = optional_param('userid', -1, PARAM_INT);
$questionattemptid = optional_param('questionattemptid', -1, PARAM_INT);

if ($function == "quiz") {
    echo get_quiz_data($courseId, $quiz, $cm, $userID);
}
elseif ($function == "test") {
    echo get_quiz_test_data($courseId, $quiz, $cm, $userID);
}
if ($function == "create") {
    echo create_new_attempt($courseId, $quiz, $cm, $userID);
}
if ($function == "add") {
    echo add_new_answer_for_user($questionattemptid, $userID, $answer, $index);
}
if ($function == "finish") {
    // $questionattemptids[] = new stdClass;

    //  $questionattemptids[0]->qid = 285;
    // // $questionattemptids[0]->answer = "1\n";
    //  $questionattemptids[1]->qid = 286;
    // // $questionattemptids[1]->answer = "2\n";
    echo finish_attempt_user($questionattemptids, $userID, $attemptid);
}




// function create_new_attempt($course,$quiz,$cm,$user){
//     global $DB;
// $cm      = get_coursemodule_from_id('quiz', $cm, $course);
// $quizobj = quiz::create($cm->instance, $user);

// $check_quiz=$DB->get_record("quiz_attempts",array("quiz"=>$quiz,"userid"=>$user));
// if(empty($check_quiz)){
//     // echo "1";
//     $qq=quiz_prepare_and_start_new_attempt($quizobj,1,"",$offlineattempt=false,$forcedrandomquestions =[],$forcedvariants=[] ,$user );

// }
// else{
//     // echo "2";

//     $old_attempt=$check_quiz->attempt+1;
//     $qq=quiz_prepare_and_start_new_attempt($quizobj,$old_attempt,"",$offlineattempt=false,$forcedrandomquestions =[],$forcedvariants=[] ,$user );

// }
// // $attempt = quiz_create_attempt($quizobj, 1, "", time(), false, $user);
// // $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
// // $attempt = quiz_start_new_attempt($quizobj, $quba, $attempt, 1, time(),
// // $forcedrandomquestions=array(), $forcedvariants=array());

// return $qq->id;
// // var_dump($attempt);

// }
function create_new_attempt($course, $quiz, $cmodule, $user)
{
    global $DB;
    $cm      = get_coursemodule_from_id('quiz', $cmodule, $course);
    $quizobj = quiz::create($cm->instance, $user);
    // return json_encode([$quizobj]);
    $check_quiz = $DB->get_records_sql("SELECT * FROM `mdl_quiz_attempts`where quiz=$quiz and userid=$user ORDER BY `timestart` DESC limit 1");
    if (empty($check_quiz)) {

        $qq = quiz_prepare_and_start_new_attempt($quizobj, 1, "", $offlineattempt = false, $forcedrandomquestions = [], $forcedvariants = [], $user);
    } else {
        foreach ($check_quiz as $check) {
            $new = $check->attempt + 1;
            $qq = quiz_prepare_and_start_new_attempt($quizobj, $new, "", $offlineattempt = false, $forcedrandomquestions = [], $forcedvariants = [], $user);
        }
    }


    return $qq->id;
}

function get_attempt_id($quiz, $user, $course, $cm)
{
    global $DB;
    $attemptid = $DB->get_record('quiz_attempts', array('quiz' => $quiz, "userid" => $user, "state" => "inprogress", "timefinish" => "0"));
    if (!empty($attemptid)) {
        return $attemptid->id;
    } else {
        $id = create_new_attempt($course, $quiz, $cm, $user);
        return $id;
    }
}
function add_new_answer_for_user($questionattemptid, $user, $answer = null, $index = null)
{
    global $DB;
    $step1 = $DB->get_record('question_attempt_steps', array('questionattemptid' => $questionattemptid, 'state' => "todo", "userid" => $user));

    if (!empty($step1)) {
 
        $resubmit = $DB->get_records_sql("SELECT  * from mdl_question_attempt_steps where questionattemptid= $questionattemptid and state='complete' and userid= $user ORDER by id DESC limit 1" );
        // return json_encode(['quiz'=>$resubmit]);

        // array('questionattemptid' => $questionattemptid, 'state' => "complete", "userid" => $user)
        //check if the suser return to submit question
        if (empty($resubmit)) {

            if ($index!=-1) {

                $steps = new stdClass();
                $steps->questionattemptid = $questionattemptid;
                $steps->sequencenumber = 1;
                $steps->state = "complete";
                $steps->timecreated = time();
                $steps->userid = $user;
                $steps->id = $DB->insert_record('question_attempt_steps', $steps);

                $steps_data = new stdClass();
                $steps_data->attemptstepid = $steps->id;
                $steps_data->name = "answer";
                $steps_data->value = $index;
                $DB->insert_record('question_attempt_step_data', $steps_data);

                $question_attempts_data = $DB->get_record("question_attempts", array("id" => $questionattemptid));
                if (!empty($question_attempts_data)) {
                    $question_attempts = new stdClass();
                    $question_attempts->id = $question_attempts_data->id;
                    $question_attempts->responsesummary = $answer;
                    $DB->update_record("question_attempts", $question_attempts);
                }
            }
        } else {
            foreach($resubmit as $res){

            
            $steps = new stdClass();
            $steps->questionattemptid = $questionattemptid;
            $steps->sequencenumber = $res->sequencenumber + 1;
            $steps->state = "complete";
            $steps->timecreated = time();
            $steps->userid = $user;
            $steps->id = $DB->insert_record('question_attempt_steps', $steps);

            $steps_data = new stdClass();
            $steps_data->attemptstepid = $steps->id;
            $steps_data->name = "answer";
            $steps_data->value = $index;
            $DB->insert_record('question_attempt_step_data', $steps_data);

            $question_attempts_data = $DB->get_record("question_attempts", array("id" => $questionattemptid));
            if (!empty($question_attempts_data)) {
                $question_attempts = new stdClass();
                $question_attempts->id = $question_attempts_data->id;
                $question_attempts->responsesummary = $answer;
                $DB->update_record("question_attempts", $question_attempts);
            }
        }
        }
    }
}
function finish_attempt_user($questionattemptids, $user, $attemptid)
{
    global $DB;
    $total = 0;

    for ($i = 0; $i < count($questionattemptids); $i++) {

        $step2 = $DB->get_records_sql("SELECT * FROM `mdl_question_attempt_steps` where questionattemptid=".$questionattemptids[$i]." and state='complete' and userid=$user ORDER by id DESC limit 1" );

        if (!empty($step2)) {  
            foreach ($step2 as $step) {
            
            $attempt = $DB->get_record('quiz_attempts', array('id' =>$attemptid));

            $questionsattempt = $DB->get_record('question_attempts', array('questionusageid' => $attempt->uniqueid, "id" => $questionattemptids[$i]));


            $rightAnswer = $questionsattempt->rightanswer;
            // $rightAnswer=str_replace("\r\n","",$rightAnswer);
            $rightAnswer = trim(preg_replace('/\s\s+/', ' ', $rightAnswer));

            $minfraction = $questionsattempt->minfraction;
            $mark = $questionsattempt->maxmark;

            $answer = $questionsattempt->responsesummary;

            // return json_encode(['quiz'=>$answer,"answer"=>$rightAnswer]);

            if ($answer == $rightAnswer) {

                $steps = new stdClass();
                $steps->questionattemptid = $questionattemptids[$i];
                $steps->sequencenumber = $step->sequencenumber + 1;
                $steps->state = "gradedright";
                $steps->fraction = $mark;
                $steps->timecreated = time();
                $steps->userid = $user;
                // return json_encode(['quiz'=>$steps]);

                $steps->id = $DB->insert_record('question_attempt_steps', $steps);

                $steps_data = new stdClass();
                $steps_data->attemptstepid = $steps->id;
                $steps_data->name = "-finish";
                $steps_data->value = 1;
                $DB->insert_record('question_attempt_step_data', $steps_data);
                $total += $mark;
            } else {

                $steps = new stdClass();
                $steps->questionattemptid = $questionattemptids[$i];
                $steps->sequencenumber = $step->sequencenumber + 1;
                $steps->state = "gradedwrong";
                $steps->fraction = $minfraction;
                $steps->timecreated = time();
                $steps->userid = $user;
                $steps->id = $DB->insert_record('question_attempt_steps', $steps);

                $steps_data = new stdClass();
                $steps_data->attemptstepid = $steps->id;
                $steps_data->name = "-finish";
                $steps_data->value = 1;
                $DB->insert_record('question_attempt_step_data', $steps_data);
            }}
        } else {
            $steps = new stdClass();
            $steps->questionattemptid = $questionattemptids[$i];
            $steps->sequencenumber = 1;
            $steps->state = "gaveup";
            $steps->timecreated = time();
            $steps->userid = $user;
            $steps->id = $DB->insert_record('question_attempt_steps', $steps);

            $steps_data = new stdClass();
            $steps_data->attemptstepid = $steps->id;
            $steps_data->name = "-finish";
            $steps_data->value = 1;
            $DB->insert_record('question_attempt_step_data', $steps_data);
        }
     
    }
    $quiz_data = $DB->get_record("quiz_attempts", array("id" => $attemptid));
    $quiz_grade=$DB->get_record("quiz_grades", array("quiz" => $quiz_data->quiz, "userid" => $user));
    if(empty($quiz_grade)){
    $quiz_grade = new stdClass();
    $quiz_grade->quiz = $quiz_data->quiz;
    $quiz_grade->userid = $user;
    $quiz_grade->grade = $total;
    $quiz_grade->timemodified = time();
    $DB->insert_record('quiz_grades', $quiz_grade);
    }
    else{
        $quizData=$DB->get_record('quiz', array('id' => $quiz_data->quiz));
        if($quizData->grademethod==1){
            if($total>$quiz_grade->grade){
                $newGrade=new stdClass();
                $newGrade->id=$quiz_grade->id;
                $newGrade->grade = $total;
                $newGrade->timemodified = time();
                $DB->update_record('quiz_grades', $newGrade);
            }
        }
        elseif($quizData->grademethod==2){
                $newGrade=new stdClass();
                $newGrade->id=$quiz_grade->id;
                $newGrade->grade = $total+$quiz_grade->grade/2;
                $newGrade->timemodified = time();
                $DB->update_record('quiz_grades', $newGrade);
            
        }
        elseif($quizData->grademethod==4){
                $newGrade=new stdClass();
                $newGrade->id=$quiz_grade->id;
                $newGrade->grade = $total;
                $newGrade->timemodified = time();
                $DB->update_record('quiz_grades', $newGrade);
            
        }
    }


    $quiz_attempt = $DB->get_record('quiz_attempts', array('id' => $attemptid));
    if (!empty($quiz_attempt)) {
        $attempt_finish = new stdClass();
        $attempt_finish->id = $attemptid;
        $attempt_finish->sumgrades = $total;
        $attempt_finish->state = "finished";
        $attempt_finish->preview = 0;
        $attempt_finish->timefinish = time();
        $attempt_finish->timemodified = time();
        $attempt_finish->id = $DB->update_record('quiz_attempts', $attempt_finish);
    }
    return json_encode(['quiz'=>$total]);

}

// function get_quiz_data($course, $quiz, $cm, $user)
// {
//     global $DB;
//     // $attemptid = get_attempt_id($quiz, $user, $course, $cm);
//     $attemptid=create_new_attempt($course,$quiz,$cm,$user);

//     $cm      = get_coursemodule_from_id('quiz', $cm, $course);
//     $course  = $DB->get_record('course', array('id' => $course));
//     $quiz    = $DB->get_record('quiz', array('id' => $quiz));
//     $attempt = $DB->get_record('quiz_attempts', array('id' => $attemptid));
//     $questionsattempts = $DB->get_records_sql("SELECT quiza.userid, quiza.quiz, quiza.id AS quizattemptid, quiza.attempt, quiza.sumgrades, qu.preferredbehaviour, qa.slot, qa.behaviour, qa.questionid, qa.variant, qa.maxmark, qa.minfraction, qa.flagged, qas.sequencenumber, qas.state, qas.fraction, FROM_UNIXTIME(qas.timecreated), qas.userid, qasd.name, qasd.value, qa.questionsummary, qa.rightanswer, qa.responsesummary FROM mdl_quiz_attempts quiza JOIN mdl_question_usages qu ON qu.id = quiza.uniqueid JOIN mdl_question_attempts qa ON qa.questionusageid = qu.id JOIN mdl_question_attempt_steps qas ON qas.questionattemptid = qa.id LEFT JOIN mdl_question_attempt_step_data qasd ON qasd.attemptstepid = qas.id WHERE quiza.id = $attemptid ");
//     // return json_encode(['quiz' => $attemptid]);

//     $quizData = array();
//     $ans = array();

//     foreach ($questionsattempts as $value) {

//         $questionsattempt = $value;
//         $output = $questionsattempt->questionsummary;
//         $output = trim(preg_replace('/\s\s+/', ' ', $output));
//         $rightAnswer = $questionsattempt->rightanswer;
//         $rightAnswer = trim(preg_replace('/\s\s+/', ' ', $rightAnswer));

//         // $last_string = substr($output, strrpos($output, ':') );
//         $options = explode(":", $output);
//         $answers = $options[1];
//         if (strpos($answers, ';') !== false) {

//             $answers = explode(";", $answers);
//         }
//         for ($i = 0; $i < sizeof($answers); $i++) {
//             $ans[$i] = trim(preg_replace('/\s\s+/', ' ', $answers[$i]));
//         }

//         $index = array_search($rightAnswer, $ans);
//         $options[0] = trim(preg_replace('/\s\s+/', ' ', $options[0]));
//         $quizData[] = array("questionattemptid" => $value->questionid, "index" => $index, "numberOfAttempts" => $quiz->attempts, "question" => $options[0], "rightAnswer" => $rightAnswer, "options" => $ans);
//     }
//     $data["attemptid"] = $attemptid;
//     $data["quizData"] = $quizData;
//     return json_encode(['quiz' => $data]);
// }
// function get_quiz_data($course,$quiz,$cm,$user){
//     global $DB;
//     // $attemptid=$DB->get_record('quiz_attempts',array('quiz'=>$quiz,"userid"=>$user,"state"=>"inprogress","timefinish"=>"0"));
//     // $attemptid=get_attempt_id($quiz,$user,$course,$cm);
//     $attemptid=create_new_attempt($course,$quiz,$cm,$user);
//     $cm      = get_coursemodule_from_id('quiz', $cm, $course);
//     $course  = $DB->get_record('course', array('id' => $course));
// $quiz    = $DB->get_record('quiz', array('id' =>$quiz));
// $attempt = $DB->get_record('quiz_attempts', array('id' =>$attemptid));
// $questionsattempts = $DB->get_records('question_attempts', array('questionusageid'=>$attempt->uniqueid));

// $quizData=array();
// foreach ($questionsattempts as $value) {

//     $questionsattempt = $value;
//     $output = $questionsattempt->questionsummary;
//     $output = trim(preg_replace('/\s\s+/', ' ', $output));
//     $rightAnswer = $questionsattempt->rightanswer;
//     $rightAnswer = trim(preg_replace('/\s\s+/', ' ', $rightAnswer));

//     // $last_string = substr($output, strrpos($output, ':') );
//     $options = explode(":", $output);
//     $answers = $options[1];
//     if (strpos($answers, ';') !== false) {

//         $answers = explode(";", $answers);
//     }
//     for ($i = 0; $i < sizeof($answers); $i++) {
//         $ans[$i] = trim(preg_replace('/\s\s+/', ' ', $answers[$i]));
//     }

//     $index = array_search($rightAnswer, $ans);
//     $options[0] = trim(preg_replace('/\s\s+/', ' ', $options[0]));
//     $quizData[] = array("questionattemptid" => $value->id, "index" => $index, "numberOfAttempts" => $quiz->attempts, "question" => $options[0], "rightAnswer" => $rightAnswer, "options" => $ans);
// }
// // $quizData=$DB->get_record('quiz',array('id'=>$quiz->id));
// $data["grade"]=round($quiz->grade, 2);
// $data["attemptid"]=$attemptid;
// $data["quizData"]=$quizData;

// return json_encode(['quiz'=>$data]);
// }
function get_quiz_data($course,$quiz,$cm,$user){
    global $DB;
    // $attemptid=$DB->get_record('quiz_attempts',array('quiz'=>$quiz,"userid"=>$user,"state"=>"inprogress","timefinish"=>"0"));
    // $attemptid=get_attempt_id($quiz,$user,$course,$cm);
    // $attemptid=create_new_attempt($course,$quiz,$cm,$user);
    // $cm      = get_coursemodule_from_id('quiz', $cm, $course);
    // $course  = $DB->get_record('course', array('id' => $course));
$quiz    = $DB->get_record('quiz', array('id' =>$quiz));
 $numberOfAttempts=0;
$quizData=array();
$attemptid=0;
if(time()>$quiz->timeopen && time()<$quiz->timeclose || $quiz->timeopen==0 ){

    $check_attempt = $DB->get_records_sql("SELECT * from mdl_quiz_attempts where quiz=$quiz->id and userid=$user order by id desc limit 1" ); 

if(!empty($check_attempt)){
    foreach($check_attempt as $check){
        $numberOfAttempts=$check->attempt;
    }

}
if($numberOfAttempts<=$quiz->attempts||$quiz->attempts==0){

    $attemptid=create_new_attempt($course,$quiz->id,$cm,$user);

    $attempt = $DB->get_record('quiz_attempts', array('id' =>$attemptid));
    $questionsattempts = $DB->get_records('question_attempts', array('questionusageid'=>$attempt->uniqueid));
    foreach ($questionsattempts as $value) {

        $questionsattempt = $value;
        $output = $questionsattempt->questionsummary;
        $output = trim(preg_replace('/\s\s+/', ' ', $output));
        $rightAnswer = $questionsattempt->rightanswer;
        $rightAnswer = trim(preg_replace('/\s\s+/', ' ', $rightAnswer));
    
        // $last_string = substr($output, strrpos($output, ':') );
        $options = explode(":", $output);
        $answers = $options[1];
        if (strpos($answers, ';') !== false) {
    
            $answers = explode(";", $answers);
        }
        for ($i = 0; $i < sizeof($answers); $i++) {
            $ans[$i] = trim(preg_replace('/\s\s+/', ' ', $answers[$i]));
        }
    
        $index = array_search($rightAnswer, $ans);
        $options[0] = trim(preg_replace('/\s\s+/', ' ', $options[0]));
        $quizData[] = array("questionattemptid" => $value->id, "index" => $index, "numberOfAttempts" => $quiz->attempts, "question" => $options[0], "rightAnswer" => $rightAnswer, "options" => $ans);
    }
}



}   


// $quizData=$DB->get_record('quiz',array('id'=>$quiz->id));

if(empty($quizData)){
    $data["grade"]="";
    $data["attemptid"]=$attemptid;
    $data["error"]="لقد انتهي وقت الامتحان او انتهت مجموع محاولاتك لاتمام الامتحان";
    $data["quizData"]=array();

}else{
    $data["grade"]=round($quiz->grade, 2);
    $data["attemptid"]=$attemptid;
    $data["error"]="";  
    $data["quizData"]=$quizData;

}

return json_encode(['quiz'=>$data]);
}