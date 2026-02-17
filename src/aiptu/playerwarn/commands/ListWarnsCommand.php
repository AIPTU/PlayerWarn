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
		$msg = $plugin->getMessageManager();
		parent::__construct(
			'listwarns',
			$msg->get('command.listwarns.description'),
			$msg->get('command.listwarns.usage'),
		);
		$this->setPermission('playerwarn.command.listwarns');
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
		if (!$this->testPermission($sender)) {
			return false;
		}

		$msg = $this->plugin->getMessageManager();

		$this->plugin->getProvider()->getAllPlayersWithWarnings(
			function (array $playersData) use ($sender, $msg) : void {
				if (count($playersData) === 0) {
					$sender->sendMessage($msg->get('listwarns.no-players'));
					return;
				}

				usort($playersData, fn ($a, $b) => $b['count'] <=> $a['count']);

				$totalPlayers = count($playersData);
				$totalWarnings = 0;
				foreach ($playersData as $data) {
					$totalWarnings += $data['count'];
				}

				$sender->sendMessage($msg->get('listwarns.header'));
				$sender->sendMessage($msg->get('listwarns.summary', [
					'total_players' => (string) $totalPlayers,
					'total_warnings' => (string) $totalWarnings,
				]));
				$sender->sendMessage(str_repeat('-', 43));

				foreach ($playersData as $playerData) {
					$playerName = $playerData['player'];
					$warningCount = $playerData['count'];
					$lastWarning = $playerData['last_warning'];

					$timeAgo = Utils::formatTimeAgo($lastWarning);

					$warningIds = implode(', ', $playerData['warning_ids']);

					$sender->sendMessage($msg->get('listwarns.entry', [
						'player' => $playerName,
						'count' => (string) $warningCount,
						'plural' => $warningCount > 1 ? 's' : '',
						'last_warning' => $timeAgo,
						'ids' => $warningIds,
					]));
				}

				$sender->sendMessage($msg->get('listwarns.footer'));
			},
			function (\Throwable $error) use ($sender, $msg) : void {
				$sender->sendMessage($msg->get('error.failed-fetch-list', ['error' => $error->getMessage()]));
			}
		);

		return true;
	}
}