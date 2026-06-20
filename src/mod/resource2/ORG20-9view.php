<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * resource2 module version information
 *
 * @package    mod_resource2
 * @copyright  2009 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/mod/resource2/lib.php');
require_once($CFG->dirroot.'/mod/resource2/locallib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require '../../vimeo/vendor/autoload.php';
use Vimeo\Vimeo;
$id       = optional_param('id', 0, PARAM_INT); // Course Module ID
$r        = optional_param('r', 0, PARAM_INT);  // resource2 instance ID
$redirect = optional_param('redirect', 0, PARAM_BOOL);
$forceview = optional_param('forceview', 0, PARAM_BOOL);
$token = optional_param('token',  0, PARAM_TEXT); //token from mobile api

if ($r) {
    if (!$resource2 = $DB->get_record('resource2', array('id'=>$r))) {
        resource2_redirect_if_migrated($r, 0);
        print_error('invalidaccessparameter');
    }
    $cm = get_coursemodule_from_instance('resource2', $resource2->id, $resource2->course, false, MUST_EXIST);

} else {
    if (!$cm = get_coursemodule_from_id('resource2', $id)) {
        resource2_redirect_if_migrated(0, $id);
        print_error('invalidcoursemodule');
    }
    $resource2 = $DB->get_record('resource2', array('id'=>$cm->instance), '*', MUST_EXIST);
}


$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);

if(!empty($token) ){

    $api = new webservice();
    $array = array();
    try{
        $array = $api->authenticate_user($token);
    if (!empty($array)){

    }
    else 
       echo json_encode( ['message'=>'invalide token']);

    }catch(Exception $e){
        echo json_encode( ['message'=>'invalide token']);
    }
    

}
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/resource2:view', $context);

// Completion and trigger events.
resource2_view($resource2, $course, $cm, $context);

$PAGE->set_url('/mod/resource2/view.php', array('id' => $cm->id));

if ($redirect && !$forceview) {

}
$course_modules=$DB->get_record("course_modules",array('id'=>$id));
$video=$DB->get_record("resource2",array("id"=>$course_modules->instance));
$record=$DB->get_record("vimeo_files2",array('resource2_id'=>$video->id));
$PAGE->set_title($video->name);
$PAGE->set_heading($video->name);
// echo'<link href="https://vjs.zencdn.net/7.19.2/video-js.css" rel="stylesheet" />';

echo $OUTPUT->header();
echo'  <script src="https://vjs.zencdn.net/7.19.2/video.min.js"></script>
';
echo'
<style>
.homepage-video, .homepage-video video {
    position: relative;
  }
  .homepage-video video {
      background-size: cover !important;
      background-repeat: no-repeat !important;
      background-position: 50% !important;
  }


  .advertisement{
      font-size: 12px !important;
    position:absolute;
      color: white;
    background-color: rgba(0, 0, 0, 0.5);
    
 
      padding: 5px 10px;
      text-align: center ;
      justify-content: center;
      align-items: center;
    bottom: 50px;
      right:0;
      font-weight: 700;
      z-index: 1 !important;
      display: flex;
      justify-content: center;
      align-items: center;
  
}
</style>';
if (strpos($record->url, 'iframe') !== false) {

    if(strpos($record->url, 'academyvideos') !== false){
        echo'  <div class="homepage-video row">';

        echo'<video id="my-video"
        class="video-js col-12"
        controls disablePictureInPicture playsinline autoplay muted loop playbackspeed
        preload="auto"
        width="1280"
        height="720"
        data-setup="{}">     <source src="https://nitg-eg.com/academyvideos/'.$record->name.'" type="video/mp4" />
        </video>
        <!--<div class="moveToVideoJs">
        <div class="advertisement" >'.$USER->id.'</div>
        </div>-->
        </div>
        
        ';
    }
    else{
        echo'<div class="homepage-video">
        ' . $record->url. '
        <!-- <div class="moveToVideoJs">
        <div class="advertisement" >'.$USER->id.'</div>
        </div>-->
        </div>
        
        ';
    }

    echo "<script>

// $(document).ready(function(){
//         length=$('.advertisement').text().length;
//         $('.advertisement').css('width',length*12+'px');
//         $('.advertisement').css('height',length*6+'px');    
//           function moveElmRand(elm){
//             elm.style.position ='absolute';
//             elm.style.top = Math.floor(Math.random()*90+5)+'%';
//             elm.style.left = Math.floor(Math.random()*90+5)+'%';
//             elm.style.right = Math.floor(Math.random()*90+5)+'%';
//             elm.style.bottom = Math.floor(Math.random()*90+5)+'%';
//            }
//           const getRandom = (min, max) => Math.floor(Math.random()*(max-min+1)+min);
//         const square= document.querySelector('.advertisement');
//         setInterval(() => {
// moveElmRand(square);
//         }, 2000);
//         document.addEventListener('fullscreenchange', function() {
//             if (document.fullscreen) {
//     $('.advertisement ')
//     .appendTo($('.video-js'));
//     $('.advertisement').css('width',length*12+'px');
//     $('.advertisement').css('height',length*6+'px');  
//             } else {
//                 $('.advertisement')
//                 .appendTo($('.video-js ' ));   
//                 $('.advertisement').css('width',length*12+'px');
//                 $('.advertisement').css('height',length*6+'px');
//             }
//             const player = new Player('handstick', {
//                 id: 682907342,
//                 width: 640
//             });
//             player.getFullscreen().then(function(fullscreen) {
//               console.log(fullscreen);
//             }).catch(function(error) {
//                 console.log(error);
//             });
//           });    
//   });
</script>";
}
elseif (strpos($record->url, 'video') !== false){
    echo'  <div class="homepage-video row">';
echo $record->url;
    echo'
    <!--  <div class="moveToVideoJs">
    <div class="advertisement" >'.$USER->id.'</div>
    </div>-->
    </div>
    
    ';

    // echo "<script>

    // $(document).ready(function(){
    //         length=$('.advertisement').text().length;
    //         $('.advertisement').css('width',length*12+'px');
    //         $('.advertisement').css('height',length*6+'px');    
    
    //           function moveElmRand(elm){
    //             elm.style.position ='absolute';
    //             elm.style.top = Math.floor(Math.random()*90+5)+'%';
    //             elm.style.left = Math.floor(Math.random()*90+5)+'%';
    //             elm.style.right = Math.floor(Math.random()*90+5)+'%';
    //             elm.style.bottom = Math.floor(Math.random()*90+5)+'%';
    
    //            }
    
    //           const getRandom = (min, max) => Math.floor(Math.random()*(max-min+1)+min);
    //         const square= document.querySelector('.advertisement');
    
    //         setInterval(() => {
    
    // moveElmRand(square);
    //         }, 2000);
        
               
      
    //         document.addEventListener('fullscreenchange', function() {
    //             if (document.fullscreen) {
    //                 console.log('hi');
    
    //     $('.advertisement ')
    //     .appendTo($('.video-js'));
    //     $('.advertisement').css('width',length*12+'px');
    //     $('.advertisement').css('height',length*6+'px');  
    //             } else {
    //                 $('.advertisement')
    //                 .appendTo($('.video-js ' ));   
    //                 $('.advertisement').css('width',length*12+'px');
    //                 $('.advertisement').css('height',length*6+'px');
    //             }
    //           });
             
    //   });
    
    
    // </script>";
}
else{
    $client = new Vimeo("518cbf96a9bf75b7427b39c3f6de29897804f742", "3tXqY4wQpAnhY+knCSbh2qoZYf2ITUzvoXO6B7iEuiUy+/C+laWW9N3gWoztI1NDvKDsOmWluG6DWC5ofpqrH9Fa3s3W4IVZEuBADczqzc9Z+zgrWzDzhI2a47bsVZSY", "b7b98271c1465b68a7d9901600c2119b");
    $uri="/videos/".$record->url;
    $response = $client->request($uri, [], 'GET');
    // var_dump($response['body']['files'][4]['link']);
     $status=$response['body']['transcode']['status'];
     if($status=="in_progress"){
         echo '<div class="spinner-border text-warning"></div>
         <span>Your video is still transcoding Refresh again after a while.</span>
         ';
     }
     else{
       
        $time =$DB->get_records_sql("SELECT UNIX_TIMESTAMP(current_Timestamp())-UNIX_TIMESTAMP(timecreated) as time from mdl_vimeo_files2 where id='$record->id'");
        foreach($time as $t){
            if($t->time<150){
                echo '
                <div class="alert alert-success" role="alert" id="alert">
                Your video finished transcoding.
           </div>
                ';
            }
        }
    
     }
     
    
    echo '
    <h1 class="dsipaly-3">'.$video->name.'</h1>';
    // <iframe src="https://player.vimeo.com/video/'.$record->url.'" width="640" height="564" frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe>';
    //    echo $response['body']['embed']['html'];
    // echo '<iframe src="'.$response['body']['player_embed_url'].'" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" width="640" style="height:420px" allowfullscreen></iframe>';
//     echo '
//    <div class="vidcontainer">
//    <select class="qualitypick" autocomplete="off">
//       <option selected>fullHD</option>
//       <option>720p</option>
//       <option>360p</option>
//    </select>
//    <video controls preload>
//       <source label="fullHD" src="'.$response['body']['files'][4]['link'].'" type="video/mp4">
//       <source label="720p"   src="'.$response['body']['files'][3]['link'].'" type="video/mp4" >
//       <source label="360p"   src="'.$response['body']['files'][1]['link'].'" type="video/mp4">
//    </video>
// </div>
//     ';
//     echo "<script>
//     $(document).ready(function(){
//         $('.qualitypick').change(function(){ 
     
//            //Have several videos in file, so have to navigate directly
//            video = $(this).parent().find('video');
     
//            //Need access to DOM element for some functionality
//            videoDOM = video.get(0);
     
//            curtime = videoDOM.currentTime;  //Get Current Time of Video
//            source = video.find('source[label=' + $(this).textContent + ']'); //Copy Source
     
//            source.remove();                 //Remove the source from select
//            video.prepend(source);           //Prepend source on top of options
//            video.load();                    //Reload Video
//            videoDOM.currentTime = curtime;  //Continue from video's stop
//            videoDOM.play();                 //Resume video
//         })
//      })
     
//     </script>";
    //video with id vimeo
        echo "<div class='homepage-video row vidcontainer'>
        <select class='qualitypick' autocomplete='off'>
        <option selected value='1'>fullHD</option>
        <option value='2'>720</option>
        <option value='3'>360</option>
        <option value='4'>540</option>
        <option value='5'>240</option>

     </select>
    <video id='my-video'
    class='video-js col-12'
    controls disablePictureInPicture playsinline autoplay muted loop playbackspeed
    preload='auto'
    width='1280'
    height='720'
    data-setup='{}'>
    <source label='fullHD' src='".$response['body']['files'][1]['link']."' type='video/mp4'>



    Your browser does not support the video tag.
  </video>


  <div class='moveToVideoJs'>
    <div class='advertisement' >".$USER->id."</div>
    </div>
  </div>
  ";
//   echo $response['body']['files'][0]['link']."<br>";
//   echo $response['body']['files'][1]['link']."<br>";
//   echo $response['body']['files'][2]['link']."<br>";
//   echo $response['body']['files'][3]['link']."<br>";
//   echo $response['body']['files'][4]['link']."<br>";

    echo "<script>

    $(document).ready(function(){
        $('.qualitypick').change(function(){ 

            //Have several videos in file, so have to navigate directly
            video = $(this).parent().find('video');

            //Need access to DOM element for some functionality
            videoDOM = video.get(0);
          
            curtime = videoDOM.currentTime;  //Get Current Time of Video
            // source = video.find('source[label=' + $(this).textContent + ']'); //Copy Source
            // console.log(source);
            // source.remove();                 //Remove the source from select
            // video.prepend(source);           //Prepend source on top of options
            var data= $('.qualitypick').val() ;
                        $.ajax({
                type: 'POST',
                url: 'ajax.php',
                 data: {  myData:data,url:'". $record->url."'},
             success: function (data) {
                console.log('dataftrom : '+data);

                $(video).children().each(function(index) {
                    $(this).attr('src',data);
                });
                video.load();                    //Reload Video
                videoDOM.currentTime = curtime;  //Continue from video's stop
                videoDOM.play();                 //Resume video

            }
             }); 
      
         });
            length=$('.advertisement').text().length;
            $('.advertisement').css('width',length*12+'px');
            $('.advertisement').css('height',length*6+'px');    
    
              function moveElmRand(elm){
                elm.style.position ='absolute';
                elm.style.top = Math.floor(Math.random()*90+5)+'%';
                elm.style.left = Math.floor(Math.random()*90+5)+'%';
                elm.style.right = Math.floor(Math.random()*90+5)+'%';
                elm.style.bottom = Math.floor(Math.random()*90+5)+'%';
    
               }
    
              const getRandom = (min, max) => Math.floor(Math.random()*(max-min+1)+min);
            const square= document.querySelector('.advertisement');
    
            setInterval(() => {
    
    moveElmRand(square);
            }, 2000);
        
               
      
            document.addEventListener('fullscreenchange', function() {
                if (document.fullscreen) {
                    console.log('hi');
    
        $('.advertisement ')
        .appendTo($('.video-js'));
        $('.advertisement').css('width',length*12+'px');
        $('.advertisement').css('height',length*6+'px');  
                } else {
                    $('.advertisement')
                    .appendTo($('.video-js ' ));   
                    $('.advertisement').css('width',length*12+'px');
                    $('.advertisement').css('height',length*6+'px');
                }
              });
             
      });
    
    
    </script>";
}


// $last_string=substr($record->url, strrpos($record->url, '/') + 1);
// $first_string=preg_replace('/\s+?(\S+)?$/', '', substr($record->url, 0, 18));
// $result=str_replace($first_string, '', $record->url);
// $result=str_replace($last_string, '', $result);

echo $OUTPUT->footer();

