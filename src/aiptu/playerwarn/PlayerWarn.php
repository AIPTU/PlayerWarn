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

use aiptu\playerwarn\commands\ClearWarnsCommand;
use aiptu\playerwarn\commands\DeleteWarnCommand;
use aiptu\playerwarn\commands\EditWarnCommand;
use aiptu\playerwarn\commands\ListWarnsCommand;
use aiptu\playerwarn\commands\WarnCommand;
use aiptu\playerwarn\commands\WarnsCommand;
use aiptu\playerwarn\discord\DiscordService;
use aiptu\playerwarn\provider\WarnProvider;
use aiptu\playerwarn\punishment\PendingPunishmentManager;
use aiptu\playerwarn\punishment\PunishmentService;
use aiptu\playerwarn\punishment\PunishmentType;
use aiptu\playerwarn\task\ExpiredWarningsTask;
use JackMD\UpdateNotifier\UpdateNotifier;
use pocketmine\plugin\DisablePluginException;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Filesystem as Files;
use pocketmine\utils\TextFormat;
use poggit\libasynql\libasynql;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use function array_keys;
use function file_exists;
use function filter_var;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function json_decode;
use const FILTER_VALIDATE_URL;
use const JSON_THROW_ON_ERROR;

class PlayerWarn extends PluginBase {
	private const float CONFIG_VERSION = 2.0;

	private WarnProvider $warnProvider;
	private PunishmentService $punishmentService;
	private ?DiscordService $discordService = null;
	private PendingPunishmentManager $pendingPunishmentManager;
	private WarningTracker $warningTracker;

	private bool $updateNotifierEnabled;
	private int $expirationCheckInterval;
	private int $warningLimit;
	private PunishmentType $punishmentType;
	private bool $broadcastToEveryone = true;

	public function onEnable() : void {
		foreach (array_keys($this->getResources()) as $resource) {
			$this->saveResource($resource);
		}

		try {
			$this->checkConfig();
		} catch (\Throwable $e) {
			$this->getLogger()->error('An error occurred while checking the configuration: ' . $e->getMessage());
			throw new DisablePluginException();
		}

		try {
			$this->loadConfig();
		} catch (\Throwable $e) {
			$this->getLogger()->error('An error occurred while loading the configuration: ' . $e->getMessage());
			throw new DisablePluginException();
		}

		$dbConfig = $this->getConfig()->get('database');
		$connector = libasynql::create(
			$this,
			$dbConfig,
			['sqlite' => 'sqlite.sql', 'mysql' => 'mysql.sql']
		);

		$this->warnProvider = new WarnProvider($connector, $this->getLogger());

		$this->initializeServices();
		$this->checkMigration();

		$commandMap = $this->getServer()->getCommandMap();
		$commandMap->registerAll('PlayerWarn', [
			new WarnCommand($this),
			new WarnsCommand($this),
			new ClearWarnsCommand($this),
			new DeleteWarnCommand($this),
			new EditWarnCommand($this),
			new ListWarnsCommand($this),
		]);

		$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);

		$this->getScheduler()->scheduleRepeatingTask(new ExpiredWarningsTask($this), $this->expirationCheckInterval * 20);

		if ($this->updateNotifierEnabled) {
			UpdateNotifier::checkUpdate($this->getDescription()->getName(), $this->getDescription()->getVersion());
		}
	}

	public function onDisable() : void {
		if (isset($this->warnProvider)) {
			$this->warnProvider->close();
		}
	}

	/**
	 * Checks if the config is outdated and generates a new one if needed.
	 */
	private function checkConfig() : void {
		$config = $this->getConfig();

		if (
			!$config->exists('config-version')
			|| $config->get('config-version') !== self::CONFIG_VERSION
		) {
			$this->getLogger()->warning('Outdated configuration detected. Generating a new config file...');
			$oldConfigPath = Path::join($this->getDataFolder(), 'config.old.yml');
			$newConfigPath = Path::join($this->getDataFolder(), 'config.yml');

			$filesystem = new Filesystem();
			try {
				$filesystem->rename($newConfigPath, $oldConfigPath);
			} catch (IOException $e) {
				$this->getLogger()->critical('Failed to backup old config: ' . $e->getMessage());
				throw new DisablePluginException();
			}

			$this->reloadConfig();
			$this->getLogger()->info('New configuration generated. Old config backed up to config.old.yml');
		}
	}

	/**
	 * Loads and validates the plugin configuration.
	 */
	private function loadConfig() : void {
		$config = $this->getConfig();

		$updateNotifierEnabled = $config->get('update_notifier');
		if (!is_bool($updateNotifierEnabled)) {
			throw new \InvalidArgumentException('Invalid "update_notifier" value. Expected boolean.');
		}

		$this->updateNotifierEnabled = $updateNotifierEnabled;

		$expirationCheckInterval = $config->getNested('warning.expiration-check-interval');
		if (!is_int($expirationCheckInterval) || $expirationCheckInterval <= 0) {
			throw new \InvalidArgumentException('Invalid "warning.expiration-check-interval" value. Expected positive integer.');
		}

		$this->expirationCheckInterval = $expirationCheckInterval;

		$warningLimit = $config->getNested('warning.limit');
		if (!is_int($warningLimit) || $warningLimit <= 0) {
			throw new \InvalidArgumentException('Invalid "warning.limit" value. Expected positive integer.');
		}

		$this->warningLimit = $warningLimit;

		$punishmentTypeStr = $config->getNested('punishment.type', 'none');
		if (!is_string($punishmentTypeStr)) {
			throw new \InvalidArgumentException('Invalid "punishment.type" value. Expected string.');
		}

		$punishmentType = PunishmentType::fromString($punishmentTypeStr);
		if ($punishmentType === null) {
			throw new \InvalidArgumentException(
				'Invalid "punishment.type" value. Valid options: none, kick, ban, ban-ip, tempban'
			);
		}

		$this->punishmentType = $punishmentType;

		$broadcastToEveryone = $config->getNested('warning.broadcast_to_everyone', true);
		if (!is_bool($broadcastToEveryone)) {
			throw new \InvalidArgumentException('Invalid "warning.broadcast_to_everyone" value. Expected boolean.');
		}

		$this->broadcastToEveryone = $broadcastToEveryone;
	}

	/**
	 * Initializes various services used by the plugin.
	 */
	private function initializeServices() : void {
		$config = $this->getConfig();

		$delaySeconds = $config->getNested('warning.delay', 5);
		if (!is_int($delaySeconds) || $delaySeconds < 0) {
			$delaySeconds = 5;
		}

		$warningMessage = $config->getNested('warning.message', '&cYou have reached the warning limit.');
		if (!is_string($warningMessage)) {
			$warningMessage = '&cYou have reached the warning limit.';
		}

		$punishmentMessages = [];
		$messagesConfig = $config->getNested('punishment.messages', []);
		if (is_array($messagesConfig)) {
			foreach (['kick', 'ban', 'ban-ip', 'tempban'] as $type) {
				if (isset($messagesConfig[$type]) && is_string($messagesConfig[$type])) {
					$punishmentMessages[$type] = TextFormat::colorize($messagesConfig[$type]);
				}
			}
		}

		$this->punishmentService = new PunishmentService(
			$this,
			$this->getServer(),
			$this->getLogger(),
			$delaySeconds,
			TextFormat::colorize($warningMessage),
			$punishmentMessages
		);

		$discordEnabled = $config->getNested('discord.enabled', false);
		if (is_bool($discordEnabled) && $discordEnabled) {
			$webhookUrl = $config->getNested('discord.webhook_url');

			if (is_string($webhookUrl) && filter_var($webhookUrl, FILTER_VALIDATE_URL) !== false) {
				$webhookTemplates = $this->loadWebhookTemplates();
				$this->discordService = new DiscordService(
					$this->getServer(),
					$this->getLogger(),
					$webhookUrl,
					$webhookTemplates
				);
			} else {
				$this->getLogger()->warning('Discord enabled but webhook URL is invalid. Disabling Discord integration.');
			}
		}

		$this->pendingPunishmentManager = new PendingPunishmentManager();
		$this->warningTracker = new WarningTracker();
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function loadWebhookTemplates() : array {
		/** @var array<string, array<string, mixed>> $templates */
		$templates = [];
		$eventTypes = ['add', 'remove', 'edit', 'expire', 'punishment'];

		foreach ($eventTypes as $eventType) {
			$jsonFile = Path::join($this->getDataFolder(), 'webhooks', "{$eventType}_event.json");

			try {
				$jsonData = Files::fileGetContents($jsonFile);
				$decoded = json_decode($jsonData, true, flags: JSON_THROW_ON_ERROR);

				if (!is_array($decoded)) {
					$this->getLogger()->warning("Webhook template '{$eventType}' is not an array. Using empty template.");
					$templates[$eventType] = [];
					continue;
				}

				$templates[$eventType] = $decoded;
			} catch (\Throwable $e) {
				$this->getLogger()->warning("Failed to load webhook template '{$eventType}': " . $e->getMessage());
				$templates[$eventType] = [];
			}
		}

		return $templates;
	}

	/**
	 * Checks if the warnings.json file exists and migrates the data to the database if it does.
	 */
	private function checkMigration() : void {
		$oldWarningsFile = Path::join($this->getDataFolder(), 'warnings.json');
		if (!file_exists($oldWarningsFile)) {
			return;
		}

		$migrationService = new MigrationService($this->warnProvider, $this->getLogger());
		$migrationService->migrateFromJson($oldWarningsFile);
	}

	public function getProvider() : WarnProvider {
		return $this->warnProvider;
	}

	public function getPunishmentService() : PunishmentService {
		return $this->punishmentService;
	}

	public function getDiscordService() : ?DiscordService {
		return $this->discordService;
	}

	public function getPendingPunishmentManager() : PendingPunishmentManager {
		return $this->pendingPunishmentManager;
	}

	public function getWarningTracker() : WarningTracker {
		return $this->warningTracker;
	}

	public function getWarningLimit() : int {
		return $this->warningLimit;
	}

	public function getPunishmentType() : PunishmentType {
		return $this->punishmentType;
	}

	public function isDiscordEnabled() : bool {
		return $this->discordService !== null;
	}

	public function isBroadcastToEveryoneEnabled() : bool {
		return $this->broadcastToEveryone;
	}
}
