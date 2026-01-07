/**
 * @filesource /scripts/exct/dashboard_ws_server.0.3.4.js
 * @version 0.3.4
 * @date 2026.01.07
 * @author vladimir@tsurkanenko.ru
 * @description Stateful WebSocket сервер для SvxLink Dashboard с поддержкой модулей
 * 
 * @section Назначение
 * Stateful WebSocket сервер с поддержкой:
 * - Отслеживание журнала в реальном времени
 * - События transmitter/squelch/talkgroup_activity
 * - События активации/деактивации модулей (один активный модуль на логику)
 * - События подключения/отключения модулей EchoLink/Frn
 * - Формирование активных устройств и модулей
 * - Автоматическое управление мониторингом лога
 * - Отключение при отсутствии клиентов (15 сек таймаут)
 * @since 0.3.4
 * - Добавлена обработка событий модулей (activation/deactivation)
 * - Добавлена обработка подключений модулей EchoLink/Frn
 * - Поддержка uptime для модулей с форматом ЧЧ:ММ:СС
 * - Восстановление состояния при "потерянных" событиях
 * - Правильная логика: один активный модуль на логику
 * @todo Добавить обновление списка подключенных узлов для модулей Echolink & Frn
 */

const WebSocket = require('ws');
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

// ==================== КОНСТАНТЫ И КОНФИГУРАЦИЯ ====================

const CONFIG = {
	version: '0.3.4',
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
		types: ['transmitter', 'squelch', 'talkgroup_activity', 'module_activation', 'module_connection']
	},
	duration: {
		updateInterval: 1000, // Обновление длительности каждую секунду
		maxDuration: 86400 // Максимальная длительность в секундах (24 часа)
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
			},
			// Активация модуля: "SimplexLogic: Activating module EchoLink..."
			{
				regex: /^(.+?): (\S+): Activating module (\S+)\.\.\.$/,
				handler: (match) => ({
					type: 'module_activation',
					event: 'activating',
					logic: match[2],
					module: match[3],
					timestamp: match[1],
					raw: match[0]
				})
			},
			// Деактивация модуля: "SimplexLogic: Deactivating module EchoLink..."
			{
				regex: /^(.+?): (\S+): Deactivating module (\S+)\.\.\.$/,
				handler: (match) => ({
					type: 'module_activation',
					event: 'deactivating',
					logic: match[2],
					module: match[3],
					timestamp: match[1],
					raw: match[0]
				})
			},
			// EchoLink: QSO state changed to CONNECTED/DISCONNECTED
			{
				regex: /^(.+?): (\S+): EchoLink QSO state changed to (\S+)$/,
				handler: (match) => ({
					type: 'module_connection',
					event: 'state_changed',
					module_type: 'EchoLink',
					node: match[2],
					state: match[3].toLowerCase(), // connected/disconnected
					timestamp: match[1],
					raw: match[0]
				})
			},
			// Frn: login stage 2 completed
			{
				regex: /^(.+?): login stage 2 completed: (.+)$/,
				handler: (match) => ({
					type: 'module_connection',
					event: 'login_completed',
					module_type: 'Frn',
					xml_data: match[2],
					timestamp: match[1],
					raw: match[0]
				})
			},
			// Frn: подключение к серверу (дополнительный паттерн для надежности)
			{
				regex: /^(.+?): Connected to FRn server (.+)$/,
				handler: (match) => ({
					type: 'module_connection',
					event: 'connected',
					module_type: 'Frn',
					server: match[2],
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

// ==================== МЕНЕДЖЕР СОСТОЯНИЯ УСТРОЙСТВ И МОДУЛЕЙ ====================

class DeviceStateManager {
	constructor() {
		this.activeDevices = new Map(); // transmitter/squelch: device -> {state, startTime, type, lastDurationUpdate}
		this.activeTalkgroups = new Map(); // talkgroup активность: device -> {callsign, talkgroup, startTime, lastDurationUpdate}
		this.activeModules = new Map(); // logic -> {module, activatedAt, connectedAt, lastDurationUpdate, moduleType} - ОДИН АКТИВНЫЙ МОДУЛЬ НА ЛОГИКУ
		this.parser = new SimpleParser();
	}

	// ==================== ФОРМАТИРОВАНИЕ ВРЕМЕНИ ====================

	formatDuration(milliseconds) {
		const totalSeconds = Math.floor(milliseconds / 1000);
		const hours = Math.floor(totalSeconds / 3600).toString().padStart(2, '0');
		const minutes = Math.floor((totalSeconds % 3600) / 60).toString().padStart(2, '0');
		const seconds = (totalSeconds % 60).toString().padStart(2, '0');
		return `${hours}:${minutes}:${seconds}`;
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
			lastDurationUpdate: now
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

	// ==================== ОБРАБОТКА СОБЫТИЙ МОДУЛЕЙ ====================

	processModuleActivationEvent(event) {
		const { logic, module, event: state, timestamp } = event;

		if (state === 'activating') {
			return this.activateModule(logic, module, timestamp);
		} else if (state === 'deactivating') {
			return this.deactivateModule(logic, module);
		}

		return null;
	}

	activateModule(logic, module, timestamp) {
		const now = Date.now();

		// Получаем текущий активный модуль для этой логики
		const existingState = this.activeModules.get(logic);

		// Создаем новое состояние
		const moduleState = {
			module: module,
			activatedAt: now,
			connectedAt: (existingState && existingState.module === module) ? existingState.connectedAt : null,
			lastDurationUpdate: now,
			moduleType: this.detectModuleType(module),
			logTimestamp: timestamp
		};

		// Сохраняем (перезаписываем предыдущее состояние)
		this.activeModules.set(logic, moduleState);

		// Формируем обновления
		const updates = [];

		// Если был другой активный модуль → деактивируем его
		if (existingState && existingState.module !== module) {
			updates.push(this.createModuleDeactivationUpdate(logic, existingState.module, existingState));
			log(`Logic ${logic}: auto-deactivating ${existingState.module} for new module ${module}`, 'INFO');
		}

		// Активируем новый модуль
		updates.push(this.createModuleActivationUpdate(logic, moduleState));

		return {
			action: 'module_activated',
			module: moduleState,
			updates: updates
		};
	}

	deactivateModule(logic, module) {
		const existingState = this.activeModules.get(logic);

		if (existingState && existingState.module === module) {
			// Деактивируем именно тот модуль, который сейчас активен
			this.activeModules.delete(logic);
			return {
				action: 'module_deactivated',
				logic: logic,
				module: module,
				activationDuration: existingState.activatedAt ? Date.now() - existingState.activatedAt : 0,
				connectionDuration: existingState.connectedAt ? Date.now() - existingState.connectedAt : 0,
				updates: [this.createModuleDeactivationUpdate(logic, module, existingState)]
			};
		} else {
			// Модуль не активен, но получили событие деактивации (потерянное событие активации)
			log(`Received deactivation for inactive module ${logic}:${module}, performing cleanup`, 'WARNING');

			return {
				action: 'module_deactivated_cleanup',
				logic: logic,
				module: module,
				updates: [this.createModuleDeactivationUpdate(logic, module, null)]
			};
		}
	}

	// ==================== ОБРАБОТКА СОБЫТИЙ ПОДКЛЮЧЕНИЯ МОДУЛЕЙ ====================

	processModuleConnectionEvent(event) {
		const { module_type, event: state, node, server, xml_data, timestamp } = event;

		// Определяем логику из активных модулей
		let logic = null;
		let module = module_type;

		// Ищем логику с активным модулем данного типа
		for (const [logicName, moduleState] of this.activeModules) {
			if (moduleState.moduleType === module_type) {
				logic = logicName;
				module = moduleState.module;
				break;
			}
		}

		if (!logic) {
			// Нет активного модуля данного типа, но получили событие подключения
			// Создаем состояние с логикой по умолчанию
			logic = this.createFallbackLogicForModule(module_type);
			log(`No active ${module_type} module found for connection event, using fallback logic ${logic}`, 'WARNING');
		}

		if (state === 'state_changed' || state === 'login_completed' || state === 'connected') {
			const connected = (state === 'state_changed' && event.state === 'connected') ||
				state === 'login_completed' ||
				state === 'connected';

			if (connected) {
				return this.connectModule(logic, module, timestamp);
			} else {
				return this.disconnectModule(logic, module);
			}
		}

		return null;
	}

	createFallbackLogicForModule(moduleType) {
		// Создаем логику-заглушку для восстановления состояния
		const fallbackLogic = moduleType === 'EchoLink' ? 'SimplexLogic' : 'RepeaterLogic';

		const now = Date.now();
		const moduleState = {
			module: moduleType,
			activatedAt: now - 1000, // Минус 1 секунда для логики
			connectedAt: null,
			lastDurationUpdate: now,
			moduleType: moduleType,
			logTimestamp: new Date().toISOString()
		};

		this.activeModules.set(fallbackLogic, moduleState);
		log(`Created fallback state for ${moduleType} module with logic ${fallbackLogic}`, 'INFO');

		return fallbackLogic;
	}

	connectModule(logic, module, timestamp) {
		const existingState = this.activeModules.get(logic);
		const now = Date.now();

		if (!existingState) {
			// Модуль не активен, но получили событие подключения (потерянное событие активации)
			log(`Received connection for inactive module ${logic}:${module}, activating first`, 'WARNING');

			const moduleState = {
				module: module,
				activatedAt: now - 1000, // Минус 1 секунда для логики
				connectedAt: now,
				lastDurationUpdate: now,
				moduleType: this.detectModuleType(module),
				logTimestamp: timestamp
			};

			this.activeModules.set(logic, moduleState);

			return {
				action: 'module_connected',
				module: moduleState,
				updates: [
					this.createModuleActivationUpdate(logic, moduleState),
					this.createModuleConnectionUpdate(logic, moduleState, true)
				]
			};
		} else if (existingState.module === module) {
			// Модуль активен, устанавливаем время подключения (сброс таймера)
			existingState.connectedAt = now;
			existingState.lastDurationUpdate = now;

			return {
				action: 'module_connected',
				module: existingState,
				updates: [this.createModuleConnectionUpdate(logic, existingState, true)]
			};
		} else {
			// Активен другой модуль, но получили подключение для этого
			log(`Received connection for module ${module}, but active module is ${existingState.module}`, 'WARNING');
			return null;
		}
	}

	disconnectModule(logic, module) {
		const existingState = this.activeModules.get(logic);

		if (existingState && existingState.module === module) {
			const connectionDuration = existingState.connectedAt ?
				Date.now() - existingState.connectedAt : 0;

			existingState.connectedAt = null;
			existingState.lastDurationUpdate = Date.now();

			return {
				action: 'module_disconnected',
				module: existingState,
				connectionDuration: connectionDuration,
				updates: [this.createModuleConnectionUpdate(logic, existingState, false)]
			};
		} else {
			// Модуль не активен, но получили событие отключения
			log(`Received disconnection for inactive module ${logic}:${module}, ignoring`, 'WARNING');
			return null;
		}
	}

	detectModuleType(moduleName) {
		const lowerName = moduleName.toLowerCase();
		if (lowerName.includes('echolink')) return 'EchoLink';
		if (lowerName.includes('frn')) return 'Frn';
		return 'Other';
	}

	// ==================== СОЗДАНИЕ ОБНОВЛЕНИЙ ДЛЯ УСТРОЙСТВ ====================

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
			parser: 'server_0.3.4'
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
			parser: 'server_0.3.4'
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
			parser: 'server_0.3.4'
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
			parser: 'server_0.3.4'
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
			parser: 'server_0.3.4'
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
			parser: 'server_0.3.4'
		};
	}

	// ==================== СОЗДАНИЕ ОБНОВЛЕНИЙ ДЛЯ МОДУЛЕЙ ====================

	createModuleActivationUpdate(logic, moduleState) {
		const moduleId = `module${logic}${moduleState.module}`;
		const now = Date.now();

		// Вычисляем uptime с момента активации
		const uptime = moduleState.activatedAt ? now - moduleState.activatedAt : 0;
		const formattedUptime = this.formatDuration(uptime);

		// Формируем полный HTML для модуля с tooltip
		const tooltipHtml = `<a class="tooltip" href="#"><span><b>Uptime:</b> ${formattedUptime}</span>${moduleState.module}</a>`;

		return {
			id: moduleId,
			content: tooltipHtml, // Вставляем HTML вместо текста
			type: 'module_status',
			logic: logic,
			module: moduleState.module,
			moduleType: moduleState.moduleType,
			state: 'activated',
			uptime: uptime,
			formattedUptime: formattedUptime,
			class: 'paused-mode-cell',
			timestamp: Math.floor(now / 1000),
			parser: 'server_0.3.4'
		};
	}

	createModuleDeactivationUpdate(logic, module, moduleState) {
		const moduleId = `module${logic}${module}`;

		return {
			id: moduleId,
			content: module,
			type: 'module_status',
			logic: logic,
			module: module,
			state: 'deactivated',
			uptime: 0,
			formattedUptime: '00:00:00',
			tooltip: '', // Очищаем tooltip
			class: 'disabled-mode-cell', // Деактивирован
			timestamp: Math.floor(Date.now() / 1000),
			parser: 'server_0.3.4'
		};
	}

	createModuleConnectionUpdate(logic, moduleState, connected) {
		const moduleId = `module${logic}${moduleState.module}`;
		const now = Date.now();

		// Вычисляем uptime
		let uptime, formattedUptime;
		if (connected && moduleState.connectedAt) {
			uptime = now - moduleState.connectedAt;
		} else if (moduleState.activatedAt) {
			uptime = now - moduleState.activatedAt;
		} else {
			uptime = 0;
		}

		formattedUptime = this.formatDuration(uptime);

		// Формируем HTML с tooltip
		const tooltipHtml = `<a class="tooltip" href="#"><span><b>Uptime:</b> ${formattedUptime}</span>${moduleState.module}</a>`;

		return {
			id: moduleId,
			content: tooltipHtml, // Вставляем HTML вместо текста
			type: 'module_status',
			logic: logic,
			module: moduleState.module,
			moduleType: moduleState.moduleType,
			state: connected ? 'connected' : 'disconnected',
			uptime: uptime,
			formattedUptime: formattedUptime,
			class: connected ? 'active-mode-cell' : 'paused-mode-cell',
			timestamp: Math.floor(now / 1000),
			parser: 'server_0.3.4'
		};
	}

	// ==================== ПОЛУЧЕНИЕ АКТИВНЫХ СОСТОЯНИЙ ====================

	getActiveDevices() {
		return Array.from(this.activeDevices.values());
	}

	getActiveTalkgroups() {
		return Array.from(this.activeTalkgroups.values());
	}

	getActiveModules() {
		return Array.from(this.activeModules.values());
	}

	// ==================== ОБНОВЛЕНИЕ ДЛИТЕЛЬНОСТИ ДЛЯ ВСЕХ АКТИВНЫХ СОСТОЯНИЙ ====================

	getDurationUpdates() {
		const now = Date.now();
		const updates = [];

		// Обновления для transmitter/squelch устройств
		for (const [deviceName, deviceState] of this.activeDevices) {
			if (now - deviceState.lastDurationUpdate >= 1000) {
				const update = this.createDeviceUpdate(deviceState);
				updates.push(update);
				deviceState.lastDurationUpdate = now;
			}
		}

		// Обновления для talkgroup активности
		for (const [deviceName, talkgroupState] of this.activeTalkgroups) {
			if (now - talkgroupState.lastDurationUpdate >= 1000) {
				const update = this.createTalkgroupStatusUpdate(talkgroupState);
				updates.push(update);
				talkgroupState.lastDurationUpdate = now;
			}
		}

		// Обновления для модулей
		for (const [logicName, moduleState] of this.activeModules) {
			if (now - moduleState.lastDurationUpdate >= 1000) {
				const update = this.createModuleConnectionUpdate(logicName, moduleState, moduleState.connectedAt !== null);
				updates.push(update);
				moduleState.lastDurationUpdate = now;
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
			talkgroupEvents: 0,
			moduleEvents: 0
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

		// Отправка текущего состояния активных устройств и модулей
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
			message: 'Connected to Stateful WebSocket Server 0.3.4 with module tracking and uptime support'
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

		// Активные модули
		const activeModules = this.stateManager.getActiveModules();
		if (activeModules.length > 0) {
			activeModules.forEach(moduleState => {
				// Нужно найти логику для этого модуля
				for (const [logic, state] of this.stateManager.activeModules) {
					if (state === moduleState) {
						const moduleUpdate = this.stateManager.createModuleConnectionUpdate(
							logic,
							moduleState,
							moduleState.connectedAt !== null
						);
						updates.push(moduleUpdate);
						break;
					}
				}
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
				// Обработка активации/деактивации модулей
				else if (event.type === 'module_activation') {
					this.stats.moduleEvents++;
					const result = this.stateManager.processModuleActivationEvent(event);
					if (result && result.updates) {
						if (Array.isArray(result.updates)) {
							updates.push(...result.updates);
						} else {
							updates.push(result.updates);
						}

						log(`Module ${event.event}: ${event.logic}:${event.module}`, 'INFO');
					}
				}
				// Обработка подключения модулей
				else if (event.type === 'module_connection') {
					this.stats.moduleEvents++;
					const result = this.stateManager.processModuleConnectionEvent(event);
					if (result && result.updates) {
						if (Array.isArray(result.updates)) {
							updates.push(...result.updates);
						} else {
							updates.push(result.updates);
						}

						const state = event.state || (event.event === 'state_changed' ? event.state : event.event);
						log(`Module connection ${state}: ${event.module_type}`, 'INFO');
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
				log('No clients connected for 15000ms, shutting down...', 'INFO');
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
				log(`Module events: ${this.stats.moduleEvents}`);
				log(`Duration updates sent: ${this.stats.durationUpdatesSent}`);
				log(`Clients connected: ${this.stats.clientsConnected}`);
				log(`Clients disconnected: ${this.stats.clientsDisconnected}`);
				log(`Active devices: ${this.stateManager.getActiveDevices().length}`);
				log(`Active talkgroups: ${this.stateManager.getActiveTalkgroups().length}`);
				log(`Active modules: ${this.stateManager.getActiveModules().length}`);

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
			activeModules: this.stateManager.getActiveModules().length,
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