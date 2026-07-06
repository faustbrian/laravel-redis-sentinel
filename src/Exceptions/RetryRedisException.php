<?php

namespace Cline\RedisSentinel\Exceptions;

use RedisException;

/**
 * Indicates that Redis remained unavailable after all retry attempts were used.
 */
class RetryRedisException extends RedisException
{
    //
}
