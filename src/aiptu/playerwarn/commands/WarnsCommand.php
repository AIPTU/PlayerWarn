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
use pocketmine\utils\TextFormat;
use function count;

class WarnsCommand extends Command implements PluginOwned {
	use PluginOwnedTrait {
		__construct as setOwningPlugin;
	}

	public function __construct(
		private PlayerWarn $plugin
	) {
		$this->setOwningPlugin($plugin);
		parent::__construct(
			'warns',
			'View warnings for a player',
			'/warns [player]',
		);
		$this->setPermission('playerwarn.command.warns');
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
		if (!$this->testPermission($sender)) {
			return false;
		}

		if (!$sender instanceof Player && !isset($args[0])) {
			$sender->sendMessage(TextFormat::RED . 'Please specify a player name when using this command from the console.');
			return false;
		}

		$playerName = $args[0] ?? $sender->getName();

		$this->plugin->getProvider()->getWarns($playerName, function (array $warns) use ($sender, $playerName) : void {
			if (count($warns) === 0) {
				$sender->sendMessage(TextFormat::RED . "No warnings found for {$playerName}.");
				return;
			}

			$warningCount = count($warns);

			$message = TextFormat::AQUA . "Warnings for {$playerName} (Count: {$warningCount}):";
			foreach ($warns as $warnEntry) {
				$timestamp = $warnEntry->getTimestamp()->format(Utils::DATE_TIME_FORMAT);
				$reason = $warnEntry->getReason();
				$source = $warnEntry->getSource();
				$expiration = $warnEntry->getExpiration();
				$expirationString = $expiration !== null ? Utils::formatDuration($expiration->getTimestamp() - (new \DateTimeImmutable())->getTimestamp()) . " ({$expiration->format(Utils::DATE_TIME_FORMAT)})" : 'Never';
				$id = $warnEntry->getId() ?? 'N/A';

				$message .= TextFormat::GRAY . "\n- " . TextFormat::YELLOW . "ID: {$id} " . TextFormat::YELLOW . '| Timestamp: ' . TextFormat::WHITE . "{$timestamp} " . TextFormat::YELLOW . '| Reason: ' . TextFormat::WHITE . "{$reason} " . TextFormat::YELLOW . '| Source: ' . TextFormat::WHITE . "{$source} " . TextFormat::YELLOW . '| Expiration: ' . TextFormat::WHITE . "{$expirationString}";
			}

			$sender->sendMessage($message);
		}, function (\Throwable $error) use ($sender) : void {
			$sender->sendMessage(TextFormat::RED . 'Failed to fetch warnings: ' . $error->getMessage());
		});

		return true;
	}
}