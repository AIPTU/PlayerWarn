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

namespace aiptu\playerwarn;

use function is_string;
use function strtolower;

class WarnEntry {
	public const DATE_TIME_FORMAT = 'Y-m-d H:i:s';

	private \DateTime $timestamp;

	public function __construct(
		private string $playerName,
		private string $reason,
		private string $source,
		private ?\DateTime $expiration = null
	) {
		$this->playerName = strtolower($playerName);
		$this->timestamp = new \DateTime();
	}

	public static function fromArray(array $data) : ?self {
		if (
			isset($data['player'], $data['reason'], $data['source'])
			&& is_string($data['player']) && is_string($data['reason']) && is_string($data['source'])
		) {
			$playerName = $data['player'];
			$reason = $data['reason'];
			$source = $data['source'];

			$expiration = null;
			if (isset($data['expiration']) && strtolower($data['expiration']) !== 'never') {
				$expiration = self::parseExpiration($data['expiration']);
				if ($expiration === null) {
					throw new \InvalidArgumentException('Invalid expiration date format or value: ' . $data['expiration']);
				}
			}

			return new self($playerName, $reason, $source, $expiration);
		}

		return null;
	}

	private static function parseExpiration(string $expirationString) : ?\DateTime {
		$dateTime = \DateTime::createFromFormat(self::DATE_TIME_FORMAT, $expirationString);
		if ($dateTime instanceof \DateTime) {
			return $dateTime;
		}

		return null;
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

	public function getTimestamp() : \DateTime {
		return $this->timestamp;
	}

	public function getExpiration() : ?\DateTime {
		return $this->expiration;
	}

	public function hasExpired() : bool {
		if ($this->expiration === null) {
			return false;
		}

		$now = new \DateTime();
		return $now >= $this->expiration;
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
