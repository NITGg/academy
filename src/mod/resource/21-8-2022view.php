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
 * Resource module version information
 *
 * @package    mod_resource
 * @copyright  2009 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/mod/resource/lib.php');
require_once($CFG->dirroot.'/mod/resource/locallib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require '../../vimeo/vendor/autoload.php';
use Vimeo\Vimeo;
$id       = optional_param('id', 0, PARAM_INT); // Course Module ID
$r        = optional_param('r', 0, PARAM_INT);  // Resource instance ID
$redirect = optional_param('redirect', 0, PARAM_BOOL);
$forceview = optional_param('forceview', 0, PARAM_BOOL);
$token = optional_param('token',  0, PARAM_TEXT); //token from mobile api

if ($r) {
    if (!$resource = $DB->get_record('resource', array('id'=>$r))) {
        resource_redirect_if_migrated($r, 0);
        print_error('invalidaccessparameter');
    }
    $cm = get_coursemodule_from_instance('resource', $resource->id, $resource->course, false, MUST_EXIST);

} else {
    if (!$cm = get_coursemodule_from_id('resource', $id)) {
        resource_redirect_if_migrated(0, $id);
        print_error('invalidcoursemodule');
    }
    $resource = $DB->get_record('resource', array('id'=>$cm->instance), '*', MUST_EXIST);
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
require_capability('mod/resource:view', $context);

// Completion and trigger events.
resource_view($resource, $course, $cm, $context);

$PAGE->set_url('/mod/resource/view.php', array('id' => $cm->id));

if ($redirect && !$forceview) {

}
$course_modules=$DB->get_record("course_modules",array('id'=>$id));
$video=$DB->get_record("resource",array("id"=>$course_modules->instance));
$record=$DB->get_record("vimeo_files",array('resource_id'=>$video->id));
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
        <div class="moveToVideoJs">
        <div class="advertisement" >'.$USER->id.'</div>
        </div>
        </div>
        
        ';
    }
    else{
        echo'<div class="homepage-video">
        ' . $record->url. '
        <div class="moveToVideoJs">
        <div class="advertisement" >'.$USER->id.'</div>
        </div>
        </div>
        
        ';
    }

    echo "<script>

$(document).ready(function(){
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
            const player = new Player('handstick', {
                id: 682907342,
                width: 640
            });
            player.getFullscreen().then(function(fullscreen) {
              console.log(fullscreen);
            }).catch(function(error) {
                console.log(error);
            });
          });    
  });
</script>";
}
elseif (strpos($record->url, 'video') !== false){
    echo'  <div class="homepage-video row">';
echo $record->url;
    echo'
    <div class="moveToVideoJs">
    <div class="advertisement" >'.$USER->id.'</div>
    </div>
    </div>
    
    ';

    echo "<script>

    $(document).ready(function(){
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
else{
    $client = new Vimeo("4dad588b7f47a44426afc26f398fe2367ea49c92", "IHRxCFjq5qvsKlU6DjWGfNQwtZGHGmK1pByyCYWGrkWnE9F91BbNqPdqXY+dHVyvKjvRWYTu3ba2A8KM1GR2gcqqYiz+jXAx6uLrsEb0jFJrUSMIi3KMIyS+Je+nsN3s", "195c95a4e775fca8d6e70cb8db4aca73");
    $uri="/videos/".$record->url;
    $response = $client->request($uri, [], 'GET');
     $status=$response['body']['transcode']['status'];
     if($status=="in_progress"){
         echo '<div class="spinner-border text-warning"></div>
         <span>Your video is still transcoding Refresh again after a while.</span>
         ';
     }
     else{
       
        $time =$DB->get_records_sql("SELECT UNIX_TIMESTAMP(current_Timestamp())-UNIX_TIMESTAMP(timecreated) as time from mdl_vimeo_files where id='$record->id'");
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
    echo '<iframe src="'.$response['body']['player_embed_url'].'" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" width="640" style="height:420px" allowfullscreen></iframe>';
}


// $last_string=substr($record->url, strrpos($record->url, '/') + 1);
// $first_string=preg_replace('/\s+?(\S+)?$/', '', substr($record->url, 0, 18));
// $result=str_replace($first_string, '', $record->url);
// $result=str_replace($last_string, '', $result);

echo $OUTPUT->footer();

