<?php
require_once('../config.php');
require_once($CFG->dirroot . '/academy/academyApi/academy_utils.php');
require_once($CFG->dirroot . '/academy/academyApi/academy_config.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/course/externallib.php');
require_once($CFG->dirroot . '/user/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/enrol/externallib.php');

define('PARAM_STRING','string');

$function= required_param('function', PARAM_RAW);
$meetingID = optional_param('meetingID',  null, PARAM_TEXT);
$internalMeetingID = optional_param('internalMeetingID',  null, PARAM_TEXT);
$parentMeetingID = optional_param('parentMeetingID',  null, PARAM_TEXT);
$attendeePW = optional_param('attendeePW',  null, PARAM_TEXT);
$moderatorPW = optional_param('moderatorPW',  null, PARAM_TEXT);
$createTime = optional_param('createTime',  null, PARAM_TEXT);
$voiceBridge = optional_param('voiceBridge',  null, PARAM_TEXT);
$dialNumber = optional_param('dialNumber',  null, PARAM_TEXT);
$createDate = optional_param('createDate',  null, PARAM_TEXT);
$hasUserJoined = optional_param('hasUserJoined',  null, PARAM_TEXT);
$duration = optional_param('duration',  null, PARAM_TEXT);
$hasBeenForciblyEnded = optional_param('hasBeenForciblyEnded',  null, PARAM_TEXT);
$messageKey = optional_param('messageKey',  null, PARAM_TEXT);
$message = optional_param('message',  null, PARAM_TEXT);
$courseid = optional_param('courseid',null,PARAM_TEXT);
$isRunning = optional_param('isRunning',null,PARAM_TEXT);

$teacherid = optional_param('teacherid',0,PARAM_TEXT);
$user = optional_param('user',0,PARAM_TEXT);
$roomid = optional_param('roomid',0,PARAM_TEXT);
$maxUser = optional_param('maxUser',0,PARAM_TEXT);
$sharedSecret=ACADEMY_BBB_SECRET; 

if($function == 'create_bbb_table'){
	echo create_bbb_table($message,$messageKey,$hasBeenForciblyEnded,$duration,$hasUserJoined,$createDate,$dialNumber,$voiceBridge,$createTime,$moderatorPW,$attendeePW,$parentMeetingID,$internalMeetingID,$meetingID,$courseid,$isRunning);

}
if($function == 'get_join_info'){

	echo get_join_info($courseid);
}
if($function == 'end_room'){

	echo end_room($meetingID,$roomid);
}

if($function == 'checkReservationClassRoom'){

	echo checkReservationClassRoom($teacherid,$courseid);
}

if ($function == 'check_course_has_bbb'){
	
	echo check_course_has_bbb($courseid);
}

if ($function == 'bbb_check_attend'){
	
	echo bbb_check_attend($meetingID,$courseid,$user);
}

if ($function == 'get_bbb_course_meetings'){
	
	echo get_bbb_course_meetings($courseid);
}
if ($function == 'get_reports_bbb'){
	
	echo get_reports_bbb($courseid,$meetingID);
}
if ($function == 'get_available_sessions'){
	
	echo get_available_sessions();
}
if ($function == 'get_running_session'){
	
	echo get_running_session($courseid);
}
if ($function == 'create_new_bbb_session'){
	
	echo create_new_bbb_session($courseid,$maxUser,$user);
}
if ($function == 'end_session'){
	
	echo end_session($courseid);
}
if ($function == 'join_attendee'){
	
	echo join_attendee($courseid,$user);
}
if ($function == 'join_moderator_Api'){
	
	echo join_moderator_Api($courseid,$user);
}
if ($function == 'bbb_room_time'){
	
	echo bbb_room_time($courseid);
}
if ($function == 'check_bbb_room_time'){
	
	echo check_bbb_room_time($courseid);
}
function get_join_info($courseid){
	global $DB;
	$room=$DB->get_records_sql("SELECT meetingID,attendeePW,moderatorPW FROM mdl_bbb_info WHERE courseid='$courseid' and  isRunning = '1' ");
	//$record=$DB->get_record('bbb_info',array('meetingID'=>$meetingID));
	if (!empty($room)){
		$room =  array_values($room);
		return json_encode(["data"=> $room[0]]);
	}
	 
	return json_encode(["data"=>$room[0]]);
}


function end_room($meetingID,$roomid){
	global $DB;
	$ins = new stdClass();
	$insroom = new stdClass();

	$ins->meetingID = $meetingID;
        
        $record=$DB->get_record('bbb_info',array('meetingID'=>$meetingID));
		$room=$DB->get_record('bbb_class_rooms',array('id'=>$roomid));
		if(!empty($record)){
            $ins->id=$record->id;
			$ins->isRunning= 0;
            $id = $DB->update_record('bbb_info', $ins);

			$insroom->id= $room->id;
			$insroom->teacherid= 0;
			$insroom->courseid = 0;
			$res = $DB->update_record('bbb_class_rooms', $insroom);

			if (!empty($id)&& $res == true){
				return json_encode(['message'=>'roomended']);
			}
			
			
        }
		return json_encode(['message'=>'error']);
}


function create_bbb_table($message,$messageKey,$hasBeenForciblyEnded,$duration,$hasUserJoined,$createDate,$dialNumber,$voiceBridge,$createTime,$moderatorPW,$attendeePW,$parentMeetingID,$internalMeetingID,$meetingID,$courseid,$isRunning){
		global $DB;
		$bbbInfo = new stdClass();
	
			try{
			
				$bbbInfo->meetingID = $meetingID;
				$bbbInfo->internalMeetingID = $internalMeetingID;
				$bbbInfo->parentMeetingID = $parentMeetingID;
				$bbbInfo->attendeePW = $attendeePW;
				$bbbInfo->moderatorPW = $moderatorPW;
				$bbbInfo->createTime = $createTime;
				$bbbInfo->courseid =  $courseid;
				$bbbInfo->isRunning = $isRunning;
				$bbbInfo->voiceBridge = $voiceBridge;
				$bbbInfo->dialNumber = $dialNumber;
				$bbbInfo->createDate = $createDate;
				$bbbInfo->hasUserJoined = $hasUserJoined;
				$bbbInfo->duration = $duration;
				$bbbInfo->hasBeenForciblyEnded = $hasBeenForciblyEnded;
				$bbbInfo->messageKey = $messageKey;
				$bbbInfo->message = $message;
				
				
				
				$bbbInfo->id = $DB->insert_record('bbb_info', $bbbInfo);
				 
				return json_encode(["message"=> 'successful']);
				
			} catch(Exception $e){
				 echo json_encode( ['message'=>$e]);
			 }
		
		
			return json_encode(["message"=> "error empty"]);
		}


		function checkReservationClassRoom($teacherid,$courseid){
			global $DB;
			$ins = new stdClass();
			//$room=$DB->get_records_sql("SELECT meetingID,attendeePW,moderatorPW FROM mdl_bbb_info WHERE courseid='$courseid' and  isRunning = '1' ");
	        $room=$DB->get_record('bbb_class_rooms',array('teacherid'=>0));
	        //return  json_encode(["message"=> $room]);
	       if (!empty($room)){
			    $ins->id=$room->id;
			    $ins->teacherid= $teacherid;
				$ins->courseid = $courseid;
                $res = $DB->update_record('bbb_class_rooms', $ins);
				if ($res == true){
					$room=$DB->get_record('bbb_class_rooms',array('id'=>$ins->id));
					return json_encode(["data"=> $room]);
				}
			}
			return  json_encode(["data"=> "norooms"]);
		}
		
		function check_course_has_bbb($courseid){
			global $DB;
			$room=$DB->get_record('course_bbb',array('course'=>$courseid));
			
			if (!empty($room)){
				if ($room->bbb == true){
					return json_encode(["bbb"=> 'true']);
				}
			}
			
			return json_encode(["bbb"=> 'false']);
		}
		function bbb_check_attend($meetingID,$course,$user){
			global $DB;
			$ins = new stdClass();
			$record=$DB->get_record('bbb_meeting_attend',array('meetingID'=>$meetingID,'course'=>$course,'user'=>$user));
			//return json_encode(["bbb"=> $record]);
			if(empty($record)){
				$ins->course=$course;
				$ins->meetingID=$meetingID;
				$ins->user=$user;
			    // $ins->starttime=date('m/d/Y h:i:s a', time());
				// return json_encode(["bbb"=> $ins]);
				$ins->id=$DB->insert_record('bbb_meeting_attend',$ins);
				return json_encode(["bbb"=> 'true']);
			}
			else{
				return json_encode(["bbb"=> 'Error']);
			}
		}
		function get_bbb_course_meetings($course){
			global $DB;
			$records=$DB->get_records_sql('SELECT info.meetingID,time.timecreated from mdl_bbb_info as info JOIN mdl_meeting_time as time ON info.meetingID=time.meetingID where isRunning=0 and courseid='.$course.'');
			//return var_dump('SELECT meetingID from mdl_bbb_info where courseid='.$course.'AND isRunning=0');
			if(empty($records)){
				return json_encode(["bbb"=> 'NO DATA']);
			}
			$data=array_values($records);
			return json_encode(["bbb"=> $data]);
		}
		function get_reports_bbb($course,$meeting){
			global $DB;
			$records=$DB->get_records_sql("SELECT u.id as id ,u.url,u.firstname,u.lastname  from mdl_user as u  JOIN mdl_bbb_meeting_attend as attend ON attend.user=u.id where attend.meetingID='".$meeting."' and attend.course=".$course." ");
			if(empty($records)){
				return json_encode(["bbb"=> 'NO DATA']);
			}
			else{
				$data=array_values($records);
				return json_encode(["bbb"=> $data]);
			}

		}
		function get_available_sessions(){
			$get_meetings_info=ACADEMY_BBB_URL.'getMeetings?checksum=2408dde2a4706931ee63798a2cd633d9b510030b';
			$xml = academy_http_request($get_meetings_info, array('timeout' => 10));
			$json=simplexml_load_string($xml);
			$data=json_encode($json);
			$get_meetings=json_decode($data,true);
			$count_meetings=count($get_meetings['meetings']['meeting']);
			$meetings_max_user=[10,20,40];
			$available_sessions=array();
			if ($count_meetings == 25 || $count_meetings <3){
				return 'true';//json_encode(["bbb"=> 'true']); 
			}
			return 'false';//json_encode(["bbb"=> 'false']);
			
			/*if($count_meetings==0){
					return json_encode(["bbb"=> $meetings_max_user]);

			}
			elseif ($count_meetings==25) {
			  foreach($meetings_max_user as $meeting ){
				if($meeting!= $get_meetings['meetings']['meeting']['maxUsers']){
				  array_push($available_sessions, $meeting);
				}
			  }
			 
			}
			else{
			 
			  for($i=0;$i<3;$i++){
				$flag=false;
				for($j=0;$j<$count_meetings;$j++){
				  if($meetings_max_user[$i]==$get_meetings['meetings']['meeting'][$j]['maxUsers']){
					$flag=true;
					break;
				  }
				}
				if($flag==false){
				  array_push($available_sessions,$meetings_max_user[$i]);
				}
			  }
			}
			if (empty($available_sessions)) {
				return json_encode(["bbb"=> 'NO DATA']);
			}
			else{
				return json_encode(["bbb"=> $available_sessions]);
			}*/
		}
		function get_running_session($course){
			global $DB;
			$sharedSecret=ACADEMY_BBB_SECRET;
			$records=$DB->get_records_sql('SELECT * FROM `mdl_bbb_info`WHERE courseid='.$course.' and isRunning=1');
			$data=array_values($records);
			$count=count($records);
			$lcount=$count-1;
		    $last_record=$data[$lcount];
			// return var_dump($count);
			if($last_record->isrunning=="1"){
				$check_isrunning='isMeetingRunningmeetingID='.$last_record->meetingid.''.$sharedSecret.'';
				$val=sha1($check_isrunning);
				$result=ACADEMY_BBB_URL.'isMeetingRunning?meetingID='.$last_record->meetingid.'&checksum='.$val.'';
				$xml = academy_http_request($result, array('timeout' => 10));
				$json=simplexml_load_string($xml);
				$data=json_encode($json);
				$get_meetings=json_decode($data,true);
				// var_dump($get_meetings);
				if($get_meetings['running']=="true"){
					return json_encode(["bbb"=> $last_record->meetingid]);
				}
				elseif($get_meetings['running']=="false") {
					// echo $last_record->id;
					$res=new stdClass();
					$res->id=$last_record->id;
					$res->isRunning=0;
					//  return $get_meetings['running'];
					$DB->update_record('bbb_info',$res);
					return json_encode(["bbb"=> 'NO SESSIONS']);      
			  
				}
				
		  
			}
			else{
				return json_encode(["bbb"=> 'NO SESSIONS']);  
			}
		}
		
		function create_new_bbb_session($course,$maxUsers=0,$user){
			global $DB,$sharedSecret;
			$sessionAvail = get_available_sessions();
			//echo file_get_contents(' https://api.covidtracking.com/v1/us/current.json');
			if ($sessionAvail == 'true'){
				
				
				$meetingID=random_string(10);
				$attendeePW=random_string(10);
				$moderatorPW=random_string(10);
				$ins = new stdClass();
				$ins->meetingID=  $meetingID;
				$ins->attendeePW=  $attendeePW;
				$ins->moderatorPW=  $moderatorPW;
				$ins->courseid=  $course;
				$ins->isRunning=1;
				$res= $DB->insert_record('bbb_info', $ins);
					
					$name="Session";
				    $duration=bbb_room_time($course);
				    $copyright='copyright';
				    $welcome="WELCOME";
				
					$link= 'createname='.$name.'&meetingID='.$meetingID.'&attendeePW='.$attendeePW.'&moderatorPW='.$moderatorPW.'&maxParticipants='.$maxUsers.'&welcome='.$welcome.'&copyright='.$copyright.'&duration='.$duration.''.$sharedSecret.'';
					$value=sha1($link);
					 $response=ACADEMY_BBB_URL.'create?name='.$name.'&meetingID='.$meetingID.'&attendeePW='.$attendeePW.'&moderatorPW='.$moderatorPW.'&maxParticipants='.$maxUsers.'&welcome='.$welcome.'&copyright='.$copyright.'&duration='.$duration.'&checksum='.$value.'';
					 $arrContextOptions=array(
						"ssl"=>array(
							  "verify_peer"=>false,
							  "verify_peer_name"=>false,
						  ),
					  );  
				  
				  $xml = academy_http_request($response, array('timeout' => 10));
					//  $xml = file_get_contents($response);
					 $json=simplexml_load_string($xml);
					 $data=json_encode($json);
					 $final=json_decode($data,true);
					//  $ch = curl_init();
					//  curl_setopt($ch, CURLOPT_URL, "http://api.football-data.org/v1/soccerseasons/426/leagueTable");
					//  curl_setopt($ch, CURLOPT_POST, 1);
					//  $result = curl_exec($ch);
					//  return $result ;
					//  curl_close($ch); 
					// $w = stream_get_wrappers();
// echo 'openssl: ',  extension_loaded  ('openssl') ? 'yes':'no', "\n";
// echo 'http wrapper: ', in_array('http', $w) ? 'yes':'no', "\n";
// echo 'https wrapper: ', in_array('https', $w) ? 'yes':'no', "\n";
// echo 'wrappers: ', var_export($w);
// echo file_get_contents(' https://api.covidtracking.com/v1/us/current.json');
				// return  file_get_contents('http://api.football-data.org/v1/soccerseasons/426/leagueTable');
					 if($final['returncode']=="SUCCESS"){
						$join_link =join_moderator($course,$user);
						if($join_link){
							return json_encode(["bbb"=> $join_link ]);
						}
						else{
							return json_encode(["bbb"=> 'error 1 ']);
						}
					 
					}
					 else{
						return json_encode(["bbb"=> 'error 2']);
					 }
				
				
			}elseif($sessionAvail == 'false'){
				return json_encode(["bbb"=> 'NO SESSION']);
			}
			
		

		}
		function join_moderator($course,$user){
			global $sharedSecret,$DB;
			$records=$DB->get_records_sql('SELECT * FROM `mdl_bbb_info`WHERE courseid='.$course.' and isRunning=1');
			$user=$DB->get_record('user',array('id'=>$user));
			$data=array_values($records);
		   $count=count($records);
		   $lcount=$count-1;
		   $last_record=$data[$lcount];
		   $meetingID= $last_record->meetingid;
 			$moderatorPW= $last_record->moderatorpw;
			$check='joinmeetingID='.$meetingID.'&password='.$moderatorPW.'&fullName='.$user->username.''.$sharedSecret.'';
			$value=sha1($check);
			$response=ACADEMY_BBB_URL.'join?meetingID='.$meetingID.'&password='.$moderatorPW.'&fullName='.$user->username.'&checksum='.$value.'';
			
			insert_create_time($meetingID,$course);
			
			return $response;
		}
		
		function join_moderator_Api($course,$userid){
			global $sharedSecret,$DB;
			$records=$DB->get_records_sql('SELECT * FROM `mdl_bbb_info`WHERE courseid='.$course.' and isRunning=1');
			$user=$DB->get_record('user',array('id'=>$userid));
			$data=array_values($records);
		   $count=count($records);
		   $lcount=$count-1;
		   $last_record=$data[$lcount];
		   $meetingID= $last_record->meetingid;
 			$moderatorPW= $last_record->moderatorpw;
			$check='joinmeetingID='.$meetingID.'&password='.$moderatorPW.'&fullName='.$user->username.''.$sharedSecret.'';
			$value=sha1($check);
			
			$response=ACADEMY_BBB_URL.'join?meetingID='.$meetingID.'&password='.$moderatorPW.'&fullName='.$user->username.'&checksum='.$value.'';
			
			$ins = new stdClass();
			$record=$DB->get_record('bbb_meeting_attend',array('meetingID'=>$meetingID,'course'=>$course,'user'=>$userid));
			
			if(empty($record)){
				$ins->course=$course;
				$ins->meetingID=$meetingID;
				$ins->user=$userid;
			    // $ins->starttime=date('m/d/Y h:i:s a', time());
				// return json_encode(["bbb"=> $ins]);
				$ins->id=$DB->insert_record('bbb_meeting_attend',$ins);
			}
			
			return json_encode(["join Link"=> $response ]);
		}
		
		function join_attendee($course,$userid){
			global $sharedSecret,$DB;
			$records=$DB->get_records_sql('SELECT * FROM `mdl_bbb_info`WHERE courseid='.$course.' and isRunning=1');
			$user=$DB->get_record('user',array('id'=>$userid));
			$data=array_values($records);
		   $count=count($records);
		   $lcount=$count-1;
		   $last_record=$data[$lcount];
		   $meetingID= $last_record->meetingid;
 			$attendeePW= $last_record->attendeepw;
			$check='joinmeetingID='.$meetingID.'&password='.$attendeePW.'&fullName='.$user->username.''.$sharedSecret.'';
			 $value=sha1($check);
			 $response=ACADEMY_BBB_URL.'join?meetingID='.$meetingID.'&password='.$attendeePW.'&fullName='.$user->username.'&checksum='.$value.'';
			 
			 $ins = new stdClass();
			$record=$DB->get_record('bbb_meeting_attend',array('meetingID'=>$meetingID,'course'=>$course,'user'=>$userid));
			
			if(empty($record)){
				$ins->course=$course;
				$ins->meetingID=$meetingID;
				$ins->user=$userid;
			    // $ins->starttime=date('m/d/Y h:i:s a', time());
				// return json_encode(["bbb"=> $ins]);
				$ins->id=$DB->insert_record('bbb_meeting_attend',$ins);
			}
			
			 
			 return json_encode(["join Link"=> $response ]);
		}
		function end_session($course){
			global $sharedSecret,$DB;
			$records=$DB->get_records_sql('SELECT * FROM `mdl_bbb_info`WHERE courseid='.$course.' and isRunning=1');
			$data=array_values($records);
			$count=count($records);
			$lcount=$count-1;
			$last_record=$data[$lcount];
			$meetingID= $last_record->meetingid;
			$moderatorPW= $last_record->moderatorpw;
			$check='endmeetingID='.$meetingID.'&password='.$moderatorPW.''.$sharedSecret.'';
 			$value=sha1($check);
 			$response_end=ACADEMY_BBB_URL.'end?meetingID='.$meetingID.'&password='.$moderatorPW.'&checksum='.$value.'';
			 $xml = academy_http_request($response_end, array('timeout' => 10));
			 $json=simplexml_load_string($xml);
			 $data=json_encode($json);
			 $final=json_decode($data,true);
			if($final['returncode']=="FAILED"){
				return json_encode(["bbb"=> 'Error End']);

			}
			else {
				$isRunning=$DB->get_record('bbb_info',array('meetingID'=>$meetingID));
				$res=new stdClass();
				$res->id=$isRunning->id;
				$res->isRunning=0;
				$DB->update_record('bbb_info',$res);
				// return $response_end;
				return json_encode(["bbb"=> 'Ended Successfully']);
			}
		}
		
		function insert_create_time($meetingID,$course){
		global $DB;
		//$meetingID=get_meeting_id($course);
		if(!empty($meetingID)){
			$ins = new stdClass();
			$ins->meetingID=$meetingID;
			$ins->course=$course;
			$ins->timecreated=date('Y-d-m H:i:s',time());
			$DB->insert_record('meeting_time',$ins);
	
		}
		

	}
	function insert_end_time($course){
		global $DB;
		$records=$DB->get_records_sql('SELECT * FROM `mdl_bbb_info`WHERE courseid='.$course.' and isRunning=1');
		$data=array_values($records);
		$count=count($records);
		$lcount=$count-1;
		$last_record=$data[$lcount];
		 $meetingID= $last_record->meetingid;
		$record=$DB->get_record('meeting_time',array('meetingID'=>$meetingID,'course'=>$course));
		if(!empty($record)){
			$ins= new stdClass();
			$ins->id=$record->id;
			$ins->timeended=date('Y-d-m H:i:s',time());
			$ins->id=$DB->update_record('meeting_time',$ins);
		}
	}
	function get_time($meetingID){
		global $DB;
		$record=$DB->get_record('meeting_time',array('meetingID'=>$meetingID));
		return $record;
	}
	function bbb_room_time($course){
		global $DB;
		date_default_timezone_set('Africa/Cairo');
		$date = date('H:i:s');
		$day=date('Y-m-d');
		$record=$DB->get_record('bbb_reservation',array('course'=>$course,'date'=>$day));
		$time2=$record->endtime;
		$t1 = explode(':', $date);
		$t2 = explode(':', $time2);
		$m1=$t1[0]*60 + $t1[1] ;
		$m2=$t2[0]*60 + $t2[1] ;
		return ($m2 - $m1 -1);
	}
	function check_bbb_room_time($course){//to check the validety of the current time by api 
		global $DB;
		date_default_timezone_set('Africa/Cairo');
		$date = date('H:i:s');
		$day=date('Y-m-d');
		$record=$DB->get_record('bbb_reservation',array('course'=>$course,'date'=>$day));
		if(($record->starttime<=$date && $record->endtime>$date)&& $record->date==$day){
			return json_encode(["bbb"=> 'true']);
		}
		else{
			return json_encode(["bbb"=> 'false']);
		}
		
	}
	
	

?>
