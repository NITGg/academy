<?php

require_once("../config.php");
$admins = get_admins();
$isadmin = false;
foreach ($admins as $admin) {
    if ($USER->id == $admin->id) {
        $isadmin = true;
        break;
    }
}
if($isadmin){
echo $OUTPUT->header();
$teachers = $DB->get_records_sql("SELECT DISTINCT u.*  FROM mdl_user as u INNER JOIN mdl_role_assignments as role ON role.userid=u.id and role.roleid=3");
function generate_random_string($length)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $index = rand(0, strlen($characters) - 1);
        $randomString .= $characters[$index];
    }

    return $randomString;
}

?>
<a class="btn btn-primary" href="<?php echo $CFG->wwwroot?>/Codes/dashboard.php">Dashboard</a>

<form action="index.php" method="POST">

    <div class="form-group">
        <label for="teacher">Teacher</label>

        <select name="teacher" id="teacher" class="form-control">
        <option value="0">Choose a Teacher</option>

            <?php
            foreach ($teachers as $teacher) {
                echo "<option value='" . $teacher->id . "'>" . $teacher->firstname . " " . $teacher->lastname . "</option>";
            }
            ?>
        </select>
    </div>
    <label for="radio">Choose the types of codes</label>

    <div class="form-check">
        <input class="form-check-input" type="radio" name="checkGroup" id="checkGroup1" value="0">
        <label class="form-check-label" for="checkGroup1">
            Group
        </label>
    </div>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="checkGroup" id="checkGroup2" value="1">
        <label class="form-check-label" for="checkGroup2">
            General
        </label>
    </div>
    <div class="form-group">
        <label for="course">course</label>

        <select name="course" id="course" class="form-control">

        </select>
    </div>

    <div class="form-group" id="divGroups">
        <label for="groups">groups</label>

        <select name="groups" id="groups" class="form-control">

        </select>
    </div>
    <div class="form-group">
        <label for="coursename">Number Of Codes</label>
        <input type="number" min="1" class="form-control" name="codeslength" id="codeslength" aria-describedby="emailHelp" placeholder="Number Of Codes" value="<?php if (isset($_POST['codeslength'])) {
                                                                                                                                                                    echo $_POST['codeslength'];
                                                                                                                                                                } ?>" required>
    </div>
    <div class="form-group">
        <label for="centername">Center Name </label>
        <input type="text" class="form-control" name="centername" id="centername" placeholder="Center Name" value="<?php if (isset($_POST['centername'])) {
                                                                                                                        echo $_POST['centername'];
                                                                                                                    } ?>" required>
    </div>
    <button type="submit" class="btn btn-primary" name="submit">Generate</button>
</form>
<script>
    $(document).ready(function() {
        $("#divGroups").hide();

        $('#teacher').change(function() {
            value = $(this).val();
            $.ajax({
                url: "ajax.php",
                type: "POST",
                dataType: "json",
                data: {
                    teacher: value,
                    // user_id: 
                },
                success: function(data) {
                    // console.log(data);
                    var out = '<div class="form-group><label for="">Course</label> <select class="form-control" id="jobs" required  name="course">        <option value="0">Choose a Course</option>';
                    $.each(data, function(key, value) {

                        // out += '<input type="radio" value=' + value['id'] + ' name="city"><label class="ml-2 mr-2">' + value['name'] + '</label><br>';
                        out += '<option value=' + value['id'] + '>' + value['fullname'] + '</option>';
                    });

                    out += '</select></div>';
                    // $('#jobs').selectpicker();

                    $("#course").html(out);
                    // console.log(data);
                }
            });

        });
        $('#checkGroup1').click(function() {
            $("#divGroups").show();
            $('#course').change(function() {

                value = $(this).val();
                // console.log(value);
                $.ajax({
                    url: "ajax.php",
                    type: "POST",
                    dataType: "json",
                    data: {
                        course: value,
                        // user_id: 
                    },
                    success: function(data) {
                        console.log(data);
                        var out = '<div class="form-group><label for="">Groups</label> <select class="form-control" id="groups" required  name="groups">         <option value="0">Choose a Group</option>';
                        $.each(data, function(key, value) {

                            // out += '<input type="radio" value=' + value['id'] + ' name="city"><label class="ml-2 mr-2">' + value['name'] + '</label><br>';
                            out += '<option value=' + value['id'] + '>' + value['name'] + '</option>';
                        });

                        out += '</select></div>';
                        // $('#jobs').selectpicker();

                        $("#groups").html(out);
                        // console.log(data);
                    }
                });

            });
        });

        $('#checkGroup2').click(function() {
            $("#divGroups").hide();
        
        });


    });
</script>
<?php
if (isset($_POST['submit'])) {
    $codesnumber = $_POST['codeslength'];

    $ins = new stdClass();
    $ins->teacherid = $_POST['teacher'];
    $ins->courseid = $_POST['course'];
    if($_POST['checkGroup'] == 0){
        $ins->groupid = $_POST['groups'];}
    else{
        $ins->groupid = 0;
    }
    // $ins->groupid = $_POST['groups'];
    $ins->centername = $_POST['centername'];
    $ins->empty1= $_POST['checkGroup'];//0 for group 1 for general ( to check if it is group or general )
    $ins->id = $DB->insert_record('groups_attendence_patch', $ins);
    // var_dump($ins);
    if (!empty($ins->id)) {
        for ($i = 0; $i < $codesnumber; $i++) {
            $ins2 = new stdClass();
            $ins2->code = generate_random_string(10);
            $ins2->used = 0;
            $ins2->patchid = $ins->id;
            $DB->insert_record('groups_attendence_codes', $ins2);
        }
    }
    echo "<div class='alert alert-success' role='alert'>
    Added successfully
  </div>";
}

echo $OUTPUT->footer();}
else{
    redirect($CFG->wwwroot);  
}
?>