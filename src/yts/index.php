<?php
require_once('../config.php');
require_once($CFG->dirroot .'/course/lib.php');

set_include_path(get_include_path() . PATH_SEPARATOR . 'phpseclib');
include('Crypt/RSA.php');
include('Math/BigInteger.php');
include('Crypt/Hash.php');
include('Crypt/Random.php');

if (!isset($_GET['id'])) {
	header('Location: '.$CFG->wwwroot);
	exit();
}

use phpseclib\Crypt\RSA;

$rsa = new RSA();
$private_key = "MIICXAIBAAKBgQCqGKukO1De7zhZj6+H0qtjTkVxwTCpvKe4eCZ0FPqri0cb2JZfXJ/DgYSF6vUpwmJG8wVQZKjeGcjDOL5UlsuusFncCzWBQ7RKNUSesmQRMSGkVb1/3j+skZ6UtW+5u09lHNsj6tQ51s1SPrCBkedbNf0Tp0GbMJDyR4e9T04ZZwIDAQABAoGAFijko56+qGyN8M0RVyaRAXz++xTqHBLh3tx4VgMtrQ+WEgCjhoTwo23KMBAuJGSYnRmoBZM3lMfTKevIkAidPExvYCdm5dYq3XToLkkLv5L2pIIVOFMDG+KESnAFV7l2c+cnzRMW0+b6f8mR1CJzZuxVLL6Q02fvLi55/mbSYxECQQDeAw6fiIQXGukBI4eMZZt4nscy2o12KyYner3VpoeE+Np2q+Z3pvAMd/aNzQ/W9WaI+NRfcxUJrmfPwIGm63ilAkEAxCL5HQb2bQr4ByorcMWm/hEP2MZzROV73yF41hPsRC9m66KrheO9HPTJuo3/9s5p+sqGxOlFL0NDt4SkosjgGwJAFklyR1uZ/wPJjj611cdBcztlPdqoxssQGnh85BzCj/u3WqBpE2vjvyyvyI5kX6zk7S0ljKtt2jny2+00VsBerQJBAJGC1Mg5Oydo5NwD6BiROrPxGo2bpTbu/fhrT8ebHkTz2eplU9VQQSQzY1oZMVX8i1m5WUTLPz2yLJIBQVdXqhMCQBGoiuSoSjafUhV7i1cEGpb88h5NBYZzWXGZ37sJ5QsW+sJyoNde3xH8vdXhzU7eT82D6X/scw9RZz+/6rCJ4p0=";
$rsa->loadKey($private_key); // private key
$rsa->setEncryptionMode(RSA::ENCRYPTION_PKCS1);

$content = str_replace(' ', '+', urldecode( $_GET['id']));

//echo base64_decode($_REQUEST['id']).'<br>';
$content = $rsa->decrypt(base64_decode($content));

//echo $content;
parse_str ($content, $obj);

//print_r($obj);

//die();
$secure = true;
if (isset($obj['s'])) {
	if ($obj['s'] === '0') {
		$secure = false;
	}
}

if ($secure) {
	header("Content-Security-Policy: frame-ancestors 'self'");
	if (!isset($_SERVER['HTTP_REFERER']) || strpos($_SERVER['HTTP_REFERER'], $CFG->wwwroot)<0) {
		header('Location: '.$CFG->wwwroot);
		exit();
	}
	
	//Check if User already log in
	if ($USER->id === 0) {
		header('Location: '.$CFG->wwwroot);
		exit();
	}
}

function get_http_response_code($url) {
  $headers = @get_headers($url);
  return substr($headers[0], 9, 3);
}

function url_exists($url) {
    $get_http_response_code = get_http_response_code($url);
	if ($get_http_response_code == 200 || $get_http_response_code == 302) return true;
	return false;
}

if (isset($obj['v'])) { // vimeo link
	header('Location: https://player.vimeo.com/external/'.$obj['v']);
	exit();
}


$existing = $DB->get_record('yts', array('yts_id'=>$obj['id']));
if ($existing) {
	if (url_exists($existing->url)) {
		header('Location: '.$existing->url);
		exit();
	}
} 

function curl_get_file_contents($URL)
{
    $c = curl_init();
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_URL, $URL);
    $contents = curl_exec($c);
    curl_close($c);

    if ($contents) {
        return $contents;
    }

    return false;
}

$content = curl_get_file_contents('http://127.0.0.1:3000/?v='.$obj['id']);
if (!$content) {
	for($i = 0; $i < 10; ++$i) {
		$content = @exec('/opt/yts/node_modules/youtube-dl/bin/youtube-dl --dump-json -f best http://www.youtube.com/watch?v='.$obj['id']);
		if ($content) {
			break;
		}
	}
}

if ($content) {
	$vd_url = json_decode ($content)->url;
	if ($existing) {
		$existing->url = $vd_url;
		$DB->update_record('yts', $existing);
	}
	else {
		$existing = new stdClass();
		$existing->url = $vd_url;
		$existing->yts_id = $obj['id'];
		$DB->insert_record_raw('yts', $existing);
	}
	header('Location: '.$vd_url);
}