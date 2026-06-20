<?php
global $DB,$USER,$COURSE,$CFG ;


require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/my/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir.'/filelib.php');

$PAGE->set_context(get_system_context());
$PAGE->set_pagelayout('login');
$PAGE->set_title("Sign Up");
$PAGE->set_heading(get_string('signup','theme_edumy'));

$PAGE->set_url(new moodle_url('/signup/signup.php'));
function signUp(){
	global $DB;
	$userInfo = new stdClass();
	
	$yearInfo = new stdClass();
	
	$yearMap=array("primary 1"=>1, "primary 2"=>2, "primary 3"=>3, "primary 4"=>4,"primary 5"=>5,"primary 6"=>6,"preparatory 1"=>7,"preparatory 2"=>8,"preparatory 3"=>9,"Secondary 1"=>10,"Secondary 2"=>11,"Secondary 3"=>12);
	
	try{
		
		$firstname = $_GET["firstname"];
        $lastname = $_GET["lastname"];
	    $username = $_GET["username"];
	    $email = $_GET["email"];
	    $password = $_GET["password"];
	    $uYear = $_GET["year"];
        $parentPhone = $_GET["Pphone"];
        $sPhone = $_GET["Sphone"];
		
	} catch(Exception $e){
             echo json_encode( ['message'=>$e]);
     }
	
	if (!empty($firstname)&&!empty($lastname) && !empty($username) && !empty($email) && !empty($password) && !empty($uYear) && !empty($parentPhone)&& !empty($sPhone)){
		
		try{
			
			$hashPass=hash_internal_user_password($password);
		
		    $userInfo->firstname = $firstname;
            $userInfo->lastname = $lastname;
	        $userInfo->username = $username;
    	    $userInfo->email = $email;
		    $userInfo->password = $hashPass;
            $userInfo->phone1 = $sPhone;
    	    $userInfo->phone2 = $parentPhone;
			$userInfo->confirmed = 1;
			$userInfo->mnethostid = 1;
			
			
	        $userInfo->id = $DB->insert_record('user', $userInfo);
			 $key = array_search(intval($uYear), $yearMap);
			 
			$yearInfo->userid = $userInfo->id;
			$yearInfo->fieldid = 1;
			$yearInfo->data = $key;
			$yearInfo->dataformat = 0;
			
			$yearInfo->id = $DB->insert_record('user_info_data', $yearInfo);
			//return json_encode(["user"=> $yearInfo]);
			
			//INSERT INTO mdl_user_info_data(userid,fieldid,data,dataformat) VALUES(3,1,"primary 1",0)
            redirect($CFG->wwwroot."/login/index.php");

		return json_encode(["message"=> "successful"]);

		} catch(Exception $e){
            //  echo json_encode( ['message'=>$e]);
                 echo '
                 
                 <div class="alert alert-danger">
                    <h6>
                    '.get_string('smthWentWrong','theme_edumy').'
                    </h6>
                 
                 </div>';
             
         }
	}
	else {
        echo 
        '
        <div class="alert alert-danger">
            <h3>'.get_string('alertEmpty','theme_edumy').'</h3>
        </div>
        ';
		return json_encode(["message"=> "error empty"]);
	}
	
}

function signUpParent(){
	global $DB;
	$userInfo = new stdClass();
	
	$yearInfo = new stdClass();
	
	$yearMap=array("primary 1"=>1, "primary 2"=>2, "primary 3"=>3, "primary 4"=>4,"primary 5"=>5,"primary 6"=>6,"preparatory 1"=>7,"preparatory 2"=>8,"preparatory 3"=>9,"Secondary 1"=>10,"Secondary 2"=>11,"Secondary 3"=>12);
	
	try{
		
		$firstname = $_GET["firstname"];
        $lastname = $_GET["lastname"];
	    $username = $_GET["username"];
	    $email = $_GET["email"];
	    $password = $_GET["password"];
	    $studentEmail = $_GET["studentEmail"];
        $parentPhone = $_GET["parentPhone"];

	} catch(Exception $e){
             echo json_encode( ['message'=>$e]);
     }
	
	if (!empty($firstname)&&!empty($lastname) && !empty($username) && !empty($email) && !empty($password) && !empty($studentEmail) &&!empty($parentPhone) ){
		
		try{
			
			

            $selectStudent = $DB->get_record("user",array("email"=>$studentEmail));

            if($selectStudent){
          
                    $getContextid = $DB->get_record("context",array("instanceid"=>$selectStudent->id));
                    
                    if($getContextid->id !== null){
                        $hashPass=hash_internal_user_password($password);
		
                        $userInfo->firstname = $firstname;
                        $userInfo->lastname = $lastname;
                        $userInfo->username = $username;
                        $userInfo->email = $email;
                        $userInfo->password = $hashPass;
                        $userInfo->phone1 = $parentPhone;
                        $userInfo->confirmed = 1;
                        $userInfo->mnethostid = 1;
                        
                        
                        $userInfo->id = $DB->insert_record('user', $userInfo);
        
        
                        $selectParentid = $DB->get_field('user', 'MAX(id)', array());
        
                        $createParent = new stdClass();
                        $createParent->roleid=  9;
                        $createParent->contextid = $getContextid->id;
                        $createParent->userid = $selectParentid;
                        $createParent->modifierid = $selectStudent->id;
                        
                        $createParent->id = $DB->insert_record('role_assignments', $createParent);
                        var_dump($createParent);
                        redirect($CFG->wwwroot."/login/index.php");
                      
                    }else{
                        echo'
                        <div class="alert alert-danger">
                            <p><Strong>'.$selectStudent->username.'</Strong> '.get_string('alertStudentLogin','theme_edumy').' </p>
                        </div>
                    ';
                    }

                   

                    return json_encode(["message"=> "successful"]);
                

                
            }else{
                echo '
             
                <div class="alert alert-danger">
                   <h6>
                        '.get_string('noStudentEmail','theme_edumy').'
                   </h6>
                
                </div>';
            }

		} catch(Exception $e){
            //  echo json_encode( ['message'=>$e]);
             echo '
             
             <div class="alert alert-danger">
                <h6>
                '.get_string('smthWentWrong','theme_edumy').'
                </h6>
             
             </div>';
         }
	}
	else {
        echo 
        '
        <div class="alert alert-danger">
            <h3>'.get_string('alertEmpty','theme_edumy').'
            </h3>
        </div>
        ';
		return json_encode(["message"=> "error empty"]);
	}
	
}
// https://academy.nitg-eg.com/webservice/rest/server.php?wsfunction=signup&moodlewsrestformat=json
echo $OUTPUT->header();
if(isset($_GET['submitForm'])){
    if($_GET['year'] > 0){
        signUp();
    }elseif($_GET['studentEmail'] !== null)
    { 
        signUpParent(); 
    }
}
echo '
    <style>
        #studentYear{
            display:none;
        }
        #parentdiv{
            display:none;
        }
    </style>
    <div class="container">

    
        <h2 class="text-center"> '.get_string('signup','theme_edumy').' </h2>

        <form class="mt-3" action="" method="GET">
        <div class="form-group">
            <label for="exampleInputEmail1">'.get_string('usernameS','theme_edumy').'</label>
            <input type="text" class="form-control" id="exampleInputEmail1" name="username"  placeholder="'.get_string('usernamePH','theme_edumy').'"  required>
        </div>

        <div class="form-group">
            <label for="exampleInputEmail1">'.get_string('firstnameS','theme_edumy').'</label>
            <input type="text" class="form-control" id="exampleInputEmail1" name="firstname"  placeholder="'.get_string('firstnamePH','theme_edumy').'" required>
        </div>
        <div class="form-group">
            <label for="exampleInputEmail1">'.get_string('LastnameS','theme_edumy').'</label>
            <input type="text" class="form-control" id="exampleInputEmail1" name="lastname"  placeholder="'.get_string('LastnamePH','theme_edumy').'" required>
        </div>
        
        <div class="form-group">
            <label for="exampleInputEmail1">'.get_string('emailS','theme_edumy').'</label>
            <input type="email" class="form-control" id="exampleInputEmail1" name="email" aria-describedby="emailHelp" placeholder="'.get_string('emailPH','theme_edumy').'" required>
        </div>
        <div class="form-group">
            <label for="exampleInputPassword1">'.get_string('passwordS','theme_edumy').'</label>
            <input type="password" class="form-control" id="exampleInputPassword1" name="password" placeholder="'.get_string('PasswordPH','theme_edumy').'" required>
        </div>
        <div class="form-group">
            <div class="form-check">
                <input type="radio" class="form-check-input" name="type" id="parent" onclick="myFunction()">
                <label class="form-check-label" for="parent">'.get_string('parentcheckbox','theme_edumy').'</label>
            </div>
            <div class="form-check">
                <input type="radio" class="form-check-input" name="type" id="student1" onclick="myFunction()" checked="checked">
                <label class="form-check-label" for="student1">'.get_string('studentcheckbox','theme_edumy').'</label>
            </div>
        </div>
        <div id="studentYear">
            <div class="form-group">
                <label for="parentphone" >'.get_string('Pphone','theme_edumy').'</label>
                <input type="text" class="form-control" id="parentphone" name="Pphone"  placeholder="'.get_string('Pphone','theme_edumy').'" >

            </div>
            <div class="form-group">
                <label for="Sphone" >'.get_string('Sphone','theme_edumy').'</label>
                <input type="text" class="form-control" id="Sphone" name="Sphone"  placeholder="'.get_string('Sphone','theme_edumy').'" >

            </div>
            <div class="form-group"  >
                <label >'.get_string('Studentyear','theme_edumy').'</label>
                <select class="form-select form-control" name="year" aria-label="Default select example">
                    <option selected>'.get_string('Studentyear_selectmenu','theme_edumy').'</option>
                    
                    <option value="1">'.get_string('primary1','theme_edumy').'</option>
                    <option value="2">'.get_string('primary2','theme_edumy').'</option>  
                    <option value="3">'.get_string('primary3','theme_edumy').'</option>
                    <option value="4">'.get_string('primary4','theme_edumy').'</option>
                    <option value="5">'.get_string('primary5','theme_edumy').'</option>
                    <option value="6">'.get_string('primary6','theme_edumy').'</option>
                    <option value="7">'.get_string('preparatory1','theme_edumy').'</option>
                    <option value="8">'.get_string('preparatory2','theme_edumy').'</option>
                    <option value="9">'.get_string('preparatory3','theme_edumy').'</option>
                    <option value="10">'.get_string('secondary1','theme_edumy').'</option>
                    <option value="11">'.get_string('secondary2','theme_edumy').'</option>
                    <option value="12">'.get_string('secondary3','theme_edumy').'</option>


                </select>
            </div>
        </div>
     
        <div id="parentdiv">
            <div class="form-group" >
                <label for="exampleInputEmail1">'.get_string('studentEmail','theme_edumy').'</label>
                <input type="email" class="form-control" id="exampleInputEmail1" name="studentEmail" aria-describedby="emailHelp" placeholder="'.get_string('studentEmailPH','theme_edumy').'">
            </div>
            <div class="form-group" >
                <label for="parentPhone">'.get_string('Pphone','theme_edumy').'</label>
                <input type="text" class="form-control" id="parentPhone" name="parentPhone" aria-describedby="emailHelp" placeholder="'.get_string('Pphone','theme_edumy').'">
            </div>
        </div>
       

        <button type="submit" name="submitForm"  class="btn btn-primary">'.get_string('signup','theme_edumy').'</button>
        </form>

        <script>
        function myFunction() {
            var checkBoxStudent = document.getElementById("student1");
            var studentYear = document.getElementById("studentYear");
            var checkBoxParent = document.getElementById("parent");
            var ParentDiv = document.getElementById("parentdiv");

            if (checkBoxParent.checked == true){
                ParentDiv.style.display = "block";
                studentYear.style.display = "none";

            } else {
                ParentDiv.style.display = "none";
            }
          }
        </script>
    </div>
';

echo $OUTPUT->footer();


?>