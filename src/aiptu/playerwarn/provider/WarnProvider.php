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

use aiptu\playerwarn\cache\QueryCache;
use aiptu\playerwarn\event\WarnAddEvent;
use aiptu\playerwarn\event\WarnEditEvent;
use aiptu\playerwarn\event\WarnRemoveEvent;
use aiptu\playerwarn\utils\Utils;
use aiptu\playerwarn\warns\WarnEntry;
use Closure;
use DateTimeImmutable;
use aiptu\playerwarn\libs\_6d59fc2a2e926b48\poggit\libasynql\DataConnector;
use function count;
use function strtolower;

class WarnProvider {
	private QueryCache $cache;

	public function __construct(
		private DataConnector $database,
		private \AttachableLogger $logger
	) {
		$this->cache = new QueryCache();
		$this->database->executeGeneric('table.init');
	}

	/**
	 * Adds a new warning to the database.
	 * Invalidates warning count cache for the player.
	 */
	public function addWarn(
		string $playerName,
		string $reason,
		string $source,
		?DateTimeImmutable $expiration,
		?Closure $onSuccess = null,
		?Closure $onError = null
	) : void {
		$normalizedName = strtolower($playerName);
		$timestamp = new DateTimeImmutable();

		$this->database->executeInsert('warn.add', [
			'player_name' => $normalizedName,
			'reason' => $reason,
			'source' => $source,
			'expiration' => $expiration?->format(Utils::DATE_TIME_FORMAT),
			'timestamp' => $timestamp->format(Utils::DATE_TIME_FORMAT),
		], function (int $insertId, int $affectedRows) use ($normalizedName, $reason, $source, $expiration, $timestamp, $onSuccess) : void {
			$this->cache->invalidate("warn_count:{$normalizedName}");
			$this->cache->invalidate("warn_list:{$normalizedName}");

			$warnEntry = new WarnEntry(
				$insertId,
				$normalizedName,
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
	 * Invalidates related caches.
	 */
	public function removeWarns(
		string $playerName,
		?Closure $onSuccess = null,
		?Closure $onError = null
	) : void {
		$normalizedName = strtolower($playerName);

		$this->getWarns($playerName, function (array $warns) use ($normalizedName, $onSuccess, $onError) : void {
			$this->database->executeChange('warn.remove_player', [
				'player_name' => $normalizedName,
			], function (int $affectedRows) use ($normalizedName, $warns, $onSuccess) : void {
				if ($affectedRows > 0) {
					$this->cache->invalidate("warn_count:{$normalizedName}");
					$this->cache->invalidate("warn_list:{$normalizedName}");

					foreach ($warns as $warnEntry) {
						(new WarnRemoveEvent($warnEntry))->call();
					}
				}

				if ($onSuccess !== null) {
					$onSuccess($affectedRows);
				}
			}, $this->wrapErrorHandler($onError, "Failed to remove warnings for {$normalizedName}"));
		}, $this->wrapErrorHandler($onError, "Failed to fetch warnings for {$normalizedName}"));
	}

	/**
	 * Removes a specific warning by its ID.
	 * Invalidates caches for the affected player.
	 */
	public function removeWarnById(
		int $id,
		string $playerName,
		?Closure $onSuccess = null,
		?Closure $onError = null
	) : void {
		$normalizedName = strtolower($playerName);

		$this->database->executeSelect('warn.get_id', [
			'id' => $id,
			'player_name' => $normalizedName,
		], function (array $rows) use ($normalizedName, $id, $onSuccess, $onError) : void {
			$warnEntry = null;
			if (count($rows) > 0) {
				$row = $rows[0];
				$warnEntry = new WarnEntry(
					(int) $row['id'],
					$row['player_name'],
					$row['reason'],
					$row['source'],
					$row['expiration'] !== null ? new DateTimeImmutable($row['expiration']) : null,
					new DateTimeImmutable($row['timestamp'])
				);
			}

			$this->database->executeChange('warn.remove_id', [
				'id' => $id,
				'player_name' => $normalizedName,
			], function (int $affectedRows) use ($normalizedName, $warnEntry, $onSuccess) : void {
				if ($affectedRows > 0) {
					$this->cache->invalidate("warn_count:{$normalizedName}");
					$this->cache->invalidate("warn_list:{$normalizedName}");

					if ($warnEntry !== null) {
						(new WarnRemoveEvent($warnEntry))->call();
					}
				}

				if ($onSuccess !== null) {
					$onSuccess($affectedRows);
				}
			}, $this->wrapErrorHandler($onError, "Failed to remove warning ID {$id}"));
		}, $this->wrapErrorHandler($onError, "Failed to fetch warning ID {$id}"));
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
	 * Uses cache to minimize database queries.
	 */
	public function getWarningCount(
		string $playerName,
		?Closure $onSuccess = null,
		?Closure $onError = null
	) : void {
		$normalizedName = strtolower($playerName);
		$cacheKey = "warn_count:{$normalizedName}";

		$cachedCount = $this->cache->get($cacheKey);
		if ($cachedCount !== null) {
			if ($onSuccess !== null) {
				$onSuccess($cachedCount);
			}

			return;
		}

		$this->database->executeSelect('warn.count', [
			'player_name' => $normalizedName,
		], function (array $rows) use ($cacheKey, $onSuccess) : void {
			$count = (int) ($rows[0]['count'] ?? 0);

			$this->cache->set($cacheKey, $count, 300.0);

			if ($onSuccess !== null) {
				$onSuccess($count);
			}
		}, $this->wrapErrorHandler($onError, "Failed to get warning count for {$normalizedName}"));
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
	 * Retrieves all players with active warnings and their warning counts.
	 */
	public function getAllPlayersWithWarnings(
		?Closure $onSuccess = null,
		?Closure $onError = null
	) : void {
		$this->database->executeSelect('warn.get_all_players', [], function (array $rows) use ($onSuccess) : void {
			$playersData = [];

			foreach ($rows as $row) {
				try {
					$playersData[] = [
						'player' => $row['player_name'],
						'count' => (int) $row['count'],
						'last_warning' => new DateTimeImmutable($row['last_warning']),
					];
				} catch (\Throwable $e) {
					$this->logger->error('Failed to parse player warning data: ' . $e->getMessage());
				}
			}

			if ($onSuccess !== null) {
				$onSuccess($playersData);
			}
		}, $this->wrapErrorHandler($onError, 'Failed to fetch all players with warnings'));
	}

	/**
	 * Removes all expired warnings.
	 * Clears affected player caches.
	 */
	public function removeExpiredWarns(
		?Closure $onSuccess = null,
		?Closure $onError = null
	) : void {
		$this->database->executeChange('warn.delete_expired', [], function (int $affectedRows) use ($onSuccess) : void {
			if ($affectedRows > 0) {
				$this->cache->invalidatePattern('/^warn_(count|list):/');
			}

			if ($onSuccess !== null) {
				$onSuccess($affectedRows);
			}
		}, $this->wrapErrorHandler($onError, 'Failed to remove expired warnings'));
	}

	/**
	 * Updates the reason for a specific warning.
	 */
	public function updateWarnReason(
		int $id,
		string $playerName,
		string $newReason,
		?Closure $onSuccess = null,
		?Closure $onError = null
	) : void {
		$normalizedName = strtolower($playerName);

		$this->getWarns($playerName, function (array $warns) use ($id, $normalizedName, $newReason, $onSuccess, $onError) : void {
			$currentWarn = null;
			foreach ($warns as $warn) {
				if ($warn->getId() === $id) {
					$currentWarn = $warn;
					break;
				}
			}

			$this->database->executeChange('warn.update_reason', [
				'id' => $id,
				'player_name' => $normalizedName,
				'reason' => $newReason,
			], function (int $affectedRows) use ($normalizedName, $currentWarn, $newReason, $onSuccess) : void {
				if ($affectedRows > 0) {
					$this->cache->invalidate("warn_count:{$normalizedName}");
					$this->cache->invalidate("warn_list:{$normalizedName}");

					if ($currentWarn !== null) {
						$newWarnEntry = new WarnEntry(
							$currentWarn->getId(),
							$currentWarn->getPlayerName(),
							$newReason,
							$currentWarn->getSource(),
							$currentWarn->getExpiration(),
							$currentWarn->getTimestamp()
						);
						(new WarnEditEvent(
							$newWarnEntry,
							'reason',
							$currentWarn->getReason(),
							$newReason
						))->call();
					}
				}

				if ($onSuccess !== null) {
					$onSuccess($affectedRows);
				}
			}, $this->wrapErrorHandler($onError, "Failed to update warning ID {$id}"));
		}, $onError);
	}

	/**
	 * Updates the expiration date for a specific warning.
	 */
	public function updateWarnExpiration(
		int $id,
		string $playerName,
		?DateTimeImmutable $newExpiration,
		?Closure $onSuccess = null,
		?Closure $onError = null
	) : void {
		$normalizedName = strtolower($playerName);

		$this->getWarns($playerName, function (array $warns) use ($id, $normalizedName, $newExpiration, $onSuccess, $onError) : void {
			$currentWarn = null;
			foreach ($warns as $warn) {
				if ($warn->getId() === $id) {
					$currentWarn = $warn;
					break;
				}
			}

			$this->database->executeChange('warn.update_expiration', [
				'id' => $id,
				'player_name' => $normalizedName,
				'expiration' => $newExpiration?->format(Utils::DATE_TIME_FORMAT),
			], function (int $affectedRows) use ($normalizedName, $currentWarn, $newExpiration, $onSuccess) : void {
				if ($affectedRows > 0) {
					$this->cache->invalidate("warn_count:{$normalizedName}");
					$this->cache->invalidate("warn_list:{$normalizedName}");

					if ($currentWarn !== null) {
						$oldExpirationStr = $currentWarn->getExpiration() !== null
							? $currentWarn->getExpiration()->format(Utils::DATE_TIME_FORMAT)
							: 'Never';
						$newExpirationStr = $newExpiration !== null
							? $newExpiration->format(Utils::DATE_TIME_FORMAT)
							: 'Never';

						$newWarnEntry = new WarnEntry(
							$currentWarn->getId(),
							$currentWarn->getPlayerName(),
							$currentWarn->getReason(),
							$currentWarn->getSource(),
							$newExpiration,
							$currentWarn->getTimestamp()
						);
						(new WarnEditEvent(
							$newWarnEntry,
							'expiration',
							$oldExpirationStr,
							$newExpirationStr
						))->call();
					}
				}

				if ($onSuccess !== null) {
					$onSuccess($affectedRows);
				}
			}, $this->wrapErrorHandler($onError, "Failed to update warning ID {$id}"));
		}, $onError);
	}

	public function close() : void {
		$this->cache->clear();
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