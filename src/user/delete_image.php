<?php
require_once('../config.php');

// Ensure the user is logged in
require_login();

if (isset($_POST["submit"])) {
    $userid = $_POST['user_id'];
    $image_id = $_POST['image_id'];

    // Get the record for the course promo image
    $record = $DB->get_record("gallery_images", array("user_id" => $userid, "id" => $image_id));

    if ($record) {
        // Delete the image file
        $imageFilePath = "uploads/" . $record->image_name;
        if (file_exists($imageFilePath)) {
            unlink($imageFilePath);
        }

        // Delete the record from the database
        $DB->delete_records("gallery_images", array("user_id" => $userid, "id" => $image_id));

        // Redirect to the profile page with the userid parameter
        header('Location: profile.php?id=' . $userid);
        exit();
    } else {
        // Record not found
        echo "Record not found.";
    }
} else {
    // Invalid input
    echo "Invalid input.";
}
?>
