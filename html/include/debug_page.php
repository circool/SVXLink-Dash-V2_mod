<?php

/**
 * @filesource /include/exct/debug_page.2.2.php
 * @brief Блок отладки с уровнями детализации
 * @description Выводит переменные среды с фильтрацией по уровнями и метрики производительности
 * @author vladimir@tsurkanenko
 * @date 2021-11-24
 * @version 2.2
 */


// === НАЧАЛО ИЗМЕРЕНИЙ ПРОИЗВОДИТЕЛЬНОСТИ ===
$perf_start = microtime(true);
$perf_memory_start = memory_get_usage(true);

// Используем уже существующие константы, если они определены
// Если не определены - создаем стандартные значения
if (!defined('DEBUG_NONE')) {
	define('DEBUG_NONE', 0);      // Только критичные переменные
}
if (!defined('DEBUG_BASIC')) {
	define('DEBUG_BASIC', 1);     // Базовые переменные (по умолчанию)
}
if (!defined('DEBUG_VERBOSE')) {
	define('DEBUG_VERBOSE', 2);   // Все переменные включая временные
}
if (!defined('DEBUG_FULL')) {
	define('DEBUG_FULL', 3);      // Полный вывод со всеми системными переменными
}

// Определяем текущий уровень отладки
// Сначала проверяем вашу существующую переменную $DEBUG_VERBOSE
if (isset($DEBUG_VERBOSE) && is_numeric($DEBUG_VERBOSE)) {
	$debug_level = (int)$DEBUG_VERBOSE;
}
// Затем проверяем константу DEBUG_LEVEL, если она есть
elseif (defined('DEBUG_LEVEL')) {
	$debug_level = DEBUG_VERBOSE;
}
// Иначе используем уровень по умолчанию
else {
	$debug_level = DEBUG_BASIC;
}

// Если передан параметр в GET, используем его (для тестирования)
if (isset($_GET['debug_level']) && is_numeric($_GET['debug_level'])) {
	$debug_level = (int)$_GET['debug_level'];
}

// Сохраняем уровень в сессии для сохранения между страницами
if (isset($_POST['debug_level'])) {
	$_SESSION['debug_level'] = (int)$_POST['debug_level'];
	$debug_level = $_SESSION['debug_level'];
	// Обновляем также вашу переменную $DEBUG_VERBOSE
	$DEBUG_VERBOSE = $debug_level;
} elseif (isset($_SESSION['debug_level'])) {
	$debug_level = $_SESSION['debug_level'];
	// Обновляем также вашу переменную $DEBUG_VERBOSE
	$DEBUG_VERBOSE = $debug_level;
}

// === ФУНКЦИИ ДЛЯ ТЕСТИРОВАНИЯ ПРОИЗВОДИТЕЛЬНОСТИ ===

/**
 * Тестирование времени выполнения функций
 */
function benchmarkFunction($functionName, $iterations = 5)
{
	if (!function_exists($functionName)) {
		return ['error' => "Function $functionName not found"];
	}

	$times = [];
	$memories = [];

	for ($i = 0; $i < $iterations; $i++) {
		$mem_start = memory_get_usage(true);
		$time_start = microtime(true);

		// Вызываем функцию
		call_user_func($functionName);

		$times[] = microtime(true) - $time_start;
		$memories[] = memory_get_usage(true) - $mem_start;
	}

	// Убираем первый результат (может быть медленнее из-за кэша)
	if (count($times) > 1) {
		array_shift($times);
		array_shift($memories);
	}

	$avg_time = array_sum($times) / count($times);
	$avg_memory = array_sum($memories) / count($memories);
	$max_time = max($times);

	return [
		'avg_time_ms' => round($avg_time * 1000, 2),
		'max_time_ms' => round($max_time * 1000, 2),
		'memory_bytes' => round($avg_memory),
		'calls_per_second' => round(1 / $avg_time, 1),
		'iterations' => count($times)
	];
}



function testUpdateFunctions()
{
	$results = [];
	
	
	// @bookmark Функции к тесту
	// Тестируем getActualStatus если существует
	if (function_exists('getActualStatus')) {
		// Тестируем getActualStatus()
		$results['getActualStatus'] = benchmarkFunction('getActualStatus', 1);
	}

	if (function_exists('getConfig')) {
		// Тестируем getConfig()
		$results['getConfig'] = benchmarkFunction('getConfig', 1);
	}

	// if (function_exists('getServiceStatusLogLine')) {
	// 	// Тестируем getServiceStatusLogLine()
	// 	$results['getServiceStatusLogLine'] = benchmarkFunction('getServiceStatusLogLine', 1);
	// }

	return $results;
}

/**
 * Оценка производительности функции
 */
function evaluatePerformance($avg_time_ms, $function_type = 'generic')
{
	$thresholds = [
		'updateRadioStatusSimple' => ['good' => 10, 'warning' => 30, 'critical' => 100],
		'updateSystemStatus' => ['good' => 50, 'warning' => 150, 'critical' => 500],
		'saveRadioData' => ['good' => 5, 'warning' => 15, 'critical' => 50],
		'generic' => ['good' => 20, 'warning' => 100, 'critical' => 500]
	];

	$threshold = $thresholds[$function_type] ?? $thresholds['generic'];

	if ($avg_time_ms < $threshold['good']) {
		return ['status' => 'good', 'message' => 'Fast'];
	} elseif ($avg_time_ms < $threshold['warning']) {
		return ['status' => 'warning', 'message' => 'Acceptable'];
	} else {
		return ['status' => 'critical', 'message' => 'Slow - needs optimization'];
	}
}

// === МЕТРИКИ ПРОИЗВОДИТЕЛЬНОСТИ ===
$perf_metrics = [];

// 1. Основные метрики PHP
$perf_metrics[] = [
	'parameter' => 'PHP Version',
	'value' => PHP_VERSION,
	'recommendation' => checkPhpVersion(PHP_VERSION),
	'status' => 'php'
];

$perf_metrics[] = [
	'parameter' => 'PHP Memory Limit',
	'value' => ini_get('memory_limit'),
	'recommendation' => checkMemoryLimit(ini_get('memory_limit')),
	'status' => 'memory'
];

$perf_metrics[] = [
	'parameter' => 'Max Execution Time',
	'value' => ini_get('max_execution_time') . ' sec',
	'recommendation' => (ini_get('max_execution_time') >= 30 ? 'OK' : 'Consider increasing to 30+ sec'),
	'status' => 'time'
];

// 2. Метрики сервера
$load = sys_getloadavg();
$perf_metrics[] = [
	'parameter' => 'Server Load (1/5/15 min)',
	'value' => sprintf('%.2f / %.2f / %.2f', $load[0], $load[1], $load[2]),
	'recommendation' => checkServerLoad($load),
	'status' => 'load'
];

$perf_metrics[] = [
	'parameter' => 'Free Disk Space',
	'value' => formatBytes(disk_free_space('/')),
	'recommendation' => checkDiskSpace(disk_free_space('/'), disk_total_space('/')),
	'status' => 'disk'
];

// 3. Метрики приложения
$perf_metrics[] = [
	'parameter' => 'Session Handler',
	'value' => ini_get('session.save_handler'),
	'recommendation' => (ini_get('session.save_handler') === 'redis' ? 'OK (Redis)' : 'Consider using Redis for sessions'),
	'status' => 'session'
];

// 4. Тестирование производительности функций
$function_tests = testUpdateFunctions();

foreach ($function_tests as $func_name => $test_result) {
	if (isset($test_result['error'])) {
		$perf_metrics[] = [
			'parameter' => "Function: $func_name",
			'value' => 'Not available',
			'recommendation' => $test_result['error'],
			'status' => 'error'
		];
		continue;
	}

	$eval = evaluatePerformance($test_result['avg_time_ms'], $func_name);

	$perf_metrics[] = [
		'parameter' => "Function: $func_name",
		'value' => sprintf(
			"%.2f ms (max: %.2f ms, %d iter)",
			$test_result['avg_time_ms'],
			$test_result['max_time_ms'],
			$test_result['iterations']
		),
		'recommendation' => sprintf(
			"%s - %d calls/sec, Memory: %s",
			$eval['message'],
			$test_result['calls_per_second'],
			formatBytes($test_result['memory_bytes'])
		),
		'status' => $eval['status']
	];
}

// 5. Метрики запроса
$perf_end = microtime(true);
$perf_execution_time = round(($perf_end - $perf_start) * 1000, 2);
$perf_eval = evaluatePerformance($perf_execution_time, 'generic');

$perf_metrics[] = [
	'parameter' => 'Script Execution Time (Total)',
	'value' => $perf_execution_time . ' ms',
	'recommendation' => $perf_eval['message'],
	'status' => $perf_eval['status']
];

$perf_memory_end = memory_get_peak_usage(true);
$perf_memory_used = $perf_memory_end - $perf_memory_start;
$perf_metrics[] = [
	'parameter' => 'Memory Usage (This Script)',
	'value' => formatBytes($perf_memory_used),
	'recommendation' => ($perf_memory_used < 1024 * 1024 ? 'Good (<1MB)' : ($perf_memory_used < 10 * 1024 * 1024 ? 'OK (<10MB)' : 'High, check for memory leaks')),
	'status' => ($perf_memory_used < 10 * 1024 * 1024 ? 'good' : 'warning')
];

// 6. Метрики сессии
$session_size = strlen(session_encode());
$perf_metrics[] = [
	'parameter' => 'Session Size',
	'value' => formatBytes($session_size),
	'recommendation' => ($session_size < 1024 ? 'Small session' : ($session_size < 10240 ? 'OK' : 'Large session, consider reducing data')),
	'status' => ($session_size < 10240 ? 'good' : 'warning')
];

// 7. Метрики для Raspberry Pi
if (php_uname('m') === 'armv7l' || php_uname('m') === 'aarch64') {
	$cpu_temp = getCpuTemperature();
	$temp_status = checkCpuTemperature($cpu_temp);

	$perf_metrics[] = [
		'parameter' => 'CPU Temperature',
		'value' => $cpu_temp !== false ? $cpu_temp . '°C' : 'N/A',
		'recommendation' => $temp_status,
		'status' => ($cpu_temp !== false && $cpu_temp < 70 ? 'good' : 'warning')
	];

	// Рекомендации для RPi на основе тестов производительности
	if (isset($function_tests['updateRadioStatusSimple'])) {
		$radio_time = $function_tests['updateRadioStatusSimple']['avg_time_ms'];
		$recommended_interval = $radio_time < 10 ? 1 : ($radio_time < 30 ? 2 : 3);

		$perf_metrics[] = [
			'parameter' => 'Recommended Radio Update Interval',
			'value' => $recommended_interval . ' second(s)',
			'recommendation' => 'Based on current performance: ' . $radio_time . ' ms per call',
			'status' => 'good'
		];
	}

	if (isset($function_tests['updateSystemStatus'])) {
		$system_time = $function_tests['updateSystemStatus']['avg_time_ms'];
		$recommended_interval = $system_time < 50 ? 3 : ($system_time < 150 ? 5 : 10);

		$perf_metrics[] = [
			'parameter' => 'Recommended System Update Interval',
			'value' => $recommended_interval . ' second(s)',
			'recommendation' => 'Based on current performance: ' . $system_time . ' ms per call',
			'status' => 'good'
		];
	}

	$perf_metrics[] = [
		'parameter' => 'Platform',
		'value' => 'Raspberry Pi (' . php_uname('m') . ')',
		'recommendation' => 'Consider optimizing update intervals for RPi',
		'status' => 'neutral'
	];
}

// === ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ДЛЯ МЕТРИК ===

function checkPhpVersion($version)
{
	if (version_compare($version, '8.0', '>=')) {
		return 'Modern version';
	} elseif (version_compare($version, '7.4', '>=')) {
		return 'OK, but consider upgrading to 8.0+';
	} else {
		return 'Consider upgrading to PHP 8.0+';
	}
}

function checkMemoryLimit($limit)
{
	$bytes = convertToBytes($limit);
	if ($bytes >= 256 * 1024 * 1024) { // 256MB
		return 'More than enough';
	} elseif ($bytes >= 128 * 1024 * 1024) { // 128MB
		return 'OK for most applications';
	} else {
		return 'Consider increasing memory_limit';
	}
}

function checkServerLoad($load)
{
	$cpu_cores = (int)shell_exec('nproc') ?? 4;
	$load_percent = ($load[0] / $cpu_cores) * 100;

	if ($load_percent < 50) {
		return 'Low load';
	} elseif ($load_percent < 80) {
		return 'Moderate load';
	} elseif ($load_percent < 100) {
		return 'High load';
	} else {
		return 'Critical load - consider optimizing';
	}
}

function checkDiskSpace($free, $total)
{
	if ($total == 0) return 'Unable to check';

	$percent_free = ($free / $total) * 100;

	if ($percent_free > 20) {
		return 'OK (>20% free)';
	} elseif ($percent_free > 10) {
		return 'Low (<20% free)';
	} else {
		return 'Critical (<10% free)';
	}
}

function checkCpuTemperature($temp)
{
	if ($temp === false) return 'Unable to read temperature';

	if ($temp < 60) {
		return 'Good (<60°C)';
	} elseif ($temp < 70) {
		return 'Warm (60-70°C)';
	} elseif ($temp < 80) {
		return 'Hot (70-80°C)';
	} else {
		return 'Critical (>80°C) - Consider cooling';
	}
}

function getCpuTemperature()
{
	$paths = [
		'/sys/class/thermal/thermal_zone0/temp',
		'/sys/class/hwmon/hwmon0/temp1_input',
		'/sys/class/hwmon/hwmon1/temp1_input'
	];

	foreach ($paths as $path) {
		if (file_exists($path)) {
			$temp = trim(file_get_contents($path));
			if (is_numeric($temp)) {
				return round($temp / 1000, 1);
			}
		}
	}

	return false;
}

function formatBytes($bytes, $precision = 2)
{
	$units = ['B', 'KB', 'MB', 'GB', 'TB'];
	$bytes = max($bytes, 0);
	$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
	$pow = min($pow, count($units) - 1);
	$bytes /= pow(1024, $pow);

	return round($bytes, $precision) . ' ' . $units[$pow];
}

function convertToBytes($value)
{
	$value = trim($value);
	$last = strtolower($value[strlen($value) - 1]);
	$value = (int)$value;

	switch ($last) {
		case 'g':
			$value *= 1024;
		case 'm':
			$value *= 1024;
		case 'k':
			$value *= 1024;
	}

	return $value;
}

// === ПРОДОЛЖЕНИЕ ОРИГИНАЛЬНОГО КОДА ===

// Параметр для ограничения длины выводимых значений (в символах)
$MAX_VALUE_LENGTH = 80;

// Базовый набор переменных, которые всегда показываются
$always_show = [
	'debug_level',      // Текущий уровень отладки
	'DEBUG_VERBOSE',    // Ваша переменная отладки
	'PHP_SELF',         // Текущий скрипт
	'REQUEST_METHOD',   // Метод запроса
	'page_title',       // Заголовок страницы (если есть)
	'error',            // Ошибки (если есть)
	'success',          // Успешные сообщения (если есть)
	'_SESSION',
];

// @bookmark Переменные, которые скрываются на уровнях ниже DEBUG_FULL
$system_vars = [
	'_SERVER',
	'_FILES',
	'_COOKIE',
	'_GET',
	'_POST',
	'GLOBALS',
	'tg_db',
	'lang_db',
	'_msg',
	'translations',
	'ru_translations',
	'loadedTranslations',
	'translations_cache',
	'translations_loaded',
	'always_show',

];

// Получаем все определенные переменные
$allVariables = get_defined_vars();

/**
 * Функция для проверки уровня переменной
 * @param string $var_name Имя переменной
 * @param mixed $value Значение переменной
 * @return int Уровень переменной (DEBUG_NONE, DEBUG_BASIC, DEBUG_VERBOSE, DEBUG_FULL)
 */
function getVariableDebugLevel($var_name, $value)
{
	global $always_show, $system_vars;

	// Всегда показываемые переменные
	if (in_array($var_name, $always_show)) {
		return DEBUG_BASIC;
	}

	// Системные переменные показываем только на DEBUG_FULL
	if (in_array($var_name, $system_vars)) {
		return DEBUG_FULL;
	}

	// Определяем уровень по имени переменной
	$lower_name = strtolower($var_name);

	// Переменные с префиксами temp_, tmp_, _ показываем только на DEBUG_VERBOSE и выше
	if (preg_match('/^(temp_|tmp_|_|test_)/', $lower_name)) {
		return DEBUG_VERBOSE;
	}

	// Сессионные переменные показываем на DEBUG_VERBOSE
	if (isset($_SESSION[$var_name])) {
		return DEBUG_VERBOSE;
	}

	// Массивы и объекты показываем на DEBUG_BASIC
	if (is_array($value) || is_object($value)) {
		return DEBUG_BASIC;
	}

	// Остальные переменные показываем на DEBUG_BASIC
	return DEBUG_BASIC;
}

/**
 * Помощник для пометки переменной определенным уровнем отладки
 * Использование в коде: debug_var($myVar, DEBUG_VERBOSE);
 */
function debug_var($value, $level = DEBUG_BASIC, $name = null)
{
	$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
	$caller = $backtrace[1] ?? $backtrace[0];
	$line = $caller['line'];
	$file = basename($caller['file']);

	// Если имя не указано, генерируем его
	if ($name === null) {
		$name = "debug_var_line_{$line}";
	}

	// Сохраняем переменную с метаданными
	$GLOBALS['_debug_vars'][$name] = [
		'value' => $value,
		'level' => $level,
		'file' => $file,
		'line' => $line,
		'time' => microtime(true)
	];

	return $value;
}

/**
 * Конвертация значения в строку для отображения
 */
function convertToString($value, $maxLength = 80)
{
	if ($value === null) {
		return 'null';
	} elseif ($value === true) {
		return 'true';
	} elseif ($value === false) {
		return 'false';
	} elseif (is_string($value)) {
		$value = htmlspecialchars($value);
		if (strlen($value) > $maxLength) {
			$value = chunk_split($value, $maxLength, " ↵<br>");
		}
		return $value;
	} elseif (is_numeric($value)) {
		$value = (string)$value;
		if (strlen($value) > $maxLength) {
			$value = chunk_split($value, $maxLength, " ↵<br>");
		}
		return $value;
	} elseif (is_object($value)) {
		if ($value instanceof DateTime) {
			return $value->format('Y-m-d H:i:s');
		} else {
			return 'Object: ' . get_class($value);
		}
	} elseif (is_resource($value)) {
		return 'Resource: ' . get_resource_type($value);
	} else {
		$value = strval($value);
		if (strlen($value) > $maxLength) {
			$value = chunk_split($value, $maxLength, " ↵<br>");
		}
		return $value;
	}
}

/**
 * Отображение массива в виде таблицы
 */
function displayArrayAsTable($array, $arrayName, $maxLength = 80, $level = 0)
{
	if (count($array) == 0) {
		return "Массив пуст.\n";
	}

	$html = "<div class='divTable'>";
	$html .= "<div class='divTableHead'><b>Массив: $arrayName</b> (элементов: " . count($array) . ")</div>";
	$html .= "<table class='cell_content'>";
	$html .= '<tr><th style ="width: 10%;">Ключ</th><th>Значение</th></tr>';

	foreach ($array as $key => $value) {
		$html .= "<tr>";
		$html .= "<td class='divTableCell cell_content'>" . convertToString($key, $maxLength) . "</td>";
		$html .= "<td class='divTableCell cell_content'>";

		if (is_array($value)) {
			$html .= displayArrayAsTable($value, "[$key]", $maxLength, $level + 1);
		} else {
			$html .= convertToString($value, $maxLength);
		}

		$html .= "</td>";
		$html .= "</tr>";
	}

	$html .= "</table>";
	$html .= "</div>";
	return $html;
}

/**
 * Получение названия уровня отладки
 */
function getDebugLevelName($level)
{
	// Сначала проверяем ваши существующие константы
	if (defined('DEBUG_NONE') && $level == DEBUG_NONE) return 'NONE';
	if (defined('DEBUG_BASIC') && $level == DEBUG_BASIC) return 'BASIC';
	if (defined('DEBUG_VERBOSE') && $level == DEBUG_VERBOSE) return 'VERBOSE';
	if (defined('DEBUG_FULL') && $level == DEBUG_FULL) return 'FULL';

	// Если константы не определены, используем стандартные названия
	$levels = [
		0 => 'NONE - Без отладки',
		1 => 'BASIC - Базовые переменные',
		2 => 'VERBOSE - Все переменные',
		3 => 'FULL - Полный вывод'
	];
	return $levels[$level] ?? "Неизвестный уровень ($level)";
}

// Добавляем текущий уровень отладки в массив переменных для отображения
$allVariables['debug_level'] = $debug_level;
if (isset($DEBUG_VERBOSE)) {
	$allVariables['DEBUG_VERBOSE'] = $DEBUG_VERBOSE;
}
// @bookmark HTML
?>

<div class="divTable">
	<div class="divTableHead">Отладка: <?php echo basename(__FILE__) ?></div>

	<div class="grid-container" style="display: inline-grid; grid-template-columns: auto 150px 40px; padding: 1px; grid-column-gap: 5px;">
		<div class="grid-item activity" style="padding: 10px 0 0 20px; color: #ffffff;" title="">Уровень отладки:</div>
		<div class="grid-item">
			<form method="post" style="margin: 5px 0;">
				<select name="debug_level" onchange="this.form.submit()" style="width: 100%; padding: 3px;">
					<option value="<?= DEBUG_NONE ?>" <?= $debug_level == DEBUG_NONE ? 'selected' : '' ?>>NONE - Без отладки</option>
					<option value="<?= DEBUG_BASIC ?>" <?= $debug_level == DEBUG_BASIC ? 'selected' : '' ?>>BASIC - Базовые переменные</option>
					<option value="<?= DEBUG_VERBOSE ?>" <?= $debug_level == DEBUG_VERBOSE ? 'selected' : '' ?>>VERBOSE - Все переменные</option>
					<option value="<?= DEBUG_FULL ?>" <?= $debug_level == DEBUG_FULL ? 'selected' : '' ?>>FULL - Полный вывод</option>
				</select>
			</form>
		</div>
		<div class="grid-item">
			<div style="padding-top:6px;">
				<input id="toggle-debug-visible" class="toggle toggle-round-flat" type="checkbox" onchange="toggleDebugVisibility()" checked>
				<label for="toggle-debug-visible"></label>
			</div>
		</div>
	</div>
</div>

<div>
	<div id="debugContent" class="div-Table">
		<!-- СЕКЦИЯ ПРОИЗВОДИТЕЛЬНОСТИ -->
		<div id="performanceSection" class="divTable">
			<!-- <div class="divTableHead">Метрики производительности</div> -->
			<div class="divTableBody">
				<div class="divTableRow center">
					<div style="width: 30%;" class="divTableHeadCell">Параметр</div>
					<div style="width: 30%;" class="divTableHeadCell">Значение</div>
					<div style="width: 40%;" class="divTableHeadCell">Вывод / Рекомендация</div>
				</div>
				<?php foreach ($perf_metrics as $metric): ?>
					<div class="divTableRow center">
						<div class="divTableCell cell_content middle"><?php echo htmlspecialchars($metric['parameter']); ?></div>
						<div class="divTableCell cell_content middle"><?php echo htmlspecialchars($metric['value']); ?></div>
						<div class="divTableCell cell_content middle">
							<?php
							$recommendation = $metric['recommendation'];
							$status = $metric['status'] ?? 'neutral';
							$color_class = 'perf-' . $status;
							?>
							<span class="perf-recommendation <?php echo $color_class; ?>">
								<?php echo htmlspecialchars($recommendation); ?>
							</span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<!-- Информация о тестировании -->
		<div id="performanceSection" class="divTable">
			<div class="divTableBody">
				<div class="divTableRow center">
					<div class="divTableCell cell_content middle" colspan="3" style="text-align: center; padding: 10px; background: #f5f5f5;">
						<small>
							<strong>Тестирование функций:</strong>
							Функции тестируются при каждой загрузке страницы. Первое выполнение может быть медленнее из-за кэширования. Рекомендации основаны на среднем времени выполнения.
						</small>
					</div>
				</div>
			</div>
		</div>
		<div id="variableSection" class="divTable">
			<div class="divTableBody">
				<div class="divTableRow center">
					<div style="width: 8%;" class="divTableHeadCell">Переменная</div>
					<div style="width: 6%;" class="divTableHeadCell">Уровень</div>
					<div class="divTableHeadCell">Значение</div>
				</div>

				<?php
				// Сначала добавляем отладочные переменные, если они есть
				if (isset($allVariables['_debug_vars']) && is_array($allVariables['_debug_vars'])) {
					foreach ($allVariables['_debug_vars'] as $debug_name => $debug_data) {
						$var_level = $debug_data['level'];

						// Пропускаем переменные, уровень которых выше текущего
						if ($var_level > $debug_level) {
							continue;
						}

						echo '<div class="divTableRow center">';
						echo '<div class="divTableCell cell_content middle">';
						echo convertToString($debug_name, $MAX_VALUE_LENGTH);
						echo $debug_data['file'] . ':' . $debug_data['line'];
						echo '</div>';
						echo '<div class="divTableCell cell_content middle">';
						echo '<span class="debug-level-badge level-' . $var_level . '">' . getDebugLevelName($var_level) . '</span>';
						echo '</div>';
						echo '<div class="divTableCell cell_content middle">';

						if (is_array($debug_data['value'])) {
							echo displayArrayAsTable($debug_data['value'], $debug_name, $MAX_VALUE_LENGTH);
						} else {
							echo convertToString($debug_data['value'], $MAX_VALUE_LENGTH);
						}

						echo '</div></div>';
					}
					// Удаляем отладочные переменные из основного списка
					unset($allVariables['_debug_vars']);
				}

				// Затем обрабатываем остальные переменные
				foreach ($allVariables as $key => $variable) {
					// Пропускаем служебные переменные
					$skip_vars = [
						'allVariables',
						'debug_level',
						//'always_show',
						'system_vars',
						'MAX_VALUE_LENGTH',
						'perf_metrics',
						'perf_start',
						'perf_end',
						'perf_memory_start',
						'perf_memory_end',
						'perf_execution_time',
						'perf_memory_used',
						'load',
						'function_tests',
						'cpu_temp',
						'session_size',
						'perf_eval'
					];

					if (in_array($key, $skip_vars)) {
						continue;
					}

					// Определяем уровень переменной
					$var_level = getVariableDebugLevel($key, $variable);

					// Пропускаем переменные, уровень которых выше текущего
					if ($var_level > $debug_level) {
						continue;
					}

					echo '<div class="divTableRow center">';
					echo '<div class="divTableCell cell_content middle">' . convertToString($key, $MAX_VALUE_LENGTH) . '</div>';
					echo '<div class="divTableCell cell_content middle">';
					echo '<span class="debug-level-badge level-' . $var_level . '">' . getDebugLevelName($var_level) . '</span>';
					echo '</div>';
					echo '<div class="divTableCell cell_content middle">';

					if (is_array($variable)) {
						// Для системных переменных на DEBUG_FULL показываем ограниченную вложенность
						if (in_array($key, $system_vars) && $debug_level == DEBUG_FULL) {
							echo "Массив (" . count($variable) . " элементов)";
							if (count($variable) > 0) {
								echo '<br><small>Показаны только ключи первого уровня</small>';
								echo '<ul style="text-align: left; margin: 5px 0 0 20px;">';
								$count = 0;
								foreach ($variable as $sub_key => $sub_value) {
									if ($count++ >= 10) {
										echo '<li>... и ещё ' . (count($variable) - 10) . ' элементов</li>';
										break;
									}
									echo '<li>' . convertToString($sub_key, 50) . '</li>';
								}
								echo '</ul>';
							}
						} else {
							echo displayArrayAsTable($variable, $key, $MAX_VALUE_LENGTH);
						}
					} else {
						echo convertToString($variable, $MAX_VALUE_LENGTH);
					}

					echo '</div></div>';
				}
				?>
			</div>
		</div>
	</div>
</div>

<style>
	.debug-level-badge {
		display: inline-block;
		padding: 2px 8px;
		border-radius: 3px;
		font-size: 11px;
		font-weight: bold;
	}

	.debug-level-badge.level-0 {
		background: #999;
		color: white;
	}

	.debug-level-badge.level-1 {
		background: #4CAF50;
		color: black;
	}

	.debug-level-badge.level-2 {
		background: #2196F3;
		color: white;
	}

	.debug-level-badge.level-3 {
		background: #f44336;
		color: white;
	}

	.perf-recommendation {
		display: inline-block;
		padding: 3px 8px;
		border-radius: 3px;
		font-weight: bold;
	}

	.perf-good {
		background: #4CAF50;
		color: black;
	}

	.perf-warning {
		background: #FF9800;
		color: black;
	}

	.perf-critical {
		background: #f44336;
		color: white;
	}

	.perf-neutral {
		background: #333333;
		color: white;
	}

	.perf-error {
		background: #9C27B0;
		color: white;
	}

	.perf-php {
		background: #795548;
		color: white;
	}
</style>
<script>
	function toggleDebugVisibility() {
		const block = document.getElementById('debugContent');
		const toggle = document.getElementById('toggle-debug-visible');
		const isVisible = toggle.checked;

		if (isVisible) {
			block.classList.remove('hidden');
		} else {
			block.classList.add('hidden');
		}

		localStorage.setItem('debugBlockVisible', JSON.stringify(isVisible));
	}

	function initDebugBlock() {
		const block = document.getElementById('debugContent');
		const toggle = document.getElementById('toggle-debug-visible');

		if (!block || !toggle) return;

		const savedState = localStorage.getItem('debugBlockVisible');
		const isVisible = savedState !== null ? JSON.parse(savedState) : false;

		toggle.checked = isVisible;

		if (isVisible) {
			block.classList.remove('hidden');
		} else {
			block.classList.add('hidden');
		}
	}

	initDebugBlock();
</script>