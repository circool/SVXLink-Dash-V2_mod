<?php

/**
 * @version 0.4.4
 * @since 0.4.4
 * @date 2026.01.23
 * @author vladimir@tsurkanenko.ru
 * @filesource /include/exct/radio_activity.0.4.1.php
 * @description Рендерит таблицу активных логик и устройств с ID для динамического обновления
 * @since 0.4.0
 *  - Добавлен id для Destination 
 *  - По умолчанию строки для рефлекторов скрыты (управляется WS)
 */

/**
 * @function renderRadioActivityTable()
 * @returns string HTML тело таблицы с активными логиками и устройствами
 * 
 * @note Алгоритм работы:
 * 1. Получение данных логик из сессии
 * 2. Фильтрация активных/подключенных логик
 * 3. Для каждой логики:
 *    - Получение устройств RX/TX как есть
 *    - Создание tooltip для составных устройств
 *    - Добавление ID для всех динамических ячеек
 *    - Поиск активного модуля для Destination
 *    - Рендеринг строки таблицы
 * 
 * @session_requires
 * - $_SESSION['status']['logic'] - конфигурация логик и устройств
 * - $_SESSION['status']['multiple_device'] - конфигурация составных устройств
 * 
 * @since 0.2
 * - Рендерит только тело таблицы (строки с данными)
 * - Добавляет ID для ячеек, которые будут обновляться через WebSocket
 * - ID формируются по правилам: deviceName, deviceName_StatusRX/TX, logicNameCallsign
 * - Для составных устройств создает tooltip с под-устройствами
 */

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getTranslation.php';

function renderRadioActivityTable()
{
	$func_start = microtime(true);
	$ver = "radio_activity.0.4.0::renderRadioActivityTable";
	if (defined("DEBUG") && DEBUG && function_exists("dlog")) dlog("$ver: Начинаю работу", 4, "INFO");
	// Проверяем наличие данных в сессии
	if (!isset($_SESSION['status']['logic'])) {
		return '';
	}

	$logics = $_SESSION['status']['logic'];
	$multipleDevices = $_SESSION['status']['multiple_device'] ?? [];

	$html = '';

	// Проходим по всем логикам
	foreach ($logics as $logicName => $logic) {
		// Проверяем, активна ли логика или подключена
		$isActive = $logic['is_active'] ?? false;
		$isConnected = $logic['is_connected'] ?? false;

		if (!$isActive && !$isConnected) {
			continue; // Пропускаем неактивные логики
		}

		// Получаем устройства RX/TX
		$rxDevice = $logic['rx']['name'] ?? '';
		$txDevice = $logic['tx']['name'] ?? '';
		

		// Пропускаем логики без устройств (рефлекторы оставляем)
		if (empty($rxDevice) && empty($txDevice)) {
			if ($logic['type'] !== 'Reflector') continue;
		}


		// Форматируем ячейки для логик с устройствами 
		$rowDevice = $logicName;

		if ($logic['type'] !== 'Reflector') {
			$rowClass = '';
			$rowStyle = ' style = "font-size:1.3em; text-align: center;" ';
			$row_rxDevice = htmlspecialchars($rxDevice);
			$row_txDevice = htmlspecialchars($txDevice);
			
			$rxDeviceStart = !empty($rxDevice) ? $logic['rx']['start'] : 0;
			$rxCellClass = $rxDeviceStart > 0 ? ' active-mode-cell' : '';
			$rxDuration = $rxDeviceStart > 0 ? time() - $rxDeviceStart : 0;
			$rxDuration = $rxDuration < 60 ? $rxDuration . ' s' : formatDuration($rxDuration);
			$rxDeviceState = $rxDeviceStart > 0 ? 'RECEIVE ( ' . $rxDuration .' )' : "STANDBY";

			$txDeviceStart = !empty($txDevice) ? $logic['tx']['start'] : 0;
			$txCellClass = $txDeviceStart > 0 ? ' inactive-mode-cell' : '';
			$txDuration = $txDeviceStart > 0 ? time() - $txDeviceStart : 0;			
			$txDuration = $txDuration < 60 ? $txDuration . ' s' : formatDuration($txDuration);			
			$txDeviceState = $txDeviceStart > 0 ? 'TRANSMIT ( ' . $txDuration . ' )' : "STANDBY";

			$callsign = $logic['callsign'] ?? '';
		} else {
			$rowClass = 'hidden';
			$rowStyle = ' style = "padding: 0px; margin: 0px" ';

			$row_rxDevice = htmlspecialchars($logicName);
			$row_txDevice = htmlspecialchars($logicName);
			$rxDeviceState = '';
			$txDeviceState = '';
			$callsign = '';
		}

		// Формируем HTML для RX устройства
		$rxDeviceHtml = $rxDevice;
		if (!empty($rxDevice) && isset($multipleDevices[$rxDevice])) {
			// Составное устройство - создаем tooltip
			$subDevices = $multipleDevices[$rxDevice];
			$rxDeviceHtml = '<a class="tooltip" href="#"><span><b>Multiple device:</b>' . $subDevices . '</span>' . $rxDevice . '</a>';
		}

		// Формируем HTML для TX устройства
		$txDeviceHtml = $txDevice;
		if (!empty($txDevice) && isset($multipleDevices[$txDevice])) {
			// Составное устройство - создаем tooltip
			$subDevices = $multipleDevices[$txDevice];
			$txDeviceHtml = '<a class="tooltip" href="#"><span><b>Multiple device:</b>' . $subDevices . '</span>' . $txDevice . '</a>';
		}

		// Находим активный модуль для Destination
		$destination = '';
		if (isset($logic['module']) && is_array($logic['module'])) {
			foreach ($logic['module'] as $moduleName => $module) {
				$moduleActive = $module['is_active'] ?? false;
				$moduleConnected = $module['is_connected'] ?? false;

				if ($moduleActive || $moduleConnected) {
					$destination = $moduleName;
					break; // Берем первый найденный активный модуль
				}
			}
		}

		if (defined("DEBUG") && DEBUG && function_exists("dlog")) dlog("$ver: Рендеринг строки для $logicName, ($rxDevice)/($txDevice)", 1, "DEBUG");

		// @bookmark Рендерим строку таблицы
		$html .= '<div class="divTableRow ' . $rowClass .  '">';

		// Logic
		$html .= '<div id="radio_logic_' . $rowDevice . '" class="divTableCell cell_content middle"' . $rowStyle .  '>' . $rowDevice . '</div>';
		// RX Device
		$html .= '<div id="device_' . $row_rxDevice . '_rx" class="divTableCell cell_content middle"' . $rowStyle . '>' . $rxDeviceHtml . '</div>';
		// Status RX
		$html .= '<div id="device_' . $row_rxDevice . '_rx_status" class="divTableCell cell_content middle' . $rxCellClass .  '" ' . $rowStyle . '>' . $rxDeviceState . '</div>';
		// TX Device
		$html .= '<div id="device_' . $row_txDevice . '_tx" class="divTableCell cell_content middle" ' . $rowStyle . '>' . $txDeviceHtml . '</div>';
		// Status TX
		$html .= '<div id="device_' . $row_txDevice . '_tx_status" class="divTableCell cell_content middle' . $txCellClass .  '"' . $rowStyle . '>' . $txDeviceState . '</div>';
		// Callsign
		$html .= '<div id="radio_logic_' . $logicName . '_callsign" class="callsign divTableCell cell_content middle"' . $rowStyle . '>' . htmlspecialchars($callsign) . '</div>';
		// Destination
		$html .= '<div id="radio_logic_' . $logicName . '_destination" class="destination divTableCell cell_content middle"' . $rowStyle . '>' . htmlspecialchars($destination) . '</div>';

		$html .= '</div>';
	}
	if (defined("DEBUG") && DEBUG && function_exists("dlog")) {
		$func_time = microtime(true) - $func_start;
		dlog("$ver: Закончил работу за $func_time msec", 3, "INFO");
	}
	return $html;
}
?>
<div id="radio_activity">
	<div class="larger" style="vertical-align: bottom; font-weight:bold;text-align:left;margin-top:-12px;"><?php echo getTranslation('Radio Status') ?></div>

	<div class="divTable">
		<div class="divTableBody">
			<div class="divTableRow">
				<div style="width: 10%;" class="divTableHeadCell"><?php echo getTranslation('Logic') ?></div>
				<div style="width: 10%;" class="divTableHeadCell"><?php echo getTranslation('Device') ?> RX</div>
				<div style="width: 10%;" class="divTableHeadCell"><?php echo getTranslation('Status') ?> RX</div>
				<div style="width: 10%;" class="divTableHeadCell"><?php echo getTranslation('Device') ?> TX </div>
				<div style="width: 10%;" class="divTableHeadCell"><?php echo getTranslation('Status') ?> TX</div>
				<div style="width: 30%;" class="divTableHeadCell"><?php echo getTranslation('Callsign') ?></div>
				<div class="divTableHeadCell"><?php echo getTranslation('Destination') ?></div>
			</div>
			<?php echo renderRadioActivityTable(); ?>
		</div>
	</div>
	<br>
</div>
