<?php

/**
 * @filesource /include/exct/keypad.0.4.0.php
 * @simlink /include/keypad.php 
 * @version 0.4.0
 * @date 2026-01-07
 * @author vladimir@tsurkanenko.ru
 * @todo Убрать стили в menu.css
 * @note Изменение от 0.1.3
 * Исправлено ошибка - при нахождении в фокусе ввода команды отправлялись сразу 
 * после вводи с клавиатуры
 * @note Изменения в 0.2.1
 * Адаптировано под новый подход к версионированию и порядку включения зависимостей
 */


require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getActiveLogic.0.2.1.php';
// require_once $_SERVER["DOCUMENT_ROOT"] . '/include/init.php';




// Обработка AJAX запросов - ДОЛЖНА БЫТЬ ПЕРВОЙ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_keypad'])) {
	header('Content-Type: application/json');

	error_log("=== KEYPAD AJAX ВЫЗВАН ===");
	error_log("POST: " . print_r($_POST, true));
	error_log("SESSION DTMF_CTRL_PTY: " . ($_SESSION['DTMF_CTRL_PTY'] ?? 'NOT SET'));

	// Просто возвращаем тестовый JSON
	echo json_encode([
		'status' => 'test',
		'message' => 'AJAX работает',
		'post' => $_POST,
		'session_has_dtmf' => isset($_SESSION['DTMF_CTRL_PTY']) ? 'yes' : 'no'
	]);
	exit;

	require_once $_SERVER["DOCUMENT_ROOT"] . '/include/exct/session_header.0.0.1.php';
	$cmd = $_SESSION['DTMF_CTRL_PTY'];
	// $cmd = $_SESSION['DTMF_CTRL_PTY'] ?? '';
	// $keypad_activeLogics = getActiveLogic()['DTMF_CTRL_PTY'];
	// $keypad_activeLogics = $_SESSION['DTMF_CTRL_PTY'];

	header('Content-Type: application/json');
	if (!isset($_SESSION['DTMF_CTRL_PTY']) || is_null($_SESSION['DTMF_CTRL_PTY'])) {
		$response = ['status' => 'error', 'message' => "DTMF_CTRL_PTY still not initialise.<br> Just reload page if use DTMF first time."];
		echo json_encode($response);
		// exit;
	} else if (empty($_SESSION['DTMF_CTRL_PTY'])) {
		$response = ['status' => 'error', 'message' => "DTMF_CTRL_PTY not configured."];
		echo json_encode($response);
		// exit;
	}
	// error_log("DTMF_CTRL_PTY = (" . $_SESSION['DTMF_CTRL_PTY'] . ')');
	// error_log("PHP_SESSION_NONE = (" . session_status() . ')');
	$response = ['status' => 'error', 'message' => 'Unknown command'];

	try {
		// Проверяем, не отправлена ли серия команд
		if (isset($_POST['send_sequence'])) {
			$sequence = $_POST['sequence'] ?? '';
			if (empty($sequence)) {
				$response = ['status' => 'error', 'message' => 'Sequence is empty'];
			} else {
				// Проверяем допустимые символы
				if (!preg_match('/^[0-9A-D*#]+$/i', $sequence)) {
					$response = ['status' => 'error', 'message' => 'Invalid characters in sequence. Only 0-9, A-D, *, # allowed'];
				} else {
					// Отправляем каждый символ отдельно с небольшой задержкой
					$success = true;
					$chars = str_split(strtoupper($sequence));
					foreach ($chars as $char) {
						shell_exec('echo "' . $char . '" > ' . escapeshellarg($cmd));
						usleep(100000); // 100ms задержка между командами
					}
					$response = ['status' => 'success', 'message' => 'Sequence sent: ' . $sequence];
				}
			}
		} else {
			// Одиночные команды
			$command_map = [
				'info' => '*#',
				'keypad_1' => '1',
				'keypad_2' => '2',
				'keypad_3' => '3',
				'keypad_4' => '4',
				'keypad_5' => '5',
				'keypad_6' => '6',
				'keypad_7' => '7',
				'keypad_8' => '8',
				'keypad_9' => '9',
				'keypad_0' => '0',
				'keypad_*' => '*',
				'keypad_A' => 'A',
				'keypad_B' => 'B',
				'keypad_C' => 'C',
				'keypad_D' => 'D',
				'keypad_?' => '*#',
				'keypad_disconnect' => '#'
			];

			$command_sent = false;
			foreach ($command_map as $post_key => $dtmf_command) {
				if (isset($_POST[$post_key])) {
					shell_exec('echo "' . $dtmf_command . '" > ' . escapeshellarg($cmd));
					$response = ['status' => 'success', 'message' => 'Command sent: ' . $dtmf_command];
					$command_sent = true;
					break;
				}
			}

			if (!$command_sent) {
				$response = ['status' => 'error', 'message' => 'No valid command received'];
			}
		}
	} catch (Exception $e) {
		$response = ['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()];
	}

	echo json_encode($response);
	exit;
}

// Начинаем сессию для получения данных
if (session_status() === PHP_SESSION_NONE) {
	session_set_cookie_params(SESSION_LIFETIME, SESSION_PATH);
	session_name(SESSION_NAME);
	session_id(SESSION_ID);
	session_start();
}

// Определяем, настроен ли DTMF_CTRL_PTY из данных сессии

$cmd = $_SESSION['DTMF_CTRL_PTY'];

if (defined("DEBUG") && DEBUG) {
	dlog("$ver: DTMF command path: " . ($cmd ? $cmd : 'NOT SET'), 4, "DEBUG");
	dlog("$ver: Session status logic: " . (isset($_SESSION['status']['logic']) ? 'SET' : 'NOT SET'), 4, "DEBUG");
}
unset($kp_logics);
?>

<!-- HTML структура модального окна DTMF -->
<div id="keypadOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); z-index: 9998; backdrop-filter: blur(3px);"></div>

<div id="keypadContainer" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.9); z-index: 9999; width: 400px; max-width: 95%; opacity: 0; transition: all 0.3s ease;">
	<div style="background: #212529; border: 1px solid #3c3f47; padding: 15px; box-shadow: 0 0 20px rgba(0, 0, 0, 0.8); border-radius: 10px;">
		<button style="position: absolute; top: 10px; right: 10px; background: none; border: none; color: #bebebe; font-size: 20px; cursor: pointer; width: 25px; height: 25px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease;"
			onmouseover="this.style.color='#ffffff';this.style.background='rgba(255, 255, 255, 0.1)';"
			onmouseout="this.style.color='#bebebe';this.style.background='none';"
			onclick="closeKeypad()"
			title="Close">&times;</button>

		<div style="color: #bebebe; text-align: center; margin-bottom: 15px; font-size: 1.2em; font-weight: 600; padding-bottom: 8px; border-bottom: 1px solid #3c3f47;">
			<i class="fa fa-keyboard-o" style="margin-right: 8px;"></i><?php echo getTranslation('DTMF Keypad') ?>
		</div>

		<!-- Поле ввода для последовательности команд -->
		<div style="margin-bottom: 12px;">
			<div style="display: flex; gap: 8px; margin-bottom: 4px;">
				<input type="text"
					id="dtmfSequence"
					style="flex: 1; padding: 8px 12px; font-size: 14px; font-family: 'Roboto Mono', monospace; border: 1px solid #3c3f47; background: #535353; color: #b3b3af; border-radius: 6px; transition: all 0.2s ease;"
					placeholder="Sequence (0-9, A-D, *, #)"
					maxlength="50"
					<?php echo empty($cmd) ? 'disabled' : ''; ?>
					onfocus="this.style.outline='none';this.style.borderColor='#65737e';this.style.boxShadow='0 0 3px rgba(101, 115, 126, 0.5)';this.style.background='#5C5C5C';this.style.color='#ffffff';"
					onblur="this.style.borderColor='#3c3f47';this.style.boxShadow='none';this.style.background='#535353';this.style.color='#b3b3af';">
				<button style="padding: 8px 12px; font-size: 14px; font-weight: 600; border: 1px solid #3c3f47; background: #2c7f2c; color: #ffffff; border-radius: 6px; cursor: pointer; transition: all 0.2s ease; white-space: nowrap; display: flex; align-items: center; justify-content: center; min-width: 120px;"
					onmouseover="if(!this.disabled) { this.style.background='#37803A'; }"
					onmouseout="if(!this.disabled) { this.style.background='#2c7f2c'; }"
					name="send_sequence"
					<?php echo empty($cmd) ? 'disabled' : ''; ?>
					onclick="sendSequence()">
					<i class="fa fa-paper-plane" style="margin-right: 6px;"></i>Send
				</button>
			</div>
			<div style="text-align: center; color: #949494; font-size: 11px; margin-top: 3px;">
				<small><?php echo getTranslation('Example') ?>: 123ABCD*0#</small>
			</div>
		</div>

		<!-- Основная сетка с цифрами и кнопками справа -->
		<div style="display: grid; grid-template-columns: 3fr 1fr; gap: 10px; margin-bottom: 12px;">
			<!-- Левая часть: цифровая клавиатура 3x4 -->
			<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px;">
				<!-- Первый ряд -->
				<button class="dtmf-btn dtmf-num" name="keypad_1" <?php echo empty($cmd) ? 'disabled' : ''; ?> onclick="sendSingleCommand('keypad_1')">1</button>
				<button class="dtmf-btn dtmf-num" name="keypad_2" <?php echo empty($cmd) ? 'disabled' : ''; ?> onclick="sendSingleCommand('keypad_2')">2</button>
				<button class="dtmf-btn dtmf-num" name="keypad_3" <?php echo empty($cmd) ? 'disabled' : ''; ?> onclick="sendSingleCommand('keypad_3')">3</button>

				<!-- Второй ряд -->
				<button class="dtmf-btn dtmf-num" name="keypad_4" <?php echo empty($cmd) ? 'disabled' : ''; ?> onclick="sendSingleCommand('keypad_4')">4</button>
				<button class="dtmf-btn dtmf-num" name="keypad_5" <?php echo empty($cmd) ? 'disabled' : ''; ?> onclick="sendSingleCommand('keypad_5')">5</button>
				<button class="dtmf-btn dtmf-num" name="keypad_6" <?php echo empty($cmd) ? 'disabled' : ''; ?> onclick="sendSingleCommand('keypad_6')">6</button>

				<!-- Третий ряд -->
				<button class="dtmf-btn dtmf-num" name="keypad_7" <?php echo empty($cmd) ? 'disabled' : ''; ?> onclick="sendSingleCommand('keypad_7')">7</button>
				<button class="dtmf-btn dtmf-num" name="keypad_8" <?php echo empty($cmd) ? 'disabled' : ''; ?> onclick="sendSingleCommand('keypad_8')">8</button>
				<button class="dtmf-btn dtmf-num" name="keypad_9" <?php echo empty($cmd) ? 'disabled' : ''; ?> onclick="sendSingleCommand('keypad_9')">9</button>

				<!-- Четвертый ряд -->
				<button class="dtmf-btn dtmf-special" name="keypad_*" <?php echo empty($cmd) ? 'disabled' : ''; ?> onclick="sendSingleCommand('keypad_*')">*</button>
				<button class="dtmf-btn dtmf-num" name="keypad_0" <?php echo empty($cmd) ? 'disabled' : ''; ?> onclick="sendSingleCommand('keypad_0')">0</button>
				<button class="dtmf-btn dtmf-info" name="keypad_?" <?php echo empty($cmd) ? 'disabled' : ''; ?> onclick="sendSingleCommand('keypad_?');">?</button>
			</div>

			<!-- Правая часть: кнопки A-D вертикально -->
			<div style="display: grid; grid-template-rows: repeat(4, 1fr); gap: 6px;">
				<button class="dtmf-btn dtmf-letter" name="keypad_A" <?php echo empty($cmd) ? 'disabled' : ''; ?> onclick="sendSingleCommand('keypad_A')">A</button>
				<button class="dtmf-btn dtmf-letter" name="keypad_B" <?php echo empty($cmd) ? 'disabled' : ''; ?> onclick="sendSingleCommand('keypad_B')">B</button>
				<button class="dtmf-btn dtmf-letter" name="keypad_C" <?php echo empty($cmd) ? 'disabled' : ''; ?> onclick="sendSingleCommand('keypad_C')">C</button>
				<button class="dtmf-btn dtmf-letter" name="keypad_D" <?php echo empty($cmd) ? 'disabled' : ''; ?> onclick="sendSingleCommand('keypad_D')">D</button>
			</div>
		</div>

		<!-- Кнопка Disconnect на всю ширину -->
		<div style="margin-top: 8px; width: 100%;">
			<button class="dtmf-btn dtmf-disconnect" name="keypad_disconnect" <?php echo empty($cmd) ? 'disabled' : ''; ?> onclick="sendSingleCommand('keypad_disconnect')"><i class="fa fa-paper-plane" style="margin-right: 6px;"></i> # CONFIRM or DISCONNECT</button>
		</div>

		<div style="text-align: center; color: <?php echo empty($cmd) ? '#ff6b6b' : '#2c7f2c'; ?>; font-size: 12px; margin-top: 8px; padding: 6px; background: rgba(255, 255, 255, 0.05); border-radius: 5px;">
			<?php if (empty($cmd)): ?>
				<i class="fa fa-exclamation-triangle" style="margin-right: 6px;"></i>DTMF_CTRL_PTY not configured
			<?php else: ?>
				<i class="fa fa-check-circle" style="margin-right: 6px;"></i>DTMF ready
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- Центральное всплывающее окно -->
<div id="keypadToast" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 10001; padding: 15px 25px; border-radius: 8px; font-weight: bold; box-shadow: 0 4px 12px rgba(0,0,0,0.5); min-width: 250px; max-width: 80%; text-align: center; color: #ffffff; font-size: 14px; opacity: 0; transition: opacity 0.3s ease, transform 0.3s ease;"></div>

<style>
	/* Стили для кнопок DTMF keypad - уменьшены в 2 раза */
	.dtmf-btn {
		padding: 8px 3px;
		font-size: 16px;
		font-weight: 600;
		border: 1px solid #3c3f47;
		border-radius: 6px;
		cursor: pointer;
		transition: all 0.15s ease;
		display: flex;
		align-items: center;
		justify-content: center;
		min-height: 35px;
		outline: none;
	}

	.dtmf-num {
		background: #2e363f;
		color: #bebebe;
	}

	.dtmf-num:hover:not(:disabled) {
		background: #65737e;
		color: #ffffff;
		transform: translateY(-1px);
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
	}

	.dtmf-special,
	.dtmf-letter {
		background: #a65d14;
		color: #ffffff;
	}

	.dtmf-special:hover:not(:disabled),
	.dtmf-letter:hover:not(:disabled) {
		background: #b86b20;
		transform: translateY(-1px);
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
	}

	.dtmf-info {
		background: #2c7f2c;
		color: #ffffff;
	}

	.dtmf-info:hover:not(:disabled) {
		background: #37803A;
		transform: translateY(-1px);
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
	}

	.dtmf-disconnect {
		background: #8C0C26;
		color: #ffffff;
		width: 100%;
		font-size: 14px;
		padding: 8px;
	}

	.dtmf-disconnect:hover:not(:disabled) {
		background: #a00e2d;
		transform: translateY(-1px);
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
	}

	.dtmf-btn:disabled {
		opacity: 0.5;
		cursor: not-allowed;
		background: #5C5C5C;
	}

	.dtmf-btn:active:not(:disabled) {
		transform: translateY(0);
	}

	/* Адаптивность */
	@media (max-width: 480px) {
		#keypadContainer {
			width: 95% !important;
			transform: translate(-50%, -50%) scale(0.95) !important;
		}

		.dtmf-btn {
			padding: 6px 2px;
			font-size: 14px;
			min-height: 30px;
		}

		#keypadToast {
			min-width: 200px;
			padding: 12px 18px;
			font-size: 13px;
		}
	}
</style>

<script>
	// Функция открытия DTMF клавиатуры
	function openKeypad() {
		console.log("Opening DTMF keypad...");
		const overlay = document.getElementById('keypadOverlay');
		const container = document.getElementById('keypadContainer');

		overlay.style.display = 'block';
		container.style.display = 'block';

		// Плавное появление
		setTimeout(() => {
			container.style.opacity = '1';
			container.style.transform = 'translate(-50%, -50%) scale(1)';
		}, 10);

		document.body.style.overflow = 'hidden';

		// Фокус на поле ввода при открытии
		setTimeout(() => {
			const input = document.getElementById('dtmfSequence');
			if (input && !input.disabled) {
				input.focus();
			}
		}, 100);
	}

	// Функция закрытия DTMF клавиатуры
	function closeKeypad() {
		console.log("Closing DTMF keypad...");
		const overlay = document.getElementById('keypadOverlay');
		const container = document.getElementById('keypadContainer');

		// Плавное исчезновение
		container.style.opacity = '0';
		container.style.transform = 'translate(-50%, -50%) scale(0.9)';

		setTimeout(() => {
			overlay.style.display = 'none';
			container.style.display = 'none';
			document.body.style.overflow = 'auto';
		}, 300);
	}

	// Функция отправки одиночной DTMF команды
	function sendSingleCommand(command) {
		const button = document.querySelector(`button[name="${command}"]`);

		if (button && button.disabled) {
			return;
		}

		// Визуальная обратная связь на кнопке
		if (button) {
			const originalBg = button.style.backgroundColor;
			const originalText = button.innerHTML;
			button.style.backgroundColor = '#65737e';
			button.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

			setTimeout(() => {
				button.style.backgroundColor = originalBg;
				button.innerHTML = originalText;
			}, 300);
		}

		// Отправляем AJAX запрос
		const formData = new FormData();
		formData.append(command, '1');
		formData.append('ajax_keypad', 'true');
		
		// отладим
		console.log('Отправляемые данные:');
		for (let pair of formData.entries()) {
			console.log(pair[0] + ': ' + pair[1]);
		}
		
		
		fetch('/include/keypad.php', {
				method: 'POST',
				body: formData
			})
			.then(response => {
				console.log("DTMF response status:", response.status);
				if (!response.ok) {
					throw new Error('HTTP error: ' + response.status);
				}
				return response.json();
			})
			.then(data => {
				console.log("DTMF response data:", data);

				if (data && data.status === 'success') {
					showToast(data.message, 'success');
				} else {
					const errorMsg = data ? data.message : 'Unknown error';
					showToast(errorMsg, 'error');
				}
			})
			.catch(error => {
				console.error("DTMF error:", error);
				showToast('Error: ' + error.message, 'error');
			});
	}

	// Функция отправки последовательности
	function sendSequence() {
		const sequenceInput = document.getElementById('dtmfSequence');
		const sendButton = document.querySelector('button[name="send_sequence"]');

		if (sequenceInput.disabled || sendButton.disabled) {
			return;
		}

		const sequence = sequenceInput.value.trim().toUpperCase();
		if (!sequence) {
			showToast('Please enter a sequence', 'error');
			sequenceInput.focus();
			return;
		}

		// Проверка на допустимые символы
		if (!/^[0-9A-D*#]+$/.test(sequence)) {
			showToast('Invalid characters. Only 0-9, A-D, *, # allowed', 'error');
			sequenceInput.focus();
			return;
		}

		// Визуальная обратная связь
		const originalText = sendButton.innerHTML;
		sendButton.innerHTML = '<i class="fa fa-spinner fa-spin" style="margin-right: 6px;"></i>Sending...';
		sendButton.disabled = true;

		// Отправляем AJAX запрос
		const formData = new FormData();
		formData.append('send_sequence', '1');
		formData.append('sequence', sequence);
		formData.append('ajax_keypad', 'true');

		fetch('/include/keypad.php', {
				method: 'POST',
				body: formData
			})
			.then(response => {
				console.log("DTMF response status:", response.status);
				if (!response.ok) {
					throw new Error('HTTP error: ' + response.status);
				}
				return response.json();
			})
			.then(data => {
				console.log("DTMF response data:", data);

				if (data && data.status === 'success') {
					showToast(data.message, 'success');
					// Очищаем поле ввода после успешной отправки
					sequenceInput.value = '';
				} else {
					const errorMsg = data ? data.message : 'Unknown error';
					showToast(errorMsg, 'error');
				}

				// Восстанавливаем кнопку
				sendButton.innerHTML = originalText;
				sendButton.disabled = false;
			})
			.catch(error => {
				console.error("DTMF error:", error);
				showToast('Error: ' + error.message, 'error');
				// Восстанавливаем кнопку
				sendButton.innerHTML = originalText;
				sendButton.disabled = false;
			});
	}

	// Функция показа уведомления по центру экрана
	function showToast(message, type) {
		const toast = document.getElementById('keypadToast');

		// Устанавливаем стили в зависимости от типа
		toast.style.display = 'block';
		toast.style.backgroundColor = type === 'success' ? '#2c7f2c' : '#8C0C26';
		toast.innerHTML = type === 'success' ?
			'<i class="fa fa-check" style="margin-right: 6px;"></i>' + message :
			'<i class="fa fa-exclamation-triangle" style="margin-right: 6px;"></i>' + message;

		// Плавное появление
		setTimeout(() => {
			toast.style.opacity = '1';
			toast.style.transform = 'translate(-50%, -50%)';
		}, 10);

		// Автоматически скрываем через 3 секунды
		setTimeout(() => {
			toast.style.opacity = '0';
			toast.style.transform = 'translate(-50%, -40%)';
			setTimeout(() => {
				toast.style.display = 'none';
			}, 300);
		}, 3000);
	}

	// Обработка нажатия клавиш в поле ввода
	document.getElementById('dtmfSequence')?.addEventListener('keydown', function(e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			sendSequence();
		}
	});

	// Обработка кликов по кнопкам для добавления в поле ввода
	document.querySelectorAll('.dtmf-num, .dtmf-special, .dtmf-info, .dtmf-letter').forEach(button => {
		button.addEventListener('click', function(e) {
			if (this.disabled) return;

			const sequenceInput = document.getElementById('dtmfSequence');
			if (!sequenceInput || sequenceInput.disabled) return;

			// Определяем какой символ добавить
			let char = '';
			const name = this.getAttribute('name');
			const charMap = {
				'keypad_1': '1',
				'keypad_2': '2',
				'keypad_3': '3',
				'keypad_4': '4',
				'keypad_5': '5',
				'keypad_6': '6',
				'keypad_7': '7',
				'keypad_8': '8',
				'keypad_9': '9',
				'keypad_0': '0',
				'keypad_*': '*',
				'keypad_A': 'A',
				'keypad_B': 'B',
				'keypad_C': 'C',
				'keypad_D': 'D',
				'keypad_?': '' // Кнопка ? не добавляет символ в последовательность
			};

			if (charMap[name] !== undefined) {
				// Для кнопки ? не добавляем символ
				if (name !== 'keypad_?') {
					sequenceInput.value += charMap[name];
					sequenceInput.focus();
				}
			}
		});
	});

	// Закрытие по клику на оверлей
	document.getElementById('keypadOverlay').addEventListener('click', function(e) {
		if (e.target === this) {
			closeKeypad();
		}
	});

	// Закрытие по ESC
	document.addEventListener('keydown', function(e) {
		if (e.key === 'Escape' && document.getElementById('keypadContainer').style.display === 'block') {
			closeKeypad();
		}

		// Обработка клавиатурных комбинаций для DTMF (только когда окно открыто)
		if (document.getElementById('keypadContainer').style.display === 'block') {
			const keyMap = {
				'1': 'keypad_1',
				'2': 'keypad_2',
				'3': 'keypad_3',
				'4': 'keypad_4',
				'5': 'keypad_5',
				'6': 'keypad_6',
				'7': 'keypad_7',
				'8': 'keypad_8',
				'9': 'keypad_9',
				'0': 'keypad_0',
				'*': 'keypad_*',
				'#': 'keypad_disconnect',
				'a': 'keypad_A',
				'b': 'keypad_B',
				'c': 'keypad_C',
				'd': 'keypad_D'
			};

			const key = e.key.toLowerCase();
			if (keyMap[key]) {
				e.preventDefault();

				// Проверяем, находится ли фокус в поле ввода
				const sequenceInput = document.getElementById('dtmfSequence');
				const isInputFocused = sequenceInput && document.activeElement === sequenceInput;

				if (isInputFocused) {
					// Если фокус в поле ввода - только добавляем символ
					const addChar = key === '#' ? '#' : key.toUpperCase();
					sequenceInput.value += addChar;
				} else {
					// Если фокус не в поле ввода - отправляем команду
					sendSingleCommand(keyMap[key]);

					// Также добавляем символ в поле ввода если оно активно
					if (sequenceInput && !sequenceInput.disabled) {
						const addChar = key === '#' ? '#' : key.toUpperCase();
						sequenceInput.value += addChar;
					}
				}
			}
		}
	});

	// Экспортируем функции для глобального использования
	window.openKeypad = openKeypad;
	window.closeKeypad = closeKeypad;
	window.sendSequence = sendSequence;
	window.sendSingleCommand = sendSingleCommand;
</script>