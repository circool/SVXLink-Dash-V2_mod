<?php

/**
 * Выход из системы
 * @filesource /include/exct/logout.0.0.1.php
 * @version 0.0.1
 * 
 */



include_once $_SERVER["DOCUMENT_ROOT"] . "/include/init.php";
include_once $_SERVER["DOCUMENT_ROOT"] . "/include/fn/dlog.0.2.php";

$ver = 'logout.php 0.1.1';


if (defined("DEBUG") && DEBUG ) dlog("$ver: Начинаю", 4, "AUTH");


$_SESSION['auth'] = 'UNAUTHORISED';
if (defined("DEBUG") && DEBUG ) dlog("$ver: Устанавливаю UNAUTHORISED", 4, "AUTH");

session_destroy();
if (defined("DEBUG") && DEBUG ) dlog("$ver: Сессия удалена", 4, "AUTH");


if (defined("DEBUG") && DEBUG ) dlog("$ver: Перенаправляю на /index_debug.php", 4, "AUTH");
header('Location: /index_debug.php');
exit;

if (defined("DEBUG") && DEBUG ) dlog("$ver: Закончил", 4, "AUTH");
?>