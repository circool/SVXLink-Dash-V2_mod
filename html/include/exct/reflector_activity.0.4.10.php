<?php

/**
 * @version 0.4.2
 * @since 0.2.0
 * @date 2026.01.22
 * @author vladimir@tsurkanenko.ru
 * @filesource /include/exct/reflector_activity.0.4.2.php 
 * @description Страница информации о рефлекторах
 * @note Изменения в 0.4.2:
 * - Добавлен контейнер для обновляемых данных с id="reflector_activity_content"
 * - Заголовок вынесен из обновляемой части
 * - Упрощен AJAX обработчик, используется единый dtmf_handler.php
 */

// AJAX режим - минимальная инициализация
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
	// Устанавливаем заголовок
	header('Content-Type: text/html; charset=utf-8');

	// Минимальные зависимости для AJAX режима
	$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html';

	// Подключаем settings для констант
	require_once $docRoot . '/include/settings.php';

	// Подключаем необходимые файлы
	$requiredFiles = [
		'/include/fn/getTranslation.php',
		'/include/fn/logTailer.php',
		'/include/fn/formatDuration.php',
		'/include/fn/getLineTime.php'
	];

	foreach ($requiredFiles as $file) {
		$fullPath = $docRoot . $file;
		if (file_exists($fullPath)) {
			require_once $fullPath;
		}
	}

	// Подключаем dlog если DEBUG включен
	if (defined("DEBUG") && DEBUG) {
		$dlogFile = $docRoot . '/include/fn/dlog.php';
		if (file_exists($dlogFile)) {
			require_once $dlogFile;
		}
	}

	// Начинаем сессию для AJAX
	if (session_status() === PHP_SESSION_NONE) {
		session_name(SESSION_NAME);
		session_start();
	}

	// Сразу освобождаем сессию
	session_write_close();

	// В AJAX режиме возвращаем только контент без внешнего div
	echo getReflectorActivityContent();
	exit;
}

if (defined("DEBUG") && DEBUG) {
	$funct_start = microtime(true);
	$ver = 'reflector_activity.0.4.2';
	dlog("$ver: Начинаю выполнение", 4, "DEBUG");
}

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/init.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getTranslation.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/logTailer.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/formatDuration.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getLineTime.php';

// Проверяем, является ли это AJAX запросом для управления линками
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_link'])) {
	require_once $_SERVER["DOCUMENT_ROOT"] . '/include/dtmf_handler.php';
	exit;
}

/**
 * Генерирует контент для блока reflector_activity
 */
function getReflectorActivityContent(): string
{
	if (isset($_SESSION['TIMEZONE'])) {
		date_default_timezone_set($_SESSION['TIMEZONE']);
	}
	// Получаем данные из сессии
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

		// Находим соответствующий линк для этого рефлектора
		$linkData = null;
		$activateCmd = '';
		$deactivateCmd = '';
		$dtmfPath = '';
		$isLinkConnected = false;
		$isToggleEnabled = false;
		$foundLinkName = '';
		$sourceLogicName = '';

		// Поиск линка, где рефлектор является целью (destination)
		foreach ($refl_links as $linkName => $link) {
			if (
				isset($link['destination']['logic']) &&
				$link['destination']['logic'] === $refl_name
			) {
				$linkData = $link;
				$foundLinkName = $linkName;

				// Определяем состояние подключения
				if (isset($link['is_connected'])) {
					if (is_string($link['is_connected'])) {
						$isLinkConnected = ($link['is_connected'] === '1' || $link['is_connected'] === 'true');
					} else {
						$isLinkConnected = (bool)$link['is_connected'];
					}
				} else {
					// Если is_connected нет, используем состояние рефлектора
					$isLinkConnected = (bool)($refl_logic['is_connected'] ?? false);
				}

				// Получаем команды DTMF
				if (isset($link['source']['command'])) {
					$activateCmd = $link['source']['command']['activate_command'] ?? '' . '#';
					$deactivateCmd = $link['source']['command']['deactivate_command'] ?? '' . '#';
				}

				// Находим путь DTMF из логики-источника
				$sourceLogicName = $link['source']['logic'] ?? '';
				if ($sourceLogicName && isset($refl_logics[$sourceLogicName])) {
					$dtmfPath = $refl_logics[$sourceLogicName]['dtmf_cmd'] ?? '';
					$isToggleEnabled = !empty($dtmfPath);
				}
				break;
			}
		}

		// Определяем атрибуты для тумблера 
		$toggleId = "toggle-link-state-" . htmlspecialchars($foundLinkName);
		$checkedAttr = $isLinkConnected ? 'checked' : '';
		$disabledAttr = !$isToggleEnabled ? 'disabled' : '';
		$titleText = $isToggleEnabled ?
			getTranslation('Unlink reflector from logic') :
			getTranslation('DTMF not configured for source logic');
		$textColor = $isToggleEnabled ? 'inherit' : '#999';

		// @bookmark Тумблер состояния линка
		$html .= '<div style="float: right; vertical-align: bottom; padding-top: 0px;" id="unl' . htmlspecialchars($refl_name) . '">';
		$html .= '<div class="grid-container" style="display: inline-grid; grid-template-columns: auto 40px; padding: 1px;; grid-column-gap: 5px;">';
		$html .= '<div class="grid-item link-control" style="padding: 10px 0 0 20px; color: ' . $textColor . ';" title="' . htmlspecialchars($titleText) . '">';
		$html .= getTranslation('Link/Unlink');
		$html .= '</div>';
		$html .= '<div class="grid-item">';
		$html .= '<div style="padding-top:6px; position: relative;">';
		$html .= '<input id="' . $toggleId . '" class="toggle toggle-round-flat link-toggle" type="checkbox"';
		$html .= ' name="display-lastcaller" value="ON" aria-label="' . htmlspecialchars($titleText) . '"';
		$html .= ' data-logic-name="' . htmlspecialchars($refl_name) . '"';
		$html .= ' data-activate-cmd="' . htmlspecialchars($activateCmd) . '"';
		$html .= ' data-deactivate-cmd="' . htmlspecialchars($deactivateCmd) . '"';
		$html .= ' data-dtmf-path="' . htmlspecialchars($dtmfPath) . '"';
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

		// @bookmark Шапка таблицы линка/рефлектора
		$html .= '<div class="larger" style="vertical-align: bottom; font-weight:bold;text-align:left;margin-top:12px;">';
		$html .= htmlspecialchars($refl_name);
		$html .= '</div>';

		// Получаем значения talkgroups с проверкой типов
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
		// $duration = empty($refl_logic['duration']) ? '' : formatDuration($refl_logic['duration']);
		
		// @bookmark Таблица линка/рефлектора 
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

		/**
		 * Статистика активности рефлектора
		 */
		function getReflectorHistory(string $reflector_name): array
		{
			require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/logTailer.php';

			$required_condition = $reflector_name;
			$or_conditions = ['Talker start on TG', 'Talker stop on TG'];
			$limit = REFLECTOR_ACTIVITY_LIMIT * 5;

			if (isset($_SESSION['status']['service']['log_line_count']) && $_SESSION['status']['service']['log_line_count'] > 0) {
				$session_log_size = $_SESSION['status']['service']['log_line_count'];
			} else {
				$session_log_size = countLogLines("Tobias Blomberg");
			}

			$refl_history = getLogTailFiltered($limit, $required_condition, $or_conditions, $session_log_size);

			$result = [];
			$open_events = []; // Временное хранилище для открытых событий

			if ($refl_history !== false) {
				foreach ($refl_history as $line) {
					if (strpos($line, 'Talker start on TG #') !== false) {
						preg_match('/:\s*([^:]+):\s*Talker start on TG #(\d+):\s*([^\s]+)/', $line, $m);
						if (isset($m[1], $m[2], $m[3])) {
							$event_start = getLineTime($line);
							$key = $m[2] . '|' . $m[3]; // Ключ по TG и callsign

							$open_events[$key] = [
								'reflector' => $m[1],
								'tg' => (int)$m[2],
								'callsign' => $m[3],
								'start' => $event_start,
								'start_line' => $line
							];
						}
					} else if (strpos($line, 'Talker stop on TG #') !== false) {
						preg_match('/:\s*([^:]+):\s*Talker stop on TG #(\d+):\s*([^\s]+)/', $line, $m);
						if (isset($m[1], $m[2], $m[3])) {
							$event_end = getLineTime($line);
							$key = $m[2] . '|' . $m[3];

							// Если есть соответствующее открытое событие
							if (isset($open_events[$key])) {
								$event_start = $open_events[$key]['start'];
								$duration = $event_end - $event_start;
								if ($duration > 0) {
									// Добавляем завершенное событие в результат
									$result[] = [
										'reflector' => $m[1],
										'tg' => (int)$m[2],
										'callsign' => $m[3],
										'start' => $event_start,
										'end' => $event_end,
										'duration' => $duration
									];
								}


								// Удаляем из массива открытых событий
								unset($open_events[$key]);
							}
						}
					}
				}

				// Сортируем результат по времени окончания (самые свежие первые)
				usort($result, function ($a, $b) {
					return $b['end'] - $a['end'];
				});

				// Ограничиваем количество результатов
				$result = array_slice($result, 0, REFLECTOR_ACTIVITY_LIMIT);
			}

			return $result;
		}

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

	// JavaScript для управления линками
	$html .= '<script>
    function setLinkState(toggleElement) {
        if (toggleElement.disabled) return;
        
        const logicName = toggleElement.getAttribute("data-logic-name");
        let activateCmd = toggleElement.getAttribute("data-activate-cmd");
        let deactivateCmd = toggleElement.getAttribute("data-deactivate-cmd");
        const dtmfPath = toggleElement.getAttribute("data-dtmf-path");
        const isChecked = toggleElement.checked;
        
        if (!dtmfPath || dtmfPath.trim() === "") {
            showLinkToast("DTMF path not configured for this link", "error");
            toggleElement.checked = !isChecked;
            return;
        }
        
        let dtmfCommand = isChecked ? activateCmd : deactivateCmd;
        if (!dtmfCommand || dtmfCommand.trim() === "") {
            const action = isChecked ? "activate" : "deactivate";
            showLinkToast(`${action} command not configured for this link`, "error");
            toggleElement.checked = !isChecked;
            return;
        }
        
        toggleElement.disabled = true;
        const originalOpacity = toggleElement.style.opacity;
        toggleElement.style.opacity = "0.7";
        
        dtmfCommand += "#";
        
        fetch(window.location.href, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: new URLSearchParams({
                command: dtmfCommand,
                source: "reflector_link",
                ajax_link: "true"
            })
        }).finally(() => {
            toggleElement.disabled = false;
            toggleElement.style.opacity = originalOpacity || "";
            const action = isChecked ? "activated" : "deactivated";
            showLinkToast(`Link ${logicName} ${action}`, "success");
        });
    }
    
    function showLinkToast(message, type) {
        let toastContainer = document.getElementById("linkToastContainer");
        if (!toastContainer) {
            toastContainer = document.createElement("div");
            toastContainer.id = "linkToastContainer";
            toastContainer.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10001;
                display: flex;
                flex-direction: column;
                gap: 10px;
            `;
            document.body.appendChild(toastContainer);
        }
        
        const toast = document.createElement("div");
        toast.className = "link-toast " + type;
        toast.style.cssText = `
            padding: 12px 20px;
            border-radius: 6px;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
            color: white;
            background: ${type === "success" ? "#2c7f2c" : "#8C0C26"};
            opacity: 0;
            transform: translateX(100%);
            transition: opacity 0.3s ease, transform 0.3s ease;
            min-width: 250px;
            max-width: 350px;
            word-wrap: break-word;
        `;
        toast.innerHTML = message;
        toastContainer.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = "1";
            toast.style.transform = "translateX(0)";
        }, 10);
        
        setTimeout(() => {
            toast.style.opacity = "0";
            toast.style.transform = "translateX(100%)";
            setTimeout(() => {
                if (toast.parentNode === toastContainer) {
                    toastContainer.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }
    
    if (typeof window.linkStateHandlerInitialized === "undefined") {
        window.linkStateHandlerInitialized = true;
        window.setLinkState = setLinkState;
        window.showLinkToast = showLinkToast;
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
<br>

<?php
if (defined("DEBUG") && DEBUG) {
	$funct_time = microtime(true) - $funct_start;
	dlog("$ver: Закончил работу за $funct_time мсек", 3, "INFO");
	unset($ver, $funct_start, $funct_time);
}
