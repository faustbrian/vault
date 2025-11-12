<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Vault\Exceptions;

use RuntimeException;

use function sprintf;

final class DecryptionFailedException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self(sprintf(
            'Failed to decrypt value for key: %s',
            $key,
        ));
    }

    public static function withMessage(string $message): self
    {
        return new self($message);
    }
}
