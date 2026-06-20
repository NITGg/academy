<?php
require_once('../config.php');
require '../vimeo/vendor/autoload.php';

use Vimeo\Vimeo;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['video_id_to_delete'])) {
    // Assuming you have a valid $userid variable from your session
    $userid = $_GET['user_id']; // Replace with your actual user ID

    // Get the video ID to delete from the query parameter
    $video_id_to_delete = $_GET['video_id_to_delete'];

    // Initialize Vimeo client
    $client = new Vimeo("4dad588b7f47a44426afc26f398fe2367ea49c92", "IHRxCFjq5qvsKlU6DjWGfNQwtZGHGmK1pByyCYWGrkWnE9F91BbNqPdqXY+dHVyvKjvRWYTu3ba2A8KM1GR2gcqqYiz+jXAx6uLrsEb0jFJrUSMIi3KMIyS+Je+nsN3s", "195c95a4e775fca8d6e70cb8db4aca73");

    // Delete the video from Vimeo
    try {
        $client->request("/videos/" . $video_id_to_delete, array(), 'DELETE');
    } catch (Exception $e) {
        echo "Error deleting video from Vimeo: " . $e->getMessage();
        exit;
    }

    // Delete the video data from the database
    $delete_result = $DB->delete_records('profile_videos', array('user_id' => $userid, 'video_url' => $video_id_to_delete));

    if ($delete_result) {
        echo "Video deleted successfully!";
        // Redirect to the profile page with the userid parameter
        header('Location: profile.php?id=' . $userid);
    } else {
        echo "Error deleting video from the database.";
    }
} else {
    echo "Invalid request.";
}
