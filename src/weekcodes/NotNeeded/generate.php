
<?php 

require_once('../redaElkassas/academyApi/json.php');

$records=$DB->get_records_sql("SELECT c.fullname as coursename ,c.id as cid FROM mdl_course c LEFT OUTER JOIN mdl_context cx ON c.id = cx.instanceid LEFT OUTER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3' LEFT OUTER JOIN mdl_user u ON ra.userid = u.id WHERE cx.contextlevel = '50' AND u.id=4170");

$admins = get_admins();
$isadmin = false;
foreach ($admins as $admin) {
    if ($USER->id == $admin->id) {
        $isadmin = true;
        break;
    }
}
if(!$isadmin){
redirect($CFG->wwwroot);
}
else{
    if(isset($_POST['generate'])){
        $course=$_POST['courseid'];
        $codesnumber=$_POST['codeslength'];
        $views=$_POST['viewsnumber'];
        $last=$_POST['time'];
        $name=$_POST['name'];
    
        $check_name=$DB->get_record('codes_generator_patch',array('name'=>$name));
        if(empty($check_name)){
            for($i=0;$i<$codesnumber;$i++){
                $code=generate_random_string(10);
                $check_code=$DB->get_record('codes_generator_patch',array('code'=>$code,'course'=>$course));
                if(empty($check_code)){
                    $ins= new stdClass();
                    $ins->code=$code;
                    $ins->course=$course;
                    $ins->last=$last;
                    $ins->number_of_tries=$views;
                    $ins->name=$name;
        
                    $ins->id=$DB->insert_record('codes_generator_patch',$ins);
                }
                else{
                    $code=generate_random_string(10);
                    $ins= new stdClass();
                    $ins->code=$code;
                    $ins->course=$course;
                    $ins->last=$last;
                    $ins->number_of_tries=$views;
                    $ins->name=$name;
        
                    $ins->id=$DB->insert_record('codes_generator_patch',$ins);
                }
         
            }
        }
        else{
            echo "choose another name";
        }
      
    
    
    }
    
}
echo $OUTPUT->header();
?>

<body>
    <a href="dashboard.php" class="btn btn-warning "> Dashboard</a>
    <div class="container">
        <div id="error" class="alert alert-danger "><?php echo $error?></div>

        <div class="container">
            <div class="generate_options">
                <form action="" method="POST" >
                    
                    <div class="form-group">
                        <label for="coursename">Course Name</label>
                        <select  class="form-control form-control-sm text mb-2" name="courseid" id="coursename" required>
                        <?php
                            foreach($records as $record){
                                echo '
                                <option value="'.$record->cid.'">'.$record->coursename.' </option>
                                
                                ';
                            }
                        
                        ?>        
                                
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="coursename">Number Of Codes</label>
                        <input type="number" min="1" class="form-control" name="codeslength"  id="codeslength" aria-describedby="emailHelp" placeholder="Number Of Codes"value="<?php if(isset($_POST['codeslength'])){echo $_POST['codeslength'];}?>" required>

                    </div>
                    <div class="form-group">
                        <label for="coursename">Number of Views </label>
                        <input type="number" min="1" class="form-control" name="viewsnumber" id="viewsnumber" aria-describedby="emailHelp" placeholder="Number Of Views"value="<?php if(isset($_POST['viewsnumber'])){echo $_POST['viewsnumber'];}?>" required>

                    </div>
                    <div class="form-group">
                        <label for="coursename">Time by hours </label>
                        <input type="number" min="1" class="form-control" name="time" id="time" aria-describedby="emailHelp" placeholder="Hours"value="<?php if(isset($_POST['time'])){echo $_POST['time'];}?>" required>

                    </div>
                    <div class="form-group">
                        <label for="coursename">Patch Name </label>
                        <input type="text" min="1" class="form-control" name="name" id="name" aria-describedby="emailHelp" placeholder="name"value="<?php if(isset($_POST['name'])){echo $_POST['name'];}?>" required>

                    </div>
                    
                    <button type="submit" name="generate" class="btn btn-primary">Generate</button>
                </form>
            </div>

        </div>

    </div>



<script>
    let err = document.getElementById("error");
    let input = document.getElementById("code");

    if(err.firstChild == null){
        err.style.display="none"
    }


</script>
</body>
<?php
echo $OUTPUT->footer();
?>