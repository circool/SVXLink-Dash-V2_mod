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
			// @todo How to show callsign and talkgroups?
			$rxDeviceStart = $logic['rx']['start'] ;
			
			if($rxDeviceStart > 0){
				
				if(isset($logic['caller_callsign']) && isset($logic['caller_tg'])) {
					
					$callsign = $logic['caller_callsign'];
					$destination = getTranslation('Talkgroup') . ': ' . $logic['caller_tg'] . '.';
					
					if($callsign === $logic['callsign']){
						// direction to reflector
						$txDevice = getTranslation("Network");
						$txDeviceState = getTranslation('OUTCOMING') . ' ( ' . $rxDuration . ' )';
						$txCellClass = ' inactive-mode-cell';
						$txDuration = time() - $rxDeviceStart;
						$txDuration = $rxDuration < 60 ? $rxDuration . ' s' : formatDuration($rxDuration);
						$rxDevice = "";
						$rxCellClass = '';
						$rxDeviceState = getTranslation('INCOMING') . ' ( 0 )';
						$rxDuration = 0;
					} else {
						// direction from reflector
						$rxDevice = getTranslation("Network");
						$txDevice = '';
						$txDeviceState = getTranslation('OUTCOMING') . ' ( 0 )';
						$txCellClass = '';
						$txDuration = 0;
						$rxCellClass = ' active-mode-cell';						
						$rxDuration = time() - $rxDeviceStart;
						$rxDuration = $rxDuration < 60 ? $rxDuration . ' s' : formatDuration($rxDuration);
						$rxDeviceState = getTranslation('INCOMING') . ' ( ' . $rxDuration . ' )';
					}

				} else {
					$callsign = '';
					$destination = '';
					$txDeviceState = getTranslation('OUTCOMING') . ' ( 0 )';
					$rxDeviceState = getTranslation('INCOMING') . ' ( 0 )';
					$rxCellClass = ' active-mode-cell';
					$rxDeviceState = '';
				}		

				$rowClass = '';
			
			} else {
				$rxCellClass = '';
				$rxDuration = 0;
				$txDuration = 0;
				$callsign = '';
				$destination = getTranslation('Talkgroup') . ': ' . $logic['talkgroups']['selected'] . '.';
				$rowClass = 'hidden';
				$txDeviceState = getTranslation('OUTCOMING') . ' ( 0 )';
				$rxDeviceState = getTranslation('INCOMING') . ' ( 0 )';
			}
			
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