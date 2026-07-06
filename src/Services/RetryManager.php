<?php

declare(strict_types=1);

namespace Cline\RedisSentinel\Services;

use Cline\RedisSentinel\Exceptions\RetryRedisException;
use RedisException;
use Throwable;

/**
 * Decides which Redis failures are safe to retry during Sentinel failover.
 *
 * Sentinel failover briefly leaves clients talking to an old master or a host
 * that is no longer reachable. This manager classifies those exceptions,
 * executes retry loops, and raises a dedicated exception when the retry budget
 * is exhausted.
 */
class RetryManager
{
    /**
     * Message fragments that indicate a lost connection or stale master.
     */
    public const ERROR_MESSAGES_INDICATING_UNAVAILABILITY = [
        'connection closed',
        'connection refused',
        'connection lost',
        'failed while reconnecting',
        'is loading the dataset in memory',
        'php_network_getaddresses',
        'read error on connection',
        'socket',
        'went away',
        'loading',
        'readonly',
        "can't write against a read only replica",
        'connection timed out',
    ];

    /**
     * Message fragments that indicate DNS or name-resolution instability.
     */
    public const MESSAGES_INDICATING_NAME_RESOLUTION_ERRORS = [
        'getaddrinfo',
        'name or service not known',
    ];

    /**
     * Execute an operation until it succeeds or the retry budget is exhausted.
     *
     * @param  callable  $callback  The operation to execute.
     * @param  int  $retryAttempts  The number of times the retry is performed.
     * @param  int  $retryDelay  The time in milliseconds to wait before retrying again.
     * @param  callable|null  $failureCallback  The callback to execute when failure occours.
     * @return mixed The result of the first successful attempt.
     *
     * @throws RetryRedisException|RedisException
     */
    public function retryOnFailure(
        callable $callback,
        int $retryAttempts,
        int $retryDelay,
        ?callable $failureCallback = null,
    ): mixed {
        $lastException = null;

        for ($currentAttempt = 0; $currentAttempt <= $retryAttempts; $currentAttempt++) {
            try {
                // We directly return the callback on the first attempt.
                if ($currentAttempt === 0) {
                    return $callback();
                }

                // Wrap the callback to distinguish them from the first attempt.
                return $this->retry($callback);
            } catch (Throwable $exception) {
                // Check if we should retry this exception.
                if (! $this->shouldRetry($exception)) {
                    throw $exception;
                }

                // Wait before retry.
                if ($retryAttempts !== 0) {
                    usleep($retryDelay * 1000);
                }

                // Execute optional failure callback.
                if ($failureCallback && is_callable($failureCallback)) {
                    call_user_func($failureCallback);
                }

                $lastException = $exception;
            }
        }

        throw new RetryRedisException(
            sprintf('Reached the (re)connect limit of %d attempts.', $retryAttempts),
            0,
            $lastException,
        );
    }

    /**
     * Execute a subsequent attempt.
     *
     * This indirection exists so tests can spy on retry behavior separately
     * from the initial attempt.
     */
    public function retry(callable $callback): mixed
    {
        return $callback();
    }

    /**
     * Decide whether the exception represents a retryable failover condition.
     */
    public function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof RedisException && $this->shouldRetryRedisException($exception)) {
            return true;
        }

        if ($this->isNameResolutionException($exception)) {
            return true;
        }

        return false;
    }

    /**
     * Check whether the Redis exception message matches known failover states.
     */
    public function shouldRetryRedisException(RedisException $exception): bool
    {
        // We convert the exception message to lower-case in order to perform case-insensitive comparison.
        $exceptionMessage = strtolower($exception->getMessage());

        // Because we also match only partial exception messages, we cannot use in_array() at this point.
        foreach (self::ERROR_MESSAGES_INDICATING_UNAVAILABILITY as $errorMessage) {
            if (str_contains($exceptionMessage, $errorMessage)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether the exception represents temporary DNS instability.
     */
    public function isNameResolutionException(Throwable $exception): bool
    {
        // We convert the exception message to lower-case in order to perform case-insensitive comparison.
        $exceptionMessage = strtolower($exception->getMessage());

        // Because we also match only partial exception messages, we cannot use in_array() at this point.
        foreach (self::MESSAGES_INDICATING_NAME_RESOLUTION_ERRORS as $errorMessage) {
            if (str_contains($exceptionMessage, $errorMessage)) {
                return true;
            }
        }

        return false;
    }
}
