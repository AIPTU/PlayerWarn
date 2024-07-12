<?php

/*
 * Copyright (c) 2023-2024 AIPTU
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/AIPTU/PlayerWarn
 */

declare(strict_types=1);

namespace aiptu\playerwarn\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\utils\Internet;
use pocketmine\utils\InternetRequestResult;
use pocketmine\utils\Utils;
use function igbinary_serialize;
use function igbinary_unserialize;
use function is_array;
use function is_callable;
use function json_encode;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

class DiscordWebhookTask extends AsyncTask {
	protected string $args;
	protected string $headers;

	public function __construct(
		protected string $page,
		array|string $args,
		array $headers,
		?\Closure $closure = null
	) {
		$this->args = is_array($args) ? json_encode($args, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) : $args;

		$serializedHeaders = igbinary_serialize($headers);
		if ($serializedHeaders === null) {
			throw new \InvalidArgumentException('Headers cannot be serialized');
		}

		$this->headers = $serializedHeaders;

		if ($closure !== null) {
			Utils::validateCallableSignature(static function (?InternetRequestResult $result) : void {}, $closure);
			$this->storeLocal('closure', $closure);
		}
	}

	public function onRun() : void {
		$extraHeaders = igbinary_unserialize($this->headers);
		if (!is_array($extraHeaders)) {
			throw new \InvalidArgumentException('Failed to unserialize headers');
		}

		$this->setResult(Internet::postURL(
			page: $this->page,
			args: $this->args,
			extraHeaders: $extraHeaders
		));
	}

	public function onCompletion() : void {
		$closure = $this->fetchLocal('closure');
		if ($closure !== null && is_callable($closure)) {
			$closure($this->getResult());
		}
	}
}
