<?php
require_once('../../config.php');
require_once($CFG->dirroot .'/course/lib.php');

function is_allowed($contextid = 0) {
	global $USER, $DB, $COURSE;

	if ($contextid == 0 || !$DB->record_exists('context', array('id' => $contextid))) {
		$context = context_course::instance($COURSE->id);
	} else {
		$context = context::instance_by_id($contextid);
	}
	// Check switched role.
	if (!empty($USER->access['rsw'])) {
		$context = context_course::instance(SITEID);
		if (has_capability_in_accessdata('moodle/course:create', $context, $USER->access)) {
			return true;
		}
		return false;
	}
	if (has_capability('moodle/course:create', $context)) {
		return true;
	}
	return false;
}

if (/*!is_siteadmin($USER->id)*/ !is_allowed()) {
	header('Location: '.$CFG->wwwroot);
	exit();
}

?>
<script src="jsencrypt.min.js"></script>
<script>
	const mainUrl = '<?php echo $CFG->wwwroot ?>/yts/?id=';
	function youtube_parser(url){
		var regExp = /^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#\&\?]*).*/;
		var match = url.match(regExp);
		return (match&&match[7].length==11)? match[7] : false;
	}
	function convert() {
		var input_url = document.querySelector('#input').value
		var ytb_id = youtube_parser(input_url);
		var sv = "https://player.vimeo.com/external/";
		
		if (input_url && input_url.length>sv.length && input_url.substring(0, sv.length) == sv) {
			
			var elm = document.querySelector('#output');
			elm.value = mainUrl + encodeURI(crypt.encrypt("v="+input_url.substring(sv.length, input_url.length) + (!document.querySelector('#secure').checked? "&s=0":"")))
				+ (document.querySelector('#videojs').checked? "&file=video.mp4":"");
				
			elm.focus();
			elm.select();
			document.execCommand('copy');
			console.log('vimeo');
		}
		else if (ytb_id) {
			
			var elm = document.querySelector('#output');
			elm.value = mainUrl + encodeURI(crypt.encrypt("id="+ytb_id + (!document.querySelector('#secure').checked? "&s=0":"")))
				+ (document.querySelector('#videojs').checked? "&file=video.mp4":"");
				
			elm.focus();
			elm.select();
			document.execCommand('copy');
		}
		else {
			alert('Invalid youtube/vimeo link ...');
		}
	}
	function initEncrypt() {
		window.crypt = new JSEncrypt();
		crypt.setPublicKey('MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCqGKukO1De7zhZj6+H0qtjTkVxwTCpvKe4eCZ0FPqri0cb2JZfXJ/DgYSF6vUpwmJG8wVQZKjeGcjDOL5UlsuusFncCzWBQ7RKNUSesmQRMSGkVb1/3j+skZ6UtW+5u09lHNsj6tQ51s1SPrCBkedbNf0Tp0GbMJDyR4e9T04ZZwIDAQAB');
	}
</script>
<body onload="initEncrypt()">
<p>
	Enter Youtube/Vimeo Link: 
	<input id="input" type="text" value="" />
</p>
<p>
	Result:
	<input id="output" type="text" value="" readonly />
</p>

<p>
	<input id="secure" type="checkbox" checked /> Secure
	<input id="videojs" type="checkbox" checked /> Support Videojs player.
	
	
</p>
<p>
	<input type="button" value="Convert" onclick="convert()"/>
</p>
</body>