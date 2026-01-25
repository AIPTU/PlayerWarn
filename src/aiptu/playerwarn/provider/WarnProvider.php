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

namespace aiptu\playerwarn\provider;

use aiptu\playerwarn\event\WarnAddEvent;
use aiptu\playerwarn\event\WarnRemoveEvent;
use aiptu\playerwarn\warns\WarnEntry;
use Closure;
use pocketmine\utils\SingletonTrait;
use poggit\libasynql\DataConnector;
use function strtolower;

class WarnProvider {
	use SingletonTrait;

	public function __construct(
		private DataConnector $database
	) {
		self::setInstance($this);
		$this->database->executeGeneric('table.init');
	}

	public function addWarn(string $playerName, string $reason, string $source, ?\DateTimeImmutable $expiration, ?Closure $onSuccess = null, ?Closure $onError = null) : void {
		$timestamp = new \DateTimeImmutable();
		$this->database->executeInsert('warn.add', [
			'player_name' => strtolower($playerName),
			'reason' => $reason,
			'source' => $source,
			'expiration' => $expiration?->format(WarnEntry::DATE_TIME_FORMAT),
			'timestamp' => $timestamp->format(WarnEntry::DATE_TIME_FORMAT),
		], function (int $insertId, int $affectedRows) use ($playerName, $reason, $source, $expiration, $timestamp, $onSuccess) : void {
			$warnEntry = new WarnEntry(
				$insertId,
				strtolower($playerName),
				$reason,
				$source,
				$expiration,
				$timestamp
			);
			(new WarnAddEvent($warnEntry))->call();
			if ($onSuccess !== null) {
				$onSuccess($warnEntry);
			}
		}, $onError);
	}

	public function removeWarns(string $playerName, ?Closure $onSuccess = null, ?Closure $onError = null) : void {
		$this->getWarns($playerName, function (array $warns) use ($playerName, $onSuccess, $onError) : void {
			$this->database->executeChange('warn.remove_player', [
				'player_name' => $playerName,
			], function (int $affectedRows) use ($warns, $onSuccess) : void {
				foreach ($warns as $warnEntry) {
					(new WarnRemoveEvent($warnEntry))->call();
				}

				if ($onSuccess !== null) {
					$onSuccess($affectedRows);
				}
			}, $onError);
		});
	}

	public function removeWarnId(int $id, string $playerName, ?Closure $onSuccess = null, ?Closure $onError = null) : void {
		$this->database->executeChange('warn.remove_id', [
			'id' => $id,
			'player_name' => $playerName,
		], function (int $affectedRows) use ($onSuccess) : void {
			if ($onSuccess !== null) {
				$onSuccess($affectedRows);
			}
		}, $onError);
	}

	public function getWarns(string $playerName, ?Closure $onSuccess = null, ?Closure $onError = null) : void {
		$this->database->executeSelect('warn.get_all', [
			'player_name' => $playerName,
		], function (array $rows) use ($onSuccess) : void {
			$warns = [];
			foreach ($rows as $row) {
				$warns[] = WarnEntry::fromArray([
					'id' => $row['id'],
					'player' => $row['player_name'],
					'reason' => $row['reason'],
					'source' => $row['source'],
					'expiration' => $row['expiration'],
					'timestamp' => $row['timestamp'],
				]);
			}

			if ($onSuccess !== null) {
				$onSuccess($warns);
			}
		}, $onError);
	}

	public function getWarningCount(string $playerName, ?Closure $onSuccess = null, ?Closure $onError = null) : void {
		$this->database->executeSelect('warn.count', [
			'player_name' => $playerName,
		], function (array $rows) use ($onSuccess) : void {
			$count = $rows[0]['count'] ?? 0;
			if ($onSuccess !== null) {
				$onSuccess($count);
			}
		}, $onError);
	}

	public function getExpiredWarns(?Closure $onSuccess = null, ?Closure $onError = null) : void {
		$this->database->executeSelect('warn.get_expired', [], function (array $rows) use ($onSuccess) : void {
			$warns = [];
			foreach ($rows as $row) {
				$warns[] = WarnEntry::fromArray([
					'id' => $row['id'],
					'player' => $row['player_name'],
					'reason' => $row['reason'],
					'source' => $row['source'],
					'expiration' => $row['expiration'],
					'timestamp' => $row['timestamp'],
				]);
			}

			if ($onSuccess !== null) {
				$onSuccess($warns);
			}
		}, $onError);
	}

	public function removeExpiredWarns(?Closure $onSuccess = null, ?Closure $onError = null) : void {
		$this->database->executeChange('warn.delete_expired', [], function (int $affectedRows) use ($onSuccess) : void {
			if ($onSuccess !== null) {
				$onSuccess($affectedRows);
			}
		}, $onError);
	}

	public function close() : void {
		$this->database->close();
	}
}
