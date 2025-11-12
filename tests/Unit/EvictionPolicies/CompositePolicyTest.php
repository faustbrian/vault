<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Vault\EvictionPolicies\AccessCountPolicy;
use Cline\Vault\EvictionPolicies\AccessTimePolicy;
use Cline\Vault\EvictionPolicies\CompositePolicy;
use Cline\Vault\EvictionPolicies\TimeBasedPolicy;
use Cline\Vault\Models\VaultEntry;
use Illuminate\Support\Facades\Date;

describe('CompositePolicy', function (): void {
    describe('OR operator', function (): void {
        test('getName returns composite name with OR', function (): void {
            // Arrange
            $policies = [
                new TimeBasedPolicy(60),
                new AccessCountPolicy(5),
            ];
            $policy = new CompositePolicy($policies, 'OR');

            // Act
            $name = $policy->getName();

            // Assert
            expect($name)->toBe('composite_or_time_based_access_count');
        });

        test('shouldEvict returns true when first policy matches', function (): void {
            // Arrange
            $policies = [
                new TimeBasedPolicy(60),
                new AccessCountPolicy(5),
            ];
            $policy = new CompositePolicy($policies, 'OR');

            $entry = new VaultEntry();
            $entry->created_at = Date::now()->subSeconds(61);
            $entry->access_count = 2;

            // Act
            $result = $policy->shouldEvict($entry);

            // Assert
            expect($result)->toBeTrue();
        });

        test('shouldEvict returns true when second policy matches', function (): void {
            // Arrange
            $policies = [
                new TimeBasedPolicy(60),
                new AccessCountPolicy(5),
            ];
            $policy = new CompositePolicy($policies, 'OR');

            $entry = new VaultEntry();
            $entry->created_at = Date::now();
            $entry->access_count = 5;

            // Act
            $result = $policy->shouldEvict($entry);

            // Assert
            expect($result)->toBeTrue();
        });

        test('shouldEvict returns true when all policies match', function (): void {
            // Arrange
            $policies = [
                new TimeBasedPolicy(60),
                new AccessCountPolicy(5),
            ];
            $policy = new CompositePolicy($policies, 'OR');

            $entry = new VaultEntry();
            $entry->created_at = Date::now()->subSeconds(61);
            $entry->access_count = 5;

            // Act
            $result = $policy->shouldEvict($entry);

            // Assert
            expect($result)->toBeTrue();
        });

        test('shouldEvict returns false when no policies match', function (): void {
            // Arrange
            $policies = [
                new TimeBasedPolicy(60),
                new AccessCountPolicy(5),
            ];
            $policy = new CompositePolicy($policies, 'OR');

            $entry = new VaultEntry();
            $entry->created_at = Date::now();
            $entry->access_count = 2;

            // Act
            $result = $policy->shouldEvict($entry);

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('AND operator', function (): void {
        test('getName returns composite name with AND', function (): void {
            // Arrange
            $policies = [
                new TimeBasedPolicy(60),
                new AccessCountPolicy(5),
            ];
            $policy = new CompositePolicy($policies, 'AND');

            // Act
            $name = $policy->getName();

            // Assert
            expect($name)->toBe('composite_and_time_based_access_count');
        });

        test('shouldEvict returns false when only first policy matches', function (): void {
            // Arrange
            $policies = [
                new TimeBasedPolicy(60),
                new AccessCountPolicy(5),
            ];
            $policy = new CompositePolicy($policies, 'AND');

            $entry = new VaultEntry();
            $entry->created_at = Date::now()->subSeconds(61);
            $entry->access_count = 2;

            // Act
            $result = $policy->shouldEvict($entry);

            // Assert
            expect($result)->toBeFalse();
        });

        test('shouldEvict returns false when only second policy matches', function (): void {
            // Arrange
            $policies = [
                new TimeBasedPolicy(60),
                new AccessCountPolicy(5),
            ];
            $policy = new CompositePolicy($policies, 'AND');

            $entry = new VaultEntry();
            $entry->created_at = Date::now();
            $entry->access_count = 5;

            // Act
            $result = $policy->shouldEvict($entry);

            // Assert
            expect($result)->toBeFalse();
        });

        test('shouldEvict returns true when all policies match', function (): void {
            // Arrange
            $policies = [
                new TimeBasedPolicy(60),
                new AccessCountPolicy(5),
            ];
            $policy = new CompositePolicy($policies, 'AND');

            $entry = new VaultEntry();
            $entry->created_at = Date::now()->subSeconds(61);
            $entry->access_count = 5;

            // Act
            $result = $policy->shouldEvict($entry);

            // Assert
            expect($result)->toBeTrue();
        });

        test('shouldEvict returns false when no policies match', function (): void {
            // Arrange
            $policies = [
                new TimeBasedPolicy(60),
                new AccessCountPolicy(5),
            ];
            $policy = new CompositePolicy($policies, 'AND');

            $entry = new VaultEntry();
            $entry->created_at = Date::now();
            $entry->access_count = 2;

            // Act
            $result = $policy->shouldEvict($entry);

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('complex scenarios', function (): void {
        test('handles three policies with OR operator', function (): void {
            // Arrange
            $policies = [
                new TimeBasedPolicy(60),
                new AccessCountPolicy(5),
                new AccessTimePolicy(30),
            ];
            $policy = new CompositePolicy($policies, 'OR');

            $entry = new VaultEntry();
            $entry->created_at = Date::now();
            $entry->access_count = 2;
            $entry->last_accessed_at = Date::now()->subSeconds(31);

            // Act
            $result = $policy->shouldEvict($entry);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles three policies with AND operator', function (): void {
            // Arrange
            $policies = [
                new TimeBasedPolicy(60),
                new AccessCountPolicy(5),
                new AccessTimePolicy(30),
            ];
            $policy = new CompositePolicy($policies, 'AND');

            $entry = new VaultEntry();
            $entry->created_at = Date::now()->subSeconds(61);
            $entry->access_count = 5;
            $entry->last_accessed_at = Date::now()->subSeconds(31);

            // Act
            $result = $policy->shouldEvict($entry);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles empty policy array with OR', function (): void {
            // Arrange
            $policy = new CompositePolicy([], 'OR');
            $entry = new VaultEntry();

            // Act
            $result = $policy->shouldEvict($entry);

            // Assert
            expect($result)->toBeFalse();
        });

        test('handles empty policy array with AND', function (): void {
            // Arrange
            $policy = new CompositePolicy([], 'AND');
            $entry = new VaultEntry();

            // Act
            $result = $policy->shouldEvict($entry);

            // Assert
            expect($result)->toBeTrue();
        });
    });
});
