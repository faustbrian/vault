<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Vault\Events\SecretValueAccessed;
use Cline\Vault\Events\SecretValueEvicted;
use Cline\Vault\Events\SecretValueStored;
use Cline\Vault\EvictionPolicies\TimeBasedPolicy;
use Cline\Vault\Exceptions\DecryptionFailedException;
use Cline\Vault\Models\VaultEntry;
use Cline\Vault\ValueTypes\ArrayValue;
use Cline\Vault\ValueTypes\IntValue;
use Cline\Vault\ValueTypes\StringValue;
use Cline\Vault\Vault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Sleep;
use RuntimeException;

uses(RefreshDatabase::class);

describe('Vault CRUD Operations', function (): void {
    describe('put', function (): void {
        test('stores string value in vault', function (): void {
            // Arrange
            $vault = app(Vault::class);
            Event::fake();

            // Act
            $entry = $vault->put('api_key', 'secret-key-123');

            // Assert
            expect($entry)->toBeInstanceOf(VaultEntry::class);
            expect($entry->key)->toBe('api_key');
            expect($entry->value_type)->toBe(StringValue::class);
            expect($entry->encrypted_value)->not()->toContain('secret-key-123');
            $this->assertDatabaseHas('vault_entries', ['key' => 'api_key']);

            Event::assertDispatched(SecretValueStored::class);
        });

        test('stores integer value in vault', function (): void {
            // Arrange
            $vault = app(Vault::class);

            // Act
            $entry = $vault->put('user_id', 42);

            // Assert
            expect($entry->value_type)->toBe(IntValue::class);
            $this->assertDatabaseHas('vault_entries', ['key' => 'user_id']);
        });

        test('stores array value in vault', function (): void {
            // Arrange
            $vault = app(Vault::class);
            $data = ['name' => 'John', 'email' => 'john@example.com'];

            // Act
            $entry = $vault->put('user_data', $data);

            // Assert
            expect($entry->value_type)->toBe(ArrayValue::class);
            $this->assertDatabaseHas('vault_entries', ['key' => 'user_data']);
        });

        test('updates existing entry when key already exists', function (): void {
            // Arrange
            $vault = app(Vault::class);
            $vault->put('api_key', 'old-value');

            // Act
            $entry = $vault->put('api_key', 'new-value');
            $retrieved = $vault->get('api_key');

            // Assert
            expect($retrieved)->toBe('new-value');
            expect(VaultEntry::query()->where('key', 'api_key')->count())->toBe(1);
        });

        test('stores entry with eviction policy', function (): void {
            // Arrange
            $vault = app(Vault::class);
            $policy = new TimeBasedPolicy(3_600);

            // Act
            $entry = $vault->put('temp_key', 'value', null, $policy);

            // Assert
            expect($entry->eviction_policy)->not()->toBeNull();
            expect(unserialize($entry->eviction_policy))->toBeInstanceOf(TimeBasedPolicy::class);
        });

        test('stores entry without owner', function (): void {
            // Arrange
            $vault = app(Vault::class);

            // Act
            $entry = $vault->put('global_key', 'value');

            // Assert
            expect($entry->owner_type)->toBeNull();
            expect($entry->owner_id)->toBeNull();
        });
    });

    describe('get', function (): void {
        test('retrieves stored string value', function (): void {
            // Arrange
            $vault = app(Vault::class);
            $vault->put('api_key', 'secret-123');

            // Act
            $value = $vault->get('api_key');

            // Assert
            expect($value)->toBe('secret-123');
        });

        test('retrieves stored integer value', function (): void {
            // Arrange
            $vault = app(Vault::class);
            $vault->put('count', 42);

            // Act
            $value = $vault->get('count');

            // Assert
            expect($value)->toBe(42);
        });

        test('retrieves stored array value', function (): void {
            // Arrange
            $vault = app(Vault::class);
            $data = ['foo' => 'bar', 'baz' => 123];
            $vault->put('data', $data);

            // Act
            $value = $vault->get('data');

            // Assert
            expect($value)->toBe($data);
        });

        test('returns null for non-existent key', function (): void {
            // Arrange
            $vault = app(Vault::class);

            // Act
            $value = $vault->get('non_existent');

            // Assert
            expect($value)->toBeNull();
        });

        test('dispatches SecretValueAccessed event', function (): void {
            // Arrange
            $vault = app(Vault::class);
            $vault->put('api_key', 'value');
            Event::fake();

            // Act
            $vault->get('api_key');

            // Assert
            Event::assertDispatched(SecretValueAccessed::class);
        });

        test('returns null for evicted entry', function (): void {
            // Arrange
            $vault = app(Vault::class);
            $policy = new TimeBasedPolicy(1);
            $vault->put('temp', 'value', null, $policy);

            Sleep::sleep(2);

            // Act
            $value = $vault->get('temp');

            // Assert
            expect($value)->toBeNull();
        });
    });

    describe('has', function (): void {
        test('returns true for existing key', function (): void {
            // Arrange
            $vault = app(Vault::class);
            $vault->put('api_key', 'value');

            // Act
            $exists = $vault->has('api_key');

            // Assert
            expect($exists)->toBeTrue();
        });

        test('returns false for non-existent key', function (): void {
            // Arrange
            $vault = app(Vault::class);

            // Act
            $exists = $vault->has('non_existent');

            // Assert
            expect($exists)->toBeFalse();
        });

        test('returns false for evicted entry', function (): void {
            // Arrange
            $vault = app(Vault::class);
            $policy = new TimeBasedPolicy(1);
            $vault->put('temp', 'value', null, $policy);

            Sleep::sleep(2);

            // Act
            $exists = $vault->has('temp');

            // Assert
            expect($exists)->toBeFalse();
        });

        test('does not increment access count', function (): void {
            // Arrange
            $vault = app(Vault::class);
            $vault->put('api_key', 'value');

            // Act
            $vault->has('api_key');
            $vault->has('api_key');

            // Assert
            $entry = VaultEntry::query()->where('key', 'api_key')->first();
            expect($entry->access_count)->toBe(0);
        });
    });

    describe('forget', function (): void {
        test('deletes existing entry', function (): void {
            // Arrange
            $vault = app(Vault::class);
            $vault->put('api_key', 'value');

            // Act
            $result = $vault->forget('api_key');

            // Assert
            expect($result)->toBeTrue();
            $this->assertDatabaseMissing('vault_entries', ['key' => 'api_key']);
        });

        test('returns false for non-existent key', function (): void {
            // Arrange
            $vault = app(Vault::class);

            // Act
            $result = $vault->forget('non_existent');

            // Assert
            expect($result)->toBeFalse();
        });

        test('removes entry completely from database', function (): void {
            // Arrange
            $vault = app(Vault::class);
            $vault->put('api_key', 'value');

            // Act
            $vault->forget('api_key');

            // Assert
            expect(VaultEntry::query()->where('key', 'api_key')->exists())->toBeFalse();
        });
    });

    describe('evict', function (): void {
        test('evicts expired entries', function (): void {
            // Arrange
            $vault = app(Vault::class);
            $policy = new TimeBasedPolicy(1);
            $vault->put('temp1', 'value1', null, $policy);
            $vault->put('temp2', 'value2', null, $policy);
            $vault->put('permanent', 'value3');

            Sleep::sleep(2);

            // Act
            $count = $vault->evict();

            // Assert
            expect($count)->toBe(2);
            $this->assertDatabaseMissing('vault_entries', ['key' => 'temp1']);
            $this->assertDatabaseMissing('vault_entries', ['key' => 'temp2']);
            $this->assertDatabaseHas('vault_entries', ['key' => 'permanent']);
        });

        test('returns zero when no entries to evict', function (): void {
            // Arrange
            $vault = app(Vault::class);
            $vault->put('permanent', 'value');

            // Act
            $count = $vault->evict();

            // Assert
            expect($count)->toBe(0);
        });

        test('dispatches SecretValueEvicted event for each evicted entry', function (): void {
            // Arrange
            $vault = app(Vault::class);
            $policy = new TimeBasedPolicy(1);
            $vault->put('temp1', 'value1', null, $policy);
            $vault->put('temp2', 'value2', null, $policy);

            Sleep::sleep(2);
            Event::fake();

            // Act
            $vault->evict();

            // Assert
            Event::assertDispatched(SecretValueEvicted::class, 2);
        });
    });

    describe('error handling', function (): void {
        test('throws DecryptionFailedException for corrupted data', function (): void {
            // Arrange
            $vault = app(Vault::class);
            $vault->put('test', 'value');

            // Manually corrupt the encrypted value in database
            $entry = VaultEntry::query()->where('key', 'test')->first();
            $entry->encrypted_value = 'corrupted_data';
            $entry->save();

            // Act & Assert
            expect(fn () => $vault->get('test'))
                ->toThrow(DecryptionFailedException::class, 'Failed to decrypt value for key: test');
        });

        test('throws RuntimeException for missing encryption key', function (): void {
            // Arrange
            Config::set('vault.encryption_keys.default');
            $vault = app(Vault::class);

            // Act & Assert
            expect(fn () => $vault->put('test', 'value'))
                ->toThrow(RuntimeException::class, 'Encryption key not found: default');
        });

        test('DecryptionFailedException can be created with custom message', function (): void {
            // Act
            $exception = DecryptionFailedException::withMessage('Custom error');

            // Assert
            expect($exception)->toBeInstanceOf(DecryptionFailedException::class);
            expect($exception->getMessage())->toBe('Custom error');
        });

        test('falls back to default key when config is not a string', function (): void {
            // Arrange
            Config::set('vault.default_encryption_key', 123); // Non-string value
            $vault = app(Vault::class);

            // Act
            $vault->put('test', 'value');

            $retrieved = $vault->get('test');

            // Assert - Should use 'default' key when config value is invalid
            expect($retrieved)->toBe('value');
        });
    });
});
