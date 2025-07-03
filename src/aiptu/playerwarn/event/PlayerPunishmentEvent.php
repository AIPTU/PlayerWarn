<?php

/*
 * Copyright (c) 2023-2025 AIPTU
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/AIPTU/PlayerWarn
 */

declare(strict_types=1);

namespace aiptu\playerwarn\event;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\Event;
use pocketmine\player\Player;

class PlayerPunishmentEvent extends Event implements Cancellable {
	use CancellableTrait;

	public function __construct(
		private Player $player,
		private string $punishmentType,
		private string $issuerName,
		private string $reason
	) {}

	public function getPlayer() : Player {
		return $this->player;
	}

	public function getPunishmentType() : string {
		return $this->punishmentType;
	}

	public function getIssuerName() : string {
		return $this->issuerName;
	}

	public function getReason() : string {
		return $this->reason;
	}
}
