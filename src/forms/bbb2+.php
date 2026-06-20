<?php
require_once('../config.php');
$PAGE->set_pagelayout('site');
$PAGE->set_title("Add Permissions for Big Blue Button sessions");
$PAGE->set_heading("Add Permissions for Big Blue Button sessions");
$PAGE->requires->js(new moodle_url($CFG->wwwroot . '/forms/script.js'));
echo $OUTPUT->header();
$teacher_id = $_GET['teacher'];
$course = $_GET['course'];
$admins = get_admins();
$isadmin = false;
foreach ($admins as $admin) {
    if ($USER->id == $admin->id) {
        $isadmin = true;
        break;
    }
}
$roleassignments = $DB->get_records('role_assignments', ['userid' => $USER->id]);
$manager = 0;
foreach ($roleassignments as $role) {
    if ($role->roleid == 1) {
        $manager = 1;
        break;
    }
}
if ($isadmin || $manager == 1) {
    if ($course != "all") {
        $activity = $DB->get_record('control_activities', array('course' => $course, 'teacher_id' => $teacher_id));

        if ($activity->quiz == 1) {
            echo '<script>
        $( document ).ready(function() {
            $("#checked1").prop("checked", true);

        });
        </script>
        ';
        }
        if ($activity->assign == 1) {
            echo '<script>
        $( document ).ready(function() {
            $("#checked2").prop("checked", true);

        });
        </script>
        ';
        }
        if ($activity->page == 1) {
            echo '<script>
        $( document ).ready(function() {
            $("#checked3").prop("checked", true);

        });
        </script>
        ';
        }
        if ($activity->file2 == 1) {
            echo '<script>
        $( document ).ready(function() {
            $("#checked4").prop("checked", true);

        });
        </script>
        ';
        }
        if ($activity->bulk == 1) {
            echo '<script>
        $( document ).ready(function() {
            $("#checked5").prop("checked", true);

        });
        </script>
        ';
        }
        if ($activity->pdf == 1) {
            echo '<script>
        $( document ).ready(function() {
            $("#checked6").prop("checked", true);

        });
        </script>
        ';
        }
        if ($activity->url == 1) {
            echo '<script>
        $( document ).ready(function() {
            $("#checked7").prop("checked", true);

        });
        </script>
        ';
        }
        echo '<form action="bbb2.php?teacher=' . $teacher_id . '&course=' . $course . '" method="post" id="first_form">
    <!-- <div class="form-check  ">
    <input type="checkbox" name="enable_bbb"   class="form-check-label"  id="checked">
    <label class="form-check-label" >
    ' . get_string('enable_bbb', 'theme_edumy') . '
      </label>
    </div>-->
    <div class="form-check  ">
    <input type="checkbox" name="enable_quiz"   class="form-check-label"  id="checked1">
    <label class="form-check-label" >
    ' . get_string('enable_quiz', 'theme_edumy') . '
      </label>
    </div>
    <div class="form-check  ">
    <input type="checkbox" name="enable_assign"   class="form-check-label"  id="checked2">
    <label class="form-check-label" >
    ' . get_string('enable_assign', 'theme_edumy') . '
      </label>
    </div>
    <div class="form-check  ">
    <input type="checkbox" name="enable_page"   class="form-check-label"  id="checked3">
    <label class="form-check-label" >
    ' . get_string('enable_page', 'theme_edumy') . '
      </label>
    </div>
    <div class="form-check  ">
    <input type="checkbox" name="enable_file2"   class="form-check-label"  id="checked4">
    <label class="form-check-label" >
    ' . get_string('enable_file2', 'theme_edumy') . '
      </label>
    </div>
    <div class="form-check  ">
    <input type="checkbox" name="enable_bulk"   class="form-check-label"  id="checked5">
    <label class="form-check-label" >
    ' . get_string('bulk', 'theme_edumy') . '
      </label>
    </div>
    <div class="form-check  ">
    <input type="checkbox" name="enable_pdf"   class="form-check-label"  id="checked6">
    <label class="form-check-label" >
    ' . get_string('pdf', 'theme_edumy') . '
      </label>
    </div>

    <div class="form-check  ">
    <input type="checkbox" name="enable_url"   class="form-check-label"  id="checked7">
    <label class="form-check-label" >
    ' . get_string('url', 'theme_edumy') . '
      </label>
    </div>
    <div class="text-center"><button class="btn btn-primary" type="submit" name="button1" id="submit_teacher">' . get_string('save', 'theme_edumy') . '</button>
    <a class="btn btn-success" href="' . $CFG->wwwroot . '/forms/bbb.php">' . get_string('back', 'theme_edumy') . '</a>
    </div>
    </form>';


        if (isset($_POST['button1'])) {
            $ins = new stdClass();
            $ins1 = new stdClass();
            $bbb = $_POST['enable_bbb'];
            $quiz = $_POST['enable_quiz'];
            $assign = $_POST['enable_assign'];
            $page = $_POST['enable_page'];
            $file2 = $_POST['enable_file2'];
            $bulk = $_POST['enable_bulk'];
            $pdf = $_POST['enable_pdf'];
            $url = $_POST['enable_url'];
            if (!empty($activity)) {
                $ins1->id = $activity->id;
                if (empty($quiz)) {
                    $ins1->quiz = 0;
                }
                if (!empty($quiz)) {
                    $ins1->quiz = 1;
                }
                if (empty($assign)) {
                    $ins1->assign = 0;
                }
                if (!empty($assign)) {
                    $ins1->assign = 1;
                }
                if (empty($page)) {
                    $ins1->page = 0;
                }
                if (!empty($page)) {
                    $ins1->page = 1;
                }
                if (empty($file2)) {
                    $ins1->file2 = 0;
                }
                if (!empty($file2)) {
                    $ins1->file2 = 1;
                }
                if (empty($bulk)) {
                    $ins1->bulk = 0;
                }
                if (!empty($bulk)) {
                    $ins1->bulk = 1;
                }
                if (empty($pdf)) {
                    $ins1->pdf = 0;
                }
                if (!empty($pdf)) {
                    $ins1->pdf = 1;
                }
                if (empty($url)) {
                    $ins1->url = 0;
                }
                if (!empty($url)) {
                    $ins1->url = 1;
                }
                $DB->update_record('control_activities', $ins1);
            }
            if (empty($activity)) {
                $ins1->course = $course;
                $ins1->teacher_id = $teacher_id;
                if (empty($quiz)) {
                    $ins1->quiz = 0;
                }
                if (!empty($quiz)) {
                    $ins1->quiz = 1;
                }
                if (empty($assign)) {
                    $ins1->assign = 0;
                }
                if (!empty($assign)) {
                    $ins1->assign = 1;
                }
                if (empty($page)) {
                    $ins1->page = 0;
                }
                if (!empty($page)) {
                    $ins1->page = 1;
                }
                if (empty($file2)) {
                    $ins1->file2 = 0;
                }
                if (!empty($file2)) {
                    $ins1->file2 = 1;
                }
                if (empty($bulk)) {
                    $ins1->bulk = 0;
                }
                if (!empty($bulk)) {
                    $ins1->bulk = 1;
                }
                if (empty($pdf)) {
                    $ins1->pdf = 0;
                }
                if (!empty($pdf)) {
                    $ins1->pdf = 1;
                }
                if (empty($url)) {
                    $ins1->url = 0;
                }
                if (!empty($url)) {
                    $ins1->url = 1;
                }
                $DB->insert_record('control_activities', $ins1);
            }
            unset($_SESSION["teacher_id"]);
            unset($_SESSION["course_data"]);
            redirect($CFG->wwwroot . '/forms/bbb.php');
        }
    } else {
        $courses = $DB->get_records_sql('SELECT distinct c.id as id
        FROM mdl_course as c, mdl_role_assignments AS ra, mdl_user AS u, mdl_context AS ct
        WHERE c.id = ct.instanceid AND ra.roleid =3 AND ra.userid = u.id AND ct.id = ra.contextid AND u.id=' . $teacher_id . ' ');
        // var_dump($courses);
        echo '<form action="bbb2.php?teacher=' . $teacher_id . '&course=all" method="post" id="first_form">
           <!-- <div class="form-check  ">
           <input type="checkbox" name="enable_bbb"   class="form-check-label"  id="checked">
           <label class="form-check-label" >
           ' . get_string('enable_bbb', 'theme_edumy') . '
             </label>
           </div>-->
           <div class="form-check  ">
           <input type="checkbox" name="enable_quiz"   class="form-check-label"  id="checked1">
           <label class="form-check-label" >
           ' . get_string('enable_quiz', 'theme_edumy') . '
             </label>
           </div>
           <div class="form-check  ">
           <input type="checkbox" name="enable_assign"   class="form-check-label"  id="checked2">
           <label class="form-check-label" >
           ' . get_string('enable_assign', 'theme_edumy') . '
             </label>
           </div>
           <div class="form-check  ">
           <input type="checkbox" name="enable_page"   class="form-check-label"  id="checked3">
           <label class="form-check-label" >
           ' . get_string('enable_page', 'theme_edumy') . '
             </label>
           </div>
           <div class="form-check  ">
           <input type="checkbox" name="enable_file2"   class="form-check-label"  id="checked4">
           <label class="form-check-label" >
           ' . get_string('enable_file2', 'theme_edumy') . '
             </label>
           </div>
           <div class="form-check  ">
           <input type="checkbox" name="enable_bulk"   class="form-check-label"  id="checked5">
           <label class="form-check-label" >
           ' . get_string('bulk', 'theme_edumy') . '
             </label>
           </div>
           <div class="form-check  ">
           <input type="checkbox" name="enable_pdf"   class="form-check-label"  id="checked6">
           <label class="form-check-label" >
           ' . get_string('pdf', 'theme_edumy') . '
             </label>
           </div>
       
           <div class="form-check  ">
           <input type="checkbox" name="enable_url"   class="form-check-label"  id="checked7">
           <label class="form-check-label" >
           ' . get_string('url', 'theme_edumy') . '
             </label>
           </div>
           <div class="text-center"><button class="btn btn-primary" type="submit" name="button1" id="submit_teacher">' . get_string('save', 'theme_edumy') . '</button>
           <a class="btn btn-success" href="' . $CFG->wwwroot . '/forms/bbb.php">' . get_string('back', 'theme_edumy') . '</a>
           </div>
           </form>';
        $count = sizeof($courses);
        $i = 0;
        foreach ($courses as $data) {
            $course = $data->id;
            $activity = $DB->get_record('control_activities', array('course' => $course, 'teacher_id' => $teacher_id));


            if ($activity->quiz == 1) {
                echo '<script>
            $( document ).ready(function() {
                $("#checked1").prop("checked", true);
    
            });
            </script>
            ';
            }
            if ($activity->assign == 1) {
                echo '<script>
            $( document ).ready(function() {
                $("#checked2").prop("checked", true);
    
            });
            </script>
            ';
            }
            if ($activity->page == 1) {
                echo '<script>
            $( document ).ready(function() {
                $("#checked3").prop("checked", true);
    
            });
            </script>
            ';
            }
            if ($activity->file2 == 1) {
                echo '<script>
            $( document ).ready(function() {
                $("#checked4").prop("checked", true);
    
            });
            </script>
            ';
            }
            if ($activity->bulk == 1) {
                echo '<script>
            $( document ).ready(function() {
                $("#checked5").prop("checked", true);
    
            });
            </script>
            ';
            }
            if ($activity->pdf == 1) {
                echo '<script>
            $( document ).ready(function() {
                $("#checked6").prop("checked", true);
    
            });
            </script>
            ';
            }
            if ($activity->url == 1) {
                echo '<script>
            $( document ).ready(function() {
                $("#checked7").prop("checked", true);
    
            });
            </script>
            ';
            }



            if (isset($_POST['button1'])) {
                $i++;

                $ins = new stdClass();
                $ins1 = new stdClass();
                $bbb = $_POST['enable_bbb'];
                $quiz = $_POST['enable_quiz'];
                $assign = $_POST['enable_assign'];
                $page = $_POST['enable_page'];
                $file2 = $_POST['enable_file2'];
                $bulk = $_POST['enable_bulk'];
                $pdf = $_POST['enable_pdf'];
                $url = $_POST['enable_url'];
                if (!empty($activity)) {
                    $ins1->id = $activity->id;
                    if (empty($quiz)) {
                        $ins1->quiz = 0;
                    }
                    if (!empty($quiz)) {
                        $ins1->quiz = 1;
                    }
                    if (empty($assign)) {
                        $ins1->assign = 0;
                    }
                    if (!empty($assign)) {
                        $ins1->assign = 1;
                    }
                    if (empty($page)) {
                        $ins1->page = 0;
                    }
                    if (!empty($page)) {
                        $ins1->page = 1;
                    }
                    if (empty($file2)) {
                        $ins1->file2 = 0;
                    }
                    if (!empty($file2)) {
                        $ins1->file2 = 1;
                    }
                    if (empty($bulk)) {
                        $ins1->bulk = 0;
                    }
                    if (!empty($bulk)) {
                        $ins1->bulk = 1;
                    }
                    if (empty($pdf)) {
                        $ins1->pdf = 0;
                    }
                    if (!empty($pdf)) {
                        $ins1->pdf = 1;
                    }
                    if (empty($url)) {
                        $ins1->url = 0;
                    }
                    if (!empty($url)) {
                        $ins1->url = 1;
                    }
                    $DB->update_record('control_activities', $ins1);
                }
                if (empty($activity)) {
                    $ins1->course = $course;
                    $ins1->teacher_id = $teacher_id;
                    if (empty($quiz)) {
                        $ins1->quiz = 0;
                    }
                    if (!empty($quiz)) {
                        $ins1->quiz = 1;
                    }
                    if (empty($assign)) {
                        $ins1->assign = 0;
                    }
                    if (!empty($assign)) {
                        $ins1->assign = 1;
                    }
                    if (empty($page)) {
                        $ins1->page = 0;
                    }
                    if (!empty($page)) {
                        $ins1->page = 1;
                    }
                    if (empty($file2)) {
                        $ins1->file2 = 0;
                    }
                    if (!empty($file2)) {
                        $ins1->file2 = 1;
                    }
                    if (empty($bulk)) {
                        $ins1->bulk = 0;
                    }
                    if (!empty($bulk)) {
                        $ins1->bulk = 1;
                    }
                    if (empty($pdf)) {
                        $ins1->pdf = 0;
                    }
                    if (!empty($pdf)) {
                        $ins1->pdf = 1;
                    }
                    if (empty($url)) {
                        $ins1->url = 0;
                    }
                    if (!empty($url)) {
                        $ins1->url = 1;
                    }
                    $DB->insert_record('control_activities', $ins1);
                }
            }
        }
        if ($i == $count) {
            unset($_SESSION["teacher_id"]);
            unset($_SESSION["course_data"]);
            redirect($CFG->wwwroot . '/forms/bbb.php');
        }
    }
}
echo $OUTPUT->footer();
