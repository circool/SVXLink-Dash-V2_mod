/**
 * @filesource /scripts/exct/dashboard_ws_server.4.0.js
 * @version 4.0
 * @date 2026.01.09
 * @description Stateful WebSocket сервер для SvxLink Dashboard с новой системой команд DOM
 */

const WebSocket = require('ws');
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

// ==================== КОНСТАНТЫ И КОНФИГУРАЦИЯ ====================

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

// ==================== СЕРВИС ЛОГИРОВАНИЯ ====================

function getTimestamp() {
	const now = new Date();
	return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;
}

function log(message, level = 'INFO') {
	const timestamp = getTimestamp();
	const logMessage = `[${timestamp}] [${level}] ${message}`;
	console.log(logMessage);
}

// ==================== ПАРСЕР (формирует команды напрямую) ====================

class CommandParser {
	constructor() {
		this.patterns = [
			// Transmitter ON/OFF
			{
				regex: /^(.+?): (\w+): Turning the transmitter (ON|OFF)$/,
				handler: (match) => {
					const device = match[2];
					const state = match[3];

					if (state === 'ON') {
						
						const deviceKey = `${device}_TX`;
						// this.commandManager.activeDevices.set(deviceKey, {
						// 	// startTime: Date.now(),
						// 	// lastUpdate: Date.now(),
						// 	deviceType: 'TX'
						// });

						return [
							{ id: `${device}_StatusTX`, action: 'set_content', payload: 'TRANSMIT ( 0 )' },
							{ id: `${device}_StatusTX`, action: 'add_class', class: 'inactive-mode-cell' },
							// { id: `${device}_StatusTX`, action: 'add_parent_class', class: 'active-mode' }
						];
					} else {
						return [
							{ id: `${device}_StatusTX`, action: 'set_content', payload: 'STANDBY' },
							{ id: `${device}_StatusTX`, action: 'remove_class', class: 'inactive-mode-cell' },
							// { id: `${device}_StatusTX`, action: 'remove_parent_class', class: 'active-mode' }
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
						return [
							{ id: `${device}_StatusRX`, action: 'set_content', payload: 'RECEIVE ( 0 )' },
							{ id: `${device}_StatusRX`, action: 'add_class', class: 'active-mode-cell' },
						];
					} else {
						return [
							{ id: `${device}_StatusRX`, action: 'set_content', payload: 'STANDBY' },
							{ id: `${device}_StatusRX`, action: 'remove_class', class: 'active-mode-cell' },
						];
					}
				}
			},
			// Talker start
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
			// Talker stop
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
			// Selecting TG #0
			{
				regex: /^(.+?): (\S+): Selecting TG #0/,
				handler: (match) => {
					const device = match[2];

					return [

						{ id: `logic_${device}_GroupsTableBody`, class: 'disabled-mode-cell', operation: 'replace_class', action: 'handle_element_classes', oldClass: 'active-mode-cell' },
						{ id: `logic_${device}_GroupsTableBody`, class: 'default,active-mode-cell', operation: 'replace_class', action: 'handle_element_classes', oldClass: 'default' },
						{ id: `logic_${device}_GroupsTableBody`, class: 'default', operation: 'replace_class', action: 'handle_element_classes', oldClass: 'default,disabled-mode-cell' },
					];
				}
			},
			// Selecting TG #
			{
				regex: /^(.+?): (\S+): Selecting TG #(\d+)/,
				handler: (match) => {
					const device = match[2];
					const talkgroup = match[3];
					if (talkgroup === "0") {
						return [];
					}
					return [
						{ id: `logic_${device}_GroupsTableBody`, class: 'disabled-mode-cell', operation: 'replace_class', action: 'handle_element_classes', oldClass: 'active-mode-cell', },
						{ id: `logic_${device}_Group_${talkgroup}`, action: 'remove_class', class: 'disabled-mode-cell' },
						{ id: `logic_${device}_Group_${talkgroup}`, action: 'add_class', class: 'active-mode-cell' },
						
					];
				}
			},
			
			// Add temporary monitor for TG #
			{
				regex: /^(.+?): (\S+): Add temporary monitor for TG #(\d+)/,
				handler: (match) => {
					const device = match[2];
					const talkgroup = match[3];
					
					if (talkgroup === "0") {
						return []; 
					}
					return [
						//{ id: `logic_${device}_GroupsTableBody`, class: 'disabled-mode-cell', operation: 'replace_class', action: 'handle_element_classes', oldClass: 'paused-mode-cell', },
						{ id: `logic_${device}_Group_${talkgroup}`, action: 'remove_class', class: 'disabled-mode-cell' },
						{ id: `logic_${device}_Group_${talkgroup}`, action: 'add_class', class: 'paused-mode-cell' },

					];
				}
			},

			// Refresh temporary monitor for TG #
			{
				regex: /^(.+?): (\S+): Refresh temporary monitor for TG #(\d+)/,
				handler: (match) => {
					const device = match[2];
					const talkgroup = match[3];
					return [
						{ id: `logic_${device}_Group_${talkgroup}`, action: 'remove_class', class: 'disabled-mode-cell' },
						{ id: `logic_${device}_Group_${talkgroup}`, action: 'remove_class', class: 'active-mode-cell' },
						{ id: `logic_${device}_Group_${talkgroup}`, action: 'add_class', class: 'paused-mode-cell' },

					];
				}
			},	
			// Temporary monitor timeout for TG #
			{
				regex: /^(.+?): (\S+): Temporary monitor timeout for TG #(\d+)/,
				handler: (match) => {
					const device = match[2];
					const talkgroup = match[3];
					return [
						{ id: `logic_${device}_Group_${talkgroup}`, action: 'remove_class', class: 'paused-mode-cell' },
						{ id: `logic_${device}_Group_${talkgroup}`, action: 'add_class', class: 'disabled-mode-cell' },

					];
				}
			},


			// Активация модуля
			{
				regex: /^(.+?): (\S+): Activating module (\S+)\.\.\.$/,
				handler: (match) => {
					const logic = match[2];
					const module = match[3];

					return [
						{ id: `module_${logic}${module}`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell,disabled-mode-cell' },
						{ id: `module_${logic}${module}`, action: 'add_class', class: 'paused-mode-cell' },
						
					];
				}
			},
			// Деактивация модуля
			{
				regex: /^(.+?): (\S+): Deactivating module (\S+)\.\.\.$/,
				handler: (match) => {
					const logic = match[2];
					const module = match[3];

					return [
						{ id: `module_${logic}${module}`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell' },
						{ id: `module_${logic}${module}`, action: 'add_class', class: 'disabled-mode-cell' },
						{ id: `module_${logic}_Status`, action: 'add_class', class: 'hidden' },
						{ id: `module_${logic}_Status`, action: 'remove_parent_class', class: 'module-active' },
						{ id: `module_${logic}_Status_Header`, action: 'set_content', payload: '' },
						{ id: `module_${logic}_Status_Content`, action: 'set_content', payload: '' }
					];
				}
			},
			// EchoLink: QSO state changed to CONNECTED @todo Придумать как разбирать логику
			{
				regex: /^(.+?): (\S+): EchoLink QSO state changed to (CONNECTED)$/,
				handler: (match) => {
					const node = match[2];
					const logic = 'SimplexLogic';
					return [
						{ id: `module_${logic}EchoLink`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell,disabled-mode-cell' },
						{ id: `module_${logic}EchoLink`, action: 'add_class', class: 'active-mode-cell' },
						{ id: `module_${logic}_Status`, action: 'remove_class', class: 'hidden' },
						{ id: `module_${logic}_Status`, action: 'add_parent_class', class: 'module-connected' },
						{ id: `module_${logic}_Status_Header`, action: 'set_content', payload: 'EchoLink' },
						{ id: `module_${logic}_Status_Content`, action: 'set_content', payload: `<a class="tooltip" href="#"><span><b>Uptime:</b>00:00:00<br>Connected ${node}</span>${node}</a>` }
					];
				}
			},
			// EchoLink: QSO state changed to DISCONNECTED
			{
				regex: /^(.+?): (\S+): EchoLink QSO state changed to (DISCONNECTED)$/,
				handler: (match) => {
					const logic = 'SimplexLogic';
					return [
						{ id: `module_${logic}EchoLink`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,disabled-mode-cell' },
						{ id: `module_${logic}EchoLink`, action: 'add_class', class: 'paused-mode-cell' },
						{ id: `module_${logic}_Status`, action: 'add_class', class: 'hidden' },
						{ id: `module_${logic}_Status`, action: 'remove_parent_class', class: 'module-connected' },
						{ id: `module_${logic}_Status_Header`, action: 'set_content', payload: '' },
						{ id: `module_${logic}_Status_Content`, action: 'set_content', payload: '' }
					];
				}
			},
			// Frn: login stage 2 completed
			{
				regex: /^(.+?): login stage 2 completed: (.+)$/,
				handler: (match) => {
					let serverName = 'Frn Server';
					const logic = 'SimplexLogic';
					const xml = match[2];
					const bnMatch = xml.match(/<BN>(.*?)<\/BN>/);
					if (bnMatch && bnMatch[1]) {
						serverName = bnMatch[1];
					}

					return [
						{ id: `module_${logic}Frn`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell,disabled-mode-cell' },
						{ id: `module_${logic}Frn`, action: 'add_class', class: 'active-mode-cell' },
						{ id: `module_${logic}_Status`, action: 'remove_class', class: 'hidden' },
						{ id: `module_${logic}_Status`, action: 'add_parent_class', class: 'module-connected' },
						{ id: `module_${logic}_Status_Header`, action: 'set_content', payload: 'Frn' },
						{ id: `module_${logic}_Status_Content`, action: 'set_content', payload: `<a class="tooltip" href="#"><span><b>Uptime:</b>00:00:00<br>Server ${serverName}</span>${serverName}</a>` }
					];
				}
			},
			// Активация линка
			{
				regex: /^(.+?): Activating link (\S+)$/,
				handler: (match) => {
					const link = match[2];

					return [
						{ id: `link_${ link }`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell,disabled-mode-cell' },
						{ id: `link_${ link }`, action: 'add_class', class: 'active-mode-cell' },
						{ id: `link_${ link }`, action: 'add_parent_class', class: 'link-active' },
						{ id: `toggle-link-state-${ link }`, action: "set_checkbox_state", state: "on"}
						
					];
				}
			},
			// Деактивация линка
			{
				regex: /^(.+?): Deactivating link (\S+)$/,
				handler: (match) => {
					const link = match[2];

					return [
						{ id: `link_${ link }`, action: 'remove_class', class: 'active-mode-cell,paused-mode-cell,disabled-mode-cell' },
						{ id: `link_${ link }`, action: 'add_class', class: 'inactive-mode-cell' },
						{ id: `link_${ link }`, action: 'remove_parent_class', class: 'link-active' },
						{ id: `toggle-link-state-${link}`, action: "set_checkbox_state", state: "off" }
					];
				}
			},
		];
	}

	parse(line) {
		const trimmed = line.trim();
		if (!trimmed) return null;

		for (const pattern of this.patterns) {
			const match = trimmed.match(pattern.regex);
			if (match) {
				const commands = pattern.handler(match);
				return {
					commands: commands,
					raw: match[0],
					timestamp: match[1]
				};
			}
		}

		return null;
	}
}

// ==================== МЕНЕДЖЕР КОМАНД ====================

class CommandManager {
	constructor() {
		this.parser = new CommandParser();

		// Храним состояния для обновления времени
		this.activeStates = {
			links: new Map(),       // link -> {startTime, lastUpdate}
			talkgroups: new Map(),  // device -> {startTime, lastUpdate, callsign}
			modules: new Map()      // logic_module -> {startTime, lastUpdate, nodeInfo}
		};
		
		this.activeDevices = new Map(); // device -> {state, startTime, type, lastDurationUpdate}
		
		// Статистика
		this.stats = {
			commandsGenerated: 0,
			eventsProcessed: 0
		};

		
	}

	// ==================== ФОРМАТИРОВАНИЕ ВРЕМЕНИ ====================

	formatDuration(milliseconds) {
		const totalSeconds = Math.floor(milliseconds / 1000);
		const hours = Math.floor(totalSeconds / 3600).toString().padStart(2, '0');
		const minutes = Math.floor((totalSeconds % 3600) / 60).toString().padStart(2, '0');
		const seconds = (totalSeconds % 60).toString().padStart(2, '0');
		return `${hours}:${minutes}:${seconds}`;
	}


	// ==================== ОБРАБОТКА СОБЫТИЙ ====================

	processEvent(line) {
		const result = this.parser.parse(line);
		if (!result) return null;

		this.stats.eventsProcessed++;
		this.stats.commandsGenerated += result.commands.length;

		return {
			type: 'dom_commands',
			commands: result.commands,
			raw: result.raw,
			timestamp: Date.now()
		};
	}

	// ==================== ОБНОВЛЕНИЕ ВРЕМЕНИ ====================

	getTimeUpdateCommands() {
		const now = Date.now();
		const commands = [];

		// Обновление времени для линков
		for (const [linkId, linkState] of this.activeStates.links) {
			if (now - linkState.lastUpdate >= 1000) {
				const uptime = now - linkState.startTime;
				const formatted = this.formatDuration(uptime);

				commands.push({
					id: linkId,
					action: 'replace_content',
					payload: ["</b>", "</span>", formatted]
				});

				linkState.lastUpdate = now;
			}
		}
		
		// Обновление времени для устройств
		for (const [deviceId, deviceState] of this.activeDevices) {
			if (now - deviceState.lastUpdate >= 1000) {
				const uptime = now - deviceState.startTime;
				const formatted = this.formatDuration(uptime);

				// Определяем, что обновлять (TX или RX)
				const isTx = deviceId.includes('_tx') || deviceId.includes('_TX');
				const statusId = isTx ? `${device}_StatusTX` : `${device}_StatusRX`;
				const baseStatus = isTx ? 'TRANSMIT' : 'RECEIVE';

				commands.push({
					id: statusId,
					action: 'set_content',
					payload: `${baseStatus} ( ${formatted} )`
				});

				deviceState.lastUpdate = now;
			}
		}

		return commands;
	}

	// ==================== СТАТИСТИКА ====================

	getStats() {
		return {
			...this.stats,
			activeLinks: this.activeStates.links.size,
			activeTalkgroups: this.activeStates.talkgroups.size,
			activeModules: this.activeStates.modules.size
		};
	}
}

// ==================== ОСНОВНОЙ КЛАСС СЕРВЕРА ====================

class StatefulWebSocketServerV4 {
	constructor(config = {}) {
		this.config = { ...CONFIG, ...config };
		this.wss = null;
		this.commandManager = new CommandManager();
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
			errors: 0
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
			const result = this.commandManager.processEvent(line);

			if (result && result.commands && result.commands.length > 0) {
				allCommands.push(...result.commands);

				// Выводим подробную информацию о командах
				log(`Processed event: ${result.commands.length} commands generated`);

				// Выводим содержимое каждой команды
				result.commands.forEach((cmd, index) => {
					const cmdInfo = `  [${index + 1}] ${cmd.id} -> ${cmd.action}`;
					const details = [];

					if (cmd.class) details.push(`class: ${cmd.class}`);
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
				const timeCommands = this.commandManager.getTimeUpdateCommands();

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
				const cmdStats = this.commandManager.getStats();

				log(`Server v4.0 Statistics:`);
				log(`  Uptime: ${uptime}s`);
				log(`  Clients connected: ${this.stats.clientsConnected}`);
				log(`  Clients disconnected: ${this.stats.clientsDisconnected}`);
				log(`  Messages sent: ${this.stats.messagesSent}`);
				log(`  Events processed: ${cmdStats.eventsProcessed}`);
				log(`  Commands generated: ${cmdStats.commandsGenerated}`);
				log(`  Active links: ${cmdStats.activeLinks}`);
				log(`  Active talkgroups: ${cmdStats.activeTalkgroups}`);
				log(`  Active modules: ${cmdStats.activeModules}`);
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
		const cmdStats = this.commandManager.getStats();

		return {
			version: this.config.version,
			uptime: uptime,
			clients: this.clients.size,
			monitoring: this.isMonitoring,
			durationTimerActive: !!this.durationTimer,
			serverStats: this.stats,
			commandStats: cmdStats
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

	// Запуск сервера v4.0
	const server = new StatefulWebSocketServerV4();
	global.serverInstance = server;

	server.start();
}

// Запуск если файл исполняется напрямую
if (require.main === module) {
	main();
}

// Экспорт для тестирования
module.exports = {
	StatefulWebSocketServerV4,
	CommandParser,
	CommandManager
};