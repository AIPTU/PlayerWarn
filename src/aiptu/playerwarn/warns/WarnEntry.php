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

namespace aiptu\playerwarn\warns;

use DateTimeImmutable;
use InvalidArgumentException;
use function array_diff;
use function array_keys;
use function count;
use function implode;
use function is_int;
use function is_string;
use function strtolower;
use function trim;

class WarnEntry {
	public const string DATE_TIME_FORMAT = 'Y-m-d H:i:s';

	private DateTimeImmutable $timestamp;

	public function __construct(
		private int $id,
		private string $playerName,
		private string $reason,
		private string $source,
		private ?DateTimeImmutable $expiration = null,
		?DateTimeImmutable $timestamp = null
	) {
		$this->playerName = strtolower($playerName);
		$this->timestamp = $timestamp ?? new DateTimeImmutable();
	}

	/**
	 * Create a new WarnEntry object from an array of data.
	 *
	 * @param array<string, mixed> $data
	 *
	 * @throws InvalidArgumentException if required fields are missing or invalid
	 */
	public static function fromArray(array $data) : self {
		$requiredFields = ['id', 'player', 'reason', 'source', 'timestamp'];
		$missingFields = array_diff($requiredFields, array_keys($data));

		if (count($missingFields) > 0) {
			throw new InvalidArgumentException(
				'Invalid data format for WarnEntry. Missing fields: ' . implode(', ', $missingFields)
			);
		}

		if (!isset($data['id']) || !is_int($data['id'])) {
			throw new InvalidArgumentException("Invalid or missing 'id' field. Expected an integer.");
		}

		if (!is_string($data['player'])) {
			throw new InvalidArgumentException("Invalid 'player' field. Expected a string.");
		}

		$playerName = trim($data['player']);
		if ($playerName === '') {
			throw new InvalidArgumentException("'player' field cannot be empty.");
		}

		if (!is_string($data['reason'])) {
			throw new InvalidArgumentException("Invalid 'reason' field. Expected a string.");
		}

		$reason = trim($data['reason']);
		if ($reason === '') {
			throw new InvalidArgumentException("'reason' field cannot be empty.");
		}

		if (!is_string($data['source'])) {
			throw new InvalidArgumentException("Invalid 'source' field. Expected a string.");
		}

		$source = trim($data['source']);
		if ($source === '') {
			throw new InvalidArgumentException("'source' field cannot be empty.");
		}

		$expiration = null;
		if (isset($data['expiration'])) {
			if (!is_string($data['expiration'])) {
				throw new InvalidArgumentException("Invalid 'expiration' field. Expected a string or null.");
			}

			$expirationString = trim($data['expiration']);
			if ($expirationString !== '' && strtolower($expirationString) !== 'never') {
				$expiration = self::parseDateTime($expirationString, 'expiration date');
			}
		}

		if (!is_string($data['timestamp'])) {
			throw new InvalidArgumentException("Invalid 'timestamp' field. Expected a string.");
		}

		$timestamp = self::parseDateTime($data['timestamp'], 'timestamp');

		return new self(
			$data['id'],
			$playerName,
			$reason,
			$source,
			$expiration,
			$timestamp
		);
	}

	/**
	 * Parses a date/time string into a DateTimeImmutable object.
	 *
	 * @throws InvalidArgumentException if the date/time string has an invalid format
	 */
	private static function parseDateTime(string $dateTimeString, string $fieldName) : DateTimeImmutable {
		$dateTime = DateTimeImmutable::createFromFormat(self::DATE_TIME_FORMAT, $dateTimeString);

		if ($dateTime === false) {
			$dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateTimeString);
		}

		if ($dateTime === false) {
			throw new InvalidArgumentException(
				"Invalid {$fieldName} format: '{$dateTimeString}'. Expected format: '" . self::DATE_TIME_FORMAT . "'"
			);
		}

		return $dateTime;
	}

	/**
	 * Get the ID of the warning.
	 */
	public function getId() : int {
		return $this->id;
	}

	/**
	 * Get the name of the player who received the warning.
	 */
	public function getPlayerName() : string {
		return $this->playerName;
	}

	/**
	 * Get the reason for the warning.
	 */
	public function getReason() : string {
		return $this->reason;
	}

	/**
	 * Get the source (issuer) of the warning.
	 */
	public function getSource() : string {
		return $this->source;
	}

	/**
	 * Get the timestamp of the warning.
	 */
	public function getTimestamp() : DateTimeImmutable {
		return $this->timestamp;
	}

	/**
	 * Get the expiration date/time of the warning, or null if it does not expire.
	 */
	public function getExpiration() : ?DateTimeImmutable {
		return $this->expiration;
	}

	/**
	 * Check if the warning has expired.
	 */
	public function hasExpired(?DateTimeImmutable $now = null) : bool {
		$now ??= new DateTimeImmutable();
		return $this->expiration !== null && $this->expiration <= $now;
	}

	/**
	 * Check if the warning is expiring.
	 */
	public function isExpiring() : bool {
		return $this->expiration !== null;
	}

	/**
	 * Convert the WarnEntry to an associative array.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray() : array {
		return [
			'id' => $this->id,
			'player' => $this->playerName,
			'reason' => $this->reason,
			'source' => $this->source,
			'timestamp' => $this->timestamp->format(self::DATE_TIME_FORMAT),
			'expiration' => $this->expiration?->format(self::DATE_TIME_FORMAT) ?? 'Never',
		];
	}
}