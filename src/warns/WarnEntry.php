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

namespace aiptu\playerwarn\warns;

use function array_diff;
use function array_keys;
use function count;
use function implode;
use function strtolower;
use function trim;

class WarnEntry {
	public const DATE_TIME_FORMAT = 'Y-m-d H:i:s';

	private \DateTimeImmutable $timestamp;

	public function __construct(
		private string $playerName,
		private string $reason,
		private string $source,
		private ?\DateTimeImmutable $expiration = null
	) {
		$this->playerName = strtolower($playerName);
		$this->timestamp = new \DateTimeImmutable();
	}

	/**
	 * Create a new WarnEntry object from an array of data.
	 *
	 * @throws \InvalidArgumentException if required fields are missing or the expiration date is in an invalid format
	 */
	public static function fromArray(array $data) : self {
		$requiredFields = ['player', 'reason', 'source'];

		$missingFields = array_diff($requiredFields, array_keys($data));
		if (count($missingFields) > 0) {
			throw new \InvalidArgumentException('Invalid data format for WarnEntry. Missing fields: ' . implode(', ', $missingFields));
		}

		$playerName = trim($data['player']);
		$reason = trim($data['reason']);
		$source = trim($data['source']);

		$expiration = null;
		if (isset($data['expiration']) && strtolower(trim($data['expiration'])) !== 'never') {
			$expiration = self::parseExpiration($data['expiration']);
		}

		return new self($playerName, $reason, $source, $expiration);
	}

	/**
	 * Parses the expiration date from a string and returns a DateTimeImmutable object.
	 *
	 * @throws \InvalidArgumentException if the expiration date has an invalid format
	 */
	private static function parseExpiration(string $expirationString) : \DateTimeImmutable {
		$dateTime = \DateTimeImmutable::createFromFormat(self::DATE_TIME_FORMAT, $expirationString);
		if ($dateTime === false) {
			throw new \InvalidArgumentException('Invalid expiration date format: ' . $expirationString);
		}

		return $dateTime;
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
	public function hasExpired() : bool {
		$now = new \DateTimeImmutable();
		return $this->expiration !== null && $this->expiration < $now;
	}

	/**
	 * Convert the WarnEntry object to an associative array for serialization.
	 */
	public function toArray() : array {
		return [
			'player' => $this->playerName,
			'reason' => $this->reason,
			'source' => $this->source,
			'timestamp' => $this->timestamp->format(self::DATE_TIME_FORMAT),
			'expiration' => $this->expiration !== null ? $this->expiration->format(self::DATE_TIME_FORMAT) : 'Never',
		];
	}
}
