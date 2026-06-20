<?php

require_once('../config.php');
require '../vimeo/vendor/autoload.php';
// require 'BigFileTools/src/BigFileTools.php';

use Vimeo\Vimeo;
// use BigFileTools\BigFileTools;

$PAGE->set_context(get_system_context());
$PAGE->set_pagelayout('site');
$PAGE->set_title("Add Files to vimeo ");
$PAGE->set_heading("Add Files to vimeo");
// $id=required_param('id',PARAM_INT);
$id = $_GET['resource_id'];
$update = $_GET['update'];
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
    //       print '
    //       <div class="alert alert-success" role="alert">
    //       Your video finished transcoding.
    //  </div>
    //       ';
    // redirect($CFG->wwwroot.'/teacherprofile/profile.php?id='.$id.'');
  } elseif ($response['body']['transcode']['status'] === 'in_progress') {
    print '<div class="spinner-border text-warning"></div>
      <span>Your video is still transcoding Refresh again after a while.</span>
      ';
  } else {
    // print 'Your video encountered an error during transcoding.';
  }

  $response = $client->request($uri . '?fields=link');
  return $response['body']['link'];
}

echo $OUTPUT->header();
$admins = get_admins();
$isadmin = false;
foreach ($admins as $admin) {
  if ($USER->id == $admin->id) {
    $isadmin = true;
    break;
  }
}
$roleassignments = $DB->get_records('role_assignments', ['userid' => $USER->id]);
$manager=0;
foreach($roleassignments as $role){
  if($role->roleid==1){
    $manager=1;
    break;
  }
}
$course_module = $DB->get_record('course_modules', array('instance' => $id, 'module' => 18));
// var_dump($course_module);
// if ($isadmin || $manager==1) {

  $url = new moodle_url("/mod/resource/view.php", array('id' => $course_module->id, 'forceview' => 1));

  // var_dump($course_module->id);
  $record = $DB->get_record('resource', array('id' => $id));
  $video = $DB->get_record('vimeo_files', array('resource_id' => $id));
  
  if ($update == 1) {

    if(strpos($video->url, 'iframe') !== false && strpos($video->url, 'nitg-eg.com') !== false){
      echo '  <script>
      $( document ).ready(function() {
        $("#server_form_add").css("display","block");
        $("#video_form").css("display","none");
        $("#update_form").css("display","none");

      });
      </script>
     ';
    }

    elseif (strpos($video->url, 'iframe') !== false) {
      echo '  <script>
 $( document ).ready(function() {
   $("#update_form").css("display","none");
   $("#server_form_add").css("display","none");

   $("#embed_form_update").css("display","block");
 });
 </script>
';
    } 
    elseif(empty($video->url)){
      echo '  <script>
      $( document ).ready(function() {
        $("#update_form").css("display","none");
        $("#server_form_add").css("display","none");
     
        $("#embed_form_update").css("display","block");
      });
      </script>
     ';
    }
    else {
      echo '  <script>
  $( document ).ready(function() {
    $("#update_form").css("display","block");
    $("#embed_form_update").css("display","none");
  });
  </script>
 ';
    }
    echo '<form action="submit.php?resource_id=' . $id . '&update=' . $update . '" method="post" enctype="multipart/form-data" id="update_form">
  <div class="form-check">
  <input class="form-check-input" type="checkbox" value=""name="check" id="check">
  <label class="form-check-label" for="check">
Check to upload a vedio  </label>
</div>

  <div class="form-group">
  <label for="url">Upload a file</label>
  <input type="file" class="form-control-file" id="url_update" name="url_update" accept="video/mp4,video/x-m4v,video/*" disabled>
  </div>
  <div class="form-group">
  <label for="name">Vimeo Name</label>
  <input type="text" class="form-control" idname" name="name" placeholder="Enter a Name" value="' . $video->name . '">
  </div>
  <div class="form-group">
  <label for="description">Vimeo Description</label>
  <input type="text" class="form-control" id="description" name="description" placeholder="Enter a Description" value="' . $video->description . '">
  </div>
  <button type="submit" class="btn btn-primary" id="upload1" name="upload1">Upload</button>
  <a href="' . $url . '" class="btn btn-dark ">Back</a>
  </form>
  <form action="submit.php?resource_id=' . $id . '&update=' . $update . '" method="post" style="display:none;" id="embed_form_update">
  ' . $video->url . '
  <div class="form-group">
  <label for="embed_update">Add an embed link</label>
  <input type="text" class="form-control" id="embed" name="embed_update"  >
  </div>
  <button type="submit" class="btn btn-primary" name="embed_upload_update">Update</button>
  <a href="' . $url . '" class="btn btn-dark ">Back</a>
  </form>
  <form name="upload-form-admin" method="post" style="display:none;"enctype="multipart/form-data" id="server_form_add">
 hi
  <div class="form-group">
  <label for="file">Upload a file</label>
  <input type="file" class="form-control-file" id="file" name="file" accept="video/mp4,video/x-m4v,video/*" >
  </div>
  <button type="submit" class="btn btn-primary" name="submit" id="submitUpdateNit">Update</button>
  <a href="'.$url.'" class="btn btn-dark" >back</a>

  <input id="size" name="size"   style="display:none">
  <input id="state" name="state" style="display:none">
  <progress value="0" max="100"></progress>
  <p class="error"></p>
  <p class="success"></p>
  </form>
  <script>
  $( document ).ready(function() {
      $("#check").click(function(){
      if ($("#check").is(":checked")) {
     
          $("#url_update").prop("disabled", false);
      } else {
          $("#url_update").prop("disabled", true);
      }});
      $("#url_update").bind("change", function() {
        console.log(5);
          });
  });
  </script>
  ';
  } else {
    echo '  <div class="form-check">
  <input class="form-check-input slectOne" type="checkbox"  value=""name="check_vimeo check" id="check_vimeo">
  <label class="form-check-label" for="check_vimeo">
  Check to add an embed link </label>
  </div>
  <!-- <div class="form-check">
  <input class="form-check-input slectOne" type="checkbox" value=""name="check_server check" id="check_server">
  <label class="form-check-label" for="check_server">
  Check to upload to external server </label>
  </div> -->
  
  ';
    echo '<form name="upload-vimeo"   enctype="multipart/form-data" id="video_form">

  <select class="form-select" aria-label="Default select example" name="video_type" required>
  <option value="1" selected>quiz</option>
  <option value="2">Lecutre</option>
  <option value="3">Homework</option>
  <option value="4">Summary</option>
  <option value="5">Revision</option>
</select>
  <div class="form-group">
  <label for="url">Upload a file</label>
  <input type="file" class="form-control-file" id="url" name="url" accept="video/mp4,video/x-m4v,video/*" >
  </div>

  <div class="form-group">
  <label for="name">Vimeo Name</label>
  <input type="text" class="form-control" idname" name="name" placeholder="Enter a Name" value="' . $record->name . '">
  </div>
  <div class="form-group">
  <label for="description">Vimeo Description</label>
  <input type="text" class="form-control" id="description" name="description" placeholder="Enter a Description" value="' . $record->name . '">
  </div>
  <input id="size" name="size"   style="display:none">
  <input id="state" name="state" style="display:none">

  <button type="submit" class="btn btn-primary" name="upload" id="upload">Upload</button>
  <a href="' . $url . '" class="btn btn-dark ">Back</a>
  <progress value="0" max="100"></progress>
  <p class="error"></p>
  <p class="success"></p>
  </form>

  <script>
  document.forms["upload-vimeo"].onsubmit = function(e){
    e.preventDefault();
  

    let file = this.url.files[0];
    let id= "' . $id . '";
    let type= this.video_type.value;
    let name= this.name.value;
    let description= this.description.value;


  
  
    let formdata = new FormData(); 
    formdata.append("url", file); 
    formdata.append("id", id); 
    formdata.append("type", type); 
    formdata.append("name", name);
    formdata.append("description", description);


    let http = new XMLHttpRequest();
    http.upload.addEventListener("progress", function(event){
      let percent = (event.loaded / event.total) * 100;
      document.querySelector("progress").value = Math.round(percent);
    });
  
    http.addEventListener("load", function(){
    
      if(this.readyState == 4 && this.status == 200){
        if(!this.responseText){
          window.location.replace("' . $url . '");
    
        }
        else{
console.log(this.responseText);
            
        }
      }
    });
  
    http.open("post", "script.php");
    http.send(formdata);
  }
  </script>
  <form action="submit.php?resource_id=' . $id . '&update=' . $update . '" method="post" style="display:none;" id="embed_form">
  <select class="form-select" aria-label="Default select example" name="video_type">
  <option value="1" selected>quiz</option>
  <option value="2">Lecutre</option>
  <option value="3">Homework</option>
  <option value="4">Summary</option>
  <option value="5">Revision</option>
</select>
  <div class="form-group">
  <label for="embed">Add an embed link</label>
  <input type="text" class="form-control" id="embed" name="embed"  >
  </div>
  <button type="submit" class="btn btn-primary" name="embed_upload">Add</button>
  <a href="' . $url . '" class="btn btn-dark ">Back</a>

  </form>
  <form  name="upload-form-admin" style="display:none;"enctype="multipart/form-data" id="server_form_add">
  <select class="form-select" aria-label="Default select example" name="type" id="video_type_server">
  <option value="1" selected>quiz</option>
  <option value="2">Lecutre</option>
  <option value="3">Homework</option>
  <option value="4">Summary</option>
  <option value="5">Revision</option>
</select>                                 
  <div class="form-group">
  <label for="file">Upload a file</label>
  <input type="file" class="form-control-file" id="file" name="file" accept="video/mp4,video/x-m4v,video/*" >
  </div>
  <button type="submit" class="btn btn-primary" name="submit" id="submit">Add</button>
  <a href="' . $url . '" class="btn btn-dark ">Back</a>
  <input id="size" name="size"   style="display:none">
  <input id="state" name="state" style="display:none">
  <progress value="0" max="100"></progress>
  <p class="error"></p>
  <p class="success"></p>
  </form>

  <script>
  $( document ).ready(function() {
      $("#check_vimeo").click(function(){
      if ($("#check_vimeo").is(":checked")) {
        $("#embed_form").css("display","block");
        $("#video_form").css("display","none");
        $("#server_form_add").css("display","none");

      } else {
        $("#embed_form").css("display","none");
        $("#video_form").css("display","block");
      }
      $(".slectOne").not(this).prop("checked", false);

    });

    $("#check_server").click(function(){
      if ($("#check_server").is(":checked")) {
        $("#server_form_add").css("display","block");
        $("#video_form").css("display","none");
        $("#embed_form").css("display","none");

      } else {
        $("#server_form_add").css("display","none");
        $("#video_form").css("display","block");
      }
      $(".slectOne").not(this).prop("checked", false);

    });

  });

  </script>
  ';
  }
  $allowedExts = array("mp3", "mp4", "wma");
  $extension = pathinfo($_FILES['url']['name'], PATHINFO_EXTENSION);
  // if ((($_FILES["file"]["type"] == "video/mp4")||($_FILES["file"]["type"] == "video/wma")&& ($_FILES["file"]["size"] < 20000)
  // && in_array($extension, $allowedExts))){
    
  if (isset($_POST['embed_upload_update'])) {

    $ins = new stdClass();
    $ins->id = $video->id;
    if (empty($_POST['embed_update'])) {
      $ins->url = $video->url;
    } else {
      $ins->url = $_POST['embed_update'];
    }
    $ins->id = $DB->update_record('vimeo_files', $ins);
    $url = new moodle_url("/mod/resource/view.php", array('id' => $course_module->id, 'forceview' => 1));
    redirect($url);
  } elseif (isset($_POST['embed_upload'])) {
    $ins = new stdClass();
    $ins->name = "test";
    $ins->description = "test";
    $ins->resource_id = $id;
    $ins->url = $_POST['embed'];
    $ins->id = $DB->insert_record('vimeo_files', $ins);

    $data = new stdClass();
    $data->resource_id = $id;
    $data->type = $_POST['video_type'];
    $data->id = $DB->insert_record('reda_video_type', $data);

    $url = new moodle_url("/mod/resource/view.php", array('id' => $course_module->id, 'forceview' => 1));

    redirect($url);
  } 
  elseif (isset($_POST['upload1'])) {
    $client = new Vimeo("4dad588b7f47a44426afc26f398fe2367ea49c92", "IHRxCFjq5qvsKlU6DjWGfNQwtZGHGmK1pByyCYWGrkWnE9F91BbNqPdqXY+dHVyvKjvRWYTu3ba2A8KM1GR2gcqqYiz+jXAx6uLrsEb0jFJrUSMIi3KMIyS+Je+nsN3s", "195c95a4e775fca8d6e70cb8db4aca73");
    $record = $DB->get_record("vimeo_files", array("resource_id" => $id));
    $last_string = substr($record->url, strrpos($record->url, '/') + 1);
    $first_string = preg_replace('/\s+?(\S+)?$/', '', substr($record->url, 0, 18));
    $result = str_replace('videos/', '', $record->url);
    $uri = "/videos/" . $result;
    // var_dump('hi'.$result);
    $response = $client->request($uri, [], 'GET');
    $ins = new stdClass();
    $ins->id = $record->id;
    if ($_FILES["url_update"]["name"] == "") {
      //var_dump($response);
      $request = $client->request($uri, array(
        'name' => $_POST['name'],
        'description' => $_POST['description']
      ), 'PATCH');
      $ins->name = $_POST['name'];
      $ins->description = $_POST['description'];
      $ins->url = $result;
      $ins->id = $DB->update_record('vimeo_files', $ins);
      redirect($url);

      // var_dump($request);
    } else {
      $response = $client->replace($uri, $_FILES["url_update"]["tmp_name"], []);
      $request = $client->request($uri, array(
        'name' => $_POST['name'],
        'description' => $_POST['description']
      ), 'PATCH');
      $last_word_start = strrpos($response, ' ') + 1; // +1 so we don't include the space in our result
      $last_word = substr($response, $last_word_start);
      $last_word = str_replace('videos/', '', $last_word);
      $ins->name = $_POST['name'];
      $ins->description = $_POST['description'];
      $ins->url = $last_word;
      $ins->id = $DB->update_record('vimeo_files', $ins);
      // var_dump($request);
    }
    $url = new moodle_url("/mod/resource/view.php", array('id' => $course_module->id, 'forceview' => 1));
    redirect($url);
  } 
  else {

    if (isset($_POST['upload'])) {
        $ins = new stdClass();
        $getSize = $DB->get_record('control_max_size', array('userid' => $USER->id));
        if (empty($getSize)) {

          $ins = new stdClass();
          $ins->userid = $USER->id;
          $ins->size = $_POST['size'];
          $ins->max_size = 28311552;
          $date = date('Y-m-d H:i:s');
          $date = strtotime($date);
          $date = strtotime("+7 day", $date);
          $ins->empty = $date;
          $DB->insert_record('control_max_size', $ins);
        } else {
          $up = new stdClass();
          $up->id = $getSize->id;
          $up->size = $_POST['size'];
          $DB->update_record('control_max_size', $up);
        }
        $ins->name = $_POST["name"];
        $ins->description = $_POST["description"];
        $ins->resource_id = $id;

        $data = new stdClass();
        $data->resource_id = $id;
        $data->type = $_POST['video_type'];
        $data->id = $DB->insert_record('reda_video_type', $data);
        $output = vimeo($_FILES["url"]["tmp_name"], $ins->name, $ins->description, $id);
        $last_string = substr($output, strrpos($output, '/') + 1);
        $first_string = preg_replace('/\s+?(\S+)?$/', '', substr($output, 0, 18));
        $result = str_replace($first_string, '', $output);
        $result = str_replace($last_string, '', $result);
        // $result=str_replace('https://vimeo.com/', '', $output);
        $ins->url = $result;
        $ins->id = $DB->insert_record('vimeo_files', $ins);

        // $videoSize = new stdClass();
        // $videoSize->resource_id = $id;
        // $videoSize->size = $_POST['size'];

        // $DB->insert_record('video_size', $videoSize);
        $url = new moodle_url("/mod/resource/view.php", array('id' => $course_module->id, 'forceview' => 1));
        redirect($url);
      
    }

    //var_dump($course_module->id);

    if ($_FILES["url"]["error"] > 0) {
      echo "Return Code: " . $_FILES["file"]["error"] . "<br />";
    }
  }
//   echo '  <script>

  // document.forms["upload-form-admin"].onsubmit = function(e){
  //   e.preventDefault();
  
  //   let error = document.querySelector(".error");
  //   let success = document.querySelector(".success");
  //   let file = this.file.files[0];
  //   let size = this.size.value;
  //   let state = this.state.value;
  //   let type = this.type.value;

  //   error.innerHTML = "";
  // if('.$update.'==0){
  //   if(!file){
  //     error.innerHTML = "Please select a file";
  //     return false;
  //   }
  // }
  
  
  //   let formdata = new FormData(); 
  //   formdata.append("fileA", file); 
  //   formdata.append("stateA", state); 
  //   formdata.append("sizeA", size); 
  //   formdata.append("updateA", '.$update.'); 
  //   formdata.append("cmA", '.$course_module->id.'); 
  //   formdata.append("cmcA", '.$course_module->course.'); 
  //   formdata.append("resource_idA", '.$id.'); 
  //   formdata.append("type", type); 

  //   let http = new XMLHttpRequest();
  //   http.upload.addEventListener("progress", function(event){
  //     let percent = (event.loaded / event.total) * 100;
  //     document.querySelector("progress").value = Math.round(percent);
  //   });
  
  //   http.addEventListener("load", function(){
    
  //     if(this.readyState == 4 && this.status == 200){
  //       if(!this.responseText){
  //         window.location.replace("' . $url . '");
    
  //       }
  //       else{
  //         success.innerHTML = `File ${this.responseText} uploaded successfully from fileA `;
  
  //       }
  //     }
  //   });
  
  //   http.open("post", "script.php");
  //   http.send(formdata);
  // }
// $( document ).ready(function() {

//   if($("#url").get(0).files.length === 0){
//     $("#upload").prop("disabled", true);
//   }
//   else{
//     $("#upload").removeAttr("disabled");;
//   }


//   $("#url").bind("change", function() {
//     $.ajax("ajax.php", {
//       type: "POST",  
//       data: { myData:this.files[0].size,activityid:' . $id . ' },  
//       dataType: "json",
//       success: function (data, status, xhr) {
//       if(data[0]=="limits"){
//         $("#state").val(data[0]);

//         alert("the file size is too big");
//         $("#upload").prop("disabled", true);

//       }
//       else if (data[0]=="maximum"){
//         $("#state").val(data[0]);

//         alert("You have reached the maximum number of videos this week");

//         $("#upload").prop("disabled", true);
//       }
//       else if (data[0]=="still") {
//         $("#size").val(data[1]);
//         $("#state").val(data[0]);

//         $("#upload").removeAttr("disabled");;
//       }
//       },
//       error: function (jqXhr, textStatus, errorMessage) {
//         }
//     });
  
//   });

 
// });
// </script>
// ';
// if (isset($_POST['submit'])) {
//   $resource = $DB->get_record('vimeo_files', array('resource_id' => $id));

//   $ftp_server = "ftp.nitg-eg.com";
//   // name file in serverA that you want to store file in serverB
//   $file = $_FILES['file']['tmp_name'];
//   date_default_timezone_set("Africa/Egypt");
//   $server_root = "public_html/academyvideos/";
//   $file_name = date("Y-m-d h:i:sa") . $_FILES['file']['name'];
//   $file_name = preg_replace('/\s+/', '', $file_name);

//   $remote_file = '/' . $server_root . '' . $file_name;
//   $ftp_user_name = "nitgegco";
//   $ftp_user_pass = "Nitg@2019AHmed";
//   try {
//     $con = ftp_connect($ftp_server);
//     if (false === $con) {
//       throw new Exception('Unable to connect');
//     }

//     $loggedIn = ftp_login($con,  $ftp_user_name,  $ftp_user_pass);
//     ftp_set_option($con, FTP_USEPASVADDRESS, false);
//     ftp_pasv($con, true);

//     if (true === $loggedIn) {
//       // echo 'Success!';


//       if ($update == 1 && !empty($_FILES['file']['name'])) {
//         $upload = ftp_put($con, $remote_file, $file, FTP_BINARY);

//         if ($upload) {
//           $up = new stdClass();
//           $up->id = $resource->id;
//           $up->name = $file_name;
//           $up->description = $file_name;
//           $up->url = "<iframe src='https://nitg-eg.com/academyvideos/" . $file_name . "'></iframe>";
//           $DB->update_record('vimeo_files', $up);
//           $url = new moodle_url("/mod/resource/view.php", array('id' => $course_module->id, 'forceview' => 1));

//           redirect($url);
//         } else {
//           echo "There was a problem while uploading $file\n";
//         }
  
//       }
//       elseif($update == 1 && empty($_FILES['file']['name'])){
//         $url = new moodle_url("/mod/resource/view.php", array('id' => $course_module->id, 'forceview' => 1));

//         redirect($url);
//       }
//       elseif ($update == 0 && !empty($_FILES)) {
//         $upload = ftp_put($con, $remote_file, $file, FTP_BINARY);
//         if ($upload) {
//           $ins = new stdClass();
//           $ins->name = $file_name;
//           $ins->description = $file_name;
//           $ins->resource_id = $id;
//           $ins->url = "<iframe src='https://nitg-eg.com/academyvideos/" . $file_name . "'></iframe>";
//           // var_dump($ins);
//           $ins->id = $DB->insert_record('vimeo_files', $ins);
//           $url = new moodle_url("/mod/resource/view.php", array('id' => $course_module->id, 'forceview' => 1));

//           redirect($url);
//         } else {
//           echo "There was a problem while uploading $file\n";
//         }
      
//       }
      

//     } else {
//       throw new Exception('Unable to log in');
//     }

//     ftp_close($con);
//   } catch (Exception $e) {
//     echo "Failure: " . $e->getMessage();
//   }
// }
// }
// $teacherRole = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'));
// $isTeacher = $DB->record_exists('role_assignments', ['userid' => $USER->id, 'roleid' => $teacherRole]);
// // if ($isTeacher&& !$isadmin) {
//   if ($update == 1) {
//     $resource = $DB->get_record('vimeo_files', array('resource_id' => $id));
//     echo "<div class='container'>" . $resource->url . "</div>";
//   }
//   $url = new moodle_url("/mod/resource/view.php", array('id' => $course_module->id, 'forceview' => 1));

//   echo '<form name="upload-form">
//   <select class="form-select" aria-label="Default select example" name="video_type">
//   <option value="1" selected>quiz</option>
//   <option value="2">Lecutre</option>
//   <option value="3">Homework</option>
//   <option value="4">Summary</option>
//   <option value="5">Revision</option>
// </select>
//   <div class="form-group">

// <input type="file" name="file" id="url" class="form-control form-control-file">
// </div>
// <input type="submit" value="Submit" name="submit" class="btn btn-primary" id="upload">

// <a href="' . $url . '" class="btn btn-dark ">Back</a>
// <input id="size" name="size"   style="display:none">
// <input id="state" name="state" style="display:none">
// <progress value="0" max="100"></progress>
// <p class="error"></p>
// <p class="success"></p>
// </form>


// <script>
// document.forms["upload-form"].onsubmit = function(e){
// 	e.preventDefault();

// 	let error = document.querySelector(".error");
// 	let success = document.querySelector(".success");
// 	let file = this.file.files[0];
//   let size = this.size.value;
//   let state = this.state.value;
//   let type = this.video_type.value;

// 	error.innerHTML = "";
// if('.$update.'==0){
// 	if(!file){
// 		error.innerHTML = "Please select a file";
// 		return false;
// 	}
// }


// 	let formdata = new FormData(); 
// 	formdata.append("file", file); 
// 	formdata.append("state", state); 
//   formdata.append("size", size); 
//   formdata.append("type", type); 

//   formdata.append("update", '.$update.'); 
//   formdata.append("cm", '.$course_module->id.'); 
//   formdata.append("cmc", '.$course_module->course.'); 
//   formdata.append("resource_id", '.$id.'); 

// 	let http = new XMLHttpRequest();
// 	http.upload.addEventListener("progress", function(event){
// 		let percent = (event.loaded / event.total) * 100;
// 		document.querySelector("progress").value = Math.round(percent);
// 	});

// 	http.addEventListener("load", function(){
  
// 		if(this.readyState == 4 && this.status == 200){
//       if(!this.responseText){
//         window.location.replace("' . $url . '");
  
//       }
//       else{
//         success.innerHTML = `File ${this.responseText} uploaded successfully file`;

//       }
// 		}
// 	});

// 	http.open("post", "script.php");
// 	http.send(formdata);
// }
// $(document).ready(function(){

//   $("#url").bind("change", function() {
//     $.ajax("ajax.php", {
//       type: "POST",  
//       data: { myData:this.files[0].size,activityid:' . $id . ' },  
//       dataType: "json",
//       success: function (data, status, xhr) {
//       if(data[0]=="limits"){
//         $("#state").val(data[0]);

//         alert("the file size is too big");
//         $("#upload").prop("disabled", true);

//       }
//       else if (data[0]=="maximum"){
//         $("#state").val(data[0]);

//         alert("You have reached the maximum size You have to wait till the next week to renew your uploads");

//         $("#upload").prop("disabled", true);
//       }
//       else if (data[0]=="still") {
//         $("#size").val(data[1]);
//         $("#state").val(data[0]);

//         $("#upload").removeAttr("disabled");;
//       }
//       },
//       error: function (jqXhr, textStatus, errorMessage) {
//         }
//     });
  
//   });
// });
// </script>
// ';
// $enrolled_students = $DB->get_records_sql("SELECT COUNT(u.id) As total
// FROM mdl_course c LEFT OUTER JOIN mdl_context cx ON c.id = cx.instanceid 
// LEFT OUTER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
//  LEFT OUTER JOIN mdl_user u ON ra.userid = u.id 
//  WHERE cx.contextlevel = '50' AND c.id=$course_module->course");
//  $total=0;
// foreach($enrolled_students as $enrolled_student){
// $total=$enrolled_student->total;
// }
//   if (isset($_POST['submit'])) {
//     if ($_POST['state'] == "still") {
//       $ins = new stdClass();
//       $getSize = $DB->get_record('control_max_size', array('userid' => $USER->id));
//       if (empty($getSize)) {

//         $ins = new stdClass();
//         $ins->userid = $USER->id;
//         $ins->size = $_POST['size'];
//         $ins->max_size =  2097152*$total;//in bytes
//         date_default_timezone_set("Africa/Egypt");
//         $date = date('Y-m-d H:i:s');
//         $date = strtotime($date);
//         $date = strtotime("+7 day", $date);
//         $ins->empty = $date;
//         $DB->insert_record('control_max_size', $ins);
//       } else {
  

//        $up = new stdClass();
//         $up->id = $getSize->id;
//         $up->size = $_POST['size'];
//         $DB->update_record('control_max_size', $up);
//       }
//     }
//     $ftp_server = "ftp.nitg-eg.com";
//     // name file in serverA that you want to store file in serverB
//     $file = $_FILES['file']['tmp_name'];
//     date_default_timezone_set("Africa/Egypt");
//     $server_root = "public_html/academyvideos/";
//     $file_name = date("Y-m-d h:i:sa") . $_FILES['file']['name'];
//     $file_name = preg_replace('/\s+/', '', $file_name);

//     $remote_file = '/' . $server_root . '' . $file_name;
//     $ftp_user_name = "nitgegco";
//     $ftp_user_pass = "Nitg@2019AHmed";
//     try {
//       $con = ftp_connect($ftp_server);
//       if (false === $con) {
//         throw new Exception('Unable to connect');
//       }

//       $loggedIn = ftp_login($con,  $ftp_user_name,  $ftp_user_pass);
//       ftp_set_option($con, FTP_USEPASVADDRESS, false);
//       ftp_pasv($con, true);

//       if (true === $loggedIn) {
//         // echo 'Success!';


//         if ($update == 1 && !empty($_FILES['file']['name'])) {
//           $upload = ftp_put($con, $remote_file, $file, FTP_BINARY);

//           if ($upload) {
//             $up = new stdClass();
//             $up->id = $resource->id;
//             $up->name = $file_name;
//             $up->description = $file_name;
//             $up->url = "<iframe src='https://nitg-eg.com/academyvideos/" . $file_name . "'></iframe>";
//             $DB->update_record('vimeo_files', $up);
//             $url = new moodle_url("/mod/resource/view.php", array('id' => $course_module->id, 'forceview' => 1));

//             redirect($url);
//           } else {
//             echo "There was a problem while uploading $file\n";
//           }
    
//         }
//         elseif($update == 1 && empty($_FILES['file']['name'])){

//           redirect($url);
//         }
//         elseif ($update == 0 && !empty($_FILES)) {
//           $upload = ftp_put($con, $remote_file, $file, FTP_BINARY);
//           if ($upload) {
//             $ins = new stdClass();
//             // $ins->name = $file_name;
//             // $ins->description =$file_name;
//             $ins->name ="test";
//             $ins->description ="test";
//             $ins->resource_id = $id;
//             $ins->url = "<iframe src='https://nitg-eg.com/academyvideos/" . $file_name . "'></iframe>";
//             // var_dump($ins);
//             $ins->id = $DB->insert_record('vimeo_files', $ins);
//             $url = new moodle_url("/mod/resource/view.php", array('id' => $course_module->id, 'forceview' => 1));

//             redirect($url);
//           } else {
//             echo "There was a problem while uploading $file\n";
//           }
        
//         }
        

//       } else {
//         throw new Exception('Unable to log in');
//       }

//       ftp_close($con);
//     } catch (Exception $e) {
//       echo "Failure: " . $e->getMessage();
//     }
//   }
// }
echo $OUTPUT->footer();
