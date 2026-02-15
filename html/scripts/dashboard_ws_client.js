/**
 * @filesource /scripts/dashboard_ws_client.js
 * @author Vladimir Tsurkanenko <vladimir@tsurkanenko.ru>
 * @date 2026.02.14
 * @version 0.4.24
 * @description DOM Command Executor with WebSocket transport
 */

class DashboardWebSocketClientV4 {
	constructor(config = {}) {
			// Default config
			this.config = {
				host: window.location.hostname,
				port: 8080,
				autoConnect: true,
				reconnectDelay: 3000,
				debugLevel: 2,  // 1=ERROR, 2=WARNING, 3=INFO, 4=DEBUG
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
		this.init();
	}

	// @bookmark INIT

	init() {
		this.log('INFO', 'Dashboard WebSocket Client v0.4.3.release initialized');

		// Menu button
		this.createStatusButton();

		// Connetction
		if (this.config.autoConnect) {
			setTimeout(() => this.connect(), 1000);
		}
	}

	// @bookmark  Button
	createStatusButton() {
		
		const oldButton = document.getElementById('websocketStatus');
		if (oldButton) oldButton.remove();

		const navbar = document.querySelector('.navbar');
		if (!navbar) {
			setTimeout(() => this.createStatusButton(), 100);
			return;
		}

		const button = document.createElement('a');
		button.id = 'feedStatus';
		button.href = 'javascript:void(0)';
		button.className = 'menufeed ajax';
		button.title = 'Try to connect with DOM Command Server';

		const textSpan = document.createElement('span');
		textSpan.id = 'feedStatusText';
		textSpan.textContent = 'Periodic';
		button.appendChild(textSpan);

		button.addEventListener('click', (e) => {
			e.preventDefault();
			this.handleStatusButton();
		});

		navbar.appendChild(button);
		this.log('INFO', 'Feed status button created');
	}

	handleStatusButton() {
			
		switch (this.status) {
			case 'connected':
				this.disconnect();
				break;
			case 'disconnected':
			case 'error':
			case 'timeout':
				this.startPageReload();
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
		this.log('INFO', 'Starting page reload to launch DOM Command Server...');

		const button = document.getElementById('feedStatus');
		const textSpan = document.getElementById('feedStatusText');

		if (button && textSpan) {
			textSpan.textContent = 'Connecting';
			button.title = 'Starting DOM Command Server...';
			button.classList.remove('ajax', 'icon-active', 'connecting', 'reconnecting');
			button.classList.add('reconnecting');
		}

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
					this.updateStatus('timeout', 'Connection timeout');
					this.scheduleReconnect();
				}
			}, 5000);

			return true;

		} catch (error) {
			this.log('ERROR', `Error creating DOM Command Server: ${error.message}`, error);
			this.updateStatus('error', 'Connection error');
			this.scheduleReconnect();
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

	reconnect() {
		if (this.isManualDisconnect) {
			return;
		}

		this.reconnectAttempts++;
		if (this.reconnectAttempts >= 2) {
			this.log('WARNING', 'Multiple reconnect failures, reloading page...');
			this.startPageReload();
			return;
		}
		// const delay = this.config.reconnectDelay * this.reconnectAttempts;
		const delay = this.config.reconnectDelay;
		this.log('INFO', `Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts})`);
		this.updateStatus('reconnecting', `Reconnecting... (${this.reconnectAttempts})`);

		this.reconnectTimer = setTimeout(() => {
			if (!this.isManualDisconnect) {
				this.connect();
			}
		}, delay);
	}

	scheduleReconnect() {
		if (this.reconnectAttempts < this.config.maxReconnectAttempts) {
			this.reconnect();
		} else {
			this.log('ERROR', `Max reconnect attempts reached (${this.reconnectAttempts})`);
			this.updateStatus('error', 'Max reconnect attempts');
		}
	}

	// @bookmark Event's handler
	handleOpen(event) {
		this.log('INFO', 'DOM Command Server connected successfully');
		this.updateStatus('connected', 'Connected');
		this.reconnectAttempts = 0;

		this.startPingTimer();
		window.setAJAXMode?.(true);  // Changing AJAX blocks set after DOM Command Server mode 
	}

	handleMessage(event) {
		try {
			const data = JSON.parse(event.data);

			if (data.type === 'welcome') {
				this.clientId = data.clientId;
				this.log('INFO', `Connected to server v${data.version}, client ID: ${this.clientId}`);
			
			} else if (data.type === 'pong') {
			
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
					this.logToWebConsole(
						data.timestamp,
						data.level,
						data.message,
						data.source
					);
				}
							
			} else {
				this.log('WARNING', `Unknown message format:`, data);
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
			this.updateStatus('disconnected', 'Periodic');
			return;
		}

		this.startPageReload();
	}

	handleError(error) {
		this.log('ERROR', 'DOM Command Server error', error);
		this.updateStatus('error', 'Connection error');
	}

	// @bookmark Command handler
	processCommands(commands, chunkNum = null, totalChunks = null) {
		if (!Array.isArray(commands)) {
			this.log('ERROR', 'Commands must be an array', commands);
			return;
		}

		const chunkInfo = chunkNum ? ` (chunk ${chunkNum}/${totalChunks})` : '';
		this.log('DEBUG', `Processed ${commands.length} commands ${chunkInfo}`);

		let successCount = 0;
		let errorCount = 0;

		commands.forEach((cmd, index) => {
			if (this.executeAction(cmd)) {
				successCount++;
			} else {
				errorCount++;
			}
		});

		if (errorCount === 0) {
			this.log('INFO', `Processed ${commands.length} commands ${chunkInfo}: ${successCount} sussefull`);
		} else {
			this.log('WARNING', `Processed ${commands.length} commands ${chunkInfo}: ${successCount} sussefull, ${errorCount} with error`);
		}
	}

	executeAction(cmd) {
		if (!cmd || !cmd.action) {
			this.log('ERROR', 'Invalid command: missing action', cmd);
			return false;
		}
		
		//@bookmark Method selector
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

	// @bookmark Methods
	getElement(id) {
		const element = document.getElementById(id);
		if (!element) {
			if (this.config.debugLevel >= 2) {
				this.log('WARNING', `Элемент ${id} не найден`);
			}
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
			if (cmd.class.includes(',')) {
				cmd.class.split(',').forEach(cls => {
					element.classList.add(cls.trim());
				});
			} else {
				element.classList.add(cmd.class);
			}

			if (this.config.debugLevel >= 4) {
				this.log('DEBUG', `In ${cmd.id} added class "${cmd.class}"`);
			}
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
			if (cmd.class.includes(',')) {

				cmd.class.split(',').forEach(cls => {
					element.classList.remove(cls.trim());
				});
				if (this.config.debugLevel >= 4) {
					this.log('DEBUG', `Classes ${cmd.class} removed from ${cmd.id}`);
				}

			} else {

				element.classList.remove(cmd.class);
				if (this.config.debugLevel >= 4) {
					this.log('DEBUG', `Class ${cmd.class} removed from ${cmd.id}`);
				}
			}

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
			
			if (this.config.debugLevel >= 4) {
				this.log('DEBUG', `Set content "${cmd.payload.substring(0, 50)}${cmd.payload.length > 50 ? '...' : ''}" for ${cmd.id}`);
			}
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

			if (startIndex === -1) {
				if (this.config.debugLevel >= 2) {
					this.log('WARNING', `Open key "${beginCond}" not found in ${cmd.id}`);
				}
				return false;
			}

			const endIndex = html.indexOf(endCond, startIndex + beginCond.length);
			if (endIndex === -1) {
				if (this.config.debugLevel >= 2) {
					this.log('WARNING', `Close key "${beginCond}" not found in ${cmd.id}`);
				}
				return false;
			}

			const before = html.substring(0, startIndex + beginCond.length);
			const after = html.substring(endIndex);
			element.innerHTML = before + newContent + after;

			if (this.config.debugLevel >= 4) {
				this.log('DEBUG', `Replaced content for ${cmd.id}: "${newContent}"`);
			}
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
			if (operation === 'add') {
				if (cmd.class.includes(',')) {
					cmd.class.split(',').forEach(cls => {
						parentElement.classList.add(cls.trim());
					});
				} else {
					parentElement.classList.add(cmd.class);
				}
				if (this.config.debugLevel >= 4) {
					this.log('DEBUG', `Added parent class "${cmd.class}" to parent of ${cmd.id}`);
				}
			} else {
				if (cmd.class.includes(',')) {
					cmd.class.split(',').forEach(cls => {
						parentElement.classList.remove(cls.trim());
					});
				} else {
					parentElement.classList.remove(cmd.class);
				}
				if (this.config.debugLevel >= 4) {
					this.log('DEBUG', `Removed parent class "${cmd.class}" from parent of ${cmd.id}`);
				}
			}
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

		if (this.config.debugLevel >= 4) {
			this.log('DEBUG', `Deleted ${cmd.id}`);
		}

		return true;
	}
	
	// @bookmark Операции с дочерними элементами
	/**
	 * Добавление дочернего элемента в элемент с указанным id.
	 * Если у родителя уже есть дочерний элемент с таким id,
	 * игнорировать добавление
	 * Если родитель не найден - логировать ошибку и возвращать false
	 * 
	 * @params
	 *  target: string <id родителя>
	 * 	payload: string <содержимое добавляемого блока>	
	 *  optional type:  string <тип нового элемента, по умолчанию div>
	 * 	optional id: string <id нового элемента, по умолчанию нет>
	 * 	optional class: string <class нового элемента, по умолчанию нет>
	 * 	optional style: string <style нового элемента, по умолчанию нет>
	 * @returns
	 * 	bool	<успешность выполнения>
	 */
	addChild(cmd) {
		
		if (!cmd.target) {
			this.log('ERROR', 'add_child: missing target', cmd);
			return false;
		}

		if (!cmd.payload) {
			this.log('ERROR', 'add_child: missing payload', cmd);
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
				const existingElement = document.getElementById(childId);
				if (existingElement && existingElement.parentElement === parent) {
					return true;
				}
			}

			const elementType = cmd.type || 'div';
			const element = document.createElement(elementType);
			element.innerHTML = cmd.payload;
			if (childId) element.id = childId;
			if (cmd.class !== undefined) element.className = cmd.class;
			if (cmd.style !== undefined) element.style.cssText = cmd.style;
			if (cmd.title !== undefined) element.title = cmd.title;

			parent.appendChild(element);

			if (this.config.debugLevel >= 4) {
				const childInfo = childId ? `"${childId}"` : 'new child';
				this.log('DEBUG', `Added child ${elementType} with id ${childInfo} to ${cmd.target}`);
			}

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
		const children = Array.from(parent.children); // ИЗМЕНЕНО: используем parent.children вместо parent.new_id
		const ignoreClasses = cmd.ignoreClass
			? cmd.ignoreClass.split(',').map(cls => cls.trim())
			: [];

		children.forEach(child => {
			const hasIgnoreClass = ignoreClasses.some(ignoreClass =>
				child.classList.contains(ignoreClass)
			);

			if (!hasIgnoreClass) {
				child.remove();
			}
		});

		return true;
	}

	/**
 * Заменяет существующие классы у всех дочерних элементов (первого уровня вложенности)
 * При отсутствии исходных классов операция игнорируется
 * @param cmd 
 *  id: string <id элемента-родителя>
 *  class: string <классы которые нужно установить (значения разделенные запятыми)>
 *  oldClass: string <классы которые нужно заменить (значения разделенные запятыми)>
 * @returns 
 *  bool <успешность выполнения>
 */
	replaceChildClasses(cmd) {
		if (!cmd.class) {
			this.log('ERROR', 'replaceChildClasses missing class parameter', cmd);
			return false;
		}

		if (!cmd.oldClass) {
			this.log('ERROR', 'replaceChildClasses missing oldClass parameter', cmd);
			return false;
		}

		const element = this.getElement(cmd.id);
		if (!element) {
			if (this.config.debugLevel >= 2) {
				this.log('WARNING', `Element not found for ${cmd.id}`);
			}
			return false;
		}

		try {
			
			const oldClasses = cmd.oldClass.includes(',')
				? cmd.oldClass.split(',').map(oldCls => oldCls.trim())
				: [cmd.oldClass];

			const newClasses = cmd.class.includes(',')
				? cmd.class.split(',').map(newCls => newCls.trim())
				: [cmd.class];

			let elementsModified = 0;
			const childElements = Array.from(element.children);

			childElements.forEach(el => {
				let hasAllOldClasses = true;
				oldClasses.forEach(oldCls => {
					if (!el.classList.contains(oldCls)) {
						hasAllOldClasses = false;
					}
				});

				if (hasAllOldClasses) {

					oldClasses.forEach(oldCls => {
						el.classList.remove(oldCls);
					});

					newClasses.forEach(newCls => {
						el.classList.add(newCls);
					});

					elementsModified++;
				}
			});

			if (this.config.debugLevel >= 4) {
				this.log('DEBUG', `Replaced class(es) "${cmd.oldClass}" with "${cmd.class}" for ${elementsModified} child elements of "${cmd.id}" (total: ${childElements.length})`);
			}

			return elementsModified > 0;

		} catch (error) {
			this.log('ERROR', `Error in replaceChildClasses for ${cmd.id}: ${error.message}`, cmd);
			return false;
		}
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
				if (this.config.debugLevel >= 4) {
					this.log('DEBUG', 'Sent ping to server');
				}
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

	
	updateStatus(status, message) {
		this.status = status;
		this.log('INFO', `Status: ${status} - ${message}`);

		const button = document.getElementById('feedStatus');
		const textSpan = document.getElementById('feedStatusText');

		if (button && textSpan) {
			button.classList.remove(
				'ajax',
				'icon-active',
				'connecting',
				'reconnecting'
			);

			let statusClass = 'ajax';
			let buttonText = 'Periodic';  // Облако
			let tooltip = 'AJAX update mode (slow). Click to try realtime mode.';

			switch (status) {
				case 'connected':
					statusClass = 'icon-active';
					buttonText = 'Realtime';  // WiFi зеленый
					tooltip = 'Real-time updates active';
					break;

				case 'connecting':
					statusClass = 'connecting';
					buttonText = 'Connecting';  // Цепь желтая
					tooltip = 'Connecting to real-time server...';
					break;

				case 'error':
				case 'timeout':
					statusClass = 'reconnecting';
					buttonText = 'Connecting';  // Цепь красная
					tooltip = 'Reconnecting to real-time server...';
					break;

				case 'disconnected':
				default:
					statusClass = 'ajax';
					buttonText = 'Periodic';  // Облако серое
					tooltip = 'AJAX update mode (slow). Click to try realtime mode.';
			}

			button.classList.add(statusClass);
			textSpan.textContent = buttonText;
			button.title = tooltip;
		}
	}
	// @bookmark ЛОГИРОВАНИЕ

	log(level, message, data = null) {
		const levels = {
			'ERROR': 1,
			'WARNING': 2,
			'INFO': 3,
			'DEBUG': 4
		};

		const levelNum = typeof level === 'string' ? levels[level] || 2 : level;

		if (levelNum > this.config.debugLevel) {
			return;
		}

		const timestamp = new Date().toISOString();

		if (this.config.debugConsole) {
			const prefix = `[DOM CE Client ${level}]`;
			
			switch (levelNum) {
				case 0:
					console.error(prefix, message, data || '');
					break;
				case 1:
					console.warn(prefix, message, data || '');
					break;
				case 2:
					console.info(prefix, message, data ? data : '');
					break;
				case 3:
					console.debug(prefix, message, data || '');
					break;
			}
		}

		if (this.config.debugWebConsole) {
			this.logToWebConsole(timestamp, level, message, data);
		}
	}

	logToWebConsole(timestamp, level, message, data = null) {
		const debugConsole = document.getElementById('debugLog');
		if (!debugConsole) {
			return;
		}

		const time = new Date(timestamp).toLocaleTimeString();
		const levelClass = `debug-${level.toLowerCase()}`;

		let fullMessage = message;
		let sender = "[DOM CE Client v0.4.24]";
		if (typeof data === 'string') {
			sender = `[${data}]`;
			
		} else if (data && typeof data === 'object') {
			if (data.source) {
				sender = `[${data.source}]`;
			} else {				
				fullMessage += ' ' + JSON.stringify(data);
			}
		} else if (data) {			
			fullMessage += ' ' + String(data);
		}


		const messageHtml = `
			<div class="debug-entry ${levelClass}">
				<span class="debug-time">[${time}]</span>
				<span class="debug-source">${sender}</span>
				<span class="debug-level">[${level}]</span>
				<span class="debug-message">${this.escapeHtml(fullMessage)}</span>
			</div>`;

		debugConsole.innerHTML = messageHtml + debugConsole.innerHTML;

		// Max debug messages
		const entries = debugConsole.querySelectorAll('.debug-entry');
		if (entries.length > 100) {
			for (let i = 100; i < entries.length; i++) {
				entries[i].remove();
			}
		}
	}

	escapeHtml(text) {
		if (typeof text !== 'string') {
			text = String(text);
		}
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	// @bookmark API
	getStatus() {
		return {
			status: this.status,
			wsState: this.ws ? this.ws.readyState : null,
			clientId: this.clientId,
			reconnectAttempts: this.reconnectAttempts,
			config: this.config
		};
	}

	// Manual commands execute (debug)
	executeTestCommand(command) {
		return this.executeAction(command);
	}
}

// @bookmark Init
document.addEventListener('DOMContentLoaded', () => {

	const wsConfig = window.DASHBOARD_CONFIG?.websocket;

	if (!wsConfig) {
		console.error('Configuration not found in window.DASHBOARD_CONFIG.websocket');
		console.warn('DOM Command Executor v0.4.24 will use default settings');
	}

	window.dashboardWSClient = new DashboardWebSocketClientV4(wsConfig);

	window.connectWebSocket = () => {
		if (window.dashboardWSClient) window.dashboardWSClient.connect();
	};

	window.disconnectWebSocket = () => {
		if (window.dashboardWSClient) window.dashboardWSClient.disconnect();
	};

	window.getWebSocketStatus = () => {
		if (window.dashboardWSClient) return window.dashboardWSClient.getStatus();
		return { status: 'no_client' };
	};

	console.log('Dashboard DOM Command Executor Client v0.4.24 initialized with config:', wsConfig || 'default');
});