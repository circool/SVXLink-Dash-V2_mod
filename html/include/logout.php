<?php

/**
 * @author vladimir@tsurkanenko.ru
 * @filesource /include/logout.php
 * @version 0.0.1.release
 * @note Preliminary version.
 */

include_once $_SERVER["DOCUMENT_ROOT"] . "/include/init.php";

$_SESSION['auth'] = 'UNAUTHORISED';
session_destroy();
header('Location: /index.php');
exit;
?>