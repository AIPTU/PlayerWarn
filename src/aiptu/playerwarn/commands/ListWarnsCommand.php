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
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use pocketmine\utils\TextFormat;
use function count;
use function implode;
use function str_repeat;
use function usort;

class ListWarnsCommand extends Command implements PluginOwned {
	use PluginOwnedTrait {
		__construct as setOwningPlugin;
	}

	public function __construct(
		private PlayerWarn $plugin
	) {
		$this->setOwningPlugin($plugin);
		parent::__construct(
			'listwarns',
			'View all players with warnings',
			'/listwarns',
		);
		$this->setPermission('playerwarn.command.listwarns');
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
		if (!$this->testPermission($sender)) {
			return false;
		}

		$this->plugin->getProvider()->getAllPlayersWithWarnings(
			function (array $playersData) use ($sender) : void {
				if (count($playersData) === 0) {
					$sender->sendMessage(TextFormat::YELLOW . 'No players have any warnings.');
					return;
				}

				usort($playersData, fn ($a, $b) => $b['count'] <=> $a['count']);

				$totalPlayers = count($playersData);
				$totalWarnings = 0;
				foreach ($playersData as $data) {
					$totalWarnings += $data['count'];
				}

				$sender->sendMessage(TextFormat::GOLD . '========== Players with Warnings ==========');
				$sender->sendMessage(TextFormat::AQUA . "Total Players: {$totalPlayers} | Total Warnings: {$totalWarnings}");
				$sender->sendMessage(TextFormat::GRAY . str_repeat('-', 43));

				foreach ($playersData as $playerData) {
					$playerName = $playerData['player'];
					$warningCount = $playerData['count'];
					$lastWarning = $playerData['last_warning'];

					$timeAgo = Utils::formatTimeAgo($lastWarning);

					$warningIds = implode(', ', $playerData['warning_ids']);

					$sender->sendMessage(
						TextFormat::YELLOW . $playerName .
						TextFormat::GRAY . ' - ' .
						TextFormat::RED . $warningCount .
						TextFormat::GRAY . ' warning' . ($warningCount > 1 ? 's' : '') .
						TextFormat::DARK_GRAY . ' (Last: ' . $timeAgo . ')' .
						TextFormat::YELLOW . 'IDs: ' . $warningIds
					);
				}

				$sender->sendMessage(TextFormat::GOLD . str_repeat('=', 43));
			},
			function (\Throwable $error) use ($sender) : void {
				$sender->sendMessage(TextFormat::RED . 'Failed to fetch warnings list: ' . $error->getMessage());
			}
		);

		return true;
	}
}