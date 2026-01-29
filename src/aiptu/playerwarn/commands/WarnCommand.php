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
use DateTimeImmutable;
use InvalidArgumentException;
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

		try {
			[$playerName, $reason, $expiration] = self::parseArguments($args, $sender);
		} catch (InvalidArgumentException $e) {
			$sender->sendMessage(TextFormat::RED . $e->getMessage());
			return false;
		}

		$target = $server->getPlayerExact($playerName);
		if ($target instanceof Player && $target->hasPermission('playerwarn.bypass')) {
			$sender->sendMessage(TextFormat::RED . 'You cannot warn this player.');
			return true;
		}

		if ($target === null) {
			$offlinePlayer = $server->getOfflinePlayer($playerName);
			if ($offlinePlayer !== null) {
				$offlinePlayerName = $offlinePlayer->getName();
				if ($this->hasOfflineBypassPermission($offlinePlayerName)) {
					$sender->sendMessage(TextFormat::RED . 'You cannot warn this player (has bypass permission).');
					return true;
				}
			}
		}

		$this->addWarning($playerName, $reason, $sender, $expiration);
		return true;
	}

	/**
	 * Check if an offline player has bypass permission.
	 * This checks if the player is an operator or has explicit bypass permission.
	 */
	private function hasOfflineBypassPermission(string $playerName) : bool {
		return $this->plugin->getServer()->isOp($playerName);
		// TODO: Add support for permission plugin integration
		// For now, we rely on op status for offline players
	}

	/**
	 * @return array{string, string, ?DateTimeImmutable}
	 *
	 * @throws InvalidArgumentException
	 */
	private static function parseArguments(array $args, CommandSender $sender) : array {
		$playerName = array_shift($args);
		$expiration = null;

		if (count($args) > 1) {
			// Join all but the last argument as the reason
			$reason = implode(' ', array_slice($args, 0, -1));
			$durationString = array_pop($args);

			try {
				$expiration = Utils::parseDurationString($durationString);
			} catch (InvalidArgumentException $e) {
				throw new InvalidArgumentException('Invalid duration format: ' . $e->getMessage());
			}
		} elseif (count($args) === 1) {
			// Only one argument left, this is the reason
			$reason = $args[0];
		} else {
			throw new InvalidCommandSyntaxException();
		}

		return [$playerName, $reason, $expiration];
	}

	private function addWarning(
		string $playerName,
		string $reason,
		CommandSender $sender,
		?DateTimeImmutable $expiration
	) : void {
		$this->plugin->getProvider()->addWarn(
			$playerName,
			$reason,
			$sender->getName(),
			$expiration,
			function (WarnEntry $entry) use ($sender, $expiration) : void {
				$this->sendMessageToPlayer($entry, $sender, $expiration);

				$this->plugin->getProvider()->getWarningCount(
					$entry->getPlayerName(),
					function (int $count) use ($entry, $sender, $expiration) : void {
						self::sendMessageToSender($entry, $sender, $expiration, $count);
						$this->applyPotentialPunishment($entry, $sender, $count);
					},
					function (\Throwable $error) use ($sender) : void {
						$sender->sendMessage(TextFormat::RED . 'Failed to get warning count: ' . $error->getMessage());
					}
				);
			},
			function (\Throwable $error) use ($sender) : void {
				$sender->sendMessage(TextFormat::RED . 'Failed to add warning: ' . $error->getMessage());
			}
		);
	}

	private function sendMessageToPlayer(
		WarnEntry $entry,
		CommandSender $sender,
		?DateTimeImmutable $expiration
	) : void {
		$player = $this->plugin->getServer()->getPlayerExact($entry->getPlayerName());
		if (!$player instanceof Player) {
			return;
		}

		$expirationText = self::formatExpiration($expiration);

		$message = TextFormat::YELLOW . 'You have been warned by ' .
			TextFormat::AQUA . $sender->getName() .
			TextFormat::YELLOW . ' for: ' .
			TextFormat::AQUA . $entry->getReason();

		$player->sendMessage($message);
		$player->sendMessage(
			TextFormat::YELLOW . 'The warning will ' . $expirationText
		);

		if ($this->plugin->isBroadcastToEveryoneEnabled()) {
			$this->plugin->getServer()->broadcastMessage(
				TextFormat::LIGHT_PURPLE . $entry->getPlayerName() . TextFormat::YELLOW . ' has been warned for: ' .
				TextFormat::LIGHT_PURPLE . $entry->getReason() . TextFormat::YELLOW . ' by ' . TextFormat::LIGHT_PURPLE . $sender->getName()
			);
		}
	}

	private static function sendMessageToSender(
		WarnEntry $entry,
		CommandSender $sender,
		?DateTimeImmutable $expiration,
		int $warningCount
	) : void {
		$expirationText = self::formatExpiration($expiration);

		$sender->sendMessage(
			TextFormat::AQUA . 'Player ' .
			TextFormat::YELLOW . $entry->getPlayerName() .
			TextFormat::AQUA . ' has been warned for: ' .
			TextFormat::YELLOW . $entry->getReason()
		);
		$sender->sendMessage(
			TextFormat::AQUA . 'The warning will ' . $expirationText
		);

		if ($warningCount > 0) {
			$sender->sendMessage(
				TextFormat::AQUA . 'Player ' .
				TextFormat::YELLOW . $entry->getPlayerName() .
				TextFormat::AQUA . ' now has a total of ' .
				TextFormat::YELLOW . $warningCount .
				TextFormat::AQUA . ' warning(s).'
			);
		}
	}

	private function applyPotentialPunishment(
		WarnEntry $entry,
		CommandSender $sender,
		int $warningCount
	) : void {
		$warningLimit = $this->plugin->getWarningLimit();
		$punishmentType = $this->plugin->getPunishmentType();

		if ($warningCount < $warningLimit || $punishmentType->isNone()) {
			return;
		}

		$player = $this->plugin->getServer()->getPlayerExact($entry->getPlayerName());
		if ($player instanceof Player && $player->hasPermission('playerwarn.bypass')) {
			return;
		}

		$sender->sendMessage(
			TextFormat::RED . 'Player ' .
			TextFormat::YELLOW . $entry->getPlayerName() .
			TextFormat::RED . ' has reached the warning limit and will be punished.'
		);

		if ($player instanceof Player) {
			$tempbanDuration = $this->plugin->getTempbanDuration();

			$this->plugin->getPunishmentService()->scheduleDelayedPunishment(
				$player,
				$punishmentType,
				$sender->getName(),
				$entry->getReason(),
				$tempbanDuration
			);
		} else {
			$this->plugin->getPendingPunishmentManager()->add(
				$entry->getPlayerName(),
				$punishmentType,
				$sender->getName(),
				$entry->getReason()
			);
			$sender->sendMessage(
				TextFormat::YELLOW . 'Player ' .
				TextFormat::AQUA . $entry->getPlayerName() .
				TextFormat::YELLOW . ' is currently offline. The punishment will be applied when they rejoin.'
			);
		}
	}

	private static function formatExpiration(?DateTimeImmutable $expiration) : string {
		if ($expiration === null) {
			return 'never expire';
		}

		$secondsRemaining = $expiration->getTimestamp() - (new DateTimeImmutable())->getTimestamp();
		$duration = Utils::formatDuration($secondsRemaining);
		$date = $expiration->format(Utils::DATE_TIME_FORMAT);

		return "until {$duration} ({$date})";
	}
}