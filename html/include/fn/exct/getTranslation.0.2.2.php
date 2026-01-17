<?php

/**
 * Функция для получения перевода
 * 
 * @param string $key Ключ перевода (английский текст или идентификатор)
 * @param string $default Значение по умолчанию, если перевод не найден
 * @param bool $escape_html Флаг экранирования HTML-символов
 * 
 * @return string Переведенный текст или оригинал
 */
function getTranslation(string $key, string $default = '', bool $escape_html = true): string
{
	// Если ключ пустой - возвращаем значение по умолчанию или сам ключ
	if (trim($key) === '') {
		$result = $default ?: $key;
		return $escape_html ? htmlspecialchars($result, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $result;
	}

	// Определяем язык из заголовка браузера
	$lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';

	// Путь к файлу перевода
	$langFile = $_SERVER["DOCUMENT_ROOT"] . "/include/languages/{$lang}.php";

	// Если файл существует - пытаемся загрузить переводы
	if (file_exists($langFile)) {
		$translations = include $langFile;

		if (is_array($translations) && isset($translations[$key])) {
			$result = (string)$translations[$key];
			return $escape_html ? htmlspecialchars($result, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $result;
		}
	}

	// Если перевод не найден - возвращаем значение по умолчанию или оригинальный ключ
	$result = $default ?: $key;
	return $escape_html ? htmlspecialchars($result, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $result;
}
