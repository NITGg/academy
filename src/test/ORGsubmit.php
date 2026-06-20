<?php

require_once('../config.php');
require '../vimeo/vendor/autoload.php';

use Vimeo\Vimeo;

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
$course_module = $DB->get_record('course_modules', array('instance' => $id, 'module' => 18));
$url = new moodle_url("/mod/resource/view.php", array('id' => $course_module->id, 'forceview' => 1));

// var_dump($course_module->id);
$record = $DB->get_record('resource', array('id' => $id));
$video = $DB->get_record('vimeo_files', array('resource_id' => $id));
if ($update == 1) {
  if (strpos($video->url, 'iframe') !== false) {
    echo '  <script>
 $( document ).ready(function() {
   $("#update_form").css("display","none");
   $("#embed_form_update").css("display","block");
 });
 </script>
';
  } else {
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
  <input type="file" class="form-control-file" id="url" name="url" accept="video/mp4,video/x-m4v,video/*" disabled>
  </div>
  <div class="form-group">
  <label for="name">Vimeo Name</label>
  <input type="text" class="form-control" idname" name="name" placeholder="Enter a Name" value="' . $video->name . '">
  </div>
  <div class="form-group">
  <label for="description">Vimeo Description</label>
  <input type="text" class="form-control" id="description" name="description" placeholder="Enter a Description" value="' . $video->description . '">
  </div>
  <button type="submit" class="btn btn-primary" name="upload1">Upload</button>
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
  <script>
  $( document ).ready(function() {
      $("#check").click(function(){
      if ($("#check").is(":checked")) {
     
          $("#url").prop("disabled", false);
      } else {
          $("#url").prop("disabled", true);
      }});
  });
  </script>
  ';
} else {
  echo '  <div class="form-check">
  <input class="form-check-input" type="checkbox" value=""name="check_vimeo" id="check_vimeo">
  <label class="form-check-label" for="check_vimeo">
  Check to add an embed link </label>
  </div>';
  echo '<form action="submit.php?resource_id=' . $id . '&update=' . $update . '" method="post" enctype="multipart/form-data" id="video_form">

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
  <button type="submit" class="btn btn-primary" name="upload">Upload</button>
  <a href="' . $url . '" class="btn btn-dark ">Back</a>

  </form>
  <form action="submit.php?resource_id=' . $id . '&update=' . $update . '" method="post" style="display:none;" id="embed_form">
  <div class="form-group">
  <label for="embed">Add an embed link</label>
  <input type="text" class="form-control" id="embed" name="embed"  >
  </div>
  <button type="submit" class="btn btn-primary" name="embed_upload">Add</button>
  <a href="' . $url . '" class="btn btn-dark ">Back</a>

  </form>
  <script>
  $( document ).ready(function() {
      $("#check_vimeo").click(function(){
      if ($("#check_vimeo").is(":checked")) {
        $("#embed_form").css("display","block");
        $("#video_form").css("display","none");
      } else {
        $("#embed_form").css("display","none");
        $("#video_form").css("display","block");
      }
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
    }
    else{
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
  $url = new moodle_url("/mod/resource/view.php", array('id' => $course_module->id, 'forceview' => 1));
  redirect($url);
} elseif (isset($_POST['upload1'])) {
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
  if ($_FILES["url"]["name"] == "") {
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
    $response = $client->replace($uri, $_FILES["url"]["tmp_name"], []);
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
} else {
  if ($_FILES["url"]["error"] > 0) {
    echo "Return Code: " . $_FILES["file"]["error"] . "<br />";
  }
  if (isset($_POST['upload'])) {
    $ins = new stdClass();

    $ins->name = $_POST["name"];
    $ins->description = $_POST["description"];
    $ins->resource_id = $id;
    $output = vimeo($_FILES["url"]["tmp_name"], $ins->name, $ins->description, $id);
    $last_string = substr($output, strrpos($output, '/') + 1);
    $first_string = preg_replace('/\s+?(\S+)?$/', '', substr($output, 0, 18));
    $result = str_replace($first_string, '', $output);
    $result = str_replace($last_string, '', $result);
    // $result=str_replace('https://vimeo.com/', '', $output);
    $ins->url = $result;
    $ins->id = $DB->insert_record('vimeo_files', $ins);
    $url = new moodle_url("/mod/resource/view.php", array('id' => $course_module->id, 'forceview' => 1));
    redirect($url);
    //var_dump($course_module->id);
  }
}


echo $OUTPUT->footer();
