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
use pocketmine\utils\TextFormat;
use function in_array;
use function is_int;

class PlayerWarn extends PluginBase implements Listener {
	private WarnList $warnList;
	private array $pendingPunishments = [];

	private int $warningLimit;
	private string $punishmentType;

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
	}

	/**
	 * @priority MONITOR
	 */
	public function onPlayerJoin(PlayerJoinEvent $event) : void {
		$player = $event->getPlayer();
		$playerName = $player->getName();

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

	public function getWarns() : WarnList {
		return $this->warnList;
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
}
