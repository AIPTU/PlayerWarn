<?php

/*
 * Copyright (c) 2023 AIPTU
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/AIPTU/PlayerWarn
 */

declare(strict_types=1);

namespace aiptu\playerwarn;

use aiptu\playerwarn\commands\ClearWarnsCommand;
use aiptu\playerwarn\commands\WarnCommand;
use aiptu\playerwarn\commands\WarnsCommand;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;
use function array_sum;
use function in_array;
use function is_int;
use function preg_match;

class PlayerWarn extends PluginBase implements Listener {
	private WarnList $warnList;
	private int $warningLimit;
	private string $punishmentType;
	private array $pendingPunishments = [];
	private array $lastWarningCounts = [];

	public function onEnable() : void {
		$this->warnList = new WarnList($this->getDataFolder() . 'warnings.json');

		try {
			$this->loadConfig();
		} catch (\InvalidArgumentException $e) {
			$this->getLogger()->error('Error loading plugin configuration: ' . $e->getMessage());
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		$commandMap = $this->getServer()->getCommandMap();
		$commandMap->registerAll('PlayerWarn', [
			new WarnCommand($this),
			new WarnsCommand($this),
			new ClearWarnsCommand($this),
		]);

		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->getScheduler()->scheduleRepeatingTask(
			new ClosureTask(function () : void {
				$server = $this->getServer();
				$warns = $this->getWarns();

				foreach ($server->getOnlinePlayers() as $player) {
					$playerName = $player->getName();

					if (!$player->isOnline()) {
						continue;
					}

					$playerWarns = $warns->getWarns($playerName);
					$expiredCount = 0;

					foreach ($playerWarns as $index => $warnEntry) {
						if ($warnEntry->hasExpired()) {
							$warns->removeSpecificWarn($warnEntry);
							++$expiredCount;
						}
					}

					if ($expiredCount > 0) {
						$player->sendMessage(TextFormat::YELLOW . "You have {$expiredCount} warning(s) that have expired.");
					}
				}
			}),
			20
		);
	}

	/**
	 * @priority MONITOR
	 */
	public function onPlayerJoin(PlayerJoinEvent $event) : void {
		$player = $event->getPlayer();
		$playerName = $player->getName();

		$lastWarningCount = $this->getLastWarningCount($playerName);
		$currentWarningCount = $this->getWarns()->getWarningCount($playerName);

		if ($currentWarningCount > $lastWarningCount) {
			$newWarningCount = $currentWarningCount - $lastWarningCount;
			$player->sendMessage(TextFormat::YELLOW . "You have received {$newWarningCount} new warning(s). Please take note of your behavior.");
		}

		$this->setLastWarningCount($playerName, $currentWarningCount);

		$warns = $this->getWarns();

		if ($warns->hasWarnings($playerName)) {
			$warningCount = $warns->getWarningCount($playerName);
			$player->sendMessage(TextFormat::RED . "You have {$warningCount} active warning(s). Please take note of your behavior.");
		}

		if ($this->hasPendingPunishments($playerName)) {
			$pendingPunishments = $this->getPendingPunishments($playerName);
			foreach ($pendingPunishments as $pendingPunishment) {
				$punishmentType = $pendingPunishment['punishmentType'];
				$reason = $pendingPunishment['reason'];
				$issuerName = $pendingPunishment['issuerName'];
				$this->applyPunishment($player, $punishmentType, $issuerName, $reason);
			}

			$this->removePendingPunishments($playerName);
		}
	}

	/**
	 * Loads and validates the plugin configuration from the `config.yml` file.
	 * If the configuration is invalid, an exception will be thrown.
	 *
	 * @throws \InvalidArgumentException when the configuration is invalid
	 */
	private function loadConfig() : void {
		$this->saveDefaultConfig();

		$config = $this->getConfig();

		$warningLimit = $config->get('warning_limit', 3);
		if (!is_int($warningLimit) || $warningLimit <= 0) {
			throw new \InvalidArgumentException('Invalid warning limit value in the configuration.');
		}
		$this->warningLimit = $warningLimit;

		$punishmentType = $config->get('punishment_type', 'none');
		if (!in_array($punishmentType, ['none', 'kick', 'ban', 'ban-ip'], true)) {
			throw new \InvalidArgumentException('Invalid punishment type in the configuration. Valid options are "none", "kick", "ban", and "ban-ip".');
		}
		$this->punishmentType = $punishmentType;
	}

	public static function parseDurationString(string $durationString) : ?\DateTime {
		$pattern = '/^(\d+d)?(\d+h)?(\d+m)?(\d+s)?$/';

		if (preg_match($pattern, $durationString, $matches) !== 1) {
			throw new \InvalidArgumentException('Invalid duration string format. The format should be a combination of digits followed by d (days), h (hours), m (minutes), and s (seconds). Example: 1d2h30m');
		}

		$duration = [
			'days' => (int) ($matches[1] ?? 0),
			'hours' => (int) ($matches[2] ?? 0),
			'minutes' => (int) ($matches[3] ?? 0),
			'seconds' => (int) ($matches[4] ?? 0),
		];

		$hasDuration = array_sum($duration) > 0;

		if (!$hasDuration) {
			return null;
		}

		$now = new \DateTime();
		$interval = new \DateInterval('P' . $duration['days'] . 'DT' . $duration['hours'] . 'H' . $duration['minutes'] . 'M' . $duration['seconds'] . 'S');

		return $now->add($interval);
	}

	public function getWarns() : WarnList {
		return $this->warnList;
	}

	public function getWarningLimit() : int {
		return $this->warningLimit;
	}

	public function getPunishmentType() : string {
		return $this->punishmentType;
	}

	public function applyPunishment(Player $player, string $punishmentType, string $issuerName, string $reason) : void {
		$server = $player->getServer();
		$playerName = $player->getName();

		switch ($punishmentType) {
			case 'kick':
				$player->kick(TextFormat::RED . 'You have reached the warning limit.');
				break;
			case 'ban':
				$banList = $server->getNameBans();
				if (!$banList->isBanned($playerName)) {
					$banList->addBan($playerName, $reason, null, $issuerName);
				}
				$player->kick(TextFormat::RED . 'You have been banned for reaching the warning limit.');
				break;
			case 'ban-ip':
				$ip = $player->getNetworkSession()->getIp();
				$ipBanList = $server->getIPBans();
				if (!$ipBanList->isBanned($ip)) {
					$ipBanList->addBan($ip, $reason, null, $issuerName);
				}
				$player->kick(TextFormat::RED . 'You have been banned for reaching the warning limit.');
				$server->getNetwork()->blockAddress($ip, -1);
				break;
		}
	}

	public function addPendingPunishment(string $playerName, string $punishmentType, string $issuerName, string $reason) : void {
		$pendingPunishment = [
			'punishmentType' => $punishmentType,
			'issuerName' => $issuerName,
			'reason' => $reason,
		];
		$this->pendingPunishments[$playerName][] = $pendingPunishment;
	}

	public function hasPendingPunishments(string $playerName) : bool {
		return isset($this->pendingPunishments[$playerName]);
	}

	public function getPendingPunishments(string $playerName) : array {
		return $this->pendingPunishments[$playerName] ?? [];
	}

	public function removePendingPunishments(string $playerName) : void {
		unset($this->pendingPunishments[$playerName]);
	}

	private function getLastWarningCount(string $playerName) : int {
		return $this->lastWarningCounts[$playerName] ?? 0;
	}

	private function setLastWarningCount(string $playerName, int $warningCount) : void {
		$this->lastWarningCounts[$playerName] = $warningCount;
	}
}
