<?php
// Include necessary Moodle files
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

global $DB, $CFG;

$selectedActivity = $_GET['activity'];
$selectedCourse = $_GET['course_id'];
/* $selectedRecordNum = $_GET['num']; */
$activities = array(); // Initialize an empty array for activities

function formatTimeDifference($timestamp)
{
    $currentTimestamp = time(); // Get the current timestamp
    $timeDifference = $currentTimestamp - $timestamp; // Calculate the time difference

    if ($timeDifference < 3600) {
        // Less than an hour
        $minutes = ceil($timeDifference / 60);
        return "قبل " . $minutes . " دقيقة" . ($minutes > 1 ? "" : "") . " من الآن";
    } elseif ($timeDifference < 86400) {
        // Less than a day
        $hours = floor($timeDifference / 3600);
        return "قبل " . $hours . " ساعة" . ($hours > 1 ? "" : "") . " من الآن";
    } elseif ($timeDifference < 2592000) {
        // Less than a month (30 days)
        $days = floor($timeDifference / 86400);
        return "قبل " . $days . " يوم" . ($days > 1 ? "" : "") . " من الآن";
    } elseif ($timeDifference < 31536000) {
        // Less than a year (365 days)
        $months = floor($timeDifference / 2592000);
        return "قبل " . $months . " شهر" . ($months > 1 ? "" : "") . " من الآن";
    } else {
        // More than a year
        $years = floor($timeDifference / 31536000);
        return "قبل " . $years . " سنة" . ($years > 1 ? "" : "") . " من الآن";
    }
}

if ($selectedActivity) {
    if (!$selectedCourse) {
        // Perform your database query to get activities based on the selected option.
        $act_sql = "SELECT cm.*
                FROM {course_modules} cm
                JOIN {modules} m ON cm.module = m.id
                WHERE m.name = :module_name AND deletioninprogress != 1";

        $act_params = array('module_name' => $selectedActivity);
    } else {
        $act_sql = "SELECT cm.*
                FROM {course_modules} cm
                JOIN {modules} m ON cm.module = m.id
                WHERE m.name = :module_name AND cm.course = :course_id AND deletioninprogress != 1";

        $act_params = array('module_name' => $selectedActivity, 'course_id' => $selectedCourse);
    }

    $activities = $DB->get_records_sql($act_sql, $act_params);
}

// Prepare the data to be returned to the AJAX request
$data = array(
    'count' => count($activities), // Number of activities
    'activitie_name' => $selectedActivity,
    'html' => '' // Placeholder for HTML content
);

if (!empty($activities)) {
    // If there are activities, generate the HTML content for the table
    $html = '';
    $rowCounter = 1;
    foreach ($activities as $activity) {
        // Define the course module ID
        $cmid = $activity->id; // Replace with the actual course module ID you want to retrieve the name for
        // Get the course module information
        $cm = get_coursemodule_from_id($selectedActivity, $cmid, 0, false, MUST_EXIST);
        // Get the module name
        $modulename = $cm->modname;
        // Output the module name
        //echo "Module Name: " . $modulename;

        /***************** course topic name **********************/
        $course_section = $DB->get_field('course_sections', 'name', array('id' => $activity->section));
        /***************** course name **********************/
        $course_name = $DB->get_field('course', 'fullname', array('id' => $activity->course));
        /***************** module url **********************/
        $open = $CFG->wwwroot.'/mod/'.$selectedActivity.'/view.php?id=' . $cmid;

        /***************** module date **********************/
        $added_timestamp = $activity->added;
        // Convert the timestamp to a human-readable date
        $creation_date = date('Y-m-d H:i:s', $added_timestamp);

        $timestamp = strtotime($creation_date); // Replace this with your creation_date
        $formattedTime = formatTimeDifference($timestamp);


        // Build HTML row for the table
        $html .= '<tr>';
        $html .= '<th scope="row">' . $rowCounter . '</th>';
        $html .= '<td>' . $activity->id . '</td>';
        $html .= '<td>' . $course_name . '</td>';
        /* $html .= '<td>' . $formattedTime . '</td>'; */
        $html .= '<td>' . $creation_date . '</td>';
        $html .= '<td>' . $modulename . '</td>';
        $html .= '<td>' . $course_section . '</td>';
        $html .= '<td><a href= "' . $open . '">open</a></td>';
        $html .= '</tr>';

        $rowCounter++;
    }

    // Set the HTML content in the response data
    $data['html'] = $html;
}

// Return the data as JSON
header('Content-Type: application/json');
echo json_encode($data);
