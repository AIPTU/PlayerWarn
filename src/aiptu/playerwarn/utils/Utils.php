<?php

/*
 * Copyright (c) 2023-2026 AIPTU
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/AIPTU/PlayerWarn
 */

declare(strict_types=1);

namespace aiptu\playerwarn\utils;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use function array_slice;
use function array_sum;
use function count;
use function end;
use function implode;
use function intdiv;
use function preg_match;
use function strtr;

class Utils {
	public const string DATE_TIME_FORMAT = 'Y-m-d H:i:s';

	/**
	 * Parses a date/time string into a DateTimeImmutable object.
	 *
	 * @throws InvalidArgumentException if the date/time string has an invalid format
	 */
	public static function parseDateTime(string $dateTimeString, string $fieldName) : DateTimeImmutable {
		$dateTime = DateTimeImmutable::createFromFormat(self::DATE_TIME_FORMAT, $dateTimeString);

		if ($dateTime === false) {
			throw new InvalidArgumentException(
				"Invalid {$fieldName} format: '{$dateTimeString}'. Expected format: '" . self::DATE_TIME_FORMAT . "'"
			);
		}

		return $dateTime;
	}

	/**
	 * Parses a duration string and returns a DateTimeImmutable object representing the duration.
	 *
	 * The duration string should be a combination of digits followed by d (days), h (hours), m (minutes), and s (seconds).
	 * Example: "1d2h30m" represents 1 day, 2 hours, and 30 minutes.
	 *
	 * @param string $durationString the duration string to be parsed
	 *
	 * @return DateTimeImmutable|null the parsed DateTimeImmutable object representing the duration, or null if the duration string is empty
	 *
	 * @throws InvalidArgumentException when the duration string format is invalid
	 */
	public static function parseDurationString(string $durationString) : ?DateTimeImmutable {
		$pattern = '/^(\d+d)?(\d+h)?(\d+m)?(\d+s)?$/';

		if (preg_match($pattern, $durationString, $matches) !== 1) {
			throw new InvalidArgumentException('Invalid duration string format. The format should be a combination of digits followed by d (days), h (hours), m (minutes), and s (seconds). Example: 1d2h30m');
		}

		$duration = [
			'days' => isset($matches[1]) ? (int) $matches[1] : 0,
			'hours' => isset($matches[2]) ? (int) $matches[2] : 0,
			'minutes' => isset($matches[3]) ? (int) $matches[3] : 0,
			'seconds' => isset($matches[4]) ? (int) $matches[4] : 0,
		];

		if (array_sum($duration) === 0) {
			return null;
		}

		$now = new DateTimeImmutable();
		$interval = new DateInterval('P' . $duration['days'] . 'DT' . $duration['hours'] . 'H' . $duration['minutes'] . 'M' . $duration['seconds'] . 'S');

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
				$parts[] = "{$value} {$unit}" . ($value > 1 ? 's' : '');
				$duration -= $value * $secondsPerUnit;
			}
		}

		$count = count($parts);

		return match ($count) {
			0 => '0 seconds',
			1 => $parts[0],
			2 => implode(' and ', $parts),
			default => implode(', ', array_slice($parts, 0, -1)) . ', and ' . end($parts),
		};
	}

	/**
	 * Formats a DateTimeImmutable object into a human-readable "time ago" string.
	 *
	 * @param DateTimeImmutable $dateTime the date/time to format
	 *
	 * @return string the formatted time ago string
	 */
	public static function formatTimeAgo(DateTimeImmutable $dateTime) : string {
		$now = new DateTimeImmutable();
		$diff = $now->getTimestamp() - $dateTime->getTimestamp();

		if ($diff < 60) {
			return 'just now';
		}

		if ($diff < 3600) {
			$minutes = (int) ($diff / 60);
			return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
		}

		if ($diff < 86400) {
			$hours = (int) ($diff / 3600);
			return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
		}

		if ($diff < 604800) {
			$days = (int) ($diff / 86400);
			return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
		}

		return $dateTime->format(self::DATE_TIME_FORMAT);
	}

	/**
	 * Replaces variables in a given string with their corresponding values from the provided associative array.
	 *
	 * @param string $str  the input string containing placeholders to be replaced
	 * @param array  $vars an associative array where keys represent the placeholder names, and values are the replacements
	 *
	 * @return string the string with all placeholders replaced by their corresponding values
	 */
	public static function replaceVars(string $str, array $vars) : string {
		$replacements = [];
		foreach ($vars as $key => $value) {
			$replacements["{{$key}}"] = (string) $value;
		}

		return strtr($str, $replacements);
	}
}