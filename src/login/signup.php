<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * user signup page.
 *
 * @package    core
 * @subpackage auth
 * @copyright  1999 onwards Martin Dougiamas  http://dougiamas.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('check.php');
require_once($CFG->dirroot . '/user/editlib.php');
require_once($CFG->libdir . '/authlib.php');
require_once('lib.php');



if (!$authplugin = signup_is_enabled()) {
    print_error('notlocalisederrormessage', 'error', '', 'Sorry, you may not use this page.');
}

$PAGE->set_url('/login/signup.php');
$PAGE->set_context(context_system::instance());

// If wantsurl is empty or /login/signup.php, override wanted URL.
// We do not want to end up here again if user clicks "Login".
if (empty($SESSION->wantsurl)) {
    $SESSION->wantsurl = $CFG->wwwroot . '/';
} else {
    $wantsurl = new moodle_url($SESSION->wantsurl);
    if ($PAGE->url->compare($wantsurl, URL_MATCH_BASE)) {
        $SESSION->wantsurl = $CFG->wwwroot . '/';
    }
}

if (isloggedin() and !isguestuser()) {
    // Prevent signing up when already logged in.
    echo $OUTPUT->header();
    echo $OUTPUT->box_start();
    $logout = new single_button(new moodle_url(
        '/login/logout.php',
        array('sesskey' => sesskey(), 'loginpage' => 1)
    ), get_string('logout'), 'post');
    $continue = new single_button(new moodle_url('/'), get_string('cancel'), 'get');
    echo $OUTPUT->confirm(get_string('cannotsignup', 'error', fullname($USER)), $logout, $continue);
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
    exit;
}

// If verification of age and location (digital minor check) is enabled.
if (\core_auth\digital_consent::is_age_digital_consent_verification_enabled()) {
    $cache = cache::make('core', 'presignup');
    $isminor = $cache->get('isminor');
    if ($isminor === false) {
        // The verification of age and location (minor) has not been done.
        redirect(new moodle_url('/login/verify_age_location.php'));
    } else if ($isminor === 'yes') {
        // The user that attempts to sign up is a digital minor.
        redirect(new moodle_url('/login/digital_minor.php'));
    }
}

// Plugins can create pre sign up requests.
// Can be used to force additional actions before sign up such as acceptance of policies, validations, etc.
core_login_pre_signup_requests();

$mform_signup = $authplugin->signup_form();

if ($mform_signup->is_cancelled()) {
    redirect(get_login_url());
} else if ($user = $mform_signup->get_data()) {
    // Add missing required fields.
    $user = signup_setup_new_user($user);

    // Plugins can perform post sign up actions once data has been validated.
    core_login_post_signup_requests($user);

    $authplugin->user_signup($user, true); // prints notice and link to login/index.php
    exit; //never reached
}


$newaccount = get_string('newaccount');
$login      = get_string('login');

$PAGE->navbar->add($login);
$PAGE->navbar->add($newaccount);

$PAGE->set_pagelayout('login');
$PAGE->set_title($newaccount);
$PAGE->set_heading($SITE->fullname);

echo $OUTPUT->header();

// if ($mform_signup instanceof renderable) {
//     // Try and use the renderer from the auth plugin if it exists.
//     try {
//         $renderer = $PAGE->get_renderer('auth_' . $authplugin->authtype);
//     } catch (coding_exception $ce) {
//         // Fall back on the general renderer.
//         $renderer = $OUTPUT;
//     }
//     echo $renderer->render($mform_signup);
// } else {
//     // Fall back for auth plugins not using renderables.
//     $mform_signup->display();
// }
// var_dump( $CFG->userId); 
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/jquery.validate.min.js" integrity="sha512-rstIgDs0xPgmG6RX1Aba4KV5cWJbAMcvRCVmglpam9SoHZiUCyQVDdH2LPlxoHtrv17XWblE/V/PP+Tr04hbtA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<style>
    :root { --brand:#B21F61; --brand-dark:#8f184e; }

    body { background: linear-gradient(135deg,#fdf1f6 0%, #f4f5fb 100%); }

    #content {
        padding: 34px 32px;
        background-color: #ffffff;
        width: 100%;
        max-width: 580px;
        margin: 34px auto;
        border-radius: 20px;
        box-shadow: 0 18px 50px rgba(178,31,97,.14);
    }

    #content h3 {
        color: var(--brand);
        font-weight: 700;
        margin-bottom: 6px;
    }

    .center {
        display: flex;
        justify-content: center;
        align-items: center;
    }

    #content label {
        font-weight: 600;
        color: #444;
        font-size: 14px;
        margin-bottom: 4px;
    }

    /* required marker inside labels */
    #content label span {
        color: var(--brand);
        font-size: 12px;
        font-weight: 500;
    }

    #content .form-control {
        border-radius: 12px;
        height: 46px;
        border: 1px solid #e7e7ef;
        padding: 0 14px;
        transition: border-color .15s, box-shadow .15s;
    }
    #content .form-control:focus {
        border-color: var(--brand);
        box-shadow: 0 0 0 3px rgba(178,31,97,.15);
    }

    #passEye, #confPassEye { cursor: pointer; color: var(--brand); }

    /* Sign-up submit button */
    .btn-warning {
        background: var(--brand) !important;
        border-color: var(--brand) !important;
        color: #fff !important;
        border-radius: 90px;
        font-weight: 600;
        font-size: 17px;
        padding: 11px 44px;
        margin-top: 10px;
    }
    .btn-warning:hover {
        background: var(--brand-dark) !important;
        border-color: var(--brand-dark) !important;
    }

    button { color: white !important; }

    input { margin-bottom: 8px; }

    #passwordContainrer {
        justify-content: center;
        align-items: center;
    }

    /* "Already have an account? Login" link */
    #login-link {
        margin-top: 18px;
        font-size: 15px;
        color: #666;
    }
    #login-link a {
        color: var(--brand);
        font-weight: 700;
        margin-right: 6px;
    }
    #login-link a:hover { text-decoration: underline; }
</style>
<!-- <div class="container-fluid"> -->
<div id="content" class="mt-2">
    <div class="text-center">
        <h3><?php echo get_string('create_new_acc', 'theme_edumy'); ?></h3>
    </div>
    <!-- <span class='arrow alert alert-danger'></span> -->
    <form action="signup.php" name="registration" method="post">
        <input type="text" hidden value="<?php echo $_GET['id'];?>" name='id'>
        <div class="row">
            <div class="form-group col-6">
                <label for=""><?php echo get_string('FirstName', 'theme_edumy'); ?></label>
                <input type="text" class="form-control" id="fname" placeholder="<?php echo get_string('FirstName', 'theme_edumy'); ?>" name="fname" required value="<?php if (isset($_POST['fname'])) {
                                                                                                                                                                        echo $_POST['fname'];
                                                                                                                                                                    } ?>">
            </div>
            <div class="form-group col-6">
                <label for=""><?php echo get_string('SecondName', 'theme_edumy'); ?></label>
                <input type="text" class="form-control" id="lname" placeholder="<?php echo get_string('SecondName', 'theme_edumy'); ?>" name="lname" required value="<?php if (isset($_POST['lname'])) {
                                                                                                                                                                            echo $_POST['lname'];
                                                                                                                                                                        } ?>">
            </div>
        </div>
        <div class="form-group">
            <label for=""><?php echo get_string('Email', 'theme_edumy'); ?></label>
            <input type="email" class="form-control" id="email" placeholder="<?php echo get_string('Email', 'theme_edumy'); ?>" name="email" required value="<?php if (isset($_POST['email'])) {
                                                                                                                                                                    echo $_POST['email'];
                                                                                                                                                                } ?>">
        </div>
        <div class="form-group">
            <label for=""><?php echo get_string('Password', 'theme_edumy'); ?></label>
            <div class="row " id="passwordContainrer">
                <div class="col-11">
                    <input type="password" class="form-control " id="password" placeholder="<?php echo get_string('Password', 'theme_edumy'); ?>" name="password" required>
                </div>
                <div class="col-1">
                    <span><i class="fa-solid fa-eye" id=passEye></i></span>
                </div>
            </div>
            <div class="form-group">
                <label for=""><?php echo get_string('ConfirmPassword', 'theme_edumy'); ?></label>
                <div class="row " id="passwordContainrer">
                    <div class="col-11">
                        <input type="password" class="form-control" id="password_confirm" placeholder="<?php echo get_string('ConfirmPassword', 'theme_edumy'); ?>" name="password_confirm" required>
                    </div>
                    <div class="col-1">
                        <span><i class="fa-solid fa-eye" id=confPassEye></i></span>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label for=""><?php echo get_string('Phone', 'theme_edumy'); ?> <span><?php echo get_string('required', 'theme_edumy'); ?></span> </label>
                <input type="number" class="form-control" id="snumber" placeholder="<?php echo get_string('Phone', 'theme_edumy'); ?> <?php echo get_string('required', 'theme_edumy'); ?>" name="snumber" required value="<?php if (isset($_POST['snumber'])) {
                                                                                                                                                                                                                                echo $_POST['snumber'];
                                                                                                                                                                                                                            } ?>">
            </div>
            <div class="form-group">
                <label for=""><?php echo get_string('Phone2', 'theme_edumy'); ?> <span><?php echo get_string('required', 'theme_edumy'); ?></span> </label>
                <input type="number" class="form-control" id="pnumber" placeholder="<?php echo get_string('Phone2', 'theme_edumy'); ?> <?php echo get_string('required', 'theme_edumy'); ?>" name="pnumber" required value="<?php if (isset($_POST['pnumber'])) {
                                                                                                                                                                                                                                echo $_POST['pnumber'];
                                                                                                                                                                                                                            } ?>">
            </div>
            <div class="form-group">
                <label for=""><?php echo get_string('school', 'theme_edumy'); ?> <span><?php echo get_string('required', 'theme_edumy'); ?></span> </label>
                <input type="text" class="form-control" id="school" placeholder="<?php echo get_string('school', 'theme_edumy'); ?> <?php echo get_string('required', 'theme_edumy'); ?>" name="school" required value="<?php if (isset($_POST['school'])) {
                                                                                                                                                                                                                            echo $_POST['school'];
                                                                                                                                                                                                                        } ?>">
            </div>
            <div class="form-group">
                <label for=""><?php echo get_string('center_name', 'theme_edumy'); ?> </label>
                <input type="text" class="form-control" id="center" placeholder="<?php echo get_string('center_name', 'theme_edumy'); ?>" name="center" value="<?php if (isset($_POST['center'])) {
                                                                                                                                                                    echo $_POST['center'];
                                                                                                                                                                } ?>">
            </div>
            <div class="form-group">
                <label for=""><?php echo get_string('government', 'theme_edumy'); ?> <span><?php echo get_string('required', 'theme_edumy'); ?></span> </label>
                <input type="text" class="form-control" id="gov" placeholder="<?php echo get_string('government', 'theme_edumy'); ?><?php echo get_string('required', 'theme_edumy'); ?>" name="gov" required value="<?php if (isset($_POST['gov'])) {
                                                                                                                                                                                                                            echo $_POST['gov'];
                                                                                                                                                                                                                        } ?>">
            </div>
            <div class="form-group">
                <label for=""><?php echo get_string('year', 'theme_edumy'); ?> </label>
                <select class="form-control" id="year" name="year" required>
                    <option value="10"><?php echo get_string('sec1', 'theme_edumy'); ?></option>
                    <option value="11"><?php echo get_string('sec2', 'theme_edumy'); ?></option>
                    <option value="12" selected><?php echo get_string('sec3', 'theme_edumy'); ?></option>
                </select>
            </div>
            <!-- <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" id="terms" value="option1" name="terms">
                <label class="form-check-label" for="terms"><?php echo get_string('agree', 'theme_edumy'); ?> <a href=""><?php echo get_string('terms', 'theme_edumy'); ?></a></label>
            </div> -->
            <div class="row center">
                <button class="btn btn-warning" type="submit"><?php echo get_string('signup', 'theme_edumy'); ?></button>

            </div>
            <div class="row center mt-2" id="login-link">
                <span><?php echo get_string('have_account', 'theme_edumy'); ?></span>
                <a href="<?php echo $CFG->wwwroot; ?>/login/index.php?lang=ar" class="ml-1">
                    <?php echo get_string('login', 'theme_edumy'); ?>
                </a>
            </div>
            <div class='alert alert-danger' id="error"></div>

    </form>
</div>
<!-- </div> -->
<script>
    $(document).ready(function() {
        $("#error").hide();
        $("#passEye").click(function() {
            if ($(this).hasClass('fa-eye')) {
                $("#password").attr('type', "text");
                $(this).removeClass("fa-eye");
                $(this).addClass('fa-eye-slash');
            } else {
                $("#password").attr('type', "password");
                $(this).removeClass("fa-eye-slash");
                $(this).addClass('fa-eye');
            }
        });
        $("#confPassEye").click(function() {
            if ($(this).hasClass('fa-eye')) {
                $("#password_confirm").attr('type', "text");
                $(this).removeClass("fa-eye");
                $(this).addClass('fa-eye-slash');
            } else {
                $("#password_confirm").attr('type', "password");
                $(this).removeClass("fa-eye-slash");
                $(this).addClass('fa-eye');
            }
        });
        $("form[name='registration']").validate({
            wrapper: 'span',
            errorPlacement: function(error, element) {
                error.css({

                });
                // error.addClass("arrow");
                error.addClass("alert alert-danger")
                error.addClass("arrow");
                error.insertAfter(element);
            },
            // Specify validation rules
            rules: {
                // The key name on the left side is the name attribute
                // of an input field. Validation rules are defined
                // on the right side
                fname: "required",
                lname: "required",
                email: {
                    required: true,
                    // Specify that email should be validated
                    // by the built-in "email" rule
                    email: true,
                    // remote: "check.php"

                },
                password: {
                    required: true,
                    minlength: 6
                },
                password_confirm: {
                    minlength: 6,
                    equalTo: "#password"
                },
                snumber: {
                    required: true,
                    minlength: 11,
                    maxlength: 11
                },
                pnumber: {
                    required: true,
                    maxlength: 11,
                    minlength: 11
                },
                school: "required",
                gov: "required",

            },
            // Specify validation error messages
            messages: {
                fname: "<?php echo get_string('enterFname', 'theme_edumy'); ?>",
                lname: "<?php echo get_string('enterLname', 'theme_edumy'); ?>",
                password: {
                    required: "<?php echo get_string('enterPass', 'theme_edumy'); ?>",
                    minlength: "<?php echo get_string('pass6', 'theme_edumy'); ?>"
                },
                password_confirm: {
                    required: "<?php echo get_string('enterPass', 'theme_edumy'); ?>",
                    equalTo: "<?php echo get_string('equalTo', 'theme_edumy'); ?>",
                    minlength: "<?php echo get_string('pass6', 'theme_edumy'); ?>"

                },
                email: {
                    required: "<?php echo get_string('enterEmail', 'theme_edumy'); ?>",
                    // remote: "Email is already in use, please change it."

                },
                snumber: {
                    required: "<?php echo get_string('enterSnumber', 'theme_edumy'); ?>",
                    maxlength: " <?php echo get_string('snumber11', 'theme_edumy'); ?>",
                    minlength: " <?php echo get_string('snumber11', 'theme_edumy'); ?>"

                },
                pnumber: {
                    required: "<?php echo get_string('enterPnumber', 'theme_edumy'); ?>",
                    maxlength: "<?php echo get_string('pnumber11', 'theme_edumy'); ?>",
                    minlength: " <?php echo get_string('snumber11', 'theme_edumy'); ?>"

                },
                school: "<?php echo get_string('enterSchool', 'theme_edumy'); ?>",
                gov: "<?php echo get_string('enterGov', 'theme_edumy'); ?>",

            },
            // Make sure the form is submitted to the destination defined
            // in the "action" attribute of the form when valid
            submitHandler: function(form) {
                checkMail = $("#email").val();
                $.ajax({
                    url: "check.php",
                    method: "post",
                    data: {
                        checkMail: checkMail
                    },
                    success: function(data) {
                        console.log("dd" + checkMail);
                        if (data == "true") {
                            form.submit();
                        } else {
                            $("#error").show();
                            $("#error").text("<?php echo get_string('mail_exists', 'theme_edumy');?>");
                        }
                    }
                });
            }
        })
    });
</script>

<?php

if (isset($_POST['email'])) {
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $phone1 = $_POST['snumber'];
    $phone2 = $_POST['pnumber'];
    $school = $_POST['school'];
    $center = $_POST['center'];
    $year = $_POST['year'];
    $gov = $_POST['gov'];
    if ($_POST['id'] == 14) {
        $result = register($fname, $lname, $email, $email, $password, $phone1, $phone2, 5, $year, $gov, $school, $center);
        // var_dump($result);
        if ($result == "success") {
            $user = $DB->get_record('user', array('username' => $email));
            complete_user_login($user);
            echo "
                    
            <script type='text/javascript'> 
            $( document ).ready(function() {
                window.location.replace('" . $CFG->wwwroot . "/?id=14&lang=ar');       
                 });
        </script>";
        } else {
            echo $result;
        }
    } else {
        echo "<div class='alert alert-danger'>Farid Only</div>";
    }
}
echo $OUTPUT->footer();
