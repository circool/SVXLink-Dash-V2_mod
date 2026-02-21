# SVXLink Dashboard Installation Guide

**IMPORTANT NOTE**
Keep in mind that no special measures have been taken to ensure the secure use of the dashboard.
Consider this if you provide access to the dashboard from the internet.

## Mandatory Requirements

1. **Timezone**: Set the system timezone

```bash
sudo timedatectl set-timezone Europe/Moscow
# or manually:
echo "Europe/Moscow" | sudo tee /etc/timezone
```

2. **SVXLink Configuration**:

	2.1. Make sure that the `TIMESTAMP_FORMAT` parameter is set in the `[GLOBAL]` section of the SVXLink configuration, use the `%d %b %Y %H:%M:%S.%f` format for guaranteed operation.

```bash
sudo nano /etc/svxlink/svxlink.conf
```
Add or uncomment:

```
[GLOBAL]
TIMESTAMP_FORMAT = "%d %b %Y %H:%M:%S.%f"
```

2.2. Make sure that the `LOCATION_INFO` parameter is set in the `[GLOBAL]` section of the SVXLink configuration and the `CALLSIGN` parameter is set in the specified section.
		
```
[GLOBAL]
...
LOCATION_INFO=LocationInfo
...
[LocationInfo]
CALLSIGN=...

```

3. **Install web server (if not installed)**:
```bash
sudo apt update
sudo apt install apache2 -y
```

4. **Install PHP (if not installed)**:
```bash
sudo apt install php php-json php-mbstring -y
```

5. **Install Node.js (if not installed)**:
```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install nodejs -y
```

## Optional Settings

### 1. DTMF Control

To enable control via the web interface, make sure that each logic in the `svxlink.conf` configuration has a unique value for the `DTMF_CTRL_PTY` parameter:

If multiple logics are used, set different values for each.

In the required logic (e.g., in the `[SimplexLogic]` section), add:

```
DTMF_CTRL_PTY = /dev/shm/simplex_ctrl
```

### 2. Audio Monitoring
To listen to the airwaves through the browser:

**2.1.** Configure the logic in the SVXLink config to use a composite device:

Replace the audio device in the required logic:

```
[SimplexLogic]
#TX = Tx1
TX = MultiTx
```

**2.2.** Add the composite device and the streaming device:
```
[TxStream]
TYPE = Local
AUDIO_DEV = alsa:plughw:Loopback,0,0
AUDIO_CHANNEL = 0
PTT_TYPE = NONE
TIMEOUT = 7200
TX_DELAY = 0
PREEMPHASIS = 0

[MultiTx]
TYPE = Multi
TRANSMITTERS = Tx1,TxStream
```

## Dashboard Installation

### Copying Project Files

1. **Navigate to the project directory** 

2. **Copy all contents to the web server root**:
   
```bash
sudo cp -r * /var/www/html/
```

3. **Set access permissions**:
   
```bash
# File owner - (svxlink service)
sudo chown -R svxlink:svxlink /var/www/html/

# Directory permissions - 755 (rwxr-xr-x)
sudo find /var/www/html/ -type d -exec chmod 755 {} \;

# File permissions - 644 (rw-r--r--)
sudo find /var/www/html/ -type f -exec chmod 644 {} \;

# JavaScript and PHP files must be executable for the owner
sudo chmod 755 /var/www/html/scripts/*.js
sudo chmod 755 /var/www/html/include/*.php
```

### First Launch and Authorization Setup

Upon first access to the site, administrator credentials will be created.

You will be redirected to the /install/setup_auth.php page

Click the **"Run Setup"** button

The installer will create the necessary directories and the credentials file

The created credentials will be displayed on the screen

Go to the main page and log in

Change the password through the login menu

#### Manual Installation

If for some reason the automatic installation did not work, perform the installation manually.

```bash
# Create the configuration directory
sudo mkdir -p /etc/svxlink/dashboard

# Set the directory owner (svxlink user)
sudo chown svxlink:svxlink /etc/svxlink/dashboard
sudo chmod 755 /etc/svxlink/dashboard

# Copy the configuration example
sudo cp /var/www/html/config/sample.auth.ini /etc/svxlink/dashboard/auth.ini

# Set the file owner (svxlink user)
sudo chown svxlink:svxlink /etc/svxlink/dashboard/auth.ini
sudo chmod 644 /etc/svxlink/dashboard/auth.ini
```

### Default Credentials
- **Login**: `svxlink`
- **Password**: `svxlink`

## Audio Monitoring Configuration

### Creating a systemd Service

Create the service file:

```bash
sudo nano /etc/systemd/system/svxlink-audio-proxy.service
```

Paste the contents:

```ini
[Unit]
Description=SVXLink Node.js Server
After=network.target

[Service]
StandardOutput=journal
StandardError=journal
Restart=always
RestartSec=5
ExecReload=/bin/kill -HUP
TimeoutStopSec=10
Type=simple
User=svxlink
Group=svxlink
ExecStart=/usr/bin/node /var/www/html/scripts/svxlink-audio-proxy-server.js
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
```

### Audio Monitoring Service Management

To reduce load, the service automatically shuts down when there is no connection and starts when monitoring is enabled in the dashboard menu.

For manual service management, use the following commands:

```bash
# Start the server
sudo systemctl start svxlink-audio-proxy.service

# Stop the server
sudo systemctl stop svxlink-audio-proxy.service

# Restart the server
sudo systemctl restart svxlink-audio-proxy.service

# Enable service to start on boot
sudo systemctl enable svxlink-audio-proxy.service

# Disable service from starting on boot
sudo systemctl disable svxlink-audio-proxy.service

# Check status
sudo systemctl status svxlink-audio-proxy.service

# View logs in real time
sudo journalctl -u svxlink-audio-proxy.service -f

# Logs from the last hour
sudo journalctl -u svxlink-audio-proxy.service --since="1 hour ago"
```