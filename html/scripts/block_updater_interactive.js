/**
 * @fileoverview Periodic session refresh + block renderer 
 * @filesource /scripts/block_updater.js
 * @author Vladimir Tsurkanenko <vladimir@tsurkanenko.ru>
 * @date 2026.02.11
 * @version 0.4.6
 */
(function () {
	'use strict';

	const UPDATE_INTERVAL = window.UPDATE_INTERVAL;
	if (!UPDATE_INTERVAL) return;

	// Основной список всех возможных блоков
	const ALL_BLOCKS = [
		{ name: 'radio_activity', container: 'radio_activity_content', wsManaged: true },     // управляется WS
		{ name: 'connection_details', container: 'connection_details_content', wsManaged: false },
		{ name: 'rf_activity', container: 'rf_activity_content', wsManaged: false },
		{ name: 'net_activity', container: 'net_activity_content', wsManaged: false },
		{ name: 'left_panel', container: 'leftPanel', wsManaged: true }                       // управляется WS
	];

	// Только исторические блоки (не управляемые WS)
	function getHistoricalBlocks() {
		return ALL_BLOCKS.filter(block => !block.wsManaged);
	}

	// Все блоки (когда WS отключен)
	function getAllBlocks() {
		return ALL_BLOCKS.filter(block => {
			// Проверяем константы видимости
			if (block.name === 'radio_activity' && window.SHOW_RADIO_ACTIVITY === false) return false;
			if (block.name === 'connection_details' && window.SHOW_CON_DETAILS === false) return false;
			if (block.name === 'rf_activity' && window.SHOW_RF_ACTIVITY === false) return false;
			if (block.name === 'net_activity' && window.SHOW_NET_ACTIVITY === false) return false;
			return true;
		});
	}

	let currentBlocks = window.WS_ENABLED ? getHistoricalBlocks() : getAllBlocks();
	let isUpdating = false;

	// Функция для переключения режима
	window.setAJAXMode = function(useWS) {
		if (useWS) {
			currentBlocks = getHistoricalBlocks();
			console.log('[AJAX] Switching to WS mode, updating blocks:', currentBlocks.map(b => b.name));
		} else {
			currentBlocks = getAllBlocks();
			console.log('[AJAX] Switching to AJAX mode, updating blocks:', currentBlocks.map(b => b.name));
		}
	};

	async function updateSession() {
		const response = await fetch(`/include/ajax_update.php?update_session=1&t=${Date.now()}`);
		const data = await response.json();
		return data;
	}

	async function updateBlock(block) {
		const container = document.getElementById(block.container);
		if (!container) {
			console.warn('[AJAX] Container not found:', block.container);
			return;
		}

		console.log('[AJAX] Fetching block:', block.name);
		const response = await fetch(`/include/ajax_update.php?block=${block.name}&t=${Date.now()}`);
		const data = await response.json();

		if (data.html) {
			container.innerHTML = data.html;
		} else {
			console.error('[AJAX] No HTML in response for block:', block.name, data);
		}
	}

	async function updateAllBlocks() {
		if (isUpdating) {
			console.log('[AJAX] Update already in progress, skipping');
			return;
		}
		isUpdating = true;

		try {
			await updateSession();

			for (const block of currentBlocks) {
				await updateBlock(block);
			}
			
		} catch (error) {
			console.error('[AJAX] Update cycle failed:', error);
		} finally {
			isUpdating = false;
		}
	}

	// Запускаем цикл обновления
	if (currentBlocks.length > 0) {
		setInterval(updateAllBlocks, UPDATE_INTERVAL);
	}
})();