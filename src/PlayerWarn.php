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
use aiptu\playerwarn\task\ExpiredWarningsTask;
use aiptu\playerwarn\warns\WarnList;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use Symfony\Component\Filesystem\Path;
use function in_array;
use function is_int;

class PlayerWarn extends PluginBase {
	private WarnList $warnList;
	private int $warningLimit;
	private string $punishmentType;
	private array $pendingPunishments = [];
	private array $lastWarningCounts = [];

	public function onEnable() : void {
		$this->warnList = new WarnList(Path::join($this->getDataFolder(), 'warnings.json'));

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

		$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);

		$this->getScheduler()->scheduleRepeatingTask(new ExpiredWarningsTask($this), 20);
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

	public function getWarns() : WarnList {
		return $this->warnList;
	}

	/**
	 * Returns the warning limit.
	 */
	public function getWarningLimit() : int {
		return $this->warningLimit;
	}

	/**
	 * Returns the punishment type.
	 */
	public function getPunishmentType() : string {
		return $this->punishmentType;
	}

	/**
	 * Applies a punishment to the player based on the punishment type.
	 */
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

	/**
	 * Adds a pending punishment for the player.
	 */
	public function addPendingPunishment(string $playerName, string $punishmentType, string $issuerName, string $reason) : void {
		$pendingPunishment = [
			'punishmentType' => $punishmentType,
			'issuerName' => $issuerName,
			'reason' => $reason,
		];
		$this->pendingPunishments[$playerName][] = $pendingPunishment;
	}

	/**
	 * Checks if a player has pending punishments.
	 */
	public function hasPendingPunishments(string $playerName) : bool {
		return isset($this->pendingPunishments[$playerName]);
	}

	/**
	 * Returns the pending punishments for the player.
	 */
	public function getPendingPunishments(string $playerName) : array {
		return $this->pendingPunishments[$playerName] ?? [];
	}

	/**
	 * Removes the pending punishments for the player.
	 */
	public function removePendingPunishments(string $playerName) : void {
		unset($this->pendingPunishments[$playerName]);
	}

	/**
	 * Returns the last warning count for the player.
	 */
	public function getLastWarningCount(string $playerName) : int {
		return $this->lastWarningCounts[$playerName] ?? 0;
	}

	/**
	 * Sets the last warning count for the player.
	 */
	public function setLastWarningCount(string $playerName, int $warningCount) : void {
		$this->lastWarningCounts[$playerName] = $warningCount;
	}
}
