<?php
$GLOBALS['DEBUG'] ? dlog("debug_block_debug.php loaded") : "";


/**
 * @file block_debug.php
 * @brief Блок отладки
 * @description Выводит переменные среды
 * @author vladimir@tsurkanenko
 * @date 2021-11-24
 * @version 0.4
 */


if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

// Получаем все определенные переменные
$allConstants = get_defined_vars();



$ignore = [];
// $ignore[] = "svxconfig";
// $ignore[] = "svxConfigFile";
$ignore[] = "tg_db";
$ignore[] = "lang_db";
$ignore[] = "_SERVER";
$ignore[] = "_FILES";
$ignore[] = "_COOKIE";
$ignore[] = "_GET";
$ignore[] = "_POST";
?>


<div class="divTable">
	<div class="divTableHead">Отладка - debug_block_debug.php</div>
	<div class="grid-container" style="display: inline-grid; grid-template-columns: auto 40px; padding: 1px;; grid-column-gap: 5px;">
		<div class="grid-item activity" style="padding: 10px 0 0 20px; color: #ffffff;" title="">VISIBLE</div>
		<div class="grid-item">
			<div style="padding-top:6px;">
				<input id="toggle-debug-visible" class="toggle toggle-round-flat" type="checkbox" onchange="toggleDebugVisibility()" checked>
				<label for="toggle-debug-visible"></label>
			</div>
		</div>
	</div>


	<div id="debugContent" class="divTable">
		<div id="radioMonitoringControl" style="background:#d6d6d6;color:black; margin:0 0 10px 0;"></div>

		<div class="divTable divTableBody">
			<div class="divTableRow center">
				<div class="divTableHeadCell">Переменная</div>
				<div class="divTableHeadCell">Значение</div>
			</div>

			<?php
			/**
			 * Convert any value to a string representation for display
			 */
			function convertToString($value)
			{
				if ($value === null) {
					return 'null';
				} elseif ($value === true) {
					return 'true';
				} elseif ($value === false) {
					return 'false';
				} elseif (is_string($value)) {
					return htmlspecialchars($value);
				} elseif (is_numeric($value)) {
					return (string)$value;
				} elseif (is_object($value)) {
					if ($value instanceof DateTime) {
						return $value->format('Y-m-d H:i:s');
					} else {
						return 'Object: ' . get_class($value);
					}
				} elseif (is_resource($value)) {
					return 'Resource: ' . get_resource_type($value);
				} else {
					return strval($value);
				}
			}

			function displayArrayAsTable($array, $arrayName)
			{
				if (count($array) == 0) {
					return "Массив пуст.\n";
				}

				$html = "<div class='divTable'>";
				$html .= "<div class='divTableHead'><b>Массив: $arrayName</b> (элементов: " . count($array) . ")</div>";
				$html .= "<table class='cell_content'>";
				$html .= "<tr><th>Ключ</th><th>Значение</th></tr>";

				foreach ($array as $key => $value) {
					$html .= "<tr>";
					$html .= "<td class='divTableCell cell_content'>" . convertToString($key) . "</td>";
					$html .= "<td class='divTableCell cell_content'>";

					if (is_array($value)) {
						$html .= displayArrayAsTable($value, "[$key]");
					} else {
						$html .= convertToString($value);
					}

					$html .= "</td>";
					$html .= "</tr>";
				}

				$html .= "</table>";
				$html .= "</div>";
				return $html;
			}

			foreach ($allConstants as $key => $variable) {
				if (in_array($key, $ignore)) continue;

				echo '<div class="divTableRow center">';
				echo '<div class="divTableCell cell_content middle">' . convertToString($key) . '</div>';
				echo '<div class="divTableCell cell_content middle">';

				if (is_array($variable)) {
					echo displayArrayAsTable($variable, $key);
				} else {
					echo convertToString($variable);
				}

				echo '</div></div>';
			}; ?>
		</div>
	</div>
</div>

<script>
	function toggleDebugVisibility() {
		const block = document.getElementById('debugContent');
		const toggle = document.getElementById('toggle-debug-visible');

		const isVisible = toggle.checked;

		if (isVisible) {
			block.classList.remove('hidden');
		} else {
			block.classList.add('hidden');
		}

		localStorage.setItem('debugBlockVisible', JSON.stringify(isVisible));
	}

	function initDebugBlock() {
		const block = document.getElementById('debugContent');
		const toggle = document.getElementById('toggle-debug-visible');

		if (!block || !toggle) return;

		// Загружаем сохраненное состояние
		const savedState = localStorage.getItem('debugBlockVisible');
		const isVisible = savedState !== null ? JSON.parse(savedState) : false;

		// Устанавливаем состояние переключателя
		toggle.checked = isVisible;

		// Устанавливаем видимость блока
		if (isVisible) {
			block.classList.remove('hidden');
		} else {
			block.classList.add('hidden');
		}
	}

	// Инициализируем блок сразу после его вставки
	initDebugBlock();
</script>