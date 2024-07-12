<?php

/*
 * Copyright (c) 2023-2024 AIPTU
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/AIPTU/PlayerWarn
 */

declare(strict_types=1);

namespace aiptu\playerwarn\task;

use aiptu\playerwarn\event\WarnExpiredEvent;
use aiptu\playerwarn\PlayerWarn;
use pocketmine\scheduler\Task;

class ExpiredWarningsTask extends Task {
	public function __construct(
		private PlayerWarn $plugin
	) {}

	public function onRun() : void {
		$server = $this->plugin->getServer();
		$warns = $this->plugin->getWarns();

		foreach ($server->getOnlinePlayers() as $player) {
			$playerName = $player->getName();

			if (!$player->isOnline()) {
				continue;
			}

			$playerWarns = $warns->getWarns($playerName);

			foreach ($playerWarns as $index => $warnEntry) {
				if ($warnEntry->hasExpired()) {
					$event = new WarnExpiredEvent($player, $warnEntry);
					$event->call();

					$warns->removeSpecificWarn($warnEntry);
				}
			}
		}
	}
}