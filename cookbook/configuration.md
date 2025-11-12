# Configuration

## Publishing Config

```bash
php artisan vendor:publish --tag=vault-config
```

This creates `config/vault.php`.

## Configuration Options

```php
return [
    // Encryption key for securing vault values
    'encryption_key' => env('VAULT_ENCRYPTION_KEY', env('APP_KEY')),

    // Default eviction policy (null = never evict)
    'default_eviction_policy' => null,

    // Track access count and timestamps
    'track_access' => true,

    // Registered value types (priority order)
    'value_types' => [
        \Cline\Vault\ValueTypes\JsonValue::class,
        \Cline\Vault\ValueTypes\ArrayValue::class,
        \Cline\Vault\ValueTypes\IntValue::class,
        \Cline\Vault\ValueTypes\StringValue::class,
    ],
];
```

## Encryption Key

Set a dedicated encryption key for vault:

```bash
# .env
VAULT_ENCRYPTION_KEY=base64:your-secret-key-here
```

Or use Laravel's app key (default):

```bash
APP_KEY=base64:your-app-key
```

## Default Eviction Policy

Set a global eviction policy:

```php
use Cline\Vault\EvictionPolicies\TimeBasedPolicy;

// In config/vault.php
'default_eviction_policy' => new TimeBasedPolicy(86400), // 24 hours
```

Now all `put()` calls without an explicit policy use this default:

```php
// Uses default policy from config
Vault::put('temp_key', 'value123');
```

## Access Tracking

Disable access tracking for better performance:

```php
// In config/vault.php
'track_access' => false,
```

When disabled:
- `access_count` won't increment
- `last_accessed_at` won't update
- Access-based eviction policies won't work

## Custom Value Types

Register custom types in config:

```php
// In config/vault.php
'value_types' => [
    \App\Vault\CreditCardValue::class,  // Highest priority
    \Cline\Vault\ValueTypes\JsonValue::class,
    \Cline\Vault\ValueTypes\ArrayValue::class,
    \Cline\Vault\ValueTypes\IntValue::class,
    \Cline\Vault\ValueTypes\StringValue::class,
],
```

Order matters - types are checked from top to bottom.

## Environment Variables

```bash
# .env
VAULT_ENCRYPTION_KEY=base64:...
VAULT_TRACK_ACCESS=true
```
