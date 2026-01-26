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

namespace aiptu\playerwarn\discord;

use aiptu\playerwarn\event\PlayerPunishmentEvent;
use aiptu\playerwarn\task\DiscordWebhookTask;
use aiptu\playerwarn\utils\Utils;
use aiptu\playerwarn\warns\WarnEntry;
use pocketmine\Server;
use pocketmine\utils\InternetRequestResult;
use function is_array;
use function is_string;

class DiscordService {
	/** @var array<string, mixed>|null Cached empty template */
	private ?array $emptyTemplate = null;

	public function __construct(
		private Server $server,
		private \AttachableLogger $logger,
		private string $webhookUrl,
		private array $webhookTemplates
	) {
		$this->emptyTemplate = [];
	}

	public function sendWarningAdded(WarnEntry $warnEntry) : void {
		$template = $this->webhookTemplates['add'] ?? $this->emptyTemplate;
		if (!$template) {
			return;
		}

		$expirationString = self::formatExpiration($warnEntry->getExpiration());

		$payload = $this->replaceTemplateVars($template, [
			'player' => $warnEntry->getPlayerName(),
			'source' => $warnEntry->getSource(),
			'reason' => $warnEntry->getReason(),
			'timestamp' => $warnEntry->getTimestamp()->format(Utils::DATE_TIME_FORMAT),
			'expiration' => $expirationString,
		]);

		$this->sendWebhook($payload);
	}

	public function sendWarningRemoved(WarnEntry $warnEntry) : void {
		$template = $this->webhookTemplates['remove'] ?? $this->emptyTemplate;
		if (!$template) {
			return;
		}

		$payload = $this->replaceTemplateVars($template, [
			'player' => $warnEntry->getPlayerName(),
		]);

		$this->sendWebhook($payload);
	}

	public function sendWarningExpired(WarnEntry $warnEntry) : void {
		$template = $this->webhookTemplates['expire'] ?? $this->emptyTemplate;
		if (!$template) {
			return;
		}

		$payload = $this->replaceTemplateVars($template, [
			'player' => $warnEntry->getPlayerName(),
		]);

		$this->sendWebhook($payload);
	}

	public function sendPunishment(PlayerPunishmentEvent $event) : void {
		$template = $this->webhookTemplates['punishment'] ?? $this->emptyTemplate;
		if (!$template) {
			return;
		}

		$payload = $this->replaceTemplateVars($template, [
			'player' => $event->getPlayer()->getName(),
			'punishmentType' => $event->getPunishmentType(),
			'issuerName' => $event->getIssuerName(),
			'reason' => $event->getReason(),
		]);

		$this->sendWebhook($payload);
	}

	private static function formatExpiration(?\DateTimeImmutable $expiration) : string {
		if ($expiration === null) {
			return 'Never';
		}

		$secondsRemaining = $expiration->getTimestamp() - (new \DateTimeImmutable())->getTimestamp();
		$durationString = Utils::formatDuration($secondsRemaining);
		$dateString = $expiration->format(Utils::DATE_TIME_FORMAT);

		return "{$durationString} ({$dateString})";
	}

	private function replaceTemplateVars(array $template, array $vars) : array {
		$result = [];

		foreach ($template as $key => $value) {
			if (is_string($value)) {
				$result[$key] = Utils::replaceVars($value, $vars);
			} elseif (is_array($value)) {
				$result[$key] = $this->replaceTemplateVars($value, $vars);
			} else {
				$result[$key] = $value;
			}
		}

		return $result;
	}

	private function sendWebhook(array $payload) : void {
		$this->server->getAsyncPool()->submitTask(new DiscordWebhookTask(
			$this->webhookUrl,
			$payload,
			['Content-Type: application/json'],
			function (?InternetRequestResult $result) : void {
				if ($result === null) {
					$this->logger->warning('Discord webhook request failed or returned null');
					return;
				}

				$responseCode = $result->getCode();

				if ($responseCode === 204 || $responseCode === 200) {
					$this->logger->debug('Discord webhook sent successfully');
					return;
				}

				if ($responseCode === 429 || ($responseCode >= 500 && $responseCode < 600)) {
					$this->logger->info("Discord webhook temporary error (code {$responseCode}), will retry");
					return;
				}

				$this->logger->warning("Discord webhook failed with response code: {$responseCode}");
				$this->logger->debug('Response body: ' . $result->getBody());
			}
		));
	}
}
