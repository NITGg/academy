<?php

require 'vendor/autoload.php';
use Vimeo\Vimeo;
require_once('../config.php');
$client = new Vimeo("4dad588b7f47a44426afc26f398fe2367ea49c92", "IHRxCFjq5qvsKlU6DjWGfNQwtZGHGmK1pByyCYWGrkWnE9F91BbNqPdqXY+dHVyvKjvRWYTu3ba2A8KM1GR2gcqqYiz+jXAx6uLrsEb0jFJrUSMIi3KMIyS+Je+nsN3s", "195c95a4e775fca8d6e70cb8db4aca73");

// $file_name = "video.mp4";
// $uri = $client->upload($file_name, array(
//   "name" => "Untitled",
//   "description" => "The description goes here."
// ));

// $response = $client->request($uri . '?fields=transcode.status');
// if ($response['body']['transcode']['status'] === 'complete') {
//   print 'Your video finished transcoding.';
// } elseif ($response['body']['transcode']['status'] === 'in_progress') {
//   print 'Your video is still transcoding.';
// } else {
//   print 'Your video encountered an error during transcoding.';
// }

// $response = $client->request($uri . '?fields=link');
// echo "Your video link is: " . $response['body']['link'];

// $uri="https://api.vimeo.com/videos/129794973";
// $response =$client->request($uri, array(), 'GET');
// $uri="/videos/534931282";
// $response = $client->request($uri, [], 'GET');
$record=$DB->get_record("vimeovedios",array("id"=>2));
$last_string=substr($record->output, strrpos($record->output, '/') + 1);
$first_string=preg_replace('/\s+?(\S+)?$/', '', substr($record->output, 0, 18));
$result=str_replace($first_string, '', $record->output);
$result=str_replace($last_string, '', $result);
$uri="/videos/".$result;
$response = $client->request($uri, [], 'GET');
echo '<iframe src="https://player.vimeo.com/video/'.$result.'" width="640" height="564" frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe>';
 ?>
