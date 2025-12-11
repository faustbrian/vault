<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Vault\Models\VaultEntry;
use Cline\Vault\ValueTypes\ArrayValue;
use Cline\Vault\ValueTypes\IntValue;
use Cline\Vault\ValueTypes\JsonValue;
use Cline\Vault\ValueTypes\StringValue;
use Cline\Vault\Vault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\User;

uses(RefreshDatabase::class);

describe('Vault Mixed Value Types', function (): void {
    test('handles string values correctly', function (): void {
        // Arrange
        $vault = resolve(Vault::class);
        $values = [
            'simple' => 'Hello',
            'empty' => '',
            'unicode' => '你好世界 🌍',
            'long' => str_repeat('Lorem ipsum ', 100),
        ];

        // Act
        foreach ($values as $key => $value) {
            $vault->put($key, $value);
        }

        // Assert
        foreach ($values as $key => $value) {
            expect($vault->get($key))->toBe($value);
            $entry = VaultEntry::query()->where('key', $key)->first();
            expect($entry->value_type)->toBe(StringValue::class);
        }
    });

    test('handles integer values correctly', function (): void {
        // Arrange
        $vault = resolve(Vault::class);
        $values = [
            'zero' => 0,
            'positive' => 42,
            'negative' => -999,
            'large' => \PHP_INT_MAX,
        ];

        // Act
        foreach ($values as $key => $value) {
            $vault->put($key, $value);
        }

        // Assert
        foreach ($values as $key => $value) {
            expect($vault->get($key))->toBe($value);
            $entry = VaultEntry::query()->where('key', $key)->first();
            expect($entry->value_type)->toBe(IntValue::class);
        }
    });

    test('handles array values correctly', function (): void {
        // Arrange
        $vault = resolve(Vault::class);
        $values = [
            'empty' => [],
            'indexed' => [1, 2, 3, 4, 5],
            'assoc' => ['name' => 'John', 'age' => 30],
            'nested' => [
                'users' => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                ],
                'meta' => ['count' => 2],
            ],
            'mixed' => [1, 'two', ['three' => 3]],
        ];

        // Act
        foreach ($values as $key => $value) {
            $vault->put($key, $value);
        }

        // Assert
        foreach ($values as $key => $value) {
            expect($vault->get($key))->toBe($value);
            $entry = VaultEntry::query()->where('key', $key)->first();
            expect($entry->value_type)->toBe(ArrayValue::class);
        }
    });

    test('handles object values as JSON', function (): void {
        // Arrange
        $vault = resolve(Vault::class);
        $values = [
            'simple' => (object) ['key' => 'value'],
            'complex' => (object) [
                'user' => (object) ['name' => 'Alice', 'email' => 'alice@example.com'],
                'settings' => (object) ['theme' => 'dark'],
            ],
        ];

        // Act
        foreach ($values as $key => $value) {
            $vault->put($key, $value);
        }

        // Assert
        foreach ($values as $key => $value) {
            $retrieved = $vault->get($key);
            expect($retrieved)->toEqual(json_decode(json_encode($value), true));
            $entry = VaultEntry::query()->where('key', $key)->first();
            expect($entry->value_type)->toBe(JsonValue::class);
        }
    });

    test('maintains type integrity across multiple operations', function (): void {
        // Arrange
        $vault = resolve(Vault::class);

        // Act - Store different types
        $vault->put('str', 'test');
        $vault->put('int', 123);
        $vault->put('arr', ['a' => 1]);

        // Act - Retrieve multiple times
        for ($i = 0; $i < 3; ++$i) {
            $str = $vault->get('str');
            $int = $vault->get('int');
            $arr = $vault->get('arr');

            // Assert - Types preserved
            expect($str)->toBe('test');
            expect($int)->toBe(123);
            expect($arr)->toBe(['a' => 1]);
        }
    });

    test('handles type updates correctly', function (): void {
        // Arrange
        $vault = resolve(Vault::class);

        // Act - Store as string
        $vault->put('dynamic', 'original');

        expect($vault->get('dynamic'))->toBe('original');

        // Act - Update to integer
        $vault->put('dynamic', 42);
        expect($vault->get('dynamic'))->toBe(42);

        // Act - Update to array
        $vault->put('dynamic', ['key' => 'value']);
        expect($vault->get('dynamic'))->toBe(['key' => 'value']);

        // Assert - Type changed in database
        $entry = VaultEntry::query()->where('key', 'dynamic')->first();
        expect($entry->value_type)->toBe(ArrayValue::class);
    });

    test('all types can coexist in vault', function (): void {
        // Arrange
        $vault = resolve(Vault::class);
        $user = User::query()->create(['name' => 'John', 'email' => 'john@example.com']);

        // Act - Store all types with different owners
        $vault->put('string', 'value');
        $vault->put('int', 100, $user);
        $vault->put('array', [1, 2, 3]);
        $vault->put('json', (object) ['test' => true], $user);

        // Assert - All retrievable
        expect($vault->get('string'))->toBe('value');
        expect($vault->get('int', $user))->toBe(100);
        expect($vault->get('array'))->toBe([1, 2, 3]);
        expect($vault->get('json', $user))->toBe(['test' => true]);

        // Assert - Correct type handlers
        $entries = VaultEntry::all();
        $types = $entries->pluck('value_type')->unique()->values()->toArray();
        expect($types)->toContain(StringValue::class);
        expect($types)->toContain(IntValue::class);
        expect($types)->toContain(ArrayValue::class);
        expect($types)->toContain(JsonValue::class);
    });

    test('encrypts different types with same key', function (): void {
        // Arrange
        $vault = resolve(Vault::class);

        // Act
        $vault->put('str', 'secret');
        $vault->put('int', 42);
        $vault->put('arr', ['secret' => 'data']);

        // Assert - All encrypted differently despite same encryption key
        $entries = VaultEntry::query()->whereIn('key', ['str', 'int', 'arr'])->get();
        $encryptedValues = $entries->pluck('encrypted_value')->unique();

        expect($encryptedValues->count())->toBe(3);

        foreach ($entries as $entry) {
            expect($entry->encrypted_value)->not()->toContain('secret');
            expect($entry->encrypted_value)->not()->toContain('42');
            expect($entry->encrypted_value)->not()->toContain('data');
        }
    });

    test('handles edge case values', function (): void {
        // Arrange
        $vault = resolve(Vault::class);

        // Act - Edge cases
        $vault->put('empty_string', '');
        $vault->put('zero', 0);
        $vault->put('empty_array', []);
        $vault->put('null_containing', ['null' => null]);

        // Assert
        expect($vault->get('empty_string'))->toBe('');
        expect($vault->get('zero'))->toBe(0);
        expect($vault->get('empty_array'))->toBe([]);
        expect($vault->get('null_containing'))->toBe(['null' => null]);
    });

    test('large value storage and retrieval', function (): void {
        // Arrange
        $vault = resolve(Vault::class);

        // Act - Large values
        $largeString = str_repeat('A', 10_000);
        $largeArray = array_fill(0, 1_000, 'value');

        $vault->put('large_string', $largeString);
        $vault->put('large_array', $largeArray);

        // Assert
        expect($vault->get('large_string'))->toBe($largeString);
        expect($vault->get('large_array'))->toBe($largeArray);
    });

    test('special characters in string values', function (): void {
        // Arrange
        $vault = resolve(Vault::class);
        $specialStrings = [
            'quotes' => "It's a \"test\"",
            'newlines' => "Line 1\nLine 2\r\nLine 3",
            'tabs' => "Column1\tColumn2\tColumn3",
            'emoji' => '😀🎉🔥💯',
            'html' => '<script>alert("xss")</script>',
            'sql' => "'; DROP TABLE users; --",
        ];

        // Act
        foreach ($specialStrings as $key => $value) {
            $vault->put($key, $value);
        }

        // Assert
        foreach ($specialStrings as $key => $value) {
            expect($vault->get($key))->toBe($value);
        }
    });
});
