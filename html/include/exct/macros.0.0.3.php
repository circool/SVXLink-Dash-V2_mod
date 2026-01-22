<div id="macros_panel" class="toggleable-section">
	<?php
	$m_logic = $_SESSION['status']['logic'];

	// Проверяем, есть ли логики с макросами
	$hasMacros = false;
	foreach ($m_logic as $logic) {
		if (isset($logic['macros']) && !empty($logic['macros'])) {
			$hasMacros = true;
			break;
		}
	}

	if ($hasMacros) {


		echo '<div class="divTable" ><div class="divTableBody">';
		// echo '<div>';
		foreach ($m_logic as $logic) {
			if (isset($logic['macros']) && !empty($logic['macros'])) {
				$macros = $logic['macros'];
				// ksort($macros);
				// echo '<div class="divTableBody">';
				echo '<div class="divTableRow">';

				// Колонка с именем логики
				echo '<div class="divTableCell middle">';
				echo htmlspecialchars($logic['name']);
				echo '</div>';

				// Колонка с кнопками макросов
				echo '<div class="divTableCell cell_content middle">';
				echo '<div style="display: flex; flex-wrap: wrap; gap: 5px;">';

				foreach ($macros as $key => $value) {
					$styleColor = '';

					if (strpos($value, "EchoLink") !== false) $styleColor = 'background-color:#1c381c;';
					if (strpos($value, "MetarInfo") !== false) $styleColor = 'background-color:rgb(75, 35, 75);';
					if (strpos($value, "MetarParrot") !== false) $styleColor = 'background-color: #999966;';

					echo '<button style="' . $styleColor . '" class="button macro-button" 
                            title="' . htmlspecialchars('D' . $key . ' -> ' . $value) . '" 
                            data-command="' . htmlspecialchars('D' . $key . '#') . '" 
                            data-logic="' . htmlspecialchars($logic['name']) . '">' .
						htmlspecialchars($value) . '</button>';
				}

				echo '</div>';
				echo '</div>';

				echo '</div>';
			}
		}

		// echo '</div>';
		echo '</div>';
	} else {
		echo '<div>';
		echo getTranslation("No macros available");
		echo '</div>';
	}

	unset($m_logic, $macros);
	?>
</div>

<script>
	// Обработка кликов на кнопки макросов
	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('.macro-button').forEach(button => {
			button.addEventListener('click', function() {
				const command = this.getAttribute('data-command');
				const logic = this.getAttribute('data-logic');

				if (!command) {
					console.error('No command specified for macro button');
					return;
				}

				// Визуальная обратная связь
				const originalBg = this.style.backgroundColor;
				const originalText = this.innerHTML;
				this.style.backgroundColor = '#65737e';
				this.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

				// Отправляем команду через единый обработчик
				fetch('/include/exct/dtmf_handler.0.1.0.php', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
						},
						body: new URLSearchParams({
							command: command,
							source: 'macros_' + logic
						})
					})
					.then(response => {
						if (!response.ok) {
							throw new Error('HTTP error: ' + response.status);
						}
						return response.json();
					})
					.then(data => {
						console.log('Macro response:', data);

						if (data && data.status === 'success') {
							showMacroToast('Macro sent: ' + command, 'success');
						} else {
							const errorMsg = data ? data.message : 'Unknown error';
							showMacroToast('Error: ' + errorMsg, 'error');
						}

						// Восстанавливаем кнопку
						setTimeout(() => {
							this.style.backgroundColor = originalBg;
							this.innerHTML = originalText;
						}, 300);
					})
					.catch(error => {
						console.error('Macro error:', error);
						showMacroToast('Error: ' + error.message, 'error');

						// Восстанавливаем кнопку
						setTimeout(() => {
							this.style.backgroundColor = originalBg;
							this.innerHTML = originalText;
						}, 300);
					});
			});
		});
	});

	// Функция для показа уведомлений для макросов
	function showMacroToast(message, type) {
		// Проверяем, существует ли уже контейнер для тостов
		let toastContainer = document.getElementById('macroToastContainer');
		if (!toastContainer) {
			toastContainer = document.createElement('div');
			toastContainer.id = 'macroToastContainer';
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
		toast.className = 'macro-toast ' + type;
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

	// Пример из оригинального файла (оставляем для обратной совместимости)
	// document.querySelectorAll('.button').forEach((button, index) => {
	// 	// Если это не макрос-кнопка, оставляем оригинальный обработчик
	// 	if (!button.classList.contains('macro-button')) {
	// 		button.addEventListener('click', () => {
	// 			alert(`Вы нажали кнопку ${index + 1}`);
	// 		});
	// 	}
	// });
</script>