<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Vault\ValueTypes\ArrayValue;

describe('ArrayValue', function (): void {
    describe('supports', function (): void {
        test('returns true for array values', function (): void {
            expect(ArrayValue::supports([]))->toBeTrue();
            expect(ArrayValue::supports([1, 2, 3]))->toBeTrue();
            expect(ArrayValue::supports(['key' => 'value']))->toBeTrue();
        });

        test('returns false for non-array values', function (): void {
            expect(ArrayValue::supports('test'))->toBeFalse();
            expect(ArrayValue::supports(123))->toBeFalse();
            expect(ArrayValue::supports(null))->toBeFalse();
            expect(ArrayValue::supports((object) []))->toBeFalse();
        });
    });

    describe('priority', function (): void {
        test('returns correct priority', function (): void {
            expect(ArrayValue::priority())->toBe(30);
        });
    });

    describe('set and get', function (): void {
        test('sets and retrieves array value', function (): void {
            // Arrange
            $value = new ArrayValue();
            $testArray = ['foo' => 'bar', 'baz' => 123];

            // Act
            $value->set($testArray);

            // Assert
            expect($value->get())->toBe($testArray);
        });

        test('handles empty array', function (): void {
            // Arrange
            $value = new ArrayValue();

            // Act
            $value->set([]);

            // Assert
            expect($value->get())->toBe([]);
        });

        test('handles nested arrays', function (): void {
            // Arrange
            $value = new ArrayValue();
            $nested = [
                'level1' => [
                    'level2' => [
                        'level3' => 'value',
                    ],
                ],
            ];

            // Act
            $value->set($nested);

            // Assert
            expect($value->get())->toBe($nested);
        });

        test('throws exception for non-array value', function (): void {
            // Arrange
            $value = new ArrayValue();

            // Act & Assert
            expect(fn () => $value->set('not an array'))
                ->toThrow(InvalidArgumentException::class, 'Value must be an array');
        });
    });

    describe('encrypt and decrypt', function (): void {
        test('encrypts and decrypts array successfully', function (): void {
            // Arrange
            $value = new ArrayValue();
            $originalValue = ['secret' => 'data', 'count' => 42];
            $key = base64_encode(random_bytes(32));

            // Act
            $encrypted = $value->encrypt($originalValue, $key);
            $decrypted = $value->decrypt($encrypted, $key);

            // Assert
            expect($encrypted)->not()->toBe(serialize($originalValue));
            expect($decrypted)->toBe($originalValue);
        });

        test('throws exception when decrypting with wrong key', function (): void {
            // Arrange
            $value = new ArrayValue();
            $originalValue = ['test' => 'value'];
            $correctKey = base64_encode(random_bytes(32));
            $wrongKey = base64_encode(random_bytes(32));

            // Act
            $encrypted = $value->encrypt($originalValue, $correctKey);

            // Assert
            expect(fn (): array => $value->decrypt($encrypted, $wrongKey))
                ->toThrow(RuntimeException::class, 'Decryption failed');
        });

        test('handles empty array', function (): void {
            // Arrange
            $value = new ArrayValue();
            $originalValue = [];
            $key = base64_encode(random_bytes(32));

            // Act
            $encrypted = $value->encrypt($originalValue, $key);
            $decrypted = $value->decrypt($encrypted, $key);

            // Assert
            expect($decrypted)->toBe([]);
        });

        test('handles complex nested arrays', function (): void {
            // Arrange
            $value = new ArrayValue();
            $originalValue = [
                'users' => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                ],
                'meta' => ['count' => 2],
            ];
            $key = base64_encode(random_bytes(32));

            // Act
            $encrypted = $value->encrypt($originalValue, $key);
            $decrypted = $value->decrypt($encrypted, $key);

            // Assert
            expect($decrypted)->toBe($originalValue);
        });
    });

    describe('serialize and unserialize', function (): void {
        test('serializes and unserializes array value', function (): void {
            // Arrange
            $originalValue = ['test' => 'data', 'number' => 123];
            $value = new ArrayValue();
            $value->set($originalValue);

            // Act
            $serialized = $value->serialize();
            $unserialized = ArrayValue::unserialize($serialized);

            // Assert
            expect($unserialized->get())->toBe($originalValue);
        });
    });

    describe('error handling', function (): void {
        test('throws exception when decrypting invalid base64', function (): void {
            // Arrange
            $value = new ArrayValue();
            $key = base64_encode(random_bytes(32));

            // Act & Assert
            expect(fn (): array => $value->decrypt('!!!invalid base64!!!', $key))
                ->toThrow(RuntimeException::class, 'Base64 decoding failed');
        });
    });
});
