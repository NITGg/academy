<?php
require_once('../../config.php');
require_once('../../lib/moodlelib.php');
// require_once('json.php');
$PAGE->set_context(get_system_context());
 $PAGE->set_pagelayout('site');
$PAGE->set_title("Confirm Password ");
$PAGE->set_heading("Confirm Password");
$PAGE->set_url($CFG->wwwroot.'/json/confirm.php');
echo $OUTPUT->header();
// echo '<div class="container"><form action="confirm.php" method="post">
// <div class="form-group">
// <input type="text"name="confirm" class="form-control" id="confirm" placeholder="Enter your confirmation code">
// </div>
// <button type="submit" class="btn btn-primary">Confirm</button>
// </form></div>';
echo '<div class="container"><form action="confirm.php" method="post">
<div class="form-group">
<label for="username">'.get_string('Email', 'theme_edumy').'</label>
<input type="text"name="username" class="form-control" id="username" placeholder="'.get_string('Email', 'theme_edumy').'">
</div>
<div class="form-group">
<label for="password">'.get_string('Password', 'theme_edumy').'</label>
<div style="display:flex">
<input type="password"name="password" class="form-control" id="password" placeholder="'.get_string('Password', 'theme_edumy').'">
<i class="bi bi-eye-slash" id="togglePassword"></i>
</div>
</div>
<div class="form-group">
<label for="reset_password">'.get_string('ConfirmPassword', 'theme_edumy').'</label>
<div style="display:flex">
<input type="password"name="reset_password" class="form-control" id="reset_password" placeholder="'.get_string('ConfirmPassword', 'theme_edumy').'">
<i class="bi bi-eye-slash" id="togglePassword"></i>
</div>
<span id="message"></span>
</div>
<button type="submit" class="btn btn-primary">'.get_string('save', 'theme_edumy').'</button>
</form>
<style>
 i {
    font-size:20px;
    cursor: pointer;
    padding-left:2px;
}
</style>
<script>
const togglePassword = document.querySelector("#togglePassword");
const password = document.querySelector("#password");
const reset_password = document.querySelector("#reset_password");
togglePassword.addEventListener("click", function (e) {
    // toggle the type attribute
    const type = password.getAttribute("type") === "password" ? "text" : "password";
    password.setAttribute("type", type);
    // toggle the eye / eye slash icon
    this.classList.toggle("bi-eye");
});
</script>
<script>
$( document ).ready(function() {
    $("#password, #reset_password").on("keyup", function () {
        if ($("#password").val() == $("#reset_password").val()) {
          $("#message").html('.get_string('matching', 'theme_edumy').').css("color", "green");
        } else 
          $("#message").html("'.get_string('not_matching', 'theme_edumy').'").css("color", "red");
      });

});
</script>
</div>

';
if(isset($_POST['password'])){
    $uname=$_POST["username"];
    $upass=$_POST['password'];
    $check = strpos($uname, '@');
    if ($check == true) {
        $user = $DB->get_record('user', array('email' => $uname));
        $email=$user->username;
    } else {
        $user = $DB->get_record('user', array('username' => $uname));
    }
    // $record=$DB->get_record('random_code',array('code'=>$code));
    // if(!empty($record)){
    //     redirect($CFG->wwwroot);
    // }
    // else{
    //     echo "<div class='alert alert-danger'>wrong Code</div>";
    // }
    $user=$DB->get_record('user',array("username"=>$email));
    if(!empty($user)&&!empty($upass)){
        $password=hash_internal_user_password($upass);
        $userupdate = new stdClass();
        $userupdate->id = $user->id;
        $userupdate->password=$password;
        $userupdate->id = $DB->update_record('user', $userupdate);
        echo "<div class='alert alert-success'>".get_string('newpass', 'theme_edumy')."</div>";
    }
    else{
        echo "<div class='alert alert-danger'>".get_string('check_pass', 'theme_edumy')."</div>";
    }
}
echo $OUTPUT->footer();