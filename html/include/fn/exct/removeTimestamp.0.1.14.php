<?php

/**
 * Удаляет временную метку из начала строки лога SvxLink.
 * Метка определяется как: строка начинается не с пробела И
 * (содержит ": " ИЛИ заканчивается на ":")
 * Удаляет метку вместе с разделителем ": ".
 * @version 0.1.14
 * @date 2025.12.20
 * @filesource removeTimestamp.0.1.14.php
 * @param string $logLine Строка лога
 * @return string Строка без временной метки или исходная строка, если метка не найдена
 */
function removeTimestamp(string $logLine): string
{
	// Если строка начинается с пробела - не обрабатываем
	if (str_starts_with($logLine, ' ')) {
		return $logLine;
	}

	// Проверяем условия для временной метки
	$hasTimeStamp = false;

	// Вариант 1: строка содержит ": " (метка с последующим текстом)
	if (str_contains($logLine, ': ')) {
		$hasTimeStamp = true;
	}
	// Вариант 2: строка заканчивается на ":" (метка без последующего текста)
	elseif (str_ends_with($logLine, ':')) {
		$hasTimeStamp = true;
	}

	// Если временная метка найдена, удаляем её
	if ($hasTimeStamp) {
		// Находим позицию первого ": " или ":" в конце строки
		$pos = strpos($logLine, ': ');

		if ($pos !== false) {
			// Удаляем всё до ": " включительно
			return substr($logLine, $pos + 2);
		} elseif (str_ends_with($logLine, ':')) {
			// Удаляем всё до ":" включительно
			return substr($logLine, strrpos($logLine, ':') + 1);
		}
	}

	// Возвращаем исходную строку, если метка не соответствует условиям
	return $logLine;
}
?>