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

namespace aiptu\playerwarn\commands;

use aiptu\playerwarn\PlayerWarn;
use aiptu\playerwarn\utils\Utils;
use aiptu\playerwarn\warns\WarnEntry;
use aiptu\playerwarn\warns\WarnList;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use pocketmine\utils\TextFormat;
use function array_pop;
use function array_shift;
use function array_slice;
use function count;
use function implode;

class WarnCommand extends Command implements PluginOwned {
	use PluginOwnedTrait {
		__construct as setOwningPlugin;
	}

	public function __construct(
		private PlayerWarn $plugin
	) {
		$this->setOwningPlugin($plugin);
		parent::__construct(
			'warn',
			'Warn a player',
			'/warn <player> <reason> [duration]',
		);
		$this->setPermission('playerwarn.command.warn');
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
		if (!$this->testPermission($sender)) {
			return false;
		}

		if (count($args) < 2) {
			throw new InvalidCommandSyntaxException();
		}

		$playerName = $args[0];
		$server = $this->plugin->getServer();
		if (!$server->hasOfflinePlayerData($playerName)) {
			$sender->sendMessage(TextFormat::RED . 'Invalid player username. The player has not played before.');
			return false;
		}

		[$playerName, $reason, $expiration] = $this->parseArguments($args, $sender);

		$this->addWarning($playerName, $reason, $sender, $expiration);

		return true;
	}

	private function parseArguments(array $args, CommandSender $sender) : array {
		$playerName = array_shift($args);
		$reason = implode(' ', array_slice($args, 0, -1)); // Join all but the last argument as the reason
		$expiration = count($args) > 0 ? $this->parseExpiration(array_pop($args), $sender) : null;

		return [$playerName, $reason, $expiration];
	}

	private function parseExpiration(?string $durationString, CommandSender $sender) : ?\DateTimeImmutable {
		if ($durationString === null) {
			return null;
		}

		try {
			return Utils::parseDurationString($durationString);
		} catch (\InvalidArgumentException $e) {
			$sender->sendMessage(TextFormat::RED . $e->getMessage());
			return null;
		}
	}

	private function addWarning(string $playerName, string $reason, CommandSender $sender, ?\DateTimeImmutable $expiration) : void {
		$warns = $this->plugin->getWarns();
		$warnEntry = new WarnEntry($playerName, $reason, $sender->getName(), $expiration);
		$warns->addWarn($warnEntry);

		$this->sendMessageToPlayer($playerName, $reason, $sender, $expiration);
		$this->sendMessageToSender($playerName, $reason, $sender, $expiration, $warns);

		$this->applyPotentialPunishment($playerName, $reason, $sender, $warns);
	}

	private function sendMessageToPlayer(string $playerName, string $reason, CommandSender $sender, ?\DateTimeImmutable $expiration) : void {
		$player = $this->plugin->getServer()->getPlayerExact($playerName);
		if ($player instanceof Player) {
			$expirationText = $expiration === null
				? 'never expire'
				: Utils::formatDuration($expiration->getTimestamp() - (new \DateTimeImmutable())->getTimestamp()) . " ({$expiration->format(WarnEntry::DATE_TIME_FORMAT)})";

			$message = [
				TextFormat::YELLOW . 'You have been warned by ' . TextFormat::AQUA . $sender->getName() . TextFormat::YELLOW . ' for: ' . TextFormat::AQUA . $reason,
				TextFormat::YELLOW . 'The warning will ' . $expirationText,
			];

			$this->sendMultipleMessages($player, $message);
		}
	}

	private function sendMessageToSender(string $playerName, string $reason, CommandSender $sender, ?\DateTimeImmutable $expiration, WarnList $warns) : void {
		$newWarningCount = $warns->getWarningCount($playerName);
		$expirationText = $expiration === null
			? 'never expire'
			: Utils::formatDuration($expiration->getTimestamp() - (new \DateTimeImmutable())->getTimestamp()) . " ({$expiration->format(WarnEntry::DATE_TIME_FORMAT)})";

		$message = [
			TextFormat::AQUA . 'Player ' . TextFormat::YELLOW . $playerName . TextFormat::AQUA . ' has been warned for: ' . TextFormat::YELLOW . $reason,
			TextFormat::AQUA . 'The warning will ' . $expirationText,
		];

		if ($newWarningCount > 0) {
			$message[] = TextFormat::AQUA . 'Player ' . TextFormat::YELLOW . $playerName . TextFormat::AQUA . ' now has a total of ' . TextFormat::YELLOW . $newWarningCount . TextFormat::AQUA . ' warnings.';
		}

		$this->sendMultipleMessages($sender, $message);
	}

	private function sendMultipleMessages(CommandSender $recipient, array $messages) : void {
		foreach ($messages as $message) {
			$recipient->sendMessage($message);
		}
	}

	private function applyPotentialPunishment(string $playerName, string $reason, CommandSender $sender, WarnList $warns) : void {
		$warningLimit = $this->plugin->getWarningLimit();
		$punishmentType = $this->plugin->getPunishmentType();

		$newWarningCount = $warns->getWarningCount($playerName);

		if ($newWarningCount >= $warningLimit && $punishmentType !== 'none') {
			$sender->sendMessage(TextFormat::RED . 'Player ' . TextFormat::YELLOW . $playerName . TextFormat::RED . ' has reached the warning limit and will be punished.');

			$player = $this->plugin->getServer()->getPlayerExact($playerName);
			if ($player instanceof Player) {
				$this->plugin->scheduleDelayedPunishment($player, $punishmentType, $sender->getName(), $reason);
			} else {
				$this->plugin->addPendingPunishment($playerName, $punishmentType, $sender->getName(), $reason);
				$sender->sendMessage(TextFormat::YELLOW . 'Player ' . TextFormat::AQUA . $playerName . TextFormat::YELLOW . ' is currently offline. The punishment will be applied when they rejoin.');
			}
		}
	}
}