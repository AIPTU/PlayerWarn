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
use function is_numeric;

class DeleteWarnCommand extends Command implements PluginOwned {
	use PluginOwnedTrait {
		__construct as setOwningPlugin;
	}

	public function __construct(
		private PlayerWarn $plugin
	) {
		$this->setOwningPlugin($plugin);
		$msg = $plugin->getMessageManager();
		parent::__construct(
			'delwarn',
			$msg->get('command.delwarn.description'),
			$msg->get('command.delwarn.usage'),
		);
		$this->setPermission('playerwarn.command.delwarn');
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
		if (!$this->testPermission($sender)) {
			return false;
		}

		if (count($args) < 2) {
			throw new InvalidCommandSyntaxException();
		}

		$playerName = $args[0];
		$msg = $this->plugin->getMessageManager();

		if (!is_numeric($args[1])) {
			$sender->sendMessage($msg->get('delwarn.id-must-be-number'));
			return false;
		}

		$id = (int) $args[1];

		$this->plugin->getProvider()->removeWarnById($id, $playerName, function (int $affectedRows) use ($sender, $playerName, $id, $msg) : void {
			if ($affectedRows > 0) {
				$sender->sendMessage($msg->get('delwarn.deleted', [
					'id' => (string) $id,
					'player' => $playerName,
				]));
			} else {
				$sender->sendMessage($msg->get('delwarn.not-found', ['id' => (string) $id]));
			}
		}, function (\Throwable $error) use ($sender, $msg) : void {
			$sender->sendMessage($msg->get('error.failed-delete-warning', ['error' => $error->getMessage()]));
		});

		return true;
	}
}
