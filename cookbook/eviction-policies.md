# Eviction Policies

Control when secrets are automatically removed from the vault.

## Time-Based Eviction

Remove secrets after a specific time period:

```php
use Cline\Vault\EvictionPolicies\TimeBasedPolicy;

// Evict after 1 hour
Vault::put('temp_token', 'xyz123', null, new TimeBasedPolicy(3600));

// Evict after 7 days
Vault::put('session_key', 'abc456', null, new TimeBasedPolicy(604800));
```

## Access Count Eviction

Remove secrets after being accessed N times:

```php
use Cline\Vault\EvictionPolicies\AccessCountPolicy;

// One-time use secret (evict after 1 access)
Vault::put('reset_token', 'token123', null, new AccessCountPolicy(1));

// Multi-use secret (evict after 10 accesses)
Vault::put('api_key', 'key456', null, new AccessCountPolicy(10));
```

## Access Time Eviction

Remove secrets after idle period (time since last access):

```php
use Cline\Vault\EvictionPolicies\AccessTimePolicy;

// Evict if not accessed for 1 hour
Vault::put('session', 'sess789', null, new AccessTimePolicy(3600));

// Evict if idle for 30 days
Vault::put('backup_key', 'bak123', null, new AccessTimePolicy(2592000));
```

## Composite Policies (AND/OR)

Combine multiple policies:

```php
use Cline\Vault\EvictionPolicies\CompositePolicy;

// Evict after 7 days OR 100 accesses (whichever comes first)
$policy = new CompositePolicy([
    new TimeBasedPolicy(604800),
    new AccessCountPolicy(100),
], 'OR');

Vault::put('limited_key', 'key789', null, $policy);

// Evict after 24 hours AND more than 50 accesses (both must be true)
$policy = new CompositePolicy([
    new TimeBasedPolicy(86400),
    new AccessCountPolicy(50),
], 'AND');

Vault::put('rate_limited', 'rl123', null, $policy);
```

## Running Eviction

Manually trigger eviction to clean up expired secrets:

```php
// Returns count of evicted entries
$evicted = Vault::evict();
```

Schedule in your console kernel:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        app(\Cline\Vault\Vault::class)->evict();
    })->hourly();
}
```

## Creating Custom Policies

Implement the `EvictionPolicy` interface:

```php
use Cline\Vault\Contracts\EvictionPolicy;
use Cline\Vault\Models\VaultEntry;

final readonly class BusinessHoursPolicy implements EvictionPolicy
{
    public function shouldEvict(VaultEntry $entry): bool
    {
        $now = now();

        // Evict secrets created outside business hours (9am-5pm)
        $hour = $now->hour;

        return $hour < 9 || $hour >= 17;
    }

    public function getName(): string
    {
        return 'business_hours';
    }
}
```

Use custom policy:

```php
Vault::put('temp_data', 'data123', null, new BusinessHoursPolicy());
```
