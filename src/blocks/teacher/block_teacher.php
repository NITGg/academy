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
 * Defines the base class form used by blocks/edit.php to edit block instance configuration.
 *
 * It works with the {@link block_edit_form} class, or rather the particular
 * subclass defined by this block, to do the editing.
 *
 * @package    core_block
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_teacher extends block_base
{

    public function init()
    {
        global $PAGE;

        $currentcss2 = '/blocks/teacher/styles.css';

        $PAGE->requires->css($currentcss2, true);
        $this->title = '';
    }

    // display my courses image
    public function get_course_image($course)
    {
        global $CFG;
        $url = '';
        require_once($CFG->libdir . '/filelib.php');

        $context = context_course::instance($course->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0);

        foreach ($files as $f) {
            if ($f->is_valid_image()) {
                $url = moodle_url::make_pluginfile_url($f->get_contextid(), $f->get_component(), $f->get_filearea(), null, $f->get_filepath(), $f->get_filename(), false);
            }
        }

        return $url;
    }
    function get_all_courses($user, $teacher, $lang = '')
    {
        global $DB, $CFG;
        $coursesData = array();
        $studentRole = $DB->get_field('role', 'id', array('shortname' => 'student'));
        $isStudent = $DB->record_exists('role_assignments', ['userid' => $user, 'roleid' => $studentRole]);
        $teacherRole = $DB->get_field('role', 'id', array('shortname' => 'student'));
        $teacherRole = $DB->record_exists('role_assignments', ['userid' => $user, 'roleid' => $teacherRole]);
        $assisstantRole = $DB->get_field('role', 'id', array('shortname' => 'teacher'));
        $assisstantRole = $DB->record_exists('role_assignments', ['userid' => $user, 'roleid' => $assisstantRole]);
        if ($lang == "ar") {
            $lang = "and c.lang='ar'";
        } elseif ($lang == "en") {
            $lang = "and c.lang='en'";
        }
        $data_courses = $DB->get_records_sql("SELECT  c.id AS courseId,c.summary as summary,c.visible as visible, c.fullname as courseName,c.category as catId,cat.name as catName ,cinfo.value as year
    FROM   mdl_course c
    LEFT OUTER JOIN mdl_customfield_data cinfo ON c.id=cinfo.instanceid

     LEFT OUTER JOIN mdl_course_categories  cat   ON c.category=cat.id 
      LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
    LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
     LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id 
     WHERE cx.contextlevel = '50' AND cinfo.fieldid=1 AND u.id= '$teacher' $lang;");

        // $data_courses = array_values($courses);
        $teacherId = -1;
        $teacherName = "";
        $get_teacher_data = $DB->get_record('user', array('id' => $teacher));
        if ($isStudent || ($teacherRole && $teacher != $user)) {

            // get use custom field data 
            $student = $DB->get_record('user_info_data', array('userid' => $user, 'fieldid' => 1));
            $yearMap = array(1 => "primary 1", 2 => "primary 2", 3 => "primary 3", 4 => "primary 4", 5 => "primary 5", 6 => "primary 6", 7 => "preparatory 1", 8 => "preparatory 2", 9 => "preparatory 3", 10 => "Secondary 1", 11 => "Secondary 2", 12 => "Secondary 3");
            $key = array_search($student->data, $yearMap);
            foreach ($data_courses as $course) {
                // get course custom field data
                if ($key == $course->year) {
                    $price = $DB->get_record('customfield_data', array('fieldid' => 12, 'instanceid' => $course->courseid));
                    if (empty($price)) {
                        $price = 'free';
                    }
                    $courseDescription = $DB->get_record('customfield_data', array('fieldid' => 15, 'instanceid' => $course->courseid));
                    $rating = $DB->get_records_sql("SELECT ceil(AVG(rate)) as rate FROM `mdl_course_rates` WHERE courseid=$course->courseid");
                    $rating = array_values($rating);
                    // course_views table (done by team)
                    $view = $DB->get_record('course_views', array('courseid' => $course->courseid));
                    $imges = $DB->get_record("course", array('id' => $course->courseid));
                    $courselist = new core_course_list_element($imges);
                    $context = context_course::instance($course->courseid, MUST_EXIST);
                    $enrol = is_enrolled($context, $user, '', true);
                    if ($enrol) {
                        $enrol = 'true';
                    } else {
                        $enrol = 'false';
                    }
                    $overviewfiles = array();
                    foreach ($courselist->get_course_overviewfiles() as $file) {
                        $fileurl = moodle_url::make_webservice_pluginfile_url(
                            $file->get_contextid(),
                            $file->get_component(),
                            $file->get_filearea(),
                            null,
                            $file->get_filepath(),
                            $file->get_filename()
                        )->out(false);
                        $overviewfiles[] = array(
                            'filename' => $file->get_filename(),
                            'fileurl' => $fileurl,
                            'filesize' => $file->get_filesize(),
                            'filepath' => $file->get_filepath(),
                            'mimetype' => $file->get_mimetype(),
                            'timemodified' => $file->get_timemodified(),
                        );
                    }
                    $overviewfiles = $overviewfiles[0]['fileurl'];


                    $teacherId = $get_teacher_data->id;
                    $teacherName = $get_teacher_data->firstname . ' ' . $get_teacher_data->lastname;

                    $course->coursedesc = '';
                    if (empty($teacherId)) {
                        $teacherId = -1;
                        $teacherName = null;
                    }
                    if (empty($view->visit)) {
                        $view->visit = "";
                    }
                    if (empty($rating[0]->rate)) {
                        $rating[0]->rate = "0";
                    }
                    $coursesData[] = array(
                        'course_id' => $course->courseid, 'course_name' => $course->coursename,
                        'enrol' => $enrol, 'course_desc' => $course->summary, 'course_year' => $course->year,
                        'views' => $view->visit, 'teacherId' => $teacherId,
                        'teacherName' => $teacherName, 'image' => $overviewfiles, 'price' => $price->value,
                        'rate' => $rating[0]->rate, 'cat_id' => $course->catid, 'cat_name' => $course->catname
                    );
                }
            }
        } else {
            // 
            // return json_encode(["data" => $data_courses]);
            foreach ($data_courses as $course) {
                // return json_encode(["data" => '2']);
                if ($course->visible) {
                    $price = $DB->get_record('customfield_data', array('fieldid' => 12, 'instanceid' => $course->courseid));
                    if (empty($price)) {
                        $price = 'free';
                    }
                    $courseDescription = $DB->get_record('customfield_data', array('fieldid' => 15, 'instanceid' => $course->courseid));
                    $rating = $DB->get_records_sql("SELECT ceil(AVG(rate)) as rate FROM `mdl_course_rates` WHERE courseid=$course->courseid");
                    $rating = array_values($rating);

                    $view = $DB->get_record('course_views', array('courseid' => $course->courseid));
                    $imges = $DB->get_record("course", array('id' => $course->courseid));
                    $courselist = new core_course_list_element($imges);
                    $context = context_course::instance($course->courseid, MUST_EXIST);
                    $enrol = is_enrolled($context, $user, '', true);
                    if ($enrol) {
                        $enrol = 'true';
                    } else {
                        $enrol = 'false';
                    }
                    $url = "";
                    foreach ($courselist->get_course_overviewfiles() as $file) {
                        $isimage = $file->is_valid_image();
                        $url = file_encode_url("{$CFG->wwwroot}/pluginfile.php", '/' . $file->get_contextid() . '/' . $file->get_component() . '/' . $file->get_filearea() . $file->get_filepath() . $file->get_filename(), !$isimage);
                    }
                    $overviewfiles = array();
                    foreach ($courselist->get_course_overviewfiles() as $file) {
                        $fileurl = moodle_url::make_webservice_pluginfile_url(
                            $file->get_contextid(),
                            $file->get_component(),
                            $file->get_filearea(),
                            null,
                            $file->get_filepath(),
                            $file->get_filename()
                        )->out(false);
                        $overviewfiles[] = array(
                            'filename' => $file->get_filename(),
                            'fileurl' => $fileurl,
                            'filesize' => $file->get_filesize(),
                            'filepath' => $file->get_filepath(),
                            'mimetype' => $file->get_mimetype(),
                            'timemodified' => $file->get_timemodified(),
                        );
                    }
                    $overviewfiles = $overviewfiles[0]['fileurl'];
                    $teachers = $DB->get_records_sql("SELECT u.firstname AS name,u.lastname AS lastname,u.description,u.email,ra.contextid,u.id As id, c.fullname as coursename
                FROM   mdl_course c
               LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
               LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
               LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id WHERE cx.contextlevel = '50' AND c.id= '$course->courseid';");
                    foreach ($teachers as $teacher) {
                        $teacherId = $teacher->id;
                        $teacherName = $teacher->name . ' ' . $teacher->lastname;
                    }
                    $course->coursedesc = '';
                    if (empty($teacherId)) {
                        $teacherId = -1;
                        $teacherName = null;
                    }
                    if (empty($view->visit)) {
                        $view->visit = "";
                    }
                    if (empty($rating[0]->rate)) {
                        $rating[0]->rate = "0";
                    }

                    $coursesData[] = array(
                        'course_id' => $course->courseid, 'course_name' => $course->coursename,
                        'enrol' => $enrol, 'course_desc' => $courseDescription->value, 'course_year' => $course->year,
                        'views' => $view->visit, 'teacherId' => $teacherId,
                        'teacherName' => $teacherName, 'image' => $overviewfiles, 'price' => $price->value,
                        'rate' => $rating[0]->rate, 'cat_id' => $course->catid, 'cat_name' => $course->catname
                    );
                }
            }
        }

        return json_encode(["data" => $coursesData]);
    }
    function get_user_courses($userid, $teacherid, $token = 0)
    {
        global $DB;
        try {
            $sql = "SELECT u.firstname AS name,u.lastname AS lastname, cat.description as catDesc,cat.name as catname,c.category as cat_id,c.summary As course_desc, c.fullname as coursename ,c.id as courseid
        FROM   mdl_course c
       LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
       LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
       LEFT OUTER JOIN   mdl_course_categories as cat ON c.category=cat.id
       LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id WHERE cx.contextlevel = '50' AND u.id= " . $teacherid . "";
            // $courses = $DB->get_recordset_sql("");
            $courses_data = array();

            $studentRole = $DB->get_field('role', 'id', array('shortname' => 'student'));
            $isStudent = $DB->record_exists('role_assignments', ['userid' => $userid, 'roleid' => $studentRole]);

            $assistantRole = $DB->get_field('role', 'id', array('shortname' => 'teacher'));
            $isAssistant = $DB->record_exists('role_assignments', ['userid' => $userid, 'roleid' => $assistantRole]);
            $courses = $DB->get_recordset_sql($sql);
            foreach ($courses as $key => $record) {
                // Do whatever you want with this record
                $context = context_course::instance($record->courseid, MUST_EXIST);
                $enrol = is_enrolled($context, $userid, '', true);
                $status = 0;
                $endtime = "";
                $enrolment = $DB->get_records_sql("SELECT ue.status as status ,ue.timeend as endtime from mdl_user_enrolments as ue join mdl_enrol as e on ue.enrolid=e.id where ue.userid=" . $userid . " and e.courseid=" . $record->courseid . "");
                foreach ($enrolment as $enrol) {
                    $status = $enrol->status;
                    $endtime = $enrol->endtime;
                }
                if ($enrol && $isStudent) {
                    $user = $DB->get_record('user', array('id' => $userid));
                    if ($user->suspended == 1) {
                        return json_encode(['status' => "fail", "data" => null, "error" => "This user is susbended from the site"]);
                    } elseif ($status == 1) {
                        return json_encode(['status' => "fail", "data" => null, "error" => "This user is susbended from course " . $record->courseid . ""]);
                    } elseif ($_SERVER['REQUEST_TIME'] > $endtime && $endtime != 0) {
                        return json_encode(['status' => "fail", "data" => null, "error" => "the enrolment has expired"]);
                    }
                }
                $price = $DB->get_record('customfield_data', array('fieldid' => 12, 'instanceid' => $record->courseid));
                if (empty($price)) {
                    $price = 'free';
                }
                // table by team course_rates
                $rating = $DB->get_records_sql("SELECT ceil(AVG(rate)) as rate FROM `mdl_course_rates` WHERE courseid=$record->courseid");
                $rate = 0;
                foreach ($rating as $rate) {
                    $rate = $rate->rate;
                }
                // return json_encode(['status' => "success", "data" =>, "error" => '']);

                $view = $DB->get_record('course_views', array('courseid' => $record->courseid));
                $imges = $DB->get_record("course", array('id' => $record->courseid));
                $courselist = new core_course_list_element($imges);

                $url = "";
                $overviewfiles = array();
                foreach ($courselist->get_course_overviewfiles() as $file) {
                    $fileurl = moodle_url::make_webservice_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        null,
                        $file->get_filepath(),
                        $file->get_filename()
                    )->out(false);
                    $overviewfiles[] = array(
                        'filename' => $file->get_filename(),
                        'fileurl' => $fileurl,
                        'filesize' => $file->get_filesize(),
                        'filepath' => $file->get_filepath(),
                        'mimetype' => $file->get_mimetype(),
                        'timemodified' => $file->get_timemodified(),
                    );
                }
                $overviewfiles = $overviewfiles[0]['fileurl'];

                if ($enrol && ($isStudent || $isAssistant)) {
                    // $courses[$key]->enrolled = true;

                    $courses_data[] = array(
                        "course_id" => $record->courseid, "course_name" => $record->coursename,
                        "course_desc" => format_string($record->course_desc),
                        "cat_id" => $record->cat_id,
                        "catname" => format_string($record->catname), "rate" => (int)$rate, "image" => $overviewfiles,
                        'price' => $price->value, 'views' => "0", "catDesc" => format_string($record->catDesc)
                    );
                    return  json_encode(["data" => $courses_data]);
                }
            }
            if (empty($courses_data)) {
                return json_encode(["data" => "fail"]);
            } else {
                return $courses_data;
            }
        } catch (Exception $e) {
            return json_encode(["data" => "fail"]);
        }
    }
    public function get_content()
    {
        global $DB, $CFG, $USER, $PAGE;
        require_once($CFG->dirroot . '/lib/enrollib.php');

        $PAGE->requires->css(new moodle_url($CFG->wwwroot . '/blocks/teacher/styles.css'));


        $this->content = new stdClass();

        $this->content->text = '';
        // unset($_SESSION['userdata']);
                //    $_SESSION['userdata'] = $_COOKIE['user_data'];
                //    var_dump($CFG->userId);
        if (!isLoggedin()||isset($_GET['id'])) {
            // var_dump($CFG->userId);
            unset($_SESSION['userdata']);

            $_SESSION['userdata']=$_GET['id'];
        }


        // $this->content->text =  $_SESSION['userdata'];
        // $courses = enrol_get_users_courses($_SESSION['userdata']);
        $studentRole = $DB->get_field('role', 'id', array('shortname' => 'student'));
        $isStudent = $DB->record_exists('role_assignments', ['userid' => $USER->id, 'roleid' => $studentRole]);
        if ($isStudent) {
            $coursesStudent = json_decode($this->get_user_courses($USER->id, $_SESSION['userdata']));
            // var_dump($coursesStudent);
            if ($coursesStudent->data == "fail") {
                $coursesStudent = json_decode($this->get_all_courses($USER->id, $_SESSION['userdata']));
            }
        } else {
        }
        $courses = enrol_get_users_courses($_SESSION['userdata']);

        // $this->content->text .= $courses->data;

        // $courses=array();
        $userData = $DB->get_record('teacher_styles', array('teacher_id' => $_SESSION['userdata']));
        $admins = get_admins();
        $isadmin = false;
        foreach ($admins as $admin) {
            if ($USER->id == $admin->id) {
                $isadmin = true;
                break;
            }
        }
        //  var_dump( $_SESSION['userdata']);


        if (!empty($_SESSION['userdata'])) {
            //echo '<script>alert('.$_SESSION['userdata'].')</script>';
            $user = $DB->get_record('user', array('id' => $_SESSION['userdata']));
            $home_teacher_data = $DB->get_record('home_teacher_data', array('teacherid' => $_SESSION['userdata']));
            $social = $DB->get_record('social_teacher', array('patch' => $home_teacher_data->id));
            $teacher_styles = $DB->get_record('teacher_styles', array('teacher_id' => $_SESSION['userdata']));
            if ($_SESSION['userdata'] != 14) //user not Mr. Farid
            {
                $this->content->text .= "<style>
                .carousel-inner .carousel-item:first-of-type{
                    height:870px !important;
                    background-image:url('" . $userData->src . "');
                    background-size:cover;background-repeat:no-repeat;
                }";
            } elseif ($_SESSION['userdata'] == 14) //user is Mr. Farid, change height of header image
            {
                $this->content->text .= "<img style='width:100%; min-height: 250px;' src='https://academy2022.nitg-eg.com/faridshawky/images/teacher.jpg'>
                <p><br></p><p><br></p><center><h2>✅متوافر الآن بجميع السناتر✅</h2>
                <p><br></p><h2>الجزء الأول والثاني من كتاب الفريد للثانوية العامة</h2>
                <p><br></p><img src='https://academy2022.nitg-eg.com/faridshawky/images/books.jpg'></center>
              
                
                ";
            }

            $this->content->text .= "<style>#sectionFour .about-image div{
                background-image:url('" . $home_teacher_data->section3image . "');
                background-size: 100% 100%;background-repeat:no-repeat;
                height: 533px;
            }
            .service-area .card .iconContainer{
                background:" . $teacher_styles->bgblock . ";

            }
            a{
                color:" . $teacher_styles->color . " ;
            }
            .service-area .card .p a{text-decoration:underline  ; color:" . $teacher_styles->bgblock . " ;}
            #sectionFour .about-image{
                background-color:" . $teacher_styles->bgblock . ";
            }
            #sectionFour .col-1 .bi-check-circle-fill{
                color:" . $teacher_styles->bgblock . ";
            }
            .owl-theme .owl-dots .owl-dot.active span{background: " . $teacher_styles->bgblock . " !important;}
            .owl-item , .owl-stage{
            }
            </style>";
            if ($_SESSION['userdata'] != 14) //Display things for teachers else than Mr. Farid
            {
                $this->content->text .= '
                <div id="carouselExampleControls" class="carousel slide mx-0 px-0" data-ride="carousel" data-interval="false">
                    <div class="carousel-inner conatiner-fluid mx-0 px-0 ">
                        <div class="carousel-item active row mx-0 px-0">
                        <div class="content text-center">
                        <h1 >' . $user->firstname . ' ' . $user->lastname . ' <br> <span id="sectionOneHead">';
                if (empty($home_teacher_data->section1head) && ($isadmin)) {

                    $this->content->text .= get_string('click', "theme_edumy");
                } else {
                    $this->content->text .= $home_teacher_data->section1head;
                }

                $this->content->text .= ' </span>
                       <input type="text " style="display:none;" id="editsectionOneHead" class="form-control">

                       </h1>
                       <p id="sectionOnePrag">';
                if (empty($home_teacher_data->section1body) && ($isadmin)) {

                    $this->content->text .= get_string('click', "theme_edumy");
                } else {
                    $this->content->text .= $home_teacher_data->section1body;
                }
                //    .$home_teacher_data->section1body.
                $this->content->text .= '</p>
                       <input type="text " style="display:none;" id="editsectionOnePrag" class="form-control">

                    </div>
                </div>
    
           
            </div>
        
        </div>
    
    
        <div class="container-fluid px-sm-2 px-md-5" id="sectionTwo">
            <div class="row mx-md-5" >
                <div class="col-sm-12 col-md-4 col-lg-4 mt-sm-2 mt-md-2 mt-sm-3 mt-lg service-area">
                    <div class="card">
                        <div class="iconContainer rounded-circle col-3">
                            <i class="bi bi-telephone-fill"></i>
                        </div>
                        <h2>' . get_string('phone', 'theme_edumy') . '</h2>
                        <p class="p" dir="ltr" id="phone">';
                if (empty($user->phone1) && ($isadmin)) {

                    $this->content->text .= get_string('click', "theme_edumy");
                } else {
                    $this->content->text .=  $user->phone1;
                }
                // . .


                $this->content->text .= '</p>
                        <input type="text " style="display:none;" id="editPhone" class="form-control">

                    </div>
                </div>
    
                <div class="col-sm-12 col-md-4 col-lg-4 mt-sm-2 mt-md-2 mt-lg service-area">
                    <div class="card">
                    <div class="row">

                        <div class="iconContainer rounded-circle">
                            <img src="' . $CFG->wwwroot . '/blocks/teacher/facebook.png" width="35px" height="35px">
                        </div>
                       ';
                $data = "";
                $data1 = "";
                $data2 = "";
                $data3 = "";
                if (empty($social->facebook)) {
                    $data = '';
                } else {
                    $data = 'href="' . $social->facebook . '"';
                }

                if (empty($social->empty1)) { //instagram
                    $data1 = '';
                } else {
                    $data1 = 'href="' . $social->empty1 . '"';
                }
                if (empty($social->youtube)) { //linkedin
                    $data2 = '';
                } else {
                    $data2 = 'href="' . $social->youtube . '"';
                }

                if (empty($social->empty2)) { //telegram
                    $data3 = '';
                } else {
                    $data3 = 'href="' . $social->empty2 . '"';
                }

                $this->content->text .= ' 
                        
                        <div class="iconContainer rounded-circle mx-1">
                        <a  ><i class="bi bi-youtube"></i></a>
                    </div>';
                if ($_SESSION['userdata'] == 16) {
                    $this->content->text .= '   <div class="iconContainer rounded-circle mx-1">
                            <a  ><i class="bi bi-instagram"></i></a>
                            </div>
                            <div class="iconContainer rounded-circle mx-1">
                            <a  ><i class="bi bi-telegram"></i></a>
                            </div>
                            ';
                }

                $this->content->text .= '  </div>
                        <h2>' . get_string('follow', 'theme_edumy') . '</h2>
                        <div class="p text-center row">
                            <div class="col-5">
                            <a id="facebookLink" ' . $data . '" target="_blank">Facebook</a> 
                           <span>  <i class="bi bi-gear  show" style="display:none;"id="editFacebookIcon" ></i>
                           <i class="bi bi-archive-fill show del"  style="display:none;" id="delFacebookIcon" ></i> </span>
                            <input type="text " style="display:none;" id="editFacebook" class="form-control-sm form-control-sm">
                            </div>
                            | 
                            <div class="col-5">

                            <a id="youtubeLink" ' . $data2 . '" target="_blank">Youtube</a>
                            <span>  <i class="bi bi-gear  show" style="display:none;"id="editYoutubeIcon" ></i>
                            <i class="bi bi-archive-fill show del"  style="display:none;" id="delYoutubeIcon" ></i> </span>
                            <input type="text " style="display:none;" id="editYoutube" class="form-control-sm form-control-sm">
                            </div>';
                if ($_SESSION['userdata'] == 16) {
                    $this->content->text .= ' |
                                <div class="col-5">

                                <a id="linkedinLink"  ' . $data1 . '" target="_blank">Instagram</a>
    
                                <span>  <i class="bi bi-gear  show" style="display:none;"id="editLinkedinIcon" ></i>
                                <i class="bi bi-archive-fill show del"  style="display:none;" id="delLinkedinIcon" ></i> </span>
                                <input type="text " style="display:none;" id="editLinkedin" class="form-control-sm form-control-sm">
                            </div>
                                
                                |
                                <div class="col-5">

                                <a id="telegramLink"  ' . $data3 . '" target="_blank">Telegram</a>
    
                                <span>  <i class="bi bi-gear  show" style="display:none;"id="editTelegramIcon" ></i>
                                <i class="bi bi-archive-fill show del"  style="display:none;" id="delTelegramIcon" ></i> </span>
                                <input type="text " style="display:none;" id="editTelegram" class="form-control-sm form-control-sm">
                                </div>
                                ';
                }

                $this->content->text .= ' </div>
                    </div>
                </div>
    
                <div class="col-sm-12 col-md-4 col-lg-4 mt-sm-2 mt-md-2 mt-lg service-area">
                    <div class="card">
                        <div class="iconContainer rounded-circle">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <h2>' . get_string('team', 'theme_edumy') . '</h2>

                        <p id="section1left">';
                if (empty($home_teacher_data->section1left) && ($isadmin)) {

                    $this->content->text .= get_string('click', "theme_edumy");
                } else {
                    $this->content->text .= $home_teacher_data->section1left;
                }
                // .$home_teacher_data->section1left.

                $this->content->text .= '</p>
                        <input type="text " style="display:none;" id="editsection1left" class="form-control">

                    </div>
                </div>
            </div>
        </div>';
            }
            // var_dump($courses);
            if (!empty($_SESSION['userdata']) &&(!isloggedin()||$isStudent) ) {
                // var_dump($courses);
                $this->content->text .= ' <div class="container-fluid px-sm-2 px-md-5" id="sectionThree">
                     <div class="text-center"><h2>' . get_string('teacher_courses', 'theme_edumy') . '</h2></div>
                        <div class="owl-carousel owl-theme px-sm-0 px-md-5" >
                   ';
                    //  var_dump($_SESSION['userdata']);
                foreach ($courses as $course) {
                    // $this->content->text .='dsf'.$course->course_id;
                    if (isLoggedin()) {
                        $redirectUrl = '/course/view.php?id=' . $course->id . '';
                    } else {
                        $redirectUrl = '/login/index.php?id=' . $_SESSION['userdata'];
                    }
                    $data = $DB->get_record('course', array('id' => $course->id));
                    $this->content->text .= '    <div class=" item ">
                    <div class="course">
                    <img src="' . $this->get_course_image($course) . '">

                        <div class="courseImg">
                            <div>
                           <a href= "' . $redirectUrl . '">
                            </div>
                        </div>
                        <div class="text-center mt-3 px-2"> <h2 id="courseName">' . $course->fullname . '</h2></div>
                        <div class="course-content py-4">
                            <p>
                             ' . format_text($data->summary) . '
                            </p>
                            <button class="rounded-pill mt-3">
                                <a href=' . $redirectUrl . '>' . get_string('enter', 'theme_edumy') . '</a>
                            </button>
                        </div>
                    </div>
                    </div>
    ';
                }
                $this->content->text .= '          

           
                   </div>
              </div>
              <script>
              $(document).ready(function(){
            $(".owl-carousel").owlCarousel({
                loop:false,
                margin:10,
                nav:true,
                responsive:{
                    0:{
                        items:1
                    },
                    600:{
                        items:2
                    },
                    1000:{
                        items:3
                    }
                }
            });
            $(".owl-dot").click(function(){
                $(this).addClass("active").siblings().removeClass("active");
            });
        });
        </script>
        ';
            }
            if ($isadmin || $_SESSION['userdata'] == $USER->id) {
                $this->content->text .= ' <div class="container-fluid px-sm-2 px-md-5" id="sectionThree">
            <div class="text-center"><h2>' . get_string('teacher_courses', 'theme_edumy') . '</h2></div>
            <div class="owl-carousel owl-theme px-sm-0 px-md-5" >
          ';
                foreach ($courses as $course) {
                    // $this->content->text .='dsf'.$course->course_id;
                    $data = $DB->get_record('course', array('id' => $course->id));
                    $this->content->text .= '    <div class=" item ">
                    <div class="course">
                    <img src="' . $this->get_course_image($course) . '">

                        <div class="courseImg">
                            <div>
                           <a href= "' . $CFG->wwwroot . '/course/view.php?id=' . $course->id . '">
                            </div>
                        </div>
                        <div class="text-center mt-3 px-2"> <h2 id="courseName">' . $course->fullname . '</h2></div>
                        <div class="course-content py-4">
                            <p>
                             ' . format_text($data->summary) . '
                            </p>
                            <button class="rounded-pill mt-3">
                                <a href="' . $CFG->wwwroot . '/course/view.php?id=' . $course->id . '">' . get_string('enter', 'theme_edumy') . '</a>
                            </button>
                        </div>
                    </div>
                    </div>
    ';
                }
                $this->content->text .= '          

           
            </div>
        </div>
        <script>
        $(document).ready(function(){
            $(".owl-carousel").owlCarousel({
                loop:false,
                margin:10,
                nav:true,
                responsive:{
                    0:{
                        items:1
                    },
                    600:{
                        items:2
                    },
                    1000:{
                        items:3
                    }
                }
            });
            $(".owl-dot").click(function(){
                $(this).addClass("active").siblings().removeClass("active");
            });
        });
        </script>
        ';
            } elseif ($isStudent) {
                // $this->content->text .= $USER->id."fdds". $_SESSION['userdata'];

                $this->content->text .= ' <div class="container-fluid px-5" id="sectionThree">
            <div class="text-center"><h2>' . get_string('your_courses', 'theme_edumy') . '</h2></div>
            <div class="owl-carousel owl-theme px-5"  >
          ';
                foreach ($coursesStudent->data as $course) {

                    $this->content->text .= '    <div class=" item ">
                    <div class="course">
                    <div>
                           <a href= "' . $CFG->wwwroot . '/course/view.php?id=' . $course->course_id . '">
                            
                    <img src="' . $course->image . '?token=f30821f45f3c2fc9650a58917be47cad">
                    </div>
                        <div class="courseImg">
                            <div>
                           <a href= "' . $CFG->wwwroot . '/course/view.php?id=' . $course->course_id . '">
                            </div>
                        </div>
                        <div class="text-center mt-3 px-2"> <h2 id="courseName">' . $course->course_name . '</h2></div>
                        <div class="course-content py-4">
                            <p>
                             ' . format_text($course->course_desc) . '
                            </p>
                            <button class="rounded-pill mt-3">                                
                                <a href="' . $CFG->wwwroot . '/course/view.php?id=' . $course->course_id . '">' . get_string('enter', 'theme_edumy') . '</a>
                            </button>
                        </div>
                    </div>
                    </div>
                    
    ';
                }
                $this->content->text .= '          

           
            </div>
        </div>
        <script>
        $(document).ready(function(){
            $(".owl-carousel").owlCarousel({
                loop:false,
                margin:10 ,
                nav:true,
                responsive:{
                    0:{
                        items:1
                    },
                    600:{
                        items:2
                    },
                    1000:{
                        items:3
                    }
                }
            });
            $(".owl-dot").click(function(){
                $(this).addClass("active").siblings().removeClass("active");
            });
        });
        </script>
        ';
            }
            $this->content->text .= '      <div class="container-fluid" id="sectionFour">
            <div class="row" >
                <div class="col-sm-12 col-lg-6 px-sm-0 col-md-12  px-mb-5">
                    <div class="col-sm-12 col-md-12 px-md-5 px-sm-2 px-mb-5 px-sm-0">
                        <h2 class="ml-4">' . get_string('about', 'theme_edumy') . '</h2>
                        <ul>';
            if (!empty($home_teacher_data->section3) || $isadmin) {


                $this->content->text .= ' <li class="row">
                                <div class="col-1 text-center px-1">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div class="col-11 px-1">
                                    <p id="divOne px-sm-1">
                                     ';
                if (empty($home_teacher_data->section3) && $isadmin) {
                    $this->content->text .= get_string('click', "theme_edumy");
                } else {
                    $this->content->text .= $home_teacher_data->section3;
                }
                //  .$home_teacher_data->section3.

                $this->content->text .= '

                                    </p>
                                    <input type="text " style="display:none;" id="editDivOne" class="form-control">

                                </div>
                            </li>';
            }
            if (!empty($home_teacher_data->empty1) || $isadmin) {

                $this->content->text .= '    <li class="row">
                                <div class="col-1 text-center px-1">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div class="col-11 px-1">
                                    <p id="divTwo px-sm-1">
                                    ';
                if (empty($home_teacher_data->empty1) && $isadmin) {
                    $this->content->text .= get_string('click', "theme_edumy");
                } else {
                    $this->content->text .= $home_teacher_data->empty1;
                }
                // .$home_teacher_data->empty1.

                $this->content->text .= '
                                    </p>
                                    <input type="text " style="display:none;" id="editDivTwo" class="form-control">

                                </div>
                            </li>';
            }
            if (!empty($home_teacher_data->empty2) || $isadmin) {

                $this->content->text .= ' <li class="row">
                                <div class="col-1 text-center px-1">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div class="col-11 px-1">
                                    <p id="divThree px-sm-1">
                                    ';
                if (empty($home_teacher_data->empty2) && ($isadmin)) {
                    $this->content->text .= get_string('click', "theme_edumy");
                } else {
                    $this->content->text .= $home_teacher_data->empty2;
                }
                // .$home_teacher_data->empty2.

                $this->content->text .= '
                                    </p>
                                    <input type="text " style="display:none;" id="editDivThree" class="form-control">

                                </div>
                            </li>';
            }
            if (!empty($home_teacher_data->empty3) || $isadmin) {
                $this->content->text .= ' <li class="row">
                                <div class="col-1 text-center px-1">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div class="col-11 px-1">
                                    <p id="divFour px-sm-1">
                                    ';
                if (empty($home_teacher_data->empty3) && ($isadmin)) {
                    $this->content->text .= get_string('click', "theme_edumy");
                } else {
                    $this->content->text .= $home_teacher_data->empty3;
                }
                // .$home_teacher_data->empty3.
                $this->content->text .= '
                                    </p>
                                    <input type="text " style="display:none;" id="editDivFour" class="form-control">

                                </div>
                            </li>';
            }
            if (!empty($home_teacher_data->empty4) || $isadmin) {
                $this->content->text .= '<li class="row">
                                <div class="col-1 text-center px-1">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div class="col-11 px-1">
                                    <p id="divFive px-sm-1">
                                    ';
                if (empty($home_teacher_data->empty4) && ($isadmin)) {
                    $this->content->text .= get_string('click', "theme_edumy");
                } else {
                    $this->content->text .= $home_teacher_data->empty4;
                }
                // .$home_teacher_data->empty4.
                $this->content->text .= '
                                    </p>
                                    <input type="text " style="display:none;" id="editDivFive" class="form-control">
                                </div>
                            </li>';
            }

            $this->content->text .= '</ul>
                    </div>
                </div>
                <div class="col-sm-12 col-md-6 px-sm-0">
                    <div class="about-image">
                        <div></div>
                    </div>
                </div>
            </div>
        </div>
        ';

            if ($isadmin) {
                $this->content->text .= '
        <script>
        $( document ).ready(function() {
                $(".owl-carousel").owlCarousel({
                    loop:false,
                    margin:10,
                    nav:true,
                    responsive:{
                        0:{
                            items:1
                        },
                        600:{
                            items:2
                        },
                        1000:{
                            items:3
                        }
                    }
                })
           var direction= $("body").css("direction");
                $(".show").show();
            $("#sectionOnePrag").click(function(e){
                val = $("#sectionOnePrag").text();
              $("#sectionOnePrag").hide();
              $("#editsectionOnePrag").show();
                $("#editsectionOnePrag").val(val);
         
            });
            $("#editsectionOnePrag").blur(function(e){
                val = $("#editsectionOnePrag").val();
                if(val.includes("' . get_string('click', "theme_edumy") . '")){
                val = "";
                $("#sectionOnePrag").text("' . get_string('click', "theme_edumy") . '");
                }
                else{
                    $("#sectionOnePrag").text(val);
                }
                $("#editsectionOnePrag").hide();
                $("#sectionOnePrag").show();
                $.ajax({
                    type: "POST",
                    url: "/ajax.php",
                     data: {  sectionOnePragValue:val ,teacherId:' . $_SESSION['userdata'] . ' },
                 success: function (data) {
                        console.log("yes");
                     }
                 }); 
            });

            $("#section1left").click(function(e){
                val = $("#section1left").text();
              $("#section1left").hide();
              $("#editsection1left").show();
                $("#editsection1left").val(val);
         
            });
            $("#editsection1left").blur(function(e){
                val = $("#editsection1left").val();
                if(val.includes("' . get_string('click', "theme_edumy") . '")){
                val = "";
                $("#section1left").text("' . get_string('click', "theme_edumy") . '");
                }
                else{
                    $("#section1left").text(val);
                }
                $("#editsection1left").hide();
                $("#section1left").show();
                $.ajax({
                    type: "POST",
                    url: "/ajax.php",
                     data: {  section1leftValue:val ,teacherId:' . $_SESSION['userdata'] . ' },
                 success: function (data) {
                        console.log("yes");
                     }
                 }); 
            });
            
            $("#sectionOneHead").click(function(e){
                val = $("#sectionOneHead").text();
              $("#sectionOneHead").hide();
              $("#editsectionOneHead").show();
                $("#editsectionOneHead").val(val);
         
            });
            $("#editsectionOneHead").blur(function(e){
                val = $("#editsectionOneHead").val();
                if(val.includes("' . get_string('click', "theme_edumy") . '")){
                val = "";
                $("#sectionOneHead").text("' . get_string('click', "theme_edumy") . '");
                }
                else{
                    $("#sectionOneHead").text(val);
                }

                $("#editsectionOneHead").hide();
                $("#sectionOneHead").show();
                $.ajax({
                    type: "POST",
                    url: "/ajax.php",
                     data: {  sectionOneHeadValue:val ,teacherId:' . $_SESSION['userdata'] . ' },
                 success: function (data) {
                        console.log("yes");
                     }
                 }); 
            });


            $("#phone").click(function(e){
                val = $("#phone").text();
              $("#phone").hide();
              $("#editPhone").show();
                $("#editPhone").val(val);
         
            });
            $("#editPhone").blur(function(e){
                val = $("#editPhone").val();
                if(val.includes("' . get_string('click', "theme_edumy") . '")){
                val = "";
                $("#phone").text("' . get_string('click', "theme_edumy") . '");
                }
                else{
                    $("#phone").text(val);
                }
                $("#editPhone").hide();
                $("#phone").show();
                $("#phone").text(val);
                $.ajax({
                    type: "POST",
                    url: "/ajax.php",
                     data: {  editPhone:val ,teacherId:' . $_SESSION['userdata'] . ' },
                 success: function (data) {
                        console.log("yes");
                     }
                 }); 
            });

            $("#divOne").click(function(e){
                val = $("#divOne").text();
              $("#divOne").hide();
              $("#editDivOne").show();
                $("#editDivOne").val(val);
         
            });
            $("#editDivOne").blur(function(e){
                val = $("#editDivOne").val();
                if(val.includes("' . get_string('click', "theme_edumy") . '")){
                    val = "";
                    $("#divOne").text("' . get_string('click', "theme_edumy") . '");

                }
                else{
                    $("#divOne").text(val);
                }
                $("#editDivOne").hide();
                $("#divOne").show();
                $.ajax({
                    type: "POST",
                    url: "/ajax.php",
                     data: {  divOne:val ,teacherId:' . $_SESSION['userdata'] . ',value:1 },
                 success: function (data) {
                        console.log("yes");
                     }
                 }); 
            });
            $("#divTwo").click(function(e){
                val = $("#divTwo").text();
              $("#divTwo").hide();
              $("#editDivTwo").show();
                $("#editDivTwo").val(val);
         
            });
            $("#editDivTwo").blur(function(e){
              
                val = $("#editDivTwo").val();
                if(val.includes("' . get_string('click', "theme_edumy") . '")){
                    val = "";
                    $("#divTwo").text("' . get_string('click', "theme_edumy") . '");

                }
                else{
                    $("#divTwo").text(val);

                }
                $("#editDivTwo").hide();
                $("#divTwo").show();
                $.ajax({
                    type: "POST",
                    url: "/ajax.php",
                     data: {  divTwo:val ,teacherId:' . $_SESSION['userdata'] . ',value:1 },
                 success: function (data) {
                        console.log("yes");
                     }
                 }); 
            });
            $("#divThree").click(function(e){
                val = $("#divThree").text();
              $("#divThree").hide();
              $("#editDivThree").show();
                $("#editDivThree").val(val);
         
            });
            $("#editDivThree").blur(function(e){
                val = $("#editDivThree").val();
                if(val.includes("' . get_string('click', "theme_edumy") . '")){
                    val = "";
                    $("#divThree").text("' . get_string('click', "theme_edumy") . '");

                }
                else{
                    $("#divThree").text(val);

                }
                $("#editDivThree").hide();
                $("#divThree").show();
                $.ajax({
                    type: "POST",
                    url: "/ajax.php",
                     data: {  divThree:val ,teacherId:' . $_SESSION['userdata'] . ',value:1 },
                 success: function (data) {
                        console.log("yes");
                     }
                 }); 
            });

            $("#divFour").click(function(e){
                val = $("#divFour").text();
              $("#divFour").hide();
              $("#editDivFour").show();
                $("#editDivFour").val(val);
         
            });
            $("#editDivFour").blur(function(e){
                val = $("#editDivFour").val();
                if(val.includes("' . get_string('click', "theme_edumy") . '")){
                    val = "";
                    $("#divFour").text("' . get_string('click', "theme_edumy") . '");

                }
                else{
                    $("#divFour").text(val);

                }
                $("#editDivFour").hide();
                $("#divFour").show();
                $.ajax({
                    type: "POST",
                    url: "/ajax.php",
                     data: {  divFour:val ,teacherId:' . $_SESSION['userdata'] . ',value:1 },
                 success: function (data) {
                        console.log("yes");
                     }
                 }); 
            });
            $("#editFacebookIcon").click(function(){
                var attr = $("#facebookLink").attr("href");
                    $("#facebookLink").hide();
                    $("#editFacebook").show();
                    $("#editFacebook").val(attr);

                    $("#editFacebook").blur(function(e){
                        val = $("#editFacebook").val();

                        if( !$(this).val()){
                            $("#facebookLink").removeAttr("href");
                        }
                        else{
                            $("#facebookLink").attr("href",val);
                        }
                            $("#editFacebook").hide();
                            $("#facebookLink").show();
                            // $("#facebookLink").attr("href",val);
                            $.ajax({
                                type: "POST",
                                url: "/ajax.php",
                                 data: {  facebookLink:val ,teacherId:' . $_SESSION['userdata'] . ',value:1 },
                             success: function (data) {
                                    console.log("yes");
                                 }
                             }); 
                       
                       
                    });
                                
            });

            $("#editYoutubeIcon").click(function(){
                var attr = $("#youtubeLink").attr("href");
                    $("#youtubeLink").hide();
                    $("#editYoutube").show();
                    $("#editYoutube").val(attr);

                    $("#editYoutube").blur(function(e){
                        val = $("#editYoutube").val();

                        if( !$(this).val()){
                            $("#youtubeLink").removeAttr("href");
                        }
                        else{
                            $("#youtubeLink").attr("href",val);
                        }
                            $("#editYoutube").hide();
                            $("#youtubeLink").show();
                            $.ajax({
                                type: "POST",
                                url: "/ajax.php",
                                 data: {  youtubeLink:val ,teacherId:' . $_SESSION['userdata'] . ',value:1 },
                             success: function (data) {
                                    console.log("yes");
                                 }
                             }); 
                        
                       
                    });
                               
            });

            $("#editLinkedinIcon").click(function(){
                var attr = $("#linkedinLink").attr("href");
                    $("#linkedinLink").hide();
                    $("#editLinkedin").show();
                    $("#editLinkedin").val(attr);

                    $("#editLinkedin").blur(function(e){
                        val = $("#editLinkedin").val();

                        if( !$(this).val()){
                            $("#linkedinLink").removeAttr("href");
                        }
                        else{
                            $("#linkedinLink").attr("href",val);
                        }
                            $("#editLinkedin").hide();
                            $("#linkedinLink").show();
                            $.ajax({
                                type: "POST",
                                url: "/ajax.php",
                                 data: {  linkedinLink:val ,teacherId:' . $_SESSION['userdata'] . ',value:1 },
                             success: function (data) {
                                    console.log("yes");
                                 }
                             }); 
                        
                       
                    });
                              
            });
            $("#editTelegramIcon").click(function(){
                var attr = $("#telegramLink").attr("href");
                    $("#telegramLink").hide();
                    $("#editTelegram").show();
                    $("#editTelegram").val(attr);

                    $("#editTelegram").blur(function(e){
                        val = $("#editTelegram").val();

                        if( !$(this).val()){
                            $("#telegramLink").removeAttr("href");
                        }
                        else{
                            $("#telegramLink").attr("href",val);
                        }
                            $("#editTelegram").hide();
                            $("#telegramLink").show();
                            $.ajax({
                                type: "POST",
                                url: "/ajax.php",
                                 data: {  telegramLink:val ,teacherId:' . $_SESSION['userdata'] . ',value:1 },
                             success: function (data) {
                                    console.log("yes");
                                 }
                             }); 
                        
                       
                    });
                              
            });

            $("#delFacebookIcon").click(function(){
                var attr = $("#facebookLink").attr("href");
                if (typeof attr !== "undefined" || attr !== false) {
                    $("#facebookLink").removeAttr("href");
                    $.ajax({
                        type: "POST",
                        url: "/ajax.php",
                         data: {  delSocial:1 ,teacherId:' . $_SESSION['userdata'] . ',value:1 },
                     success: function (data) {
                            console.log("yes");
                         }
                     }); 

                                }
            });
            
            $("#delYoutubeIcon").click(function(){
                var attr = $("#youtubeLink").attr("href");
                if (typeof attr !== "undefined" || attr !== false) {
                    $("#youtubeLink").removeAttr("href");
                    $.ajax({
                        type: "POST",
                        url: "/ajax.php",
                         data: {  delSocial:2 ,teacherId:' . $_SESSION['userdata'] . ',value:1 },
                     success: function (data) {
                            console.log("yes");
                         }
                     }); 

                                }
            });

            $("#delLinkedinIcon").click(function(){
                var attr = $("#linkedinLink").attr("href");
                if (typeof attr !== "undefined" || attr !== false) {
                    $("#linkedinLink").removeAttr("href");
                    $.ajax({
                        type: "POST",
                        url: "/ajax.php",
                         data: {  delSocial:3,teacherId:' . $_SESSION['userdata'] . ',value:1 },
                     success: function (data) {
                            console.log("yes");
                         }
                     }); 

                                }
            });

            $("#delTelegramIcon").click(function(){
                var attr = $("#telegramLink").attr("href");
                if (typeof attr !== "undefined" || attr !== false) {
                    $("#telegramLink").removeAttr("href");
                    $.ajax({
                        type: "POST",
                        url: "/ajax.php",
                         data: {  delSocial:4,teacherId:' . $_SESSION['userdata'] . ',value:1 },
                     success: function (data) {
                            console.log("yes");
                         }
                     }); 

                                }
            });

            $("#divFive").click(function(e){
                val = $("#divFive").text();
              $("#divFive").hide();
              $("#editDivFive").show();
                $("#editDivFive").val(val);
         
            });
            $("#editDivFive").blur(function(e){
                val = $("#editDivFive").val();
                if(val.includes("' . get_string('click', "theme_edumy") . '")){
                    val = "";
                    $("#divFive").text("' . get_string('click', "theme_edumy") . '");

                }
                else{
                    $("#divFive").text(val);

                }
                $("#editDivFive").hide();
                $("#divFive").show();
                $.ajax({
                    type: "POST",
                    url: "/ajax.php",
                     data: {  divFive:val ,teacherId:' . $_SESSION['userdata'] . ',value:1 },
                 success: function (data) {
                        console.log("yes");
                     }
                 }); 
            });
        });
        </script>
        ';
            }

            $this->content->text .= '';



            return $this->content;
        }
    }
    // The PHP tag and the curly bracket for the class definition 
    // will only be closed after there is another function added in the next section.
}
