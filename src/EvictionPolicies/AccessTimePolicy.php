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
final readonly class AccessTimePolicy implements EvictionPolicy
{
    public function __construct(
        private int $secondsSinceLastAccess,
    ) {}

    public function shouldEvict(VaultEntry $entry): bool
    {
        if ($entry->last_accessed_at === null) {
            return false;
        }

        return $entry->last_accessed_at->addSeconds($this->secondsSinceLastAccess)->isPast();
    }

    public function getName(): string
    {
        return 'access_time';
    }
}
