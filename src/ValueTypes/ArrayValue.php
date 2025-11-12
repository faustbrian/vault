<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Vault\ValueTypes;

use Cline\Vault\Contracts\SecretValue;
use InvalidArgumentException;
use RuntimeException;

use const OPENSSL_RAW_DATA;

use function base64_decode;
use function base64_encode;
use function is_array;
use function openssl_decrypt;
use function openssl_encrypt;
use function random_bytes;
use function serialize;
use function substr;
use function throw_if;
use function throw_unless;
use function unserialize;

final class ArrayValue implements SecretValue
{
    /** @var array<array-key, mixed> */
    private array $value;

    public static function supports(mixed $value): bool
    {
        return is_array($value);
    }

    public static function priority(): int
    {
        return 30;
    }

    public static function unserialize(string $data): static
    {
        $instance = new self();
        $instance->set(unserialize($data));

        return $instance;
    }

    public function set(mixed $value): void
    {
        throw_unless(is_array($value), InvalidArgumentException::class, 'Value must be an array');

        $this->value = $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function get(): array
    {
        return $this->value;
    }

    public function encrypt(mixed $value, string $key): string
    {
        $serialized = serialize($value);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(
            $serialized,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        return base64_encode($iv.$tag.$encrypted);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function decrypt(string $encrypted, string $key): array
    {
        $data = base64_decode($encrypted, true);

        throw_if($data === false, RuntimeException::class, 'Base64 decoding failed');

        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $ciphertext = substr($data, 32);

        $decrypted = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        throw_if($decrypted === false, RuntimeException::class, 'Decryption failed');

        /** @var array<array-key, mixed> */
        return unserialize($decrypted);
    }

    public function serialize(): string
    {
        return serialize($this->value);
    }
}
