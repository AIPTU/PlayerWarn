<?php

declare(strict_types=1);

namespace aiptu\playerwarn;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\TextFormat;

class EventListener implements Listener {
	public function __construct(
		private PlayerWarn $plugin
	) {}

	/**
	 * @priority MONITOR
	 */
	public function onPlayerJoin(PlayerJoinEvent $event) : void {
		$player = $event->getPlayer();
		$playerName = $player->getName();

		$lastWarningCount = $this->plugin->getLastWarningCount($playerName);
		$currentWarningCount = $this->plugin->getWarns()->getWarningCount($playerName);

		if ($currentWarningCount > $lastWarningCount) {
			$newWarningCount = $currentWarningCount - $lastWarningCount;
			$player->sendMessage(TextFormat::YELLOW . "You have received {$newWarningCount} new warning(s). Please take note of your behavior.");
		}

		$this->plugin->setLastWarningCount($playerName, $currentWarningCount);

		$warns = $this->plugin->getWarns();

		if ($warns->hasWarnings($playerName)) {
			$warningCount = $warns->getWarningCount($playerName);
			$player->sendMessage(TextFormat::RED . "You have {$warningCount} active warning(s). Please take note of your behavior.");
		} else {
			$player->sendMessage(TextFormat::GREEN . 'You have no active warnings. Keep up the good behavior!');
		}

		if ($this->plugin->hasPendingPunishments($playerName)) {
			$pendingPunishments = $this->plugin->getPendingPunishments($playerName);
			foreach ($pendingPunishments as $pendingPunishment) {
				$punishmentType = $pendingPunishment['punishmentType'];
				$reason = $pendingPunishment['reason'];
				$issuerName = $pendingPunishment['issuerName'];
				$this->plugin->applyPunishment($player, $punishmentType, $issuerName, $reason);
			}

			$this->plugin->removePendingPunishments($playerName);
		}
	}
}
