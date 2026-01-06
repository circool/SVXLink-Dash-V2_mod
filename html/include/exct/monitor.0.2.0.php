<?php

/**
 * @version 0.2.0
 * @since 0.1.14
 * @date 2025.12.17
 * @author vladimir@tsurkanenko.ru
 * @filesource /include/exct/monitor.0.2.0.php
 * @copyright Copyright (C) 2025 vladimir@tsurkanenko.ru
 * @description Скрипты аудиомониторинга и управления иеонкой меню
 */

// Проверяем, не был ли уже загружен монитор
if (defined('AUDIO_MONITOR_LOADED')) {
	return;
}
define('AUDIO_MONITOR_LOADED', true);
?>

<script>
	// Простой аудиомонитор для SvxLink
	(function() {
		'use strict';

		// Конфигурация
		var config = {
			port: 8001,
			sampleRate: 48000,
			bufferSize: 4096
		};

		// Состояние
		var ws = null;
		var audioCtx = null;
		var isPlaying = false;
		var audioBuffer = [];
		var connectionAttempts = 0;
		var maxConnectionAttempts = 3;
		var connectionTimeout = null;

		// Получаем элемент кнопки монитора
		var monitorButton = null;

		// Функция для получения кнопки монитора
		function getMonitorButton() {
			if (!monitorButton) {
				monitorButton = document.querySelector('a.menuaudio');
			}
			return monitorButton;
		}

		// Функция обновления иконки
		function updateMonitorIcon(state) {
			var button = getMonitorButton();
			if (!button) return;

			// Удаляем оба класса состояний
			button.classList.remove('menuaudio_mute', 'menuaudio_active');

			// Добавляем нужный класс
			if (state === 'active') {
				button.classList.add('menuaudio_active');
			} else {
				button.classList.add('menuaudio_mute');
			}
		}

		// Функция сброса состояния подключения
		function resetConnectionState() {
			connectionAttempts = 0;
			if (connectionTimeout) {
				clearTimeout(connectionTimeout);
				connectionTimeout = null;
			}
		}

		// Основная функция переключения
		window.toggleMonitor = function() {
			if (isPlaying) {
				stopMonitor();
			} else {
				startMonitor();
			}
			return false;
		};

		// Запуск монитора
		function startMonitor() {
			if (isPlaying) {
				console.log('Monitor: Already playing');
				return;
			}

			console.log('Monitor: Starting');
			isPlaying = true;

			// Оптимистично обновляем иконку
			updateMonitorIcon('active');

			// Создаем AudioContext
			try {
				audioCtx = new(window.AudioContext || window.webkitAudioContext)();
				console.log('Monitor: AudioContext created');
			} catch (e) {
				console.error('Monitor: AudioContext error', e);
				handleMonitorError('AudioContext initialization failed');
				return;
			}

			// Подключаем WebSocket
			connectWebSocket();

			// Сохраняем состояние
			localStorage.setItem('svxlinkMonitor', 'on');
		}

		// Остановка монитора
		function stopMonitor() {
			if (!isPlaying) {
				console.log('Monitor: Already stopped');
				return;
			}

			console.log('Monitor: Stopping');
			isPlaying = false;

			// Обновляем иконку
			updateMonitorIcon('mute');

			// Сбрасываем состояние подключения
			resetConnectionState();

			// Закрываем WebSocket
			if (ws) {
				ws.close();
				ws = null;
			}

			// Закрываем AudioContext
			if (audioCtx) {
				audioCtx.close();
				audioCtx = null;
			}

			// Очищаем буфер
			audioBuffer = [];

			// Сохраняем состояние
			localStorage.setItem('svxlinkMonitor', 'off');
		}

		// Обработка ошибок монитора
		function handleMonitorError(error) {
			console.error('Monitor error:', error);
			isPlaying = false;
			updateMonitorIcon('mute');
			resetConnectionState();

			// Очищаем ресурсы
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

		// Подключение WebSocket
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


				// Таймаут для подключения (5 секунд)
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
					// Подтверждаем активное состояние
					updateMonitorIcon('active');
				};

				ws.onmessage = function(event) {
					processAudioData(event.data);
				};

				ws.onerror = function(error) {
					console.error('Monitor: WebSocket error', error);
					clearTimeout(connectionTimeout);
					connectionTimeout = null;

					// Если это последняя попытка и мы все еще в состоянии isPlaying
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
						// Меняем иконку на mute при обрыве
						updateMonitorIcon('mute');

						// Пытаемся переподключиться, если не превышен лимит попыток
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

		// Обработка аудиоданных (без изменений)
		function processAudioData(data) {

			if (!audioCtx || !isPlaying) return;

			try {
				// SVxLink отправляет 16-битные PCM данные
				var pcm16 = new Int16Array(data);

				// Конвертируем в Float32
				var float32 = new Float32Array(pcm16.length);
				for (var i = 0; i < pcm16.length; i++) {
					float32[i] = pcm16[i] / 32768.0; // -1.0 to 1.0
				}

				// Воспроизводим
				playAudio(float32);

			} catch (e) {
				console.error('Monitor: Audio processing error', e);
			}
		}

		// Воспроизведение аудио (без изменений)
		function playAudio(floatData) {
			if (!audioCtx) return;

			// Возобновляем AudioContext если нужно
			if (audioCtx.state !== 'running') {
				audioCtx.resume().then(function() {
					playAudio(floatData);
				}).catch(function(e) {
					console.error('Monitor: AudioContext resume failed', e);
				});
				return;
			}

			try {
				// Создаем буфер
				var buffer = audioCtx.createBuffer(1, floatData.length, config.sampleRate);
				var channel = buffer.getChannelData(0);
				channel.set(floatData);

				// Воспроизводим
				var source = audioCtx.createBufferSource();
				source.buffer = buffer;
				source.connect(audioCtx.destination);
				source.start();

			} catch (e) {
				console.error('Monitor: Playback error', e);
			}
		}

		// Восстановление состояния при загрузке
		document.addEventListener('DOMContentLoaded', function() {
			// Устанавливаем начальное состояние - mute
			updateMonitorIcon('mute');

			// Ждем немного чтобы меню загрузилось
			setTimeout(function() {
				var saved = localStorage.getItem('svxlinkMonitor');
				if (saved === 'on') {
					console.log('Monitor: Restoring state');
					// Начинаем подключение, но показываем mute до успешного подключения
					startMonitor();
				}
			}, 1000);
		});

	})();
</script>