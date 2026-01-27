<?php

/**
 * @filesource /include/js_utils.php
 * @version 0.1.0.release
 * @date 2026.01.16
 * @description JavaScript utilits
 */
?>
<script>

	function sendDtmfCommand(command, source = 'unknown') {
		return fetch('/include/dtmf_handler.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					command: command,
					source: source
				})
			})
			.then(response => {
				if (!response.ok) {
					throw new Error('HTTP error: ' + response.status);
				}
				return response.json();
			});
	}

	function showToast(message, type, containerId = 'globalToastContainer') {
		// Проверяем, существует ли уже контейнер для тостов
		let toastContainer = document.getElementById(containerId);
		if (!toastContainer) {
			toastContainer = document.createElement('div');
			toastContainer.id = containerId;
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

		const toast = document.createElement('div');
		toast.className = 'toast ' + type;
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

		setTimeout(() => {
			toast.style.opacity = '1';
			toast.style.transform = 'translateX(0)';
		}, 10);

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

	function checkDtmfAvailable() {
		return new Promise((resolve) => {
			sendDtmfCommand('*#', 'check')
				.then(data => {
					resolve(data.status === 'success');
				})
				.catch(() => {
					resolve(false);
				});
		});
	}

	window.sendDtmfCommand = sendDtmfCommand;
	window.showToast = showToast;
	window.checkDtmfAvailable = checkDtmfAvailable;
</script>