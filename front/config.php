<?php
require_once "misc.php";

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

$DBServer = getenv('DB_SERVER');
$DBUser = getenv('DB_USER');
$DBPass = getenv('DB_PASS');
$DBName = getenv('DB_NAME');
$DBTable = getenv('DB_TABLE');

$isconpbx = false;

$casdoor_config = [
    'server_url'    => getenv('CASDOOR_SERVER_URL'),
    'client_id'     => getenv('CASDOOR_CLIENT_ID'),
    'client_secret' => getenv('CASDOOR_CLIENT_SECRET'),
    'redirect_uri'  => getenv('CASDOOR_REDIRECT_URI'),
    'org_name'      => getenv('CASDOOR_ORG_NAME'),
    'app_name'      => getenv('CASDOOR_APP_NAME')
];

$casdoor_config = [
    'server_url'    => 'https://id-test.sberhealth.team',
    'client_id'     => '7e7d70a4eaa638ebc5f8',
    'client_secret' => 'aebabdc4c5bc9af318b15e2eb0f207a1341a04a0',
    'redirect_uri'  => 'http://10.137.2.178:8082/casdoor_callback.php',
    'org_name'      => 'test',
    'app_name'      => 'asternic'
];

define('RECPATH',"/var/spool/asterisk/monitor/");

$connection = new mysqli($DBServer, $DBUser, $DBPass, $DBName);
$connection->set_charset('utf8');

// check connection

//print_r(getenv());

if ($connection->connect_error) {
	trigger_error('Database connection failed: ' . $connection->connect_error, E_USER_ERROR);
}

if ($isconpbx){
	$confpbx = new mysqli('localhost', 'freepbxuser', '', 'asterisk');
	$confpbx->set_charset('utf8');
}

//$user = $_SERVER['PHP_AUTH_USER'];
//$pass = $_SERVER['PHP_AUTH_PW'];

//$valid_passwords2 = $confpbx->query("SELECT password_sha1 FROM ampusers WHERE username = '$user'");
//$valid_passwords = $valid_passwords2->fetch_row();

//$validated = (sha1($pass) == $valid_passwords[0]);

//if (!$validated) {
//	header('WWW-Authenticate: Basic realm="fs-tst"');
//	header('HTTP/1.0 401 Unauthorized');
//	die("Not authorized");
//}

//$valid_passwords2->free();

//AJAM for realtime. For use: webenable=yes; mini-http enable; 

$config['urlraw'] = 'http://127.0.0.1:8088/asterisk/rawman';
$config['admin'] = 'admin';
$config['secret'] = '';
$config['authtype'] = 'plaintext';
$config['cookiefile'] = null;
$config['debug'] = false;


// Available languages "en", "ru"
$language = "ru";

require_once "lang/$language.php";

$page_rows = '100';
//$midb = conecta_db($dbhost,$dbname,$dbuser,$dbpass);
$self = $_SERVER['PHP_SELF'];

$DB_DEBUG = false;

session_start();
header('content-type: text/html; charset: utf-8');

?>
