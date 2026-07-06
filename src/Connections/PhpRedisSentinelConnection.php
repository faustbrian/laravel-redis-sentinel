<?php

/* @noinspection PhpRedundantCatchClauseInspection */

declare(strict_types=1);

namespace Cline\RedisSentinel\Connections;

use Cline\RedisSentinel\Exceptions\RetryRedisException;
use Cline\RedisSentinel\Services\RetryContext;
use Closure;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Redis;
use RedisException;
use Throwable;

/**
 * PhpRedis connection wrapper that retries commands across Sentinel failovers.
 *
 * Once the connector has resolved the active master, this connection layer
 * watches for transport and failover-related exceptions. When those occur, it
 * forces a reconnect through Sentinel and retries the command using the shared
 * retry policy.
 */
class PhpRedisSentinelConnection extends PhpRedisConnection
{
    /**
     * @param  Redis  $client  Connected PhpRedis client instance
     * @param  callable|null  $connector  Closure that can resolve a fresh master
     * @param  array<string, mixed>  $config  Original Laravel Redis configuration
     * @param  RetryContext  $retryContext  Retry rules for reconnectable failures
     */
    public function __construct(
        $client,
        ?callable $connector,
        array $config,
        protected RetryContext $retryContext,
    ) {
        parent::__construct($client, $connector, $config);
    }

    /**
     * Retry `SCAN` across transient failover errors.
     *
     * @throws RetryRedisException
     */
    public function scan($cursor, $options = []): mixed
    {
        return $this->retryOnFailure(fn () => parent::scan($cursor, $options));
    }

    /**
     * Retry `ZSCAN` across transient failover errors.
     *
     * @throws RetryRedisException
     */
    public function zscan($key, $cursor, $options = []): mixed
    {
        return $this->retryOnFailure(fn () => parent::zscan($key, $cursor, $options));
    }

    /**
     * Retry `HSCAN` across transient failover errors.
     *
     * @throws RetryRedisException
     */
    public function hscan($key, $cursor, $options = []): mixed
    {
        return $this->retryOnFailure(fn () => parent::hscan($key, $cursor, $options));
    }

    /**
     * Retry `SSCAN` across transient failover errors.
     *
     * @throws RetryRedisException
     */
    public function sscan($key, $cursor, $options = []): mixed
    {
        return $this->retryOnFailure(fn () => parent::sscan($key, $cursor, $options));
    }

    /**
     * Retry pipeline execution, reconnecting through Sentinel when required.
     *
     * @throws RetryRedisException
     */
    public function pipeline(
        ?callable $callback = null,
        ?int $retryAttempts = null,
    ): Redis|array {
        return $this->retryOnFailure(
            fn () => parent::pipeline($callback),
            $retryAttempts,
        );
    }

    /**
     * Retry transaction execution, reconnecting through Sentinel when required.
     *
     * @throws RetryRedisException
     */
    public function transaction(
        ?callable $callback = null,
        ?int $retryAttempts = null,
    ): Redis|array {
        return $this->retryOnFailure(
            fn () => parent::transaction($callback),
            $retryAttempts,
        );
    }

    /**
     * Retry `EVALSHA` across transient failover errors.
     *
     * @throws RetryRedisException
     */
    public function evalsha($script, $numkeys, ...$arguments): mixed
    {
        return $this->retryOnFailure(fn () => parent::evalsha($script, $numkeys, ...$arguments));
    }

    /**
     * Retry `SUBSCRIBE` setup across transient failover errors.
     *
     * @throws RetryRedisException
     */
    public function subscribe($channels, Closure $callback): void
    {
        $this->retryOnFailure(fn () => parent::subscribe($channels, $callback));
    }

    /**
     * Retry `PSUBSCRIBE` setup across transient failover errors.
     *
     * @throws RetryRedisException
     */
    public function psubscribe($channels, Closure $callback): void
    {
        $this->retryOnFailure(fn () => parent::psubscribe($channels, $callback));
    }

    /**
     * Retry `FLUSHDB` across transient failover errors.
     *
     * @throws RetryRedisException
     */
    public function flushdb(): void
    {
        $this->retryOnFailure(fn () => parent::flushdb());
    }

    /**
     * Retry command dispatch across transient failover errors.
     *
     * @throws RetryRedisException
     */
    public function command($method, array $parameters = []): mixed
    {
        return $this->retryOnFailure(fn () => parent::command($method, $parameters));
    }

    /**
     * Retry dynamic Redis method calls across transient failover errors.
     *
     * @throws RetryRedisException
     */
    public function __call($method, $parameters): mixed
    {
        return $this->retryOnFailure(fn () => parent::__call(strtolower($method), $parameters));
    }

    /**
     * Execute an operation with retry and forced reconnect support.
     *
     * @param  callable  $callback  The operation to execute.
     * @param  ?int  $retryAttempts  The number of times the retry is performed.
     * @param  ?int  $retryDelay  The time in milliseconds to wait before retrying again.
     * @return mixed The result of the first successful attempt.
     *
     * @throws RetryRedisException|RedisException
     */
    protected function retryOnFailure(
        callable $callback,
        ?int $retryAttempts = null,
        ?int $retryDelay = null,
    ): mixed {
        return $this->retryContext->retryOnFailure(
            $callback,
            $retryAttempts,
            $retryDelay,
            failureCallback: fn () => $this->forceReconnect(),
        );
    }

    /**
     * Force a reconnect while ignoring expected teardown and DNS errors.
     */
    protected function forceReconnect(): void
    {
        try {
            $this->disconnect();
        } catch (RedisException $e) {
            // Ignore when the creation of a new client gets an exception.
            // If this exception isn't caught the retry will stop.
        } catch (Throwable $e) {
            if (! $this->retryContext->manager()->isNameResolutionException($e)) {
                throw $e;
            }
        }

        // Here we reconnect through Redis Sentinel if we lost connection to the server or if another unavailability occurred.
        // We may actually reconnect to the same, broken server. But after a failover occured, we should be ok.
        // It may take a moment until the Sentinel returns the new master, so this may be triggered multiple times.
        try {
            $this->reconnect();
        } catch (RedisException $e) {
            // Ignore when the creation of a new client gets an exception.
            // If this exception isn't caught the retry will stop.
        } catch (Throwable $e) {
            if (! $this->retryContext->manager()->isNameResolutionException($e)) {
                throw $e;
            }
        }
    }

    /**
     * Replace the current client with a freshly resolved connection.
     */
    private function reconnect(): void
    {
        $this->client = $this->connector ? call_user_func($this->connector) : $this->client;
    }
}
