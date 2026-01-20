<?php

/**
 * Отражает данные о текущес подключении - активный модуль, подробности
 * @version 0.4.0
 * @filesource /include/exct/connection_details.0.4.0.php
 * @since 0.2.0
 * @date 2026.01.18
 * @author vladimir@tsurkanenko.ru
 * @todo Реализовать динамическое обновление
 */

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/removeTimestamp.php';
// require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/compareTimestampMilis.php';

if (defined("DEBUG") && DEBUG) {
	$funct_start = microtime(true);
	$ver = "connection_details.0.4.0";
	dlog("$ver: Начинаю работу", 4, "INFO");
}





/** Текущее состояние */
function getConnectionDetails(): array
{
	$result = [];

	if (!isset($_SESSION['status'])) {
		if (defined("DEBUG") && DEBUG) {
			dlog("Не найдено данных в сессии", 1, "ERROR");
		} else {
			error_log('Mandatory data not found in session. $_SESSION["status"]');
		}
		return [];
	}
	$con_det_logics = $_SESSION['status']['logic'];

	foreach ($con_det_logics as $logicName => $logic) {
		if ($logic['is_active'] == false || $logic['is_connected'] == false) {
			continue;
		}

		$lName = $logicName;
		$lDuration = $logic['duration'] ?? '';
		$lDestination = '';
		$lDetails = [];

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
					$lDuration = $module['duration'] ?? '';
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

/** Узлы сервера Frn */
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
						if (defined("DEBUG") && DEBUG) dlog("Обрабатываю строку $line", 4, "DEBUG");
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
	
	if (isset($_SESSION['status'])) {

		$actualLogSize = $_SESSION['status']['service']['log_line_count'];
		
		$search_condition = "Echolink Node " . $nodeName;

		$logPosition = countLogLines($search_condition, $actualLogSize);
		
		if ($logPosition !== false) {
			$logPosition++;
			if (defined("DEBUG") && DEBUG) dlog('Ищу  строку "' . $search_condition . '" в последних ' . $logPosition . ' строках журнала', 4, "DEBUG");
			$msg = 	getLogTailFiltered(1, $search_condition, [], $logPosition);
			
			if ($msg !== false) {
				if (defined("DEBUG") && DEBUG) dlog('Возвращаю строку ' .'"$msg[0]"', 4, "DEBUG");
				$clearedLine = removeTimestamp($msg[0]);
				$result = explode('<0d>', $clearedLine);
				$result = array_filter($result, function ($line) {
					return trim($line) !== 'oNDATA';

				});
			}
		} 
			
		$search_condition = "message received from " . $nodeName . ' ';
		$logPosition = countLogLines($search_condition, $actualLogSize);
		if (defined("DEBUG") && DEBUG) dlog('Поиск строки "' . $search_condition . '" вернул результат ' . $logPosition, 4, "DEBUG");
		if ($logPosition !== false) {
			if (defined("DEBUG") && DEBUG) dlog("Отбираю $logPosition последних строк  журнала", 4, "DEBUG");
			$logContent = getLogTail($logPosition);
			if ($logContent !== false) {

				$firstLine = $logContent[0];
				foreach ($logContent as $line) {

					// $milisDiff = compareTimestampMilis($firstLine, $line);
					// if ($milisDiff === false || $milisDiff > 2) {
					// 	break;
					// };
					$secDiff = getLineTime($line) - getLineTime($firstLine);
					if ($secDiff > 1) {
							break;
					};

						if (strpos($line, "Trailing chat data") !== false) {
						break;
					};

					$clearedLine = removeTimestamp($line);
					if (!empty($clearedLine)) {
						$nodes[] = $clearedLine;
					} else {
						$nodes[] = '<p><p>';
					};
				}
				if (defined("DEBUG") && DEBUG) dlog("Возвращаю " . count($nodes) . " значимых строк", 4, "DEBUG");
				return $nodes;
			}
		}
		
	}
	return $result;
}
?>

<div id="connection_details">
	<div id="refl_header" class="larger" style="vertical-align: bottom; font-weight:bold;text-align:left;margin-top:12px;">
		<?= getTranslation('Connection Details') ?>
	</div>
	<div>
		<table style="word-wrap: break-word; white-space:normal;">
			<thead>
				<tr>
					<th><a class="tooltip" href="#"><?php echo getTranslation('Logic'); ?><span><b><?php echo getTranslation('Source'); ?></b></span></a></th>
					<th><a class="tooltip" href="#"><?php echo getTranslation('Destination'); ?><span><b><?php echo getTranslation('Destination of transmission'); ?></b></span></a></th>
					<th><a class="tooltip" href="#"><?php echo getTranslation('Duration'); ?><span><b><?php echo getTranslation('Duration'); ?></b></span></a></th>
				</tr>
			</thead>
			<tbody id="connection_details_content">
				<?php
				$data = getConnectionDetails();
				if (!empty($data)) {
					foreach ($data as $logic) {
						echo '<tr>';
						echo '<td>' . $logic['name'] . '</td>';
						echo '<td>' . $logic['destination'] . '</td>';
						echo '<td>' . formatDuration($logic['duration']) . '</td>';
						echo '</tr>';
					}
				} else {
					echo '<tr><td colspan=3>' . getTranslation('No activity history found') . '</td></tr>';
				}
				?>
			</tbody>
		</table>

		<br>
		<?php
		if (!empty($data)) {
			foreach ($data as $logic) {
				if (!empty($logic['details'])) {
					// Проверяем тип модуля по destination
					$isFrnModule = stripos($logic['destination'], 'Frn') !== false ||
						(isset($logic['name']) && stripos($logic['name'], 'Frn') !== false);

					$isEchoLinkModule = stripos($logic['destination'], 'EchoLink') !== false ||
						(isset($logic['name']) && stripos($logic['name'], 'EchoLink') !== false);

					// Frn
					if ($isFrnModule) {
						echo '<table style="word-wrap: break-word; white-space:normal;">';
						echo '<tbody>';

						// Заголовки из первого узла
						if (!empty($logic['details'][0])) {
							echo '<tr>';
							foreach (array_keys($logic['details'][0]) as $key) {
								// Преобразуем теги в названия
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
								echo '<th>' . $header . '</th>';
							}
							echo '</tr>';
						}

						// Данные узлов
						foreach ($logic['details'] as $node) {
							echo '<tr>';
							foreach ($node as $key => $value) {
								// Преобразуем значение для тега CL
								if ($key == 'CL') {
									$nodeTypes = [
										'0' => getTranslation('crosslink'),
										'1' => getTranslation('gateway'),
										'2' => getTranslation('pc only')
									];
									$value = isset($nodeTypes[$value]) ? $nodeTypes[$value] : $value;
								}
								echo '<td>' . $value . '</td>';
							}
							echo '</tr>';
						}

						echo '</tbody></table>';
						echo '<br>';
					}
					// EchoLink
					elseif ($isEchoLinkModule) {
						echo '<div>';
						foreach ($logic['details'] as $message) {
							if (!empty(trim($message))) {
								echo '<div class="mode_flex debug-source">' . $message . '</div>';
							}
						}
						echo '</div>';
						echo '<br>';
					}
				}
			}
		}
		?>
	</div>
</div>

<?php
if (defined("DEBUG") && DEBUG) {
	$funct_time = microtime(true) - $funct_start;
	dlog("$ver: Закончил работу за $funct_time мсек", 3, "INFO");
	unset($ver, $funct_start, $funct_time);
}
