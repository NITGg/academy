<?php
require_once('../config.php');
if(isset($_POST['myData'])){
    $size=$_POST['myData'];
    // if($size>28311552){
    //     $returnArr = ["limits",0];
    //     echo json_encode($returnArr);
    // }
    // else{
     $getSize=$DB->get_record('control_max_size',array('userid'=>$USER->id));
        $check=0;
     if(empty($getSize)){
        $returnArr = ["still",$size];

        echo json_encode($returnArr);
    }
        else{

            $total=$getSize->size+$size;
            $week =$getSize->empty;
            $now=time();
    
            if($week <=$now){
               $up = new stdClass();
               $up->id = $getSize->id;
               date_default_timezone_set("Africa/Egypt");
               $date = date('Y-m-d H:i:s');
               $date = strtotime($date);
               $date = strtotime("+7 day", $date);
               $up->size = 0;
                $up->empty = $date;
               $DB->update_record('control_max_size', $up);
               $check=1;
           }
           if($check==1){
            // $getSize=$DB->get_record('control_max_size',array('userid'=>$USER->id));
            $total=$size;

           }
            if($total <$getSize->max_size){
                
                $returnArr = ["still",$total];
             echo json_encode($returnArr);
            }
          
            else{
                $returnArr = ["maximum",0];
                echo json_encode($returnArr);
            }
           
          
        }
    
}