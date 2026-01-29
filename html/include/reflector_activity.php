<?php

/**
 * @version 0.4.3.release
 * @date 2026.01.29
 * @author vladimir@tsurkanenko.ru
 * @filesource /include/reflector_activity.php 
 */

if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
	header('Content-Type: text/html; charset=utf-8');
	$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html';
	require_once $docRoot . '/include/settings.php';

	$requiredFiles = [
		'/include/fn/getTranslation.php',
		'/include/fn/logTailer.php',
		'/include/fn/formatDuration.php',
		'/include/fn/getLineTime.php',
		'/include/fn/getReflectorHistory.php'
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
	echo getReflectorActivityContent();
	exit;
}

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/init.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getTranslation.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/logTailer.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/formatDuration.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getLineTime.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getReflectorHistory.php';

function getReflectorActivityContent(): string
{
	if (isset($_SESSION['TIMEZONE'])) {
		date_default_timezone_set($_SESSION['TIMEZONE']);
	}

	$status = $_SESSION['status'];
	$refl_logics = $status['logic'] ?? [];
	$refl_links = $status['link'] ?? [];
	$html = '';
	$html .= '<div id="refl_header" class="larger" style="vertical-align: bottom; font-weight:bold;text-align:left;margin-top:-12px;">';
	$html .= getTranslation('Reflectors Info');
	$html .= '</div>';

	foreach ($refl_logics as $refl_name => $refl_logic) {
		if ($refl_logic['type'] !== 'Reflector') {
			continue;
		}

		$linkData = null;
		$activateCmd = '';
		$deactivateCmd = '';
		$sourceLogicName = '';
		$isLinkConnected = false;
		$isToggleEnabled = false;
		$foundLinkName = '';

		foreach ($refl_links as $linkName => $link) {
			if (
				isset($link['destination']['logic']) &&
				$link['destination']['logic'] === $refl_name
			) {
				$linkData = $link;
				$foundLinkName = $linkName;

				if (isset($link['is_connected'])) {
					if (is_string($link['is_connected'])) {
						$isLinkConnected = ($link['is_connected'] === '1' || $link['is_connected'] === 'true');
					} else {
						$isLinkConnected = (bool)$link['is_connected'];
					}
				} else {
					$isLinkConnected = (bool)($refl_logic['is_connected'] ?? false);
				}

				if (isset($link['source']['command'])) {
					$activateCmd = $link['source']['command']['activate_command'] ?? '';
					$deactivateCmd = $link['source']['command']['deactivate_command'] ?? '';
				}

				$sourceLogicName = $link['source']['logic'] ?? '';
				if ($sourceLogicName && isset($refl_logics[$sourceLogicName])) {
					$dtmfCmd = $refl_logics[$sourceLogicName]['dtmf_cmd'] ?? '';
					$isToggleEnabled = !empty($dtmfCmd);
				}
				break;
			}
		}

		$toggleId = "toggle-link-state-" . htmlspecialchars($foundLinkName);
		$checkedAttr = $isLinkConnected ? 'checked' : '';
		$disabledAttr = !$isToggleEnabled ? 'disabled' : '';
		$titleText = $isToggleEnabled ?
			getTranslation('Unlink reflector from logic') :
			getTranslation('DTMF not configured for source logic');
		$textColor = $isToggleEnabled ? 'inherit' : '#999';

		$html .= '<div style="float: right; vertical-align: bottom; padding-top: 0px;" id="unl' . htmlspecialchars($refl_name) . '">';
		$html .= '<div class="grid-container" style="display: inline-grid; grid-template-columns: auto 40px; padding: 1px;; grid-column-gap: 5px;">';
		$html .= '<div class="grid-item link-control" style="padding: 10px 0 0 20px; color: ' . $textColor . ';" title="' . htmlspecialchars($titleText) . '">';
		$html .= getTranslation('Link/Unlink');
		$html .= '</div>';
		$html .= '<div class="grid-item">';
		$html .= '<div style="padding-top:6px; position: relative;">';
		$html .= '<input id="' . $toggleId . '" class="toggle toggle-round-flat link-toggle" type="checkbox"';
		$html .= ' name="display-lastcaller" value="ON" aria-label="' . htmlspecialchars($titleText) . '"';
		$html .= ' data-reflector-name="' . htmlspecialchars($refl_name) . '"';
		$html .= ' data-source-logic="' . htmlspecialchars($sourceLogicName) . '"';
		$html .= ' data-activate-cmd="' . htmlspecialchars($activateCmd) . '"';
		$html .= ' data-deactivate-cmd="' . htmlspecialchars($deactivateCmd) . '"';
		$html .= ' ' . $checkedAttr . ' ' . $disabledAttr;
		$html .= ' onchange="setLinkState(this)">';
		$html .= '<label for="' . $toggleId . '"></label>';

		if (!$isToggleEnabled) {
			$html .= '<div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; 
                background: rgba(0,0,0,0.3); border-radius: 50px; cursor: not-allowed; z-index: 1;"
                title="' . getTranslation('DTMF not configured') . '"></div>';
		}
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<div class="larger" style="vertical-align: bottom; font-weight:bold;text-align:left;margin-top:12px;">';
		$html .= htmlspecialchars($refl_name);
		$html .= '</div>';

		$selectedTG = $refl_logic['talkgroups']['selected'] ?? '';
		if (is_array($selectedTG)) {
			$selectedTG = implode(', ', $selectedTG);
		}

		$monitoringTG = isset($refl_logic['talkgroups']['monitoring']) && is_array($refl_logic['talkgroups']['monitoring'])
			? implode(', ', $refl_logic['talkgroups']['monitoring'])
			: '';

		$tempMonitoringTG = $refl_logic['talkgroups']['temp_monitoring'] ?? '';
		if (is_array($tempMonitoringTG)) {
			$tempMonitoringTG = implode(', ', $tempMonitoringTG);
		}

		$hosts = $refl_logic['hosts'] ?? '';
		if (is_array($hosts)) {
			$hosts = implode(', ', $hosts);
		}

		$duration = empty($refl_logic['start']) ? '' : formatDuration(time() - $refl_logic['start']);

		$html .= '<table style="word-wrap: break-word; white-space:normal;">';
		$html .= '<tbody>';
		$html .= '<tr>';
		$html .= '<th width="150px"><a class="tooltip" href="#">' . getTranslation('Date') . '<span><b>' . getTranslation('Current Date') . '</b></span></a></th>';
		$html .= '<th width="150px"><a class="tooltip" href="#">' . getTranslation('Time') . '<span><b>' . getTranslation('Current Time') . '</b></span></a></th>';
		$html .= '<th width="150px"><a class="tooltip" href="#">' . getTranslation('Selected Talkgroup') . '<span><b>' . getTranslation('Talkgroup') . '</b></span></th>';
		$html .= '<th><a class="tooltip" href="#">' . getTranslation('Monitored Talkgroups') . '<span><b>' . getTranslation('Talkgroups') . '</b></span></th>';
		$html .= '<th><a class="tooltip" href="#">' . getTranslation('Temporary Monitored Talkgroups') . '<span><b>' . getTranslation('Talkgroups') . '</b></span></th>';
		$html .= '<th><a class="tooltip" href="#">' . getTranslation('Logic') . '<span><b>' . getTranslation('Linked Logic') . '</b></span></a></th>';
		$html .= '<th><a class="tooltip" href="#">' . getTranslation('Host') . '<span><b>' . getTranslation('Host') . '</b></span></a></th>';
		$html .= '<th width="150px"><a class="tooltip" href="#">' . getTranslation('Duration') . '<span><b>' . getTranslation('Duration in Seconds') . '</b></span></a></th>';
		$html .= '</tr>';

		$html .= '<tr>';
		$html .= '<td>' . htmlspecialchars(date('d.m.y')) . '</td>';
		$html .= '<td>' . htmlspecialchars(date('H:i:s')) . '</td>';
		$html .= '<td>' . htmlspecialchars($selectedTG) . '</td>';
		$html .= '<td>' . htmlspecialchars($monitoringTG) . '</td>';
		$html .= '<td>' . htmlspecialchars($tempMonitoringTG) . '</td>';
		$html .= '<td>' . htmlspecialchars($sourceLogicName) . '</td>';
		$html .= '<td>' . htmlspecialchars($hosts) . '</td>';
		$html .= '<td>' . htmlspecialchars($duration) . '</td>';
		$html .= '</tr>';
		$html .= '</tbody>';
		$html .= '</table>';

		$history = getReflectorHistory($refl_name);

		$html .= '<div class="larger" style="vertical-align: bottom; font-weight:bold;text-align:left;margin-top:12px;">';
		$html .= getTranslation('Last') . ' ' . REFLECTOR_ACTIVITY_LIMIT . ' ' . getTranslation('Actions');
		$html .= '</div>';

		$html .= '<table style="word-wrap: break-word; white-space:normal;">';
		$html .= '<tbody>';
		$html .= '<tr>';
		$html .= '<th width="150px"><a class="tooltip" href="#">' . getTranslation('Date') . '<span><b>' . getTranslation('Current Date') . '</b></span></a></th>';
		$html .= '<th width="150px"><a class="tooltip" href="#">' . getTranslation('Time') . '<span><b>' . getTranslation('Current Time') . '</b></span></a></th>';
		$html .= '<th width="150px"><a class="tooltip" href="#">' . getTranslation('Selected Talkgroup') . '<span><b>' . getTranslation('Talkgroup') . '</b></span></th>';
		$html .= '<th><a class="tooltip" href="#">' . getTranslation('Callsign') . '<span><b>' . getTranslation('Talker') . '</b></span></th>';
		$html .= '<th width="150px"><a class="tooltip" href="#">' . getTranslation('Duration') . '<span><b>' . getTranslation('Duration in Seconds') . '</b></span></a></th>';
		$html .= '</tr>';

		if (empty($history)) {
			$html .= '<tr>';
			$html .= '<td colspan="5" style="text-align: center;">' . getTranslation('No activity history found') . '</td>';
			$html .= '</tr>';
		} else {
			foreach ($history as $event) {
				$html .= '<tr>';
				$html .= '<td>' . htmlspecialchars(date('d.m.y', $event['end'])) . '</td>';
				$html .= '<td>' . htmlspecialchars(date('H:i:s', $event['end'])) . '</td>';
				$html .= '<td>' . htmlspecialchars($event['tg']) . '</td>';
				$html .= '<td>' . htmlspecialchars($event['callsign']) . '</td>';
				$html .= '<td>' . formatDuration($event['duration']) . '</td>';
				$html .= '</tr>';
			}
		}

		$html .= '</tbody>';
		$html .= '</table>';
		$html .= '<br>';
	}

	$html .= '<script>
    function setLinkState(toggleElement) {
        if (toggleElement.disabled) return;
        
        const reflectorName = toggleElement.getAttribute("data-reflector-name");
        const sourceLogicName = toggleElement.getAttribute("data-source-logic");
        let activateCmd = toggleElement.getAttribute("data-activate-cmd");
        let deactivateCmd = toggleElement.getAttribute("data-deactivate-cmd");
        const isChecked = toggleElement.checked;
        
        if (!sourceLogicName || sourceLogicName.trim() === "") {
            console.error("Source logic not configured for:", reflectorName);
            toggleElement.checked = !isChecked;
            return;
        }
        
        let dtmfCommand = isChecked ? activateCmd : deactivateCmd;
        if (!dtmfCommand || dtmfCommand.trim() === "") {
            const action = isChecked ? "activate" : "deactivate";
            console.error(action + " command not configured for:", reflectorName);
            toggleElement.checked = !isChecked;
            return;
        }
        
        if (!dtmfCommand.endsWith("#")) {
            dtmfCommand += "#";
        }
        
        toggleElement.disabled = true;
        const originalOpacity = toggleElement.style.opacity;
        toggleElement.style.opacity = "0.7";
        
        fetch("/include/dtmf_handler.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: new URLSearchParams({
                command: dtmfCommand,
                source: sourceLogicName,
                ajax_link: "true"
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error("HTTP error: " + response.status);
            }
            return response.text();
        })
        .then(jsCode => {

            try {
                eval(jsCode);
            } catch (e) {
                console.error("Error executing toast JS:", e);
            }
        })
        .catch(error => {
            console.error("Network error:", error);
            // Откатываем состояние тоггла при ошибке сети
            toggleElement.checked = !isChecked;
        })
        .finally(() => {
            toggleElement.disabled = false;
            toggleElement.style.opacity = originalOpacity || "";
        });
    }
    
    if (typeof window.linkStateHandlerInitialized === "undefined") {
        window.linkStateHandlerInitialized = true;
        window.setLinkState = setLinkState;
    }
    </script>';

	return $html;
}
?>
<div id="reflector_activity">
	<div id="reflector_activity_content">
		<?php echo getReflectorActivityContent(); ?>
	</div>
</div>