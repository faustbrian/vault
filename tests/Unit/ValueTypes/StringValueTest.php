<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Vault\ValueTypes\StringValue;

describe('StringValue', function (): void {
    describe('supports', function (): void {
        test('returns true for string values', function (): void {
            expect(StringValue::supports('test'))->toBeTrue();
            expect(StringValue::supports(''))->toBeTrue();
            expect(StringValue::supports('12345'))->toBeTrue();
        });

        test('returns false for non-string values', function (): void {
            expect(StringValue::supports(123))->toBeFalse();
            expect(StringValue::supports([]))->toBeFalse();
            expect(StringValue::supports(null))->toBeFalse();
            expect(StringValue::supports(true))->toBeFalse();
        });
    });

    describe('priority', function (): void {
        test('returns correct priority', function (): void {
            expect(StringValue::priority())->toBe(10);
        });
    });

    describe('set and get', function (): void {
        test('sets and retrieves string value', function (): void {
            // Arrange
            $value = new StringValue();
            $testString = 'Hello World';

            // Act
            $value->set($testString);

            // Assert
            expect($value->get())->toBe($testString);
        });

        test('throws exception for non-string value', function (): void {
            // Arrange
            $value = new StringValue();

            // Act & Assert
            expect(fn () => $value->set(123))
                ->toThrow(InvalidArgumentException::class, 'Value must be a string');
        });
    });

    describe('encrypt and decrypt', function (): void {
        test('encrypts and decrypts string successfully', function (): void {
            // Arrange
            $value = new StringValue();
            $originalValue = 'Secret Message';
            $key = base64_encode(random_bytes(32));

            // Act
            $encrypted = $value->encrypt($originalValue, $key);
            $decrypted = $value->decrypt($encrypted, $key);

            // Assert
            expect($encrypted)->not()->toBe($originalValue);
            expect($decrypted)->toBe($originalValue);
        });

        test('throws exception when decrypting with wrong key', function (): void {
            // Arrange
            $value = new StringValue();
            $originalValue = 'Secret Message';
            $correctKey = base64_encode(random_bytes(32));
            $wrongKey = base64_encode(random_bytes(32));

            // Act
            $encrypted = $value->encrypt($originalValue, $correctKey);

            // Assert
            expect(fn (): string => $value->decrypt($encrypted, $wrongKey))
                ->toThrow(RuntimeException::class, 'Decryption failed');
        });

        test('handles empty string', function (): void {
            // Arrange
            $value = new StringValue();
            $originalValue = '';
            $key = base64_encode(random_bytes(32));

            // Act
            $encrypted = $value->encrypt($originalValue, $key);
            $decrypted = $value->decrypt($encrypted, $key);

            // Assert
            expect($decrypted)->toBe('');
        });

        test('handles unicode characters', function (): void {
            // Arrange
            $value = new StringValue();
            $originalValue = '你好世界 🌍';
            $key = base64_encode(random_bytes(32));

            // Act
            $encrypted = $value->encrypt($originalValue, $key);
            $decrypted = $value->decrypt($encrypted, $key);

            // Assert
            expect($decrypted)->toBe($originalValue);
        });
    });

    describe('serialize and unserialize', function (): void {
        test('serializes and unserializes string value', function (): void {
            // Arrange
            $originalValue = 'Test Value';
            $value = new StringValue();
            $value->set($originalValue);

            // Act
            $serialized = $value->serialize();
            $unserialized = StringValue::unserialize($serialized);

            // Assert
            expect($unserialized->get())->toBe($originalValue);
        });
    });

    describe('error handling', function (): void {
        test('throws exception when encrypting non-string value', function (): void {
            // Arrange
            $value = new StringValue();
            $key = base64_encode(random_bytes(32));

            // Act & Assert
            expect(fn (): string => $value->encrypt(123, $key))
                ->toThrow(InvalidArgumentException::class, 'Value must be a string');
        });

        test('throws exception when decrypting invalid base64', function (): void {
            // Arrange
            $value = new StringValue();
            $key = base64_encode(random_bytes(32));

            // Act & Assert
            expect(fn (): string => $value->decrypt('!!!invalid base64!!!', $key))
                ->toThrow(RuntimeException::class, 'Base64 decoding failed');
        });
    });
});
