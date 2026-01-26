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

namespace aiptu\playerwarn\event;

use aiptu\playerwarn\warns\WarnEntry;
use pocketmine\event\Event;

class WarnEditEvent extends Event {
	public function __construct(
		private WarnEntry $warnEntry,
		private string $editType,
		private string $oldValue,
		private string $newValue
	) {}

	public function getWarnEntry() : WarnEntry {
		return $this->warnEntry;
	}

	public function getEditType() : string {
		return $this->editType;
	}

	public function getOldValue() : string {
		return $this->oldValue;
	}

	public function getNewValue() : string {
		return $this->newValue;
	}
}