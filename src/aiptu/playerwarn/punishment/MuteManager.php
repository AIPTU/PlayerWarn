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

use aiptu\playerwarn\utils\Utils;
use Closure;
use DateTimeImmutable;
use poggit\libasynql\DataConnector;
use function count;
use function strtolower;

class MuteManager {
	/** @var array<string, array{expiration: ?DateTimeImmutable, reason: string, source: string}> */
	private array $muteCache = [];

	public function __construct(
		private DataConnector $database,
		private \AttachableLogger $logger
	) {}

	/**
	 * Mutes a player by inserting/replacing a row in the database.
	 *
	 * @param string                     $playerName the player to mute
	 * @param string                     $reason     reason for the mute
	 * @param string                     $source     who issued the mute
	 * @param ?DateTimeImmutable         $expiration when the mute expires (null = permanent)
	 * @param ?Closure(): void           $onSuccess  callback on success
	 * @param ?Closure(\Throwable): void $onError    callback on error
	 */
	public function mute(
		string $playerName,
		string $reason,
		string $source,
		?DateTimeImmutable $expiration,
		?Closure $onSuccess = null,
		?Closure $onError = null
	) : void {
		$normalizedName = strtolower($playerName);
		$timestamp = new DateTimeImmutable();

		$this->database->executeInsert('mute.add', [
			'player_name' => $normalizedName,
			'reason' => $reason,
			'source' => $source,
			'expiration' => $expiration?->format(Utils::DATE_TIME_FORMAT),
			'timestamp' => $timestamp->format(Utils::DATE_TIME_FORMAT),
		], function (int $insertId, int $affectedRows) use ($normalizedName, $reason, $source, $expiration, $onSuccess) : void {
			$this->muteCache[$normalizedName] = [
				'expiration' => $expiration,
				'reason' => $reason,
				'source' => $source,
			];

			if ($onSuccess !== null) {
				$onSuccess();
			}
		}, $this->wrapErrorHandler($onError, "Failed to mute player {$normalizedName}"));
	}

	/**
	 * Unmutes a player by removing their mute from the database.
	 *
	 * @param ?Closure(int): void        $onSuccess callback with affected rows count
	 * @param ?Closure(\Throwable): void $onError   callback on error
	 */
	public function unmute(
		string $playerName,
		?Closure $onSuccess = null,
		?Closure $onError = null
	) : void {
		$normalizedName = strtolower($playerName);

		$this->database->executeChange('mute.remove', [
			'player_name' => $normalizedName,
		], function (int $affectedRows) use ($normalizedName, $onSuccess) : void {
			unset($this->muteCache[$normalizedName]);

			if ($onSuccess !== null) {
				$onSuccess($affectedRows);
			}
		}, $this->wrapErrorHandler($onError, "Failed to unmute player {$normalizedName}"));
	}

	/**
	 * Checks if a player is currently muted.
	 * Uses in-memory cache first, falls back to database.
	 *
	 * @param Closure(bool): void        $onResult callback with mute status
	 * @param ?Closure(\Throwable): void $onError  callback on error
	 */
	public function isMuted(
		string $playerName,
		Closure $onResult,
		?Closure $onError = null
	) : void {
		$normalizedName = strtolower($playerName);

		if (isset($this->muteCache[$normalizedName])) {
			$cached = $this->muteCache[$normalizedName];
			if ($cached['expiration'] !== null && $cached['expiration'] <= new DateTimeImmutable()) {
				unset($this->muteCache[$normalizedName]);
				$onResult(false);
				return;
			}

			$onResult(true);
			return;
		}

		$this->getActiveMute($normalizedName, function (?array $muteData) use ($onResult) : void {
			$onResult($muteData !== null);
		}, $onError);
	}

	/**
	 * Retrieves active mute details for a player.
	 *
	 * @param Closure(?array{expiration: ?DateTimeImmutable, reason: string, source: string}): void $onResult
	 * @param ?Closure(\Throwable): void                                                            $onError
	 */
	public function getActiveMute(
		string $playerName,
		Closure $onResult,
		?Closure $onError = null
	) : void {
		$normalizedName = strtolower($playerName);

		if (isset($this->muteCache[$normalizedName])) {
			$cached = $this->muteCache[$normalizedName];
			if ($cached['expiration'] !== null && $cached['expiration'] <= new DateTimeImmutable()) {
				unset($this->muteCache[$normalizedName]);
				$onResult(null);
				return;
			}

			$onResult($cached);
			return;
		}

		$this->database->executeSelect('mute.get', [
			'player_name' => $normalizedName,
		], function (array $rows) use ($normalizedName, $onResult) : void {
			if (count($rows) === 0) {
				$onResult(null);
				return;
			}

			$row = $rows[0];
			$muteData = [
				'expiration' => $row['expiration'] !== null ? new DateTimeImmutable($row['expiration']) : null,
				'reason' => $row['reason'],
				'source' => $row['source'],
			];

			$this->muteCache[$normalizedName] = $muteData;
			$onResult($muteData);
		}, $this->wrapErrorHandler($onError, "Failed to get mute status for {$normalizedName}"));
	}

	/**
	 * Loads mute status from the database into the cache for a specific player.
	 * Should be called on player join.
	 */
	public function loadPlayerMute(string $playerName) : void {
		$normalizedName = strtolower($playerName);

		$this->database->executeSelect('mute.get', [
			'player_name' => $normalizedName,
		], function (array $rows) use ($normalizedName) : void {
			if (count($rows) > 0) {
				$row = $rows[0];
				$this->muteCache[$normalizedName] = [
					'expiration' => $row['expiration'] !== null ? new DateTimeImmutable($row['expiration']) : null,
					'reason' => $row['reason'],
					'source' => $row['source'],
				];
			}
		}, function (\Throwable $error) use ($normalizedName) : void {
			$this->logger->warning("Failed to load mute status for {$normalizedName}: " . $error->getMessage());
		});
	}

	/**
	 * Removes a player's mute from the cache (e.g., on quit).
	 */
	public function unloadPlayerMute(string $playerName) : void {
		unset($this->muteCache[strtolower($playerName)]);
	}

	/**
	 * Cleans up expired mutes from the database.
	 */
	public function cleanupExpiredMutes(?Closure $onSuccess = null) : void {
		$this->database->executeChange('mute.delete_expired', [], function (int $affectedRows) use ($onSuccess) : void {
			if ($onSuccess !== null) {
				$onSuccess($affectedRows);
			}
		}, function (\Throwable $error) : void {
			$this->logger->error('Failed to clean up expired mutes: ' . $error->getMessage());
		});
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
