<?php

declare(strict_types=1);

namespace Cline\RedisSentinel;

use Cline\RedisSentinel\Connectors\PhpRedisSentinelConnector;
use Cline\RedisSentinel\Services\RetryManager;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Redis Sentinel connector with Laravel's Redis manager.
 *
 * The package adds a `phpredis-sentinel` driver that resolves the current
 * Redis master through Sentinel before deferring to Laravel's PhpRedis
 * connection layer. The retry manager is shared so connectors and live
 * connections interpret failover conditions consistently.
 */
class RedisSentinelServiceProvider extends ServiceProvider
{
    /**
     * Register package services in the container.
     */
    public function register(): void
    {
        $this->app->singleton(RetryManager::class, fn () => new RetryManager);
    }

    /**
     * Extend Laravel's Redis manager with the Sentinel-aware driver.
     */
    public function boot(): void
    {
        $this->app->extend('redis', function (RedisManager $service, $app) {
            $retryManager = $app->make(RetryManager::class);

            return $service->extend('phpredis-sentinel', fn () => new PhpRedisSentinelConnector($retryManager));
        });
    }
}
