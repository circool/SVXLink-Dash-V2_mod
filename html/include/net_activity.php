<?php

/**
 * @filesource /include/net_activity.php
 * @author Vladimir Tsurkanenko <vladimir@tsurkanenko.ru>
 * @date 2026.02.15
 * @version 0.4.6
 */

define("ACTION_LIFETIME", 1); 

if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
	header('Content-Type: text/html; charset=utf-8');
	$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html';
	require_once $docRoot . '/include/settings.php';

	$requiredFiles = [
		'/include/fn/getTranslation.php',
		'/include/fn/logTailer.php',
		'/include/fn/formatDuration.php',
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

	// AJAX POST
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$data = json_decode(file_get_contents('php://input'), true);
		if (isset($data['filter_activity'])) {
			$_SESSION['net_filter'] = $data['filter_activity'];
		}
		if (isset($data['filter_activity_max'])) {
			$_SESSION['net_filter_max'] = floatval($data['filter_activity_max']);
		}
		echo json_encode(['status' => 'ok']);
		exit;
	}

	// AJAX GET
	echo getNetActivityTable();
	return;
}

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getTranslation.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/logTailer.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/formatDuration.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/parseXmlTags.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getLineTime.php';


function getNetActivityActions(): array
{
	$result = [];

	if (!isset($_SESSION['status'])) {
		return $result;
	}

	$min_duration = 1;
	if (isset($_SESSION['net_filter']) && $_SESSION['net_filter'] === 'OFF') {
		$min_duration = 0;
	} elseif (isset($_SESSION['net_filter_max'])) {
		$min_duration = $_SESSION['net_filter_max'];
	}

	$actualLogSize = $_SESSION['status']['service']['log_line_count'];
	$or_condition = ['Turning the transmitter', 'voice started', 'Talker start',  'Talker stop', 'message received from'];
	$log_actions = getLogTailFiltered(NET_ACTIVITY_LIMIT * 15, null, $or_condition, $actualLogSize);

	if ($log_actions === false) return $result;

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
		} elseif (strpos($line, "message received from") !== false && $row['start'] == 0) {

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

					if ($diff_sec > ACTION_LIFETIME) {
						$parent = '';
						$source = '';
					}

					if (!empty($parent)) {

						if ($source === 'Frn') {
							$parsedLine = parseXmlTags($parent);

							if (isset($parsedLine['ON'])) {
								$source = 'Frn: <b>' . $parsedLine['ON'] . '</b>,&nbsp' . $parsedLine['CT'] . ' (' . $parsedLine['BC'] . ' / ' . $parsedLine['DS'] . ')';
							} else {
								$source = '';
							}
						} elseif ($source === 'Reflector') {

							$regexp = '/^(.+?): (\S+): Talker start on TG #(\d*): (\S+)$/';
							if (preg_match($regexp, $parent, $matches)) {
								$source = $matches[2] . ': <b>' . $matches[4] . ' in TG: ' . $matches[3] . '</b>';
							} else {
								$source = '';
							}
						} elseif ($source === 'EchoLinkConference') {

							$regexp = '/received from (.+) ---$/';
							if (preg_match($regexp, $parent, $matches)) {
								$source = 'EchoLink Conference <b>' . $matches[1] . '</b>';
							} else {
								$source = '';
							}
						}

						$parent = '';
					}
				}

				if ($source === 'Frn' || $source === 'Reflector' || $source === 'EchoLinkConference') {
					$row['source'] = '';
				} else {
					$row['source'] = $source;
				}

				$row['start'] = $timestamp;
				$row['date'] = date('d M Y', $timestamp);
				$row['time'] = date('H:i:s', $timestamp);
			} else {

				if ($row['start'] > 0) {
					$stop = getLineTime($line);
					if ($stop - $row['start'] > $min_duration) {
						if (!empty($row['source'])) {
							$row['duration'] = $stop - $row['start'];
							$result[] = $row;
						}
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
					} else {
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
					}
				}
			}
		}
	}

	// Show incoplette activity
	if ($row['start'] > 0 && !empty($row['source'])) {
		$lastMsgDuration = time() - $row['start'];
		if ($lastMsgDuration > $min_duration) {
			$row['duration'] = $lastMsgDuration;
			$result[] = $row;
		}
	}

	return array_slice(array_reverse($result), 0, NET_ACTIVITY_LIMIT);
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

if (!isset($_GET['ajax'])) {

	if (isset($_GET['filter_activity'])) {
		$_SESSION['net_filter'] = $_GET['filter_activity'];
	}
	if (isset($_GET['filter_activity_max']) && is_numeric($_GET['filter_activity_max'])) {
		$_SESSION['net_filter_max'] = floatval($_GET['filter_activity_max']);
	}

	$netResultLimit = NET_ACTIVITY_LIMIT . ' ' . getTranslation('Actions');
	$current_filter = $_SESSION['net_filter'] ?? 'ON';
	$current_max = $_SESSION['net_filter_max'] ?? '1';
	
?>
	<div id="net_activity">
		<div style="float: right; vertical-align: bottom; padding-top: 0px;" id="lhAc">
			<div class="grid-container" style="display: inline-grid; grid-template-columns: auto 40px; padding: 1px; grid-column-gap: 5px;">
				<div class="grid-item filter-activity" style="padding: 10px 0 0 20px;" title="<?= getTranslation('Hide Kerchunks') ?>"><?= getTranslation('Hide Kerchunks') ?>:</div>
				<div class="grid-item">
					<div style="padding-top:6px;">
						<input id="toggle-filter-activity" class="toggle toggle-round-flat" type="checkbox" name="display-lastcaller"
							value="ON" <?php echo $current_filter === 'OFF' ? '' : 'checked="checked"'; ?>
							onchange="
								fetch('/include/net_activity.php?ajax=1', {
									method: 'POST',
									headers: {'Content-Type': 'application/json'},
									body: JSON.stringify({
										filter_activity: this.checked ? 'ON' : 'OFF',
										filter_activity_max: document.querySelector('.filter-activity-max').value
									})
								}).then(() => {
									if (typeof updateBlock === 'function') {
										updateBlock({name: 'net_activity', container: 'net_activity_content'});
									}
								});
							">
						<label for="toggle-filter-activity"></label>
					</div>
				</div>
			</div>
			<div class="filter-activity-max-wrap">
				<input onchange="
					fetch('/include/net_activity.php?ajax=1', {
						method: 'POST',
						headers: {'Content-Type': 'application/json'},
						body: JSON.stringify({
							filter_activity: document.getElementById('toggle-filter-activity').checked ? 'ON' : 'OFF',
							filter_activity_max: this.value
						})
					}).then(() => {
						if (typeof updateBlock === 'function') {
							updateBlock({name: 'net_activity', container: 'net_activity_content'});
						}
					});
				" class="filter-activity-max"
					style="width:40px;" type="number" step="0.5" min="0.5" max="5" name="filter-activity-max"
					value="<?php echo $current_max; ?>"> s
			</div>
		</div>
		<div class="larger" style="vertical-align: bottom; font-weight:bold;text-align:left;margin-top:12px;">
			<?php echo getTranslation('Last') . " " . $netResultLimit . " " . getTranslation('NET Activity') ?>
		</div>
		<div id="net_activity_content">
			<?php echo getNetActivityTable(); ?>
		</div>
		<br>
	</div>

<?php }
?>