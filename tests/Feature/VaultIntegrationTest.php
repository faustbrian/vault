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
use Cline\Vault\EvictionPolicies\AccessCountPolicy;
use Cline\Vault\EvictionPolicies\CompositePolicy;
use Cline\Vault\EvictionPolicies\TimeBasedPolicy;
use Cline\Vault\Models\VaultEntry;
use Cline\Vault\Vault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Sleep;
use Tests\Fixtures\User;

uses(RefreshDatabase::class);

describe('Vault Integration - Full Workflow', function (): void {
    test('complete lifecycle: store, access, track, evict', function (): void {
        // Arrange
        $vault = app(Vault::class);
        $policy = new AccessCountPolicy(3);
        Event::fake();

        // Act - Store
        $entry = $vault->put('api_key', 'secret-123', null, $policy);

        // Assert - Storage
        expect($entry)->toBeInstanceOf(VaultEntry::class);
        expect($entry->access_count)->toBe(0);
        Event::assertDispatched(SecretValueStored::class);

        // Act - Access multiple times
        $value1 = $vault->get('api_key');
        $value2 = $vault->get('api_key');
        $value3 = $vault->get('api_key');

        // Assert - Access tracking
        expect($value1)->toBe('secret-123');
        expect($value2)->toBe('secret-123');
        expect($value3)->toBe('secret-123');

        $entry->refresh();
        expect($entry->access_count)->toBe(3);
        expect($entry->last_accessed_at)->not()->toBeNull();
        Event::assertDispatched(SecretValueAccessed::class, 3);

        // Act - Eviction (triggered on next access or explicit evict)
        $value4 = $vault->get('api_key');

        // Assert - Eviction
        expect($value4)->toBeNull();
        expect($vault->has('api_key'))->toBeFalse();
        Event::assertDispatched(SecretValueEvicted::class);
    });

    test('multi-user workflow with different policies', function (): void {
        // Arrange
        $vault = app(Vault::class);
        $user1 = User::query()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $user2 = User::query()->create(['name' => 'Bob', 'email' => 'bob@example.com']);

        $policy1 = new AccessCountPolicy(2);
        $policy2 = new TimeBasedPolicy(1);

        // Act - Store for different users
        $vault->put('token', 'alice-token', $user1, $policy1);
        $vault->put('token', 'bob-token', $user2, $policy2);

        // Act - Access Alice's token (will evict after 2 accesses)
        expect($vault->get('token', $user1))->toBe('alice-token');
        expect($vault->get('token', $user1))->toBe('alice-token');
        expect($vault->get('token', $user1))->toBeNull();

        // Act - Bob's token still exists
        expect($vault->has('token', $user2))->toBeTrue();

        // Act - Wait for Bob's token to expire
        Sleep::sleep(2);
        expect($vault->get('token', $user2))->toBeNull();

        // Assert - Both evicted
        expect(VaultEntry::query()->where('key', 'token')->count())->toBe(0);
    });

    test('complex composite policy workflow', function (): void {
        // Arrange
        $vault = app(Vault::class);
        $policy = new CompositePolicy([
            new TimeBasedPolicy(2),
            new AccessCountPolicy(3),
        ], 'AND');

        // Act - Store with AND policy (needs BOTH time expired AND 3 accesses)
        $vault->put('complex', 'value', null, $policy);

        // Act - Access twice but don't wait
        $vault->get('complex');
        $vault->get('complex');

        // Assert - Not evicted (only 2 accesses, time not expired)
        expect($vault->evict())->toBe(0);

        // Act - Third access but time still not expired
        $vault->get('complex');

        // Assert - Not evicted (3 accesses but time not expired)
        expect($vault->evict())->toBe(0);

        // Act - Wait for time to expire
        Sleep::sleep(3);

        // Assert - Now evicted (3 accesses AND time expired)
        expect($vault->evict())->toBe(1);
        expect($vault->has('complex'))->toBeFalse();
    });

    test('encryption and decryption with multiple value types', function (): void {
        // Arrange
        $vault = app(Vault::class);

        // Act - Store different types
        $vault->put('string_key', 'Hello World');
        $vault->put('int_key', 42);
        $vault->put('array_key', ['foo' => 'bar', 'baz' => 123]);
        $vault->put('json_key', (object) ['user' => 'Alice', 'role' => 'admin']);

        // Assert - Retrieve and verify types
        expect($vault->get('string_key'))->toBe('Hello World');
        expect($vault->get('int_key'))->toBe(42);
        expect($vault->get('array_key'))->toBe(['foo' => 'bar', 'baz' => 123]);
        expect($vault->get('json_key'))->toBe(['user' => 'Alice', 'role' => 'admin']);

        // Assert - Verify encryption (values stored are not plaintext)
        $entries = VaultEntry::all();

        foreach ($entries as $entry) {
            expect($entry->encrypted_value)->not()->toContain('Hello World');
            expect($entry->encrypted_value)->not()->toContain('Alice');
            expect($entry->encrypted_value)->not()->toContain('admin');
        }
    });

    test('concurrent eviction with manual and automatic triggers', function (): void {
        // Arrange
        $vault = app(Vault::class);
        $policy = new TimeBasedPolicy(1);

        $vault->put('auto1', 'value1', null, $policy);
        $vault->put('auto2', 'value2', null, $policy);
        $vault->put('manual', 'value3');

        Sleep::sleep(2);

        // Act - Manual eviction
        $manualCount = $vault->evict();

        // Assert
        expect($manualCount)->toBe(2);
        expect($vault->has('auto1'))->toBeFalse();
        expect($vault->has('auto2'))->toBeFalse();
        expect($vault->has('manual'))->toBeTrue();

        // Act - Try automatic eviction via get (should return null immediately)
        expect($vault->get('auto1'))->toBeNull();
    });

    test('update existing entry preserves key but changes value', function (): void {
        // Arrange
        $vault = app(Vault::class);
        $vault->put('api_key', 'old-secret');
        $vault->get('api_key');
        $vault->get('api_key');

        $firstEntry = VaultEntry::query()->where('key', 'api_key')->first();
        $originalId = $firstEntry->id;

        // Act - Update
        $vault->put('api_key', 'new-secret');

        // Assert
        $updatedEntry = VaultEntry::query()->where('key', 'api_key')->first();
        expect($updatedEntry->id)->toBe($originalId);
        expect($vault->get('api_key'))->toBe('new-secret');
        expect($updatedEntry->access_count)->toBe(0);
    });

    test('stress test: multiple operations in sequence', function (): void {
        // Arrange
        $vault = app(Vault::class);

        // Act - Rapid operations
        for ($i = 0; $i < 10; ++$i) {
            $vault->put('key_'.$i, 'value_'.$i);
        }

        // Assert - All stored
        for ($i = 0; $i < 10; ++$i) {
            expect($vault->has('key_'.$i))->toBeTrue();
            expect($vault->get('key_'.$i))->toBe('value_'.$i);
        }

        // Act - Delete half
        for ($i = 0; $i < 5; ++$i) {
            $vault->forget('key_'.$i);
        }

        // Assert - Half remain
        for ($i = 0; $i < 5; ++$i) {
            expect($vault->has('key_'.$i))->toBeFalse();
        }

        for ($i = 5; $i < 10; ++$i) {
            expect($vault->has('key_'.$i))->toBeTrue();
        }

        // Assert - Correct count
        expect(VaultEntry::query()->count())->toBe(5);
    });
});
