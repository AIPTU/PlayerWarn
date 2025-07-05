<?php

/*
 * Copyright (c) 2023-2025 AIPTU
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
use aiptu\playerwarn\event\PlayerPunishmentEvent;
use aiptu\playerwarn\task\DelayedPunishmentTask;
use aiptu\playerwarn\task\DiscordWebhookTask;
use aiptu\playerwarn\task\ExpiredWarningsTask;
use aiptu\playerwarn\utils\Utils;
use aiptu\playerwarn\warns\WarnEntry;
use aiptu\playerwarn\warns\WarnList;
use aiptu\playerwarn\libs\_472e996530913792\JackMD\UpdateNotifier\UpdateNotifier;
use pocketmine\player\Player;
use pocketmine\plugin\DisablePluginException;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Filesystem as Files;
use pocketmine\utils\InternetRequestResult;
use pocketmine\utils\TextFormat;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use function array_keys;
use function array_merge;
use function filter_var;
use function in_array;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function json_decode;
use function trim;
use const FILTER_VALIDATE_URL;
use const JSON_THROW_ON_ERROR;

class PlayerWarn extends PluginBase {
	private const CONFIG_VERSION = 1.0;

	private WarnList $warnList;
	private bool $updateNotifierEnabled;
	private int $warningLimit;
	private int $punishmentDelay;
	private string $warningMessage;
	private string $punishmentType;
	private array $punishmentMessages = [];
	private bool $discordEnabled;
	private string $webhookUrl;
	private array $pendingPunishments = [];
	private array $lastWarningCounts = [];
	private array $webhookData = [];

	public function onEnable() : void {
		foreach (array_keys($this->getResources()) as $resource) {
			$this->saveResource($resource);
		}

		try {
			$this->warnList = new WarnList(Path::join($this->getDataFolder(), 'warnings.json'));
		} catch (\Throwable $e) {
			$this->getLogger()->error('An error occurred while loading the warn list: ' . $e->getMessage());
			throw new DisablePluginException();
		}

		try {
			$this->loadConfig();
		} catch (\Throwable $e) {
			$this->getLogger()->error('An error occurred while loading the configuration: ' . $e->getMessage());
			throw new DisablePluginException();
		}

		$eventTypes = ['add', 'remove', 'expire', 'punishment'];
		try {
			foreach ($eventTypes as $eventType) {
				$jsonFile = Path::join($this->getDataFolder(), 'webhooks', "{$eventType}_event.json");
				$this->webhookData[$eventType] = $this->loadWebhookData($jsonFile, $eventType);
			}
		} catch (\InvalidArgumentException $e) {
			$this->getLogger()->error('Failed to load webhook data: ' . $e->getMessage());
			throw new DisablePluginException();
		}

		$commandMap = $this->getServer()->getCommandMap();
		$commandMap->registerAll('PlayerWarn', [
			new WarnCommand($this),
			new WarnsCommand($this),
			new ClearWarnsCommand($this),
		]);

		$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);

		$this->getScheduler()->scheduleRepeatingTask(new ExpiredWarningsTask($this), 20);

		if ($this->updateNotifierEnabled) {
			UpdateNotifier::checkUpdate($this->getDescription()->getName(), $this->getDescription()->getVersion());
		}
	}

	/**
	 * Loads and validates the plugin configuration from the `config.yml` file.
	 * If the configuration is invalid, an exception will be thrown.
	 *
	 * @throws \InvalidArgumentException when the configuration is invalid
	 */
	private function loadConfig() : void {
		$this->checkConfig();

		$config = $this->getConfig();

		$updateNotifierEnabled = $config->get('update_notifier');
		if (!is_bool($updateNotifierEnabled)) {
			throw new \InvalidArgumentException('Invalid or missing "update_notifier" value in the configuration. Please provide a boolean (true/false) value.');
		}

		$this->updateNotifierEnabled = $updateNotifierEnabled;

		$warningLimit = $config->getNested('warning.limit');
		if (!is_int($warningLimit) || $warningLimit <= 0) {
			throw new \InvalidArgumentException('Invalid or missing "warning.limit" value in the configuration. Please provide a positive integer value.');
		}

		$this->warningLimit = $warningLimit;

		$punishmentDelay = $config->getNested('warning.delay');
		if (!is_int($punishmentDelay) || $punishmentDelay < 0) {
			throw new \InvalidArgumentException('Invalid or missing "warning.delay" value in the configuration. Please provide a positive integer value.');
		}

		$this->punishmentDelay = $punishmentDelay;

		$warningMessage = $config->getNested('warning.message');
		if (!is_string($warningMessage) || trim($warningMessage) === '') {
			throw new \InvalidArgumentException('Invalid or missing "warning.message" in the configuration. Please provide a non-empty string value.');
		}

		$this->warningMessage = TextFormat::colorize($warningMessage);

		$punishmentType = $config->getNested('punishment.type', 'none');
		if (!in_array($punishmentType, ['none', 'kick', 'ban', 'ban-ip'], true)) {
			throw new \InvalidArgumentException('Invalid "punishment.type" value in the configuration. Valid options are "none", "kick", "ban", and "ban-ip".');
		}

		$this->punishmentType = $punishmentType;

		if ($this->punishmentType !== 'none') {
			$messagesConfig = $config->getNested('punishment.messages', []);
			if (!is_array($messagesConfig)) {
				throw new \InvalidArgumentException('Invalid "punishment.messages" configuration. Please make sure it is properly defined as an array.');
			}

			foreach (['kick', 'ban', 'ban-ip'] as $type) {
				$message = $messagesConfig[$type] ?? '';
				if (!is_string($message) || trim($message) === '') {
					throw new \InvalidArgumentException("Invalid or missing punishment message for '{$type}' in the configuration. Please provide a non-empty string containing the custom message.");
				}

				$this->punishmentMessages[$type] = TextFormat::colorize($message);
			}
		}

		$discordEnabled = $config->getNested('discord.enabled');
		if (!is_bool($discordEnabled)) {
			throw new \InvalidArgumentException('Invalid or missing "discord.enabled" value in the configuration. Please provide a boolean (true/false) value.');
		}

		$this->discordEnabled = $discordEnabled;

		if ($this->discordEnabled) {
			$webhookUrl = $config->getNested('discord.webhook_url');
			if (!is_string($webhookUrl) || trim($webhookUrl) === '') {
				throw new \InvalidArgumentException('Invalid or missing "discord.webhook_url" value in the configuration. Please provide a non-empty string containing the Discord webhook URL.');
			}

			if (filter_var($webhookUrl, FILTER_VALIDATE_URL) === false) {
				throw new \InvalidArgumentException('Invalid URL for "discord.webhook_url" in the configuration. The provided value must be a valid URL.');
			}

			$this->webhookUrl = $webhookUrl;
		}
	}

	/**
	 * Checks and manages the configuration for the plugin.
	 * Generates a new configuration if an outdated one is provided and backs up the old config.
	 */
	private function checkConfig() : void {
		$config = $this->getConfig();

		if (!$config->exists('config-version') || $config->get('config-version', self::CONFIG_VERSION) !== self::CONFIG_VERSION) {
			$this->getLogger()->warning('An outdated config was provided; attempting to generate a new one...');

			$oldConfigPath = Path::join($this->getDataFolder(), 'config.old.yml');
			$newConfigPath = Path::join($this->getDataFolder(), 'config.yml');

			$filesystem = new Filesystem();
			try {
				$filesystem->rename($newConfigPath, $oldConfigPath);
			} catch (IOException $e) {
				$this->getLogger()->critical('An error occurred while attempting to generate the new config: ' . $e->getMessage());
				throw new DisablePluginException();
			}

			$this->reloadConfig();
		}
	}

	/**
	 * Loads webhook data from the given JSON file for the specified event type.
	 *
	 * @throws \InvalidArgumentException If unable to read or parse JSON data for the specified event type
	 */
	private function loadWebhookData(string $jsonFile, string $eventType) : array {
		try {
			$jsonData = Files::fileGetContents($jsonFile);
		} catch (\RuntimeException $e) {
			throw new \InvalidArgumentException($e->getMessage());
		}

		try {
			$decodedData = json_decode($jsonData, true, flags: JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			throw new \InvalidArgumentException("Unable to parse JSON data from file '{$jsonFile}' for '{$eventType}' event. Reason: " . $e->getMessage());
		}

		if (!is_array($decodedData)) {
			throw new \InvalidArgumentException("Decoded data from file '{$jsonFile}' for '{$eventType}' event is not an array.");
		}

		return $decodedData;
	}

	/**
	 * Get the WarnList object containing player warnings.
	 */
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
	 * Check if Discord integration is enabled.
	 */
	public function isDiscordEnabled() : bool {
		return $this->discordEnabled;
	}

	/**
	 * Schedules a delayed punishment for the player and sends a warning message.
	 */
	public function scheduleDelayedPunishment(Player $player, string $punishmentType, string $issuerName, string $reason) : void {
		$delay = $this->punishmentDelay;

		if ($delay < 0) {
			$this->applyPunishment($player, $punishmentType, $issuerName, $reason);
			return;
		}

		$this->getScheduler()->scheduleDelayedTask(new DelayedPunishmentTask($this, $player, $punishmentType, $issuerName, $reason), $delay * 20);

		$warningMessage = Utils::replaceVars($this->warningMessage, ['delay' => $delay]);
		$player->sendMessage($warningMessage);
	}

	/**
	 * Applies a punishment to the player based on the punishment type.
	 */
	public function applyPunishment(Player $player, string $punishmentType, string $issuerName, string $reason) : void {
		$server = $player->getServer();
		$playerName = $player->getName();

		$event = new PlayerPunishmentEvent($player, $punishmentType, $issuerName, $reason);
		$event->call();

		if ($event->isCancelled()) {
			return;
		}

		$customPunishmentMessage = $this->punishmentMessages[$punishmentType];

		switch ($punishmentType) {
			case 'kick':
				$player->kick($customPunishmentMessage);
				break;
			case 'ban':
				$banList = $server->getNameBans();
				if (!$banList->isBanned($playerName)) {
					$banList->addBan($playerName, $reason, null, $issuerName);
				}

				$player->kick($customPunishmentMessage);
				break;
			case 'ban-ip':
				$ip = $player->getNetworkSession()->getIp();
				$ipBanList = $server->getIPBans();
				if (!$ipBanList->isBanned($ip)) {
					$ipBanList->addBan($ip, $reason, null, $issuerName);
				}

				$player->kick($customPunishmentMessage);
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

	/**
	 * Sends a webhook request with the provided payload.
	 */
	public function sendWebhookRequest(array $payload) : void {
		$webhookUrl = $this->webhookUrl;

		$this->getServer()->getAsyncPool()->submitTask(new DiscordWebhookTask(
			$webhookUrl,
			$payload,
			['Content-Type: application/json'],
			function (?InternetRequestResult $result) : void {
				if ($result === null) {
					$this->getLogger()->warning('DiscordWebhookTask failed or returned null');
					return;
				}

				$responseCode = $result->getCode();
				if ($responseCode !== 204) {
					$this->getLogger()->warning("DiscordWebhookTask failed with response code: {$responseCode}");
					return;
				}

				$this->getLogger()->info('DiscordWebhookTask completed successfully');
			}
		));
	}

	/**
	 * Sends a webhook request when adding a warning.
	 */
	public function sendAddRequest(WarnEntry $warnEntry) : void {
		$defaultEventData = $this->webhookData['add'];

		$playerName = $warnEntry->getPlayerName();
		$timestamp = $warnEntry->getTimestamp()->format(WarnEntry::DATE_TIME_FORMAT);
		$reason = $warnEntry->getReason();
		$source = $warnEntry->getSource();
		$expiration = $warnEntry->getExpiration();
		$expirationString = $expiration !== null ? Utils::formatDuration($expiration->getTimestamp() - (new \DateTimeImmutable())->getTimestamp()) . " ({$expiration->format(WarnEntry::DATE_TIME_FORMAT)})" : 'Never';

		$eventData = [];

		if (isset($defaultEventData['content']) && is_string($defaultEventData['content'])) {
			$eventData['content'] = Utils::replaceVars($defaultEventData['content'], [
				'player' => $playerName,
				'source' => $source,
				'reason' => $reason,
				'timestamp' => $timestamp,
				'expiration' => $expirationString,
			]);
		}

		if (isset($defaultEventData['embeds']) && is_array($defaultEventData['embeds'])) {
			$embeds = $defaultEventData['embeds'];

			foreach ($embeds as &$embed) {
				if (isset($embed['description']) && is_string($embed['description'])) {
					$embed['description'] = Utils::replaceVars($embed['description'], [
						'player' => $playerName,
						'source' => $source,
						'reason' => $reason,
						'timestamp' => $timestamp,
						'expiration' => $expirationString,
					]);
				}

				if (isset($embed['fields']) && is_array($embed['fields'])) {
					foreach ($embed['fields'] as &$field) {
						$field['value'] = Utils::replaceVars($field['value'], [
							'player' => $playerName,
							'source' => $source,
							'reason' => $reason,
							'timestamp' => $timestamp,
							'expiration' => $expirationString,
						]);
					}
				}
			}

			$eventData['embeds'] = $embeds;
		}

		$mergedEventData = array_merge($defaultEventData, $eventData);

		$this->sendWebhookRequest($mergedEventData);
	}

	/**
	 * Sends a webhook request when clearing the warning.
	 */
	public function sendRemoveRequest(WarnEntry $warnEntry) : void {
		$defaultEventData = $this->webhookData['remove'];

		$playerName = $warnEntry->getPlayerName();

		$eventData = [];

		if (isset($defaultEventData['content']) && is_string($defaultEventData['content'])) {
			$eventData['content'] = Utils::replaceVars($defaultEventData['content'], [
				'player' => $playerName,
			]);
		}

		if (isset($defaultEventData['embeds']) && is_array($defaultEventData['embeds'])) {
			$embeds = $defaultEventData['embeds'];

			foreach ($embeds as &$embed) {
				if (isset($embed['description']) && is_string($embed['description'])) {
					$embed['description'] = Utils::replaceVars($embed['description'], [
						'player' => $playerName,
					]);
				}

				if (isset($embed['fields']) && is_array($embed['fields'])) {
					foreach ($embed['fields'] as &$field) {
						$field['value'] = Utils::replaceVars($field['value'], [
							'player' => $playerName,
						]);
					}
				}
			}

			$eventData['embeds'] = $embeds;
		}

		$mergedEventData = array_merge($defaultEventData, $eventData);

		$this->sendWebhookRequest($mergedEventData);
	}

	/**
	 * Sends a webhook request when the warning expires.
	 */
	public function sendExpiredRequest(WarnEntry $warnEntry) : void {
		$defaultEventData = $this->webhookData['expire'];

		$playerName = $warnEntry->getPlayerName();

		$eventData = [];

		if (isset($defaultEventData['content']) && is_string($defaultEventData['content'])) {
			$eventData['content'] = Utils::replaceVars($defaultEventData['content'], [
				'player' => $playerName,
			]);
		}

		if (isset($defaultEventData['embeds']) && is_array($defaultEventData['embeds'])) {
			$embeds = $defaultEventData['embeds'];

			foreach ($embeds as &$embed) {
				if (isset($embed['description']) && is_string($embed['description'])) {
					$embed['description'] = Utils::replaceVars($embed['description'], [
						'player' => $playerName,
					]);
				}

				if (isset($embed['fields']) && is_array($embed['fields'])) {
					foreach ($embed['fields'] as &$field) {
						$field['value'] = Utils::replaceVars($field['value'], [
							'player' => $playerName,
						]);
					}
				}
			}

			$eventData['embeds'] = $embeds;
		}

		$mergedEventData = array_merge($defaultEventData, $eventData);

		$this->sendWebhookRequest($mergedEventData);
	}

	/**
	 * Sends a webhook request for the PlayerPunishmentEvent.
	 */
	public function sendPunishmentRequest(PlayerPunishmentEvent $event) : void {
		$defaultEventData = $this->webhookData['punishment'];

		$player = $event->getPlayer();
		$playerName = $player->getName();
		$punishmentType = $event->getPunishmentType();
		$issuerName = $event->getIssuerName();
		$reason = $event->getReason();

		$eventData = [];

		if (isset($defaultEventData['content']) && is_string($defaultEventData['content'])) {
			$eventData['content'] = Utils::replaceVars($defaultEventData['content'], [
				'player' => $playerName,
				'punishmentType' => $punishmentType,
				'issuerName' => $issuerName,
				'reason' => $reason,
			]);
		}

		if (isset($defaultEventData['embeds']) && is_array($defaultEventData['embeds'])) {
			$embeds = $defaultEventData['embeds'];

			foreach ($embeds as &$embed) {
				if (isset($embed['description']) && is_string($embed['description'])) {
					$embed['description'] = Utils::replaceVars($embed['description'], [
						'player' => $playerName,
						'punishmentType' => $punishmentType,
						'issuerName' => $issuerName,
						'reason' => $reason,
					]);
				}

				if (isset($embed['fields']) && is_array($embed['fields'])) {
					foreach ($embed['fields'] as &$field) {
						$field['value'] = Utils::replaceVars($field['value'], [
							'player' => $playerName,
							'punishmentType' => $punishmentType,
							'issuerName' => $issuerName,
							'reason' => $reason,
						]);
					}
				}
			}

			$eventData['embeds'] = $embeds;
		}

		$mergedEventData = array_merge($defaultEventData, $eventData);

		$this->sendWebhookRequest($mergedEventData);
	}
}