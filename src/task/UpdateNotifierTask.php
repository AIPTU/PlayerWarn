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
use function is_array;
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
			err: $err
		);
		if ($result !== null) {
			$versions = json_decode($result->getBody(), true, flags: JSON_THROW_ON_ERROR);
			if (is_array($versions)) {
				$this->setResult(['versions' => $versions, 'error' => $err]);
			}
		}
	}

	public function onCompletion() : void {
		$currentApiVersion = Server::getInstance()->getApiVersion();
		$logger = Server::getInstance()->getLogger();

		/** @var array{versions: array, error: string|null} $results */
		$results = $this->getResult();

		$error = $results['error'];
		if ($error !== null) {
			$logger->error('Update notify error: ' . $error);
			return;
		}

		/**
		 * @var array{
		 *      version: string,
		 *      api: array{
		 *          from: string,
		 *          to: string
		 *      }[],
		 *      artifact_url: string
		 *  }[] $versions
		 */
		$versions = $results['versions'];
		foreach ($versions as $version) {
			if (
				version_compare($this->version, $version['version'], '>=')
				|| !ApiVersion::isCompatible($currentApiVersion, $version['api'][0])
			) {
				continue;
			}

			if ($this->version !== $version['version']) {
				$downloadUrl = $version['artifact_url'] . '/' . $this->name . '.phar';
				$message = "{$this->name} v{$version['version']} is available for download at {$downloadUrl}";
				$logger->notice($message);
				return;
			}
		}

		$logger->info("No compatible update found for {$this->name} (Current version: {$this->version}).");
	}
}
