<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Vault\EvictionPolicies\AccessTimePolicy;
use Cline\Vault\Models\VaultEntry;
use Illuminate\Support\Facades\Date;

describe('AccessTimePolicy', function (): void {
    test('getName returns correct policy name', function (): void {
        // Arrange
        $policy = new AccessTimePolicy(60);

        // Act
        $name = $policy->getName();

        // Assert
        expect($name)->toBe('access_time');
    });

    test('shouldEvict returns false when never accessed', function (): void {
        // Arrange
        $policy = new AccessTimePolicy(60);
        $entry = new VaultEntry();
        $entry->last_accessed_at = null;

        // Act
        $result = $policy->shouldEvict($entry);

        // Assert
        expect($result)->toBeFalse();
    });

    test('shouldEvict returns false for recently accessed entry', function (): void {
        // Arrange
        $policy = new AccessTimePolicy(3_600);
        $entry = new VaultEntry();
        $entry->last_accessed_at = Date::now();

        // Act
        $result = $policy->shouldEvict($entry);

        // Assert
        expect($result)->toBeFalse();
    });

    test('shouldEvict returns true for stale entry', function (): void {
        // Arrange
        $policy = new AccessTimePolicy(60);
        $entry = new VaultEntry();
        $entry->last_accessed_at = Date::now()->subSeconds(61);

        // Act
        $result = $policy->shouldEvict($entry);

        // Assert
        expect($result)->toBeTrue();
    });

    test('shouldEvict returns true for entry at exact boundary', function (): void {
        // Arrange
        $policy = new AccessTimePolicy(60);
        $entry = new VaultEntry();
        $entry->last_accessed_at = Date::now()->subSeconds(60);

        // Act
        $result = $policy->shouldEvict($entry);

        // Assert
        expect($result)->toBeTrue();
    });

    test('shouldEvict handles zero seconds policy with null access time', function (): void {
        // Arrange
        $policy = new AccessTimePolicy(0);
        $entry = new VaultEntry();
        $entry->last_accessed_at = null;

        // Act
        $result = $policy->shouldEvict($entry);

        // Assert
        expect($result)->toBeFalse();
    });

    test('shouldEvict handles long idle periods', function (): void {
        // Arrange
        $policy = new AccessTimePolicy(3_600);
        $entry = new VaultEntry();
        $entry->last_accessed_at = Date::now()->subDays(1);

        // Act
        $result = $policy->shouldEvict($entry);

        // Assert
        expect($result)->toBeTrue();
    });
});
