<?php

/**
 * Functions for working with the svxlink log
 * @filesource /include/fn/logTailer.php
 * @version 0.4.0.release
 * @author Vladimir Tsurkanenko <vladimir@tsurkanenko.ru>
 * @date 2026.01.18
 */

if (defined("DEBUG") && DEBUG && function_exists("dlog")) {
	include_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/dlog.php';
}


/** 
 * Returns the last N lines from the SVXLink log file
 * @filesource /include/fn/logTailer.php
 * @version 0.4.0
 * @param int $num_lines Number of lines to read from the end of the file
 * @return array|false Array of lines (without end-of-line characters) or false on error
 */
function getLogTail($num_lines)
{
	$ver = "getLogTail 0.4.0";
	
	if (!is_int($num_lines) || $num_lines <= 0) {
		error_log("$ver: Wrong param $num_lines");
		return false;
	}

	if (!defined('SVXLOGPATH') || !defined('SVXLOGPREFIX')) {
		error_log("$ver: Unset SVXLOGPATH or SVXLOGPREFIX");
		return false;
	}

	$logPath = SVXLOGPATH . SVXLOGPREFIX;
	if (!file_exists($logPath) || !is_readable($logPath)) {
		error_log("$ver: Not found $logPath");
		return false;
	}

	$output = `tail -n $num_lines $logPath 2>&1`;
	if ($output === null) {
		error_log("$ver: shell_exec return null");
		return false;
	}

	$lines = explode("\n", $output);
	if (end($lines) === '') {
		array_pop($lines);
	}

	foreach ($lines as $line) {
		$line = trim($line);
	}
	
	if (empty($lines) || (count($lines) === 1 && $lines[0] === '')) {
		return false;
	}

	return $lines;
}

/** 
 * Returns the number of lines after the last occurrence of the pattern in the log file
 * @version 0.4.2
 * @param string $pattern Substring to search for
 * @param int $analyze_lines Number of lines to analyze:
 * 	0 = analyze entire file
 * 	N > 0 = analyze only the last N lines
 * @return int|false Number of lines after the pattern or false if not found
 */
function countLogLines(string $pattern, int $analyze_lines = 0): int|false {
	
	$useCache = false;
	$cacheDuration = 1000;

	if (defined('USE_CACHE') && is_bool(USE_CACHE)) {
		$useCache = USE_CACHE;
	}

	if (defined('LOG_CACHE_TTL_MS') && is_int(LOG_CACHE_TTL_MS) && LOG_CACHE_TTL_MS > 0) {
		$cacheDuration = LOG_CACHE_TTL_MS;
	}

	if ($useCache && session_status() !== PHP_SESSION_ACTIVE) {
		$useCache = false;
	}

	$cacheKey = null;
	$currentTimeMs = null;

	if ($useCache) {
		$currentTimeMs = microtime(true) * 1000;
		$cacheKey = md5($pattern . '|' . $analyze_lines);

		if (!isset($_SESSION['countLogLines_cache'])) {
			$_SESSION['countLogLines_cache'] = [];
		}

		$cache = &$_SESSION['countLogLines_cache'];

		foreach ($cache as $key => $data) {
			if (
				isset($data['timestamp_ms']) &&
				($currentTimeMs - $data['timestamp_ms']) > $cacheDuration
			) {
				unset($cache[$key]);
			}
		}

		if (isset($cache[$cacheKey])) {
			$cacheData = $cache[$cacheKey];
			return $cacheData['result'];
		}
	}
		
	if (!is_string($pattern) || trim($pattern) === '') {
		error_log("countLogLines: Wrong searching pattern");
		return false;
	}

	if (!is_int($analyze_lines) || $analyze_lines < 0) {
		error_log("countLogLines: Wrong param");
		return false;
	}

	if (!defined('SVXLOGPATH') || !defined('SVXLOGPREFIX')) {
		error_log("countLogLines: Path not set");
		return false;
	}

	$logPath = SVXLOGPATH . SVXLOGPREFIX;

	if (!file_exists($logPath) || !is_readable($logPath)) {
		error_log("countLogLines: File not found");
		return false;
	}

	$shell_escaped_pattern = str_replace("'", "'\"'\"'", $pattern);
	$result = false; 

	if ($analyze_lines === 0) {

		$command = sprintf(
			"grep -a -F -n '%s' %s 2>/dev/null | tail -1 | cut -d: -f1",
			$shell_escaped_pattern,
			escapeshellarg($logPath)
		);

		$last_line = shell_exec($command);

		if ($last_line !== null && trim($last_line) !== '') {
			$last_line = trim($last_line);

			$total_lines_command = sprintf("wc -l < %s 2>/dev/null", escapeshellarg($logPath));
			$total_lines = shell_exec($total_lines_command);

			if ($total_lines !== null && is_numeric(trim($total_lines))) {
				$total_lines = (int)trim($total_lines);
				$result = $total_lines - (int)$last_line;
			}
		}
	} else {

		$command = sprintf(
			"tail -n %d %s 2>/dev/null | grep -a -F -n '%s' 2>/dev/null | tail -1 | cut -d: -f1",
			$analyze_lines,
			escapeshellarg($logPath),
			$shell_escaped_pattern
		);

		$last_line = shell_exec($command);

		if ($last_line !== null && trim($last_line) !== '') {
			$last_line = trim($last_line);
			$result = $analyze_lines - (int)$last_line;			
		}
	}

	if ($useCache && $cacheKey !== null && $currentTimeMs !== null) {
		$_SESSION['countLogLines_cache'][$cacheKey] = [
			'result' => $result,
			'timestamp_ms' => $currentTimeMs,
			'pattern' => $pattern,
			'analyze_lines' => $analyze_lines,
			'cached_at' => date('Y-m-d H:i:s')
		];
	}

	return $result;
}

/** 
 * Returns the last N lines from the SVXLink log file with filtering by conditions
 * @version 0.1.13
 * @param int $num_lines How many lines to return in the result
 * @param string|null $required_condition Optional AND condition
 * @param array $or_conditions Array of conditions for OR
 * @param int|null $search_limit How many lines to check (parsing depth)
 * @return array|false In case of no result, incorrect parameters, or error getting the log.
 */
function getLogTailFiltered($num_lines, $required_condition = null, $or_conditions = [], $search_limit = null) : array|false
{	
	$ver = 'getLogTailFiltered 0.1.13';
	if (!is_int($num_lines) || $num_lines <= 0) {
		error_log("$ver: Wrong quantity in 1 param in - $num_lines");
		return false;
	}

	if (!is_array($or_conditions)) {
		error_log("$ver: Wrong param - or_conditions");
		return false;
	}

	// search_limit
	$actual_search_limit = 100000;

	if ($search_limit !== null) {
		if (!is_numeric($search_limit)) {
			error_log("$ver: Wrong param - search_limit");
			return false;
		}

		$search_limit = (int)$search_limit;

		if ($search_limit == 0) {
			$actual_search_limit = 100000;			
		} else {
			$actual_search_limit = abs($search_limit);
		}
	}
	
	if (!defined('SVXLOGPATH') || !defined('SVXLOGPREFIX')) {
		error_log("$ver: SVXLOGPATH or SVXLOGPREFIX not set");
		return false;
	}

	$logPath = SVXLOGPATH . SVXLOGPREFIX;
	if (!file_exists($logPath) || !is_readable($logPath)) {
		error_log("$ver: File not found: $logPath");
		return false;
	}

	$has_required = false;

	if ($required_condition !== null && is_string($required_condition)) {
		$required_condition = trim($required_condition);
		if ($required_condition !== '') {
			$has_required = true;
		}
	}

	$valid_or_conditions = [];
	foreach ($or_conditions as $condition) {
		if (is_string($condition) && trim($condition) !== '') {
			$valid_or_conditions[] = trim($condition);
		}
	}

	if (!$has_required && empty($valid_or_conditions)) {
		$command = sprintf('tail -n %d %s 2>&1', $num_lines, escapeshellarg($logPath));
		$output = shell_exec($command);
		if ($output === null || $output === '') {		
			return false;
		}

		$lines = explode("\n", trim($output));
		$lines = array_map('trim', $lines);		
		return $lines;
	}

	
	$command = '';

	if ($has_required && !empty($valid_or_conditions)) {
		
		$command = sprintf(
			'tail -n %d %s | grep -a -F %s | grep -a -F -e %s | tail -n %d 2>&1',
			$actual_search_limit,
			escapeshellarg($logPath),
			escapeshellarg($required_condition),
			implode(' -e ', array_map('escapeshellarg', $valid_or_conditions)),
			$num_lines
		);
		
	} elseif ($has_required) {

		$command = sprintf(
			'tail -n %d %s | grep -a -F %s | tail -n %d 2>&1',
			$actual_search_limit,
			escapeshellarg($logPath),
			escapeshellarg($required_condition),
			$num_lines
		);		
	} else {

		$command = sprintf(
			'tail -n %d %s | grep -a -F -e %s | tail -n %d 2>&1',
			$actual_search_limit,
			escapeshellarg($logPath),
			implode(' -e ', array_map('escapeshellarg', $valid_or_conditions)),
			$num_lines
		);
	}

	$output = shell_exec($command);

	if ($output === null || $output === '') {		
		return false;
	}

	$lines = explode("\n", trim($output));
	$lines = array_map('trim', $lines);

	if (empty($lines) || (count($lines) === 1 && $lines[0] === '')) {		
		return false;
	}
	return $lines;
}

?>