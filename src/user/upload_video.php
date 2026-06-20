<?php
require_once('../config.php');
require '../vimeo/vendor/autoload.php';

use Vimeo\Vimeo;

/* // Define the vimeo function
function vimeo($url, $name, $description, $id)
{
    $client = new Vimeo("4dad588b7f47a44426afc26f398fe2367ea49c92", "IHRxCFjq5qvsKlU6DjWGfNQwtZGHGmK1pByyCYWGrkWnE9F91BbNqPdqXY+dHVyvKjvRWYTu3ba2A8KM1GR2gcqqYiz+jXAx6uLrsEb0jFJrUSMIi3KMIyS+Je+nsN3s", "195c95a4e775fca8d6e70cb8db4aca73");

    $file_name = $url;
    $uri = $client->upload($file_name, array(
        "name" => $name,
        "description" => "$description"
    ));

    $response = $client->request($uri . '?fields=transcode.status');
    if ($response['body']['transcode']['status'] === 'complete') {
        // Video transcoding complete
    } elseif ($response['body']['transcode']['status'] === 'in_progress') {
        // Video still transcoding
    } else {
        // Video transcoding encountered an error
    }

    $response = $client->request($uri . '?fields=link');
    return $response['body']['link'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Assuming you have a valid $userid variable from your form or session
    $userid = $_POST['user_id'];

    // Handle video upload to Vimeo and get the video URL
    $file_path = $_FILES['videoFile']['tmp_name'];
    $video_name = $_FILES['videoFile']['name'];
    $video_description = ""; // Set your video description here

    $client = new Vimeo("4dad588b7f47a44426afc26f398fe2367ea49c92", "IHRxCFjq5qvsKlU6DjWGfNQwtZGHGmK1pByyCYWGrkWnE9F91BbNqPdqXY+dHVyvKjvRWYTu3ba2A8KM1GR2gcqqYiz+jXAx6uLrsEb0jFJrUSMIi3KMIyS+Je+nsN3s", "195c95a4e775fca8d6e70cb8db4aca73");

    $vimeo_url = vimeo($file_path, $video_name, $video_description, $userid);

    // Insert video data into the profile_videos table
    $videoRecord = new stdClass();
    $videoRecord->user_id = $userid;
    $videoRecord->video_url = $vimeo_url;

    if (!$DB->insert_record('profile_videos', $videoRecord)) {
        echo "Database Error: " . $DB->get_last_error();
    } else {
        echo "Video uploaded and data saved successfully!";
    }
}
?>
 */

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
    $userid = $_POST['user_id'];

    // Handle video upload to Vimeo and get the video ID
    $file_path = $_FILES['videoFile']['tmp_name'];
    $video_name = $_FILES['videoFile']['name'];
    $video_description = ""; // Set your video description here

    $vimeo_video_id = vimeo($file_path, $video_name, $video_description, $userid);

    // Insert video data into the profile_videos table
    $videoRecord = new stdClass();
    $videoRecord->user_id = $userid;
    $videoRecord->video_url = $vimeo_video_id; // Save only the video ID

    if (!$DB->insert_record('profile_videos', $videoRecord)) {
        echo "Database Error: " . $DB->get_last_error();
    } else {
        echo "Video uploaded and data saved successfully!";

        // Redirect to the profile page with the userid parameter
        header('Location: profile.php?id=' . $userid);

    }
}



/* // Fetch video data from the database
        $videoRecords = $DB->get_records('profile_videos', array('user_id'=>$userid));

        foreach ($videoRecords as $record) {
        $vimeo_video_id = $record->video_url;

        // Display the Vimeo player
        echo '<div class="vimeo-player">';
        echo '<iframe src="https://player.vimeo.com/video/' . $vimeo_video_id . '" width="640" height="360" frameborder="0" allowfullscreen></iframe>';
        echo '</div>';
        } */