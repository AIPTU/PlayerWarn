<?php

declare(strict_types=1);

namespace aiptu\playerwarn\task;

use aiptu\playerwarn\PlayerWarn;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;

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
			$expiredCount = 0;

			foreach ($playerWarns as $index => $warnEntry) {
				if ($warnEntry->hasExpired()) {
					$warns->removeSpecificWarn($warnEntry);
					++$expiredCount;
				}
			}

			if ($expiredCount > 0) {
				$player->sendMessage(TextFormat::YELLOW . "You have {$expiredCount} warning(s) that have expired.");
			}
		}
	}
}
