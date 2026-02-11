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

use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use Symfony\Component\Filesystem\Path;
use function array_merge;
use function is_array;
use function is_string;
use function strtr;

class MessageManager {
	/** @var array<string, string> */
	private array $messages = [];

	/** @var array<string, string> */
	private array $fallbackMessages = [];

	public function __construct(
		private PluginBase $plugin,
		string $language
	) {
		$this->loadFallback();
		$this->loadLanguage($language);
	}

	/**
	 * Get a message by dot-notation key with optional placeholder replacement.
	 *
	 * @param array<string, string> $params placeholders to replace in the message
	 */
	public function get(string $key, array $params = []) : string {
		$message = $this->messages[$key] ?? $this->fallbackMessages[$key] ?? $key;

		if ($params !== []) {
			$replacements = [];
			foreach ($params as $k => $v) {
				$replacements['{' . $k . '}'] = $v;
			}

			$message = strtr($message, $replacements);
		}

		return TextFormat::colorize($message);
	}

	/**
	 * Load the fallback (English) language file from plugin resources.
	 */
	private function loadFallback() : void {
		$fallbackFile = Path::join($this->plugin->getDataFolder(), 'lang', 'en.yml');
		$this->fallbackMessages = self::parseFile($fallbackFile);
	}

	/**
	 * Load the selected language file.
	 */
	private function loadLanguage(string $language) : void {
		if ($language === 'en') {
			$this->messages = $this->fallbackMessages;
			return;
		}

		$langFile = Path::join($this->plugin->getDataFolder(), 'lang', "{$language}.yml");
		$this->messages = self::parseFile($langFile);
	}

	/**
	 * Parse a YAML file into a flat dot-notation array of messages.
	 *
	 * @return array<string, string>
	 */
	private static function parseFile(string $filePath) : array {
		$config = new \pocketmine\utils\Config($filePath, \pocketmine\utils\Config::YAML);
		$data = $config->getAll();

		return self::flatten($data);
	}

	/**
	 * Flatten a nested array into dot-notation keys.
	 *
	 * @param array<mixed, mixed> $array
	 *
	 * @return array<string, string>
	 */
	private static function flatten(array $array, string $prefix = '') : array {
		$result = [];

		foreach ($array as $key => $value) {
			$fullKey = $prefix !== '' ? "{$prefix}.{$key}" : (string) $key;

			if (is_array($value)) {
				$result = array_merge($result, self::flatten($value, $fullKey));
			} elseif (is_string($value)) {
				$result[$fullKey] = $value;
			}
		}

		return $result;
	}
}
