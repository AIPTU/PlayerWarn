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

namespace aiptu\playerwarn;

class WarningTracker {
	/** @var array<string, int> */
	private array $lastWarningCounts = [];

	/**
	 * Get the last known warning count for a player.
	 */
	public function getLastCount(string $playerName) : int {
		return $this->lastWarningCounts[$playerName] ?? 0;
	}

	/**
	 * Set the last known warning count for a player.
	 */
	public function setLastCount(string $playerName, int $count) : void {
		$this->lastWarningCounts[$playerName] = $count;
	}

	/**
	 * Remove a player from the tracker.
	 */
	public function remove(string $playerName) : void {
		unset($this->lastWarningCounts[$playerName]);
	}

	/**
	 * Check if a player is being tracked.
	 */
	public function hasTracking(string $playerName) : bool {
		return isset($this->lastWarningCounts[$playerName]);
	}
}