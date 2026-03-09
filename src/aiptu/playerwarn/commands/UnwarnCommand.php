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
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use function count;

class UnwarnCommand extends Command implements PluginOwned {
	use PluginOwnedTrait {
		__construct as setOwningPlugin;
	}

	public function __construct(
		private PlayerWarn $plugin
	) {
		$this->setOwningPlugin($plugin);
		$msg = $plugin->getMessageManager();
		parent::__construct(
			'unwarn',
			$msg->get('command.unwarn.description'),
			$msg->get('command.unwarn.usage'),
		);
		$this->setPermission('playerwarn.command.unwarn');
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
		if (!$this->testPermission($sender)) {
			return false;
		}

		if (count($args) < 1) {
			throw new InvalidCommandSyntaxException();
		}

		$playerName = $args[0];
		$msg = $this->plugin->getMessageManager();

		$this->plugin->getProvider()->getWarns(
			$playerName,
			function (array $warns) use ($sender, $playerName, $msg) : void {
				if (count($warns) === 0) {
					$sender->sendMessage($msg->get('unwarn.no-warnings', ['player' => $playerName]));
					return;
				}

				$latest = $warns[0];
				$id = $latest->getId();

				$this->plugin->getProvider()->removeWarnById(
					$id,
					$playerName,
					function (int $affectedRows) use ($sender, $playerName, $id, $latest, $msg) : void {
						if ($affectedRows > 0) {
							$sender->sendMessage($msg->get('unwarn.success', [
								'player' => $playerName,
								'id' => (string) $id,
								'reason' => $latest->getReason(),
							]));
						} else {
							$sender->sendMessage($msg->get('unwarn.failed', ['player' => $playerName]));
						}
					},
					function (\Throwable $error) use ($sender, $msg) : void {
						$sender->sendMessage($msg->get('error.failed-delete-warning', ['error' => $error->getMessage()]));
					}
				);
			},
			function (\Throwable $error) use ($sender, $msg) : void {
				$sender->sendMessage($msg->get('error.failed-fetch-warnings', ['error' => $error->getMessage()]));
			}
		);

		return true;
	}
}
