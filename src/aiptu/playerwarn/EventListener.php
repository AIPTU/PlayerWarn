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

use aiptu\playerwarn\event\PlayerPunishmentEvent;
use aiptu\playerwarn\event\WarnAddEvent;
use aiptu\playerwarn\event\WarnEditEvent;
use aiptu\playerwarn\event\WarnExpiredEvent;
use aiptu\playerwarn\event\WarnRemoveEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\TextFormat;

class EventListener implements Listener {
	public function __construct(
		private PlayerWarn $plugin
	) {}

	/**
	 * @priority HIGH
	 */
	public function onPlayerJoin(PlayerJoinEvent $event) : void {
		$player = $event->getPlayer();
		$playerName = $player->getName();

		$pendingManager = $this->plugin->getPendingPunishmentManager();
		if ($pendingManager->hasPending($playerName)) {
			$pendingPunishments = $pendingManager->getPending($playerName);

			foreach ($pendingPunishments as $punishment) {
				$this->plugin->getPunishmentService()->scheduleDelayedPunishment(
					$player,
					$punishment['type'],
					$punishment['issuer'],
					$punishment['reason']
				);
			}

			$pendingManager->clear($playerName);
		}

		$this->plugin->getProvider()->getWarningCount(
			$playerName,
			function (int $currentWarningCount) use ($player, $playerName) : void {
				$tracker = $this->plugin->getWarningTracker();
				$lastWarningCount = $tracker->getLastCount($playerName);

				if ($currentWarningCount > $lastWarningCount) {
					$newWarningCount = $currentWarningCount - $lastWarningCount;
					$player->sendMessage(
						TextFormat::YELLOW .
						"You have received {$newWarningCount} new warning(s). Please take note of your behavior."
					);
				}

				$tracker->setLastCount($playerName, $currentWarningCount);

				if ($currentWarningCount > 0) {
					$player->sendMessage(
						TextFormat::RED .
						"You have {$currentWarningCount} active warning(s). Please take note of your behavior."
					);
				} else {
					$player->sendMessage(
						TextFormat::GREEN .
						'You have no active warnings. Keep up the good behavior!'
					);
				}
			},
			function (\Throwable $error) use ($playerName) : void {
				$this->plugin->getLogger()->error(
					"Failed to get warning count for {$playerName}: " . $error->getMessage()
				);
			}
		);
	}

	public function onWarnAdd(WarnAddEvent $event) : void {
		$discordService = $this->plugin->getDiscordService();
		if ($discordService !== null) {
			$discordService->sendWarningAdded($event->getWarnEntry());
		}
	}

	public function onWarnRemove(WarnRemoveEvent $event) : void {
		$discordService = $this->plugin->getDiscordService();
		if ($discordService !== null) {
			$discordService->sendWarningRemoved($event->getWarnEntry());
		}
	}

	public function onWarnEdit(WarnEditEvent $event) : void {
		$discordService = $this->plugin->getDiscordService();
		if ($discordService !== null) {
			$discordService->sendWarningEdited(
				$event->getWarnEntry(),
				$event->getEditType(),
				$event->getOldValue(),
				$event->getNewValue()
			);
		}
	}

	public function onWarnExpired(WarnExpiredEvent $event) : void {
		$discordService = $this->plugin->getDiscordService();
		if ($discordService !== null) {
			$discordService->sendWarningExpired($event->getWarnEntry());
		}
	}

	public function onPlayerPunishment(PlayerPunishmentEvent $event) : void {
		$discordService = $this->plugin->getDiscordService();
		if ($discordService !== null) {
			$discordService->sendPunishment($event);
		}
	}
}
