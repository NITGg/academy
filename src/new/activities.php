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

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activities</title>
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

        <!-- all activities -->
        <div class="container-fluid mt-5">

            <!-- ccc -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Activities DataTable</h6>
                    <div class="col-sm-12 col-md-6">
                        <div id="dataTable_filter" class="dataTables_filter">
                            <form method="get" id="activity-form">
                                <div class="mb-3">
                                    <label class="form-label">Module Type:
                                        <select class="form-control" name="activity" aria-label="Choose an activity">
                                            <option selected disabled>Choose an activity</option>
                                            <option value="url">URL</option>
                                            <option value="resource2">Vimeo</option>
                                            <option value="page">Page</option>
                                            <option value="pdfannotator">Pdf</option>
                                        </select>
                                    </label>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Course Name:
                                        <select class="form-control" name="course_id" aria-label="Choose an course">
                                            <option selected disabled>All Courses</option>
                                            <?php
                                            $courses = $DB->get_records('course');
                                            foreach ($courses as $course) {
                                            ?>
                                                <option value="<?php echo $course->id ?>"><?php echo $course->fullname ?></option>
                                            <?php
                                            }
                                            ?>
                                        </select>
                                    </label>
                                </div>
                                <!-- <div class="mb-3">
                                    <label class="form-label">Show:
                                        <select class="form-control" name="num" aria-label="Choose an course">
                                        <option selected disabled>Choose an activity</option>
                                            <option value="10">10</option>
                                            <option value="30">30</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                            <option value="500">500</option>
                                        </select>
                                    </label>
                                </div> -->
                                <button class="btn btn-primary" type="submit">Submit</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                            <caption id="activity_results">Count of <strong id="activitie_name"></strong> : <span id="activity_count"></span></caption>
                            <thead>
                                <tr>
                                    <th scope="th-sm">#</th>
                                    <th scope="th-sm">Mod_id</th>
                                    <th scope="col">Course</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Module_type</th>
                                    <th scope="col">Topic</th>
                                    <th scope="col">Link</th>
                                </tr>
                            </thead>
                            <tbody id="activity_table">
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <!-- <nav>
                        <ul class="pagination justify-content-center">
                            <li class="page-item disabled">
                                <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
                            </li>
                            <li class="page-item active" aria-current="page">
                                <a class="page-link" href="#1">1</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="#2">2</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="#3">3</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="#">Next</a>
                            </li>
                        </ul>
                    </nav> -->
                </div>
            </div>


            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script>
                $(document).ready(function() {
                    $("#activity-form").submit(function(event) {
                        event.preventDefault(); // Prevent the form from being submitted in the traditional way

                        var selectedActivity = $("select[name='activity']").val(); // Get the selected activity value
                        var selectedCourse = $("select[name='course_id']").val();
                        var selectedRecordNum = $("select[name='num']").val();

                        //alert(selectedRecordNum);

                        $.ajax({
                            type: "GET",
                            url: "./your_server_url.php",
                            data: {
                                activity: selectedActivity,
                                course_id: selectedCourse,
                                num: selectedRecordNum
                            },
                            dataType: 'json',
                            success: function(response) {
                                // Update the content of the activity-results div with the response
                                $("#activity_count").text(response.count); // Update the activity count
                                $("#activitie_name").text(response.activitie_name);
                                $("#activity_table").html(response.html); // Update the table content
                            },
                            error: function() {
                                $("#activity_results").html("An error occurred.");
                            }
                        });
                    });
                });
            </script>
        </div>



    </div>

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fa fa-angle-up"></i>
    </a>


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