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
use aiptu\playerwarn\utils\Utils;
use aiptu\playerwarn\warns\WarnEntry;
use Closure;
use DateTimeImmutable;
use poggit\libasynql\DataConnector;
use function strtolower;

class WarnProvider {
	public function __construct(
		private DataConnector $database,
		private \AttachableLogger $logger
	) {
		$this->database->executeGeneric('table.init');
	}

	/**
	 * Adds a new warning to the database.
	 */
	public function addWarn(
		string $playerName,
		string $reason,
		string $source,
		?DateTimeImmutable $expiration,
		?Closure $onSuccess = null,
		?Closure $onError = null
	) : void {
		$timestamp = new DateTimeImmutable();

		$this->database->executeInsert('warn.add', [
			'player_name' => strtolower($playerName),
			'reason' => $reason,
			'source' => $source,
			'expiration' => $expiration?->format(Utils::DATE_TIME_FORMAT),
			'timestamp' => $timestamp->format(Utils::DATE_TIME_FORMAT),
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
		}, $this->wrapErrorHandler($onError, 'Failed to add warning'));
	}

	/**
	 * Removes all warnings for a player from the database.
	 */
	public function removeWarns(
		string $playerName,
		?Closure $onSuccess = null,
		?Closure $onError = null
	) : void {
		$this->getWarns($playerName, function (array $warns) use ($playerName, $onSuccess, $onError) : void {
			$this->database->executeChange('warn.remove_player', [
				'player_name' => strtolower($playerName),
			], function (int $affectedRows) use ($warns, $onSuccess) : void {
				if ($affectedRows > 0) {
					foreach ($warns as $warnEntry) {
						(new WarnRemoveEvent($warnEntry))->call();
					}
				}

				if ($onSuccess !== null) {
					$onSuccess($affectedRows);
				}
			}, $this->wrapErrorHandler($onError, "Failed to remove warnings for {$playerName}"));
		}, $this->wrapErrorHandler($onError, "Failed to fetch warnings for {$playerName}"));
	}

	/**
	 * Removes a specific warning by its ID.
	 */
	public function removeWarnById(
		int $id,
		string $playerName,
		?Closure $onSuccess = null,
		?Closure $onError = null
	) : void {
		$this->database->executeChange('warn.remove_id', [
			'id' => $id,
			'player_name' => strtolower($playerName),
		], function (int $affectedRows) use ($onSuccess) : void {
			if ($onSuccess !== null) {
				$onSuccess($affectedRows);
			}
		}, $this->wrapErrorHandler($onError, "Failed to remove warning ID {$id}"));
	}

	/**
	 * Retrieves all warnings for a player.
	 */
	public function getWarns(
		string $playerName,
		?Closure $onSuccess = null,
		?Closure $onError = null
	) : void {
		$this->database->executeSelect('warn.get_all', [
			'player_name' => strtolower($playerName),
		], function (array $rows) use ($onSuccess) : void {
			$warns = [];

			foreach ($rows as $row) {
				try {
					$warns[] = WarnEntry::fromArray([
						'id' => $row['id'],
						'player' => $row['player_name'],
						'reason' => $row['reason'],
						'source' => $row['source'],
						'expiration' => $row['expiration'],
						'timestamp' => $row['timestamp'],
					]);
				} catch (\Throwable $e) {
					$this->logger->error('Failed to parse warning from database: ' . $e->getMessage());
				}
			}

			if ($onSuccess !== null) {
				$onSuccess($warns);
			}
		}, $this->wrapErrorHandler($onError, "Failed to fetch warnings for {$playerName}"));
	}

	/**
	 * Retrieves the count of warnings for a player.
	 */
	public function getWarningCount(
		string $playerName,
		?Closure $onSuccess = null,
		?Closure $onError = null
	) : void {
		$this->database->executeSelect('warn.count', [
			'player_name' => strtolower($playerName),
		], function (array $rows) use ($onSuccess) : void {
			$count = (int) ($rows[0]['count'] ?? 0);

			if ($onSuccess !== null) {
				$onSuccess($count);
			}
		}, $this->wrapErrorHandler($onError, "Failed to get warning count for {$playerName}"));
	}

	/**
	 * Retrieves all expired warnings.
	 */
	public function getExpiredWarns(
		?Closure $onSuccess = null,
		?Closure $onError = null
	) : void {
		$this->database->executeSelect('warn.get_expired', [], function (array $rows) use ($onSuccess) : void {
			$warns = [];

			foreach ($rows as $row) {
				try {
					$warns[] = WarnEntry::fromArray([
						'id' => $row['id'],
						'player' => $row['player_name'],
						'reason' => $row['reason'],
						'source' => $row['source'],
						'expiration' => $row['expiration'],
						'timestamp' => $row['timestamp'],
					]);
				} catch (\Throwable $e) {
					$this->logger->error('Failed to parse expired warning from database: ' . $e->getMessage());
				}
			}

			if ($onSuccess !== null) {
				$onSuccess($warns);
			}
		}, $this->wrapErrorHandler($onError, 'Failed to fetch expired warnings'));
	}

	/**
	 * Removes all expired warnings.
	 */
	public function removeExpiredWarns(
		?Closure $onSuccess = null,
		?Closure $onError = null
	) : void {
		$this->database->executeChange('warn.delete_expired', [], function (int $affectedRows) use ($onSuccess) : void {
			if ($onSuccess !== null) {
				$onSuccess($affectedRows);
			}
		}, $this->wrapErrorHandler($onError, 'Failed to remove expired warnings'));
	}

	public function close() : void {
		$this->database->close();
	}

	private function wrapErrorHandler(?Closure $userHandler, string $context) : Closure {
		return function (\Throwable $error) use ($userHandler, $context) : void {
			$this->logger->error("{$context}: " . $error->getMessage());

			if ($userHandler !== null) {
				$userHandler($error);
			}
		};
	}
}
