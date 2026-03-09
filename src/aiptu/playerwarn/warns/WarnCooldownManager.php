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

use function strtolower;
use function time;

class WarnCooldownManager {
	/**
	 * [senderName => [targetName => timestamp]].
	 *
	 * @var array<string, array<string, int>>
	 */
	private array $cooldowns = [];

	public function __construct(
		private int $cooldownSeconds
	) {}

	/**
	 * Returns true if the sender must still wait before warning targetName again.
	 */
	public function isOnCooldown(string $senderName, string $targetName) : bool {
		if ($this->cooldownSeconds <= 0) {
			return false;
		}

		$last = $this->cooldowns[strtolower($senderName)][strtolower($targetName)] ?? null;
		return $last !== null && (time() - $last) < $this->cooldownSeconds;
	}

	/**
	 * Returns the remaining cooldown in seconds (0 if none).
	 */
	public function getRemaining(string $senderName, string $targetName) : int {
		$last = $this->cooldowns[strtolower($senderName)][strtolower($targetName)] ?? null;
		if ($last === null) {
			return 0;
		}

		$remaining = $this->cooldownSeconds - (time() - $last);
		return $remaining > 0 ? $remaining : 0;
	}

	/**
	 * Record that senderName just warned targetName.
	 */
	public function record(string $senderName, string $targetName) : void {
		if ($this->cooldownSeconds <= 0) {
			return;
		}

		$this->cooldowns[strtolower($senderName)][strtolower($targetName)] = time();
	}

	/**
	 * Drop expired entries to prevent unbounded memory growth.
	 */
	public function cleanup() : void {
		$now = time();
		foreach ($this->cooldowns as $sender => $targets) {
			foreach ($targets as $target => $ts) {
				if (($now - $ts) >= $this->cooldownSeconds) {
					unset($this->cooldowns[$sender][$target]);
				}
			}

			if ($this->cooldowns[$sender] === []) {
				unset($this->cooldowns[$sender]);
			}
		}
	}

	public function getCooldownSeconds() : int {
		return $this->cooldownSeconds;
	}
}
