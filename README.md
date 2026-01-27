![Project Status](https://img.shields.io/badge/Status-Testing-red?style=flat)
![Version](https://img.shields.io/badge/Version-0.4.x-red?style=flat)
![License](https://img.shields.io/badge/License-GNU_FDL_v1.3-green?style=flat)


# Svxlink Dashboard

A functional beta version.


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

- Customizable multi-language interface in the WPSD style
![Menu](./readme_images/top_menu_en.jpg)
![Menu](./readme_images/top_menu_rus.jpg)

### Extended Monitoring

- Informative status panel with connection information

- Available and active modules, connected nodes/conferences/servers, etc. (for EchoLink/Frn modules)

![Modules](./readme_images/modules.jpg)

![EchoLink](./readme_images/el_panel.jpg)
![Frn](./readme_images/frn_panel.jpg)

- Reflectors, talk groups, links, connected nodes

![Reflector Talk Groups](./readme_images/reflectors_panel_rus.jpg)
![Talk Groups](./readme_images/tg_panel.jpg)
![Link Control](./readme_images/link.jpg)

- Convenient DTMF keypad controllable by mouse or direct keyboard input for managing the server state, similar to sending control signals over the air

![DTMF Keypad](./readme_images/dtmf_keypad.jpg)

- Audio monitoring of the transmitted signal

- Extended information from the active module
![EchoLink Conference Info](./readme_images/el_conf_details.jpg)
![Frn Nodes](./readme_images/frn_details.jpg)

- Historical data (last n on-air events, incoming calls from the network)


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