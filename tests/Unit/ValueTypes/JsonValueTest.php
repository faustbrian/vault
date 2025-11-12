<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Vault\ValueTypes\JsonValue;

describe('JsonValue', function (): void {
    describe('supports', function (): void {
        test('returns true for objects', function (): void {
            expect(JsonValue::supports((object) ['key' => 'value']))->toBeTrue();
            expect(JsonValue::supports(
                new stdClass(),
            ))->toBeTrue();
        });

        test('returns true for arrays that can be JSON encoded', function (): void {
            expect(JsonValue::supports(['key' => 'value']))->toBeTrue();
            expect(JsonValue::supports([1, 2, 3]))->toBeTrue();
        });

        test('returns false for values that cannot be JSON encoded', function (): void {
            expect(JsonValue::supports('string'))->toBeFalse();
            expect(JsonValue::supports(123))->toBeFalse();
        });
    });

    describe('priority', function (): void {
        test('returns correct priority', function (): void {
            expect(JsonValue::priority())->toBe(25);
        });
    });

    describe('set and get', function (): void {
        test('sets and retrieves object value', function (): void {
            // Arrange
            $value = new JsonValue();
            $testObject = (object) ['foo' => 'bar', 'baz' => 123];

            // Act
            $value->set($testObject);

            // Assert
            expect($value->get())->toBe($testObject);
        });

        test('sets and retrieves array value', function (): void {
            // Arrange
            $value = new JsonValue();
            $testArray = ['key' => 'value', 'number' => 42];

            // Act
            $value->set($testArray);

            // Assert
            expect($value->get())->toBe($testArray);
        });
    });

    describe('encrypt and decrypt', function (): void {
        test('encrypts and decrypts object successfully', function (): void {
            // Arrange
            $value = new JsonValue();
            $originalValue = (object) ['secret' => 'data'];
            $key = base64_encode(random_bytes(32));

            // Act
            $encrypted = $value->encrypt($originalValue, $key);
            $decrypted = $value->decrypt($encrypted, $key);

            // Assert
            expect($encrypted)->not()->toBe(json_encode($originalValue));
            expect($decrypted)->toEqual(['secret' => 'data']);
        });

        test('encrypts and decrypts array successfully', function (): void {
            // Arrange
            $value = new JsonValue();
            $originalValue = ['users' => [['name' => 'Alice'], ['name' => 'Bob']]];
            $key = base64_encode(random_bytes(32));

            // Act
            $encrypted = $value->encrypt($originalValue, $key);
            $decrypted = $value->decrypt($encrypted, $key);

            // Assert
            expect($decrypted)->toBe($originalValue);
        });

        test('throws exception when decrypting with wrong key', function (): void {
            // Arrange
            $value = new JsonValue();
            $originalValue = ['test' => 'value'];
            $correctKey = base64_encode(random_bytes(32));
            $wrongKey = base64_encode(random_bytes(32));

            // Act
            $encrypted = $value->encrypt($originalValue, $correctKey);

            // Assert
            expect(fn (): mixed => $value->decrypt($encrypted, $wrongKey))
                ->toThrow(RuntimeException::class, 'Decryption failed');
        });

        test('throws exception for non-JSON-encodable value', function (): void {
            // Arrange
            $value = new JsonValue();
            $resource = fopen('php://memory', 'rb');
            $key = base64_encode(random_bytes(32));

            // Act & Assert
            expect(fn (): string => $value->encrypt($resource, $key))
                ->toThrow(RuntimeException::class, 'Failed to encode value as JSON');

            fclose($resource);
        });

        test('handles unicode in JSON', function (): void {
            // Arrange
            $value = new JsonValue();
            $originalValue = ['message' => '你好世界 🌍'];
            $key = base64_encode(random_bytes(32));

            // Act
            $encrypted = $value->encrypt($originalValue, $key);
            $decrypted = $value->decrypt($encrypted, $key);

            // Assert
            expect($decrypted)->toBe($originalValue);
        });
    });

    describe('serialize and unserialize', function (): void {
        test('serializes and unserializes JSON value', function (): void {
            // Arrange
            $originalValue = ['test' => 'data', 'number' => 123];
            $value = new JsonValue();
            $value->set($originalValue);

            // Act
            $serialized = $value->serialize();
            $unserialized = JsonValue::unserialize($serialized);

            // Assert
            expect($unserialized->get())->toBe($originalValue);
        });
    });

    describe('error handling', function (): void {
        test('throws exception when decrypting invalid base64', function (): void {
            // Arrange
            $value = new JsonValue();
            $key = base64_encode(random_bytes(32));

            // Act & Assert
            expect(fn (): mixed => $value->decrypt('!!!invalid base64!!!', $key))
                ->toThrow(RuntimeException::class, 'Base64 decoding failed');
        });
    });
});
