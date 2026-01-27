<?php

/**
 * @version 0.4.11.release
 * @date 2026.01.26
 * @author vladimir@tsurkanenko.ru
 * @filesource /include/net_activity.php 
 */

if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
	header('Content-Type: text/html; charset=utf-8');
	$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html';
	require_once $docRoot . '/include/settings.php';

	$requiredFiles = [
		'/include/fn/getTranslation.php',
		'/include/fn/logTailer.php',
		'/include/fn/formatDuration.php',
		'/include/fn/removeTimestamp.php',
		'/include/fn/parseXmlTags.php',
		'/include/fn/getLineTime.php'
	];

	foreach ($requiredFiles as $file) {
		$fullPath = $docRoot . $file;
		if (file_exists($fullPath)) {
			require_once $fullPath;
		}
	}

	if (session_status() === PHP_SESSION_NONE) {
		session_name(SESSION_NAME);
		session_start();
	}
	session_write_close();
	echo getNetActivityTable();
	exit;
}

define("MIN_DURATION", 3);
define("ACTION_LIFETIME", 1);

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getTranslation.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/logTailer.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/formatDuration.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/removeTimestamp.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/parseXmlTags.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getLineTime.php';


function getNetActivityActions(): array
{
	$result = [];
	if (!isset($_SESSION['status'])) {
		return [];
	}

	$actualLogSize = $_SESSION['status']['service']['log_line_count'];
	$or_condition = ['Turning the transmitter', 'voice started', 'Talker start',  'Talker stop', 'chat message received from'];
	$log_actions = getLogTailFiltered(NET_ACTIVITY_LIMIT * 6, null, $or_condition, $actualLogSize);

	if ($log_actions !== false) {
		$row = [
			'start' => 0,
			'date' => '',
			'time' => '',
			'source' => '',
			'destination' => '',
			'duration' => 0
		];

		$parent = '';
		$source = '';

		foreach ($log_actions as $line) {
			if (strpos($line, "voice started") !== false) {
				// Frn 
				if ($row['start'] == 0) {
					$parent = $line;
					$source = "Frn";
				}
			} elseif (strpos($line, "Talker") !== false) {

				// Reflector
				if (strpos($line, "Talker start") !== false) {
					if ($row['start'] == 0) {
						$parent = $line;
						$source = "Reflector";
					}
				} elseif (strpos($line, "Talker stop") !== false) {
					if ($source == "Reflector" && $row['start'] == 0) {
						$parent = '';
						$source = '';
					}
				}
			} elseif (strpos($line, "chat message received from") !== false && $row['start'] == 0) {

				// EchoLink conference	
				$parent = $line;
				$source = "EchoLinkConference";
			} elseif (strpos($line, "Turning the transmitter") !== false) {

				// Transmitter
				$regexp = '/^(.+?): (\w+): Turning the transmitter (ON|OFF)$/';
				if (!preg_match($regexp, $line, $matches)) {
					error_log("Error parsing transmitter state from line " . $line);
					continue;
				}
				$state = $matches[3];

				if ($state == 'ON') {
					$timestamp = getLineTime($line);

					if (!empty($parent)) {
						$diff_sec = $timestamp - getLineTime($parent);
						
						if ($diff_sec > 1) {
							$parent = '';
							$source = '';
						}

						if (!empty($parent)) {

							if ($source === 'Frn') {
								$parsedLine = parseXmlTags($parent);

								if (isset($parsedLine['ON'])) {
									$source = 'Frn: <b>' . $parsedLine['ON'] . '</b>,&nbsp' . $parsedLine['CT'] . ' (' . $parsedLine['BC'] . ' / ' . $parsedLine['DS'] . ')';
								} else {
									$source = 'Error parsing Frn Server';
								}
							} elseif ($source === 'Reflector') {

								$regexp = '/^(.+?): (\S+): Talker start on TG #(\d*): (\S+)$/';
								preg_match($regexp, $parent, $matches);
								$source = $matches[2] . ': <b>' . $matches[4] . ' in TG: ' . $matches[3] . '</b>';
							} elseif ($source === 'EchoLinkConference') {

								$regexp = '/^(.+?): --- EchoLink chat message received from (\S+) ---$/';
								preg_match($regexp, $parent, $matches);
								$source = 'EchoLink Conference <b>' . $matches[2] . '</b>';
							} else {
								$source = $_SESSION['status']['service']['name'];
							}

							$parent = '';
						}
					}

					$row['start'] = $timestamp;
					$row['date'] = date('d M Y', $timestamp);
					$row['time'] = date('H:i:s', $timestamp);
					$row['source'] = $source === '' ? $_SESSION['status']['service']['name'] : $source;
				} else {
					
					if ($row['start'] > 0) {
						$stop = getLineTime($line);
						if ($stop - $row['start'] > 1) {

							$row['duration'] = $stop - $row['start'];
							$result[] = $row;
							$row = [
								'start' => 0,
								'date' => '',
								'time' => '',
								'source' => '',
								'destination' => '',
								'duration' => 0
							];
						} else {
							$dur = $stop - $row['start'];
							$row = [
								'start' => 0,
								'date' => '',
								'time' => '',
								'source' => '',
								'destination' => '',
								'duration' => 0
							];
						}
					}
				}
			}
		}

		if ($row['start'] > 0 && !empty($row['source'])) {
			$lastMsgDuration = time() - $row['start'];
			if ($lastMsgDuration > 1) {
				$row['duration'] = $lastMsgDuration;
				$result[] = $row;
			} 
		}
		return array_slice(array_reverse($result), 0, NET_ACTIVITY_LIMIT);
	}
	return [];
}

function getNetActivityTable(): string
{
	if (isset($_SESSION['TIMEZONE'])) {
		date_default_timezone_set($_SESSION['TIMEZONE']);
	}
	$net_data = getNetActivityActions();

	$html = '<table style="word-wrap: break-word; white-space:normal;">';
	$html .= '<thead>';
	$html .= '<tr>';
	$html .= '<th width="150px"><a class="tooltip" href="#">' . getTranslation('Date') . '<span><b>' . getTranslation('Date') . '</b></span></a></th>';
	$html .= '<th width="150px"><a class="tooltip" href="#">' . getTranslation('Time') . '<span><b>' . getTranslation('Time') . '</b></span></a></th>';
	$html .= '<th><a class="tooltip" href="#">' . getTranslation('Source') . '<span><b>' . getTranslation('Source') . '</b></span></a></th>';
	$html .= '<th width="150px"><a class="tooltip" href="#">' . getTranslation('Duration') . '<span><b>' . getTranslation('Duration') . '</b></span></a></th>';
	$html .= '</tr>';
	$html .= '</thead>';
	$html .= '<tbody>';

	if (!empty($net_data)) {
		foreach ($net_data as $row) {
			$html .= '<tr>';
			$html .= '<td>' . $row['date'] . '</td>';
			$html .= '<td>' . $row['time'] . '</td>';
			$html .= '<td>' . $row['source'] . '</td>';
			$html .= '<td>' . formatDuration($row['duration']) . '</td>';
			$html .= '</tr>';
		}
	} else {
		$html .= '<tr><td colspan=5>' . getTranslation('No activity history found') . '</td></tr>';
	}

	$html .= '</tbody>';
	$html .= '</table>';

	return $html;
}

$netResultLimit = NET_ACTIVITY_LIMIT . ' ' . getTranslation('Actions');
?>
<div id="net_activity">
	<div class="larger" style="vertical-align: bottom; font-weight:bold;text-align:left;margin-top:12px;">
		<?php echo getTranslation('Last') . " " . $netResultLimit . " " . getTranslation('NET Activity') ?>
	</div>
	<div id="net_activity_content">
		<?php echo getNetActivityTable(); ?>
	</div>
	<br>
</div>

