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

use aiptu\playerwarn\punishment\PunishmentService;
use aiptu\playerwarn\punishment\PunishmentType;
use DateTimeImmutable;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;

class DelayedPunishmentTask extends Task {
	public function __construct(
		private PunishmentService $service,
		private Player $player,
		private PunishmentType $type,
		private string $issuerName,
		private string $reason,
		private ?DateTimeImmutable $until = null
	) {}

	public function onRun() : void {
		if (!$this->player->isOnline() || $this->player->isClosed()) {
			return;
		}

		$this->service->apply($this->player, $this->type, $this->issuerName, $this->reason, $this->until);
	}
}
