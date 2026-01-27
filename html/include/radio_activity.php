<?php

/**
 * @version 0.4.4.release
 * @date 2026.01.26
 * @author vladimir@tsurkanenko.ru
 * @filesource /include/radio_activity.php
 */

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getTranslation.php';

function renderRadioActivityTable()
{
	if (!isset($_SESSION['status']['logic'])) {
		return '';
	}

	$logics = $_SESSION['status']['logic'];
	$multipleDevices = $_SESSION['status']['multiple_device'] ?? [];

	$html = '';

	foreach ($logics as $logicName => $logic) {
		$isActive = $logic['is_active'] ?? false;
		$isConnected = $logic['is_connected'] ?? false;

		if (!$isActive && !$isConnected) {
			continue; 
		}

		$rxDevice = $logic['rx']['name'] ?? '';
		$txDevice = $logic['tx']['name'] ?? '';
		
		if (empty($rxDevice) && empty($txDevice)) {
			if ($logic['type'] !== 'Reflector') continue;
		}

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
			$callsign = '';
			
		} else {
			$rowClass = 'hidden';
			$rowStyle = ' style = "padding: 0px; margin: 0px" ';

			$row_rxDevice = htmlspecialchars($logicName);
			$row_txDevice = htmlspecialchars($logicName);
			$rxDeviceState = '';
			$txDeviceState = '';
			$callsign = '';
		}

		$rxDeviceHtml = $rxDevice;
		if (!empty($rxDevice) && isset($multipleDevices[$rxDevice])) {
			$subDevices = $multipleDevices[$rxDevice];
			$rxDeviceHtml = '<a class="tooltip" href="#"><span><b>Multiple device:</b>' . $subDevices . '</span>' . $rxDevice . '</a>';
		}

		$txDeviceHtml = $txDevice;
		if (!empty($txDevice) && isset($multipleDevices[$txDevice])) {
			$subDevices = $multipleDevices[$txDevice];
			$txDeviceHtml = '<a class="tooltip" href="#"><span><b>Multiple device:</b>' . $subDevices . '</span>' . $txDevice . '</a>';
		}

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
