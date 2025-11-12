<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Vault\ValueTypes;

use Cline\Vault\Contracts\SecretValue;
use RuntimeException;

use const OPENSSL_RAW_DATA;

use function base64_decode;
use function base64_encode;
use function is_array;
use function is_object;
use function json_decode;
use function json_encode;
use function openssl_decrypt;
use function openssl_encrypt;
use function random_bytes;
use function substr;
use function throw_if;

final class JsonValue implements SecretValue
{
    private mixed $value;

    public static function supports(mixed $value): bool
    {
        return is_object($value) || (is_array($value) && json_encode($value) !== false);
    }

    public static function priority(): int
    {
        return 25;
    }

    public static function unserialize(string $data): static
    {
        $instance = new self();
        $instance->set(json_decode($data, true));

        return $instance;
    }

    public function set(mixed $value): void
    {
        $this->value = $value;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function encrypt(mixed $value, string $key): string
    {
        $json = json_encode($value);

        throw_if($json === false, RuntimeException::class, 'Failed to encode value as JSON');

        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(
            $json,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        // @codeCoverageIgnoreStart
        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed');
            // @codeCoverageIgnoreEnd
        }

        return base64_encode($iv.$tag.$encrypted);
    }

    public function decrypt(string $encrypted, string $key): mixed
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

        return json_decode($decrypted, true);
    }

    public function serialize(): string
    {
        $result = json_encode($this->value);

        // @codeCoverageIgnoreStart
        throw_if($result === false, RuntimeException::class, 'Failed to encode value as JSON');

        // @codeCoverageIgnoreEnd

        return $result;
    }
}
