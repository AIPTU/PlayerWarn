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
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use function count;

class WarnsCommand extends Command implements PluginOwned {
	use PluginOwnedTrait {
		__construct as setOwningPlugin;
	}

	public function __construct(
		private PlayerWarn $plugin
	) {
		$this->setOwningPlugin($plugin);
		$msg = $plugin->getMessageManager();
		parent::__construct(
			'warns',
			$msg->get('command.warns.description'),
			$msg->get('command.warns.usage'),
		);
		$this->setPermission('playerwarn.command.warns');
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
		if (!$this->testPermission($sender)) {
			return false;
		}

		$msg = $this->plugin->getMessageManager();

		if (!$sender instanceof Player && !isset($args[0])) {
			$sender->sendMessage($msg->get('warns.console-specify-player'));
			return false;
		}

		$playerName = $args[0] ?? $sender->getName();

		$this->plugin->getProvider()->getWarns($playerName, function (array $warns) use ($sender, $playerName, $msg) : void {
			if (count($warns) === 0) {
				$sender->sendMessage($msg->get('warns.no-warnings', ['player' => $playerName]));
				return;
			}

			$warningCount = count($warns);

			$message = $msg->get('warns.header', [
				'player' => $playerName,
				'count' => (string) $warningCount,
			]);
			foreach ($warns as $warnEntry) {
				$timestamp = $warnEntry->getTimestamp()->format(Utils::DATE_TIME_FORMAT);
				$reason = $warnEntry->getReason();
				$source = $warnEntry->getSource();
				$expiration = $warnEntry->getExpiration();
				$expirationString = $expiration !== null ? Utils::formatDuration($expiration->getTimestamp() - (new \DateTimeImmutable())->getTimestamp()) . " ({$expiration->format(Utils::DATE_TIME_FORMAT)})" : 'Never';
				$id = $warnEntry->getId();

				$message .= $msg->get('warns.entry', [
					'id' => (string) $id,
					'timestamp' => $timestamp,
					'reason' => $reason,
					'source' => $source,
					'expiration' => $expirationString,
				]);
			}

			$sender->sendMessage($message);
		}, function (\Throwable $error) use ($sender, $msg) : void {
			$sender->sendMessage($msg->get('error.failed-fetch-warnings', ['error' => $error->getMessage()]));
		});

		return true;
	}
}
