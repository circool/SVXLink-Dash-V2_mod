<?php

/**
 * Возвращает время строки журнала
 * @version 0.1.1
 * @filesource getLineTime.0.1.1.php
 */
function getLineTime(string $line) : int|bool
{
	$timeZone = isset($_SESSION['TIMEZONE']) ? $_SESSION['TIMEZONE'] : "UTC";

	$_timestamp = strstr($line, ': ', true);
	if ($_timestamp !== false) {
		return strtotime($_timestamp . " " . $timeZone);
	}
	if (defined("DEBUG") && DEBUG) dlog("getLineTime: Ошибка получения времени из строки $line (попытка разобрать: $_timestamp оказалась неуспешной), возвращаю FALSE", 1, "ERROR");
	return false;
}
?>