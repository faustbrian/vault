<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Vault\Contracts\SecretValue;
use Cline\Vault\Exceptions\ValueTypeNotFoundException;
use Cline\Vault\ValueTypeRegistry;
use Cline\Vault\ValueTypes\ArrayValue;
use Cline\Vault\ValueTypes\IntValue;
use Cline\Vault\ValueTypes\JsonValue;
use Cline\Vault\ValueTypes\StringValue;

describe('ValueTypeRegistry', function (): void {
    describe('register', function (): void {
        test('registers a valid value type', function (): void {
            // Arrange
            $registry = new ValueTypeRegistry();

            // Act
            $registry->register(StringValue::class);

            // Assert
            $result = $registry->resolve('test string');
            expect($result)->toBeInstanceOf(StringValue::class);
        });

        test('throws exception for non-existent class', function (): void {
            // Arrange
            $registry = new ValueTypeRegistry();

            // Act & Assert
            expect(fn () => $registry->register('NonExistentClass'))
                ->toThrow(InvalidArgumentException::class, 'Class NonExistentClass does not exist');
        });

        test('throws exception for non-SecretValue class', function (): void {
            // Arrange
            $registry = new ValueTypeRegistry();

            // Act & Assert
            expect(fn () => $registry->register(stdClass::class))
                ->toThrow(InvalidArgumentException::class, 'Type must implement SecretValue interface');
        });

        test('registers multiple types and sorts by priority', function (): void {
            // Arrange
            $registry = new ValueTypeRegistry();

            // Act
            $registry->register(StringValue::class);
            $registry->register(IntValue::class);
            $registry->register(ArrayValue::class);

            // Assert - IntValue has higher priority (20) than StringValue (10)
            $result = $registry->resolve(123);
            expect($result)->toBeInstanceOf(IntValue::class);
        });
    });

    describe('resolve', function (): void {
        test('resolves string value to StringValue', function (): void {
            // Arrange
            $registry = new ValueTypeRegistry();
            $registry->register(StringValue::class);
            $registry->register(IntValue::class);

            // Act
            $result = $registry->resolve('test');

            // Assert
            expect($result)->toBeInstanceOf(StringValue::class);
            expect($result->get())->toBe('test');
        });

        test('resolves integer value to IntValue', function (): void {
            // Arrange
            $registry = new ValueTypeRegistry();
            $registry->register(StringValue::class);
            $registry->register(IntValue::class);

            // Act
            $result = $registry->resolve(42);

            // Assert
            expect($result)->toBeInstanceOf(IntValue::class);
            expect($result->get())->toBe(42);
        });

        test('resolves array value to ArrayValue', function (): void {
            // Arrange
            $registry = new ValueTypeRegistry();
            $registry->register(ArrayValue::class);
            $registry->register(JsonValue::class);

            // Act
            $result = $registry->resolve(['key' => 'value']);

            // Assert
            expect($result)->toBeInstanceOf(ArrayValue::class);
            expect($result->get())->toBe(['key' => 'value']);
        });

        test('resolves object value to JsonValue', function (): void {
            // Arrange
            $registry = new ValueTypeRegistry();
            $registry->register(JsonValue::class);

            // Act
            $result = $registry->resolve((object) ['key' => 'value']);

            // Assert
            expect($result)->toBeInstanceOf(JsonValue::class);
        });

        test('throws exception for unsupported value type', function (): void {
            // Arrange
            $registry = new ValueTypeRegistry();
            $registry->register(StringValue::class);

            // Act & Assert
            expect(fn (): SecretValue => $registry->resolve(fopen('php://memory', 'rb')))
                ->toThrow(ValueTypeNotFoundException::class);
        });

        test('respects priority when multiple types support same value', function (): void {
            // Arrange
            $registry = new ValueTypeRegistry();
            $registry->register(JsonValue::class);
            $registry->register(ArrayValue::class);

            // Act - Both JsonValue and ArrayValue support arrays, but ArrayValue has higher priority (30 vs 25)
            $result = $registry->resolve(['key' => 'value']);

            // Assert
            expect($result)->toBeInstanceOf(ArrayValue::class);
        });

        test('sets value on resolved type', function (): void {
            // Arrange
            $registry = new ValueTypeRegistry();
            $registry->register(StringValue::class);

            $testValue = 'Hello World';

            // Act
            $result = $registry->resolve($testValue);

            // Assert
            expect($result->get())->toBe($testValue);
        });
    });

    describe('findType', function (): void {
        test('finds type class for string value', function (): void {
            // Arrange
            $registry = new ValueTypeRegistry();
            $registry->register(StringValue::class);
            $registry->register(IntValue::class);

            // Act
            $result = $registry->findType('test');

            // Assert
            expect($result)->toBe(StringValue::class);
        });

        test('finds type class for integer value', function (): void {
            // Arrange
            $registry = new ValueTypeRegistry();
            $registry->register(StringValue::class);
            $registry->register(IntValue::class);

            // Act
            $result = $registry->findType(42);

            // Assert
            expect($result)->toBe(IntValue::class);
        });

        test('finds type class for array value', function (): void {
            // Arrange
            $registry = new ValueTypeRegistry();
            $registry->register(ArrayValue::class);

            // Act
            $result = $registry->findType([1, 2, 3]);

            // Assert
            expect($result)->toBe(ArrayValue::class);
        });

        test('throws exception for unsupported value type', function (): void {
            // Arrange
            $registry = new ValueTypeRegistry();
            $registry->register(StringValue::class);

            // Act & Assert
            $resource = fopen('php://memory', 'rb');
            expect(fn (): string => $registry->findType($resource))
                ->toThrow(ValueTypeNotFoundException::class);
            fclose($resource);
        });

        test('respects priority when multiple types support same value', function (): void {
            // Arrange
            $registry = new ValueTypeRegistry();
            $registry->register(JsonValue::class);
            $registry->register(ArrayValue::class);

            // Act - ArrayValue has higher priority (30) than JsonValue (25)
            $result = $registry->findType(['key' => 'value']);

            // Assert
            expect($result)->toBe(ArrayValue::class);
        });
    });

    describe('priority ordering', function (): void {
        test('higher priority types are checked first', function (): void {
            // Arrange
            $registry = new ValueTypeRegistry();

            // Act - Register in random order
            $registry->register(StringValue::class);
            $registry->register(ArrayValue::class);
            $registry->register(JsonValue::class);
            $registry->register(IntValue::class);

            // Assert - IntValue (20) > StringValue (10)
            expect($registry->findType(123))->toBe(IntValue::class);

            // Assert - ArrayValue (30) > JsonValue (25)
            expect($registry->findType(['test']))->toBe(ArrayValue::class);
        });
    });
});
