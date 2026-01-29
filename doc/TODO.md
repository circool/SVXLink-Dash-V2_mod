# Known issues and unimplemented features.

## Bugs.

[ ] Sending DTMF from the keypad or macro panel works after 2 unsuccessful attempts (Links control works correctly).

[ ] `block_updater.js` does not have access to the updatable elements on the first run.

[+] When reflector connected ("...: ReflectorLogic...: Connected nodes: ..."), reflector's cell set to orange color(paused) instead green (connected)

[+] When reflector disconnected, need also clear tooltip timer instead stopping timer only

[+] Squelch messages must to stop collecting packet El chat message parsing

## Unimplemented features.

[+] Add EchoLink conference server & proxy state to left panel

[+] Add APRS server state to left panel
[+] Macro commands must use logic-specific `DTMF_CTRL_PTY` instead `$_SESSION_['DTMF_CTRL_PTY']`

[+] DTMF Keypad need handling target logic selector
[+] Reflector link need handling target logic selector too

### Error control and their display. 

[ ] Show errors in audio devices (incl. multiple devices)

[+] Show PEAK METER events

[ ] Implement verbal indication of reflector's talk groups.

[ ] Implement log message display somewhere in a separate window.

[ ] Any valuable features that fellow amateur radio colleagues might suggest to me.

[ ] Need process alse Talker audio timeout on TG #... messages too - drop Transmitter timer or change transmitt cell color to something specific color

