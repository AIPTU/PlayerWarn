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
use DateTimeImmutable;
use InvalidArgumentException;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use pocketmine\utils\TextFormat;
use function array_shift;
use function count;
use function implode;
use function is_numeric;
use function strtolower;

class EditWarnCommand extends Command implements PluginOwned {
	use PluginOwnedTrait {
		__construct as setOwningPlugin;
	}

	public function __construct(
		private PlayerWarn $plugin
	) {
		$this->setOwningPlugin($plugin);
		parent::__construct(
			'editwarn',
			'Edit a player\'s warning reason or expiration',
			'/editwarn <player> <id> <field> <value...>',
		);
		$this->setPermission('playerwarn.command.editwarn');
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
		if (!$this->testPermission($sender)) {
			return false;
		}

		if (count($args) < 4) {
			$sender->sendMessage(TextFormat::RED . 'Usage: /editwarn <player> <id> <reason|expiration> <value...>');
			return false;
		}

		$playerName = array_shift($args);
		$warnIdStr = array_shift($args);
		$field = strtolower((string) array_shift($args));

		if (!is_numeric($warnIdStr)) {
			$sender->sendMessage(TextFormat::RED . 'Warning ID must be a number.');
			return false;
		}

		$warnId = (int) $warnIdStr;

		$server = $this->plugin->getServer();
		if (!$server->hasOfflinePlayerData($playerName)) {
			$sender->sendMessage(TextFormat::RED . 'Invalid player username. The player has not played before.');
			return false;
		}

		if ($field === 'reason') {
			$newReason = implode(' ', $args);
			if ($newReason === '') {
				$sender->sendMessage(TextFormat::RED . 'Reason cannot be empty.');
				return false;
			}

			$this->updateWarnReason($playerName, $warnId, $newReason, $sender);
			return true;
		}

		if ($field === 'expiration') {
			try {
				$durationStr = implode(' ', $args);
				if (strtolower($durationStr) === 'never') {
					$newExpiration = null;
				} else {
					$newExpiration = Utils::parseDurationString($durationStr);
				}
			} catch (InvalidArgumentException $e) {
				$sender->sendMessage(TextFormat::RED . 'Invalid duration format: ' . $e->getMessage());
				return false;
			}

			$this->updateWarnExpiration($playerName, $warnId, $newExpiration, $sender);
			return true;
		}

		$sender->sendMessage(TextFormat::RED . 'Unknown field. Use "reason" or "expiration".');
		return false;
	}

	private function updateWarnReason(
		string $playerName,
		int $warnId,
		string $newReason,
		CommandSender $sender
	) : void {
		$this->plugin->getProvider()->updateWarnReason(
			$warnId,
			strtolower($playerName),
			$newReason,
			function (int $affectedRows) use ($sender, $warnId, $newReason) : void {
				if ($affectedRows > 0) {
					$sender->sendMessage(
						TextFormat::GREEN . "Successfully updated warning ID {$warnId} reason to: {$newReason}"
					);
				} else {
					$sender->sendMessage(TextFormat::RED . "Warning ID {$warnId} not found.");
				}
			},
			function (\Throwable $error) use ($sender) : void {
				$sender->sendMessage(TextFormat::RED . 'Failed to update warning: ' . $error->getMessage());
			}
		);
	}

	private function updateWarnExpiration(
		string $playerName,
		int $warnId,
		?DateTimeImmutable $newExpiration,
		CommandSender $sender
	) : void {
		$expirationStr = $newExpiration !== null
			? $newExpiration->format(Utils::DATE_TIME_FORMAT)
			: 'Never';

		$this->plugin->getProvider()->updateWarnExpiration(
			$warnId,
			strtolower($playerName),
			$newExpiration,
			function (int $affectedRows) use ($sender, $warnId, $expirationStr) : void {
				if ($affectedRows > 0) {
					$sender->sendMessage(
						TextFormat::GREEN . "Successfully updated warning ID {$warnId} expiration to: {$expirationStr}"
					);
				} else {
					$sender->sendMessage(TextFormat::RED . "Warning ID {$warnId} not found.");
				}
			},
			function (\Throwable $error) use ($sender) : void {
				$sender->sendMessage(TextFormat::RED . 'Failed to update warning: ' . $error->getMessage());
			}
		);
	}
}