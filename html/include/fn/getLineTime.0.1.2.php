<?php

/**
 * Возвращает время строки журнала
 * @version 0.1.2
 * @filesource /include/fn/getLineTime.0.1.2.php
 */
function getLineTime(string $line): int
{
	$timeZone = isset($_SESSION['TIMEZONE']) ? $_SESSION['TIMEZONE'] : "UTC";

	// Ищем позицию ": " в строке
	$pos = strpos($line, ': ');

	// Если ": " не найден
	if ($pos === false) {
		// Проверяем, заканчивается ли строка на ":" (пустая строка после ":")
		if (substr($line, -1) === ':') {
			// Это не ошибка, а просто пустая строка после ":"
			if (defined("DEBUG") && DEBUG) {
				dlog("getLineTime: Ошибка получения времени из строки $line (попытка разобрать пустую строку оказалась неуспешной), возвращаю FALSE", 4, "DEBUG");
			}
			return false;
		}

		// Если строка не заканчивается на ":", это настоящая ошибка
		if (defined("DEBUG") && DEBUG) {
			dlog("getLineTime: Ошибка получения времени из строки $line (попытка разобрать оказалась неуспешной), возвращаю FALSE", 1, "ERROR");
		}
		return false;
	}

	// Извлекаем временную метку до ": "
	$_timestamp = substr($line, 0, $pos);

	// Парсим время
	$time = strtotime($_timestamp . " " . $timeZone);

	// Если парсинг неудачен
	if ($time === false) {
		if (defined("DEBUG") && DEBUG) {
			dlog("getLineTime: Ошибка получения времени из строки $line (попытка разобрать: $_timestamp оказалась неуспешной), возвращаю FALSE", 1, "ERROR");
		}
		return false;
	}

	return $time;
}
?>