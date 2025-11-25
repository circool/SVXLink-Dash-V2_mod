<?php
/**
 * @file debug_parse_svxconf.php
 * @brief Парсинг конфигурационного файла svxlink.conf
 * @author vladimir@tsurkanenko.ru
 * @date 2025-12-25
 * @version 0.1.3
 */
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
include "debug_config.php";
include_once "debug_min_function.php";

if ((defined('SVXCONFIG')) && (defined('SVXCONFPATH'))) {
	$svxConfigFile = SVXCONFPATH . SVXCONFIG;
} else {
	$svxConfigFile = trim(substr(shell_exec("grep CFGFILE /etc/default/svxlink"), strrpos(shell_exec("grep CFGFILE /etc/default/svxlink"), "=") + 1));
}

