<?php
require_once('../../config.php');
require '../../vimeo/vendor/autoload.php';

use Vimeo\Vimeo;

if (isset($_POST['myData'])) {
    try {
        $client = new Vimeo("518cbf96a9bf75b7427b39c3f6de29897804f742", "3tXqY4wQpAnhY+knCSbh2qoZYf2ITUzvoXO6B7iEuiUy+/C+laWW9N3gWoztI1NDvKDsOmWluG6DWC5ofpqrH9Fa3s3W4IVZEuBADczqzc9Z+zgrWzDzhI2a47bsVZSY", "b7b98271c1465b68a7d9901600c2119b");
        $uri = "/videos/" . $_POST['url'];
        $response = $client->request($uri, [], 'GET');
        // echo sizeof($response['body']['files']);

        for ($i = 0; $i < sizeof($response['body']['files']); $i++) {
            if ($_POST['myData'] == 1) {
                if (strpos($response['body']['files'][$i]['link'], '1080p') !== false) {
                    echo $response['body']['files'][$i]['link'];
                }
            } elseif ($_POST['myData'] == 2) {
                if (strpos($response['body']['files'][$i]['link'], '720p') !== false) {
                    echo $response['body']['files'][$i]['link'];
                }
            } elseif ($_POST['myData'] == 3) {
                if (strpos($response['body']['files'][$i]['link'], '360p') !== false) {
                    echo $response['body']['files'][$i]['link'];
                }
            } elseif ($_POST['myData'] == 4) {
                if (strpos($response['body']['files'][$i]['link'], '540p') !== false) {
                    echo $response['body']['files'][$i]['link'];
                }
            } elseif ($_POST['myData'] == 5) {
                if (strpos($response['body']['files'][$i]['link'], '240p') !== false) {
                    echo $response['body']['files'][$i]['link'];
                }
            }
        }
    } catch (Exception $e) {
        echo "fail " . $e;
    }
}
