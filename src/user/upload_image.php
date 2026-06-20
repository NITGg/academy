<?php
require_once('../config.php');

global $DB, $CFG;

$targetDirectory = 'uploads/';
$imageFileType = strtolower(pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION));



if (isset($_POST["submit"])) {
    $userid = $_POST["user_id"];

    // Generate a unique file name based on current date and time
    $currentDateTime = date("Ymd_His");
    $newFileName = $currentDateTime . '.' . $imageFileType;
    $uploadFile = $targetDirectory . $newFileName;

    // Check file size
    if ($_FILES["file"]["size"] > 50000000) { // Change this value to your desired maximum file size
        echo "Sorry, your file is too large.";
    } else {
        // Allow only specific image file formats
        $allowedFormats = array("jpg", "jpeg", "png", "gif");
        if (!in_array($imageFileType, $allowedFormats)) {
            echo "Sorry, only JPG, JPEG, PNG, and GIF files are allowed.";
        } else {
            // Attempt to move the uploaded file to the target directory with the new file name
            if (move_uploaded_file($_FILES["file"]["tmp_name"], $uploadFile)) {

                /* add image name and userid to course_promo_images */
                $data = new stdClass();
                $data->user_id = $userid;
                $data->image_name = $newFileName;
                $success = $DB->insert_record('gallery_images', $data);

                // Handle the response and return success or failure
                if ($success) {
                    // Redirect to the profile page with the userid parameter
                    header('Location: profile.php?id=' . $userid);
                } else {
                    echo 'Error!';
                }

                //echo "The file ". htmlspecialchars(basename($_FILES["file"]["name"])). " has been uploaded.";
                exit(); // Exit to prevent further code execution
            } else {
                echo "Sorry, there was an error uploading your file.";
            }
        }
    }
}
