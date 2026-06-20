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
        $this->title = '';
    }
    public function get_content()
    {
        global $DB, $CFG, $USER, $PAGE;
        require_once($CFG->dirroot . '/lib/enrollib.php');

        $PAGE->requires->css(new moodle_url($CFG->wwwroot . '/blocks/teacher/styles.css'));
        $currentcss2 = '/blocks/teacher/styles.css';

        $PAGE->requires->css($currentcss2, true);

        $this->content = new stdClass();

        $this->content->text = '';
        // $this->content->text =  $_SESSION['userdata'];
        $courses=enrol_get_users_courses($_SESSION['userdata']);
        $userData=$DB->get_record('teacher_styles',array('teacher_id'=>$_SESSION['userdata']));
        if(!empty($_SESSION['userdata'])){
            $user = $DB->get_record('user', array('id' => $_SESSION['userdata']));
            $this->content->text .= "<style>
         
            .navbar-expand-lg{
                background: #303235 !important;
            }

            
            #footer{background:#000 !important;}
            #footer div{
                font-size: 22px !important;
                text-align:center;color:var(--white);font-weight:500;padding:25px 0px;
                font-family: 'Cairo', sans-serif !important;
            }
            #footer div a{
                color:var(--white);
            }
            #footer div a:hover{color:var(--orange);}
            :root{
    
            }
            
            #region-main{margin:0px;padding: 0px !important;overflow-x:hidden;}
            a{text-decoration:none!important;}
            
            
            .navbar-expand-lg .navbar-nav {
                flex-direction: row;
                direction: rtl !important;
            }
        
            
            
            
            
            .carousel-inner .carousel-item:first-of-type{
                height:870px !important;
                background-image:url('".$userData->src."');
                background-size:cover;background-repeat:no-repeat;
            }
            .carousel-inner .carousel-item:nth-of-type(2){
                height:870px !important;
                background-image:url('https://omar-sherbeni.com/assets/images/Cover-site1.jpg');
                background-size:cover;background-repeat:no-repeat;
            }
            .carousel-inner .carousel-item:first-of-type .content ,
            .carousel-inner .carousel-item:nth-of-type(2) .content{
                padding: 200px 0px  250px 0px!important;background:rgba(33, 37, 41,0.6) !important;
                width:100%;height:100%;justify-content:center;align-items:center;
                display:flex;flex-direction:column !important;padding: 100px 0px !important;
            }
            .carousel-inner .carousel-item:first-of-type .content h1,
            .carousel-inner .carousel-item:nth-of-type(2) .content h1{
                font-size:75px !important;color:var(--white);font-family: playfair display,serif;
                font-weight:700;margin-bottom: 0px !important;
            }
            .carousel-inner .carousel-item:first-of-type .content p,
            .carousel-inner .carousel-item:nth-of-type(2) .content p{
                color:var(--white);font-size:23px !important;font-family:poppins,sans-serif;
                padding:0px 16%;margin-top: 20px;
            }
            .carousel-inner .carousel-item:first-of-type .content div .btn:first-of-type,
            .carousel-inner .carousel-item:nth-of-type(2) .content div .btn:first-of-type{
                background:var(--white);padding: 20px 60px;margin:20px 10px !important ;
                border: 2px solid var(--white);
            }
            .carousel-inner .carousel-item:first-of-type .content div .btn:first-of-type:hover,
            .carousel-inner .carousel-item:first-of-type .content div .btn:nth-of-type(2):hover,
            .carousel-inner .carousel-item:nth-of-type(2) .content div .btn:first-of-type:hover,
            .carousel-inner .carousel-item:nth-of-type(2) .content div .btn:nth-of-type(2):hover{
                background:#eb5f2e;border: 2px solid #eb5f2e;
            }
            .carousel-inner .carousel-item:first-of-type .content div .btn:first-of-type:hover a ,
            .carousel-inner .carousel-item:first-of-type .content div .btn:nth-of-type(2):hover a,
            .carousel-inner .carousel-item:nth-of-type(2) .content div .btn:first-of-type:hover a ,
            .carousel-inner .carousel-item:nth-of-type(2) .content div .btn:nth-of-type(2):hover a{
                color:var(--white);
            }
            .carousel-inner .carousel-item:first-of-type .content div .btn:nth-of-type(2):hover,
            .carousel-inner .carousel-item:nth-of-type(2) .content div .btn:nth-of-type(2):hover{
                border:2px solid #eb5f2e;
            }
            .carousel-inner .carousel-item:first-of-type .content div .btn:first-of-type a,
            .carousel-inner .carousel-item:nth-of-type(2) .content div .btn:first-of-type a{
                font-size:20px !important;color:var(--orange);font-weight: 600 !important;
            }
            .carousel-inner .carousel-item:first-of-type .content div .btn:nth-of-type(2),
            .carousel-inner .carousel-item:nth-of-type(2) .content div .btn:nth-of-type(2){
                background:transparent;border:2px solid var(--white);padding: 20px 40px;
                margin:10px;
            }
            .carousel-inner .carousel-item:first-of-type .content div .btn:nth-of-type(2) a,
            .carousel-inner .carousel-item:nth-of-type(2) .content div .btn:nth-of-type(2) a{
                font-size:20px !important;color:var(--white);font-weight: 600 !important;
            }
            .carousel-control-prev{margin-left:20px;}
            .carousel-control-next{margin-right:20px;}
            .carousel-control-prev, .carousel-control-next {
                position: absolute;
                top: 50%;
                z-index: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-direction:column;
                border:none !important;
                width: 60px;
                height:60px;
                color: var(--whitee) !important;
                text-align: center;
                opacity: .5;background:var(--white) !important;
                border-radius:50%;
            }
            .bi-caret-left-fill,.bi-caret-right-fill{color:var(--orange) !important;font-size:30px !important;}
            
            
            
            
            .service-area .card{
                background-image:url('https://omar-sherbeni.com/assets/images/servicebg.png');
                align-items:center;background-repeat:no-repeat;background-size:cover;
                height:340px ;padding:50px 10px;text-align:center;box-shadow: 2px 2px 20px 4px rgba(0,0,0,.07);
                border-radius:10px !important;
            }
            .service-area .card:hover{
                margin-top:-10px !important;
                transition: .3s;
            }
            .service-area .card .iconContainer{
                height:60px;
                width:60px;
                justify-content:center;align-items:center;display:flex;flex-direction:column;
                background:var(--orange);color:var(--white);
            }
            .service-area .card .iconContainer .bi-telephone-fill , .service-area .card .iconContainer .bi-facebook
            ,.service-area .card .iconContainer .bi-people-fill{
                font-size:25px !important;
            }
            .service-area .card h2{
                font-size: 26px !important;margin-top:20px;
                color: #181818 !important;font-weight:500;
                margin-bottom: 5px !important;
            }
            .service-area .card p{color: #4f4f4f;font-size:18px !important;margin-top:5px;}
            .service-area .card .p,.service-area .card .p a{
                font-size:25px !important;
            }
            .service-area .card .p a{color:var(--orange);text-decoration:underline !important;}
            
            
            
            #sectionThree{
                margin-top: 100px !important;
                background-image:url('https://omar-sherbeni.com/assets/images/shape.png') !important;
                background-size:cover !important;
            }
            #sectionThree .row{margin-top:75px;}
            #sectionThree .course{box-shadow: 2px 2px 20px 4px rgba(0,0,0,0.1);}
            #sectionThree .course:hover{margin-top:-15px;transition: 0.3s;}
            #sectionThree div h2{font-size:50px !important;font-weight:500!important;}
            #sectionThree .courseImg{
                background-image:url('https://omar-sherbeni.com/assets/images/sec1.jpeg')!important;
                background-size:cover !important;background-repeat:no-repeat !important;height:350px;
                border-radius:0px 0px 40% 40%;
            }
            #sectionThree .courseImg div{
                background:rgba(235, 95, 46,0.7)!important;height:100%;
                justify-content:center;align-items:center;display:flex;
                flex-direction:column;text-align:center;border-radius:0px 0px 40% 40%;
            }
            #sectionThree .courseImg div h2{line-height:50px;color:var(--white);}
            #sectionThree .course .course-content{padding:30px;text-align:center;}
            #sectionThree .course .course-content p{
                font-size:24px !important;line-height: 1.5;color:#616161;padding:0px 10px;
            }
            #sectionThree .course .course-content button{
                border:2px solid var(--orange);padding:15px 30px !important;background:var(--white);
            }
            #sectionThree .course .course-content button a{
                color:var(--orange);font-size:22px!important;
            }
            #sectionThree .course .course-content button:hover{background:var(--orange);}
            #sectionThree .course .course-content button:hover a{color:var(--white);}
            
            
            #sectionFour{margin-top:100px !important;}
            #sectionFour .about-image{background:var(--orange);height:100%;border-radius:10px;}
            #sectionFour .about-image div{
                background-image:url('https://omar-sherbeni.com/assets/images/about4.png');
                background-size:cover;background-repeat:no-repeat;
                height:100%;margin-left:15px!important;margin-right:-15px !important;
                border-radius:10px !important;
            }
            #sectionFour h2{font-size:60px !important;font-weight:500;}
            #sectionFour p{
                font-size:22px !important;color:#444343!important;
                line-height:28px!important;font-weight:500;
            }
            #sectionFour .col-1 .bi-check-circle-fill{
                color:var(--move) !important;font-size: 22px !important;
            }
            
            #sectionFive{background:var(--babyBlue)!important;}
            #sectionFive div .row{
                background:#121624 !important;border-radius:30px;
            }
            #sectionFive div .row .col-sm-12:nth-of-type(1){
                justify-content:center;display:flex;flex-direction:column;
            }
            #sectionFive div .row .col-sm-12:nth-of-type(1) p{
              font-size:40px !important;color:var(--white);
              font-weight:600;max-width:75%;line-height:45px;
              font-family: poppins,sans-serif;
            }
            #sectionFive div .row .col-sm-12:nth-of-type(2) div img{
                border-radius:30px;
            }
            
            
            #sectionSix{margin-top:200px;}
            #sectionSix .card{
                background-image:url('https://omar-sherbeni.com/assets/images/contact-bg.png');
                border-radius:50px;
            }
            #sectionSix .card h1{
                font-size:50px;font-weight:600;font-family: 'Playfair Display', serif;
            }
            input.form-control{
                height: 60px !important;
                border-radius: 90px;
                border: 1px solid #c0ccff;
                margin-bottom: 20px;
                padding: 35px 0px 35px 20px;
            }
            textarea{
                border-radius: 30px !important;
                border: 1px solid #c0ccff !important;
                margin-bottom: 20px;
                padding: 15px 0px 35px 20px !important;
                resize:none !important
            }
            input[type='submit']{
                border-radius: 100px;
                border: 1px solid var(--orange);
                margin-bottom: 20px;
                padding: 18px 30px;
                background: var(--orange);
                color:var(--white);
                font-size:20px;font-weight:500;
            }
            input[type='submit']:hover{background:var(--white);color:var(--orange);}
            textarea:focus{
                border: 1px solid var(--orange) !important;
                outline:none !important;box-shadow:none !important;
            }
            input.form-control:focus{
                border: 1px solid var(--orange);
                outline:none !important;box-shadow:none !important;
            }
            ::placeholder{font-size:22px;color:#495057;}
            
            
            
            
            
            #sectionSeven{
                background-image:url('https://omar-sherbeni.com/assets/images/footer-area.png');
                margin-top:100px;
            }
            #sectionSeven p{font-family:poppins,sans-serif;}
            #sectionSeven .row div:nth-child(3) div p{font-size:22px;font-weight:600;margin-top:20px;}
            #sectionSeven h2{font-size:40px !important;font-weight:600 !important;font-family:poppins,sans-serif;}
            #sectionSeven ul li{
                list-style-type:none;font-size:22px;margin-top:10px;font-weight:500;
            }
            #sectionSeven ul li a{color:#fff;font-family:poppins,sans-serif;}
            
            
            
            
            
            
            
            
            
            
            
            @media (min-width: 768px) {
                .service-area{margin-top:-100px !important}
                #sectionFour .about-image{margin-left:-40px !important;}
                #sectionSix .card{
                    margin: -50px 100px 0px -50px !important;z-index:2;
                }
                .navbar-expand-lg{padding: 40px 0px !important;}
            }
            @media (max-width: 800px) {
                .service-area{margin-top:40px !important}
                .carousel-inner .carousel-item .content h1{
                    font-size:40px !important;color:#fff;font-family: playfair display,serif;
                    font-weight:700;margin-bottom: 0px !important;margin-top:70px;
                }
                #sectionFour .about-image{height:400px !important;}
                
            } </style>";
            $this->content->text .= '
            
            
     
    
        <div id="carouselExampleControls" class="carousel slide mx-0 px-0" data-ride="carousel" data-interval="false">
            <div class="carousel-inner conatiner-fluid mx-0 px-0 ">
                <div class="carousel-item active row mx-0 px-0">
                    <div class="content text-center">
                       <h1>'.$user->firstname.' '.$user->lastname.' <br> Mathematics Platform</h1>
                       <p>Our aim is providing the high school students the sufficient training to be qualified for sitting their final exams. Hope we change and improve your way of thinking in our journey by various techniques of teaching in order to learn math with love and fun.</p>
               
                    </div>
                </div>
    
                <div class="carousel-item row mx-0 px-0">
                    <div class="content text-center">
                        <h1>We Focus on Your <br> Student Development</h1>
                        <p>We seek to improve the students" personal skills and aquire them new ones to accomplish their goals .</p>
               
                    </div>
                </div>
            </div>
            <button class="carousel-control-prev" type="button" data-target="#carouselExampleControls" data-slide="prev">
                <i class="bi bi-caret-left-fill" aria-hidden="true"></i>
                <span class="sr-only">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-target="#carouselExampleControls" data-slide="next">
                <i class="bi bi-caret-right-fill" aria-hidden="true"></i>
                <span class="sr-only">Next</span>
            </button>
        </div>
    
    
        <div class="container-fluid px-5" id="sectionTwo">
            <div class="row mx-5" >
                <div class="col-sm-12 col-md-4 col-lg-4 mt-sm-2 mt-md-2 mt-sm-3 mt-lg service-area">
                    <div class="card">
                        <div class="iconContainer rounded-circle">
                            <i class="bi bi-telephone-fill"></i>
                        </div>
                        <h2>Phone / Whatsappppp</h2>
                        <p class="p" dir="ltr">+20 144 608 6066</p>
                    </div>
                </div>
    
                <div class="col-sm-12 col-md-4 col-lg-4 mt-sm-2 mt-md-2 mt-lg service-area">
                    <div class="card">
                        <div class="iconContainer rounded-circle">
                            <img src="'.$CFG->wwwroot.'/blocks/teacher/facebook.png" width="35px" height="35px">
                        </div>
                        <h2>Facebook</h2>
                        <p class="p"><a href="#">'.$user->firstname.' '.$user->lastname.'</a></p>
                    </div>
                </div>
    
                <div class="col-sm-12 col-md-4 col-lg-4 mt-sm-2 mt-md-2 mt-lg service-area">
                    <div class="card">
                        <div class="iconContainer rounded-circle">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <h2>Mathematician Team</h2>
                        <p>Our team is composed of more than 70 qualified engineers and teachers. All of them will always do their best to provide you with the required support</p>
                    </div>
                </div>
            </div>
        </div>
    
        <div class="container-fluid px-5" id="sectionThree">
            <div class="text-center"><h2>Our Regular Courses</h2></div>
            <div class="row mx-5" >';
            foreach ($courses as $course) {
                // $this->content->text .=$course->id;
            
              $this->content->text .='    <div class="col-sm-12 col-md-4 my-3">
                    <div class="course">
                        <div class="courseImg">
                            <div>
                           <a href= "'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'"> <h2>'.$course->fullname.'</h2>
                            </div>
                        </div>
                        <div class="course-content py-4">
                            <p>
                                FHD videos , live sessions , online Quizzes , personal assistant and high standard of thinking Question
                            </p>
                            <button class="rounded-pill mt-3">
                                <a href="">Read More</a>
                            </button>
                        </div>
                    </div>
                </div>
    ';}
    $this->content->text .='          
    
           
            </div>
        </div>
    
        <div class="container-fluid" id="sectionFour">
            <div class="row" >
                <div class="col-sm-12 col-md-6">
                    <div class="pl-md-5">
                        <h2 class="ml-4">About Our System ? </h2>
                        <ul>
                            <li class="row">
                                <div class="col-1 text-right px-2">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div class="col-11 px-1">
                                    <p>
                                        Online videos with high quality: <br>
                                        High resolution which offers you a clear comfortable watching and A flexible way of teaching which aims all the levels of thinking .
                                    </p>
                                </div>
                            </li>
                            <li class="row">
                                <div class="col-1 text-right px-2">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div class="col-11 px-1">
                                    <p>
                                        Online quizzes:<br>
                                        To rate yourself and to fill the gaps in your understanding
                                    </p>
                                </div>
                            </li>
                            <li class="row">
                                <div class="col-1 text-right px-2">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div class="col-11 px-1">
                                    <p>
                                        live classes: <br>
                                        To keep in contact with you
                                    </p>
                                </div>
                            </li>
                            <li class="row">
                                <div class="col-1 text-right px-2">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div class="col-11 px-1">
                                    <p>
                                        personal assistant:<br>
                                        To help in solving any difficulties
                                    </p>
                                </div>
                            </li>
                            <li class="row">
                                <div class="col-1 text-right px-2">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div class="col-11 px-1">
                                    <p>
                                        provide you with high standards of thinking questions:<br>
                                        To inspire your mind with different ideas
                                    </p>
                                </div>
                            </li>
                            <li class="row">
                                <div class="col-1 text-right px-2">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div class="col-11 px-1">
                                    <p>
                                        monthly competitions:<br>
                                        To create a charged and exciting atmospheres
                                    </p>
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
    
        <div class="container-fluid py-5 px-5" id="sectionFive">
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
        </div>
    
    
        <div class="container-fluid" id="sectionSix">
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
        </div>';
    if($user->lang=='ar' || $_GET['lang']=="ar"){
        $class="bi-chevron-left";
    }
    else{
        $class="bi-chevron-right";

    }
    
        $this->content->text .=' <div class="container-fluid" id="sectionSeven">
            <div class="p-sm-2 p-md-5 ">
                <div class="row px-md-5 py-md-3 text-white" >
                    <div class="col-sm-12 col-md-3 my-sm-3">
                        <h2>Find Us</h2>
                        <ul>
                            <li><a href="https://www.facebook.com/omar.mohsnsherbeni" target="_blank">Facebook Account <i class="bi '.$class.'"></i></a></li>
                            <li><a href="https://www.facebook.com/groups/2672994876159471/?ref=share" target="_blank">Facebook Group <i class="bi '.$class.'"></i></a></li>
                            <li><a href="#">  01146086066<i class="bi '.$class.'"></i></a></li>
                        </ul>
                    </div>
                    <div class="col-sm-12 col-md-3 my-sm-3">
                        <h2>Quick Links</h2>
                        <ul>
                            <li><a href="#">Home <i class="bi '.$class.'"></i></a></li>
                            <li><a href="register.php" target="_self">Registration <i class="bi '.$class.'"></i></a></li>
                            <li><a href="signin.php" target="_self">Sign In <i class="bi '.$class.'"></i></a></li>
                        </ul>
                    </div>
                    <div class="col-sm-12 col-md-6 my-sm-3 px-md-5">
                        <div>
                            <img src="https://omar-sherbeni.com/assets/images/logow.png" width="100%" />
                            <p class="text-white text-center">.Feel free to contact us at any time</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    
        <div class="container-fluid" id="footer">
            <div>
                Copyright @2020 '.$user->firstname.' '.$user->lastname.'. All Rights Reserved by 
                <a href="https://www.facebook.com/onxameg">ONXAM</a>
            </div>
        </div>';
        }
       

        return $this->content;
    }
    // The PHP tag and the curly bracket for the class definition 
    // will only be closed after there is another function added in the next section.
}
