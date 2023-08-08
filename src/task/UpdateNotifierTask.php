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

namespace aiptu\playerwarn\task;

use pocketmine\plugin\ApiVersion;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Internet;
use function assert;
use function is_array;
use function is_string;
use function json_decode;
use function version_compare;
use const JSON_THROW_ON_ERROR;

class UpdateNotifierTask extends AsyncTask {
	public function __construct(
		private string $name,
		private string $version
	) {}

	public function onRun() : void {
		$result = Internet::getURL(
			page: 'https://poggit.pmmp.io/releases.min.json?name=' . $this->name,
		);
		$this->setResult($result?->getBody());
	}

	public function onCompletion() : void {
		$logger = Server::getInstance()->getLogger();
		$body = $this->getResult();
		assert(is_string($body));

		$versions = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
		if (!is_array($versions)) {
			$logger->warning('Failed to decode JSON data for updates.');
			return;
		}

		$currentApiVersion = Server::getInstance()->getApiVersion();

		foreach ($versions as $version) {
			if (version_compare($this->version, $version['version']) === -1
				&& ApiVersion::isCompatible($currentApiVersion, $version['api'][0])) {
				$downloadUrl = $version['artifact_url'] . '/' . $this->name . '.phar';
				$message = "{$this->name} v{$version['version']} is available for download at {$downloadUrl}";
				$logger->notice($message);
				break;
			}
		}
	}
}
