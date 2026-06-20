<?php

require_once("../config.php");
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
<form action="index.php" method="POST">

    <div class="form-group">
        <label for="teacher">Teacher</label>

        <select name="teacher" id="teacher" class="form-control">
            <?php
            foreach ($teachers as $teacher) {
                echo "<option value='" . $teacher->id . "'>" . $teacher->firstname . " " . $teacher->lastname . "</option>";
            }
            ?>
        </select>
    </div>

    <div class="form-group">
        <label for="course">course</label>

        <select name="course" id="course" class="form-control">

        </select>
    </div>
    <div class="form-group">
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
                    var out = '<div class="form-group><label for="">Course</label> <select class="form-control" id="jobs" required  name="course">';
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
                    var out = '<div class="form-group><label for="">Groups</label> <select class="form-control" id="groups" required  name="groups">';
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
</script>
<?php
if (isset($_POST['submit'])) {
    $codesnumber = $_POST['codeslength'];

    $ins = new stdClass();
    $ins->teacherid = $_POST['teacher'];
    $ins->courseid = $_POST['course'];
    $ins->groupid = $_POST['groups'];
    $ins->centername = $_POST['centername'];
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

echo $OUTPUT->footer();
?>