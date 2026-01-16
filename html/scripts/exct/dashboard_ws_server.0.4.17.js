/**
 * @filesource /scripts/exct/dashboard_ws_server.0.4.15.js
 * @version 0.4.15
 * @date 2026.01.14
 * @description Stateful WebSocket сервер для SvxLink Dashboard с поддержкой начального состояния
 * @todo Дописать разбор
 */

const WebSocket = require('ws');
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const http = require('http');
const { match } = require('assert');

// @bookmark  КОНСТАНТЫ И КОНФИГУРАЦИЯ
const CONFIG = {
	version: '0.4.15',
	ws: {
		host: '0.0.0.0',
		port: 8080,
		clientTimeout: 15000
	},
	log: {
		path: '/var/log/svxlink',
		bufferTimeout: 3,
		maxBufferSize: 50
	},
	duration: {
		updateInterval: 1000
	},
	php: {
		stateEndpoint: 'http://localhost/ws_state.php',
		timeout: 3000
	}
};

// @bookmark СЕРВИС ЛОГИРОВАНИЯ
function getTimestamp() {
	const now = new Date();
	return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;
}

// function log(message, level = 'INFO') {
// 	const timestamp = getTimestamp();
// 	const logMessage = `[${timestamp}] [${level}] ${message}`;
// 	console.log(logMessage);

// 	// Отправка сообщения всем подключенным клиентам
// 	if (global.serverInstance && global.serverInstance.clients && global.serverInstance.clients.size > 0) {
// 		global.serverInstance.broadcast({
// 			type: 'log_message',
// 			level: level,
// 			message: message,
// 			timestamp: new Date().toISOString(),
// 			source: 'WS Server v4'
// 		});
// 	}
// }
function log(message, level = 'DEBUG') {
	const logLevels = {
		'ERROR': 1,
		'WARNING': 2,
		'INFO': 3,
		'DEBUG': 4
	};

	const currentDebugLevel = parseInt(process.env.DEBUG_LEVEL) || 4;
	const messageLevel = logLevels[level] || 4;

	// Выводим только если уровень сообщения <= текущему уровню отладки
	if (messageLevel <= currentDebugLevel) {
		const timestamp = getTimestamp();
		const logMessage = `[${timestamp}] [${level}] ${message}`;
		console.log(logMessage);

		// Отправка клиентам
		if (global.serverInstance) {
			global.serverInstance.broadcast({
				type: 'log_message',
				level: level,
				message: message,
				timestamp: new Date().toISOString(),
				source: 'WS Server v4'
			});
		}
	}
}

// 
function getTimerTooltip(callsign, uptime = "0 s") {
	// Форматируем uptime (может приходить как "0 s", "15 s", "01:30" и т.д.)
	const formattedUptime = uptime || "0 s";

	// Возвращаем HTML-строку
	return `<a class="tooltip" href="#"><span><b>Uptime:</b>${formattedUptime}<br></span>${callsign}</a>`;
}

// @bookmark  МЕНЕДЖЕР СОСТОЯНИЙ
class StateManager {
	constructor() {
		this.timers = new Map(); // key -> {startTime, lastUpdate, metadata}
		// this.lastPacketMs = null; // msec последнего сообщения пакета
	}

	// @bookmark Форматирование времени
	formatDuration(milliseconds) {
		const totalSeconds = Math.floor(milliseconds / 1000);

		// Менее 60 секунд: показываем "N s"
		if (totalSeconds < 60) {
			return `${totalSeconds} s`;
		}

		// От 1 минуты до 1 часа: MM:SS
		if (totalSeconds < 3600) {
			const minutes = Math.floor(totalSeconds / 60).toString().padStart(2, '0');
			const seconds = (totalSeconds % 60).toString().padStart(2, '0');
			return `${minutes}:${seconds}`;
		}

		// Более 1 часа: HH:MM:SS
		const hours = Math.floor(totalSeconds / 3600).toString().padStart(2, '0');
		const minutes = Math.floor((totalSeconds % 3600) / 60).toString().padStart(2, '0');
		const seconds = (totalSeconds % 60).toString().padStart(2, '0');
		return `${hours}:${minutes}:${seconds}`;
	}

	// @bookmark Запустить таймер
	startTimer(key, metadata = {}) {
		const now = Date.now();
		
		// Проверяем, нет ли уже таймера с таким же elementId
		for (const [existingKey, existingTimer] of this.timers) {
			if (existingTimer.metadata.elementId === metadata.elementId && existingKey !== key) {
				log(`Таймер  ${metadata.elementId} уже существует с ключом ${existingKey}. Удаляем его.`, 'WARNING');
				this.timers.delete(existingKey);
			}
		}
		
		this.timers.set(key, {
			startTime: now,
			lastUpdate: now,
			metadata: metadata
		});
		log(`Запущен таймер для: ${key}`);
		return now;
	}

	// @bookmark Остановить таймер
	stopTimer(key) {
		const existed = this.timers.delete(key);
		if (existed) {
			log(`Остановлен таймер: ${key}`);
		}
		return existed;
	}

	// @bookmark Установить время старта таймера
	setTimerStart(key, startTimestamp) {
		const timer = this.timers.get(key);
		if (timer) {
			timer.startTime = startTimestamp;
			timer.lastUpdate = Date.now();
			// log(`Таймер ${key} установлен на время: ${new Date(startTimestamp).toISOString()}`);
			return true;
		}
		return false;
	}

	// @bookmark Получить данные об активных таймерах
	getTimerUpdates() {
		const now = Date.now();
		const updates = [];

		for (const [key, timer] of this.timers) {
			if (now - timer.lastUpdate >= 1000) {
				const durationMs = now - timer.startTime;
				updates.push({
					key: key,
					durationMs: durationMs,
					metadata: timer.metadata
				});
				timer.lastUpdate = now;
			}
		}

		return updates;
	}

	// @bookmark Статистика
	getStats() {
		return {
			activeTimers: this.timers.size
		};
	}
}

// @bookmark ОБРАБОТЧИК СВЯЗЕЙ
class ConnectionHandler {
	constructor() {
		this.connections = new Map(); // key -> Set(relatedKeys)
	}

	// Добавить связь
	add(source, target) {
		if (!this.connections.has(source)) {
			this.connections.set(source, new Set());
		}
		this.connections.get(source).add(target);
		log(`Добавлена связка: ${source} -> ${target}`);
	}

	// Удалить связь
	remove(source, target) {
		const targets = this.connections.get(source);
		if (targets) {
			targets.delete(target);
			if (targets.size === 0) {
				this.connections.delete(source);
			}
			log(`Удалена связка: ${source} -> ${target}`);
		}
	}

	// Получить все цели для источника
	getAllFrom(source) {
		const targets = this.connections.get(source);
		return targets ? Array.from(targets) : [];
	}

	// Получить все источники для цели
	getAllTo(target) {
		const sources = [];
		for (const [source, targets] of this.connections.entries()) {
			if (targets.has(target)) {
				sources.push(source);
			}
		}
		return sources;
	}

	// Инициализация
	initFromData(data, sourceKey, targetKey) {
		for (const [source, targets] of Object.entries(data || {})) {
			for (const target of targets) {
				this.add(source, target);
			}
		}
		log(`Установлены связи: ${this.connections.size} связей ${sourceKey} с ${targetKey}`);
	}

	// Быстрый доступ к специфическим связям
	// getModuleLogics(moduleName) {
	// 	return this.getAllFrom(moduleName);
	// }

	// getLinkLogics(linkName) {
	// 	return this.getAllTo(linkName);
	// }

	// getLogicModules(logicName) {
	// 	return this.getAllTo(logicName);
	// }

	// getLogicLinks(logicName) {
	// 	return this.getAllFrom(logicName).filter(item =>
	// 		item.startsWith('Link') || item.startsWith('link_')
	// 	);
	// }

	// Проверить, есть ли активные линки у логики
	// hasActiveLinksForLogic(logicName) {
	// 	const relatedLinks = this.getAllFrom(logicName).filter(link =>
	// 		link.startsWith('Link') || link.startsWith('link_')
	// 	);
	// 	return relatedLinks.length > 0;
	// }
}

// @bookmark ПАРСЕР КОМАНД
class CommandParser {
	constructor(stateManager) {
		this.sm = stateManager;
		this.connections = new ConnectionHandler();

		// Состояние для пакетного режима
		this.isPacketMessageMode = false; // <-- НОВОЕ: режим ожидания позывного в пакете
		this.packetActive = false;
		this.packetType = null; // 'EchoLink' | 'Frn'
		this.packetBuffer = [];
		this.packetStartTime = null;
		this.packetMetadata = {};

		// @bookmark Разбор паттернов
		this.patterns = [
			// @bookmark Сервис
			// [timestamp]: [SvxLink] v1.8.0@24.02-3-gcde00792 Copyright (C) 2003-2023 Tobias Blomberg / SM0SVX
			{
				regex: /^(.+?): (\S+) (.+?) Copyright \(C\) .+ Tobias Blomberg \/ SM0SVX$/,
				handler: (match) => {
					const service = match[2];

					this.sm.startTimer('service', {
						elementId: 'service',
						replaceStart: ':</b>',
						replaceEnd: '<br>',
					});

					return [
						{
							id: 'service',
							action: 'set_content',
							payload: getTimerTooltip(service, this.sm.formatDuration(0))
						},
						{
							id: 'service',
							action: 'remove_class',
							class: 'inactive-mode-cell,disabled-mode-cell,paused-mode-cell'
						},
						{
							id: 'service',
							action: 'add_class',
							class: 'active-mode-cell'
						}
					]
				}
			},

			// [timestamp]: SIGTERM received. Shutting down application...
			{
				regex: /^(.+?): SIGTERM received\. Shutting down application\.\.\.$/,
				handler: (match) => {
					this.sm.stopTimer('service');
					return [
						{
							id: 'service',
							action: 'remove_class',
							class: 'active-mode-cell,disabled-mode-cell,paused-mode-cell'
						},
						{
							id: 'service',
							action: 'add_class',
							class: 'inactive-mode-cell'
						},
						{
							id: 'service',
							action: 'set_content',
							payload: 'OFF'
						}
					]
				}
			},

			// @bookmark Логика
			// [timestamp]: Starting logic: [SimplexLogic]
			{
				regex: /^(.+?): Starting logic: (\S+)$/,
				handler: (match) => {
					const timestamp = match[1];
					const logic = match[2];

					return [
						{
							id: `logic_${logic}`,
							action: 'remove_class',
							class: 'paused-mode-cell,active-mode-cell,inactive-mode-cell'
						},
						{
							id: `logic_${logic}`,
							action: 'add_class',
							class: 'disabled-mode-cell'
						},
					]
				}
			},

			// [timestamp]: SimplexLogic: Event handler script successfully loaded.
			{
				regex: /^(.+?): (\S+): Event handler script successfully loaded.$/,
				handler: (match) => {
					const timestamp = match[1];
					const logic = match[2];
					
					this.sm.startTimer(`logic_${logic}`, {
						elementId: `logic_${logic}`,
						replaceStart: ':</b>',
						replaceEnd: '<br>',
						logic: logic
					});

					return [
						{
							id: `logic_${logic}`,
							action: 'set_content',
							payload: getTimerTooltip(logic, this.sm.formatDuration(0))
						},
						{
							id: `logic_${logic}`,
							action: 'remove_class',
							class: 'disabled-mode-cell,active-mode-cell,inactive-mode-cell'
						},
						{
							id: `logic_${logic}`,
							action: 'add_class',
							class: 'paused-mode-cell'
						},
					]
				}
			},

			// @bookmark Инициализация логики
			// [timestamp]: SimplexLogic: Loading RX "Rx1"
			{
				regex: /^(.+?): (\S+): Loading (RX|TX) "(\S+)"$/,
				handler: (match) => {
					const timestamp = match[1];
					const logic = match[2];
					const deviceType = match[3];  // "RX" или "TX"
					const deviceName = match[4];  // "Rx1", "MultiTx"

					log(`${logic}: Loading ${deviceType} device "${deviceName}"`, 'DEBUG');

					// 1. Добавляем связь устройство-логика
					// Формат: device -> logic 
					this.connections.add(deviceName, logic);
					return [];
				}
			},

			// [timestamp]: SimplexLogic: Loading module "ModuleHelp"
			{
				regex: /^(.+?): (\S+): Loading module "(\S+)"$/,
				handler: (match) => {
					const logic = match[2];
					const module = match[3];

					// Добавляем связь модуль -> логика
					this.connections.add(module, logic);
					log(`Добавил связку: ${module} -> ${logic}`, 'DEBUG');
					return [];
				}
			},

			// @bookmark TX/RX
			// Transmitter [ON/OFF]
			{
				regex: /^(.+?): (\w+): Turning the transmitter (ON|OFF)$/,
				handler: (match) => {
					const device = match[2];
					const state = match[3];

					if (state === 'ON') {
						this.sm.startTimer(`device_${device}`, {
							elementId: `device_${device}_tx_status`,
							replaceStart: '( ',
							replaceEnd: ' )',
							type: 'device'
						});

						return [
							{ id: `device_${device}_tx_status`, action: 'set_content', payload: 'TRANSMIT ( 0 s )' },
							{ id: `device_${device}_tx_status`, action: 'add_class', class: 'inactive-mode-cell' },
						];

					} else {
						this.sm.stopTimer(`device_${device}`);

						return [
							{ id: `device_${device}_tx_status`, action: 'set_content', payload: 'STANDBY' },
							{ id: `device_${device}_tx_status`, action: 'remove_class', class: 'inactive-mode-cell' },
						];
					}
				}
			},

			// Squelch OPEN/CLOSED
			{
				regex: /^(.+?): (\w+): The squelch is (OPEN|CLOSED)$/,
				handler: (match) => {
					const device = match[2];
					const state = match[3];

					if (state === 'OPEN') {
						this.sm.startTimer(`device_${device}`, {
							elementId: `device_${device}_rx_status`,
							replaceStart: '( ',
							replaceEnd: ' )',
							type: 'device'
						});

						return [
							{ id: `device_${device}_rx_status`, action: 'set_content', payload: 'RECEIVE ( 0 s )' },
							{ id: `device_${device}_rx_status`, action: 'add_class', class: 'active-mode-cell' },
						];
					} else {
						this.sm.stopTimer(`device_${device}`);

						return [
							{ id: `device_${device}_rx_status`, action: 'set_content', payload: 'STANDBY' },
							{ id: `device_${device}_rx_status`, action: 'remove_class', class: 'active-mode-cell' },
						];
					}
				}
			},

			// @bookmark Рефлектор

			// [timestamp]: ReflectorLogic: Disconnected from 255.255.255.255:5300: Host not found
			{
				regex: /^(.+?): (\S+): Disconnected/,
				handler: (match) => {
					const logic = match[2];
					
					// У рефлектора нет таймера!
					//this.sm.stopTimer(`logic_${logic}`);

					// Остановить таймеры для подключенных узлов и удалить связки с логикой
					const nodes = this.connections.getAllTo(logic);
					for (let i = 0; i < nodes.length; i++) { 
						const node = nodes[i];						
						this.connections.remove(node, logic);
						this.sm.stopTimer(`logic_${logic}_node_${node}`);	
					}

					return [
						// Показать рефлектор отключенным
						{ id: `logic_${logic}`, action: 'remove_class', class: 'active-mode-cell,paused-mode-cell,disabled-mode-cell' },
						{ id: `logic_${logic}`, action: 'add_class', class: 'inactive-mode-cell' },
						
						// Удалить все подключенные узлы
						{ id: `logic_${logic}_nodes_header`, action: 'replace_content', payload: ['[', ']', ''] },
						{ id: `logic_${logic}_nodes`, action: 'remove_child' },
						
						// Очистить разговорные группы
						{ id: `logic_${logic}_groups`, action: 'remove_child', ignoreClass: 'monitored,default' },
					];
				}
			},

			// @bookmark ReflectorLogic: Connected nodes
			// Пример: "12 Jan 2026 04:30:15.123: ReflectorLogicKAVKAZ: Connected nodes: RY6HAB-1, UB6LPY-1, R2ADU-1"
			{
				regex: /^(.+?): (\S+): Connected nodes: (.+)$/,
				handler: (match) => {
					const timestamp = match[1];
					const logic = match[2];
					const nodesString = match[3];  // "RY6HAB-1, UB6LPY-1, R2ADU-1"

					// Разбиваем строку узлов
					const nodes = nodesString.split(',').map(node => node.trim());

					// Извлекаем позывные (убираем суффиксы -1, -T и т.д.)
					// const callsigns = nodes.map(node => {
						// Убираем всё после дефиса
						// return node.split('-')[0];
					// });

					log(`У рефлектора ${logic} ${nodes.length} подключенный(х) узлов`, 'INFO');

					const commands = [];

					// Обновляем состояние логики
					commands.push(
						{
							id: `logic_${logic}`,
							action: 'remove_class',
							class: 'inactive-mode-cell,disabled-mode-cell,active-mode-cell'
						},
						{
							id: `logic_${logic}`,
							action: 'add_class',
							class: 'paused-mode-cell'
						}
					);

					// Очищаем старые узлы
					commands.push(
						{
							id: `logic_${logic}_nodes`,
							action: 'set_content',
							payload: ''  
						},
						{
							id: `logic_${logic}_nodes_header`,
							action: 'set_content',
							payload: `Nodes [${nodes.length}]`  
						}
					);

					// Добавляем каждый узел: устанавливаем связь с логикой, добавляем таймер
					for (let i = 0; i < nodes.length; i++) {
						const node = nodes[i];      
						this.connections.add(node, logic);
						this.sm.startTimer(`logic_${logic}_node_${node}`, {
							elementId: `logic_${logic}_node_${node}`,
							replaceStart: ':</b>',
							replaceEnd: '<br>',
							logic: logic,
							node: node
						});

						// Добавляем HTML для узла
						commands.push(
							{
								target: `logic_${logic}_nodes`,
								action: 'add_child',
								id: `logic_${logic}_node_${node}`,
								class: 'mode_flex column disabled-mode-cell',
								style: 'border: .5px solid #3c3f47;',
								payload: `${getTimerTooltip(node, "0 s")}`
							});
					}

					return commands;
				}
			},

			// Talker start
			{
				regex: /^(.+?): (\S+): Talker start on TG #(\d*): (\S+)$/,
				handler: (match) => {
					const logic = match[2];
					const talkgroup = match[3];
					const callsign = match[4];

					return [
						{ id: `radio_logic_${logic}_callsign`, action: 'set_content', payload: callsign },
						{ id: `radio_logic_${logic}_destination`, action: 'set_content', payload: `Talkgroup: ${talkgroup}` },
						{ id: `device_${logic}_tx_status`, action: 'set_content', payload: 'NET' },
						{ id: `device_${logic}_tx_status`, action: 'add_class', class: 'active-mode-cell' },
						// Показать строку устройств рефлектора
						{ id: `radio_logic_${logic}`, action: 'remove_parent_class', class: 'hidden' }
					];
				}
			},

			// Talker stop
			{
				regex: /^(.+?): (\S+): Talker stop on TG #(\d+): (\S+)$/,
				handler: (match) => {
					const logic = match[2];
					const group = match[3];
					
					return [
						{ id: `radio_logic_${logic}_callsign`, action: 'set_content', payload: '' },
						{ id: `radio_logic_${logic}_destination`, action: 'set_content', payload: '' },
						{ id: `device_${logic}_tx_status`, action: 'set_content', payload: '' },
						{ id: `device_${logic}_tx_status`, action: 'remove_class', class: 'active-mode-cell' },
						
						// Скрыть строку устройств рефлектора
						{ id: `radio_logic_${logic}`, action: 'add_parent_class', class: 'hidden' }
					];
				}
			},

			// Node left
			{
				regex: /^(.+?): (\S+): Node left: (\S+)$/,
				handler: (match) => {
					const logic = match[2];
					const node = match[3];

					// Останавливаем таймер, удаляем связку с логикой, удаляем из списка узлов
					this.sm.stopTimer(`logic_${logic}_node_${node}`);
					this.connections.remove(node, logic);
					
					return [
						{
							id: `logic_${logic}_node_${node}`,
							action: 'remove_element'
						}
					]
				}
			},

			// Node joined
			{
				regex: /^(.+?): (\S+): Node joined: (\S+)$/,
				handler: (match) => {
					const logic = match[2];
					const node = match[3];
					// Запускаем таймер, добавляем связку с логикой, добавляем в список узлов
					this.sm.startTimer(`logic_${logic}_node_${node}`, {
						elementId: `logic_${logic}_node_${node}`,
						replaceStart: ':</b>',
						replaceEnd: '<br>',
						type: 'node'
					});
					
					this.connections.add(node, logic);
					
					return [
						{
							target: `logic_${logic}_nodes`,
							action: 'add_child',
							id: `logic_${logic}_node_${node}`,
							class: 'mode_flex column disabled-mode-cell',
							style: 'border: .5px solid #3c3f47;',
							title: node,
							payload: getTimerTooltip(node, this.sm.formatDuration(0)),
						},
					]
				}
			},

			// Selecting TG #0
			{
				regex: /^(.+?): (\S+): Selecting TG #0/,
				handler: (match) => {
					const logic = match[2];
					return [
						// Удалить все элементы не относящиеся к группе по умолчанию или группы для мониторинга
						{
							id: `logic_${logic}_groups`,
							action: 'remove_child',
							ignoreClass: 'default,monitored'
						},
						// Оставшиеся группы перевести в состояние disabled-mode-cell
						{
							id: `logic_${logic}_groups`,
							class: 'disabled-mode-cell',
							action: 'replace_child_classes',
							oldClass: 'active-mode-cell'
						},

						{
							id: `logic_${logic}_groups`,
							class: 'default,active-mode-cell',
							action: 'replace_child_classes',
							oldClass: 'default,disabled-mode-cell'
						},
					];
				}
			},

			// Selecting TG #
			{
				regex: /^(.+?): (\S+): Selecting TG #(\d+)/,
				handler: (match) => {
					const logic = match[2];
					const group = match[3];

					if (group === "0") {
						return [];
					}

					return [
						// Удалить все элементы не относящиеся к группе по умолчанию или группы для мониторинга
						{
							id: `logic_${logic}_groups`,
							action: 'remove_child',
							ignoreClass: 'default,monitored',
						},
						// Оставшиеся группы перевести в состояние disabled-mode-cell
						{
							id: `logic_${logic}_groups`,
							action: 'replace_child_classes',
							oldClass: 'active-mode-cell',
							class: 'disabled-mode-cell',					
						},
						// Попытаться добавить новый элемент (будет проигнорировано при наличии)
						{
							action: "add_child",
							target: `logic_${logic}_groups`,
							id: `logic_${logic}_group_${group}`,
							class: 'mode_flex column',
							style: 'border: .5px solid #3c3f47;',
							title: group,
							payload: group,						
						},
						// Установить у выбранной группы состояние active-mode-cell
						{
							id: `logic_${logic}_group_${group}`,
							action: "add_class",
							class: 'active-mode-cell',
						}
					];
				}
			},

			// Add temporary monitor for TG #
			{
				regex: /^(.+?): (\S+): Add temporary monitor for TG #(\d+)/,
				handler: (match) => {
					const logic = match[2];
					const group = match[3];

					if (group === "0") {
						return [];
					}

					return [
						{
							action: 'add_child',
							target: `logic_${logic}_groups`,							
							id: `logic_${logic}_group_${group}`,
							class: 'mode_flex column paused-mode-cell monitored',
							style: 'border: .5px solid #3c3f47;',
							title: group,
							payload: group,
						},
					];
				}
			},

			// Refresh temporary monitor for TG #
			{
				regex: /^(.+?): (\S+): Refresh temporary monitor for TG #(\d+)/,
				handler: (match) => {
					const logic = match[2];
					const group = match[3];
					return [
						{							
							action: 'add_child',
							target: `logic_${logic}_groups`,
							id: `logic_${logic}_group_${group}`,
							class: 'mode_flex column paused-mode-cell monitored',
							style: 'border: .5px solid #3c3f47;',
							title: group,
							payload: group,
						},
					];
				}
			},

			// Temporary monitor timeout for TG #
			{
				regex: /^(.+?): (\S+): Temporary monitor timeout for TG #(\d+)/,
				handler: (match) => {
					const logic = match[2];
					const group = match[3];

					return [
						{
							id: `logic_${logic}_group_${group}`,
							action: 'remove_element',
						},
					];
				}
			},

			// @bookmark Модуль

			// Активация
			{
				regex: /^(.+?): (\S+): Activating module (\S+)\.\.\.$/,
				handler: (match) => {
					const logic = match[2];
					const module = match[3];

					this.sm.startTimer(`logic_${logic}_module_${module}`, {
						elementId: `logic_${logic}_module_${module}`,
						replaceStart: ':</b>',
						replaceEnd: '<br>',
						logic: logic,
						module: module
					});


					return [
						{
							// Логику включить
							id: `logic_${logic}`,
							action: 'remove_class',
							class: 'disabled-mode-cell,inactive-mode-cell,active-mode-cell'
						},

						{
							id: `logic_${logic}`,
							action: 'add_class',
							class: 'active-mode-cell'
						},

						// Сам модуль
						{
							id: `logic_${logic}_module_${module}`,
							action: 'remove_class',
							class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell,disabled-mode-cell'
						},
						{
							id: `logic_${logic}_module_${module}`,
							action: 'add_class',
							class: 'paused-mode-cell'
						},
						{
							id: `logic_${logic}_module_${module}`,
							action: 'set_content',
							payload: getTimerTooltip(module, this.sm.formatDuration(0))
						},

						// Поле  destination на radio_activity
						{
							id: `radio_logic_${logic}_destination`,
							action: 'set_content',
							payload: module
						},
					];
				}
			},

			// Деактивация
			{
				regex: /^(.+?): (\S+): Deactivating module (\S+)\.\.\.$/,
				handler: (match) => {
					const logic = match[2];
					const module = match[3];

					this.sm.stopTimer(`logic_${logic}_module_${module}`);
					this.sm.stopTimer(`logic_${logic}_active_content`);

					log(`Получаю связки для модуля ${module}`, 'INFO');
					const allLogicsForModule = this.connections.getAllFrom(module);
					// Для каждой логики находим связанные линки
					let hasActiveLinks = false;

					for (const relatedLogic of allLogicsForModule) {
						log(`Проверяю связку ${relatedLogic}`, 'DEBUG');
						const linksForLogic = this.connections.getAllFrom(relatedLogic)
						for (const link of linksForLogic) {
							log(`Проверяю связку ${link}`, 'DEBUG');
							if (this.sm.timers.has(`link_${link}`)) {
								hasActiveLinks = true;
								log(`Линк ${link} активен!`, 'DEBUG');
								break;
							}
						}
						if (hasActiveLinks) break;
					}

					// НЕТ активных линков - меняем класс логики на disabled
					const commands = [];

					if (!hasActiveLinks) {

						log(`Ставлю логику ${logic} на паузу.`, 'DEBUG');
						commands.push(
							{
								id: `logic_${logic}`,
								action: 'remove_class',
								class: 'disabled-mode-cell,paused-mode-cell,inactive-mode-cell,active-mode-cell'
							},
							{
								id: `logic_${logic}`,
								action: 'add_class',
								class: 'paused-mode-cell'
							}
						);
					}

					commands.push(
						// Сам модуль
						{
							id: `logic_${logic}_module_${module}`,
							action: 'remove_class',
							class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell'
						},

						{
							id: `logic_${logic}_module_${module}`,
							action: 'add_class',
							class: 'disabled-mode-cell'
						},

						// Удалить тултип
						{
							id: `logic_${logic}_module_${module}`,
							action: 'set_content',
							payload: module
						},

						// Подключенные узлы модуля
						{
							id: `logic_${logic}_active`,
							action: 'add_class',
							class: 'hidden'
						},

						{
							id: `logic_${logic}_active_header`,
							action: 'set_content',
							payload: ''
						},
						{
							id: `logic_${logic}_active_content`,
							action: 'set_content',
							payload: ''
						},

						// Поле  destination на radio_activity
						{
							id: `radio_logic_${logic}_destination`,
							action: 'set_content',
							payload: ''
						},
					);
					return commands;
				}
			},

			// @bookmark EchoLink: 

			// Соединение
			// QSO state changed to CONNECTED
			{
				regex: /^(.+?): (\S+): EchoLink QSO state changed to (CONNECTED)$/,
				handler: (match) => {
					const node = match[2];
					const allLogics = this.connections.getAllFrom('EchoLink');

					if (allLogics.length === 0) {
						log(`[WARNING] EchoLink не имеет связей с логикой!`, 'DEBUG');
						return [];
					}

					const resultCommands = [];
					for (const logic of allLogics) {
						this.sm.startTimer(`EchoLink_${node}`, {
							elementId: `logic_${logic}_active_content`,
							replaceStart: ':</b>',
							replaceEnd: '<br>',
							type: 'module_connection',
							module: 'EchoLink',
							node: node,
							logic: logic
						});

						resultCommands.push(
							{ id: `logic_${logic}`, action: 'remove_class', class: 'inactive-mode-cell,paused-mode-cell,disabled-mode-cell' },
							{ id: `logic_${logic}`, action: 'add_class', class: 'active-mode-cell' },
							{ id: `logic_${logic}_module_EchoLink`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell,disabled-mode-cell' },
							{ id: `logic_${logic}_module_EchoLink`, action: 'add_class', class: 'active-mode-cell' },
							{ id: `logic_${logic}_active`, action: 'remove_class', class: 'hidden' },

							{ id: `logic_${logic}_active_header`, action: 'set_content', payload: 'EchoLink' },
							{
								id: `EchoLink_${node}`,
								target: `logic_${logic}_active_content`,
								action: 'add_child',
								class: 'mode_flex column disabled-mode-cell',
								style: 'border: .5px solid #3c3f47;',
								payload: `${getTimerTooltip(node, "0 s")}`
							},
							
							
							{ id: `radio_logic_${logic}_destination`, action: 'set_content', payload: `EchoLink:  ${node}` },
						);
					}

					return resultCommands;
				}
			},

			// Разъединение
			// EchoLink: QSO state changed to DISCONNECTED
			{
				regex: /^(.+?): (\S+): EchoLink QSO state changed to (DISCONNECTED)$/,
				handler: (match) => {
					const node = match[2];
					const allLogics = this.connections.getAllFrom('EchoLink');

					if (allLogics.length === 0) {
						log(`EchoLink DISCONNECTED event but no logic links found!`, 'WARNING');
						return [];
					}

					const resultCommands = [];
					for (const logic of allLogics) {
						this.sm.stopTimer(`EchoLink_${node}`);

						resultCommands.push(
							{ id: `logic_${logic}`, action: 'remove_class', class: 'active-mode-cell' },
							{ id: `logic_${logic}`, action: 'add_class', class: 'paused-mode-cell' },
							{ id: `logic_${logic}_module_EchoLink`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,disabled-mode-cell' },
							{ id: `logic_${logic}_module_EchoLink`, action: 'add_class', class: 'paused-mode-cell' },
							{ id: `logic_${logic}_active`, action: 'add_class', class: 'hidden' },
							{ id: `logic_${logic}_active_header`, action: 'set_content', payload: '' },
							{ id: `logic_${logic}_active_content`, action: 'set_content', payload: '' },
							{ id: `radio_logic_${logic}_destination`, action: 'set_content', payload: `EchoLink` },
							{ id: 'ElDisconnect', targetClass: 'callsign', action: 'set_content_by_class', payload: '' },
						);
					}

					return resultCommands;
				}
			},

			// Конференция и позывной передающего
			// EchoLink: --- EchoLink chat message received from ... ---
			{
				regex: /^(.+?): --- EchoLink chat message received from (\S+) ---$/,
				handler: (match) => {
					const timestamp = match[1];
					const node = match[2];

					// Включаем режим ожидания позывного
					this.isPacketMessageMode = true; // <-- НОВОЕ

					const commands = [];
					commands.push(
						{
							targetClass: 'callsign',
							action: 'set_content_by_class',
							payload: "",
							id: "ElChatStart"
						},
						{
							targetClass: 'destination',
							action: 'set_content_by_class',
							payload: `EchoLink: Conference: ${node}`,
							id: "ElChatStart"
						}
					);

					return commands;
				}
			},

			// @bookmark Frn

			// Соединение
			// Акт : login stage 2 completed
			{
				regex: /^(.+?): login stage 2 completed: (.+)$/,
				handler: (match) => {
					log(`Получена команда подключения Frn`, 'DEBUG');

					let serverName = 'Unknown Server';
					const xml = match[2];
					const bnMatch = xml.match(/<BN>(.*?)<\/BN>/);
					if (bnMatch && bnMatch[1]) {
						serverName = bnMatch[1];
					}

					const allLogics = this.connections.getAllFrom('Frn');
					const resultCommands = [];

					if (allLogics.length === 0) {
						log(`Frn event but no logic links found!`, 'WARNING');
						return [];
					}

					for (const logic of allLogics) {
						this.sm.startTimer(`logic_${logic}_active_content`, {
							elementId: `logic_${logic}_active_content`,
							replaceStart: ':</b>',
							replaceEnd: '<br>',
							type: 'module_frn',
							logic: logic,
							server: serverName
						});
						resultCommands.push(
							{ id: `logic_${logic}_module_Frn`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell,disabled-mode-cell' },
							{ id: `logic_${logic}_module_Frn`, action: 'add_class', class: 'active-mode-cell' },
							{ id: `logic_${logic}_active`, action: 'remove_class', class: 'hidden' },
							{ id: `logic_${logic}_active_header`, action: 'set_content', payload: 'Frn' },
							{ id: `logic_${logic}_active_content`, action: 'set_content', payload: `<a class="tooltip" href="#"><span><b>Uptime:</b><br>Server ${serverName}</span>${serverName}</a>` },
							{ id: `radio_logic_${logic}_destination`, action: 'set_content', payload: `Frn: ${serverName}` },
						);
					}

					return resultCommands;
				}
			},

			// Frn: voice started
			{
				regex: /^(.+?): voice started: (.+)$/,
				handler: (match) => {
					const xml = match[2];
					const onMatch = xml.match(/<ON>(.*?)<\/ON>/);
					const node = onMatch ? onMatch[1].trim() : 'Unknown Frn node';

					const allLogics = this.connections.getAllFrom('Frn');
					const commands = [];

					if (allLogics.length === 0) {
						log(`Frn voice started event but no logic links found!`, 'WARNING');
						return [];
					}

					allLogics.forEach(logic => {
						commands.push({
							id: `radio_logic_${logic}_callsign`,
							action: 'set_content',
							payload: `${node}`
						});
					});

					log(`Frn voice started: Обновил ${allLogics.length} логик узла ${node}`, "DEBUG");
					return commands;
				}
			},

			// Frn: FRN list received:
			// Узлы или разговорные группы
			{
				regex: /^(.+?): FRN list received:$/,
				handler: (match) => {
					const timestamp = match[1];

					// Начинаем пакетный режим (для Frn)
					this.packetActive = true;
					this.packetType = 'Frn';
					this.packetBuffer = [];
					this.packetStartTime = timestamp;

					return [];
				}
			},

			// @bookmark Линки

			// Активация линка
			{
				regex: /^(.+?): Activating link (\S+)$/,
				handler: (match) => {
					const link = match[2];
					const relatedLogics = this.connections.getAllTo(link);

					this.sm.startTimer(`link_${link}`, {
						elementId: `link_${link}`,
						replaceStart: ':</b>',
						replaceEnd: '<br>',
						type: 'link'
					});

					const commands = [
						{ id: `link_${link}`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell,disabled-mode-cell' },
						{ id: `link_${link}`, action: 'add_class', class: 'active-mode-cell' },
						
						{ id: `toggle-link-state-${link}`, action: "set_checkbox_state", state: "on" }
					];

					// Добавляем команды для связанных логик
					for (const logic of relatedLogics) {
						commands.push(
							// { id: `logic_${logic}`, action: 'remove_class', class: 'disabled-mode-cell,paused-mode-cell,inactive-mode-cell' },
							// { id: `logic_${logic}`, action: 'add_class', class: 'active-mode-cell' }
							{ id: `logic_${logic}`, action: 'replace_class', new_class: 'active-mode-cell', old_class: 'paused-mode-cell' },
						);
					}

					return commands;
				}
			},

			// Деактивация линка
			{
				regex: /^(.+?): Deactivating link (\S+)$/,
				handler: (match) => {
					const link = match[2];
					const relatedLogics = this.connections.getAllTo(link);

					this.sm.stopTimer(`link_${link}`);

					const commands = [
						{ id: `link_${link}`, action: 'remove_class', class: 'active-mode-cell,paused-mode-cell,inactive-mode-cell,disabled-mode-cell' },
						{ id: `link_${link}`, action: 'add_class', class: 'disabled-mode-cell' },
						// { id: `link_${link}`, action: 'remove_parent_class', class: 'link-active' },
						
						{ id: `toggle-link-state-${link}`, action: "set_checkbox_state", state: "off" }
					];

					// Обновляем связанные логики
					for (const logic of relatedLogics) {
						// Проверяем, есть ли у логики активные модули или другие линки
						let hasActiveConnections = false;
						const allConnections = this.connections.getAllTo(logic);

						for (const conn of allConnections) {
							if (conn === link) continue; // Пропускаем текущий линк

							if (conn.startsWith('Link') || conn.startsWith('link_')) {
								// Проверяем таймеры других линков
								if (this.sm.timers.has(`link_${conn}`)) {
									hasActiveConnections = true;
									break;
								}
							} else {
								// Проверяем таймеры модулей
								if (this.sm.timers.has(`${logic}_${conn}`) || this.sm.timers.has(`logic_${logic}_module_${conn}`)) {
									hasActiveConnections = true;
									break;
								}
							}
						}

						if (!hasActiveConnections) {
							// Устанавливаем связанную логику на паузу
							commands.push(
								// { id: `logic_${logic}`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell' },
								// { id: `logic_${logic}`, action: 'add_class', class: 'paused-mode-cell' }
								{ id: `logic_${logic}`, action: 'replace_class', new_class: 'paused-mode-cell', old_class: 'active-mode-cell' },
							);
						}
					}

					return commands;
				}
			},
		];
	}

	// @bookmark Основной метод парсинга
	parse(line) {
		const trimmed = line.trim();

		// 1. Если в режиме ожидания позывного (пакетный режим)
		// Добавить разбор строк
		// 14 Jan 2026 05:02:41.016: ->R2ADU-L Moscow 145.4625 #88.5
		// 14 Jan 2026 05:02:51.749: R2ADU>test
		// Нужно оменять формат вывода - для сообщений с позывным обрамлять его <b> </b>

		if (this.isPacketMessageMode) {
			
			
			// Проверяем на известные признаки окончания пакетов
			// SMS в Echolink завершается [TIMESTAMP]: Trailing chat data: {различные варианты, напр <0c><11><ce>+}
			if (trimmed.includes('Trailing chat data:')) { 
				this.isPacketMessageMode = false;
			}
			// Искать > в строке, но только если нет <
			if (trimmed.includes('>') && !trimmed.includes('<')) {
				let processedPayload = trimmed;

				// Удаляем лидирующий timestamp (все до первого ": ", включая ": ")
				const firstColonSpaceIndex = processedPayload.indexOf(': ');
				if (firstColonSpaceIndex !== -1) {
					// Удаляем все до ": " включительно
					processedPayload = processedPayload.substring(firstColonSpaceIndex + 2);
				}

				// Если это строка с ->, поменять -> на >
				if (processedPayload.includes('->')) {
					processedPayload = processedPayload.replace('->', '>');
				}

				// Проверяем первый символ после обработки ->
				if (processedPayload.startsWith('>')) {
					// Если > начинает строку, поменять ее на <b>
					processedPayload = '<b>' + processedPayload.substring(1);

					// Найти первый пробел после <b>
					const firstSpaceIndex = processedPayload.indexOf(' ', 3); // 3 - длина "<b>"
					if (firstSpaceIndex !== -1) {
						// Вставить </b> перед пробелом
						processedPayload = processedPayload.substring(0, firstSpaceIndex) +
							'</b>' +
							processedPayload.substring(firstSpaceIndex);
					} else {
						// Если пробела нет, добавляем </b> в конец
						processedPayload += '</b>';
					}
				} else if (processedPayload.includes('>') && !trimmed.includes('->')) {
					// Если > не первый символ и это не строка с ->, поменять ее на "</b>: " и добавить первым в начало строки "SMS from <b>"
					const gtIndex = processedPayload.indexOf('>');
					const beforeGt = processedPayload.substring(0, gtIndex);
					const afterGt = processedPayload.substring(gtIndex + 1);
					processedPayload = `SMS from <b>${beforeGt}</b>: ${afterGt}`;
				}

				// Отправляем обработанную строку
				return {
					commands: [{
						id: 'elPacketMode',
						targetClass: 'callsign',
						action: 'set_content_by_class',
						payload: processedPayload
					}],
					raw: trimmed,
					timestamp: this.extractTimestamp(trimmed) || new Date().toISOString()
				};
			}
			

			// Если -> не нашли - проверяем основные паттерны
			for (const pattern of this.patterns) {
				const match = trimmed.match(pattern.regex);
				if (match) {
					// Нашли основной паттерн - выключаем режим ожидания
					this.isPacketMessageMode = false;

					// Обрабатываем эту строку как обычную команду
					const commands = pattern.handler(match);
					return {
						commands: commands,
						raw: match[0],
						timestamp: match[1]
					};
				}
			}

			// Если не -> и не основной паттерн - остаёмся в режиме ожидания
			return null;
		}

		// 2. Обычный режим парсинга (не в ожидании)
		for (const pattern of this.patterns) {
			const match = trimmed.match(pattern.regex);
			if (match) {
				const commands = pattern.handler(match);

				// Если это начало EchoLink пакета - режим уже включен в обработчике
				// (в паттерне "EchoLink: --- EchoLink chat message received from")

				return {
					commands: commands,
					raw: match[0],
					timestamp: match[1]
				};
			}
		}

		return null;
	}

	// Вспомогательный метод для извлечения timestamp
	extractTimestamp(line) {
		const match = line.match(/^(.+?):/);
		return match ? match[1] : null;
	}

	// Метод для обновления времени
	timerUpdatesToCommands(timerUpdates) {
		const commands = [];

		for (const update of timerUpdates) {
			const duration = this.sm.formatDuration(update.durationMs);
			const metadata = update.metadata || {};

			if (metadata.elementId && metadata.replaceStart !== undefined && metadata.replaceEnd !== undefined) {
				commands.push({
					id: metadata.elementId,
					action: 'replace_content',
					payload: [metadata.replaceStart, metadata.replaceEnd, duration]
				});
			}
		}

		return commands;
	}

	// Инициализация из данных PHP
	initFromWsData(wsData) {
		// Инициализируем связи модуль-логика
		if (wsData.module_logic) {
			this.connections.initFromData(wsData.module_logic, 'module', 'logic');
		}

		// Инициализируем связи линк-логика
		if (wsData.link_logic) {
			this.connections.initFromData(wsData.link_logic, 'link', 'logic');
		}

		log(`Парсер команд готов, получено ${this.connections.connections.size} связок`, 'DEBUG');
	}
}

// @bookmark ОСНОВНОЙ КЛАСС СЕРВЕРА
class StatefulWebSocketServerV4 {
	constructor(config = {}) {
		this.config = { ...CONFIG, ...config };
		this.wss = null;
		this.stateManager = new StateManager();
		this.commandParser = new CommandParser(this.stateManager);
		this.clients = new Set();
		this.tailProcess = null;
		this.isMonitoring = false;
		this.shutdownTimer = null;
		this.durationTimer = null;

		// WebSocket состояние (полученное из PHP)
		this.wsState = {
			service: {},
			devices: {},
			modules: {},
			links: {},
			nodes: {},
			module_logic: {},
			logics: {}
		};

		// Статистика
		this.stats = {
			startedAt: Date.now(),
			clientsConnected: 0,
			clientsDisconnected: 0,
			messagesSent: 0,
			errors: 0,
			eventsProcessed: 0,
			commandsGenerated: 0,
			stateLoads: 0,
			stateLoadErrors: 0
		};
	}

	// ==================== ЗАГРУЗКА СОСТОЯНИЯ ИЗ PHP ====================

	// Загрузка состояния из PHP endpoint
	async loadWsState() {
		try {
			log('Получаю текущие данные...', 'DEBUG');

			const controller = new AbortController();
			const timeout = setTimeout(() => controller.abort(), this.config.php.timeout);

			const response = await fetch(this.config.php.stateEndpoint + '?_=' + Date.now(), {
				signal: controller.signal
			});

			clearTimeout(timeout);

			if (!response.ok) {
				throw new Error(`HTTP ${response.status}: ${response.statusText}`);
			}

			const responseText = await response.text();

			// Проверяем, что ответ не пустой
			if (!responseText || responseText.trim() === '') {
				throw new Error('Empty response from PHP endpoint');
			}

			let data;
			try {
				data = JSON.parse(responseText);
			} catch (parseError) {
				log(`Failed to parse JSON response: ${parseError.message}`, 'ERROR');
				log(`Response text: ${responseText.substring(0, 200)}...`, 'DEBUG');
				throw new Error('Invalid JSON response from PHP');
			}

			// Проверяем структуру ответа
			if (!data || typeof data !== 'object') {
				throw new Error('Invalid response format: expected object');
			}

			// data.data может быть undefined, поэтому используем пустой объект по умолчанию
			const wsData = data.data || {};

			// Проверяем, что wsData - объект
			if (typeof wsData !== 'object' || wsData === null) {
				throw new Error('Invalid WS data format: expected object');
			}

			this.wsState = {
				devices: wsData.devices || {},
				modules: wsData.modules || {},
				links: wsData.links || {},
				nodes: wsData.nodes || {},
				module_logic: wsData.module_logic || {},
				link_logic: wsData.link_logic || {},
				logics: wsData.logics || {},
				service: wsData.service || {}
			};

			this.stats.stateLoads++;

			// Восстанавливаем состояние
			this.restoreFromWsState();

			log(`WS статус загружен: ${Object.keys(this.wsState.devices).length} devices, ${Object.keys(this.wsState.modules).length} modules, ${Object.keys(this.wsState.link_logic || {}).length} link-logic connections`, 'INFO');
			return true;

		} catch (error) {
			this.stats.stateLoadErrors++;
			log(`Failed to load WS state: ${error.message}`, 'WARNING');

			// Инициализируем пустое состояние
			this.wsState = {
				devices: {},
				modules: {},
				links: {},
				nodes: {},
				module_logic: {},
				link_logic: {},
				logics: {},
				service: {}
			};

			return false;
		}
	}

	// Восстановление состояния из полученных данных
	restoreFromWsState() {
		const devices = this.wsState.devices || {};
		const modules = this.wsState.modules || {};
		const links = this.wsState.links || {};
		const nodes = this.wsState.nodes || {};
		const service = this.wsState.service || {};
		const logics = this.wsState.logics || {};

		let restoredCount = 0;

		// 1. Инициализируем ВСЕ связи через CommandParser
		if (this.wsState.module_logic || this.wsState.link_logic) {
			this.commandParser.initFromWsData(this.wsState);
		}

		// 2. Восстанавливаем таймеры логик
		for (const [logicKey, info] of Object.entries(logics)) {
			if (info && info.start && info.start > 0 && info.is_active) {
				const startTime = info.start * 1000;
				const logicName = info.name || logicKey.replace('logic_', '');

				this.stateManager.startTimer(`logic_${logicName}`, {
					elementId: `logic_${logicName}`,
					replaceStart: ':</b>',
					replaceEnd: '<br>',
					type: 'logic',
					logicName: logicName
				});

				this.stateManager.setTimerStart(`logic_${logicName}`, startTime);
				restoredCount++;
				log(`Восстановлен таймер для логики: ${logicName}`, 'DEBUG');
			}
		}

		// 3. Восстанавливаем таймеры устройств
		for (const [device, info] of Object.entries(devices)) {
			if (info && info.start && info.start > 0) {
				const startTime = info.start * 1000;

				this.stateManager.startTimer(`device_${device}_TX`, {
					elementId: `device_${device}tx_status`,
					replaceStart: '( ',
					replaceEnd: ' )',
					type: 'device_TX'
				});
				this.stateManager.setTimerStart(`device_${device}_TX`, startTime);

				this.stateManager.startTimer(`device_${device}_RX`, {
					elementId: `device_${device}rx_status`,
					replaceStart: '( ',
					replaceEnd: ' )',
					type: 'device_RX'
				});
				this.stateManager.setTimerStart(`device_${device}_RX`, startTime);

				restoredCount++;
			}
		}

		// 4. Восстанавливаем таймеры модулей
		for (const [moduleKey, info] of Object.entries(modules)) {
			if (info && info.logic && info.module) {
				const logicName = info.logic;
				const moduleName = info.module;
				const startTime = info.start * 1000;

				// Добавляем связь модуль-логика
				// this.commandParser.connections.add(moduleName, logicName);

				// Таймер запускаем только если модуль активен (start > 0)
				if (startTime > 0) {
					this.stateManager.startTimer(`logic_${logicName}_module_${moduleName}`, {
						elementId: `logic_${logicName}_module_${moduleName}`,
						replaceStart: ':</b>',
						replaceEnd: '<br>',
						logic: logicName,
						module: moduleName
					});
					this.stateManager.setTimerStart(`logic_${logicName}_module_${moduleName}`, startTime);
					restoredCount++;
				}
			}
		}


		// 5. Восстанавливаем таймеры линков
		for (const [linkName, info] of Object.entries(links)) {
			if (info && info.start && info.start > 0) {
				const startTime = info.start * 1000;

				this.stateManager.startTimer(`link_${linkName}`, {
					elementId: `link_${linkName}`,
					replaceStart: ':</b>',
					replaceEnd: '<br>',
					type: 'link'
				});
				this.stateManager.setTimerStart(`link_${linkName}`, startTime);
				restoredCount++;
			}
		}

		// 6. Восстанавливаем таймеры узлов
		for (const [nodeKey, info] of Object.entries(nodes)) {
			if (info && info.start && info.start > 0) {
				const match = nodeKey.match(/^logic_(.+?)_node_(.+)$/);
				if (match) {
					const logicName = match[1];
					const nodeName = match[2];
					const startTime = info.start * 1000;

					let timerKey, elementId;

					// УЗЛЫ МОДУЛЕЙ (Frn, EchoLink)
					if (info.type === 'module_node' && info.module) {
						timerKey = `${info.module}_${nodeName}`;
						elementId = `logic_${logicName}_active_content`;
					}
					// УЗЛЫ РЕФЛЕКТОРА (старый формат)
					else {
						timerKey = `logic_${logicName}_node_${nodeName}`;
						elementId = `logic_${logicName}_node_${nodeName}`;
					}

					this.stateManager.startTimer(timerKey, {
						elementId: elementId,
						replaceStart: ':</b>',
						replaceEnd: '<br>',
						type: info.type || 'node',
						module: info.module,
						logic: logicName
					});
					this.stateManager.setTimerStart(timerKey, startTime);
					restoredCount++;
				}
			}
		}

		// 7. Восстанавливаем таймер сервиса
		if (this.wsState.service && this.wsState.service.start && this.wsState.service.start > 0 && this.wsState.service.is_active) {
			const serviceStart = this.wsState.service.start * 1000;
			const serviceName = this.wsState.service.name || 'SvxLink';

			this.stateManager.startTimer(`service`, {
				elementId: `service`,
				replaceStart: ':</b>',
				replaceEnd: '<br>',
				type: 'service',
				name: serviceName
			});
			this.stateManager.setTimerStart(`service`, serviceStart);
			restoredCount++;
			log(`Service timer restored: ${serviceName}`, 'DEBUG');
		}

		log(`Восстановлено: ${restoredCount} элементов, активно ${this.stateManager.timers.size} таймеров,  ${this.commandParser.connections.connections.size} связей`, 'DEBUG');
	}

	/// Отправка начальных команд новому клиенту
	sendInitialCommands(ws) {
		const commands = [];

		// Безопасный доступ к данным
		const devices = this.wsState.devices || {};
		const service = this.wsState.service || {};

		// 1. Команды для устройств
		for (const [device, info] of Object.entries(devices)) {
			if (info && info.start && info.start > 0) {
				const duration = Math.floor(Date.now() / 1000 - info.start);
				const durationText = this.stateManager.formatDuration(duration * 1000);

				// TX состояние
				commands.push({
					id: `device_${device}_tx_status`,
					action: 'set_content',
					payload: `TRANSMIT ( ${durationText} )`
				});
				commands.push({
					id: `device_${device}_tx_status`,
					action: 'add_class',
					class: 'inactive-mode-cell'
				});

				// RX состояние
				commands.push({
					id: `device_${device}_rx_status`,
					action: 'set_content',
					payload: `RECEIVE ( ${durationText} )`
				});
				commands.push({
					id: `device_${device}_rx_status`,
					action: 'add_class',
					class: 'active-mode-cell'
				});
			}
		}
		// Команды для сервиса
		if (service.name && service.start && service.start > 0) {
			const serviceDurationMs = (Date.now() - (service.start * 1000));
			const serviceDurationText = this.stateManager.formatDuration(serviceDurationMs);

			// Формируем HTML как в DOM
			const serviceHtml = getTimerTooltip(service.name, serviceDurationText);
			
			commands.push({
				id: `service`,
				action: 'set_content',
				payload: serviceHtml
			});

			// Устанавливаем класс активности
			if (service.is_active) {
				commands.push({
					id: `service`,
					action: 'add_class',
					class: 'active-mode-cell'
				});
				commands.push({
					id: `service`,
					action: 'remove_class',
					class: 'inactive-mode-cell,paused-mode-cell,disabled-mode-cell'
				});
			}

			// ЗАПУСКАЕМ ТАЙМЕР СЕРВИСА
			this.stateManager.startTimer(`service`, {
				elementId: `service`,
				replaceStart: ':</b>',
				replaceEnd: '<br>',
				type: 'service'
			});

			// Устанавливаем правильное время начала
			this.stateManager.setTimerStart(`service`, service.start * 1000);

		}
		// Отправляем команды если есть
		if (commands.length > 0) {
			try {
				ws.send(JSON.stringify({
					type: 'dom_commands',
					commands: commands,
					timestamp: Date.now(),
					subtype: 'initial_state'
				}));

				log(`Sent ${commands.length} initial commands to new client`, 'DEBUG');
			} catch (error) {
				log(`Failed to send initial commands: ${error.message}`, 'ERROR');
			}
		}
	}

	// ==================== УПРАВЛЕНИЕ СЕРВЕРОМ ====================

	async start() {
		log(`Запущен WebSocket Server v${this.config.version}`, 'INFO');
		log(`WebSocket: ${this.config.ws.host}:${this.config.ws.port}`, 'DEBUG');

		try {
			// Проверка файла лога
			if (!fs.existsSync(this.config.log.path)) {
				log(`Log file not found: ${this.config.log.path}`, 'WARNING');
			}

			// Запуск WebSocket сервера
			this.startWebSocket();

			// Таймер выключения при отсутствии клиентов
			this.startShutdownTimer();

			log('Server v4.14 успешно запущен', 'INFO');

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

		log(`WebSocket сервер принимает вызовы по ${this.config.ws.host}:${this.config.ws.port}`, 'DEBUG');
	}

	// ==================== УПРАВЛЕНИЕ КЛИЕНТАМИ ====================

	async handleClientConnection(ws, req) {
		const clientId = `client_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
		const clientIp = req.socket.remoteAddress;

		this.clients.add({
			id: clientId,
			ws: ws,
			ip: clientIp,
			connectedAt: Date.now()
		});

		this.stats.clientsConnected++;
		
		log(`Сервер v${this.config.version}: Клиент ${clientId} подключен с ${clientIp} (всего: ${this.clients.size})`, 'DEBUG');

		// 1. Отправка приветственного сообщения
		this.sendWelcome(ws, clientId);

		// 2. Загружаем состояние если это первый клиент и состояние еще не загружено
		if (this.clients.size === 1 && Object.keys(this.wsState.devices).length === 0) {
			await this.loadWsState();
		}

		// 3. Отправляем начальные команды
		this.sendInitialCommands(ws);

		// 4. Запуск мониторинга если это первый клиент
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
			this.stats.errors++;
		});
	}

	handleClientDisconnect(clientId) {
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

			log(`Клиент ${clientId} отключен (осталось ${this.clients.size} клиентов)`, 'DEBUG');

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
			message: 'Server WebSocket v0.4.15',
			system: 'dom_commands_v4_state',
			initialState: Object.keys(this.wsState.devices).length > 0
		}));
	}

	sendToClient(clientId, message) {
		for (const client of this.clients) {
			if (client.id === clientId && client.ws.readyState === WebSocket.OPEN) {
				client.ws.send(JSON.stringify(message));
				this.stats.messagesSent++;
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
				this.stats.messagesSent++;
			}
		}

		return sentCount;
	}

	// ==================== ОБРАБОТКА ЛОГА И КОМАНД ====================

	startLogMonitoring() {
		if (this.isMonitoring) {
			log('Log monitoring already active', 'WARNING');
			return;
		}

		log('Начинаю мониторинг журнала ...', 'INFO');

		this.tailProcess = spawn('tail', ['-F', '-n', '0', this.config.log.path]);
		this.isMonitoring = true;

		let logBuffer = [];
		let bufferTimer = null;

		this.tailProcess.stdout.on('data', (data) => {
			const lines = data.toString().split('\n').filter(line => line.trim());

			if (lines.length > 0) {
				logBuffer.push(...lines);

				if (bufferTimer) {
					clearTimeout(bufferTimer);
				}

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
				this.stats.errors++;
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

		log('Запущен мониторинг журнала', 'DEBUG');
	}

	stopLogMonitoring() {
		if (this.tailProcess && this.isMonitoring) {
			log('Останавливаю мониторинг журнала ...', 'DEBUG');

			this.tailProcess.kill('SIGTERM');
			this.tailProcess = null;
			this.isMonitoring = false;

			log('Мониторинг журнала остановлен', 'INFO');
		}
	}

	processLogBuffer(buffer) {
		const linesToProcess = buffer.slice(-this.config.log.maxBufferSize);
		let allCommands = [];

		linesToProcess.forEach(line => {
			const result = this.commandParser.parse(line);

			if (result && result.commands && result.commands.length > 0) {
				allCommands.push(...result.commands);
				this.stats.eventsProcessed++;
				this.stats.commandsGenerated += result.commands.length;

				// log(`Событие обработано, создано ${result.commands.length} команд`, 'DEBUG');

				result.commands.forEach((cmd, index) => {
					const cmdInfo = `  [${index + 1}] ${cmd.id} -> ${cmd.action}`;
					const details = [];

					if (cmd.class) details.push(`class: ${cmd.class}`);
					if (cmd.ignoreClass) details.push(`ignoreClass: ${cmd.ignoreClass}`);
					if (cmd.payload !== undefined) {
						if (Array.isArray(cmd.payload)) {
							details.push(`payload: [${cmd.payload.map(p => `"${p}"`).join(', ')}]`);
						} else {
							details.push(`payload: "${cmd.payload}"`);
						}
					}

					if (details.length > 0) {
						log(`${cmdInfo} (${details.join(', ')})`, 'DEBUG');
					} else {
						log(cmdInfo, 'DEBUG');
					}
				});
			}
		});

		if (allCommands.length > 0 && this.clients.size > 0) {
			const chunkSize = 10;
			for (let i = 0; i < allCommands.length; i += chunkSize) {
				const chunk = allCommands.slice(i, i + chunkSize);

				const message = {
					type: 'dom_commands',
					commands: chunk,
					timestamp: Date.now(),
					total: allCommands.length,
					chunk: Math.floor(i / chunkSize) + 1,
					chunks: Math.ceil(allCommands.length / chunkSize)
				};

				const sentCount = this.broadcast(message);

				if (sentCount > 0) {
					// log(`Отправил ${chunk.length} команд для ${sentCount} клиентов (чанк ${message.chunk}/${message.chunks})`, 'DEBUG');
				}
			}
		}
	}

	// ==================== ТАЙМЕР ОБНОВЛЕНИЯ ВРЕМЕНИ ====================

	startDurationTimer() {
		if (this.durationTimer) {
			clearInterval(this.durationTimer);
		}

		this.durationTimer = setInterval(() => {
			if (this.clients.size > 0) {
				const timerUpdates = this.stateManager.getTimerUpdates();

				if (timerUpdates.length > 0) {
					const timeCommands = this.commandParser.timerUpdatesToCommands(timerUpdates);

					if (timeCommands.length > 0) {
						const message = {
							type: 'dom_commands',
							commands: timeCommands,
							timestamp: Date.now(),
							subtype: 'time_updates'
						};

						const sentCount = this.broadcast(message);

						if (sentCount > 0) {
							// log(`Отправил ${timeCommands.length} команд обновления времени`);
						}
					}
				}
			}
		}, this.config.duration.updateInterval);

		log(`Запущен отсчет длительности с интервалом (${this.config.duration.updateInterval}ms)`, 'DEBUG');
	}

	stopDurationTimer() {
		if (this.durationTimer) {
			clearInterval(this.durationTimer);
			this.durationTimer = null;
			log('Duration timer stopped', 'DEBUG');
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

		this.stopDurationTimer();
		this.stopLogMonitoring();

		for (const client of this.clients) {
			if (client.ws.readyState === WebSocket.OPEN) {
				client.ws.close(1000, 'Server shutdown');
			}
		}

		if (this.wss) {
			this.wss.close(() => {
				log('WebSocket server closed', 'INFO');

				const uptime = Math.floor((Date.now() - this.stats.startedAt) / 1000);
				const stateStats = this.stateManager.getStats();

				log(`Server v4.14 Statistics:`);
				log(`  Uptime: ${uptime}s`);
				log(`  Clients connected: ${this.stats.clientsConnected}`);
				log(`  Clients disconnected: ${this.stats.clientsDisconnected}`);
				log(`  Messages sent: ${this.stats.messagesSent}`);
				log(`  Events processed: ${this.stats.eventsProcessed}`);
				log(`  Commands generated: ${this.stats.commandsGenerated}`);
				log(`  State loads: ${this.stats.stateLoads}`);
				log(`  State load errors: ${this.stats.stateLoadErrors}`);
				log(`  Active timers: ${stateStats.activeTimers}`);
				log(`  Connections: ${this.commandParser.connections.connections.size}`);
				log(`  Errors: ${this.stats.errors}`);

				log('Server shutdown complete', 'INFO');
				process.exit(0);
			});

			setTimeout(() => {
				log('Принудительное выключение по тайм-ауту', 'WARNING');
				process.exit(1);
			}, 3000);
		} else {
			process.exit(0);
		}
	}

	// ==================== СТАТИСТИКА ====================

	getStats() {
		const uptime = Math.floor((Date.now() - this.stats.startedAt) / 1000);
		const stateStats = this.stateManager.getStats();

		return {
			version: this.config.version,
			uptime: uptime,
			clients: this.clients.size,
			monitoring: this.isMonitoring,
			durationTimerActive: !!this.durationTimer,
			wsState: {
				devices: Object.keys(this.wsState.devices).length,
				modules: Object.keys(this.wsState.modules).length,
				links: Object.keys(this.wsState.links).length,
				nodes: Object.keys(this.wsState.nodes).length,
				connections: this.commandParser.connections.connections.size
			},
			serverStats: this.stats,
			stateStats: stateStats
		};
	}
}

// @bookmark ТОЧКА ВХОДА
async function main() {
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
	const server = new StatefulWebSocketServerV4();
	global.serverInstance = server;

	await server.start();
}

// Запуск если файл исполняется напрямую
if (require.main === module) {
	main().catch(error => {
		log(`Failed to start server: ${error.message}`, 'ERROR');
		process.exit(1);
	});
}

// @bookmark Экспорт для тестирования
module.exports = {
	StatefulWebSocketServerV4,
	StateManager,
	CommandParser,
	ConnectionHandler
};