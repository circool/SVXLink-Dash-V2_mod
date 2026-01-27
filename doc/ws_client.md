# WebSocket Client Documentation v4.2

## Overview

The WebSocket client version 4.2 provides real-time bidirectional communication between the browser and the SvxLink Dashboard server. The client automatically connects to the WebSocket server, handles DOM update commands, and supports visual connection status indication.

## Architecture

### Core Components

- **DashboardWebSocketClientV4** - Main client class
- **Connection Manager** - Manages connection and reconnections
- **DOM Command Handler** - Executes interface update commands
- **Status Interface** - Visual indicator button in navigation
- **Logging System** - Logs to web console

### Connection States

- **connected** - Successfully connected to server
- **connecting** - Connection in progress
- **reconnecting** - Reconnection after disconnection
- **disconnected** - Manually disconnected
- **error** - Connection error
- **timeout** - Connection timeout

## Configuration

### Default Parameters

```javascript
{
  host: window.location.hostname,  // Server host
  port: 8080,                      // WebSocket port
  autoConnect: true,               // Auto-connect on load
  reconnectDelay: 3000,            // Reconnection delay (ms)
  debugLevel: 2,                   // Logging level: 1=ERROR, 2=WARNING, 3=INFO, 4=DEBUG
  debugWebConsole: true,           // Logs to web console
  debugConsole: false,             // Logs to browser console
  maxReconnectAttempts: 5,         // Max reconnection attempts
  pingInterval: 30000              // Ping interval (ms)
}
```

### Obtaining Configuration

The client expects configuration in a global variable:
```javascript
window.DASHBOARD_CONFIG = {
  websocket: {
    host: "192.168.1.100",
    port: 8080,
    // ... other parameters
  }
};
```

## Class Methods

### Initialization and Connection Management

`constructor(config = {})`

**Purpose:** Creates a WebSocket client instance.

**Parameters:**
- `config` (Object) - Client configuration

**Actions:**
- Initializes configuration with default values
- Creates status button in navigation
- Auto-connects (if `autoConnect: true`)

---

`init()`

**Purpose:** Initializes the client.

**Actions:**
- Creates WebSocket status button
- Auto-connects with 1 second delay
- Logs initialization information

---

`connect()`

**Purpose:** Establishes connection with WebSocket server.

**Actions:**
- Resets manual disconnect flag
- Closes existing connection (if any)
- Creates new WebSocket connection
- Sets event handlers
- Configures connection timeout (5 seconds)
- Updates status to "connecting"

**Returns:** `true` on successful connection attempt, `false` on error

---

`disconnect()`

**Purpose:** Manually disconnects from server.

**Actions:**
- Sets manual disconnect flag
- Closes WebSocket connection with code 1000
- Clears timers
- Updates status to "disconnected"
- Logs action

---

`reconnect()`

**Purpose:** Automatically reconnects after connection loss.

**Actions:**
- Checks for manual disconnect (abort)
- Increments attempt counter
- Calculates exponential delay
- Logs reconnection information
- Updates status to "reconnecting"
- Starts timer for new connection

---

`scheduleReconnect()`

**Purpose:** Schedules next reconnection attempt.

**Actions:**
- Checks maximum attempt limit
- Calls `reconnect()` or sets "error" status

### WebSocket Event Handlers

`handleOpen(event)`

**Purpose:** Handles successful connection.

**Actions:**
- Logs successful connection
- Resets reconnection attempt counter
- Updates status to "connected"
- Starts ping timer

---

`handleMessage(event)`

**Purpose:** Processes incoming messages from server.

**Handles:**
- **welcome** - Welcome message with version and client ID
- **pong** - Response to ping requests
- **dom_commands** - DOM update commands
- **log_message** - Server logs
- **command array** - Direct command passing (for compatibility)

**Actions:**
- Parses JSON data
- Routes messages by type
- Filters logs by debug level
- Processes DOM commands via `processCommands()`

---

`handleClose(event)`

**Purpose:** Handles connection closing.

**Actions:**
- Logs close code and reason
- Clears timers
- Checks for manual disconnect
- Starts reconnection (if not manual)

---

`handleError(error)`

**Purpose:** Handles connection errors.

**Actions:**
- Logs error
- Updates status to "error"

### DOM Command Processing

`processCommands(commands, chunkNum, totalChunks)`

**Purpose:** Main method for processing command batches.

**Parameters:**
- `commands` (Array) - Array of commands to execute
- `chunkNum` (Number) - Current chunk number (optional)
- `totalChunks` (Number) - Total number of chunks (optional)

**Actions:**
- Validates input data
- Logs chunk information
- Executes commands sequentially via `executeAction()`
- Counts successful and failed operations
- Logs execution results

---

`executeAction(cmd)`

**Purpose:** Executes a single command.

**Parameters:**
- `cmd` (Object) - Command object

**Actions:**
- Validates required fields
- Routes by action type
- Calls corresponding handler method
- Returns execution result

**Returns:** `true` on successful execution, `false` on error

### Core DOM Operations

`handleAddClass(cmd)`

**Purpose:** Adds class(es) to an element.

**Command parameters:**
- `id` (String) - Target element ID
- `class` (String) - Class or comma-separated class list

**Actions:**
- Finds element by ID
- Adds one or multiple classes
- Logs operation

---

`handleRemoveClass(cmd)`

**Purpose:** Removes class(es) from an element.

**Command parameters:**
- `id` (String) - Target element ID
- `class` (String) - Class or comma-separated class list

**Actions:**
- Finds element by ID
- Removes one or multiple classes
- Logs operation

---

`handleReplaceClass(cmd)`

**Purpose:** Replaces one class with another.

**Command parameters:**
- `id` (String) - Target element ID
- `old_class` (String) - Old class to replace
- `new_class` (String) - New class

**Actions:**
- Finds element by ID
- Checks if old class exists
- Replaces old class with new
- Logs operation

---

`handleSetContent(cmd)`

**Purpose:** Completely replaces element content.

**Command parameters:**
- `id` (String) - Target element ID
- `payload` (String) - New HTML content

**Actions:**
- Finds element by ID
- Sets new content via `innerHTML`
- Logs operation (truncates long content)

---

`handleReplaceContent(cmd)`

**Purpose:** Partially replaces element content.

**Command parameters:**
- `id` (String) - Target element ID
- `payload` (Array) - Three-element array:
  + `beginCond` - Opening sequence for search
  + `endCond` - Closing sequence for search
  + `newContent` - New content to insert

**Actions:**
- Finds element by ID
- Finds positions of opening and closing sequences
- Replaces content between sequences
- Logs operation

---

`handleSetContentByClass(cmd)`

**Purpose:** Sets content for elements by class.

**Command parameters:**
- `targetClass` (String) - Class for finding elements
- `payload` (String) - New HTML content
- `multipleElements` (Boolean) - Process all elements or one (default false)
- `elementIndex` (Number) - Element index when `multipleElements: false`

**Actions:**
- Finds all elements with specified class
- Determines processing mode (one/all elements)
- Sets content for selected elements
- Logs number of processed elements

---

`handleRemoveElement(cmd)`

**Purpose:** Removes element from DOM.

**Command parameters:**
- `id` (String) - ID of element to remove

**Actions:**
- Finds element by ID
- Calls `remove()` method
- Logs operation

### Parent Operations

`handleParentClass(cmd, operation)`

**Purpose:** Adds or removes class from parent element.

**Command parameters:**
- `id` (String) - Child element ID
- `class` (String) - Class or comma-separated class list
- `action` (String) - Operation type: `add_parent_class` or `remove_parent_class`

**Actions:**
- Finds parent element via cache
- Adds or removes classes
- Logs operation

---

`handleReplaceParentContent(cmd)`
**Purpose:** Partially replaces parent element content.

**Command parameters:**
- `id` (String) - Child element ID
- `payload` (Array) - Three-element array (similar to `handleReplaceContent`)

**Actions:**
- Finds parent element via cache
- Finds and replaces content between sequences
- Logs operation

### Child Element Operations

`addChild(cmd)`

**Purpose:** Adds a child element.

**Command parameters:**
- `target` (String) - Parent element ID
- `payload` (String) - HTML content of new element
- `type` (String) - Element type (default 'div')
- `id` (String) - ID of new element (optional)
- `class` (String) - Class of new element (optional)
- `style` (String) - Inline styles (optional)
- `title` (String) - Title attribute (optional)

**Actions:**
- Finds parent element
- Checks for existing element with same ID
- Creates new element with specified parameters
- Adds element to parent
- Logs operation

---

`removeChild(cmd)`

**Purpose:** Removes child elements with filtering.

**Command parameters:**
- `id` (String) - Parent element ID
- `ignoreClass` (String) - Classes that should NOT be removed (comma-separated)

**Actions:**
- Finds parent element
- Gets all child elements
- Removes elements that don't have classes specified in `ignoreClass`
- Logs operation

---

`replaceChildClasses(cmd)`

**Purpose:** Replaces classes on all first-level child elements.

**Command parameters:**
- `id` (String) - Parent element ID
- `oldClass` (String) - Old classes to replace (comma-separated)
- `class` (String) - New classes to set (comma-separated)

**Actions:**
- Finds parent element
- Gets all first-level child elements
- Checks if EACH element has ALL old classes
- Replaces old classes with new (for matching elements)
- Logs number of modified elements

### Special Operations

`handleSetCheckboxState(cmd)`

**Purpose:** Synchronizes checkbox state.

**Command parameters:**
- `id` (String) - Checkbox element ID
- `state` (String) - State: 'on' or 'off'

**Actions:**
- Finds checkbox element
- Converts string state to boolean
- Sets `checked` property
- Logs operation (in debug mode)

### Helper Methods

#### DOM Element Management

`getElement(id)`

**Purpose:** Finds element by ID.

**Parameters:**
- `id` (String) - Element ID

**Returns:** DOM element or `null`

**Actions:**
- Searches via `document.getElementById()`
- Logs warning when element not found (level 2+)

`getParentElement(childId)`

**Purpose:** Finds parent element with caching.

**Parameters:**
- `childId` (String) - Child element ID

**Returns:** Parent DOM element or `null`

**Actions:**
- Checks parent element cache
- Finds child element
- Gets parent element via `parentElement`
- Saves to cache for future use

`clearParentCache(childId)`

**Purpose:** Clears parent element cache.

**Parameters:**
- `childId` (String) - ID to clear specific entry (optional)

**Actions:**
- Removes entry from cache or clears entirely

#### Timer Management

`startPingTimer()`

**Purpose:** Starts timer for ping requests.

**Actions:**
- Clears existing timer
- Sets interval timer
- Sends ping messages to server
- Logs in debug mode

`clearTimers()`

**Purpose:** Clears all active timers.

**Actions:**
- Stops ping timer
- Stops reconnection timer

#### Status Management

`updateStatus(status, message)`

**Purpose:** Updates visual connection state.

**Parameters:**
- `status` (String) - New connection state
- `message` (String) - Message to display

**Actions:**
- Updates internal state
- Logs status change
- Updates status button text and styles
- Sets appropriate tooltip

`createStatusButton()`
**Purpose:** Creates status button in navigation.

**Actions:**
- Removes old button (if exists)
- Finds navigation panel
- Creates new button with click handler
- Adds button to DOM
- Logs creation

`handleStatusButton()`
**Purpose:** Handles clicks on status button.

**Actions depending on state:**
- **connected** - Manual disconnect (`disconnect()`)
- **disconnected/error/timeout** - Page reload
- **connecting/reconnecting** - Ignore click

`startPageReload()`
**Purpose:** Initiates page reload.

**Actions:**
- Logs start of reload
- Updates status button text
- Reloads page after 300 ms

#### Logging

`log(level, message, data)`
**Purpose:** Multi-level logging.

**Parameters:**
- `level` (String|Number) - Logging level
- `message` (String) - Text message
- `data` (Any) - Additional data (optional)

**Logging levels:**
1. **ERROR** - Critical errors
2. **WARNING** - Warnings
3. **INFO** - Informational messages (default)
4. **DEBUG** - Detailed debug information

**Actions:**
- Checks configuration debug level
- Logs to browser console (if enabled)
- Logs to web console (if enabled)

`logToWebConsole(timestamp, level, message, data)`
**Purpose:** Logs to web console on page.

**Parameters:**
- `timestamp` (String) - Timestamp
- `level` (String) - Logging level
- `message` (String) - Text message
- `data` (Any) - Additional data or message source

**Actions:**
- Finds web console element
- Forms HTML message structure
- Adds message to beginning of console
- Limits message count (max 500)
- Escapes HTML characters

`escapeHtml(text)`
**Purpose:** Escapes HTML characters.

**Parameters:**
- `text` (String) - Text to escape

**Returns:** Escaped string

### Public API

`getStatus()`
**Purpose:** Gets current client state.

**Returns:** Object with state information:

```javascript
{
  status: 'connected',           // Current state
  wsState: 1,                    // WebSocket state (0-3)
  clientId: 'client_123456789',  // Client ID from server
  reconnectAttempts: 0,          // Reconnection attempts count
  parentCacheSize: 5,            // Parent element cache size
  config: {...}                  // Current configuration
}
```

`executeTestCommand(command)`
**Purpose:** Manual command execution (for debugging).

**Parameters:**
- `command` (Object) - Command object

**Returns:** Execution result (`true`/`false`)

**Usage:**
```javascript
window.dashboardWSClient.executeTestCommand({
  id: 'testElement',
  action: 'add_class',
  class: 'active'
});
```

## Global Functions (Debugging)

After client initialization, global debug functions are created:

`window.connectWebSocket()`
Manual connection to server.

`window.disconnectWebSocket()`
Manual disconnection from server.

`window.getWebSocketStatus()`
Gets current client status.

`window.sendTestCommand(command)`
Executes test command.

`window.testCommands`
Set of ready test commands:
- `addClass(id, className)` - Add class
- `removeClass(id, className)` - Remove class
- `setContent(id, content)` - Set content
- `addParentClass(id, className)` - Add class to parent
- `removeParentClass(id, className)` - Remove class from parent

## Initialization

The client automatically initializes on `DOMContentLoaded` event:

```javascript
document.addEventListener('DOMContentLoaded', () => {
  // Get configuration from PHP
  const wsConfig = window.DASHBOARD_CONFIG?.websocket;
  
  // Create client
  window.dashboardWSClient = new DashboardWebSocketClientV4(wsConfig);
  
  // Create global debug functions
  // ...
});
```

## Implementation Features v4.2

### Parent Element Caching
- Automatic caching on first access
- Performance improvement for frequent parent operations
- Manual clearing via `clearParentCache()`

### Enhanced Error Handling
- Validation of all input parameters
- Protection against incorrect commands
- Detailed error logging

### Flexible Logging System
- 4 detail levels
- Support for web console and browser console
- Automatic message rotation
- Filtering by debug level

### Connection Reliability
- Exponential reconnection delay
- Maximum attempt limit
- Graceful shutdown on manual disconnect
- Ping-pong for connection maintenance