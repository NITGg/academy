<?php

if (isset($_POST["submit"])) {

    require_once('../config.php');

    global $DB, $CFG;

    $userid = $_POST["user_id"];
    $name = $_POST["name"];
    $body = $_POST["body"];
    $phone1 = $_POST["phone1"];
    $phone2 = $_POST["phone2"];

    // You should sanitize and validate the data before inserting it into the database

    $data = new stdClass();
    $data->user_id = $userid;
    $data->name = $name;
    $data->body = $body;
    $data->phone1 = $phone1;
    $data->phone2 = $phone2;

    // Insert data into the teacher_centers datatable
    $success = $DB->insert_record('teacher_centers', $data);

    if ($success) {
        echo "Data saved successfully!";
        header('Location: profile.php?id=' . $userid);
    } else {
        echo "Error saving data.";
    }
}
