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
use pocketmine\event\player\PlayerQuitEvent;

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
				$msg = $this->plugin->getMessageManager();
				$tracker = $this->plugin->getWarningTracker();
				$lastWarningCount = $tracker->getLastCount($playerName);

				if ($currentWarningCount > $lastWarningCount) {
					$newWarningCount = $currentWarningCount - $lastWarningCount;
					$player->sendMessage($msg->get('join.new-warnings', [
						'count' => (string) $newWarningCount,
					]));
				}

				$tracker->setLastCount($playerName, $currentWarningCount);

				if ($currentWarningCount > 0) {
					$player->sendMessage($msg->get('join.active-warnings', [
						'count' => (string) $currentWarningCount,
					]));
				} else {
					$player->sendMessage($msg->get('join.no-warnings'));
				}
			},
			function (\Throwable $error) use ($playerName) : void {
				$this->plugin->getLogger()->error(
					"Failed to get warning count for {$playerName}: " . $error->getMessage()
				);
			}
		);
	}

	public function onPlayerQuit(PlayerQuitEvent $event) : void {
		$player = $event->getPlayer();
		$this->plugin->getWarningTracker()->remove($player->getName());
	}

	public function onWarnAdd(WarnAddEvent $event) : void {
		$discordService = $this->plugin->getDiscordService();
		if ($discordService !== null) {
			$warnEntry = $event->getWarnEntry();
			$playerName = $warnEntry->getPlayerName();
			$this->plugin->getProvider()->getWarningCount(
				$playerName,
				function (int $count) use ($discordService, $warnEntry) : void {
					$discordService->sendWarningAdded($warnEntry, $count);
				},
				function (\Throwable $error) : void {
					$this->plugin->getLogger()->warning('Failed to fetch warning count for Discord, skipping notification: ' . $error->getMessage());
				}
			);
		}
	}

	public function onWarnRemove(WarnRemoveEvent $event) : void {
		$discordService = $this->plugin->getDiscordService();
		if ($discordService !== null) {
			$warnEntry = $event->getWarnEntry();
			$playerName = $warnEntry->getPlayerName();
			$this->plugin->getProvider()->getWarningCount(
				$playerName,
				function (int $count) use ($discordService, $warnEntry) : void {
					$discordService->sendWarningRemoved($warnEntry, $count);
				},
				function (\Throwable $error) : void {
					$this->plugin->getLogger()->warning('Failed to fetch warning count for Discord, skipping notification: ' . $error->getMessage());
				}
			);
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
			$warnEntry = $event->getWarnEntry();
			$playerName = $warnEntry->getPlayerName();
			$this->plugin->getProvider()->getWarningCount(
				$playerName,
				function (int $count) use ($discordService, $warnEntry) : void {
					$discordService->sendWarningExpired($warnEntry, $count);
				},
				function (\Throwable $error) : void {
					$this->plugin->getLogger()->warning('Failed to fetch warning count for Discord, skipping notification: ' . $error->getMessage());
				}
			);
		}
	}

	public function onPlayerPunishment(PlayerPunishmentEvent $event) : void {
		$discordService = $this->plugin->getDiscordService();
		if ($discordService !== null) {
			$playerName = $event->getPlayer()->getName();
			$this->plugin->getProvider()->getWarningCount(
				$playerName,
				function (int $count) use ($discordService, $event) : void {
					$discordService->sendPunishment($event, $count);
				},
				function (\Throwable $error) : void {
					$this->plugin->getLogger()->warning('Failed to fetch warning count for Discord, skipping notification: ' . $error->getMessage());
				}
			);
		}
	}
}
