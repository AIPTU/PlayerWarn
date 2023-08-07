<?php

/*
 * Copyright (c) 2023 AIPTU
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/AIPTU/PlayerWarn
 */

declare(strict_types=1);

namespace aiptu\playerwarn\utils;

use function array_pop;
use function array_sum;
use function count;
use function implode;
use function intdiv;
use function preg_match;
use function str_replace;

class Utils {
	/**
	 * Parses a duration string and returns a DateTimeImmutable object representing the duration.
	 *
	 * The duration string should be a combination of digits followed by d (days), h (hours), m (minutes), and s (seconds).
	 * Example: "1d2h30m" represents 1 day, 2 hours, and 30 minutes.
	 *
	 * @param string $durationString the duration string to be parsed
	 *
	 * @return \DateTimeImmutable|null the parsed DateTimeImmutable object representing the duration, or null if the duration string is empty
	 *
	 * @throws \InvalidArgumentException when the duration string format is invalid
	 */
	public static function parseDurationString(string $durationString) : ?\DateTimeImmutable {
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

		$now = new \DateTimeImmutable();
		$interval = new \DateInterval('P' . $duration['days'] . 'DT' . $duration['hours'] . 'H' . $duration['minutes'] . 'M' . $duration['seconds'] . 'S');

		return $now->add($interval);
	}

	/**
	 * Formats a duration in seconds into a human-readable string.
	 *
	 * The duration is formatted as a combination of years, days, hours, minutes, and seconds.
	 * The resulting string will include only the necessary units, e.g., "2 days 5 hours" or "1 hour 30 minutes".
	 *
	 * @param int $duration the duration in seconds to be formatted
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

	/**
	 * Replaces variables in a given string with their corresponding values from the provided associative array.
	 *
	 * The `replaceVars` method searches for placeholders in the input string and replaces them with the associated values
	 * from the `$vars` array. The placeholders in the string should be enclosed in curly braces, like "{key}". Each placeholder
	 * is replaced with the corresponding value for the provided key from the `$vars` array. If a key in the `$vars` array
	 * does not exist in the input string, it will be left as-is in the output string.
	 *
	 * @param string $str  the input string containing placeholders to be replaced
	 * @param array  $vars an associative array where keys represent the placeholder names, and values are the replacements
	 *
	 * @return string the string with all placeholders replaced by their corresponding values
	 */
	public static function replaceVars(string $str, array $vars) : string {
		foreach ($vars as $key => $value) {
			$placeholder = '{' . $key . '}';
			$str = str_replace($placeholder, (string) $value, $str);
		}
		return $str;
	}
}
