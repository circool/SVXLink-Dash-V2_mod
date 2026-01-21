# SVXLink Dashboard Installation Guide

## Mandatory Requirements

1. Set the system timezone in `/etc/timezone`.
2. Ensure that SVXlink has the `TIMESTAMP_FORMAT` parameter with milliseconds in the `[GLOBAL]` section. For example:  
   `TIMESTAMP_FORMAT = "%d %b %Y %H:%M:%S.%f"` (`.%f` - milliseconds).

## Optional Settings

3. For DTMF control, ensure that the SVXlink logic has the `DTMF_CTRL_PTY` parameter set to `/dev/shm/dtmf_ctrl` or another path.
4.1. For audio monitoring, configure multiple devices for the logic's `TX` device.

```svxlink.conf
...
[SimplexLogic]
#TX = Tx1
TX=MultiTx
...
```
4.2. Add the device itself (similar to [TX1])

```svxlink.conf
...
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


## Quick Installation

1. **Upload Files**: Copy all files to your web server.
2. **Copy and run the installation script** from `doc/install.sh`.
3. @TODO **Automatic Setup**: Open your browser and navigate to your site.
4. @TODO **One-Click Setup**: You will be automatically redirected to the setup page.
5. @TODO **Click "Run Setup"**: The system will create all necessary files.
6. **Login**: Use default credentials: **svxlink** / **svxlink**.
7. **Secure**: Change the password after the first login.

## Default Credentials
- **Username**: `svxlink`
- **Password**: `svxlink`

## Manual Installation (if needed)

```bash
# Create the dashboard directory
sudo mkdir -p /etc/svxlink/dashboard

# Set proper ownership (adjust www-data to your web server user if different)
sudo chown svxlink:svxlink /etc/svxlink/dashboard

# Set proper permissions
sudo chmod 755 /etc/svxlink/dashboard

# Copy sample config
sudo cp config/sample.auth.ini /etc/svxlink/dashboard/auth.ini

# Set permissions (adjust for your web server user)
sudo chown www-data:www-data /etc/svxlink/dashboard/auth.ini
sudo chmod 644 /etc/svxlink/dashboard/auth.ini
```

## Support

If setup fails, check:

- Web server has write permissions.
- PHP can create directories.
- No firewall is blocking access.

After setup, remove the install directory for security.

## SVXLink Setup

### Additional Logic

When adding non-standard logic (for example, for a `ReflectorLogicMyName`), remember to create a corresponding TCL file by copying an existing standard one and editing its workspace name.

```bash
cp ReflectorLogic.tcl ReflectorLogicMyName.tcl
nano ReflectorLogicMyName.tcl
```

```tcl
###############################################################################
#
# ReflectorLogic event handlers
#
###############################################################################

#
# This is the namespace in which all functions below will exist. The name
# must match the corresponding section "[ReflectorLogic]" in the configuration
# file. The name may be changed but it must be changed in both places.
#
namespace eval ReflectorLogic {
...

```

Rename `ReflectorLogic` to `ReflectorLogicMyName` and save the file.

## Beta Testing Notes

1. Check if necessary files are present in the site directory:

```bash
sudo chmod +x files_check.sh
./files_check.sh
```

2. Update symlinks for `/include`, `/include/fn/`, and `/scripts` directories:

```bash
cd /var/www/html/include
sudo chmod +x update_symlink.sh
sudo ./update_symlink.sh exct

cd /var/www/html/include/fn
sudo chmod +x update_symlink.sh
sudo ./update_symlink.sh exct

cd /var/www/html/scripts
sudo chmod +x update_symlink.sh
sudo ./update_symlink.sh exct

cd /var/www/html
sudo chmod +x update_symlink.sh
sudo ./update_symlink.sh include/exct

sudo ./set_rights.sh
```
