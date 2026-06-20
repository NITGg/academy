<?php

require_once('../config.php');
require '../vimeo/vendor/autoload.php';

use Vimeo\Vimeo;


// Define the vimeo function
function vimeo($url, $name, $description, $id)
{
    $client = new Vimeo("4dad588b7f47a44426afc26f398fe2367ea49c92", "IHRxCFjq5qvsKlU6DjWGfNQwtZGHGmK1pByyCYWGrkWnE9F91BbNqPdqXY+dHVyvKjvRWYTu3ba2A8KM1GR2gcqqYiz+jXAx6uLrsEb0jFJrUSMIi3KMIyS+Je+nsN3s", "195c95a4e775fca8d6e70cb8db4aca73");

    $file_name = $url;
    $uri = $client->upload($file_name, array(
        "name" => $name,
        "description" => "$description"
    ));

    // Extract video ID from Vimeo URL
    $video_id = substr(parse_url($uri, PHP_URL_PATH), 8); // Extract video ID from the URL

    $response = $client->request($uri . '?fields=transcode.status');
    if ($response['body']['transcode']['status'] === 'complete') {
        // Video transcoding complete
    } elseif ($response['body']['transcode']['status'] === 'in_progress') {
        // Video still transcoding
    } else {
        // Video transcoding encountered an error
    }

    return $video_id; // Return only the video ID
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Assuming you have a valid $userid variable from your form or session
    $course_id = $_POST['course_id'];

    // Handle video upload to Vimeo and get the video ID
    $file_path = $_FILES['videoFile']['tmp_name'];
    $video_name = $_FILES['videoFile']['name'];
    $video_description = ""; // Set your video description here

    $vimeo_video_id = vimeo($file_path, $video_name, $video_description, $userid);

    // Insert video data into the course_promo_videos table
    $videoRecord = new stdClass();
    $videoRecord->course_id = $course_id;
    $videoRecord->url_name = $vimeo_video_id; // Save only the video ID

    if (!$DB->insert_record('course_promo_videos', $videoRecord)) {
        echo "Database Error: " . $DB->get_last_error();
    } else {
        echo "Video uploaded and data saved successfully!";
    }
}



/* require_once('../config.php');

global $DB, $CFG;

$targetDirectory = 'videos/';
$videoFileType = strtolower(pathinfo($_FILES["fileToUpload"]["name"], PATHINFO_EXTENSION));



if (isset($_POST["submit"])) {
    $course_id = $_POST["course_id"];

    // Generate a unique file name based on current date and time
    $currentDateTime = date("Ymd_His");
    $newFileName = $currentDateTime . '.' . $videoFileType;
    $uploadFile = $targetDirectory . $newFileName;

    // Check file size
    if ($_FILES["fileToUpload"]["size"] > 50000000) { // Change this value to your desired maximum file size
        echo "Sorry, your file is too large.";
    } else {
        // Allow only specific video file formats
        $allowedFormats = array("mp4", "avi", "mov");
        if (!in_array($videoFileType, $allowedFormats)) {
            echo "Sorry, only MP4, AVI, and MOV files are allowed.";
        } else {
            // Attempt to move the uploaded file to the target directory with the new file name
            if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $uploadFile)) {

                // add video name and course_id to course_promo_videos 
                $data = new stdClass();
                $data->course_id = $course_id;
                $data->url_name = $newFileName;
                $success = $DB->insert_record('course_promo_videos', $data);

                // Handle the response and return success or failure
                if ($success) {
                    // Redirect to the edit page with the course_id parameter
                    header('Location: edit.php?id=' . $course_id);
                } else {
                    echo 'Error!';
                }

                //echo "The file ". htmlspecialchars(basename($_FILES["fileToUpload"]["name"])). " has been uploaded.";
                exit(); // Exit to prevent further code execution
            } else {
                echo "Sorry, there was an error uploading your file.";
            }
        }
    }
} */

