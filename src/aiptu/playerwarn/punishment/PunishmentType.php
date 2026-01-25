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

namespace aiptu\playerwarn\punishment;

use function strtolower;

enum PunishmentType : string {
	case NONE = 'none';
	case KICK = 'kick';
	case BAN = 'ban';
	case BAN_IP = 'ban-ip';
	case TEMPBAN = 'tempban';

	public static function fromString(string $value) : ?self {
		return match (strtolower($value)) {
			'none' => self::NONE,
			'kick' => self::KICK,
			'ban' => self::BAN,
			'ban-ip' => self::BAN_IP,
			'tempban' => self::TEMPBAN,
			default => null,
		};
	}

	public function isNone() : bool {
		return $this === self::NONE;
	}
}
