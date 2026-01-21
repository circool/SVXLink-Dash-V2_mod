/**
 * @file block_updater.0.0.2.js
 * @version 0.0.2
 * @description Единый обновлятель блоков с использованием UPDATE_INTERVAL
 * @note Изменения в 0.0.2:
 * - Обновление контейнеров с суффиксом _content
 * - Единообразная структура для всех блоков
 */

(function () {
	'use strict';

	// Конфигурация - контейнеры для обновляемых данных
	const blocks = {
		'rf_activity': 'rf_activity_content',
		'net_activity': 'net_activity_content',
		'connection_details': 'connection_details_content',
		'reflector_activity': 'reflector_activity_content'
	};

	// Глобальный интервал из константы PHP
	const UPDATE_INTERVAL = window.UPDATE_INTERVAL || 3000;

	// Обновляем все блоки
	function updateAllBlocks() {
		Object.entries(blocks).forEach(([blockName, containerId]) => {
			const container = document.getElementById(containerId);
			if (!container) {
				console.warn(`Container ${containerId} not found for block ${blockName}`);
				return;
			}

			fetch(`/include/ajax_update.php?block=${blockName}&t=${Date.now()}`)
				.then(response => {
					if (!response.ok) {
						throw new Error(`HTTP ${response.status}`);
					}
					return response.text();
				})
				.then(html => {
					container.innerHTML = html;
				})
				.catch(error => {
					console.error(`Error updating ${blockName}:`, error);
				});
		});
	}

	// Запускаем обновление
	setTimeout(updateAllBlocks, 1000); // Первое обновление
	setInterval(updateAllBlocks, UPDATE_INTERVAL); // Периодическое

	// Останавливаем при скрытии вкладки
	document.addEventListener('visibilitychange', function () {
		if (!document.hidden) {
			updateAllBlocks(); // Обновляем при возвращении
		}
	});

})();