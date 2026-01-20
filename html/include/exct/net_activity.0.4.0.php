<?php

/**
 * @version 0.4.0
 * @since 0.1.14
 * @date 2026.01.20
 * @author vladimir@tsurkanenko.ru
 * @filesource /include/exct/net_activity.0.4.0.php 
 * @description Блок информации о активности из сети
 */

define ("MIN_DURATION", 3);
define("ACTION_LIFETIME", 1 );
// require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/compareTimestampMilis.php';

if (defined("DEBUG") && DEBUG) {
	$funct_start = microtime(true);
	$ver = "net_activity.0.4.0";
	dlog("$ver: Начинаю работу", 4, "INFO");
}
/**
 * Получает данные о сетевой активности и возвращает массив структур
 *
 * @return array [ row['date','time','source','destination','duration'] ]
 */



function getNetActivityActions(): array
{
	if (defined("DEBUG") && DEBUG){
		$ver = "getNetActivityActions()";
		dlog("$ver: Начинаю работу", 4, "INFO");
	}
	
	$result = [];

	// Сессии нет - выдаем ошибку и пустой результат
	if (!isset($_SESSION['status'])) {
		if (defined("DEBUG") && DEBUG) {
			dlog("Не найдено данных в сессии", 1, "ERROR");
		} else {
			error_log('Mandatory data not found in session. $_SESSION["status"]');
		}
		return [];
	}

	$actualLogSize = $_SESSION['status']['service']['log_line_count'];

	$or_condition = ['Turning the transmitter', 'voice started', 'Talker start',  'Talker stop', 'chat message received from'];
	$log_actions = getLogTailFiltered(NET_ACTIVITY_LIMIT * 6, null, $or_condition, $actualLogSize);

	// Если есть результат - разбираем
	if ($log_actions !== false) {

		if (defined("DEBUG") && DEBUG) {
			dlog("$ver Получено " . count($log_actions) . " строк, первая - " . removeTimestamp($log_actions[0]), 4, "DEBUG");			
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


		foreach ($log_actions as $line) {

			// Разбираем строку, добавляем маркет события в $source, но только передатчик еще не включился
			if (strpos($line, "voice started") !== false) {
							
				// Frn 
				if($row['start'] == 0){
					$parent = $line;
					$source = "Frn";
				}
				
				
			} elseif (strpos($line, "Talker") !== false) {
				
				// Reflector
				if (strpos($line, "Talker start") !== false){
					if ($row['start'] == 0) {
						$parent = $line;
						$source = "Reflector";
					}
				} elseif (strpos($line, "Talker stop") !== false) {
					// Если передача в рефлекторе остановилась до того как началась работа передатчика, не считаем предыдущее событие как маркер
					if ($source == "Reflector" && $row['start'] == 0 ) {
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
					if( defined("DEBUG") && DEBUG ) {
						dlog("Не удалось разобрать строку передатчика: $line", 2, "WARNING");
					} else {
						error_log("Error parsing transmitter state from line " . $line);
					}
					continue; 
				}
				$state = $matches[3];

				if ($state == 'ON') {
					// Начинаем событие когда включилась передача
					$timestamp = getLineTime($line);
					
					if (!empty($parent)) {
						// Если есть метка - источник события, проверяем не протухла ли она
						$diff_sec = $timestamp - getLineTime($parent);
						if (defined("DEBUG") && DEBUG) dlog("$ver Проверяю время от события $source => $diff_sec", 4, "DEBUG");
						if ($diff_sec > 1) {
							$parent = '';
							$source = '';
						}

						// Если метка не протухла, вычисляем что она означала
						if (!empty($parent)) {
							
							if ($source === 'Frn') {

								if (defined("DEBUG") && DEBUG) dlog("Получаю сервер из строки $parent", 4, "DEBUG");
								$parsedLine = parseXmlTags($parent);

								if (isset($parsedLine['ON'])) {
									$source = '<b>Frn</b>: ' . $parsedLine['ON'] . ', '. $parsedLine['CT'] . ' (' . $parsedLine['BC'] . ' / ' . $parsedLine['DS'] . ')';
								} else {
									$source = 'Error parsing Frn Server';
								}

							} elseif ($source === 'Reflector') {

								$regexp = '/^(.+?): (\S+): Talker start on TG #(\d*): (\S+)$/';
								preg_match($regexp, $parent, $matches);
								$source = '<b>' . $matches[2] . '</b>: ' . $matches[4] . ' in TG: ' . $matches[3];

							} elseif ($source === 'EchoLinkConference') {

								$regexp = '/^(.+?): --- EchoLink chat message received from (\S+) ---$/';
								preg_match($regexp, $parent, $matches);
								$source = '<b>EchoLink Conference</b> ' . $matches[2];

							} else {
								$source = $_SESSION['status']['service']['name'];
							}

							// очищаем отработанное событие-маркер
							$parent = '';
						} 
					}

					if (defined("DEBUG") && DEBUG) dlog("$ver Открываю событие ($source) от " . date('H:i:s', $timestamp), 4, "DEBUG");
					$row['start'] = $timestamp;
					$row['date'] = date('d M Y', $timestamp);
					$row['time'] = date('H:i:s', $timestamp);
					$row['source'] = $source === '' ? $_SESSION['status']['service']['name'] : $source;
					
				} else {
					// Завершаем события когда выключилась передача, добавляем событие к результату
					if ($row['start'] > 0) {
						$stop = getLineTime($line);
						if ($stop - $row['start'] > 1) {

							$row['duration'] = $stop - $row['start'];
							$result[] = $row;
							if (defined("DEBUG") && DEBUG) dlog("$ver Закрыл событие " . $row['source'] . ' от ' . date('H:i:s', $row['start']) . ' продолж ' . $row['duration'] , 4, "DEBUG");

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
							if (defined("DEBUG") && DEBUG) dlog("$ver Пропустил событие как слишком короткое ($dur)", 4, "DEBUG");
						}
					} 





					// $source = '';
				}
			}
		}

		if ($row['start'] > 0 && !empty($row['source'])) {
			// Закрываем незавершенное событие
			$lastMsgDuration = time() - $row['start'];
			if($lastMsgDuration > 1){
				$row['duration'] = $lastMsgDuration;			
				$result[] = $row;
				if (defined("DEBUG") && DEBUG) dlog("$ver Закрыл последнее незакрытое событие", 4, "DEBUG");
			} else {
				if (defined("DEBUG") && DEBUG) dlog("$ver Пропустил последнее незакрытое событие как слишком короткое ($lastMsgDuration)", 4, "DEBUG");
			}
			
		}
		// Переворачиваем и отрезаем лишнее
		if (defined("DEBUG") && DEBUG) dlog("Всего получено " . count($result) . " событий", 4, "DEBUG");
		return array_slice(array_reverse($result), 0, NET_ACTIVITY_LIMIT);
	}

	// Нет результата - пустой массив
	return [];
}



$netResultLimit = NET_ACTIVITY_LIMIT . ' ' . getTranslation('Actions');
?>
<div id="net_activity">
	<div class="larger" style="vertical-align: bottom; font-weight:bold;text-align:left;margin-top:12px;">
		<?php echo getTranslation('Last') . " " . $netResultLimit . " " . getTranslation('NET Activity') ?>
	</div>
	<table style="word-wrap: break-word; white-space:normal;">
		<thead>
			<tr>
				<th width="150px"><a class="tooltip" href="#"><?php echo getTranslation('Date'); ?><span><b><?php echo getTranslation('Date'); ?></b></span></a></th>
				<th width="150px"><a class="tooltip" href="#"><?php echo getTranslation('Time'); ?><span><b><?php echo getTranslation('Time'); ?></b></span></a></th>
				<th><a class="tooltip" href="#"><?php echo getTranslation('Source'); ?><span><b><?php echo getTranslation('Source'); ?></b></span></a></th>
				<th><a class="tooltip" href="#"><?php echo getTranslation('Duration'); ?><span><b><?php echo getTranslation('Duration'); ?></b></span></a></th>
			</tr>
		</thead>
		<tbody id="net_activity_table_content">
			<?php $net_data = getNetActivityActions();
			if (!empty($net_data)) {
				foreach ($net_data as $row) {
					echo '<tr>';
					echo '<td>' . $row['date'] . '</td>';
					echo '<td>' . $row['time'] . '</td>';
					echo '<td>' . $row['source'] . '</td>';
					echo '<td>' . formatDuration($row['duration']) . '</td>';
					echo '</tr>';
				}
			} else {
				echo '<tr><td colspan=5>' . getTranslation('No activity history found') . '</td></tr>';
			}
			?>
		</tbody>
	</table>
	<br>
</div>

<?php
if (defined("DEBUG") && DEBUG) {
	$funct_time = microtime(true) - $funct_start;
	dlog("$ver: Закончил работу за $funct_time мсек", 3, "INFO");
	unset($ver, $funct_start, $funct_time);
}
