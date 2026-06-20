<?php
require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/course/externallib.php');
require_once($CFG->dirroot . '/user/externallib.php');
require_once($CFG->libdir . "/weblib.php");
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/theme/edumy/ccn/block_handler/ccn_block_handler.php');
require_once($CFG->dirroot . '/course/renderer.php');
require_once($CFG->dirroot . '/theme/edumy/ccn/course_handler/ccn_course_handler.php');
require_once($CFG->dirroot . '/theme/edumy/ccn/user_handler/ccn_user_handler.php');

global $DB, $OUTPUT, $CFG, $USER;

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
    } elseif (!is_siteadmin()) {
        print_error('Only administrators can access this page.');
    }
} else if (!empty($CFG->forcelogin)) {
    require_login();
}


$error_message = null;

$users = $DB->get_records('user');
$users_count = count($users);

$courses = $DB->get_records_sql("SELECT * FROM {course}");
$courses_count = $DB->count_records_sql("SELECT COUNT(*) FROM {course}");

$Categories = $DB->get_records('course_categories');
$Categories_count = count($Categories);

/* $userData = get_complete_user_data('id', 4); */


$student_role = $DB->get_record('role', ['shortname' => 'student']);
$teacherRole = $DB->get_record('role', ['shortname' => 'editingteacher']);


if ($student_role) {
    // Next, find all users with the "teacher" role.
    $sql = "SELECT u.*
                FROM {user} u
                JOIN {role_assignments} ra ON u.id = ra.userid
                WHERE ra.roleid = :roleid";

    $params = array('roleid' => $student_role->id);

    $students = $DB->get_records_sql($sql, $params);
    $count_students = COUNT($students);
} else {
    $error_message = "Student role not found.";
}

if ($teacherRole) {
    // Next, find all users with the "teacher" role.
    $sql = "SELECT u.*
                FROM {user} u
                JOIN {role_assignments} ra ON u.id = ra.userid
                WHERE ra.roleid = :roleid";

    $params = array('roleid' => $teacherRole->id);

    $teachers = $DB->get_records_sql($sql, $params);
    $count_teachers = COUNT($teachers);
} else {
    $error_message = "Teacher role not found.";
}



?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <!-- Add Bootstrap CSS link -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Custom fonts for this template-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src='https://kit.fontawesome.com/a076d05399.js' crossorigin='anonymous'></script>
    <!-- Custom styles for this template-->
    <link href="./css/sb-admin-2.min.css" rel="stylesheet">
    <!-- fav icon -->
    <link rel="icon" type="image/x-icon" href="/images/favicon.ico">
    <!-- Add your custom CSS file (dashboard.css) -->
    <link rel="stylesheet" href="dashboard.css">

    <script src="https://appuals.com/wp-content/litespeed/localres/aHR0cHM6Ly9jb2RlLmpxdWVyeS5jb20vjquery-3.6.1.min.js" integrity="sha256-o88AwQnZB+VDvE9tvIXrMQaPlFFSUTR+nldQm1LuPXQ=" crossorigin="anonymous"></script>

</head>

<body id="page-top">
    <nav id="navbar" class="navbar navbar-expand-lg navbar-dark bg-primary">
        <a class="navbar-brand" href="#">Dashboard</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="./index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">Users</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="./activities.php">Activities</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="content mb-4">
        <!-- error message -->
        <div class="error_message"><?php echo $error_message ?></div>

        <!-- Counters -->
        <div class="container mt-4">
            <div class="row">

                <!-- Total Students Card Example -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Students</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $count_students ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fa fa-user fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Courses Card Example -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Total Courses</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $courses_count ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fa fa-graduation-cap fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Categories Card Example -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Categories</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $Categories_count ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fa fa-list-alt fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Teachers Card Example -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Total Teachers</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $count_teachers ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fa fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- all courses -->
        <div class="container mt-4">
            <h3 class="mb-4">All Courses</h3>
            <div class="row">
                <div class="slider">
                    <?php
                    foreach ($courses as $course) {
                        $ccnCourseHandler = new ccnCourseHandler();
                        $ccnCourse = $ccnCourseHandler->ccnGetCourseDetails($course->id);

                        $fullname = $course->fullname;
                        $desc = $course->summary;
                        $enrolid = $DB->get_field('enrol', 'id', array('enrol' => 'manual', 'courseid' => $course->id));
                        $userEnrolments = $DB->get_records('user_enrolments', array('enrolid' => $enrolid));
                        //print_r($ccnCourse);
                    ?>
                        <div class="course_card card" style="width: 18rem;">
                            <a class="card_hover" href="<?php echo $CFG->wwwroot ?>/course/view.php?id=<?php echo $course->id ?>">
                                <img class="card-img-top" src="<?php echo $ccnCourse->imageUrl ?>" alt="Card image cap">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo $fullname ?></h5>
                                    <div class="card-text">
                                        <?php if (strlen($desc) > 100) { ?>
                                            <p class="short-desc"><?php echo substr($desc, 0, 100); ?>...</p>
                                            <p class="full-desc" style="display: none;"><?php echo $desc; ?></p>
                                            <a href="#" class="readMore">Read More</a>
                                            <a href="#" class="readLess" style="display: none;">Read Less</a>
                                        <?php } else { ?>
                                            <p class="full-desc"><?php echo $desc; ?></p>
                                        <?php } ?>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- all teachers -->
        <div class="container mt-4">
            <h3 class="mb-4">All Teachers</h3>
            <div class="row" style="row-gap: 20px;">

                <?php
                // Now, $teachers contains an array of user data for all teachers.
                foreach ($teachers as $teacher) {
                    $ccnUserHandler = new ccnUserHandler();
                    $ccnUser = $ccnUserHandler->ccnGetUserDetails($teacher->id);
                    //print_r($ccnUser);
                    //echo $userId = $teacher->id . '<br>';
                    $fullName = $teacher->firstname . " " . $teacher->lastname;
                    $desc = $teacher->description;
                    $profileUrl = $ccnUser->profileUrl;
                    $rawAvatar = $ccnUser->rawAvatar;

                ?>
                    <div class="col-lg-4">
                        <div class="user_card p-0">
                            <div class="card-image">
                                <img src="<?php echo $rawAvatar ?>" alt="Avatar"> <!-- https://images.pexels.com/photos/2746187/pexels-photo-2746187.jpeg?auto=compress&cs=tinysrgb&dpr=1&w=500 -->
                            </div>

                            <div class="card-content d-flex flex-column align-items-center">
                                <a class="teacher_brofile_link" href="<?php echo $profileUrl ?>">
                                    <h4 class="pt-2" style="color: black;text-decoration: none;"><?php echo $fullName ?></h4>
                                    <h5 style="color: black;"><?php echo substr($desc, 0, 70); ?></h5>
                                </a>

                                <ul class="social-icons d-flex justify-content-center">
                                    <li style="--i:1">
                                        <a href="#">
                                            <span class="fa fa-facebook" style="color: black;"></span>
                                        </a>
                                    </li>
                                    <li style="--i:2">
                                        <a href="#">
                                            <span class="fa fa-twitter" style="color: black;"></span>
                                        </a>
                                    </li>
                                    <li style="--i:3">
                                        <a href="#">
                                            <span class="fa fa-instagram" style="color: black;"></span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                <?php
                }
                ?>

            </div>
        </div>


    </div>

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fa fa-angle-up"></i>
    </a>

    <!-- Read more script -->
    <script>
        document.querySelectorAll('.readMore').forEach(function(readMoreLink) {
            readMoreLink.addEventListener('click', function(event) {
                event.preventDefault();
                var shortDesc = this.parentElement.querySelector('.short-desc');
                var fullDesc = this.parentElement.querySelector('.full-desc');
                var readLessLink = this.parentElement.querySelector('.readLess');

                shortDesc.style.display = 'none';
                fullDesc.style.display = 'block';
                readLessLink.style.display = 'inline';
                this.style.display = 'none';
            });
        });

        document.querySelectorAll('.readLess').forEach(function(readLessLink) {
            readLessLink.addEventListener('click', function(event) {
                event.preventDefault();
                var shortDesc = this.parentElement.querySelector('.short-desc');
                var fullDesc = this.parentElement.querySelector('.full-desc');
                var readMoreLink = this.parentElement.querySelector('.readMore');

                shortDesc.style.display = 'block';
                fullDesc.style.display = 'none';
                readMoreLink.style.display = 'inline';
                this.style.display = 'none';
            });
        });
    </script>

    <!-- sticky navbar script -->
    <script>
        window.onscroll = function() {
            myFunction()
        };

        var navbar = document.getElementById("navbar");
        var sticky = navbar.offsetTop;

        function myFunction() {
            if (window.pageYOffset >= sticky) {
                navbar.classList.add("sticky")
            } else {
                navbar.classList.remove("sticky");
            }
        }
    </script>



    <!-- Add Bootstrap JS and jQuery scripts at the end of the body -->
    <!-- <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script> -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.1/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <!-- scripts -->
    <!-- Bootstrap core JavaScript-->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="js/sb-admin-2.min.js"></script>

    <!-- Page level custom scripts -->
    <script src="js/demo/datatables-demo.js"></script>
</body>

</html>