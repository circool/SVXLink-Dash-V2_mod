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

	const blocks = [];
	if (!window.WS_ENABLED) {
		if (window.SHOW_RADIO_ACTIVITY !== false) blocks.push({ name: 'radio_activity', container: 'radio_activity_content' });
		blocks.push({ name: 'left_panel', container: 'leftPanel' });
	}
	if (window.SHOW_CON_DETAILS !== false) blocks.push({ name: 'connection_details', container: 'connection_details_content' });
	if (window.SHOW_RF_ACTIVITY !== false) blocks.push({ name: 'rf_activity', container: 'rf_activity_content' });
	if (window.SHOW_NET_ACTIVITY !== false) blocks.push({ name: 'net_activity', container: 'net_activity_content' });

	if (blocks.length === 0) return;

	let isUpdating = false;

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

			for (const block of blocks) {
				await updateBlock(block);
			}
			
		} catch (error) {
			console.error('[AJAX] Update cycle failed:', error);
		} finally {
			isUpdating = false;
		}
	}

	setInterval(updateAllBlocks, UPDATE_INTERVAL);
})();