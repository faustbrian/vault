<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Vault\EvictionPolicies\AccessCountPolicy;
use Cline\Vault\Models\VaultEntry;

describe('AccessCountPolicy', function (): void {
    test('getName returns correct policy name', function (): void {
        // Arrange
        $policy = new AccessCountPolicy(5);

        // Act
        $name = $policy->getName();

        // Assert
        expect($name)->toBe('access_count');
    });

    test('shouldEvict returns false when access count below threshold', function (): void {
        // Arrange
        $policy = new AccessCountPolicy(5);
        $entry = new VaultEntry();
        $entry->access_count = 3;

        // Act
        $result = $policy->shouldEvict($entry);

        // Assert
        expect($result)->toBeFalse();
    });

    test('shouldEvict returns true when access count equals threshold', function (): void {
        // Arrange
        $policy = new AccessCountPolicy(5);
        $entry = new VaultEntry();
        $entry->access_count = 5;

        // Act
        $result = $policy->shouldEvict($entry);

        // Assert
        expect($result)->toBeTrue();
    });

    test('shouldEvict returns true when access count exceeds threshold', function (): void {
        // Arrange
        $policy = new AccessCountPolicy(5);
        $entry = new VaultEntry();
        $entry->access_count = 10;

        // Act
        $result = $policy->shouldEvict($entry);

        // Assert
        expect($result)->toBeTrue();
    });

    test('shouldEvict handles zero access count', function (): void {
        // Arrange
        $policy = new AccessCountPolicy(5);
        $entry = new VaultEntry();
        $entry->access_count = 0;

        // Act
        $result = $policy->shouldEvict($entry);

        // Assert
        expect($result)->toBeFalse();
    });

    test('shouldEvict handles threshold of 1', function (): void {
        // Arrange
        $policy = new AccessCountPolicy(1);
        $entry = new VaultEntry();
        $entry->access_count = 0;

        // Act
        $result = $policy->shouldEvict($entry);

        // Assert
        expect($result)->toBeFalse();

        // Act - after one access
        $entry->access_count = 1;
        $result = $policy->shouldEvict($entry);

        // Assert
        expect($result)->toBeTrue();
    });
});
