/**
 * @filesource /scripts/exct/dashboard_ws_client.0.3.3.js
 * @version 0.3.3
 * @date 2026.01.03
 * @author vladimir@tsurkanenko.ru
 * @description Stateful WebSocket клиент с одной кнопкой статуса
 * 
 * @changes 0.3.3 от 0.3.2:
 * - Кнопка WS при отключении/ошибке перезагружает страницу (запускает сервер через init.php)
 * - Улучшенные тексты и tooltip для кнопки
 * - Дебаунс на клики чтобы предотвратить множественные перезагрузки
 */

class StatefulWebSocketClient {
	constructor(config = {}) {
		// Конфигурация
		this.config = {
			enabled: true,
			host: '127.0.0.1',
			port: 8080,
			path: '/ws',
			autoConnect: true,
			reconnectDelay: 3000,
			maxReconnectAttempts: 5,
			pingInterval: 30000,
			debugToConsole: true,
			debugLevel: 'info',
			...config
		};

		// Состояние
		this.ws = null;
		this.status = 'disconnected';
		this.reconnectAttempts = 0;
		this.isManualDisconnect = false;
		this.activeDevices = new Map();

		// Дебаунс для кликов
		this.lastClickTime = 0;
		this.clickDebounceMs = 1000; // 1 секунда

		// Debug сообщения
		this.debugMessages = [];
		this.maxDebugMessages = 100;

		// Таймеры
		this.pingTimer = null;
		this.reconnectTimer = null;

		// Инициализация
		this.init();
	}

	// ==================== ИНИЦИАЛИЗАЦИЯ ====================

	init() {
		if (!this.config.enabled) {
			this.debug('error', 'Stateful WebSocket client disabled in config');
			this.updateStatus('disabled', 'Disabled');
			return;
		}

		// Создаём кнопку статуса
		this.createStatusButton();

		// Автоподключение
		if (this.config.autoConnect) {
			setTimeout(() => this.connect(), 1000);
		}

		this.debug('info', `Stateful WebSocket Client v0.3.3 initialized`);
	}

	// ==================== КНОПКА СТАТУСА ====================

	/**
	 * Создаёт одну кнопку статуса WebSocket в меню
	 */
	createStatusButton() {
		// Проверяем, есть ли уже кнопка (созданная PHP)
		if (document.getElementById('websocketStatus')) {
			// Если есть кнопка от PHP - удаляем её, создадим свою
			const oldButton = document.getElementById('websocketStatus');
			if (oldButton) oldButton.remove();
		}

		const navbar = document.querySelector('.navbar');
		if (!navbar) {
			setTimeout(() => this.createStatusButton(), 100);
			return;
		}

		// Создаём кнопку статуса
		const button = document.createElement('a');
		button.id = 'websocketStatus';
		button.href = 'javascript:void(0)';
		button.className = 'menuwebsocket menuwebsocket-disconnected';
		button.title = 'WebSocket: Offline. Click to connect';

		const textSpan = document.createElement('span');
		textSpan.id = 'websocketStatusText';
		textSpan.textContent = 'WS';
		button.appendChild(textSpan);

		// Обработчик клика
		button.addEventListener('click', (e) => {
			e.preventDefault();
			this.handleStatusButtonClick();
		});

		// Вставляем в navbar (последним элементом)
		navbar.appendChild(button);

		this.debug('info', 'WebSocket status button created');
	}

	/**
	 * Обработчик клика на кнопку статуса
	 * - При подключении: отключает
	 * - При отключении/ошибке: перезагружает страницу (запускает сервер через init.php)
	 */
	handleStatusButtonClick() {
		const now = Date.now();

		// Дебаунс: предотвращаем множественные быстрые клики
		if (now - this.lastClickTime < this.clickDebounceMs) {
			this.debug('info', `Click ignored (debounce ${this.clickDebounceMs}ms)`);
			return;
		}
		this.lastClickTime = now;

		this.debug('info', `Status button clicked, current status: ${this.status}`);

		switch (this.status) {
			case 'connected':
				// Подключен → отключаем
				this.disconnect();
				break;

			case 'disconnected':
			case 'error':
			case 'timeout':
				// Не подключен → перезагружаем страницу
				// init.php запустит сервер при перезагрузке
				this.startPageReload();
				break;

			case 'connecting':
			case 'reconnecting':
				// В процессе подключения → игнорируем
				this.debug('info', `Already ${this.status}, ignoring click`);
				break;

			case 'disabled':
				// Клиент отключён → ничего не делаем
				break;

			default:
				this.debug('warning', `Unknown status: ${this.status}`);
		}
	}

	/**
	 * Запускает перезагрузку страницы с визуальной индикацией
	 */
	startPageReload() {
		this.debug('info', 'Starting page reload to launch WebSocket server...');

		// Обновляем кнопку для отображения процесса
		const button = document.getElementById('websocketStatus');
		const textSpan = document.getElementById('websocketStatusText');

		if (button && textSpan) {
			textSpan.textContent = 'WS...';
			button.title = 'Starting WebSocket server...';
			button.classList.remove('menuwebsocket-disconnected', 'menuwebsocket-error');
			button.classList.add('menuwebsocket-connecting');
		}

		// Показываем краткое сообщение (опционально)
		this.showReloadNotification();

		// Перезагружаем через небольшую задержку
		setTimeout(() => {
			window.location.reload();
		}, 300);
	}

	/**
	 * Показывает краткое уведомление о перезагрузке
	 */
	showReloadNotification() {
		// Создаём всплывающее сообщение
		const notification = document.createElement('div');
		notification.id = 'ws-reload-notification';
		notification.textContent = 'Starting WebSocket server...';
		notification.style.cssText = `
            position: fixed;
            top: 60px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 10px 15px;
            border-radius: 4px;
            font-size: 14px;
            z-index: 10000;
            animation: fadeInOut 2.5s ease-in-out;
        `;

		// Добавляем стили для анимации
		if (!document.getElementById('ws-notification-styles')) {
			const style = document.createElement('style');
			style.id = 'ws-notification-styles';
			style.textContent = `
                @keyframes fadeInOut {
                    0% { opacity: 0; transform: translateY(-10px); }
                    10% { opacity: 1; transform: translateY(0); }
                    90% { opacity: 1; transform: translateY(0); }
                    100% { opacity: 0; transform: translateY(-10px); }
                }
            `;
			document.head.appendChild(style);
		}

		document.body.appendChild(notification);

		// Удаляем через 2.5 секунды (страница перезагрузится раньше)
		setTimeout(() => {
			if (notification.parentNode) {
				notification.remove();
			}
		}, 2500);
	}

	// ==================== МЕТОД ОТКЛЮЧЕНИЯ ====================

	disconnect() {
		this.isManualDisconnect = true;

		if (this.ws && this.ws.readyState === WebSocket.OPEN) {
			this.ws.close(1000, 'Manual disconnect');
		}

		this.clearTimers();
		this.updateStatus('disconnected', 'Disconnected manually');

		this.debug('info', 'Manual disconnect initiated');
	}

	// ==================== DEBUG CONSOLE МЕТОДЫ ====================

	debug(level, message, data = null) {
		const levels = { 'error': 0, 'warning': 1, 'info': 2, 'debug': 3 };
		const configLevel = levels[this.config.debugLevel] || 2;
		const messageLevel = levels[level] || 2;

		if (messageLevel > configLevel) {
			return;
		}

		const entry = {
			timestamp: new Date().toISOString(),
			level: level,
			message: message,
			data: data,
			source: 'client'
		};

		this.debugMessages.unshift(entry);
		if (this.debugMessages.length > this.maxDebugMessages) {
			this.debugMessages.pop();
		}

		const consoleMethod = level === 'error' ? 'error' :
			level === 'warning' ? 'warn' : 'log';
		console[consoleMethod](`[WS Client ${level.toUpperCase()}] ${message}`, data || '');

		if (this.config.debugToConsole) {
			this.sendToDebugConsole(entry);
		}
	}

	sendToDebugConsole(entry) {
		const debugConsole = document.getElementById('debugLog');
		if (!debugConsole) {
			return;
		}

		const time = new Date(entry.timestamp).toLocaleTimeString();
		const levelClass = `debug-${entry.level}`;
		const sourceClass = `debug-source-${entry.source}`;

		const messageHtml = `
            <div class="debug-entry ${levelClass} ${sourceClass}">
                <span class="debug-time">[${time}]</span>
                <span class="debug-source">[WS Client]</span>
                <span class="debug-level">[${entry.level.toUpperCase()}]</span>
                <span class="debug-message">${this.escapeHtml(entry.message)}</span>
            </div>
        `;

		debugConsole.innerHTML = messageHtml + debugConsole.innerHTML;

		const entries = debugConsole.querySelectorAll('.debug-entry');
		if (entries.length > this.maxDebugMessages) {
			for (let i = this.maxDebugMessages; i < entries.length; i++) {
				entries[i].remove();
			}
		}
	}

	escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	// ==================== УПРАВЛЕНИЕ СОЕДИНЕНИЕМ ====================

	connect() {
		if (!this.config.enabled) {
			this.debug('error', 'Client disabled in config');
			this.updateStatus('disabled', 'Disabled');
			return false;
		}

		this.isManualDisconnect = false;

		if (this.ws && this.ws.readyState === WebSocket.OPEN) {
			this.ws.close(1000, 'Reconnecting');
		}

		const wsUrl = this.buildWsUrl();
		this.debug('info', `Connecting to: ${wsUrl}`);

		this.updateStatus('connecting', 'Connecting...');

		try {
			this.ws = new WebSocket(wsUrl);

			this.ws.onopen = (event) => this.handleOpen(event);
			this.ws.onmessage = (event) => this.handleMessage(event);
			this.ws.onclose = (event) => this.handleClose(event);
			this.ws.onerror = (error) => this.handleError(error);

			setTimeout(() => {
				if (this.ws && this.ws.readyState === WebSocket.CONNECTING) {
					this.debug('error', 'Connection timeout (5s)');
					this.ws.close();
					this.updateStatus('timeout', 'Connection timeout');
				}
			}, 5000);

			return true;

		} catch (error) {
			this.debug('error', `Error creating WebSocket: ${error.message}`, error);
			this.updateStatus('error', 'Connection error');
			this.scheduleReconnect();
			return false;
		}
	}

	// ==================== ОБРАБОТЧИКИ СОБЫТИЙ WS ====================

	handleOpen(event) {
		this.debug('info', 'WebSocket connected successfully');
		this.updateStatus('connected', 'Connected');
		this.reconnectAttempts = 0;

		this.startPingTimer();
	}

	handleMessage(event) {
		try {
			const data = JSON.parse(event.data);
			this.debug('debug', `Received message type: ${data.type}`, data);
			this.processServerMessage(data);

		} catch (error) {
			this.debug('error', `Error parsing message: ${error.message}`, event.data);
		}
	}

	handleClose(event) {
		this.debug('info', `WebSocket closed: code=${event.code}, reason=${event.reason}, clean=${event.wasClean}`);

		if (this.isManualDisconnect) {
			this.debug('info', 'Manual disconnect confirmed');
			return;
		}

		if (event.code !== 1000 && this.reconnectAttempts < this.config.maxReconnectAttempts) {
			this.reconnectAttempts++;
			const delay = this.config.reconnectDelay * this.reconnectAttempts;

			this.debug('warning', `Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts})`);
			this.updateStatus('reconnecting', `Reconnecting... (${this.reconnectAttempts})`);

			this.reconnectTimer = setTimeout(() => {
				if (!this.isManualDisconnect) {
					this.connect();
				}
			}, delay);

		} else {
			this.debug('error', `Max reconnect attempts reached (${this.reconnectAttempts})`);
			this.updateStatus('disconnected', 'Disconnected');
		}

		this.clearTimers();
	}

	handleError(error) {
		this.debug('error', 'WebSocket error', error);
		this.updateStatus('error', 'Connection error');
	}

	// ==================== ОБРАБОТКА СООБЩЕНИЙ СЕРВЕРА ====================

	processServerMessage(data) {
		switch (data.type) {
			case 'welcome':
				this.handleWelcome(data);
				break;

			case 'current_state':
				this.handleCurrentState(data);
				break;

			case 'updates':
				this.handleUpdates(data);
				break;

			case 'pong':
				this.debug('debug', 'Pong received from server');
				break;

			default:
				this.debug('warning', `Unknown message type: ${data.type}`, data);
		}
	}

	handleWelcome(data) {
		this.debug('info', `Connected to server v${data.version}, client ID: ${data.clientId}`);
		this.clientId = data.clientId;
	}

	handleCurrentState(data) {
		const count = data.updates?.length || 0;
		this.debug('info', `Received current state: ${count} active devices`);

		if (data.updates && Array.isArray(data.updates)) {
			this.applyUpdates(data.updates, 'initial_state');

			data.updates.forEach(update => {
				if (update.state === 'on' || update.state === 'open') {
					this.activeDevices.set(update.device, {
						...update,
						receivedAt: Date.now()
					});
				}
			});
		}
	}

	handleUpdates(data) {
		const count = data.updates?.length || 0;
		this.debug('info', `Processing ${count} updates from server`);

		if (data.updates && Array.isArray(data.updates)) {
			const applied = this.applyUpdates(data.updates, 'websocket');
			this.debug('debug', `Applied ${applied}/${count} updates to DOM`);

			data.updates.forEach(update => {
				if (update.state === 'on' || update.state === 'open') {
					this.activeDevices.set(update.device, {
						...update,
						receivedAt: Date.now()
					});
				} else if (update.state === 'off' || update.state === 'closed') {
					this.activeDevices.delete(update.device);
				}
			});
		}
	}

	// ==================== ПРИМЕНЕНИЕ ОБНОВЛЕНИЙ К DOM ====================

	applyUpdates(updates, source = 'unknown') {
		let appliedCount = 0;

		updates.forEach(update => {
			const element = document.getElementById(update.id);

			if (element) {
				if (update.content !== undefined) {
					element.textContent = update.content;
				}

				element.classList.remove('active-mode-cell', 'inactive-mode-cell');

				if (update.class) {
					element.classList.add(update.class);
				}

				if (update.state) {
					element.dataset.state = update.state;
				}
				if (update.device_type) {
					element.dataset.deviceType = update.device_type;
				}
				if (update.device) {
					element.dataset.device = update.device;
				}

				appliedCount++;

				if (update.state === 'on' || update.state === 'open') {
					this.debug('info', `Device ${update.device} activated: ${update.content}`);
				} else if (update.state === 'off' || update.state === 'closed') {
					this.debug('info', `Device ${update.device} deactivated`);
				}

			} else {
				this.debug('warning', `DOM element not found: ${update.id}`);
			}
		});

		return appliedCount;
	}

	// ==================== ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ ====================

	buildWsUrl() {
		// Используем хост из конфига, если он нормальный
		let host = this.config.host || window.location.hostname;

		// Если host из конфига локальный, а страница сетевая - исправляем
		if ((host === 'localhost' || host === '127.0.0.1') &&
			window.location.hostname !== 'localhost' &&
			window.location.hostname !== '127.0.0.1') {

			// Используем хост страницы
			host = window.location.hostname;
			console.log('Auto-corrected localhost to page host:', host);
		}

		return `ws://${host}:${this.config.port}${this.config.path}`;
	}

	startPingTimer() {
		if (this.pingTimer) {
			clearInterval(this.pingTimer);
		}

		this.pingTimer = setInterval(() => {
			if (this.ws && this.ws.readyState === WebSocket.OPEN) {
				this.ws.send(JSON.stringify({
					type: 'ping',
					timestamp: Date.now(),
					clientId: this.clientId
				}));
				this.debug('debug', 'Sent ping to server');
			}
		}, this.config.pingInterval);
	}

	scheduleReconnect() {
		if (this.reconnectTimer) {
			clearTimeout(this.reconnectTimer);
		}

		if (this.reconnectAttempts < this.config.maxReconnectAttempts) {
			this.reconnectAttempts++;
			const delay = this.config.reconnectDelay * this.reconnectAttempts;

			this.debug('warning', `Scheduled reconnect in ${delay}ms (attempt ${this.reconnectAttempts})`);

			this.reconnectTimer = setTimeout(() => {
				if (!this.isManualDisconnect) {
					this.connect();
				}
			}, delay);
		} else {
			this.debug('error', `Max reconnect attempts reached (${this.config.maxReconnectAttempts})`);
		}
	}

	clearTimers() {
		if (this.pingTimer) {
			clearInterval(this.pingTimer);
			this.pingTimer = null;
		}

		if (this.reconnectTimer) {
			clearTimeout(this.reconnectTimer);
			this.reconnectTimer = null;
		}
	}

	// ==================== ОБНОВЛЕНИЕ СТАТУСА ====================

	updateStatus(status, message) {
		this.status = status;
		this.debug('info', `Status: ${status} - ${message}`);

		const button = document.getElementById('websocketStatus');
		const textSpan = document.getElementById('websocketStatusText');

		if (button && textSpan) {
			// Обновляем CSS классы
			button.classList.remove(
				'menuwebsocket-disconnected',
				'menuwebsocket-connected',
				'menuwebsocket-error',
				'menuwebsocket-connecting'
			);
			button.classList.add('menuwebsocket-' + status);

			// Обновляем текст кнопки
			const buttonTexts = {
				'connected': 'WS',
				'disconnected': 'WS',
				'connecting': 'WS...',
				'reconnecting': 'WS...',
				'error': 'WS!',
				'timeout': 'WS',
				'disabled': 'WS'
			};

			textSpan.textContent = buttonTexts[status] || 'WS';

			// Обновляем tooltip с действием
			const actionTexts = {
				'connected': 'Connected. Click to disconnect',
				'disconnected': 'Offline. Click to reload page and start server',
				'connecting': 'Connecting...',
				'reconnecting': 'Reconnecting...',
				'error': 'Error! Click to reload page and restart',
				'timeout': 'Timeout. Click to reload page and restart',
				'disabled': 'WebSocket disabled'
			};

			button.title = `WebSocket: ${message}. ${actionTexts[status] || ''}`;
		}
	}

	// ==================== PUBLIC API ====================

	getStatus() {
		return {
			status: this.status,
			wsConnected: this.ws && this.ws.readyState === WebSocket.OPEN,
			activeDevices: this.activeDevices.size,
			reconnectAttempts: this.reconnectAttempts,
			debugMessages: this.debugMessages.length,
			config: this.config
		};
	}

	getDebugMessages(limit = 20) {
		return this.debugMessages.slice(0, limit);
	}

	clearDebugMessages() {
		this.debugMessages = [];
		const debugConsole = document.getElementById('debugLog');
		if (debugConsole) {
			debugConsole.innerHTML = '';
		}
	}
}

// ==================== ГЛОБАЛЬНАЯ ИНИЦИАЛИЗАЦИЯ ====================

document.addEventListener('DOMContentLoaded', () => {
	console.log('DOM Content Loaded - Stateful WebSocket Client 0.3.3');

	// Проверяем конфигурацию
	if (!window.DASHBOARD_CONFIG) {
		window.DASHBOARD_CONFIG = {};
	}

	if (!window.DASHBOARD_CONFIG.websocket) {
		console.warn('No websocket config in DASHBOARD_CONFIG, using defaults');
		window.DASHBOARD_CONFIG.websocket = {
			enabled: true,
			host: window.location.hostname || 'localhost',
			port: 8080,
			path: '/ws',
			autoConnect: true,
			reconnectDelay: 3000,
			maxReconnectAttempts: 5,
			pingInterval: 30000,
			debugToConsole: true,
			debugLevel: 'info'
		};
	}

	const wsConfig = window.DASHBOARD_CONFIG.websocket;

	if (!wsConfig.enabled) {
		console.warn('WebSocket not enabled in config');
		return;
	}

	console.log('Creating Stateful WebSocket Client 0.3.3');
	console.log('WebSocket config:', wsConfig);

	// Создаем экземпляр клиента
	window.statefulWSClient = new StatefulWebSocketClient(wsConfig);

	// Глобальные функции (для совместимости)
	window.connectStatefulWebSocket = () => {
		if (window.statefulWSClient) window.statefulWSClient.connect();
	};

	window.disconnectStatefulWebSocket = () => {
		if (window.statefulWSClient) window.statefulWSClient.disconnect();
	};

	window.toggleWebSocketConnection = () => {
		if (window.statefulWSClient) {
			const client = window.statefulWSClient;
			switch (client.status) {
				case 'disconnected':
				case 'error':
				case 'timeout':
					client.startPageReload();
					break;
				case 'connected':
					client.disconnect();
					break;
			}
		}
	};

	window.getWebSocketDebug = (limit = 20) => {
		if (window.statefulWSClient) return window.statefulWSClient.getDebugMessages(limit);
		return [];
	};

	window.clearWebSocketDebug = () => {
		if (window.statefulWSClient) window.statefulWSClient.clearDebugMessages();
	};

	console.log('Stateful WebSocket Client 0.3.3 initialized');
});