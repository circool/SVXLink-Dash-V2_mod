# Technical Specification: net_activity.php

## Overview

**File:** `/include/net_activity.php`  
**Version:** 0.4.6  
**Purpose:** Display and filter network activity (Frn, Reflector, EchoLink)  
**Dependencies:** logTailer.php, parseXmlTags.php, getLineTime.php, getTranslation.php, formatDuration.php

---

## 1. Operation Modes

### 1.1 Normal Mode (embedded in index.php)
```php
if (!isset($_GET['ajax']))
```
- Included directly in index.php
- Renders full block with filters and table
- Saves GET filter parameters to session

### 1.2 AJAX Mode (periodic updates)
```php
if (isset($_GET['ajax']) && $_GET['ajax'] == 1)
```
- Called via block_updater.js
- Returns table HTML only
- Supports both GET and POST methods

---

## 2. Filter Handling

### 2.1 Configuration Storage

**Session variables:**
```php
$_SESSION['net_filter']      // 'ON' or 'OFF' - show all or filter
$_SESSION['net_filter_max']  // float, threshold (0.5-5.0 sec)
```

**Write scenarios:**

1. **On index.php load:**
```php
if (isset($_GET['filter_activity'])) {
    $_SESSION['net_filter'] = $_GET['filter_activity'];
}
```
GET parameters from URL are saved to session on full page load.

2. **On user filter change:**
```javascript
fetch('/include/net_activity.php?ajax=1', {
    method: 'POST',
    body: JSON.stringify({
        filter_activity: 'ON/OFF',
        filter_activity_max: 1.5
    })
})
```
POST request saves settings to session without page reload.

3. **On AJAX update:**
```php
// GET request from block_updater.js
echo getNetActivityTable();  // reads settings from session
```

### 2.2 Filter Application

In `getNetActivityActions()`:
```php
$min_duration = 1;  // base value
if (isset($_SESSION['net_filter']) && $_SESSION['net_filter'] === 'OFF') {
    $min_duration = 0;  // show everything
} elseif (isset($_SESSION['net_filter_max'])) {
    $min_duration = $_SESSION['net_filter_max'];  // cutoff threshold
}
```

Filter applied in two places:
```php
// On transmission end
if ($stop - $row['start'] > $min_duration) {
    // save only transmissions longer than threshold
}

// For incomplete transmissions
if ($lastMsgDuration > $min_duration) {
    // show only if already longer than threshold
}
```

### 2.3 Filter Constraints
- HTML constraint: `min="0.5" max="5" step="0.5"`
- Session does not enforce limits (trusts HTML)
- When `filter_activity = OFF` (`min_duration = 0`), **all** transmissions are shown, including kerchunks

---

## 3. Network Activity Collection Algorithm

### 3.1 Log Line Selection Criteria
```php
$or_condition = [
    'Turning the transmitter',  // transmitter ON/OFF
    'voice started',           // Frn voice activity start
    'Talker start',           // reflector activity start
    'Talker stop',           // reflector activity end
    'message received from'  // EchoLink messages (chat/info)
];
```

### 3.2 Sample Size
**Current implementation:**
```php
$log_actions = getLogTailFiltered(
    NET_ACTIVITY_LIMIT * 15,  // hardcoded multiplier
    null, 
    $or_condition, 
    $actualLogSize
);
```
**Issue:** Does not depend on `$min_duration`. With high filter thresholds, the sample may not contain enough events.

**Recommendation:** Implement dynamic multiplier based on `$min_duration`.

---

## 4. Source Parsing

### 4.1 Frn (Free Radio Network)
**Trigger:** line contains `voice started`  
**Flag:** `$source = "Frn"`  
**Parsing:** `parseXmlTags($parent)`  
**Output format:**
```
Frn: <b>{ON}</b>, {CT} ({BC} / {DS})
```
**On failure:** `$source = ''`

### 4.2 Reflector
**Trigger:** line contains `Talker start`  
**Flag:** `$source = "Reflector"`  
**Parsing:** regexp `/^(.+?): (\S+): Talker start on TG #(\d*): (\S+)$/`  
**Output format:**
```
{logic_name}: <b>{callsign} in TG: {number}</b>
```
**On failure:** `$source = ''`

### 4.3 EchoLinkConference
**Trigger:** line contains `message received from`  
**Flag:** `$source = "EchoLinkConference"`  
**Parsing:** regexp `/received from (.+) ---$/`  
**Output format:**
```
EchoLink Conference <b>{conference_name}</b>
```
**On failure:** `$source = ''`

### 4.4 Critical Time Constraint
```php
if ($diff_sec > ACTION_LIFETIME) {  // ACTION_LIFETIME = 1
    $parent = '';
    $source = '';  // reset if event is older than 1 second
}
```
Only events that occurred no more than 1 second before transmitter activation are considered to have caused this transmission.

---

## 5. Event Data Structure

```php
$row = [
    'start'       => int,    // transmission start timestamp
    'date'        => string, // date in 'd M Y' format
    'time'        => string, // time in 'H:i:s' format
    'source'      => string, // HTML string with source description
    'destination' => string, // not used
    'duration'    => int     // duration in seconds
];
```

### 5.1 Flag Cleaning
```php
if ($source === 'Frn' || $source === 'Reflector' || $source === 'EchoLinkConference') {
    $row['source'] = '';  // unrecognized source not saved
}
```
This ensures only successfully parsed events appear in the table.

---

## 6. Event Lifecycle

1. **Cause detection** (`voice started`/`Talker start`/`message received from`)
   - Store line in `$parent`
   - Set `$source` flag

2. **Transmitter ON** (`Turning the transmitter ON`)
   - Check `!empty($parent)`
   - Verify `$diff_sec <= ACTION_LIFETIME`
   - Parse source
   - Create record with `$row['start']`

3. **Active transmission**
   - `$row['start']` > 0
   - New cause events are ignored

4. **Transmitter OFF** (`Turning the transmitter OFF`)
   - Calculate duration: `$stop - $row['start']`
   - Apply `$min_duration` filter
   - Save to `$result[]`
   - Reset all variables

5. **Incomplete transmission** (end of processing loop)
   - If `$row['start']` > 0 and transmitter still active
   - Calculate current duration: `time() - $row['start']`
   - Apply `$min_duration` filter
   - Save to `$result[]`

---

## 7. Output Formatting

### 7.1 Sorting
```php
array_slice(array_reverse($result), 0, NET_ACTIVITY_LIMIT)
```
- Events sorted newest to oldest
- Limited by `NET_ACTIVITY_LIMIT` constant (from settings.php)

### 7.2 Timezone
```php
if (isset($_SESSION['TIMEZONE'])) {
    date_default_timezone_set($_SESSION['TIMEZONE']);
}
```
Timezone is set in `init.php` and stored in session.

---

## 8. Known Issues and Recommendations

### 8.1 Sample Size
**Current state:** Fixed multiplier 15 independent of filter threshold.  
**Recommendation:** Implement dynamic calculation:
```php
$multiplier = $min_duration == 0 ? 50 : 15 * (1 + $min_duration * 0.5);
$log_limit = (int)(NET_ACTIVITY_LIMIT * min(100, $multiplier));
```

### 8.2 Session Data Validation
**Current state:** No limits on session values.  
**Recommendation:** Add normalization on read:
```php
$min_duration = $_SESSION['net_filter_max'] ?? 1;
$min_duration = max(0.5, min(30, $min_duration));  // 0.5-30 sec
if ($_SESSION['net_filter'] === 'OFF') $min_duration = 0;
```

### 8.3 Parse Error Handling
**Current state:** Flags reset to empty string.  
**Recommendation:** Log parsing failures for debugging:
```php
if ($source === 'EchoLinkConference' && !preg_match(...)) {
    error_log("Failed to parse EchoLink conference from: " . $parent);
    $source = '';
}
```

### 8.4 Performance
**Current state:** Log is read on every AJAX update.  
**Recommendation:** Cache parsing results for short period (1-2 sec).

---

## 9. Key Constants

| Constant | Value | Purpose |
|----------|-------|---------|
| `ACTION_LIFETIME` | 1 | Max delay between event and transmitter ON (sec) |
| `NET_ACTIVITY_LIMIT` | from settings | Max displayed records |
| `min="0.5" max="5"` | HTML | Filter slider boundaries |

---

## 10. Session Data Structure

```php
$_SESSION['status'] = [
    'service' => [
        'log_line_count' => int,  // log size for search context
        // ...
    ]
    // ...
];

$_SESSION['net_filter'] = 'ON'|'OFF';
$_SESSION['net_filter_max'] = float;  // 0.5-5.0
$_SESSION['TIMEZONE'] = string;  // timezone
```

---

## Conclusion

The net_activity.php module implements complex logic for correlating transmitter activations with network events using SvxLink log timestamps. Duration filtering is implemented via session variables, allowing AJAX updates to work without modifying block_updater.js.

The main deficiency of the current implementation is the static log sample size, which does not account for the filter threshold. Implementing a dynamic multiplier based on exponential dependency is recommended.