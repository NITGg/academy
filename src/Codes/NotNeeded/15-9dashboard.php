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
if ($isadmin) {
    // $PAGE->requires->js( new moodle_url($CFG->wwwroot . '/Codes/jcode/jquery-qrcode-master/jquery.qrcode.min.js') );

    echo $OUTPUT->header();
    $teachers = $DB->get_records_sql("SELECT DISTINCT u.*  FROM mdl_user as u INNER JOIN mdl_role_assignments as role ON role.userid=u.id and role.roleid=3");

?>
    <style>
        .offscreen {
  position:absolute;
  left:-10000px;
  top:auto;

  overflow:hidden;
  }
        .addBorder {
            border: 1px solid blue;
        }


        @media (max-width: 767.98px) {
            #tab {
                overflow: scroll;
            }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.1/jquery.min.js" integrity="sha512-aVKKRRi/Q/YV+4mjoKBsE4x3H+BkegoM/em46NNlCqNTmUYADjBbeNefNxYV7giUp0VxICtqdrbqU7iVaeZNXA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" integrity="sha512-BNaRQnYJYiPSqHHDb58B0yaPfCu+Wgds8Gp/gU33kqBtgNS4tSPHuGibyoeqMV/TJlSKda6FXzoEyYGjTe+vXA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script type="text/javascript" src="jcode/jquery-qrcode-master/jquery.qrcode.min.js"></script>

    <script src="https://cdn.jsdelivr.net/gh/linways/table-to-excel@v1.0.4/dist/tableToExcel.js"></script>

<a class="btn btn-primary" href="<?php echo $CFG->wwwroot?>/weekcodes">Add New Codes</a>
    <div class="container text-center">
        <div class="row">
            <div class="form-group col-lg-3">
                <label for="teacher">Teacher</label>

                <select name="teacher" id="teacher" class="form-control" required>
                    <option value="0">Choose a Teacher</option>

                    <?php
                    foreach ($teachers as $teacher) {
                        echo "<option value='" . $teacher->id . "'>" . $teacher->firstname . " " . $teacher->lastname . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group col-lg-3">
                <label for="course">course</label>

                <select name="course" id="course" class="form-control">
                    <option value="0">Choose a Course</option>

                </select>
            </div>
            <div class="form-group  col-lg-2" id="divGroups">
                <label for="groups">groups</label>

                <select name="groups" id="groups" class="form-control">
                    <option value="0">All</option>

                </select>
            </div>
            <div class="form-group  col-lg-2" id="divState">
                <label for="state">Status</label>

                <select name="state" id="state" class="form-control">
                    <option value="0">All</option>
                    <option value="1">Used</option>
                    <option value="2">Not Used</option>

                </select>
            </div>
            <div class="form-group col-lg-2">
                <label for="centerName">center Name</label>

                <select name="centerName" id="centerName" class="form-control">
                    <option value="0">Choose a center Name</option>

                </select>
            </div>
        </div>
        <button type="button" class="btn btn-primary" id="getCodes">Get Codes</button>
        <input type="hidden" id="courseName">
        <input type="hidden" id="teacherName">
        <input type="hidden" id="groupName">
        <div class="row text-center justify-content-center">
            <div class="alert alert-info numbers_info col-lg-4     ml-2 mr-2" role="alert">
                Number of Total codes :
                <span id="total_codes"></span>
            </div>
            <div class="alert alert-info numbers_info col-lg-4 ml-2 mr-2" role="alert">
                Number of Used codes :
                <span id="number_of_used_codes"></span>
            </div>
            <button id="btnExport" class="btn btn-info">CSV</button>
            <button id="exportpdf" class="btn btn-info">PDF</button>
            <button id="exportQr" class="btn btn-info">QR</button>

            <!-- <input type="button" id="" value="PDF"class="btn btn-info"> -->


        </div>
        <div id="tab">
            <table class="table" id="codes"></table>
            <table class="table" id="codes2" style="display: none;"></table>
         <table class="table offscreen" id="codes3"  ></table>
        

        </div>
    </div>
    <script>
        $(document).ready(function() {

            $("#teacher").change(function() {

                var teacher = $("#teacher").val();


                setTimeout(function() {
                    $('#course').addClass("addBorder");
                }, 500);

                setTimeout(function() {
                    $('#course').removeClass("addBorder");
                }, 1000);


                $.ajax({
                    url: "ajax.php",
                    method: "POST",
                    dataType: "json",
                    data: {
                        teacher: teacher
                    },
                    success: function(data) {
                        var out = '<div class="form-group  col-lg-4  "><label for="">Course</label> <select class="form-control" id="jobs" required  name="course">        <option value="0">Choose a Course</option>';
                        $.each(data['data'], function(key, value) {

                            // out += '<input type="radio" value=' + value['id'] + ' name="city"><label class="ml-2 mr-2">' + value['name'] + '</label><br>';
                            out += '<option value=' + value['id'] + '>' + value['fullname'] + '</option>';
                        });

                        out += '</select></div>';
                        // $('#jobs').selectpicker();

                        $("#course").html(out);
                        $("#teacherName").val(data['teachername']);

                    }
                });

            });
            $('#course').change(function() {

                value = $(this).val();
                setTimeout(function() {
                    $('#groups').addClass("addBorder");
                }, 500);

                setTimeout(function() {
                    $('#groups').removeClass("addBorder");
                }, 1000);
                setTimeout(function() {
                    $('#centerName').addClass("addBorder");
                }, 500);

                setTimeout(function() {
                    $('#centerName').removeClass("addBorder");
                }, 1000);
                $.ajax({
                    url: "ajax.php",
                    type: "POST",
                    dataType: "json",
                    data: {
                        course: value,
                        // user_id: 
                    },
                    success: function(data) {
                        // console.log(data);
                        var out = '<div class="form-group  col-lg-4"><label for="">Groups</label> <select class="form-control" id="groups"   name="groups">         <option value="0">All</option>';
                        $.each(data['data'], function(key, value) {

                            // out += '<input type="radio" value=' + value['id'] + ' name="city"><label class="ml-2 mr-2">' + value['name'] + '</label><br>';
                            out += '<option value=' + value['id'] + '>' + value['name'] + '</option>';
                        });

                        out += '</select></div>';
                        // $('#jobs').selectpicker();

                        $("#groups").html(out);
                        // console.log(data);
                        $("#courseName").val(data['coursename']);

                    }
                });
                $.ajax({
                    url: "ajax.php",
                    type: "POST",
                    dataType: "json",
                    data: {
                        centername: value,
                        // user_id: 
                    },
                    success: function(data) {
                        // console.log(data);
                        var out = '<div class="form-group  col-lg-4"><label for="">centerName</label> <select class="form-control" id="centername"   name="centername">         <option value="0">All</option>';
                        $.each(data['data'], function(key, value) {

                            // out += '<input type="radio" value=' + value['id'] + ' name="city"><label class="ml-2 mr-2">' + value['name'] + '</label><br>';
                            out += '<option value=' + value['centername'] + '>' + value['centername'] + '</option>';
                        });

                        out += '</select></div>';
                        // $('#jobs').selectpicker();

                        $("#centerName").html(out);
                        // console.log(data);
                        // $("#courseName").val(data['coursename']);

                    }
                });

            });
            $("#getCodes").click(function() {
                var teacher = $("#teacher").val();
                var course = $("#course").val();
                var group = $("#groups").val();
                var centerName = $("#centerName").val();
                var state = $("#state").val();
                var temp = 1;
                // var tempState=3;
                // console.log('grroup' + group);
                // if (course != '0') {
                if (group == "0") {
                    temp = 0;
                }

                // console.log('temp' + temp);
                // console.log('tempState' + tempState);

                $.ajax({
                    url: "ajax.php",
                    type: "POST",
                    dataType: "json",
                    data: {
                        dataCourse: course,
                        dataGroup: group,
                        dataTeacher: teacher,
                        state: state,
                        centerName:centerName,
                        temp: temp
                        // user_id: 
                    },
                    success: function(data) {
                        // console.log(data);
                        $("#total_codes").show();
                        $("#number_of_used_codes").show();
                        $("#total_codes").text(data['codes']);
                        $("#number_of_used_codes").text(data['used_codes']);
                        var out = '<div id="tab"><table id="codes" class="table" id="codes"><thead><tr><th>Course</th><th>Group</th><th>Teacher</th><th>Code</th> <th>Used</th> <th>user who used the code</th><th>user mail</th>  <th>Time Of Using</th> <th class="no-export">Actions</th></tr></thead><tbody>';
                        $.each(data['data'], function(key, value) {
                            if (value['groupname'] == null) {
                                gn = '-';
                            } else {
                                gn = value['groupname'];
                            }
                            if (value['used'] == 1) {
                                used = 'btn-warning';
                                text = 'Yes';
                            } else {
                                used = 'btn-success';
                                text = 'No';
                            }
                            if (value['time'] == 0) {
                                time = '-';
                            } else {
                                time = value['time'];

                            }
                            // out += '<input type="radio" value=' + value['id'] + ' name="city"><label class="ml-2 mr-2">' + value['name'] + '</label><br>';
                            out += '<tr id="tr' + value['id'] + '"><td>' + $("#courseName").val() + '</td><td>' + gn + '</td><td>' + $("#teacherName").val() + '</td><td>' + value['code'] + '</td><td ><button class="btn ' + used + '" id="used' + value['id'] + '" name="' + value['id'] + '" >' + text + '</button></td><td id="fullname' + value['id'] + '">' + value['fullname'] + '</td><td id="email' + value['id'] + '">' + value['email'] + '</td><td id="time' + value['id'] + '" name="' + value['id'] + '">' + time + '</td><td><button id="button' + value['id'] + '"  value="' + value['id'] + '" class="btn btn-danger no-export">Delete</button></td></tr>';

                        });

                        out += '</table ></div>';
                        // $('#jobs').selectpicker();

                        $("#codes").html(out);
                        $("#codes2").html(out);
                        var out = '<div id="tab"><table class="table offscreen" id="codesQr"><thead><tr><th>Code</th></tr></thead><tbody>';

                        $.each(data['data'], function(key, value) {
                          
                          out += '<tr ><td id="tdCodes'+value['id']+'"></td></tr>';

                      });
                      out += '</table ></div>';
                      $("#codes3").html(out);
                      $.each(data['data'], function(key, value) {
                            $("#tdCodes"+value['id']+"").qrcode({width: 64,height: 64,text: value['code'] })
// console.log(value['code']);
                        });
                        // $("#codes3").hide();
                    }
                });
                // }
            });
            $(document).on('click', 'button[id*="button"]', function() {
                var id = $(this).val();
                totalValue = parseInt($("#total_codes").text());
                usedValue = parseInt($("#number_of_used_codes").text());
                // console.log(totalValue--);
                // console.log(usedValue--);
                $.ajax({
                    url: "ajax.php",
                    type: "POST",
                    data: {
                        delete: id,
                        // user_id: 
                    },
                    success: function(data) {
                        $("#tr" + id).fadeOut();
                        $("#total_codes").text(totalValue--);
                        if (data == 1) {
                            $("#number_of_used_codes").text(usedValue--);
                        }


                    }
                });
            });

            $(document).on('click', 'button[id*="used"]', function() {
                var id = $(this).attr('name');
                usedValue = parseInt($("#number_of_used_codes").text());

                // alert(id);
                temp = 0;
                if ($(this).hasClass('btn-success')) {
                    $(this).removeClass('btn-success');
                    $(this).addClass('btn-warning');
                    $(this).text('Yes');
                    // usedValue=usedValue++;
                    // console.log('used'+usedValue);

                    // $("#number_of_used_codes").text(usedValue);
                    // console.log(usedValue++);
                    temp = 1;
                } else {
                    $(this).removeClass('btn-warning');
                    $(this).addClass('btn-success');
                    $(this).text('No');
                    // usedValue=usedValue--;
                    // console.log('used'+usedValue);

                    // $("#number_of_used_codes").text(usedValue);
                    // console.log(usedValue--);

                    temp = 2;
                }
         
                $.ajax({
                    url: "ajax.php",
                    type: "POST",
                    data: {
                        update: id,
                        temp: temp
                    },
                    success: function(data) {
                        console.log(data);
                        if (temp == 1) {
                            $("#time" + id).text(data);
                            $("#number_of_used_codes").text(parseInt($("#number_of_used_codes").text()) + 1);

                        } else {
                            $("#time" + id).text('-');
                            $("#number_of_used_codes").text(parseInt($("#number_of_used_codes").text()) - 1);
                            $("#fullname"+id).text("-");
                               $("#email"+id).text("-");
                        }
                        // $("#used"+data).css('color', 'red');
                        // console.log(data);
                    }
                });
            });
            $("#btnExport").click(function() {
                let table =$("#codes2");
                table
      	.find(".no-export")
        .each(function(){
        	$(this).remove();
        });
                TableToExcel.convert(table[0], { // html code may contain multiple tables so here we are refering to 1st table tag
                    name: $("#courseName").val(), // fileName you could use any name
                    sheet: {
                        name: 'Sheet 1' // sheetName
                    }
                });
            });

           
            $("#exportpdf").click(function() {
                let mywindow = window.open("", "PRINT", "height=650,width=900,top=100,left=150");
                var style = "<style>";
                style = style + "#codes {text-align: center;}";
                style = style + "</style>";

                mywindow.document.write("<html><head>" + style);
                mywindow.document.write("</head><body >");
                mywindow.document.write(document.getElementById("tab").innerHTML);
                mywindow.document.write("</body></html>");

                mywindow.document.close(); // necessary for IE >= 10
                mywindow.focus(); // necessary for IE >= 10*/
                window.open(mywindow.print(), "_blank");
            });
            $("#exportQr").click(function() {
                html2canvas($('#codes3').get(0)).then( function (canvas) {
                    // document.body.appendChild(canvas);//                        
                    var a = document.createElement('a');
                    // toDataURL defaults to png, so we need to request a jpeg, then convert for file download.
                    //a.href = canvas.toDataURL("image/jpeg").replace("image/jpeg", "image/octet-stream");
                    a.href = canvas.toDataURL("image/png").replace("image/png", "image/octet-stream");
                    a.download = 'qrcodes.png';
                    a.click();  
                });
            });
        });
    </script>
<?php
    // echo $OUTPUT->footer();
} ?>