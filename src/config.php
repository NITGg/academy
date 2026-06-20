<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'academy_db';
$CFG->dbname    = 'academy2022_moodle';
$CFG->dbuser    = 'root';
$CFG->dbpass    = 'root';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => 3306,
  'dbsocket' => '',
  'dbcollation' => 'utf8mb4_general_ci',
);

$CFG->wwwroot   = 'http://localhost:8081';
$CFG->dataroot  = '/var/www/moodledata';
$CFG->admin     = 'admin';

$CFG->directorypermissions = 0777;
$CFG->cachejs = true;
$CFG->langstringcache = true;
$CFG->cachetemplates = true;
$CFG->themedesignermode = false;
$CFG->localcachedir = '/var/www/moodledata/localcache';
@ini_set('session.auto_start','0');

if(isset( $_COOKIE["userdata"])){
  if($_COOKIE["userdata"]==14){
    $CFG->logouturl="$CFG->wwwroot/login/index.php?id=".$_COOKIE['userdata']."&lang=ar";
    $CFG->logouturl2="$CFG->wwwroot/?id=".$_COOKIE['userdata']."&lang=ar";
    $CFG->signup="$CFG->wwwroot/login/signup.php?id=".$_COOKIE['userdata']."&lang=ar";
  }
  else{
    $CFG->logouturl="$CFG->wwwroot/login/index.php?id=".$_COOKIE['userdata']."";
    $CFG->logouturl2="$CFG->wwwroot/?id=".$_COOKIE['userdata']."";
    $CFG->signup="$CFG->wwwroot/login/signup.php?id=".$_COOKIE['userdata']."&lang=ar";

  }
 
  $CFG->userId=$_COOKIE['userdata'];
}
else{

  $CFG->logouturl="$CFG->wwwroot/login/index.php";
}
require_once(__DIR__ . '/lib/setup.php');
