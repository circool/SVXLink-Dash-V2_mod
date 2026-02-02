/**
 * @filesource block_updater.js
 * @version 0.0.2.release
 * @description Handle update blocks with UPDATE_INTERVAL
 */

(function () {
	'use strict';

	const blocks = {
		'rf_activity': 'rf_activity_content',
		'net_activity': 'net_activity_content',
		'connection_details': 'connection_details_content',
		
	};
	if (typeof window.WS_ENABLED !== 'undefined' && !window.WS_ENABLED) {
		blocks['left_panel'] = 'leftPanel';
		blocks['radio_activity'] = 'radio_activity_content';
	}

	const UPDATE_INTERVAL = window.UPDATE_INTERVAL || 5000;
	

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

	setTimeout(updateAllBlocks, UPDATE_INTERVAL); 
	setInterval(updateAllBlocks, UPDATE_INTERVAL); 


	document.addEventListener('visibilitychange', function () {
		if (!document.hidden) {
			updateAllBlocks();
		}
	});
})();