<?php

/**
 * @param string $key 
 * @param string $default 
 * @param bool $escape_html 
 * 
 * @return string 
 */
function getTranslation(string $key, string $default = '', bool $escape_html = true): string
{
	if (trim($key) === '') {
		$result = $default ?: $key;
		return $escape_html ? htmlspecialchars($result, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $result;
	}

	$lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';

	$langFile = $_SERVER["DOCUMENT_ROOT"] . "/include/languages/{$lang}.php";

	if (file_exists($langFile)) {
		$translations = include $langFile;

		if (is_array($translations) && isset($translations[$key])) {
			$result = (string)$translations[$key];
			return $escape_html ? htmlspecialchars($result, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $result;
		}
	}

	$result = $default ?: $key;
	return $escape_html ? htmlspecialchars($result, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $result;
}
