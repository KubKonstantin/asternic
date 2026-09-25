<?php
require_once "config.php";
require_once "sesvars.php";
require_once "casdoor_auth.php";
check_auth();
$current_user = $_SESSION['auth_user'] ?? null;
if (isset($_POST['agent_sync'])) {
	$aquery = "truncate agents_new;";
	$aquery .= "insert ignore into agents_new (agent) SELECT DISTINCT(agent) FROM $DBTable where agent != 'NONE' and agent not like 'Local%' and agent not like 'PJSIP%'";
	$ares = mysqli_multi_query($connection, $aquery);
	$_POST['agent_sync'] = NULL;
	echo "<!DOCTYPE html>\r\n";
	echo "<head>\r\n";
	echo "<meta http-equiv='refresh' content='0;url=./index.php' /> ";
	echo "</head><html></html>";
}

if (isset($_POST['queue_sync'])) {
	$qquery = "truncate queues_new;";
	$qquery .= "insert ignore into queues_new (queuename) SELECT DISTINCT(queuename) FROM $DBTable where queuename != 'NONE'";
	$qres = mysqli_multi_query($connection, $qquery);
	$_POST['queue_sync'] = NULL;
	echo "<!DOCTYPE html>\r\n";
	echo "<head>\r\n";
	echo "<meta http-equiv='refresh' content='0;url=./index.php' /> ";
	echo "</head><html></html>";
}
//session_unset();
$casdoor_auth_data = isset($_SESSION['casdoor_authenticated']) ? $_SESSION['casdoor_authenticated'] : null;
$casdoor_user_data = isset($_SESSION['casdoor_user']) ? $_SESSION['casdoor_user'] : null;
$internal_auth_data = isset($_SESSION['internal_authenticated']) ? $_SESSION['internal_authenticated'] : null;
$auth_user_data = isset($_SESSION['auth_user']) ? $_SESSION['auth_user'] : null;

// Очищаем сессию
session_unset();

// Восстанавливаем данные авторизации
if ($casdoor_auth_data !== null) {
    $_SESSION['casdoor_authenticated'] = $casdoor_auth_data;
}
if ($casdoor_user_data !== null) {
    $_SESSION['casdoor_user'] = $casdoor_user_data;
}
if ($internal_auth_data !== null) {
    $_SESSION['internal_authenticated'] = $internal_auth_data;
}
if ($auth_user_data !== null) {
    $_SESSION['auth_user'] = $auth_user_data;
}
?>
