# Advanced Patterns

## Temporary Secrets with Metadata

Store additional context with your secrets:

```php
$entry = Vault::put('reset_token', 'token123', $user, new TimeBasedPolicy(3600));

// Add metadata directly on the model
$entry->update([
    'metadata' => [
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
        'created_by' => auth()->id(),
    ],
]);
```

## Bulk Operations

Store multiple secrets efficiently:

```php
$user = User::find(1);

$secrets = [
    'api_key' => 'key123',
    'api_secret' => 'secret456',
    'webhook_url' => 'https://example.com/webhook',
];

foreach ($secrets as $key => $value) {
    Vault::put($key, $value, $user);
}
```

Retrieve multiple secrets:

```php
$keys = ['api_key', 'api_secret', 'webhook_url'];
$secrets = collect($keys)->mapWithKeys(fn($key) => [
    $key => Vault::get($key, $user)
]);
```

## Key Rotation

Rotate encryption keys while maintaining access:

```php
use Cline\Vault\Models\VaultEntry;

// Get all entries using old key
$entries = VaultEntry::where('encryption_key_id', 'old_key')->get();

foreach ($entries as $entry) {
    // Decrypt with old key
    $type = app($entry->value_type);
    $decrypted = $type->decrypt($entry->encrypted_value, config('vault.old_key'));

    // Re-encrypt with new key
    $encrypted = $type->encrypt($decrypted, config('vault.encryption_key'));

    // Update entry
    $entry->update([
        'encrypted_value' => $encrypted,
        'encryption_key_id' => 'new_key',
    ]);
}
```

## Multi-Tenant Secrets

Store tenant-specific secrets:

```php
class Tenant extends Model
{
    // ...
}

$tenant = Tenant::find(1);

// Each tenant gets isolated secrets
Vault::put('stripe_key', 'sk_test_...', $tenant);
Vault::put('stripe_secret', 'rk_test_...', $tenant);

// Retrieve tenant secrets
$stripeKey = Vault::get('stripe_key', $tenant);
```

## One-Time Secrets (Self-Destructing)

```php
use Cline\Vault\EvictionPolicies\AccessCountPolicy;

// Secret destroyed after first access
Vault::put('password_reset', 'temp123', $user, new AccessCountPolicy(1));

// First access - works
$token = Vault::get('password_reset', $user); // 'temp123'

// Second access - returns null (evicted)
$token = Vault::get('password_reset', $user); // null
```

## Conditional Eviction

```php
use Cline\Vault\Contracts\EvictionPolicy;
use Cline\Vault\Models\VaultEntry;

class WeekdayOnlyPolicy implements EvictionPolicy
{
    public function shouldEvict(VaultEntry $entry): bool
    {
        // Evict on weekends
        return now()->isWeekend();
    }

    public function getName(): string
    {
        return 'weekday_only';
    }
}

// Secret only available on weekdays
Vault::put('office_access', 'code123', null, new WeekdayOnlyPolicy());
```

## Caching Layer

Add a caching layer for frequently accessed secrets:

```php
use Illuminate\Support\Facades\Cache;

function getCachedSecret(string $key, $owner = null): mixed
{
    $cacheKey = "vault.{$key}." . ($owner?->id ?? 'global');

    return Cache::remember($cacheKey, 300, function () use ($key, $owner) {
        return Vault::get($key, $owner);
    });
}

// First call hits database
$secret = getCachedSecret('api_key', $user);

// Subsequent calls use cache (5 min TTL)
$secret = getCachedSecret('api_key', $user);
```

## Rate-Limited Secrets

Combine access count with time window:

```php
use Cline\Vault\EvictionPolicies\CompositePolicy;

// 100 accesses within 24 hours, then evict
$policy = new CompositePolicy([
    new TimeBasedPolicy(86400),
    new AccessCountPolicy(100),
], 'AND');

Vault::put('rate_limited_api', 'key123', null, $policy);
```

## Automatic Cleanup Job

Create a scheduled job to evict expired secrets:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        $evicted = app(\Cline\Vault\Vault::class)->evict();
        Log::info("Evicted {$evicted} secrets from vault");
    })->hourly()->withoutOverlapping();
}
```

## Testing with Vault

```php
use Cline\Vault\Facades\Vault;
use Cline\Vault\Events\SecretValueAccessed;
use Illuminate\Support\Facades\Event;

test('stores and retrieves secrets', function () {
    Event::fake();

    Vault::put('test_key', 'test_value');

    expect(Vault::has('test_key'))->toBeTrue();
    expect(Vault::get('test_key'))->toBe('test_value');

    Event::assertDispatched(SecretValueAccessed::class);
});
```
