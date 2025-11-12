<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Vault;

use Cline\Vault\Contracts\EvictionPolicy;
use Cline\Vault\Contracts\SecretValue;
use Cline\Vault\Events\SecretValueAccessed;
use Cline\Vault\Events\SecretValueEvicted;
use Cline\Vault\Events\SecretValueStored;
use Cline\Vault\Exceptions\DecryptionFailedException;
use Cline\Vault\Models\VaultEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Throwable;

use function is_string;
use function now;
use function serialize;
use function throw_unless;
use function unserialize;

/**
 * @psalm-immutable
 */
final readonly class Vault
{
    public function __construct(
        private ValueTypeRegistry $registry,
    ) {}

    public function put(
        string $key,
        mixed $value,
        ?Model $owner = null,
        ?EvictionPolicy $policy = null,
    ): VaultEntry {
        $valueType = $this->registry->resolve($value);
        $encryptionKey = $this->getEncryptionKey();

        $encrypted = $valueType->encrypt($value, $encryptionKey);
        $valueType->serialize();

        $ownerType = $owner instanceof Model ? $owner->getMorphClass() : null;
        $ownerId = $owner instanceof Model ? $owner->getKey() : null;

        /** @var VaultEntry $entry */
        $entry = VaultEntry::query()->updateOrCreate([
            'key' => $key,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
        ], [
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'value_type' => $valueType::class,
            'encrypted_value' => $encrypted,
            'encryption_key_id' => $this->getEncryptionKeyId(),
            'eviction_policy' => $policy instanceof EvictionPolicy ? serialize($policy) : null,
            'access_count' => 0,
            'last_accessed_at' => null,
        ]);

        $entry->refresh();

        Event::dispatch(
            new SecretValueStored($entry),
        );

        return $entry;
    }

    public function get(string $key, ?Model $owner = null): mixed
    {
        $entry = $this->findEntry($key, $owner);

        if (!$entry instanceof VaultEntry) {
            return null;
        }

        // Check eviction before accessing
        if ($this->shouldEvict($entry)) {
            $this->evictEntry($entry);

            return null;
        }

        // Track access
        $this->trackAccess($entry);

        // Decrypt value
        try {
            /** @var class-string<SecretValue> $valueTypeClass */
            $valueTypeClass = $entry->value_type;
            $valueType = new $valueTypeClass();
            $encryptionKey = $this->getEncryptionKey($entry->encryption_key_id);

            $decrypted = $valueType->decrypt($entry->encrypted_value, $encryptionKey);

            Event::dispatch(
                new SecretValueAccessed($entry),
            );

            return $decrypted;
        } catch (Throwable) {
            throw DecryptionFailedException::forKey($key);
        }
    }

    public function has(string $key, ?Model $owner = null): bool
    {
        $entry = $this->findEntry($key, $owner);

        if (!$entry instanceof VaultEntry) {
            return false;
        }

        // Check if should be evicted
        if ($this->shouldEvict($entry)) {
            $this->evictEntry($entry);

            return false;
        }

        return true;
    }

    public function forget(string $key, ?Model $owner = null): bool
    {
        $entry = $this->findEntry($key, $owner);

        if (!$entry instanceof VaultEntry) {
            return false;
        }

        return (bool) $entry->delete();
    }

    public function evict(): int
    {
        $entries = VaultEntry::all();
        $evicted = 0;

        foreach ($entries as $entry) {
            if ($this->shouldEvict($entry)) {
                $this->evictEntry($entry);
                ++$evicted;
            }
        }

        return $evicted;
    }

    /**
     * @phpstan-return VaultEntry|null
     */
    private function findEntry(string $key, ?Model $owner = null): ?VaultEntry
    {
        $query = VaultEntry::query()->where('key', $key);

        if ($owner instanceof Model) {
            $query = $query->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey());
        } else {
            $query = $query->whereNull('owner_type')
                ->whereNull('owner_id');
        }

        /** @var null|VaultEntry */
        return $query->first();
    }

    private function shouldEvict(VaultEntry $entry): bool
    {
        if (!$entry->eviction_policy) {
            return false;
        }

        /** @var EvictionPolicy|false $policy */
        $policy = unserialize($entry->eviction_policy);

        return $policy instanceof EvictionPolicy && $policy->shouldEvict($entry);
    }

    private function evictEntry(VaultEntry $entry): void
    {
        Event::dispatch(
            new SecretValueEvicted($entry),
        );
        $entry->delete();
    }

    private function trackAccess(VaultEntry $entry): void
    {
        if (!Config::get('vault.track_access', true)) {
            return;
        }

        // Use public method instead of protected increment
        ++$entry->access_count;
        $entry->last_accessed_at = now();
        $entry->save();
    }

    private function getEncryptionKey(?string $keyId = null): string
    {
        $keyId ??= $this->getEncryptionKeyId();

        /** @var mixed $key */
        $key = Config::get('vault.encryption_keys.'.$keyId);

        throw_unless(is_string($key), RuntimeException::class, 'Encryption key not found: '.$keyId);

        return $key;
    }

    private function getEncryptionKeyId(): string
    {
        /** @var mixed $keyId */
        $keyId = Config::get('vault.default_encryption_key', 'default');

        if (!is_string($keyId)) {
            return 'default';
        }

        return $keyId;
    }
}
