<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Vault\Contracts;

interface SecretValue
{
    public static function supports(mixed $value): bool;

    public static function priority(): int;

    public static function unserialize(string $data): static;

    public function set(mixed $value): void;

    public function get(): mixed;

    public function encrypt(mixed $value, string $key): string;

    public function decrypt(string $encrypted, string $key): mixed;

    public function serialize(): string;
}
