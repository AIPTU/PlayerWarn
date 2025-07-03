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

namespace aiptu\playerwarn;

use aiptu\playerwarn\event\PlayerPunishmentEvent;
use aiptu\playerwarn\event\WarnAddEvent;
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
		$plugin = $this->plugin;
		$player = $event->getPlayer();
		$playerName = $player->getName();

		$lastWarningCount = $plugin->getLastWarningCount($playerName);
		$currentWarningCount = $plugin->getWarns()->getWarningCount($playerName);

		if ($currentWarningCount > $lastWarningCount) {
			$newWarningCount = $currentWarningCount - $lastWarningCount;
			$player->sendMessage(TextFormat::YELLOW . "You have received {$newWarningCount} new warning(s). Please take note of your behavior.");
		}

		$plugin->setLastWarningCount($playerName, $currentWarningCount);

		$warns = $plugin->getWarns();

		if ($warns->hasWarnings($playerName)) {
			$warningCount = $warns->getWarningCount($playerName);
			$player->sendMessage(TextFormat::RED . "You have {$warningCount} active warning(s). Please take note of your behavior.");
		} else {
			$player->sendMessage(TextFormat::GREEN . 'You have no active warnings. Keep up the good behavior!');
		}

		if ($plugin->hasPendingPunishments($playerName)) {
			$pendingPunishments = $plugin->getPendingPunishments($playerName);
			foreach ($pendingPunishments as $pendingPunishment) {
				$punishmentType = $pendingPunishment['punishmentType'];
				$reason = $pendingPunishment['reason'];
				$issuerName = $pendingPunishment['issuerName'];
				$plugin->scheduleDelayedPunishment($player, $punishmentType, $issuerName, $reason);
			}

			$plugin->removePendingPunishments($playerName);
		}
	}

	public function onWarnAdd(WarnAddEvent $event) : void {
		$plugin = $this->plugin;
		$warnEntry = $event->getWarnEntry();

		if ($plugin->isDiscordEnabled()) {
			$plugin->sendAddRequest($warnEntry);
		}
	}

	public function onWarnRemove(WarnRemoveEvent $event) : void {
		$plugin = $this->plugin;
		$warnEntry = $event->getWarnEntry();

		if ($plugin->isDiscordEnabled()) {
			$plugin->sendRemoveRequest($warnEntry);
		}
	}

	public function onWarnExpired(WarnExpiredEvent $event) : void {
		$plugin = $this->plugin;
		$player = $event->getPlayer();
		$warnEntry = $event->getWarnEntry();

		$player->sendMessage(TextFormat::YELLOW . 'Your warning has expired: ' . $warnEntry->getReason());
		if ($plugin->isDiscordEnabled()) {
			$plugin->sendExpiredRequest($warnEntry);
		}
	}

	public function onPlayerPunishment(PlayerPunishmentEvent $event) : void {
		$plugin = $this->plugin;
		if ($plugin->isDiscordEnabled()) {
			$plugin->sendPunishmentRequest($event);
		}
	}
}