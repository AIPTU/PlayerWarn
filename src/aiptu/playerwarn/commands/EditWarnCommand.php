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
		$msg = $plugin->getMessageManager();
		parent::__construct(
			'editwarn',
			$msg->get('command.editwarn.description'),
			$msg->get('command.editwarn.usage'),
		);
		$this->setPermission('playerwarn.command.editwarn');
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
		if (!$this->testPermission($sender)) {
			return false;
		}

		$msg = $this->plugin->getMessageManager();

		if (count($args) < 4) {
			$sender->sendMessage($msg->get('editwarn.usage'));
			return false;
		}

		$playerName = array_shift($args);
		$warnIdStr = array_shift($args);
		$field = strtolower((string) array_shift($args));

		if (!is_numeric($warnIdStr)) {
			$sender->sendMessage($msg->get('editwarn.id-must-be-number'));
			return false;
		}

		$warnId = (int) $warnIdStr;

		$server = $this->plugin->getServer();
		if (!$server->hasOfflinePlayerData($playerName)) {
			$sender->sendMessage($msg->get('editwarn.player-not-found'));
			return false;
		}

		if ($field === 'reason') {
			$newReason = implode(' ', $args);
			if ($newReason === '') {
				$sender->sendMessage($msg->get('editwarn.reason-empty'));
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
				$sender->sendMessage($msg->get('editwarn.invalid-duration', ['error' => $e->getMessage()]));
				return false;
			}

			$this->updateWarnExpiration($playerName, $warnId, $newExpiration, $sender);
			return true;
		}

		$sender->sendMessage($msg->get('editwarn.unknown-field'));
		return false;
	}

	private function updateWarnReason(
		string $playerName,
		int $warnId,
		string $newReason,
		CommandSender $sender
	) : void {
		$msg = $this->plugin->getMessageManager();

		$this->plugin->getProvider()->updateWarnReason(
			$warnId,
			strtolower($playerName),
			$newReason,
			function (int $affectedRows) use ($sender, $warnId, $newReason, $msg) : void {
				if ($affectedRows > 0) {
					$sender->sendMessage($msg->get('editwarn.reason-updated', [
						'id' => (string) $warnId,
						'reason' => $newReason,
					]));
				} else {
					$sender->sendMessage($msg->get('editwarn.not-found', ['id' => (string) $warnId]));
				}
			},
			function (\Throwable $error) use ($sender, $msg) : void {
				$sender->sendMessage($msg->get('error.failed-update-warning', ['error' => $error->getMessage()]));
			}
		);
	}

	private function updateWarnExpiration(
		string $playerName,
		int $warnId,
		?DateTimeImmutable $newExpiration,
		CommandSender $sender
	) : void {
		$msg = $this->plugin->getMessageManager();
		$expirationStr = $newExpiration !== null
			? $newExpiration->format(Utils::DATE_TIME_FORMAT)
			: 'Never';

		$this->plugin->getProvider()->updateWarnExpiration(
			$warnId,
			strtolower($playerName),
			$newExpiration,
			function (int $affectedRows) use ($sender, $warnId, $expirationStr, $msg) : void {
				if ($affectedRows > 0) {
					$sender->sendMessage($msg->get('editwarn.expiration-updated', [
						'id' => (string) $warnId,
						'expiration' => $expirationStr,
					]));
				} else {
					$sender->sendMessage($msg->get('editwarn.not-found', ['id' => (string) $warnId]));
				}
			},
			function (\Throwable $error) use ($sender, $msg) : void {
				$sender->sendMessage($msg->get('error.failed-update-warning', ['error' => $error->getMessage()]));
			}
		);
	}
}