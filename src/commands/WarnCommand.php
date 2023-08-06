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

namespace aiptu\playerwarn\commands;

use aiptu\playerwarn\PlayerWarn;
use aiptu\playerwarn\utils\Utils;
use aiptu\playerwarn\warns\WarnEntry;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\utils\TextFormat;
use function array_pop;
use function array_slice;
use function count;
use function implode;

class WarnCommand extends Command implements PluginOwned {
	public function __construct(
		private PlayerWarn $plugin
	) {
		parent::__construct('warn', 'Warn a player');
		$this->setPermission('playerwarn.command.warn');
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
		if (!$this->testPermission($sender)) {
			return false;
		}

		if (count($args) < 2) {
			$sender->sendMessage(TextFormat::RED . 'Usage: /warn <player> <reason> [duration]');
			return false;
		}

		$playerName = $args[0];
		if (!$this->plugin->getServer()->hasOfflinePlayerData($playerName)) {
			$sender->sendMessage(TextFormat::RED . 'Invalid player username. The player has not played before.');
			return false;
		}

		$reason = implode(' ', array_slice($args, 1));
		$durationString = isset($args[2]) ? array_pop($args) : null;

		$expiration = null;
		if ($durationString !== null) {
			try {
				$expiration = Utils::parseDurationString($durationString);
			} catch (\InvalidArgumentException $e) {
				$sender->sendMessage(TextFormat::RED . $e->getMessage());
				return false;
			}
		}

		$warningLimit = $this->plugin->getWarningLimit();
		$punishmentType = $this->plugin->getPunishmentType();

		$warns = $this->plugin->getWarns();

		$warnEntry = new WarnEntry($playerName, $reason, $sender->getName(), $expiration);
		$warns->addWarn($warnEntry);

		$player = $this->plugin->getServer()->getPlayerExact($playerName);
		if ($player instanceof Player) {
			$player->sendMessage(TextFormat::YELLOW . 'You have been warned by ' . TextFormat::AQUA . $sender->getName() . TextFormat::YELLOW . ' for: ' . TextFormat::AQUA . $reason);
			$player->sendMessage(TextFormat::YELLOW . 'The warning will ' . ($expiration === null ? TextFormat::AQUA . 'never expire' : 'expire on ' . TextFormat::AQUA . Utils::formatDuration($expiration->getTimestamp() - (new \DateTimeImmutable())->getTimestamp()) . " ({$expiration->format(WarnEntry::DATE_TIME_FORMAT)})"));
		}

		$newWarningCount = $warns->getWarningCount($playerName);
		$sender->sendMessage(TextFormat::AQUA . 'Player ' . TextFormat::YELLOW . $playerName . TextFormat::AQUA . ' has been warned for: ' . TextFormat::YELLOW . $reason);
		$sender->sendMessage(TextFormat::AQUA . 'The warning will ' . ($expiration === null ? TextFormat::YELLOW . 'never expire' : 'expire on ' . TextFormat::YELLOW . Utils::formatDuration($expiration->getTimestamp() - (new \DateTimeImmutable())->getTimestamp()) . " ({$expiration->format(WarnEntry::DATE_TIME_FORMAT)})"));

		if ($newWarningCount > 0) {
			$sender->sendMessage(TextFormat::AQUA . 'Player ' . TextFormat::YELLOW . $playerName . TextFormat::AQUA . ' now has a total of ' . TextFormat::YELLOW . $newWarningCount . TextFormat::AQUA . ' warnings.');
		}

		if ($newWarningCount >= $warningLimit && $punishmentType !== 'none') {
			$sender->sendMessage(TextFormat::RED . 'Player ' . TextFormat::YELLOW . $playerName . TextFormat::RED . ' has reached the warning limit and will be punished.');

			if ($player instanceof Player) {
				$this->plugin->applyPunishment($player, $punishmentType, $sender->getName(), $reason);
			} else {
				$this->plugin->addPendingPunishment($playerName, $punishmentType, $sender->getName(), $reason);
				$sender->sendMessage(TextFormat::YELLOW . 'Player ' . TextFormat::AQUA . $playerName . TextFormat::YELLOW . ' is currently offline. The punishment will be applied when they rejoin.');
			}
		}

		return true;
	}

	public function getOwningPlugin() : PlayerWarn {
		return $this->plugin;
	}
}
