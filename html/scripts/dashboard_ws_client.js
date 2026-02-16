/**
 * @filesource /scripts/dashboard_ws_client.js
 * @author Vladimir Tsurkanenko <vladimir@tsurkanenko.ru>
 * @date 2026.02.16
 * @version 0.4.32
 * @description DOM Command Executor with WebSocket transport
 */

class DashboardWebSocketClientV4 {
	constructor(config = {}) {
		// Default config
		this.config = {
			translations: {
				connected: 'Connected',
				disconnected: 'Periodic',
				connecting: 'Connecting',
			},
			host: window.location.hostname,
			port: 8080,
			autoConnect: true,
			reconnectDelay: 3000,
			debugLevel: 2,
			debugWebConsole: true,
			debugConsole: false,
			maxReconnectAttempts: 5,
			pingInterval: 30000,
			...config
		};

		// State
		this.ws = null;
		this.status = 'disconnected';
		this.reconnectAttempts = 0;
		this.isManualDisconnect = false;
		this.clientId = null;

		// Timers
		this.pingTimer = null;
		this.reconnectTimer = null;

		// Store translations reference
		this.t = this.config.translations;

		// Button elements cache
		this.button = null;
		this.buttonText = null;

		// Initialize after DOM is ready
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', () => this.init());
		} else {
			this.init();
		}
	}

	// @bookmark INIT
	init() {
		this.log('INFO', 'DOM Command Executor Client v0.4.32 initialized');
		this.createStatusButton();
		this.updateButtonStatus();

		if (this.config.autoConnect) {
			setTimeout(() => this.connect(), 1000);
		}
	}

	// @bookmark Button
	createStatusButton() {
		const oldButton = document.getElementById('feedStatus');
		if (oldButton) oldButton.remove();

		const navbar = document.querySelector('.navbar');
		if (!navbar) {
			setTimeout(() => this.createStatusButton(), 100);
			return;
		}

		this.button = document.createElement('a');
		this.button.id = 'feedStatus';
		this.button.href = 'javascript:void(0)';
		this.button.className = 'menufeed ajax';

		this.buttonText = document.createElement('span');
		this.buttonText.id = 'feedStatusText';
		this.buttonText.textContent = this.t.disconnected;

		this.button.appendChild(this.buttonText);
		this.button.addEventListener('click', (e) => {
			e.preventDefault();
			this.handleStatusButton();
		});

		navbar.appendChild(this.button);
		this.log('INFO', 'Feed status button created');
	}

	updateButtonStatus() {
		if (!this.button || !this.buttonText) return;

		// Remove all status classes
		this.button.classList.remove(
			'ajax',
			'icon-active',
			'connecting',
			'reconnecting'
		);

		let statusClass = 'ajax';
		let buttonText = this.t.disconnected;

		switch (this.status) {
			case 'connected':
				statusClass = 'icon-active';
				buttonText = this.t.connected;
				break;

			case 'connecting':
				statusClass = 'connecting';
				buttonText = this.t.connecting;
				break;

			case 'reconnecting':
				statusClass = 'reconnecting';
				buttonText = this.t.connecting;
				break;

			case 'error':
			case 'timeout':
			case 'disconnected':
			default:
				statusClass = 'ajax';
				buttonText = this.t.disconnected;
				break;
		}

		this.button.classList.add(statusClass);
		this.buttonText.textContent = buttonText;
	}

	handleStatusButton() {
		switch (this.status) {
			case 'connected':
				this.disconnect();
				break;
			case 'disconnected':
			case 'error':
			case 'timeout':
				this.connect();
				break;
			case 'connecting':
			case 'reconnecting':
				this.log('INFO', `Already ${this.status}, ignoring click`);
				break;
			default:
				this.log('WARNING', `Unknown status: ${this.status}`);
		}
	}

	startPageReload() {
		this.log('INFO', 'Starting page reload...');
		setTimeout(() => {
			window.location.reload();
		}, 300);
	}

	// @bookmark Connecting
	connect() {
		this.isManualDisconnect = false;

		if (this.ws) {
			try {
				this.ws.onopen = null;
				this.ws.onmessage = null;
				this.ws.onclose = null;
				this.ws.onerror = null;
				if (this.ws.readyState === WebSocket.OPEN || this.ws.readyState === WebSocket.CONNECTING) {
					this.ws.close(1000, 'Reconnecting');
				}
			} catch (e) { }
			this.ws = null;
		}

		const wsUrl = `ws://${this.config.host}:${this.config.port}`;
		this.log('INFO', `Connecting to: ${wsUrl}`);

		this.updateStatus('connecting', 'Connecting...');

		try {
			this.ws = new WebSocket(wsUrl);

			this.ws.onopen = (event) => this.handleOpen(event);
			this.ws.onmessage = (event) => this.handleMessage(event);
			this.ws.onclose = (event) => this.handleClose(event);
			this.ws.onerror = (error) => this.handleError(error);

			setTimeout(() => {
				if (this.ws && this.ws.readyState === WebSocket.CONNECTING) {
					this.log('WARNING', 'Connection timeout (5s)');
					this.ws.close();
					this.startPageReload();
				}
			}, 5000);

			return true;

		} catch (error) {
			this.log('ERROR', `Error creating DOM Command Server: ${error.message}`, error);
			this.startPageReload();
			return false;
		}
	}

	disconnect() {
		this.isManualDisconnect = true;

		if (this.ws && this.ws.readyState === WebSocket.OPEN) {
			this.ws.close(1000, 'Manual disconnect');
		}

		this.clearTimers();
		this.updateStatus('disconnected', 'Disconnected manually');
		this.log('INFO', 'Manual disconnect initiated');
	}

	// @bookmark Event handlers
	handleOpen(event) {
		this.log('INFO', 'DOM Command Server connected successfully');
		this.updateStatus('connected', 'Connected');
		this.reconnectAttempts = 0;
		this.startPingTimer();
		window.setAJAXMode?.(true);
	}

	handleMessage(event) {
		try {
			const data = JSON.parse(event.data);

			if (data.type === 'welcome') {
				this.clientId = data.clientId;
				this.log('INFO', `Connected to server v${data.version}, client ID: ${this.clientId}`);

			} else if (data.type === 'pong') {
				// Ignore pong

			} else if (data.type === 'dom_commands' && Array.isArray(data.commands)) {
				this.processCommands(data.commands, data.chunk, data.chunks);

			} else if (data.type === 'log_message') {
				const logLevels = {
					'ERROR': 1,
					'WARNING': 2,
					'INFO': 3,
					'DEBUG': 4
				};

				const messageLevel = logLevels[data.level] || 2;
				const clientDebugLevel = this.config.debugLevel || 2;

				if (messageLevel <= clientDebugLevel) {
					this.logToWebConsole(data.timestamp, data.level, data.message, data.source);
				}

			} else {
				this.log('WARNING', 'Unknown message format:', data);
			}

		} catch (error) {
			this.log('ERROR', `Error parsing message: ${error.message}`, event.data);
		}
	}

	handleClose(event) {
		this.log('INFO', `DOM Command Server closed: code=${event.code}, reason=${event.reason}, clean=${event.wasClean}`);

		this.clearTimers();
		window.setAJAXMode?.(false);
		this.log('INFO', 'Switching to AJAX mode');

		if (this.isManualDisconnect) {
			this.log('INFO', 'Manual disconnect confirmed');
			this.updateStatus('disconnected');
			return;
		}

		// Connection lost - reload page immediately
		this.log('INFO', 'Connection lost, reloading page...');
		this.startPageReload();
	}

	handleError(error) {
		this.log('ERROR', 'DOM Command Server error', error);
		this.startPageReload();
	}

	// @bookmark Command processing
	processCommands(commands, chunkNum = null, totalChunks = null) {
		if (!Array.isArray(commands)) {
			this.log('ERROR', 'Commands must be an array', commands);
			return;
		}

		const chunkInfo = chunkNum ? ` (chunk ${chunkNum}/${totalChunks})` : '';
		this.log('DEBUG', `Processing ${commands.length} commands ${chunkInfo}`);

		let successCount = 0;
		let errorCount = 0;

		commands.forEach((cmd) => {
			if (this.executeAction(cmd)) {
				successCount++;
			} else {
				errorCount++;
			}
		});

		if (errorCount === 0) {
			this.log('INFO', `Processed ${commands.length} commands: ${successCount} successful`);
		} else {
			this.log('WARNING', `Processed ${commands.length} commands: ${successCount} successful, ${errorCount} errors`);
		}
	}

	executeAction(cmd) {
		if (!cmd || !cmd.action) {
			this.log('ERROR', 'Invalid command: missing action', cmd);
			return false;
		}

		switch (cmd.action) {
			case 'add_class':
				return this.handleAddClass(cmd);
			case 'remove_class':
				return this.handleRemoveClass(cmd);
			case 'remove_element':
				return this.handleRemoveElement(cmd);
			case 'set_content':
				return this.handleSetContent(cmd);
			case 'replace_content':
				return this.handleReplaceContent(cmd);
			case 'add_parent_class':
				return this.handleParentClass(cmd, 'add');
			case 'remove_parent_class':
				return this.handleParentClass(cmd, 'remove');
			case 'add_child':
				return this.addChild(cmd);
			case 'remove_child':
				return this.removeChild(cmd);
			case 'replace_child_classes':
				return this.replaceChildClasses(cmd);
			default:
				this.log('ERROR', `Unknown action: ${cmd.action}`, cmd);
				return false;
		}
	}

	// @bookmark DOM operations
	getElement(id) {
		const element = document.getElementById(id);
		if (!element && this.config.debugLevel >= 2) {
			this.log('WARNING', `Element ${id} not found`);
		}
		return element;
	}

	getParentElement(childId) {
		const childElement = this.getElement(childId);
		return childElement ? childElement.parentElement : null;
	}

	handleAddClass(cmd) {
		if (!cmd.class) {
			this.log('ERROR', 'add_class missing class parameter', cmd);
			return false;
		}

		const element = this.getElement(cmd.id);
		if (!element) return false;

		try {
			const classes = cmd.class.includes(',')
				? cmd.class.split(',').map(c => c.trim())
				: [cmd.class];

			classes.forEach(cls => element.classList.add(cls));
			return true;
		} catch (error) {
			this.log('ERROR', `Error adding class to ${cmd.id}: ${error.message}`, cmd);
			return false;
		}
	}

	handleRemoveClass(cmd) {
		if (!cmd.class) {
			this.log('ERROR', 'remove_class missing class parameter', cmd);
			return false;
		}

		const element = this.getElement(cmd.id);
		if (!element) return false;

		try {
			const classes = cmd.class.includes(',')
				? cmd.class.split(',').map(c => c.trim())
				: [cmd.class];

			classes.forEach(cls => element.classList.remove(cls));
			return true;
		} catch (error) {
			this.log('ERROR', `Error removing class from ${cmd.id}: ${error.message}`, cmd);
			return false;
		}
	}

	handleSetContent(cmd) {
		if (cmd.payload === undefined) {
			this.log('ERROR', 'set_content missing payload', cmd);
			return false;
		}

		const element = this.getElement(cmd.id);
		if (!element) return false;

		try {
			element.innerHTML = cmd.payload;
			return true;
		} catch (error) {
			this.log('ERROR', `Error setting content for ${cmd.id}: ${error.message}`, cmd);
			return false;
		}
	}

	handleReplaceContent(cmd) {
		if (!Array.isArray(cmd.payload) || cmd.payload.length !== 3) {
			this.log('ERROR', 'replace_content requires payload array of 3 items', cmd);
			return false;
		}

		const element = this.getElement(cmd.id);
		if (!element) return false;

		try {
			const [beginCond, endCond, newContent] = cmd.payload;
			const html = element.innerHTML;
			const startIndex = html.indexOf(beginCond);

			if (startIndex === -1) return false;

			const endIndex = html.indexOf(endCond, startIndex + beginCond.length);
			if (endIndex === -1) return false;

			const before = html.substring(0, startIndex + beginCond.length);
			const after = html.substring(endIndex);
			element.innerHTML = before + newContent + after;

			return true;
		} catch (error) {
			this.log('ERROR', `Error replacing content for ${cmd.id}: ${error.message}`, cmd);
			return false;
		}
	}

	handleParentClass(cmd, operation) {
		if (!cmd.class) {
			this.log('ERROR', `${cmd.action} missing class parameter`, cmd);
			return false;
		}

		const parentElement = this.getParentElement(cmd.id);
		if (!parentElement) {
			if (this.config.debugLevel >= 2) {
				this.log('WARNING', `Parent element not found for ${cmd.id}`);
			}
			return false;
		}

		try {
			const classes = cmd.class.includes(',')
				? cmd.class.split(',').map(c => c.trim())
				: [cmd.class];

			classes.forEach(cls => {
				if (operation === 'add') {
					parentElement.classList.add(cls);
				} else {
					parentElement.classList.remove(cls);
				}
			});
			return true;
		} catch (error) {
			this.log('ERROR', `Error ${operation} parent class for ${cmd.id}: ${error.message}`, cmd);
			return false;
		}
	}

	handleRemoveElement(cmd) {
		if (!cmd.id) {
			this.log('ERROR', 'remove_element missing id', cmd);
			return false;
		}

		const element = document.getElementById(cmd.id);
		if (!element) return false;

		element.remove();
		return true;
	}

	addChild(cmd) {
		if (!cmd.target || !cmd.payload) {
			this.log('ERROR', 'add_child: missing target or payload', cmd);
			return false;
		}

		const parent = document.getElementById(cmd.target);
		if (!parent) {
			this.log('ERROR', `add_child: Parent "${cmd.target}" not found`, cmd);
			return false;
		}

		try {
			const childId = cmd.id || null;
			if (childId) {
				const existing = document.getElementById(childId);
				if (existing && existing.parentElement === parent) return true;
			}

			const element = document.createElement(cmd.type || 'div');
			element.innerHTML = cmd.payload;
			if (childId) element.id = childId;
			if (cmd.class) element.className = cmd.class;
			if (cmd.style) element.style.cssText = cmd.style;
			if (cmd.title) element.title = cmd.title;

			parent.appendChild(element);
			return true;
		} catch (error) {
			this.log('ERROR', `Error in add_child: ${error.message}`, cmd);
			return false;
		}
	}

	removeChild(cmd) {
		if (!cmd.id) return false;

		const parent = document.getElementById(cmd.id);
		if (!parent) return false;

		const ignoreClasses = cmd.ignoreClass
			? cmd.ignoreClass.split(',').map(c => c.trim())
			: [];

		Array.from(parent.children).forEach(child => {
			const shouldIgnore = ignoreClasses.some(cls => child.classList.contains(cls));
			if (!shouldIgnore) child.remove();
		});

		return true;
	}

	replaceChildClasses(cmd) {
		if (!cmd.class || !cmd.oldClass) {
			this.log('ERROR', 'replaceChildClasses missing class or oldClass parameter', cmd);
			return false;
		}

		const element = this.getElement(cmd.id);
		if (!element) return false;

		try {
			const oldClasses = cmd.oldClass.includes(',')
				? cmd.oldClass.split(',').map(c => c.trim())
				: [cmd.oldClass];

			const newClasses = cmd.class.includes(',')
				? cmd.class.split(',').map(c => c.trim())
				: [cmd.class];

			let modified = 0;
			Array.from(element.children).forEach(child => {
				const hasAllOldClasses = oldClasses.every(cls => child.classList.contains(cls));
				if (hasAllOldClasses) {
					oldClasses.forEach(cls => child.classList.remove(cls));
					newClasses.forEach(cls => child.classList.add(cls));
					modified++;
				}
			});

			return modified > 0;
		} catch (error) {
			this.log('ERROR', `Error in replaceChildClasses: ${error.message}`, cmd);
			return false;
		}
	}

	// @bookmark Timer methods
	startPingTimer() {
		if (this.pingTimer) clearInterval(this.pingTimer);

		this.pingTimer = setInterval(() => {
			if (this.ws?.readyState === WebSocket.OPEN) {
				this.ws.send(JSON.stringify({
					type: 'ping',
					timestamp: Date.now(),
					clientId: this.clientId
				}));
			}
		}, this.config.pingInterval);
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

	// @bookmark Status management
	updateStatus(status, message = '') {
		this.status = status;
		this.log('INFO', `Status: ${status} - ${message}`);
		this.updateButtonStatus();
	}

	// @bookmark Logging
	log(level, message, data = null) {
		const levels = { 'ERROR': 1, 'WARNING': 2, 'INFO': 3, 'DEBUG': 4 };
		const levelNum = typeof level === 'string' ? levels[level] || 2 : level;

		if (levelNum > this.config.debugLevel) return;

		const timestamp = new Date().toISOString();

		if (this.config.debugConsole) {
			const prefix = `[DOM CE Client ${level}]`;
			switch (levelNum) {
				case 1: console.error(prefix, message, data || ''); break;
				case 2: console.warn(prefix, message, data || ''); break;
				case 3: console.info(prefix, message, data || ''); break;
				case 4: console.debug(prefix, message, data || ''); break;
			}
		}

		if (this.config.debugWebConsole) {
			this.logToWebConsole(timestamp, level, message, data);
		}
	}

	logToWebConsole(timestamp, level, message, data = null) {
		const debugConsole = document.getElementById('debugLog');
		if (!debugConsole) return;

		const time = new Date(timestamp).toLocaleTimeString();
		const levelClass = `debug-${level.toLowerCase()}`;

		let fullMessage = message;
		let sender = '[DOM CE Client v0.4.32]';

		if (typeof data === 'string') {
			sender = `[${data}]`;
		} else if (data?.source) {
			sender = `[${data.source}]`;
		} else if (data) {
			fullMessage += ' ' + JSON.stringify(data);
		}

		const html = `
			<div class="debug-entry ${levelClass}">
				<span class="debug-time">[${time}]</span>
				<span class="debug-source">${sender}</span>
				<span class="debug-level">[${level}]</span>
				<span class="debug-message">${this.escapeHtml(fullMessage)}</span>
			</div>`;

		debugConsole.innerHTML = html + debugConsole.innerHTML;

		const entries = debugConsole.querySelectorAll('.debug-entry');
		if (entries.length > 100) {
			for (let i = 100; i < entries.length; i++) entries[i].remove();
		}
	}

	escapeHtml(text) {
		if (typeof text !== 'string') text = String(text);
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	// @bookmark Public API
	getStatus() {
		return {
			status: this.status,
			wsState: this.ws?.readyState ?? null,
			clientId: this.clientId,
			reconnectAttempts: this.reconnectAttempts,
			config: this.config
		};
	}

	executeTestCommand(command) {
		return this.executeAction(command);
	}
}

// @bookmark Initialization
document.addEventListener('DOMContentLoaded', () => {
	const wsConfig = window.DASHBOARD_CONFIG?.websocket;

	if (!wsConfig) {
		console.warn('DOM Command Executor v0.4.32 using default settings');
	}

	window.dashboardWSClient = new DashboardWebSocketClientV4(wsConfig);

	window.connectWebSocket = () => window.dashboardWSClient?.connect();
	window.disconnectWebSocket = () => window.dashboardWSClient?.disconnect();
	window.getWebSocketStatus = () => window.dashboardWSClient?.getStatus();

	console.log('DOM Command Executor Client v0.4.32 initialized');
});