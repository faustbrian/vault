<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Vault\EvictionPolicies\TimeBasedPolicy;
use Cline\Vault\Models\VaultEntry;
use Illuminate\Support\Facades\Date;

describe('TimeBasedPolicy', function (): void {
    test('getName returns correct policy name', function (): void {
        // Arrange
        $policy = new TimeBasedPolicy(60);

        // Act
        $name = $policy->getName();

        // Assert
        expect($name)->toBe('time_based');
    });

    test('shouldEvict returns false for recently created entry', function (): void {
        // Arrange
        $policy = new TimeBasedPolicy(3_600);
        $entry = new VaultEntry();
        $entry->created_at = Date::now();

        // Act
        $result = $policy->shouldEvict($entry);

        // Assert
        expect($result)->toBeFalse();
    });

    test('shouldEvict returns true for expired entry', function (): void {
        // Arrange
        $policy = new TimeBasedPolicy(60);
        $entry = new VaultEntry();
        $entry->created_at = Date::now()->subSeconds(61);

        // Act
        $result = $policy->shouldEvict($entry);

        // Assert
        expect($result)->toBeTrue();
    });

    test('shouldEvict returns true for entry at exact boundary', function (): void {
        // Arrange
        $policy = new TimeBasedPolicy(60);
        $entry = new VaultEntry();
        $entry->created_at = Date::now()->subSeconds(60);

        // Act
        $result = $policy->shouldEvict($entry);

        // Assert
        expect($result)->toBeTrue();
    });

    test('shouldEvict handles zero seconds policy', function (): void {
        // Arrange
        $policy = new TimeBasedPolicy(0);
        $entry = new VaultEntry();
        $entry->created_at = Date::now();

        // Act
        $result = $policy->shouldEvict($entry);

        // Assert
        expect($result)->toBeTrue();
    });

    test('shouldEvict handles large time spans', function (): void {
        // Arrange
        $policy = new TimeBasedPolicy(86_400);
        $entry = new VaultEntry();
        $entry->created_at = Date::now()->subDays(2);

        // Act
        $result = $policy->shouldEvict($entry);

        // Assert
        expect($result)->toBeTrue();
    });
});
