# Events

Vault dispatches events for auditing and monitoring.

## Available Events

- `SecretValueStored` - When a value is stored
- `SecretValueAccessed` - When a value is retrieved
- `SecretValueEvicted` - When a value is evicted

## Listening to Events

Register listeners in your `EventServiceProvider`:

```php
use Cline\Vault\Events\SecretValueStored;
use Cline\Vault\Events\SecretValueAccessed;
use Cline\Vault\Events\SecretValueEvicted;

protected $listen = [
    SecretValueStored::class => [
        LogSecretStorage::class,
    ],
    SecretValueAccessed::class => [
        LogSecretAccess::class,
    ],
    SecretValueEvicted::class => [
        LogSecretEviction::class,
    ],
];
```

## Example: Audit Logging

```php
use Cline\Vault\Events\SecretValueAccessed;
use Illuminate\Support\Facades\Log;

class LogSecretAccess
{
    public function handle(SecretValueAccessed $event): void
    {
        Log::info('Secret accessed', [
            'key' => $event->entry->key,
            'owner_type' => $event->entry->owner_type,
            'owner_id' => $event->entry->owner_id,
            'access_count' => $event->entry->access_count,
            'accessed_at' => $event->entry->last_accessed_at,
        ]);
    }
}
```

## Example: Security Alerts

```php
use Cline\Vault\Events\SecretValueAccessed;
use App\Notifications\HighAccessAlert;

class MonitorHighAccess
{
    public function handle(SecretValueAccessed $event): void
    {
        // Alert if accessed more than 50 times
        if ($event->entry->access_count > 50) {
            $admin = User::find(1);
            $admin->notify(new HighAccessAlert($event->entry));
        }
    }
}
```

## Example: Eviction Cleanup

```php
use Cline\Vault\Events\SecretValueEvicted;
use Illuminate\Support\Facades\Cache;

class CleanupOnEviction
{
    public function handle(SecretValueEvicted $event): void
    {
        // Clear related cache entries
        Cache::forget("vault.{$event->entry->key}");

        // Notify owner if they have one
        if ($event->entry->owner) {
            $event->entry->owner->notify(
                new SecretEvictedNotification($event->entry->key)
            );
        }
    }
}
```

## Event Properties

All events expose the `VaultEntry` model:

```php
$event->entry->key;                  // string
$event->entry->value_type;           // class-string
$event->entry->owner_type;           // ?string
$event->entry->owner_id;             // ?string
$event->entry->access_count;         // int
$event->entry->last_accessed_at;     // ?Carbon
$event->entry->eviction_policy;      // ?string
$event->entry->metadata;             // ?array
$event->entry->created_at;           // Carbon
$event->entry->updated_at;           // Carbon
```
