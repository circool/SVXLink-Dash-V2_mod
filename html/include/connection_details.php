<?php

/**
 * @filesource /include/connection_details.php
 * @version 0.4.11.release
 * @author vladimir@tsurkanenko.ru
 * @since 0.2.0
 * @date 2026.01.26
 * @note Preliminary version.
 */

if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {

	header('Content-Type: text/html; charset=utf-8');
	$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html';
	require_once $docRoot . '/include/settings.php';
	$requiredFiles = [
		'/include/fn/getTranslation.php',
		'/include/fn/removeTimestamp.php',
		'/include/fn/logTailer.php',
		'/include/fn/formatDuration.php',
		'/include/fn/getLineTime.php',
		'/include/fn/parseXmlTags.php'
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

	echo getConnectionDetailsTable();
	exit;
}

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/removeTimestamp.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getTranslation.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/logTailer.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/formatDuration.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getLineTime.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/parseXmlTags.php';

function getConnectionDetails(): array
{
	$result = [];

	if (!isset($_SESSION['status'])) {
		error_log('Mandatory data not found in session. $_SESSION["status"]');
		return [];
	}
	$con_det_logics = $_SESSION['status']['logic'];

	foreach ($con_det_logics as $logicName => $logic) {
		if ($logic['is_active'] == false || $logic['is_connected'] == false) {
			continue;
		}

		$lName = $logicName;
		$lDuration = isset($logic['start']) ? time() - $logic['start'] : '';
		$lDestination = '';
		$lDetails = [];
		$msg = [];
		if ($logic['type'] == 'Reflector') {
			$lDestination = 'TG #' . $logic['talkgroups']['selected'];
			if (!empty($logic['connected_nodes'])) {
				$nodes = implode(', ', array_keys($logic['connected_nodes']));
				$lDestination .= ': ' . $nodes;
			}
		} else {
			foreach ($logic['module'] as $moduleName => $module) {
				if ($module['is_active']) {
					$lDestination = $module['name'];
					$lDuration = isset($module['start']) ? time() - $module['start'] : '';
					if ($moduleName == 'Frn') {
						$nodes = getFrnNodes();
						if (isset($nodes)) {
							$lDetails = $nodes;
						}
					} else if ($moduleName == 'EchoLink') {
						foreach ($module['connected_nodes'] as $nodeName => $node) {
							$msg = getEchoLinkMsg($nodeName);
						}
						$lDetails = $msg;
					}
					if (!empty($module['connected_nodes'])) {
						$lDestination .= ': ' . implode(', ', array_keys($module['connected_nodes']));
					}
					break;
				}
			}
		}

		$result[] = [
			'name' => $lName,
			'duration' => $lDuration,
			'destination' => $lDestination,
			'details' => $lDetails,
		];
	}

	return $result;
}

function getFrnNodes(): array
{
	$nodes = [];
	if (isset($_SESSION['status'])) {
		$actualLogSize = $_SESSION['status']['service']['log_line_count'];
		$search_condition = "FRN active client list updated";
		$logPosition = countLogLines($search_condition, $actualLogSize);
		if ($logPosition !== false) {
			$logContent = getLogTail($logPosition);
			$isFirstMsg = false;

			if ($logContent !== false) {
				foreach ($logContent as $line) {

					if (strpos($line, 'FRN list received:') !== false) {
						if ($isFirstMsg) break;
						$isFirstMsg = true;
						continue;
					}

					if (strpos($line, '--') !== false) {
						$node = parseXmlTags($line);
						if (count($node) > 0) $nodes[] = $node;
					}
				}
			}
		}
	}
	return $nodes;
}

function getEchoLinkMsg($nodeName): array
{
	$result = [];
	$msg = [];

	if (isset($_SESSION['status'])) {

		$actualLogSize = $_SESSION['status']['service']['log_line_count'];

		$search_condition = "Echolink Node " . $nodeName;

		$logPosition = countLogLines($search_condition, $actualLogSize);

		if ($logPosition !== false) {
			$logPosition++;
			$msg = getLogTailFiltered(1, $search_condition, [], $logPosition);

			if ($msg !== false) {
				$clearedLine = removeTimestamp($msg[0]);
				$result = explode('<0d>', $clearedLine);
				$result = array_filter($result, function ($line) {
					return trim($line) !== 'oNDATA';
				});
			}
		}

		$search_condition = "message received from " . $nodeName . ' ';
		$logPosition = countLogLines($search_condition, $actualLogSize);
		
		if ($logPosition !== false) {
			$logContent = getLogTail($logPosition);
			if ($logContent !== false) {

				$firstLine = $logContent[0];
				$nodes = [];

				foreach ($logContent as $line) {
					$secDiff = getLineTime($line) - getLineTime($firstLine);
					if ($secDiff > 1) {
						break;
					};

					if (strpos($line, "Trailing chat data") !== false) {
						break;
					};
					if (strpos($line, "EchoLink QSO state changed to CONNECTED") !== false) {
						break;
					};
					if (strpos($line, "Turning the transmitter") !== false) {
						break;
					};
					if (strpos($line, "The squelch is") !== false) {
						break;
					};

					$clearedLine = removeTimestamp($line);
					if (!empty($clearedLine)) {
						$nodes[] = $clearedLine;
					} else {
						$nodes[] = '<br>';
					};
				}
				return $nodes;
			}
		}
	}
	return $result;
}


function getConnectionDetailsTable(): string
{
	$data = getConnectionDetails();

	$html = '<table style="word-wrap: break-word; white-space:normal;">';
	$html .= '<thead>';
	$html .= '<tr>';
	$html .= '<th><a class="tooltip" href="#">' . getTranslation('Logic') . '<span><b>' . getTranslation('Source') . '</b></span></a></th>';
	$html .= '<th><a class="tooltip" href="#">' . getTranslation('Destination') . '<span><b>' . getTranslation('Destination of transmission') . '</b></span></a></th>';
	$html .= '<th width="150px"><a class="tooltip" href="#">' . getTranslation('Duration') . '<span><b>' . getTranslation('Duration') . '</b></span></a></th>';
	$html .= '</tr>';
	$html .= '</thead>';
	$html .= '<tbody>';

	if (!empty($data)) {
		foreach ($data as $logic) {
			$html .= '<tr>';
			$html .= '<td>' . htmlspecialchars($logic['name']) . '</td>';
			$html .= '<td>' . htmlspecialchars($logic['destination']) . '</td>';
			$html .= '<td >' . formatDuration($logic['duration']) . '</td>';
			$html .= '</tr>';
		}
	} else {
		$html .= '<tr><td colspan=3>' . getTranslation('No activity history found') . '</td></tr>';
	}

	$html .= '</tbody>';
	$html .= '</table>';

	$html .= '<br>';

	if (!empty($data)) {
		foreach ($data as $logic) {
			if (!empty($logic['details'])) {
				$isFrnModule = stripos($logic['destination'], 'Frn') !== false ||
					(isset($logic['name']) && stripos($logic['name'], 'Frn') !== false);

				$isEchoLinkModule = stripos($logic['destination'], 'EchoLink') !== false ||
					(isset($logic['name']) && stripos($logic['name'], 'EchoLink') !== false);

				// Frn
				if ($isFrnModule) {
					$html .= '<table style="word-wrap: break-word; white-space:normal;">';
					$html .= '<tbody>';

					if (!empty($logic['details'][0])) {
						$html .= '<tr>';
						foreach (array_keys($logic['details'][0]) as $key) {
							$russianNames = [
								'S' => getTranslation('Status'),
								'M' => getTranslation('Messages'),
								'NN' => getTranslation('Country'),
								'CT' => getTranslation('City'),
								'BC' => getTranslation('Frequency'),
								'CL' => getTranslation('Node Type'),
								'ON' => getTranslation('Callsign'),
								'ID' => getTranslation('ID'),
								'DS' => getTranslation('Details')
							];

							$header = isset($russianNames[$key]) ? $russianNames[$key] : $key;
							$html .= '<th>' . htmlspecialchars($header) . '</th>';
						}
						$html .= '</tr>';
					}

					foreach ($logic['details'] as $node) {
						$html .= '<tr>';
						foreach ($node as $key => $value) {
							if ($key == 'CL') {
								$nodeTypes = [
									'0' => getTranslation('crosslink'),
									'1' => getTranslation('gateway'),
									'2' => getTranslation('pc only')
								];
								$value = isset($nodeTypes[$value]) ? $nodeTypes[$value] : $value;
							}
							$html .= '<td>' . htmlspecialchars($value) . '</td>';
						}
						$html .= '</tr>';
					}

					$html .= '</tbody></table>';
				}
				// EchoLink
				elseif ($isEchoLinkModule) {
					$html .= '<div class="message_block">';
					foreach ($logic['details'] as $message) {
						$html .= '<div class="mode_flex">' . $message . '</div>';
					}
					$html .= '</div>';
				}
			}
		}
	}
	return $html;
}
?>
<div id="connection_details">
	<div id="refl_header" class="larger" style="vertical-align: bottom; font-weight:bold;text-align:left;margin-top:12px;">
		<?= getTranslation('Connection Details') ?>
	</div>
	<div id="connection_details_content">
		<?php echo getConnectionDetailsTable(); ?>
	</div>
	<br>
</div>