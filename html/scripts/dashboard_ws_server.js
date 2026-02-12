/**
 * @filesource /scripts/dashboard_ws_server.js
 * @version 0.4.23
 * @date 2026.02.11
 * @description Stateful WebSocket server
 */

const WebSocket = require('ws');
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const http = require('http');
const { match } = require('assert');

const CONFIG = {
	version: '0.4.23',
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


function getTimestamp() {
	const now = new Date();
	return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;
}

function log(message, level = 'DEBUG') {
	const logLevels = {
		'ERROR': 1,
		'WARNING': 2,
		'INFO': 3,
		'DEBUG': 4
	};

	const currentDebugLevel = parseInt(process.env.DEBUG_LEVEL) || 4;
	const messageLevel = logLevels[level] || 4;

	if (messageLevel <= currentDebugLevel) {
		const timestamp = getTimestamp();
		const logMessage = `[${timestamp}] [${level}] ${message}`;
		console.log(logMessage);

		if (global.serverInstance) {
			global.serverInstance.broadcast({
				type: 'log_message',
				level: level,
				message: message,
				timestamp: new Date().toISOString(),
				source: 'WS Server v 0.4.23'
			});
		}
	}
}


function getTimerTooltip(callsign, uptime = "0 s") {
	const formattedUptime = uptime || "0 s";
	return `<a class="tooltip" href="#"><span><b>Uptime:</b>${formattedUptime}<br></span>${callsign}</a>`;
}



class StateManager {
	constructor() {
		this.timers = new Map(); // key -> {startTime, lastUpdate, metadata}
	}

	formatDuration(milliseconds) {
		const totalSeconds = Math.floor(milliseconds / 1000);

		if (totalSeconds < 60) {
			return `${totalSeconds} s`;
		}

		if (totalSeconds < 3600) {
			const minutes = Math.floor(totalSeconds / 60).toString().padStart(2, '0');
			const seconds = (totalSeconds % 60).toString().padStart(2, '0');
			return `${minutes}:${seconds}`;
		}

		const hours = Math.floor(totalSeconds / 3600);
		const minutes = Math.floor((totalSeconds % 3600) / 60).toString().padStart(2, '0');
		const seconds = (totalSeconds % 60).toString().padStart(2, '0');
		return `${hours}:${minutes}:${seconds}`;
	}


	startTimer(key, metadata = {}) {
		const now = Date.now();

		for (const [existingKey, existingTimer] of this.timers) {
			if (existingTimer.metadata.elementId === metadata.elementId && existingKey !== key) {
				this.timers.delete(existingKey);
			}
		}

		this.timers.set(key, {
			startTime: now,
			lastUpdate: now,
			metadata: metadata
		});
		
		return now;
	}

	stopTimer(key) {

		if (this.timers.has(key)) {
			this.timers.delete(key);
			const stoppedTimers = [];

			for (const [timerKey, timer] of this.timers.entries()) {
				if (timerKey.startsWith(key)) {
					this.timers.delete(timerKey);
					stoppedTimers.push(timerKey);
				}
			}

			return true;
		}

		return false;
	}


	setTimerStart(key, startTimestamp) {
		const timer = this.timers.get(key);
		if (timer) {
			timer.startTime = startTimestamp;
			timer.lastUpdate = Date.now();
			return true;
		}
		return false;
	}

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


	getStats() {
		return {
			activeTimers: this.timers.size
		};
	}
}


class ConnectionHandler {
	constructor() {
		this.connections = new Map(); // key -> Set(relatedKeys)
	}

	add(source, target) {
		if (!this.connections.has(source)) {
			this.connections.set(source, new Set());
		}
		this.connections.get(source).add(target);		
		// log(`Add connection with ${source} and ${target}`, `INFO`);
	}

	remove(source, target) {
		const targets = this.connections.get(source);
		if (targets) {
			targets.delete(target);
			if (targets.size === 0) {
				this.connections.delete(source);
			}
		}
	}

	getAllFrom(source) {
		const targets = this.connections.get(source);
		return targets ? Array.from(targets) : [];
	}

	getAllTo(target) {
		const sources = [];
		for (const [source, targets] of this.connections.entries()) {
			if (targets.has(target)) {
				sources.push(source);
			}
		}
		return sources;
	}


	initFromData(data, sourceKey, targetKey) {
		for (const [source, targets] of Object.entries(data || {})) {
			for (const target of targets) {
				if (sourceKey === 'device' && targetKey === 'subdevice') {
					continue; 
				}
				this.add(source, target);
			}
		}
	}
}

//@bookmark class CommandParser
class CommandParser {
	constructor(server) {
		this.server = server;
		this.sm = server.stateManager;
		this.compositeDeviceManager = server.compositeDeviceManager;

		this.connections = new ConnectionHandler();
		this.isPacketMessageMode = false; 
		this.packetActive = false;
		this.packetType = null; // 'EchoLink' | 'Frn'
		this.packetBuffer = [];
		this.packetStartTime = null;
		this.packetMetadata = {};
		this.patterns = [
			// @bookmark Start service
			// [timestamp]: [SvxLink] v1.8.0@24.02-3-gcde00792 Copyright (C) 2003-2023 Tobias Blomberg / SM0SVX
			{
				regex: /^(.+?): (\S+) (.+?) Copyright \(C\) .+ Tobias Blomberg \/ SM0SVX$/,
				handler: (match) => {
					this.sm.startTimer('service', {
						elementId: 'service',
						replaceStart: ':</b>',
						replaceEnd: '<br>',
					});

					return [
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
					const commands = [];
					commands.push(
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
							id: 'aprs_status',
							action: 'remove_class',
							class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell'
						},
						{
							id: 'aprs_status',
							action: 'add_class',
							class: 'disabled-mode-cell'
						},
						//proxy_server
						{
							id: 'proxy_server_status',
							action: 'remove_class',
							class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell'
						},
						{
							id: 'proxy_server_status',
							action: 'add_class',
							class: 'disabled-mode-cell'
						},
						{
							id: 'proxy_server',
							action: 'add_class',
							class: 'hidden'
						},
						//directory_server
						{
							id: 'directory_server_status',
							action: 'remove_class',
							class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell'
						},
						{
							id: 'directory_server_status',
							action: 'add_class',
							class: 'disabled-mode-cell'
						},
						
					);
					// stop all timers and clear all known content
					for (const timerKey of this.sm.timers.keys()) {
						this.sm.stopTimer(timerKey);
						commands.push(
							{
								id: timerKey,
								action: 'remove_class',
								class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell'
							},
							{
								id: timerKey,
								action: 'add_class',
								class: 'disabled-mode-cell'
							},
							{
								id: `${timerKey}_nodes_header`,
								action: 'set_content',
								payload: 'Nodes'
							},
							{
								id: `${timerKey}_nodes`,
								action: 'set_content',
								payload: ''
							},
						);
					}

					return commands;
				}
			},

			// [timestamp]: Starting logic: [...Logic]
			{
				regex: /^(.+?): Starting logic: (\S+)$/,
				handler: (match) => {
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

			// [timestamp]: ...Logic: Loading RX "..."
			{
				regex: /^(.+?): (\S+): Loading (RX|TX) "(\S+)"$/,
				handler: (match) => {
					const logic = match[2];
					const deviceType = match[3];  
					const deviceName = match[4];  

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
					this.connections.add(module, logic);

					return [];
				}
			},
			// @bookmark Transmitter
			// Transmitter [ON/OFF]
			{
				regex: /^(.+?): (\w+): Turning the transmitter (ON|OFF)$/,
				handler: (match) => {
					const device = match[2];
					const state = match[3];

					const commands = [];

					// is composite device?
					const components = this.compositeDeviceManager.getComponents(device);
					let deviceContent;

					if (components && components.length > 0) {
						const componentsList = components.join(', ');
						deviceContent = `<a class="tooltip" href="#"><span><b>Multiple device:</b>${componentsList}</span>${device}</a>`;
					} else {
						deviceContent = device;
					}

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
							{ id: `device_${device}_tx`, action: 'set_content', payload: deviceContent },
						];
					} else {					
						this.sm.stopTimer(`device_${device}`);			
						const allLogics = this.connections.getAllFrom(device);
						for (const logic of allLogics) {							
							commands.push(
								{ id: `radio_logic_${logic}_callsign`, action: 'set_content', payload: '' },
							);

						}
						commands.push(
							{ id: `device_${device}_tx_status`, action: 'set_content', payload: 'STANDBY' },
							{ id: `device_${device}_tx_status`, action: 'remove_class', class: 'inactive-mode-cell' },
							{ id: `device_${device}_tx`, action: 'set_content', payload: deviceContent },
						);
						return commands;
					}
				}
			},
			
			// @bookmark ERRORS
			// *** ERROR: Transmitter ... have been active for too long. Turning it off...
			{
				regex: /^(.+?): \*\*\* ERROR: Transmitter (\S+) have been active for too long\. Turning it off\.\.\.$/,
				
				handler: (match) => {
					
					const device = match[2];
					const hasTimer = this.sm.timers.has(`device_${device}`);
					if (hasTimer) {						
						this.sm.stopTimer(`device_${device}`);	
						return [
							{ id: `device_${device}_tx_status`, action: 'set_content', payload: 'STANDBY' },
							{ id: `device_${device}_tx_status`, action: 'remove_class', class: 'inactive-mode-cell' },
							{ id: `device_${device}_tx`, action: 'set_content', payload: `<a class="tooltip" href="#"><span><b>Timeout</b></span><em class="error">${device}</em></a>` },
						];
					
					} else {
						
						const relatedDevices = this.compositeDeviceManager.getComponents(device);
						const commands = [];
						for (const relatedDevice of relatedDevices) { 
							if (this.sm.timers.has(`device_${relatedDevice}`)) {
								this.sm.stopTimer(`device_${relatedDevice}`);
								commands.push(
									{ id: `device_${relatedDevice}_tx_status`, action: 'set_content', payload: 'STANDBY' },
									{ id: `device_${relatedDevice}_tx_status`, action: 'remove_class', class: 'inactive-mode-cell' },								
									{
										id: `device_${relatedDevice}_tx`,
										action: 'replace_content',
										payload: [
											'</span>',
											'</a>',
											`${relatedDevice} <em class="error">${device}</em>`
										]										
									},
								);
							}							
						}						
						return commands;
					}					
				}
			},
			// *** ERROR: Could not open audio device for transmitter "..."
			{
				regex: /^(.+?): \*\*\* ERROR: Could not open audio device for transmitter "(\S+)"$/,

				handler: (match) => {

					const device = match[2];
					const hasTimer = this.sm.timers.has(`device_${device}`);
					if (hasTimer) {
						this.sm.stopTimer(`device_${device}`);
						return [
							{ id: `device_${device}_tx_status`, action: 'set_content', payload: 'STANDBY' },
							{ id: `device_${device}_tx_status`, action: 'remove_class', class: 'inactive-mode-cell' },
							{ id: `device_${device}_tx`, action: 'set_content', payload: `<a class="tooltip" href="#"><span><b>ERROR</b></span><em class="error">${device}</em></a>` },
						];

					} else {

						this.compositeDeviceManager.getComponents(device);
						const commands = [];
						for (const relatedDevice of relatedDevices) {
							if (this.sm.timers.has(`device_${relatedDevice}`)) {
								this.sm.stopTimer(`device_${relatedDevice}`);
								commands.push(
									{ id: `device_${relatedDevice}_tx_status`, action: 'set_content', payload: 'STANDBY' },
									{ id: `device_${relatedDevice}_tx_status`, action: 'remove_class', class: 'inactive-mode-cell' },
									{
										id: `device_${relatedDevice}_tx`,
										action: 'replace_content',
										payload: [
											'</span>',
											'</a>',
											`${relatedDevice} <em class="error">${device}</em>`
										]
									},
								);
							}
						}
						return commands;
					}
				}
			},
			
			// @bookmark Receiver
			// Squelch OPEN/CLOSED
			{
				regex: /^(.+?): (\w+): The squelch is (OPEN|CLOSED).*/,
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
							{ id: `device_${device}_rx`, action: 'set_content', payload: device },
							{ id: `device_${device}_rx_status`, action: 'set_content', payload: 'RECEIVE ( 0 s )' },
							{ id: `device_${device}_rx_status`, action: 'add_class', class: 'active-mode-cell' },
						];
					} else {
						this.sm.stopTimer(`device_${device}`);
						return [
							{ id: `device_${device}_rx_status`, action: 'set_content', payload: 'STANDBY' },
							{ id: `device_${device}_rx_status`, action: 'remove_class', class: 'active-mode-cell' },
							// peak meter off
							{ id: `device_${device}_rx`, action: 'remove_class', class: 'inactive-mode-cell' },
							{ id: `device_${device}_rx`, action: 'set_content', payload: device },
						];
					}
				}
			},
			// PEAK METER
			{
				regex: /^(.+?): (\w+): Distortion detected/,
				handler: (match) => {
					const device = match[2];					
						return [
							{ id: `device_${device}_rx`, action: 'set_content', payload: `${device} <span class="error">Distortion!</span>` },						
						];					
				}
			},

			// @bookmark Reflector
			// [timestamp]: ReflectorLogic: Disconnected from 255.255.255.255:5300: Host not found
			{
				regex: /^(.+?): (\S+): Disconnected/,
				handler: (match) => {
					const logic = match[2];
					this.sm.stopTimer(`logic_${logic}`);
					const nodes = this.connections.getAllTo(logic);
					for (let i = 0; i < nodes.length; i++) {
						const node = nodes[i];
						this.connections.remove(node, logic);
						this.sm.stopTimer(`logic_${logic}_node_${node}`);
					}

					return [
						{ id: `logic_${logic}`, action: 'remove_class', class: 'active-mode-cell,paused-mode-cell,disabled-mode-cell' },
						{ id: `logic_${logic}`, action: 'add_class', class: 'inactive-mode-cell' },
						{ id: `logic_${logic}_nodes_header`, action: 'replace_content', payload: ['[', ']', ''] },
						{ id: `logic_${logic}_nodes`, action: 'remove_child' },
						{ id: `logic_${logic}_groups`, action: 'remove_child', ignoreClass: 'monitored,default' },
					];
				}
			},
			// "...: ReflectorLogic: Authentication OK"
			{
				regex: /^(.+?): (\S+): Authentication OK$/,
				handler: (match) => {
					const logic = match[2];
					this.sm.startTimer(`logic_${logic}`, {
						elementId: `logic_${logic}`,
						replaceStart: ':</b>',
						replaceEnd: '<br>',
						logic: logic
					});
					const linksForLogic = this.connections.getAllFrom(logic);
					let logicCellClass = 'paused-mode-cell';
					for (const link of linksForLogic) {
						if (this.sm.timers.has(`link_${link}`)) {
							logicCellClass = 'active-mode-cell';
							break;
						}
					}

					return[
						{
							id: `logic_${logic}`,
							action: 'remove_class',
							class: 'paused-mode-cell,inactive-mode-cell,disabled-mode-cell,active-mode-cell'
						},
						{
							id: `logic_${logic}`,
							action: 'add_class',
							class: logicCellClass
						}
					];
				}
			},
			// "...: ReflectorLogic...: Connected nodes: ..."
			{
				regex: /^(.+?): (\S+): Connected nodes: (.+)$/,
				handler: (match) => {
					const logic = match[2];
					const nodesString = match[3];
					const nodes = nodesString.split(',').map(node => node.trim());
					const commands = [];
					// Clear reflector's nodes
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
					// Fill reflector's nodes
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
					const logicCallsign = this.server.wsState.logics?.[`logic_${logic}`]?.callsign || '';
					const statusStyle = callsign === logicCallsign ? 'inactive-mode-cell' : 'active-mode-cell';
					const activeCell = callsign === logicCallsign ? `device_${logic}_tx_status` : `device_${logic}_rx_status`;
					this.sm.startTimer(activeCell, {
						elementId: activeCell,
						replaceStart: '( ',
						replaceEnd: ' )',
						type: 'device'
					});
					return [
						{ id: `radio_logic_${logic}_callsign`, action: 'set_content', payload: callsign },
						{ id: `radio_logic_${logic}_destination`, action: 'replace_content', payload: [`<span class="tg">`,`</span>`, `${talkgroup}`]  },
						{ id: activeCell, action: 'remove_class', class: 'transparent' },
						{ id: activeCell, action: 'add_class', class: statusStyle },
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
					const callsign = match[4];
					const logicCallsign = this.server.wsState.logics?.[`logic_${logic}`]?.callsign || '';
					const statusStyle = callsign === logicCallsign ? 'inactive-mode-cell' : 'active-mode-cell';
					const activeCell = callsign === logicCallsign ? `device_${logic}_tx_status` : `device_${logic}_rx_status`;
					this.sm.stopTimer(activeCell);
					return [
						{ id: `radio_logic_${logic}_callsign`, action: 'set_content', payload: '' },
						{ id: activeCell, action: 'remove_class', class: statusStyle },
						{ id: activeCell, action: 'add_class', class: 'transparent' },
						{ id: activeCell, action: 'replace_content', payload: ['(',')',' 0 '] },
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
						{
							id: `logic_${logic}_groups`,
							action: 'remove_child',
							ignoreClass: 'default,monitored'
						},
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
						{
							id: `logic_${logic}_groups`,
							action: 'remove_child',
							ignoreClass: 'default,monitored',
						},
						{
							id: `logic_${logic}_groups`,
							action: 'replace_child_classes',
							oldClass: 'active-mode-cell',
							class: 'disabled-mode-cell',
						},
						{
							action: "add_child",
							target: `logic_${logic}_groups`,
							id: `logic_${logic}_group_${group}`,
							class: 'mode_flex column',
							style: 'border: .5px solid #3c3f47;',
							title: group,
							payload: group,
						},
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
						{ id: `logic_${logic}_group_${group}`, action: 'remove_element', },
					];
				}
			},

			// @bookmark Module

			// Activating module
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
					const commands = [];
					const allLogicsForModule = this.connections.getAllFrom(module);
					let hasActiveLinks = false;
					const muteLogic = this.server.wsState.modules?.[`logic_${logic}_module_${module}`]?.mute_logic || false;
					for (const relatedLogic of allLogicsForModule) {
						commands.push(
							{
								id: `logic_${relatedLogic}`,
								action: 'remove_class',
								class: 'disabled-mode-cell,paused-mode-cell,inactive-mode-cell,active-mode-cell'
							},
							{
								id: `logic_${relatedLogic}`,
								action: 'add_class',
								class: 'active-mode-cell'
							},
						)
						const linksForLogic = this.connections.getAllFrom(relatedLogic)
						for (const link of linksForLogic) {							
							if (this.sm.timers.has(`link_${link}`)) {
								hasActiveLinks = true;
								const allLogicsForLink = this.connections.getAllTo(link);
								for (const reflector of allLogicsForLink) {
									if (!allLogicsForModule.includes(reflector) && muteLogic) {
										commands.push(
										{ id: `logic_${reflector}`, action: 'remove_class', class: 'disabled-mode-cell,paused-mode-cell,inactive-mode-cell,active-mode-cell'},
										{ id: `logic_${reflector}`, action: 'add_class', class: 'paused-mode-cell' },
										)
									}
								}
							}
						}
						if (hasActiveLinks) break;
					}

					let newclass;
					if (module === 'EchoLink' || module === 'Frn') {
						newclass = 'paused-mode-cell';
					} else {
						newclass = 'active-mode-cell';
					}

					commands.push(
						// Logic on
						{ id: `logic_${logic}`, action: 'remove_class', class: 'disabled-mode-cell,inactive-mode-cell,active-mode-cell' },
						{ id: `logic_${logic}`, action: 'add_class', class: 'active-mode-cell' },
						// Module
						{ id: `logic_${logic}_module_${module}`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell,disabled-mode-cell'},
						{ id: `logic_${logic}_module_${module}`, action: 'add_class', class: newclass },
						// Tooltip with DTMF commands						
						{
							id: `logic_${logic}_module_${module}`,
							action: 'replace_content',
							payload: [`sendLinkCommand('`, `#'`, ''],
						},
						{
							id: `radio_logic_${logic}_destination`,
							action: 'set_content',
							payload: module
						},
					);
					return commands;
				}
			},

			// Deactivating module
			{
				regex: /^(.+?): (\S+): Deactivating module (\S+)\.\.\.$/,
				handler: (match) => {
					const logic = match[2];
					const module = match[3];
					const activateCmd = this.server.wsState.modules?.[`logic_${logic}_module_${module}`]?.id || '';
					const muteLogic = this.server.wsState.modules?.[`logic_${logic}_module_${module}`]?.mute_logic || false;
					this.sm.stopTimer(`logic_${logic}_module_${module}`);
					this.sm.stopTimer(`logic_${logic}_active_content`);
					const commands = [];
					let hasActiveLinks = false;
					const linksForLogic = this.connections.getAllFrom(logic)
					for (const link of linksForLogic) {
						if (this.sm.timers.has(`link_${link}`)) {
							hasActiveLinks = true;
							const allLogicsForLink = this.connections.getAllTo(link);
							for (const reflector of allLogicsForLink) {
								// Logic's reflector
								commands.push(
									{ id: `logic_${reflector}`, action: 'remove_class', class: 'disabled-mode-cell,paused-mode-cell,inactive-mode-cell,active-mode-cell' },
									{ id: `logic_${reflector}`, action: 'add_class', class: 'active-mode-cell' },
								)
							}
						}
					}

					let cellClass = 'active-mode-cell';
					if (!hasActiveLinks) { 
						cellClass = 'paused-mode-cell';	
					}
					// Module parent logic
					commands.push(
						{ id: `logic_${logic}`, action: 'remove_class', class: 'disabled-mode-cell,paused-mode-cell,inactive-mode-cell,active-mode-cell' },
						{ id: `logic_${logic}`, action: 'add_class', class: cellClass },
					);
					
					// Module
					commands.push(	
						{ id: `logic_${logic}_module_${module}`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell' },
						{ id: `logic_${logic}_module_${module}`, action: 'add_class', class: 'disabled-mode-cell' },						
						{ id: `logic_${logic}_module_${module}`, action: 'replace_content', payload: [`sendLinkCommand('`, `#'`, activateCmd], },
						{ id: `logic_${logic}_module_${module}`, action: 'replace_content', payload: [':</b>', '<br>', '0'], },

						// Amodule nodes
						{ id: `logic_${logic}_active`, action: 'add_class', class: 'hidden' },
						{ id: `logic_${logic}_active_header`, action: 'set_content', payload: '' },
						{ id: `logic_${logic}_active_content`, action: 'set_content', payload: '' },
						// Clear callsign & destination	
						{ id: `radio_logic_${logic}_destination`, action: 'set_content', payload: '' },
						{ id: `radio_logic_${logic}_callsign`, action: 'set_content', payload: '' },
					);
					return commands;
				}
			},

			// @bookmark EchoLink: 
			// QSO state changed to CONNECTED
			{
				regex: /^(.+?): (\S+): EchoLink QSO state changed to (CONNECTED)$/,
				handler: (match) => {
					const node = match[2];
					const allLogics = this.connections.getAllFrom('EchoLink');

					if (allLogics.length === 0) {
						return [];
					}

					const resultCommands = [];
					for (const logic of allLogics) {
						const timerKey = `logic_${logic}_module_EchoLink`;
						const hasTimer = this.sm.timers.has(timerKey);
						if (hasTimer) {
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
					}
					return resultCommands;
				}
			},

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
						const timerKey = `logic_${logic}_module_EchoLink`;
						const hasTimer = this.sm.timers.has(timerKey);
						if (hasTimer) { 
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
								{ id: 'radio_logic_${logic}_callsign', action: 'set_content', payload: '' },
							);
						}						
					}
					return resultCommands;
				}
			},

			// EchoLink: --- EchoLink chat message received from ... ---
			{
				regex: /^(.+?): --- EchoLink chat message received from (\S+) ---$/,
				handler: (match) => {
					const node = match[2];
					this.isPacketMessageMode = true;
					this.packetType = 'EchoLink';
					const commands = [];
					const allLogics = this.connections.getAllFrom('EchoLink');

					for (const logic of allLogics) {
						const timerKey = `logic_${logic}_module_EchoLink`;
						const hasTimer = this.sm.timers.has(timerKey);
						if (hasTimer) {
							commands.push(
								{ id: `radio_logic_${logic}_callsign`,action: 'set_content',payload: '', },
								{ id: `radio_logic_${logic}_destination`,action: 'set_content',payload: `EchoLink: Conference: ${node}`,}
							);
						} 
					}

					return commands;
				}
			},

			// @bookmark Frn
			// Frn : login stage 2 completed
			{
				regex: /^(.+?): login stage 2 completed: (.+)$/,
				handler: (match) => {
					
					let node = 'Unknown Server';
					const xml = match[2];
					const bnMatch = xml.match(/<BN>(.*?)<\/BN>/);
					if (bnMatch && bnMatch[1]) {
						node = bnMatch[1];
					}

					const allLogics = this.connections.getAllFrom('Frn');
					const resultCommands = [];

					if (allLogics.length === 0) {
						log(`Frn event but no logic links found!`, 'WARNING');
						return [];
					}

					for (const logic of allLogics) {
						const timerKey = `logic_${logic}_module_Frn`;
						const hasTimer = this.sm.timers.has(timerKey);
						if (hasTimer) { 
							this.sm.startTimer(`logic_${logic}_module_Frn_node_${node}`, {
								elementId: `logic_${logic}_node_${node}`,
								replaceStart: ':</b>',
								replaceEnd: '<br>',
								type: 'module_frn',
								logic: logic,
								server: node
							});
							resultCommands.push(
								{ id: `logic_${logic}_module_Frn`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell,disabled-mode-cell' },
								{ id: `logic_${logic}_module_Frn`, action: 'add_class', class: 'active-mode-cell' },
								{ id: `logic_${logic}_active`, action: 'remove_class', class: 'hidden' },
								{ id: `logic_${logic}_active_header`, action: 'set_content', payload: 'Frn [1]' },

								{
									target: `logic_${logic}_active_content`,
									action: 'add_child',
									id: `logic_${logic}_node_${node}`,
									class: 'mode_flex column disabled-mode-cell',
									style: 'border: .5px solid #3c3f47;',
									payload: `<a class="tooltip" href="#"><span><b>Uptime:</b><br>Server ${node}</span>${node}</a>`
								},
								{ id: `radio_logic_${logic}_destination`, action: 'set_content', payload: `Frn: ${node}` },
							);
						}
						
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
						const timerKey = `logic_${logic}_module_Frn`;
						const hasTimer = this.sm.timers.has(timerKey);
						if (hasTimer) {
							commands.push({
								id: `radio_logic_${logic}_callsign`,
								action: 'set_content',
								payload: `${node}`
							});
						} 
					});
					return commands;
				}
			},

			// Frn: FRN list received:
			// @todo Thinking...
			{
				regex: /^(.+?): FRN list received:$/,
				handler: (match) => {
					const timestamp = match[1];
					this.packetActive = true;
					this.packetType = 'Frn';
					this.packetBuffer = [];
					this.packetStartTime = timestamp;
					return [];
				}
			},

			// @bookmark Links
			// Activating link
			{
				regex: /^(.+?): Activating link (\S+)$/,
				handler: (match) => {
					const link = match[2];
					const relatedLogics = this.connections.getAllTo(link);
					const deactivateCmd = this.server.wsState.links?.[link]?.source?.command?.deactivate_command || '';
					this.sm.startTimer(`link_${link}`, {
						elementId: `link_${link}`,
						replaceStart: ':</b>',
						replaceEnd: '<br>',
						type: 'link'
					});

					const commands = [
						{ id: `link_${link}`, action: 'remove_class', class: 'active-mode-cell,inactive-mode-cell,paused-mode-cell,disabled-mode-cell' },
						{ id: `link_${link}`, action: 'add_class', class: 'active-mode-cell' },
						{ id: `toggle-link-state-${link}`, action: "set_checkbox_state", state: "on" },
						{ id: `link_${link}`, action: 'replace_content', payload: [`sendLinkCommand('`, `#'`, deactivateCmd] },
					];

					for (const logic of relatedLogics) {
						
						commands.push(
							{ id: `logic_${logic}`, action: 'remove_class', class: 'paused-mode-cell' },
							{ id: `logic_${logic}`, action: 'add_class', class: 'active-mode-cell' },
							
						);
					}

					return commands;
				}
			},

			// Deactivating link
			{
				regex: /^(.+?): Deactivating link (\S+)$/,
				handler: (match) => {
					const link = match[2];
					const relatedLogics = this.connections.getAllTo(link);
					const activateCmd = this.server.wsState.links?.[link]?.source?.command?.activate_command || '';
					this.sm.stopTimer(`link_${link}`);
					const commands = [
						{ id: `link_${link}`, action: 'remove_class', class: 'active-mode-cell,paused-mode-cell,inactive-mode-cell,disabled-mode-cell' },
						{ id: `link_${link}`, action: 'add_class', class: 'disabled-mode-cell' },
						{ id: `toggle-link-state-${link}`, action: "set_checkbox_state", state: "off" },
						{ id: `link_${link}`, action: 'replace_content', payload: [`sendLinkCommand('`, `#'`, activateCmd] },
					];
					for (const logic of relatedLogics) {
						let hasActiveConnections = false;
						const allConnections = this.connections.getAllTo(logic);

						for (const conn of allConnections) {
							if (conn === link) continue;

							if (conn.startsWith('Link') || conn.startsWith('link_')) {
								if (this.sm.timers.has(`link_${conn}`)) {
									hasActiveConnections = true;
									break;
								}
							} else {
								if (this.sm.timers.has(`${logic}_${conn}`) || this.sm.timers.has(`logic_${logic}_module_${conn}`)) {
									hasActiveConnections = true;
									break;
								}
							}
						}

						if (!hasActiveConnections) {

							commands.push(
								{ id: `logic_${logic}`, action: 'remove_class', class: 'active-mode-cell' },
								{ id: `logic_${logic}`, action: 'add_class', class: 'paused-mode-cell'},
							);
						}
					}
					return commands;
				}
			},

			// @bookmark APRS
			// APRS connected
			{
				regex: /^(.+?): Connected to APRS server (\S+) on port (\d+)$/,
				handler: (match) => {
					const aprs = match[2];
					const port = match[3];
					return [
						{ id: 'aprs_status', action: 'remove_class', class: 'inactive-mode-cell,paused-mode-cell,disabled-mode-cell'},
						{ id: 'aprs_status', action: 'add_class', class: 'active-mode-cell'},
						{ id: 'aprs_status', action: 'set_content', payload: aprs }
					];
				},
			},
			// APRS Disconnected
			{
				regex: /^(.+?): \*\*\* WARNING: Disconnected from APRS server$/,
				handler: (match) => {				
					return [
						{ id: 'aprs_status', action: 'remove_class', class: 'active-mode-cell,paused-mode-cell,disabled-mode-cell' },
						{ id: 'aprs_status', action: 'add_class', class: 'inactive-mode-cell' }
					];
				},
			},
			// @bookmark Directory server
			// EchoLink directory status changed to ON
			{
				regex: /^(.+?): EchoLink directory status changed to ON$/,
				handler: (match) => {
					return [					
						{ id: 'directory_server_status', action: 'remove_class', class: 'inactive-mode-cell,paused-mode-cell,disabled-mode-cell'},
						{ id: 'directory_server_status', action: 'add_class', class: 'active-mode-cell'},
						{ id: 'directory_server_status', action: 'set_content', payload: 'Connected' }
					];
				},
			},
			// *** ERROR: Directory server offline
			{
				regex: /^(.+?): \*\*\* ERROR: Directory server offline(?: \((.+?)\))?/,
				handler: (match) => {
					let reason = match[2];

					if (reason && reason.startsWith('status=')) {
						reason = reason.substring(7);
					}
					return [
						{ id: 'directory_server_status', action: 'remove_class', class: 'active-mode-cell,disabled-mode-cell,paused-mode-cell' },
						{ id: 'directory_server_status', action: 'add_class', class: 'inactive-mode-cell' },
						{ id: 'directory_server_status', action: 'set_content', payload: reason }
					];
				},
			},
			// *** ERROR: + directory server
			{
				regex: /^(.+?): \*\*\* ERROR: .*[Dd]irectory server.*$/,
				handler: (match) => {					
					return [
						{ id: 'directory_server_status', action: 'remove_class', class: 'active-mode-cell,disabled-mode-cell,paused-mode-cell' },
						{ id: 'directory_server_status', action: 'add_class', class: 'inactive-mode-cell' },
						{ id: 'directory_server_status', action: 'set_content', payload: 'ERROR' }
					];
				},
			},
			

			// @bookmark EchoLink proxy server
			// Connected
			// @note ipv6 ready
			{
				regex: /^(.+?): Connected to EchoLink proxy (.+?):(\d+)$/,
				handler: (match) => {
					const host = match[2];
					return [
						{ id: 'proxy_server', action: 'remove_class', class: 'hidden' },
						{ id: 'proxy_server_status', action: 'remove_class', class: 'inactive-mode-cell,disabled-mode-cell,paused-mode-cell' },
						{ id: 'proxy_server_status', action: 'add_class', class: 'active-mode-cell' },
						{ id: 'proxy_server_status', action: 'set_content', payload: host }
					];
				},
			},
			// Disconnected
			{
				regex: /^(.+?): Disconnected from EchoLink proxy (.+?):(\d+)$/,
				handler: (match) => {
					const host = match[2];
					return [
						{ id: 'proxy_server_status', action: 'remove_class', class: 'active-mode-cell,paused-mode-cell,disabled-mode-cell' },
						{ id: 'proxy_server_status', action: 'add_class', class: 'inactive-mode-cell' },
						{ id: 'proxy_server_status', action: 'set_content', payload: host }
					];
				},
			},

			// @bookmark WARNINGS

			// WARNING[getaddrinfo]: Could not look up host "aprs.echolink.org": Temporary failure in name resolution
			{
				regex: /^(.+?): \*\*\* WARNING\[getaddrinfo\]: Could not look up host "([^"]+)": Temporary failure in name resolution$/,
				handler: (match) => {
					const host = match[2];
					return [
						{ id: host, action: 'remove_class', class: 'disabled-mode-cell,active-mode-cell,paused-mode-cell' },
						{ id: host, action: 'add_class', class: 'inactive-mode-cell' },
						{ id: host, action: 'set_content',  payload: 'DNS failure' },
					];
				},
			},
			// *** ERROR: EchoLink directory server DNS lookup failed
			{
				regex: /^(.+?): \*\*\* ERROR: EchoLink directory server DNS lookup failed$/,
				handler: (match) => {
					return [
						{ id: 'directory_server_status', action: 'remove_class', class: 'active-mode-cell,paused-mode-cell,disabled-mode-cell' },
						{ id: 'directory_server_status', action: 'add_class', class: 'inactive-mode-cell' },
						{ id: 'directory_server_status', action: 'set_content', payload: 'DNS failed' }
					];
				},
			},
		];
	}

	parse(line) {
		const trimmed = line.trim();
		
		if (this.isPacketMessageMode) {
			// @note Any logic or device messages must break packet mode
			// @todo Thinking...
			const deviceMatch = trimmed.match(/^.+: (\w+):/)
			if (deviceMatch) {
				const deviceName = deviceMatch[1];
				const isKnownDevice = this.connections.connections.has(deviceName) ||					
					this.compositeDeviceManager.getComponents(deviceName).length > 0 ||
					Array.from(this.connections.connections.keys()).some(key =>
						key === deviceName
					);
				if (isKnownDevice) {
					this.isPacketMessageMode = false;
				}
			}

			if (this.packetType == "EchoLink") {
				if (trimmed.includes('Trailing chat data:')) {
					this.isPacketMessageMode = false;
				}
				if (trimmed.includes('>') && !trimmed.includes('<')) {
					let processedPayload = trimmed;
					const firstColonSpaceIndex = processedPayload.indexOf(': ');
					if (firstColonSpaceIndex !== -1) {
						processedPayload = processedPayload.substring(firstColonSpaceIndex + 2);
					}

					if (processedPayload.includes('->')) {
						processedPayload = processedPayload.replace('->', '>');
					}

					if (processedPayload.startsWith('>')) {
						processedPayload = '<b>' + processedPayload.substring(1);
						const firstSpaceIndex = processedPayload.indexOf(' ', 3);
						if (firstSpaceIndex !== -1) {
							processedPayload = processedPayload.substring(0, firstSpaceIndex) +
								'</b>' +
								processedPayload.substring(firstSpaceIndex);
						} else {
							processedPayload += '</b>';
						}
					} else if (processedPayload.includes('>') && !trimmed.includes('->')) {
						const gtIndex = processedPayload.indexOf('>');
						const beforeGt = processedPayload.substring(0, gtIndex);
						const afterGt = processedPayload.substring(gtIndex + 1);
						processedPayload = `SMS from <b>${beforeGt}</b>: ${afterGt}`;
					}
					
					const allLogics = this.connections.getAllFrom('EchoLink');
					for (const logic of allLogics) {
						const timerKey = `logic_${logic}_module_EchoLink`;
						const hasTimer = this.sm.timers.has(timerKey);
						if (hasTimer) {
							return {
								commands: [{
									id: `radio_logic_${logic}_callsign`,
									action: 'set_content',
									payload: processedPayload
								}],
								raw: trimmed,
								timestamp: this.extractTimestamp(trimmed) || new Date().toISOString()
							};
						}
					}
				}

				for (const pattern of this.patterns) {
					const match = trimmed.match(pattern.regex);
					if (match) {
						this.isPacketMessageMode = false;
						const commands = pattern.handler(match);
						return {
							commands: commands,
							raw: match[0],
							timestamp: match[1]
						};
					}
				}
				return null;
			};
		}

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

	extractTimestamp(line) {
		const match = line.match(/^(.+?):/);
		return match ? match[1] : null;
	}

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

	initFromWsData(wsData) {
		if (wsData.module_logic) {
			this.connections.initFromData(wsData.module_logic, 'module', 'logic');
		}

		if (wsData.link_logic) {
			this.connections.initFromData(wsData.link_logic, 'link', 'logic');
		}

		if (wsData.device_logic) {
			this.connections.initFromData(wsData.device_logic, 'device', 'logic');
		}

		if (wsData.multiple_device) {
			this.connections.initFromData(wsData.multiple_device, 'device', 'subdevice');
		}
	}
}

class CompositeDeviceManager {
	constructor() {
		this.compositeDevices = new Map(); // compositeKey -> [componentKeys]
	}

	initFromWsData(wsData) {
		this.compositeDevices.clear();
		const data = wsData.multiple_device || {};
		for (const [composite, components] of Object.entries(data)) {
			if (Array.isArray(components)) {
				this.compositeDevices.set(composite, [...components]);
			}
		}
	}

	getComponents(deviceKey) {
		if (this.compositeDevices.has(deviceKey)) {
			return this.compositeDevices.get(deviceKey); 
		}

		for (const [composite, components] of this.compositeDevices.entries()) {
			if (components.includes(deviceKey)) {
				return [composite];
			}
		}
		return [];
	}
}
//@bookmark class StatefulWebSocketServerV4
class StatefulWebSocketServerV4 {
	constructor(config = {}) {
		this.config = { ...CONFIG, ...config };
		this.wss = null;
		this.stateManager = new StateManager();		
		this.compositeDeviceManager = new CompositeDeviceManager();
		this.commandParser = new CommandParser(this); 
		
		this.clients = new Set();
		this.tailProcess = null;
		this.isMonitoring = false;
		this.shutdownTimer = null;
		this.durationTimer = null;
		this.lineBuffer = '';
		this.wsState = {
			service: {},
			devices: {},
			modules: {},
			links: {},
			nodes: {},
			module_logic: {},
			logics: {}
		};

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

	async loadWsState() {
		try {
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
			if (!responseText || responseText.trim() === '') {
				throw new Error('Empty response from PHP endpoint');
			}

			let data;
			try {
				data = JSON.parse(responseText);
			} catch (parseError) {
				log(`Failed to parse JSON response: ${parseError.message}`, 'ERROR');
				throw new Error('Invalid JSON response from PHP');
			}

			if (!data || typeof data !== 'object') {
				throw new Error('Invalid response format: expected object');
			}

			const wsData = data.data || {};
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
				service: wsData.service || {},
				device_logic: wsData.device_logic || {},
				multiple_device: wsData.multiple_device || {}
			};

			this.stats.stateLoads++;
			this.restoreFromWsState();
			return true;

		} catch (error) {
			this.stats.stateLoadErrors++;
			log(`Failed to load WS state: ${error.message}`, 'WARNING');

			this.wsState = {
				devices: {},
				modules: {},
				links: {},
				nodes: {},
				module_logic: {},
				link_logic: {},
				logics: {},
				service: {},
				device_logic: {},
				multiple_device: {}
			};
			return false;
		}
	}

	restoreFromWsState() {
		const devices = this.wsState.devices || {};
		const modules = this.wsState.modules || {};
		const links = this.wsState.links || {};
		const nodes = this.wsState.nodes || {};
		const service = this.wsState.service || {};
		const logics = this.wsState.logics || {};
		let restoredCount = 0;
		// Relation init
		this.commandParser.initFromWsData(this.wsState);
		this.compositeDeviceManager.initFromWsData(this.wsState);
		// Logic timers
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
			}
		}

		// Device timers
		for (const [device, info] of Object.entries(devices)) {
			if (info && info.start && info.start > 0) {
				const startTime = info.start * 1000;
				const deviceType = info.type || 'UNKNOWN';

				if (deviceType === 'TX') {
					this.stateManager.startTimer(`device_${device}`, {
						elementId: `device_${device}_tx_status`,
						replaceStart: '( ',
						replaceEnd: ' )',
						type: 'device'
					});
					this.stateManager.setTimerStart(`device_${device}`, startTime);
				} else if (deviceType === 'RX') {
					this.stateManager.startTimer(`device_${device}`, {
						elementId: `device_${device}_rx_status`,
						replaceStart: '( ',
						replaceEnd: ' )',
						type: 'device'
					});
					this.stateManager.setTimerStart(`device_${device}`, startTime);
				}
				restoredCount++;
			}
		}

		// Modules timer
		for (const [moduleKey, info] of Object.entries(modules)) {
			if (info && info.logic && info.module) {
				const logicName = info.logic;
				const moduleName = info.module;
				const startTime = info.start * 1000;
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

		// Links timer
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

		// Nodes timer
		for (const [nodeKey, info] of Object.entries(nodes)) {
			if (info && info.start && info.start > 0) {
				const match = nodeKey.match(/^logic_(.+?)_node_(.+)$/);
				if (match) {
					const logicName = match[1];
					const nodeName = match[2];
					const startTime = info.start * 1000;
					let timerKey, elementId;
					if (info.type === 'module_node' && info.module) {
						timerKey = `${info.module}_${nodeName}`;
						elementId = `logic_${logicName}_active_content`;
					} else {
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

		// Service timer
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
		}
		log(`Restored ${restoredCount} timers from WS state`, 'INFO');
	}

	sendInitialCommands(ws) {
		const commands = [];
		const devices = this.wsState.devices || {};
		const service = this.wsState.service || {};
		for (const [device, info] of Object.entries(devices)) {
			if (info && info.start && info.start > 0) {
				const duration = Math.floor(Date.now() / 1000 - info.start);
				const durationText = this.stateManager.formatDuration(duration * 1000);
				if (info.type === 'TX') { 
					commands.push(
						{ id: `device_${device}_tx_status`, action: 'set_content', payload: `TRANSMIT ( ${durationText} )` },
						{ id: `device_${device}_tx_status`, action: 'add_class', class: 'inactive-mode-cell' }
					);
				}
				if (info.type === 'RX') { 
					commands.push(
						{ id: `device_${device}_rx_status`, action: 'set_content', payload: `RECEIVE ( ${durationText} )` },
						{ id: `device_${device}_rx_status`, action: 'add_class', class: 'active-mode-cell' }
					);
				}
			}
		}

		if (service.name && service.start && service.start > 0) {
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

			this.stateManager.startTimer(`service`, {
				elementId: `service`,
				replaceStart: ':</b>',
				replaceEnd: '<br>',
				type: 'service'
			});
			this.stateManager.setTimerStart(`service`, service.start * 1000);
		}

		if (commands.length > 0) {
			try {
				ws.send(JSON.stringify({
					type: 'dom_commands',
					commands: commands,
					timestamp: Date.now(),
					subtype: 'initial_state'
				}));
			} catch (error) {
				log(`Failed to send initial commands: ${error.message}`, 'ERROR');
			}
		}
	}


	async start() {
		log(`Run WebSocket Server v${this.config.version}`, 'INFO');
		try {
			if (!fs.existsSync(this.config.log.path)) {
				log(`Log file not found: ${this.config.log.path}`, 'WARNING');
			}

			this.startWebSocket();
			this.startShutdownTimer();
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
	}

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
		log(`Server v${this.config.version}: Client ${clientId} connected from ${clientIp} (tital: ${this.clients.size})`, 'DEBUG');

		// Welcome msg
		this.sendWelcome(ws, clientId);
		if (this.clients.size === 1 && Object.keys(this.wsState.devices).length === 0) {
			await this.loadWsState();
		}
		this.sendInitialCommands(ws);

		if (this.clients.size === 1) {
			this.startLogMonitoring();
			this.startDurationTimer();
			this.clearShutdownTimer();
		}

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

			log(`Client ${clientId} disconnected (remain ${this.clients.size} clients)`, 'DEBUG');

			// Zero clients - stopping
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
			// Ignore
		}
	}

	sendWelcome(ws, clientId) {
		ws.send(JSON.stringify({
			type: 'welcome',
			version: this.config.version,
			clientId: clientId,
			serverTime: Date.now(),
			message: 'Server WebSocket v' + this.config.version,
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

	startLogMonitoring() {
		if (this.isMonitoring) {
			log('Log monitoring already active', 'WARNING');
			return;
		}

		this.tailProcess = spawn('tail', ['-F', '-n', '0', this.config.log.path]);
		this.isMonitoring = true;
		this.tailProcess.stdout.on('data', (data) => {
			this.processTailData(data);
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

			if (this.lineBuffer.trim()) {
				this.processLogBuffer([this.lineBuffer]);
				this.lineBuffer = '';
			}

			if (this.clients.size > 0) {
				setTimeout(() => this.startLogMonitoring(), 2000);
			}
		});
	}

	processTailData(data) {

		this.lineBuffer += data.toString();
		const lines = [];
		let newlineIndex;
		while ((newlineIndex = this.lineBuffer.indexOf('\n')) !== -1) {
			const line = this.lineBuffer.substring(0, newlineIndex).trim();
			if (line) {
				lines.push(line);
			}
			this.lineBuffer = this.lineBuffer.substring(newlineIndex + 1);
		}

		if (lines.length > 0) {
			this.processLogBuffer(lines);
		}
	}

	stopLogMonitoring() {
		if (this.tailProcess && this.isMonitoring) {
			this.tailProcess.kill('SIGTERM');
			this.tailProcess = null;
			this.isMonitoring = false;
			log('Log monitoring stopped.', 'INFO');
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

				this.broadcast(message);
			}
		}
	}

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
						this.broadcast(message);
					}
				}
			}
		}, this.config.duration.updateInterval);
	}

	stopDurationTimer() {
		if (this.durationTimer) {
			clearInterval(this.durationTimer);
			this.durationTimer = null;
		}
	}

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
	}

	clearShutdownTimer() {
		if (this.shutdownTimer) {
			clearTimeout(this.shutdownTimer);
			this.shutdownTimer = null;
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
				log(`Server v 0.4.22 Statistics:`, 'INFO');
				log(`  Uptime: ${uptime}s`, 'INFO');
				log(`  Clients connected: ${this.stats.clientsConnected}`, 'INFO');
				log(`  Clients disconnected: ${this.stats.clientsDisconnected}`, 'INFO');
				log(`  Messages sent: ${this.stats.messagesSent}`, 'INFO');
				log(`  Events processed: ${this.stats.eventsProcessed}`, 'INFO');
				log(`  Commands generated: ${this.stats.commandsGenerated}`, 'INFO');
				log(`  State loads: ${this.stats.stateLoads}`, 'INFO');
				log(`  State load errors: ${this.stats.stateLoadErrors}`, 'INFO');
				log(`  Active timers: ${stateStats.activeTimers}`, 'INFO');
				log(`  Connections: ${this.commandParser.connections.connections.size}`, 'INFO');
				log(`  Errors: ${this.stats.errors}`, 'INFO');
				log('Server shutdown complete', 'INFO');
				process.exit(0);
			});

			setTimeout(() => {
				process.exit(1);
			}, 3000);
		} else {
			process.exit(0);
		}
	}

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


async function main() {
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

	const server = new StatefulWebSocketServerV4();
	global.serverInstance = server;
	await server.start();
}

if (require.main === module) {
	main().catch(error => {
		log(`Failed to start server: ${error.message}`, 'ERROR');
		process.exit(1);
	});
}

module.exports = {
	StatefulWebSocketServerV4,
	StateManager,
	CommandParser,
	ConnectionHandler
};