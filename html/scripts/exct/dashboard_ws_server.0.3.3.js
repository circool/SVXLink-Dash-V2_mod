/**
 * @filesource /scripts/exct/dashboard_ws_server.0.3.3.js
 * @version 0.3.3
 * @date 2026.01.04
 * @author vladimir@tsurkanenko.ru
 * @description Stateful WebSocket сервер для SvxLink Dashboard
 * 
 * @section Назначение
 * Stateful WebSocket сервер с минимальным функционалом:
 * - Отслеживание журнала в реальном времени
 * - Жестко заданные типы событий (transmitter/squelch/talkgroup_activity)
 * - Формирование активных устройств
 * - Автоматическое управление мониторингом лога
 * - Отключение при отсутствии клиентов (15 сек таймаут)
 * @note Новое в 0.3.3
 * - Добавлена обработка talkgroup_activity (talker_start/talker_stop) для рефлекторов
 * - Формирование обновлений позывных и статусов TX при активности в рефлекторах
 * - Обновление длительности для INCOMING статуса
 */

const WebSocket = require('ws');
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

// ==================== КОНСТАНТЫ И КОНФИГУРАЦИЯ ====================

const CONFIG = {
	version: '0.3.3',
	ws: {
		host: '0.0.0.0',
		port: 8080,
		clientTimeout: 15000 // мс до выключения при отсутствии клиентов
	},
	log: {
		path: '/var/log/svxlink',
		bufferTimeout: 3, // мс для буферизации строк
		maxBufferSize: 50
	},
	events: {
		// Жестко заданные типы событий для версии 0.3.3
		types: ['transmitter', 'squelch', 'talkgroup_activity']
	},
	duration: {
		updateInterval: 1000, // Обновление длительности каждую секунду
		maxDuration: 3600 // Максимальная длительность в секундах (1 час)
	}
};

// ==================== СЕРВИС ЛОГИРОВАНИЯ ====================

function getTimestamp() {
	const now = new Date();
	return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;
}

function log(message, level = 'INFO') {
	const timestamp = getTimestamp();
	const logMessage = `[${timestamp}] [${level}] ${message}`;
	console.log(logMessage);

	// Также пишем в файл если нужно
	if (CONFIG.log.file) {
		fs.appendFileSync(CONFIG.log.file, logMessage + '\n');
	}
}

// ==================== ВСТРОЕННЫЙ ПАРСЕР ====================

class SimpleParser {
	constructor() {
		this.patterns = [
			// Transmitter ON: "MultiTx: Turning the transmitter ON"
			{
				regex: /^(.+?): (\w+): Turning the transmitter ON$/,
				handler: (match) => ({
					type: 'transmitter',
					event: 'on',
					device: match[2],
					timestamp: match[1],
					raw: match[0]
				})
			},
			// Transmitter OFF: "MultiTx: Turning the transmitter OFF"
			{
				regex: /^(.+?): (\w+): Turning the transmitter OFF$/,
				handler: (match) => ({
					type: 'transmitter',
					event: 'off',
					device: match[2],
					timestamp: match[1],
					raw: match[0]
				})
			},
			// Squelch OPEN: "Rx1: The squelch is OPEN"
			{
				regex: /^(.+?): (\w+): The squelch is OPEN$/,
				handler: (match) => ({
					type: 'squelch',
					event: 'open',
					device: match[2],
					timestamp: match[1],
					raw: match[0]
				})
			},
			// Squelch CLOSED: "Rx1: The squelch is CLOSED"
			{
				regex: /^(.+?): (\w+): The squelch is CLOSED$/,
				handler: (match) => ({
					type: 'squelch',
					event: 'closed',
					device: match[2],
					timestamp: match[1],
					raw: match[0]
				})
			},
			// Talker start: "Dec 31 2024 23:59:59.999: Refl1: Talker start on TG #9999: CALLSIGN"
			{
				regex: /^(.+?): (\S+): Talker start on TG #(\d*): (\S+)$/,
				handler: (match) => ({
					type: 'talkgroup_activity',
					event: 'talker_start',
					device: match[2],
					talkgroup: match[3],
					callsign: match[4],
					timestamp: match[1],
					raw: match[0]
				})
			},
			// Talker stop: "Dec 31 2024 23:59:59.999: Refl1: Talker stop on TG #9999: CALLSIGN"
			{
				regex: /^(.+?): (\S+): Talker stop on TG #(\d+): (\S+)$/,
				handler: (match) => ({
					type: 'talkgroup_activity',
					event: 'talker_stop',
					device: match[2],
					talkgroup: match[3],
					callsign: match[4],
					timestamp: match[1],
					raw: match[0]
				})
			}
		];
	}

	parse(line) {
		const trimmed = line.trim();
		if (!trimmed) return null;

		for (const pattern of this.patterns) {
			const match = trimmed.match(pattern.regex);
			if (match) {
				return pattern.handler(match);
			}
		}

		return null; // Не распознано
	}
}

// ==================== МЕНЕДЖЕР СОСТОЯНИЯ УСТРОЙСТВ ====================

class DeviceStateManager {
	constructor() {
		this.activeDevices = new Map(); // transmitter/squelch: device -> {state, startTime, type, lastDurationUpdate}
		this.activeTalkgroups = new Map(); // talkgroup активность: device -> {callsign, talkgroup, startTime, lastDurationUpdate}
		this.parser = new SimpleParser();
	}

	// ==================== ОБРАБОТКА ОСНОВНЫХ СОБЫТИЙ ====================

	processEvent(event) {
		const { device, type, event: eventState } = event;

		if (!device) return null;

		let update = null;

		if (eventState === 'on' || eventState === 'open') {
			// Активация устройства
			update = this.activateDevice(device, type, eventState, event.timestamp);
		} else if (eventState === 'off' || eventState === 'closed') {
			// Деактивация устройства
			update = this.deactivateDevice(device, type, eventState);
		}

		return update;
	}

	activateDevice(device, type, state, timestamp) {
		const now = Date.now();

		const deviceState = {
			device: device,
			type: type, // 'transmitter' или 'squelch'
			state: state, // 'on' или 'open'
			startTime: now,
			logTimestamp: timestamp,
			lastUpdate: now,
			lastDurationUpdate: now // Время последнего обновления длительности
		};

		this.activeDevices.set(device, deviceState);

		return {
			action: 'device_activated',
			device: deviceState,
			update: this.createDeviceUpdate(deviceState)
		};
	}

	deactivateDevice(device, type, state) {
		if (this.activeDevices.has(device)) {
			const deviceState = this.activeDevices.get(device);
			const duration = Date.now() - deviceState.startTime;

			this.activeDevices.delete(device);

			return {
				action: 'device_deactivated',
				device: device,
				type: type,
				state: state,
				duration: duration,
				update: this.createDeviceDeactivationUpdate(device, type, state, duration)
			};
		}

		return null;
	}

	// ==================== ОБРАБОТКА TALKGROUP СОБЫТИЙ ====================

	processTalkgroupEvent(event) {
		const { device, event: state, callsign, talkgroup, timestamp } = event;

		if (state === 'talker_start') {
			return this.activateTalkgroup(device, callsign, talkgroup, timestamp);
		} else if (state === 'talker_stop') {
			return this.deactivateTalkgroup(device);
		}

		return null;
	}

	activateTalkgroup(device, callsign, talkgroup, timestamp) {
		const now = Date.now();

		const talkgroupState = {
			device: device,
			callsign: callsign,
			talkgroup: talkgroup,
			startTime: now,
			logTimestamp: timestamp,
			lastDurationUpdate: now
		};

		this.activeTalkgroups.set(device, talkgroupState);

		return {
			action: 'talkgroup_started',
			talkgroup: talkgroupState,
			updates: this.createTalkgroupActivationUpdates(talkgroupState)
		};
	}

	deactivateTalkgroup(device) {
		if (this.activeTalkgroups.has(device)) {
			const talkgroupState = this.activeTalkgroups.get(device);
			const duration = Date.now() - talkgroupState.startTime;

			this.activeTalkgroups.delete(device);

			return {
				action: 'talkgroup_stopped',
				device: device,
				callsign: talkgroupState.callsign,
				talkgroup: talkgroupState.talkgroup,
				duration: duration,
				updates: this.createTalkgroupDeactivationUpdates(device, talkgroupState.callsign, talkgroupState.talkgroup)
			};
		}

		return null;
	}

	// ==================== ФОРМАТИРОВАНИЕ ДЛИТЕЛЬНОСТИ ====================

	formatDuration(milliseconds) {
		const seconds = Math.floor(milliseconds / 1000);

		if (seconds < 60) {
			return `${seconds}s`;
		} else if (seconds < 3600) {
			const minutes = Math.floor(seconds / 60);
			const remainingSeconds = seconds % 60;
			return `${minutes}m ${remainingSeconds}s`;
		} else {
			const hours = Math.floor(seconds / 3600);
			const minutes = Math.floor((seconds % 3600) / 60);
			return `${hours}h ${minutes}m`;
		}
	}

	// ==================== СОЗДАНИЕ ОБНОВЛЕНИЙ ====================

	createDeviceUpdate(deviceState) {
		const deviceType = deviceState.type === 'transmitter' ? 'tx' : 'rx';
		const baseStatus = deviceState.state === 'on' ? 'TRANSMIT' : 'RECEIVE';

		// Вычисляем текущую длительность
		const currentDuration = Date.now() - deviceState.startTime;
		const formattedDuration = this.formatDuration(currentDuration);

		// Формируем полный статус с временем
		const fullStatus = `${baseStatus} ( ${formattedDuration} )`;

		const statusId = deviceState.type === 'transmitter' ?
			`${deviceState.device}_StatusTX` : `${deviceState.device}_StatusRX`;

		return {
			id: statusId,
			content: fullStatus,
			baseStatus: baseStatus,
			duration: currentDuration,
			formattedDuration: formattedDuration,
			type: 'radio_status',
			device: deviceState.device,
			device_type: deviceType,
			state: deviceState.state,
			class: deviceState.state === 'on' ? 'inactive-mode-cell' :
				deviceState.state === 'open' ? 'active-mode-cell' : null,
			timestamp: Math.floor(deviceState.startTime / 1000),
			currentTime: Date.now(),
			parser: 'server_0.3.3'
		};
	}

	createDeviceDeactivationUpdate(device, type, state, duration) {
		const deviceType = type === 'transmitter' ? 'tx' : 'rx';
		const status = 'STANDBY';
		const statusId = type === 'transmitter' ?
			`${device}_StatusTX` : `${device}_StatusRX`;

		return {
			id: statusId,
			content: status,
			type: 'radio_status',
			device: device,
			device_type: deviceType,
			state: state,
			duration: duration,
			formattedDuration: this.formatDuration(duration),
			class: null, // STANDBY - без класса
			timestamp: Math.floor(Date.now() / 1000),
			parser: 'server_0.3.3'
		};
	}

	createTalkgroupActivationUpdates(talkgroupState) {
		const updates = [];

		// 1. Обновление позывного (device + "Callsign" без подчеркивания)
		updates.push({
			id: `${talkgroupState.device}Callsign`,
			content: talkgroupState.callsign,
			type: 'callsign_update',
			device: talkgroupState.device,
			device_type: 'talkgroup',
			state: 'talker_start',
			class: null,
			talkgroup: talkgroupState.talkgroup,
			timestamp: Math.floor(talkgroupState.startTime / 1000),
			parser: 'server_0.3.3'
		});

		// 2. Начальное обновление статуса TX (INCOMING)
		const initialStatus = this.createTalkgroupStatusUpdate(talkgroupState);
		updates.push(initialStatus);

		return updates;
	}

	createTalkgroupDeactivationUpdates(device, callsign, talkgroup) {
		const updates = [];

		// 1. Очистка позывного
		updates.push({
			id: `${device}Callsign`,
			content: '', // Очищаем поле
			type: 'callsign_update',
			device: device,
			device_type: 'talkgroup',
			state: 'talker_stop',
			class: null,
			talkgroup: talkgroup,
			timestamp: Math.floor(Date.now() / 1000),
			parser: 'server_0.3.3'
		});

		// 2. Очистка статуса TX
		updates.push({
			id: `${device}_StatusTX`,
			content: '', // Очищаем статус
			type: 'radio_status',
			device: device,
			device_type: 'tx',
			state: 'closed',
			class: null,
			timestamp: Math.floor(Date.now() / 1000),
			parser: 'server_0.3.3'
		});

		return updates;
	}

	createTalkgroupStatusUpdate(talkgroupState) {
		const currentDuration = Date.now() - talkgroupState.startTime;
		const formattedDuration = this.formatDuration(currentDuration);

		// Форматируем как "INCOMING ( 15s )"
		const fullStatus = `INCOMING ( ${formattedDuration} )`;

		return {
			id: `${talkgroupState.device}_StatusTX`,
			content: fullStatus,
			baseStatus: 'INCOMING',
			duration: currentDuration,
			formattedDuration: formattedDuration,
			type: 'radio_status',
			device: talkgroupState.device,
			device_type: 'tx',
			state: 'incoming',
			class: 'inactive-mode-cell',
			callsign: talkgroupState.callsign,
			talkgroup: talkgroupState.talkgroup,
			timestamp: Math.floor(talkgroupState.startTime / 1000),
			currentTime: Date.now(),
			parser: 'server_0.3.3'
		};
	}

	// ==================== ПОЛУЧЕНИЕ ОБНОВЛЕНИЙ ДЛИТЕЛЬНОСТИ ====================

	getActiveDevices() {
		return Array.from(this.activeDevices.values());
	}

	getActiveTalkgroups() {
		return Array.from(this.activeTalkgroups.values());
	}

	// Получение обновлений длительности для активных устройств
	getDurationUpdates() {
		const now = Date.now();
		const updates = [];

		// Обновления для transmitter/squelch устройств
		for (const [deviceName, deviceState] of this.activeDevices) {
			// Проверяем, нужно ли обновлять (прошла ли секунда)
			if (now - deviceState.lastDurationUpdate >= 1000) {
				const update = this.createDeviceUpdate(deviceState);
				updates.push(update);
				deviceState.lastDurationUpdate = now;
			}
		}

		// Обновления для talkgroup активности
		for (const [deviceName, talkgroupState] of this.activeTalkgroups) {
			// Проверяем, нужно ли обновлять (прошла ли секунда)
			if (now - talkgroupState.lastDurationUpdate >= 1000) {
				const update = this.createTalkgroupStatusUpdate(talkgroupState);
				updates.push(update);
				talkgroupState.lastDurationUpdate = now;
			}
		}

		return updates;
	}
}

// ==================== ОСНОВНОЙ КЛАСС СЕРВЕРА ====================

class StatefulWebSocketServer {
	constructor(config = {}) {
		this.config = { ...CONFIG, ...config };
		this.wss = null;
		this.stateManager = new DeviceStateManager();
		this.clients = new Set();
		this.tailProcess = null;
		this.isMonitoring = false;
		this.shutdownTimer = null;

		// Таймеры
		this.durationTimer = null;

		// Статистика
		this.stats = {
			startedAt: Date.now(),
			eventsProcessed: 0,
			clientsConnected: 0,
			clientsDisconnected: 0,
			durationUpdatesSent: 0,
			talkgroupEvents: 0
		};
	}

	// ==================== УПРАВЛЕНИЕ СЕРВЕРОМ ====================

	start() {
		log(`Starting Stateful WebSocket Server v${this.config.version}`);
		log(`WebSocket: ${this.config.ws.host}:${this.config.ws.port}`);
		log(`Log file: ${this.config.log.path}`);
		log(`Supported events: ${this.config.events.types.join(', ')}`);

		try {
			// Проверка файла лога
			if (!fs.existsSync(this.config.log.path)) {
				log(`Log file not found: ${this.config.log.path}`, 'WARNING');
				log('Will attempt to monitor anyway', 'INFO');
			}

			// Запуск WebSocket сервера
			this.startWebSocket();

			// Таймер выключения при отсутствии клиентов
			this.startShutdownTimer();

			log('Server started successfully', 'INFO');

		} catch (error) {
			log(`Failed to start server: ${error.message}`, 'ERROR');
			process.exit(1);
		}
	}

	startWebSocket() {
		this.wss = new WebSocket.Server({
			port: this.config.ws.port,
			host: this.config.ws.host
		});

		this.wss.on('connection', (ws, req) => {
			this.handleClientConnection(ws, req);
		});

		this.wss.on('error', (error) => {
			log(`WebSocket server error: ${error.message}`, 'ERROR');
		});

		log(`WebSocket server listening on ${this.config.ws.host}:${this.config.ws.port}`);
	}

	// ==================== УПРАВЛЕНИЕ КЛИЕНТАМИ ====================

	handleClientConnection(ws, req) {
		const clientId = `client_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
		const clientIp = req.socket.remoteAddress;

		this.clients.add({
			id: clientId,
			ws: ws,
			ip: clientIp,
			connectedAt: Date.now()
		});

		this.stats.clientsConnected++;

		log(`Client ${clientId} connected from ${clientIp} (total: ${this.clients.size})`);

		// Отправка приветственного сообщения
		this.sendWelcome(ws, clientId);

		// Отправка текущего состояния активных устройств
		this.sendCurrentState(ws);

		// Запуск мониторинга если это первый клиент
		if (this.clients.size === 1) {
			this.startLogMonitoring();
			this.startDurationTimer();
			this.clearShutdownTimer();
		}

		// Обработчики событий клиента
		ws.on('message', (data) => {
			this.handleClientMessage(clientId, data);
		});

		ws.on('close', () => {
			this.handleClientDisconnect(clientId);
		});

		ws.on('error', (error) => {
			log(`Client ${clientId} error: ${error.message}`, 'ERROR');
		});
	}

	handleClientDisconnect(clientId) {
		// Находим и удаляем клиента
		let disconnectedClient = null;
		for (const client of this.clients) {
			if (client.id === clientId) {
				disconnectedClient = client;
				break;
			}
		}

		if (disconnectedClient) {
			this.clients.delete(disconnectedClient);
			this.stats.clientsDisconnected++;

			log(`Client ${clientId} disconnected (remaining: ${this.clients.size})`);

			// Останавливаем мониторинг если клиентов не осталось
			if (this.clients.size === 0) {
				this.stopLogMonitoring();
				this.stopDurationTimer();
				this.startShutdownTimer();
			}
		}
	}

	handleClientMessage(clientId, data) {
		try {
			const message = JSON.parse(data);

			if (message.type === 'ping') {
				this.sendToClient(clientId, {
					type: 'pong',
					timestamp: Date.now()
				});
			}

		} catch (error) {
			// Игнорируем ошибки парсинга
		}
	}

	sendWelcome(ws, clientId) {
		ws.send(JSON.stringify({
			type: 'welcome',
			version: this.config.version,
			clientId: clientId,
			serverTime: Date.now(),
			message: 'Connected to Stateful WebSocket Server 0.3.3 with talkgroup activity and duration updates'
		}));
	}

	sendCurrentState(ws) {
		const updates = [];

		// Активные transmitter/squelch устройства
		const activeDevices = this.stateManager.getActiveDevices();
		if (activeDevices.length > 0) {
			const deviceUpdates = activeDevices.map(device =>
				this.stateManager.createDeviceUpdate(device)
			);
			updates.push(...deviceUpdates);
		}

		// Активные talkgroups
		const activeTalkgroups = this.stateManager.getActiveTalkgroups();
		if (activeTalkgroups.length > 0) {
			activeTalkgroups.forEach(talkgroup => {
				const talkgroupUpdates = this.stateManager.createTalkgroupActivationUpdates(talkgroup);
				updates.push(...talkgroupUpdates);
			});
		}

		if (updates.length > 0) {
			ws.send(JSON.stringify({
				type: 'updates',
				updates: updates,
				timestamp: Date.now(),
				processed: updates.length,
				message: 'Current state'
			}));
		}
	}

	sendToClient(clientId, message) {
		for (const client of this.clients) {
			if (client.id === clientId && client.ws.readyState === WebSocket.OPEN) {
				client.ws.send(JSON.stringify(message));
				return true;
			}
		}
		return false;
	}

	broadcast(message) {
		let sentCount = 0;

		for (const client of this.clients) {
			if (client.ws.readyState === WebSocket.OPEN) {
				client.ws.send(JSON.stringify(message));
				sentCount++;
			}
		}

		return sentCount;
	}

	// ==================== ТАЙМЕР ОБНОВЛЕНИЯ ДЛИТЕЛЬНОСТИ ====================

	startDurationTimer() {
		if (this.durationTimer) {
			clearInterval(this.durationTimer);
		}

		this.durationTimer = setInterval(() => {
			if (this.clients.size > 0) {
				const durationUpdates = this.stateManager.getDurationUpdates();

				if (durationUpdates.length > 0) {
					const message = {
						type: 'updates',
						updates: durationUpdates,
						timestamp: Date.now(),
						processed: durationUpdates.length,
						message: 'Duration updates'
					};

					const sentCount = this.broadcast(message);

					if (sentCount > 0) {
						this.stats.durationUpdatesSent++;
						log(`Sent ${durationUpdates.length} duration updates to ${sentCount} clients`, 'DEBUG');
					}
				}
			}
		}, this.config.duration.updateInterval);

		log(`Duration timer started (${this.config.duration.updateInterval}ms interval)`);
	}

	stopDurationTimer() {
		if (this.durationTimer) {
			clearInterval(this.durationTimer);
			this.durationTimer = null;
			log('Duration timer stopped');
		}
	}

	// ==================== УПРАВЛЕНИЕ МОНИТОРИНГОМ ЛОГА ====================

	startLogMonitoring() {
		if (this.isMonitoring) {
			log('Log monitoring already active', 'WARNING');
			return;
		}

		log('Starting log monitoring...');

		this.tailProcess = spawn('tail', ['-F', '-n', '0', this.config.log.path]);
		this.isMonitoring = true;

		// Буфер для группировки строк
		let logBuffer = [];
		let bufferTimer = null;

		this.tailProcess.stdout.on('data', (data) => {
			const lines = data.toString().split('\n').filter(line => line.trim());

			if (lines.length > 0) {
				// Добавляем строки в буфер
				logBuffer.push(...lines);

				// Сбрасываем таймер
				if (bufferTimer) {
					clearTimeout(bufferTimer);
				}

				// Запускаем новый таймер обработки
				bufferTimer = setTimeout(() => {
					if (logBuffer.length > 0) {
						this.processLogBuffer(logBuffer);
						logBuffer = [];
					}
					bufferTimer = null;
				}, this.config.log.bufferTimeout);
			}
		});

		this.tailProcess.stderr.on('data', (data) => {
			const error = data.toString().trim();
			if (error) {
				log(`Tail error: ${error}`, 'ERROR');
			}
		});

		this.tailProcess.on('close', (code, signal) => {
			log(`Tail process stopped. Code: ${code}, Signal: ${signal}`, 'INFO');
			this.isMonitoring = false;

			// Перезапуск если есть клиенты
			if (this.clients.size > 0) {
				setTimeout(() => this.startLogMonitoring(), 2000);
			}
		});

		log('Log monitoring started', 'INFO');
	}

	stopLogMonitoring() {
		if (this.tailProcess && this.isMonitoring) {
			log('Stopping log monitoring...');

			this.tailProcess.kill('SIGTERM');
			this.tailProcess = null;
			this.isMonitoring = false;

			log('Log monitoring stopped', 'INFO');
		}
	}

	processLogBuffer(buffer) {
		const linesToProcess = buffer.slice(-this.config.log.maxBufferSize);
		let updates = [];

		linesToProcess.forEach(line => {
			// Парсим строку
			const event = this.stateManager.parser.parse(line);

			if (event) {
				this.stats.eventsProcessed++;

				// Обработка transmitter и squelch
				if (event.type === 'transmitter' || event.type === 'squelch') {
					const result = this.stateManager.processEvent(event);
					if (result && result.update) {
						updates.push(result.update);
					}
				}
				// Обработка talkgroup_activity
				else if (event.type === 'talkgroup_activity') {
					this.stats.talkgroupEvents++;
					const result = this.stateManager.processTalkgroupEvent(event);
					if (result && result.updates) {
						updates.push(...result.updates);

						if (event.event === 'talker_start') {
							log(`Talkgroup START: ${event.device} TG#${event.talkgroup} by ${event.callsign}`, 'INFO');
						} else {
							log(`Talkgroup STOP: ${event.device} TG#${event.talkgroup} by ${event.callsign}`, 'INFO');
						}
					}
				}
			}
		});

		// Рассылаем обновления клиентам
		if (updates.length > 0 && this.clients.size > 0) {
			const message = {
				type: 'updates',
				updates: updates,
				timestamp: Date.now(),
				processed: updates.length,
				message: 'Log updates'
			};

			const sentCount = this.broadcast(message);

			if (sentCount > 0) {
				const eventTypes = updates.map(u => u.type).filter((v, i, a) => a.indexOf(v) === i);
				log(`Sent ${updates.length} updates (types: ${eventTypes.join(', ')}) to ${sentCount} clients`, 'DEBUG');
			}
		}
	}

	// ==================== УПРАВЛЕНИЕ ВЫКЛЮЧЕНИЕМ ====================

	startShutdownTimer() {
		if (this.shutdownTimer) {
			clearTimeout(this.shutdownTimer);
		}

		this.shutdownTimer = setTimeout(() => {
			if (this.clients.size === 0) {
				log('No clients connected for 5000ms, shutting down...', 'INFO');
				this.gracefulShutdown();
			}
		}, this.config.ws.clientTimeout);

		log(`Shutdown timer set for ${this.config.ws.clientTimeout}ms`, 'DEBUG');
	}

	clearShutdownTimer() {
		if (this.shutdownTimer) {
			clearTimeout(this.shutdownTimer);
			this.shutdownTimer = null;
			log('Shutdown timer cleared', 'DEBUG');
		}
	}

	gracefulShutdown() {
		log('Initiating graceful shutdown...', 'INFO');

		// 1. Останавливаем таймеры
		this.stopDurationTimer();

		// 2. Останавливаем мониторинг лога
		this.stopLogMonitoring();

		// 3. Закрываем соединения с клиентами
		for (const client of this.clients) {
			if (client.ws.readyState === WebSocket.OPEN) {
				client.ws.close(1000, 'Server shutdown');
			}
		}

		// 4. Закрываем WebSocket сервер
		if (this.wss) {
			this.wss.close(() => {
				log('WebSocket server closed', 'INFO');

				// 5. Выводим статистику
				const uptime = Math.floor((Date.now() - this.stats.startedAt) / 1000);
				log(`Server uptime: ${uptime}s`);
				log(`Total events processed: ${this.stats.eventsProcessed}`);
				log(`Talkgroup events: ${this.stats.talkgroupEvents}`);
				log(`Duration updates sent: ${this.stats.durationUpdatesSent}`);
				log(`Clients connected: ${this.stats.clientsConnected}`);
				log(`Clients disconnected: ${this.stats.clientsDisconnected}`);
				log(`Active devices: ${this.stateManager.getActiveDevices().length}`);
				log(`Active talkgroups: ${this.stateManager.getActiveTalkgroups().length}`);

				log('Server shutdown complete', 'INFO');
				process.exit(0);
			});

			// Таймаут на закрытие
			setTimeout(() => {
				log('Forced shutdown after timeout', 'WARNING');
				process.exit(1);
			}, 3000);
		} else {
			process.exit(0);
		}
	}

	// ==================== СТАТИСТИКА ====================

	getStats() {
		const uptime = Math.floor((Date.now() - this.stats.startedAt) / 1000);

		return {
			version: this.config.version,
			uptime: uptime,
			clients: this.clients.size,
			activeDevices: this.stateManager.getActiveDevices().length,
			activeTalkgroups: this.stateManager.getActiveTalkgroups().length,
			monitoring: this.isMonitoring,
			durationTimerActive: !!this.durationTimer,
			stats: this.stats
		};
	}
}

// ==================== ТОЧКА ВХОДА ====================

function main() {
	// Обработка сигналов
	process.on('SIGINT', () => {
		log('SIGINT received, shutting down...', 'INFO');

		const server = global.serverInstance;
		if (server) {
			server.gracefulShutdown();
		} else {
			process.exit(0);
		}
	});

	process.on('SIGTERM', () => {
		log('SIGTERM received, shutting down...', 'INFO');

		const server = global.serverInstance;
		if (server) {
			server.gracefulShutdown();
		} else {
			process.exit(0);
		}
	});

	// Запуск сервера
	const server = new StatefulWebSocketServer();
	global.serverInstance = server;

	server.start();
}

// Запуск если файл исполняется напрямую
if (require.main === module) {
	main();
}

// Экспорт для тестирования
module.exports = {
	StatefulWebSocketServer,
	SimpleParser,
	DeviceStateManager
};