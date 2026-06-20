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
<label for="username">Username</label>
<input type="text"name="username" class="form-control" id="username" placeholder="Enter your username">
</div>
<div class="form-group">
<label for="password">New Password</label>
<div style="display:flex">
<input type="password"name="password" class="form-control" id="password" placeholder="Enter new password">
<i class="bi bi-eye-slash" id="togglePassword"></i>
</div>
</div>
<div class="form-group">
<label for="reset_password">Re-enter Password</label>
<div style="display:flex">
<input type="password"name="reset_password" class="form-control" id="reset_password" placeholder="Enter Re-enter password">
<i class="bi bi-eye-slash" id="togglePassword"></i>
</div>
<span id="message"></span>
</div>
<button type="submit" class="btn btn-primary">Submit</button>
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
          $("#message").html("Matching").css("color", "green");
        } else 
          $("#message").html("Not Matching").css("color", "red");
      });

});
</script>
</div>

';
if(isset($_POST['password'])){
    $uname=$_POST["username"];
    $upass=$_POST['password'];
    // $record=$DB->get_record('random_code',array('code'=>$code));
    // if(!empty($record)){
    //     redirect($CFG->wwwroot);
    // }
    // else{
    //     echo "<div class='alert alert-danger'>wrong Code</div>";
    // }
    $user=$DB->get_record('user',array("username"=>$uname));
    if(!empty($user)&&!empty($upass)){
        $password=hash_internal_user_password($upass);
        $userupdate = new stdClass();
        $userupdate->id = $user->id;
        $userupdate->password=$password;
        $userupdate->id = $DB->update_record('user', $userupdate);
        echo "<div class='alert alert-success'>username is not found Or check password</div>";
    }
    else{
        echo "<div class='alert alert-danger'>Check password</div>";
    }
}
echo $OUTPUT->footer();