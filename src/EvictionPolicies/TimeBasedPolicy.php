<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Vault\EvictionPolicies;

use Cline\Vault\Contracts\EvictionPolicy;
use Cline\Vault\Models\VaultEntry;

/**
 * @psalm-immutable
 */
final readonly class TimeBasedPolicy implements EvictionPolicy
{
    public function __construct(
        private int $seconds,
    ) {}

    public function shouldEvict(VaultEntry $entry): bool
    {
        return $entry->created_at->addSeconds($this->seconds)->isPast();
    }

    public function getName(): string
    {
        return 'time_based';
    }
}
