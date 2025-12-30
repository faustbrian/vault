[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

# Vault

Secure value storage system for Laravel with encryption, owner scoping, and flexible eviction policies.

## Requirements

> **Requires [PHP 8.4+](https://php.net/releases/)**

## Installation

```bash
composer require cline/vault
```

## Usage

```php
use Cline\Vault\Facades\Vault;

// Store encrypted values
Vault::put('api_key', 'secret-key-123');

// Retrieve values
$apiKey = Vault::get('api_key');

// Owner-scoped storage
Vault::put('token', 'user-token', $user);
$token = Vault::get('token', $user);

// With eviction policies
use Cline\Vault\EvictionPolicies\TimeBasedPolicy;
Vault::put('temp_key', 'value', null, new TimeBasedPolicy(3600));
```

## Documentation

- **[Basic Usage](cookbook/basic-usage.md)** - Storing, retrieving, and owner-scoped values
- **[Value Types](cookbook/value-types.md)** - Built-in types and creating custom handlers
- **[Eviction Policies](cookbook/eviction-policies.md)** - Time, access, and composite eviction
- **[Events](cookbook/events.md)** - Audit logging and monitoring
- **[Configuration](cookbook/configuration.md)** - Encryption keys and settings
- **[Advanced Patterns](cookbook/advanced-patterns.md)** - Key rotation, multi-tenancy, caching

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- [Brian Faust][link-maintainer]
- [All Contributors][link-contributors]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://git.cline.sh/faustbrian/vault/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/vault.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/vault.svg

[link-tests]: https://git.cline.sh/faustbrian/vault/actions
[link-packagist]: https://packagist.org/packages/cline/vault
[link-downloads]: https://packagist.org/packages/cline/vault
[link-security]: https://git.cline.sh/faustbrian/vault/security
[link-maintainer]: https://git.cline.sh/faustbrian
[link-contributors]: ../../contributors
