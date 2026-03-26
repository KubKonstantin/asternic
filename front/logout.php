<?php
require_once("config.php");
require_once("sesvars.php");

session_start();

// Очищаем сессию
session_unset();
session_destroy();

// Перенаправляем на главную страницу
header('Location: index.php');
exit();

function flushSession() {
    unset($_SESSION["QSTATS"]);

    session_destroy();

    return true;
}
flushSession();
?>
