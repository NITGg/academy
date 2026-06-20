<?php

require_once("../config.php");
header('Content-Type: application/json');

$function =$_GET['function'];
$token = $_GET['token'];
if($function=="change_password"){
    $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => ''.$CFG->wwwroot.'/signleteacher/apis.php?function=change_password&token='.$token,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => array('oldpassword' => $_REQUEST['oldpassword'] , 'newpassword' =>   $_REQUEST['newpassword']),    
                ));
                $response = curl_exec($curl);
                curl_close($curl);
                echo $response;
}