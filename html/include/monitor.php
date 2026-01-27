<?php

/**
 * @version 0.2.0.release
 * @date 2025.12.17
 * @author vladimir@tsurkanenko.ru
 * @filesource /include/monitor.php
 * @description Audio monitor and menu button
 */

if (defined('AUDIO_MONITOR_LOADED')) {
	return;
}
define('AUDIO_MONITOR_LOADED', true);
?>

<script>
	(function() {
		'use strict';

		var config = {
			port: 8001,
			sampleRate: 48000,
			bufferSize: 4096
		};

		var ws = null;
		var audioCtx = null;
		var isPlaying = false;
		var audioBuffer = [];
		var connectionAttempts = 0;
		var maxConnectionAttempts = 3;
		var connectionTimeout = null;
		var monitorButton = null;

		function getMonitorButton() {
			if (!monitorButton) {
				monitorButton = document.querySelector('a.menuaudio');
			}
			return monitorButton;
		}

		function updateMonitorIcon(state) {
			var button = getMonitorButton();
			if (!button) return;

			button.classList.remove('menuaudio_mute', 'menuaudio_active');

			if (state === 'active') {
				button.classList.add('menuaudio_active');
			} else {
				button.classList.add('menuaudio_mute');
			}
		}

		function resetConnectionState() {
			connectionAttempts = 0;
			if (connectionTimeout) {
				clearTimeout(connectionTimeout);
				connectionTimeout = null;
			}
		}

		window.toggleMonitor = function() {
			if (isPlaying) {
				stopMonitor();
			} else {
				startMonitor();
			}
			return false;
		};

		function startMonitor() {
			if (isPlaying) {
				console.log('Monitor: Already playing');
				return;
			}

			console.log('Monitor: Starting');
			isPlaying = true;

			updateMonitorIcon('active');

			try {
				audioCtx = new(window.AudioContext || window.webkitAudioContext)();
				console.log('Monitor: AudioContext created');
			} catch (e) {
				console.error('Monitor: AudioContext error', e);
				handleMonitorError('AudioContext initialization failed');
				return;
			}

			connectWebSocket();
			localStorage.setItem('svxlinkMonitor', 'on');
		}

		function stopMonitor() {
			if (!isPlaying) {
				console.log('Monitor: Already stopped');
				return;
			}

			console.log('Monitor: Stopping');
			isPlaying = false;
			updateMonitorIcon('mute');
			resetConnectionState();

			if (ws) {
				ws.close();
				ws = null;
			}

			if (audioCtx) {
				audioCtx.close();
				audioCtx = null;
			}

			audioBuffer = [];
			localStorage.setItem('svxlinkMonitor', 'off');
		}

		function handleMonitorError(error) {
			console.error('Monitor error:', error);
			isPlaying = false;
			updateMonitorIcon('mute');
			resetConnectionState();

			if (ws) {
				ws.close();
				ws = null;
			}
			if (audioCtx) {
				audioCtx.close();
				audioCtx = null;
			}

			localStorage.setItem('svxlinkMonitor', 'off');
		}

		function connectWebSocket() {
			if (ws) {
				ws.close();
			}

			var url = 'ws://' + window.location.hostname + ':' + config.port;
			console.log('Monitor: Connecting to', url);

			connectionAttempts++;
			console.log('Monitor: Connection attempt', connectionAttempts, 'of', maxConnectionAttempts);

			try {
				ws = new WebSocket(url);
				ws.binaryType = 'arraybuffer';
				connectionTimeout = setTimeout(function() {
					if (ws && ws.readyState !== WebSocket.OPEN) {
						console.warn('Monitor: WebSocket connection timeout');
						ws.close();
					}
				}, 5000);

				ws.onopen = function() {
					console.log('Monitor: WebSocket connected');
					clearTimeout(connectionTimeout);
					connectionTimeout = null;
					resetConnectionState();
					updateMonitorIcon('active');
				};

				ws.onmessage = function(event) {
					processAudioData(event.data);
				};

				ws.onerror = function(error) {
					console.error('Monitor: WebSocket error', error);
					clearTimeout(connectionTimeout);
					connectionTimeout = null;
					if (connectionAttempts >= maxConnectionAttempts && isPlaying) {
						console.error('Monitor: Max connection attempts reached');
						handleMonitorError('Connection failed after ' + maxConnectionAttempts + ' attempts');
					}
				};

				ws.onclose = function(event) {
					console.log('Monitor: WebSocket closed', event.code, event.reason);
					clearTimeout(connectionTimeout);
					connectionTimeout = null;

					if (isPlaying) {
						updateMonitorIcon('mute');
						if (connectionAttempts < maxConnectionAttempts) {
							console.log('Monitor: Reconnecting in 2s');
							setTimeout(connectWebSocket, 2000);
						} else {
							console.error('Monitor: Max reconnection attempts reached');
							handleMonitorError('Reconnection attempts exhausted');
						}
					}
				};

			} catch (e) {
				console.error('Monitor: WebSocket creation error', e);
				clearTimeout(connectionTimeout);
				connectionTimeout = null;

				if (isPlaying) {
					if (connectionAttempts < maxConnectionAttempts) {
						setTimeout(connectWebSocket, 2000);
					} else {
						handleMonitorError('WebSocket creation failed');
					}
				}
			}
		}

		function processAudioData(data) {

			if (!audioCtx || !isPlaying) return;

			try {
				var pcm16 = new Int16Array(data);
				var float32 = new Float32Array(pcm16.length);
				for (var i = 0; i < pcm16.length; i++) {
					float32[i] = pcm16[i] / 32768.0; // -1.0 to 1.0
				}

				playAudio(float32);

			} catch (e) {
				console.error('Monitor: Audio processing error', e);
			}
		}

		function playAudio(floatData) {
			if (!audioCtx) return;

			if (audioCtx.state !== 'running') {
				audioCtx.resume().then(function() {
					playAudio(floatData);
				}).catch(function(e) {
					console.error('Monitor: AudioContext resume failed', e);
				});
				return;
			}

			try {
				var buffer = audioCtx.createBuffer(1, floatData.length, config.sampleRate);
				var channel = buffer.getChannelData(0);
				channel.set(floatData);
				var source = audioCtx.createBufferSource();
				source.buffer = buffer;
				source.connect(audioCtx.destination);
				source.start();

			} catch (e) {
				console.error('Monitor: Playback error', e);
			}
		}

		document.addEventListener('DOMContentLoaded', function() {
			updateMonitorIcon('mute');
			setTimeout(function() {
				var saved = localStorage.getItem('svxlinkMonitor');
				if (saved === 'on') {
					console.log('Monitor: Restoring state');
					startMonitor();
				}
			}, 1000);
		});

	})();
</script>