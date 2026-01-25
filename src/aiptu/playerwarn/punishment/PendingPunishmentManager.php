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

namespace aiptu\playerwarn\punishment;

use function count;

class PendingPunishmentManager {
	/** @var array<string, list<array{type: PunishmentType, issuer: string, reason: string}>> */
	private array $pendingPunishments = [];

	public function add(
		string $playerName,
		PunishmentType $type,
		string $issuerName,
		string $reason
	) : void {
		$this->pendingPunishments[$playerName][] = [
			'type' => $type,
			'issuer' => $issuerName,
			'reason' => $reason,
		];
	}

	public function hasPending(string $playerName) : bool {
		return isset($this->pendingPunishments[$playerName])
			&& count($this->pendingPunishments[$playerName]) > 0;
	}

	/**
	 * @return list<array{type: PunishmentType, issuer: string, reason: string}>
	 */
	public function getPending(string $playerName) : array {
		return $this->pendingPunishments[$playerName] ?? [];
	}

	public function clear(string $playerName) : void {
		unset($this->pendingPunishments[$playerName]);
	}

	public function count(string $playerName) : int {
		return count($this->pendingPunishments[$playerName] ?? []);
	}
}