<?php

/** Форматирование количества секунд в адаптивный формат (дн HH:MM:SS без лидирующих нулей)
 * 
 * @author vladimir@tsurkanenko.ru
 * @date 2026.01.18
 * @version 0.1.3
 * @filesource /include/fn/exct/formatDuration.0.1.3.php
 * @param int $seconds
 * @return string
 */
function formatDuration(int $seconds): string
{
	$ver = "formatDuration 0.1.3";
	if (defined("DEBUG") && DEBUG) dlog("$ver: Начинаю выполнение", 4, "DEBUG");
	$seconds = (int)$seconds;

	if ($seconds === 0) {
		return '0';
	}

	// Вычисляем все компоненты времени
	$days = floor($seconds / 86400);
	$hours = floor(($seconds % 86400) / 3600);
	$minutes = floor(($seconds % 3600) / 60);
	$secs = $seconds % 60;

	// Если есть дни - добавляем их
	$result = '';
	if ($days > 0) {
		$result .= $days . ' дн ';
	}

	// Формируем временную часть
	if ($hours > 0 || $days > 0) {
		// Есть часы или дни - формат ЧЧ:ММ:СС
		$result .= sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
	} elseif ($minutes > 0) {
		// Есть минуты - формат ММ:СС
		$result .= sprintf('%d:%02d', $minutes, $secs);
	} else {
		// Меньше минуты - формат 0:СС
		$result .= sprintf('0:%02d', $secs);
	}

	return $result;
}
