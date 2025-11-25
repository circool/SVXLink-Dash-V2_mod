<?php

/**
 * Copyright SVXLink-Dashboard-V2 by F5VMR 
 */
include_once "include/debug_min_function.php";

// Всегда подключаем config.inc.php если существует
if (file_exists(__DIR__ . "/debug_config.inc.php")) {
	include_once __DIR__ . "/debug_config.inc.php";
}

include_once "debug_parse_svxconf.php";

error_reporting(0);

$svxConfigFile = '/etc/svxlink/svxlink.conf';
$sessionInfo = debug_getSessionInfo($svxConfigFile);
