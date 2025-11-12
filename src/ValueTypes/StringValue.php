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
use function is_string;
use function openssl_decrypt;
use function openssl_encrypt;
use function random_bytes;
use function substr;
use function throw_if;
use function throw_unless;

final class StringValue implements SecretValue
{
    private string $value;

    public static function supports(mixed $value): bool
    {
        return is_string($value);
    }

    public static function priority(): int
    {
        return 10;
    }

    public static function unserialize(string $data): static
    {
        $instance = new self();
        $instance->set($data);

        return $instance;
    }

    public function set(mixed $value): void
    {
        throw_unless(is_string($value), InvalidArgumentException::class, 'Value must be a string');

        $this->value = $value;
    }

    public function get(): string
    {
        return $this->value;
    }

    public function encrypt(mixed $value, string $key): string
    {
        throw_unless(is_string($value), InvalidArgumentException::class, 'Value must be a string');

        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(
            $value,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        // @codeCoverageIgnoreStart
        throw_if($encrypted === false, RuntimeException::class, 'Encryption failed');

        // @codeCoverageIgnoreEnd

        return base64_encode($iv.$tag.$encrypted);
    }

    public function decrypt(string $encrypted, string $key): string
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

        return $decrypted;
    }

    public function serialize(): string
    {
        return $this->value;
    }
}
