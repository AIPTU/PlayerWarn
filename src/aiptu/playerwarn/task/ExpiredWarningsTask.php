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
		$this->plugin->getProvider()->getExpiredWarns(function (array $warns) : void {
			foreach ($warns as $warnEntry) {
				$player = $this->plugin->getServer()->getPlayerExact($warnEntry->getPlayerName());
				if ($player !== null) {
					$player->sendMessage(TextFormat::YELLOW . 'Your warning has expired: ' . $warnEntry->getReason());
					(new WarnExpiredEvent($player, $warnEntry))->call();
				}
			}

			if (count($warns) > 0) {
				$this->plugin->getProvider()->removeExpiredWarns();
			}
		});
	}
}