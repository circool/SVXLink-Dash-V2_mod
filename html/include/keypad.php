<?php

/**
 * @filesource /include/keypad.php
 * @version 0.4.2.release
 * @date 2026-01-30
 * @author vladimir@tsurkanenko.ru
 * @note DTMF keypad with logic selection based on dtmf_cmd parameter
 */

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/session_header.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/settings.php';

// Загружаем функцию перевода
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getTranslation.php';

// Получаем список логик с dtmf_cmd
$dtmfLogics = [];
if (isset($_SESSION['status']['logic']) && is_array($_SESSION['status']['logic'])) {
	foreach ($_SESSION['status']['logic'] as $logicName => $logicData) {
		if (!empty($logicData['dtmf_cmd'])) {
			$dtmfLogics[$logicName] = $logicData['dtmf_cmd'];
		}
	}
}

$singleLogic = count($dtmfLogics) === 1 ? key($dtmfLogics) : null;
?>

<!-- Модальное окно DTMF клавиатуры -->
<div id="keypadOverlay" class="auth-overlay" style="display: none;"></div>
<div id="keypadContainer" class="auth-container" style="display: none;">
	<div class="auth-form">
		<button class="auth-close" onclick="hideKeypad()">&times;</button>

		<div class="auth-title" id="keypadTitle" style="padding-right: 30px;">
			<?php if ($singleLogic): ?>
				<?= htmlspecialchars($singleLogic) ?>
			<?php else: ?>
				<?= getTranslation('DTMF Keypad') ?>
			<?php endif; ?>
		</div>

		<?php if (!$singleLogic && !empty($dtmfLogics)): ?>
			<div class="auth-field">
				<label for="dtmfLogicSelect"><?= getTranslation('Select logic') ?>:</label>
				<select id="dtmfLogicSelect" class="keypad-logic-select" onchange="updateSelectedLogic()">
					<option value="">-- <?= getTranslation('Select logic') ?> --</option>
					<?php foreach ($dtmfLogics as $logicName => $dtmfCmd): ?>
						<option value="<?= htmlspecialchars($logicName) ?>">
							<?= htmlspecialchars($logicName) ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php elseif ($singleLogic): ?>
			<input type="hidden" id="dtmfLogicSelect" value="<?= htmlspecialchars($singleLogic) ?>">
		<?php endif; ?>

		<div class="keypad-sequence-container">
			<div class="keypad-sequence-field">
				<input type="text"
					id="dtmfSequence"
					class="keypad-sequence-input"
					placeholder="<?= getTranslation('Sequence (0-9, A-D, *, #)') ?>"
					maxlength="50"
					<?= empty($dtmfLogics) ? 'disabled' : '' ?>>
				<button class="keypad-sequence-send"
					name="send_sequence"
					<?= empty($dtmfLogics) ? 'disabled' : '' ?>
					onclick="sendSequence()">
					<?= getTranslation('Send') ?>
				</button>
			</div>
			<div class="keypad-sequence-hint">
				<small><?= getTranslation('Example') ?>: 123ABCD*0#</small>
			</div>
		</div>

		<div class="keypad-main-grid">
			<div class="keypad-numpad">
				<button class="dtmf-btn dtmf-num" onclick="sendDTMF('1')">1</button>
				<button class="dtmf-btn dtmf-num" onclick="sendDTMF('2')">2</button>
				<button class="dtmf-btn dtmf-num" onclick="sendDTMF('3')">3</button>

				<button class="dtmf-btn dtmf-num" onclick="sendDTMF('4')">4</button>
				<button class="dtmf-btn dtmf-num" onclick="sendDTMF('5')">5</button>
				<button class="dtmf-btn dtmf-num" onclick="sendDTMF('6')">6</button>

				<button class="dtmf-btn dtmf-num" onclick="sendDTMF('7')">7</button>
				<button class="dtmf-btn dtmf-num" onclick="sendDTMF('8')">8</button>
				<button class="dtmf-btn dtmf-num" onclick="sendDTMF('9')">9</button>

				<button class="dtmf-btn dtmf-special" onclick="sendDTMF('*')">*</button>
				<button class="dtmf-btn dtmf-num" onclick="sendDTMF('0')">0</button>
				<button class="dtmf-btn dtmf-info" onclick="sendDTMF('*#')">?</button>
			</div>

			<div class="keypad-letters">
				<button class="dtmf-btn dtmf-letter keypad-letter" onclick="sendDTMF('A')">A</button>
				<button class="dtmf-btn dtmf-letter keypad-letter" onclick="sendDTMF('B')">B</button>
				<button class="dtmf-btn dtmf-letter keypad-letter" onclick="sendDTMF('C')">C</button>
				<button class="dtmf-btn dtmf-letter keypad-letter" onclick="sendDTMF('D')">D</button>
			</div>
		</div>

		<div class="keypad-bottom-row">
			<button class="dtmf-btn dtmf-disconnect keypad-disconnect" onclick="sendDTMF('#')">
				# <?= getTranslation('Confirm or Disconnect') ?>
			</button>
		</div>

		<div class="keypad-status" style="color: <?= empty($dtmfLogics) ? '#ff6b6b' : '#2c7f2c' ?>;">
			<?php if (empty($dtmfLogics)): ?>
				<?= getTranslation('No logic with DTMF configured') ?>
			<?php else: ?>
				<?= getTranslation('DTMF ready') ?>
			<?php endif; ?>
		</div>
	</div>
</div>

<script>
	// Глобальная переменная для хранения выбранной логики
	let selectedLogic = '<?= $singleLogic ? htmlspecialchars($singleLogic) : "" ?>';

	function updateSelectedLogic() {
		const select = document.getElementById('dtmfLogicSelect');
		selectedLogic = select.value;

		const dtmfSequence = document.getElementById('dtmfSequence');
		const sendButton = document.querySelector('button[name="send_sequence"]');
		const dtmfButtons = document.querySelectorAll('.dtmf-btn');

		if (selectedLogic) {
			document.getElementById('keypadTitle').textContent = selectedLogic;
			if (dtmfSequence) dtmfSequence.disabled = false;
			if (sendButton) sendButton.disabled = false;
			dtmfButtons.forEach(btn => {
				btn.disabled = false;
			});
		} else {
			document.getElementById('keypadTitle').textContent = '<?= addslashes(getTranslation("DTMF Keypad")) ?>';
			if (dtmfSequence) dtmfSequence.disabled = true;
			if (sendButton) sendButton.disabled = true;
			dtmfButtons.forEach(btn => {
				btn.disabled = true;
			});
		}
	}

	function showKeypad() {
		const overlay = document.getElementById('keypadOverlay');
		const container = document.getElementById('keypadContainer');

		overlay.style.display = 'block';
		container.style.display = 'block';

		setTimeout(() => {
			container.style.opacity = '1';
			container.style.transform = 'translate(-50%, -50%)';
		}, 10);

		document.body.style.overflow = 'hidden';

		// Инициализируем состояние кнопок при открытии
		updateSelectedLogic();

		if (selectedLogic) {
			const dtmfInput = document.getElementById('dtmfSequence');
			if (dtmfInput && !dtmfInput.disabled) {
				setTimeout(() => dtmfInput.focus(), 100);
			}
		}
	}

	function hideKeypad() {
		const overlay = document.getElementById('keypadOverlay');
		const container = document.getElementById('keypadContainer');

		container.style.opacity = '0';
		container.style.transform = 'translate(-50%, -60%)';

		setTimeout(() => {
			overlay.style.display = 'none';
			container.style.display = 'none';
			document.body.style.overflow = 'auto';
		}, 300);
	}

	function sendDTMF(command) {
		if (!selectedLogic) {
			alert('<?= addslashes(getTranslation("Please select logic for DTMF sending")) ?>');
			return;
		}

		const button = event.target;
		const originalBg = button.style.backgroundColor;
		const originalText = button.innerHTML;
		button.style.backgroundColor = '#65737e';
		button.innerHTML = '...';

		const sequenceInput = document.getElementById('dtmfSequence');
		if (sequenceInput && !sequenceInput.disabled) {
			sequenceInput.value += command;
		}

		fetch('/include/dtmf_handler.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					command: command,
					source: selectedLogic
				})
			})
			.then(response => response.text())
			.then(jsCode => {
				try {
					eval(jsCode);
				} catch (e) {
					console.error('Error:', e);
				}
			})
			.catch(error => {
				console.error('Error:', error);
			})
			.finally(() => {
				setTimeout(() => {
					button.style.backgroundColor = originalBg;
					button.innerHTML = originalText;
				}, 300);
			});
	}

	function sendSequence() {
		if (!selectedLogic) {
			alert('<?= addslashes(getTranslation("Please select logic for DTMF sending")) ?>');
			return;
		}

		const sequenceInput = document.getElementById('dtmfSequence');
		const sequence = sequenceInput.value.trim().toUpperCase();

		if (!sequence) {
			alert('<?= addslashes(getTranslation("Please enter DTMF sequence")) ?>');
			sequenceInput.focus();
			return;
		}

		if (!/^[0-9A-D*#]+$/.test(sequence)) {
			alert('<?= addslashes(getTranslation("Invalid characters. Only allowed: 0-9, A-D, *, #")) ?>');
			sequenceInput.focus();
			return;
		}

		const sendButton = document.querySelector('button[name="send_sequence"]');
		const originalText = sendButton.innerHTML;
		sendButton.innerHTML = '...';
		sendButton.disabled = true;

		fetch('/include/dtmf_handler.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					command: sequence,
					source: selectedLogic
				})
			})
			.then(response => response.text())
			.then(jsCode => {
				try {
					eval(jsCode);
				} catch (e) {
					console.error('Error:', e);
				}

				sequenceInput.value = '';
			})
			.catch(error => {
				console.error('Error:', error);
			})
			.finally(() => {
				sendButton.innerHTML = originalText;
				sendButton.disabled = false;
			});
	}

	// Обработка клавиш
	document.addEventListener('keydown', function(e) {
		if (e.key === 'Escape' && document.getElementById('keypadContainer').style.display === 'block') {
			hideKeypad();
		}

		if (document.getElementById('keypadContainer').style.display === 'block') {
			const keyMap = {
				'1': '1',
				'2': '2',
				'3': '3',
				'4': '4',
				'5': '5',
				'6': '6',
				'7': '7',
				'8': '8',
				'9': '9',
				'0': '0',
				'*': '*',
				'#': '#',
				'a': 'A',
				'b': 'B',
				'c': 'C',
				'd': 'D'
			};

			const key = e.key.toLowerCase();
			if (keyMap[key] && selectedLogic) {
				e.preventDefault();

				const sequenceInput = document.getElementById('dtmfSequence');
				const isInputFocused = sequenceInput && document.activeElement === sequenceInput;

				if (isInputFocused) {
					sequenceInput.value += key === '#' ? '#' : key.toUpperCase();
				} else {
					const mockEvent = {
						target: document.querySelector('.dtmf-btn')
					};
					event = mockEvent;
					sendDTMF(keyMap[key]);

					if (sequenceInput && !sequenceInput.disabled) {
						sequenceInput.value += key === '#' ? '#' : key.toUpperCase();
					}
				}
			}
		}
	});

	// Экспортируем функции
	window.showKeypad = showKeypad;
	window.hideKeypad = hideKeypad;
	window.sendDTMF = sendDTMF;
	window.sendSequence = sendSequence;
	window.updateSelectedLogic = updateSelectedLogic;

	// Инициализация при загрузке страницы
	document.addEventListener('DOMContentLoaded', function() {
		// Устанавливаем начальное состояние кнопок
		updateSelectedLogic();

		const sequenceInput = document.getElementById('dtmfSequence');
		if (sequenceInput) {
			sequenceInput.addEventListener('keydown', function(e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					sendSequence();
				}
			});
		}

		const overlay = document.getElementById('keypadOverlay');
		if (overlay) {
			overlay.addEventListener('click', function(e) {
				if (e.target === this) {
					hideKeypad();
				}
			});
		}
	});
</script>