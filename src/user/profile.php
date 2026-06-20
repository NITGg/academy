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
 * Public Profile -- a user's public profile page
 *
 * - each user can currently have their own page (cloned from system and then customised)
 * - users can add any blocks they want
 * - the administrators can define a default site public profile for users who have
 *   not created their own public profile
 *
 * This script implements the user's view of the public profile, and allows editing
 * of the public profile.
 *
 * @package    core_user
 * @copyright  2010 Remote-Learner.net
 * @author     Hubert Chathi <hubert@remote-learner.net>
 * @author     Olav Jordan <olav.jordan@remote-learner.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/my/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/filelib.php');

$userid         = optional_param('id', 0, PARAM_INT);
$edit           = optional_param('edit', null, PARAM_BOOL);    // Turn editing on and off.
$reset          = optional_param('reset', null, PARAM_BOOL);

$PAGE->set_url('/user/profile.php', array('id' => $userid));


require '../vimeo/vendor/autoload.php';

use Vimeo\Vimeo;

if (!empty($CFG->forceloginforprofiles)) {
    require_login();
    if (isguestuser()) {
        $PAGE->set_context(context_system::instance());
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('guestcantaccessprofiles', 'error'),
            get_login_url(),
            $CFG->wwwroot
        );
        echo $OUTPUT->footer();
        die;
    }
} else if (!empty($CFG->forcelogin)) {
    require_login();
}

$userid = $userid ? $userid : $USER->id;       // Owner of the page.
if ((!$user = $DB->get_record('user', array('id' => $userid))) || ($user->deleted)) {
    $PAGE->set_context(context_system::instance());
    echo $OUTPUT->header();
    if (!$user) {
        echo $OUTPUT->notification(get_string('invaliduser', 'error'));
    } else {
        echo $OUTPUT->notification(get_string('userdeleted'));
    }
    echo $OUTPUT->footer();
    die;
}

$currentuser = ($user->id == $USER->id);
$context = $usercontext = context_user::instance($userid, MUST_EXIST);

if (!user_can_view_profile($user, null, $context)) {

    // Course managers can be browsed at site level. If not forceloginforprofiles, allow access (bug #4366).
    $struser = get_string('user');
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title("$SITE->shortname: $struser");  // Do not leak the name.
    $PAGE->set_heading($struser);
    $PAGE->set_pagelayout('mypublic');
    $PAGE->set_url('/user/profile.php', array('id' => $userid));
    $PAGE->navbar->add($struser);
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('usernotavailable', 'error'));
    echo $OUTPUT->footer();
    exit;
}

// Get the profile page.  Should always return something unless the database is broken.
if (!$currentpage = my_get_page($userid, MY_PAGE_PUBLIC)) {
    print_error('mymoodlesetup');
}

$PAGE->set_context($context);
$PAGE->set_pagelayout('mypublic');
$PAGE->set_pagetype('user-profile');

// Set up block editing capabilities.
if (isguestuser()) {     // Guests can never edit their profile.
    $USER->editing = $edit = 0;  // Just in case.
    $PAGE->set_blocks_editing_capability('moodle/my:configsyspages');  // unlikely :).
} else {
    if ($currentuser) {
        $PAGE->set_blocks_editing_capability('moodle/user:manageownblocks');
    } else {
        $PAGE->set_blocks_editing_capability('moodle/user:manageblocks');
    }
}

// Start setting up the page.
$strpublicprofile = get_string('publicprofile');

$PAGE->blocks->add_region('content');
$PAGE->set_subpage($currentpage->id);
$PAGE->set_title(fullname($user) . ": $strpublicprofile");
$PAGE->set_heading(fullname($user));

if (!$currentuser) {
    $PAGE->navigation->extend_for_user($user);
    if ($node = $PAGE->settingsnav->get('userviewingsettings' . $user->id)) {
        $node->forceopen = true;
    }
} else if ($node = $PAGE->settingsnav->get('dashboard', navigation_node::TYPE_CONTAINER)) {
    $node->forceopen = true;
}
if ($node = $PAGE->settingsnav->get('root')) {
    $node->forceopen = false;
}


// Toggle the editing state and switches.
if ($PAGE->user_allowed_editing()) {
    if ($reset !== null) {
        if (!is_null($userid)) {
            if (!$currentpage = my_reset_page($userid, MY_PAGE_PUBLIC, 'user-profile')) {
                print_error('reseterror', 'my');
            }
            redirect(new moodle_url('/user/profile.php', array('id' => $userid)));
        }
    } else if ($edit !== null) {             // Editing state was specified.
        $USER->editing = $edit;       // Change editing state.
    } else {                          // Editing state is in session.
        if ($currentpage->userid) {   // It's a page we can edit, so load from session.
            if (!empty($USER->editing)) {
                $edit = 1;
            } else {
                $edit = 0;
            }
        } else {
            // For the page to display properly with the user context header the page blocks need to
            // be copied over to the user context.
            if (!$currentpage = my_copy_page($userid, MY_PAGE_PUBLIC, 'user-profile')) {
                print_error('mymoodlesetup');
            }
            $PAGE->set_context($usercontext);
            $PAGE->set_subpage($currentpage->id);
            // It's a system page and they are not allowed to edit system pages.
            $USER->editing = $edit = 0;          // Disable editing completely, just to be safe.
        }
    }

    // Add button for editing page.
    $params = array('edit' => !$edit, 'id' => $userid);

    $resetbutton = '';
    $resetstring = get_string('resetpage', 'my');
    $reseturl = new moodle_url("$CFG->wwwroot/user/profile.php", array('edit' => 1, 'reset' => 1, 'id' => $userid));

    if (!$currentpage->userid) {
        // Viewing a system page -- let the user customise it.
        $editstring = get_string('updatemymoodleon');
        $params['edit'] = 1;
    } else if (empty($edit)) {
        $editstring = get_string('updatemymoodleon');
        $resetbutton = $OUTPUT->single_button($reseturl, $resetstring);
    } else {
        $editstring = get_string('updatemymoodleoff');
        $resetbutton = $OUTPUT->single_button($reseturl, $resetstring);
    }

    $url = new moodle_url("$CFG->wwwroot/user/profile.php", $params);
    $button = $OUTPUT->single_button($url, $editstring);
    $PAGE->set_button($resetbutton . $button);
} else {
    $USER->editing = $edit = 0;
}

// Trigger a user profile viewed event.
profile_view($user, $usercontext);

// TODO WORK OUT WHERE THE NAV BAR IS!
echo $OUTPUT->header();

/* user data */
$ccnUserHandler = new ccnUserHandler();
$ccnUser = $ccnUserHandler->ccnGetUserDetails($userid);

//print_object($ccnUser);

// custom field data
$Profession = $DB->get_record('user_info_field', array('shortname' => 'profession1'));
$Profession_data = $DB->get_field('user_info_data', 'data', array('userid' => $userid, 'fieldid' => $Profession->id));

echo '
<style>
#region-main .row .col-xl-12 .row:nth-child(1) {display: none;}

.our-dashbord {
    background-image: url(../../services_imgs/background.png);
    margin-top: 0px;
    position: relative;
}
#region-main {
    border: 0!important;
    margin-left: -36px;
    margin-right: -36px;
    background: none!important;
    padding: 0!important;
    display: block!important;
    overflow: visible!important;
}
/* Extra small devices (phones, 600px and down) */
@media only screen and (max-width: 600px) {
    #region-main {
        width: 118%!important;
    }
    .profile_user_img img {
        margin-top: 150px;
        width: 200px;
    }
    .image-gallery {
        justify-content: center;
    }
    .card_box_centers {
        justify-content: center;
    }
}

/* Small devices (portrait tablets and large phones, 600px and up) */
@media only screen and (min-width: 600px) {
    #region-main {
        width: 115%!important;
    }
}

/* Medium devices (landscape tablets, 768px and up) */
@media only screen and (min-width: 768px) {
    #region-main {
        width: 110%!important;
    }
}

@media only screen and (min-width: 1000px) {
    #region-main {
        width: 105%!important;
    }
}

.profile_cover {
  display: flex;
  justify-content: center;
  width: 100%;
  height: 800px;
  background-image: url(../../services_imgs/profile_cover.webp);
  background-repeat: no-repeat;
  background-attachment: fixed;  
  background-size: cover;
  background-blend-mode: lighten;
}

.profile_user_img {
    margin-top: 18%;
    text-align: center;
}
.profile_user_img img {
    width: 200px;
    border-radius: 50%;
    border: 5px solid #fff;
    box-shadow: rgba(0, 0, 0, 0.2) 0px 12px 28px 0px, rgba(0, 0, 0, 0.1) 0px 2px 4px 0px, rgba(255, 255, 255, 0.05) 0px 0px 0px 1px inset;
}
.user_fname {
    color: #fff;
}
#contact_box {
    border: 2px solid #B31F61;
    padding: 15px 20px;
    color: #0E2647;
    font-size: 18px;
}
.w3_black {
    color: #B21F61!important;
    background-color: #B21F61!important;
}
#button1 {
    color: white;
    font-size: 22px;
    padding: 10px;
    font-weight: bold;
    cursor: pointer;
}
#button1:hover {
    color: #0E2647!important;
}
/* Add this to your stylesheet */
.image-gallery {
    display: grid;
    grid-template-columns: repeat(4, 1fr); /* Display 4 images per line by default */
    gap: 15px;
}

/* Media query for smaller screens */
@media (max-width: 768px) {
    .image-gallery {
        grid-template-columns: repeat(2, 1fr); /* Display 2 images per line for smaller screens */
    }
}

/* Media query for even smaller screens */
@media (max-width: 480px) {
    .image-gallery {
        grid-template-columns: 1fr; /* Display 1 image per line for the smallest screens */
    }
}

.gallery-img {
    width: 100%;
    height: 200px; /* Set the desired height for all images */
    object-fit: cover; /* Maintain aspect ratio while filling the container */
}

#center_input {
    border: none;
    outline: none;
    width: 100%;
}
#center_input:focus {
    background: #0E2647;
    color: white;
}
#phone_box {
    display: flex;
    gap: 10px;
}
#center_btn {
    background: linear-gradient(180deg, #0E2647 17.99%, rgba(178, 31, 96, 0.67) 100%);
    padding: 10px 15px;
    color: white;
    border-radius: 5px;
}
#center_btn:hover {
    font-weight: bold;
}
#add_card {
    font-size: 40px;
    color: #B21F61;
    cursor: pointer;
    width: 18rem;
    display: flex;
    justify-content: center;
    border: 4px solid #B21F61;
    border-radius: 5px;
}
#center_card {
    width: 18rem;
    display: none;
}
.card_box_centers {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    justify-content: normal;
}
#center_card_loop {
    width: 18rem;
    box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
}
/* Extra small devices (phones, 600px and down) */
@media only screen and (max-width: 600px) {
    .card_box_centers {
        justify-content: center;
    }
    #add_card {
        width: 100%;
    }
    #center_card_loop {
        width: 100%;
    }
    #center_card {
        width: 100%;
    }
}

/* Initially hide the form */
.delete-image-form {
    display: none;
}

/* Style the delete button */
#delete_image_btn {
    background-color: #f4eeee;
    border: none;
    font-size: 16px;
    color: red;
    cursor: pointer;
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 1;
    transition: transform 0.2s;
    border-radius: 5px;
    padding: 10px 20px;
}

#delete_image_btn:hover {
    transform: scale(1.2);
}

/* Show the form when hovering over the card */
.card:hover .delete-image-form {
    display: block;
}

.save_edit {
    display: none;
}
.videos_container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    padding: 20px;
}
.progress-bar {
    width: 100%;
    height: 20px;
    background-color: #f2f2f2;
    border-radius: 5px;
    margin-top: 10px;
    overflow: hidden;
}

.progress {
    height: 100%;
    width: 0;
    background-color: #4caf50;
    text-align: center;
    line-height: 20px;
    color: white;
    transition: width 0.3s ease-in-out;
}
.w3-container {
    background-color: #0E2647;
    padding: 20px;
}
.w3-container h2 {
    color: white;
}
#zoom_icon {
    position: absolute;
    top: 30%;
    right: 40%;
    font-size: 50px;
    color: white;
    opacity: 0.5;
    cursor: pointer;
}
#zoom_icon:hover {
    scale: 1.1;
    opacity: 0.8;
}
#about_section {
    color: #0E2647;
}
#about_section p {
    font-size: 18px;
}
</style>
<div class="profile_cover">
<div class="profile_user_img">
' . $ccnUser->printAvatar . '
<br>
<h1 class="user_fname">' . $ccnUser->firstname . '</h1>
<h3 class="user_fname">' . $Profession_data . '</h3>
</div>
</div>
';

//check if user is a teacher ANYWHERE in Moodle
$teacherRole = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'));
$isTeacher = $DB->record_exists('role_assignments', ['userid' => $userid, 'roleid' => $teacherRole]);
$teachingCourses = $DB->get_records('role_assignments', ['userid' => $userid, 'roleid' => $teacherRole]);
$teachingCoursesCount = count($teachingCourses);

// Load user profile fields and data for the given user
$fields = profile_get_custom_fields($userid);

foreach ($fields as $field) {
    //echo "Field Name: {$field->name}<br>";

    // custom field data
    $customField = $DB->get_record('user_info_field', array('shortname' => $field->shortname));
    $customField_data = $DB->get_field('user_info_data', 'data', array('userid' => $userid, 'fieldid' => $customField->id));

    //echo "Field Data: {$customField_data}<br>";
}

/* get_string("courseAddCode", "theme_edumy") */


/* if ($lang == 'ar') {

    echo '<style>
    #titel {
        text-align: center;
    }
    #contact_box {
        text-align: left;
    }
    #box2 {
        justify-content: flex-start;
    }

    </style>';
} */


$firstname_lable = get_string("FirstName", "theme_edumy");
$lastname_lable = get_string("LastName", "theme_edumy");
$email_lable = get_string("Email", "theme_edumy");
$phone1_lable = get_string("phone", "theme_edumy");
$tab_lable = '';
$country_lable = get_string("country_pr", "theme_edumy");
$year_lable = get_string("year", "theme_edumy");
$school_lable = get_string("school", "theme_edumy");
$center_lable = get_string("center_name", "theme_edumy");
$parent_phone_lable = get_string("Phone2", "theme_edumy");

if ($year_lable == 'Year') {

    $Images_var = 'Images';
    $Videos_var = 'Videos';
    $Centers_var = 'Centers';
    $Courses_var = 'Courses';

    $address_lable = 'Address';
    $phone2_lable = 'Mobile Numper';
    $department_lable = 'Department';
    $institution_lable = 'Institution';
    $parent_name_lable = 'Your Parent Name';
    $var = 'About';
    $slog_name = 'Name';

} elseif ($year_lable == 'السنه') {

    $Images_var = 'الصور';
    $Videos_var = 'الفيديوهات';
    $Centers_var = 'السناتر';
    $Courses_var = 'الكورسات';
    
    $address_lable = 'العنوان بالتفصيل';
    $phone2_lable = 'رقم الهاتف';
    $department_lable = 'القسم';
    $institution_lable = 'المؤسسة';
    $parent_name_lable = 'اسم ولي الامر';
    $var = 'نبذة عن';
    $slog_name = 'الأسم';
}



if ($isTeacher) {


    // custom field data
    $facebook = $DB->get_record('user_info_field', array('shortname' => 'facebook'));
    $youtube = $DB->get_record('user_info_field', array('shortname' => 'youtube'));
    $whatsapp = $DB->get_record('user_info_field', array('shortname' => 'whatsapp'));

    $facebook_data = $DB->get_field('user_info_data', 'data', array('userid' => $userid, 'fieldid' => $facebook->id));
    $youtube_data = $DB->get_field('user_info_data', 'data', array('userid' => $userid, 'fieldid' => $youtube->id));
    $whatsapp_data = $DB->get_field('user_info_data', 'data', array('userid' => $userid, 'fieldid' => $whatsapp->id));


    echo '
    <div class="row" style="padding: 40px;">
        <div class="col-lg-6">
            <div class="h-100 d-flex flex-column justify-content-center pb-5" id="about_section">
                <h1 class="mb-4" id="titel" style="text-align: center;">' . $var . '</h1>
                 ' . $ccnUser->description . '
            
            <div class="d-flex flex-column mt-5" id="contact_box">
            <span><strong>'.$slog_name.':</strong> ' . $ccnUser->fullname . '</span>
            <span><strong>'.$phone2_lable.':</strong> ' . $ccnUser->phone1 . '</span>
            <span><strong>'.$phone1_lable.':</strong> ' . $ccnUser->phone2 . '</span>
            <div class="d-flex flex-row" id="box2" style="
            justify-content: flex-end;
            margin-top: 15px;gap: 15px;">
            
            <a href="' . $facebook_data . '">
            <div class="facebook">
              <svg xmlns="http://www.w3.org/2000/svg" width="35" height="35" viewBox="0 0 35 35" fill="none">
              <path d="M17.5 35C27.165 35 35 27.165 35 17.5C35 7.83502 27.165 0 17.5 0C7.83502 0 0 7.83502 0 17.5C0 27.165 7.83502 35 17.5 35Z" fill="#3C5A99"/>
              <path d="M18.8604 26.25V18.2793H21.5469L21.9502 15.1587H18.8604V13.1729C18.8604 12.2705 19.1099 11.6587 20.4019 11.6587H22.0391V8.87647C21.7554 8.83887 20.7778 8.75342 19.6396 8.75342C17.2642 8.75342 15.6372 10.2026 15.6372 12.8652V15.1621H12.9609V18.2827H15.6372V26.25H18.8604Z" fill="white"/>
              </svg>
            </div>
            </a>
            <a href="' . $youtube_data . '">
            <div class="youtube">
              <svg xmlns="http://www.w3.org/2000/svg" width="35" height="35" viewBox="0 0 35 35" fill="none">
              <path d="M17.5 35C27.165 35 35 27.165 35 17.5C35 7.83502 27.165 0 17.5 0C7.83502 0 0 7.83502 0 17.5C0 27.165 7.83502 35 17.5 35Z" fill="#E43535"/>
              <path fill-rule="evenodd" clip-rule="evenodd" d="M23.1617 23.3338H12.4216C11.0438 23.3338 9.91663 22.1822 9.91663 20.7747V14.8096C9.91663 13.4021 11.0438 12.2505 12.4216 12.2505H23.1617C24.5394 12.2505 25.6666 13.4021 25.6666 14.8096V20.7747C25.6666 22.1822 24.5394 23.3338 23.1617 23.3338ZM16.3614 15.6487V20.3822L20.6499 17.9383L16.3614 15.6487Z" fill="white"/>
              </svg>
            </div>
            </a>
            <a href="' . $whatsapp_data . '">
            <div class="watsapp">
              <svg xmlns="http://www.w3.org/2000/svg" width="35" height="35" viewBox="0 0 35 35" fill="none">
              <path d="M35 17.5C35 7.83502 27.165 0 17.5 0C7.83502 0 0 7.83502 0 17.5C0 27.165 7.83502 35 17.5 35C27.165 35 35 27.165 35 17.5Z" fill="#00D95F"/>
              <path d="M8.75 25.5991L10.0056 20.9305C8.993 19.1118 8.67129 16.9896 9.0995 14.9533C9.5277 12.917 10.6771 11.1031 12.3368 9.84451C13.9965 8.58597 16.0551 7.96713 18.1349 8.10157C20.2148 8.23601 22.1763 9.11474 23.6595 10.5765C25.1428 12.0382 26.0482 13.9849 26.2098 16.0594C26.3714 18.1339 25.7782 20.1969 24.5391 21.87C23.3001 23.5431 21.4982 24.714 19.4642 25.1678C17.4302 25.6216 15.3005 25.3279 13.4659 24.3406L8.75 25.5991ZM13.6933 22.5936L13.9849 22.7663C15.3137 23.5526 16.8658 23.8781 18.3993 23.6918C19.9327 23.5056 21.3613 22.8181 22.4626 21.7367C23.5638 20.6552 24.2758 19.2404 24.4875 17.7128C24.6992 16.1851 24.3989 14.6305 23.6332 13.2911C22.8675 11.9517 21.6795 10.9028 20.2543 10.3078C18.8292 9.7129 17.2469 9.60529 15.7541 10.0018C14.2613 10.3984 12.9419 11.2768 12.0013 12.5001C11.0608 13.7235 10.552 15.2231 10.5543 16.7653C10.5531 18.044 10.9073 19.298 11.5775 20.3876L11.7605 20.6887L11.0585 23.2944L13.6933 22.5936Z" fill="white"/>
              <path fill-rule="evenodd" clip-rule="evenodd" d="M20.7728 17.9748C20.6018 17.8371 20.4016 17.7402 20.1875 17.6915C19.9733 17.6427 19.7509 17.6434 19.537 17.6935C19.2157 17.8268 19.0081 18.3301 18.8004 18.5818C18.7567 18.6422 18.6923 18.6845 18.6195 18.7008C18.5467 18.7172 18.4704 18.7064 18.405 18.6706C17.2287 18.2107 16.2427 17.3672 15.6071 16.2771C15.5529 16.2091 15.5272 16.1227 15.5355 16.0362C15.5439 15.9497 15.5855 15.8698 15.6516 15.8132C15.8832 15.5844 16.0532 15.3009 16.1459 14.9891C16.1665 14.6451 16.0876 14.3025 15.9185 14.0021C15.7879 13.5809 15.5391 13.2059 15.2018 12.9213C15.0278 12.8432 14.8348 12.817 14.6463 12.8459C14.4577 12.8748 14.2815 12.9575 14.139 13.0842C13.8915 13.2973 13.6952 13.5631 13.5643 13.862C13.4334 14.161 13.3713 14.4854 13.3827 14.8114C13.3834 14.9945 13.4067 15.1769 13.4519 15.3543C13.5666 15.7806 13.7432 16.188 13.9759 16.5634C14.1437 16.8509 14.3269 17.1294 14.5245 17.3974C15.1669 18.2778 15.9744 19.0253 16.9022 19.5984C17.3678 19.8897 17.8654 20.1265 18.3852 20.3042C18.9252 20.5485 19.5213 20.6423 20.1104 20.5756C20.446 20.5249 20.764 20.3926 21.0364 20.1903C21.3088 19.9881 21.5272 19.7221 21.6725 19.4158C21.7578 19.2308 21.7837 19.024 21.7466 18.8236C21.6577 18.414 21.1089 18.1722 20.7728 17.9748Z" fill="white"/>
              </svg>
            </div>
            </a>
            </div>
            </div>
          </div>
        </div>
        <div class="col-lg-6"  style="min-height: 400px;display: flex;
        justify-content: center;">
            <div class="position-relative h-100"><img src="' . $ccnUser->rawAvatar . '"></div>
        </div>
    </div>
    ';

    require_once($CFG->dirroot . '/theme/edumy/ccn/block_handler/ccn_block_handler.php');
    require_once($CFG->dirroot . '/course/renderer.php');
    require_once($CFG->dirroot . '/theme/edumy/ccn/course_handler/ccn_course_handler.php');
    $userCourses = enrol_get_users_courses($userid);

    $ccnCourseHandler = new ccnCourseHandler(); // Initialize once

    echo '
    <div class="row_2" style="padding: 40px;">
    <div class="w3_black">
      <a id="button1" onclick="opentab(\'Courses\')">' . $Courses_var . '</a>
      <a id="button1" onclick="opentab(\'Centers\')">' . $Centers_var . '</a>
      <a id="button1" onclick="opentab(\'Videos\')">' . $Videos_var . '</a>
      <a id="button1" onclick="opentab(\'Images\')">' . $Images_var . '</a>
    </div>

    <div id="Courses" class="w3-container tab">
      <h2>' . $Courses_var . '</h2>
      <div class="row" id="course_slider">
      ';
    foreach ($userCourses as $course) {
        $ccnCourse = $ccnCourseHandler->ccnGetCourseDetails($course->id);

        echo '
        <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
        <a href="https://academy2022.nitg-eg.com/course/view.php?id=' . $course->id . '">
        <div class="card" style="background: #0F5784;color: white;font-size: 20px;">
        <img class="card-img-top" src="' . $ccnCourse->imageUrl . '" alt="Card image cap">
         <div class="card-body">
           <p class="card-text" style="font-size: 18px;">' . $ccnCourse->fullName . '</p>
         </div>
        </div>
        </a>
        </div>
        ';
        //print_object($ccnCourse);
    }
    echo '
      </div>
    </div>


    <div id="Centers" class="w3-container tab" style="display:none">
      <h2>' . $Centers_var . '</h2>
      <div class="roow">
        <div class="card_box_centers">
        ';
    // Toggle the editing state and switches.
    if ($PAGE->user_allowed_editing()) {
        echo '
            <a id="add_card"><i class="fa fa-plus-circle" style="padding: 50px;" aria-hidden="true"></i></a>
            
            <div class="card" id="center_card" style="box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;background-color: #0E2647;
            color: white;
            border: 4px solid;
            border-color: #B21F61;">
              <div class="card-body">
                <form action="./save_center.php" method="post">
                  <input type="hidden" name="user_id" value="' . $userid . '">
                  <input name="name" id="center_input" placeholder="Name" style="font-size: 20px;
                  font-weight: bold;background-color: #0E2647;
                  color: white;">
                  <br><br>
                  <textarea name="body" id="center_input" placeholder="Body" style="background-color: #0E2647;
                  color: white;
                  box-shadow: none;"></textarea>
                  <br><br>
                  <div class="form-group" id="phone_box">
                    <i class="fa fa-phone" aria-hidden="true"></i>
                    <input name="phone1" id="center_input" placeholder="Phone 1" style="background-color: #0E2647;
                    color: white;">
                  </div>
                  <div class="form-group" id="phone_box">
                    <i class="fa fa-phone" aria-hidden="true"></i>
                    <input name="phone2" id="center_input" placeholder="Phone 2" style="background-color: #0E2647;
                    color: white;">
                  </div>
                  <br>
                  <input type="submit" name="submit" id="center_btn" value="Publiching">
                </form>
              </div>
            </div>';
    }

    $centers = $DB->get_records('teacher_centers', array('user_id' => $userid));
    //echo $images;
    foreach ($centers as $center) {
        echo '
            <div class="card center-card" id="center_card_loop" style="background-color: #0E2647;
            color: white;border: 4px solid;border-color: #B21F61;">
            <div class="card-body">
            ';
        // Toggle the editing state and switches.
        if ($PAGE->user_allowed_editing()) {
            echo '
                    <div style="display:flex;float: right;gap: 10px;">
                        <a class="save_edit" style="display: none;"><i class="fas fa-save"></i></a>
                        <a class="edit_btn"><i class="fas fa-edit"></i></a>
                        <a href="./delete_center.php?user_id=' . $userid . '&id=' . $center->id . '" class="delete_center"><i style="color: white;" class="fa fa-trash"></i></a>
                    </div>
                    ';
        }

        echo '
                    <input type="hidden" name="user_id" value="' . $userid . '">
                    <input type="hidden" name="center_id" value="' . $center->id . '">
                    <h5 class="card-title view-mode" style="font-size: 20px; font-weight: bold;color: white;">' . $center->name . '</h5>
                    <input type="text" name="center_name" class="form-control edit-mode" style="display: none; font-size: 20px; font-weight: bold;border: none;
                    box-shadow: none;" value="' . $center->name . '">
                    <p class="card-text view-mode" style="font-size: 18px;">' . $center->body . '</p>
                    <textarea name="center_body" class="form-control edit-mode" style="display: none; font-size: 18px;border: none;
                    box-shadow: none;">' . $center->body . '</textarea>
                    <br>
                    <p class="card-text view-mode">' . $center->phone1 . '</p>
                    <input name="center_phone1" type="text" class="form-control edit-mode" style="display: none;border: none;
                    box-shadow: none;" value="' . $center->phone1 . '">
                    <p class="card-text view-mode">' . $center->phone2 . '</p>
                    <input name="center_phone2" type="text" class="form-control edit-mode" style="display: none;border: none;
                    box-shadow: none;" value="' . $center->phone2 . '">
                </div>
            </div>
        ';
    }
    echo '
        </div>

       <script>
       document.addEventListener("DOMContentLoaded", function () {
        const cards = document.querySelectorAll(".center-card");
    
        cards.forEach((card) => {
            const editBtn = card.querySelector(".edit_btn");
            const saveEdit = card.querySelector(".save_edit");
            const viewElements = card.querySelectorAll(".view-mode");
            const editElements = card.querySelectorAll(".edit-mode");
    
            // Function to toggle between view and edit modes
            function toggleEditMode(isEditMode) {
                viewElements.forEach((element) => {
                    element.style.display = isEditMode ? "none" : "block";
                });
    
                editElements.forEach((element) => {
                    element.style.display = isEditMode ? "block" : "none";
                });
    
                editBtn.style.display = isEditMode ? "none" : "block";
                saveEdit.style.display = isEditMode ? "block" : "none";
            }
    
            // Add click event listener to the edit button
            editBtn.addEventListener("click", function () {
                toggleEditMode(true);
            });
    
            // Add click event listener to the save edit button
            saveEdit.addEventListener("click", function () {
                const user_id = card.querySelector("input[name=\'user_id\']").value;
                const center_id = card.querySelector("input[name=\'center_id\']").value;
                const nameInput = card.querySelector("input[name=\'center_name\']").value;
                const bodyTextarea = card.querySelector("textarea[name=\'center_body\']").value;
                const phone1Input = card.querySelector("input[name=\'center_phone1\']").value;
                const phone2Input = card.querySelector("input[name=\'center_phone2\']").value;
    
                // Use AJAX to send the updated data to the server for saving
                fetch("./save_edited_center.php", {
                    method: "POST",
                    body: new URLSearchParams({
                        user_id: user_id,
                        center_id: center_id,
                        name: nameInput,
                        body: bodyTextarea,
                        phone1: phone1Input,
                        phone2: phone2Input,
                    }),
                })
                    .then((response) => response.text())
                    .then((data) => {
                        console.log(data); // Log the response from the server
                        // Switch back to view mode and update displayed values
                        toggleEditMode(false);
                        viewElements[0].textContent = nameInput;
                        viewElements[1].textContent = bodyTextarea;
                        viewElements[2].textContent = phone1Input;
                        viewElements[3].textContent = phone2Input;
                    })
                    .catch((error) => {
                        console.error("Error:", error);
                    });
            });
        });
    });    
       </script>

      </div> 

      <script>
        document.getElementById("add_card").addEventListener("click", function(event) {
            event.preventDefault(); 
            var centerCard = document.getElementById("center_card");
            centerCard.style.display = centerCard.style.display === "none" ? "block" : "none";
        });
      </script>
    </div>
    
    <div id="Videos" class="w3-container tab" style="display:none">
      <h2>' . $Videos_var . '</h2>';
?>
    <?php
    // Toggle the editing state and switches.
    if ($PAGE->user_allowed_editing()) { ?>
        <form id="videoUploadForm" action="./upload_video.php" enctype="multipart/form-data" method="post">
            <input type="hidden" name="user_id" value="<?php echo $userid; ?>">
            <input type="file" name="videoFile" accept="video/*">
            <button type="submit">Upload Video</button>
        </form>
        <div class="progress-bar">
            <div class="progress" id="progress"></div>
        </div>
    <?php } ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('videoUploadForm');
            const fileInput = document.querySelector('input[name="videoFile"]');
            const progressBar = document.getElementById('progress');

            form.addEventListener('submit', async e => {
                e.preventDefault();

                const file = fileInput.files[0];
                if (!file) {
                    alert('Please select a video file.');
                    return;
                }

                const formData = new FormData(form);

                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'upload_video.php', true);

                xhr.upload.onprogress = e => {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progressBar.style.width = `${percentComplete}%`;
                        progressBar.innerHTML = `${Math.round(percentComplete)}%`;
                    }
                };

                xhr.onload = () => {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                progressBar.style.backgroundColor = '#4caf50';
                            } else {
                                progressBar.style.backgroundColor = '#f44336';
                            }
                        } catch (error) {
                            progressBar.style.backgroundColor = '#f44336';
                        }
                    } else {
                        progressBar.style.backgroundColor = '#f44336';
                    }
                    progressBar.style.width = '100%';
                    progressBar.innerHTML = '100%';

                    // Refresh the page after upload
                    setTimeout(() => {
                        location.reload();
                    }, 500); // Change the delay as needed

                };

                xhr.onerror = () => {
                    progressBar.style.backgroundColor = '#f44336';
                    progressBar.style.width = '100%';
                    progressBar.innerHTML = '100%';
                };

                xhr.send(formData);
            });
        });
    </script>
<?php
    echo '

      <div class="videos_container">
      ';

    // Fetch video data from the database
    $videoRecords = $DB->get_records('profile_videos', array('user_id' => $userid));
    $client = new Vimeo("4dad588b7f47a44426afc26f398fe2367ea49c92", "IHRxCFjq5qvsKlU6DjWGfNQwtZGHGmK1pByyCYWGrkWnE9F91BbNqPdqXY+dHVyvKjvRWYTu3ba2A8KM1GR2gcqqYiz+jXAx6uLrsEb0jFJrUSMIi3KMIyS+Je+nsN3s", "195c95a4e775fca8d6e70cb8db4aca73");
    foreach ($videoRecords as $record) {

        $uri = "/videos/" . $record->video_url;
        $response = $client->request($uri, [], 'GET');
        $status = $response['body']['transcode']['status'];

        // Display the Vimeo player
        echo '<div class="vimeo-player">';
        // Toggle the editing state and switches.
        if ($PAGE->user_allowed_editing()) {
            echo '
        <a href="./delete_video.php?video_id_to_delete=' . $record->video_url . '&user_id=' . $userid . '" style="color: #B21F61;">
        <i class="fa fa-trash" aria-hidden="true"></i> delete</a>
        ';
        }
        echo '
       <iframe src="' . $response['body']['player_embed_url'] . '" width="640" height="360" frameborder="0" allowfullscreen></iframe>
       </div>';
    }
    echo '
    </div>
    </div>

    <div id="Images" class="w3-container tab" style="display:none">
      <h2>' . $Images_var . '</h2>
      ';
    // Toggle the editing state and switches.
    if ($PAGE->user_allowed_editing()) {
        echo '
       <form action="./upload_image.php" enctype="multipart/form-data" method="post">
         <input type="hidden" name="user_id" value="' . $userid . '">
         <div class="form-group">
           <label for="file">Click to upload image</label>
           <input type="file" id="file" name="file" class="form-control-file">
         </div>
         <input type="submit" value="Upload Image" name="submit" class="btn btn-primary">
       </form>
       ';
    }
    echo '
      <div class="image-gallery">
      ';

    $images = $DB->get_records('gallery_images', array('user_id' => $userid));
    if ($images) {
        //echo $images;
        foreach ($images as $image) {
            echo '
        <div class="card" style="height: 200px;background-color: #ffffff00;">
        ';
            // Toggle the editing state and switches.
            if ($PAGE->user_allowed_editing()) {
                echo '
            <form class="delete-image-form" action="./delete_image.php" enctype="multipart/form-data" method="post">
              <input type="hidden" name="user_id" value="' . $userid . '">
              <input type="hidden" name="image_id" value="' . $image->id . '">
              <input type="submit" value="x" name="submit" id="delete_image_btn" style="color: red;">
            </form>
        ';
            }
            echo '
            <span id="zoom_icon"><i class="fa fa-search-plus" aria-hidden="true"></i></span>
            <img id="myImage" class="card-img-top gallery-img" src="' . $CFG->wwwroot . '/user/uploads/' . $image->image_name . '" alt="image" data-zoomable="true" style="cursor: pointer;">
        </div>
        ';
        }
    } else {
        echo '<h3>No data found!</h3>';
    }
    echo '
    <style>
      #zoomedImg {
          height: 500px;
          width: 60%;
          top: 12%;
      }
      /* The Modal (background) */
      .modal {
          display: none;
          position: fixed;
          z-index: 1;
          left: 0;
          top: 0;
          width: 100%;
          margin-top: 87px;
          height: 100%;
          background-color: rgba(0, 0, 0, 0.9);
          overflow: auto;
      }
      
      /* Extra small devices (phones, 600px and down) */
      @media only screen and (max-width: 600px) {
        .modal {
            margin-top: 0px;
        }
      }
      /* Modal Content */
      .modal-content {
          margin: auto;
          display: block;
          max-width: 80%;
          max-height: 80%;
      }
      
      /* Close Button */
      .close {
          position: absolute;
          top: 15px;
          right: 35px;
          color: #f1f1f1;
          font-size: 40px;
          font-weight: bold;
          transition: 0.3s;
      }
      
      .close:hover,
      .close:focus {
          color: #bbb;
          text-decoration: none;
          cursor: pointer;
      }
      
      /* Responsive Styling */
      @media screen and (max-width: 768px) {
          .modal-content {
              max-width: 100%;
              max-height: 100%;
          }
      }
  
    </style>
      </div>
      <!-- The Modal for Image Zoom -->
      <div id="imageZoomModal" class="modal">
          <span class="close" id="imageZoomClose">&times;</span>
          <img class="modal-content" id="zoomedImg">
      </div>

      <script>
          var modal = document.getElementById(\'imageZoomModal\');
          var zoomedImg = document.getElementById(\'zoomedImg\');
      
          // Loop through all images to attach click event for zooming
          var images = document.querySelectorAll(\'.gallery-img[data-zoomable="true"]\');
          images.forEach(function(image) {
              image.addEventListener(\'click\', function() {
                  modal.style.display = \'block\';
                  zoomedImg.src = this.src;
              });
          });
      
          // Close the modal on close button click
          var imageZoomClose = document.getElementById(\'imageZoomClose\');
          imageZoomClose.addEventListener(\'click\', function() {
              modal.style.display = \'none\';
          });
      </script>


    </div>

    
    <script>
    function opentab(tabName) {
      var i;
      var x = document.getElementsByClassName("tab");
      for (i = 0; i < x.length; i++) {
        x[i].style.display = "none";  
      }
      document.getElementById(tabName).style.display = "block";  
    }
    </script>
    </div>';


    //echo 'you are a teacher';
} else {
    $editurl = new moodle_url("$CFG->wwwroot/user/edit.php", array('id' => $userid));
    echo '<div>
    <div class="container_top" style="display: flex; gap: 15px;padding: 20px 40px;
    font-size: 20px;">
      <a href="'.$editurl.'">
        <i class="fas fa-edit" style="color: #0E2647;"></i>
      </a>
      <a href="#">
        <i class="fa fa-bookmark" style="color: #0E2647;"></i>
      </a>
    </div>
    <style>
      #region-main .row .col-xl-12 .row:nth-child(1) {display: none;}
      
      .grid-container {
          display: grid;
          grid-template-columns: repeat(2, 1fr);
          grid-gap: 45px;
          padding: 40px;
      }
      
      .item_box {
          padding: 10px;
          border-radius: 20px;
          border: 2px solid #0E2647;
          background: #FFF;
          box-shadow: 3px 7px 6px 0px rgba(0, 0, 0, 0.25);
      }
      
      .grid-item lable {
          color: #154372;
          font-family: Bodoni Moda;
          font-size: 20px;
          font-style: normal;
          font-weight: 400;
          line-height: normal;
      }
      #profession16, #whatsapp5, #youtube4, #facebook3 {display: none;}
    </style>
    <div class="grid-container">
    ';

    if ($ccnUser->firstname) {
        echo '
        <div class="grid-item">
        <lable>' . $firstname_lable . '</lable><br>
        <div class="item_box">' . $ccnUser->firstname . '</div>
        </div>
       ';
    }
    if ($ccnUser->lastname) {
        echo '
        <div class="grid-item">
        <lable>' . $lastname_lable . '</lable><br>
        <div class="item_box">' . $ccnUser->lastname . '</div>
        </div>
       ';
    }
    if ($ccnUser->email) {
        echo '
        <div class="grid-item">
        <lable>' . $email_lable . '</lable><br>
       <div class="item_box">' . $ccnUser->email . '</div>
       </div>
       ';
    }
    if ($ccnUser->phone1) {
        echo '
        <div class="grid-item">
        <lable>' . $phone1_lable . '</lable><br>
       <div class="item_box">' . $ccnUser->phone1 . '</div>
       </div>
       ';
    }
    if ($ccnUser->phone2) {
        echo '
        <div class="grid-item">
        <lable>' . $phone2_lable . '</lable><br>
       <div class="item_box">' . $ccnUser->phone2 . '</div>
       </div>
       ';
    }
    if ($ccnUser->department) {
        echo '
        <div class="grid-item">
        <lable>' . $department_lable . '</lable><br>
       <div class="item_box">' . $ccnUser->department . '</div>
       </div>
       ';
    }
    if ($ccnUser->institution) {
        echo '
        <div class="grid-item">
        <lable>' . $institution_lable . '</lable><br>
       <div class="item_box">' . $ccnUser->institution . '</div>
       </div>
       ';
    }
    if ($user->address) {
        echo '
        <div class="grid-item">
        <lable>' . $address_lable . '</lable><br>
       <div class="item_box">' . $user->address . '</div>
       </div>
       ';
    }
    if ($ccnUser->tab) {
        echo '
        <div class="grid-item">
        <lable>' . $tab_lable . '</lable><br>
       <div class="item_box">' . $ccnUser->tab . '</div>
       </div>
       ';
    }
    if ($ccnUser->country) {
        echo '
        <div class="grid-item">
        <lable>' . $country_lable . '</lable><br>
       <div class="item_box">' . $ccnUser->country . '</div>
       </div>
       ';
    }

    /* echo '
       <div class="grid-item">' . $ccnUser->firstname . '</div>
       <div class="grid-item">' . $ccnUser->lastname . '</div>
       <div class="grid-item">' . $ccnUser->email . '</div>
       <div class="grid-item">' . $ccnUser->phone1 . '</div>
       <div class="grid-item">' . $ccnUser->phone2 . '</div>
       <div class="grid-item">' . $ccnUser->department . '</div>
       <div class="grid-item">' . $user->institution . '</div>
       <div class="grid-item">' . $user->address . '</div>
       <div class="grid-item">' . $user->tab . '</div>
       <div class="grid-item">' . $user->country . '</div>
    '; */


    // Load user profile fields and data for the given user
    $fields = profile_get_custom_fields($userid);

    foreach ($fields as $field) {

        // custom field data
        $customField = $DB->get_record('user_info_field', array('shortname' => $field->shortname));
        $customField_data = $DB->get_field('user_info_data', 'data', array('userid' => $userid, 'fieldid' => $customField->id));

        echo '<div class="grid-item" id="' . $field->shortname . $field->id . '">
                <lable>' . $year_lable . '</lable><br>
                <div class="item_box">' . $customField_data . '</div>
             </div>';
    }

    $optional = $DB->get_record('optional_data_aibrahim', array('userid' => $userid));

    if ($optional->school) {
        echo '
        <div class="grid-item">
          <lable>' . $school_lable . '</lable><br>
          <div class="item_box">' . $optional->school . '</div>
        </div>
        ';
    }
    if ($optional->empty) {
        echo '
        <div class="grid-item">
          <lable>' . $center_lable . '</lable><br>
          <div class="item_box">' . $optional->empty . '</div>
        </div>
        ';
    }

    echo '</div>

    </div>';

    $parentId = $DB->get_field('parent_child', 'parentid', array('childid' => $userid));
    $parent = $DB->get_record('user', array('id' => $parentId));

    echo '<div class="grid-container">';

    if ($parent->firstname && $parent->lastname) {
        echo '
        <div class="grid-item">
          <lable>' . $parent_name_lable . '</lable><br>
          <div class="item_box">' . $parent->firstname . ' ' . $parent->lastname . '</div>
        </div>
        ';
    }
    if ($parent->phone1) {
        echo '
        <div class="grid-item">
          <lable>' . $parent_phone_lable . '</lable><br>
          <div class="item_box">' . $parent->phone1 . '</div>
        </div>
        ';
    }


    echo '</div>';



    //echo 'you are a student';
}

echo '<div class="userprofile">';

$hiddenfields = [];
if (!has_capability('moodle/user:viewhiddendetails', $usercontext)) {
    $hiddenfields = array_flip(explode(',', $CFG->hiddenuserfields));
}
if ($user->description && !isset($hiddenfields['description'])) {
    echo '<div class="description">';
    if (
        !empty($CFG->profilesforenrolledusersonly) && !$currentuser &&
        !$DB->record_exists('role_assignments', array('userid' => $user->id))
    ) {
        echo get_string('profilenotshown', 'moodle');
    } else {
        $user->description = file_rewrite_pluginfile_urls(
            $user->description,
            'pluginfile.php',
            $usercontext->id,
            'user',
            'profile',
            null
        );
        echo format_text($user->description, $user->descriptionformat);
    }
    echo '</div>';
}

echo $OUTPUT->custom_block_region('content');


// Render custom blocks.
//$renderer = $PAGE->get_renderer('core_user', 'myprofile');
//$tree = core_user\output\myprofile\manager::build_tree($user, $currentuser);
//echo $renderer->render($tree);

echo '</div>';  // Userprofile class.

echo $OUTPUT->footer();
