/**
 * @filesource /scripts/exct/dashboard_ws_client.0.4.3.js
 * @version 0.4.3
 * @date 2026.01.22
 * @author vladimir@tsurkanenko.ru
 * @description WebSocket клиент с полной поддержкой системы команд DOM v4.0
 * 
 * @since 4.0
 * - Полная поддержка родительских операций
 * - Поддержка всех действий: add_class, remove_class, set_content, replace_content
 * - Добавлены: add_parent_class, remove_parent_class, replace_parent_content
 * - Поддержка массивов ID
 * - Совместимость с сервером dashboard_ws_server.4.0.js
 * @since 0.4.1
 * @todo Неиспользуемые методы клиента:
 * Удален `handleAddContent` - `add_content`
 * Удален `handleRemoveContent` - `remove_content`
 * Удален  `handleParentContent` (оба варианта) - `add_parent_content` и `replace_parent_content`
 * Удален  `handleAddParentContent` - специальная логика для `add_content` с parent_id
 * Удален  `handleChildClasses`
 * Добавлен `replaceChildClasses`
 * * @todo Вернуть получение конфига из вызывающего скрипта
 * @since 0.4.2
 * Добавлен `replace_class`
 */

class DashboardWebSocketClientV4 {
	constructor(config = {}) {
			// Конфигурация
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

		// Состояние
		this.ws = null;
		this.status = 'disconnected';
		this.reconnectAttempts = 0;
		this.isManualDisconnect = false;
		this.clientId = null;

		// Таймеры
		this.pingTimer = null;
		this.reconnectTimer = null;

		// Кэш родительских элементов
		this.parentCache = new Map();

		// Инициализация
		this.init();
	}

	// @bookmark ИНИЦИАЛИЗАЦИЯ

	init() {
		this.log('INFO', 'Dashboard WebSocket Client v0.4.1 initialized');

		// Создаём кнопку статуса
		this.createStatusButton();

		// Автоподключение
		if (this.config.autoConnect) {
			setTimeout(() => this.connect(), 1000);
		}
	}

	// @bookmark  КНОПКА СТАТУСА

	createStatusButton() {
		// Удаляем старую кнопку если есть
		const oldButton = document.getElementById('websocketStatus');
		if (oldButton) oldButton.remove();

		const navbar = document.querySelector('.navbar');
		if (!navbar) {
			setTimeout(() => this.createStatusButton(), 100);
			return;
		}

		const button = document.createElement('a');
		button.id = 'websocketStatus';
		button.href = 'javascript:void(0)';
		button.className = 'menuwebsocket menuwebsocket-disconnected';
		button.title = 'WebSocket: Offline. Click to connect';

		const textSpan = document.createElement('span');
		textSpan.id = 'websocketStatusText';
		textSpan.textContent = 'WS';
		button.appendChild(textSpan);

		button.addEventListener('click', (e) => {
			e.preventDefault();
			this.handleStatusButton();
		});

		navbar.appendChild(button);
		this.log('INFO', 'WebSocket status button created');
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
		this.log('INFO', 'Starting page reload to launch WebSocket server...');

		const button = document.getElementById('websocketStatus');
		const textSpan = document.getElementById('websocketStatusText');

		if (button && textSpan) {
			textSpan.textContent = 'WS...';
			button.title = 'Starting WebSocket server...';
			button.classList.remove('menuwebsocket-disconnected', 'menuwebsocket-error');
			button.classList.add('menuwebsocket-connecting');
		}

		setTimeout(() => {
			window.location.reload();
		}, 300);
	}

	// @bookmark УПРАВЛЕНИЕ СОЕДИНЕНИЕМ

	connect() {
		this.isManualDisconnect = false;

		if (this.ws && this.ws.readyState === WebSocket.OPEN) {
			this.ws.close(1000, 'Reconnecting');
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

			// Таймаут подключения
			setTimeout(() => {
				if (this.ws && this.ws.readyState === WebSocket.CONNECTING) {
					this.log('ERROR', 'Connection timeout (5s)');
					this.ws.close();
					this.updateStatus('timeout', 'Connection timeout');
					this.scheduleReconnect();
				}
			}, 5000);

			return true;

		} catch (error) {
			this.log('ERROR', `Error creating WebSocket: ${error.message}`, error);
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
		const delay = this.config.reconnectDelay * this.reconnectAttempts;

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

	// @bookmark ОБРАБОТЧИКИ СОБЫТИЙ WS

	handleOpen(event) {
		this.log('INFO', 'WebSocket connected successfully');
		this.updateStatus('connected', 'Connected');
		this.reconnectAttempts = 0;

		this.startPingTimer();
	}

	handleMessage(event) {
		try {
			const data = JSON.parse(event.data);

			// if (this.config.debugLevel >= 3) {
			// 	this.log('DEBUG', `Received message:`, data);
			// }

			if (data.type === 'welcome') {
				this.clientId = data.clientId;
				this.log('INFO', `Connected to server v${data.version}, client ID: ${this.clientId}`);
			
			} else if (data.type === 'pong') {
				// Игнорируем pong
			
			} else if (data.type === 'dom_commands' && Array.isArray(data.commands)) {
				this.processCommands(data.commands, data.chunk, data.chunks);
			
			} else if (data.type === 'log_message') {
				const logLevels = {
					'ERROR': 1,
					'WARNING': 2,
					'INFO': 3,
					'DEBUG': 4
				};
				const messageLevel = logLevels[data.level] || 3;
				// Получаем уровень отладки клиента
				const clientDebugLevel = this.config.debugLevel || 2;

				// Выводим только если уровень сообщения <= уровню клиента
				if (messageLevel <= clientDebugLevel) {
					this.logToWebConsole(
						data.timestamp,
						data.level,
						data.message,
						data.source
					);
				}
				
				
			} else if (Array.isArray(data)) {
				// Прямой массив команд (для совместимости)
				this.processCommands(data);
			} else {
				this.log('WARNING', `Unknown message format:`, data);
			}

		} catch (error) {
			this.log('ERROR', `Error parsing message: ${error.message}`, event.data);
		}
	}

	handleClose(event) {
		this.log('INFO', `WebSocket closed: code=${event.code}, reason=${event.reason}, clean=${event.wasClean}`);

		this.clearTimers();

		if (this.isManualDisconnect) {
			this.log('INFO', 'Manual disconnect confirmed');
			return;
		}

		if (event.code !== 1000) {
			this.scheduleReconnect();
		} else {
			this.updateStatus('disconnected', 'Disconnected');
		}
	}

	handleError(error) {
		this.log('ERROR', 'WebSocket error', error);
		this.updateStatus('error', 'Connection error');
	}

	// @bookmark ОБРАБОТКА КОМАНД

	processCommands(commands, chunkNum = null, totalChunks = null) {
		if (!Array.isArray(commands)) {
			this.log('ERROR', 'Commands must be an array', commands);
			return;
		}

		const chunkInfo = chunkNum ? ` (chunk ${chunkNum}/${totalChunks})` : '';
		this.log('DEBUG', `Обработка ${commands.length} команд${chunkInfo}`);

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
			this.log('INFO', `Обработал ${commands.length} команд ${chunkInfo}: ${successCount} успешно`);
		} else {
			this.log('WARNING', `Обработал ${commands.length} команд${chunkInfo}: ${successCount} успешно, ${errorCount} с ошибкой`);
		}
	}

	executeAction(cmd) {
		if (!cmd || !cmd.action) {
			this.log('ERROR', 'Invalid command: missing action', cmd);
			return false;
		}
		// Если action set_content_by_class и есть targetClass, то ID не обязателен
		if (cmd.action !== 'set_content_by_class' && !cmd.id) {
			this.log('ERROR', 'Invalid command: missing id', cmd);
			return false;
		}

		// if (!cmd.id) {
		// 	this.log('ERROR', 'Invalid command: missing id', cmd);
		// 	return false;
		// }
		//@bookmark Селектор методов
		switch (cmd.action) {
			
			// Действия над элементом DOM
			case 'add_class':
				return this.handleAddClass(cmd);
			
			case 'remove_class':
				return this.handleRemoveClass(cmd);
			
			case 'remove_element':
				return this.handleRemoveElement(cmd);
			
			case 'set_content':
				return this.handleSetContent(cmd);
			
			case 'replace_class':  
				return this.handleReplaceClass(cmd);
			
							
			case 'replace_content':
				return this.handleReplaceContent(cmd);
			
			case 'set_content_by_class':
				return this.handleSetContentByClass(cmd);
			

			// Действие с родительским элементом
			case 'add_parent_class':
				return this.handleParentClass(cmd, 'add');
			
			case 'remove_parent_class':
				return this.handleParentClass(cmd, 'remove');
			
			

			
			// Действие с дочерними элементами
			case 'add_child':
				return this.addChild(cmd);
			
			case 'remove_child':
				return this.removeChild(cmd);		
			
			case 'replace_child_classes':

				return this.replaceChildClasses(cmd);
			

			// Специальные действия			
			case 'set_checkbox_state':
				return this.handleSetCheckboxState(cmd);
			
			default:
				this.log('ERROR', `Unknown action: ${cmd.action}`, cmd);
				return false;
		}
	}

	// @bookmark DOM ОПЕРАЦИИ

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
		// Проверяем кэш
		if (this.parentCache.has(childId)) {
			return this.parentCache.get(childId);
		}

		const childElement = this.getElement(childId);
		if (!childElement) return null;

		const parentElement = childElement.parentElement;
		if (parentElement) {
			// Кэшируем
			this.parentCache.set(childId, parentElement);
			return parentElement;
		}

		return null;
	}

	clearParentCache(childId = null) {
		if (childId) {
			this.parentCache.delete(childId);
		} else {
			this.parentCache.clear();
		}
	}

	// @bookmark установка содержимого по классу
	handleSetContentByClass(cmd) {
		if (cmd.payload === undefined) {
			this.log('ERROR', 'set_content_by_class missing payload', cmd);
			return false;
		}

		if (!cmd.targetClass) {
			this.log('ERROR', 'set_content_by_class missing targetClass parameter', cmd);
			return false;
		}

		try {
			// Поиск всех элементов с указанным классом
			const selector = `.${cmd.targetClass}`;
			const elements = document.querySelectorAll(selector);

			if (!elements || elements.length === 0) {
				if (this.config.debugLevel >= 2) {
					this.log('WARNING', `No elements found with class "${cmd.targetClass}"`);
				}
				return false;
			}

			// Определяем, как обрабатывать multipleElements (по умолчанию: false)
			const multipleElements = cmd.multipleElements === true;

			// Определяем индекс элемента (если указан)
			const elementIndex = cmd.elementIndex !== undefined ? parseInt(cmd.elementIndex) : 0;

			let elementsProcessed = 0;

			if (multipleElements) {
				// Обрабатываем все элементы
				elements.forEach((element, index) => {
					try {
						element.innerHTML = cmd.payload;
						elementsProcessed++;

						if (this.config.debugLevel >= 4) {
							this.log('DEBUG', `Set content for element #${index} with class "${cmd.targetClass}"`);
						}
					} catch (error) {
						this.log('ERROR', `Error setting content for element #${index} with class "${cmd.targetClass}": ${error.message}`);
					}
				});
			} else {
				// Обрабатываем конкретный элемент по индексу
				if (elementIndex < 0 || elementIndex >= elements.length) {
					this.log('ERROR', `Element index ${elementIndex} out of range (0-${elements.length - 1}) for class "${cmd.targetClass}"`);
					return false;
				}

				const element = elements[elementIndex];
				element.innerHTML = cmd.payload;
				elementsProcessed = 1;

				if (this.config.debugLevel >= 4) {
					this.log('DEBUG', `Set content for element #${elementIndex} with class "${cmd.targetClass}"`);
				}
			}

			if (elementsProcessed > 0) {
				const countText = multipleElements ? `${elementsProcessed} elements` : `element #${elementIndex}`;
				const payloadPreview = cmd.payload.length > 50 ?
					cmd.payload.substring(0, 50) + '...' : cmd.payload;

				this.log('INFO', `Set content "${payloadPreview}" for ${countText} with class "${cmd.targetClass}"`);
				return true;
			}

			return false;

		} catch (error) {
			this.log('ERROR', `Error in set_content_by_class for class "${cmd.targetClass}": ${error.message}`, cmd);
			return false;
		}
	}

	// @bookmark ОБРАБОТКА КОНКРЕТНЫХ ДЕЙСТВИЙ

	handleAddClass(cmd) {
		if (!cmd.class) {
			this.log('ERROR', 'add_class missing class parameter', cmd);
			return false;
		}

		const element = this.getElement(cmd.id);
		if (!element) return false;

		try {
			if (cmd.class.includes(',')) {
				// Несколько классов через запятую
				cmd.class.split(',').forEach(cls => {
					element.classList.add(cls.trim());
				});
			} else {
				// Один класс
				element.classList.add(cmd.class);
			}

			if (this.config.debugLevel >= 4) {
				this.log('DEBUG', `В ${cmd.id} добавлен класс "${cmd.class}"`);
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
				// Несколько классов через запятую
				cmd.class.split(',').forEach(cls => {
					element.classList.remove(cls.trim());
				});
				if (this.config.debugLevel >= 4) {
					this.log('DEBUG', `Из ${cmd.id} удалены классы ${cmd.class}`);
				}

			} else {
				// Один класс
				element.classList.remove(cmd.class);
				if (this.config.debugLevel >= 4) {
					this.log('DEBUG', `Из ${cmd.id} удален класс ${cmd.class}`);
				}
			}

			return true;

		} catch (error) {
			this.log('ERROR', `Error removing class from ${cmd.id}: ${error.message}`, cmd);
			return false;
		}
	}

	handleReplaceClass(cmd) {
		// Проверяем обязательные параметры
		if (!cmd.old_class) {
			this.log('ERROR', 'replace_class missing old_class parameter', cmd);
			return false;
		}

		if (!cmd.new_class) {
			this.log('ERROR', 'replace_class missing new_class parameter', cmd);
			return false;
		}

		if (!cmd.id) {
			this.log('ERROR', 'replace_class missing id parameter', cmd);
			return false;
		}

		const element = this.getElement(cmd.id);
		if (!element) return false;

		try {
			
			const oldClass = cmd.old_class.trim();
			const newClass = cmd.new_class.trim();

			// Проверяем, есть ли старый класс
			if (!element.classList.contains(oldClass)) {
				// Если старого класса нет - игнорируем операцию
				if (this.config.debugLevel >= 2) {
					this.log('WARNING', `Element ${cmd.id} doesn't have old class: "${oldClass}"`);
				}
				return true; // Возвращаем true, так как это не ошибка, а ожидаемое поведение
			}

			// Удаляем старый класс и добавляем новый
			element.classList.remove(oldClass);
			element.classList.add(newClass);

			// Логируем результат
			if (this.config.debugLevel >= 3) {
				this.log('INFO', `В  ${cmd.id} класс "${oldClass}" изменен на "${newClass}"`);
			}

			return true;

		} catch (error) {
			this.log('ERROR', `Error replacing class in ${cmd.id}: ${error.message}`, cmd);
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
				this.log('DEBUG', `В ${cmd.id} установлено новое содержимое "${cmd.payload.substring(0, 50)}${cmd.payload.length > 50 ? '...' : ''}"`);
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
					this.log('WARNING', `В ${cmd.id} не найдена открывающая последовательность "${beginCond}" `);
				}
				return false;
			}

			const endIndex = html.indexOf(endCond, startIndex + beginCond.length);
			if (endIndex === -1) {
				if (this.config.debugLevel >= 2) {
					this.log('WARNING', `В ${cmd.id} не найдена закрывающая последовательность "${endCond}"`);
				}
				return false;
			}

			const before = html.substring(0, startIndex + beginCond.length);
			const after = html.substring(endIndex);
			element.innerHTML = before + newContent + after;

			if (this.config.debugLevel >= 4) {
				this.log('DEBUG', `Заменил содержимое в ${cmd.id} на "${newContent}"`);
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

	handleReplaceParentContent(cmd) {
		if (!Array.isArray(cmd.payload) || cmd.payload.length !== 3) {
			this.log('ERROR', 'replace_parent_content requires payload array of 3 items', cmd);
			return false;
		}

		const parentElement = this.getParentElement(cmd.id);
		if (!parentElement) {
			if (this.config.debugLevel >= 2) {
				this.log('WARNING', `Не найдено дочерних элементов у ${cmd.id}`);
			}
			return false;
		}

		try {
			const [beginCond, endCond, newContent] = cmd.payload;
			const html = parentElement.innerHTML;
			const startIndex = html.indexOf(beginCond);

			if (startIndex === -1) {
				if (this.config.debugLevel >= 2) {
					this.log('WARNING', `Begin condition not found in parent: "${beginCond}" for ${cmd.id}`);
				}
				return false;
			}

			const endIndex = html.indexOf(endCond, startIndex + beginCond.length);
			if (endIndex === -1) {
				if (this.config.debugLevel >= 2) {
					this.log('WARNING', `End condition not found in parent: "${endCond}" for ${cmd.id}`);
				}
				return false;
			}

			const before = html.substring(0, startIndex + beginCond.length);
			const after = html.substring(endIndex);
			parentElement.innerHTML = before + newContent + after;

			if (this.config.debugLevel >= 4) {
				this.log('DEBUG', `Replaced parent content for ${cmd.id} with "${newContent}"`);
			}
			return true;

		} catch (error) {
			this.log('ERROR', `Error replacing parent content for ${cmd.id}: ${error.message}`, cmd);
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
			this.log('DEBUG', `Удален ${cmd.id}`);
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
		// Проверяем обязательные параметры
		if (!cmd.target) {
			this.log('ERROR', 'add_child: не указан целевой родитель (target)', cmd);
			return false;
		}

		if (!cmd.payload) {
			this.log('ERROR', 'add_child: не указано содержимое (payload)', cmd);
			return false;
		}

		// Находим родительский элемент
		const parent = document.getElementById(cmd.target);
		if (!parent) {
			this.log('ERROR', `add_child: родительский элемент "${cmd.target}" не найден`, cmd);
			return false;
		}

		try {
			// Определяем ID нового элемента
			const childId = cmd.id || null;

			// Если ID указан, ищем существующий элемент
			if (childId) {
				const existingElement = document.getElementById(childId);

				// Если элемент существует и его родитель совпадает с целевым
				if (existingElement && existingElement.parentElement === parent) {
					return true;
				}
			}

			// Создаем новый элемент
			const elementType = cmd.type || 'div';
			const element = document.createElement(elementType);
			element.innerHTML = cmd.payload;
			if (childId) element.id = childId;
			if (cmd.class !== undefined) element.className = cmd.class;
			if (cmd.style !== undefined) element.style.cssText = cmd.style;
			if (cmd.title !== undefined) element.title = cmd.title;

			parent.appendChild(element);

			if (this.config.debugLevel >= 4) {
				const childInfo = childId ? `"${childId}"` : 'новый элемент';
				this.log('DEBUG', `В "${cmd.target}" добавлен дочерний "${elementType}" с id "${childInfo}"`);
			}

			return true;

		} catch (error) {
			this.log('ERROR', `Ошибка в add_child: ${error.message}`, cmd);
			return false;
		}
	}

	// Удаляет все дочерние элементы id, если у них нет классов перечисленных в ignoreClass
	removeChild(cmd) {
		if (!cmd.id) return false;

		const parent = document.getElementById(cmd.id);
		if (!parent) return false;

		// Получаем все дочерние элементы
		const children = Array.from(parent.children); // ИЗМЕНЕНО: используем parent.children вместо parent.new_id

		// Преобразуем ignoreClass в массив классов
		const ignoreClasses = cmd.ignoreClass
			? cmd.ignoreClass.split(',').map(cls => cls.trim())
			: [];

		// Удаляем только те элементы, у которых НЕТ НИ ОДНОГО из ignoreClasses
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
			// Подготавливаем массивы старых и новых классов
			const oldClasses = cmd.oldClass.includes(',')
				? cmd.oldClass.split(',').map(oldCls => oldCls.trim())
				: [cmd.oldClass];

			const newClasses = cmd.class.includes(',')
				? cmd.class.split(',').map(newCls => newCls.trim())
				: [cmd.class];

			let elementsModified = 0;

			// Обрабатываем только дочерние элементы первого уровня
			const childElements = Array.from(element.children);

			childElements.forEach(el => {
				// Проверяем, есть ли ВСЕ старые классы
				let hasAllOldClasses = true;
				oldClasses.forEach(oldCls => {
					if (!el.classList.contains(oldCls)) {
						hasAllOldClasses = false;
					}
				});

				if (hasAllOldClasses) {
					// Удаляем ВСЕ старые классы
					oldClasses.forEach(oldCls => {
						el.classList.remove(oldCls);
					});

					// Добавляем ВСЕ новые классы
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
	

	handleSetCheckboxState(cmd) {

		const element = document.getElementById(cmd.id);
		if (!element) return false;

		const shouldBeChecked = String(cmd.state).toLowerCase() === 'on';

		// Просто меняем состояние чекбокса
		element.checked = shouldBeChecked;
		// Это чисто визуальная синхронизация от сервера
		// this.log('DEBUG', `Checkbox ${cmd.id} synced to: ${shouldBeChecked ? 'ON' : 'OFF'}`);
		return true;
	}
	
	
	// @bookmark ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ

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

	// @bookmark ОБНОВЛЕНИЕ СТАТУСА

	updateStatus(status, message) {
		this.status = status;
		this.log('INFO', `Status: ${status} - ${message}`);

		const button = document.getElementById('websocketStatus');
		const textSpan = document.getElementById('websocketStatusText');

		if (button && textSpan) {
			// Удаляем все классы состояния
			button.classList.remove(
				'menuwebsocket-disconnected',
				'menuwebsocket-connected',
				'menuwebsocket-connecting',
				'menuwebsocket-error'
			);

			// Добавляем соответствующий класс
			let statusClass = 'menuwebsocket-disconnected';
			let buttonText = 'WS!';
			let tooltip = `WebSocket: ${message}. `;

			switch (status) {
				case 'connected':
					statusClass = 'menuwebsocket-connected';
					buttonText = 'OK';
					tooltip += 'Click to disconnect';
					break;

				case 'connecting':
				case 'reconnecting':
					statusClass = 'menuwebsocket-connecting';
					buttonText = 'TRY';
					tooltip += 'Connecting...';
					break;

				case 'error':
					statusClass = 'menuwebsocket-error';
					buttonText = 'ERR';
					tooltip += 'Click to reload page and restart';
					break;

				case 'timeout':
					statusClass = 'menuwebsocket-error';
					buttonText = 'TO';
					tooltip += 'Connection timeout. Click to reload';
					break;

				case 'disconnected':
				default:
					statusClass = 'menuwebsocket-disconnected';
					buttonText = 'WS!';
					tooltip += 'Offline. Click to reload page and start server';
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

		// Проверяем уровень лога
		if (levelNum > this.config.debugLevel) {
			return;
		}

		const timestamp = new Date().toISOString();

		// Лог в консоль браузера
		if (this.config.debugConsole) {
			const prefix = `[WS Client ${level}]`;
			
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

		// Лог в debug консоль на странице
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
		let sender = "[WS Client v4.1]";

		// СНАЧАЛА определяем отправителя
		if (typeof data === 'string') {
			sender = `[${data}]`; // например: [WS Server v4]
			// Если data - это имя отправителя, НЕ добавляем его к сообщению!
		} else if (data && typeof data === 'object') {
			if (data.source) {
				sender = `[${data.source}]`;
			} else {
				// Только если это не source, а реальные данные - добавляем к сообщению
				fullMessage += ' ' + JSON.stringify(data);
			}
		} else if (data) {
			// Любые другие данные добавляем к сообщению (числа, булевы значения и т.д.)
			fullMessage += ' ' + String(data);
		}


		const messageHtml = `
			<div class="debug-entry ${levelClass}">
				<span class="debug-time">[${time}]</span>
				<span class="debug-source">${sender}</span>
				<span class="debug-level">[${level}]</span>
				<span class="debug-message">${this.escapeHtml(fullMessage)}</span>
			</div>
		`;

		debugConsole.innerHTML = messageHtml + debugConsole.innerHTML;

		// Ограничиваем количество сообщений
		const entries = debugConsole.querySelectorAll('.debug-entry');
		if (entries.length > 500) {
			for (let i = 500; i < entries.length; i++) {
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

	// @bookmark PUBLIC API

	getStatus() {
		return {
			status: this.status,
			wsState: this.ws ? this.ws.readyState : null,
			clientId: this.clientId,
			reconnectAttempts: this.reconnectAttempts,
			parentCacheSize: this.parentCache.size,
			config: this.config
		};
	}

	// Метод для ручного выполнения команд (отладка)
	executeTestCommand(command) {
		return this.executeAction(command);
	}
}

// @bookmark ИНИЦИАЛИЗАЦИЯ

document.addEventListener('DOMContentLoaded', () => {
	// Берем конфигурацию ТОЛЬКО из PHP, без резервного варианта
	const wsConfig = window.DASHBOARD_CONFIG?.websocket;

	if (!wsConfig) {
		console.error('WebSocket configuration not found in window.DASHBOARD_CONFIG.websocket');
		console.warn('Dashboard WebSocket Client v4.0 will use default settings');
	}

	// Создаем клиент с конфигурацией из PHP (или undefined для дефолтов)
	window.dashboardWSClient = new DashboardWebSocketClientV4(wsConfig);

	// Глобальные функции для отладки (@todo Удалить после отладки)
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

	// Функция для тестирования команд
	window.sendTestCommand = (command) => {
		if (window.dashboardWSClient) {
			const success = window.dashboardWSClient.executeTestCommand(command);
			console.log('Test command result:', success ? 'SUCCESS' : 'FAILED');
			return success;
		}
		console.error('WebSocket client not available');
		return false;
	};

	// Примеры тестовых команд
	window.testCommands = {
		addClass: (id, className) => window.sendTestCommand({
			id: id,
			action: 'add_class',
			class: className
		}),
		removeClass: (id, className) => window.sendTestCommand({
			id: id,
			action: 'remove_class',
			class: className
		}),
		setContent: (id, content) => window.sendTestCommand({
			id: id,
			action: 'set_content',
			payload: content
		}),
		addParentClass: (id, className) => window.sendTestCommand({
			id: id,
			action: 'add_parent_class',
			class: className
		}),
		removeParentClass: (id, className) => window.sendTestCommand({
			id: id,
			action: 'remove_parent_class',
			class: className
		})
	};

	console.log('Dashboard WebSocket Client v4.1 initialized with config:', wsConfig || 'default');
});