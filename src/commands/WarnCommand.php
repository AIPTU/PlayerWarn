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
		$warningCount = $warns->getWarningCount($playerName);

		if ($warningCount > $warningLimit && $punishmentType !== 'none') {
			$sender->sendMessage(TextFormat::RED . "Player {$playerName} has reached the warning limit and will be punished.");

			$player = $this->plugin->getServer()->getPlayerExact($playerName);
			if ($player instanceof Player) {
				$this->applyPunishment($player, $punishmentType, $sender, $reason);
			}

			return true;
		}

		$warnEntry = new WarnEntry($playerName, $reason, $sender->getName());
		$warns->addWarn($warnEntry);
		$sender->sendMessage(TextFormat::AQUA . "Player {$playerName} has been warned for: {$reason}");

		$player = $this->plugin->getServer()->getPlayerExact($playerName);
		if ($player instanceof Player) {
			$player->sendMessage(TextFormat::YELLOW . "You have been warned by {$sender->getName()} for: {$reason}");
		}

		if ($warningCount > 0) {
			$sender->sendMessage(TextFormat::AQUA . "Player {$playerName} now has a total of {$warningCount} warnings.");
		}

		return true;
	}

	private function applyPunishment(Player $player, string $punishmentType, CommandSender $sender, string $reason) : void {
		$server = $player->getServer();

		switch ($punishmentType) {
			case 'kick':
				$player->kick(TextFormat::RED . 'You have reached the warning limit.');
				break;
			case 'ban':
				$server->getNameBans()->addBan($player->getName(), $reason, null, $sender->getName());
				$player->kick(TextFormat::RED . 'You have been banned for reaching the warning limit.');
				break;
			case 'ban-ip':
				$ip = $player->getNetworkSession()->getIp();
				$server->getIPBans()->addBan($ip, $reason, null, $sender->getName());
				$player->kick(TextFormat::RED . 'You have been banned for reaching the warning limit.');
				$server->getNetwork()->blockAddress($ip, -1);
				break;
		}
	}

	public function getOwningPlugin() : PlayerWarn {
		return $this->plugin;
	}
}
