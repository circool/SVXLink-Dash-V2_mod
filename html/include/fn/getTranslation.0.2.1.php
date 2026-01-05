<?php

/**
 * Функция для получения перевода с кэшированием в сессии
 * 
 * @filesource getTranslation.0.2.1.php
 * @version 0.2.1
 * @date 2025.12.21
 * @author vladimir@tsurkanenko.ru
 * @deprecated see 0.1.1
 * 
 * @note Новое в 0.2.1:
 * - Добавлено кэширование переводов в сессии между запросами
 * - Файл переводов читается только один раз за сессию
 * - Оптимизирована производительность для частых AJAX-запросов
 * 
 * @param string $key Ключ перевода (английский текст или идентификатор)
 * @param string $default Значение по умолчанию, если перевод не найден (необязательный)
 * @param bool $escape_html Флаг экранирования HTML-символов (по умолчанию true)
 * 
 * @return string Переведенный текст или значение по умолчанию
 * 
 * @throws InvalidArgumentException Если ключ не является непустой строкой
 * 
 * @example getTranslation('Hello', 'Привет') // Вернет перевод или 'Привет'
 * @example getTranslation('Hello', 'Привет', false) // Без экранирования HTML
 */
function getTranslation(string $key, string $default = '', bool $escape_html = true): string
{
	// Проверка корректности ключа
	if (trim($key) === '') {
		$result = $default ?: $key;
		return $escape_html ? htmlspecialchars($result, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $result;
	}

	// Определяем язык из сессии
	$lang = $_SESSION['dashboard_lang'] ?? 'en';

	// Уникальный ключ кэша для комбинации язык+ключ
	$cacheKey = 'trans_' . $lang . '_' . md5($key);

	// Инициализация кэша переводов в сессии если нужно
	if (!isset($_SESSION['translations_cache'])) {
		$_SESSION['translations_cache'] = [];
	}
	if (!isset($_SESSION['translations_loaded'])) {
		$_SESSION['translations_loaded'] = [];
	}

	// Проверяем кэш переводов в сессии
	if (isset($_SESSION['translations_cache'][$cacheKey])) {
		$result = $_SESSION['translations_cache'][$cacheKey];
		return $escape_html ? htmlspecialchars($result, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $result;
	}

	// Если переводы для этого языка еще не загружены - загружаем и кэшируем
	if (!isset($_SESSION['translations_loaded'][$lang]) || $_SESSION['translations_loaded'][$lang] !== true) {
		$langFile = $_SERVER["DOCUMENT_ROOT"] . "/include/languages/{$lang}.php";

		if (file_exists($langFile)) {
			$loadedTranslations = include $langFile;

			if (is_array($loadedTranslations)) {
				// Кэшируем все переводы для этого языка
				foreach ($loadedTranslations as $transKey => $value) {
					$transCacheKey = 'trans_' . $lang . '_' . md5($transKey);
					$_SESSION['translations_cache'][$transCacheKey] = (string)$value;
				}

				$_SESSION['translations_loaded'][$lang] = true;

				if (defined("DEBUG") && DEBUG) {
					dlog("getTranslation: Загружено и закэшировано " .
						count($loadedTranslations) . " переводов для языка '$lang'", 4, "DEBUG");
				}
			} else {
				$_SESSION['translations_loaded'][$lang] = false;
				if (defined("DEBUG") && DEBUG) {
					dlog("getTranslation: Файл переводов '$langFile' не содержит массив", 2, "WARNING");
				}
			}
		} else {
			$_SESSION['translations_loaded'][$lang] = false;
			if (defined("DEBUG") && DEBUG) {
				dlog("getTranslation: Файл переводов не найден: $langFile", 2, "WARNING");
			}
		}
	}

	// Пробуем найти перевод после загрузки
	if (isset($_SESSION['translations_cache'][$cacheKey])) {
		$result = $_SESSION['translations_cache'][$cacheKey];
		return $escape_html ? htmlspecialchars($result, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $result;
	}

	// Перевод не найден - возвращаем значение по умолчанию или оригинальный ключ
	$result = $default ?: $key;

	// Кэшируем и негативный результат (значение по умолчанию), чтобы не искать каждый раз
	$_SESSION['translations_cache'][$cacheKey] = $result;

	return $escape_html ? htmlspecialchars($result, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $result;
}

/**
 * Вспомогательная функция для сброса кэша переводов
 * Полезно при изменении файлов переводов во время работы
 * 
 * @param string|null $lang Язык для очистки (null - все языки)
 */
function clearTranslationCache(?string $lang = null): void
{
	if ($lang === null) {
		unset($_SESSION['translations_cache'], $_SESSION['translations_loaded']);
	} else {
		if (isset($_SESSION['translations_cache'])) {
			$prefix = 'trans_' . $lang . '_';
			foreach (array_keys($_SESSION['translations_cache']) as $key) {
				if (strpos($key, $prefix) === 0) {
					unset($_SESSION['translations_cache'][$key]);
				}
			}
		}
		if (isset($_SESSION['translations_loaded'][$lang])) {
			unset($_SESSION['translations_loaded'][$lang]);
		}
	}

	if (defined("DEBUG") && DEBUG) {
		dlog("Translation cache cleared" . ($lang ? " for language '$lang'" : ""), 4, "DEBUG");
	}
}

/**
 * Вспомогательная функция для получения статистики кэша переводов
 * 
 * @return array Статистика по кэшу
 */
function getTranslationCacheStats(): array
{
	$stats = [
		'total_cached' => 0,
		'languages_loaded' => [],
		'memory_usage' => 0
	];

	if (isset($_SESSION['translations_cache'])) {
		$stats['total_cached'] = count($_SESSION['translations_cache']);

		// Определяем языки по префиксам ключей
		$languages = [];
		foreach (array_keys($_SESSION['translations_cache']) as $key) {
			if (preg_match('/^trans_([a-z]+)_/', $key, $matches)) {
				$languages[$matches[1]] = true;
			}
		}
		$stats['languages_loaded'] = array_keys($languages);
	}

	if (isset($_SESSION['translations_loaded'])) {
		$stats['languages_loaded'] = array_unique(
			array_merge($stats['languages_loaded'], array_keys($_SESSION['translations_loaded']))
		);
	}

	// Примерная оценка использования памяти
	$stats['memory_usage'] = function_exists('memory_get_usage') ?
		round(memory_get_usage(true) / 1024, 2) . ' KB' : 'unknown';

	return $stats;
}
