<?php

/**
 * @filesource /include/rf_activity.php
 * @version 0.4.12.release
 * @author vladimir@tsurkanenko.ru
 */


if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
	header('Content-Type: text/html; charset=utf-8');
	$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html';
	require_once $docRoot . '/include/session_header.php';

	$requiredFiles = [
		'/include/fn/getTranslation.php',
		'/include/fn/logTailer.php',
		'/include/fn/formatDuration.php',
		'/include/fn/getLineTime.php'
	];

	foreach ($requiredFiles as $file) {
		$fullPath = $docRoot . $file;
		if (file_exists($fullPath)) {
			require_once $fullPath;
		}
	}

	if (session_status() === PHP_SESSION_ACTIVE) {
		session_write_close();
	} 
	echo generatePageHead();
	echo buildLocalActivityTable();
	echo generatePageTail();
	exit;
}

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getTranslation.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/logTailer.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/formatDuration.php';

function buildLocalActivityTable(): string
{
	// if (isset($_SESSION['TIMEZONE'])) {
	// 	date_default_timezone_set($_SESSION['TIMEZONE']);
	// }
	// if (!isset($_SESSION['TIMEZONE'])) {
	// 	if (file_exists('/etc/timezone')) {
	// 		$systemTimezone = trim(file_get_contents('/etc/timezone'));
	// 	} else {
	// 		$systemTimezone = 'UTC';
	// 		error_log("Cant found /etc/timezone file");
	// 	}
	// 	$_SESSION['TIMEZONE'] = $systemTimezone;
	// } else {
	// 	$systemTimezone = $_SESSION['TIMEZONE'];
	// }
	// date_default_timezone_set($systemTimezone);


	$logLinesCount = $_SESSION['service']['log_line_count'] ?? 10000;

	$squelch_lines = getLogTailFiltered( RF_ACTIVITY_LIMIT * 20, null, ["The squelch is"], $logLinesCount);

	if (!$squelch_lines) {
		return generateEmptyTableBody();
	}
	$squelch_pairs = findSquelchPairs($squelch_lines);
	if (empty($squelch_pairs)) {
		return generateEmptyTableBody();
	}

	$activity_rows = [];
	$defaultDestination = getTranslation('Unknown');
	foreach ($squelch_pairs as $pair) {
		$destination = $defaultDestination; 
		$open_colon_pos = strpos($pair['open_line'], ': ');
		$close_colon_pos = strpos($pair['close_line'], ': ');

		if ($open_colon_pos === false || $close_colon_pos === false) {
			continue;
		}

		$open_timestamp = substr($pair['open_line'], 0, $open_colon_pos);
		$close_timestamp = substr($pair['close_line'], 0, $close_colon_pos);
		$time_prefix = '';
		$min_length = min(strlen($open_timestamp), strlen($close_timestamp));

		for ($i = 0; $i < $min_length; $i++) {
			if ($open_timestamp[$i] !== $close_timestamp[$i]) {
				break;
			}
			$time_prefix .= $open_timestamp[$i];
		}

		$time_lines = getLogTailFiltered(100, $time_prefix, [], $logLinesCount);

		if ($time_lines && is_array($time_lines)) {

			$context_lines = [];
			$open_found = false;

			foreach ($time_lines as $line) {
				if (trim($line) === trim($pair['open_line'])) {
					$open_found = true;
					continue;
				}

				if (trim($line) === trim($pair['close_line'])) {
					break;
				}

				if ($open_found) {
					$context_lines[] = $line;
				}
			}

			if ($context_lines) {
				$primary_destination = null;
				$dtmf_digits = [];
				$dtmf_prefix = '';

				foreach ($context_lines as $line) {
					$pattern_match = findPatternInLine($line);
					if ($pattern_match !== null) {
						$primary_destination = $pattern_match;
						$dtmf_digits = [];
						$dtmf_prefix = '';
					}

					if (preg_match('/:\s*([^:]+):\s*digit=(.+)/', $line, $dtmf_matches)) {
						if ($primary_destination === null && empty($dtmf_digits)) {
							$dtmf_prefix = $dtmf_matches[1];
						}
						$dtmf_digits[] = $dtmf_matches[2];
					}
				}

				if ($primary_destination !== null && !empty($dtmf_digits)) {
					$destination = $primary_destination . ': <b>DTMF ' . implode('', $dtmf_digits) . '</b>';
				} elseif ($primary_destination !== null) {
					$destination = $primary_destination;
				} elseif (!empty($dtmf_digits)) {
					$destination = $dtmf_prefix . ': <b>DTMF ' . implode('', $dtmf_digits) . '</b>';
				}				
			}
		}
		
		$open_time = strtotime($open_timestamp);
		$activity_rows[] = [
			'date' => date('d M Y', $open_time),
			'time' => date('H:i:s', $open_time),
			'destination' => $destination,
			'duration' => formatDuration((int)$pair['duration'])
		];
	}

	usort($activity_rows, function ($a, $b) {
		return strtotime($b['date'] . ' ' . $b['time']) <=> strtotime($a['date'] . ' ' . $a['time']);
	});

	$activity_rows = array_slice($activity_rows, 0, RF_ACTIVITY_LIMIT);
	$html = generateActivityTableBody($activity_rows);
	return $html;
}

function findSquelchPairs(array $squelch_lines): array
{
	$squelch_events = [];

	foreach ($squelch_lines as $line) {
		$time = getLineTime($line);
		if (!$time) continue;

		if (strpos($line, 'The squelch is') !== false) {
			preg_match('/:\s*(\w+):\s*The squelch is (OPEN|CLOSED)/', $line, $m);
			if (isset($m[1], $m[2])) {
				$squelch_events[] = [
					'time' => $time,
					'device' => $m[1],
					'state' => $m[2],
					'line' => $line
				];
			}
		}
	}

	usort($squelch_events, function ($a, $b) {
		return $a['time'] <=> $b['time'];
	});

	$pairs = [];
	$open_event = null;

	foreach ($squelch_events as $event) {
		if ($event['state'] === 'OPEN') {
			$open_event = $event;
		} elseif ($event['state'] === 'CLOSED' && $open_event && $open_event['device'] === $event['device']) {
			$duration = $event['time'] - $open_event['time'];

			if ($duration >= 2) {
				$pairs[] = [
					'open_time' => $open_event['time'],
					'close_time' => $event['time'],
					'duration' => $duration,
					'device' => $event['device'],
					'open_line' => $open_event['line'],
					'close_line' => $event['line']
				];
			}

			$open_event = null;
		}
	}

	return $pairs;
}

function findPatternInLine(string $line): ?string
{
	if (strpos($line, 'Talker start on TG #') !== false) {
		preg_match('/:\s*([^:]+):\s*Talker start on TG #(\d+):\s*([^\s]+)/', $line, $m);
		if (isset($m[1], $m[2], $m[3])) {
			$logic_name = $m[1];
			$tg_number = $m[2];
			$callsign_from_log = $m[3];

			if (isset($_SESSION['status']['logic'][$logic_name]['callsign'])) {
				$callsign_from_session = $_SESSION['status']['logic'][$logic_name]['callsign'];
				if ($callsign_from_log === $callsign_from_session) {
					return "{$logic_name} (TG #{$tg_number})";
				} else {
					return null;
				}
			} else {
				return null;
			}
		}
	}

	if (strpos($line, 'Selecting TG #') !== false) {
		preg_match('/:\s*([^:]+):\s*Selecting TG #(\d+)/', $line, $m);
		if (isset($m[1], $m[2])) {
			return "{$m[1]} (TG #{$m[2]})";
		}
	}

	if (strpos($line, 'message received from') !== false) {
		preg_match('/from ([^\s]+)/', $line, $m);
		if (isset($m[1])) {
			return "EchoLink ({$m[1]})";
		}
	}
	return null;
}

function generateActivityTableBody(array $activity_rows): string
{
	$html = '';
	if (!empty($activity_rows)) {
		foreach ($activity_rows as $row) {
			$html .= '<tr class="divTable divTableRow">';
			$html .= '<td class="divTableContent">' . htmlspecialchars($row['date']) . '</td>';
			$html .= '<td class="divTableCol">' . htmlspecialchars($row['time']) . '</td>';
			$html .= '<td class="divTableCol">' . $row['destination'] . '</td>';
			$html .= '<td class="divTableCol">' . htmlspecialchars($row['duration']) . '</td>';
			$html .= '</tr>';
		}
		return $html;
	} else {
		return generateEmptyTableBody();
	}
}

function generateEmptyTableBody(): string
{
	return '<tr class="divTable divTableRow"><td colspan="4" class="divTableContent">' . getTranslation('No activity history found') . '</td></tr>';
}

function generatePageHead(): string
{

	$result = '</div><div id="rf_activity_content"><table class="divTable" style="word-wrap: break-word; white-space:normal;">';
	$result .= '<tbody class="divTableBody"><tr>';
	$result .= '<th width="150px"><a class="tooltip" href="#">' . getTranslation('Date') . '<span><b>' . getTranslation('Date') . '</b></span></a></th>';
	$result .= '<th width="150px"><a class="tooltip" href="#">' . getTranslation('Time') . '<span><b>' . getTranslation('Local Time') . '</b></span></a></th>';
	$result .= '<th><a class="tooltip" href="#">' . getTranslation('Transmission destination') . '<span><b>' . getTranslation("Frn Server, Reflector's Talkgroup, Echolink Node, Conference etc.") . '</b></span></a></th>';
	$result .= '<th width="150px"><a class="tooltip" href="#">' . getTranslation('Duration') . '<span><b>' . getTranslation('Duration in Seconds') . '</b></span></a></th></tr>';
	return $result;
}

function generatePageTail(): string
{
	return '</tbody></table></div></div>';
}

$tableHtml = buildLocalActivityTable();
$rfResultLimit = RF_ACTIVITY_LIMIT . ' ' . getTranslation('Actions');
echo '<div id="rf_activity"><div class="larger" style="vertical-align: bottom; font-weight:bold;text-align:left;margin-top:12px;">';
echo getTranslation('Last') . ' ' . RF_ACTIVITY_LIMIT . ' ' . getTranslation('Actions') . " " . getTranslation('RF Activity');
echo generatePageHead();
echo $tableHtml;
echo generatePageTail();


unset($tableHtml, $rfResultLimit);
?>

			