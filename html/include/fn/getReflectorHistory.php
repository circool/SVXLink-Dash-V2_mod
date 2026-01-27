<?php
function getReflectorHistory(string $reflector_name): array
{
	require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/logTailer.php';

	$required_condition = $reflector_name;
	$or_conditions = ['Talker start on TG', 'Talker stop on TG'];
	$limit = REFLECTOR_ACTIVITY_LIMIT * 5;

	if (isset($_SESSION['status']['service']['log_line_count']) && $_SESSION['status']['service']['log_line_count'] > 0) {
		$session_log_size = $_SESSION['status']['service']['log_line_count'];
	} else {
		$session_log_size = countLogLines("Tobias Blomberg");
	}

	$refl_history = getLogTailFiltered($limit, $required_condition, $or_conditions, $session_log_size);

	$result = [];
	$open_events = []; 

	if ($refl_history !== false) {
		foreach ($refl_history as $line) {
			if (strpos($line, 'Talker start on TG #') !== false) {
				preg_match('/:\s*([^:]+):\s*Talker start on TG #(\d+):\s*([^\s]+)/', $line, $m);
				if (isset($m[1], $m[2], $m[3])) {
					$event_start = getLineTime($line);
					$key = $m[2] . '|' . $m[3]; // Ключ по TG и callsign

					$open_events[$key] = [
						'reflector' => $m[1],
						'tg' => (int)$m[2],
						'callsign' => $m[3],
						'start' => $event_start,
						'start_line' => $line
					];
				}
			} else if (strpos($line, 'Talker stop on TG #') !== false) {
				preg_match('/:\s*([^:]+):\s*Talker stop on TG #(\d+):\s*([^\s]+)/', $line, $m);
				if (isset($m[1], $m[2], $m[3])) {
					$event_end = getLineTime($line);
					$key = $m[2] . '|' . $m[3];

					if (isset($open_events[$key])) {
						$event_start = $open_events[$key]['start'];
						$duration = $event_end - $event_start;
						if ($duration > 0) {

							$result[] = [
								'reflector' => $m[1],
								'tg' => (int)$m[2],
								'callsign' => $m[3],
								'start' => $event_start,
								'end' => $event_end,
								'duration' => $duration
							];
						}
						unset($open_events[$key]);
					}
				}
			}
		}
		usort($result, function ($a, $b) {
			return $b['end'] - $a['end'];
		});

		$result = array_slice($result, 0, REFLECTOR_ACTIVITY_LIMIT);
	}

	return $result;
}