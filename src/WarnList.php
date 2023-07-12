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

use pocketmine\utils\Config;
use function count;
use function is_array;
use function strtolower;

class WarnList {
	private array $warns = [];
	private Config $config;

	public function __construct(
		private string $filePath
	) {
		$this->config = new Config($this->filePath, Config::JSON);

		$this->loadWarns();
	}

	public function addWarn(WarnEntry $warnEntry) : void {
		$playerName = strtolower($warnEntry->getPlayerName());
		$this->warns[$playerName][] = $warnEntry;

		$this->saveWarns();
	}

	public function removeWarns(string $playerName) : void {
		$playerName = strtolower($playerName);
		if (isset($this->warns[$playerName])) {
			unset($this->warns[$playerName]);

			$this->saveWarns();
		}
	}

	public function removeSpecificWarn(WarnEntry $warnEntry) : void {
		$playerName = strtolower($warnEntry->getPlayerName());

		if (isset($this->warns[$playerName])) {
			$playerWarns = &$this->warns[$playerName];

			foreach ($playerWarns as $index => $existingWarnEntry) {
				if ($existingWarnEntry === $warnEntry) {
					unset($playerWarns[$index]);
					break;
				}
			}

			if (count($playerWarns) === 0) {
				unset($this->warns[$playerName]);
			}

			$this->saveWarns();
		}
	}

	public function getWarns(string $playerName) : array {
		$playerName = strtolower($playerName);
		return $this->warns[$playerName] ?? [];
	}

	public function getWarningCount(string $playerName) : int {
		$playerName = strtolower($playerName);
		return count($this->getWarns($playerName));
	}

	public function hasWarnings(string $playerName) : bool {
		$playerName = strtolower($playerName);
		return $this->getWarningCount($playerName) > 0;
	}

	private function loadWarns() : void {
		$warnsData = $this->config->get('warns', []);

		if (!is_array($warnsData)) {
			return;
		}

		foreach ($warnsData as $playerName => $playerWarns) {
			$playerName = strtolower($playerName);

			if (!is_array($playerWarns)) {
				continue;
			}

			foreach ($playerWarns as $warnData) {
				if (is_array($warnData)) {
					$warnEntry = WarnEntry::fromArray($warnData);

					if ($warnEntry !== null) {
						$this->warns[$playerName][] = $warnEntry;
					}
				}
			}
		}

		$this->removeExpiredWarns();
	}

	private function saveWarns() : void {
		$warnsData = [];

		foreach ($this->warns as $playerName => $playerWarns) {
			$playerName = strtolower($playerName);

			foreach ($playerWarns as $warnEntry) {
				$warnsData[$playerName][] = $warnEntry->toArray();
			}
		}

		$this->config->set('warns', $warnsData);
		$this->config->save();
	}

	private function removeExpiredWarns() : void {
		$now = new \DateTime();

		foreach ($this->warns as $playerName => &$playerWarns) {
			foreach ($playerWarns as $index => $warnEntry) {
				if ($warnEntry->hasExpired()) {
					unset($playerWarns[$index]);
				}
			}

			if (count($playerWarns) === 0) {
				unset($this->warns[$playerName]);
			}
		}

		$this->saveWarns();
	}
}
