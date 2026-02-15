<?php

/**
 * @filesource /include/radio_activity.php
 * @author Vladimir Tsurkanenko <vladimir@tsurkanenko.ru>
 * @date 2026.02.14
 * @version 0.4.7
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
	} else {
		error_log("renderRadioActivityTable: Empty $_SESSION data! Recalculating status...");
		$status = getActualStatus();
		$logics = $status['logic'];
		$multipleDevices = $status['multiple_device'];
	}

	$html = '';

	foreach ($logics as $logicName => $logic) {
		$rowDevice = $logicName;
		$rxDevice = $logic['rx']['name'] ?? '';
		$row_rxDevice = htmlspecialchars($rxDevice);
		$txDevice = $logic['tx']['name'] ?? '';
		$row_txDevice = htmlspecialchars($txDevice);
		$callsign = $logic['callsign'] ?? '';
		
		if (empty($rxDevice) && empty($txDevice)) {
			if ($logic['type'] !== 'Reflector') continue;
		}
		
		$rxDeviceStart = $logic['rx']['start'];
		$txDeviceStart = $logic['tx']['start'];
		if ($rxDeviceStart > 0) {
			$rxDuration = time() - $rxDeviceStart;
			if ($rxDuration < 60) {
				$rxDuration = $rxDuration . ' s';
			} else {
				$rxDuration = formatDuration($rxDuration);
			}
		} else {
			$rxDuration = '';
		}

		if ($txDeviceStart > 0) {
			$txDuration = time() - $txDeviceStart;
			if ($txDuration < 60) {
				$txDuration = $txDuration . ' s';
			} else {
				$txDuration = formatDuration($txDuration);
			}
		} else {
			$txDuration = '';
		}


		if ($logic['type'] !== 'Reflector') {
			// Repeater or Simplex
			$rxDeviceAction = getTranslation('RECEIVE');
			$txDeviceAction = getTranslation('TRANSMIT');
			$destination = '';
			
			// Module
			if (isset($logic['module']) && is_array($logic['module'])) {
				foreach ($logic['module'] as $moduleName => $module) {
					$moduleActive = $module['is_active'] ?? false;
					$moduleConnected = $module['is_connected'] ?? false;
					if ($moduleActive || $moduleConnected) {
						$destination = $moduleName;
						break;
					}
				}
			}

			// Linked reflectors
			if (empty($destination) && isset($_SESSION['status']['link'])) {
				$linkedLogics = [];
				foreach ($_SESSION['status']['link'] as $link) {
					if (($link['is_active'] && $link['is_connected']) &&
						isset($link['source']['logic']) &&
						$link['source']['logic'] === $logicName
					) {
						if (isset($link['destination']['logic']) && !empty($link['destination']['logic'])) {
							$linkedLogics[] = $link['destination']['logic'];
						}
					}
				}
				if (!empty($linkedLogics)) {
					$destination = implode(', ', $linkedLogics);
				}
			}

			// Large font
			$rowStyle = ' style = "font-size:1.3em; text-align: center;" ';
			$rowClass = '';

		} else {
			// Reflector
			$row_rxDevice = $logic['name'];
			$row_txDevice = $logic['name'];
			$rxDeviceAction = getTranslation('INCOMING');
			$txDeviceAction = getTranslation('OUTCOMING');
			
			$rowStyle = '';
			$rowClass = $rxDeviceStart > 0 ? '' : 'hidden';
			if (!empty($logic['caller_tg'])) {
				$destination = getTranslation('Talkgroup') . ': <span class="val">' . $logic['caller_tg'] . '</span>';
			} else {
				$destination = getTranslation('Talkgroup') . ': <span class="val">' . $logic['talkgroups']['selected'] . '</span>';;
			}

			if (!empty($logic['caller_callsign'])) {
				$callsign = $logic['caller_callsign'];
				if($logic['caller_callsign'] === $logic['callsign']){
					[$rxDeviceStart, $txDeviceStart] = [$txDeviceStart, $rxDeviceStart];
					[$rxDuration, $txDuration] = [$txDuration, $rxDuration];
				}
			}
		}

		if($rxDeviceStart > 0){
			$rxCellClass = ' receiving-mode-cell';
		} else {
			$rxCellClass = ' transparent';
		}

		if ($txDeviceStart > 0) {
			$txCellClass = ' transmitting-mode-cell';
		} else {
			$txCellClass = ' transparent';
		}
		
		$rxDeviceState = $rxDeviceAction . ': <span class="val">' . $rxDuration . '</span>';
		$txDeviceState = $txDeviceAction . ': <span class="val">' . $txDuration . '</span>';

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
				<div style="width: 200px" class="divTableHeadCell"><?php echo getTranslation('Callsign') ?></div>
				<div class="divTableHeadCell"><?php echo getTranslation('Destination') ?></div>
			</div>
			<?php echo renderRadioActivityTable(); ?>
		</div>
	</div>
	<br>
</div>