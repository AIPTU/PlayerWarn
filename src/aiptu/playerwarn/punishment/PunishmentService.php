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

use aiptu\playerwarn\event\PlayerPunishmentEvent;
use aiptu\playerwarn\MessageManager;
use aiptu\playerwarn\task\DelayedPunishmentTask;
use DateTimeImmutable;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;

class PunishmentService {
	public function __construct(
		private PluginBase $plugin,
		private Server $server,
		private \AttachableLogger $logger,
		private int $delaySeconds,
		private MessageManager $messageManager
	) {}

	public function scheduleDelayedPunishment(
		Player $player,
		PunishmentType $type,
		string $issuerName,
		string $reason,
		?DateTimeImmutable $until = null
	) : void {
		if ($this->delaySeconds <= 0) {
			$this->apply($player, $type, $issuerName, $reason, $until);
			return;
		}

		$this->plugin->getScheduler()->scheduleDelayedTask(
			new DelayedPunishmentTask($this, $player, $type, $issuerName, $reason, $until),
			$this->delaySeconds * 20
		);

		$warningMessage = $this->messageManager->get('punishment.warning-message', [
			'delay' => (string) $this->delaySeconds,
		]);
		$player->sendMessage($warningMessage);
	}

	public function apply(
		Player $player,
		PunishmentType $type,
		string $issuerName,
		string $reason,
		?DateTimeImmutable $until = null
	) : void {
		if (!$player->isOnline() || $player->isClosed()) {
			$this->logger->warning("Cannot punish offline player: {$player->getName()}");
			return;
		}

		$event = new PlayerPunishmentEvent($player, $type->value, $issuerName, $reason);
		$event->call();

		if ($event->isCancelled()) {
			$this->logger->info("Punishment cancelled for {$player->getName()} by event");
			return;
		}

		$message = $this->messageManager->get("punishment.{$type->value}");

		try {
			match ($type) {
				PunishmentType::KICK => $this->kick($player, $message),
				PunishmentType::BAN => $this->ban($player, $reason, $issuerName, $message, $until),
				PunishmentType::BAN_IP => $this->banIp($player, $reason, $issuerName, $message, $until),
				PunishmentType::TEMPBAN => $this->ban($player, $reason, $issuerName, $message, $until ?? (new DateTimeImmutable())->modify('+1 day')),
				PunishmentType::NONE => null,
			};
		} catch (\Throwable $e) {
			$this->logger->error("Failed to apply punishment to {$player->getName()}: " . $e->getMessage());
		}
	}

	private function kick(Player $player, string $message) : void {
		$player->kick($message);
		$this->logger->info("Kicked player: {$player->getName()}");
	}

	private function ban(
		Player $player,
		string $reason,
		string $issuerName,
		string $message,
		?DateTimeImmutable $until = null
	) : void {
		$playerName = $player->getName();
		$banList = $this->server->getNameBans();

		if ($banList->isBanned($playerName)) {
			$this->logger->info("Player {$playerName} is already banned, skipping duplicate ban");
			$player->kick($message);
			return;
		}

		$expiry = $until !== null ? \DateTime::createFromImmutable($until) : null;
		$banList->addBan($playerName, $reason, $expiry, $issuerName);
		$player->kick($message);
		$this->logger->info("Banned player: {$playerName}" . ($expiry !== null ? " until {$expiry->format('Y-m-d H:i:s')}" : ' permanently'));
	}

	private function banIp(
		Player $player,
		string $reason,
		string $issuerName,
		string $message,
		?DateTimeImmutable $until = null
	) : void {
		$playerName = $player->getName();
		$ip = $player->getNetworkSession()->getIp();
		$ipBanList = $this->server->getIPBans();

		if ($ipBanList->isBanned($ip)) {
			$this->logger->info("IP {$ip} is already banned for player {$playerName}, skipping duplicate ban");
			$player->kick($message);
			return;
		}

		$expiry = $until !== null ? \DateTime::createFromImmutable($until) : null;
		$ipBanList->addBan($ip, $reason, $expiry, $issuerName);
		$player->kick($message);
		$this->server->getNetwork()->blockAddress($ip, -1);
		$this->logger->info("IP banned player: {$playerName} (IP: {$ip})" . ($expiry !== null ? " until {$expiry->format('Y-m-d H:i:s')}" : ' permanently'));
	}

	public function getDelaySeconds() : int {
		return $this->delaySeconds;
	}
}
