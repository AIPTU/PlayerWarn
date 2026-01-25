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

	private \DateTimeImmutable $timestamp;

	public function __construct(
		private int $id,
		private string $playerName,
		private string $reason,
		private string $source,
		private ?\DateTimeImmutable $expiration = null,
		?\DateTimeImmutable $timestamp = null
	) {
		$this->playerName = strtolower($playerName);
		$this->timestamp = $timestamp ?? new \DateTimeImmutable();
	}

	/**
	 * Create a new WarnEntry object from an array of data.
	 *
	 * @throws \InvalidArgumentException if required fields are missing, a field has an invalid type, or a date is in an invalid format
	 */
	public static function fromArray(array $data) : self {
		$requiredFields = ['player', 'reason', 'source', 'timestamp']; // id might not be in required if we assume it's set manually later? No, fromArray usually implies complete object.
		// Wait, fromArray is used when fetching from DB. existing code:
		// $id = isset($data['id']) && is_int($data['id']) ? $data['id'] : null;
		// If ID is non-nullable, it MUST be in data.

		// If fromArray is used for migration (warnings.json doesn't have ID), we need to handle that.
		// The migration code adds warn to provider. Provider usually inserts.
		// If we make WarnEntry ID non-nullable, we cannot represent a migration entry as a WarnEntry before insert.
		// So migration logic needs to pass raw data to addWarn, NOT a WarnEntry.

		if (!isset($data['id']) || !is_int($data['id'])) {
			// For migration or legacy, we might not have ID. But WarnEntry now requires it.
			// If this method is ONLY for DB retrieval, we strictly require ID.
			throw new \InvalidArgumentException("Invalid or missing 'id' field.");
		}

		$id = $data['id'];

		$missingFields = array_diff($requiredFields, array_keys($data));
		if (count($missingFields) > 0) {
			throw new \InvalidArgumentException('Invalid data format for WarnEntry. Missing fields: ' . implode(', ', $missingFields));
		}

		if (!is_string($data['player'])) {
			throw new \InvalidArgumentException("Invalid 'player' field. Expected a string.");
		}

		$playerName = trim($data['player']);
		if ($playerName === '') {
			throw new \InvalidArgumentException("'player' field cannot be empty.");
		}

		if (!is_string($data['reason'])) {
			throw new \InvalidArgumentException("Invalid 'reason' field. Expected a string.");
		}

		$reason = trim($data['reason']);
		if ($reason === '') {
			throw new \InvalidArgumentException("'reason' field cannot be empty.");
		}

		if (!is_string($data['source'])) {
			throw new \InvalidArgumentException("Invalid 'source' field. Expected a string.");
		}

		$source = trim($data['source']);
		if ($source === '') {
			throw new \InvalidArgumentException("'source' field cannot be empty.");
		}

		$expiration = null;
		if (isset($data['expiration'])) {
			// Handle DB storage where null might be passed as null or string "null" depending on driver, but here we expect data from array
			if (!is_string($data['expiration'])) {
				throw new \InvalidArgumentException("Invalid 'expiration' field. Expected a string or 'Never'.");
			}

			if (is_string($data['expiration'])) {
				$expirationString = trim($data['expiration']);
				if (strtolower($expirationString) !== 'never') {
					$expiration = self::parseDateTime($expirationString, 'expiration date');
				}
			}
		}

		if (!is_string($data['timestamp'])) {
			throw new \InvalidArgumentException("Invalid 'timestamp' field. Expected a string.");
		}

		$timestamp = self::parseDateTime($data['timestamp'], 'timestamp');

		return new self($id, $playerName, $reason, $source, $expiration, $timestamp);
	}

	/**
	 * Parses a date/time string into a DateTimeImmutable object.
	 *
	 * @throws \InvalidArgumentException if the date/time string has an invalid format
	 */
	private static function parseDateTime(string $dateTimeString, string $fieldName) : \DateTimeImmutable {
		$dateTime = \DateTimeImmutable::createFromFormat(self::DATE_TIME_FORMAT, $dateTimeString);
		if ($dateTime === false) {
			// Try standard SQL format if default fails (just in case)
			$dateTime = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $dateTimeString);
		}

		if ($dateTime === false) {
			throw new \InvalidArgumentException("Invalid {$fieldName} format: '{$dateTimeString}'. Expected format: '" . self::DATE_TIME_FORMAT . "'");
		}

		return $dateTime;
	}

	/**
	 * Get the unique ID of the warning.
	 */
	public function getId() : ?int {
		return $this->id;
	}

	/**
	 * Get the name of the player who received the warning.
	 */
	public function getPlayerName() : string {
		return $this->playerName;
	}

	/**
	 * Get the reason for issuing the warning.
	 */
	public function getReason() : string {
		return $this->reason;
	}

	/**
	 * Get the source or issuer of the warning.
	 */
	public function getSource() : string {
		return $this->source;
	}

	/**
	 * Get the timestamp when the warning was created.
	 */
	public function getTimestamp() : \DateTimeImmutable {
		return $this->timestamp;
	}

	/**
	 * Get the expiration date and time of the warning.
	 */
	public function getExpiration() : ?\DateTimeImmutable {
		return $this->expiration;
	}

	/**
	 * Check if the warning has expired based on the expiration date.
	 */
	public function hasExpired(?\DateTimeImmutable $now = null) : bool {
		$now ??= new \DateTimeImmutable();
		return $this->expiration !== null && $this->expiration < $now;
	}

	/**
	 * Convert the WarnEntry object to an associative array for serialization.
	 */
	public function toArray() : array {
		return [
			'id' => $this->id,
			'player' => $this->playerName,
			'reason' => $this->reason,
			'source' => $this->source,
			'timestamp' => $this->timestamp->format(self::DATE_TIME_FORMAT),
			'expiration' => $this->expiration !== null ? $this->expiration->format(self::DATE_TIME_FORMAT) : 'Never',
		];
	}
}