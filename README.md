![Project Status](https://img.shields.io/badge/pre-release-red?style=flat)
![Version](https://img.shields.io/badge/Version-0.4.x-red?style=flat)
![License](https://img.shields.io/badge/License-GNU_FDL_v1.3-green?style=flat)


# Svxlink Dashboard

A functional pre-release version.


## Inspiration

This project was inspired by the functionality of:

- [SVXLink-Dashboard-V2 by F5VMR](https://github.com/f5vmr/SVXLink-Dash-V2)

and the design of

- [WPSD Project](https://w0chp.radio/wpsd/)

## License

The project incorporates design elements derived from the WPSD Project (styles and element layout),
distributed under the GNU FDL v1.3 license.

Original copyright: Copyright © 2023 WPSD Project Development Team, et al.

## Description

A fully rewritten implementation of an SVXLink control panel with a modern architecture.

Detailed technical information is available in **PROJECT_DETAILS.md**

## Features

This is a tested and functional version, though it is still under active development (minor issues are being fixed, approaches and algorithms optimized, and features added). Please be patient.

I have made every effort to ensure you don't encounter difficulties; however, there's always a chance that the instructions may contain inaccuracies or outdated information.

### New Approach

The panel is designed for SVXLink owners/administrators, reflecting the server's real-time[^note1] status and allowing control over its state[^note2].

### Modern Interface

- Interface in the WPSD style

![Main screen](./readme_images/main_screen_en.jpg)

- Disablable  [^note3] multilingual interface with the ability to add custom languages

### Dual-mode updates

- Available realtime and periodic update mode

![Menu](./readme_images/top_menu_en_realtime.jpg)
![Menu](./readme_images/top_menu_en_ajax.jpg)

### Service, Logic, Reflectorsm, Modules and Link states

#### Service
![Service](./readme_images/service_en.jpg)

#### Logic and module state

- Active but not connected

![Modules](./readme_images/module_disconnected_en.jpg)

- Active and connected

![Modules](./readme_images/module_connected_en.jpg)

#### Mouse swhitchable modules and links

![Modules](./readme_images/switchable_module_state_en.jpg)

#### Reflector's state and information 

![Reflectors](./readme_images/reflector_en.jpg)


#### Extended pop-up link's information

![Link](./readme_images/switchable_links_en.jpg)


#### Different colours for selected ans temporally monitored talkgroup

![Talk Groups](./readme_images/tg_panel.jpg)

### Additional information

#### APRS connection status

![APRS](./readme_images/aprs_en.jpg)

#### Echolink directory and proxy status

![EL Directory](./readme_images/directory_en.jpg)

#### Hideable macros panel

Different button colors for different modules

![Macros](./readme_images/macros_en.jpg)

### Receiver and Transmitter status

#### Main receiver & transmitter row

![Radio status](./readme_images/radio_status_single_en.jpg)
![Radio status](./readme_images/el_callsign_en.jpg)
![Radio status](./readme_images/radio_transmit_en.jpg)

#### Additional reflector's rows

![Radio status](./readme_images/radio_status_en.jpg)

#### Error detecting

Overload and timeout error indication for devices (including composite devices)

![Peak](./readme_images/peak_meter_en.jpg)
![Peak](./readme_images/tx_error_en.jpg)
![Peak](./readme_images/tx_timeout_en.jpg)

### Convenient DTMF keypad controllable by mouse or direct keyboard input for managing the server state, similar to sending control signals over the air

#### Single logic compact view

![DTMF Keypad](./readme_images/keypad_single_en.jpg)

#### Multiple logic supporting

![DTMF Keypad](./readme_images/dtmf_keypad.jpg)


### Audio monitoring of the transmitted signal

Listen activity with web-browser

![Monitor off](./readme_images/monitor_off_en.jpg)
![Monitor on](./readme_images/monitor_on_en.jpg)

### Hideable information about current state

#### EchoLink chat, message and malformed support

![EchoLink details](./readme_images/el_msg_en.jpg)
![EchoLink details](./readme_images/el_info_en.jpg)
![EchoLink details](./readme_images/el_repeater_details.jpg)

#### Frn server connected nodes

![Frn details](./readme_images/frn_details_en.jpg)

### Historical data with ajustable kerchungs limits

![kerchungs](./readme_images/kerchung_en.jpg)

#### Ajustable [^note3] network activity

![network](./readme_images/network_activity_en.jpg)

#### Ajustable [^note3] local activity (RF incoming)

![rf](./readme_images/rf_activity_en.jpg)


## Technical Notes

This is an independent implementation, primarily related to the original project by similar principles.


## Important Compatibility Note

**This project is NOT a replacement or update for the original SVXLink-Dashboard-V2**


### What was preserved from the original:

**The audio monitoring system** was preserved from the [source - f5vmr/SVXLink-Dash-V2](https://github.com/f5vmr/SVXLink-Dash-V2) as a proven and functional solution.


## For Users of the Original Project

If you are looking for:
- **Updates for the original SVXLink-Dashboard-V2** → please refer to the [original repository](https://github.com/f5vmr/SVXLink-Dash-V2)
- **Compatible enhancements** → this project is not for you
- **A fully reworked alternative** → please continue reading

## Installation

Deployment instructions are available in `doc/INSTALL.md`


[^note1]: Radio status, active module, reflector, connected nodes.
[^note2]: The `DTMF_CTRL_PTY` parameter must be configured for control.
[^note3]: Configured via parameter in `settings.php` file.
