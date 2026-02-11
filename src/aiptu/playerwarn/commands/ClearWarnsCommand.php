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

class ClearWarnsCommand extends Command implements PluginOwned {
	use PluginOwnedTrait {
		__construct as setOwningPlugin;
	}

	public function __construct(
		private PlayerWarn $plugin
	) {
		$this->setOwningPlugin($plugin);
		$msg = $plugin->getMessageManager();
		parent::__construct(
			'clearwarns',
			$msg->get('command.clearwarns.description'),
			$msg->get('command.clearwarns.usage'),
		);
		$this->setPermission('playerwarn.command.clearwarns');
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

		$this->plugin->getProvider()->removeWarns($playerName, function (int $count) use ($sender, $playerName, $msg) : void {
			if ($count > 0) {
				$sender->sendMessage($msg->get('clearwarns.cleared', [
					'count' => (string) $count,
					'player' => $playerName,
				]));
			} else {
				$sender->sendMessage($msg->get('clearwarns.no-warnings', ['player' => $playerName]));
			}
		}, function (\Throwable $error) use ($sender, $msg) : void {
			$sender->sendMessage($msg->get('error.failed-clear-warnings', ['error' => $error->getMessage()]));
		});

		return true;
	}
}