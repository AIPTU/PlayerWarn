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
use pocketmine\utils\TextFormat;
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
		parent::__construct(
			'delwarn',
			'Delete a specific warning',
			'/delwarn <player> <id>',
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
		// ID check
		if (!is_numeric($args[1])) {
			$sender->sendMessage(TextFormat::RED . 'Warning ID must be a number.');
			return false;
		}

		$id = (int) $args[1];

		// Optional: Verify player has warning with that ID?
		// WarnProvider->removeWarnId just deletes by ID.
		// But for safety, we might want to ensure the ID belongs to the player?
		// The SQL `DELETE FROM player_warnings WHERE id = :id` doesn't check player name unless we add `AND player_name = :mn`.
		// WarnProvider's removeWarnId currently only takes ID.
		// Let's rely on ID being unique. The player argument is mostly for confirmation/UX or if we enforce ownership.

		$this->plugin->getProvider()->removeWarnId($id, $playerName, function (int $affectedRows) use ($sender, $playerName, $id) : void {
			if ($affectedRows > 0) {
				$sender->sendMessage(TextFormat::GREEN . "Successfully deleted warning ID {$id} for player {$playerName}.");
			} else {
				$sender->sendMessage(TextFormat::RED . "Warning ID {$id} not found.");
			}
		}, function (\Throwable $error) use ($sender) : void {
			$sender->sendMessage(TextFormat::RED . 'Failed to delete warning: ' . $error->getMessage());
		});

		return true;
	}
}
