<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Vault\EvictionPolicies\TimeBasedPolicy;
use Cline\Vault\Models\VaultEntry;
use Cline\Vault\Vault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Sleep;
use Tests\Fixtures\User;

uses(RefreshDatabase::class);

describe('Vault Owner-Scoped Operations', function (): void {
    test('stores value with owner', function (): void {
        // Arrange
        $vault = resolve(Vault::class);
        $user = User::query()->create(['name' => 'John', 'email' => 'john@example.com']);

        // Act
        $entry = $vault->put('api_key', 'secret-123', $user);

        // Assert
        expect($entry->owner_type)->toBe(User::class);
        expect($entry->owner_id)->toBe($user->id);
        $this->assertDatabaseHas('vault_entries', [
            'key' => 'api_key',
            'owner_type' => User::class,
            'owner_id' => $user->id,
        ]);
    });

    test('retrieves value for specific owner', function (): void {
        // Arrange
        $vault = resolve(Vault::class);
        $user = User::query()->create(['name' => 'John', 'email' => 'john@example.com']);
        $vault->put('api_key', 'user-secret', $user);

        // Act
        $value = $vault->get('api_key', $user);

        // Assert
        expect($value)->toBe('user-secret');
    });

    test('same key different owners store separately', function (): void {
        // Arrange
        $vault = resolve(Vault::class);
        $user1 = User::query()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $user2 = User::query()->create(['name' => 'Bob', 'email' => 'bob@example.com']);

        // Act
        $vault->put('api_key', 'alice-secret', $user1);
        $vault->put('api_key', 'bob-secret', $user2);

        // Assert
        expect($vault->get('api_key', $user1))->toBe('alice-secret');
        expect($vault->get('api_key', $user2))->toBe('bob-secret');
        expect(VaultEntry::query()->where('key', 'api_key')->count())->toBe(2);
    });

    test('same key with and without owner are separate', function (): void {
        // Arrange
        $vault = resolve(Vault::class);
        $user = User::query()->create(['name' => 'John', 'email' => 'john@example.com']);

        // Act
        $vault->put('api_key', 'global-secret');
        $vault->put('api_key', 'user-secret', $user);

        // Assert
        expect($vault->get('api_key'))->toBe('global-secret');
        expect($vault->get('api_key', $user))->toBe('user-secret');
        expect(VaultEntry::query()->where('key', 'api_key')->count())->toBe(2);
    });

    test('has returns true only for correct owner', function (): void {
        // Arrange
        $vault = resolve(Vault::class);
        $user1 = User::query()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $user2 = User::query()->create(['name' => 'Bob', 'email' => 'bob@example.com']);
        $vault->put('api_key', 'secret', $user1);

        // Act & Assert
        expect($vault->has('api_key', $user1))->toBeTrue();
        expect($vault->has('api_key', $user2))->toBeFalse();
        expect($vault->has('api_key'))->toBeFalse();
    });

    test('forget only removes entry for specific owner', function (): void {
        // Arrange
        $vault = resolve(Vault::class);
        $user1 = User::query()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $user2 = User::query()->create(['name' => 'Bob', 'email' => 'bob@example.com']);
        $vault->put('api_key', 'alice-secret', $user1);
        $vault->put('api_key', 'bob-secret', $user2);

        // Act
        $vault->forget('api_key', $user1);

        // Assert
        expect($vault->has('api_key', $user1))->toBeFalse();
        expect($vault->has('api_key', $user2))->toBeTrue();
    });

    test('owner relationship works correctly', function (): void {
        // Arrange
        $vault = resolve(Vault::class);
        $user = User::query()->create(['name' => 'John', 'email' => 'john@example.com']);
        $entry = $vault->put('api_key', 'secret', $user);

        // Act
        $owner = $entry->owner;

        // Assert
        expect($owner)->toBeInstanceOf(User::class);
        expect($owner->id)->toBe($user->id);
        expect($owner->name)->toBe('John');
    });

    test('eviction respects owner scoping', function (): void {
        // Arrange
        $vault = resolve(Vault::class);
        $user1 = User::query()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $user2 = User::query()->create(['name' => 'Bob', 'email' => 'bob@example.com']);
        $policy = new TimeBasedPolicy(1);

        $vault->put('temp', 'value1', $user1, $policy);
        $vault->put('temp', 'value2', $user2, $policy);
        $vault->put('temp', 'value3', null, $policy);

        Sleep::sleep(2);

        // Act
        $count = $vault->evict();

        // Assert
        expect($count)->toBe(3);
        expect($vault->has('temp', $user1))->toBeFalse();
        expect($vault->has('temp', $user2))->toBeFalse();
        expect($vault->has('temp'))->toBeFalse();
    });
});
