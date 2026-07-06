[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

Laravel Redis Sentinel adds a `phpredis-sentinel` Redis driver that resolves
the active master through Redis Sentinel before handing control back to
Laravel's native PhpRedis connection layer.

## Requirements

> **Requires [PHP 8.2+](https://php.net/releases/)** and Laravel 8+

## Installation

```bash
composer require cline/laravel-redis-sentinel
```

## Documentation

- **[Getting Started](DOCS.md#doc-docs-readme)** - Installation, driver setup, and first configuration
- **[Configuration](DOCS.md#doc-docs-configuration)** - Sentinel options, retry settings, and TLS support
- **[Multiple Sentinel Hosts](DOCS.md#doc-docs-multiple-sentinel-hosts)** - Using several Sentinel endpoints for discovery
- **[Failover Behavior](DOCS.md#doc-docs-failover-behavior)** - How reconnect and retry handling works
- **[Testing](DOCS.md#doc-docs-testing)** - Local Redis Sentinel test setup

## Security

If you discover any security related issues, please use the
[GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- [Brian Faust][link-maintainer]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://github.com/faustbrian/laravel-redis-sentinel/actions/workflows/tests.yml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/laravel-redis-sentinel.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/laravel-redis-sentinel.svg

[link-tests]: https://github.com/faustbrian/laravel-redis-sentinel/actions
[link-packagist]: https://packagist.org/packages/cline/laravel-redis-sentinel
[link-downloads]: https://packagist.org/packages/cline/laravel-redis-sentinel
[link-security]: https://github.com/faustbrian/laravel-redis-sentinel/security
[link-maintainer]: https://github.com/faustbrian
