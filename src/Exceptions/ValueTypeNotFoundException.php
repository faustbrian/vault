<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Vault\Exceptions;

use RuntimeException;

use function get_debug_type;
use function sprintf;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class ValueTypeNotFoundException extends RuntimeException
{
    public static function forValue(mixed $value): self
    {
        return new self(sprintf(
            'No value type handler found for: %s',
            get_debug_type($value),
        ));
    }
}
