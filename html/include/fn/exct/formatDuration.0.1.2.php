<?php

/** Форматирование количества секунд в формат HH:MM:SS
 *
 * @author vladimir@tsurkanenko.ru
 * @date 2025.12.11
 * @version 0.1.2
 * @filesource /include/fn/exct/formatDuration.0.1.2.php
 * @param int $seconds
 * @return string
 */
function formatDuration(int $seconds): string
{
	$ver = "formatDuration 0.1.2";
	if (defined("DEBUG") && DEBUG) dlog("$ver: Начинаю выполнение", 4, "DEBUG");
	$seconds = (int)$seconds;

	// Для значений с пределах 24 часов - простой вывод
	if ($seconds < 86400) return gmdate("H:i:s", $seconds);

	// Для больших интервалов вычисляем отдельные компоненты времени
	$hours = floor($seconds / 3600);
	$minutes = floor(($seconds % 3600) / 60);
	$secs = $seconds % 60;

	$days = floor($hours / 24);
	$hours = $hours % 24;

	$parts = [];

	// Дни (теперь считаем правильно)
	if ($days > 0) {
		$parts[] = $days . ' дн.';
	}

	// Время
	$timePart = sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);

	if (!empty($parts)) {
		return implode(' ', $parts) . ' ' . $timePart;
	} else {
		return $timePart;
	}
}
?>