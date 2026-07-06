<?php

declare(strict_types=1);

namespace Cline\RedisSentinel\Services;

use Cline\RedisSentinel\Exceptions\RetryRedisException;
use RedisException;

/**
 * Carries retry defaults for one connector or connection lifecycle.
 *
 * The context lets callers override attempts or delay for a single operation
 * while still sharing the same `RetryManager` heuristics for retryable errors.
 */
class RetryContext
{
    /**
     * @param  RetryManager  $retryManager  Shared retry classifier and executor
     * @param  int  $retryAttempts  Default retry count for reconnectable failures
     * @param  int  $retryDelay  Default wait in milliseconds between retries
     */
    public function __construct(
        protected RetryManager $retryManager,
        protected int $retryAttempts,
        protected int $retryDelay,
    ) {
        //
    }

    /**
     * Execute an operation with the configured retry defaults.
     *
     * @param  callable  $callback  The operation to execute.
     * @param  int|null  $retryAttempts  The number of times the retry is performed.
     * @param  int|null  $retryDelay  The time in milliseconds to wait before retrying again.
     * @param  callable|null  $failureCallback  The callback to execute when failure occours.
     * @return mixed The result of the first successful attempt.
     *
     * @throws RetryRedisException|RedisException
     */
    public function retryOnFailure(
        callable $callback,
        ?int $retryAttempts = null,
        ?int $retryDelay = null,
        ?callable $failureCallback = null,
    ): mixed {
        $retryAttempts ??= $this->retryAttempts;
        $retryDelay ??= $this->retryDelay;

        return $this->retryManager->retryOnFailure(
            $callback,
            $retryAttempts,
            $retryDelay,
            $failureCallback,
        );
    }

    /**
     * Expose the underlying manager for exception classification helpers.
     */
    public function manager(): RetryManager
    {
        return $this->retryManager;
    }
}
