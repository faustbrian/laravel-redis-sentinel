## Table of Contents

1. [Getting Started](#doc-docs-readme)
2. [Configuration](#doc-docs-configuration)
3. [Multiple Sentinel Hosts](#doc-docs-multiple-sentinel-hosts)
4. [Failover Behavior](#doc-docs-failover-behavior)
5. [Testing](#doc-docs-testing)
<a id="doc-docs-readme"></a>

Laravel Redis Sentinel extends Laravel's Redis manager with a
`phpredis-sentinel` driver. The driver asks Redis Sentinel for the active
master, connects through Laravel's existing PhpRedis stack, and retries
transient failures while Sentinel failover settles.

## Installation

Install the package via Composer:

```bash
composer require cline/laravel-redis-sentinel
```

Laravel discovers the service provider automatically.

## Basic Setup

Update the `redis` section in `config/database.php` to use the
`phpredis-sentinel` client:

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis-sentinel'),

    'default' => [
        'sentinel_host' => env('REDIS_SENTINEL_HOST', '127.0.0.1'),
        'sentinel_port' => (int) env('REDIS_SENTINEL_PORT', 26379),
        'sentinel_service' => env('REDIS_SENTINEL_SERVICE', 'mymaster'),
        'sentinel_timeout' => (float) env('REDIS_SENTINEL_TIMEOUT', 0),
        'sentinel_persistent' => env('REDIS_SENTINEL_PERSISTENT'),
        'sentinel_retry_interval' => (int) env('REDIS_SENTINEL_RETRY_INTERVAL', 0),
        'sentinel_read_timeout' => (float) env('REDIS_SENTINEL_READ_TIMEOUT', 0),
        'sentinel_username' => env('REDIS_SENTINEL_USERNAME'),
        'sentinel_password' => env('REDIS_SENTINEL_PASSWORD'),

        'connector_retry_attempts' => env('REDIS_CONNECTOR_RETRY_ATTEMPTS'),
        'connector_retry_delay' => env('REDIS_CONNECTOR_RETRY_DELAY'),

        'password' => env('REDIS_PASSWORD'),
        'database' => (int) env('REDIS_DB', 0),
    ],
];
```

You can also switch the client by setting:

```env
REDIS_CLIENT=phpredis-sentinel
```

<a id="doc-docs-configuration"></a>

## Configuration

The package does not publish its own config file. It extends Laravel's Redis
connection config with Sentinel-specific keys:

- `sentinel_host`: Hostname or IP of the Sentinel endpoint
- `sentinel_port`: Sentinel port, default `26379`
- `sentinel_service`: Name of the monitored Redis master
- `sentinel_timeout`: Connect timeout for Sentinel
- `sentinel_persistent`: Whether Sentinel connections should be persistent
- `sentinel_retry_interval`: Retry interval passed to PhpRedis
- `sentinel_read_timeout`: Read timeout for the Sentinel connection
- `sentinel_username`: Optional Sentinel username
- `sentinel_password`: Optional Sentinel password
- `connector_retry_attempts`: Number of times the package retries reconnectable failures
- `connector_retry_delay`: Delay in milliseconds between retries

All other Redis options continue to come from Laravel's normal PhpRedis
configuration. The package ignores `host` and `port` for the Redis server
itself because those values are discovered dynamically through Sentinel.

### TLS Support

If Sentinel itself is exposed over TLS, add `sentinel_ssl`:

```php
'sentinel_ssl' => [
    'cafile' => '/path/to/sentinel_ca.crt',
],
```

If the resolved Redis master also expects TLS, configure Laravel's normal
PhpRedis context as well:

```php
'context' => [
    'stream' => [
        'cafile' => '/path/to/redis_ca.crt',
    ],
],
'scheme' => 'tls',
```

Full example:

```php
'redis' => [
    'client' => 'phpredis-sentinel',

    'default' => [
        'sentinel_host' => 'tls://sentinel.example.internal',
        'sentinel_port' => 26379,
        'sentinel_service' => 'mymaster',
        'sentinel_username' => 'sentinel-user',
        'sentinel_password' => 'sentinel-password',
        'sentinel_ssl' => [
            'cafile' => '/path/to/sentinel_ca.crt',
        ],
        'context' => [
            'stream' => [
                'cafile' => '/path/to/redis_ca.crt',
            ],
        ],
        'scheme' => 'tls',
        'username' => 'redis-user',
        'password' => 'redis-password',
        'database' => 1,
    ],
];
```

Because Sentinel resolves masters by IP and port, certificates often need the
resolved IP address in the SAN list unless your network layer preserves names.

<a id="doc-docs-multiple-sentinel-hosts"></a>

## Multiple Sentinel Hosts

If you expose several Sentinel nodes, configure `sentinel_hosts` instead of a
single `sentinel_host`/`sentinel_port` pair. The connector will try each host
in order until one returns a master.

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis-sentinel'),

    'default' => [
        'sentinel_hosts' => [
            ['host' => '10.0.0.10', 'port' => 26379],
            ['host' => '10.0.0.11', 'port' => 26379],
            ['host' => '10.0.0.12', 'port' => 26379],
        ],
        'sentinel_service' => 'mymaster',
        'password' => env('REDIS_PASSWORD'),
        'database' => 0,
    ],
];
```

<a id="doc-docs-failover-behavior"></a>

## Failover Behavior

The package adds a third Laravel Redis client, `phpredis-sentinel`. Under the
hood it works in two layers:

1. The connector asks Sentinel for the current master and builds a normal
   PhpRedis client that targets that host.
2. The connection wrapper retries commands when Redis reports failover-style
   transport errors, readonly replica errors, or temporary DNS issues.

This means your application continues to use Laravel's regular Redis manager
API while gaining Sentinel master discovery and reconnect behavior that is
specific to failover windows.

The package does not load balance reads across replicas and does not try to
coordinate Sentinel topology itself. All traffic is sent to the master that
Sentinel currently reports.

<a id="doc-docs-testing"></a>

## Testing

Package tests expect a local Redis/Sentinel cluster. The repository includes a
helper script to start one:

```bash
sh start-redis-cluster.sh
```

Then run the test suite:

```bash
composer test
```
