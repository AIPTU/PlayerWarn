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
use function array_column;
use function array_slice;
use function array_sum;
use function ceil;
use function count;
use function implode;
use function max;
use function usort;

class ListWarnsCommand extends Command implements PluginOwned {
	use PluginOwnedTrait {
		__construct as setOwningPlugin;
	}

	private const int PAGE_SIZE = 10;

	public function __construct(private PlayerWarn $plugin) {
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
		$requestedPage = isset($args[0]) ? max(1, (int) $args[0]) : 1;

		$this->plugin->getProvider()->getAllPlayersWithWarnings(
			function (array $players) use ($sender, $requestedPage, $msg) : void {
				if (count($players) === 0) {
					$sender->sendMessage($msg->get('listwarns.no-players'));
					return;
				}

				usort($players, static fn (array $a, array $b) : int => $b['count'] <=> $a['count']);

				$totalPlayers = count($players);
				$totalWarnings = (int) array_sum(array_column($players, 'count'));
				$totalPages = (int) ceil($totalPlayers / self::PAGE_SIZE);

				if ($requestedPage > $totalPages) {
					$sender->sendMessage($msg->get('listwarns.no-more-pages', [
						'page' => (string) $requestedPage,
						'total_pages' => (string) $totalPages,
					]));
					return;
				}

				$page = $requestedPage;

				$sender->sendMessage($msg->get('listwarns.header', [
					'total_players' => (string) $totalPlayers,
					'total_warnings' => (string) $totalWarnings,
					'page' => (string) $page,
					'total_pages' => (string) $totalPages,
				]));

				$entries = array_slice($players, ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE);

				foreach ($entries as $entry) {
					$lastWarning = (new \DateTimeImmutable('@' . $entry['last_warning_timestamp']))->format(Utils::DATE_TIME_FORMAT);
					$ids = implode(', ', $entry['ids']);

					$sender->sendMessage($msg->get('listwarns.entry', [
						'player' => $entry['player'],
						'count' => (string) $entry['count'],
						'last_warning' => $lastWarning,
						'ids' => $ids,
					]));
				}

				$sender->sendMessage($msg->get('listwarns.footer', [
					'page' => (string) $page,
					'total_pages' => (string) $totalPages,
				]));
			},
			function (\Throwable $e) use ($sender, $msg) : void {
				$sender->sendMessage($msg->get('error.failed-fetch-list', ['error' => $e->getMessage()]));
			}
		);

		return true;
	}
}
