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
use aiptu\playerwarn\WarnEntry;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\utils\TextFormat;
use function array_shift;
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
			$sender->sendMessage(TextFormat::RED . 'Usage: /warn <player> [reason]');
			return false;
		}

		$playerName = array_shift($args);
		if (!Player::isValidUserName($playerName)) {
			$sender->sendMessage(TextFormat::RED . 'Invalid player username. Please provide a valid username.');
			return false;
		}

		$reason = implode(' ', $args);

		$warningLimit = $this->plugin->getWarningLimit();
		$punishmentType = $this->plugin->getPunishmentType();

		$warns = $this->plugin->getWarns();

		$warnEntry = new WarnEntry($playerName, $reason, $sender->getName());
		$warns->addWarn($warnEntry);

		$player = $this->plugin->getServer()->getPlayerExact($playerName);
		if ($player instanceof Player) {
			$player->sendMessage(TextFormat::YELLOW . "You have been warned by {$sender->getName()} for: {$reason}");
		}

		$newWarningCount = $warns->getWarningCount($playerName);
		$sender->sendMessage(TextFormat::AQUA . "Player {$playerName} has been warned for: {$reason}");

		if ($newWarningCount > 0) {
			$sender->sendMessage(TextFormat::AQUA . "Player {$playerName} now has a total of {$newWarningCount} warnings.");
		}

		if ($newWarningCount >= $warningLimit && $punishmentType !== 'none') {
			$sender->sendMessage(TextFormat::RED . "Player {$playerName} has reached the warning limit and will be punished.");

			if ($player instanceof Player) {
				$this->plugin->applyPunishment($player, $punishmentType, $sender->getName(), $reason);
			} else {
				$this->plugin->addPendingPunishment($playerName, $punishmentType, $sender->getName(), $reason);
				$sender->sendMessage(TextFormat::YELLOW . "Player {$playerName} is currently offline. The punishment will be applied when they rejoin.");
			}
		}

		return true;
	}

	public function getOwningPlugin() : PlayerWarn {
		return $this->plugin;
	}
}
