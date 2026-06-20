<div class="form-check">
  <input class="form-check-input slectOne" type="checkbox"  value=""name="check_vimeo check" id="check_vimeo">
  <label class="form-check-label" for="check_vimeo">
  Check to add an embed link </label>
</div>
<!-- <div class="form-check">
<input class="form-check-input slectOne" type="checkbox" value=""name="check_server check" id="check_server">
<label class="form-check-label" for="check_server">
Check to upload to external server </label>
</div> -->

<form name="upload-vimeo"   enctype="multipart/form-data" id="video_form">
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
		<input type="file" style="display: none;" id="pupload" />
	</div>

	<div class="form-group">
		<label for="name">Vimeo Name</label>
		<input type="text" class="form-control" idname" name="name" placeholder="Enter a Name" value="<?php echo $record->name ?>">
	</div>
	<div class="form-group">
		<label for="description">Vimeo Description</label>
		<input type="text" class="form-control" id="description" name="description" placeholder="Enter a Description" value="<?php echo $record->name?>">
	</div>
  <input id="size" name="size"   style="display:none">
  <input id="state" name="state" style="display:none">

  <button type="submit" class="btn btn-primary" name="upload" id="upload">Upload</button>
  <a href="<?php echo $url?>" class="btn btn-dark ">Back</a>
  <div class="row">

  <progress value="0" max="100"></progress>
  <p id="value"></p>
  </div>
  <p class="error"></p>
  <p class="success"></p>
  </form>

  <script type="text/javascript">
	const VALUE_EL = document.getElementById("value");
	var uploader = new plupload.Uploader({
		browse_button: 'pupload',
	  url: 'upload_chunked.php',
	  chunk_size: '10mb',
	  max_retries: 3
	});
	 
	uploader.init();
	 
	uploader.bind('UploadProgress', function(up, file) {
		VALUE_EL.innerHTML = Math.round(file.percent) +" % " + __2MegaBytes(file.loaded).toString() + "/"+ __2MegaBytes(file.size).toString() + "Mega";
	});

	uploader.bind('UploadComplete', function(up, file) {
		window.location.replace("<?php echo $url ?>");
	});
	 
	uploader.bind('Error', function(up, err) {
		console.log(err.code + "\n" + err.message);
	});
	
	function __2MegaBytes(num) {
		return (num/1024/1024).toFixed(2); //change Bytes to MegaBytes
	}
	document.forms["upload-vimeo"].onsubmit = function(e){
		e.preventDefault();
		let id= "<?php echo $id?>";
		let type= this.video_type.value;
		let name= this.name.value;
		let description= this.description.value;

		for(var i in uploader.files) {
			uploader.removeFile(uploader.files[i]);
		}
		uploader.addFile(this.url.files[0]);
		uploader.settings.multipart_params = {
			"did" : id,
			"dtype" : type,
			"dname" : name,
			"ddescription" : description,
		};
		uploader.start();
	};
  </script>
  <form action="submit.php?resource_id=<?php echo $id?>&update=<?php echo $update?>" method="post" style="display:none;" id="embed_form">
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
  <a href="<?php echo $url ?>" class="btn btn-dark ">Back</a>

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
  <a href="<?php echo $url?>" class="btn btn-dark ">Back</a>
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