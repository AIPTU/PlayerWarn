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
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use Symfony\Component\Filesystem\Path;
use function count;
use function file_exists;
use function is_array;
use function is_string;
use function str_replace;
use function strtr;

class MessageManager {
	private const string FALLBACK_LANGUAGE = 'en';

	/** @var array<string, string> */
	private array $messages = [];

	/** @var array<string, string> */
	private array $fallbackMessages = [];

	public function __construct(
		private PluginBase $plugin,
		string $language
	) {
		$this->fallbackMessages = $this->loadFile(self::FALLBACK_LANGUAGE);
		$this->messages = $language === self::FALLBACK_LANGUAGE
			? $this->fallbackMessages
			: $this->loadLanguage($language);
	}

	/**
	 * Returns a formatted message for a given key.
	 * The message can contain placeholders in the format {placeholder}, which will be replaced by the corresponding values from the $params array.
	 *
	 * @param array<string, string> $params
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

		$message = str_replace('{line}', "\n", $message);

		return TextFormat::colorize($message);
	}

	/**
	 * Loads a language file and returns an array of messages.
	 * If the language file is missing or invalid, it falls back to the default language.
	 *
	 * @return array<string, string>
	 */
	private function loadLanguage(string $language) : array {
		$langPath = Path::join($this->plugin->getDataFolder(), 'lang', "{$language}.yml");

		if (!file_exists($langPath)) {
			$this->plugin->getLogger()->warning(
				"Language '{$language}' not found, falling back to '" . self::FALLBACK_LANGUAGE . "'."
			);
			return $this->fallbackMessages;
		}

		$messages = $this->loadFile($language);

		if ($messages === []) {
			$this->plugin->getLogger()->warning(
				"Language file '{$language}.yml' is empty or invalid, falling back to '" . self::FALLBACK_LANGUAGE . "'."
			);
			return $this->fallbackMessages;
		}

		$missingKeys = [];
		foreach ($this->fallbackMessages as $key => $_) {
			if (!isset($messages[$key])) {
				$missingKeys[] = $key;
			}
		}

		if ($missingKeys !== []) {
			$this->plugin->getLogger()->debug(
				"Language '{$language}' is missing " . count($missingKeys) . " key(s), falling back to '" . self::FALLBACK_LANGUAGE . "' for those."
			);
		}

		return $messages;
	}

	/**
	 * Loads a language file and returns an array of messages.
	 * If the file is missing or invalid, it returns an empty array.
	 *
	 * @return array<string, string>
	 */
	private function loadFile(string $language) : array {
		$path = Path::join($this->plugin->getDataFolder(), 'lang', "{$language}.yml");

		if (!file_exists($path)) {
			if ($language === self::FALLBACK_LANGUAGE) {
				$this->plugin->getLogger()->critical(
					"Fallback language file 'en.yml' is missing. The plugin may not function correctly."
				);
			}

			return [];
		}

		$config = new Config($path, Config::YAML);

		return self::flatten($config->getAll());
	}

	/**
	 * Flattens a multidimensional array into a single-level array with dot-separated keys.
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
				foreach (self::flatten($value, $fullKey) as $k => $v) {
					$result[$k] = $v;
				}
			} elseif (is_string($value)) {
				$result[$fullKey] = $value;
			}
		}

		return $result;
	}
}
