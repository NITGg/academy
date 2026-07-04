<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost    = getenv('DB_HOST')    ?: 'academy_db';
$CFG->dbname    = getenv('DB_NAME')    ?: 'academy2022_moodle';
$CFG->dbuser    = getenv('DB_USER')    ?: 'root';
$CFG->dbpass    = getenv('DB_PASS')    ?: 'root';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array(
  'dbpersist' => 0,
  'dbport'    => 3306,
  'dbsocket'  => '',
  'dbcollation' => 'utf8mb4_general_ci',
);

$CFG->wwwroot  = getenv('MOODLE_WWWROOT') ?: 'http://localhost:8081';
$CFG->dataroot = '/var/www/moodledata';
$CFG->admin    = 'admin';

$CFG->directorypermissions = 0777;
$CFG->cachejs = true;
$CFG->langstringcache = true;
$CFG->cachetemplates = true;
$CFG->themedesignermode = false;
$CFG->localcachedir = '/var/www/moodledata/localcache';

// On HTTPS production — enable secure cookies
if (getenv('MOODLE_SSLPROXY')) {
    $CFG->sslproxy   = true;
    $CFG->cookiesecure = true;
}

@ini_set('session.auto_start', '0');

if (isset($_COOKIE["userdata"])) {
    if ($_COOKIE["userdata"] == 14) {
        $CFG->logouturl  = "$CFG->wwwroot/login/index.php?id=" . $_COOKIE['userdata'] . "&lang=ar";
        $CFG->logouturl2 = "$CFG->wwwroot/?id=" . $_COOKIE['userdata'] . "&lang=ar";
        $CFG->signup     = "$CFG->wwwroot/login/signup.php?id=" . $_COOKIE['userdata'] . "&lang=ar";
    } else {
        $CFG->logouturl  = "$CFG->wwwroot/login/index.php?id=" . $_COOKIE['userdata'];
        $CFG->logouturl2 = "$CFG->wwwroot/?id=" . $_COOKIE['userdata'];
        $CFG->signup     = "$CFG->wwwroot/login/signup.php?id=" . $_COOKIE['userdata'] . "&lang=ar";
    }
    $CFG->userId = $_COOKIE['userdata'];
} else {
    $CFG->logouturl = "$CFG->wwwroot/login/index.php";
    $CFG->signup    = "$CFG->wwwroot/login/signup.php?id=14&lang=ar";
}

require_once(__DIR__ . '/lib/setup.php');