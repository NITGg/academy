<form name="upload-vimeo-update" enctype="multipart/form-data" id="update_form">
  <div class="form-check">
	  <input class="form-check-input" type="checkbox" value="" name="check" id="check">
	  <label class="form-check-label" for="check">Check to upload a vedio  </label>
  </div>
	<select class="form-select" aria-label="Default select example" name="video_type" >
		<option value="1" <?php if($type==1){echo "selected";} ?>>quiz</option>
		<option value="2" <?php if($type==2){echo "selected";} ?>>Lecutre</option>
		<option value="3" <?php if($type==3){echo "selected";} ?>>Homework</option>
		<option value="4" <?php if($type==4){echo "selected";} ?>>Summary</option>
		<option value="5" <?php if($type==5){echo "selected";} ?>>Revision</option>
	</select>
  <div class="form-group">
	  <label for="url">Upload a file</label>
	  <input type="file" class="form-control-file" id="url_update" name="url_update" accept="video/mp4,video/x-m4v,video/*" disabled />
	  <input type="file" style="display: none;" id="pupload" />
  </div>
  <div class="form-group">
	  <label for="name">Vimeo Name</label>
	  <input type="text" class="form-control" name="name" placeholder="Enter a Name" value="<?php echo $video->name?>" />
  </div>
  <div class="form-group">
	  <label for="description">Vimeo Description</label>
	  <input type="text" class="form-control" id="description" name="description" placeholder="Enter a Description" value="<?php echo $video->description ?>" />
  </div>
  <button type="submit" class="btn btn-primary" id="upload1" name="upload1">Upload</button>
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
	document.forms["upload-vimeo-update"].onsubmit = function(e){
		e.preventDefault();
		let id= "<?php echo $id?>";
		let type= this.video_type.value;
		let name= this.name.value;
		let description= this.description.value;
		let isChecked = document.getElementById('check').checked;

		uploader.settings.multipart_params = {
			"did" : id,
			"dtype" : type,
			"dname" : name,
			"ddescription" : description,
			"update_form": 1,
			"files_count": isChecked? 1 : 0
		};
		
		for(var i in uploader.files) {
			uploader.removeFile(uploader.files[i]);
		}
		
		if (isChecked) {
			if (this.url_update.files.length > 0) {
				uploader.addFile(this.url_update.files[0]);
			}
		}
		else {
			var file = new moxie.file.Blob(null, new Blob(['HelloWorld']));
			file.name = "update_dummy_for_uploading.mp4"; // you need to give it a name here (required)
			uploader.addFile(file);
		}
		
		uploader.start();
	};
  </script>

<form action="submit.php?resource_id=<?php echo $id ?>&update=<?php echo $update ?>" method="post" style="display:none;" id="embed_form_update">
	<?php echo $video->url ?>
	<div class="form-group">
		<label for="embed_update">Add an embed link</label>
		<input type="text" class="form-control" id="embed" name="embed_update"  />
	</div>
	<button type="submit" class="btn btn-primary" name="embed_upload_update">Update</button>
	<a href="<?php echo $url ?>" class="btn btn-dark ">Back</a>
</form>
<form name="upload-form-admin" method="post" style="display:none;"enctype="multipart/form-data" id="server_form_add">
	hi
	<div class="form-group">
		<label for="file">Upload a file</label>
		<input type="file" class="form-control-file" id="file" name="file" accept="video/mp4,video/x-m4v,video/*" >
	</div>
	<button type="submit" class="btn btn-primary" name="submit" id="submitUpdateNit">Update</button>
	<a href="<?php echo $url?>" class="btn btn-dark" >back</a>

	<input id="size" name="size"   style="display:none" />
	<input id="state" name="state" style="display:none" />
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
	  
  });
</script>
<?php if(strpos($video->url, 'iframe') !== false && strpos($video->url, 'nitg-eg.com') !== false){ ?>
  
      <script>
		  $( document ).ready(function() {
			$("#server_form_add").css("display","block");
			$("#video_form").css("display","none");
			$("#update_form").css("display","none");

		  });
      </script>
     
<?php } elseif (strpos($video->url, 'iframe') !== false) { ?>
      <script>
		 $( document ).ready(function() {
		   $("#update_form").css("display","none");
		   $("#server_form_add").css("display","none");

		   $("#embed_form_update").css("display","block");
		 });
	 </script>

<?php } elseif(empty($video->url)){ ?>
      <script>
		  $( document ).ready(function() {
			$("#update_form").css("display","none");
			$("#server_form_add").css("display","none");
		 
			$("#embed_form_update").css("display","block");
		  });
      </script>
     
<?php } ?>