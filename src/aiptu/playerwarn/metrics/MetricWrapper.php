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

namespace aiptu\playerwarn\metrics;

use aiptu\playerwarn\PlayerWarn;
use aiptu\playerwarn\punishment\PunishmentType;
use aiptu\playerwarn\libs\_1e9af5e52299cbb8\bStats\PocketmineMp\charts\SimplePie;
use aiptu\playerwarn\libs\_1e9af5e52299cbb8\bStats\PocketmineMp\charts\SingleLineChart;
use aiptu\playerwarn\libs\_1e9af5e52299cbb8\bStats\PocketmineMp\Metrics;
use function count;
use function is_int;
use function is_string;
use function strtolower;

class MetricWrapper {
	private const int PLUGIN_ID = 29586;

	private function __construct() {}

	public static function register(PlayerWarn $plugin) : void {
		$metrics = new Metrics($plugin, self::PLUGIN_ID);

		self::addDatabaseTypeChart($metrics, $plugin);
		self::addLanguageChart($metrics, $plugin);
		self::addPunishmentTypeChart($metrics, $plugin);
		self::addEnabledFeaturesCountChart($metrics, $plugin);
		self::addWarningLimitChart($metrics, $plugin);
		self::addPunishmentDelayChart($metrics, $plugin);
		self::addExpirationCheckIntervalChart($metrics, $plugin);
		self::addOnlineWarnedPlayersChart($metrics, $plugin);
	}

	private static function addDatabaseTypeChart(Metrics $metrics, PlayerWarn $plugin) : void {
		$metrics->addCustomChart(new SimplePie(
			'database_type',
			static function () use ($plugin) : string {
				$type = $plugin->getConfig()->getNested('database.type', 'sqlite');
				return is_string($type) ? strtolower($type) : 'sqlite';
			}
		));
	}

	private static function addLanguageChart(Metrics $metrics, PlayerWarn $plugin) : void {
		$metrics->addCustomChart(new SimplePie(
			'language',
			static function () use ($plugin) : string {
				$lang = $plugin->getConfig()->get('language', 'en');
				return is_string($lang) ? $lang : 'en';
			}
		));
	}

	private static function addPunishmentTypeChart(Metrics $metrics, PlayerWarn $plugin) : void {
		$metrics->addCustomChart(new SimplePie(
			'punishment_type',
			static function () use ($plugin) : string {
				$type = $plugin->getPunishmentType();

				if ($type !== PunishmentType::TEMPBAN) {
					return $type->value;
				}

				$until = $plugin->getTempbanDuration();
				if ($until === null) {
					return 'tempban';
				}

				$hours = ($until->getTimestamp() - (new \DateTimeImmutable())->getTimestamp()) / 3600;
				$bucket = match (true) {
					$hours < 1 => '<1 h',
					$hours <= 12 => '1–12 h',
					$hours <= 72 => '12 h–3 d',
					default => '3 d+',
				};

				return "tempban ({$bucket})";
			}
		));
	}

	private static function addEnabledFeaturesCountChart(Metrics $metrics, PlayerWarn $plugin) : void {
		$metrics->addCustomChart(new SimplePie(
			'enabled_features_count',
			static function () use ($plugin) : string {
				$enabled = 0;

				if ($plugin->isDiscordEnabled()) {
					++$enabled;
				}

				if ($plugin->isBroadcastToEveryoneEnabled()) {
					++$enabled;
				}

				$updateNotifier = $plugin->getConfig()->get('update_notifier', true);
				if ($updateNotifier === true) {
					++$enabled;
				}

				return "{$enabled} of 3";
			}
		));
	}

	private static function addWarningLimitChart(Metrics $metrics, PlayerWarn $plugin) : void {
		$metrics->addCustomChart(new SingleLineChart(
			'warning_limit',
			static fn () : int => $plugin->getWarningLimit()
		));
	}

	private static function addPunishmentDelayChart(Metrics $metrics, PlayerWarn $plugin) : void {
		$metrics->addCustomChart(new SingleLineChart(
			'punishment_delay_seconds',
			static function () use ($plugin) : int {
				$raw = $plugin->getConfig()->getNested('warning.delay', 5);
				return is_int($raw) && $raw >= 0 ? $raw : 5;
			}
		));
	}

	private static function addExpirationCheckIntervalChart(Metrics $metrics, PlayerWarn $plugin) : void {
		$metrics->addCustomChart(new SingleLineChart(
			'expiration_check_interval_seconds',
			static function () use ($plugin) : int {
				$raw = $plugin->getConfig()->getNested('warning.expiration_check_interval', 180);
				return is_int($raw) && $raw > 0 ? $raw : 180;
			}
		));
	}

	private static function addOnlineWarnedPlayersChart(Metrics $metrics, PlayerWarn $plugin) : void {
		$metrics->addCustomChart(new SingleLineChart(
			'online_warned_players',
			static function () use ($plugin) : int {
				$onlinePlayers = $plugin->getServer()->getOnlinePlayers();

				if (count($onlinePlayers) === 0) {
					return 0;
				}

				$warnedCount = 0;

				foreach ($onlinePlayers as $player) {
					$plugin->getProvider()->getWarningCount(
						$player->getName(),
						static function (int $count) use (&$warnedCount) : void {
							if ($count > 0) {
								++$warnedCount;
							}
						}
					);
				}

				return $warnedCount;
			}
		));
	}
}