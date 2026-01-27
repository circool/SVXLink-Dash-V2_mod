<?php

/** 
 * @author vladimir@tsurkanenko.ru
 * @date 2026.01.18
 * @version 0.1.3.release
 * @filesource /include/fn/formatDuration.php
 * @param int $seconds
 * @return string
 */
function formatDuration(int $seconds): string
{

	$seconds = (int)$seconds;
	if ($seconds === 0) {
		return '0';
	}

	$days = floor($seconds / 86400);
	$hours = floor(($seconds % 86400) / 3600);
	$minutes = floor(($seconds % 3600) / 60);
	$secs = $seconds % 60;

	$result = '';
	if ($days > 0) {
		$result .= $days . ' ' . getTranslation('d').' ';
	}

	
	if ($hours > 0 || $days > 0) {
		$result .= sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
	} elseif ($minutes > 0) {
		$result .= sprintf('%d:%02d', $minutes, $secs);
	} else {
		$result .= sprintf('0:%02d', $secs);
	}
	return $result;
}
