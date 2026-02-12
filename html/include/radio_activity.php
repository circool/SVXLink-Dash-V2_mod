<?php

/**
 * @filesource /include/radio_activity.php
 * @author Vladimir Tsurkanenko <vladimir@tsurkanenko.ru>
 * @date 2026.02.11
 * @version 0.4.6
 */

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getTranslation.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getActualStatus.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/formatDuration.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/session_header.php';

function renderRadioActivityTable()
{
	if (isset($_SESSION['status']['logic'])) {
		$logics = $_SESSION['status']['logic'];
		$multipleDevices = $_SESSION['status']['multiple_device'] ?? [];
		// return '';
	} else {
		$status = getActualStatus();
		$logics = $status['logic'];
		$multipleDevices = $status['multiple_device'];
	}

	$html = '';

	foreach ($logics as $logicName => $logic) {
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
			$rxDeviceState = $rxDeviceStart > 0 ? getTranslation('RECEIVE') . ' ( ' . $rxDuration . ' )' : getTranslation('STANDBY');

			$txDeviceStart = !empty($txDevice) ? $logic['tx']['start'] : 0;
			$txCellClass = $txDeviceStart > 0 ? ' inactive-mode-cell' : '';
			$txDuration = $txDeviceStart > 0 ? time() - $txDeviceStart : 0;
			$txDuration = $txDuration < 60 ? $txDuration . ' s' : formatDuration($txDuration);
			$txDeviceState = $txDeviceStart > 0 ? getTranslation('TRANSMIT') . ' ( ' . $txDuration . ' )' : getTranslation('STANDBY');

			$callsign = $logic['callsign'] ?? '';
			$callsign = '';

			$destination = '';
			if (isset($logic['module']) && is_array($logic['module'])) {
				foreach ($logic['module'] as $moduleName => $module) {
					$moduleActive = $module['is_active'] ?? false;
					$moduleConnected = $module['is_connected'] ?? false;

					if ($moduleActive || $moduleConnected) {
						$destination = $moduleName;
						break; // The first active module found is used
					}
				}
			}
		} else {
			// @bookmark Reflectors sending/receiving
			$rxDeviceStart = $logic['rx']['start'] ;
			$incomingDuration = 0;
			$outcomingDuration = 0;
			$destination = $logic['talkgroups']['selected'];
			$callsign = $logic['callsign'];

			if($rxDeviceStart > 0){
				$rowClass = '';
				$netDuration = time() - $rxDeviceStart;
				$netDuration = $netDuration < 60 ? $netDuration . ' s' : formatDuration($netDuration);
				
				if(!empty($logic['caller_tg'])) {
					$destination = $logic['caller_tg'];
				} 
				
				if (!empty($logic['caller_callsign'])) {
					$callsign = $logic['caller_callsign'];
				} 
				
				$isIncoming = $callsign !== $logic['callsign'];			
				if($isIncoming){
					$incomingDuration = $netDuration;				
					$rxCellClass = ' active-mode-cell';				
					$txCellClass = ' transparent';			
				} else {
					$outcomingDuration = $netDuration;
					$rxCellClass = ' transparent';
					$txCellClass = ' inactive-mode-cell';										
				}
			} else {
				$rxCellClass = ' transparent';
				$txCellClass = ' transparent';
				$callsign = '';
				
				$rowClass = 'hidden';				
			}
			
			$rxDeviceState = getTranslation('INCOMING') . ' ( ' . $incomingDuration . ' )';
			$txDeviceState = getTranslation('OUTCOMING') . ' ( ' . $outcomingDuration . ' )';
			$destination = getTranslation('Talkgroup') . ': <span class="tg">' . $destination . '</span>';
			
			$rowStyle = ' style = "padding: 0px; margin: 0px" ';
			$row_rxDevice = htmlspecialchars($logicName);
			$row_txDevice = htmlspecialchars($logicName);
			
		}

		$rxDeviceHtml = $rxDevice;
		if (!empty($rxDevice) && isset($multipleDevices[$rxDevice])) {
			$subDevices = $multipleDevices[$rxDevice];
			$rxDeviceHtml = '<a class="tooltip" href="#"><span><b>' . getTranslation('Multiple device') . ':</b>' . $subDevices . '</span>' . $rxDevice . '</a>';
		}

		$txDeviceHtml = $txDevice;
		if (!empty($txDevice) && isset($multipleDevices[$txDevice])) {
			$subDevices = $multipleDevices[$txDevice];
			$txDeviceHtml = '<a class="tooltip" href="#"><span><b>' . getTranslation('Multiple device') . ':</b>' . $subDevices . '</span>' . $txDevice . '</a>';
		}

		

		$html .= '<div class="divTableRow ' . $rowClass .  '">';

		// Logic
		$html .= '<div id="radio_logic_' . $rowDevice . '" class="divTableCell cell_content middle"' . $rowStyle .  '>' . $rowDevice . '</div>';
		// RX Device
		$html .= '<div id="device_' . $row_rxDevice . '_rx" class="divTableCell cell_content middle"' . $rowStyle . '>' . $rxDeviceHtml . '</div>';
		// Status RX
		$html .= '<div id="device_' . $row_rxDevice . '_rx_status" class="divTableCell cell_content middle' . $rxCellClass .  '" ' . $rowStyle . '>' . $rxDeviceState . '</div>';
		// TX Device
		$html .= '<div id="device_' . $row_txDevice . '_tx" class="divTableCell cell_content middle"' . $rowStyle . '>' . $txDeviceHtml . '</div>';
		// Status TX
		$html .= '<div id="device_' . $row_txDevice . '_tx_status" class="divTableCell cell_content middle ' . $txCellClass .  '"' . $rowStyle . '>' . $txDeviceState . '</div>';
		// Callsign
		$html .= '<div id="radio_logic_' . $logicName . '_callsign" class="callsign divTableCell cell_content middle"' . $rowStyle . '>' . $callsign . '</div>';
		// Destination
		$html .= '<div id="radio_logic_' . $logicName . '_destination" class="destination divTableCell cell_content middle"' . $rowStyle . '>' . $destination . '</div>';

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
				<div style="width: 200px" class="divTableHeadCell"><?php echo getTranslation('Logic') ?></div>
				<div style="width: 200px" class="divTableHeadCell"><?php echo getTranslation('Device') ?> RX</div>
				<div style="width: 200px" class="divTableHeadCell"><?php echo getTranslation('Status') ?> RX</div>
				<div style="width: 200px" class="divTableHeadCell"><?php echo getTranslation('Device') ?> TX </div>
				<div style="width: 200px" class="divTableHeadCell"><?php echo getTranslation('Status') ?> TX</div>
				<div class="divTableHeadCell"><?php echo getTranslation('Callsign') ?></div>
				<div class="divTableHeadCell"><?php echo getTranslation('Destination') ?></div>
			</div>
			<?php echo renderRadioActivityTable(); ?>
		</div>
	</div>
	<br>
</div>