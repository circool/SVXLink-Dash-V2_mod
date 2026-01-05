<?php

/**
 * @version 0.3.1
 * @since 0.2.0
 * @date 2026.01.04
 * @author vladimir@tsurkanenko.ru
 * @filesource /include/exct/reflector_activity.0.3.1.php 
 * @copyright vladimir@tsurkanenko.ru
 * @description Страница информации о рефлекторах
 * @note Адаптирован под 0.2
 */

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/init.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getTranslation.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/dlog.0.2.php';
$ver = 'reflector_activity.0.3.1';

// Проверяем, является ли это AJAX запросом для управления линками
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_link'])) {
	header('Content-Type: application/json');

	// Проверяем необходимые параметры
	$requiredParams = ['logic_name', 'dtmf_path', 'dtmf_command'];
	foreach ($requiredParams as $param) {
		if (!isset($_POST[$param]) || trim($_POST[$param]) === '') {
			echo json_encode(['status' => 'error', 'message' => "Missing or empty parameter: $param"]);
			exit;
		}
	}

	$logicName = trim($_POST['logic_name']);
	$dtmfPath = trim($_POST['dtmf_path']);
	$dtmfCommand = trim($_POST['dtmf_command']);
	$newState = trim($_POST['new_state'] ?? 'unknown');

	// Добавляем # в конец команды
	if (!empty($dtmfCommand) && substr($dtmfCommand, -1) !== '#') {
		$dtmfCommand .= '#';
	}

	// Логируем запрос - уровень 3 (информационный)
	if (defined('DEBUG') && DEBUG) {
		dlog("$ver: Запрос состояния логики $logicName, состояние: $newState, команда: $dtmfCommand, путь: $dtmfPath", 3, "INFO");
	}

	try {
		// Проверяем существование пути DTMF
		if (!file_exists($dtmfPath)) {
			if (defined('DEBUG') && DEBUG) {
				dlog("DTMF path does not exist: $dtmfPath", 2, "ERROR");
			}
			echo json_encode(['status' => 'error', 'message' => 'DTMF path does not exist']);
			exit;
		}

		// Проверяем, доступен ли файл для записи
		if (!is_writable($dtmfPath)) {
			if (defined('DEBUG') && DEBUG) {
				dlog("DTMF path is not writable: $dtmfPath", 2, "ERROR");
			}
			echo json_encode(['status' => 'error', 'message' => 'DTMF path is not writable']);
			exit;
		}

		// Проверяем допустимые символы в команде DTMF (после добавления #)
		if (!preg_match('/^[0-9A-D\*#]+$/i', $dtmfCommand)) {
			if (defined('DEBUG') && DEBUG) {
				dlog("Invalid DTMF command characters: $dtmfCommand", 2, "ERROR");
			}
			echo json_encode(['status' => 'error', 'message' => 'Invalid DTMF command characters. Only 0-9, A-D, *, # allowed']);
			exit;
		}

		// Отправляем команду DTMF - уровень 4 (детальная отладка)
		$escapedCommand = escapeshellcmd($dtmfCommand);
		$escapedPath = escapeshellarg($dtmfPath);
		$command = 'echo ' . $escapedCommand . ' > ' . $escapedPath . ' 2>&1';

		if (defined('DEBUG') && DEBUG) {
			dlog("Executing command: $command", 4, "DEBUG");
		}

		$output = [];
		$return_var = 0;
		$result = exec($command, $output, $return_var);

		// Логируем результат
		if (defined('DEBUG') && DEBUG) {
			dlog("Command result: return_var=$return_var, output=" . implode(', ', $output), 4, "DEBUG");
			if ($return_var === 0) {
				dlog("Link state changed successfully for $logicName: $newState, command: $dtmfCommand", 3, "INFO");
			} else {
				dlog("Failed to change link state for $logicName, exit code: $return_var, error: " . implode(', ', $output), 2, "ERROR");
			}
		}

		if ($return_var === 0) {
			$action = $newState === 'active' ? 'activated' : 'deactivated';
			echo json_encode([
				'status' => 'success',
				'message' => "Link $logicName $action successfully"
			]);
		} else {
			$errorMsg = !empty($output) ? implode(', ', $output) : 'Failed to execute DTMF command';
			echo json_encode([
				'status' => 'error',
				'message' => $errorMsg
			]);
		}
	} catch (Exception $e) {
		if (defined('DEBUG') && DEBUG) {
			dlog("Exception in link action: " . $e->getMessage(), 1, "ERROR");
		}
		echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
	} catch (Throwable $e) {
		if (defined('DEBUG') && DEBUG) {
			dlog("Throwable in link action: " . $e->getMessage(), 1, "ERROR");
		}
		echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
	}

	exit;
}

// Получаем данные из сессии
$status = $_SESSION['status'];
$refl_logics = $status['logic'] ?? [];
$refl_links = $status['link'] ?? [];

echo '<div id="refl_header" class="larger" style="vertical-align: bottom; font-weight:bold;text-align:left;margin-top:-12px;">';
echo getTranslation('Reflectors Info');
echo '</div>';

foreach ($refl_logics as $refl_name => $refl_logic) {
	if ($refl_logic['type'] !== 'Reflector' ) {
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
				$activateCmd = $link['source']['command']['activate_command'] ?? '';
				$deactivateCmd = $link['source']['command']['deactivate_command'] ?? '';
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
	$toggleId = "toggle-link-state-" . htmlspecialchars($refl_name);
	$checkedAttr = $isLinkConnected ? 'checked' : '';
	$disabledAttr = !$isToggleEnabled ? 'disabled' : '';
	$titleText = $isToggleEnabled ?
		getTranslation('Unlink reflector from logic') :
		getTranslation('DTMF not configured for source logic');
	$textColor = $isToggleEnabled ? 'inherit' : '#999';

	// Отладочная информация
	if (defined('DEBUG') && DEBUG) {
		dlog("Reflector: $refl_name, Link: $foundLinkName, Connected: " . ($isLinkConnected ? 'true' : 'false') . ", Toggle enabled: " . ($isToggleEnabled ? 'true' : 'false'), 4, "DEBUG");
	}

	echo '<div style="float: right; vertical-align: bottom; padding-top: 0px;" id="unl' . htmlspecialchars($refl_name) . '">';
	echo '<div class="grid-container" style="display: inline-grid; grid-template-columns: auto 40px; padding: 1px;; grid-column-gap: 5px;">';
	echo '<div class="grid-item link-control" style="padding: 10px 0 0 20px; color: ' . $textColor . ';" title="' . htmlspecialchars($titleText) . '">';
	echo getTranslation('Link/Unlink');
	echo '</div>';
	echo '<div class="grid-item">';
	echo '<div style="padding-top:6px; position: relative;">';
	echo '<input id="' . $toggleId . '" class="toggle toggle-round-flat link-toggle" type="checkbox"';
	echo ' name="display-lastcaller" value="ON" aria-label="' . htmlspecialchars($titleText) . '"';
	echo ' data-logic-name="' . htmlspecialchars($refl_name) . '"';
	echo ' data-activate-cmd="' . htmlspecialchars($activateCmd) . '"';
	echo ' data-deactivate-cmd="' . htmlspecialchars($deactivateCmd) . '"';
	echo ' data-dtmf-path="' . htmlspecialchars($dtmfPath) . '"';
	echo ' ' . $checkedAttr . ' ' . $disabledAttr;
	echo ' onchange="setLinkState(this)">';
	echo '<label for="' . $toggleId . '"></label>';

	if (!$isToggleEnabled) {
		echo '<div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; 
                background: rgba(0,0,0,0.3); border-radius: 50px; cursor: not-allowed; z-index: 1;"
                title="' . getTranslation('DTMF not configured') . '"></div>';
	}
	echo '</div>';
	echo '</div>';
	echo '</div>';
	echo '</div>';

	echo '<div class="larger" style="vertical-align: bottom; font-weight:bold;text-align:left;margin-top:12px;">';
	echo htmlspecialchars($refl_name);
	echo '</div>';
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

	echo '
    <table style="word-wrap: break-word; white-space:normal;">
        <tbody>
            <tr>
                <th><a class="tooltip" href="#">Date/Time<span><b>Current Date and Time</b></span></a></th>
                <th width="150px"><a class="tooltip" href="#">Selected Talkgroup<span><b>Talkgroup</b></span></th>
                <th class="noMob"><a class="tooltip" href="#">Monitored Talkgroups<span><b>Talkgroups</b></span></th>
                <th class="noMob"><a class="tooltip" href="#">Temporary Monitored Talkgroups<span><b>Talkgroups</b></span></th>

                <th><a class="tooltip" href="#">Logic<span><b>Linked Logic</b></span></a></th>
                <th><a class="tooltip" href="#">Host<span><b>Host</b></span></a></th>
                <th><a class="tooltip" href="#">Duration (s)<span><b>Duration in Seconds</b></span></a></th>
            </tr>

            <tr>
                <td>' . htmlspecialchars(date('d.m.y H:i')) . '</td>
                <td>' . htmlspecialchars($selectedTG) . '</td>
                <td>' . htmlspecialchars($monitoringTG) . '</td>
                <td>' . htmlspecialchars($tempMonitoringTG) . '</td>

                <td>'. htmlspecialchars($sourceLogicName). '</td>
                <td>' . htmlspecialchars($hosts) . '</td>
                <td></td>
            </tr>
        </tbody>
    </table>';
}

?>
<script>
	// Функция для управления состоянием линков
	function setLinkState(toggleElement) {
		// Проверяем, не заблокирован ли тумблер
		if (toggleElement.disabled) {
			console.log('Toggle is disabled, ignoring change');
			return;
		}

		const logicName = toggleElement.getAttribute('data-logic-name');
		let activateCmd = toggleElement.getAttribute('data-activate-cmd');
		let deactivateCmd = toggleElement.getAttribute('data-deactivate-cmd');
		const dtmfPath = toggleElement.getAttribute('data-dtmf-path');
		const isChecked = toggleElement.checked;

		console.log('Link state changed:', {
			logicName,
			activateCmd,
			deactivateCmd,
			dtmfPath,
			isChecked
		});

		// Дополнительная проверка на стороне клиента
		if (!dtmfPath || dtmfPath.trim() === '') {
			showLinkToast('DTMF path not configured for this link', 'error');
			toggleElement.checked = !isChecked; // Возвращаем исходное состояние
			return;
		}

		// Определяем какую команду отправлять
		let dtmfCommand = isChecked ? activateCmd : deactivateCmd;

		if (!dtmfCommand || dtmfCommand.trim() === '') {
			const action = isChecked ? 'activate' : 'deactivate';
			showLinkToast(`${action} command not configured for this link`, 'error');
			toggleElement.checked = !isChecked; // Возвращаем исходное состояние
			return;
		}

		// Визуальная обратная связь - блокируем тумблер на время запроса
		toggleElement.disabled = true;
		const originalOpacity = toggleElement.style.opacity;
		toggleElement.style.opacity = '0.7';

		// Отправляем AJAX запрос
		const formData = new FormData();
		formData.append('link_action', 'set_state');
		formData.append('logic_name', logicName);
		formData.append('dtmf_path', dtmfPath);
		formData.append('dtmf_command', dtmfCommand);
		formData.append('new_state', isChecked ? 'active' : 'inactive');
		formData.append('ajax_link', 'true');

		fetch('/include/reflector_activity.php', {
				method: 'POST',
				body: formData
			})
			.then(response => {
				if (!response.ok) {
					throw new Error('HTTP error: ' + response.status);
				}
				return response.json();
			})
			.then(data => {
				console.log('Link state response:', data);

				if (data && data.status === 'success') {
					showLinkToast(data.message, 'success');
					// Тумблер остается в новом положении
				} else {
					const errorMsg = data ? data.message : 'Unknown error';
					showLinkToast(errorMsg, 'error');
					// Откатываем состояние тумблера при ошибке
					toggleElement.checked = !isChecked;
				}
			})
			.catch(error => {
				console.error('Link state error:', error);
				showLinkToast('Error: ' + error.message, 'error');
				// Откатываем состояние тумблера при ошибке
				toggleElement.checked = !isChecked;
			})
			.finally(() => {
				// Восстанавливаем доступность тумблера
				toggleElement.disabled = false;
				toggleElement.style.opacity = originalOpacity || '';
			});
	}

	// Функция показа уведомлений для линков
	function showLinkToast(message, type) {
		// Проверяем, существует ли уже контейнер для тостов
		let toastContainer = document.getElementById('linkToastContainer');
		if (!toastContainer) {
			toastContainer = document.createElement('div');
			toastContainer.id = 'linkToastContainer';
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

		// Создаем новое уведомление
		const toast = document.createElement('div');
		toast.className = 'link-toast ' + type;
		toast.style.cssText = `
            padding: 12px 20px;
            border-radius: 6px;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
            color: white;
            background: ${type === 'success' ? '#2c7f2c' : '#8C0C26'};
            opacity: 0;
            transform: translateX(100%);
            transition: opacity 0.3s ease, transform 0.3s ease;
            min-width: 250px;
            max-width: 350px;
            word-wrap: break-word;
        `;
		toast.innerHTML = message;
		toastContainer.appendChild(toast);

		// Плавное появление
		setTimeout(() => {
			toast.style.opacity = '1';
			toast.style.transform = 'translateX(0)';
		}, 10);

		// Автоматически скрываем через 3 секунды
		setTimeout(() => {
			toast.style.opacity = '0';
			toast.style.transform = 'translateX(100%)';
			setTimeout(() => {
				if (toast.parentNode === toastContainer) {
					toastContainer.removeChild(toast);
				}
			}, 300);
		}, 3000);
	}

	// Проверяем, существует ли уже обработчик
	if (typeof window.linkStateHandlerInitialized === 'undefined') {
		window.linkStateHandlerInitialized = true;

		// Инициализируем функцию глобально
		window.setLinkState = setLinkState;
		window.showLinkToast = showLinkToast;
	}
</script>