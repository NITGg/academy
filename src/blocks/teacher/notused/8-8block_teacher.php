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
    public function get_course_image($course){
        global $CFG;
        $url = '';
        require_once( $CFG->libdir . '/filelib.php' );
 
        $context = context_course::instance( $course->id );
        $fs = get_file_storage();
        $files = $fs->get_area_files( $context->id, 'course', 'overviewfiles', 0 );
 
        foreach ( $files as $f )
        {
          if ( $f->is_valid_image() )
          {
             $url = moodle_url::make_pluginfile_url( $f->get_contextid(), $f->get_component(), $f->get_filearea(), null, $f->get_filepath(), $f->get_filename(), false );
          }
        }
 
        return $url;
       
    }
    public function get_content()
    {
        global $DB, $CFG, $USER, $PAGE;
        require_once($CFG->dirroot . '/lib/enrollib.php');

        $PAGE->requires->css(new moodle_url($CFG->wwwroot . '/blocks/teacher/styles.css'));
       

        $this->content = new stdClass();

        $this->content->text = '';
        // $this->content->text =  $_SESSION['userdata'];
        $courses = enrol_get_users_courses($_SESSION['userdata']);
        $userData = $DB->get_record('teacher_styles', array('teacher_id' => $_SESSION['userdata']));
        $admins = get_admins();
        $isadmin = false;
        foreach ($admins as $admin) {
          if ($USER->id == $admin->id) {
            $isadmin = true;
            break;
          }
        }
        if (!empty($_SESSION['userdata'])) {
            $user = $DB->get_record('user', array('id' => $_SESSION['userdata']));
            $home_teacher_data=$DB->get_record('home_teacher_data', array('teacherid' => $_SESSION['userdata']));
            $social=$DB->get_record('social_teacher', array('patch' => $home_teacher_data->id));
            $all_about=explode(",",$home_teacher_data->section3);
            $this->content->text .= "<style>
            .carousel-inner .carousel-item:first-of-type{
                height:870px !important;
                background-image:url('" . $userData->src . "');
                background-size:cover;background-repeat:no-repeat;
            }
            #sectionFour .about-image div{
                background-image:url('" . $home_teacher_data->section3image . "');
                background-size:cover;background-repeat:no-repeat;
                height:100%;margin-left:15px!important;margin-right:-15px !important;
                border-radius:10px !important;
            }
            </style>";
            $this->content->text .= '
            
            
     
    
        <div id="carouselExampleControls" class="carousel slide mx-0 px-0" data-ride="carousel" data-interval="false">
            <div class="carousel-inner conatiner-fluid mx-0 px-0 ">
                <div class="carousel-item active row mx-0 px-0">
                    <div class="content text-center">
                       <h1 >' . $user->firstname . ' ' . $user->lastname . ' <br> <span id="sectionOneHead">';
                        if(empty($home_teacher_data->section1head)&&($isadmin)){

                            $this->content->text .='Click here to edit';
                        }
                        else{
                            $this->content->text .= $home_teacher_data->section1head;
                        }
                       
                       $this->content->text .=' </span>
                       <input type="text " style="display:none;" id="editsectionOneHead" class="form-control">

                       </h1>
                       <p id="sectionOnePrag">';
                       if(empty($home_teacher_data->section1body)&&($isadmin)){

                        $this->content->text .='Click here to edit';
                    }
                    else{
                        $this->content->text .= $home_teacher_data->section1body;
                    }
                    //    .$home_teacher_data->section1body.
                       $this->content->text .='</p>
                       <input type="text " style="display:none;" id="editsectionOnePrag" class="form-control">

                    </div>
                </div>
    
           
            </div>
        
        </div>
    
    
        <div class="container-fluid px-5" id="sectionTwo">
            <div class="row mx-5" >
                <div class="col-sm-12 col-md-4 col-lg-4 mt-sm-2 mt-md-2 mt-sm-3 mt-lg service-area">
                    <div class="card">
                        <div class="iconContainer rounded-circle">
                            <i class="bi bi-telephone-fill"></i>
                        </div>
                        <h2>' . get_string('phone', 'theme_edumy') . '</h2>
                        <p class="p" dir="ltr" id="phone">' . $user->phone1 . '</p>
                        <input type="text " style="display:none;" id="editPhone" class="form-control">

                    </div>
                </div>
    
                <div class="col-sm-12 col-md-4 col-lg-4 mt-sm-2 mt-md-2 mt-lg service-area">
                    <div class="card">
                        <div class="iconContainer rounded-circle">
                            <img src="' . $CFG->wwwroot . '/blocks/teacher/facebook.png" width="35px" height="35px">
                        </div>
                        <h2>Facebook</h2>';
                        $data="";
                        if(empty($social->facebook)&&($isadmin)){
                            $data='';
                        }
                      
                        else{
                            $data='href="'.$social->facebook.'"';
                        }
                        $this->content->text .='<p class="p"><a id="facebookLink" '.$data.'">' . $user->firstname . ' ' . $user->lastname . '</a></p>
                        <input type="text " style="display:none;" id="editFacebook" class="form-control">

                    </div>
                </div>
    
                <div class="col-sm-12 col-md-4 col-lg-4 mt-sm-2 mt-md-2 mt-lg service-area">
                    <div class="card">
                        <div class="iconContainer rounded-circle">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <h2>' . get_string('team', 'theme_edumy') . ' '.$home_teacher_data->section1head.' </h2>

                        <p id="section1left">';
                        if(empty($home_teacher_data->section1left)&&($isadmin)){

                            $this->content->text .='Click here to edit';
                        }
                        else{
                            $this->content->text .= $home_teacher_data->section1left;
                        }
                        // .$home_teacher_data->section1left.
                        
                        $this->content->text .='</p>
                        <input type="text " style="display:none;" id="editsection1left" class="form-control">

                    </div>
                </div>
            </div>
        </div>
    
        <div class="container-fluid px-5" id="sectionThree">
            <div class="text-center"><h2>Our Regular Courses</h2></div>
            <div class="row mx-5" >
          ';
            foreach ($courses as $course) {
                // $this->content->text .=$course->id;
                $data=$DB->get_record('course', array('id' => $course->id));
                $this->content->text .= '    <div class=" carousel-cell col-sm-12 col-md-4 my-3 mx-auto">
                    <div class="course">
                    <img src="'.$this->get_course_image($course).'">

                        <div class="courseImg">
                            <div>
                           <a href= "' . $CFG->wwwroot . '/course/view.php?id=' . $course->id . '">
                            </div>
                        </div>
                        <div class="text-center mt-3 px-2"> <h2>' . $course->fullname . '</h2></div>
                        <div class="course-content py-4">
                            <p>
                             '.format_text($data->summary).'
                            </p>
                            <button class="rounded-pill mt-3">
                                <a href="' . $CFG->wwwroot . '/course/view.php?id=' . $course->id . '">Read More</a>
                            </button>
                        </div>
                    </div>
                    </div>
    ';
            }
            $this->content->text .= '          

           
            </div>
        </div>
    
        <div class="container-fluid" id="sectionFour">
            <div class="row" >
                <div class="col-sm-12 col-md-12 col-lg-6 px-5">
                    <div class="col-sm-12 col-md-12 col-6 px-md-0 px-sm-0">
                        <h2 class="ml-4">About Our System ? </h2>
                        <ul>
                            <li class="row">
                                <div class="col-1 text-right px-2">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div class="col-11 px-1">
                                    <p id="divOne">
                                     '.$home_teacher_data->section3.'

                                    </p>
                                    <input type="text " style="display:none;" id="editDivOne" class="form-control">

                                </div>
                            </li>
                            <li class="row">
                                <div class="col-1 text-right px-2">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div class="col-11 px-1">
                                    <p id="divTwo">
                                    '.$home_teacher_data->empty1.'
                                    </p>
                                    <input type="text " style="display:none;" id="editDivTwo" class="form-control">

                                </div>
                            </li>
                            <li class="row">
                                <div class="col-1 text-right px-2">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div class="col-11 px-1">
                                    <p id="divThree">
                                    '.$home_teacher_data->empty2.'
                                    </p>
                                    <input type="text " style="display:none;" id="editDivThree" class="form-control">

                                </div>
                            </li>
                            <li class="row">
                                <div class="col-1 text-right px-2">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div class="col-11 px-1">
                                    <p id="divFour">
                                    '.$home_teacher_data->empty3.'
                                    </p>
                                    <input type="text " style="display:none;" id="editDivFour" class="form-control">

                                </div>
                            </li>
                            <li class="row">
                                <div class="col-1 text-right px-2">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div class="col-11 px-1">
                                    <p id="divFive">
                                    '.$home_teacher_data->empty4.'
                                    </p>
                                    <input type="text " style="display:none;" id="editDivFive" class="form-control">
                                </div>
                            </li>
                        
                        </ul>
                    </div>
                </div>
                <div class="col-sm-12 col-md-6 px-sm-0">
                    <div class="about-image">
                        <div></div>
                    </div>
                </div>
            </div>
        </div>
    
        <!-- <div class="container-fluid py-5 px-5" id="sectionFive">
            <div class="mx-lg-5 px-lg-5" dir="rtl">
                <div class="row">
                    <div class="col-sm-12 col-md-7 mt-3" dir="ltr">
                        <p>
                            Going in one more round when you don’t think you can. That’s what
                            makes all the difference in your life.” – Rocky Balboa 
                        </p>
                    </div>
                    <div class="col-sm-12 col-md-5 mt-3">
                        <div class="pt-4 pl-4 pr-3 pb-3 h-100">
                            <img width="100%" height="100%" src="https://omar-sherbeni.com/assets/images/photo3.jpeg">
                        </div>
                    </div>
                </div>
            </div>
        </div> -->
    
    
        <!-- <div class="container-fluid" id="sectionSix">
            <div class="row" dir="rtl">
                <div class="col-sm-12 col-md-6 px-sm-0">
                    <div class="card px-5 pt-5 pb-2 mx-sm-2 my-sm-2">
                        <h1> ? What Do You Want to Know</h1>
                        <form>
                            <div class="row" dir="ltr">
                                <div class="col-6" dir="ltr">
                                    <input type="text" class="m-3 form-control" placeholder="Your Name">
                                </div>
                                <div class="col-6" dir="ltr">
                                    <input type="text" class="m-3 form-control" placeholder="Facebook Account">
                                </div>
                                <div class="col-6" dir="ltr">
                                    <input type="text" class="m-3 form-control" placeholder="Your Phone">
                                </div>
                                <div class="col-6" dir="ltr">
                                    <input type="text" class="m-3 form-control" placeholder="Your Subject">
                                </div>
                                <div class="col-12" dir="ltr">
                                    <textarea type="text" class="m-3 form-control" placeholder="Your Message" rows="9"></textarea>
                                </div>
                                <div class="col-12" dir="ltr">
                                    <input type="submit" class="text-center mt-3" value="Send Message">
                                </div> 
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-sm-12 col-md-6 px-0">
                    <img width="100%" src="https://omar-sherbeni.com/assets/images/contact.png"/>
                </div>
            </div>
        </div> -->';
 
        if($isadmin){
        $this->content->text .= '
        <script>
        $( document ).ready(function() {
            $("#sectionOnePrag").click(function(e){
                val = $("#sectionOnePrag").text();
              $("#sectionOnePrag").hide();
              $("#editsectionOnePrag").show();
                $("#editsectionOnePrag").val(val);
         
            });
            $("#editsectionOnePrag").blur(function(e){
                val = $("#editsectionOnePrag").val();
                $("#editsectionOnePrag").hide();
                $("#sectionOnePrag").show();
                $("#sectionOnePrag").text(val);
                $.ajax({
                    type: "POST",
                    url: "/ajax.php",
                     data: {  sectionOnePragValue:val ,teacherId:'.$_SESSION['userdata'].' },
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
                $("#editsection1left").hide();
                $("#section1left").show();
                $("#section1left").text(val);
                $.ajax({
                    type: "POST",
                    url: "/ajax.php",
                     data: {  section1leftValue:val ,teacherId:'.$_SESSION['userdata'].' },
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
                $("#editsectionOneHead").hide();
                $("#sectionOneHead").show();
                $("#sectionOneHead").text(val);
                $.ajax({
                    type: "POST",
                    url: "/ajax.php",
                     data: {  sectionOneHeadValue:val ,teacherId:'.$_SESSION['userdata'].' },
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
                $("#editPhone").hide();
                $("#phone").show();
                $("#phone").text(val);
                $.ajax({
                    type: "POST",
                    url: "/ajax.php",
                     data: {  editPhone:val ,teacherId:'.$_SESSION['userdata'].' },
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
                $("#editDivOne").hide();
                $("#divOne").show();
                $("#divOne").text(val);
                $.ajax({
                    type: "POST",
                    url: "/ajax.php",
                     data: {  divOne:val ,teacherId:'.$_SESSION['userdata'].',value:1 },
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
                $("#editDivTwo").hide();
                $("#divTwo").show();
                $("#divTwo").text(val);
                $.ajax({
                    type: "POST",
                    url: "/ajax.php",
                     data: {  divTwo:val ,teacherId:'.$_SESSION['userdata'].',value:1 },
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
                $("#editDivThree").hide();
                $("#divThree").show();
                $("#divThree").text(val);
                $.ajax({
                    type: "POST",
                    url: "/ajax.php",
                     data: {  divThree:val ,teacherId:'.$_SESSION['userdata'].',value:1 },
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
                $("#editDivFour").hide();
                $("#divFour").show();
                $("#divFour").text(val);
                $.ajax({
                    type: "POST",
                    url: "/ajax.php",
                     data: {  divFour:val ,teacherId:'.$_SESSION['userdata'].',value:1 },
                 success: function (data) {
                        console.log("yes");
                     }
                 }); 
            });

            
            $("#divFive").click(function(e){
                val = $("#divFive").text();
              $("#divFive").hide();
              $("#editDivFive").show();
                $("#editDivFive").val(val);
         
            });
            $("#editDivFive").blur(function(e){
                val = $("#editDivFive").val();
                $("#editDivFive").hide();
                $("#divFive").show();
                $("#divFive").text(val);
                $.ajax({
                    type: "POST",
                    url: "/ajax.php",
                     data: {  divFive:val ,teacherId:'.$_SESSION['userdata'].',value:1 },
                 success: function (data) {
                        console.log("yes");
                     }
                 }); 
            });
        });


        $("#facebookLink").click(function(e){
            var attr = $("#facebookLink").attr("href");
            if (typeof attr !== "undefined" || attr !== false) {
                $("#facebookLink").hide();
                $("#editFacebook").show();
                $("#editFacebook").blur(function(e){
                    val = $("#editFacebook").val();
                    $("#editFacebook").hide();
                    $("#facebookLink").show();
                    $("#facebookLink").text(val);
                    $.ajax({
                        type: "POST",
                        url: "/ajax.php",
                         data: {  facebookLink:val ,teacherId:'.$_SESSION['userdata'].',value:1 },
                     success: function (data) {
                            console.log("yes");
                         }
                     }); 
                });
            }

    });
        </script>
        ';}

            $this->content->text .= '';
        }


        return $this->content;
    }
    // The PHP tag and the curly bracket for the class definition 
    // will only be closed after there is another function added in the next section.
}
