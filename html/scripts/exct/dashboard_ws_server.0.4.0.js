/**
 * @filesource /scripts/exct/dashboard_ws_server.4.0.js
 * @version 4.1
 * @date 2026.01.11
 * @description Stateful WebSocket сервер для SvxLink Dashboard с поддержкой начального состояния
 */

const WebSocket = require('ws');
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const http = require('http');

// @bookmark  КОНСТАНТЫ И КОНФИГУРАЦИЯ
const CONFIG = {
	version: '4.1',
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

function log(message, level = 'INFO') {
	const timestamp = getTimestamp();
	const logMessage = `[${timestamp}] [${level}] ${message}`;
	console.log(logMessage);
}

// @bookmark  МЕНЕДЖЕР СОСТОЯНИЙ
class StateManager {
	constructor() {
		this.timers = new Map(); // key -> {startTime, lastUpdate, metadata}
	}

	// Форматирование времени
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

	// Запустить таймер
	startTimer(key, metadata = {}) {
		const now = Date.now();
		this.timers.set(key, {
			startTime: now,
			lastUpdate: now,
			metadata: metadata
		});
		log(`Timer started: ${key}`);
		return now;
	}

	// Остановить таймер
	stopTimer(key) {
		const existed = this.timers.delete(key);
		if (existed) {
			log(`Timer stopped: ${key}`);
		}
		return existed;
	}

	// Установить время старта таймера (для восстановления из состояния)
	setTimerStart(key, startTimestamp) {
		const timer = this.timers.get(key);
		if (timer) {
			timer.startTime = startTimestamp;
			timer.lastUpdate = Date.now();
			log(`Timer ${key} start time set to: ${new Date(startTimestamp).toISOString()}`);
			return true;
		}
		return false;
	}

	// Получить данные об активных таймерах
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

	// Статистика
	getStats() {
		return {
			activeTimers: this.timers.size
		};
	}
}

// @bookmark  ОБРАБОТЧИК СВЯЗЕЙ МОДУЛЬ-ЛОГИКА
class ModuleLogicHandler {
	constructor() {
		this.moduleToLogics = new Map(); // moduleName -> Set(logicName)
	}

	// Добавить связь
	add(module, logic) {
		if (!this.moduleToLogics.has(module)) {
			this.moduleToLogics.set(module, new Set());
		}
		this.moduleToLogics.get(module).add(logic);
		log(`Module-Link added: ${module} -> ${logic}`);
	}

	// Удалить связь
	remove(module, logic) {
		const logics = this.moduleToLogics.get(module);
		if (logics) {
			logics.delete(logic);
			if (logics.size === 0) {
				this.moduleToLogics.delete(module);
			}
			log(`Module-Link removed: ${module} -> ${logic}`);
		}
	}

	// Получить все логики для модуля
	getAll(module) {
		const logics = this.moduleToLogics.get(module);
		return logics ? Array.from(logics) : [];
	}

	// Инициализировать связи из данных
	initFromData(moduleLogicData) {
		for (const [moduleName, logics] of Object.entries(moduleLogicData || {})) {
			for (const logicName of logics) {
				this.add(moduleName, logicName);
			}
		}
		log(`Module-Link initialized: ${this.moduleToLogics.size} modules`);
	}
}

// @bookmark ПАРСЕР КОМАНД
class CommandParser {
	constructor(stateManager) {
		this.sm = stateManager;
		this.moduleHandler = new ModuleLogicHandler();

		// Состояние для пакетного режима
		this.packetActive = false;
		this.packetType = null; // 'EchoLink' | 'Frn'
		this.packetBuffer = [];
		this.packetStartTime = null;
		this.packetMetadata = {};

		this.patterns = [
			// @bookmark Transmitter ON/OFF
			{
				regex: /^(.+?): (\w+): Turning the transmitter (ON|OFF)$/,
				handler: (match) => {
					const device = match[2];
					const state = match[3];

					if (state === 'ON') {
						this.sm.startTimer(`device_${device}`, {
							elementId: `device_${device}_status`,
							replaceStart: '( ',
							replaceEnd: ' )',
							type: 'device'
						});

						return [
							{ id: `device_${device}_status`, action: 'set_content', payload: 'TRANSMIT ( 0 s )' },
							{ id: `device_${device}_status`, action: 'add_class', class: 'inactive-mode-cell' },
						];
					} else {
						this.sm.stopTimer(`device_${device}`);

						return [
							{ id: `device_${device}_status`, action: 'set_content', payload: 'STANDBY' },
							{ id: `device_${device}_status`, action: 'remove_class', class: 'inactive-mode-cell' },
						];
					}
				}
			},

			// @bookmark Squelch OPEN/CLOSED
			{
				regex: /^(.+?): (\w+): The squelch is (OPEN|CLOSED)$/,
				handler: (match) => {
					const device = match[2];
					const state = match[3];

					if (state === 'OPEN') {
						this.sm.startTimer(`device_${device}`, {
							elementId: `device_${device}_status`,
							replaceStart: '( ',
							replaceEnd: ' )',
							type: 'device'
						});

						return [
							{ id: `device_${device}_status`, action: 'set_content', payload: 'RECEIVE ( 0 s )' },
							{ id: `device_${device}_status`, action: 'add_class', class: 'active-mode-cell' },
						];
					} else {
						this.sm.stopTimer(`device_${device}`);

						return [
							{ id: `device_${device}_status`, action: 'set_content', payload: 'STANDBY' },
							{ id: `device_${device}_status`, action: 'remove_class', class: 'active-mode-cell' },
						];
					}
				}
			},

			// @bookmark Talker start
			{
				regex: /^(.+?): (\S+): Talker start on TG #(\d*): (\S+)$/,
				handler: (match) => {
					const logic = match[2];
					const talkgroup = match[3];
					const callsign = match[4];

					return [
						{ id: `radio_logic_${logic}_callsign`, action: 'set_content', payload: callsign },
						{ id: `radio_logic_${logic}_destination`, action: 'set_content', payload: `Talkgroup: ${talkgroup}` },
						{ id: `radio_logic_${logic}_status`, action: 'set_content', payload: 'INCOMING' },
						{ id: `radio_logic_${logic}_status`, action: 'add_class', class: 'active-mode-cell' },
						{ id: `radio_logic_${logic}_status`, action: 'remove_parent_class', class: 'hidden' }
					];
				}
			},

			// @bookmark Talker stop
			{
				regex: /^(.+?): (\S+): Talker stop on TG #(\d+): (\S+)$/,
				handler: (match) => {
					const logic = match[2];
					const group = match[3];
					return [
						{ id: `radio_logic_${logic}_callsign`, action: 'set_content', payload: '' },
						{ id: `radio_logic_${logic}_destination`, action: 'set_content', payload: '' },
						{ id: `radio_logic_${logic}_status`, action: 'set_content', payload: '' },
						{ id: `radio_logic_${logic}_status`, action: 'remove_class', class: 'active-mode-cell' },
						{ id: `radio_logic_${logic}_status`, action: 'add_parent_class', class: 'hidden' }
					];
				}
			},

			// @bookmark Node left
			{
				regex: /^(.+?): (\S+): Node left: (\S+)$/,
				handler: (match) => {
					const logic = match[2];
					const callsign = match[3];

					this.sm.stopTimer(`logic_${logic}_node_${callsign}`);

					return [
						{
							id: `logic_${logic}_node_${callsign}`,
							action: 'remove_element'
						}
					]
				}
			},

			// @bookmark Node joined
			{
				regex: /^(.+?): (\S+): Node joined: (\S+)$/,
				handler: (match) => {
					const logic = match[2];
					const callsign = match[3];

					this.sm.startTimer(`logic_${logic}_node_${callsign}`, {
						elementId: `logic_${logic}_node_${callsign}`,
						replaceStart: '<b>Uptime:</b>',
						replaceEnd: '<br>',
						type: 'node'
					});

					return [
						{
							id: `logic_${logic}_nodes`,
							action: 'add_child',
							new_id: `logic_${logic}_node_${callsign}`,
							payload: `<div class="mode_flex column disabled-mode-cell" title="${callsign}" style="border: .5px solid #3c3f47;"><a class="tooltip" href="#"><span><b>Uptime:</b>00:00:00<br></span>${callsign}</a></div>`,
						},
					]
				}
			},

			// @bookmark Selecting TG #0
			{
				regex: /^(.+?): (\S+): Selecting TG #0/,
				handler: (match) => {
					
					const logic = match[1];
					return [
						{ id: `logic_${logic}_groups`, action: 'remove_child', ignoreClass: 'default,monitored' },
						{ id: `logic_${logic}_groups`, class: 'disabled-mode-cell', operation: 'replace_class', action: 'handle_child_classes', oldClass: 'active-mode-cell' },
						{ id: `logic_${logic}_groups`, class: 'default,active-mode-cell', operation: 'replace_class', action: 'handle_child_classes', oldClass: 'default,disabled-mode-cell' },
					];
				}
			},

			// @bookmark Selecting TG #
			{
				regex: /^(.+?): (\S+): Selecting TG #(\d+)/,
				handler: (match) => {
					const logic = match[2];
					const group = match[3];

					if (group === "0") {
						return [];
					}

					return [
						{ id: `logic_${logic}_groups`, action: 'remove_child', ignoreClass: 'default,monitored', },
						{ id: `logic_${logic}_groups`, class: 'disabled-mode-cell', operation: 'replace_class', action: 'handle_child_classes', oldClass: 'active-mode-cell' },
						{
							id: `logic_${logic}_groups`,
							action: "add_child",
							payload: `<div id="logic_${logic}_group_${group}" class="mode_flex column active-mode-cell" title="${group}" style="border: .5px solid #3c3f47;">${group}</div>`
						},
					];
				}
			},

			// @bookmark Add temporary monitor for TG #
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
							id: `logic_${logic}_groups`,
							action: 'add_child',
							payload: `<div id = "logic_${logic}_group_${group}" class="mode_flex column paused-mode-cell" title="${group}" style="border: .5px solid #3c3f47;">${group}</div>`,
						},
					];
				}
			},

			// @bookmark Refresh temporary monitor for TG #
			{
				regex: /^(.+?): (\S+): Refresh temporary monitor for TG #(\d+)/,
				handler: (match) => {
					const logic = match[2];
					const group = match[3];
					return [
						{
							id: `logic_${logic}_groups`,
							action: 'add_child',
							payload: `<div id = "logic_${logic}_group_${group}" class="mode_flex column paused-mode-cell" title="${group}" style="border: .5px solid #3c3f47;">${group}</div>`,
						},
					];
				}
			},

			// @bookmark Temporary monitor timeout for TG #
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

			// @bookmark Активация модуля
			{
				regex: /^(.+?): (\S+): Activating module (\S+)\.\.\.$/,
				handler: (match) => {
					const logic = match[2];
					const module = match[3];
					this.moduleHandler.add(module, logic);

					const initialTime = this.sm.formatDuration(0);
					const activatedHtml = `<a class="tooltip" href="#"><span><b>Uptime:</b>${initialTime}<br></span>${module}</a>`;

					this.sm.startTimer(`${logic}_${module}`, {
						elementId: `logic_${logic}_module_${module}`,
						replaceStart: '<b>Uptime:</b>',
						replaceEnd: '<br>',
						logic: logic,
						module: module
					});

					return [
						{
							id: `logic_${logic}`,
							action: 'remove_class',
							class: 'disabled-mode-cell,inactive-mode-cell,active-mode-cell'
						},
						{
							id: `logic_${logic}`,
							action: 'add_class',
							class: 'paused-mode-cell'
						},
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
							payload: activatedHtml
						},
						{
							id: `${logic}_destination`,
							action: 'set_content',
							payload: module
						},
					];
				}
			},

			// @bookmark Деактивация модуля
			{
				regex: /^(.+?): (\S+): Deactivating module (\S+)\.\.\.$/,
				handler: (match) => {
					const logic = match[2];
					const module = match[3];
					this.moduleHandler.remove(module, logic);

					this.sm.stopTimer(`${logic}_${module}`);

					let hasActiveLinks = false;
					for (const [key] of this.sm.timers) {
						if (key.startsWith('link_')) {
							hasActiveLinks = true;
							break;
						}
					}
					let newClass = 'disabled-mode-cell';
					if (hasActiveLinks) newClass = 'active-mode-cell';

					return [
						{
							id: `logic_${logic}`,
							action: 'remove_class',
							class: 'disabled-mode-cell,paused-mode-cell,inactive-mode-cell,active-mode-cell'
						},
						{
							id: `logic_${logic}`,
							action: 'add_class',
							class: newClass
						},
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
						{
							id: `logic_${logic}_module_${module}`,
							action: 'set_content',
							payload: module
						},
						{
							id: `logic_${logic}_active`,
							action: 'add_class',
							class: 'hidden'
						},
						{
							id: `logic_${logic}_active`,
							action: 'remove_parent_class',
							class: 'module-active'
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
						{
							id: `radio_logic_${logic}_destination`,
							action: 'set_content',
							payload: ''
						},
					];
				}
			},

			// @bookmark EchoLink: QSO state changed to CONNECTED
			{
				regex: /^(.+?): (\S+): EchoLink QSO state changed to (CONNECTED)$/,
				handler: (match) => {
					const node = match[2];
					const allLogics = this.moduleHandler.getAll('EchoLink');
					const resultCommands = [];

					if (allLogics.length === 0) {
						console.log(`[WARNING] EchoLink event but no logic links found!`);
						return [];
					}

					for (const logic of allLogics) {
						this.sm.startTimer(`EchoLink_${node}`, {
							elementId: `logic_${logic}_active_content`,
							replaceStart: '<b>Uptime:</b>',
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
							{ id: `logic_${logic}_module_EchoLink`, action: 'add_parent_class', class: 'module-connected' },
							{ id: `logic_${logic}_active_header`, action: 'set_content', payload: 'EchoLink' },
							{ id: `logic_${logic}_active_content`, action: 'set_content', payload: `<a class="tooltip" href="#"><span><b>Uptime:</b>0 s<br>Connected ${node}</span>${node}</a>` },
							{ id: `radio_logic_${logic}_destination`, action: 'set_content', payload: `EchoLink:  ${node}` },
						);
					}

					return resultCommands;
				}
			},

			// @bookmark EchoLink: QSO state changed to DISCONNECTED
			{
				regex: /^(.+?): (\S+): EchoLink QSO state changed to (DISCONNECTED)$/,
				handler: (match) => {
					const node = match[2];
					const allLogics = this.moduleHandler.getAll('EchoLink');
					const resultCommands = [];

					if (allLogics.length === 0) {
						console.log(`[WARNING] EchoLink DISCONNECTED event but no logic links found!`);
						return [];
					}

					for (const logic of allLogics) {
						this.sm.stopTimer(`EchoLink_${node}`);

						resultCommands.push(
							{ id: `logic_${logic}`, action: 'remove_class', class: 'active-mode-cell' },
							{ id: `logic_${logic}`, action: 'add_class', class: 'paused-mode-cell' },
							{ id: `logic_${logic}_module_EchoLink`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,disabled-mode-cell' },
							{ id: `logic_${logic}_module_EchoLink`, action: 'add_class', class: 'paused-mode-cell' },
							{ id: `logic_${logic}_active`, action: 'add_class', class: 'hidden' },
							// { id: `logic_${logic}_active`, action: 'remove_parent_class', class: 'module-connected' },
							{ id: `logic_${ logic }_active_header`, action: 'set_content', payload: '' },
							{ id: `logic_${logic }_active_content`, action: 'set_content', payload: '' },
							{ id: `radio_logic_${logic}_destination`, action: 'set_content', payload: `EchoLink` },
						);
					}

					return resultCommands;
				}
			},

			// @bookmark EchoLink: --- EchoLink chat message received from ... ---
			{
				regex: /^(.+?): --- EchoLink chat message received from (\S+) ---$/,
				handler: (match) => {
					const timestamp = match[1];
					const node = match[2];

					// Начинаем пакетный режим
					this.startPacket('EchoLink', timestamp, { node });
					const commands = [];
					commands.push({
						id: 'EchoLink_chat_message_received',
						targetClass: 'callsign',
						action: 'set_content_by_class',
						payload: ''
					},
						{
							id: 'EchoLink_chat_message_received',
							targetClass: 'destination',
							action: 'set_content_by_class',
							payload: `EchoLink: Conference: ${node}`,
						}
					);


					return commands;
				}
			},

			// @bookmark Frn: login stage 2 completed
			{
				regex: /^(.+?): login stage 2 completed: (.+)$/,
				handler: (match) => {
					let serverName = 'Frn Server';
					const xml = match[2];
					const bnMatch = xml.match(/<BN>(.*?)<\/BN>/);
					if (bnMatch && bnMatch[1]) {
						serverName = bnMatch[1];
					}

					const allLogics = this.moduleHandler.getAll('Frn');
					const resultCommands = [];

					if (allLogics.length === 0) {
						console.log(`[WARNING] Frn event but no logic links found!`);
						return [];
					}

					for (const logic of allLogics) {
						this.sm.startTimer(`Frn_${logic}`, {
							elementId: `logic_${logic}_module_Frn`,
							replaceStart: '<b>Uptime:</b>',
							replaceEnd: '<br>',
							type: 'module_frn',
							logic: logic,
							server: serverName
						});

						resultCommands.push(
							{ id: `logic_${logic}_module_Frn`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell,disabled-mode-cell' },
							{ id: `logic_${logic}_module_Frn`, action: 'add_class', class: 'active-mode-cell' },
							{ id: `logic_${logic}_active`, action: 'remove_class', class: 'hidden' },
							// { id: `logic_${logic}_active`, action: 'add_parent_class', class: 'module-connected' },
							{ id: `logic_${logic}_active_header`, action: 'set_content', payload: 'Frn' },
							{ id: `logic_${logic}_active_content`, action: 'set_content', payload: `<a class="tooltip" href="#"><span><b>Uptime:</b>00:00:00<br>Server ${serverName}</span>${serverName}</a>` },
							{ id: `radio_logic_${logic}_destination`, action: 'set_content', payload: `Frn: ${serverName}` },
						);
					}

					return resultCommands;
				}
			},

			// @bookmark Frn: voice started
			{
				regex: /^(.+?): voice started: (.+)$/,
				handler: (match) => {
					const xml = match[2];
					const onMatch = xml.match(/<ON>(.*?)<\/ON>/);
					const nodeCallsign = onMatch ? onMatch[1].trim() : 'Unknown Frn node';

					const allLogics = this.moduleHandler.getAll('Frn');
					const commands = [];

					if (allLogics.length === 0) {
						console.log(`[WARNING] Frn voice started event but no logic links found!`);
						return [];
					}

					allLogics.forEach(logic => {
						commands.push({
							id: `radio_logic_${logic}_callsign`,
							action: 'set_content',
							payload: `${nodeCallsign}`
						});
					});

					console.log(`Frn voice started: Updated ${allLogics.length} logics with node ${nodeCallsign}`);
					return commands;
				}
			},

			// @bookmark Frn: FRN list received:
			{
				regex: /^(.+?): FRN list received:$/,
				handler: (match) => {
					const timestamp = match[1];

					// Начинаем пакетный режим
					this.startPacket('Frn', timestamp);

					// Возвращаем пустой результат
					return [];
				}
			},

			// @bookmark Активация линка
			{
				regex: /^(.+?): Activating link (\S+)$/,
				handler: (match) => {
					const link = match[2];

					this.sm.startTimer(`link_${link}`, {
						elementId: `link_${link}`,
						replaceStart: '<b>Uptime:</b>',
						replaceEnd: '<br>',
						type: 'link'
					});

					return [
						{ id: `link_${link}`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell,disabled-mode-cell' },
						{ id: `link_${link}`, action: 'add_class', class: 'active-mode-cell' },
						{ id: `link_${link}`, action: 'add_parent_class', class: 'link-active' },
						{ id: `toggle-link-state-${link}`, action: "set_checkbox_state", state: "on" }
					];
				}
			},

			// @bookmark Деактивация линка
			{
				regex: /^(.+?): Deactivating link (\S+)$/,
				handler: (match) => {
					const link = match[2];

					this.sm.stopTimer(`link_${link}`);

					return [
						{ id: `link_${link}`, action: 'remove_class', class: 'active-mode-cell,paused-mode-cell,disabled-mode-cell' },
						{ id: `link_${link}`, action: 'add_class', class: 'inactive-mode-cell' },
						{ id: `link_${link}`, action: 'remove_parent_class', class: 'link-active' },
						{ id: `toggle-link-state-${link}`, action: "set_checkbox_state", state: "off" }
					];
				}
			},
		];
	}

	// Начало пакетного режима
	startPacket(type, timestamp, metadata = {}) {
		// Завершаем предыдущий пакет если был
		this.finalizePacket();

		// Начинаем новый
		this.packetActive = true;
		this.packetType = type;
		this.packetBuffer = [];
		this.packetStartTime = timestamp;
		this.packetMetadata = metadata;

		console.log(`[DEBUG] Packet mode started: ${type} at ${timestamp}, node: ${metadata.node || 'unknown'}`);
	}

	// Завершение пакетного режима и обработка накопленных данных
	finalizePacket() {
		if (!this.packetActive) return null;

		console.log(`[DEBUG] Finalizing ${this.packetType} packet with ${this.packetBuffer.length} lines`);
		let commands = [];

		if (this.packetType === 'EchoLink') {
			// Ищем передающий позывной в пакете
			for (const line of this.packetBuffer) {
				// Убираем timestamp
				const content = line.replace(/^.+?: /, '');

				// Передающий позывной отмечен '->'
				if (content.startsWith('->')) {
					const match = content.match(/^->(\S+)/);
					if (match) {
						const transmittingCallsign = match[1];
						const conferenceNode = this.packetMetadata.node;

						commands.push({
							targetClass: 'callsign',
							action: 'set_content_by_class',
							payload: transmittingCallsign
						});
						commands.push({
							targetClass: 'destination',
							action: 'set_content_by_class',
							payload: conferenceNode
						});

						console.log(`[DEBUG] Found EchoLink transmitting callsign: ${transmittingCallsign} for ${conferenceNode}`);
						break;
					}
				}
			}
		} else if (this.packetType === 'Frn') {
			console.log(`[DEBUG] TODO Frn packet: ${this.packetBuffer.length} lines (not processed)`);
		}

		// Сбрасываем состояние
		this.packetActive = false;
		this.packetBuffer = [];
		this.packetType = null;
		this.packetMetadata = {};

		if (commands.length > 0) {
			return {
				commands: commands,
				raw: `[${this.packetType} packet: ${this.packetBuffer.length} lines]`,
				timestamp: this.packetStartTime
			};
		}

		return null;
	}

	// Основной метод парсинга
	parse(line) {
		const trimmed = line.trim();

		for (const pattern of this.patterns) {
			const match = trimmed.match(pattern.regex);
			if (match) {
				const packetResult = this.finalizePacket();
				const commandResult = pattern.handler(match);

				const result = {
					commands: commandResult,
					raw: match[0],
					timestamp: match[1]
				};

				if (packetResult && packetResult.commands && packetResult.commands.length > 0) {
					result.commands = [...packetResult.commands, ...commandResult];
					result.raw = `${packetResult.raw} + ${result.raw}`;
				}

				return result;
			}
		}

		if (this.packetActive) {
			this.packetBuffer.push(trimmed);

			if (this.packetType === 'EchoLink' && trimmed.includes('Trailing chat data:')) {
				return this.finalizePacket();
			}

			if (this.packetBuffer.length > 1 && trimmed.match(/^\d+ \w+ \d{4} \d{2}:\d{2}:\d{2}\.\d{3}: /)) {
				return this.finalizePacket();
			}

			return null;
		}

		return null;
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
			this.moduleHandler.initFromData(wsData.module_logic);
		}
		log(`CommandParser initialized from WS data: ${this.moduleHandler.moduleToLogics.size} module links`);
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
			log('Loading WS state from PHP...');

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
				logics: wsData.logics || {}
			};

			this.stats.stateLoads++;

			// Восстанавливаем состояние
			this.restoreFromWsState();

			log(`WS state loaded: ${Object.keys(this.wsState.devices).length} devices, ${Object.keys(this.wsState.modules).length} modules`);
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
				logics: {}
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
		const logics = this.wsState.logics || {};

		let restoredCount = 0;

		// 1. Восстанавливаем таймеры устройств
		for (const [device, info] of Object.entries(devices)) {
			if (info && info.start && info.start > 0) {
				const startTime = info.start * 1000; // Конвертируем в миллисекунды

				// TX таймер
				this.stateManager.startTimer(`${device}_TX`, {
					elementId: `device_${device}_status`,
					replaceStart: '( ',
					replaceEnd: ' )',
					type: 'device_TX'
				});
				this.stateManager.setTimerStart(`${device}_TX`, startTime);

				// RX таймер
				this.stateManager.startTimer(`${device}_RX`, {
					elementId: `device_${device}_status`,
					replaceStart: '( ',
					replaceEnd: ' )',
					type: 'device_RX'
				});
				this.stateManager.setTimerStart(`${device}_RX`, startTime);

				restoredCount++;
			}
		}

		// 2. Восстанавливаем таймеры модулей
		for (const [moduleKey, info] of Object.entries(modules)) {
			if (info && info.start && info.start > 0) {
				const [logicName, moduleName] = moduleKey.split('_');
				const startTime = info.start * 1000;

				if (logicName && moduleName) {
					// Добавляем в moduleHandler
					this.commandParser.moduleHandler.add(moduleName, logicName);

					// Запускаем таймер
					this.stateManager.startTimer(`${logicName}_${moduleName}`, {
						elementId: `logic_${logicName}_module_${moduleName}`,
						replaceStart: '<b>Uptime:</b>',
						replaceEnd: '<br>',
						logic: logicName,
						module: moduleName
					});
					this.stateManager.setTimerStart(`${logicName}_${moduleName}`, startTime);

					restoredCount++;
				}
			}
		}

		// 3. Восстанавливаем таймеры линков
		for (const [linkName, info] of Object.entries(links)) {
			if (info && info.start && info.start > 0) {
				const startTime = info.start * 1000;

				this.stateManager.startTimer(`link_${linkName}`, {
					elementId: `link_${linkName}`,
					replaceStart: '<b>Uptime:</b>',
					replaceEnd: '<br>',
					type: 'link'
				});
				this.stateManager.setTimerStart(`link_${linkName}`, startTime);

				restoredCount++;
			}
		}

		// 4. Восстанавливаем таймеры узлов
		for (const [nodeKey, info] of Object.entries(nodes)) {
			if (info && info.start && info.start > 0) {
				const startTime = info.start * 1000;

				// Формат nodeKey: "logic_{logicName}_node_{nodeName}"
				// Пример: "logic_ReflectorLogicKAVKAZ_node_RY6HAB-1"

				// Извлекаем logicName и nodeName из nodeKey
				const match = nodeKey.match(/^logic_(.+?)_node_(.+)$/);
				if (match) {
					const logicName = match[1];
					const nodeName = match[2];

					// Единый формат ключа таймера как в обработке событий
					const timerKey = `logic_${logicName}_node_${nodeName}`;
					const elementId = `logic_${logicName}_node_${nodeName}`;

					this.stateManager.startTimer(timerKey, {
						elementId: elementId,
						replaceStart: '<b>Uptime:</b>',
						replaceEnd: '<br>',
						type: 'node'
					});
					this.stateManager.setTimerStart(timerKey, startTime);

					restoredCount++;
				} else {
					log(`Invalid nodeKey format: ${nodeKey}`, 'WARNING');
				}
			}
		}
		// 5. Инициализируем CommandParser
		if (this.wsState.module_logic) {
			this.commandParser.initFromWsData(this.wsState);
		}

		log(`State restored: ${restoredCount} items, ${this.stateManager.timers.size} timers active`);
	}

	/// Отправка начальных команд новому клиенту
	sendInitialCommands(ws) {
		const commands = [];

		// Безопасный доступ к данным
		const devices = this.wsState.devices || {};
		const modules = this.wsState.modules || {};
		const links = this.wsState.links || {};
		const logics = this.wsState.logics || {};

		// 1. Команды для устройств
		for (const [device, info] of Object.entries(devices)) {
			if (info && info.start && info.start > 0) {
				const duration = Math.floor(Date.now() / 1000 - info.start);
				const durationText = this.stateManager.formatDuration(duration * 1000);

				// TX состояние
				commands.push({
					id: `device_${device}_status`,
					action: 'set_content',
					payload: `TRANSMIT ( ${durationText} )`
				});
				commands.push({
					id: `device_${device}_status`,
					action: 'add_class',
					class: 'inactive-mode-cell'
				});

				// RX состояние
				commands.push({
					id: `device_${device}_status`,
					action: 'set_content',
					payload: `RECEIVE ( ${durationText} )`
				});
				commands.push({
					id: `device_${device}_status`,
					action: 'add_class',
					class: 'active-mode-cell'
				});
			}
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

				log(`Sent ${commands.length} initial commands to new client`);
			} catch (error) {
				log(`Failed to send initial commands: ${error.message}`, 'ERROR');
			}
		}
	}

	// ==================== УПРАВЛЕНИЕ СЕРВЕРОМ ====================

	async start() {
		log(`Starting Stateful WebSocket Server v${this.config.version} with state support`);
		log(`WebSocket: ${this.config.ws.host}:${this.config.ws.port}`);

		try {
			// Проверка файла лога
			if (!fs.existsSync(this.config.log.path)) {
				log(`Log file not found: ${this.config.log.path}`, 'WARNING');
			}

			// Запуск WebSocket сервера
			this.startWebSocket();

			// Таймер выключения при отсутствии клиентов
			this.startShutdownTimer();

			log('Server v4.1 started successfully', 'INFO');

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

		log(`Client ${clientId} connected from ${clientIp} (total: ${this.clients.size})`);

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
			message: 'Connected to Stateful WebSocket Server 4.1 with state support',
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

		log('Starting log monitoring...');

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
		let allCommands = [];

		linesToProcess.forEach(line => {
			const result = this.commandParser.parse(line);

			if (result && result.commands && result.commands.length > 0) {
				allCommands.push(...result.commands);
				this.stats.eventsProcessed++;
				this.stats.commandsGenerated += result.commands.length;

				log(`Processed event: ${result.commands.length} commands generated`);

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
						log(`${cmdInfo} (${details.join(', ')})`);
					} else {
						log(cmdInfo);
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
					log(`Sent ${chunk.length} DOM commands to ${sentCount} clients (chunk ${message.chunk}/${message.chunks})`);
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
							log(`Sent ${timeCommands.length} time update commands`);
						}
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

		log(`Shutdown timer set for ${this.config.ws.clientTimeout}ms`);
	}

	clearShutdownTimer() {
		if (this.shutdownTimer) {
			clearTimeout(this.shutdownTimer);
			this.shutdownTimer = null;
			log('Shutdown timer cleared');
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

				log(`Server v4.1 Statistics:`);
				log(`  Uptime: ${uptime}s`);
				log(`  Clients connected: ${this.stats.clientsConnected}`);
				log(`  Clients disconnected: ${this.stats.clientsDisconnected}`);
				log(`  Messages sent: ${this.stats.messagesSent}`);
				log(`  Events processed: ${this.stats.eventsProcessed}`);
				log(`  Commands generated: ${this.stats.commandsGenerated}`);
				log(`  State loads: ${this.stats.stateLoads}`);
				log(`  State load errors: ${this.stats.stateLoadErrors}`);
				log(`  Active timers: ${stateStats.activeTimers}`);
				log(`  Errors: ${this.stats.errors}`);

				log('Server shutdown complete', 'INFO');
				process.exit(0);
			});

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
				nodes: Object.keys(this.wsState.nodes).length
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

	// Запуск сервера v4.1
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
	ModuleLogicHandler
};