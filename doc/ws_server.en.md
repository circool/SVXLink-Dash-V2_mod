# WebSocket Server Documentation v4.17

## Overview

Stateful WebSocket Server version 4.17 provides real-time SvxLink event handling, log file monitoring, state management, and DOM command broadcasting to all connected clients. The server loads initial state from a PHP endpoint, restores timers and connections, and ensures automatic connection management.

## Architecture

### Core Components

- **StatefulWebSocketServerV4** - Main server class
- **StateManager** - Manager for timers and temporal states
- **CommandParser** - SvxLink log parser with DOM command generation
- **ConnectionHandler** - Handler for connections between components
- **Log Monitor** - SvxLink log file monitoring via `tail -F`

### Server States

- **running** - Server is running and ready for connections
- **monitoring** - Log file monitoring is active
- **idle** - No connected clients, awaiting shutdown
- **shutting_down** - Graceful shutdown in progress
- **error** - Critical server error

## Configuration

### Constants and Settings

```javascript
const CONFIG = {
    version: '0.4.17',
    ws: {
        host: '0.0.0.0',           // Host to listen on
        port: 8080,                // WebSocket port
        clientTimeout: 15000       // Disconnect timeout when no clients (ms)
    },
    log: {
        path: '/var/log/svxlink',  // Path to SvxLink log file
        bufferTimeout: 3,          // Log buffering timeout (sec)
        maxBufferSize: 50          // Maximum buffer size for lines
    },
    duration: {
        updateInterval: 1000       // Timer update interval (ms)
    },
    php: {
        stateEndpoint: 'http://localhost/ws_state.php', // PHP endpoint for initial state
        timeout: 3000              // PHP request timeout (ms)
    }
};
```

### Logging Levels

The server supports 4 logging levels (configurable via `DEBUG_LEVEL` environment variable):

1. **ERROR** - Critical errors (Level 1)
2. **WARNING** - Warnings (Level 2)
3. **INFO** - Informational messages (Level 3)
4. **DEBUG** - Detailed debug information (Level 4)

## Server Classes

### StateManager

Manager for temporal states and timers.

#### Methods

`formatDuration(milliseconds)`

**Purpose:** Format duration into human-readable form.

**Returns:** Formatted string:
- `< 60 sec`: "N s"
- `< 1 hour`: "MM:SS"
- `≥ 1 hour`: "HH:MM:SS"

---

`startTimer(key, metadata = {})`

**Purpose:** Start a new timer.

**Parameters:**
- `key` (String) - Unique timer key
- `metadata` (Object) - Timer metadata:
  - `elementId` - DOM element ID for updates
  - `replaceStart` - Start marker for replacement
  - `replaceEnd` - End marker for replacement
  - Additional custom fields

**Actions:**
- Check for existing timers with same `elementId`
- Remove conflicting timers
- Register new timer in collection

**Returns:** Timer start time (timestamp)

---

`stopTimer(key)`

**Purpose:** Stop and remove a timer (and app child timers too).

**Parameters:**
- `key` (String) - Timer key to stop

**Returns:** `true` if timer existed, `false` otherwise

---

`setTimerStart(key, startTimestamp)`

**Purpose:** Set start time for an existing timer.

**Parameters:**
- `key` (String) - Timer key
- `startTimestamp` (Number) - Start time (timestamp)

**Returns:** `true` on success, `false` if timer not found

---

`getTimerUpdates()`

**Purpose:** Get updates for active timers.

**Returns:** Array of update objects:
```javascript
[
    {
        key: 'timer_key',
        durationMs: 15000,
        metadata: { ... }
    }
]
```

---

`getStats()`

**Purpose:** Get manager statistics.

**Returns:** Statistics object:
```javascript
{
    activeTimers: 5
}
```

### ConnectionHandler

Handler for connections between system components (logics, modules, links, nodes).

#### Methods

`add(source, target)`

**Purpose:** Add a connection between components.

**Parameters:**
- `source` (String) - Connection source
- `target` (String) - Connection target

**Actions:**
- Create collection for source (if missing)
- Add target to source collection

---

`remove(source, target)`

**Purpose:** Remove a connection between components.

**Parameters:**
- `source` (String) - Connection source
- `target` (String) - Connection target

**Actions:**
- Remove target from source collection
- Remove empty source collection

---

`getAllFrom(source)`

**Purpose:** Get all targets for a source.

**Parameters:**
- `source` (String) - Connection source

**Returns:** Array of targets or empty array

---

`getAllTo(target)`

**Purpose:** Get all sources for a target.

**Parameters:**
- `target` (String) - Connection target

**Returns:** Array of sources

---

`initFromData(data, sourceKey, targetKey)`
**Purpose:** Initialize connections from PHP data.

**Parameters:**
- `data` (Object) - Connection data in format `{source: [targets]}`
- `sourceKey` (String) - Source type description (for logging)
- `targetKey` (String) - Target type description (for logging)

### CommandParser

SvxLink log line parser with DOM command generation. Supports two modes: normal and batch (for EchoLink/Frn messages).

#### Constructor Parameters

- `stateManager` (StateManager) - StateManager instance

#### Supported Patterns

The server recognizes 40+ SvxLink log patterns, including:

**Service:**
- SvxLink service start
- Service stop (SIGTERM)

**Logics:**
- Logic start
- Module loading
- RX/TX device loading

**Devices:**
- Transmitter ON/OFF
- Squelch OPEN/CLOSED

**Reflectors:**
- Connect/disconnect from reflector
- Connected nodes
- Node entry/exit
- Talk group selection

**Modules:**
- Module activation/deactivation
- EchoLink/Frn states
- Conference messages

**Links:**
- Link activation/deactivation

#### Methods

`parse(line)`

**Purpose:** Main method for parsing log lines.

**Parameters:**
- `line` (String) - Log line to parse

**Returns:** Result object or `null`:
```javascript
{
    commands: [],          // Array of DOM commands
    raw: "original_line",  // Original line
    timestamp: "timestamp" // Timestamp
}
```

**Features:**
- Supports batch mode for EchoLink messages
- Automatic mode switching

---

`timerUpdatesToCommands(timerUpdates)`

**Purpose:** Convert timer updates to DOM commands.

**Parameters:**
- `timerUpdates` (Array) - Array of updates from StateManager

**Returns:** Array of DOM commands for time updates

---

`initFromWsData(wsData)`

**Purpose:** Initialize parser from PHP data.

**Parameters:**
- `wsData` (Object) - State data from PHP:
  - `module_logic` - Module-logic connections
  - `link_logic` - Link-logic connections

#### Batch Mode (EchoLink)

Commands:
- `EchoLink: --- EchoLink chat message received from ... ---` - Batch start
- `Trailing chat data:` - Batch end
- Lines like `->R2ADU-L Moscow 145.4625 #88.5` or `R2ADU>test` - Batch data

Processing:
1. Enable callsign waiting mode
2. Process lines with `->` or `>`
3. Format HTML with `<b>` tags
4. Send via `set_content_by_class` command

### StatefulWebSocketServerV4

Main WebSocket server class.

#### Methods

##### Initialization and Management

`constructor(config = {})`

**Purpose:** Create server instance.

**Parameters:**
- `config` (Object) - Server configuration (merged with CONFIG)

**Initializes:**
- StateManager, CommandParser, ConnectionHandler
- Client collections and state
- Server statistics

---

`async start()`

**Purpose:** Start WebSocket server.

**Actions:**
- Verify log file existence
- Start WebSocket server
- Setup timers
- Handle OS signals

##### State Management

`async loadWsState()`
**Purpose:** Load initial state from PHP endpoint.

**Process:**
1. HTTP request to `CONFIG.php.stateEndpoint`
2. Parse JSON response
3. Distribute data to components:
   - `devices` - Devices (RX/TX)
   - `modules` - Modules
   - `links` - Links
   - `nodes` - Nodes
   - `module_logic` - Module-logic connections
   - `link_logic` - Link-logic connections
   - `logics` - Logics
   - `service` - SvxLink service

**Error Handling:**
- 3-second timeout
- Handle empty responses
- Restore empty state on errors

---

`restoreFromWsState()`

**Purpose:** Restore state from loaded data.

**Restores:**
1. Connections via CommandParser
2. Logic timers
3. Device (RX/TX) timers
4. Module timers
5. Link timers
6. Node timers
7. Service timer

**Logging:** Counts restored elements

##### Client Management

`async handleClientConnection(ws, req)`

**Purpose:** Handle new client connection.

**Process:**
1. Generate unique clientId
2. Register client in collection
3. Send welcome message
4. Load state (if first client)
5. Send initial commands
6. Start monitoring (if first client)

**Welcome Format:**
```javascript
{
    type: 'welcome',
    version: '0.4.17',
    clientId: 'client_123456789',
    serverTime: 1673789200000,
    message: 'Server WebSocket v0.4.15',
    system: 'dom_commands_v4_state',
    initialState: true
}
```

---

`sendInitialCommands(ws)`

**Purpose:** Send initial state to new client.

**Sends:**
- Device (RX/TX) states with durations
- SvxLink service state
- Active timers

---

`handleClientDisconnect(clientId)`

**Purpose:** Handle client disconnection.

**Actions:**
- Remove client from collection
- Stop monitoring when no clients
- Start shutdown timer

---

`broadcast(message)`

**Purpose:** Broadcast message to all connected clients.

**Parameters:**
- `message` (Object) - Message to broadcast

**Returns:** Number of successfully sent messages

**Message Formats:**
```javascript
// DOM commands
{
    type: 'dom_commands',
    commands: [...],
    timestamp: 1673789200000,
    total: 15,
    chunk: 1,
    chunks: 2
}

// Logs
{
    type: 'log_message',
    level: 'INFO',
    message: 'Message text',
    timestamp: '2026-01-16T10:30:00.000Z',
    source: 'WS Server v4'
}
```

##### Log Monitoring

`startLogMonitoring()`

**Purpose:** Start SvxLink log file monitoring.

**Uses:** `tail -F -n 0 /var/log/svxlink`

**Features:**
- Line buffering (50 lines, 3 sec)
- Automatic restart on failure
- Stop when no clients

---

`stopLogMonitoring()`

**Purpose:** Stop log file monitoring.

**Actions:** Terminate tail process with SIGTERM

---

`processLogBuffer(buffer)`

**Purpose:** Process buffered log lines.

**Process:**
1. Parse each line via CommandParser
2. Collect all DOM commands
3. Split into chunks of 10 commands
4. Broadcast to all clients

**Optimization:**
- Processing limit: `maxBufferSize` lines
- Chunking for large packets
- Statistics counting

##### Time Management

`startDurationTimer()`

**Purpose:** Start periodic timer updates.

**Interval:** `CONFIG.duration.updateInterval` (1000 ms)

**Process:**
1. Get updates from StateManager
2. Convert to DOM commands
3. Broadcast to clients

---

`stopDurationTimer()`

**Purpose:** Stop update timer.

##### Shutdown Management

`startShutdownTimer()`

**Purpose:** Start auto-shutdown timer when no clients.

**Timeout:** `CONFIG.ws.clientTimeout` (15000 ms)

---

`clearShutdownTimer()`

**Purpose:** Reset auto-shutdown timer.

---

`gracefulShutdown()`

**Purpose:** Graceful server shutdown.

**Process:**
1. Stop all timers
2. Stop log monitoring
3. Close client connections (code 1000)
4. Close WebSocket server
5. Print statistics
6. Exit process

##### Statistics

`getStats()`

**Purpose:** Get server statistics.

**Returns:**
```javascript
{
    version: '0.4.17',
    uptime: 3600,                     // Uptime (sec)
    clients: 3,                       // Connected clients
    monitoring: true,                 // Monitoring active
    durationTimerActive: true,        // Duration timer active
    wsState: {
        devices: 5,                   // Devices in state
        modules: 3,                   // Modules in state
        links: 2,                     // Links in state
        nodes: 10,                    // Nodes in state
        connections: 15               // Connections in ConnectionHandler
    },
    serverStats: {
        startedAt: 1673789200000,     // Start time
        clientsConnected: 10,         // Total connections
        clientsDisconnected: 7,       // Total disconnections
        messagesSent: 1500,           // Messages sent
        errors: 2,                    // Errors
        eventsProcessed: 300,         // Processed events
        commandsGenerated: 1200,      // Generated commands
        stateLoads: 5,                // State loads
        stateLoadErrors: 1            // State load errors
    },
    stateStats: {
        activeTimers: 8               // Active timers
    }
}
```

## Message Formats

### Incoming Messages (from client)

`ping`
```javascript
{
    type: 'ping'
}
```

### Outgoing Messages (to client)

`welcome`
```javascript
{
    type: 'welcome',
    version: '0.4.17',
    clientId: 'client_123456789',
    serverTime: 1673789200000,
    message: 'Server WebSocket v0.4.17',
    system: 'dom_commands_v4_state',
    initialState: true
}
```

`pong`
```javascript
{
    type: 'pong',
    timestamp: 1673789200000
}
```

`dom_commands`
```javascript
{
    type: 'dom_commands',
    commands: [
        {
            id: 'element_id',
            action: 'add_class',
            class: 'active-mode-cell'
        }
    ],
    timestamp: 1673789200000,
    total: 15,        // Total command count (optional)
    chunk: 1,         // Current chunk number (optional)
    chunks: 2,        // Total chunks (optional)
    subtype: 'initial_state' // Packet type (optional)
}
```

`log_message`
```javascript
{
    type: 'log_message',
    level: 'INFO',
    message: 'Message text',
    timestamp: '2026-01-16T10:30:00.000Z',
    source: 'WS Server v4'
}
```

## System Requirements

- Node.js 14+
- Access to SvxLink log file (`/var/log/svxlink`)
- PHP endpoint for initial state
- Permissions to run `tail -F` process

## Environment Variables

`DEBUG_LEVEL` - Logging level (1-4)
```bash
DEBUG_LEVEL=4 node dashboard_ws_server.0.4.17.js
```

## OS Signals

- **SIGINT** (Ctrl+C) - Graceful shutdown
- **SIGTERM** - Graceful shutdown

## Error Handling

1. **Missing log file** - Warning, continue operation
2. **PHP endpoint error** - Use empty state
3. **WebSocket error** - Logging, continue operation
4. **Tail process error** - Restart after 2 seconds

## Performance

- Log buffering: Up to 50 lines, 3 sec timeout
- Command chunking: 10 commands per packet
- Auto-shutdown: 15 seconds without clients
- Ping-pong: Client ping support

## Compatibility

- WebSocket client v4.2+
- SvxLink 1.8.0+
- Any modern browser with WebSocket support

## Monitoring

The server provides statistics via:
- Console logs (with levels)
- `log_message` messages to clients
- `getStats()` method for external monitoring