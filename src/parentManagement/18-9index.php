<?php
require_once('../config.php');
$id = $_GET['id'];
$enrolled_students = $DB->get_records_sql("SELECT u.id As id 
,CONCAT(u.firstname,' ',u.lastname )as fullname ,u.email, .u.phone2
FROM mdl_course c 
INNER JOIN mdl_context cx ON c.id = cx.instanceid 
INNER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
 INNER JOIN mdl_user u ON ra.userid = u.id 
WHERE cx.contextlevel = '50' AND c.id=$id");
$admins = get_admins();
$isadmin = false;
foreach ($admins as $admin) {
    if ($USER->id == $admin->id) {
        $isadmin = true;
        break;
    }
}

$teachers = $DB->get_records_sql("SELECT u.id As id
    FROM   mdl_course c
    LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
    LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
    LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id 
    WHERE cx.contextlevel = '50' AND c.id= '$id'");
$assistants = $DB->get_records_sql("SELECT u.id As id
            FROM   mdl_course c
            LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
            LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '4'
            LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id 
            WHERE cx.contextlevel = '50' AND c.id= '$id'");
$teacherId = 0;
$assistant_data = array();
foreach ($teachers as $teach) {
    $teacherId = $teach->id;
}
foreach ($assistants as $assist) {
    array_push($assistant_data, $assist->id);
}
// var_dump(in_array($USER->id,$assistant_data));
if ($isadmin || $USER->id == $teacherId || (isloggedin() && in_array($USER->id, $assistant_data))) {

    echo $OUTPUT->header();


?>
    <style>
        input.form-control {
            border-radius: 0px !important;
        }
    </style>
    <div class="row">
        <div class="alert alert-info ml-1 mr-1">Count of students with Parents : <span id="withParent"></span></div>
        <div class="alert alert-success ml-1 mr-1">Count of students with no parent : <span id="withoutParent"></span></div>
        <button href="#" class="alert alert-secondary ml-1 mr-1"><span id="getParents">get Parents</span></button>
        <button href="#" class="alert alert-primary ml-1 mr-1"><span id="getNoParents">get No Parents</span></button>

    </div>


    <table class="table">
        <thead>
            <tr>
                <th scope="col">Student Name</th>
                <th scope="col">Student Email</th>
                <th scope="col">Parent Phone</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 0;

            foreach ($enrolled_students as $en) {
                if (empty($en->phone2)) {
                    $parentPhone = "-";
                } else {
                    $parent = $DB->get_record('parent_child', array('childid' => $en->id));
                    if (!empty($parent)) {
                        $userParent = $DB->get_record('user', array('id' => $parent->parentid));
                        $parentPhone = $userParent->phone1;
                    } else {
                        $parentPhone = "-";
                    }
                }
            ?>


                <tr id="tr<?php echo $en->id; ?>">
                    <td><?php echo $en->fullname; ?> </td>
                    <td><?php echo $en->email; ?></td>
                    <td id="phone<?php echo $en->id; ?>"><?php echo $parentPhone; ?></td>
                    <td>
                        <div id="addDiv<?php echo $en->id; ?>" <?php if ($parentPhone != "-") {
                                                                    echo "style='display:none;'";
                                                                } ?>>
                            <button id='add<?php echo $en->id; ?>' data-toggle='modal' class='fired2 btn btn-primary' data-toggle='addModal' data-target='#addModal' data-id='<?php echo $en->id; ?>'>Add Parent</button>


                        </div>
                        <div id="editDiv<?php echo $en->id; ?>" <?php if ($parentPhone == "-") {
                                                                    echo "style='display:none;'";
                                                                } ?>>
                            <button data-toggle='modal' class='btn btn-success fired' data-toggle='editModal' data-target='#editModal' data-id='<?php echo $en->id; ?>' data-fname='<?php echo $userParent->firstname; ?>' data-lname='<?php echo  $userParent->lastname; ?>' data-phone='<?php echo  $userParent->phone1; ?>' data-email='<?php echo  $userParent->email; ?>' id='edit<?php echo $en->id; ?>'>Edit Parent</button>
                            <button type='button' data-id='<?php echo $en->id; ?>' id='war<?php echo $en->id; ?>' class='btn btn-outline-warning'>Delete Parent</button>

                        </div>


                        <button type="button" data-id="<?php echo $en->id; ?>" id="dang<?php echo $en->id; ?>" class="btn btn-outline-danger">Unenrol student from the course</button>


                    </td>
                    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModal" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="exampleModalLabel">Modal Edit title</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <input type="text" class="bookId">
                                    <div class="row">
                                        <div class="form-group col">
                                            <label for="">Parent First Name</label>
                                            <input type="text" class="form-control" name="pFirstName" id="pFirstName" placeholder="Parent First Name">
                                        </div>
                                        <div class="form-group col">
                                            <label for="">Parent Last Name</label>
                                            <input type="text" class="form-control" name="pLastName" id="pLastName" placeholder="Parent Last Name">
                                        </div>
                                        <div class="form-group col">
                                            <label for="">Parent Phone Number</label>
                                            <input type="number" class="form-control" name="parentPhone" id="parentPhone" placeholder="Parent Phone Number">
                                        </div>

                                    </div>
                                    <div class="row">
                                        <div class="form-group col">
                                            <label for="">Parent Password</label>
                                            <input type="password" class="form-control" name="pPassword" id="pPassword" placeholder="Parent Password">
                                        </div>
                                        <div class="form-group col">
                                            <label for="">Parent Email</label>
                                            <input type="email" class="form-control" name="pEmail" id="pEmail" placeholder="Parent Email">
                                        </div>
                                    </div>
                                    <p class="error"></p>
                                    <p class="success"></p>

                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <button id="save-button" type="button" class="btn btn-primary">Save changes</button>

                                    <!-- <button type="button" id="editSave<?php echo $en->id; ?>"class="btn btn-primary">Save changes</button> -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal fade" id="addModal" tabindex="-1" role="dialog" aria-labelledby="addModal" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="exampleModalLabel">Modal Add title</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <form id="myForm">
                                        <input type="text" class="bookId2">

                                        <div class="row">
                                            <div class="form-group col">
                                                <label for="">Parent First Name</label>
                                                <input type="text" class="form-control pFirstName" name="pFirstName" id="pFirstName" placeholder="Parent First Name">
                                            </div>
                                            <div class="form-group col">
                                                <label for="">Parent Last Name</label>
                                                <input type="text" class="form-control pLastName" name="pLastName" id="pLastName" placeholder="Parent Last Name">
                                            </div>
                                            <div class="form-group col">
                                                <label for="">Parent Phone Number</label>
                                                <input type="number" class="form-control parentPhone" name="parentPhone" id="parentPhone" placeholder="Parent Phone Number">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="form-group col">
                                                <label for="">Parent Email</label>
                                                <input type="email" class="form-control pEmail" name="pEmail" id="pEmail" placeholder="Parent Email">
                                            </div>

                                            <div class="form-group col">
                                                <label for="">Parent Password</label>
                                                <input type="password" class="form-control pPassword" name="pPassword" id="pPassword" placeholder="Parent Password">
                                            </div>

                                        </div>
                                        <p class="error"></p>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-primary" data-id="<?php echo $en->id; ?>" id="addSave<?php echo $en->id; ?>">Save changes</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </tr>

            <?php

            } ?>
        </tbody>
    </table>
    <input type="hidden" value="<?php echo $id ?>" id="courseid">
    <script>
        $(document).ready(function() {

            var withParent = $('div[id*="editDiv"]').filter(function() {
                return $(this).css('display') !== 'none';
            }).length;
            var withoutParent = $('div[id*="addDiv"]').filter(function() {
                return $(this).css('display') !== 'none';
            }).length;
            $("#withParent").text(withParent);
            $("#withoutParent").text(withoutParent);
            $(".fired").click(function() {

                $("#pFirstName").val('');
                $("#pLastName").val('');
                $("#pEmail").val('');
                $("#pPassword").val('');
                $("#parentPhone").val('');

                $(".bookId").val($(this).attr('data-id'));
                $.ajax({
                    url: "script.php",
                    type: "POST",
                    dataType: "json",
                    data: {
                        parentEdit: $(this).attr('data-id')
                    },
                    success: function(data) {
                        // console.log('dd'+data);
                        if (data['state'] == 0) {
                            $("#pFirstName").val(data['fname']);
                            $("#pLastName").val(data['lname']);

                            $("#pEmail").val(data['email']);
                            $("#parentPhone").val(data['phone']);
                        } else {
                            console.log(data['error']);
                        }
                    }
                });
                // $("#pFirstName").val($(this).attr('data-fname'));
                // $("#pLastName").val($(this).attr('data-lname'));

                // $("#pEmail").val($(this).attr('data-email'));
                // $("#parentPhone").val($(this).attr('data-phone'));
                $(".error").text("");
            });
            $(".fired2").click(function() {
                $(".bookId2").val($(this).attr('data-id'));
                $(".error").text("");

            });

            $(document).on('click', '#save-button', function() {

                var id = $(".bookId").val();

                firstname = $("#pFirstName").val();

                lastname = $("#pLastName").val();

                email = $("#pEmail").val();
                pPassword = $("#pPassword").val();
                phone = $("#parentPhone").val();
                // alert('ddddd'+id);

                $.ajax({
                    url: "script.php",
                    type: "POST",
                    dataType: "json",
                    data: {
                        useridEdit: id,
                        firstname: firstname,
                        lastname: lastname,
                        email: email,
                        password: pPassword,
                        phone: phone,
                    },
                    success: function(data) {
                        // console.log('dd'+data);
                        if (data['state'] == 0) {
                            $(".error").text(data['error']);

                            $("#pFirstName").val('');
                            $("#pLastName").val('');
                            $("#pEmail").val('');
                            $("#pPassword").val('');
                            $("#parentPhone").val('');
                            //  $(this).attr('data-fname',firstname);
                            if (data['parentPhone'] != 0) {
                                $("#phone" + id).text(data['parentPhone']);

                            }
                            // else{
                            //     $("#phone"+id).text(data['phone']);

                            // }
                        } else {
                            $(".error").text(data['error']);
                        }



                    }
                });
            });
            $(document).on('click', 'button[id*="addSave"]', function() {
                var id = $(".bookId2").val();
                firstname = $(".pFirstName").val();
                lastname = $(".pLastName").val();
                email = $(".pEmail").val();
                pPassword = $(".pPassword").val();
                phone = $(".parentPhone").val();
                // console.log('dd'+phone);

                $.ajax({
                    url: "script.php",
                    type: "POST",
                    dataType: "json",
                    data: {
                        userid: id,
                        firstname: firstname,
                        lastname: lastname,
                        email: email,
                        password: pPassword,
                        phone: phone,
                    },
                    success: function(data) {
                        if (data['state'] == 0) {
                            $("#addDiv" + id).hide();
                            $("#editDiv" + id).show();
                            //     $("#add"+id+"").removeClass("btn-primary");
                            // $("#add"+id+"").addClass("btn-success");
                            // $("#add"+id+"").removeClass("fired2");
                            // $("#add"+id+"").addClass("fired");
                            // // $("#add"+id+" ").text(phone);

                            // $(".fired").text("Edit Parent");
                            // // $("#add"+id+"").attr('data-toggle','modal');
                            // $(".fired").attr('data-target','#editModal');
                            // $(".fired").attr('data-id',id);
                            // $(".fired").attr('id','');
                            // $(".fired").attr('id','edit'+id);
                            // $(".bookId").val(id);
                            // console.log('data'+data['comefrom']);

                            if (data['comefrom'] == 1) {
                                $("#phone" + id).text(phone);
                                $(".error").text("new Parent is added successfully");

                            } else {
                                $("#phone" + id).text(data['phone']);
                                $(".error").text("This child is added to an exsting parent");

                            }

                            setTimeout(function() {
                                $('#addModal').modal('hide');
                            }, 2000);

                            $(".pFirstName").val('');
                            $(".pLastName").val('');
                            $(".pEmail").val('');
                            $(".pPassword").val('');
                            $(".parentPhone").val('');
                            count = parseInt($("#withParent").text());
                            count += 1;
                            $("#withParent").text(count);
                            count = parseInt($("#withoutParent").text());
                            count -= 1;
                            $("#withoutParent").text(count);
                        } else {
                            $(".error").text(data['error']);
                        }



                    }
                });


            });
            $(document).on('click', 'button[id*="war"]', function() {
                id = $(this).attr('data-id');
                $.ajax({
                    url: "script.php",
                    type: "POST",
                    dataType: "json",
                    data: {
                        warId: id
                    },
                    success: function(data) {
                        // console.log('id'+data);
                        $("#addDiv" + data).show();
                        $("#editDiv" + data).hide();
                        $("#phone" + id).text("-");
                        count = parseInt($("#withParent").text());
                        count -= 1;
                        $("#withParent").text(count);
                        count = parseInt($("#withoutParent").text());
                        count += 1;
                        $("#withoutParent").text(count);
                    }
                });
            });
            $(document).on('click', 'button[id*="dang"]', function() {
                id = $(this).attr('data-id');
                courseid = $("#courseid").val();

                $.ajax({
                    url: "script.php",
                    type: "POST",
                    dataType: "json",
                    data: {
                        dangId: id,
                        course: courseid
                    },
                    success: function(data) {
                        // console.log('id'+data);
                        $("#tr" + data).fadeOut();

                        // $("#editDiv"+data).hide();
                        // $("#phone"+id).text("-");

                    }
                });
            });
            $("#getNoParents").click(function() {
                var withParent = $('table tr:has(div[id*="editDiv"]:hidden)');
                var trs = $('table tbody tr') // select all the rows
                if (withParent.length) {
    trs
      .hide() // hide them
      .filter(withParent) // find the rows with the class[es]
        .show() // show them
   } else {
     // no filters, just show everything
     trs.show()
   }
            // console.log(withParent);
            });
            $("#getParents").click(function() {
                var withParent = $('table tr:has(div[id*="addDiv"]:hidden)');
                var trs = $('table tbody tr') // select all the rows
                if (withParent.length) {
    trs
      .hide() // hide them
      .filter(withParent) // find the rows with the class[es]
        .show() // show them
   } else {
     // no filters, just show everything
     trs.show()
   }
            // console.log(withParent);
            });

        });
    </script>
<?php

    echo $OUTPUT->footer();
}
