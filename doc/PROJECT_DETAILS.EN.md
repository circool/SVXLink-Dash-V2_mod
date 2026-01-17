# SvxLink Dashboard by R2ADU – Project Documentation

Last Updated: 2026-01-17  
Version: 0.4.x

## 1. Project Purpose

This project is a web-based dashboard for monitoring and managing the SvxLink service—a software solution for amateur radio communication over the internet. The dashboard displays real-time status of:

- Logics
- Links
- Service
- Devices (multiple_device)
- WebSocket connections

All data is sourced from the SvxLink log file (`/var/log/svxlink`), parsed, and updated dynamically in the browser.

### 1.1. Prerequisites

It is assumed that SvxLink runs on a headless device such as a Raspberry Pi-like device or a virtual machine.

Users interact with the dashboard via a web browser.

Multiple clients (administrators and observers) may connect to the dashboard. Observer support adds complexity to the architecture; the current implementation supports updates for multiple clients.

## 2. Required Packages

- Apache2
- PHP
- Node.js

## 3. Component Creation Order

### 3.1. Initialization
On first launch, parameters from `settings.php` are analyzed and constants are initialized in `init.php`.

#### 3.1.1. Settings
- **Format-defining constants**:  
  `DASHBOARD_TIME_FORMAT`, `DASHBOARD_DATE_FORMAT`
- **Content-defining constants**:  
  `DASHBOARD_VERSION`, `DASHBOARD_NAME`, `DASHBOARD_TITLE`,  
  `SHOW_CON_DETAILS`, `SHOW_RADIO_ACTIVITY`, `SHOW_NET_ACTIVITY`,  
  `SHOW_REFLECTOR_ACTIVITY`, `SHOW_RF_ACTIVITY`, `RF_ACTIVITY_LIMIT`,  
  `DEBUG`, `DEBUG_VERBOSE`, `DEBUG_LOG_FILE`, `LOG_LEVEL`
- **Behavior-defining constants**:  
  `UPDATE_INTERVAL`, `WS_ENABLED`, `WS_PORT`, `WS_PATH`,  
  `USE_CACHE`, `LOG_CACHE_TTL_MS`
- **Session constants**:  
  `SESSION_NAME`, `SESSION_LIFETIME`, `SESSION_PATH`, `SESSION_ID`
- **SvxLink configuration constants**:  
  `SVXLOGPATH`, `SVXLOGPREFIX`, `SVXLINK_LOG_PATH`, `SVXCONFPATH`, `SVXCONFIG`, `TIMESTAMP_FORMAT`

#### 3.1.2. Component Structure
- **3.1.2.1** – The SvxLink configuration file is read to build the component structure (`init.php`).
- **3.1.2.2** – The latest (current) service session is read to populate the component structure with active component states. The resulting structure is saved in the session.

#### 3.1.3. DOM Component Rendering
- **3.1.3.1** – Status block (left panel):
  - Service status
  - Available logics, their modules, associated reflectors, and links
  - For active modules or reflectors: connected nodes/groups/conferences, etc.
- **3.1.3.2** – Radio block (rendered separately for each logic):
  - RX/TX devices
  - Their state (`STANDBY`/`TRANSMIT`/`RECEIVE`)
  - Callsign used for communication
  - Duration of transmission/reception
  - Reflector-type logics are rendered with empty content (framework only)

#### 3.1.4. Info Block States
- Connection details for active modules (Echolink, Frn)
- Active reflectors
- Call history related to internet services
- Local (RF) call history

## 4. Data Display Concept

Page elements are divided into three categories:

1. **Static** – Created once when the page loads
2. **Dynamically Updated** – Refreshed every 2–5 seconds (partially implemented)
3. **Real-time Updated** – Updated instantly as events occur

### 4.1. Update Order

#### Real-time Data
Real-time data is provided by a WebSocket server and processed by the client. This updates rapidly changing states such as transmitter/receiver activity:
- The server monitors the SvxLink log
- Extracts significant events
- Creates DOM update commands
- Clients apply received commands to the DOM structure

#### Dynamically Updated Data
This includes historical data:
- Activity history – recent events for relevant modes
- Recent local activity
- Recent network activity (calls from the internet, reflectors)

Historical summaries are calculated by respective functions for each block. Data is derived by analyzing the SvxLink log within the latest active session. The update interval for such data is set to 5 seconds.

## 5. State Data Structure (`$_SESSION`)

Service state data is stored in the session (a single session with a fixed ID is used for all elements, including those updated via AJAX).

### `['DTMF_CTRL_PTY']` : string
### `['TIMEZONE']` : string

### `['status']` : array
- `link`: array[linkName] (array of links)
- `logic`: array[logicName] (array of logics)
- `service`: array (service information)
- `multiple_device`: array[deviceName] (composite devices)
- `callsign`: string (global callsign)

#### `link[linkName]` Structure
- `is_active`: bool
- `is_connected`: bool
- `start`: int (timestamp)
- `duration`: int (seconds)
- `timeout`: int
- `default_active`: bool
- `source`: array
  - `logic`: string
  - `command`: array
    - `activate_command`: string
    - `deactivate_command`: string
  - `announcement_name`: string
- `destination`: array
  - `logic`: string
  - `command`: array
    - `activate_command`: string
    - `deactivate_command`: string
  - `announcement_name`: string

#### `logic[logicName]` Structure
- `start`: int (timestamp)
- `duration`: int (seconds)
- `name`: string (logicName)
- `is_active`: bool
- `callsign`: string
- `rx`: string (RX device)
- `tx`: string (TX device)
- `macros`: array [key,value]
- `type`: string
- `dtmf_cmd`: string
- `is_connected`: bool
- `module`: array[moduleName] (optional)
  - `start`: int
  - `duration`: int
  - `name`: string
  - `callsign`: string
  - `is_active`: bool
  - `is_connected`: bool
  - `connected_nodes`: array[nodeName] (optional)
    - `callsign`: string (nodeName without suffixes and surrounding '*')
    - `start`: int
    - `type`: string <'Repeater','Link','Conference'>
- `talkgroups`: array (optional)
  - `default`: string
  - `selected`: string
  - `monitoring`: array
  - `temp_monitoring`: string
- `connected_nodes`: array[nodeName] (optional)
  - `callsign`: string
  - `start`: int
  - `type`: string ("Node")
- `hosts`: string (optional) – comma-separated values

#### `service` Structure
- `start`: int
- `duration`: int
- `name`: string
- `is_active`: bool
- `timestamp_format`: string

#### `multiple_device[deviceName]` Structure
- `device_name`: string (list of transmitters, comma-separated)

## 6. File Structure Organization

**Actual root path (for reference)**: `/var/www/html/`  
**Test server URL**: `http://svxlink_development_dashboard.local`

During development, symlinks point to current versions. After development, symlinks will be replaced with production versions. Some files are leftovers from previous versions. The current version is indicated at the beginning of this file.

```
/                                       # Project root
├── index.php                           # Main page (production)
├── index_debug.php                     # Main page for debug mode
├── backup.sh                           # Backup script
├── clear_and_show_apache_error_log     # View Apache logs
├── clear_and_show_dashboard_debug_log  # Clear and show debug logs
├── clear_ans_show_websocketserver_log  # Show WebSocket server logs
├── fake_log_msg                        # SvxLink test message generator
├── favicon.ico                         # Site icon
├── kill_ws_and_show_log.sh             # Stop WS and show logs
├── kill_ws_server.sh                   # Stop WebSocket server
├── logs/                               # Logs directory
├── sessions_clear                      # Clear sessions
├── update_simlink.sh                   # Update symlinks
├── websocket_server.log                # WebSocket server log
├── ws_state.php                        # WebSocket status
├── svxlink_development_dashboard.conf  # Apache configuration
├── svxlink-node.service                # Node.js service
├── include/                            # PHP include files
│   ├── auth_config.php                 # Authorization configuration
│   ├── auth_handler.php                # Authorization handler
│   ├── authorise.php                   # Authorization
│   ├── browserdetect.php               # Browser detection
│   ├── change_password.php             # Password change
│   ├── connection_details.php          # Connection details
│   ├── debug_page.php                  # Debug data
│   ├── footer.php                      # Footer
│   ├── init.php                        # Main initialization
│   ├── js_utils.php                    # JavaScript utilities
│   ├── keypad.php                      # DTMF keypad
│   ├── languages/                      # Localizations
│   ├── left_panel.php                  # Left status panel
│   ├── logout.php                      # Logout
│   ├── macros.php                      # Macros
│   ├── monitor.php                     # Audio monitoring
│   ├── net_activity.php                # Network activity history
│   ├── radio_activity.php              # Radio status
│   ├── reflector_activity.php          # Reflector data
│   ├── reset_auth.php                  # Authorization reset
│   ├── rf_activity.php                 # Local activity history
│   ├── session_header.php              # Lightweight session start
│   ├── settings.php                    # Application settings
│   ├── top_menu.php                    # Main command menu
│   ├── update_simlink.sh               # Symlink update script
│   ├── websocket.php                   # WebSocket utilities
│   ├── websocket_client_config.php     # WebSocket client configuration
│   ├── websocket_server.php            # WebSocket server (PHP)
│   ├── exct/                           # Versioned sources
│   │   ├── auth_config.0.0.1.php
│   │   └── ...
│   └── fn/                             # Function packages
│       ├── logTailer.php               # Log tailing
│       ├── getTranslation.php          # Translation handling
│       ├── dlog.php                    # Debug logging
│       ├── parseXmlTags.php            # XML tag parsing
│       ├── getActualStatus.php         # Build initial system state
│       └── exct/                       # Versioned sources
│           ├── getActualStatus.0.4.0.php
│           └── ...
├── scripts/                            # JS scripts
│   ├── dashboard_ws_client.js          # WebSocket state client
│   ├── dashboard_ws_server.js          # WebSocket state server
│   ├── featherlight.js                 # Modal window library
│   ├── jquery.min.js                   # jQuery library
│   ├── svxlink-audio-proxy-server.js   # WebSocket Audio Monitor :8001
│   ├── restart_audio_proxy_server      # Restart audio proxy server
│   ├── show_audio_proxy_server_log     # Show audio proxy server logs
│   ├── update_simlink.sh               # Symlink update script
│   └── exct/                           # Versioned sources
│       ├── dashboard_ws_server.4.17.js # WebSocket server v4.17
│       ├── dashboard_ws_client.4.2.js  # WebSocket client v4.2
│       └── svxlink-audio-proxy-server.0.0.2.js # Audio Monitor 0.0.2
├── css/                                # Styles
│   ├── css.php                         # Main styles
│   ├── menu.0.2.2.php                  # Additional styles
│   ├── websocket_control.css           # WS control button styles
│   ├── font-awesome.min.css            # Awesome Fonts
│   └── font-awesome-4.7.0/
│       ├── css/
│       ├── fonts/
│       ├── less/
│       └── scss/
├── fonts/                              # Fonts
│   ├── stylesheet.css
│   └── ...
├── install/                            # Installation scripts
│   ├── cli_setup.php                   # CLI Setup Script 0.1.1
│   └── setup_auth.php                  # Authorization setup script
└── config/                             # Configuration files
```

## 7. WebSocket System v4.0

### Purpose
Enables real-time two-way communication between the SvxLink server and the Dashboard web interface. The server monitors the SvxLink log, parses events, and sends DOM update commands to all connected clients.

### Architecture

#### WebSocket Server (Node.js, port 8080)
**Main components:**
- **WebSocket Server** – manages client connections
- **Initial State Parser** – retrieves initial data when a client connects
- **SvxLink Log Parser** – analyzes events in real-time
- **DOM Command Generator** – converts events into interface update commands

**Functionality:**
- Monitors SvxLink log file using `tail -F`
- Parses events: transmitter, squelch, talkgroup, module activity
- Generates DOM commands for interface updates
- Broadcasts updates to all connected clients

Detailed documentation in `ws_server.md`.

#### WebSocket Client (JavaScript)
The client is automatically initialized when the page loads.

##### Command Format
Commands are JSON objects with a mandatory `action` field:

```javascript
{
  "action": "action_name",
  // additional parameters
}
```

Detailed documentation in `ws_client.md`.

## 8. DOM Structure

### Root Structure
```
html
├── head
│   ├── meta (charset)
│   ├── meta (viewport)
│   ├── title
│   ├── script (External jquery.min.js)
│   ├── script (External dashboard_ws_client.js – WebSocket client)
│   ├── script (Inline – configuration – App global parameter initialization)
│   ├── link (font-awesome)
│   ├── link (stylesheet)
│   ├── link (stylesheet)
│   ├── link (stylesheet)
│   └── link (websocket_control.css)
├── body
│   └── div.container
│
└── script                             <!-- Inline – init and functions -->
```

### Main Body Sections

#### 1. Header
```
div.header
├── div.SmallHeader (left part)
├── h1 (title)
└── div.navbar (navigation)
    ├── div.headerClock
    ├── div (authorization modal)
    │   ├── div.auth-overlay
    │   └── div.auth-container
    │       └── div.auth-form
    ├── a.menuadmin (Login)
    ├── div (DTMF modal)
    │   ├── script (DTMF handling)
    │   ├── div#keypadOverlay
    │   └── div#keypadContainer
    ├── a.menukeypad (DTMF)
    ├── script (audio monitoring control)
    ├── a.menureset (Reset Session)
    ├── a.menuconnection (Connect Details)
    ├── a.menureflector (Reflectors)
    ├── a.menuaudio (Monitor)
    ├── a.menudashboard (Dashboard)
    ├── a.menuwebsocket (WebSocket status)
    ├── div (logout/admin modal)
    │   └── div#logoutModal
    ├── script (modal control)
    ├── a.menuwsdisconnect (WS Disconnect)
    ├── a.menuwsconnect (WS Connect)
    └── a.menuwsreconnect (WS Reconnect)
```

#### 2. Main Content Structure

```
div.container
├── div.leftnav
│   └── div#leftPanel
│
├── div.content (main content)
│   ├── div#lcmsg (messages)
│   ├── div#radio_activity (radio status)
│   │   ├── div#ra_header (table header)
│   │   └── div#ra_table (table body – rows per logic)
│   ├── div#connection_details (connection details, optional)
│   │   ├── div#refl_header (reflector data?)
│   │   └── div#frn_server_table (nodes connected to Frn server)
│   │   └── div#el_msg_table (EchoLink messages)
│   ├── div#reflector_activity (reflector info)
│   │   ├── div#refl_header
│   │   └── table
│   ├── div#net_activity (network activity)
│   │   └── table
│   ├── div#local_activity (local activity)
│   │   └── table
│   └── div (debug)
│       ├── div (WebSocket console)
│       └── div.debug (debug section)
│           ├── div (debug controls)
│           ├── div#debugContent
│           │   ├── div#performanceSection
│           │   └── div#variableSection
│           └── script (debug block visibility control)
├── div#footer (Footer)
└── script <!-- Inline – clock and debug console -->
```

##### Left Panel Element IDs (`leftPanel`)

```
leftPanel
        ├── service
        ├── logic_{logic_name}
        │   ├── logic_{logic_name}
        │   ├── logic_{logic_name}_modules_header           Module block header
        │   ├── logic_{logic_name}_module_{module_name}
        │   ├── ... (other modules)
        │   └── logic_{logic_name}_active                   Active module block
        │       ├── logic_{logic_name}_active_header        Header
        │       └── logic_{logic_name}_active_content       Body
        │
        ├─── "Reflector – Link" Block
        │   ├── logic_{reflector_name}                      Reflector
        │   ├── link_{link_name}                            Link
        │   ├── logic_{reflector_name}_groups               Reflector talk groups
        │   │   ├── logic_{reflector_name}_{group_name}
        │   │   └── ...
        │   │
        │   └── logic_{reflector_name}_nodes                Reflector nodes
        │       ├── logic_{reflector_name}_nodes_header     Node block header
        │       ├── logic_{reflector_name}_node_{node_name} Node
        │       └── ...
        └── ...
```

### Interface Elements

#### Modals
1. **Authorization** (`authContainer`) – login form
2. **DTMF Keypad** (`keypadContainer`) – virtual keypad
3. **Logout/Admin** (`logoutModal`) – admin menu

#### Functional Blocks
1. **Radio Status** – radio device status
2. **Connection Details** – network connection details
3. **Reflectors Info** – reflector information
4. **NET Activity** – network activity
5. **RF Activity** – radio frequency (local) activity
6. **Debug Console** – debug information

#### Control Panels
1. **Navigation Bar** – control buttons
2. **Left Panel** (`#leftPanel`) – logic and module structure
3. **Debug Panel** – performance metrics and variables

### Structural Features
- Responsive flex layout
- Adaptive design support

## 9. Audio Monitoring Service

### Server

Automatically restarts 5 seconds after a crash.

Begins capture only when clients connect; releases capture after all clients disconnect.

Implemented as a separate service (`/etc/systemd/system/svxlink-audio-proxy.service`).

```bash
[Unit]
Description=SVXLink Node.js Server
After=network.target

[Service]
# Send logs directly to journald instead of syslog or files
StandardOutput=journal
StandardError=journal

# Ensure service restarts even after journal restarts or SIGHUPs
Restart=always
RestartSec=5

# Allow clean reloads (optional, useful if you add reload scripts later)
ExecReload=/bin/kill -HUP

# Give the process a few seconds to shut down gracefully
TimeoutStopSec=10
Type=simple
User=svxlink
Group=svxlink
ExecStart=/usr/bin/node /etc/systemd/system/svxlink-audio-proxy.service.js
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
```

#### Management:

| Command | Action |
|---------|----------|
| `sudo systemctl stop svxlink-audio-proxy.service` | Stop the server (will not restart) |
| `sudo systemctl start svxlink-audio-proxy.service` | Start the server |
| `sudo systemctl restart svxlink-audio-proxy.service` | Restart the server |
| `sudo systemctl disable svxlink-audio-proxy.service` | Disable auto-start |
| `sudo systemctl enable svxlink-audio-proxy.service` | Enable auto-start |
| `sudo journalctl -u svxlink-audio-proxy.service -f` | View logs in real-time |
| `sudo journalctl -u svxlink-audio-proxy.service --since="1 hour ago"` | Logs from the last hour |

---

#### Configuration

The server is configured to use `plughw:Loopback,1,0`:

```javascript
function startRecording() {
  console.log('Starting audio capture...');
  const record = spawn('arecord', [
    '-D', 'plughw:Loopback,1,0',
    '-f', 'S16_LE',
    '-r', '48000',
    '-c', '1'
  ], {
    stdio: ['ignore', 'pipe', 'ignore'] // stdout only
  });
  ...
```

In the SvxLink config, set it to `alsa:plughw:Loopback,0,0`:

```
[TxStream]
TYPE = Local
AUDIO_DEV = alsa:plughw:Loopback,0,0
AUDIO_CHANNEL = 0
PTT_TYPE = NONE
TIMEOUT = 7200
TX_DELAY = 0
PREEMPHASIS = 0
```
