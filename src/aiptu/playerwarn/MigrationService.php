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

namespace aiptu\playerwarn;

use aiptu\playerwarn\provider\WarnProvider;
use aiptu\playerwarn\utils\Utils;
use function file_get_contents;
use function is_array;
use function json_decode;
use function rename;
use function strtolower;
use const JSON_THROW_ON_ERROR;

class MigrationService {
	public function __construct(
		private WarnProvider $provider,
		private \AttachableLogger $logger
	) {}

	/**
	 * Migrates the warnings from the warnings.json file to the database.
	 */
	public function migrateFromJson(string $jsonFilePath) : void {
		$this->logger->info('Migrating warnings from warnings.json to database...');

		$jsonContent = file_get_contents($jsonFilePath);
		if ($jsonContent === false) {
			$this->logger->error('Failed to read warnings.json for migration.');
			return;
		}

		try {
			$data = json_decode($jsonContent, true, flags: JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			$this->logger->error('Failed to parse warnings.json: ' . $e->getMessage());
			return;
		}

		if (!is_array($data) || !isset($data['warns']) || !is_array($data['warns'])) {
			$this->logger->error('Invalid warnings.json format.');
			return;
		}

		$count = 0;
		foreach ($data['warns'] as $playerName => $warns) {
			if (!is_array($warns)) {
				continue;
			}

			foreach ($warns as $warnData) {
				if (!is_array($warnData)) {
					continue;
				}

				try {
					$this->migrateWarning($playerName, $warnData);
					++$count;
				} catch (\Throwable $e) {
					$this->logger->warning("Failed to migrate a warning for {$playerName}: " . $e->getMessage());
				}
			}
		}

		$this->logger->info("Migrated {$count} warning(s) successfully.");

		$backupPath = $jsonFilePath . '.migrated';
		if (rename($jsonFilePath, $backupPath)) {
			$this->logger->info("Old warnings file backed up to: {$backupPath}");
		}
	}

	/**
	 * Migrates a single warning to the database.
	 */
	private function migrateWarning(string $playerName, array $warnData) : void {
		$reason = $warnData['reason'] ?? 'Unknown';
		$source = $warnData['source'] ?? 'Console';
		$expirationStr = $warnData['expiration'] ?? null;

		$expiration = null;
		if ($expirationStr !== null && strtolower($expirationStr) !== 'never') {
			try {
				$expiration = Utils::parseDurationString($expirationStr);
			} catch (\InvalidArgumentException $e) {
				$this->logger->debug("Could not parse expiration '{$expirationStr}': " . $e->getMessage());
			}
		}

		$this->provider->addWarn(
			$playerName,
			$reason,
			$source,
			$expiration,
			null,
			function (\Throwable $e) use ($playerName) : void {
				$this->logger->error("Database error migrating warning for {$playerName}: " . $e->getMessage());
			}
		);
	}
}