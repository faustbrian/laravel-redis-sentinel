<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection PhpMissingParamTypeInspection */

declare(strict_types=1);

namespace Tests;

use Cline\RedisSentinel\RedisSentinelServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Redis\RedisServiceProvider;

/**
 * Base package test case with the Sentinel-aware Redis driver configured.
 */
abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * Register the Redis providers required by the package tests.
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            RedisServiceProvider::class,
            RedisSentinelServiceProvider::class,
        ];
    }

    /**
     * Configure the default Redis connection to use the Sentinel driver.
     *
     * @param  Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // We use the phpredis-sentinel redis driver as default.
        $app['config']->set('database.redis.client', 'phpredis-sentinel');

        // Setup Sentinel configuration for the default Redis connection.
        $app['config']->set('database.redis.default', [
            'sentinel_host' => env('REDIS_SENTINEL_HOST', '127.0.0.1'),
            'sentinel_port' => (int) env('REDIS_SENTINEL_PORT', 6379),
            'sentinel_username' => env('REDIS_SENTINEL_USERNAME'),
            'sentinel_password' => env('REDIS_SENTINEL_PASSWORD'),
            'sentinel_service' => env('REDIS_SENTINEL_SERVICE', 'mymaster'),

            'connector_retry_attempts' => 20,
            'connector_retry_delay' => 1000,
        ]);
    }
}
