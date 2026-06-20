<?php

require_once('../config.php');

global $DB, $CFG;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_id"]) && isset($_POST["center_id"])) {

    $userid = $_POST["user_id"];
    $centerid = $_POST["center_id"];
    $name = $_POST["name"];
    $body = $_POST["body"];
    $phone1 = $_POST["phone1"];
    $phone2 = $_POST["phone2"];

    // You should sanitize and validate the data before updating it in the database

    $data = new stdClass();
    $data->id = $centerid; // Assuming your database column is named 'id'
    $data->user_id = $userid;
    $data->name = $name;
    $data->body = $body;
    $data->phone1 = $phone1;
    $data->phone2 = $phone2;

    // Update data in the teacher_centers datatable
    $success = $DB->update_record('teacher_centers', $data);

    if ($success) {
        echo "Data updated successfully!";
        // Redirect to the profile page with the userid parameter
        header('Location: profile.php?id='.$userid);
    } else {
        echo "Error updating data.";
    }
} else {
    echo "Invalid request.";
}
?>
