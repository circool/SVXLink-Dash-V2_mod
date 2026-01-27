<?php

/**
 * @author vladimir@tsurkanenko.ru
 * @version 0.1.2.release
 * @filesource /include/fn/getLineTime.php
 */
function getLineTime(string $line): int
{
	$timeZone = isset($_SESSION['TIMEZONE']) ? $_SESSION['TIMEZONE'] : "UTC";
	
	$pos = strpos($line, ': ');

	if ($pos === false) {
		error_log("getLineTime: Timestamp parsing error in: $line");
		return false;
	}

	$_timestamp = substr($line, 0, $pos);
	$time = strtotime($_timestamp . " " . $timeZone);
	if ($time === false) {
		error_log("getLineTime: Timestamp parsing error in: $line");
		return false;
	}

	return $time;
}
?>