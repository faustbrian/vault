<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Vault\ValueTypes\IntValue;

describe('IntValue', function (): void {
    describe('supports', function (): void {
        test('returns true for integer values', function (): void {
            expect(IntValue::supports(123))->toBeTrue();
            expect(IntValue::supports(0))->toBeTrue();
            expect(IntValue::supports(-456))->toBeTrue();
        });

        test('returns false for non-integer values', function (): void {
            expect(IntValue::supports('123'))->toBeFalse();
            expect(IntValue::supports(12.34))->toBeFalse();
            expect(IntValue::supports([]))->toBeFalse();
            expect(IntValue::supports(null))->toBeFalse();
        });
    });

    describe('priority', function (): void {
        test('returns correct priority', function (): void {
            expect(IntValue::priority())->toBe(20);
        });
    });

    describe('set and get', function (): void {
        test('sets and retrieves integer value', function (): void {
            // Arrange
            $value = new IntValue();
            $testInt = 42;

            // Act
            $value->set($testInt);

            // Assert
            expect($value->get())->toBe($testInt);
        });

        test('handles zero', function (): void {
            // Arrange
            $value = new IntValue();

            // Act
            $value->set(0);

            // Assert
            expect($value->get())->toBe(0);
        });

        test('handles negative integers', function (): void {
            // Arrange
            $value = new IntValue();

            // Act
            $value->set(-999);

            // Assert
            expect($value->get())->toBe(-999);
        });

        test('throws exception for non-integer value', function (): void {
            // Arrange
            $value = new IntValue();

            // Act & Assert
            expect(fn () => $value->set('123'))
                ->toThrow(InvalidArgumentException::class, 'Value must be an integer');
        });
    });

    describe('encrypt and decrypt', function (): void {
        test('encrypts and decrypts integer successfully', function (): void {
            // Arrange
            $value = new IntValue();
            $originalValue = 12_345;
            $key = base64_encode(random_bytes(32));

            // Act
            $encrypted = $value->encrypt($originalValue, $key);
            $decrypted = $value->decrypt($encrypted, $key);

            // Assert
            expect($encrypted)->not()->toBe((string) $originalValue);
            expect($decrypted)->toBe($originalValue);
        });

        test('throws exception when decrypting with wrong key', function (): void {
            // Arrange
            $value = new IntValue();
            $originalValue = 999;
            $correctKey = base64_encode(random_bytes(32));
            $wrongKey = base64_encode(random_bytes(32));

            // Act
            $encrypted = $value->encrypt($originalValue, $correctKey);

            // Assert
            expect(fn (): int => $value->decrypt($encrypted, $wrongKey))
                ->toThrow(RuntimeException::class, 'Decryption failed');
        });

        test('handles zero value', function (): void {
            // Arrange
            $value = new IntValue();
            $originalValue = 0;
            $key = base64_encode(random_bytes(32));

            // Act
            $encrypted = $value->encrypt($originalValue, $key);
            $decrypted = $value->decrypt($encrypted, $key);

            // Assert
            expect($decrypted)->toBe(0);
        });

        test('handles negative integers', function (): void {
            // Arrange
            $value = new IntValue();
            $originalValue = -12_345;
            $key = base64_encode(random_bytes(32));

            // Act
            $encrypted = $value->encrypt($originalValue, $key);
            $decrypted = $value->decrypt($encrypted, $key);

            // Assert
            expect($decrypted)->toBe($originalValue);
        });
    });

    describe('serialize and unserialize', function (): void {
        test('serializes and unserializes integer value', function (): void {
            // Arrange
            $originalValue = 789;
            $value = new IntValue();
            $value->set($originalValue);

            // Act
            $serialized = $value->serialize();
            $unserialized = IntValue::unserialize($serialized);

            // Assert
            expect($unserialized->get())->toBe($originalValue);
        });
    });

    describe('error handling', function (): void {
        test('throws exception when encrypting non-integer value', function (): void {
            // Arrange
            $value = new IntValue();
            $key = base64_encode(random_bytes(32));

            // Act & Assert
            expect(fn (): string => $value->encrypt('not an int', $key))
                ->toThrow(InvalidArgumentException::class, 'Value must be an integer');
        });

        test('throws exception when decrypting invalid base64', function (): void {
            // Arrange
            $value = new IntValue();
            $key = base64_encode(random_bytes(32));

            // Act & Assert
            expect(fn (): int => $value->decrypt('!!!invalid base64!!!', $key))
                ->toThrow(RuntimeException::class, 'Base64 decoding failed');
        });
    });
});
