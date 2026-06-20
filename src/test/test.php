<?php
// require('../config.php');
// require '../vimeo/vendor/autoload.php';
// use Vimeo\Vimeo;
// $client = new Vimeo("4dad588b7f47a44426afc26f398fe2367ea49c92", "IHRxCFjq5qvsKlU6DjWGfNQwtZGHGmK1pByyCYWGrkWnE9F91BbNqPdqXY+dHVyvKjvRWYTu3ba2A8KM1GR2gcqqYiz+jXAx6uLrsEb0jFJrUSMIi3KMIyS+Je+nsN3s", "195c95a4e775fca8d6e70cb8db4aca73");
// $course_modules=$DB->get_record("course_modules",array('id'=>710));
// $video=$DB->get_record("resource",array("id"=>$course_modules->instance));
// $record=$DB->get_record("vimeo_files",array('resource_id'=>$video->id));
// $uri="/videos/".$record->url;
// $response = $client->request($uri, [], 'GET');
// echo$response['body']['embed']['html'] ;


require_once('../config.php');
// $additionalUserInfo->userid =5;
// $additionalUserInfo->age ="5";
// $additionalUserInfo->check_ecducation =1;
// $additionalUserInfo->ecducation = "sdfds";
// $additionalUserInfo->check1 = 1;
// $additionalUserInfo->check2 = "sada";

// $additionalUserInfo->gov =6;
// $additionalUserInfo->city =7;
// $additionalUserInfo->gender =1;
// $additionalUserInfo->prefrences =  '[' . implode(',', $prefrences) . ']';
// $additionalUserInfo->centers =  '[' . implode(',', $centers) . ']';
// $additionalUserInfo->job="sdsa";
// $additionalUserInfo->secondname="sada";
// $additionalUserInfo->thirdname="asdas";
// $additionalUserInfo->id = $DB->insert_record('additional_user_data', $additionalUserInfo);

// $teachers=$DB->get_records_sql("SELECT u.id As id,concat(u.firstname , ' ', u.lastname)as teachername, c.fullname As coursename
// FROM   mdl_course c
// LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
// LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
// LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id 
// WHERE cx.contextlevel = '50' GROUP by c.id");
// var_dump($teachers);
echo'  <script src="https://vjs.zencdn.net/7.19.2/video.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>';

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
echo '
<div class="homepage-video row">
<iframe id="myframe"src="https://player.vimeo.com/video/687677966?h=e7b7b67427&badge=0&autopause=0&player_id=0&app_id=58479"allow="fullscreen" class="video-js"></iframe>
	<div class="moveToVideoJs">
	<div class="advertisement" >1509</div>
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
			  var myFrame = $('#myframe').contents().find('body');
			  var textareaValue = 1509;
			  myFrame.html(textareaValue);
	  });
	
	
	</script>";    echo "<script>

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
	$('.advertisement').show();  

            } else {
                $('.advertisement')
                .appendTo($('.video-js ' ));   
                $('.advertisement').css('width',length*12+'px');
                $('.advertisement').css('height',length*6+'px');
				$('.advertisement').show();  
            }
          });
         
  });


</script>";
?>
<!-- <form name="upload-form" > -->
<!-- <input type="text" name="fd"> -->
        <!-- <input name="file" type="file">
		<input type="submit" name="submit" value="Upload file">

		<progress value="0" max="100"></progress>
		<p class="error"></p>
		<p class="success"></p>
</form> -->
<!-- <script>
document.forms['upload-form'].onsubmit = function(e){
	e.preventDefault();

	let error = document.querySelector(".error");
	let success = document.querySelector(".success");
	let file = this.file.files[0];  
	error.innerHTML = "";

	if(!file){
		error.innerHTML = "Please select a file";
		return false;
	}

	let formdata = new FormData(); 
	formdata.append("file", file); 
	
	let http = new XMLHttpRequest();
	http.upload.addEventListener("progress", function(event){
		let percent = (event.loaded / event.total) * 100;
		document.querySelector("progress").value = Math.round(percent);
	});

	http.addEventListener("load", function(){
		if(this.readyState == 4 && this.status == 200){
			success.innerHTML = `File ${this.responseText} uploaded successfully`;
		}
	});

	http.open("post", "script.php");
	http.send(formdata);
}
</script> -->
<?php
// if (isset($_POST['submit'])) {
//     $ftp_server = "medadaa.com";
//     // name file in serverA that you want to store file in serverB
//     $file = $_FILES['file']['tmp_name'];
//     date_default_timezone_set("Africa/Egypt");
//     $remote_file = '/new.medadaa.com/videos/' . date("Y-m-d h:i:sa") . $_FILES['file']['name'];
//     $ftp_user_name = "ftpuser";
//     $ftp_user_pass = "P&h3-V(im-";
//     // set up basic connection
//     // $conn_id = ftp_connect($ftp_server)or die("Unable to connect to host");;


//     // // login with username and password
//     // $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass)or die("Authorization failed");
//     try {
//         $con = ftp_connect($ftp_server);
//         if (false === $con) {
//             throw new Exception('Unable to connect');
//         }

//         $loggedIn = ftp_login($con,  $ftp_user_name,  $ftp_user_pass);
//         ftp_set_option($con, FTP_USEPASVADDRESS, false);
//         ftp_pasv($con, true);

//         if (true === $loggedIn) {
//             echo 'Success!';
//             if (ftp_put($con, $remote_file, $file, FTP_BINARY)) {
//                 echo "successfully uploaded $file\n";
//             } else {
//                 echo "There was a problem while uploading $file\n";
//             }
//         } else {
//             throw new Exception('Unable to log in');
//         }

//         // $contents_on_server=ftp_nlist($con,$remote_file);
//         // var_dump($contents_on_server);
//         ftp_close($con);
//     } catch (Exception $e) {
//         echo "Failure: " . $e->getMessage();
//     }
//     // echo "file".$file."<br>";
//     // echo "remote_file".$remote_file."<br>";
//     // echo "ftp_user_name".$ftp_user_name."<br>";
//     // echo "ftp_user_pass".$ftp_user_pass."<br>";
//     // echo "conn_id".$conn_id."<br>";
//     // echo "login_result".$login_result."<br>";
//     // $contents_on_server = ftp_nlist($conn_id, $path);
//     // var_dump($contents_on_server);
//     // upload a file
//     // $passive=ftp_pasv($conn_id, true) or die("Unable switch to passive mode");
//     // if (ftp_put($conn_id, $remote_file, $file, FTP_BINARY)) {
//     //  echo "successfully uploaded $file\n";
//     // } else {
//     //  echo "There was a problem while uploading $file\n";
//     // }
//     //     ftp_close($conn_id);
//     // //     $file = $_FILES['file']['tmp_name'];
//     // // $mime = mime_content_type($file);
//     // // $info = pathinfo($file);
//     // // $name = $info['basename'];
//     // // $data =  new CURLFile($file, $mime, $name);
//     // // $data = array(
//     // //     "file" => $output,
//     // //     'calling_method' => 'upload_file'
//     // // );
//     // // $ch = curl_init();

//     // // curl_setopt($ch, CURLOPT_URL,'https://academy.nitg-eg.com/test');
//     // // curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
//     // // curl_setopt($ch, CURLOPT_TIMEOUT, 86400); // 1 Day Timeout
//     // // curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60000);
//     // // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     // // curl_setopt($ch, CURLOPT_REFERER, $_SERVER['HTTP_HOST']);
//     // // $moved = move_uploaded_file($_FILES['file']['tmp_name'], 'https://academy.nitg-eg.com/test');
//     // // redirect("https://new.medadaa.com/videos/");
//     // file_get_contents('https://new.medadaa.com/videos/index.php?file='.$_FILES['file']['tmp_name']);
//     // $file_location=$_SERVER["DOCUMENT_ROOT"]."/test/sdsa.mp4";
//     // echo $file_location;
//     //     if(move_uploaded_file($_FILES['file']['tmp_name'], $file_location)){

//     //         echo 'Files has uploaded'; 
//     //     }
//     //     else{
//     //         echo 'Files has not uploaded';
//     //     }

// }
// echo $OUTPUT->footer(); ?>