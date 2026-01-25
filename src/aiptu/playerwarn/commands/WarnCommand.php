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

namespace aiptu\playerwarn\commands;

use aiptu\playerwarn\PlayerWarn;
use aiptu\playerwarn\utils\Utils;
use aiptu\playerwarn\warns\WarnEntry;
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

		[$playerName, $reason, $expiration] = self::parseArguments($args, $sender);

		$target = $server->getPlayerExact($playerName);
		if ($target instanceof Player && $target->hasPermission('playerwarn.bypass')) {
			$sender->sendMessage(TextFormat::RED . 'You cannot warn this player.');
			return true;
		}

		$this->addWarning($playerName, $reason, $sender, $expiration);

		return true;
	}

	private static function parseArguments(array $args, CommandSender $sender) : array {
		$playerName = array_shift($args);
		$expiration = null;

		if (count($args) > 1) {
			// Join all but the last argument as the reason
			$reason = implode(' ', array_slice($args, 0, -1));
			$durationString = array_pop($args);
			$expiration = self::parseExpiration($durationString, $sender);
		} elseif (count($args) === 1) {
			// Only one argument left, this is the reason
			$reason = $args[0];
		} else {
			throw new InvalidCommandSyntaxException();
		}

		return [$playerName, $reason, $expiration];
	}

	private static function parseExpiration(?string $durationString, CommandSender $sender) : ?\DateTimeImmutable {
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
		$this->plugin->getProvider()->addWarn($playerName, $reason, $sender->getName(), $expiration, function (WarnEntry $entry) use ($sender, $expiration) : void {
			$this->sendMessageToPlayer($entry->getPlayerName(), $entry->getReason(), $sender, $expiration);

			// Use a separate callback for count to ensure we have the latest count including the one just added
			$this->plugin->getProvider()->getWarningCount($entry->getPlayerName(), function (int $count) use ($entry, $sender, $expiration) : void {
				self::sendMessageToSender($entry->getPlayerName(), $entry->getReason(), $sender, $expiration, $count);
				$this->applyPotentialPunishment($entry->getPlayerName(), $entry->getReason(), $sender, $count);
			});
		}, function (\Throwable $error) use ($sender) : void {
			$sender->sendMessage(TextFormat::RED . 'Failed to add warning: ' . $error->getMessage());
		});
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

			self::sendMultipleMessages($player, $message);
		}
	}

	private static function sendMessageToSender(string $playerName, string $reason, CommandSender $sender, ?\DateTimeImmutable $expiration, int $warningCount) : void {
		$expirationText = $expiration === null
			? 'never expire'
			: Utils::formatDuration($expiration->getTimestamp() - (new \DateTimeImmutable())->getTimestamp()) . " ({$expiration->format(WarnEntry::DATE_TIME_FORMAT)})";

		$message = [
			TextFormat::AQUA . 'Player ' . TextFormat::YELLOW . $playerName . TextFormat::AQUA . ' has been warned for: ' . TextFormat::YELLOW . $reason,
			TextFormat::AQUA . 'The warning will ' . $expirationText,
		];

		if ($warningCount > 0) {
			$message[] = TextFormat::AQUA . 'Player ' . TextFormat::YELLOW . $playerName . TextFormat::AQUA . ' now has a total of ' . TextFormat::YELLOW . $warningCount . TextFormat::AQUA . ' warnings.';
		}

		self::sendMultipleMessages($sender, $message);
	}

	private static function sendMultipleMessages(CommandSender $recipient, array $messages) : void {
		foreach ($messages as $message) {
			$recipient->sendMessage($message);
		}
	}

	private function applyPotentialPunishment(string $playerName, string $reason, CommandSender $sender, int $warningCount) : void {
		$warningLimit = $this->plugin->getWarningLimit();
		$punishmentType = $this->plugin->getPunishmentType();

		// Check if player has bypass permission
		$player = $this->plugin->getServer()->getPlayerExact($playerName);
		if ($player instanceof Player && $player->hasPermission('playerwarn.bypass')) {
			return;
		}

		if ($warningCount >= $warningLimit && $punishmentType !== 'none') {
			$sender->sendMessage(TextFormat::RED . 'Player ' . TextFormat::YELLOW . $playerName . TextFormat::RED . ' has reached the warning limit and will be punished.');

			if ($player instanceof Player) {
				$this->plugin->scheduleDelayedPunishment($player, $punishmentType, $sender->getName(), $reason);
			} else {
				$this->plugin->addPendingPunishment($playerName, $punishmentType, $sender->getName(), $reason);
				$sender->sendMessage(TextFormat::YELLOW . 'Player ' . TextFormat::AQUA . $playerName . TextFormat::YELLOW . ' is currently offline. The punishment will be applied when they rejoin.');
			}
		}
	}
}