<?php

/**
 * @author Vladimir Tsurkanenko <vladimir@tsurkanenko.ru>
 * @version 0.4.6
 * @filesource /include/fn/getLineTime.php
 */
function getLineTime(string $line): int
{
	
	if (isset($_SESSION['TIMEZONE'])) {
		$timeZone = $_SESSION['TIMEZONE'];
	} else {
		if (file_exists('/etc/timezone')) {
			$timeZone = trim(file_get_contents('/etc/timezone'));
		} else {
			$timeZone = 'UTC';
		}	
	}

	date_default_timezone_set($timeZone);
	
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