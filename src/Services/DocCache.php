<?php

declare(strict_types=1);

namespace BinarCode\RestifyBoost\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class DocCache
{
    protected Repository $cache;

    protected string $keyPrefix;

    protected int $ttl;

    protected bool $enabled;

    public function __construct()
    {
        $this->cache = Cache::store(config('restify-boost.cache.store'));
        $this->keyPrefix = config('restify-boost.cache.key_prefix', 'restify_mcp');
        $this->ttl = config('restify-boost.cache.ttl', 3600);
        $this->enabled = config('restify-boost.cache.enabled', true);
    }

    public function remember(string $key, callable $callback): mixed
    {
        if (! $this->enabled) {
            return $callback();
        }

        $cacheKey = $this->buildKey($key);

        return $this->cache->remember($cacheKey, $this->ttl, $callback);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (! $this->enabled) {
            return $default;
        }

        return $this->cache->get($this->buildKey($key), $default);
    }

    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        if (! $this->enabled) {
            return false;
        }

        return $this->cache->put($this->buildKey($key), $value, $ttl ?? $this->ttl);
    }

    public function forget(string $key): bool
    {
        if (! $this->enabled) {
            return false;
        }

        return $this->cache->forget($this->buildKey($key));
    }

    public function flush(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        // For safety, we'll only flush keys with our prefix
        $keys = $this->cache->get($this->buildKey('_keys'), []);
        foreach ($keys as $key) {
            $this->cache->forget($key);
        }

        return $this->cache->forget($this->buildKey('_keys'));
    }

    protected function buildKey(string $key): string
    {
        $fullKey = $this->keyPrefix.'.'.$key;

        // Track keys for potential cleanup
        if ($this->enabled && $key !== '_keys') {
            $keys = $this->cache->get($this->buildKey('_keys'), []);
            if (! in_array($fullKey, $keys, true)) {
                $keys[] = $fullKey;
                $this->cache->put($this->buildKey('_keys'), $keys, $this->ttl);
            }
        }

        return $fullKey;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }
}
