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
		$msg = $plugin->getMessageManager();
		parent::__construct(
			'warn',
			$msg->get('command.warn.description'),
			$msg->get('command.warn.usage'),
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
			$sender->sendMessage($this->plugin->getMessageManager()->get('warn.player-not-found'));
			return false;
		}

		try {
			[$playerName, $reason, $expiration] = self::parseArguments($args, $sender);
		} catch (InvalidArgumentException $e) {
			$sender->sendMessage($this->plugin->getMessageManager()->get('warn.invalid-duration', [
				'error' => $e->getMessage(),
			]));
			return false;
		}

		$target = $server->getPlayerExact($playerName);
		if ($target instanceof Player && $target->hasPermission('playerwarn.bypass')) {
			$sender->sendMessage($this->plugin->getMessageManager()->get('warn.cannot-warn'));
			return true;
		}

		if ($target === null) {
			$offlinePlayer = $server->getOfflinePlayer($playerName);
			if ($offlinePlayer !== null) {
				$offlinePlayerName = $offlinePlayer->getName();
				if ($this->hasOfflineBypassPermission($offlinePlayerName)) {
					$sender->sendMessage($this->plugin->getMessageManager()->get('warn.cannot-warn-bypass'));
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
						$this->sendMessageToSender($entry, $sender, $expiration, $count);
						$this->applyPotentialPunishment($entry, $sender, $count);
					},
					function (\Throwable $error) use ($sender) : void {
						$sender->sendMessage($this->plugin->getMessageManager()->get('error.failed-warning-count', [
							'error' => $error->getMessage(),
						]));
					}
				);
			},
			function (\Throwable $error) use ($sender) : void {
				$sender->sendMessage($this->plugin->getMessageManager()->get('error.failed-add-warning', [
					'error' => $error->getMessage(),
				]));
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

		$msg = $this->plugin->getMessageManager();
		$expirationText = $this->formatExpiration($expiration);

		$player->sendMessage($msg->get('warn.warned-player', [
			'sender' => $sender->getName(),
			'reason' => $entry->getReason(),
		]));
		$player->sendMessage($msg->get('warn.warned-player-expiration', [
			'expiration' => $expirationText,
		]));

		if ($this->plugin->isBroadcastToEveryoneEnabled()) {
			$this->plugin->getServer()->broadcastMessage(
				$msg->get('broadcast.player-warned', [
					'player' => $entry->getPlayerName(),
					'reason' => $entry->getReason(),
					'sender' => $sender->getName(),
				])
			);
		}
	}

	private function sendMessageToSender(
		WarnEntry $entry,
		CommandSender $sender,
		?DateTimeImmutable $expiration,
		int $warningCount
	) : void {
		$msg = $this->plugin->getMessageManager();
		$expirationText = $this->formatExpiration($expiration);

		$sender->sendMessage($msg->get('warn.warned-sender', [
			'player' => $entry->getPlayerName(),
			'reason' => $entry->getReason(),
			'id' => (string) $entry->getId(),
		]));
		$sender->sendMessage($msg->get('warn.warned-sender-expiration', [
			'expiration' => $expirationText,
		]));

		if ($warningCount > 0) {
			$sender->sendMessage($msg->get('warn.warned-sender-count', [
				'player' => $entry->getPlayerName(),
				'count' => (string) $warningCount,
			]));
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

		$msg = $this->plugin->getMessageManager();

		$sender->sendMessage($msg->get('warn.limit-reached', [
			'player' => $entry->getPlayerName(),
		]));

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
			$sender->sendMessage($msg->get('warn.offline-pending', [
				'player' => $entry->getPlayerName(),
			]));
		}
	}

	private function formatExpiration(?DateTimeImmutable $expiration) : string {
		$msg = $this->plugin->getMessageManager();

		if ($expiration === null) {
			return $msg->get('expiration.never');
		}

		$secondsRemaining = $expiration->getTimestamp() - (new DateTimeImmutable())->getTimestamp();
		$duration = Utils::formatDuration($secondsRemaining);
		$date = $expiration->format(Utils::DATE_TIME_FORMAT);

		return $msg->get('expiration.until', [
			'duration' => $duration,
			'date' => $date,
		]);
	}
}