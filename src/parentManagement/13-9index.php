<?php
require_once('../config.php');
echo $OUTPUT->header();
$id = $_GET['id'];
$enrolled_students = $DB->get_records_sql("SELECT u.id As id 
,CONCAT(u.firstname,' ',u.lastname )as fullname ,u.email, .u.phone2
FROM mdl_course c 
INNER JOIN mdl_context cx ON c.id = cx.instanceid 
INNER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
 INNER JOIN mdl_user u ON ra.userid = u.id 
WHERE cx.contextlevel = '50' AND c.id=$id");

?>
<style>
    input.form-control{
        border-radius: 0px !important;
    }
</style>
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
        <?php foreach ($enrolled_students as $en) {
        
            if (empty($en->phone2)) {
                $parentPhone = "-";
            } else {
                $parentPhone = $en->phone2;
            }
        ?>


            <tr>
                <td><?php echo $en->fullname; ?> </td>
                <td><?php echo $en->email; ?></td>
                <td><?php echo $parentPhone; ?></td>
                <td><?php if ($parentPhone != '-') echo "<button data-toggle='modal' class='btn btn-success fired' data-toggle='editModal' data-target='#editModal' data-id='" . $en->id . "' id=''>Edit Parent</button>";
                    else echo "<button id='add".$en->id."' data-toggle='modal'class='fired2 btn btn-primary' data-toggle='addModal' data-target='#addModal' data-id='" . $en->id . "'>Add Parent</button>"; ?></td>
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
                                <input type="text" id="bookId">
                                
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-primary">Save changes</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="addModal" tabindex="-1" role="dialog" aria-labelledby="editModal" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="exampleModalLabel">Modal Add title</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <input type="text" class="bookId2">
                           
                                <div class="row">
                                <div class="form-group col">
                                    <label for="">Parent First Name</label>
                                    <input type="text" class="form-control"name="pFirstName" id="pFirstName" placeholder="Parent First Name">
                                </div>
                                <div class="form-group col">
                                    <label for="">Parent Last Name</label>
                                    <input type="text" class="form-control"name="pLastName" id="pLastName" placeholder="Parent Last Name">
                                </div>
                                <div class="form-group col">
                                    <label for="">Parent Phone Number</label>
                                    <input type="number" class="form-control"name="parentPhone" id="parentPhone" placeholder="Parent Phone Number">
                                </div>
                                </div>
                                <div class="row">
                                <div class="form-group col">
                                    <label for="">Parent Email</label>
                                    <input type="email" class="form-control"name="pEmail" id="pEmail" placeholder="Parent Email">
                                </div>
                          
                                <div class="form-group col">
                                    <label for="">Parent Password</label>
                                    <input type="password" class="form-control"name="pPassword" id="pPassword" placeholder="Parent Password">
                                </div>

                                </div>
                              
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-primary"data-id="<?php echo $en->id;?>" id="addSave<?php echo $en->id;?>">Save changes</button>
                            </div>
                        </div>
                    </div>
                </div>
            </tr>
         
         
        <?php    } ?>
    </tbody>
</table>
<script>
                $(document).ready(function() {
                  
                    $(".fired").click(function() {
                        $("#bookId").val($(this).attr('data-id'));
                    });
                    $(".fired2").click(function() {
                        $(".bookId2").val($(this).attr('data-id'));
                    });
                    
                    $(document).on('click', 'button[id*="addSave"]', function() {
                        var id= $(".bookId2").val();
                        firstname=$("#pFirstName").val();
                        lastname=$("#pLastName").val();
                        email=$("#pEmail").val();
                        pPassword=$("#pPassword").val();
                        phone=$("#parentPhone").val();
                        console.log('ff'+firstname);

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
                        console.log(data);
                        $("#add"+id+"").removeClass("btn-primary");
                        $("#add"+id+"").addClass("btn-success");
                        $("#add"+id+"").removeClass("fired2");
                        $("#add"+id+"").addClass("fired");

                        $("#add"+id+"").text("Edit Parent");
                        $("#add"+id+"").attr('data-toggle','modal');
                        $("#add"+id+"").attr('data-target','#editModal');
                        $("#add"+id+"").attr('data-id',id);
                        $("#bookId").val(id);

                        setTimeout(function() {$('#addModal').modal('hide');}, 1000);

                        $("#pFirstName").val('');
                        $("#pLastName").val('');
                          $("#pEmail").val('');
                        $("#pPassword").val('');
                         $("#parentPhone").val('');


                    }
                });
                      

                    });
                });
            </script>
<?php

echo $OUTPUT->footer();
