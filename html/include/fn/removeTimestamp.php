<?php

/**
 * @author vladimir@tsurkanenko.ru
 * @version 0.1.14.release
 * @date 2025.12.20
 * @filesource include/fn/removeTimestamp.php
 */
function removeTimestamp(string $logLine): string
{
	if (str_starts_with($logLine, ' ')) {
		return $logLine;
	}

	$hasTimeStamp = false;

	// choise ": " (timestamp + payload)
	if (str_contains($logLine, ': ')) {
		$hasTimeStamp = true;
	}
	// choise ":" (only timestamp)
	elseif (str_ends_with($logLine, ':')) {
		$hasTimeStamp = true;
	}

	if ($hasTimeStamp) {
		$pos = strpos($logLine, ': ');

		if ($pos !== false) {
			return substr($logLine, $pos + 2);
		} elseif (str_ends_with($logLine, ':')) {
			return substr($logLine, strrpos($logLine, ':') + 1);
		}
	}
	return $logLine;
}
?>