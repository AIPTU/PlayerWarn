<?php

declare(strict_types=1);

namespace aiptu\playerwarn\utils;

use function array_pop;
use function array_sum;
use function count;
use function implode;
use function intdiv;
use function preg_match;

class Utils {
	/**
	 * Parses a duration string and returns a DateTime object representing the duration.
	 *
	 * The duration string should be a combination of digits followed by d (days), h (hours),
	 * m (minutes), and s (seconds). Example: 1d2h30m.
	 *
	 * @param string $durationString the duration string to parse
	 *
	 * @return null|\DateTime the parsed DateTime object representing the duration, or null if the duration is empty
	 *
	 * @throws \InvalidArgumentException when the duration string format is invalid
	 */
	public static function parseDurationString(string $durationString) : ?\DateTime {
		$pattern = '/^(\d+d)?(\d+h)?(\d+m)?(\d+s)?$/';

		if (preg_match($pattern, $durationString, $matches) !== 1) {
			throw new \InvalidArgumentException('Invalid duration string format. The format should be a combination of digits followed by d (days), h (hours), m (minutes), and s (seconds). Example: 1d2h30m');
		}

		$duration = [
			'days' => (int) ($matches[1] ?? 0),
			'hours' => (int) ($matches[2] ?? 0),
			'minutes' => (int) ($matches[3] ?? 0),
			'seconds' => (int) ($matches[4] ?? 0),
		];

		$hasDuration = array_sum($duration) > 0;

		if (!$hasDuration) {
			return null;
		}

		$now = new \DateTime();
		$interval = new \DateInterval('P' . $duration['days'] . 'DT' . $duration['hours'] . 'H' . $duration['minutes'] . 'M' . $duration['seconds'] . 'S');

		return $now->add($interval);
	}

	/**
	 * Formats a duration in seconds into a human-readable string.
	 *
	 * The duration is formatted as a combination of years, days, hours, minutes, and seconds.
	 * The resulting string will include only the necessary units, e.g., "2 days 5 hours" or "1 hour 30 minutes".
	 *
	 * @param int $duration the duration in seconds
	 *
	 * @return string the formatted duration string
	 */
	public static function formatDuration(int $duration) : string {
		$units = [
			['year', 60 * 60 * 24 * 365],
			['day', 60 * 60 * 24],
			['hour', 60 * 60],
			['minute', 60],
			['second', 1],
		];

		$parts = [];
		foreach ($units as [$unit, $secondsPerUnit]) {
			if ($duration >= $secondsPerUnit) {
				$value = intdiv($duration, $secondsPerUnit);
				$parts[] = $value . ' ' . $unit . ($value > 1 ? 's' : '');
				$duration -= $value * $secondsPerUnit;
			}
		}

		$count = count($parts);

		if ($count === 0) {
			return '0 seconds';
		}

		if ($count === 1) {
			return $parts[0];
		}

		if ($count === 2) {
			return implode(' and ', $parts);
		}

		$last = array_pop($parts);
		return implode(', ', $parts) . ', and ' . $last;
	}
}
