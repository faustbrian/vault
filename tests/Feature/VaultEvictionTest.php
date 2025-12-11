<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Vault\Events\SecretValueEvicted;
use Cline\Vault\EvictionPolicies\AccessCountPolicy;
use Cline\Vault\EvictionPolicies\AccessTimePolicy;
use Cline\Vault\EvictionPolicies\CompositePolicy;
use Cline\Vault\EvictionPolicies\TimeBasedPolicy;
use Cline\Vault\Models\VaultEntry;
use Cline\Vault\Vault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

describe('Vault Eviction', function (): void {
    describe('TimeBasedPolicy eviction', function (): void {
        test('evicts entries older than policy threshold', function (): void {
            // Arrange
            $vault = resolve(Vault::class);
            $policy = new TimeBasedPolicy(1);
            $vault->put('temp', 'value', null, $policy);

            Sleep::sleep(2);

            // Act
            $count = $vault->evict();

            // Assert
            expect($count)->toBe(1);
            expect($vault->has('temp'))->toBeFalse();
        });

        test('does not evict entries within policy threshold', function (): void {
            // Arrange
            $vault = resolve(Vault::class);
            $policy = new TimeBasedPolicy(3_600);
            $vault->put('temp', 'value', null, $policy);

            // Act
            $count = $vault->evict();

            // Assert
            expect($count)->toBe(0);
            expect($vault->has('temp'))->toBeTrue();
        });

        test('get automatically evicts expired entry', function (): void {
            // Arrange
            $vault = resolve(Vault::class);
            $policy = new TimeBasedPolicy(1);
            $vault->put('temp', 'value', null, $policy);

            Sleep::sleep(2);

            // Act
            $value = $vault->get('temp');

            // Assert
            expect($value)->toBeNull();
            expect(VaultEntry::query()->where('key', 'temp')->exists())->toBeFalse();
        });
    });

    describe('AccessCountPolicy eviction', function (): void {
        test('evicts entry after max accesses reached', function (): void {
            // Arrange
            $vault = resolve(Vault::class);
            $policy = new AccessCountPolicy(3);
            $vault->put('limited', 'value', null, $policy);

            // Act
            $vault->get('limited');
            $vault->get('limited');
            $vault->get('limited');

            // Assert - Fourth access should return null as entry is evicted
            expect($vault->get('limited'))->toBeNull();
        });

        test('does not evict before max accesses', function (): void {
            // Arrange
            $vault = resolve(Vault::class);
            $policy = new AccessCountPolicy(5);
            $vault->put('limited', 'value', null, $policy);

            // Act
            $vault->get('limited');
            $vault->get('limited');

            // Assert
            expect($vault->has('limited'))->toBeTrue();
            $count = $vault->evict();
            expect($count)->toBe(0);
        });
    });

    describe('AccessTimePolicy eviction', function (): void {
        test('evicts entry after idle period', function (): void {
            // Arrange
            $vault = resolve(Vault::class);
            $policy = new AccessTimePolicy(1);
            $vault->put('idle', 'value', null, $policy);

            $vault->get('idle');
            Sleep::sleep(2);

            // Act
            $count = $vault->evict();

            // Assert
            expect($count)->toBe(1);
            expect($vault->has('idle'))->toBeFalse();
        });

        test('does not evict recently accessed entry', function (): void {
            // Arrange
            $vault = resolve(Vault::class);
            $policy = new AccessTimePolicy(3_600);
            $vault->put('active', 'value', null, $policy);

            $vault->get('active');

            // Act
            $count = $vault->evict();

            // Assert
            expect($count)->toBe(0);
            expect($vault->has('active'))->toBeTrue();
        });

        test('does not evict never-accessed entry', function (): void {
            // Arrange
            $vault = resolve(Vault::class);
            $policy = new AccessTimePolicy(1);
            $vault->put('untouched', 'value', null, $policy);

            Sleep::sleep(2);

            // Act
            $count = $vault->evict();

            // Assert
            expect($count)->toBe(0);
            expect($vault->has('untouched'))->toBeTrue();
        });
    });

    describe('CompositePolicy eviction', function (): void {
        test('OR policy evicts when any condition met', function (): void {
            // Arrange
            $vault = resolve(Vault::class);
            $policy = new CompositePolicy([
                new TimeBasedPolicy(1),
                new AccessCountPolicy(10),
            ], 'OR');

            $vault->put('temp', 'value', null, $policy);
            Sleep::sleep(2);

            // Act
            $count = $vault->evict();

            // Assert
            expect($count)->toBe(1);
        });

        test('AND policy requires all conditions', function (): void {
            // Arrange
            $vault = resolve(Vault::class);
            $policy = new CompositePolicy([
                new TimeBasedPolicy(1),
                new AccessCountPolicy(3),
            ], 'AND');

            $vault->put('temp', 'value', null, $policy);
            $vault->get('temp');
            $vault->get('temp');

            Sleep::sleep(2);

            // Act
            $count = $vault->evict();

            // Assert - Not evicted because access count is only 2, not 3
            expect($count)->toBe(0);
        });

        test('AND policy evicts when all conditions met', function (): void {
            // Arrange
            $vault = resolve(Vault::class);
            $policy = new CompositePolicy([
                new TimeBasedPolicy(1),
                new AccessCountPolicy(2),
            ], 'AND');

            $vault->put('temp', 'value', null, $policy);
            $vault->get('temp');
            $vault->get('temp');

            Sleep::sleep(2);

            // Act
            $count = $vault->evict();

            // Assert
            expect($count)->toBe(1);
        });
    });

    describe('eviction events', function (): void {
        test('dispatches SecretValueEvicted event', function (): void {
            // Arrange
            $vault = resolve(Vault::class);
            $policy = new TimeBasedPolicy(1);
            $vault->put('temp', 'value', null, $policy);

            Sleep::sleep(2);
            Event::fake();

            // Act
            $vault->evict();

            // Assert
            Event::assertDispatched(SecretValueEvicted::class);
        });

        test('dispatches event for each evicted entry', function (): void {
            // Arrange
            $vault = resolve(Vault::class);
            $policy = new TimeBasedPolicy(1);
            $vault->put('temp1', 'value1', null, $policy);
            $vault->put('temp2', 'value2', null, $policy);
            $vault->put('temp3', 'value3', null, $policy);

            Sleep::sleep(2);
            Event::fake();

            // Act
            $vault->evict();

            // Assert
            Event::assertDispatched(SecretValueEvicted::class, 3);
        });
    });

    describe('edge cases', function (): void {
        test('eviction handles entries without policy', function (): void {
            // Arrange
            $vault = resolve(Vault::class);
            $vault->put('permanent', 'value');

            // Act
            $count = $vault->evict();

            // Assert
            expect($count)->toBe(0);
            expect($vault->has('permanent'))->toBeTrue();
        });

        test('eviction handles mixed entries with and without policies', function (): void {
            // Arrange
            $vault = resolve(Vault::class);
            $policy = new TimeBasedPolicy(1);

            $vault->put('temp', 'value', null, $policy);
            $vault->put('permanent', 'value');

            Sleep::sleep(2);

            // Act
            $count = $vault->evict();

            // Assert
            expect($count)->toBe(1);
            expect($vault->has('temp'))->toBeFalse();
            expect($vault->has('permanent'))->toBeTrue();
        });

        test('has checks eviction policy before returning', function (): void {
            // Arrange
            $vault = resolve(Vault::class);
            $policy = new TimeBasedPolicy(1);
            $vault->put('temp', 'value', null, $policy);

            Sleep::sleep(2);

            // Act
            $exists = $vault->has('temp');

            // Assert
            expect($exists)->toBeFalse();
            expect(VaultEntry::query()->where('key', 'temp')->exists())->toBeFalse();
        });

        test('eviction deletes entry from database', function (): void {
            // Arrange
            $vault = resolve(Vault::class);
            $policy = new TimeBasedPolicy(1);
            $vault->put('temp', 'value', null, $policy);

            $this->assertDatabaseHas('vault_entries', ['key' => 'temp']);

            Sleep::sleep(2);

            // Act
            $vault->evict();

            // Assert
            $this->assertDatabaseMissing('vault_entries', ['key' => 'temp']);
        });
    });
});
