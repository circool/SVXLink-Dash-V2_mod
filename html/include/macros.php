<?php

/**
 * @filesource /include/dtmf_handler.php
 * @version 0.0.3.release
 * @date 2026.01.16
 * @author vladimir@tsurkanenko.ru
 * @note Preliminary version.
 */

if (!isset($_SESSION['status'])) {
	//@todo	
}
echo '<div id="macros_panel" class="toggleable-section">';

$m_logic = $_SESSION['status']['logic'];

$hasMacros = false;
foreach ($m_logic as $logic) {
	if (isset($logic['macros']) && !empty($logic['macros'])) {
		$hasMacros = true;
		break;
	}
}

if ($hasMacros) {
	echo '<div class="divTable" ><div class="divTableBody">';
	foreach ($m_logic as $logic) {
		if (isset($logic['macros']) && !empty($logic['macros'])) {
			$macros = $logic['macros'];
			echo '<div class="divTableRow">';
			echo '<div class="divTableCell middle">';
			echo htmlspecialchars($logic['name']);
			echo '</div>';
			echo '<div class="divTableCell cell_content middle">';
			echo '<div style="display: flex; flex-wrap: wrap; gap: 5px;">';

			foreach ($macros as $key => $value) {
				$styleColor = '';
				if (strpos($value, "EchoLink") !== false) $styleColor = 'background-color: #1c381c;';
				if (strpos($value, "Metar") !== false) $styleColor = 'background-color: #4b2b4b;';
				if (strpos($value, "Parrot") !== false) $styleColor = 'background-color: #4e4e14;';
				if (strpos($value, "Help") !== false) $styleColor = 'background-color: #6c5cac;';
				echo '<a class="tooltip"><span><b>Macro:</b>' . htmlspecialchars($value) .
					'</span><button style="' . $styleColor . '" class="button macro-button"
								data-command="' . htmlspecialchars('D' . $key . '#') . '" 
                data-logic="' . htmlspecialchars($logic['name']) . '">' .
					htmlspecialchars('D' . $key) . '</button></a>';
			}
			echo '</div>';
			echo '</div>';
			echo '</div>';
		}
	}

	echo '</div>';
} else {
	echo '<div>' . getTranslation("No macros available") . '</div>';
}

unset($m_logic, $macros);
?>
</div>

<script>
	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('.macro-button').forEach(button => {
			button.addEventListener('click', function() {
				const command = this.getAttribute('data-command');
				const logic = this.getAttribute('data-logic');
				if (!command) return;

				// Спиннер на кнопке
				const originalBg = this.style.backgroundColor;
				const originalText = this.innerHTML;
				this.style.backgroundColor = '#65737e';
				this.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

				// Отправка и выполнение JavaScript кода от handler'а
				fetch('/include/dtmf_handler.php', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded'
						},
						body: new URLSearchParams({
							command: command,
							source: logic
						})
					})
					.then(r => r.text())
					.then(jsCode => {
						// Выполняем чистый JavaScript код
						try {
							eval(jsCode);
						} catch (e) {
							console.error('Ошибка выполнения JS:', e);
						}
					})
					.catch(error => {
						console.error('Ошибка fetch:', error);
					})
					.finally(() => {
						// Всегда возвращаем кнопку в исходное состояние
						setTimeout(() => {
							this.style.backgroundColor = originalBg;
							this.innerHTML = originalText;
						}, 300);
					});
			});
		});
	});
</script>