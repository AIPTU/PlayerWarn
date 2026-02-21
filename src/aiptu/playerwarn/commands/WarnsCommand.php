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
use function array_slice;
use function ceil;
use function count;
use function max;

class WarnsCommand extends Command implements PluginOwned {
	use PluginOwnedTrait {
		__construct as setOwningPlugin;
	}

	private const int PAGE_SIZE = 5;

	public function __construct(private PlayerWarn $plugin) {
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
		$requestedPage = isset($args[1]) ? max(1, (int) $args[1]) : 1;

		$this->plugin->getProvider()->getWarns(
			$playerName,
			function (array $warns) use ($sender, $playerName, $requestedPage, $msg) : void {
				if (count($warns) === 0) {
					$sender->sendMessage($msg->get('warns.no-warnings', ['player' => $playerName]));
					return;
				}

				$total = count($warns);
				$totalPages = (int) ceil($total / self::PAGE_SIZE);

				if ($requestedPage > $totalPages) {
					$sender->sendMessage($msg->get('warns.no-more-pages', [
						'page' => (string) $requestedPage,
						'total_pages' => (string) $totalPages,
					]));
					return;
				}

				$page = $requestedPage;

				$sender->sendMessage($msg->get('warns.header', [
					'player' => $playerName,
					'count' => (string) $total,
					'page' => (string) $page,
					'total_pages' => (string) $totalPages,
				]));

				$entries = array_slice($warns, ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE);

				foreach ($entries as $entry) {
					$expiration = $entry->getExpiration();
					$expirationStr = $expiration !== null
						? $msg->get('expiration.until', [
							'duration' => Utils::formatDuration($expiration->getTimestamp() - (new \DateTimeImmutable())->getTimestamp()),
							'date' => $expiration->format(Utils::DATE_TIME_FORMAT),
						])
						: $msg->get('expiration.never');

					$sender->sendMessage($msg->get('warns.entry', [
						'id' => (string) $entry->getId(),
						'timestamp' => $entry->getTimestamp()->format(Utils::DATE_TIME_FORMAT),
						'reason' => $entry->getReason(),
						'source' => $entry->getSource(),
						'expiration' => $expirationStr,
					]));
				}

				$sender->sendMessage($msg->get('warns.footer', [
					'page' => (string) $page,
					'total_pages' => (string) $totalPages,
				]));
			},
			function (\Throwable $e) use ($sender, $msg) : void {
				$sender->sendMessage($msg->get('error.failed-fetch-warnings', ['error' => $e->getMessage()]));
			}
		);

		return true;
	}
}
