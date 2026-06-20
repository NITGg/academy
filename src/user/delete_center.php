<?php
require_once('../config.php');

// Ensure the user is logged in
require_login();

$userid = $_GET['user_id'];
$centerId = $_GET['id'];

$center = $DB->get_records('teacher_centers', array("user_id" => $userid, "id" => $centerId));

if ($center) {
    // Delete the record from the database
    $success = $DB->delete_records("teacher_centers", array("user_id" => $userid, "id" => $centerId));

    if ($success) {

        // Redirect back to the edit page
        header('Location: profile.php?id=' . $userid);
    } else {
        echo "Not deleted.";
    }
} else {
    echo "Record not found.";
}
