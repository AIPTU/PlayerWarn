<?php

/*
 * Copyright (c) 2023 AIPTU
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/AIPTU/PlayerWarn
 */

declare(strict_types=1);

namespace aiptu\playerwarn\task;

use aiptu\playerwarn\PlayerWarn;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;

class DelayedPunishmentTask extends Task {
	public function __construct(
		private PlayerWarn $plugin,
		private Player $player,
		private string $punishmentType,
		private string $issuerName,
		private string $reason
	) {}

	public function onRun() : void {
		$this->plugin->applyPunishment($this->player, $this->punishmentType, $this->issuerName, $this->reason);
	}
}
