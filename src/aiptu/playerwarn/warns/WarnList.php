<?php

/*
 * Copyright (c) 2023-2025 AIPTU
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/AIPTU/PlayerWarn
 */

declare(strict_types=1);

namespace aiptu\playerwarn\warns;

use aiptu\playerwarn\event\WarnAddEvent;
use aiptu\playerwarn\event\WarnRemoveEvent;
use pocketmine\utils\Config;
use RuntimeException;
use function array_filter;
use function array_map;
use function count;
use function is_array;
use function strtolower;

class WarnList {
	/** @var array<string, array<WarnEntry>> */
	private array $warns = [];
	private Config $config;

	public function __construct(
		private string $filePath
	) {
		$this->config = new Config($this->filePath, Config::JSON);
		$this->loadWarns();
	}

	/**
	 * Adds a new warning entry for a player.
	 */
	public function addWarn(WarnEntry $warnEntry) : void {
		$playerName = strtolower($warnEntry->getPlayerName());
		$this->warns[$playerName][] = $warnEntry;
		(new WarnAddEvent($warnEntry))->call();
		$this->saveWarns();
	}

	/**
	 * Removes all warnings for a specific player.
	 */
	public function removeWarns(string $playerName) : void {
		$playerName = strtolower($playerName);
		if (!isset($this->warns[$playerName])) {
			return;
		}

		foreach ($this->warns[$playerName] as $warnEntry) {
			(new WarnRemoveEvent($warnEntry))->call();
		}

		unset($this->warns[$playerName]);
		$this->saveWarns();
	}

	/**
	 * Removes a specific warning entry for a player.
	 */
	public function removeSpecificWarn(WarnEntry $warnEntry) : void {
		$playerName = strtolower($warnEntry->getPlayerName());
		if (!isset($this->warns[$playerName])) {
			return;
		}

		$this->warns[$playerName] = array_filter(
			$this->warns[$playerName],
			fn ($existingWarnEntry) => $existingWarnEntry !== $warnEntry
		);

		(new WarnRemoveEvent($warnEntry))->call();

		if (count($this->warns[$playerName]) === 0) {
			unset($this->warns[$playerName]);
		}

		$this->saveWarns();
	}

	/**
	 * Retrieves all warning entries for a specific player.
	 *
	 * @return array<WarnEntry>
	 */
	public function getWarns(string $playerName) : array {
		return $this->warns[strtolower($playerName)] ?? [];
	}

	/**
	 * Retrieves the count of warnings for a specific player.
	 */
	public function getWarningCount(string $playerName) : int {
		return count($this->getWarns($playerName));
	}

	/**
	 * Checks if a player has any warnings.
	 */
	public function hasWarnings(string $playerName) : bool {
		return $this->getWarningCount($playerName) > 0;
	}

	/**
	 * Loads warning data from the JSON file and initializes the WarnList.
	 * This method is called automatically during object creation.
	 */
	private function loadWarns() : void {
		$warnsData = $this->config->get('warns', []);

		if (!is_array($warnsData)) {
			throw new RuntimeException('Invalid data format for warns. Expected an array.');
		}

		foreach ($warnsData as $playerName => $playerWarns) {
			if (!is_array($playerWarns)) {
				throw new RuntimeException("Invalid data format for warns of player {$playerName}. Expected an array.");
			}

			$this->warns[$playerName] = array_map(
				fn ($warnData) => $this->createWarnEntry($warnData, $playerName),
				$playerWarns
			);
		}

		$this->removeExpiredWarns();
	}

	/**
	 * Saves the warning data to the JSON file.
	 */
	private function saveWarns() : void {
		$warnsData = [];
		foreach ($this->warns as $playerName => $playerWarns) {
			$warnsData[$playerName] = array_map(
				fn ($warnEntry) => $warnEntry->toArray(),
				$playerWarns
			);
		}

		$this->config->set('warns', $warnsData);
		$this->config->save();
	}

	/**
	 * Removes expired warnings from the WarnList and saves the updated data to the JSON file.
	 */
	private function removeExpiredWarns() : void {
		$now = new \DateTimeImmutable();

		foreach ($this->warns as $playerName => &$playerWarns) {
			$playerWarns = array_filter(
				$playerWarns,
				fn ($warnEntry) => !$warnEntry->hasExpired($now)
			);

			if (count($playerWarns) === 0) {
				unset($this->warns[$playerName]);
			}
		}

		$this->saveWarns();
	}

	/**
	 * Creates a WarnEntry from an array, throwing an exception if the data is invalid.
	 */
	private function createWarnEntry(array $warnData, string $playerName) : WarnEntry {
		try {
			return WarnEntry::fromArray($warnData);
		} catch (\InvalidArgumentException $e) {
			throw new RuntimeException("Error while parsing warn entry of player {$playerName}: " . $e->getMessage());
		}
	}
}
