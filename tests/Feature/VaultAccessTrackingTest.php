<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Vault\Models\VaultEntry;
use Cline\Vault\Vault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Sleep;
use Tests\Fixtures\User;

uses(RefreshDatabase::class);

describe('Vault Access Tracking', function (): void {
    test('increments access count on each get', function (): void {
        // Arrange
        $vault = app(Vault::class);
        $vault->put('api_key', 'secret');

        // Act
        $vault->get('api_key');
        $vault->get('api_key');
        $vault->get('api_key');

        // Assert
        $entry = VaultEntry::query()->where('key', 'api_key')->first();
        expect($entry->access_count)->toBe(3);
    });

    test('updates last_accessed_at on each get', function (): void {
        // Arrange
        $vault = app(Vault::class);
        $vault->put('api_key', 'secret');

        $entry = VaultEntry::query()->where('key', 'api_key')->first();
        $originalAccessTime = $entry->last_accessed_at;

        Sleep::sleep(1);

        // Act
        $vault->get('api_key');

        // Assert
        $entry->refresh();
        expect($entry->last_accessed_at)->not()->toBe($originalAccessTime);
        expect($entry->last_accessed_at->isAfter($originalAccessTime ?? now()->subYear()))->toBeTrue();
    });

    test('does not track access when disabled in config', function (): void {
        // Arrange
        Config::set('vault.track_access', false);
        $vault = app(Vault::class);
        $vault->put('api_key', 'secret');

        // Act
        $vault->get('api_key');
        $vault->get('api_key');

        // Assert
        $entry = VaultEntry::query()->where('key', 'api_key')->first();
        expect($entry->access_count)->toBe(0);
        expect($entry->last_accessed_at)->toBeNull();
    });

    test('has does not increment access count', function (): void {
        // Arrange
        $vault = app(Vault::class);
        $vault->put('api_key', 'secret');

        // Act
        $vault->has('api_key');
        $vault->has('api_key');
        $vault->has('api_key');

        // Assert
        $entry = VaultEntry::query()->where('key', 'api_key')->first();
        expect($entry->access_count)->toBe(0);
    });

    test('has does not update last_accessed_at', function (): void {
        // Arrange
        $vault = app(Vault::class);
        $vault->put('api_key', 'secret');

        $entry = VaultEntry::query()->where('key', 'api_key')->first();
        $originalAccessTime = $entry->last_accessed_at;

        // Act
        $vault->has('api_key');

        // Assert
        $entry->refresh();
        expect($entry->last_accessed_at)->toBe($originalAccessTime);
    });

    test('tracks access separately for different owners', function (): void {
        // Arrange
        $vault = app(Vault::class);
        $user1 = User::query()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $user2 = User::query()->create(['name' => 'Bob', 'email' => 'bob@example.com']);

        $vault->put('api_key', 'alice-secret', $user1);
        $vault->put('api_key', 'bob-secret', $user2);

        // Act
        $vault->get('api_key', $user1);
        $vault->get('api_key', $user1);
        $vault->get('api_key', $user2);

        // Assert
        $aliceEntry = VaultEntry::query()->where('key', 'api_key')
            ->where('owner_id', $user1->id)
            ->first();
        $bobEntry = VaultEntry::query()->where('key', 'api_key')
            ->where('owner_id', $user2->id)
            ->first();

        expect($aliceEntry->access_count)->toBe(2);
        expect($bobEntry->access_count)->toBe(1);
    });

    test('access count starts at zero for new entry', function (): void {
        // Arrange
        $vault = app(Vault::class);

        // Act
        $entry = $vault->put('api_key', 'secret');

        // Assert
        expect($entry->access_count)->toBe(0);
    });

    test('last_accessed_at is null for new entry', function (): void {
        // Arrange
        $vault = app(Vault::class);

        // Act
        $entry = $vault->put('api_key', 'secret');

        // Assert
        expect($entry->last_accessed_at)->toBeNull();
    });

    test('updating entry resets access tracking', function (): void {
        // Arrange
        $vault = app(Vault::class);
        $vault->put('api_key', 'old-secret');
        $vault->get('api_key');
        $vault->get('api_key');

        // Act
        $updatedEntry = $vault->put('api_key', 'new-secret');

        // Assert
        expect($updatedEntry->access_count)->toBe(0);
        expect($updatedEntry->last_accessed_at)->toBeNull();
    });

    test('access count persists across multiple accesses', function (): void {
        // Arrange
        $vault = app(Vault::class);
        $vault->put('api_key', 'secret');

        // Act
        for ($i = 0; $i < 10; ++$i) {
            $vault->get('api_key');
        }

        // Assert
        $entry = VaultEntry::query()->where('key', 'api_key')->first();
        expect($entry->access_count)->toBe(10);
    });
});
