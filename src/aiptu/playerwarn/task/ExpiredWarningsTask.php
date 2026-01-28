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

namespace aiptu\playerwarn\task;

use aiptu\playerwarn\event\WarnExpiredEvent;
use aiptu\playerwarn\PlayerWarn;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;
use function count;

class ExpiredWarningsTask extends Task {
	public function __construct(
		private PlayerWarn $plugin
	) {}

	public function onRun() : void {
		$this->plugin->getProvider()->getExpiredWarns(
			function (array $warns) : void {
				if (count($warns) === 0) {
					return;
				}

				foreach ($warns as $warnEntry) {
					$player = $this->plugin->getServer()->getPlayerExact($warnEntry->getPlayerName());
					if ($player !== null && $player->isOnline()) {
						$player->sendMessage(
							TextFormat::YELLOW . 'Your warning has expired: ' . $warnEntry->getReason()
						);
					}

					(new WarnExpiredEvent($warnEntry))->call();
				}

				$this->plugin->getProvider()->removeExpiredWarns(
					function (int $affectedRows) : void {
						if ($affectedRows > 0) {
							$this->plugin->getLogger()->info("Removed {$affectedRows} expired warning(s)");
						}
					},
					function (\Throwable $error) : void {
						$this->plugin->getLogger()->error('Failed to remove expired warnings: ' . $error->getMessage());
					}
				);
			},
			function (\Throwable $error) : void {
				$this->plugin->getLogger()->error('Failed to fetch expired warnings: ' . $error->getMessage());
			}
		);
	}
}
