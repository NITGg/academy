<?php 
require_once('../config.php');
if(isset($_POST['userid']))
{
    $userid=$_POST['userid'];
    $firstname=$_POST['firstname'];
    $lastname=$_POST['lastname'];
    $email=$_POST['email'];
    $password=$_POST['password'];
    $phone=$_POST['phone'];
    echo json_encode(['userid' => $userid, 'firstname' => $firstname,'lastname' => $lastname,'password' => $password,'phone' => $phone]);
    // echo $phone;
}