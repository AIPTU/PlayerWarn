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

	private static function parseExpiration(string $expirationString) : \DateTimeImmutable {
		$dateTime = \DateTimeImmutable::createFromFormat(self::DATE_TIME_FORMAT, $expirationString);
		if ($dateTime === false) {
			throw new \InvalidArgumentException('Invalid expiration date format: ' . $expirationString);
		}

		return $dateTime;
	}

	public function getPlayerName() : string {
		return $this->playerName;
	}

	public function getReason() : string {
		return $this->reason;
	}

	public function getSource() : string {
		return $this->source;
	}

	public function getTimestamp() : \DateTimeImmutable {
		return $this->timestamp;
	}

	public function getExpiration() : ?\DateTimeImmutable {
		return $this->expiration;
	}

	public function hasExpired() : bool {
		$now = new \DateTimeImmutable();
		return $this->expiration !== null && $this->expiration < $now;
	}

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
