<?php

require_once('../config.php');
require_once('../lib/moodlelib.php');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once('PHPMailer/src/Exception.php');
require_once('PHPMailer/src/PHPMailer.php');
require_once('PHPMailer/src/SMTP.php');
$PAGE->set_context(get_system_context());
 $PAGE->set_pagelayout('site');
$PAGE->set_title("Forget Password ");
$PAGE->set_heading("Forget Password");
$PAGE->set_url($CFG->wwwroot.'/json/forget_password.php');
$PAGE->requires->css(new moodle_url("https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css"));

echo $OUTPUT->header();
function generate_random_code($username){
    global $DB;
    $ins = new stdClass();
    $code="";
    $record=$DB->get_record('user',array('username'=>$username));
    $record_data=$DB->get_record('random_code',array('user'=>$record->id));
    if(empty($record_data)&&!empty($record)){
        $ins->user=$record->id;
        $ins->code=random_string(10);
        $code= $ins->code;
        $ins->id = $DB->insert_record('random_code', $ins);
    }
    elseif(!empty($record_data)&&!empty($record)){
        $ins->id=$record_data->id;
        $ins->user=$record->id;
        $ins->code=random_string(10);
        $code= $ins->code;
        $ins->id = $DB->update_record('random_code', $ins);
    }
    return $code;

}
 function check_mail($email,$username){
    global $DB,$OUTPUT,$CFG;
    // $code=generate_random_code($username);
$phpmailer = new PHPMailer();
$phpmailer->SMTPOptions = array(
    'ssl' => array(
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    )
);
$phpmailer->isSMTP();
$phpmailer->Host = 'smtp.nitg-eg.com';
$phpmailer->SMTPAuth = true; 
$phpmailer->Port = 26;
$phpmailer->SMTPSecure = 'tls';
$phpmailer->Username = 'noreply@nitg-eg.com';
$phpmailer->Password = 'N.IT@202106';
 $phpmailer->setFrom('noreply@nitg-eg.com', 'Success Academy');
$phpmailer->addAddress($email, $username);
$phpmailer->Subject = 'Reset Password';
$message='You should go to these link to change password <a href= '. $CFG->wwwroot.'/json/confirm.php>Confirm Password</a>';
$phpmailer->Body=$message;
$phpmailer->IsHTML(true); 
  if(!$phpmailer->send()){
    echo "Mailer Error: " . $phpmailer->ErrorInfo;
}else{
    // echo "Message sent!";
}
  
}
echo '<div class="container"><form action="forget_password.php" method="post">
<div class="form-group">
<label for="username">Username</label>
<input type="text"name="username" class="form-control" id="username" placeholder="Enter your username">
</div>


<button type="submit" class="btn btn-primary">Submit</button>
</form>

</div>

';
if (isset($_POST["username"])) {
    $uname=$_POST["username"];
    $user="";
        $user=$DB->get_record('user',array("username"=>$uname));
    if(!empty($user)){
        check_mail($user->email,$user->username);
        echo json_encode(["message"=>"Sent"]);
    }
    else{
        echo "<div class='alert alert-danger'>username is not correct </div>";
    }
  }
echo $OUTPUT->footer();
