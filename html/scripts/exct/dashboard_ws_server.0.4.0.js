/**
 * @filesource /scripts/exct/dashboard_ws_server.4.0.js
 * @version 4.0
 * @date 2026.01.09
 * @description Stateful WebSocket сервер для SvxLink Dashboard
 */

const WebSocket = require('ws');
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

// @bookmark  КОНСТАНТЫ И КОНФИГУРАЦИЯ
const CONFIG = {
	version: '4.0',
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
	}

	// Удалить связь
	remove(module, logic) {
		const logics = this.moduleToLogics.get(module);
		if (logics) {
			logics.delete(logic);
			if (logics.size === 0) {
				this.moduleToLogics.delete(module);
			}
		}
	}

	// Получить все логики для модуля
	getAll(module) {
		const logics = this.moduleToLogics.get(module);
		return logics ? Array.from(logics) : [];
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
						this.sm.startTimer(`${device}_TX`, {
							elementId: `${device}_StatusTX`,
							replaceStart: '( ',
							replaceEnd: ' )',
							type: 'device_tx'
						});

						return [
							{ id: `${device}_StatusTX`, action: 'set_content', payload: 'TRANSMIT ( 0 s )' },
							{ id: `${device}_StatusTX`, action: 'add_class', class: 'inactive-mode-cell' },
						];
					} else {
						this.sm.stopTimer(`${device}_TX`);

						return [
							{ id: `${device}_StatusTX`, action: 'set_content', payload: 'STANDBY' },
							{ id: `${device}_StatusTX`, action: 'remove_class', class: 'inactive-mode-cell' },
							// { id: `${device}Destination`, action: 'set_content', payload: '' },
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
						this.sm.startTimer(`${device}_RX`, {
							elementId: `${device}_StatusRX`,
							replaceStart: '( ',
							replaceEnd: ' )',
							type: 'device_rx'
						});

						return [
							{ id: `${device}_StatusRX`, action: 'set_content', payload: 'RECEIVE ( 0 s )' },
							{ id: `${device}_StatusRX`, action: 'add_class', class: 'active-mode-cell' },
						];
					} else {
						this.sm.stopTimer(`${device}_RX`);

						return [
							{ id: `${device}_StatusRX`, action: 'set_content', payload: 'STANDBY' },
							{ id: `${device}_StatusRX`, action: 'remove_class', class: 'active-mode-cell' },
							// { id: `${device}Destination`, action: 'set_content', payload: '' },
						];
					}
				}
			},

			// @bookmark Talker start
			{
				regex: /^(.+?): (\S+): Talker start on TG #(\d*): (\S+)$/,
				handler: (match) => {
					const device = match[2];
					const talkgroup = match[3];
					const callsign = match[4];

					return [
						{ id: `${device}Callsign`, action: 'set_content', payload: callsign },
						{ id: `${device}Destination`, action: 'set_content', payload: `Talkgroup: ${talkgroup}` },
						{ id: `${device}_StatusTX`, action: 'set_content', payload: 'INCOMING' },
						{ id: `${device}_StatusTX`, action: 'add_class', class: 'active-mode-cell' },
						{ id: `${device}_StatusTX`, action: 'remove_parent_class', class: 'hidden' }
					];
				}
			},

			// @bookmark Talker stop
			{
				regex: /^(.+?): (\S+): Talker stop on TG #(\d+): (\S+)$/,
				handler: (match) => {
					const device = match[2];
					const talkgroup = match[3];
					return [
						{ id: `${device}Callsign`, action: 'set_content', payload: '' },
						{ id: `${device}Destination`, action: 'set_content', payload: '' },
						{ id: `${device}_StatusTX`, action: 'set_content', payload: '' },
						{ id: `${device}_StatusTX`, action: 'remove_class', class: 'active-mode-cell' },
						{ id: `${device}_StatusTX`, action: 'add_parent_class', class: 'hidden' }
					];
				}
			},

			// @bookmark Node left
			{
				regex: /^(.+?): (\S+): Node left: (\S+)$/,
				handler: (match) => {
					const device = match[2];
					const callsign = match[3];

					this.sm.stopTimer(`node_${device}_${callsign}`);

					return [
						{
							id: `logic_${device}_node_${callsign}`,
							action: 'remove_element'
						}
					]
				}
			},

			// @bookmark Node joined
			{
				regex: /^(.+?): (\S+): Node joined: (\S+)$/,
				handler: (match) => {
					const device = match[2];
					const callsign = match[3];

					this.sm.startTimer(`node_${device}_${callsign}`, {
						elementId: `logic_${device}_node_${callsign}`,
						replaceStart: '<b>Uptime:</b>',
						replaceEnd: '</span>',
						type: 'node'
					});

					return [
						{
							id: `logic_${device}_nodes`,
							action: 'add_child',
							new_id: `logic_${device}_node_${callsign}`,
							payload: `<div class="mode_flex column disabled-mode-cell" title="${callsign}" style="border: .5px solid #3c3f47;"><a class="tooltip" href="#"><span><b>Uptime:</b>00:00:00</span>${callsign}</a></div>`,
						},
					]
				}
			},

			// @bookmark Selecting TG #0
			{
				regex: /^(.+?): (\S+): Selecting TG #0/,
				handler: (match) => {
					const device = match[2];

					return [
						{ id: `logic_${device}_GroupsTableBody`, action: 'remove_child', ignoreClass: 'default,monitored' },
						{ id: `logic_${device}_GroupsTableBody`, class: 'disabled-mode-cell', operation: 'replace_class', action: 'handle_child_classes', oldClass: 'active-mode-cell' },
						{ id: `logic_${device}_GroupsTableBody`, class: 'default,active-mode-cell', operation: 'replace_class', action: 'handle_child_classes', oldClass: 'default,disabled-mode-cell' },
					];
				}
			},

			// @bookmark Selecting TG #
			{
				regex: /^(.+?): (\S+): Selecting TG #(\d+)/,
				handler: (match) => {
					const device = match[2];
					const talkgroup = match[3];

					if (talkgroup === "0") {
						return [];
					}

					return [
						{ id: `logic_${device}_GroupsTableBody`, action: 'remove_child', ignoreClass: 'default,monitored', },
						{ id: `logic_${device}_GroupsTableBody`, class: 'disabled-mode-cell', operation: 'replace_class', action: 'handle_child_classes', oldClass: 'active-mode-cell' },
						{
							id: `logic_${device}_GroupsTableBody`,
							action: "add_child",
							payload: `<div id="logic_${device}_Group_${talkgroup}" class="mode_flex column active-mode-cell" title="${talkgroup}" style="border: .5px solid #3c3f47;">${talkgroup}</div>`
						},
					];
				}
			},

			// @bookmark Add temporary monitor for TG #
			{
				regex: /^(.+?): (\S+): Add temporary monitor for TG #(\d+)/,
				handler: (match) => {
					const device = match[2];
					const talkgroup = match[3];

					if (talkgroup === "0") {
						return [];
					}

					return [
						{
							id: `logic_${device}_GroupsTableBody`,
							action: 'add_child',
							payload: `<div id = "logic_${device}_Group_${talkgroup}" class="mode_flex column paused-mode-cell" title="${talkgroup}" style="border: .5px solid #3c3f47;">${talkgroup}</div>`,
						},
					];
				}
			},

			// @bookmark Refresh temporary monitor for TG #
			{
				regex: /^(.+?): (\S+): Refresh temporary monitor for TG #(\d+)/,
				handler: (match) => {
					const device = match[2];
					const talkgroup = match[3];
					return [
						{
							id: `logic_${device}_GroupsTableBody`,
							action: 'add_child',
							payload: `<div id = "logic_${device}_Group_${talkgroup}" class="mode_flex column paused-mode-cell" title="${talkgroup}" style="border: .5px solid #3c3f47;">${talkgroup}</div>`,
						},
					];
				}
			},

			// @bookmark Temporary monitor timeout for TG #
			{
				regex: /^(.+?): (\S+): Temporary monitor timeout for TG #(\d+)/,
				handler: (match) => {
					const device = match[2];
					const talkgroup = match[3];

					return [
						{
							id: `logic_${device}_Group_${talkgroup}`,
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
					const activatedHtml = `<a class="tooltip" href="#"><span><b>Uptime:</b>${initialTime}</span>${module}</a>`;

					this.sm.startTimer(`${logic}_${module}`, {
						elementId: `module_${logic}${module}`,
						replaceStart: '<b>Uptime:</b>',
						replaceEnd: '</span>',
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
							id: `module_${logic}${module}`,
							action: 'remove_class',
							class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell,disabled-mode-cell'
						},
						{
							id: `module_${logic}${module}`,
							action: 'add_class',
							class: 'paused-mode-cell'
						},
						{
							id: `module_${logic}${module}`,
							action: 'set_content',
							payload: activatedHtml
						},
						{
							id: `${logic}Destination`,
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
							id: `module_${logic}${module}`,
							action: 'remove_class',
							class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell'
						},
						{
							id: `module_${logic}${module}`,
							action: 'add_class',
							class: 'disabled-mode-cell'
						},
						{
							id: `module_${logic}${module}`,
							action: 'set_content',
							payload: module
						},
						{
							id: `module_${logic}_Status`,
							action: 'add_class',
							class: 'hidden'
						},
						{
							id: `module_${logic}_Status`,
							action: 'remove_parent_class',
							class: 'module-active'
						},
						{
							id: `module_${logic}_Status_Header`,
							action: 'set_content',
							payload: ''
						},
						{
							id: `module_${logic}_Status_Content`,
							action: 'set_content',
							payload: ''
						},
						{
							id: `${logic}Destination`,
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
							elementId: `module_${logic}_Status_Content`,
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
							{ id: `module_${logic}EchoLink`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell,disabled-mode-cell' },
							{ id: `module_${logic}EchoLink`, action: 'add_class', class: 'active-mode-cell' },
							{ id: `module_${logic}_Status`, action: 'remove_class', class: 'hidden' },
							{ id: `module_${logic}_Status`, action: 'add_parent_class', class: 'module-connected' },
							{ id: `module_${logic}_Status_Header`, action: 'set_content', payload: 'EchoLink' },
							{ id: `module_${logic}_Status_Content`, action: 'set_content', payload: `<a class="tooltip" href="#"><span><b>Uptime:</b>00:00:00<br>Connected ${node}</span>${node}</a>` },
							{ id: `${logic}Destination`, action: 'set_content', payload: `EchoLink:  ${node}` },
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
							{ id: `module_${logic}EchoLink`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,disabled-mode-cell' },
							{ id: `module_${logic}EchoLink`, action: 'add_class', class: 'paused-mode-cell' },
							{ id: `module_${logic}_Status`, action: 'add_class', class: 'hidden' },
							{ id: `module_${logic}_Status`, action: 'remove_parent_class', class: 'module-connected' },
							{ id: `module_${logic}_Status_Header`, action: 'set_content', payload: '' },
							{ id: `module_${logic}_Status_Content`, action: 'set_content', payload: '' },
							{ id: `${logic}Destination`, action: 'set_content', payload: `EchoLink` },
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
						targetClass: 'callsign',
						action: 'set_content_by_class',
						payload: ''  
					});


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
							elementId: `module_${logic}_Status_Content`,
							replaceStart: '<b>Uptime:</b>',
							replaceEnd: '<br>',
							type: 'module_frn',
							logic: logic,
							server: serverName
						});

						resultCommands.push(
							{ id: `module_${logic}Frn`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell,disabled-mode-cell' },
							{ id: `module_${logic}Frn`, action: 'add_class', class: 'active-mode-cell' },
							{ id: `module_${logic}_Status`, action: 'remove_class', class: 'hidden' },
							{ id: `module_${logic}_Status`, action: 'add_parent_class', class: 'module-connected' },
							{ id: `module_${logic}_Status_Header`, action: 'set_content', payload: 'Frn' },
							{ id: `module_${logic}_Status_Content`, action: 'set_content', payload: `<a class="tooltip" href="#"><span><b>Uptime:</b>00:00:00<br>Server ${serverName}</span>${serverName}</a>` },
							{ id: `${logic}Destination`, action: 'set_content', payload: `Frn: ${serverName}` },
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
							id: `${logic}Callsign`,
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
						// Получаем все логики, связанные с EchoLink
						// const allLogics = this.moduleHandler.getAll('EchoLink');

						
						// Обновляем позывной и назначение для логики
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
			// Заглушка для Frn
			console.log(`[DEBUG] Frn packet: ${this.packetBuffer.length} lines (not processed)`);
			// Не возвращаем команд для DOM
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

		// Пустая строка может означать конец пакета
		// if (trimmed === '') {
		// 	if (this.packetActive) {
		// 		const packetResult = this.finalizePacket();
		// 		return packetResult;
		// 	}
		// 	return null;
		// }

		// 1. Сначала пробуем распознать как обычную команду
		for (const pattern of this.patterns) {
			const match = trimmed.match(pattern.regex);
			if (match) {
				// Если нашли команду - завершаем текущий пакет (если есть)
				const packetResult = this.finalizePacket();

				// Выполняем обработчик команды
				const commandResult = pattern.handler(match);

				// Подготавливаем результат
				const result = {
					commands: commandResult,
					raw: match[0],
					timestamp: match[1]
				};

				// Если были команды от пакета - объединяем их
				if (packetResult && packetResult.commands && packetResult.commands.length > 0) {
					result.commands = [...packetResult.commands, ...commandResult];
					result.raw = `${packetResult.raw} + ${result.raw}`;
				}

				return result;
			}
		}

		// 2. Если не распознали как команду, проверяем пакетный режим
		if (this.packetActive) {
			// Накопление строк в пакете
			this.packetBuffer.push(trimmed);

			// Проверяем особые условия конца пакета (для EchoLink)
			if (this.packetType === 'EchoLink' && trimmed.includes('Trailing chat data:')) {
				return this.finalizePacket();
			}

			// Проверяем, не является ли это началом нового пакета (по timestamp)
			// Простая проверка: если строка имеет timestamp и мы уже накопили данные
			if (this.packetBuffer.length > 1 && trimmed.match(/^\d+ \w+ \d{4} \d{2}:\d{2}:\d{2}\.\d{3}: /)) {
				// Это новое событие - завершаем текущий пакет
				return this.finalizePacket();
			}

			// Пакет продолжается, не возвращаем команды
			return null;
		}

		// 3. Ничего не распознали и не в пакетном режиме
		return null;
	}

	// Метод для обновления времени (существующий)
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

		// Статистика
		this.stats = {
			startedAt: Date.now(),
			clientsConnected: 0,
			clientsDisconnected: 0,
			messagesSent: 0,
			errors: 0,
			eventsProcessed: 0,
			commandsGenerated: 0
		};
	}

	// ==================== УПРАВЛЕНИЕ СЕРВЕРОМ ====================

	start() {
		log(`Starting Stateful WebSocket Server v${this.config.version} (DOM Commands System)`);
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

			log('Server v4.0 started successfully', 'INFO');

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
			this.stats.errors++;
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
			message: 'Connected to Stateful WebSocket Server 4.0 with DOM commands system',
			system: 'dom_commands_v4'
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

		// Буфер для группировки строк
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

				// Выводим подробную информацию о командах
				log(`Processed event: ${result.commands.length} commands generated`);

				// Выводим содержимое каждой команды
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

		// Отправляем команды клиентам
		if (allCommands.length > 0 && this.clients.size > 0) {
			// Группируем команды по 10 для эффективности
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
				// 1. Получаем обновления таймеров из StateManager
				const timerUpdates = this.stateManager.getTimerUpdates();

				if (timerUpdates.length > 0) {
					// 2. Парсер преобразует в DOM команды
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
				const stateStats = this.stateManager.getStats();

				log(`Server v4.0 Statistics:`);
				log(`  Uptime: ${uptime}s`);
				log(`  Clients connected: ${this.stats.clientsConnected}`);
				log(`  Clients disconnected: ${this.stats.clientsDisconnected}`);
				log(`  Messages sent: ${this.stats.messagesSent}`);
				log(`  Events processed: ${this.stats.eventsProcessed}`);
				log(`  Commands generated: ${this.stats.commandsGenerated}`);
				log(`  Active timers: ${stateStats.activeTimers}`);
				log(`  Errors: ${this.stats.errors}`);

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
		const stateStats = this.stateManager.getStats();

		return {
			version: this.config.version,
			uptime: uptime,
			clients: this.clients.size,
			monitoring: this.isMonitoring,
			durationTimerActive: !!this.durationTimer,
			serverStats: this.stats,
			stateStats: stateStats
		};
	}
}

// @bookmark ТОЧКА ВХОДА
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

	// Запуск сервера v4.0
	const server = new StatefulWebSocketServerV4();
	global.serverInstance = server;

	server.start();
}

// Запуск если файл исполняется напрямую
if (require.main === module) {
	main();
}

// @bookmark Экспорт для тестирования
module.exports = {
	StatefulWebSocketServerV4,
	StateManager,
	CommandParser,
	ModuleLogicHandler
};