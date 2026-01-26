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

namespace aiptu\playerwarn\cache;

use function array_keys;
use function count;
use function microtime;
use function preg_match;
use const PHP_FLOAT_MAX;

class QueryCache {
	/** @var array<string, array{data: mixed, expiry: float}> */
	private array $cache = [];

	/**
	 * Store a value in the cache with optional TTL.
	 *
	 * @param string $key   cache key
	 * @param mixed  $value value to cache
	 * @param float  $ttl   time to live in seconds (0 = no expiry)
	 */
	public function set(string $key, mixed $value, float $ttl = 300.0) : void {
		$expiry = $ttl > 0 ? microtime(true) + $ttl : PHP_FLOAT_MAX;
		$this->cache[$key] = ['data' => $value, 'expiry' => $expiry];
	}

	/**
	 * Retrieve a value from the cache.
	 *
	 * @return mixed|null the cached value, or null if not found or expired
	 */
	public function get(string $key) : mixed {
		if (!isset($this->cache[$key])) {
			return null;
		}

		$entry = $this->cache[$key];
		if (microtime(true) > $entry['expiry']) {
			unset($this->cache[$key]);
			return null;
		}

		return $entry['data'];
	}

	/**
	 * Check if a key exists and is not expired.
	 */
	public function has(string $key) : bool {
		return $this->get($key) !== null;
	}

	/**
	 * Invalidate a specific cache entry.
	 */
	public function invalidate(string $key) : void {
		unset($this->cache[$key]);
	}

	/**
	 * Invalidate all cache entries matching a pattern.
	 *
	 * @param string $pattern regex pattern to match keys
	 */
	public function invalidatePattern(string $pattern) : void {
		foreach (array_keys($this->cache) as $key) {
			if (@preg_match($pattern, $key) === 1) {
				unset($this->cache[$key]);
			}
		}
	}

	/**
	 * Clear all cache entries.
	 */
	public function clear() : void {
		$this->cache = [];
	}

	/**
	 * Get cache statistics for debugging.
	 *
	 * @return array{total: int, expired: int}
	 */
	public function getStats() : array {
		$now = microtime(true);
		$expired = 0;
		foreach ($this->cache as $entry) {
			if ($now > $entry['expiry']) {
				++$expired;
			}
		}

		return [
			'total' => count($this->cache),
			'expired' => $expired,
		];
	}
}
