<?php

/** Парсит XML-теги в ассоциативный массив
 *
 * @namespace Функции отображения активности с указанием длительности
 * @filesource parseXmlTags.0.1.5.php
 * @date 2025.11.26
 * @version 0.1.5
 * @note Новое в 0.1.5
 * Исправлена ошибка в регулярке
 * 
 */
function parseXmlTags(string $_xml_data) : array
{
	$ver = 'parseXmlTags 0.1.5';
	if (defined("DEBUG") && DEBUG) dlog("$ver: Получил строку $_xml_data", 4, "DEBUG");
	$result = [];

	// Ищем позицию первого открывающего тега
	$start_pos = strpos($_xml_data, '<');
	if ($start_pos !== false) {
		// Берем только часть строки начиная с первого тега
		$_xml_data = substr($_xml_data, $start_pos);
	}

	// Регулярное выражение для поиска XML-тегов
	$pattern = '/<([A-Za-z0-9_]+)>(.*?)<\/\1>/s';

	if (preg_match_all($pattern, $_xml_data, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $match) {
			$tag_name = $match[1];
			$payload_content = $match[2];
			$result[$tag_name] = $payload_content;
		}
	}
	
	if (defined("DEBUG") && DEBUG) {
		$resultCount = count($result);
		dlog("$ver: Возвращаю $resultCount элементов", 4, "DEBUG");
		}
	return $result;
}
